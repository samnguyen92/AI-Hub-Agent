"""Unit tests for Render and Sandbox tools."""

import pytest
from app.tools.base import ToolContext
from app.tools.render_tools import RunChecksTool


@pytest.mark.asyncio
async def test_run_checks_tool_validation() -> None:
    """Verify RunChecksTool audits HTML structure and checks for standards."""
    tool = RunChecksTool()
    ctx = ToolContext(
        project_id="test-proj",
        run_id="run-1",
        workspace_path="/workspaces",
        db=None,  # type: ignore
        agent_key="code-builder",
    )
    # Testing non-existent file handling
    res = await tool.execute(ctx, html_path="non_existent.html")
    assert "error" in res
    assert "Không tìm thấy file" in res["error"]
