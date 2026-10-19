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
        "key": "quality-auditor",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/quality-auditor/rules.md",
        "config": {"max_steps": 15, "max_cost_usd": 1.0},
    },
    {
        "key": "router",
        "model": "openai/gpt-6-luna",
        "rules_path": "agents/router/rules.md",
        "config": {"max_steps": 1, "max_cost_usd": 0.1},
    },
]


async def seed_agents(db: AsyncSession) -> None:
    """Insert or update default agents without overwriting user model customizations (BUG-05 fix)."""
    try:
        from app.services.agent_loader import agent_loader
        manifests = {m["key"]: m for m in agent_loader.get_all_agents()}
    except Exception:
        manifests = {}

    all_keys = list(dict.fromkeys(list(manifests.keys()) + [a["key"] for a in DEFAULT_AGENTS]))

    for key in all_keys:
        m = manifests.get(key, {})
        default = next((a for a in DEFAULT_AGENTS if a["key"] == key), {})

        model = m.get("model") or default.get("model", "openai/gpt-6-luna")
        rules_path = m.get("rules_path") or default.get("rules_path", f"agents/{key}/rules.md")
        config = m.get("config") or default.get("config", {"max_steps": 15, "max_cost_usd": 1.0})

        res = await db.execute(select(Agent).where(Agent.key == key))
        existing = res.scalars().first()
        if not existing:
            new_agent = Agent(
                key=key,
                model=model,
                rules_path=rules_path,
                config=config,
            )
            db.add(new_agent)
        else:
            # Preserve user-customized model
            if not existing.model:
                existing.model = model
            existing.rules_path = rules_path
            if not existing.config:
                existing.config = config
    await db.commit()
