"""API Router for Project operations."""

from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Project, ProjectNote, User
from app.db.session import get_db
from app.services.workspace import workspace_service

router = APIRouter(prefix="/projects", tags=["projects"])


class ProjectCreateRequest(BaseModel):
    name: str
    source_url: Optional[str] = None
    owner_github_login: str = "demo-user"


class ProjectResponse(BaseModel):
    id: str
    name: str
    source_url: Optional[str] = None
    workspace_path: str


@router.get("", response_model=List[ProjectResponse])
async def list_projects(db: AsyncSession = Depends(get_db)) -> List[ProjectResponse]:
    """Retrieve all active projects."""
    res = await db.execute(select(Project).order_by(Project.created_at.desc()))
    projects = res.scalars().all()
    return [
        ProjectResponse(
            id=p.id,
            name=p.name,
            source_url=p.source_url,
            workspace_path=p.workspace_path,
        )
        for p in projects
    ]


@router.post("", response_model=ProjectResponse)
async def create_project(
    req: ProjectCreateRequest, db: AsyncSession = Depends(get_db)
) -> ProjectResponse:
    """Create a new project workspace and database record."""
    # Ensure default user exists
    user_res = await db.execute(select(User).where(User.github_login == req.owner_github_login))
    user = user_res.scalars().first()
    if not user:
        user = User(github_login=req.owner_github_login, role="admin")
        db.add(user)
        await db.flush()

    project = Project(
        owner_id=user.id,
        name=req.name,
        source_url=req.source_url,
        workspace_path="",
    )
    db.add(project)
    await db.flush()

    workspace_path = workspace_service.init_project_workspace(project.id)
    project.workspace_path = workspace_path
    await db.commit()

    return ProjectResponse(
        id=project.id,
        name=project.name,
        source_url=project.source_url,
        workspace_path=project.workspace_path,
    )


from fastapi.responses import FileResponse
from app.tools.file_tools import safe_resolve_path
import os


@router.get("/{project_id}", response_model=ProjectResponse)
async def get_project(project_id: str, db: AsyncSession = Depends(get_db)) -> ProjectResponse:
    """Get project by ID."""
    res = await db.execute(select(Project).where(Project.id == project_id))
    project = res.scalars().first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    return ProjectResponse(
        id=project.id,
        name=project.name,
        source_url=project.source_url,
        workspace_path=project.workspace_path,
    )


@router.get("/{project_id}/files")
async def get_project_file(
    project_id: str,
    path: str,
    db: AsyncSession = Depends(get_db),
) -> FileResponse:
    """Safely return file from workspace for preview/rendering."""
    res = await db.execute(select(Project).where(Project.id == project_id))
    project = res.scalars().first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")

    try:
        full_path = safe_resolve_path(project.workspace_path, path)
        if not os.path.exists(full_path) or not os.path.isfile(full_path):
            raise HTTPException(status_code=404, detail=f"File not found: {path}")
        return FileResponse(full_path)
    except PermissionError:
        raise HTTPException(status_code=403, detail="Path traversal forbidden")


class ProjectNoteResponse(BaseModel):
    id: str
    project_id: str
    content: str
    status: str
    source_run_id: Optional[str] = None
    created_at: str


class NoteRespondRequest(BaseModel):
    action: str  # approve or reject


class NoteCreateRequest(BaseModel):
    content: str


@router.get("/{project_id}/notes", response_model=List[ProjectNoteResponse])
async def list_project_notes(
    project_id: str,
    status: Optional[str] = None,
    db: AsyncSession = Depends(get_db),
) -> List[ProjectNoteResponse]:
    """List memory notes for a given project."""
    q = select(ProjectNote).where(ProjectNote.project_id == project_id)
    if status:
        q = q.where(ProjectNote.status == status)
    q = q.order_by(ProjectNote.created_at.desc())
    res = await db.execute(q)
    notes = res.scalars().all()
    return [
        ProjectNoteResponse(
            id=n.id,
            project_id=n.project_id,
            content=n.content,
            status=n.status,
            source_run_id=n.source_run_id,
            created_at=n.created_at.isoformat() if n.created_at else "",
        )
        for n in notes
    ]


@router.post("/{project_id}/notes", response_model=ProjectNoteResponse)
async def create_project_note(
    project_id: str,
    req: NoteCreateRequest,
    db: AsyncSession = Depends(get_db),
) -> ProjectNoteResponse:
    """Manually add an approved note to project memory."""
    note = ProjectNote(
        project_id=project_id,
        content=req.content.strip(),
        status="approved",
    )
    db.add(note)
    await db.commit()
    await db.refresh(note)
    return ProjectNoteResponse(
        id=note.id,
        project_id=note.project_id,
        content=note.content,
        status=note.status,
        source_run_id=note.source_run_id,
        created_at=note.created_at.isoformat() if note.created_at else "",
    )


@router.post("/{project_id}/notes/{note_id}/respond", response_model=ProjectNoteResponse)
async def respond_to_note(
    project_id: str,
    note_id: str,
    req: NoteRespondRequest,
    db: AsyncSession = Depends(get_db),
) -> ProjectNoteResponse:
    """Approve or reject a proposed project note."""
    if req.action not in ("approve", "reject"):
        raise HTTPException(status_code=400, detail="Action must be 'approve' or 'reject'")

    res = await db.execute(
        select(ProjectNote).where(
            ProjectNote.id == note_id,
            ProjectNote.project_id == project_id,
        )
    )
    note = res.scalars().first()
    if not note:
        raise HTTPException(status_code=404, detail="Project note not found")

    note.status = "approved" if req.action == "approve" else "rejected"
    await db.commit()
    await db.refresh(note)
    return ProjectNoteResponse(
        id=note.id,
        project_id=note.project_id,
        content=note.content,
        status=note.status,
        source_run_id=note.source_run_id,
        created_at=note.created_at.isoformat() if note.created_at else "",
    )


class LearnRuleRequest(BaseModel):
    rule: str
    category: str = "general"


@router.post("/{project_id}/learn", response_model=ProjectNoteResponse)
async def teach_project_rule(
    project_id: str,
    req: LearnRuleRequest,
    db: AsyncSession = Depends(get_db),
) -> ProjectNoteResponse:
    """Explicitly teach the AI a permanent project rule or preference."""
    content = f"[LEARNED_RULE:{req.category.upper()}] {req.rule.strip()}"
    note = ProjectNote(
        project_id=project_id,
        content=content,
        status="approved",
    )
    db.add(note)
    await db.commit()
    await db.refresh(note)
    return ProjectNoteResponse(
        id=note.id,
        project_id=note.project_id,
        content=note.content,
        status=note.status,
        source_run_id=note.source_run_id,
        created_at=note.created_at.isoformat() if note.created_at else "",
    )
