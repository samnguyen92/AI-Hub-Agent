"""Generate and store project images using OpenRouter image models."""

import base64
import logging
import mimetypes
import os
import re
import uuid
from typing import Any, Dict, List
import httpx

from app.db.models import LibraryItem
from app.services.openrouter import openrouter_client
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path

logger = logging.getLogger(__name__)


class GenerateImageTool(BaseTool):
    """Tool to generate professional AI images using OpenAI GPT Image 2.5 Sunburst."""

    name = "generate_image"
    description = (
        "Tạo hình ảnh chuyên nghiệp độ phân giải cao sử dụng model OpenAI GPT Image 2.5 Sunburst "
        "(openai/gpt-image-2.5-sunburst). Dùng để tạo banner, ảnh sản phẩm, poster."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "prompt": {
                "type": "string",
                "description": "Mô tả chi tiết hình ảnh cần tạo (hỗ trợ cả tiếng Anh và tiếng Việt).",
            },
            "aspect_ratio": {
                "type": "string",
                "description": "Tỉ lệ khung hình ('16:9', '1:1', '4:3', '9:16'). Mặc định là '16:9'.",
                "default": "16:9",
            },
            "model": {
                "type": "string",
                "description": "Model tạo hình ảnh (mặc định 'openai/gpt-image-2.5-sunburst'). Có thể nhập model ID khác.",
                "default": "openai/gpt-image-2.5-sunburst",
            },
            "filename": {
                "type": "string",
                "description": "Tên file lưu trong thư mục images/ (không cần phần mở rộng .png).",
                "default": "generated_image",
            },
            "reference_paths": {
                "type": "array",
                "items": {"type": "string"},
                "description": "Đường dẫn ảnh hiện có trong workspace để chỉnh sửa và giữ nguyên các chi tiết không được yêu cầu thay đổi.",
            },
        },
        "required": ["prompt"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        prompt = kwargs.get("prompt", "").strip()
        aspect_ratio = kwargs.get("aspect_ratio", "16:9")
        model = context.image_model or kwargs.get("model") or "openai/gpt-image-2.5-sunburst"
        raw_name = kwargs.get("filename", "generated_image")

        safe_name = re.sub(r"[^a-zA-Z0-9_-]", "_", raw_name).strip("_") or "image"
        filename = f"{safe_name}_{uuid.uuid4().hex[:6]}.png"
        rel_path = f"images/{filename}"

        images_dir = safe_resolve_path(context.workspace_path, "images")
        os.makedirs(images_dir, exist_ok=True)
        dest_path = safe_resolve_path(context.workspace_path, rel_path)

        try:
            input_references = self._load_references(context.workspace_path, kwargs.get("reference_paths", []))
            res = await openrouter_client.generate_image(
                prompt=prompt,
                aspect_ratio=aspect_ratio,
                model=model,
                input_references=input_references,
            )
            await self._save_result(context, res.get("image_url", ""), dest_path, rel_path, model, prompt)
            web_url = f"/api/backend/projects/{context.project_id}/files?path={rel_path}"
            return {
                "status": "success",
                "model": model,
                "prompt": prompt,
                "reference_paths": kwargs.get("reference_paths", []),
                "local_path": rel_path,
                "url": web_url,
                "markdown": f"![{prompt}]({web_url})",
                "instruction": f"Include this image in your response using markdown: ![{prompt}]({web_url})",
            }
        except Exception as exc:
            logger.exception("Image generation failed for project %s", context.project_id)
            return {"error": f"Lỗi tạo hình ảnh với {model}: {str(exc)[:200]}"}

    @staticmethod
    async def _save_result(
        context: ToolContext, image_url: str, dest_path: str, rel_path: str, model: str, prompt: str
    ) -> None:
        """Persist the returned image and register it in the project library."""
        if image_url.startswith("data:image"):
            _, encoded = image_url.split(",", 1)
            with open(dest_path, "wb") as image_file:
                image_file.write(base64.b64decode(encoded))
        elif image_url.startswith("http"):
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.get(image_url)
                response.raise_for_status()
                with open(dest_path, "wb") as image_file:
                    image_file.write(response.content)
        else:
            raise ValueError(f"Unsupported image format: {image_url[:50]}")
        context.db.add(LibraryItem(project_id=context.project_id, type="generated_image", tag=model.split("/")[-1], description=prompt, path=rel_path))
        await context.db.commit()

    @staticmethod
    def _load_references(workspace_path: str, paths: List[str]) -> List[Dict[str, Any]]:
        """Read existing project images into image-model reference data URLs."""
        references = []
        for rel_path in paths[:16]:
            full_path = safe_resolve_path(workspace_path, rel_path)
            if not os.path.isfile(full_path):
                raise FileNotFoundError(f"Ảnh reference không tồn tại: {rel_path}")
            if os.path.getsize(full_path) > 20 * 1024 * 1024:
                raise ValueError(f"Ảnh reference vượt quá 20 MB: {rel_path}")
            mime_type = mimetypes.guess_type(full_path)[0] or ""
            if not mime_type.startswith("image/"):
                raise ValueError(f"File reference không phải ảnh: {rel_path}")
            with open(full_path, "rb") as image_file:
                encoded = base64.b64encode(image_file.read()).decode("ascii")
            references.append({"type": "image_url", "image_url": {"url": f"data:{mime_type};base64,{encoded}"}})
        return references


image_tool = GenerateImageTool()
tool_registry.register(image_tool)
