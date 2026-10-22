"""Upload existing Docker workspace directories to private Supabase Storage."""

import os
from app.config import settings
from app.services.workspace_storage import workspace_storage


def upload_existing_workspaces() -> int:
    """Upload every project directory under WORKSPACES_ROOT and return its count."""
    if not workspace_storage.enabled:
        raise RuntimeError("Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY first.")
    root = os.path.realpath(settings.workspaces_root)
    if not os.path.isdir(root):
        raise RuntimeError(f"Workspace directory does not exist: {root}")
    project_ids = [
        name for name in os.listdir(root)
        if os.path.isdir(os.path.join(root, name))
    ]
    for project_id in project_ids:
        workspace_storage.upload(project_id, os.path.join(root, project_id))
        print(f"Uploaded workspace snapshot: {project_id}")
    return len(project_ids)


if __name__ == "__main__":
    total = upload_existing_workspaces()
    print(f"Uploaded {total} workspace snapshot(s).")
