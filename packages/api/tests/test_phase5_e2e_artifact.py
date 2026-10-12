"""Integration test verifying end-to-end artifact generation and preview for Phase 5."""

import pytest
import os
from sqlalchemy import select
from app.db.session import async_session_maker
from app.db.models import Project, Artifact
from app.tools.base import ToolContext, tool_registry
import app.tools.file_tools  # noqa: F401
import app.tools.render_tools  # noqa: F401
from app.services.workspace import workspace_service


@pytest.mark.asyncio
async def test_design_mockup_generation_and_render() -> None:
    async with async_session_maker() as db:
        res = await db.execute(select(Project).limit(1))
        project = res.scalars().first()
        assert project is not None, "Project must exist"

        workspace_path = workspace_service.init_project_workspace(project.id)
        ctx = ToolContext(
            project_id=project.id,
            run_id="test-run-phase5",
            workspace_path=workspace_path,
            db=db,
            agent_key="design",
        )

        # 1. Write HTML Mockup
        write_tool = tool_registry.get("write_file")
        sample_html = """<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Align AI Landing Test</title>
  <style>
    body { background-color: #0B0F19; color: #F9FAFB; font-family: sans-serif; padding: 40px; }
    .hero { text-align: center; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 48px; background: rgba(17,24,39,0.7); }
    h1 { font-size: 32px; color: #6366F1; margin-bottom: 12px; }
    p { color: #9CA3AF; font-size: 16px; }
  </style>
</head>
<body>
  <div class="hero">
    <h1>Align AI Hub Platform</h1>
    <p>Nền tảng Multi-Agent chuyên nghiệp tối ưu cho website & SEO.</p>
  </div>
</body>
</html>"""
        write_res = await write_tool.execute(ctx, path="mockups/homepage.html", content=sample_html)
        assert write_res.get("status") == "success"

        # 2. Render Mockup to PNG
        render_tool = tool_registry.get("render_html_to_png")
        render_res = await render_tool.execute(ctx, html_path="mockups/homepage.html")
        assert "desktop_png" in render_res
        assert "mobile_png" in render_res

        # 3. Create Artifact record
        artifact = Artifact(
            project_id=project.id,
            artifact_id="homepage-mockup",
            version=1,
            path="mockups/homepage.html",
            git_commit=write_res.get("git_commit"),
        )
        db.add(artifact)
        await db.commit()

        # 4. Verify artifact exists in DB
        check_q = select(Artifact).where(Artifact.project_id == project.id)
        saved = (await db.execute(check_q)).scalars().all()
        assert len(saved) >= 1
