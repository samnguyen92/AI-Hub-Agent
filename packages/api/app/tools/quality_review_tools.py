"""Delegate a design checkpoint to the independent quality-auditor agent."""

from typing import Any, Dict

from app.db.models import Run
from app.services.agent_runner import agent_runner
from app.tools.base import BaseTool, ToolContext, tool_registry


class QualityReviewTool(BaseTool):
    """Run the read-only quality agent against a saved design artifact."""

    name = "run_quality_review"
    description = (
        "Chạy Quality Auditor độc lập trên HTML đã render. Trả về verdict, findings và bằng chứng; "
        "Design Agent phải sửa lỗi rồi yêu cầu review lại trước khi mở design gate."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "html_path": {"type": "string", "description": "HTML tương đối với workspace."},
            "screenshot_paths": {
                "type": "array", "items": {"type": "string"}, "maxItems": 2,
                "description": "Screenshot desktop/mobile vừa render để auditor kiểm tra.",
            },
        },
        "required": ["html_path", "screenshot_paths"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        """Create an internal child run and return its independent audit report."""
        html_path = str(kwargs.get("html_path", ""))
        screenshots = kwargs.get("screenshot_paths", [])
        if not html_path or not isinstance(screenshots, list) or not 1 <= len(screenshots) <= 2:
            return {"error": "Cần truyền html_path và screenshot_paths hợp lệ."}
        prompt = self._review_prompt(html_path, screenshots)
        messages = [
            {"role": "system", "content": agent_runner._build_system_prompt(
                "quality-auditor", context.workspace_path, context.project_id
            )},
            {"role": "user", "content": prompt},
        ]
        child = Run(
            thread_id=await self._thread_id(context),
            agent_key="quality-auditor",
            parent_run_id=context.run_id,
            status="queued",
            checkpoint_state={"messages": messages, "step_idx": 0, "total_cost": 0.0},
        )
        context.db.add(child)
        await context.db.commit()
        auditor_tools = [
            name for name in tool_registry.get_allowed_tool_names("quality-auditor")
            if name != "open_gate"
        ]
        result = await agent_runner.execute_run(
            child.id, context.db, persist_final_message=False, allowed_tool_names=auditor_tools
        )
        return {
            "status": result["status"], "run_id": child.id, "report": result.get("output"),
            "cost_usd": result.get("cost_usd", 0.0),
            "instruction": "Chỉ mở design gate sau khi verdict là pass; nếu rework, sửa theo findings rồi chạy review lại.",
        }

    async def _thread_id(self, context: ToolContext) -> str:
        """Resolve the parent run's conversation thread."""
        from sqlalchemy import select
        from app.db.models import Run as RunModel

        result = await context.db.execute(select(RunModel).where(RunModel.id == context.run_id))
        parent = result.scalars().first()
        if not parent:
            raise ValueError("Không tìm thấy parent run cho yêu cầu quality review.")
        return parent.thread_id

    @staticmethod
    def _review_prompt(html_path: str, screenshots: list[str]) -> str:
        """Set an evidence-only review contract for the child quality agent."""
        return (
            "Perform an independent, read-only quality review. Do not modify files and do not open a gate. "
            f"Inspect `{html_path}`. Call run_checks and audit_web_design, then call inspect_screenshots "
            f"on the supplied screenshot paths: {screenshots}. If visual inspection cannot run, "
            "report visual_review as unavailable and verdict rework; do not infer the pixels from HTML. "
            "Return concise JSON with verdict ('pass' or 'rework'), findings (severity, evidence, fix), "
            "automated checks, and visual_review status. Mark rework for broken assets/links, major layout "
            "or responsive defects, accessibility failures, or unverified required checks."
        )


tool_registry.register(QualityReviewTool())
