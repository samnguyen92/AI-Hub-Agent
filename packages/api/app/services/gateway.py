"""Message Gateway normalizing incoming messages from Web and Telegram."""

import mimetypes
import os
import re
from typing import Any, Dict, List, Optional, Tuple
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Agent, LibraryItem, Message, ProjectNote, Run, Thread
from app.services.router_agent import router_agent
from app.services.workspace import workspace_service
from app.tools.file_tools import safe_resolve_path


class IncomingMessagePayload(BaseModel):
    """Payload representing an inbound user message."""

    project_id: str
    thread_id: Optional[str] = None
    channel: str = "web"  # web or telegram
    content: str
    attachments: List[Dict[str, Any]] = Field(default_factory=list)
    agent_override: Optional[str] = None
    run_synchronously: bool = False


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

    async def validate_attachments(
        self, project_id: str, attachments: List[Dict[str, Any]], db: AsyncSession
    ) -> List[Dict[str, Any]]:
        """Accept only files uploaded into this project's chat attachment folder."""
        if len(attachments) > 5:
            raise ValueError("Mỗi tin nhắn chỉ được đính kèm tối đa 5 file.")
        workspace = workspace_service.get_project_path(project_id)
        normalized = []
        for attachment in attachments:
            path = str(attachment.get("path") or "")
            if not path.startswith("uploads/chat/"):
                raise ValueError("Attachment không thuộc thư mục chat của project.")
            item = (await db.execute(select(LibraryItem).where(
                LibraryItem.project_id == project_id, LibraryItem.path == path,
                LibraryItem.tag == "chat_attachment",
            ))).scalars().first()
            if not item or not os.path.isfile(safe_resolve_path(workspace, path)):
                raise ValueError("Attachment không tồn tại hoặc không thuộc project hiện tại.")
            normalized.append({
                "id": item.id, "filename": item.description or "attachment",
                "content_type": mimetypes.guess_type(path)[0] or "application/octet-stream",
                "size": os.path.getsize(safe_resolve_path(workspace, path)), "path": path,
            })
        return normalized

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
            if thread_id:
                thread = (await db.execute(select(Thread).where(Thread.id == thread_id))).scalars().first()
                if not thread or thread.project_id != payload.project_id:
                    raise PermissionError("Thread không thuộc project đã chọn.")
            else:
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
        if payload.agent_override:
            target_agent = payload.agent_override
        elif agent_mention:
            target_agent = agent_mention
        else:
            decision = await router_agent.classify(clean_content)
            target_agent = decision.agent

        agent_res = await db.execute(select(Agent.key).where(Agent.key == target_agent))
        if not agent_res.scalar_one_or_none():
            raise ValueError(f"Agent không tồn tại: {target_agent}")

        # 2. Get or create thread
        if payload.thread_id:
            thread_res = await db.execute(select(Thread).where(Thread.id == payload.thread_id))
            thread = thread_res.scalars().first()
            if not thread:
                raise ValueError("Thread not found")
            if thread.project_id != payload.project_id:
                raise PermissionError("Thread không thuộc project đã chọn.")
        else:
            thread = Thread(
                project_id=payload.project_id,
                agent_key=target_agent,
                channel=payload.channel,
                title=clean_content[:50] or "New Chat",
            )
            db.add(thread)
            await db.flush()

        # 3. Validate attachment ownership and avoid duplicate library records.
        attachments = await self.validate_attachments(payload.project_id, payload.attachments, db)
        for att in attachments:
            if att.get("id"):
                continue
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
            attachments=attachments,
        )
        db.add(msg)

        # 5. Create queued run
        run = Run(
            thread_id=thread.id,
            agent_key=target_agent,
            status="running" if payload.run_synchronously else "queued",
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
