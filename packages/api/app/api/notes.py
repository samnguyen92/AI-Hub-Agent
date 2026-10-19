"""API Router for Project Notes and Learned Rules."""

from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user, verify_project_ownership
from app.db.models import ProjectNote, User
from app.db.session import get_db

router = APIRouter(prefix="/projects", tags=["notes"])


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


class LearnRuleRequest(BaseModel):
    rule: str
    category: str = "general"


@router.get("/{project_id}/notes", response_model=List[ProjectNoteResponse])
async def list_project_notes(
    project_id: str,
    status: Optional[str] = None,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> List[ProjectNoteResponse]:
    """List memory notes for a given project, or global learned rules."""
    if project_id != "global":
        await verify_project_ownership(project_id, current_user, db)

    q = select(ProjectNote)
    if project_id == "global":
        q = q.where(ProjectNote.content.like("[LEARNED_RULE%"))
    else:
        q = q.where(ProjectNote.project_id == project_id)
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
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> ProjectNoteResponse:
    """Manually add an approved note to project memory."""
    if project_id == "global":
        if current_user.role != "admin":
            raise HTTPException(status_code=403, detail="Chỉ admin mới có quyền tạo ghi chú toàn cục.")
    else:
        await verify_project_ownership(project_id, current_user, db)

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
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> ProjectNoteResponse:
    """Approve or reject a proposed project note."""
    if req.action not in ("approve", "reject"):
        raise HTTPException(status_code=400, detail="Action must be 'approve' or 'reject'")

    await verify_project_ownership(project_id, current_user, db)

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


@router.post("/{project_id}/learn", response_model=ProjectNoteResponse)
async def teach_project_rule(
    project_id: str,
    req: LearnRuleRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> ProjectNoteResponse:
    """Explicitly teach the AI a permanent project rule or preference."""
    if project_id == "global":
        if current_user.role != "admin":
            raise HTTPException(status_code=403, detail="Chỉ admin mới có quyền tạo quy tắc toàn cục.")
    else:
        await verify_project_ownership(project_id, current_user, db)

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
