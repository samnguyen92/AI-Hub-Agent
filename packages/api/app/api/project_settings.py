"""API Router for Project Settings: WordPress, Team Members, Progress, and Telegram."""

from datetime import datetime, timedelta, timezone
import secrets
from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user
from app.api.telegram import get_bot_info
from app.db.member_models import ProjectMember
from app.db.models import Project, Run, TelegramLink, Thread, User
from app.db.session import get_db
from app.db.wp_models import WordPressSite
from app.services.wp_external import wp_external_service

router = APIRouter(prefix="/projects/{project_id}/settings", tags=["project-settings"])


class WordPressConfigRequest(BaseModel):
    site_type: str = "external"  # "external" or "internal"
    site_url: str
    admin_user: str
    admin_password: str


class MemberInviteRequest(BaseModel):
    email: str
    role: str = "editor"  # "editor" or "viewer"


async def _get_project(
    project_id: str, user: User, db: AsyncSession, min_role: str = "viewer"
) -> Project:
    """Verify project exists and user has required role (viewer or editor)."""
    q = select(Project).where(Project.id == project_id)
    project = (await db.execute(q)).scalars().first()
    if not project:
        raise HTTPException(status_code=404, detail="Dự án không tồn tại.")

    if user.role == "admin" or project.owner_id == user.id:
        return project

    member_q = select(ProjectMember).where(
        ProjectMember.project_id == project_id,
        ProjectMember.email == user.github_login,
    )
    member = (await db.execute(member_q)).scalars().first()
    if not member or member.status != "active":
        raise HTTPException(status_code=403, detail="Không có quyền truy cập cài đặt dự án.")

    if min_role == "editor" and member.role != "editor":
        raise HTTPException(
            status_code=403,
            detail="Vai trò chỉ xem (Viewer) không có quyền thay đổi cài đặt hoặc quản lý thành viên."
        )

    return project


@router.get("")
async def get_project_settings(
    project_id: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> Dict[str, Any]:
    """Retrieve all project settings: WordPress, Telegram, Members, and Progress."""
    project = await _get_project(project_id, user, db)

    # 1. WordPress details
    wp_q = select(WordPressSite).where(WordPressSite.project_id == project_id)
    wp_site = (await db.execute(wp_q)).scalars().first()
    wp_data: Dict[str, Any] = {
        "connected": bool(wp_site and wp_site.status in ["running", "active", "connected"]),
        "site_type": getattr(wp_site, "site_type", "internal") if wp_site else "none",
        "site_url": wp_site.site_url if wp_site else "",
        "admin_url": wp_site.admin_url if wp_site else "",
        "admin_user": wp_site.admin_user if wp_site else "",
        "status": wp_site.status if wp_site else "none",
        "port": wp_site.port if wp_site else None,
    }

    # 2. Telegram connection
    tg_q = select(TelegramLink).where(
        TelegramLink.user_id == project.owner_id, TelegramLink.is_confirmed.is_(True)
    )
    tg_link = (await db.execute(tg_q)).scalars().first()
    bot_info = await get_bot_info()
    tg_data = {
        "is_linked": bool(tg_link),
        "telegram_id": tg_link.telegram_id if tg_link else None,
        "bot_configured": bot_info.get("configured", False),
        "bot_username": bot_info.get("username"),
    }

    # 3. Members
    m_q = select(ProjectMember).where(ProjectMember.project_id == project_id).order_by(ProjectMember.created_at.desc())
    members = (await db.execute(m_q)).scalars().all()
    members_data = [
        {
            "id": m.id,
            "email": m.email,
            "role": m.role,
            "status": m.status,
            "created_at": m.created_at.isoformat() if m.created_at else "",
        }
        for m in members
    ]

    # 4. Progress & Activity
    runs_q = (
        select(Run)
        .join(Thread, Run.thread_id == Thread.id)
        .where(Thread.project_id == project_id)
        .order_by(Run.created_at.desc())
        .limit(8)
    )
    recent_runs = (await db.execute(runs_q)).scalars().all()
    count_q = (
        select(func.count(Run.id))
        .join(Thread, Run.thread_id == Thread.id)
        .where(Thread.project_id == project_id)
    )
    total_runs = (await db.execute(count_q)).scalar() or 0

    run_agent_keys = [r.agent_key for r in recent_runs]
    milestones = [
        {"phase": 1, "name": "Khởi tạo dự án & Không gian làm việc", "status": "completed"},
        {
            "phase": 2,
            "name": "Thiết kế & Dựng mẫu giao diện",
            "status": "completed" if any(k in ["design", "code-builder"] for k in run_agent_keys) else "pending",
        },
        {
            "phase": 3,
            "name": "Kết nối & Triển khai WordPress",
            "status": "completed" if wp_data["connected"] else "pending",
        },
        {
            "phase": 4,
            "name": "Tối ưu hóa SEO & Đóng gói sản phẩm",
            "status": "completed" if any(k in ["seo-auditor", "quality-auditor"] for k in run_agent_keys) else "pending",
        },
    ]

    return {
        "project": {"id": project.id, "name": project.name, "created_at": project.created_at.isoformat()},
        "wordpress": wp_data,
        "telegram": tg_data,
        "members": members_data,
        "progress": {
            "total_runs": total_runs,
            "milestones": milestones,
            "recent_runs": [
                {
                    "id": r.id,
                    "agent_key": r.agent_key,
                    "status": r.status,
                    "created_at": r.created_at.isoformat() if r.created_at else "",
                }
                for r in recent_runs
            ],
        },
    }


@router.post("/wordpress")
async def connect_wordpress(
    project_id: str,
    req: WordPressConfigRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> Dict[str, Any]:
    """Connect an external WordPress site using REST API Application Password."""
    await _get_project(project_id, user, db, min_role="editor")

    clean_url = req.site_url.strip().rstrip("/")
    if not clean_url.startswith("http://") and not clean_url.startswith("https://"):
        clean_url = f"https://{clean_url}"

    # Verify external connection
    verify_res = await wp_external_service.verify_connection(
        clean_url, req.admin_user.strip(), req.admin_password.strip()
    )
    if not verify_res.get("success"):
        raise HTTPException(status_code=400, detail=verify_res.get("error", "Không thể kết nối tới WordPress."))

    # Upsert site record
    wp_q = select(WordPressSite).where(WordPressSite.project_id == project_id)
    site = (await db.execute(wp_q)).scalars().first()
    container_identifier = f"external_{project_id}"

    if not site:
        site = WordPressSite(
            project_id=project_id,
            container_name=container_identifier,
            site_type="external",
            site_url=clean_url,
            admin_url=f"{clean_url}/wp-admin",
            admin_user=req.admin_user.strip(),
            admin_password=req.admin_password.strip(),
            status="connected",
        )
        db.add(site)
    else:
        site.site_type = "external"
        site.site_url = clean_url
        site.admin_url = f"{clean_url}/wp-admin"
        site.admin_user = req.admin_user.strip()
        site.admin_password = req.admin_password.strip()
        site.status = "connected"

    await db.commit()
    return {
        "success": True,
        "message": verify_res.get("message", "Kết nối WordPress thành công!"),
        "site_url": clean_url,
        "admin_user": req.admin_user,
    }


@router.post("/members")
async def add_member(
    project_id: str,
    req: MemberInviteRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> Dict[str, Any]:
    """Add a member/collaborator to the project."""
    await _get_project(project_id, user, db, min_role="editor")

    # Check if already added
    chk_q = select(ProjectMember).where(
        ProjectMember.project_id == project_id, ProjectMember.email == req.email.lower()
    )
    existing = (await db.execute(chk_q)).scalars().first()
    if existing:
        raise HTTPException(status_code=400, detail="Thành viên này đã tham gia dự án.")

    member = ProjectMember(
        project_id=project_id,
        email=req.email.lower(),
        role=req.role if req.role in ["editor", "viewer"] else "editor",
        status="active",
    )
    db.add(member)
    await db.commit()
    return {
        "success": True,
        "member": {
            "id": member.id,
            "email": member.email,
            "role": member.role,
            "status": member.status,
            "created_at": member.created_at.isoformat(),
        },
    }


@router.delete("/members/{member_id}")
async def remove_member(
    project_id: str,
    member_id: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> Dict[str, Any]:
    """Remove a member from the project."""
    await _get_project(project_id, user, db, min_role="editor")
    m_q = select(ProjectMember).where(ProjectMember.id == member_id, ProjectMember.project_id == project_id)
    member = (await db.execute(m_q)).scalars().first()
    if not member:
        raise HTTPException(status_code=404, detail="Không tìm thấy thành viên.")
    await db.delete(member)
    await db.commit()
    return {"success": True, "message": "Đã xóa thành viên khỏi dự án."}


@router.post("/telegram/link-code")
async def generate_telegram_code(
    project_id: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
) -> Dict[str, Any]:
    """Generate Telegram linking code for the project owner."""
    await _get_project(project_id, user, db, min_role="editor")
    alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
    code = "ALIGN-" + "".join(secrets.choice(alphabet) for _ in range(8))
    link_record = TelegramLink(
        user_id=user.id,
        link_code=code,
        is_confirmed=False,
        expires_at=datetime.now(timezone.utc) + timedelta(minutes=15),
    )
    db.add(link_record)
    await db.commit()
    bot_info = await get_bot_info()
    bot_user = bot_info.get("username")
    return {
        "link_code": code,
        "command": f"/link {code}",
        "bot_configured": bot_info.get("configured", False),
        "bot_username": bot_user,
        "instructions": (
            f"Mở Telegram bot @{bot_user} và gửi lệnh: /link {code} để kích hoạt nhận thông báo dự án."
            if bot_user
            else f"Mở Telegram bot của hệ thống và gửi lệnh: /link {code} để kích hoạt nhận thông báo dự án."
        ),
    }
