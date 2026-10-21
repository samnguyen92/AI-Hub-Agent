"""Shared Project Library tools for cross-agent collaboration."""

import os
from typing import Any, Dict
from sqlalchemy import or_, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Artifact, LibraryItem
from app.db.reference_models import DesignReference
from app.services.workspace import workspace_service
from app.tools.base import BaseTool, ToolContext, tool_registry


class ListLibraryTool(BaseTool):
    """Tool to inspect available items in the project library."""

    name = "list_library"
    description = "Liệt kê danh sách tài liệu, uploads, snapshots và artifacts trong thư viện dự án."
    parameters_schema = {
        "type": "object",
        "properties": {
            "type_filter": {
                "type": "string",
                "description": "Lọc loại asset; dùng 'reference' để tìm website tham khảo đã lưu.",
            },
            "search_query": {
                "type": "string",
                "description": "Tìm references theo title, URL, notes hoặc tag.",
            },
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        type_filter = kwargs.get("type_filter")
        search_query = str(kwargs.get("search_query") or "").strip()
        query = select(LibraryItem).where(LibraryItem.project_id == context.project_id)
        if type_filter and type_filter != "reference":
            query = query.where(LibraryItem.type == type_filter)

        if type_filter == "reference":
            items = []
        else:
            res = await context.db.execute(query)
            items = res.scalars().all()

        art_query = select(Artifact).where(Artifact.project_id == context.project_id)
        art_res = await context.db.execute(art_query)
        artifacts = art_res.scalars().all() if type_filter != "reference" else []
        ref_query = (
            select(DesignReference)
            .where(DesignReference.project_id == context.project_id)
            .order_by(DesignReference.captured_at.desc())
            .limit(20)
        )
        if search_query:
            pattern = f"%{search_query}%"
            ref_query = ref_query.where(
                or_(
                    DesignReference.title.ilike(pattern),
                    DesignReference.source_url.ilike(pattern),
                    DesignReference.notes.ilike(pattern),
                    DesignReference.tags.contains([search_query.lower()]),
                )
            )
        ref_res = await context.db.execute(ref_query)
        references = ref_res.scalars().all() if not type_filter or type_filter == "reference" else []

        return {
            "library_items": [
                {"id": i.id, "type": i.type, "tag": i.tag, "description": i.description, "path": i.path}
                for i in items
            ],
            "artifacts": [
                {"artifact_id": a.artifact_id, "version": a.version, "path": a.path}
                for a in artifacts
            ],
            "references": [
                {
                    "id": ref.id,
                    "title": ref.title,
                    "source_url": ref.source_url,
                    "notes": ref.notes,
                    "tags": ref.tags or [],
                    "desktop_path": ref.desktop_path,
                    "mobile_path": ref.mobile_path,
                    "snapshot_path": ref.snapshot_path,
                    "page_summary": ref.page_summary,
                }
                for ref in references
            ],
        }


class ReadItemTool(BaseTool):
    """Tool to read the content of a specific library item or artifact."""

    name = "read_item"
    description = "Đọc nội dung một file trong thư viện dự án hoặc một phiên bản artifact."
    parameters_schema = {
        "type": "object",
        "properties": {
            "path": {
                "type": "string",
                "description": "Đường dẫn file cần đọc tương đối so với workspace",
            }
        },
        "required": ["path"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rel_path = kwargs.get("path", "")
        full_path = os.path.abspath(os.path.join(context.workspace_path, rel_path))
        if not full_path.startswith(os.path.abspath(context.workspace_path)):
            return {"error": "Bị từ chối: Đường dẫn nằm ngoài thư mục dự án (path traversal)."}

        if not os.path.exists(full_path):
            return {"error": f"Không tìm thấy file: {rel_path}"}

        try:
            with open(full_path, "r", encoding="utf-8") as f:
                content = f.read()
            return {"path": rel_path, "content": content, "size": len(content)}
        except Exception as exc:
            return {"error": f"Lỗi đọc file: {str(exc)}"}


class SaveArtifactTool(BaseTool):
    """Tool to save a generated artifact as a new version."""

    name = "save_artifact"
    description = "Lưu kết quả thành một phiên bản artifact mới trong thư viện dự án và commit Git."
    parameters_schema = {
        "type": "object",
        "properties": {
            "artifact_id": {"type": "string", "description": "Tên định danh artifact (vd: homepage-mockup)"},
            "file_path": {"type": "string", "description": "Đường dẫn lưu file trong workspace"},
            "content": {"type": "string", "description": "Nội dung file"},
        },
        "required": ["artifact_id", "file_path", "content"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        artifact_id = kwargs["artifact_id"]
        rel_path = kwargs["file_path"]
        content = kwargs["content"]

        full_path = os.path.abspath(os.path.join(context.workspace_path, rel_path))
        if not full_path.startswith(os.path.abspath(context.workspace_path)):
            return {"error": "Bị từ chối: Đường dẫn nằm ngoài thư mục dự án (path traversal)."}

        os.makedirs(os.path.dirname(full_path), exist_ok=True)
        with open(full_path, "w", encoding="utf-8") as f:
            f.write(content)

        # Determine version
        q = select(Artifact).where(
            Artifact.project_id == context.project_id,
            Artifact.artifact_id == artifact_id,
        ).order_by(Artifact.version.desc())
        res = await context.db.execute(q)
        last_artifact = res.scalars().first()
        new_version = (last_artifact.version + 1) if last_artifact else 1

        commit_hash = workspace_service.commit_step(
            context.project_id,
            f"agent({context.agent_key}): save artifact {artifact_id} v{new_version}",
        )

        artifact = Artifact(
            project_id=context.project_id,
            artifact_id=artifact_id,
            version=new_version,
            parent_version=last_artifact.version if last_artifact else None,
            run_id=context.run_id,
            path=rel_path,
            git_commit=commit_hash,
        )
        context.db.add(artifact)
        await context.db.commit()

        return {
            "artifact_id": artifact_id,
            "version": new_version,
            "path": rel_path,
            "git_commit": commit_hash,
            "status": "saved",
        }


class ViewImageTool(BaseTool):
    """Tool to inspect an image file."""

    name = "view_image"
    description = "Xem thông tin file ảnh trong workspace để mô hình phân tích."
    parameters_schema = {
        "type": "object",
        "properties": {
            "image_path": {"type": "string", "description": "Đường dẫn file ảnh"}
        },
        "required": ["image_path"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rel_path = kwargs.get("image_path", "")
        full_path = os.path.abspath(os.path.join(context.workspace_path, rel_path))
        if not full_path.startswith(os.path.abspath(context.workspace_path)):
            return {"error": "Bị từ chối: Path traversal"}

        if not os.path.exists(full_path):
            return {"error": f"Không tìm thấy file ảnh: {rel_path}"}

        size = os.path.getsize(full_path)
        return {"image_path": rel_path, "size_bytes": size, "status": "accessible"}


# Register tools
tool_registry.register(ListLibraryTool())
tool_registry.register(ReadItemTool())
tool_registry.register(SaveArtifactTool())
tool_registry.register(ViewImageTool())
