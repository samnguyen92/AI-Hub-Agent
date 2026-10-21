"""Shared sandbox path checks for project workspace files."""

import os

from fastapi import HTTPException

WORKSPACES_ROOT = os.path.realpath("/workspaces")


def resolve_workspace_path(path: str) -> str:
    """Restrict sandbox file access to the shared project workspaces."""
    resolved = os.path.realpath(path)
    if os.path.commonpath((WORKSPACES_ROOT, resolved)) != WORKSPACES_ROOT or resolved == WORKSPACES_ROOT:
        raise HTTPException(status_code=403, detail="Path must stay inside a project workspace")
    return resolved
