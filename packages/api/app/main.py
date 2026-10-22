"""FastAPI main application entry point."""

from contextlib import asynccontextmanager
import os
from typing import AsyncGenerator, Awaitable, Callable
from urllib.parse import urlsplit

from fastapi import FastAPI, Request, Response
from fastapi.middleware.cors import CORSMiddleware

from app.api.agents import router as agents_router
from app.api.approvals import router as approvals_router
from app.api.auth import router as auth_router
from app.api.health import router as health_router
from app.api.library import router as library_router
from app.api.library_references import router as library_references_router
from app.api.notes import router as notes_router
from app.api.projects import router as projects_router
from app.api.router import router as router_api
from app.api.telegram import router as telegram_router
from app.api.threads import router as threads_router
from app.config import settings
from app.db.seed import seed_agents
from app.db.session import Base, async_session_maker, engine
import app.db.models  # noqa: F401 - register all tables to Base.metadata
import app.db.reference_models  # noqa: F401 - register design reference table
import app.tools.library_tools  # noqa: F401 - register library tools
import app.tools.file_tools  # noqa: F401 - register file tools
import app.tools.render_tools  # noqa: F401 - register render tools
import app.tools.image_tools  # noqa: F401 - register image generation tools
import app.tools.skill_tools  # noqa: F401 - register skill tools
import app.tools.web_tools  # noqa: F401 - register web tools
import app.tools.seo_tools  # noqa: F401 - register seo tools
import app.tools.design_tools  # noqa: F401 - register design & a11y tools
import app.tools.gate_tools  # noqa: F401 - register gate tools
import app.tools.report_tools  # noqa: F401 - register report tools
import app.tools.memory_tools  # noqa: F401 - register memory tools
import app.tools.reference_tools  # noqa: F401 - register design reference tool
import app.tools.reference_proposal_tools  # noqa: F401 - register reference selection tool
import app.tools.design_package_tools  # noqa: F401 - register design packaging tool
import app.tools.vision_tools  # noqa: F401 - register optional screenshot vision tool
import app.tools.quality_review_tools  # noqa: F401 - register quality delegation tool
import app.tools.chat_attachment_tools  # noqa: F401 - register chat attachment analysis tool


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncGenerator[None, None]:
    """Application lifespan manager initializing db schema on startup."""
    from app.services.agent_loader import agent_loader
    if len(settings.auth_secret_key) < 32 or settings.auth_secret_key.startswith("replace"):
        raise RuntimeError("AUTH_SECRET_KEY must be configured with at least 32 characters.")
    hosted = bool(os.getenv("VERCEL") or os.getenv("VERCEL_API_PREFIX"))
    if hosted:
        _validate_hosted_configuration()
    agent_loader.validate_agents()

    if not hosted and settings.environment.lower() in ("development", "dev", "test"):
        async with engine.begin() as conn:
            await conn.run_sync(Base.metadata.create_all)
    async with async_session_maker() as db:
        await seed_agents(db)
    yield
    await engine.dispose()


def _validate_hosted_configuration() -> None:
    """Reject unsafe or incomplete settings before starting a Vercel API instance."""
    if settings.auth_mode == "dev":
        raise RuntimeError("AUTH_MODE=dev is not allowed in the hosted Vercel deployment.")
    sandbox_endpoint = settings.sandbox_internal_url or settings.sandbox_url
    required_secrets = (
        settings.supabase_url,
        settings.supabase_service_role_key,
        sandbox_endpoint,
        settings.sandbox_shared_secret,
    )
    if not all(required_secrets):
        raise RuntimeError("Vercel requires Supabase Storage and authenticated sandbox settings.")
    database_host = urlsplit(settings.database_url).hostname
    if database_host in (None, "localhost", "postgres", "127.0.0.1"):
        raise RuntimeError(
            "Vercel requires DATABASE_URL to point to the Supabase PostgreSQL pooler."
        )
    if (
        not settings.openrouter_api_key
        or settings.openrouter_api_key == "your_openrouter_api_key_here"
    ):
        raise RuntimeError("Set OPENROUTER_API_KEY in the Vercel environment.")


def create_app() -> FastAPI:
    """Application factory configuring routes and middlewares."""
    app = FastAPI(
        title="Hub AI Agent API",
        description="Backend API and Orchestrator for Align AI Hub",
        version="0.1.0",
        lifespan=lifespan,
    )

    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.allowed_origins,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    @app.middleware("http")
    async def strip_vercel_api_prefix(
        request: Request,
        call_next: Callable[[Request], Awaitable[Response]],
    ) -> Response:
        """Strip the public API route prefix before FastAPI matches endpoints."""
        prefix = "/api/backend"
        path = request.scope["path"]
        hosted = bool(os.getenv("VERCEL") or os.getenv("VERCEL_API_PREFIX"))
        if hosted and (path == prefix or path.startswith(prefix + "/")):
            request.scope["path"] = path[len(prefix):] or "/"
            request.scope["raw_path"] = request.scope["path"].encode("utf-8")
        return await call_next(request)

    app.include_router(health_router)
    app.include_router(auth_router)
    app.include_router(projects_router)
    app.include_router(notes_router)
    app.include_router(threads_router)
    app.include_router(library_router)
    app.include_router(library_references_router)
    app.include_router(router_api)
    app.include_router(approvals_router)
    app.include_router(telegram_router)
    app.include_router(agents_router)
    return app


app = create_app()
