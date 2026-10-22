"""Application configuration loaded from environment variables."""

import os
from typing import List, Optional
from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


def _default_workspaces_root() -> str:
    """Choose ephemeral local storage on Vercel and the mounted volume in Docker."""
    hosted_markers = ("VERCEL", "VERCEL_API_PREFIX", "AIHUB_VERCEL_DAEMON")
    if any(os.getenv(name) for name in hosted_markers):
        return "/tmp/hub-workspaces"
    return "/workspaces"


def _default_auth_mode() -> str:
    """Use secure authentication by default on hosted runtimes."""
    hosted_markers = ("VERCEL", "VERCEL_API_PREFIX", "AIHUB_VERCEL_DAEMON")
    return "strict" if any(os.getenv(name) for name in hosted_markers) else "dev"


class Settings(BaseSettings):
    """Central configuration class for FastAPI backend."""

    environment: str = Field(default="development", description="Runtime environment")
    api_host: str = Field(default="0.0.0.0", description="API listen host")
    api_port: int = Field(default=8000, description="API listen port")

    # Database
    database_url: str = Field(
        default="postgresql+asyncpg://postgres:postgres@localhost:5432/hub_ai_agent",
        description="Async database connection string",
    )
    sync_database_url: str = Field(
        default="postgresql://postgres:postgres@localhost:5432/hub_ai_agent",
        description="Sync database connection string for migrations",
    )

    # LLM & OpenRouter
    openrouter_api_key: str = Field(default="", description="OpenRouter API key")
    default_model: str = Field(
        default="openai/gpt-6-luna", description="Default model name"
    )
    openrouter_vision_model: str = Field(
        default="openai/gpt-6-luna", description="OpenRouter model ID used to inspect chat images and screenshots"
    )

    # CORS
    cors_origins: str = Field(
        default="http://localhost:3000",
        description="Comma-separated list of allowed origins",
    )

    # Storage
    workspaces_root: str = Field(
        default_factory=_default_workspaces_root,
        description="Scratch folder for project workspaces",
    )
    supabase_url: str = Field(default="", description="Supabase project URL for durable workspace storage")
    supabase_service_role_key: str = Field(default="", description="Server-only Supabase Storage credential")
    supabase_storage_bucket: str = Field(default="hub-workspaces", description="Private workspace archive bucket")
    workspace_archive_max_bytes: int = Field(default=41943040, description="Maximum compressed workspace archive size")
    sandbox_url: str = Field(default="http://sandbox:8001", description="Playwright service URL")
    sandbox_internal_url: str = Field(default="", description="Private Vercel service binding for Playwright")
    sandbox_shared_secret: str = Field(default="", description="Shared credential for the Playwright service")

    # Authentication & Session
    auth_mode: str = Field(
        default_factory=_default_auth_mode,
        description="Authentication mode: dev, token, strict, or google",
    )
    auth_secret_key: str = Field(
        default="",
        description="HMAC secret key for signing cookies and URLs",
    )
    admin_api_token: str = Field(
        default="",
        description="Static admin token for CLI and worker access",
    )
    google_client_id: str = Field(default="", description="Google OAuth client ID")
    google_client_secret: str = Field(default="", description="Google OAuth client secret")
    google_redirect_uri: str = Field(
        default="http://localhost:8000/auth/google/callback",
        description="Registered Google OAuth callback URL",
    )
    google_frontend_url: str = Field(
        default="http://localhost:3000", description="Frontend origin for OAuth redirects"
    )
    google_allowed_emails: str = Field(
        default="", description="Comma-separated Google accounts allowed to sign in"
    )
    google_admin_emails: str = Field(
        default="", description="Comma-separated allowed accounts granted admin access"
    )
    local_login_email: str = Field(default="", description="Email allowed to use local password login")
    local_login_password_hash: str = Field(default="", description="PBKDF2 password hash for local login")
    local_login_role: str = Field(default="member", description="Role assigned to the local login account")

    # Telegram Bot
    telegram_bot_token: Optional[str] = Field(
        default=None, description="Telegram bot API token from @BotFather"
    )
    telegram_webhook_secret: Optional[str] = Field(
        default=None, description="Secret token configured on the Telegram webhook"
    )

    # Google PageSpeed Insights
    pagespeed_api_key: Optional[str] = Field(
        default=None, description="Google PageSpeed Insights API key"
    )

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    @property
    def allowed_origins(self) -> List[str]:
        """Parse comma-separated origins into list."""
        return [origin.strip() for origin in self.cors_origins.split(",") if origin.strip()]

    @property
    def google_email_allowlist(self) -> List[str]:
        """Return normalized Google accounts allowed to sign in."""
        return [email.strip().lower() for email in self.google_allowed_emails.split(",") if email.strip()]

    @property
    def google_admin_allowlist(self) -> List[str]:
        """Return normalized Google accounts granted administrator access."""
        return [email.strip().lower() for email in self.google_admin_emails.split(",") if email.strip()]


settings = Settings()
