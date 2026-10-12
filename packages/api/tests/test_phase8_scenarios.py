"""Unit and Integration tests for Phase 8: Memory, Agents and Scenarios."""

import pytest
from httpx import ASGITransport, AsyncClient
from sqlalchemy import select

from app.db.models import Agent, Project, ProjectNote, Run, Thread, User
from app.db.session import async_session_maker
from app.main import app
from app.services.agent_runner import agent_runner
from app.tools.base import ToolContext
from app.tools.memory_tools import ProposeNoteTool, ListProjectNotesTool


@pytest.mark.asyncio
async def test_propose_and_list_memory_notes() -> None:
    """Test memory tools propose_note and list_notes in database."""
    async with async_session_maker() as db:
        user_res = await db.execute(select(User).where(User.github_login == "tester-memory"))
        user = user_res.scalars().first()
        if not user:
            user = User(github_login="tester-memory", role="user")
            db.add(user)
            await db.flush()

        proj = Project(owner_id=user.id, name="Memory Test Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        thread = Thread(project_id=proj.id, agent_key="design", title="Memory Thread")
        db.add(thread)
        await db.flush()

        run = Run(thread_id=thread.id, agent_key="design", status="running")
        db.add(run)
        await db.commit()

        tool_ctx = ToolContext(
            project_id=proj.id,
            run_id=run.id,
            workspace_path="/tmp",
            db=db,
            agent_key="design",
        )

        propose_tool = ProposeNoteTool()
        res = await propose_tool.execute(
            tool_ctx, note_content="Khách hàng yêu cầu dùng font serif cho tiêu đề."
        )
        assert res.get("status") == "proposed"
        note_id = res["note_id"]
        assert note_id is not None

        # Manually approve note so list_notes finds it
        note_res = await db.execute(select(ProjectNote).where(ProjectNote.id == note_id))
        db_note = note_res.scalars().first()
        assert db_note is not None
        db_note.status = "approved"
        await db.commit()

        list_tool = ListProjectNotesTool()
        list_res = await list_tool.execute(tool_ctx)
        assert list_res["count"] >= 1
        assert any("font serif" in n for n in list_res["approved_notes"])


@pytest.mark.asyncio
async def test_project_notes_api_endpoints() -> None:
    """Test REST API for listing, creating, and responding to notes."""
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        # Create a project first
        p_res = await client.post("/projects", json={"name": "Notes API Proj"})
        assert p_res.status_code == 200
        proj_id = p_res.json()["id"]

        # 1. Create a manual note
        c_res = await client.post(
            f"/projects/{proj_id}/notes",
            json={"content": "Luôn dùng palette màu tím thương hiệu"},
        )
        assert c_res.status_code == 200
        assert c_res.json()["status"] == "approved"

        # 2. List notes
        l_res = await client.get(f"/projects/{proj_id}/notes")
        assert l_res.status_code == 200
        assert len(l_res.json()) >= 1

        # 3. Insert a pending note directly in DB
        async with async_session_maker() as db:
            pending_note = ProjectNote(
                project_id=proj_id,
                content="Cần bổ sung trang FAQ",
                status="pending",
            )
            db.add(pending_note)
            await db.commit()
            await db.refresh(pending_note)
            pending_id = pending_note.id

        # 4. Respond to note (approve)
        r_res = await client.post(
            f"/projects/{proj_id}/notes/{pending_id}/respond",
            json={"action": "approve"},
        )
        assert r_res.status_code == 200
        assert r_res.json()["status"] == "approved"


@pytest.mark.asyncio
async def test_agents_api_crud_and_rules() -> None:
    """Test agents management API endpoints."""
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        # List agents
        res = await client.get("/agents")
        assert res.status_code == 200
        agents = res.json()
        assert len(agents) >= 4
        assert any(a["key"] == "design" for a in agents)

        # Get rules of design agent
        rules_res = await client.get("/agents/design/rules")
        assert rules_res.status_code == 200
        assert "rules" in rules_res.json()

        # Update model for site-analyzer
        patch_res = await client.patch(
            "/agents/site-analyzer",
            json={"model": "deepseek/deepseek-chat"},
        )
        assert patch_res.status_code == 200
        assert patch_res.json()["model"] == "deepseek/deepseek-chat"


@pytest.mark.asyncio
async def test_agent_runner_prompt_with_approved_notes() -> None:
    """Verify that AgentRunner includes approved notes in the prompt."""
    notes = [
        "Align Brand Guide: Primary color #0A0D14, Accent #3B82F6",
        "Luôn có nút Gọi ngay ở header",
    ]
    prompt = agent_runner._build_system_prompt("design", "/tmp/ws", notes=notes)
    assert "=== BỘ NHỚ DỰ ÁN (PROJECT NOTES ĐÃ DUYỆT) ===" in prompt
    assert "Primary color #0A0D14" in prompt
    assert "Luôn có nút Gọi ngay ở header" in prompt
