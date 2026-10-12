"""Message Gateway normalizing incoming messages from Web and Telegram."""

import re
from typing import Any, Dict, List, Optional, Tuple
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import LibraryItem, Message, ProjectNote, Run, Thread


class IncomingMessagePayload(BaseModel):
    """Payload representing an inbound user message."""

    project_id: str
    thread_id: Optional[str] = None
    channel: str = "web"  # web or telegram
    content: str
    attachments: List[Dict[str, Any]] = []
    agent_override: Optional[str] = None


class MessageGateway:
    """Gateway standardizing user requests and provisioning execution runs."""

    def extract_agent_mention(self, content: str) -> Tuple[Optional[str], str]:
        """Extract @mention for target agent if present."""
        match = re.search(r"@([a-zA-Z0-9_-]+)", content)
        if match:
            agent_key = match.group(1).lower()
            clean_content = re.sub(r"\s+", " ", content.replace(match.group(0), "")).strip()
            return agent_key, clean_content
        return None, content.strip()

    async def process_incoming_message(
        self, payload: IncomingMessagePayload, db: AsyncSession
    ) -> Dict[str, Any]:
        """Normalize message, store attachments to library, and enqueue run."""
        # Check /learn quick command
        stripped = payload.content.strip()
        if stripped.startswith("/learn "):
            rule_text = stripped[7:].strip()
            note = ProjectNote(
                project_id=payload.project_id,
                content=f"[LEARNED_RULE:USER] {rule_text}",
                status="approved",
            )
            db.add(note)

            # Get or create thread
            thread_id = payload.thread_id
            if not thread_id:
                thread = Thread(
                    project_id=payload.project_id,
                    agent_key="assistant",
                    channel=payload.channel,
                    title=f"Học: {rule_text[:30]}",
                )
                db.add(thread)
                await db.flush()
                thread_id = thread.id

            db.add(Message(thread_id=thread_id, role="user", content=payload.content))
            ack_msg = Message(
                thread_id=thread_id,
                role="assistant",
                content=f"💡 Đã học và ghi nhớ quy tắc: \"{rule_text}\". Toàn bộ các Agent trong dự án sẽ tự động tuân thủ quy tắc này từ nay về sau.",
            )
            db.add(ack_msg)
            await db.commit()
            return {"thread_id": thread_id, "status": "completed", "learned": True}

        # 1. Resolve agent
        agent_mention, clean_content = self.extract_agent_mention(payload.content)
        target_agent = payload.agent_override or agent_mention or "assistant"

        # 2. Get or create thread
        if payload.thread_id:
            thread_res = await db.execute(select(Thread).where(Thread.id == payload.thread_id))
            thread = thread_res.scalars().first()
            if not thread:
                raise ValueError("Thread not found")
        else:
            thread = Thread(
                project_id=payload.project_id,
                agent_key=target_agent,
                channel=payload.channel,
                title=clean_content[:50] or "New Chat",
            )
            db.add(thread)
            await db.flush()

        # 3. Save attachments to library
        for att in payload.attachments:
            lib_item = LibraryItem(
                project_id=payload.project_id,
                type="upload",
                tag=att.get("tag", "user_upload"),
                description=att.get("filename", "Uploaded file"),
                path=att.get("path", ""),
            )
            db.add(lib_item)

        # 4. Save user message
        msg = Message(
            thread_id=thread.id,
            role="user",
            content=payload.content,
            attachments=payload.attachments,
        )
        db.add(msg)

        # 5. Create queued run
        run = Run(
            thread_id=thread.id,
            agent_key=target_agent,
            status="queued",
        )
        db.add(run)
        await db.commit()

        return {
            "thread_id": thread.id,
            "run_id": run.id,
            "agent_key": target_agent,
            "status": "queued",
        }


message_gateway = MessageGateway()
