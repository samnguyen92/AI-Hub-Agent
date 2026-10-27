"""SQLAlchemy models for project membership and collaboration."""

import uuid
from datetime import datetime, timezone
from typing import Optional
from sqlalchemy import DateTime, ForeignKey, String
from sqlalchemy.orm import Mapped, mapped_column
from app.db.session import Base


def utc_now() -> datetime:
    """Helper returning timezone-aware current UTC time."""
    return datetime.now(timezone.utc)


class ProjectMember(Base):
    """Member or collaborator assigned to a project."""

    __tablename__ = "project_members"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    project_id: Mapped[str] = mapped_column(
        ForeignKey("projects.id", ondelete="CASCADE"), index=True
    )
    user_id: Mapped[Optional[str]] = mapped_column(
        ForeignKey("users.id", ondelete="SET NULL"), nullable=True, index=True
    )
    email: Mapped[str] = mapped_column(String(255), index=True)
    role: Mapped[str] = mapped_column(String(20), default="editor")  # owner, editor, viewer
    status: Mapped[str] = mapped_column(String(20), default="active")  # pending, active
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)
