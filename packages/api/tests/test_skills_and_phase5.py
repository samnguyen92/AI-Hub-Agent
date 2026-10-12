"""Unit tests for Phase 5: Skills, Agent Rules and Input Tools."""

import pytest
from app.services.agent_runner import AgentRunner
from app.tools.base import ToolContext, tool_registry
from app.tools.skill_tools import LoadSkillTool, RequestInputTool


@pytest.mark.asyncio
async def test_load_shared_skill() -> None:
    tool = tool_registry.get("load_skill")
    assert isinstance(tool, LoadSkillTool)

    ctx = ToolContext(
        project_id="test-proj",
        run_id="test-run",
        workspace_path="/tmp",
        db=None,  # type: ignore
        agent_key="design",
    )
    res = await tool.execute(ctx, skill_name="align-style")
    assert res.get("status") == "loaded"
    assert "align.vn" in res.get("content", "")


@pytest.mark.asyncio
async def test_load_agent_specific_skill() -> None:
    tool = tool_registry.get("load_skill")
    ctx = ToolContext(
        project_id="test-proj",
        run_id="test-run",
        workspace_path="/tmp",
        db=None,  # type: ignore
        agent_key="design",
    )
    res = await tool.execute(ctx, skill_name="analyze-brief")
    assert res.get("status") == "loaded"
    assert "Analyze Brief Skill" in res.get("content", "")


def test_agent_runner_loads_rules() -> None:
    runner = AgentRunner()
    prompt = runner._build_system_prompt(agent_key="design", workspace_path="/workspaces/test")
    assert "Design Agent System Rules" in prompt or "Align AI Hub" in prompt
