import pytest
from sqlalchemy import delete, select
from app.config import Settings
from app.db.models import Project, User
from app.db.session import async_session_maker, engine
from app.services.project_service import project_service

settings = Settings()


@pytest.fixture(scope="session", autouse=True)
def guard_test_environment() -> None:
    """Refuse to run tests against production database."""
    if settings.environment == "production":
        raise RuntimeError("CRITICAL: Pytest suite is configured with environment='production'. Aborting to protect data.")


@pytest.fixture(autouse=True)
async def cleanup_db_engine() -> None:
    """Ensure SQLAlchemy connection pool does not leak across different event loops."""
    await engine.dispose()
    yield
    await engine.dispose()


@pytest.fixture(autouse=True)
async def cleanup_test_data() -> None:
    """Track and automatically clean up any Project and User records created during each test."""
    initial_proj_ids = set()
    initial_user_ids = set()
    query_success = False
    try:
        async with async_session_maker() as db:
            res_p = await db.execute(select(Project.id))
            initial_proj_ids = set(res_p.scalars().all())
            res_u = await db.execute(select(User.id))
            initial_user_ids = set(res_u.scalars().all())
            query_success = True
    except Exception:
        query_success = False

    yield

    if not query_success:
        return

    try:
        async with async_session_maker() as db:
            # Purge any new projects created during the test run
            res_new_p = await db.execute(select(Project))
            for p in res_new_p.scalars().all():
                if p.id not in initial_proj_ids:
                    ws = p.workspace_path
                    await project_service._purge_project_data(p.id, db)
                    project_service._safe_remove_workspace(ws)

            # Purge any new users created during the test run
            res_new_u = await db.execute(select(User))
            for u in res_new_u.scalars().all():
                if u.id not in initial_user_ids:
                    await db.execute(delete(User).where(User.id == u.id))

            await db.commit()
    except Exception:
        pass

