"""Tool enabling agents to open Intake, Plan, and Result gates."""

from typing import Any, Dict
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
                "enum": ["intake", "plan", "result"],
                "description": "Loại cổng: 'intake' (làm rõ), 'plan' (kế hoạch), 'result' (kết quả)",
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
        )

        formatted_md = gate_service.format_gate_markdown(approval.payload)

        return {
            "status": "gate_opened",
            "gate_id": approval.id,
            "gate_type": gate_type,
            "title": title,
            "presentation_markdown": formatted_md,
            "validation_warnings": approval.payload.get("validation_warnings", []),
        }


# Register tool
tool_registry.register(OpenGateTool())
