"""Database entities for reusable design references."""

import uuid
from datetime import datetime, timezone
from typing import List, Optional

from sqlalchemy import DateTime, ForeignKey, String, Text, text
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import Mapped, mapped_column

from app.db.session import Base


class DesignReference(Base):
    """A captured external website reference attached to a project library."""

    __tablename__ = "design_references"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    project_id: Mapped[str] = mapped_column(
        ForeignKey("projects.id", ondelete="CASCADE"), index=True
    )
    source_url: Mapped[str] = mapped_column(String(1000))
    title: Mapped[str] = mapped_column(String(200))
    notes: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    tags: Mapped[List[str]] = mapped_column(
        JSONB, default=list, server_default=text("'[]'::jsonb")
    )
    desktop_path: Mapped[str] = mapped_column(String(500))
    mobile_path: Mapped[str] = mapped_column(String(500))
    snapshot_path: Mapped[str] = mapped_column(String(500))
    page_summary: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    captured_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), default=lambda: datetime.now(timezone.utc)
    )
