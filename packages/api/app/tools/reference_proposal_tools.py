"""Offer verified public website references and pause for the user's choice."""

from typing import Any, Dict, List
from urllib.parse import urlparse

import httpx

from app.services.gate_service import gate_service
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.web_tools import extract_metadata, safe_fetch


class ProposeReferencesTool(BaseTool):
    """Verify up to three candidate sites, then open a selection gate."""

    name = "propose_references"
    description = (
        "Xác minh tối đa 3 website reference và trình cho user chọn. "
        "Dùng sau khi sitemap được duyệt nếu user chưa đưa URL."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "candidates": {
                "type": "array", "minItems": 1, "maxItems": 3,
                "items": {"type": "object", "properties": {
                    "title": {"type": "string"}, "url": {"type": "string"},
                    "rationale": {"type": "string"}, "study_focus": {"type": "string"},
                }, "required": ["url", "rationale", "study_focus"]},
            },
        },
        "required": ["candidates"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        """Validate candidate URLs and pause execution until user approval."""
        raw = kwargs.get("candidates")
        if not isinstance(raw, list) or not 1 <= len(raw) <= 3:
            return {"error": "Cần đề xuất từ 1 đến 3 reference."}
        try:
            candidates = await self._verify(raw)
        except (httpx.HTTPError, OSError, ValueError, PermissionError) as exc:
            return {"error": f"Không xác minh được reference: {exc}"}
        approval = await gate_service.open_gate(
            db=context.db, run_id=context.run_id, gate_type="reference",
            title="Chọn website tham khảo", slug="design-references",
            decision_needed="Chọn ít nhất một website để lưu vào thư viện và phân tích.",
            recommendation="Agent đã kiểm tra URL trả về trang HTML công khai; ảnh chụp và phân tích sẽ chạy sau khi bạn chọn.",
            example={"reference_candidates": candidates},
        )
        return {
            "status": "waiting_gate_reference", "pause": True,
            "gate_id": approval.id, "gate_type": "reference",
            "presentation_markdown": gate_service.format_gate_markdown(approval.payload),
        }

    @staticmethod
    async def _verify(raw: List[Dict[str, Any]]) -> List[Dict[str, str]]:
        """Fetch each candidate through the SSRF-safe web client and normalize metadata."""
        candidates: List[Dict[str, str]] = []
        async with httpx.AsyncClient(
            headers={"User-Agent": "AlignHubReferenceBot/1.0"}, timeout=15.0
        ) as client:
            for idx, item in enumerate(raw, 1):
                if not isinstance(item, dict):
                    continue
                url = str(item.get("url", "")).strip()
                response = await safe_fetch(client, url)
                if response.status_code >= 400 or "text/html" not in response.headers.get("content-type", ""):
                    continue
                final_url = str(response.url)
                metadata = extract_metadata(response.text[:500_000])
                title = str(item.get("title") or metadata.get("title") or urlparse(final_url).netloc)
                candidates.append({
                    "id": f"ref-{idx}", "title": title[:200], "url": final_url,
                    "rationale": str(item.get("rationale", ""))[:500],
                    "study_focus": str(item.get("study_focus", ""))[:300],
                    "verified_title": str(metadata.get("title") or title)[:200],
                })
        if not candidates:
            raise ValueError("Không có URL nào trả về trang HTML hợp lệ.")
        return candidates


tool_registry.register(ProposeReferencesTool())
