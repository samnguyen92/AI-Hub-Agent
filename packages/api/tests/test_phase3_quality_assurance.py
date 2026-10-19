"""Unit and integration tests for Phase 3 Quality Assurance and Accessibility Auditing."""

import os
import tempfile
import pytest
from app.tools.base import ToolContext, tool_registry
import app.tools.design_tools  # noqa: F401
import app.tools.render_tools  # noqa: F401


@pytest.mark.asyncio
async def test_audit_web_design_offline_fallback() -> None:
    """Verify audit_web_design gracefully falls back to local static checks when sandbox is offline."""
    with tempfile.TemporaryDirectory() as temp_dir:
        # Create HTML with missing viewport and missing alt attribute
        bad_html = os.path.join(temp_dir, "bad.html")
        with open(bad_html, "w", encoding="utf-8") as f:
            f.write("<html><head><title>No Viewport</title></head><body><img src='test.png'></body></html>")

        ctx = ToolContext(
            project_id="qa-proj",
            run_id="qa-run-1",
            workspace_path=temp_dir,
            db=None,  # type: ignore
            agent_key="quality-auditor",
        )

        tool = tool_registry.get("audit_web_design")
        assert tool is not None
        res = await tool.execute(ctx, html_path="bad.html", output_prefix="fallback-audit")

        # Must detect missing <!DOCTYPE html> and missing viewport
        assert res["status"] == "blocked"
        assert res["quality_score"] < 100
        assert any("viewport" in f.lower() for f in res["blocking_faults"])
        assert any("alt" in a.lower() for a in res["advisory_notes"])


@pytest.mark.asyncio
async def test_audit_web_design_passes_valid_align_html() -> None:
    """Verify compliant HTML with doctype, viewport, and alt passes audit."""
    with tempfile.TemporaryDirectory() as temp_dir:
        good_html = os.path.join(temp_dir, "good.html")
        with open(good_html, "w", encoding="utf-8") as f:
            f.write(
                "<!DOCTYPE html><html lang='vi'><head>"
                "<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
                "<title>Valid Align Page</title></head>"
                "<body><header><h1>Tiêu đề</h1></header><img src='logo.png' alt='Align Logo'></body></html>"
            )

        ctx = ToolContext(
            project_id="qa-proj",
            run_id="qa-run-2",
            workspace_path=temp_dir,
            db=None,  # type: ignore
            agent_key="quality-auditor",
        )

        tool = tool_registry.get("audit_web_design")
        res = await tool.execute(ctx, html_path="good.html", output_prefix="good-audit")

        assert res["status"] == "passed"
        assert len(res["blocking_faults"]) == 0
        assert res["quality_score"] >= 90
