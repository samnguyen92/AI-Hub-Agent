"""End-to-End integration test simulating a complete multi-agent workflow with Gates and SSOT rules."""

import os
import pytest
from httpx import ASGITransport, AsyncClient
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Approval, Message, Project, Run, Step, Thread, User
from app.db.session import async_session_maker
from app.main import app as fastapi_app
from app.services.approval_service import approval_service
from app.services.auth_service import auth_service
from app.services.gate_service import gate_service
from app.standards.writing import format_sitemap_as_table
from app.tools.base import ToolContext, tool_registry
import app.tools.design_tools  # noqa: F401
import app.tools.gate_tools  # noqa: F401


@pytest.fixture
async def api_client():
    transport = ASGITransport(app=fastapi_app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        yield client


@pytest.mark.asyncio
async def test_complete_design_to_gate_e2e_flow(api_client: AsyncClient) -> None:
    """Full lifecycle: User login -> Project -> Design Gate -> Approval -> Sitemap table."""
    # 1. User logs in
    login_res = await api_client.post("/auth/login", json={"username": f"e2e_lead_{os.urandom(4).hex()}"})
    assert login_res.status_code == 200
    token = login_res.json()["token"]
    user_id = login_res.json()["id"]

    auth_headers = {"Authorization": f"Bearer {token}"}

    # 2. Create project
    proj_res = await api_client.post(
        "/projects",
        json={"name": "Petking E-Commerce Redesign"},
        headers=auth_headers,
    )
    assert proj_res.status_code == 200
    proj_id = proj_res.json()["id"]

    # 3. Simulate Design Gate opening
    async with async_session_maker() as db:
        user = (await db.execute(select(User).where(User.id == user_id))).scalars().first()
        th = Thread(project_id=proj_id, agent_key="design", title="Petking Design Sprint")
        db.add(th)
        await db.flush()

        run = Run(thread_id=th.id, agent_key="design", status="running")
        db.add(run)
        await db.commit()

        # Seed passing quality audit required for design gate
        step_visual = Step(
            run_id=run.id,
            seq=1,
            tool="inspect_screenshots",
            result={"status": "analyzed"},
        )
        step_audit = Step(
            run_id=run.id,
            seq=2,
            tool="run_quality_review",
            result={
                "run_id": run.id,
                "report": '{"verdict": "pass", "summary": "Design passes quality review."}',
            },
        )
        db.add(step_visual)
        db.add(step_audit)
        await db.commit()

        ctx = ToolContext(
            project_id=proj_id,
            run_id=run.id,
            workspace_path="/tmp",
            db=db,
            agent_key="design",
        )

        open_gate = tool_registry.get("open_gate")
        assert open_gate is not None
        gate_res = await open_gate.execute(
            ctx,
            gate_type="design",
            title="Duyệt wireframe và sitemap trang chủ Petking",
            slug="petking-homepage-gate",
            decision_needed="Bạn có phê duyệt bố cục wireframe và cấu trúc trang đề xuất không?",
            recommendation="Đã tối ưu theo chuẩn Align Style, đáp ứng WCAG AA.",
        )
        gate_id = gate_res["gate_id"]
        assert gate_res["status"] == "waiting_gate_design"

    # 4. User views pending approval via API
    appr_list_res = await api_client.get(f"/approvals?project_id={proj_id}&status=pending", headers=auth_headers)
    assert appr_list_res.status_code == 200
    assert len(appr_list_res.json()) >= 1

    # 5. User approves the gate
    decide_res = await api_client.post(
        f"/approvals/{gate_id}/respond",
        json={"action": "approve", "comment": "Bố cục rất trực quan, đồng ý cho bước code."},
        headers=auth_headers,
    )
    assert decide_res.status_code == 200
    assert decide_res.json()["status"] == "approved"

    # 6. Verify Sitemap formatter converts pages list into human-friendly table (no raw JSON)
    sample_pages = [
        {"path": "/", "title": "Trang chủ", "desc": "Giới thiệu & sản phẩm hot"},
        {"path": "/san-pham", "title": "Cửa hàng", "desc": "Tìm kiếm và bộ lọc"},
        {"path": "/gio-hang", "title": "Giỏ hàng", "desc": "Thanh toán & đặt hàng"},
    ]
    table_md = format_sitemap_as_table(sample_pages, lang="vi")
    assert "| Đường dẫn | Tiêu đề | Mục đích" in table_md
    assert "| `/` | **Trang chủ** | Giới thiệu & sản phẩm hot |" in table_md
    assert "{" not in table_md  # No raw JSON!
