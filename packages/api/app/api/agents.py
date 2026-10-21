"""API Router for Agents inspection and configuration."""

import os
import re
from typing import Any, Dict, List, Optional
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.auth import get_current_user
from app.db.models import Agent, User
from app.db.session import get_db
from app.tools.base import tool_registry

router = APIRouter(prefix="/agents", tags=["agents"])


class AgentResponse(BaseModel):
    key: str
    model: str
    rules_path: str
    config: Dict[str, Any]
    allowed_tools: List[str]


class AgentUpdateRequest(BaseModel):
    model: Optional[str] = None
    config: Optional[Dict[str, Any]] = None


@router.get("", response_model=List[AgentResponse])
async def list_agents(db: AsyncSession = Depends(get_db)) -> List[AgentResponse]:
    """List all registered system agents with permissions."""
    res = await db.execute(select(Agent).order_by(Agent.key.asc()))
    agents = res.scalars().all()
    return [
        AgentResponse(
            key=a.key,
            model=a.model,
            rules_path=a.rules_path,
            config=a.config or {},
            allowed_tools=tool_registry.get_allowed_tool_names(a.key),
        )
        for a in agents
    ]


@router.get("/{key}", response_model=AgentResponse)
async def get_agent(key: str, db: AsyncSession = Depends(get_db)) -> AgentResponse:
    """Get single agent info."""
    res = await db.execute(select(Agent).where(Agent.key == key))
    agent = res.scalars().first()
    if not agent:
        raise HTTPException(status_code=404, detail="Agent not found")
    return AgentResponse(
        key=agent.key,
        model=agent.model,
        rules_path=agent.rules_path,
        config=agent.config or {},
        allowed_tools=tool_registry.get_allowed_tool_names(agent.key),
    )


@router.patch("/{key}", response_model=AgentResponse)
async def update_agent(
    key: str, req: AgentUpdateRequest, current_user: User = Depends(get_current_user), db: AsyncSession = Depends(get_db)
) -> AgentResponse:
    """Update agent model or configuration."""
    if current_user.role != "admin":
        raise HTTPException(status_code=403, detail="Chỉ admin mới được cấu hình agent.")
    res = await db.execute(select(Agent).where(Agent.key == key))
    agent = res.scalars().first()
    if not agent:
        raise HTTPException(status_code=404, detail="Agent not found")

    if req.model:
        agent.model = req.model.strip()
    if req.config is not None:
        new_config = dict(agent.config or {})
        new_config.update(req.config)
        agent.config = new_config

    await db.commit()
    await db.refresh(agent)
    return AgentResponse(
        key=agent.key,
        model=agent.model,
        rules_path=agent.rules_path,
        config=agent.config or {},
        allowed_tools=tool_registry.get_allowed_tool_names(agent.key),
    )


@router.get("/{key}/rules")
async def get_agent_rules(key: str, db: AsyncSession = Depends(get_db)) -> Dict[str, str]:
    """Read agent rules markdown file safely."""
    agent = (await db.execute(select(Agent).where(Agent.key == key))).scalars().first()
    if not agent:
        raise HTTPException(status_code=404, detail="Agent not found")
    if not re.fullmatch(r"[a-zA-Z0-9_-]+", key):
        raise HTTPException(status_code=400, detail="Invalid agent key")
    candidate_paths = [
        f"/app/agents/{key}/rules.md",
        f"agents/{key}/rules.md",
    ]
    for p in candidate_paths:
        if os.path.exists(p):
            try:
                with open(p, "r", encoding="utf-8") as f:
                    return {"rules": f.read()}
            except Exception as e:
                raise HTTPException(status_code=500, detail=str(e))
    return {"rules": f"# Agent {key}\nKhông tìm thấy file quy tắc riêng."}
