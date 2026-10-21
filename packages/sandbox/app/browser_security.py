"""Public URL and workspace-file checks for sandbox browser requests."""

import ipaddress
import socket
from typing import Any
from urllib.parse import unquote, urlparse

from fastapi import HTTPException

from app.path_security import resolve_workspace_path


def validate_public_url(url: str) -> None:
    """Reject non-HTTP URLs and hosts that resolve to non-public IP addresses."""
    parsed = urlparse(url)
    if parsed.scheme not in ("http", "https") or not parsed.hostname:
        raise HTTPException(status_code=400, detail="Only absolute HTTP(S) URLs are allowed")
    try:
        addresses = socket.getaddrinfo(parsed.hostname, parsed.port or (443 if parsed.scheme == "https" else 80))
    except OSError as exc:
        raise HTTPException(status_code=400, detail="URL host could not be resolved") from exc
    for address in addresses:
        if not ipaddress.ip_address(address[4][0]).is_global:
            raise HTTPException(status_code=403, detail="URL resolves to a non-public address")


async def guard_browser_request(route: Any) -> None:
    """Allow public web resources and local files confined to project workspaces."""
    request_url = route.request.url
    try:
        if request_url.startswith("file://"):
            local_path = unquote(urlparse(request_url).path)
            resolve_workspace_path(local_path)
        else:
            validate_public_url(request_url)
        await route.continue_()
    except (HTTPException, ValueError):
        await route.abort("blockedbyclient")
