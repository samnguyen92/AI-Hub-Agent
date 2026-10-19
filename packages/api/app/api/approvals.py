"""API Router for human-in-the-loop approvals."""

from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Approval, Project, Run, Thread
from app.db.session import get_db
from app.services.gate_service import gate_service
from app.services.workspace import workspace_service
from app.tools.base import ToolContext, tool_registry
from app.tools.file_tools import perform_export

router = APIRouter(prefix="/approvals", tags=["approvals"])


class ApprovalResponse(BaseModel):
    id: str
    run_id: str
    action_type: str
    status: str
    payload: Dict[str, Any]
    comment: Optional[str] = None
    created_at: str


class ApprovalDecisionRequest(BaseModel):
    action: str  # approve | rework | reject
    comment: Optional[str] = None


@router.get("", response_model=List[ApprovalResponse])
async def list_approvals(
    project_id: Optional[str] = None,
    status: Optional[str] = None,
    db: AsyncSession = Depends(get_db),
) -> List[ApprovalResponse]:
    """List pending and historical approvals."""
    q = select(Approval).order_by(Approval.created_at.desc())
    if status and status != "all":
        q = q.where(Approval.status == status)

    res = await db.execute(q)
    approvals = res.scalars().all()

    return [
        ApprovalResponse(
            id=a.id,
            run_id=a.run_id,
            action_type=a.action_type,
            status=a.status,
            payload=a.payload,
            comment=a.comment,
            created_at=a.created_at.isoformat() if a.created_at else "",
        )
        for a in approvals
    ]


@router.post("/{approval_id}/respond", response_model=ApprovalResponse)
async def respond_approval(
    approval_id: str,
    req: ApprovalDecisionRequest,
    db: AsyncSession = Depends(get_db),
) -> ApprovalResponse:
    """Approve or reject a pending action."""
    q = select(Approval).where(Approval.id == approval_id)
    res = await db.execute(q)
    approval = res.scalars().first()
    if not approval:
        raise HTTPException(status_code=404, detail="Approval record not found")

    if approval.status != "pending":
        raise HTTPException(status_code=400, detail="Approval has already been resolved")

    # Check if this is a Gate approval
    if approval.action_type.startswith("gate_"):
        try:
            approval = await gate_service.answer_gate(
                db=db,
                approval_id=approval_id,
                action=req.action,
                comment=req.comment,
            )
        except ValueError as val_err:
            raise HTTPException(status_code=400, detail=str(val_err))

        # Update run status
        run_q = select(Run).where(Run.id == approval.run_id)
        run = (await db.execute(run_q)).scalars().first()
        if run:
            if approval.status == "approved":
                run.status = "completed"
            elif approval.status == "rework":
                run.status = "rework_needed"
            else:
                run.status = "cancelled"
            await db.commit()
    else:
        # Traditional tool approvals (e.g. export_zip)
        approval.status = "approved" if req.action.lower() == "approve" else "rejected"
        approval.comment = req.comment

        # Fetch associated run and project
        run_q = select(Run).where(Run.id == approval.run_id)
        run = (await db.execute(run_q)).scalars().first()

        if run:
            if approval.status == "approved":
                if approval.action_type == "export_zip":
                    th_q = select(Thread).where(Thread.id == run.thread_id)
                    th = (await db.execute(th_q)).scalars().first()
                    if th:
                        ws_path = workspace_service.init_project_workspace(th.project_id)
                        arch_name = approval.payload.get("archive_name") or "project_export.zip"
                        zip_res = perform_export(ws_path, arch_name, th.project_id)
                        approval.payload["result"] = zip_res

                run.status = "completed"
            else:
                run.status = "cancelled"

        await db.commit()

    return ApprovalResponse(
        id=approval.id,
        run_id=approval.run_id,
        action_type=approval.action_type,
        status=approval.status,
        payload=approval.payload,
        comment=approval.comment,
        created_at=approval.created_at.isoformat() if approval.created_at else "",
    )
