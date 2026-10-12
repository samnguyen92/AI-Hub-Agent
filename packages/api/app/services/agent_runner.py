"""Agent Runner orchestrating the ReAct execution loop."""

import asyncio
import json
from typing import Any, Callable, Dict, List, Optional
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Agent, Message, ProjectNote, Run, Step, Thread
from app.services.openrouter import openrouter_client
from app.services.workspace import workspace_service
from app.tools.base import ToolContext, tool_registry


class AgentRunner:
    """Orchestrates single-agent execution runs with limits and tool calls."""

    def __init__(
        self,
        max_steps: int = 15,
        max_cost_usd: float = 1.0,
    ) -> None:
        self.max_steps = max_steps
        self.max_cost_usd = max_cost_usd

    def _build_system_prompt(
        self, agent_key: str, workspace_path: str, notes: Optional[List[str]] = None
    ) -> str:
        """Construct the system prompt for the specified agent."""
        rules_text = ""
        candidate_paths = [
            f"/app/agents/{agent_key}/rules.md",
            f"agents/{agent_key}/rules.md",
        ]
        import os

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
            "Mission: Assist with website design, coding, technical SEO analysis, and general consultation adhering to Align standards.",
            f"Your working workspace: {workspace_path}",
            "MANDATORY INSTRUCTIONS:",
            "1. Always communicate and respond fluently, professionally, and naturally in ENGLISH.",
            "2. Use tool calling to read/write files or inspect project assets when executing technical actions.",
            "3. Never fabricate metrics, files, or facts.",
            "4. Provide clear, structured, and insightful answers like ChatGPT and Claude.",
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

    async def execute_run(
        self,
        run_id: str,
        db: AsyncSession,
        event_callback: Optional[Callable[[Dict[str, Any]], Any]] = None,
    ) -> Dict[str, Any]:
        """Execute a full ReAct loop for a run."""
        q = select(Run).where(Run.id == run_id)
        res = await db.execute(q)
        run = res.scalars().first()
        if not run:
            raise ValueError(f"Run {run_id} not found")

        # Get thread and project
        thread_q = select(Thread).where(Thread.id == run.thread_id)
        thread_res = await db.execute(thread_q)
        thread = thread_res.scalars().first()
        if not thread:
            raise ValueError("Thread not found")

        run.status = "running"
        await db.commit()

        workspace_path = workspace_service.init_project_workspace(thread.project_id)
        tools = tool_registry.get_openai_schemas_for_agent(run.agent_key)

        # Query approved project notes for memory context
        notes_q = (
            select(ProjectNote)
            .where(ProjectNote.project_id == thread.project_id, ProjectNote.status == "approved")
            .order_by(ProjectNote.created_at.asc())
        )
        notes_res = await db.execute(notes_q)
        approved_notes = [n.content for n in notes_res.scalars().all()]

        # Build initial messages
        messages: List[Dict[str, Any]] = [
            {
                "role": "system",
                "content": self._build_system_prompt(
                    run.agent_key, workspace_path, approved_notes
                ),
            }
        ]

        # Fetch thread history
        msg_q = select(Message).where(Message.thread_id == thread.id).order_by(Message.created_at.asc())
        history_res = await db.execute(msg_q)
        for msg in history_res.scalars().all():
            messages.append({"role": msg.role, "content": msg.content})

        total_cost = 0.0
        final_content = ""

        tool_ctx = ToolContext(
            project_id=thread.project_id,
            run_id=run.id,
            workspace_path=workspace_path,
            db=db,
            agent_key=run.agent_key,
        )

        # Query agent record to get configured model
        agent_q = select(Agent).where(Agent.key == run.agent_key)
        agent_res = await db.execute(agent_q)
        agent_rec = agent_res.scalars().first()
        target_model = agent_rec.model if (agent_rec and agent_rec.model) else None

        for step_idx in range(1, self.max_steps + 1):
            if total_cost >= self.max_cost_usd:
                final_content = "Đã tạm dừng: Chạm giới hạn ngân sách (max_cost_usd)."
                break

            llm_res = await openrouter_client.chat_completion(
                messages=messages, tools=tools, model=target_model
            )
            total_cost += llm_res.estimated_cost_usd

            if event_callback:
                await event_callback({"type": "step", "step": step_idx, "tokens": llm_res.total_tokens})

            # Check if tools are requested
            if not llm_res.tool_calls:
                final_content = llm_res.content or "Completed task."
                break

            # Record assistant message containing tool calls
            messages.append(
                {
                    "role": "assistant",
                    "content": llm_res.content or None,
                    "tool_calls": [
                        {
                            "id": tc.id,
                            "type": "function",
                            "function": {
                                "name": tc.name,
                                "arguments": json.dumps(tc.arguments, ensure_ascii=False)
                                if isinstance(tc.arguments, dict)
                                else str(tc.arguments),
                            },
                        }
                        for tc in llm_res.tool_calls
                    ],
                }
            )

            # Execute tool calls
            for tc in llm_res.tool_calls:
                tool = tool_registry.get(tc.name)
                tool_output: Dict[str, Any] = (
                    await tool.execute(tool_ctx, **tc.arguments)
                    if tool
                    else {"error": f"Tool '{tc.name}' not found or permitted."}
                )

                step_record = Step(
                    run_id=run.id,
                    seq=step_idx,
                    tool=tc.name,
                    args=tc.arguments,
                    result=tool_output,
                    tokens=llm_res.total_tokens,
                )
                db.add(step_record)
                await db.commit()

                # Append tool response compliant with modern OpenAI/Azure specs
                messages.append(
                    {
                        "role": "tool",
                        "tool_call_id": tc.id,
                        "name": tc.name,
                        "content": json.dumps(tool_output, ensure_ascii=False)
                        if isinstance(tool_output, dict)
                        else str(tool_output),
                    }
                )

        # Save assistant message
        assistant_msg = Message(
            thread_id=thread.id,
            role="assistant",
            content=final_content,
        )
        db.add(assistant_msg)
        run.status = "completed"
        run.cost_usd = total_cost
        await db.commit()

        if event_callback:
            await event_callback({"type": "done", "content": final_content, "cost": total_cost})

        return {"run_id": run.id, "status": "completed", "cost_usd": total_cost, "output": final_content}


agent_runner = AgentRunner()
