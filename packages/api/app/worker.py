"""PostgreSQL-backed queue worker for running pending agent tasks."""

import asyncio
import logging
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Run
from app.db.session import async_session_maker
from app.services.agent_runner import agent_runner

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("hub_worker")


async def pick_and_execute_job(db: AsyncSession) -> bool:
    """Fetch next queued run using SELECT FOR UPDATE SKIP LOCKED."""
    q = (
        select(Run)
        .where(Run.status == "queued")
        .order_by(Run.created_at.asc())
        .limit(1)
        .with_for_update(skip_locked=True)
    )
    res = await db.execute(q)
    run = res.scalars().first()
    if not run:
        return False

    logger.info(f"Picked run {run.id} for agent '{run.agent_key}'")
    try:
        await agent_runner.execute_run(run.id, db)
        logger.info(f"Run {run.id} completed successfully")
    except Exception as exc:
        logger.error(f"Error processing run {run.id}: {exc}", exc_info=True)
        run.status = "failed"
        await db.commit()
    return True


async def run_worker_loop(poll_interval: float = 1.0) -> None:
    """Continuous polling loop."""
    logger.info("Hub AI Agent Worker started...")
    while True:
        try:
            async with async_session_maker() as db:
                found = await pick_and_execute_job(db)
            if not found:
                await asyncio.sleep(poll_interval)
        except Exception as exc:
            logger.error(f"Worker loop error: {exc}")
            await asyncio.sleep(poll_interval)


if __name__ == "__main__":
    asyncio.run(run_worker_loop())
