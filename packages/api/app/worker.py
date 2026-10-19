"""PostgreSQL-backed queue worker for running pending agent tasks."""

import asyncio
from datetime import datetime, timedelta, timezone
import logging
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Run
from app.db.session import async_session_maker
from app.services.agent_runner import agent_runner

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("hub_worker")


async def reap_stale_runs(db: AsyncSession, timeout_seconds: int = 600) -> int:
    """Find and fail runs stuck in 'running' state for longer than timeout."""
    cutoff = datetime.now(timezone.utc) - timedelta(seconds=timeout_seconds)
    q = select(Run).where(Run.status == "running", Run.created_at < cutoff)
    res = await db.execute(q)
    stale_runs = res.scalars().all()
    for r in stale_runs:
        logger.warning(f"Reaping abandoned stale run {r.id}")
        r.status = "failed"
        r.error_detail = "Quá thời gian thực thi tối đa (worker timeout/abandoned)."
    if stale_runs:
        await db.commit()
    return len(stale_runs)


async def pick_and_execute_job(db: AsyncSession) -> bool:
    """Fetch next queued run using SELECT FOR UPDATE SKIP LOCKED and claim atomically."""
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

    run_id = run.id
    logger.info(f"Claiming queued run {run_id} for agent '{run.agent_key}'")
    run.status = "running"
    await db.commit()

    try:
        await agent_runner.execute_run(run_id, db)
        logger.info(f"Run {run_id} finished execution")
    except Exception as exc:
        logger.error(f"Error processing run {run_id}: {exc}", exc_info=True)
        q_ref = select(Run).where(Run.id == run_id)
        res_ref = await db.execute(q_ref)
        fresh_run = res_ref.scalars().first()
        if fresh_run and fresh_run.status not in [
            "completed", "waiting_approval", "waiting_gate_intake",
            "waiting_gate_plan", "waiting_gate_result", "waiting_input",
        ]:
            fresh_run.status = "failed"
            fresh_run.error_detail = str(exc)
            await db.commit()
    return True


async def run_worker_loop(poll_interval: float = 1.0) -> None:
    """Continuous polling worker loop with periodic stale run reaping."""
    logger.info("Hub AI Agent Worker started...")
    iteration = 0
    while True:
        iteration += 1
        try:
            async with async_session_maker() as db:
                if iteration % 60 == 0:
                    await reap_stale_runs(db)
                found = await pick_and_execute_job(db)
            if not found:
                await asyncio.sleep(poll_interval)
        except Exception as exc:
            logger.error(f"Worker loop error: {exc}")
            await asyncio.sleep(poll_interval)


if __name__ == "__main__":
    asyncio.run(run_worker_loop())
