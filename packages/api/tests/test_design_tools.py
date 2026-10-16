"""Unit tests for design and a11y audit tools."""

import os
import tempfile
import pytest
from unittest.mock import AsyncMock, patch
from app.tools.base import ToolContext, tool_registry
from app.tools.design_tools import AuditWebDesignTool


@pytest.mark.asyncio
async def test_audit_web_design_tool_registered() -> None:
    """Verify AuditWebDesignTool is registered in tool registry for proper agents."""
    agent_tools = tool_registry.get_tools_for_agent("site-analyzer")
    tool_names = [t.name for t in agent_tools]
    assert "audit_web_design" in tool_names


@pytest.mark.asyncio
async def test_audit_web_design_non_existent() -> None:
    """Verify proper error handling when HTML file is missing."""
    tool = AuditWebDesignTool()
    ctx = ToolContext(
        project_id="test-proj",
        run_id="run-1",
        workspace_path="/tmp",
        db=None,  # type: ignore
        agent_key="site-analyzer",
    )
    res = await tool.execute(ctx, html_path="missing.html")
    assert "error" in res
    assert "Không tìm thấy file" in res["error"]


@pytest.mark.asyncio
async def test_audit_web_design_execution() -> None:
    """Verify execution with mocked sandbox client."""
    with tempfile.TemporaryDirectory() as temp_dir:
        test_file = os.path.join(temp_dir, "test.html")
        with open(test_file, "w", encoding="utf-8") as f:
            f.write("<!DOCTYPE html><html lang='vi'><head><meta name='viewport' content='width=device-width'><title>Test</title></head><body><h1>Hello</h1></body></html>")

        tool = AuditWebDesignTool()
        ctx = ToolContext(
            project_id="test-proj",
            run_id="run-1",
            workspace_path=temp_dir,
            db=None,  # type: ignore
            agent_key="site-analyzer",
        )

        with patch("app.tools.design_tools.sandbox_client.render_html", new_callable=AsyncMock) as mock_render, \
             patch("app.tools.design_tools.sandbox_client.run_checks", new_callable=AsyncMock) as mock_checks:
            mock_render.return_value = {"status": "success"}
            mock_checks.return_value = {"issues": [], "a11y_status": "passed"}

            res = await tool.execute(ctx, html_path="test.html", output_prefix="test-audit")

            assert res["status"] == "passed"
            assert res["quality_score"] == 100
            assert res["desktop_preview"] == "screenshots/test-audit-desktop.png"
            assert res["mobile_preview"] == "screenshots/test-audit-mobile.png"
            assert len(res["blocking_faults"]) == 0
