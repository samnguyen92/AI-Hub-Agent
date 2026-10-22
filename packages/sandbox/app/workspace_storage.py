"""Synchronize temporary Playwright workspace files with private Supabase Storage."""

import io
import os
import shutil
import tempfile
import zipfile
from urllib.parse import quote

import httpx

ROOT = os.path.realpath(os.getenv("WORKSPACES_ROOT", "/workspaces"))
SUPABASE_URL = os.getenv("SUPABASE_URL", "").rstrip("/")
SUPABASE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY", "")
BUCKET = os.getenv("SUPABASE_STORAGE_BUCKET", "hub-workspaces")
MAX_ARCHIVE_BYTES = int(os.getenv("WORKSPACE_ARCHIVE_MAX_BYTES", "41943040"))


def _project(path: str) -> tuple[str, str]:
    """Resolve and validate a workspace file path, returning project ID and root."""
    resolved = os.path.realpath(path)
    if os.path.commonpath((ROOT, resolved)) != ROOT:
        raise ValueError("Path is outside the workspace root")
    relative = os.path.relpath(resolved, ROOT)
    project_id = relative.split(os.sep, 1)[0]
    if project_id in ("", ".", ".."):
        raise ValueError("Path does not identify a project workspace")
    return project_id, os.path.join(ROOT, project_id)


def _object_url(project_id: str) -> str:
    """Build the workspace archive URL for one project."""
    key = quote(f"projects/{project_id}/workspace.zip", safe="/")
    return f"{SUPABASE_URL}/storage/v1/object/{BUCKET}/{key}"


def _headers() -> dict[str, str]:
    """Build privileged Storage headers from server-only settings."""
    return {"Authorization": f"Bearer {SUPABASE_KEY}", "apikey": SUPABASE_KEY}


def restore_for_path(path: str) -> None:
    """Restore the latest snapshot before a sandbox operation accesses a project."""
    if not (SUPABASE_URL and SUPABASE_KEY):
        return
    project_id, project_root = _project(path)
    response = httpx.get(_object_url(project_id), headers=_headers(), timeout=60)
    if response.status_code == 404:
        os.makedirs(project_root, exist_ok=True)
        return
    response.raise_for_status()
    shutil.rmtree(project_root, ignore_errors=True)
    os.makedirs(project_root, exist_ok=True)
    with zipfile.ZipFile(io.BytesIO(response.content)) as archive:
        for item in archive.infolist():
            destination = os.path.realpath(os.path.join(project_root, item.filename))
            if os.path.commonpath((project_root, destination)) != project_root:
                raise ValueError("Unsafe path in workspace archive")
        archive.extractall(project_root)


def save_for_path(path: str) -> None:
    """Upload the current project archive after a sandbox operation writes files."""
    if not (SUPABASE_URL and SUPABASE_KEY):
        return
    project_id, project_root = _project(path)
    with tempfile.SpooledTemporaryFile(max_size=4 * 1024 * 1024) as buffer:
        with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as archive:
            for current, directories, files in os.walk(project_root):
                directories[:] = [
                    name for name in directories if name not in {".git", "__pycache__"}
                ]
                for filename in files:
                    filepath = os.path.join(current, filename)
                    archive.write(filepath, os.path.relpath(filepath, project_root))
        buffer.seek(0, os.SEEK_END)
        if buffer.tell() > MAX_ARCHIVE_BYTES:
            raise ValueError("Workspace archive exceeds configured Supabase Storage limit")
        buffer.seek(0)
        response = httpx.post(
            _object_url(project_id),
            headers={**_headers(), "Content-Type": "application/zip", "x-upsert": "true"},
            content=buffer.read(),
            timeout=120,
        )
        response.raise_for_status()
