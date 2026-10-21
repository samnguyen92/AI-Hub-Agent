"""Application configuration loaded from environment variables."""

import secrets
from typing import List, Optional
from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


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
        default="", description="Optional OpenRouter model ID with image input support"
    )

    # CORS
    cors_origins: str = Field(
        default="http://localhost:3000",
        description="Comma-separated list of allowed origins",
    )

    # Storage
    workspaces_root: str = Field(
        default="/workspaces", description="Root folder for project workspaces"
    )

    # Authentication & Session
    auth_mode: str = Field(
        default="dev", description="Authentication mode: dev, token, or strict"
    )
    auth_secret_key: str = Field(
        default_factory=lambda: secrets.token_urlsafe(48),
        description="HMAC secret key for signing cookies and URLs",
    )
    admin_api_token: str = Field(
        default="",
        description="Static admin token for CLI and worker access",
    )

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


settings = Settings()
