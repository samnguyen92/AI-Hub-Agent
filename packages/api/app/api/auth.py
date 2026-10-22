"""Authentication dependencies and routes."""

import hmac
import logging
import secrets
from typing import Any, Dict, Optional
from fastapi import APIRouter, Cookie, Depends, Header, HTTPException, Request, Response
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from fastapi.responses import RedirectResponse

from app.config import settings
from app.db.models import Project, User
from app.db.session import get_db
from app.services.auth_service import auth_service
from app.services.google_oauth import build_google_authorization_url, fetch_google_profile

router = APIRouter(prefix="/auth", tags=["auth"])
logger = logging.getLogger(__name__)
OAUTH_STATE_COOKIE = "google_oauth_state"


class LoginRequest(BaseModel):
    username: str
    token: Optional[str] = None


class UserResponse(BaseModel):
    id: str
    username: str
    role: str
    token: str


async def get_current_user(
    request: Request,
    authorization: Optional[str] = Header(None),
    session_token: Optional[str] = Cookie(None),
    db: AsyncSession = Depends(get_db),
) -> User:
    """Dependency extracting the authenticated user via Bearer token, Cookie, or Dev fallback."""
    # 1. Bearer Token check
    if authorization and authorization.startswith("Bearer "):
        token_str = authorization.split(" ", 1)[1].strip()
        if settings.admin_api_token and token_str == settings.admin_api_token:
            return await auth_service.get_or_create_user(db, "admin", role="admin")

        payload = auth_service.verify_session_token(token_str)
        if payload and "uid" in payload:
            user = (await db.execute(select(User).where(User.id == payload["uid"]))).scalars().first()
            if user:
                return user
        raise HTTPException(
            status_code=401,
            detail="Token Bearer không hợp lệ hoặc đã hết hạn.",
        )

    # 2. Session Cookie check
    if session_token:
        payload = auth_service.verify_session_token(session_token)
        if payload and "uid" in payload:
            user = (await db.execute(select(User).where(User.id == payload["uid"]))).scalars().first()
            if user:
                return user
        raise HTTPException(
            status_code=401,
            detail="Phiên đăng nhập không hợp lệ hoặc đã hết hạn.",
        )

    # 3. Development Mode Fallback
    if settings.auth_mode == "dev":
        client_host = request.client.host if request and request.client else "localhost"
        is_local = client_host in ("127.0.0.1", "::1", "localhost", "testclient")
        if is_local:
            return await auth_service.get_or_create_user(db, "developer", role="admin")

    raise HTTPException(
        status_code=401,
        detail="Chưa xác thực: Vui lòng đăng nhập hoặc cung cấp Authorization header hợp lệ.",
    )


async def verify_project_ownership(
    project_id: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> Project:
    """Ensure that the project exists and is owned by the current user (or user is admin)."""
    q = select(Project).where(Project.id == project_id)
    project = (await db.execute(q)).scalars().first()
    if not project:
        raise HTTPException(status_code=404, detail="Không tìm thấy dự án.")

    if project.owner_id != user.id and user.role != "admin":
        raise HTTPException(
            status_code=403,
            detail="Bị từ chối: Bạn không có quyền truy cập hoặc thao tác trên dự án này.",
        )
    return project


@router.post("/login", response_model=UserResponse)
async def login(
    req: LoginRequest,
    response: Response,
    db: AsyncSession = Depends(get_db),
) -> UserResponse:
    """Create local development or explicitly token-authorized sessions."""
    is_dev_admin = settings.auth_mode == "dev" and req.username == "developer"
    is_token_admin = bool(settings.admin_api_token and req.token == settings.admin_api_token)
    if settings.auth_mode != "dev" and not is_token_admin:
        raise HTTPException(status_code=403, detail="Use Google sign-in to access this hub.")
    role = "admin" if is_dev_admin or is_token_admin else "member"
    user = await auth_service.get_or_create_user(db, req.username, role=role)
    token = auth_service.create_session_token(user.id, role=user.role)

    response.set_cookie(
        key="session_token",
        value=token,
        httponly=True,
        secure=settings.environment.lower() not in ("development", "dev", "test"),
        samesite="lax",
        max_age=86400 * 7,
    )
    return UserResponse(id=user.id, username=user.github_login, role=user.role, token=token)


@router.get("/google/login")
async def google_login() -> RedirectResponse:
    """Start Google sign-in and bind the callback to a short-lived state cookie."""
    if not settings.google_client_id or not settings.google_client_secret:
        raise HTTPException(status_code=503, detail="Google sign-in is not configured.")
    state = secrets.token_urlsafe(32)
    redirect = RedirectResponse(build_google_authorization_url(state), status_code=302)
    redirect.set_cookie(
        OAUTH_STATE_COOKIE,
        state,
        httponly=True,
        secure=settings.environment.lower() not in ("development", "dev", "test"),
        samesite="lax",
        max_age=600,
        path="/auth/google/callback",
    )
    return redirect


@router.get("/google/config")
async def google_config() -> Dict[str, bool]:
    """Expose whether Google sign-in is configured without returning credentials."""
    return {
        "enabled": bool(
            settings.google_client_id
            and settings.google_client_secret
            and settings.google_email_allowlist
        )
    }


@router.get("/google/callback")
async def google_callback(
    code: Optional[str] = None,
    state: Optional[str] = None,
    error: Optional[str] = None,
    oauth_state: Optional[str] = Cookie(None, alias=OAUTH_STATE_COOKIE),
    db: AsyncSession = Depends(get_db),
) -> RedirectResponse:
    """Validate Google identity and create an allowlisted Hub session."""
    if error or not code or not state or not oauth_state or not hmac.compare_digest(state, oauth_state):
        return _google_failure_redirect("google_signin_failed")
    try:
        profile = await fetch_google_profile(code)
    except Exception:
        logger.exception("Google OAuth callback failed while retrieving the verified profile")
        return _google_failure_redirect("google_signin_failed")

    email = str(profile["email"]).strip().lower()
    if email not in settings.google_email_allowlist:
        return _google_failure_redirect("account_not_allowed")
    role = "admin" if email in settings.google_admin_allowlist else "member"
    user = await auth_service.get_or_create_user(db, email, role=role)
    user.role = role
    await db.commit()
    redirect = RedirectResponse(f"{settings.google_frontend_url.rstrip('/')}/chat?login=success", status_code=302)
    _set_session_cookie(redirect, auth_service.create_session_token(user.id, role=user.role))
    redirect.delete_cookie(OAUTH_STATE_COOKIE, path="/auth/google/callback")
    return redirect


def _google_failure_redirect(reason: str) -> RedirectResponse:
    """Return users to the sign-in page with a non-sensitive failure code."""
    return RedirectResponse(f"{settings.google_frontend_url.rstrip('/')}/login?error={reason}", status_code=302)


def _set_session_cookie(response: Response, token: str) -> None:
    """Set the signed session as an HttpOnly cookie."""
    response.set_cookie(
        key="session_token",
        value=token,
        httponly=True,
        secure=settings.environment.lower() not in ("development", "dev", "test"),
        samesite="lax",
        max_age=86400 * 7,
    )


@router.get("/me", response_model=UserResponse)
async def get_me(user: User = Depends(get_current_user)) -> UserResponse:
    """Return the profile of the currently authenticated user."""
    token = auth_service.create_session_token(user.id, role=user.role)
    return UserResponse(id=user.id, username=user.github_login, role=user.role, token=token)


@router.post("/logout")
async def logout(response: Response) -> Dict[str, str]:
    """Clear session cookie upon logout."""
    response.delete_cookie(key="session_token")
    return {"status": "logged_out"}
