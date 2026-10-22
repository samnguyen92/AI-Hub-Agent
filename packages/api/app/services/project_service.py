"""Service for project lifecycle management, deletion and workspace cleanup."""

import os
import shutil
from typing import Any, Dict, List, Optional
from sqlalchemy import select, delete
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import (
    Project,
    Thread,
    Message,
    Run,
    Step,
    Artifact,
    Approval,
    LibraryItem,
    ProjectNote,
)
from app.config import settings
from app.services.workspace_storage import workspace_storage
from app.services.workspace import workspace_service


class ProjectService:
    """Handles project lifecycle, safe deletion and workspace cleanup."""

    async def _purge_project_data(self, project_id: str, db: AsyncSession) -> None:
        """Purge all dependent entities for a project cleanly."""
        t_res = await db.execute(select(Thread.id).where(Thread.project_id == project_id))
        thread_ids = t_res.scalars().all()
        if thread_ids:
            r_res = await db.execute(select(Run.id).where(Run.thread_id.in_(thread_ids)))
            run_ids = r_res.scalars().all()
            if run_ids:
                await db.execute(delete(Approval).where(Approval.run_id.in_(run_ids)))
                await db.execute(delete(Step).where(Step.run_id.in_(run_ids)))
                await db.execute(delete(Run).where(Run.id.in_(run_ids)))
            await db.execute(delete(Message).where(Message.thread_id.in_(thread_ids)))
            await db.execute(delete(Thread).where(Thread.id.in_(thread_ids)))

        await db.execute(delete(LibraryItem).where(LibraryItem.project_id == project_id))
        await db.execute(delete(Artifact).where(Artifact.project_id == project_id))
        await db.execute(delete(ProjectNote).where(ProjectNote.project_id == project_id))
        await db.execute(delete(Project).where(Project.id == project_id))

    def _safe_remove_workspace(self, ws: Optional[str]) -> None:
        """Safely remove a workspace folder without risking system directories."""
        if not ws or not os.path.exists(ws):
            return
        workspace_root = os.path.realpath(settings.workspaces_root)
        abs_ws = os.path.realpath(ws)
        if abs_ws == workspace_root or os.path.commonpath((workspace_root, abs_ws)) != workspace_root:
            return
        shutil.rmtree(abs_ws, ignore_errors=True)

    async def delete_project(self, project_id: str, db: AsyncSession) -> bool:
        """Permanently delete a project and its workspace."""
        res = await db.execute(select(Project).where(Project.id == project_id))
        project = res.scalars().first()
        if not project:
            return False

        ws = workspace_service.get_project_path(project_id)
        await self._purge_project_data(project_id, db)
        await db.commit()

        self._safe_remove_workspace(ws)
        workspace_storage.delete(project_id)
        return True

    async def cleanup_demo_projects(self, db: AsyncSession) -> Dict[str, Any]:
        """Clean up disposable test and demo projects while keeping real ones."""
        res = await db.execute(select(Project))
        projects = res.scalars().all()

        preserved_keywords = ["vietthanh", "align", "toyota", "real", "production"]
        deleted_ids: List[str] = []

        for p in projects:
            p_name_lower = (p.name or "").lower()
            is_preserved = any(kw in p_name_lower for kw in preserved_keywords)
            if not is_preserved:
                ws = workspace_service.get_project_path(p.id)
                await self._purge_project_data(p.id, db)
                deleted_ids.append(p.id)
                self._safe_remove_workspace(ws)
                workspace_storage.delete(p.id)

        await db.commit()
        return {"deleted_count": len(deleted_ids), "deleted_ids": deleted_ids}


project_service = ProjectService()
