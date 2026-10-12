"""Security unit tests verifying path traversal protections."""

import os
import tempfile
import pytest
from app.tools.file_tools import safe_resolve_path, ReadFileTool, WriteFileTool
from app.tools.base import ToolContext


def test_safe_resolve_path_valid() -> None:
    """Ensure paths inside workspace resolve correctly."""
    with tempfile.TemporaryDirectory() as tmpdir:
        resolved = safe_resolve_path(tmpdir, "src/index.html")
        expected = os.path.join(os.path.abspath(tmpdir), "src", "index.html")
        assert resolved == expected


def test_safe_resolve_path_traversal_blocked() -> None:
    """Ensure path traversal attempts raise PermissionError."""
    with tempfile.TemporaryDirectory() as tmpdir:
        with pytest.raises(PermissionError):
            safe_resolve_path(tmpdir, "../../etc/passwd")

        with pytest.raises(PermissionError):
            safe_resolve_path(tmpdir, "../sibling_dir")

        with pytest.raises(PermissionError):
            safe_resolve_path(tmpdir, "subdir/../../outside")


@pytest.mark.asyncio
async def test_read_file_tool_traversal_protection() -> None:
    """Verify ReadFileTool returns error message when path traversal is attempted."""
    with tempfile.TemporaryDirectory() as tmpdir:
        tool = ReadFileTool()
        ctx = ToolContext(
            project_id="test-proj",
            run_id="run-1",
            workspace_path=tmpdir,
            db=None,  # type: ignore
            agent_key="code-builder",
        )
        res = await tool.execute(ctx, path="../../sensitive_file.txt")
        assert "error" in res
        assert "vượt ra ngoài workspace" in res["error"]
