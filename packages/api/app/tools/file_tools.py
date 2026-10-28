"""Workspace file manipulation tools with strict path traversal defense."""

import os
from typing import Any, Dict, List
from app.services.workspace import workspace_service
from app.tools.base import BaseTool, ToolContext, tool_registry


def safe_resolve_path(workspace_root: str, relative_path: str) -> str:
    """Safely resolve relative path and verify it stays within workspace root."""
    root_abs = os.path.realpath(workspace_root)
    resolved = os.path.realpath(os.path.join(root_abs, relative_path))
    if not (resolved == root_abs or resolved.startswith(root_abs + os.sep)):
        raise PermissionError(
            f"Bị từ chối: Đường dẫn '{relative_path}' vượt ra ngoài workspace của dự án."
        )
    return resolved


class ReadFileTool(BaseTool):
    """Tool to read file contents within workspace."""

    name = "read_file"
    description = "Đọc nội dung một file văn bản trong workspace của dự án."
    parameters_schema = {
        "type": "object",
        "properties": {
            "path": {"type": "string", "description": "Đường dẫn file cần đọc"}
        },
        "required": ["path"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rel_path = kwargs.get("path", "")
        try:
            full_path = safe_resolve_path(context.workspace_path, rel_path)
            if not os.path.exists(full_path):
                return {"error": f"File không tồn tại: {rel_path}"}
            with open(full_path, "r", encoding="utf-8") as f:
                content = f.read()
            return {"path": rel_path, "content": content}
        except PermissionError as p_err:
            return {"error": str(p_err)}
        except Exception as exc:
            return {"error": f"Lỗi đọc file: {str(exc)}"}


class WriteFileTool(BaseTool):
    """Tool to write file contents within workspace and commit."""

    name = "write_file"
    description = "Ghi hoặc cập nhật file trong workspace và tự động commit Git."
    parameters_schema = {
        "type": "object",
        "properties": {
            "path": {"type": "string", "description": "Đường dẫn file cần ghi"},
            "content": {"type": "string", "description": "Nội dung cần ghi"},
        },
        "required": ["path", "content"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rel_path = kwargs.get("path", "")
        content = kwargs.get("content", "")
        try:
            full_path = safe_resolve_path(context.workspace_path, rel_path)
            os.makedirs(os.path.dirname(full_path), exist_ok=True)
            with open(full_path, "w", encoding="utf-8") as f:
                f.write(content)

            commit_hash = workspace_service.commit_step(
                context.project_id,
                f"agent({context.agent_key}): write {rel_path}",
            )
            return {"path": rel_path, "status": "success", "git_commit": commit_hash}
        except PermissionError as p_err:
            return {"error": str(p_err)}
        except Exception as exc:
            return {"error": f"Lỗi ghi file: {str(exc)}"}


class ListFilesTool(BaseTool):
    """Tool to list files inside workspace."""

    name = "list_files"
    description = "Liệt kê danh sách các file và thư mục con trong workspace."
    parameters_schema = {
        "type": "object",
        "properties": {
            "directory": {
                "type": "string",
                "description": "Thư mục cần xem (mặc định là thư mục gốc '')",
            }
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        dir_rel = kwargs.get("directory", "")
        try:
            target_dir = safe_resolve_path(context.workspace_path, dir_rel)
            if not os.path.exists(target_dir):
                return {"error": f"Thư mục không tồn tại: {dir_rel}"}

            items: List[Dict[str, Any]] = []
            for root, dirs, files in os.walk(target_dir):
                # Ignore .git folder
                if ".git" in dirs:
                    dirs.remove(".git")
                for f in files:
                    full = os.path.join(root, f)
                    rel = os.path.relpath(full, context.workspace_path)
                    items.append({"path": rel, "type": "file", "size": os.path.getsize(full)})
            return {"files": items, "count": len(items)}
        except PermissionError as p_err:
            return {"error": str(p_err)}


class ApplyPatchTool(BaseTool):
    """Tool to apply diff changes to existing files."""

    name = "apply_patch"
    description = "Áp dụng nội dung chỉnh sửa vào file mã nguồn và commit."
    parameters_schema = {
        "type": "object",
        "properties": {
            "path": {"type": "string", "description": "Đường dẫn file cần sửa"},
            "new_content": {"type": "string", "description": "Nội dung cập nhật hoàn chỉnh"},
        },
        "required": ["path", "new_content"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rel_path = kwargs.get("path", "")
        new_content = kwargs.get("new_content", "")
        try:
            full_path = safe_resolve_path(context.workspace_path, rel_path)
            with open(full_path, "w", encoding="utf-8") as f:
                f.write(new_content)

            commit_hash = workspace_service.commit_step(
                context.project_id,
                f"agent({context.agent_key}): apply patch to {rel_path}",
            )
            return {"path": rel_path, "status": "patched", "git_commit": commit_hash}
        except PermissionError as p_err:
            return {"error": str(p_err)}


import zipfile
from sqlalchemy import select
from app.db.models import Approval, Run


def perform_export(workspace_path: str, arch_name: str, project_id: str) -> Dict[str, Any]:
    """Internal function to build ZIP archive once approved."""
    if not arch_name.endswith(".zip"):
        arch_name += ".zip"
    try:
        rel_zip = f"exports/{arch_name}"
        full_zip = safe_resolve_path(workspace_path, rel_zip)
        os.makedirs(os.path.dirname(full_zip), exist_ok=True)

        with zipfile.ZipFile(full_zip, "w", zipfile.ZIP_DEFLATED) as zf:
            for root, dirs, files in os.walk(workspace_path):
                for excluded in (".git", "exports", "node_modules", "__pycache__"):
                    if excluded in dirs:
                        dirs.remove(excluded)
                for file in files:
                    full_f = os.path.join(root, file)
                    rel_f = os.path.relpath(full_f, workspace_path)
                    zf.write(full_f, rel_f)

        return {
            "status": "exported",
            "archive_path": rel_zip,
            "download_url": f"/api/backend/projects/{project_id}/files?path={rel_zip}",
        }
    except Exception as exc:
        return {"error": f"Lỗi đóng gói ZIP: {str(exc)}"}


class ExportZipTool(BaseTool):
    """Tool to package workspace into a ZIP archive, gated by approval."""

    name = "export_zip"
    description = "Đóng gói toàn bộ workspace thành file ZIP (yêu cầu phê duyệt từ người dùng)."
    parameters_schema = {
        "type": "object",
        "properties": {
            "archive_name": {
                "type": "string",
                "description": "Tên file ZIP (vd: website_release.zip)",
            },
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        arch_name = kwargs.get("archive_name") or "project_export.zip"
        if not arch_name.endswith(".zip"):
            arch_name += ".zip"

        # Export always requires approval through Gate/Approval mechanism
        if context.db:
            approval = Approval(
                run_id=context.run_id,
                action_type="export_zip",
                payload={"archive_name": arch_name, "project_id": context.project_id},
                status="pending",
            )
            context.db.add(approval)
            q = select(Run).where(Run.id == context.run_id)
            run = (await context.db.execute(q)).scalars().first()
            if run:
                run.status = "waiting_approval"
            await context.db.commit()

            return {
                "status": "waiting_approval",
                "pause": True,
                "approval_id": approval.id,
                "action": "export_zip",
                "message": f"Hành động xuất ZIP '{arch_name}' đã được gửi vào hàng đợi phê duyệt.",
            }

        return perform_export(context.workspace_path, arch_name, context.project_id)


# Register tools
tool_registry.register(ReadFileTool())
tool_registry.register(WriteFileTool())
tool_registry.register(ListFilesTool())
tool_registry.register(ApplyPatchTool())
tool_registry.register(ExportZipTool())
