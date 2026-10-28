"""Telegram Bot API integration with webhook, account linking and approvals."""

import hmac
import secrets
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, Optional
from fastapi import APIRouter, Depends, Header, HTTPException, Request
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
import httpx

from app.config import settings
from app.api.auth import get_current_user
from app.db.models import Approval, Project, TelegramLink, Thread, User
from app.db.session import get_db
from app.services.gateway import IncomingMessagePayload, message_gateway

router = APIRouter(prefix="/telegram", tags=["telegram"])


class LinkCodeResponse(BaseModel):
    link_code: str
    instructions: str


@router.post("/link-code", response_model=LinkCodeResponse)
async def generate_link_code(
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> LinkCodeResponse:
    """Generate an expiring one-time code for the authenticated user."""
    alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
    code = "ALIGN-" + "".join(secrets.choice(alphabet) for _ in range(10))
    link_record = TelegramLink(
        user_id=user.id,
        link_code=code,
        is_confirmed=False,
        expires_at=datetime.now(timezone.utc) + timedelta(minutes=10),
    )
    db.add(link_record)
    await db.commit()

    return LinkCodeResponse(
        link_code=code,
        instructions=f"Mở Telegram và gửi tin nhắn: '/link {code}' để kết nối tài khoản.",
    )


async def get_bot_info() -> Dict[str, Any]:
    """Fetch current bot identity and configuration status from Telegram."""
    token = getattr(settings, "telegram_bot_token", None)
    if not token or token == "dummy":
        return {"configured": False, "username": None, "first_name": None}
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(f"https://api.telegram.org/bot{token}/getMe")
            if resp.status_code == 200 and resp.json().get("ok"):
                res = resp.json()["result"]
                return {
                    "configured": True,
                    "username": res.get("username"),
                    "first_name": res.get("first_name"),
                }
    except Exception:
        pass
    return {"configured": True, "username": None, "first_name": None}


@router.post("/setup-webhook")
async def setup_webhook(
    webhook_url: str,
    user: User = Depends(get_current_user),
) -> Dict[str, Any]:
    """Utility endpoint to register or test the Telegram webhook URL."""
    token = getattr(settings, "telegram_bot_token", None)
    if not token or token == "dummy":
        raise HTTPException(status_code=400, detail="Chưa cấu hình TELEGRAM_BOT_TOKEN trong .env")

    clean_url = webhook_url.strip()
    payload: Dict[str, Any] = {"url": clean_url}
    if settings.telegram_webhook_secret:
        payload["secret_token"] = settings.telegram_webhook_secret

    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            resp = await client.post(f"https://api.telegram.org/bot{token}/setWebhook", json=payload)
            return resp.json()
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Không thể kết nối Telegram API: {str(exc)}")


async def send_telegram_message(chat_id: str, text: str, reply_markup: Optional[Dict[str, Any]] = None) -> None:
    """Send message to Telegram via Bot API if token configured."""
    token = getattr(settings, "telegram_bot_token", None)
    if not token or token == "dummy":
        return

    payload: Dict[str, Any] = {"chat_id": chat_id, "text": text, "parse_mode": "HTML"}
    if reply_markup:
        payload["reply_markup"] = reply_markup

    async with httpx.AsyncClient() as client:
        await client.post(f"https://api.telegram.org/bot{token}/sendMessage", json=payload, timeout=10.0)


@router.post("/webhook")
async def telegram_webhook(
    request: Request,
    db: AsyncSession = Depends(get_db),
    x_telegram_bot_api_secret_token: Optional[str] = Header(None),
) -> Dict[str, Any]:
    """Handle incoming Telegram webhook updates."""
    expected_secret = settings.telegram_webhook_secret
    # In production or whenever configured, secret token is strictly required
    if settings.environment == "production" or expected_secret:
        if (
            not expected_secret
            or not x_telegram_bot_api_secret_token
            or not hmac.compare_digest(x_telegram_bot_api_secret_token, expected_secret)
        ):
            raise HTTPException(status_code=401, detail="Invalid Telegram webhook secret token")
    data = await request.json()

    # 1. Handle Inline Button Callback (Approvals)
    if "callback_query" in data:
        cb = data["callback_query"]
        cb_data = cb.get("data", "")
        chat_id = str(cb["message"]["chat"]["id"])

        if cb_data.startswith("approve:") or cb_data.startswith("reject:") or cb_data.startswith("rework:"):
            action, app_id = cb_data.split(":", 1)
            from app.services.approval_service import approval_service
            try:
                await approval_service.decide(
                    approval_id=app_id,
                    decision=action,
                    db=db,
                    telegram_chat_id=chat_id,
                    comment="Quyết định thực hiện qua Telegram Bot",
                )
                status_label = "ĐỒNG Ý (Approved)" if action == "approve" else ("YÊU CẦU SỬA (Rework)" if action == "rework" else "TỪ CHỐI (Rejected)")
                await send_telegram_message(
                    chat_id,
                    f"✅ Đã ghi nhận quyết định: <b>{status_label}</b> cho Cổng <code>{app_id}</code>",
                )
            except PermissionError as pe:
                await send_telegram_message(
                    chat_id,
                    f"❌ Từ chối quyền truy cập: {str(pe)}",
                )
            except Exception as e:
                await send_telegram_message(
                    chat_id,
                    f"⚠️ Không thể xử lý phê duyệt: {str(e)}",
                )
        return {"ok": True}

    # 2. Handle Text Messages
    msg = data.get("message", {})
    text = msg.get("text", "").strip()
    chat_id = str(msg.get("chat", {}).get("id", ""))
    if not chat_id:
        return {"ok": True}

    # Command: /start or /help
    if text.startswith("/start") or text.startswith("/help"):
        welcome_txt = (
            "👋 Xin chào từ <b>Align AI Hub Bot</b>!\n\n"
            "Để kết nối tài khoản web, hãy lấy mã trên trang Web và gửi: <code>/link ALIGN-XXXXX</code>\n"
            "Các lệnh hỗ trợ: /status, /new, /projects"
        )
        await send_telegram_message(chat_id, welcome_txt)
        return {"ok": True}

    # Command: /link <code>
    if text.startswith("/link"):
        parts = text.split(maxsplit=1)
        if len(parts) < 2:
            await send_telegram_message(chat_id, "Vui lòng nhập đúng định dạng: <code>/link ALIGN-XXXXX</code>")
            return {"ok": True}

        code_input = parts[1].strip()
        link_q = select(TelegramLink).where(
            TelegramLink.link_code == code_input,
            TelegramLink.expires_at > datetime.now(timezone.utc),
        )
        link = (await db.execute(link_q)).scalars().first()
        if not link:
            await send_telegram_message(chat_id, "❌ Mã liên kết không hợp lệ hoặc đã hết hạn.")
            return {"ok": True}

        link.telegram_id = chat_id
        link.is_confirmed = True
        link.link_code = None
        await db.commit()
        await send_telegram_message(chat_id, "🎉 Liên kết tài khoản thành công! Bạn có thể chat trực tiếp với Hub Agent tại đây.")
        return {"ok": True}

    # Verify linked user
    u_q = select(TelegramLink).where(TelegramLink.telegram_id == chat_id, TelegramLink.is_confirmed == True)
    linked = (await db.execute(u_q)).scalars().first()
    if not linked:
        await send_telegram_message(chat_id, "⚠️ Tài khoản chưa được liên kết. Vui lòng gửi <code>/link MÃ_SỐ</code> trước.")
        return {"ok": True}

    # Find project and thread
    p_q = select(Project).where(Project.owner_id == linked.user_id).limit(1)
    project = (await db.execute(p_q)).scalars().first()
    if not project:
        await send_telegram_message(chat_id, "Bạn cần tạo một project trong Hub trước khi chat qua Telegram.")
        return {"ok": True}

    # Create thread and run agent
    th_q = select(Thread).where(Thread.project_id == project.id, Thread.channel == "telegram").limit(1)
    thread = (await db.execute(th_q)).scalars().first()
    if not thread:
        thread = Thread(project_id=project.id, agent_key="assistant", channel="telegram", title="Telegram Session")
        db.add(thread)
        await db.flush()

    result = await message_gateway.process_incoming_message(
        IncomingMessagePayload(
            project_id=project.id,
            thread_id=thread.id,
            channel="telegram",
            content=text,
        ),
        db,
    )

    await send_telegram_message(chat_id, f"🤖 Đang gửi lệnh tới Hub Agent...")
    return {"ok": True, "thread_id": thread.id, "run_id": result["run_id"]}
