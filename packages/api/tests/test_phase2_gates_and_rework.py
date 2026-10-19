"""Unit and integration tests for Phase 2: Gate Quality Review, Approvals, and Rework Loops."""

import os
import pytest
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Approval, Message, Project, ProjectNote, Run, Thread, User
from app.db.session import async_session_maker
from app.services.approval_service import approval_service
from app.services.gate_service import gate_service
from app.tools.base import ToolContext, tool_registry
import app.tools.gate_tools  # noqa: F401


@pytest.mark.asyncio
async def test_gate_design_lifecycle_and_approval() -> None:
    """Test Gate 1 (Design Gate) opening, pause behavior, and approval resolution."""
    async with async_session_maker() as db:
        user = User(github_login=f"gate_user_{os.urandom(4).hex()}", role="member")
        db.add(user)
        await db.flush()

        proj = Project(owner_id=user.id, name="Gate Design Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        th = Thread(project_id=proj.id, agent_key="design", title="Gate Thread")
        db.add(th)
        await db.flush()

        run = Run(thread_id=th.id, agent_key="design", status="running")
        db.add(run)
        await db.commit()

        ctx = ToolContext(
            project_id=proj.id,
            run_id=run.id,
            workspace_path="/tmp",
            db=db,
            agent_key="design",
        )

        open_gate_tool = tool_registry.get("open_gate")
        assert open_gate_tool is not None

        # 1. Open Gate
        res = await open_gate_tool.execute(
            ctx,
            gate_type="design",
            title="Design Review Gate 1",
            slug="gate-1-design",
            decision_needed="Bạn có đồng ý phương án thiết kế màu sắc và wireframe không?",
            recommendation="Đạt chuẩn Align Style và WCAG AA.",
        )

        assert res["status"] == "waiting_gate_design"
        assert res["pause"] is True
        gate_id = res["gate_id"]

        # Check run status updated in DB
        reloaded_run = (await db.execute(select(Run).where(Run.id == run.id))).scalars().first()
        assert reloaded_run.status == "waiting_gate_design"

        # 2. User approves gate
        appr = await approval_service.decide(
            approval_id=gate_id,
            decision="approve",
            db=db,
            user=user,
            comment="Giao diện rất đẹp, đồng ý triển khai.",
        )
        assert appr.status == "approved"
        assert appr.decided_by == user.id

        # Verify run marked completed or queued
        reloaded_run = (await db.execute(select(Run).where(Run.id == run.id))).scalars().first()
        assert reloaded_run.status in ("completed", "queued")


@pytest.mark.asyncio
async def test_gate_rework_creates_child_run_and_learned_fix() -> None:
    """Reworking a gate creates a linked child run and saves [LEARNED_FIX] into project notes."""
    async with async_session_maker() as db:
        user = User(github_login=f"rework_user_{os.urandom(4).hex()}", role="member")
        db.add(user)
        await db.flush()

        proj = Project(owner_id=user.id, name="Rework Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        th = Thread(project_id=proj.id, agent_key="code-builder", title="Rework Thread")
        db.add(th)
        await db.flush()

        parent_run = Run(thread_id=th.id, agent_key="code-builder", status="running")
        db.add(parent_run)
        await db.commit()

        ctx = ToolContext(
            project_id=proj.id,
            run_id=parent_run.id,
            workspace_path="/tmp",
            db=db,
            agent_key="code-builder",
        )

        open_gate_tool = tool_registry.get("open_gate")
        res = await open_gate_tool.execute(
            ctx,
            gate_type="code",
            title="Code Verification Gate 2",
            slug="gate-2-code",
            decision_needed="Kiểm tra HTML và độ phản hồi mobile.",
        )
        gate_id = res["gate_id"]

        rework_feedback = "Cần tăng độ tương phản của nút CTA chính lên tỷ lệ 4.5:1."
        appr = await approval_service.decide(
            approval_id=gate_id,
            decision="rework",
            db=db,
            user=user,
            comment=rework_feedback,
        )
        assert appr.status == "rework"

        # 1. Parent run set to rework_needed
        reloaded_parent = (await db.execute(select(Run).where(Run.id == parent_run.id))).scalars().first()
        assert reloaded_parent.status == "rework_needed"

        # 2. Child run created and queued
        child_q = select(Run).where(Run.parent_run_id == parent_run.id)
        child_run = (await db.execute(child_q)).scalars().first()
        assert child_run is not None
        assert child_run.status == "queued"
        assert child_run.agent_key == "code-builder"

        # 3. ProjectNote created with [LEARNED_FIX]
        note_q = select(ProjectNote).where(
            ProjectNote.project_id == proj.id,
            ProjectNote.source_run_id == parent_run.id,
        )
        note = (await db.execute(note_q)).scalars().first()
        assert note is not None
        assert "[LEARNED_FIX" in note.content
        assert rework_feedback in note.content
        assert note.status == "approved"
