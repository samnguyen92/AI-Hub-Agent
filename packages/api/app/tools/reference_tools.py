"""Tools for capturing and storing reusable visual references."""

import json
import os
import uuid
from typing import Any, Dict, List
from urllib.parse import urlparse

import httpx
from sqlalchemy import select

from app.db.reference_models import DesignReference
from app.services.sandbox import sandbox_client
from app.services.workspace import workspace_service
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path
from app.tools.web_tools import extract_metadata, safe_fetch


class CaptureReferenceTool(BaseTool):
    """Capture a public website and keep its screenshots and notes in the project library."""

    name = "capture_reference"
    description = (
        "Quét một website tham khảo an toàn, chụp desktop/mobile, lưu snapshot và metadata "
        "vào thư viện project để Design Agent dùng lại."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "url": {"type": "string", "description": "URL website tham khảo"},
            "title": {"type": "string", "description": "Tên dễ nhận biết cho reference"},
            "notes": {"type": "string", "description": "Điểm muốn tham khảo từ website"},
            "tags": {"type": "array", "items": {"type": "string"}},
        },
        "required": ["url"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        """Fetch, screenshot, and register a source page as a project reference."""
        url = str(kwargs["url"]).strip()
        notes = str(kwargs.get("notes") or "").strip()
        tags = self._clean_tags(kwargs.get("tags", []))
        reference_id = uuid.uuid4().hex
        try:
            capture_data = await self._capture_source(context, url, reference_id, kwargs)
            return await self._save_reference(context, capture_data, notes, tags)
        except (httpx.HTTPError, OSError, ValueError) as exc:
            return {"error": f"Không thể lưu reference: {exc}"}

    async def _capture_source(
        self, context: ToolContext, url: str, reference_id: str, kwargs: Dict[str, Any]
    ) -> Dict[str, Any]:
        """Fetch source HTML and capture its public page at desktop and mobile sizes."""
        async with httpx.AsyncClient(
            headers={"User-Agent": "AlignHubReferenceBot/1.0"}, timeout=20.0
        ) as client:
            response = await safe_fetch(client, url)
        if "text/html" not in response.headers.get("content-type", ""):
            raise ValueError("Reference URL phải trả về một trang HTML.")

        html = response.text[:1_000_000]
        metadata = extract_metadata(html)
        source_url = str(response.url)
        existing = await context.db.execute(
            select(DesignReference).where(
                DesignReference.project_id == context.project_id,
                DesignReference.source_url == source_url,
            )
        )
        duplicate = existing.scalars().first()
        if duplicate:
            raise ValueError(f"Reference đã có trong thư viện: {duplicate.title} (ID {duplicate.id}).")
        title = str(kwargs.get("title") or metadata.get("title") or urlparse(source_url).netloc)
        title = title.strip()[:200]
        folder = f"references/{reference_id}"
        paths = {name: f"{folder}/{name}" for name in ("desktop.png", "mobile.png", "source.html")}
        resolved = {name: safe_resolve_path(context.workspace_path, path) for name, path in paths.items()}
        os.makedirs(os.path.dirname(resolved["desktop.png"]), exist_ok=True)
        capture = await sandbox_client.take_screenshot(
            source_url, resolved["desktop.png"], resolved["mobile.png"]
        )
        with open(resolved["source.html"], "w", encoding="utf-8") as snapshot_file:
            snapshot_file.write(html)
        return {
            "source_url": source_url, "title": title, "metadata": metadata,
            "capture": capture, "html_paths": paths,
        }

    async def _save_reference(
        self, context: ToolContext, data: Dict[str, Any], notes: str, tags: List[str]
    ) -> Dict[str, Any]:
        """Persist capture metadata in the project database and return library data."""
        paths = data["html_paths"]
        summary = self._summarize_page(data["metadata"], data["capture"])
        commit = workspace_service.commit_step(
            context.project_id, f"reference: capture {data['title'][:60]}"
        )
        reference = DesignReference(
            project_id=context.project_id, source_url=data["source_url"], title=data["title"],
            notes=notes or None, tags=tags,
            desktop_path=paths["desktop.png"], mobile_path=paths["mobile.png"],
            snapshot_path=paths["source.html"], page_summary=summary,
        )
        context.db.add(reference)
        await context.db.commit()
        await context.db.refresh(reference)
        return {
            "status": "saved", "reference_id": reference.id, "title": reference.title,
            "source_url": reference.source_url, "tags": tags, "notes": notes,
            "page_summary": summary, "desktop_path": reference.desktop_path,
            "mobile_path": reference.mobile_path, "snapshot_path": reference.snapshot_path,
            "git_commit": commit,
        }

    @staticmethod
    def _clean_tags(raw_tags: Any) -> List[str]:
        """Normalize and bound reference tags supplied by the model."""
        if not isinstance(raw_tags, list):
            return []
        return list(dict.fromkeys(str(tag).strip().lower()[:40] for tag in raw_tags if str(tag).strip()))[:12]

    @staticmethod
    def _summarize_page(metadata: Dict[str, Any], capture: Dict[str, Any]) -> str:
        """Create a compact visual and semantic summary from the browser capture."""
        headings = metadata.get("h1") or []
        observations = {
            "metadata": {
                "title": metadata.get("title") or "unavailable",
                "description": metadata.get("meta_description") or "unavailable",
                "canonical": metadata.get("canonical") or "unavailable",
                "h1": headings[:5],
            },
            "desktop": CaptureReferenceTool._compact_inspection(
                capture.get("desktop_inspection", {})
            ),
            "mobile": CaptureReferenceTool._compact_inspection(
                capture.get("mobile_inspection", {})
            ),
        }
        return json.dumps(observations, ensure_ascii=False, indent=2)

    @staticmethod
    def _compact_inspection(inspection: Any) -> Dict[str, Any]:
        """Keep the first viewport findings concise enough for agent context."""
        if not isinstance(inspection, dict):
            return {}
        return {
            "viewport": inspection.get("viewport"),
            "page": inspection.get("page"),
            "headings": inspection.get("headings", [])[:10],
            "visible_elements": inspection.get("visible_elements", [])[:10],
            "fonts": inspection.get("fonts", [])[:6],
        }


tool_registry.register(CaptureReferenceTool())
