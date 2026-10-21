import asyncio
import json
from typing import Any, AsyncGenerator, Dict, List, Optional

from fastapi import APIRouter, Depends, HTTPException
from fastapi.responses import StreamingResponse
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user, verify_project_ownership
from app.db.models import Message, Run, Thread, User
from app.db.session import get_db
from app.services.agent_runner import agent_runner
from app.services.gateway import IncomingMessagePayload, message_gateway

router = APIRouter(tags=["threads"])


class SendMessageRequest(BaseModel):
    content: str
    thread_id: Optional[str] = None
    agent_override: Optional[str] = None
    run_synchronously: bool = False


class ThreadResponse(BaseModel):
    id: str
    project_id: str
    agent_key: str
    channel: str
    title: str


class MessageResponse(BaseModel):
    id: str
    role: str
    content: str
    attachments: List[Dict[str, Any]] = []


@router.get("/projects/{project_id}/threads", response_model=List[ThreadResponse])
async def list_threads(project_id: str, user: User = Depends(get_current_user), db: AsyncSession = Depends(get_db)) -> List[ThreadResponse]:
    """Retrieve all conversation threads in a project."""
    await verify_project_ownership(project_id, user, db)
    res = await db.execute(
        select(Thread).where(Thread.project_id == project_id).order_by(Thread.created_at.desc())
    )
    threads = res.scalars().all()
    return [
        ThreadResponse(
            id=t.id,
            project_id=t.project_id,
            agent_key=t.agent_key,
            channel=t.channel,
            title=t.title,
        )
        for t in threads
    ]


@router.get("/threads/{thread_id}/messages", response_model=List[MessageResponse])
async def get_thread_messages(thread_id: str, user: User = Depends(get_current_user), db: AsyncSession = Depends(get_db)) -> List[MessageResponse]:
    """Get all messages within a specific thread."""
    await _authorize_thread(thread_id, user, db)
    res = await db.execute(
        select(Message).where(Message.thread_id == thread_id).order_by(Message.created_at.asc())
    )
    msgs = res.scalars().all()
    return [
        MessageResponse(
            id=m.id,
            role=m.role,
            content=m.content,
            attachments=m.attachments or [],
        )
        for m in msgs
    ]


@router.post("/projects/{project_id}/messages")
async def send_message(
    project_id: str,
    req: SendMessageRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> Dict[str, Any]:
    """Send a user message, normalize via gateway, and dispatch run."""
    await verify_project_ownership(project_id, user, db)
    payload = IncomingMessagePayload(
        project_id=project_id,
        thread_id=req.thread_id,
        content=req.content,
        agent_override=req.agent_override,
        run_synchronously=req.run_synchronously,
    )
    try:
        result = await message_gateway.process_incoming_message(payload, db)
    except PermissionError as exc:
        raise HTTPException(status_code=403, detail=str(exc)) from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    # If run_synchronously requested, execute directly
    if req.run_synchronously:
        exec_res = await agent_runner.execute_run(result["run_id"], db)
        return {**result, "execution": exec_res}

    # The database worker is the sole executor for queued runs.
    return result

@router.get("/threads/{thread_id}/status")
async def get_thread_status(
    thread_id: str, user: User = Depends(get_current_user), db: AsyncSession = Depends(get_db)
) -> Dict[str, Any]:
    """Get the latest run status for a thread."""
    await _authorize_thread(thread_id, user, db)
    res = await db.execute(
        select(Run).where(Run.thread_id == thread_id).order_by(Run.created_at.desc())
    )
    run = res.scalars().first()
    return {
        "thread_id": thread_id,
        "run_id": run.id if run else None,
        "status": run.status if run else "none",
        "cost_usd": run.cost_usd if run else 0.0,
    }


@router.get("/threads/{thread_id}/stream")
async def stream_thread(thread_id: str, user: User = Depends(get_current_user), db: AsyncSession = Depends(get_db)) -> StreamingResponse:
    """Stream thread updates to client via Server-Sent Events (SSE)."""
    await _authorize_thread(thread_id, user, db)

    async def event_generator() -> AsyncGenerator[str, None]:
        # Polling generator emitting new assistant messages
        last_count = 0
        for _ in range(30):  # Stream for up to 30 seconds
            res = await db.execute(
                select(Message).where(Message.thread_id == thread_id).order_by(Message.created_at.asc())
            )
            messages = res.scalars().all()
            if len(messages) > last_count:
                new_msgs = messages[last_count:]
                last_count = len(messages)
                for msg in new_msgs:
                    data = json.dumps({"role": msg.role, "content": msg.content})
                    yield f"event: message\ndata: {data}\n\n"
            await asyncio.sleep(1.0)
        yield "event: ping\ndata: {}\n\n"

    return StreamingResponse(event_generator(), media_type="text/event-stream")


async def _authorize_thread(thread_id: str, user: User, db: AsyncSession) -> Thread:
    """Verify the thread exists and belongs to an accessible project."""
    thread = (await db.execute(select(Thread).where(Thread.id == thread_id))).scalars().first()
    if not thread:
        raise HTTPException(status_code=404, detail="Thread not found")
    await verify_project_ownership(thread.project_id, user, db)
    return thread
