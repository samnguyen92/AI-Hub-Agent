"""Database seeder populating initial agents into PostgreSQL."""

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.models import Agent

DEFAULT_AGENTS = [
    {
        "key": "assistant",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/assistant/rules.md",
        "config": {"max_steps": 10, "max_cost_usd": 0.5},
    },
    {
        "key": "design",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/design/rules.md",
        "config": {"max_steps": 15, "max_cost_usd": 1.0},
    },
    {
        "key": "code-builder",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/code-builder/rules.md",
        "config": {"max_steps": 20, "max_cost_usd": 1.5},
    },
    {
        "key": "site-analyzer",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/site-analyzer/rules.md",
        "config": {"max_steps": 10, "max_cost_usd": 0.5},
    },
    {
        "key": "seo-auditor",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/seo-auditor/rules.md",
        "config": {"max_steps": 15, "max_cost_usd": 0.8},
    },
    {
        "key": "router",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/router/rules.md",
        "config": {"max_steps": 1, "max_cost_usd": 0.1},
    },
]


async def seed_agents(db: AsyncSession) -> None:
    """Insert or update default system agents to openai/gpt-6-luna."""
    for agent_data in DEFAULT_AGENTS:
        res = await db.execute(select(Agent).where(Agent.key == agent_data["key"]))
        existing = res.scalars().first()
        if not existing:
            new_agent = Agent(
                key=agent_data["key"],
                model=agent_data["model"],
                rules_path=agent_data["rules_path"],
                config=agent_data["config"],
            )
            db.add(new_agent)
        else:
            existing.model = agent_data["model"]
            existing.rules_path = agent_data["rules_path"]
    await db.commit()
