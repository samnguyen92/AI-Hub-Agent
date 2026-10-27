"""Tests for WordPress management tools (wp_check_site, wp_manage_posts, plugins, themes)."""

import pytest
from unittest.mock import AsyncMock, patch
from app.db.session import async_session_maker
from app.db.models import Project, User
from app.db.wp_models import WordPressSite
from app.tools.base import ToolContext, tool_registry


@pytest.mark.asyncio
async def test_wp_check_site_no_site():
    """Verify wp_check_site returns graceful error if no site is linked."""
    tool = tool_registry.get("wp_check_site")
    assert tool is not None

    async with async_session_maker() as db:
        context = ToolContext(
            project_id="non-existent-proj",
            run_id="test_run_1",
            workspace_path="/tmp",
            db=db,
            agent_key="assistant",
        )
        res = await tool.execute(context)
        assert res["success"] is False
        assert "chưa liên kết" in res["error"]


@pytest.mark.asyncio
async def test_wp_manage_posts_external():
    """Verify wp_manage_posts routes properly for an external WordPress site."""
    tool = tool_registry.get("wp_manage_posts")
    assert tool is not None

    async with async_session_maker() as db:
        user = User(github_login="wp_test_user", role="admin")
        db.add(user)
        await db.flush()

        proj = Project(owner_id=user.id, name="Test Ext WP Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        site = WordPressSite(
            project_id=proj.id,
            site_type="external",
            site_url="https://example.com",
            admin_user="admin",
            admin_password="app_password_123",
            status="running",
            container_name=f"ext_{proj.id}",
        )
        db.add(site)
        await db.commit()

        context = ToolContext(
            project_id=proj.id,
            run_id="test_run_2",
            workspace_path="/tmp",
            db=db,
            agent_key="wordpress-builder",
        )

        with patch(
            "app.services.wp_external.wp_external_service.create_post",
            new=AsyncMock(return_value={"success": True, "post_id": 42, "title": "Test Blog", "link": "https://example.com/blog/1"}),
        ):
            res = await tool.execute(context, action="create", title="Test Blog", content="Blog content")
            assert res["success"] is True
            assert res["post_id"] == 42


@pytest.mark.asyncio
async def test_wp_manage_plugins_external():
    """Verify wp_manage_plugins routes properly for an external WordPress site."""
    tool = tool_registry.get("wp_manage_plugins")
    assert tool is not None

    async with async_session_maker() as db:
        user = User(github_login="wp_test_user2", role="admin")
        db.add(user)
        await db.flush()

        proj = Project(owner_id=user.id, name="Test Ext WP Proj 2", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        site = WordPressSite(
            project_id=proj.id,
            site_type="external",
            site_url="https://example.com",
            admin_user="admin",
            admin_password="app_password_123",
            status="running",
            container_name=f"ext_{proj.id}",
        )
        db.add(site)
        await db.commit()

        context = ToolContext(
            project_id=proj.id,
            run_id="test_run_3",
            workspace_path="/tmp",
            db=db,
            agent_key="wordpress-builder",
        )

        with patch(
            "app.services.wp_external.wp_external_service.manage_plugins",
            new=AsyncMock(return_value={"success": True, "plugins": [{"plugin": "fluent-forms", "status": "active"}]}),
        ):
            res = await tool.execute(context, action="list")
            assert res["success"] is True
            assert len(res["plugins"]) == 1
