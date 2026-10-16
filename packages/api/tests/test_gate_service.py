"""Unit tests for GateService and 3-Gate approval flows."""

import pytest
from httpx import AsyncClient
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Approval, Project, ProjectNote, Run, Thread
from app.services.gate_service import gate_service


@pytest.mark.asyncio
async def test_gate_service_validation() -> None:
    """Verify validation for Intake, Plan, and Result gates."""
    # Invalid gate type
    errs = gate_service.validate_gate_payload("invalid", {"title": "Test", "decision_needed": "?"})
    assert any("Loại cổng không hợp lệ" in e for e in errs)

    # Missing mandatory fields
    errs = gate_service.validate_gate_payload("intake", {})
    assert any("title" in e for e in errs)
    assert any("decision_needed" in e for e in errs)

    # Result Gate missing try_it and how_to_test
    errs = gate_service.validate_gate_payload(
        "result", {"title": "Finished", "decision_needed": "Approve?"}
    )
    assert any("try_it" in e for e in errs)
    assert any("how_to_test" in e for e in errs)


@pytest.mark.asyncio
async def test_gate_service_markdown_formatter() -> None:
    """Verify Gate markdown output adheres to anti-slop clean layout."""
    payload = {
        "gate_type": "result",
        "title": "Trang chủ Petking",
        "decision_needed": "Duyệt giao diện hoàn thiện để deploy",
        "try_it": {
            "url": "http://localhost:3000/demo.html",
            "instruction": "Kiểm tra cuộn trang và bộ lọc",
            "expected_result": "Header sticky mượt mà",
        },
        "how_to_test": ["1. Bấm link", "2. Chọn bộ lọc"],
        "review": {"reviewer_seat": "quality-auditor", "status": "passed"},
    }
    md = gate_service.format_gate_markdown(payload)
    assert "### 🛡️ [RESULT GATE] Trang chủ Petking" in md
    assert "**Quyết định cần duyệt:** Duyệt giao diện hoàn thiện để deploy" in md
    assert "👉 **Chạy thử nghiệm (Try It):**" in md
    assert "🔍 **Thẩm định độc lập (quality-auditor):** `[PASSED]`" in md


@pytest.mark.asyncio
async def test_gate_service_lifecycle_and_rework() -> None:
    """Verify full Gate lifecycle: open -> answer rework -> verify project note saved."""
    from app.db.session import async_session_maker
    async with async_session_maker() as db_session:
        from sqlalchemy import select
        from app.db.models import User
        u_res = await db_session.execute(select(User).limit(1))
        user = u_res.scalars().first()
        if not user:
            user = User(github_login="gate-tester", role="admin")
            db_session.add(user)
            await db_session.flush()

        proj = Project(owner_id=user.id, name="Gate Test Proj", workspace_path="/tmp")
        db_session.add(proj)
        await db_session.commit()
        await db_session.refresh(proj)

        thread = Thread(project_id=proj.id, agent_key="code-builder", title="Gate Thread")
        db_session.add(thread)
        await db_session.commit()
        await db_session.refresh(thread)

        run = Run(thread_id=thread.id, agent_key="code-builder", status="running")
        db_session.add(run)
        await db_session.commit()
        await db_session.refresh(run)

        # Open Gate
        approval = await gate_service.open_gate(
            db=db_session,
            run_id=run.id,
            gate_type="plan",
            title="Kế hoạch nâng cấp Hero",
            slug="hero-plan",
            decision_needed="Duyệt phương án dùng Tailwind glassmorphism?",
            recommendation="Sử dụng biến thể B với nền tối tương phản cao",
            questions=["Có giữ nút liên hệ không?"],
        )
        assert approval.status == "pending"
        assert approval.action_type == "gate_plan"
        assert approval.payload["slug"] == "hero-plan"

        # Answer Rework
        updated = await gate_service.answer_gate(
            db=db_session,
            approval_id=approval.id,
            action="rework",
            comment="Đổi màu nút CTA từ xanh sang vàng cam",
        )
        assert updated.status == "rework"
        assert updated.comment == "Đổi màu nút CTA từ xanh sang vàng cam"

        # Check that ProjectNote was automatically recorded with learned fix
        notes = (await db_session.execute(ProjectNote.__table__.select().where(ProjectNote.project_id == proj.id))).fetchall()
        assert len(notes) >= 1
        assert any("[LEARNED_FIX từ Gate" in n.content for n in notes)


@pytest.mark.asyncio
async def test_gate_api_rework_endpoint() -> None:
    """Verify HTTP API responds properly to rework action and persists status."""
    from httpx import ASGITransport, AsyncClient
    from app.db.session import async_session_maker
    from app.main import app as fastapi_app
    from app.db.models import User

    async with async_session_maker() as db:
        u_res = await db.execute(select(User).limit(1))
        user = u_res.scalars().first()
        if not user:
            user = User(github_login="api-gate-tester", role="admin")
            db.add(user)
            await db.flush()

        proj = Project(owner_id=user.id, name="API Gate Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        th = Thread(project_id=proj.id, agent_key="code-builder", title="API Gate Thread")
        db.add(th)
        await db.flush()

        run = Run(thread_id=th.id, agent_key="code-builder", status="running")
        db.add(run)
        await db.commit()

        approval = await gate_service.open_gate(
            db=db,
            run_id=run.id,
            gate_type="intake",
            title="Làm rõ Scope",
            slug="scope-clarify",
            decision_needed="Khách hàng muốn dùng Next.js hay HTML thuần?",
        )
        gate_id = approval.id

    transport = ASGITransport(app=fastapi_app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        res = await ac.post(
            f"/approvals/{gate_id}/respond",
            json={"action": "rework", "comment": "Yêu cầu làm rõ thêm về database"},
        )
        assert res.status_code == 200
        data = res.json()
        assert data["status"] == "rework"
        assert data["comment"] == "Yêu cầu làm rõ thêm về database"

