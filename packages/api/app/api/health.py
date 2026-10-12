"""Health check and system status router."""

from typing import Dict
from fastapi import APIRouter, Depends
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.session import get_db

router = APIRouter(tags=["health"])


@router.get("/health")
async def health_check() -> Dict[str, str]:
    """Basic liveness probe."""
    return {"status": "ok", "service": "hub-ai-agent-api"}


@router.get("/health/db")
async def db_health_check(db: AsyncSession = Depends(get_db)) -> Dict[str, str]:
    """Readiness probe verifying database connectivity."""
    try:
        await db.execute(text("SELECT 1"))
        return {"status": "ok", "database": "connected"}
    except Exception as exc:
        return {"status": "error", "database": str(exc)}
