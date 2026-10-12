"""Unit tests for Phase 6 SEO, Web Analysis, and Report tools."""

from pathlib import Path
from typing import Any
import pytest
from app.tools.base import ToolContext, tool_registry
import app.tools.web_tools  # noqa: F401
import app.tools.seo_tools  # noqa: F401
import app.tools.report_tools  # noqa: F401


@pytest.mark.asyncio
async def test_import_and_query_report(tmp_path: Any) -> None:
    # 1. Create dummy CSV
    csv_file = tmp_path / "gsc_sample.csv"
    csv_content = """Top queries,Clicks,Impressions,CTR,Position
align ai hub,120,1500,8.0%,2.1
thiet ke web ai,45,2300,1.9%,4.5
seo auditor tool,10,3000,0.3%,8.2
"""
    csv_file.write_text(csv_content, encoding="utf-8")

    ctx = ToolContext(
        project_id="test-proj-p6",
        run_id="test-run-p6",
        workspace_path=str(tmp_path),
        db=None,  # type: ignore
        agent_key="seo-auditor",
    )

    import_tool = tool_registry.get("import_report")
    assert import_tool is not None
    res = await import_tool.execute(ctx, csv_path="gsc_sample.csv", report_name="test_gsc")
    assert res.get("rows_count") == 3
    assert "clicks" in res.get("columns", [])

    query_tool = tool_registry.get("query_report")
    assert query_tool is not None
    q_res = await query_tool.execute(ctx, report_name="test_gsc", sort_by="impressions", descending=True)
    assert q_res.get("total_rows") == 3
    top_row = q_res.get("results", [])[0]
    assert top_row.get("impressions") == 3000


@pytest.mark.asyncio
async def test_seo_lighthouse_tool_structure() -> None:
    tool = tool_registry.get("run_lighthouse")
    assert tool is not None

    ctx = ToolContext(
        project_id="test-proj-p6",
        run_id="test-run-p6",
        workspace_path="/tmp",
        db=None,  # type: ignore
        agent_key="site-analyzer",
    )
    # Using public safe url
    res = await tool.execute(ctx, url="https://example.com")
    assert "lighthouse_scores" in res
    assert "performance" in res["lighthouse_scores"]
    assert "seo" in res["lighthouse_scores"]
