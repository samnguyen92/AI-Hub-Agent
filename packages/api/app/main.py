"""FastAPI main application entry point."""

from contextlib import asynccontextmanager
from typing import AsyncGenerator
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.agents import router as agents_router
from app.api.approvals import router as approvals_router
from app.api.auth import router as auth_router
from app.api.health import router as health_router
from app.api.library import router as library_router
from app.api.notes import router as notes_router
from app.api.projects import router as projects_router
from app.api.router import router as router_api
from app.api.telegram import router as telegram_router
from app.api.threads import router as threads_router
from app.config import settings
from app.db.seed import seed_agents
from app.db.session import Base, async_session_maker, engine
import app.db.models  # noqa: F401 - register all tables to Base.metadata
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


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncGenerator[None, None]:
    """Application lifespan manager initializing db schema on startup."""
    from app.services.agent_loader import agent_loader
    agent_loader.validate_agents()

    async with engine.begin() as conn:
        # Auto-create tables in development
        await conn.run_sync(Base.metadata.create_all)
    async with async_session_maker() as db:
        await seed_agents(db)
    yield
    await engine.dispose()


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

    app.include_router(health_router)
    app.include_router(auth_router)
    app.include_router(projects_router)
    app.include_router(notes_router)
    app.include_router(threads_router)
    app.include_router(library_router)
    app.include_router(router_api)
    app.include_router(approvals_router)
    app.include_router(telegram_router)
    app.include_router(agents_router)
    return app


app = create_app()
