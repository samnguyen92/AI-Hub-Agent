"""Agent Runner orchestrating the ReAct execution loop."""

import json
import os
import time
from typing import Any, Callable, Dict, List, Optional, Tuple
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Agent, Message, ProjectNote, Run, Step, Thread
from app.services.openrouter import openrouter_client
from app.services.workspace import workspace_service
from app.standards.writing import get_writing_system_prompt
from app.tools.base import ToolContext, tool_registry


class AgentRunner:
    """Orchestrates single-agent execution runs with limits and tool calls."""

    def __init__(self, max_steps: int = 15, max_cost_usd: float = 1.0) -> None:
        self.max_steps = max_steps
        self.max_cost_usd = max_cost_usd

    def _build_system_prompt(
        self,
        agent_key: str,
        workspace_path: str,
        project_id: str = "",
        notes: Optional[List[str]] = None,
    ) -> str:
        """Construct the system prompt for the specified agent."""
        rules_text = ""
        candidate_paths = [f"/app/agents/{agent_key}/rules.md", f"agents/{agent_key}/rules.md"]
        for p in candidate_paths:
            if os.path.exists(p):
                try:
                    with open(p, "r", encoding="utf-8") as f:
                        rules_text = f.read()
                    break
                except Exception:
                    pass

        prompt_parts = [
            f"You are Agent '{agent_key}' from Align AI Hub.",
            "Mission: Assist with website design, coding, image generation, technical SEO analysis, and general consultation adhering to Align standards.",
            f"Your working workspace: {workspace_path}",
            f"Current Project ID: {project_id}",
            "MANDATORY GUIDELINES:",
            "1. LANGUAGE: Respond naturally and fluently in the SAME LANGUAGE used by the user.",
            "2. VISUAL PREVIEWS & SCREENSHOTS: When updating HTML mockup, call `render_html_to_png` and embed image markdown.",
            "3. IMAGE GENERATION: When user requests images, call `generate_image` and embed returned link.",
            "4. TOOL USAGE: Inspect files, write assets, generate images, or render previews. Never fabricate data.",
            "5. STRUCTURE: Provide clear, structured, and insightful answers with high aesthetic standards.",
            "\n" + get_writing_system_prompt(),
        ]
        if notes:
            learned_rules = [n for n in notes if n.startswith("[LEARNED_RULE")]
            general_notes = [n for n in notes if not n.startswith("[LEARNED_RULE")]
            if learned_rules:
                prompt_parts.append(
                    "\n=== PERSISTENT LEARNED RULES (SELF-EVOLVED RULES - MUST COMPLY) ==="
                )
                for r in learned_rules:
                    prompt_parts.append(f"- {r}")
            if general_notes:
                prompt_parts.append("\n=== PROJECT MEMORY & APPROVED NOTES ===")
                for n in general_notes:
                    prompt_parts.append(f"- {n}")

        if rules_text:
            prompt_parts.append(f"\n=== AGENT SPECIFIC OPERATIONAL GUIDELINES ===\n{rules_text}")
        return "\n".join(prompt_parts)

    async def _load_context(
        self, run: Run, db: AsyncSession
    ) -> Tuple[Thread, str, Optional[str], Optional[str], int, float, float, List[Dict[str, Any]], List[Dict[str, Any]], int, float]:
        """Load context, configuration, and previous checkpoint or history for run."""
        thread_q = select(Thread).where(Thread.id == run.thread_id)
        thread = (await db.execute(thread_q)).scalars().first()
        if not thread:
            raise ValueError(f"Thread for run {run.id} not found")

        workspace_path = workspace_service.init_project_workspace(thread.project_id)
        tools = tool_registry.get_openai_schemas_for_agent(run.agent_key)
        agent_q = select(Agent).where(Agent.key == run.agent_key)
        agent_rec = (await db.execute(agent_q)).scalars().first()

        target_model = agent_rec.model if (agent_rec and agent_rec.model) else None
        agent_cfg = agent_rec.config if (agent_rec and isinstance(agent_rec.config, dict)) else {}
        image_model = agent_cfg.get("image_model") if isinstance(agent_cfg.get("image_model"), str) else None
        max_steps = int(agent_cfg.get("max_steps", self.max_steps))
        max_cost_usd = float(agent_cfg.get("max_cost_usd", self.max_cost_usd))
        max_minutes = float(agent_cfg.get("max_minutes", 10))

        if run.checkpoint_state and isinstance(run.checkpoint_state, dict) and "messages" in run.checkpoint_state:
            messages = list(run.checkpoint_state["messages"])
            start_step = int(run.checkpoint_state.get("step_idx", 0)) + 1
            total_cost = float(run.checkpoint_state.get("total_cost", 0.0))
            run.checkpoint_state = None
        else:
            start_step = 1
            total_cost = 0.0
            notes_q = select(ProjectNote).where(
                ProjectNote.project_id == thread.project_id, ProjectNote.status == "approved"
            ).order_by(ProjectNote.created_at.asc())
            approved_notes = [n.content for n in (await db.execute(notes_q)).scalars().all()]
            messages = [{
                "role": "system",
                "content": self._build_system_prompt(run.agent_key, workspace_path, thread.project_id, approved_notes),
            }]
            msg_q = select(Message).where(Message.thread_id == thread.id).order_by(Message.created_at.asc())
            for msg in (await db.execute(msg_q)).scalars().all():
                messages.append({"role": msg.role, "content": msg.content})

        return (thread, workspace_path, target_model, image_model, max_steps, max_cost_usd, max_minutes, tools, messages, start_step, total_cost)

    async def _execute_tool_calls(
        self,
        tool_calls: List[Any],
        step_idx: int,
        run_id: str,
        total_tokens: int,
        tool_ctx: ToolContext,
        messages: List[Dict[str, Any]],
        db: AsyncSession,
        consecutive_errors: int = 0,
    ) -> Tuple[Optional[str], int, float]:
        """Execute tool calls, handle error containment and check for pause requests."""
        paused_status: Optional[str] = None
        tool_cost_usd = 0.0
        allowed_tools = tool_registry.get_allowed_tool_names(tool_ctx.agent_key)

        for idx, tc in enumerate(tool_calls):
            tool = tool_registry.get(tc.name)
            try:
                if tool and tc.name in allowed_tools:
                    allowed_props = tool.parameters_schema.get("properties", {})
                    filtered_args = (
                        {k: v for k, v in tc.arguments.items() if k in allowed_props}
                        if isinstance(allowed_props, dict)
                        else tc.arguments
                    )
                    tool_output: Dict[str, Any] = await tool.execute(tool_ctx, **filtered_args)
                    tool_cost_usd += float(tool_output.get("llm_cost_usd", 0.0))
                    consecutive_errors = 0
                else:
                    tool_output = {"error": f"Tool '{tc.name}' not found or not permitted for agent '{tool_ctx.agent_key}'."}
                    consecutive_errors += 1
            except Exception as exc:
                tool_output = {"error": f"Lỗi thực thi công cụ '{tc.name}': {str(exc)}"}
                consecutive_errors += 1

            step_record = Step(
                run_id=run_id, seq=step_idx, tool=tc.name, args=tc.arguments, result=tool_output, tokens=total_tokens
            )
            db.add(step_record)
            await db.commit()

            tool_str = json.dumps(tool_output, ensure_ascii=False) if isinstance(tool_output, dict) else str(tool_output)
            if len(tool_str) > 12000:
                tool_str = tool_str[:12000] + "\n... [Output truncated to 12000 chars]"
            messages.append({"role": "tool", "tool_call_id": tc.id, "name": tc.name, "content": tool_str})

            if tool_output.get("pause") is True or str(tool_output.get("status", "")).startswith("waiting_"):
                paused_status = tool_output.get("status") or "waiting_approval"
                for rem_tc in tool_calls[idx + 1:]:
                    messages.append({
                        "role": "tool",
                        "tool_call_id": rem_tc.id,
                        "name": rem_tc.name,
                        "content": json.dumps({"status": "deferred", "message": "Tạm hoãn do bước trước yêu cầu phê duyệt."}),
                    })
                break

        return (paused_status, consecutive_errors, tool_cost_usd)

    async def execute_run(
        self,
        run_id: str,
        db: AsyncSession,
        event_callback: Optional[Callable[[Dict[str, Any]], Any]] = None,
    ) -> Dict[str, Any]:
        """Execute a full ReAct loop for a run."""
        q = select(Run).where(Run.id == run_id)
        run = (await db.execute(q)).scalars().first()
        if not run:
            raise ValueError(f"Run {run_id} not found")

        run.status = "running"
        await db.commit()

        (
            thread, workspace_path, target_model, image_model, max_steps, max_cost_usd,
            max_minutes, tools, messages, start_step, total_cost
        ) = await self._load_context(run, db)

        tool_ctx = ToolContext(
            project_id=thread.project_id, run_id=run.id, workspace_path=workspace_path, db=db,
            agent_key=run.agent_key, image_model=image_model,
        )
        final_content = ""
        paused_status: Optional[str] = None
        consecutive_errors = 0
        current_step = start_step
        started_at = time.monotonic()

        for step_idx in range(start_step, max_steps + 1):
            current_step = step_idx
            if time.monotonic() - started_at >= max_minutes * 60:
                final_content = "Đã dừng: Chạm giới hạn thời gian thực thi (max_minutes)."
                run.status = "limit_reached"
                break
            if total_cost >= max_cost_usd:
                final_content = "Đã tạm dừng: Chạm giới hạn ngân sách (max_cost_usd)."
                run.status = "limit_reached"
                break

            llm_res = await openrouter_client.chat_completion(messages=messages, tools=tools, model=target_model)
            total_cost += llm_res.estimated_cost_usd
            if event_callback:
                await event_callback({"type": "step", "step": step_idx, "tokens": llm_res.total_tokens})

            if not llm_res.tool_calls:
                final_content = llm_res.content or "Completed task."
                break

            messages.append({
                "role": "assistant",
                "content": llm_res.content or None,
                "tool_calls": [
                    {
                        "id": tc.id,
                        "type": "function",
                        "function": {
                            "name": tc.name,
                            "arguments": json.dumps(tc.arguments, ensure_ascii=False)
                            if isinstance(tc.arguments, dict) else str(tc.arguments),
                        },
                    }
                    for tc in llm_res.tool_calls
                ],
            })

            paused_status, consecutive_errors, tool_cost_usd = await self._execute_tool_calls(
                llm_res.tool_calls, step_idx, run.id, llm_res.total_tokens, tool_ctx, messages, db, consecutive_errors
            )
            total_cost += tool_cost_usd
            if paused_status:
                break
            if consecutive_errors >= 3:
                final_content = "Đã dừng thực thi: Gặp quá 3 lỗi liên tiếp từ công cụ."
                run.status = "failed"
                run.error_detail = "Quá 3 lỗi thực thi công cụ liên tiếp."
                break

        if paused_status:
            run.checkpoint_state = {"messages": messages, "step_idx": current_step, "total_cost": total_cost}
            run.status = paused_status if paused_status.startswith("waiting_") else run.status
            run.cost_usd = total_cost
            await db.commit()
            return {"run_id": run.id, "status": run.status, "cost_usd": total_cost, "output": None}

        if not final_content:
            final_content = "Đã đạt giới hạn số bước tối đa (max_steps). Vui lòng gửi tin nhắn tiếp để tiếp tục."
            if run.status not in ["failed", "limit_reached"]:
                run.status = "limit_reached"

        db.add(Message(thread_id=thread.id, role="assistant", content=final_content))
        if run.status not in ["failed", "limit_reached", "rework_needed", "cancelled"]:
            run.status = "completed"
        run.cost_usd = total_cost
        await db.commit()

        if event_callback:
            await event_callback({"type": "done", "content": final_content, "cost": total_cost})
        return {"run_id": run.id, "status": run.status, "cost_usd": total_cost, "output": final_content}


agent_runner = AgentRunner()
