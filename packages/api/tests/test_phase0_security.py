"""Phase 0 Security & Remediation unit tests."""

import os
import tempfile
import pytest
from unittest.mock import AsyncMock, MagicMock
from httpx import AsyncClient, ASGITransport

from app.main import app
from app.tools.file_tools import ExportZipTool, perform_export
from app.tools.base import ToolContext


def test_export_zip_schema_has_no_bypass_flag() -> None:
    """SEC-03: Ensure bypass_approval is not exposed in tool parameters schema."""
    tool = ExportZipTool()
    props = tool.parameters_schema.get("properties", {})
    assert "bypass_approval" not in props
    assert "archive_name" in props


@pytest.mark.asyncio
async def test_export_zip_cannot_bypass_approval_via_args() -> None:
    """SEC-03 & B2: Passing bypass_approval=True in arguments must still create approval."""
    tool = ExportZipTool()

    mock_db = MagicMock()
    mock_db.add = MagicMock()
    mock_db.commit = AsyncMock()

    mock_run = MagicMock()
    mock_run.id = "run-test-123"
    mock_run.status = "running"

    mock_exec = AsyncMock()
    mock_exec.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_run)))
    mock_db.execute = AsyncMock(return_value=mock_exec)

    ctx = ToolContext(
        project_id="proj-test-123",
        run_id="run-test-123",
        workspace_path="/tmp",
        db=mock_db,
        agent_key="code-builder",
    )

    # Attempt to bypass approval via injected parameter
    res = await tool.execute(ctx, archive_name="leak.zip", bypass_approval=True)

    # Must NOT bypass approval: should require approval
    assert res.get("status") == "waiting_approval"
    assert res.get("pause") is True
    assert mock_db.add.called
    assert mock_run.status == "waiting_approval"


def test_perform_export_creates_valid_zip() -> None:
    """Verify internal perform_export packages files safely."""
    with tempfile.TemporaryDirectory() as tmpdir:
        test_file = os.path.join(tmpdir, "index.html")
        with open(test_file, "w") as f:
            f.write("<h1>Hello Export</h1>")

        res = perform_export(tmpdir, "site_export.zip", "proj-999")
        assert res.get("status") == "exported"
        assert res.get("archive_path") == "exports/site_export.zip"
        assert os.path.exists(os.path.join(tmpdir, "exports", "site_export.zip"))


@pytest.mark.asyncio
async def test_cleanup_demos_endpoint_returns_410() -> None:
    """SEC-06 & S1: Ensure unsafe cleanup-demos returns 410 Gone."""
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        resp = await ac.post("/projects/cleanup-demos")
        assert resp.status_code == 410
        data = resp.json()
        assert "vô hiệu hoá" in data.get("detail", "")
