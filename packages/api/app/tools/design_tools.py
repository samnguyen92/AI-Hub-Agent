"""Design audit and visual verification tools conforming to Mandate-style QA standards."""

import os
import re
from typing import Any, Dict, List
from app.services.sandbox import sandbox_client
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path


def _local_static_audit(html: str) -> Dict[str, Any]:
    """Fallback local audit for syntax, responsive meta, and accessibility."""
    issues: List[str] = []
    if "<!doctype html>" not in html.lower():
        issues.append("Thiếu khai báo <!DOCTYPE html>")
    if "viewport" not in html.lower():
        issues.append("Thiếu thẻ meta viewport cho responsive")
    missing_alts = len(re.findall(r"<img(?![^>]*\balt=)[^>]*>", html, re.I))
    if missing_alts > 0:
        issues.append(f"Có {missing_alts} hình ảnh thiếu thuộc tính alt")
    return {"issues": issues, "a11y_status": "warning" if missing_alts > 0 else "passed"}


class AuditWebDesignTool(BaseTool):
    """Tool to perform automated visual, accessibility, and responsiveness audits on web pages."""

    name = "audit_web_design"
    description = (
        "Kiểm tra chất lượng thiết kế website: audit Accessibility (A11y), responsive viewport, "
        "thẻ alt, cấu trúc semantic và tự động render ảnh chụp Desktop (1440x900) và Mobile (390x844)."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "html_path": {
                "type": "string",
                "description": "Đường dẫn file HTML cần audit tương đối so với workspace",
            },
            "output_prefix": {
                "type": "string",
                "description": "Tiền tố ảnh chụp màn hình (mặc định: 'audit')",
            },
        },
        "required": ["html_path"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        html_rel = kwargs["html_path"]
        prefix = kwargs.get("output_prefix", "audit")

        try:
            full_html = safe_resolve_path(context.workspace_path, html_rel)
            if not os.path.exists(full_html):
                return {"error": f"Không tìm thấy file: {html_rel}"}

            with open(full_html, "r", encoding="utf-8") as f:
                html_content = f.read()

            desktop_rel = f"screenshots/{prefix}-desktop.png"
            mobile_rel = f"screenshots/{prefix}-mobile.png"
            full_desktop = safe_resolve_path(context.workspace_path, desktop_rel)
            full_mobile = safe_resolve_path(context.workspace_path, mobile_rel)
            os.makedirs(os.path.dirname(full_desktop), exist_ok=True)

            # 1. Capture visual previews via sandbox (with graceful fallback)
            try:
                render_res = await sandbox_client.render_workspace_html(
                    full_html, full_desktop, full_mobile
                )
            except Exception as r_err:
                render_res = {"status": "skipped", "message": f"Sandbox preview offline: {r_err}"}

            # 2. Run structural & accessibility checks
            try:
                checks_res = await sandbox_client.run_checks(html_content)
            except Exception:
                checks_res = _local_static_audit(html_content)

            # 3. Formulate findings
            issues: List[str] = checks_res.get("issues", [])
            blocking_faults = [iss for iss in issues if "viewport" in iss.lower() or "doctype" in iss.lower()]
            advisory = [iss for iss in issues if iss not in blocking_faults]

            score = max(0, 100 - (len(blocking_faults) * 25) - (len(advisory) * 10))

            return {
                "status": "passed" if len(blocking_faults) == 0 else "blocked",
                "quality_score": score,
                "html_path": html_rel,
                "desktop_preview": desktop_rel,
                "mobile_preview": mobile_rel,
                "blocking_faults": blocking_faults,
                "advisory_notes": advisory,
                "a11y_status": checks_res.get("a11y_status", "passed"),
                "motion_status": checks_res.get("motion_status", "not_checked"),
                "motion_families": checks_res.get("motion_families", []),
                "render_status": render_res.get("status", "success"),
            }
        except Exception as exc:
            return {"error": f"Lỗi audit thiết kế: {str(exc)}"}


tool_registry.register(AuditWebDesignTool())
