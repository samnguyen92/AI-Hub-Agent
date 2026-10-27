"""Persist project workspace snapshots in a private Supabase Storage bucket."""

import io
import os
import tempfile
import zipfile
from urllib.parse import quote

import httpx

from app.config import settings


class WorkspaceStorage:
    """Upload and restore project archives when running on stateless hosts."""

    def __init__(self) -> None:
        self.enabled = bool(settings.supabase_url and settings.supabase_service_role_key)

    def _url(self, project_id: str) -> str:
        """Build the object URL using a project-scoped, traversal-safe key."""
        if not project_id or "/" in project_id or ".." in project_id:
            raise ValueError("Invalid project ID for workspace storage")
        key = quote(f"projects/{project_id}/workspace.zip", safe="/")
        return f"{settings.supabase_url.rstrip('/')}/storage/v1/object/{settings.supabase_storage_bucket}/{key}"

    def _headers(self) -> dict[str, str]:
        """Return server-side credentials for the Supabase Storage API."""
        key = settings.supabase_service_role_key
        return {"Authorization": f"Bearer {key}", "apikey": key}

    @staticmethod
    def _is_not_found(response: httpx.Response) -> bool:
        """Check if Supabase Storage returned an object not found response."""
        if response.status_code == 404:
            return True
        if response.status_code == 400:
            try:
                data = response.json()
                return (
                    str(data.get("statusCode")) == "404"
                    or data.get("error") == "not_found"
                    or data.get("code") == "NoSuchKey"
                    or "not found" in str(data.get("message", "")).lower()
                )
            except Exception:
                return False
        return False

    def download(self, project_id: str, target_dir: str) -> bool:
        """Restore a workspace snapshot into its local scratch directory."""
        if not self.enabled:
            return False
        response = httpx.get(self._url(project_id), headers=self._headers(), timeout=60)
        if self._is_not_found(response):
            return False
        response.raise_for_status()
        with zipfile.ZipFile(io.BytesIO(response.content)) as archive:
            root = os.path.realpath(target_dir)
            for member in archive.infolist():
                destination = os.path.realpath(os.path.join(root, member.filename))
                if os.path.commonpath((root, destination)) != root:
                    raise ValueError("Unsafe path in workspace archive")
            archive.extractall(root)
        return True

    def upload(self, project_id: str, source_dir: str) -> None:
        """Save the complete workspace, including Git history, as an object."""
        if not self.enabled:
            return
        with tempfile.SpooledTemporaryFile(max_size=4 * 1024 * 1024) as archive_buffer:
            with zipfile.ZipFile(archive_buffer, "w", zipfile.ZIP_DEFLATED) as archive:
                self._add_workspace_files(archive, source_dir)
            archive_buffer.seek(0, os.SEEK_END)
            archive_size = archive_buffer.tell()
            if archive_size > settings.workspace_archive_max_bytes:
                raise ValueError("Workspace archive exceeds configured Supabase Storage limit")
            archive_buffer.seek(0)
            response = httpx.post(
                self._url(project_id),
                headers={**self._headers(), "Content-Type": "application/zip", "x-upsert": "true"},
                content=archive_buffer.read(),
                timeout=120,
            )
            response.raise_for_status()

    @staticmethod
    def _add_workspace_files(archive: zipfile.ZipFile, source_dir: str) -> None:
        """Add user files while excluding disposable Git history and Python caches."""
        for current, directories, files in os.walk(source_dir):
            directories[:] = [
                name for name in directories if name not in {".git", "__pycache__"}
            ]
            for filename in files:
                path = os.path.join(current, filename)
                archive.write(path, os.path.relpath(path, source_dir))

    def delete(self, project_id: str) -> None:
        """Delete the workspace snapshot after its project is removed."""
        if not self.enabled:
            return
        response = httpx.delete(self._url(project_id), headers=self._headers(), timeout=30)
        if response.status_code in (200, 204) or self._is_not_found(response):
            return
        response.raise_for_status()


workspace_storage = WorkspaceStorage()
