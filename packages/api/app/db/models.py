"""SQLAlchemy ORM models representing the complete Hub AI Agent schema."""

import uuid
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional

from sqlalchemy import (
    Boolean,
    DateTime,
    Float,
    ForeignKey,
    Integer,
    String,
    Text,
)
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.db.session import Base


def utc_now() -> datetime:
    """Helper returning timezone-aware current UTC time."""
    return datetime.now(timezone.utc)


class User(Base):
    """User account entity."""

    __tablename__ = "users"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    github_login: Mapped[str] = mapped_column(String(100), unique=True, index=True)
    role: Mapped[str] = mapped_column(String(20), default="member")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    projects: Mapped[List["Project"]] = relationship("Project", back_populates="owner")
    telegram_links: Mapped[List["TelegramLink"]] = relationship(
        "TelegramLink", back_populates="user"
    )


class TelegramLink(Base):
    """Mapping between user and Telegram account."""

    __tablename__ = "telegram_links"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    user_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    telegram_id: Mapped[Optional[str]] = mapped_column(String(64), unique=True, index=True, nullable=True)
    link_code: Mapped[Optional[str]] = mapped_column(String(16), unique=True, index=True, nullable=True)
    is_confirmed: Mapped[bool] = mapped_column(Boolean, default=False)
    linked_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    user: Mapped["User"] = relationship("User", back_populates="telegram_links")


class Project(Base):
    """Project workspace boundary."""

    __tablename__ = "projects"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    owner_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    name: Mapped[str] = mapped_column(String(120), index=True)
    source_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    workspace_path: Mapped[str] = mapped_column(String(500))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    owner: Mapped["User"] = relationship("User", back_populates="projects")
    threads: Mapped[List["Thread"]] = relationship("Thread", back_populates="project")
    library_items: Mapped[List["LibraryItem"]] = relationship(
        "LibraryItem", back_populates="project"
    )
    artifacts: Mapped[List["Artifact"]] = relationship("Artifact", back_populates="project")
    notes: Mapped[List["ProjectNote"]] = relationship("ProjectNote", back_populates="project")


class LibraryItem(Base):
    """Items stored in shared project library."""

    __tablename__ = "library_items"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    project_id: Mapped[str] = mapped_column(
        ForeignKey("projects.id", ondelete="CASCADE"), index=True
    )
    type: Mapped[str] = mapped_column(String(50))  # upload, url_snapshot, connector_ref
    tag: Mapped[Optional[str]] = mapped_column(String(50), nullable=True)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    path: Mapped[str] = mapped_column(String(500))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    project: Mapped["Project"] = relationship("Project", back_populates="library_items")


class Agent(Base):
    """Agent definition and default runtime configuration."""

    __tablename__ = "agents"

    key: Mapped[str] = mapped_column(String(50), primary_key=True)
    model: Mapped[str] = mapped_column(String(100))
    rules_path: Mapped[str] = mapped_column(String(500))
    config: Mapped[Dict[str, Any]] = mapped_column(JSONB, default=dict)

    threads: Mapped[List["Thread"]] = relationship("Thread", back_populates="agent")


class Skill(Base):
    """Agent skill descriptor."""

    __tablename__ = "skills"

    key: Mapped[str] = mapped_column(String(100), primary_key=True)
    version: Mapped[str] = mapped_column(String(20), default="1.0.0")
    path: Mapped[str] = mapped_column(String(500))
    agent_key: Mapped[Optional[str]] = mapped_column(
        ForeignKey("agents.key", ondelete="SET NULL"), nullable=True
    )


class Thread(Base):
    """Chat session thread."""

    __tablename__ = "threads"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    project_id: Mapped[str] = mapped_column(
        ForeignKey("projects.id", ondelete="CASCADE"), index=True
    )
    agent_key: Mapped[str] = mapped_column(ForeignKey("agents.key"))
    channel: Mapped[str] = mapped_column(String(20), default="web")  # web, telegram
    title: Mapped[str] = mapped_column(String(200), default="New Thread")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    project: Mapped["Project"] = relationship("Project", back_populates="threads")
    agent: Mapped["Agent"] = relationship("Agent", back_populates="threads")
    messages: Mapped[List["Message"]] = relationship(
        "Message", back_populates="thread", cascade="all, delete-orphan"
    )
    runs: Mapped[List["Run"]] = relationship("Run", back_populates="thread")


class Message(Base):
    """Individual chat message inside a thread."""

    __tablename__ = "messages"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    thread_id: Mapped[str] = mapped_column(
        ForeignKey("threads.id", ondelete="CASCADE"), index=True
    )
    role: Mapped[str] = mapped_column(String(20))  # user, assistant, system
    content: Mapped[str] = mapped_column(Text)
    attachments: Mapped[List[Dict[str, Any]]] = mapped_column(JSONB, default=list)
    artifact_refs: Mapped[List[str]] = mapped_column(JSONB, default=list)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    thread: Mapped["Thread"] = relationship("Thread", back_populates="messages")


class Run(Base):
    """An agent execution run."""

    __tablename__ = "runs"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    thread_id: Mapped[str] = mapped_column(
        ForeignKey("threads.id", ondelete="CASCADE"), index=True
    )
    agent_key: Mapped[str] = mapped_column(String(50))
    parent_run_id: Mapped[Optional[str]] = mapped_column(String(36), nullable=True)
    status: Mapped[str] = mapped_column(
        String(30), default="queued", index=True
    )  # queued, running, completed, failed, waiting_approval
    cost_usd: Mapped[float] = mapped_column(Float, default=0.0)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    thread: Mapped["Thread"] = relationship("Thread", back_populates="runs")
    steps: Mapped[List["Step"]] = relationship(
        "Step", back_populates="run", cascade="all, delete-orphan"
    )


class Step(Base):
    """Individual tool execution step within a run."""

    __tablename__ = "steps"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    run_id: Mapped[str] = mapped_column(ForeignKey("runs.id", ondelete="CASCADE"), index=True)
    seq: Mapped[int] = mapped_column(Integer)
    tool: Mapped[str] = mapped_column(String(80))
    args: Mapped[Dict[str, Any]] = mapped_column(JSONB, default=dict)
    result: Mapped[Dict[str, Any]] = mapped_column(JSONB, default=dict)
    tokens: Mapped[int] = mapped_column(Integer, default=0)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    run: Mapped["Run"] = relationship("Run", back_populates="steps")


class Artifact(Base):
    """Versioned artifact produced by agent runs."""

    __tablename__ = "artifacts"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    project_id: Mapped[str] = mapped_column(
        ForeignKey("projects.id", ondelete="CASCADE"), index=True
    )
    artifact_id: Mapped[str] = mapped_column(String(100), index=True)
    version: Mapped[int] = mapped_column(Integer, default=1)
    parent_version: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)
    run_id: Mapped[Optional[str]] = mapped_column(ForeignKey("runs.id", ondelete="SET NULL"))
    path: Mapped[str] = mapped_column(String(500))
    git_commit: Mapped[Optional[str]] = mapped_column(String(40), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    project: Mapped["Project"] = relationship("Project", back_populates="artifacts")


class Approval(Base):
    """Human-in-the-loop approval record for external/critical actions."""

    __tablename__ = "approvals"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    run_id: Mapped[str] = mapped_column(ForeignKey("runs.id", ondelete="CASCADE"), index=True)
    decided_by: Mapped[Optional[str]] = mapped_column(
        ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    status: Mapped[str] = mapped_column(String(20), default="pending")  # pending, approved, rejected
    action_type: Mapped[str] = mapped_column(String(50), default="export_zip")
    payload: Mapped[Dict[str, Any]] = mapped_column(JSONB, default=dict)
    comment: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    run: Mapped["Run"] = relationship("Run")


class ProjectNote(Base):
    """Project-scoped memories and notes proposed by agents."""

    __tablename__ = "project_notes"

    id: Mapped[str] = mapped_column(
        String(36), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    project_id: Mapped[str] = mapped_column(
        ForeignKey("projects.id", ondelete="CASCADE"), index=True
    )
    content: Mapped[str] = mapped_column(Text)
    status: Mapped[str] = mapped_column(String(20), default="pending")  # pending, approved, rejected
    source_run_id: Mapped[Optional[str]] = mapped_column(
        ForeignKey("runs.id", ondelete="SET NULL"), nullable=True
    )
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utc_now)

    project: Mapped["Project"] = relationship("Project", back_populates="notes")
