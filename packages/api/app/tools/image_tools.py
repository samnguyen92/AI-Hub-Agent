"""AI Image Generation tool using ByteDance Seedream 4.5 via OpenRouter."""

import base64
import os
import re
import uuid
from typing import Any, Dict
import httpx

from app.db.models import LibraryItem
from app.db.session import async_session_maker
from app.services.openrouter import openrouter_client
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path


class GenerateImageTool(BaseTool):
    """Tool to generate professional AI images using bytedance-seed/seedream-4.5."""

    name = "generate_image"
    description = (
        "Tạo hình ảnh chuyên nghiệp độ phân giải cao sử dụng model ByteDance Seedream 4.5 "
        "(bytedance-seed/seedream-4.5). Dùng để tạo banner, ảnh phối cảnh xe hơi, sản phẩm, poster."
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
            "filename": {
                "type": "string",
                "description": "Tên file lưu trong thư mục images/ (không cần phần mở rộng .png).",
                "default": "generated_image",
            },
        },
        "required": ["prompt"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        prompt = kwargs.get("prompt", "").strip()
        aspect_ratio = kwargs.get("aspect_ratio", "16:9")
        raw_name = kwargs.get("filename", "generated_image")

        safe_name = re.sub(r"[^a-zA-Z0-9_-]", "_", raw_name).strip("_") or "image"
        filename = f"{safe_name}_{uuid.uuid4().hex[:6]}.png"
        rel_path = f"images/{filename}"

        images_dir = safe_resolve_path(context.workspace_path, "images")
        os.makedirs(images_dir, exist_ok=True)
        dest_path = safe_resolve_path(context.workspace_path, rel_path)

        try:
            res = await openrouter_client.generate_image(
                prompt=prompt,
                aspect_ratio=aspect_ratio,
                model="bytedance-seed/seedream-4.5",
            )
            image_url = res.get("image_url", "")

            # Download or decode image
            if image_url.startswith("data:image"):
                # Base64 data URL
                header, encoded = image_url.split(",", 1)
                img_bytes = base64.b64decode(encoded)
                with open(dest_path, "wb") as f:
                    f.write(img_bytes)
            elif image_url.startswith("http"):
                async with httpx.AsyncClient(timeout=30.0) as client:
                    img_resp = await client.get(image_url)
                    if img_resp.status_code == 200:
                        with open(dest_path, "wb") as f:
                            f.write(img_resp.content)
                    else:
                        raise RuntimeError(f"Failed to download image: HTTP {img_resp.status_code}")
            else:
                raise RuntimeError(f"Unsupported image format: {image_url[:50]}")

            # Register in LibraryItem
            try:
                lib_item = LibraryItem(
                    project_id=context.project_id,
                    type="generated_image",
                    tag="seedream-4.5",
                    description=prompt[:200],
                    path=rel_path,
                )
                context.db.add(lib_item)
                await context.db.commit()
            except Exception:
                pass

            web_url = f"/api/backend/projects/{context.project_id}/files?path={rel_path}"
            return {
                "status": "success",
                "model": "bytedance-seed/seedream-4.5",
                "prompt": prompt,
                "local_path": rel_path,
                "url": web_url,
                "markdown": f"![{prompt}]({web_url})",
                "instruction": f"Include this image in your response using markdown: ![{prompt}]({web_url})",
            }
        except Exception as exc:
            return {"error": f"Lỗi tạo hình ảnh với Seedream 4.5: {str(exc)}"}


image_tool = GenerateImageTool()
tool_registry.register(image_tool)
