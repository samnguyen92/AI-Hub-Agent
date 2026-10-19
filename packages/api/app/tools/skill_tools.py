"""Tools for dynamic skill loading and human-in-the-loop input requests."""

import os
from typing import Any, Dict
from sqlalchemy import select
from app.db.models import Run
from app.tools.base import BaseTool, ToolContext, tool_registry


class LoadSkillTool(BaseTool):
    """Tool to dynamically load full skill instructions and guidelines."""

    name = "load_skill"
    description = "Nạp nội dung hướng dẫn chi tiết của một Skill (ví dụ: align-style, analyze-brief)."
    parameters_schema = {
        "type": "object",
        "properties": {
            "skill_name": {
                "type": "string",
                "description": "Tên skill cần nạp (vd: 'align-style', 'homepage-mockup')",
            }
        },
        "required": ["skill_name"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        skill_name = kwargs["skill_name"].strip()
        clean_name = skill_name.split("/")[-1]

        # Potential search paths
        candidate_paths = [
            f"skills/shared/{skill_name}/SKILL.md",
            f"skills/shared/{skill_name}.md",
            f"skills/shared/{clean_name}/SKILL.md",
            f"skills/shared/{clean_name}.md",
            f"agents/{context.agent_key}/skills/{skill_name}/SKILL.md",
            f"agents/{context.agent_key}/skills/{skill_name}.md",
            f"agents/{context.agent_key}/skills/{clean_name}/SKILL.md",
            f"agents/{context.agent_key}/skills/{clean_name}.md",
        ]

        # Candidate search directories
        search_roots = [
            "/app",
            os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../..")),
            os.getcwd(),
        ]

        for s_root in search_roots:
            for c_rel in candidate_paths:
                full_path = os.path.join(s_root, c_rel)
                if os.path.exists(full_path) and os.path.isfile(full_path):
                    with open(full_path, "r", encoding="utf-8") as f:
                        content = f.read()
                    return {
                        "skill_name": skill_name,
                        "path": c_rel,
                        "content": content,
                        "status": "loaded",
                    }

        return {
            "error": f"Không tìm thấy skill '{skill_name}' trong skills/shared hoặc agents/{context.agent_key}/skills/"
        }


class RequestInputTool(BaseTool):
    """Tool to pause execution and request user clarification."""

    name = "request_input"
    description = "Tạm dừng và đặt câu hỏi cho người dùng khi thiếu thông tin quan trọng."
    parameters_schema = {
        "type": "object",
        "properties": {
            "question": {
                "type": "string",
                "description": "Nội dung câu hỏi cần làm rõ từ người dùng",
            },
            "options": {
                "type": "array",
                "items": {"type": "string"},
                "description": "Các phương án lựa chọn gợi ý (tùy chọn)",
            },
        },
        "required": ["question"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        question = kwargs["question"]
        options = kwargs.get("options", [])

        # Update run status to waiting_input
        q = select(Run).where(Run.id == context.run_id)
        res = await context.db.execute(q)
        run = res.scalars().first()
        if run:
            run.status = "waiting_input"
            await context.db.commit()

        return {
            "status": "waiting_input",
            "pause": True,
            "question": question,
            "options": options,
            "message": "Đã gửi câu hỏi yêu cầu người dùng phản hồi.",
        }


# Register tools
tool_registry.register(LoadSkillTool())
tool_registry.register(RequestInputTool())
