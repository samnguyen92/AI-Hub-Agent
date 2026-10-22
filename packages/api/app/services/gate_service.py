"""Gate Service managing Intake, Plan, and Result gates conforming to Mandate architecture."""

import uuid
from typing import Any, Dict, List, Optional
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Approval, ProjectNote, Run, Thread


class GateService:
    """Manages the lifecycle of quality gates (Intake, Plan, Result)."""

    VALID_GATES = {"intake", "plan", "reference", "result", "design", "code", "launch"}
    VALID_ACTIONS = {"approve", "rework", "reject"}

    def validate_gate_payload(self, gate_type: str, payload: Dict[str, Any]) -> List[str]:
        """Validate mandatory fields per Mandate Gate standards."""
        errors: List[str] = []
        if gate_type not in self.VALID_GATES:
            errors.append(f"Loại cổng không hợp lệ: {gate_type}. Phải là một trong {self.VALID_GATES}")

        if not payload.get("title"):
            errors.append("Thiếu tiêu đề Cổng ('title')")

        if not payload.get("decision_needed"):
            errors.append("Thiếu câu hỏi quyết định cho người dùng ('decision_needed')")

        # For Result Gate, Try It and How to test are mandatory
        if gate_type == "result":
            if not payload.get("try_it"):
                errors.append("Result Gate bắt buộc phải có đường dẫn chạy thử ('try_it')")
            if not payload.get("how_to_test") or len(payload.get("how_to_test", [])) == 0:
                errors.append("Result Gate bắt buộc phải có các bước kiểm thử ('how_to_test')")

        return errors

    async def open_gate(
        self,
        db: AsyncSession,
        run_id: str,
        gate_type: str,
        title: str,
        slug: str,
        decision_needed: str,
        recommendation: Optional[str] = None,
        questions: Optional[List[str]] = None,
        try_it: Optional[Dict[str, str]] = None,
        example: Optional[Dict[str, Any]] = None,
        how_to_test: Optional[List[str]] = None,
        review: Optional[Dict[str, Any]] = None,
        screenshots: Optional[Dict[str, str]] = None,
    ) -> Approval:
        """Create a new Gate Approval record bound to a Run."""
        payload: Dict[str, Any] = {
            "gate_type": gate_type,
            "title": title,
            "slug": slug,
            "decision_needed": decision_needed,
            "recommendation": recommendation or "",
            "questions": questions or [],
            "try_it": try_it or {},
            "example": example or {},
            "how_to_test": how_to_test or [],
            "review": review or {"reviewer_seat": "quality-auditor", "status": "pending"},
            "screenshots": screenshots or {},
        }

        # Check validation errors, but log warnings rather than crashing execution
        validation_warnings = self.validate_gate_payload(gate_type, payload)
        if validation_warnings:
            payload["validation_warnings"] = validation_warnings

        approval = Approval(
            id=str(uuid.uuid4()),
            run_id=run_id,
            action_type=f"gate_{gate_type}",
            status="pending",
            payload=payload,
            comment=None,
        )

        db.add(approval)

        # Update run status to indicate it is waiting at a gate
        q = select(Run).where(Run.id == run_id)
        res = await db.execute(q)
        run = res.scalars().first()
        if run:
            run.status = f"waiting_gate_{gate_type}"

        await db.commit()
        await db.refresh(approval)
        return approval

    async def answer_gate(
        self,
        db: AsyncSession,
        approval_id: str,
        action: str,
        comment: Optional[str] = None,
        decided_by: Optional[str] = None,
    ) -> Approval:
        """Process user decision on a Gate (approve, rework, reject)."""
        action_clean = action.lower().strip()
        if action_clean not in self.VALID_ACTIONS:
            raise ValueError(f"Hành động không hợp lệ: {action}. Phải là 'approve', 'rework', hoặc 'reject'.")

        q = select(Approval).where(Approval.id == approval_id)
        res = await db.execute(q)
        approval = res.scalars().first()
        if not approval:
            raise ValueError(f"Không tìm thấy bản ghi Gate: {approval_id}")

        if approval.status != "pending":
            raise ValueError(f"Cổng này đã được giải quyết với trạng thái: {approval.status}")

        approval.status = "approved" if action_clean == "approve" else ("rework" if action_clean == "rework" else "rejected")
        approval.comment = comment
        if decided_by:
            approval.decided_by = decided_by

        # If Rework, automatically extract feedback into Project Memory as #learned_fix
        if action_clean == "rework" and comment:
            run_q = select(Run).where(Run.id == approval.run_id)
            run = (await db.execute(run_q)).scalars().first()
            if run:
                th_q = select(Thread).where(Thread.id == run.thread_id)
                th = (await db.execute(th_q)).scalars().first()
                if th and th.project_id:
                    note = ProjectNote(
                        project_id=th.project_id,
                        content=f"[LEARNED_FIX từ Gate '{approval.payload.get('title', '')}']: {comment}",
                        status="approved",
                        source_run_id=run.id,
                    )
                    db.add(note)

        await db.commit()
        await db.refresh(approval)
        return approval

    def format_gate_markdown(self, payload: Dict[str, Any]) -> str:
        """Format a clean, structured Gate report in Markdown for presenting to the user."""
        gate_type = payload.get("gate_type", "gate").upper()
        title = payload.get("title", "Untitled Gate")
        decision = payload.get("decision_needed", "")
        recommendation = payload.get("recommendation", "")

        lines = [
            f"### 🛡️ [{gate_type} GATE] {title}",
            f"**Quyết định cần duyệt:** {decision}",
        ]
        if recommendation:
            lines.append(f"**Đề xuất của Agent:** {recommendation}")

        candidates = payload.get("example", {}).get("reference_candidates", [])
        if gate_type == "reference" and candidates:
            lines.append("\n**Website reference đã được kiểm tra:**")
            for candidate in candidates:
                lines.append(
                    f"- **{candidate['id']} — [{candidate['title']}]({candidate['url']})**: "
                    f"{candidate['rationale']} (Tập trung: {candidate['study_focus']})"
                )

        # Questions
        questions = payload.get("questions", [])
        if questions:
            lines.append("\n**Các câu hỏi cần làm rõ:**")
            for idx, q in enumerate(questions, 1):
                lines.append(f"{idx}. {q}")

        # Try it
        try_it = payload.get("try_it", {})
        if try_it and try_it.get("url"):
            lines.append(f"\n👉 **Chạy thử nghiệm (Try It):** [{try_it.get('url')}]({try_it.get('url')})")
            if try_it.get("instruction"):
                lines.append(f"- **Hướng dẫn:** {try_it.get('instruction')}")
            if try_it.get("expected_result"):
                lines.append(f"- **Kỳ vọng:** {try_it.get('expected_result')}")

        # How to test
        how_to_test = payload.get("how_to_test", [])
        if how_to_test:
            lines.append("\n**Các bước kiểm thử (How to test):**")
            for idx, step in enumerate(how_to_test, 1):
                lines.append(f"{idx}. {step}")

        # Review findings
        review = payload.get("review", {})
        if review and review.get("status"):
            seat = review.get("reviewer_seat", "quality-auditor")
            status = review.get("status", "passed").upper()
            lines.append(f"\n🔍 **Thẩm định độc lập ({seat}):** `[{status}]`")

        return "\n".join(lines)


gate_service = GateService()
