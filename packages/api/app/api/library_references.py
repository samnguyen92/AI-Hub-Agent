"""Reference metadata management endpoints for a project library."""

import os
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user, verify_project_ownership
from app.db.models import User
from app.db.reference_models import DesignReference
from app.db.session import get_db
from app.services.workspace import workspace_service
from app.tools.file_tools import safe_resolve_path

router = APIRouter(prefix="/projects/{project_id}/library/references", tags=["library"])


class ReferenceUpdate(BaseModel):
    """User-editable reference notes and search tags."""

    title: Optional[str] = Field(default=None, min_length=1, max_length=200)
    notes: Optional[str] = Field(default=None, max_length=4000)
    tags: Optional[List[str]] = Field(default=None, max_length=12)


class ReferenceMutationResponse(BaseModel):
    """Confirm a metadata change or reference removal."""

    id: str
    status: str


async def _get_reference(
    project_id: str, reference_id: str, db: AsyncSession
) -> DesignReference:
    """Load one reference scoped to the requested project."""
    result = await db.execute(
        select(DesignReference).where(
            DesignReference.id == reference_id,
            DesignReference.project_id == project_id,
        )
    )
    reference = result.scalars().first()
    if reference is None:
        raise HTTPException(status_code=404, detail="Reference not found")
    return reference


@router.patch("/{reference_id}", response_model=ReferenceMutationResponse)
async def update_reference(
    project_id: str,
    reference_id: str,
    payload: ReferenceUpdate,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> ReferenceMutationResponse:
    """Update a reference title, notes, or tags."""
    await verify_project_ownership(project_id, user, db)
    reference = await _get_reference(project_id, reference_id, db)
    values = payload.model_dump(exclude_unset=True)
    if "title" in values and values["title"] is not None:
        values["title"] = values["title"].strip()
        if not values["title"]:
            raise HTTPException(status_code=422, detail="Title cannot be blank")
    if "tags" in values and values["tags"] is not None:
        values["tags"] = list(dict.fromkeys(tag.strip().lower()[:40] for tag in values["tags"] if tag.strip()))[:12]
    elif "tags" in values:
        values["tags"] = []
    for key, value in values.items():
        setattr(reference, key, value)
    await db.commit()
    return ReferenceMutationResponse(id=reference.id, status="updated")


@router.delete("/{reference_id}", response_model=ReferenceMutationResponse)
async def delete_reference(
    project_id: str,
    reference_id: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> ReferenceMutationResponse:
    """Remove a reference record and its captured files from the project."""
    await verify_project_ownership(project_id, user, db)
    reference = await _get_reference(project_id, reference_id, db)
    paths = [reference.desktop_path, reference.mobile_path, reference.snapshot_path]
    workspace = workspace_service.init_project_workspace(project_id, refresh=True)
    resolved_paths = [safe_resolve_path(workspace, path) for path in paths]
    await db.delete(reference)
    await db.commit()
    _remove_captured_files(resolved_paths)
    workspace_service.commit_step(project_id, f"reference: delete {reference_id}")
    return ReferenceMutationResponse(id=reference_id, status="deleted")


def _remove_captured_files(paths: List[str]) -> None:
    """Delete captured files while tolerating files removed outside the library."""
    for path in paths:
        try:
            os.remove(path)
        except FileNotFoundError:
            continue
