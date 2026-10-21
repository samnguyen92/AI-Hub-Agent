"""Workspace service managing isolated project directories with Git versioning."""

import os
import subprocess
from typing import Optional
from app.config import settings


class WorkspaceService:
    """Manages project workspaces in the shared storage volume."""

    def __init__(self, root_dir: Optional[str] = None) -> None:
        self.root_dir = root_dir or settings.workspaces_root
        os.makedirs(self.root_dir, exist_ok=True)

    def get_project_path(self, project_id: str) -> str:
        """Return the absolute path of a project workspace."""
        root = os.path.realpath(self.root_dir)
        path = os.path.realpath(os.path.join(root, project_id))
        if os.path.commonpath((root, path)) != root:
            raise ValueError("Path traversal attempt detected in project_id")
        return path

    def init_project_workspace(self, project_id: str) -> str:
        """Create workspace directory and initialize a Git repository."""
        project_path = self.get_project_path(project_id)
        os.makedirs(project_path, exist_ok=True)

        git_dir = os.path.join(project_path, ".git")
        if not os.path.exists(git_dir):
            subprocess.run(
                ["git", "init"],
                cwd=project_path,
                check=True,
                capture_output=True,
                text=True,
            )
            subprocess.run(
                ["git", "config", "user.name", "Hub Agent"],
                cwd=project_path,
                check=True,
            )
            subprocess.run(
                ["git", "config", "user.email", "agent@hub.align.vn"],
                cwd=project_path,
                check=True,
            )
        return project_path

    def commit_step(self, project_id: str, message: str) -> Optional[str]:
        """Stage all workspace changes and commit with the given message."""
        project_path = self.get_project_path(project_id)
        if not os.path.exists(project_path):
            return None
        try:
            subprocess.run(["git", "add", "."], cwd=project_path, check=True)
            res = subprocess.run(
                ["git", "commit", "-m", message, "--allow-empty"],
                cwd=project_path,
                check=True,
                capture_output=True,
                text=True,
            )
            rev = subprocess.run(
                ["git", "rev-parse", "HEAD"],
                cwd=project_path,
                check=True,
                capture_output=True,
                text=True,
            )
            return rev.stdout.strip()
        except subprocess.SubprocessError:
            return None


workspace_service = WorkspaceService()
