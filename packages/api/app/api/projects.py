"""API Router for Project operations."""

import os
from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Cookie, Depends, Header, HTTPException, Request
from fastapi.responses import FileResponse
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user, verify_project_ownership
from app.db.models import Project, User
from app.db.session import get_db
from app.services.auth_service import auth_service
from app.services.workspace import workspace_service
from app.tools.file_tools import safe_resolve_path

router = APIRouter(prefix="/projects", tags=["projects"])


class ProjectCreateRequest(BaseModel):
    name: str
    source_url: Optional[str] = None


class ProjectResponse(BaseModel):
    id: str
    name: str
    source_url: Optional[str] = None
    workspace_path: str


@router.get("", response_model=List[ProjectResponse])
async def list_projects(
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> List[ProjectResponse]:
    """Retrieve active projects owned by user or all projects for admin."""
    q = select(Project)
    if user.role != "admin":
        q = q.where(Project.owner_id == user.id)
    q = q.order_by(Project.created_at.desc())
    res = await db.execute(q)
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
    req: ProjectCreateRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> ProjectResponse:
    """Create a new project workspace and record belonging to current user."""
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


@router.get("/{project_id}", response_model=ProjectResponse)
async def get_project(
    project_id: str,
    project: Project = Depends(verify_project_ownership),
) -> ProjectResponse:
    """Get project by ID if authorized."""
    return ProjectResponse(
        id=project.id,
        name=project.name,
        source_url=project.source_url,
        workspace_path=project.workspace_path,
    )


def _resolve_project_file_path(workspace_path: str, path: str) -> str:
    """Resolve file path within workspace or common asset subdirectories."""
    full_path = safe_resolve_path(workspace_path, path)
    if not os.path.exists(full_path) or not os.path.isfile(full_path):
        for sub in ("mockups", "images", "screenshots"):
            candidate = safe_resolve_path(workspace_path, f"{sub}/{path}")
            if os.path.exists(candidate) and os.path.isfile(candidate):
                return candidate
    if not os.path.exists(full_path) or not os.path.isfile(full_path):
        raise HTTPException(status_code=404, detail=f"File not found: {path}")
    return full_path


@router.get("/{project_id}/files")
async def get_project_file(
    project_id: str,
    path: str,
    request: Request,
    signature: Optional[str] = None,
    expires: Optional[int] = None,
    authorization: Optional[str] = Header(None),
    session_token: Optional[str] = Cookie(None),
    db: AsyncSession = Depends(get_db),
) -> FileResponse:
    """Serve a workspace file using a query path for API and image clients."""
    return await _serve_project_file(
        project_id, path, request, signature, expires, authorization, session_token, db
    )


@router.get("/{project_id}/files/{file_path:path}")
async def get_project_file_by_path(
    project_id: str,
    file_path: str,
    request: Request,
    signature: Optional[str] = None,
    expires: Optional[int] = None,
    authorization: Optional[str] = Header(None),
    session_token: Optional[str] = Cookie(None),
    db: AsyncSession = Depends(get_db),
) -> FileResponse:
    """Serve nested HTML/assets so relative links resolve from their real folder path."""
    return await _serve_project_file(
        project_id, file_path, request, signature, expires, authorization, session_token, db
    )


async def _serve_project_file(
    project_id: str,
    path: str,
    request: Request,
    signature: Optional[str],
    expires: Optional[int],
    authorization: Optional[str],
    session_token: Optional[str],
    db: AsyncSession,
) -> FileResponse:
    """Authorize and safely return a file from a project's workspace."""
    is_signed = False
    if signature and expires:
        is_signed = auth_service.verify_file_signature(project_id, path, signature, expires)

    if is_signed:
        q = select(Project).where(Project.id == project_id)
        project = (await db.execute(q)).scalars().first()
        if not project:
            raise HTTPException(status_code=404, detail="Project not found")
    else:
        user = await get_current_user(request, authorization, session_token, db)
        project = await verify_project_ownership(project_id, user, db)

    try:
        full_path = _resolve_project_file_path(project.workspace_path, path)
        return FileResponse(
            full_path,
            headers={
                "Content-Security-Policy": "sandbox allow-scripts",
                "X-Content-Type-Options": "nosniff",
            },
        )
    except PermissionError:
        raise HTTPException(status_code=403, detail="Path traversal forbidden")


@router.delete("/{project_id}")
async def delete_project(
    project_id: str,
    project: Project = Depends(verify_project_ownership),
    db: AsyncSession = Depends(get_db),
) -> Dict[str, Any]:
    """Delete a project and wipe its workspace if owner or admin."""
    from app.services.project_service import project_service

    success = await project_service.delete_project(project.id, db)
    if not success:
        raise HTTPException(status_code=404, detail="Project not found")
    return {"status": "deleted", "id": project.id}


@router.post("/cleanup-demos")
async def cleanup_demo_projects() -> Dict[str, Any]:
    """Disabled endpoint for safety reasons."""
    raise HTTPException(
        status_code=410,
        detail="Endpoint này đã bị vô hiệu hoá để đảm bảo an toàn dữ liệu.",
    )
