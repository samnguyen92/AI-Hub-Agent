"""Unit and integration tests for Phase 1b Authentication, Multi-tenant Isolation, and Signed URLs."""

import os
import time
import pytest
from httpx import ASGITransport, AsyncClient
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.db.models import Approval, Project, Run, TelegramLink, Thread, User
from app.db.session import async_session_maker
from app.main import app
from app.services.approval_service import approval_service
from app.services.auth_service import auth_service
from app.services.workspace import workspace_service


@pytest.fixture
async def api_client():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        yield client


@pytest.mark.asyncio
async def test_auth_login_and_me(api_client: AsyncClient) -> None:
    """Test login sets session token cookie and returns valid user response."""
    res = await api_client.post("/auth/login", json={"username": "alice_test"})
    assert res.status_code == 200
    data = res.json()
    assert data["username"] == "alice_test"
    assert data["role"] == "member"
    assert "token" in data

    token = data["token"]
    # Check /auth/me with Bearer token
    me_res = await api_client.get("/auth/me", headers={"Authorization": f"Bearer {token}"})
    assert me_res.status_code == 200
    assert me_res.json()["id"] == data["id"]


@pytest.mark.asyncio
async def test_auth_invalid_token_rejected(api_client: AsyncClient) -> None:
    """Test that invalid Bearer token returns 401."""
    res = await api_client.get("/auth/me", headers={"Authorization": "Bearer invalid_garbage_token"})
    assert res.status_code == 401
    assert "Token Bearer không hợp lệ" in res.json()["detail"]


@pytest.mark.asyncio
async def test_multi_tenant_project_isolation(api_client: AsyncClient) -> None:
    """User B cannot view or delete User A's project (returns 403)."""
    async with async_session_maker() as db:
        user_a = await auth_service.get_or_create_user(db, "user_a", role="member")
        user_b = await auth_service.get_or_create_user(db, "user_b", role="member")

    token_a = auth_service.create_session_token(user_a.id, role="member")
    token_b = auth_service.create_session_token(user_b.id, role="member")

    # User A creates a project
    create_res = await api_client.post(
        "/projects",
        json={"name": "Project Alpha"},
        headers={"Authorization": f"Bearer {token_a}"},
    )
    assert create_res.status_code == 200
    proj_id = create_res.json()["id"]

    # User A can view the project
    view_a = await api_client.get(f"/projects/{proj_id}", headers={"Authorization": f"Bearer {token_a}"})
    assert view_a.status_code == 200

    # User B receives 403 Forbidden
    view_b = await api_client.get(f"/projects/{proj_id}", headers={"Authorization": f"Bearer {token_b}"})
    assert view_b.status_code == 403

    # User B cannot delete project A
    del_b = await api_client.delete(f"/projects/{proj_id}", headers={"Authorization": f"Bearer {token_b}"})
    assert del_b.status_code == 403


@pytest.mark.asyncio
async def test_signed_url_file_preview(api_client: AsyncClient) -> None:
    """Test that signed file URL works without auth headers, and tampered URL fails."""
    async with async_session_maker() as db:
        user = await auth_service.get_or_create_user(db, "preview_user", role="member")
        proj = Project(owner_id=user.id, name="Signed Preview Proj", workspace_path="")
        db.add(proj)
        await db.flush()
        proj.workspace_path = workspace_service.init_project_workspace(proj.id)
        await db.commit()
        await db.refresh(proj)

        # Write sample HTML file in project workspace
        test_file_path = os.path.join(proj.workspace_path, "preview.html")
        with open(test_file_path, "w", encoding="utf-8") as f:
            f.write("<html><body><h1>Preview Test</h1></body></html>")

        # Generate valid signed URL
        signed_url = auth_service.create_signed_file_url(proj.id, "preview.html", expires_in=300)

    # 1. Fetch with signed URL (no auth header/cookie)
    res = await api_client.get(signed_url, follow_redirects=True)
    assert res.status_code == 200
    assert "<h1>Preview Test</h1>" in res.text
    assert res.headers.get("content-security-policy") == "sandbox allow-scripts"
    assert res.headers.get("x-content-type-options") == "nosniff"

    # 2. Tampered signature fails with 401 (since no auth fallback)
    tampered_url = signed_url.replace("signature=", "signature=tampered_")
    res_tampered = await api_client.get(
        tampered_url,
        headers={"Authorization": "Bearer bad_token"},
    )
    assert res_tampered.status_code == 401

    # 3. Expired signature fails
    expired_url = auth_service.create_signed_file_url(proj.id, "preview.html", expires_in=-10)
    res_expired = await api_client.get(
        expired_url,
        headers={"Authorization": "Bearer bad_token"},
    )
    assert res_expired.status_code == 401


@pytest.mark.asyncio
async def test_approval_service_telegram_and_web_permissions(api_client: AsyncClient) -> None:
    """Test approval decisions require verified ownership via Web token or Telegram link."""
    import uuid

    rand_tg = f"tg_{uuid.uuid4().hex[:8]}"
    async with async_session_maker() as db:
        owner = await auth_service.get_or_create_user(db, f"owner_{uuid.uuid4().hex[:6]}", role="member")
        stranger = await auth_service.get_or_create_user(db, f"stranger_{uuid.uuid4().hex[:6]}", role="member")

        proj = Project(owner_id=owner.id, name="Appr Proj", workspace_path="")
        db.add(proj)
        await db.flush()
        proj.workspace_path = workspace_service.init_project_workspace(proj.id)

        thread = Thread(project_id=proj.id, agent_key="design", title="Appr Thread")
        db.add(thread)
        await db.flush()

        run = Run(thread_id=thread.id, agent_key="design", status="waiting_approval")
        db.add(run)
        await db.flush()

        approval = Approval(
            run_id=run.id,
            action_type="gate_design",
            status="pending",
            payload={"title": "Design Gate Review"},
        )
        db.add(approval)

        # Telegram link for owner
        link = TelegramLink(user_id=owner.id, telegram_id=rand_tg, is_confirmed=True)
        db.add(link)
        await db.commit()
        await db.refresh(approval)

        # 1. Stranger fails to approve via Web
        with pytest.raises(PermissionError):
            await approval_service.decide(
                approval_id=approval.id,
                decision="approve",
                db=db,
                user=stranger,
            )

        # 2. Unlinked telegram chat fails to approve
        with pytest.raises(PermissionError):
            await approval_service.decide(
                approval_id=approval.id,
                decision="approve",
                db=db,
                telegram_chat_id="unlinked_tg_9999",
            )

        # 3. Linked owner chat succeeds
        appr_result = await approval_service.decide(
            approval_id=approval.id,
            decision="approve",
            db=db,
            telegram_chat_id=rand_tg,
        )
        assert appr_result.status == "approved"
