"""Pytest configuration and async database fixtures."""

import pytest
from app.db.session import engine


@pytest.fixture(autouse=True)
async def cleanup_db_engine() -> None:
    """Ensure SQLAlchemy connection pool does not leak across different event loops."""
    await engine.dispose()
    yield
    await engine.dispose()
