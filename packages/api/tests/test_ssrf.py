"""Security tests verifying SSRF prevention and safe URL validation."""

import pytest
from app.tools.base import ToolContext, tool_registry
from app.tools.ssrf_guard import is_ip_private_or_reserved, validate_safe_url
import app.tools.web_tools  # noqa: F401


@pytest.mark.parametrize(
    "blocked_ip",
    [
        "127.0.0.1",
        "127.0.0.2",
        "10.0.0.1",
        "10.254.0.1",
        "172.16.0.1",
        "172.31.255.255",
        "192.168.1.1",
        "169.254.169.254",  # Cloud metadata
        "::1",              # IPv6 loopback
        "fe80::1",          # IPv6 link-local
    ],
)
def test_private_ip_detection(blocked_ip: str) -> None:
    assert is_ip_private_or_reserved(blocked_ip) is True


@pytest.mark.parametrize(
    "malicious_url",
    [
        "http://localhost:8000/secret",
        "http://127.0.0.1:5432",
        "http://169.254.169.254/latest/meta-data/",
        "http://10.0.0.5/admin",
        "http://192.168.1.100",
        "file:///etc/passwd",
        "ftp://internal.server/file",
        "gopher://127.0.0.1:70",
    ],
)
def test_validate_safe_url_blocks_dangerous_inputs(malicious_url: str) -> None:
    with pytest.raises(PermissionError):
        validate_safe_url(malicious_url)


def test_validate_safe_url_allows_public_domains() -> None:
    # Public domain resolution should pass validation
    safe_url = "https://example.com"
    res = validate_safe_url(safe_url)
    assert res == safe_url


@pytest.mark.asyncio
async def test_fetch_url_tool_blocks_ssrf() -> None:
    tool = tool_registry.get("fetch_url")
    assert tool is not None

    ctx = ToolContext(
        project_id="test-proj",
        run_id="test-run",
        workspace_path="/tmp",
        db=None,  # type: ignore
        agent_key="site-analyzer",
    )

    res = await tool.execute(ctx, url="http://127.0.0.1:8000/internal")
    assert "error" in res
    assert "SSRF" in res["error"] or "bị chặn" in res["error"]
