"""API Router for Project Library and Artifacts."""

import os
from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Depends, UploadFile, File, Form
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user, verify_project_ownership
from app.db.models import Artifact, LibraryItem, User
from app.db.reference_models import DesignReference
from app.db.session import get_db
from app.services.workspace import workspace_service

router = APIRouter(prefix="/projects/{project_id}/library", tags=["library"])


class ArtifactResponse(BaseModel):
    id: str
    artifact_id: str
    version: int
    parent_version: Optional[int] = None
    path: str
    git_commit: Optional[str] = None


class LibraryItemResponse(BaseModel):
    id: str
    type: str
    tag: Optional[str] = None
    description: Optional[str] = None
    path: str


class ReferenceResponse(BaseModel):
    id: str
    source_url: str
    title: str
    notes: Optional[str] = None
    tags: List[str]
    desktop_path: str
    mobile_path: str
    snapshot_path: str
    page_summary: Optional[str] = None
    captured_at: str


class LibrarySummaryResponse(BaseModel):
    items: List[LibraryItemResponse]
    artifacts: List[ArtifactResponse]
    references: List[ReferenceResponse]


@router.get("", response_model=LibrarySummaryResponse)
async def get_library_summary(
    project_id: str, user: User = Depends(get_current_user), db: AsyncSession = Depends(get_db)
) -> LibrarySummaryResponse:
    """Retrieve all library items and versioned artifacts for a project."""
    await verify_project_ownership(project_id, user, db)
    item_res = await db.execute(
        select(LibraryItem).where(LibraryItem.project_id == project_id).order_by(LibraryItem.created_at.desc())
    )
    items = item_res.scalars().all()

    art_res = await db.execute(
        select(Artifact).where(Artifact.project_id == project_id).order_by(Artifact.version.desc())
    )
    artifacts = art_res.scalars().all()
    ref_res = await db.execute(
        select(DesignReference)
        .where(DesignReference.project_id == project_id)
        .order_by(DesignReference.captured_at.desc())
    )
    references = ref_res.scalars().all()

    return LibrarySummaryResponse(
        items=[
            LibraryItemResponse(
                id=i.id, type=i.type, tag=i.tag, description=i.description, path=i.path
            )
            for i in items
        ],
        artifacts=[
            ArtifactResponse(
                id=a.id,
                artifact_id=a.artifact_id,
                version=a.version,
                parent_version=a.parent_version,
                path=a.path,
                git_commit=a.git_commit,
            )
            for a in artifacts
        ],
        references=[
            ReferenceResponse(
                id=ref.id,
                source_url=ref.source_url,
                title=ref.title,
                notes=ref.notes,
                tags=ref.tags or [],
                desktop_path=ref.desktop_path,
                mobile_path=ref.mobile_path,
                snapshot_path=ref.snapshot_path,
                page_summary=ref.page_summary,
                captured_at=ref.captured_at.isoformat() if ref.captured_at else "",
            )
            for ref in references
        ],
    )


@router.post("/upload", response_model=LibraryItemResponse)
async def upload_library_item(
    project_id: str,
    file: UploadFile = File(...),
    tag: Optional[str] = Form("upload"),
    description: Optional[str] = Form(None),
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> LibraryItemResponse:
    """Upload a file directly into the project library."""
    project = await verify_project_ownership(project_id, user, db)

    ws_path = workspace_service.init_project_workspace(project_id, refresh=True)
    upload_dir = os.path.join(ws_path, "uploads")
    os.makedirs(upload_dir, exist_ok=True)

    filename = os.path.basename(file.filename or "upload.bin")
    file_path = os.path.join(upload_dir, filename)

    content = await file.read()
    with open(file_path, "wb") as f:
        f.write(content)

    rel_path = os.path.relpath(file_path, ws_path)
    workspace_service.commit_step(project_id, f"upload: {filename} to library")

    item = LibraryItem(
        project_id=project_id,
        type="upload",
        tag=tag,
        description=description or filename,
        path=rel_path,
    )
    db.add(item)
    await db.commit()

    return LibraryItemResponse(
        id=item.id,
        type=item.type,
        tag=item.tag,
        description=item.description,
        path=item.path,
    )
