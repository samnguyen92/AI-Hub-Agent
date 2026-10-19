"""Authentication, session token management, and signed URL generation."""

import hashlib
import hmac
import json
import time
from typing import Any, Dict, Optional
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.db.models import User


class AuthService:
    """Provides cryptographic tokens for sessions and signed preview URLs."""

    def __init__(self, secret_key: Optional[str] = None) -> None:
        self.secret_key = (secret_key or settings.auth_secret_key).encode("utf-8")

    def create_session_token(self, user_id: str, role: str = "member", expires_in: int = 86400 * 7) -> str:
        """Create an HMAC-signed session token containing user id and expiration."""
        payload = {
            "uid": user_id,
            "role": role,
            "exp": int(time.time()) + expires_in,
        }
        raw_json = json.dumps(payload, separators=(",", ":"))
        sig = hmac.new(self.secret_key, raw_json.encode("utf-8"), hashlib.sha256).hexdigest()[:32]
        import base64
        b64_payload = base64.urlsafe_b64encode(raw_json.encode("utf-8")).decode("utf-8")
        return f"{b64_payload}.{sig}"

    def verify_session_token(self, token: str) -> Optional[Dict[str, Any]]:
        """Verify session token HMAC and return payload if valid and unexpired."""
        if not token or "." not in token:
            return None
        import base64
        parts = token.split(".", 1)
        try:
            raw_bytes = base64.urlsafe_b64decode(parts[0])
            expected_sig = hmac.new(self.secret_key, raw_bytes, hashlib.sha256).hexdigest()[:32]
            if not hmac.compare_digest(parts[1], expected_sig):
                return None
            data = json.loads(raw_bytes.decode("utf-8"))
            if int(data.get("exp", 0)) < time.time():
                return None
            return data
        except Exception:
            return None

    def create_signed_file_url(self, project_id: str, path: str, expires_in: int = 900) -> str:
        """Generate a short-lived HMAC signed URL for sandboxed preview of HTML/images."""
        expires_at = int(time.time()) + expires_in
        msg = f"{project_id}:{path}:{expires_at}".encode("utf-8")
        sig = hmac.new(self.secret_key, msg, hashlib.sha256).hexdigest()[:32]
        return f"/projects/{project_id}/files?path={path}&signature={sig}&expires={expires_at}"

    def verify_file_signature(self, project_id: str, path: str, signature: str, expires: int) -> bool:
        """Verify HMAC signature for file access without requiring user cookies/headers."""
        if int(expires) < time.time():
            return False
        msg = f"{project_id}:{path}:{expires}".encode("utf-8")
        expected_sig = hmac.new(self.secret_key, msg, hashlib.sha256).hexdigest()[:32]
        return hmac.compare_digest(signature, expected_sig)

    async def get_or_create_user(self, db: AsyncSession, github_login: str, role: str = "member") -> User:
        """Helper to get existing user or create a new user entity."""
        res = await db.execute(select(User).where(User.github_login == github_login))
        user = res.scalars().first()
        if not user:
            user = User(github_login=github_login, role=role)
            db.add(user)
            await db.commit()
            await db.refresh(user)
        return user


auth_service = AuthService()
