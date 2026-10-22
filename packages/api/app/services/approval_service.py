"""Unified service handling approval decisions across Web and Telegram."""

from typing import Optional
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Approval, Message, Project, Run, TelegramLink, Thread, User
from app.services.gate_service import gate_service
from app.services.workspace import workspace_service
from app.tools.file_tools import perform_export


class ApprovalService:
    """Central authority for resolving Gate and tool approvals with ownership enforcement."""

    async def decide(
        self,
        approval_id: str,
        decision: str,
        db: AsyncSession,
        user: Optional[User] = None,
        telegram_chat_id: Optional[str] = None,
        comment: Optional[str] = None,
    ) -> Approval:
        """Process approval decision with strict owner authorization for Web and Telegram."""
        action_clean = decision.lower().strip()
        if action_clean not in ("approve", "rework", "reject"):
            raise ValueError(f"Hành động không hợp lệ: {decision}. Phải là 'approve', 'rework', hoặc 'reject'.")

        q = select(Approval).where(Approval.id == approval_id)
        approval = (await db.execute(q)).scalars().first()
        if not approval:
            raise ValueError(f"Không tìm thấy bản ghi phê duyệt: {approval_id}")
        if approval.status != "pending":
            raise ValueError(f"Bản ghi đã được xử lý trước đó với trạng thái: {approval.status}")

        run_q = select(Run).where(Run.id == approval.run_id)
        run = (await db.execute(run_q)).scalars().first()
        if not run:
            raise ValueError("Không tìm thấy Run liên kết với phê duyệt này.")

        th_q = select(Thread).where(Thread.id == run.thread_id)
        thread = (await db.execute(th_q)).scalars().first()
        if not thread:
            raise ValueError("Không tìm thấy Thread liên kết.")

        proj_q = select(Project).where(Project.id == thread.project_id)
        project = (await db.execute(proj_q)).scalars().first()
        if not project:
            raise ValueError("Không tìm thấy Project liên kết.")

        decided_user_id: Optional[str] = None
        # Authorize: Web user or Telegram account must own the project
        if user:
            if project.owner_id != user.id and user.role != "admin":
                raise PermissionError("Bị từ chối: Bạn không phải chủ sở hữu dự án này.")
            decided_user_id = user.id
        elif telegram_chat_id:
            link_q = select(TelegramLink).where(
                TelegramLink.telegram_id == str(telegram_chat_id),
                TelegramLink.user_id == project.owner_id,
                TelegramLink.is_confirmed == True,  # noqa: E712
            )
            link = (await db.execute(link_q)).scalars().first()
            if not link:
                raise PermissionError("Bị từ chối: Tài khoản Telegram này chưa liên kết với chủ sở hữu dự án.")
            decided_user_id = link.user_id
        else:
            raise PermissionError("Bị từ chối: Thiếu thông tin người thực hiện phê duyệt.")

        # Execute decision logic
        if approval.action_type.startswith("gate_"):
            if approval.action_type == "gate_reference" and action_clean == "approve":
                self._validate_reference_selection(approval.payload, comment)
            approval = await gate_service.answer_gate(
                db=db,
                approval_id=approval_id,
                action=action_clean,
                comment=comment,
                decided_by=decided_user_id,
            )
            if approval.status == "approved":
                if run.checkpoint_state:
                    db.add(Message(
                        thread_id=run.thread_id,
                        role="user",
                        content=self._approval_continuation(approval, comment),
                    ))
                    run.status = "queued"
                else:
                    run.status = "completed"
            elif approval.status == "rework":
                run.status = "rework_needed"
                db.add(Run(thread_id=run.thread_id, agent_key=run.agent_key, parent_run_id=run.id, status="queued"))
                db.add(Message(
                    thread_id=run.thread_id,
                    role="user",
                    content=f"[YÊU CẦU SỬA ĐỔI / REWORK]: {comment or 'Vui lòng xem lại và chỉnh sửa.'}",
                ))
            else:
                run.status = "cancelled"
        else:
            approval.status = "approved" if action_clean == "approve" else "rejected"
            approval.comment = comment
            approval.decided_by = decided_user_id
            if approval.status == "approved":
                if approval.action_type == "export_zip":
                    ws_path = workspace_service.init_project_workspace(thread.project_id, refresh=True)
                    arch_name = approval.payload.get("archive_name") or "project_export.zip"
                    approval.payload["result"] = perform_export(ws_path, arch_name, thread.project_id)
                run.status = "queued" if run.checkpoint_state else "completed"
            else:
                run.status = "cancelled"

        await db.commit()
        return approval

    @staticmethod
    def _validate_reference_selection(payload: dict, comment: Optional[str]) -> None:
        """Require selected candidate IDs to belong to this reference proposal."""
        prefix = "reference:"
        if not comment or not comment.startswith(prefix):
            raise ValueError("Hãy chọn ít nhất một reference trước khi duyệt.")
        selected = {value.strip() for value in comment[len(prefix):].split(",") if value.strip()}
        candidates = payload.get("example", {}).get("reference_candidates", [])
        available = {str(candidate.get("id")) for candidate in candidates}
        if not selected or not selected.issubset(available):
            raise ValueError("Reference đã chọn không nằm trong danh sách đề xuất.")

    @staticmethod
    def _approval_continuation(approval: Approval, comment: Optional[str]) -> str:
        """Pass the user's selected reference IDs into the resumed agent context."""
        message = f"[CỔNG ĐÃ DUYỆT]: Phê duyệt cổng '{approval.payload.get('title', '')}'."
        if approval.action_type == "gate_reference":
            return f"{message} Người dùng đã chọn {comment}. Hãy capture các reference được chọn và tiếp tục."
        return f"{message} Tiếp tục thực hiện."


approval_service = ApprovalService()
