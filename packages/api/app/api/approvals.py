"""API Router for human-in-the-loop approvals."""

from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user
from app.db.models import Approval, Project, Run, Thread, User
from app.db.session import get_db

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
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> List[ApprovalResponse]:
    """List pending and historical approvals for authorized projects."""
    q = (
        select(Approval)
        .join(Run, Approval.run_id == Run.id)
        .join(Thread, Run.thread_id == Thread.id)
        .join(Project, Thread.project_id == Project.id)
        .order_by(Approval.created_at.desc())
    )
    if status and status != "all":
        q = q.where(Approval.status == status)
    if project_id:
        q = q.where(Project.id == project_id)
    if current_user.role != "admin":
        q = q.where(Project.owner_id == current_user.id)

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
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> ApprovalResponse:
    """Approve, rework, or reject a pending Gate or tool action via ApprovalService."""
    from app.services.approval_service import approval_service

    try:
        approval = await approval_service.decide(
            approval_id=approval_id,
            decision=req.action,
            db=db,
            user=current_user,
            comment=req.comment,
        )
    except PermissionError as p_err:
        raise HTTPException(status_code=403, detail=str(p_err))
    except ValueError as v_err:
        raise HTTPException(status_code=400, detail=str(v_err))

    return ApprovalResponse(
        id=approval.id,
        run_id=approval.run_id,
        action_type=approval.action_type,
        status=approval.status,
        payload=approval.payload,
        comment=approval.comment,
        created_at=approval.created_at.isoformat() if approval.created_at else "",
    )
