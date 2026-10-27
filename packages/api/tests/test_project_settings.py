"""Tests for Project Settings: WordPress, Team Members, Progress, and Telegram."""

import pytest
from httpx import ASGITransport, AsyncClient
from app.main import app
from app.db.models import Project, User
from app.db.session import async_session_maker


@pytest.mark.asyncio
async def test_project_settings_lifecycle():
    """Test retrieving and updating project settings."""
    async with async_session_maker() as db:
        # Create test user and project
        user = User(github_login="test_settings_user", role="admin")
        db.add(user)
        await db.flush()

        project = Project(owner_id=user.id, name="Test Settings Project", workspace_path="/tmp/test_ws")
        db.add(project)
        await db.commit()
        project_id = project.id

    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
        # Login dev user
        await client.post("/auth/login", json={"username": "developer"})

        # 1. Get settings
        res = await client.get(f"/projects/{project_id}/settings")
        assert res.status_code == 200
        data = res.json()
        assert "project" in data
        assert "wordpress" in data
        assert "telegram" in data
        assert "members" in data
        assert "progress" in data
        assert len(data["progress"]["milestones"]) == 4

        # 2. Add member
        m_res = await client.post(
            f"/projects/{project_id}/settings/members",
            json={"email": "designer@example.com", "role": "editor"},
        )
        assert m_res.status_code == 200
        m_data = m_res.json()
        assert m_data["success"] is True
        member_id = m_data["member"]["id"]

        # 3. Delete member
        del_res = await client.delete(f"/projects/{project_id}/settings/members/{member_id}")
        assert del_res.status_code == 200
        assert del_res.json()["success"] is True

        # 4. Generate Telegram link code
        tg_res = await client.post(f"/projects/{project_id}/settings/telegram/link-code")
        assert tg_res.status_code == 200
        tg_data = tg_res.json()
        assert "link_code" in tg_data
        assert tg_data["link_code"].startswith("ALIGN-")
