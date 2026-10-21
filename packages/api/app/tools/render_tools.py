"""Sandbox rendering, screenshot and audit tools."""

import os
from typing import Any, Dict
from app.services.sandbox import sandbox_client
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path


class RenderHtmlToPngTool(BaseTool):
    """Tool to render HTML code into desktop and mobile PNG screenshots."""

    name = "render_html_to_png"
    description = "Render file HTML trong workspace thành ảnh PNG Desktop (1440px) và Mobile (390px)."
    parameters_schema = {
        "type": "object",
        "properties": {
            "html_path": {
                "type": "string",
                "description": "Đường dẫn file HTML cần render tương đối so với workspace",
            },
            "output_prefix": {
                "type": "string",
                "description": "Tiền tố tên file ảnh đầu ra (vd: 'mockup-v1')",
            },
        },
        "required": ["html_path", "output_prefix"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        html_rel = kwargs["html_path"]
        prefix = kwargs.get("output_prefix", "preview")

        try:
            full_html = safe_resolve_path(context.workspace_path, html_rel)
            if not os.path.exists(full_html):
                return {"error": f"Không tìm thấy file: {html_rel}"}

            desktop_rel = f"screenshots/{prefix}-desktop.png"
            mobile_rel = f"screenshots/{prefix}-mobile.png"

            full_desktop = safe_resolve_path(context.workspace_path, desktop_rel)
            full_mobile = safe_resolve_path(context.workspace_path, mobile_rel)

            res = await sandbox_client.render_workspace_html(full_html, full_desktop, full_mobile)
            return {
                "status": "rendered",
                "desktop_png": desktop_rel,
                "mobile_png": mobile_rel,
                "raw": res,
            }
        except Exception as exc:
            return {"error": f"Lỗi render HTML: {str(exc)}"}


class TakeScreenshotTool(BaseTool):
    """Tool to capture screenshots of an external URL."""

    name = "take_screenshot"
    description = "Chụp ảnh màn hình một URL website tại 2 viewport Desktop (1440px) và Mobile (390px)."
    parameters_schema = {
        "type": "object",
        "properties": {
            "url": {"type": "string", "description": "URL website cần chụp ảnh"},
            "output_prefix": {"type": "string", "description": "Tên file ảnh xuất ra"},
        },
        "required": ["url"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        url = kwargs["url"]
        prefix = kwargs.get("output_prefix", "site-snapshot")
        try:
            desktop_rel = f"screenshots/{prefix}-desktop.png"
            mobile_rel = f"screenshots/{prefix}-mobile.png"
            full_desktop = safe_resolve_path(context.workspace_path, desktop_rel)
            full_mobile = safe_resolve_path(context.workspace_path, mobile_rel)

            res = await sandbox_client.take_screenshot(url, full_desktop, full_mobile)
            return {
                "status": "captured",
                "url": url,
                "desktop_png": desktop_rel,
                "mobile_png": mobile_rel,
                "raw": res,
            }
        except Exception as exc:
            return {"error": f"Lỗi chụp ảnh website: {str(exc)}"}


class RunChecksTool(BaseTool):
    """Tool to audit HTML code standards and accessibility."""

    name = "run_checks"
    description = "Kiểm tra cú pháp HTML, meta tags, responsive và chuẩn tiếp cận WCAG A11y."
    parameters_schema = {
        "type": "object",
        "properties": {
            "html_path": {"type": "string", "description": "File HTML cần kiểm tra"}
        },
        "required": ["html_path"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        html_rel = kwargs["html_path"]
        try:
            full_html = safe_resolve_path(context.workspace_path, html_rel)
            if not os.path.exists(full_html):
                return {"error": f"Không tìm thấy file: {html_rel}"}

            with open(full_html, "r", encoding="utf-8") as f:
                content = f.read()

            return await sandbox_client.run_checks(content)
        except Exception as exc:
            return {"error": f"Lỗi kiểm tra mã: {str(exc)}"}


class CompareScreenshotsTool(BaseTool):
    """Tool to compare visual differences between two images."""

    name = "compare_screenshots"
    description = "So sánh sự khác biệt điểm ảnh (pixel diff) giữa mockup và bản code."
    parameters_schema = {
        "type": "object",
        "properties": {
            "image1_path": {"type": "string", "description": "Đường dẫn ảnh thứ nhất"},
            "image2_path": {"type": "string", "description": "Đường dẫn ảnh thứ hai"},
        },
        "required": ["image1_path", "image2_path"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        img1_rel = kwargs["image1_path"]
        img2_rel = kwargs["image2_path"]
        try:
            full_img1 = safe_resolve_path(context.workspace_path, img1_rel)
            full_img2 = safe_resolve_path(context.workspace_path, img2_rel)
            return await sandbox_client.compare_images(full_img1, full_img2)
        except Exception as exc:
            return {"error": f"Lỗi so sánh ảnh: {str(exc)}"}


# Register tools
tool_registry.register(RenderHtmlToPngTool())
tool_registry.register(TakeScreenshotTool())
tool_registry.register(RunChecksTool())
tool_registry.register(CompareScreenshotsTool())
