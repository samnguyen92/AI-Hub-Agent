"""Tool enabling agents to open Intake, Plan, and Result gates."""

import json
import re
from typing import Any, Dict, Optional
from sqlalchemy import select
from app.db.models import Step
from app.services.gate_service import gate_service
from app.tools.base import BaseTool, ToolContext, tool_registry


class OpenGateTool(BaseTool):
    """Tool allowing agents to pause execution and present a Gate to the human operator."""

    name = "open_gate"
    description = (
        "Mở một Cổng kiểm định (Intake Gate, Plan Gate, hoặc Result Gate). "
        "Dùng khi: làm rõ yêu cầu quan trọng (intake), duyệt kế hoạch trước khi code (plan), "
        "hoặc trình sản phẩm hoàn thiện kèm link Try It và các bước kiểm thử (result)."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "gate_type": {
                "type": "string",
                "enum": ["intake", "plan", "result", "design", "code", "launch"],
                "description": "Loại cổng: 'intake', 'plan', 'result', 'design', 'code', 'launch'",
            },
            "title": {"type": "string", "description": "Tiêu đề của Cổng"},
            "slug": {"type": "string", "description": "Slug định danh (vd: 'hero-redesign')"},
            "decision_needed": {
                "type": "string",
                "description": "Câu hỏi cụ thể cần người dùng đưa ra quyết định hoặc lựa chọn",
            },
            "recommendation": {
                "type": "string",
                "description": "Đề xuất kỹ thuật hoặc phương án của Agent",
            },
            "try_it_url": {
                "type": "string",
                "description": "Đường dẫn chạy thử (bắt buộc với Result Gate, vd: '/api/backend/projects/{id}/files?path=index.html')",
            },
            "how_to_test": {
                "type": "array",
                "items": {"type": "string"},
                "description": "Danh sách các bước để người dùng tự kiểm tra kết quả",
            },
            "questions": {
                "type": "array",
                "items": {"type": "string"},
                "description": "Các câu hỏi cụ thể cần người dùng trả lời (cho Intake Gate)",
            },
        },
        "required": ["gate_type", "title", "slug", "decision_needed"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        gate_type = kwargs["gate_type"]
        title = kwargs["title"]
        slug = kwargs["slug"]
        decision_needed = kwargs["decision_needed"]
        recommendation = kwargs.get("recommendation", "")
        questions = kwargs.get("questions", [])
        try_it_url = kwargs.get("try_it_url")
        how_to_test = kwargs.get("how_to_test", [])
        review = None
        if gate_type == "design":
            review = await self._verified_quality_review(context)
            if not review:
                return {"error": "Design gate chưa mở: hãy chạy Quality Auditor, sửa findings và đạt verdict pass sau lần sửa cuối."}

        try_it = {"url": try_it_url} if try_it_url else None

        approval = await gate_service.open_gate(
            db=context.db,
            run_id=context.run_id,
            gate_type=gate_type,
            title=title,
            slug=slug,
            decision_needed=decision_needed,
            recommendation=recommendation,
            questions=questions,
            try_it=try_it,
            how_to_test=how_to_test,
            review=review,
        )

        formatted_md = gate_service.format_gate_markdown(approval.payload)

        return {
            "status": f"waiting_gate_{gate_type}",
            "pause": True,
            "gate_id": approval.id,
            "gate_type": gate_type,
            "title": title,
            "presentation_markdown": formatted_md,
            "validation_warnings": approval.payload.get("validation_warnings", []),
        }

    @staticmethod
    async def _verified_quality_review(context: ToolContext) -> Optional[Dict[str, str]]:
        """Require a passing audit after the latest design-changing tool call."""
        result = await context.db.execute(
            select(Step).where(Step.run_id == context.run_id, Step.tool == "run_quality_review")
            .order_by(Step.created_at.desc()).limit(1)
        )
        step = result.scalars().first()
        if not step or not isinstance(step.result, dict):
            return None
        mutation = await context.db.execute(
            select(Step).where(
                Step.run_id == context.run_id,
                Step.tool.in_(["write_file", "apply_patch", "save_artifact", "generate_image"]),
            ).order_by(Step.created_at.desc()).limit(1)
        )
        latest_mutation = mutation.scalars().first()
        if latest_mutation and latest_mutation.created_at > step.created_at:
            return None
        report = _parse_review_report(str(step.result.get("report") or ""))
        if report.get("verdict") != "pass":
            return None
        summary = str(report.get("summary") or "Quality Auditor passed the final design review.")[:500]
        return {"reviewer_seat": "quality-auditor", "status": "passed", "summary": summary}


def _parse_review_report(report: str) -> Dict[str, Any]:
    """Parse the child auditor's JSON verdict while tolerating code fences."""
    cleaned = re.sub(r"^```(?:json)?|```$", "", report.strip(), flags=re.IGNORECASE).strip()
    match = re.search(r"\{[\s\S]*\}", cleaned)
    if not match:
        return {}
    try:
        parsed = json.loads(match.group(0))
        return parsed if isinstance(parsed, dict) else {}
    except json.JSONDecodeError:
        return {}


# Register tool
tool_registry.register(OpenGateTool())
