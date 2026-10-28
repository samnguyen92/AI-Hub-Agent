"""API Router for Project operations."""

import os
from urllib.parse import quote
from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Cookie, Depends, Header, HTTPException, Request
from fastapi.responses import FileResponse, RedirectResponse, Response
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user, verify_project_ownership
from app.db.models import Project, User
from app.db.session import get_db
from app.services.auth_service import auth_service
from app.services.workspace import workspace_service
from app.tools.file_tools import perform_export, safe_resolve_path

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
            workspace_path=workspace_service.get_project_path(p.id),
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
        workspace_path=workspace_service.get_project_path(project.id),
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
        workspace_path=workspace_service.get_project_path(project.id),
    )


def _find_matching_workspace_files(workspace_path: str, ext_tuple: tuple[str, ...]) -> List[str]:
    """Return workspace files matching extensions sorted by newest modification time."""
    matches: List[str] = []
    ignored = {".git", "node_modules", "__pycache__", ".next"}
    for root, dirs, files in os.walk(workspace_path):
        dirs[:] = [d for d in dirs if d not in ignored and not d.startswith(".")]
        for fname in files:
            if fname.lower().endswith(ext_tuple):
                matches.append(os.path.join(root, fname))
    matches.sort(key=lambda p: os.path.getmtime(p), reverse=True)
    return matches


def _resolve_project_file_path(
    workspace_path: str, path: str, project_id: Optional[str] = None
) -> str:
    """Resolve file path within workspace or common asset subdirectories with smart fallbacks."""
    clean_req = path.strip().lstrip("/")
    full_path = safe_resolve_path(workspace_path, clean_req)
    if os.path.exists(full_path) and os.path.isfile(full_path):
        return full_path

    for sub in ("mockups", "images", "screenshots", "exports", "site", "public", "dist"):
        candidate = safe_resolve_path(workspace_path, f"{sub}/{clean_req}")
        if os.path.exists(candidate) and os.path.isfile(candidate):
            return candidate

    target_base = os.path.basename(clean_req).lower()
    if target_base:
        for root, dirs, files in os.walk(workspace_path):
            dirs[:] = [d for d in dirs if d not in {".git", "node_modules", "__pycache__"}]
            for fname in files:
                if fname.lower() == target_base:
                    return os.path.join(root, fname)

    lower_req = clean_req.lower()
    if lower_req.endswith((".html", ".htm")):
        html_files = _find_matching_workspace_files(workspace_path, (".html", ".htm"))
        non_sitemap = [f for f in html_files if "sitemap" not in os.path.basename(f).lower()]
        if non_sitemap:
            return non_sitemap[0]
        if html_files:
            return html_files[0]

    if lower_req.endswith((".png", ".jpg", ".jpeg", ".webp")):
        img_files = _find_matching_workspace_files(workspace_path, (".png", ".jpg", ".jpeg", ".webp"))
        if "desktop" in lower_req:
            desk = [f for f in img_files if "desktop" in os.path.basename(f).lower()]
            if desk:
                return desk[0]
        if "mobile" in lower_req:
            mob = [f for f in img_files if "mobile" in os.path.basename(f).lower()]
            if mob:
                return mob[0]

    if lower_req.endswith(".zip"):
        zip_files = _find_matching_workspace_files(workspace_path, (".zip",))
        if zip_files:
            return zip_files[0]
        if project_id:
            arch_name = os.path.basename(clean_req) or "site-package.zip"
            export_res = perform_export(workspace_path, arch_name, project_id)
            if export_res.get("archive_path"):
                created_zip = safe_resolve_path(workspace_path, export_res["archive_path"])
                if os.path.isfile(created_zip):
                    return created_zip

    raise HTTPException(status_code=404, detail=f"File not found: {path}")


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
) -> Response:
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
) -> Response:
    """Serve nested HTML/assets so relative links resolve from their real folder path."""
    return await _serve_project_file(
        project_id, file_path, request, signature, expires, authorization, session_token, db
    )


@router.get("/{project_id}/preview/{token}/{file_path:path}")
async def get_project_preview_file(
    project_id: str, token: str, file_path: str, db: AsyncSession = Depends(get_db)
) -> Response:
    """Serve an expiring, project-scoped static preview and its relative assets."""
    if not auth_service.verify_preview_token(project_id, token):
        raise HTTPException(status_code=401, detail="Preview link expired or invalid.")
    project = await _get_preview_project(project_id, db)
    workspace_path = workspace_service.get_project_path(project.id)
    workspace_service.init_project_workspace(project.id, refresh=False)
    full_path = _resolve_project_file_path(workspace_path, file_path, project_id=project_id)
    return FileResponse(full_path, headers=_preview_headers())


async def _get_preview_project(project_id: str, db: AsyncSession) -> Project:
    """Fetch project metadata for a signed preview request."""
    result = await db.execute(select(Project).where(Project.id == project_id))
    project = result.scalars().first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found.")
    return project


def _preview_headers() -> Dict[str, str]:
    """Keep project HTML isolated from the application origin."""
    return {"Content-Security-Policy": "sandbox allow-scripts", "X-Content-Type-Options": "nosniff"}


async def _serve_project_file(
    project_id: str,
    path: str,
    request: Request,
    signature: Optional[str],
    expires: Optional[int],
    authorization: Optional[str],
    session_token: Optional[str],
    db: AsyncSession,
) -> Response:
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
        workspace_service.init_project_workspace(project.id, refresh=False)
        workspace_path = workspace_service.get_project_path(project.id)
        full_path = _resolve_project_file_path(workspace_path, path, project_id=project.id)
        rel_path = os.path.relpath(full_path, workspace_path).replace(os.sep, "/")
        if full_path.lower().endswith((".html", ".htm")):
            token = auth_service.create_preview_token(project_id)
            safe_path = quote(rel_path.lstrip("/"), safe="/")
            api_prefix = (
                "/api/backend"
                if (os.getenv("VERCEL_API_PREFIX") or os.getenv("NEXT_PUBLIC_API_URL") == "/api/backend")
                else ""
            )
            return RedirectResponse(
                f"{api_prefix}/projects/{project_id}/preview/{token}/{safe_path}", status_code=302
            )
        if full_path.lower().endswith(".zip"):
            return FileResponse(
                full_path,
                media_type="application/zip",
                filename=os.path.basename(full_path),
                headers=_preview_headers(),
            )
        return FileResponse(
            full_path,
            headers=_preview_headers(),
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
