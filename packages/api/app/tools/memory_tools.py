"""Memory and project-scoped note proposal tools."""

from typing import Any, Dict, List
from sqlalchemy import select
from app.db.models import ProjectNote
from app.tools.base import BaseTool, ToolContext, tool_registry


class ProposeNoteTool(BaseTool):
    """Tool allowing agents to propose durable project-scoped memories."""

    name = "propose_note"
    description = "Đề xuất một ghi chú hoặc quy chuẩn mới cho dự án để ghi nhớ lâu dài (cần user duyệt)."
    parameters_schema = {
        "type": "object",
        "properties": {
            "note_content": {
                "type": "string",
                "description": "Nội dung ghi chú cần nhớ (vd: 'Khách hàng yêu cầu dùng logo nền trong suốt và font Inter.')",
            },
            "category": {
                "type": "string",
                "description": "Phân loại ghi chú (branding, design, seo, tech)",
            },
        },
        "required": ["note_content"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        content = kwargs["note_content"].strip()
        category = kwargs.get("category", "general")

        if not context.db:
            return {"error": "Database session not available"}

        formatted_content = f"[{category.upper()}] {content}"
        run_id = None
        if context.run_id:
            from app.db.models import Run
            r_check = await context.db.execute(select(Run).where(Run.id == context.run_id))
            if r_check.scalars().first():
                run_id = context.run_id

        note = ProjectNote(
            project_id=context.project_id,
            content=formatted_content,
            status="pending",
            source_run_id=run_id,
        )
        context.db.add(note)
        await context.db.commit()

        return {
            "status": "proposed",
            "note_id": note.id,
            "content": formatted_content,
            "message": "Đã ghi nhận đề xuất ghi chú. Người dùng có thể duyệt tại trang Quản lý ghi chú.",
        }


class ListProjectNotesTool(BaseTool):
    """Tool to inspect active memories and rules established for the current project."""

    name = "list_notes"
    description = "Xem danh sách các ghi chú và quy chuẩn đã được phê duyệt của dự án hiện tại."
    parameters_schema = {"type": "object", "properties": {}}

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        if not context.db:
            return {"notes": []}

        q = (
            select(ProjectNote)
            .where(ProjectNote.project_id == context.project_id, ProjectNote.status == "approved")
            .order_by(ProjectNote.created_at.asc())
        )
        res = await context.db.execute(q)
        notes = res.scalars().all()

        return {
            "project_id": context.project_id,
            "count": len(notes),
            "approved_notes": [n.content for n in notes],
        }


class LearnRuleTool(BaseTool):
    """Tool allowing agents to proactively self-learn and store operational rules."""

    name = "learn_rule"
    description = "Tự học và ghi nhớ một bài học kinh nghiệm, quy chuẩn hoặc sở thích của người dùng vào dự án."
    parameters_schema = {
        "type": "object",
        "properties": {
            "rule": {
                "type": "string",
                "description": "Nội dung bài học kinh nghiệm cần tự ghi nhớ",
            },
            "category": {
                "type": "string",
                "description": "Phân loại bài học (style, behavior, technical, general)",
            },
        },
        "required": ["rule"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rule = kwargs["rule"].strip()
        category = kwargs.get("category", "general")

        if not context.db:
            return {"error": "Database session not available"}

        formatted_content = f"[LEARNED_RULE:{category.upper()}] {rule}"
        run_id = None
        if context.run_id:
            from app.db.models import Run
            r_check = await context.db.execute(select(Run).where(Run.id == context.run_id))
            if r_check.scalars().first():
                run_id = context.run_id

        note = ProjectNote(
            project_id=context.project_id,
            content=formatted_content,
            status="approved",  # Automatically active for self-learned insights
            source_run_id=run_id,
        )
        context.db.add(note)
        await context.db.commit()

        return {
            "status": "learned",
            "note_id": note.id,
            "rule": formatted_content,
            "message": "Đã tự động học và ghi nhớ quy tắc thành công vào bộ nhớ vĩnh viễn.",
        }


# Register tools
tool_registry.register(ProposeNoteTool())
tool_registry.register(ListProjectNotesTool())
tool_registry.register(LearnRuleTool())
