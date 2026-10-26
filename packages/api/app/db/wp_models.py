"""SQLAlchemy models for WordPress site lifecycle management."""

import uuid
from datetime import datetime, timezone
from typing import Optional

from sqlalchemy import DateTime, ForeignKey, String, Text
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.db.session import Base


def utc_now() -> datetime:
    """Helper returning timezone-aware current UTC time."""
    return datetime.now(timezone.utc)


class WordPressSite(Base):
    """WordPress container environment associated with a project."""

    __tablename__ = "wordpress_sites"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    project_id: Mapped[str] = mapped_column(
        ForeignKey("projects.id", ondelete="CASCADE"), index=True
    )
    container_name: Mapped[str] = mapped_column(String(100), unique=True, index=True)
    container_id: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    site_url: Mapped[str] = mapped_column(String(255), default="")
    admin_url: Mapped[str] = mapped_column(String(255), default="")
    admin_user: Mapped[str] = mapped_column(String(50), default="admin")
    admin_password: Mapped[str] = mapped_column(String(100), default="")
    db_name: Mapped[str] = mapped_column(String(64), default="")
    status: Mapped[str] = mapped_column(String(20), default="pending", index=True)
    port: Mapped[Optional[int]] = mapped_column(nullable=True)
    last_backup_path: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    error_message: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now, onupdate=utc_now)
