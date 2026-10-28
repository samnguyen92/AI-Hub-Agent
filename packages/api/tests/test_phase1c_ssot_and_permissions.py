"""Unit and integration tests for Phase 1c: SSOT agent.yaml, runtime permissions, and model persistence."""

import os
import pytest
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.models import Agent, Message, Project, Run, Thread, User
from app.db.seed import seed_agents
from app.db.session import async_session_maker
from app.services.agent_loader import AgentConfigLoader, agent_loader
from app.services.agent_runner import AgentRunner
from app.tools.base import ToolContext, tool_registry
from app.services.openrouter import ToolCallResult


@pytest.mark.asyncio
async def test_ssot_manifest_loading_and_validation() -> None:
    """Verify all 7 agents are discovered and validate cleanly."""
    agent_loader.reload()
    agents = agent_loader.get_all_agents()
    agent_keys = {a["key"] for a in agents}

    expected_keys = {
        "assistant",
        "design",
        "code-builder",
        "site-analyzer",
        "seo-auditor",
        "quality-auditor",
        "router",
    }
    assert expected_keys.issubset(agent_keys), f"Missing agents: {expected_keys - agent_keys}"

    # Validation must succeed without raising RuntimeError
    agent_loader.validate_agents()


@pytest.mark.asyncio
async def test_validation_fails_on_broken_reference(tmp_path) -> None:
    """Verify fail-fast behavior when agent references non-existent rules or skills."""
    test_loader = AgentConfigLoader()
    test_loader._manifests = {
        "faulty-agent": {
            "key": "faulty-agent",
            "rules_path": "agents/faulty-agent/non_existent_rules.md",
            "skills": ["shared/non_existent_skill"],
        }
    }
    with pytest.raises(RuntimeError) as exc_info:
        test_loader.validate_agents()
    assert "rules_path" in str(exc_info.value)


@pytest.mark.asyncio
async def test_runtime_permission_enforcement_blocks_unauthorized_tool() -> None:
    """Agent cannot execute a tool not listed in its allowed tools manifest."""
    runner = AgentRunner()

    async with async_session_maker() as db:
        user = User(github_login=f"perm_user_{os.urandom(4).hex()}", role="member")
        db.add(user)
        await db.flush()

        proj = Project(owner_id=user.id, name="Perm Test Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        th = Thread(project_id=proj.id, agent_key="router", title="Perm Test")
        db.add(th)
        await db.flush()

        run = Run(thread_id=th.id, agent_key="router", status="running")
        db.add(run)
        await db.commit()

        tool_ctx = ToolContext(
            project_id=proj.id,
            run_id=run.id,
            workspace_path="/tmp",
            db=db,
            agent_key="router",
        )

        messages = []
        # Simulate LLM trying to call 'export_zip' (which router is never allowed to call)
        tool_calls = [ToolCallResult(id="tc-1", name="export_zip", arguments={"archive_name": "hacked.zip"})]

        paused, err_count, _ = await runner._execute_tool_calls(
            tool_calls=tool_calls,
            step_idx=1,
            run_id=run.id,
            total_tokens=100,
            tool_ctx=tool_ctx,
            messages=messages,
            db=db,
            consecutive_errors=0,
        )

        assert err_count == 1
        assert len(messages) == 1
        assert "not permitted for agent 'router'" in messages[0]["content"]


@pytest.mark.asyncio
async def test_seed_preserves_customized_model() -> None:
    """Verify seed_agents does not overwrite custom model back to default (BUG-05 fix)."""
    async with async_session_maker() as db:
        # First ensure seeded
        await seed_agents(db)

        # User customizes design agent model
        q = select(Agent).where(Agent.key == "design")
        agent = (await db.execute(q)).scalars().first()
        assert agent is not None
        custom_model = "anthropic/claude-3-5-sonnet:custom"
        agent.model = custom_model
        await db.commit()

        # Re-run seed_agents (simulating server reboot)
        await seed_agents(db)

        try:
            # Check that customized model was preserved
            reloaded = (await db.execute(q)).scalars().first()
            assert reloaded.model == custom_model
        finally:
            reloaded.model = "openai/gpt-6-luna"
            await db.commit()
