"""Unit tests for Phase 7: Approval Gate, Export ZIP, and Telegram Linking."""

import pytest
from httpx import ASGITransport, AsyncClient
from sqlalchemy import select
from app.db.models import Approval, Project, Run, TelegramLink, Thread, User
from app.db.session import async_session_maker
from app.main import app as fastapi_app
from app.tools.base import ToolContext, tool_registry
import app.tools.file_tools  # noqa: F401


@pytest.mark.asyncio
async def test_phase7_complete_flow() -> None:
    # 1. Setup Project & Thread in DB
    async with async_session_maker() as db:
        u_res = await db.execute(select(User).limit(1))
        user = u_res.scalars().first()
        if not user:
            user = User(github_login="approver-demo", role="admin")
            db.add(user)
            await db.flush()

        proj = Project(owner_id=user.id, name="Phase 7 Test Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        th = Thread(project_id=proj.id, agent_key="code-builder", title="Thread P7")
        db.add(th)
        await db.flush()

        run = Run(thread_id=th.id, agent_key="code-builder", status="running")
        db.add(run)
        await db.commit()

        # 2. Test ExportZipTool Approval Gate
        zip_tool = tool_registry.get("export_zip")
        assert zip_tool is not None

        ctx = ToolContext(
            project_id=proj.id,
            run_id=run.id,
            workspace_path="/tmp",
            db=db,
            agent_key="code-builder",
        )
        tool_res = await zip_tool.execute(ctx, archive_name="release.zip")
        assert tool_res.get("status") == "waiting_approval"
        appr_id = tool_res.get("approval_id")
        assert appr_id is not None

    # 3. Test API Client Endpoints
    transport = ASGITransport(app=fastapi_app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        # 3.1 Test respond approval
        resp_res = await ac.post(f"/approvals/{appr_id}/respond", json={"action": "approve", "comment": "Approved by lead"})
        assert resp_res.status_code == 200
        assert resp_res.json()["status"] == "approved"

        # 3.2 Test telegram link-code
        link_res = await ac.post("/telegram/link-code")
        assert link_res.status_code == 200
        code = link_res.json()["link_code"]
        assert code.startswith("ALIGN-")

        # 3.3 Test telegram webhook with /link command
        import random
        fake_chat_id = random.randint(100000000, 999999999)
        webhook_payload = {
            "update_id": 9999,
            "message": {
                "message_id": 10,
                "chat": {"id": fake_chat_id, "type": "private"},
                "from": {"id": fake_chat_id, "first_name": "DevLead"},
                "text": f"/link {code}",
            },
        }
        wb_res = await ac.post("/telegram/webhook", json=webhook_payload)
        assert wb_res.status_code == 200
        assert wb_res.json()["ok"] is True
