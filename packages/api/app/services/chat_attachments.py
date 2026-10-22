"""Validate, store, and expose files attached to project chat messages."""

import os
import uuid
from pathlib import PurePath
from typing import Any, Dict

from fastapi import UploadFile
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import LibraryItem
from app.services.workspace import workspace_service
from app.tools.file_tools import safe_resolve_path

MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024
SUPPORTED_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".webp", ".gif", ".pdf", ".txt", ".md",
    ".csv", ".json", ".html", ".css", ".js", ".ts", ".tsx", ".jsx",
    ".py", ".yaml", ".yml", ".xml", ".log",
}
IMAGE_SIGNATURES = {
    ".png": (b"\x89PNG\r\n\x1a\n",),
    ".jpg": (b"\xff\xd8\xff",),
    ".jpeg": (b"\xff\xd8\xff",),
    ".webp": (b"RIFF",),
    ".gif": (b"GIF87a", b"GIF89a"),
    ".pdf": (b"%PDF-",),
}


class ChatAttachmentService:
    """Store bounded, project-scoped chat attachments in the workspace."""

    async def upload(self, project_id: str, file: UploadFile, db: AsyncSession) -> Dict[str, Any]:
        """Validate and save one user-provided image, PDF, or text file."""
        filename = PurePath(file.filename or "attachment").name[:180]
        extension = PurePath(filename).suffix.lower()
        if extension not in SUPPORTED_EXTENSIONS:
            raise ValueError("Định dạng chưa hỗ trợ. Hãy dùng ảnh, PDF hoặc file văn bản/mã nguồn.")
        content = await file.read(MAX_ATTACHMENT_BYTES + 1)
        if not content or len(content) > MAX_ATTACHMENT_BYTES:
            raise ValueError("File rỗng hoặc vượt quá giới hạn 20 MB.")
        self._validate_signature(extension, content)
        if extension not in IMAGE_SIGNATURES and extension != ".pdf":
            self._validate_text(content)
        relative_path = f"uploads/chat/{uuid.uuid4().hex}{extension}"
        workspace = workspace_service.init_project_workspace(project_id, refresh=True)
        target = safe_resolve_path(workspace, relative_path)
        os.makedirs(os.path.dirname(target), exist_ok=True)
        with open(target, "wb") as uploaded:
            uploaded.write(content)
        item = LibraryItem(
            project_id=project_id, type="upload", tag="chat_attachment",
            description=filename, path=relative_path,
        )
        db.add(item)
        await db.commit()
        workspace_service.commit_step(project_id, f"chat: upload {extension} attachment")
        return {
            "id": item.id, "filename": filename, "content_type": self._content_type(extension),
            "size": len(content), "path": relative_path,
        }

    @staticmethod
    def _validate_signature(extension: str, content: bytes) -> None:
        """Reject files whose bytes do not match their claimed image or PDF extension."""
        signatures = IMAGE_SIGNATURES.get(extension)
        if signatures and not any(content.startswith(prefix) for prefix in signatures):
            raise ValueError("Nội dung file không khớp với định dạng đã chọn.")
        if extension == ".webp" and content[8:12] != b"WEBP":
            raise ValueError("Ảnh WebP không hợp lệ.")

    @staticmethod
    def _validate_text(content: bytes) -> None:
        """Require supported source and document files to contain UTF-8 text."""
        try:
            content.decode("utf-8")
        except UnicodeDecodeError as exc:
            raise ValueError("File văn bản phải dùng mã hóa UTF-8.") from exc

    @staticmethod
    def _content_type(extension: str) -> str:
        """Return a safe MIME type from the accepted extension list."""
        if extension in IMAGE_SIGNATURES:
            return {".jpg": "image/jpeg", ".jpeg": "image/jpeg"}.get(
                extension, "application/pdf" if extension == ".pdf" else f"image/{extension[1:]}"
            )
        return ".pdf" == extension and "application/pdf" or "text/plain"


chat_attachment_service = ChatAttachmentService()
