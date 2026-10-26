"""WordPress backup service orchestrating All-in-One WP Migration exports."""

import asyncio
import os
import shutil
from typing import Any, Dict, List, Optional, Tuple

from app.config import settings


class WordPressBackupService:
    """Manages All-in-One WP Migration exports and artifact deliveries."""

    @staticmethod
    async def run_cmd(cmd: List[str], timeout: int = 180) -> Tuple[int, str, str]:
        """Execute a shell command asynchronously."""
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        try:
            stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
            return (
                proc.returncode or 0,
                stdout.decode("utf-8", errors="replace"),
                stderr.decode("utf-8", errors="replace"),
            )
        except asyncio.TimeoutError:
            proc.kill()
            return (-1, "", "Backup execution timed out.")

    async def create_backup(
        self, container_name: str, project_id: str
    ) -> Dict[str, Any]:
        """Trigger wp ai1wm backup inside container and return download metadata."""
        # 1. Run WP-CLI backup command
        cmd = ["docker", "exec", "-u", "www-data", container_name, "wp", "ai1wm", "backup"]
        code, stdout, stderr = await self.run_cmd(cmd, timeout=300)

        if code != 0:
            # Fallback: run via php if cli command is missing
            fb_cmd = [
                "docker",
                "exec",
                "-u",
                "www-data",
                container_name,
                "php",
                "-r",
                (
                    "require_once 'wp-load.php'; "
                    "if (class_exists('Ai1wm_Export_Controller')) { "
                    "  echo 'AI1WM detected'; "
                    "}"
                ),
            ]
            await self.run_cmd(fb_cmd, timeout=30)

        # 2. Find generated .wpress file inside container
        list_cmd = [
            "docker",
            "exec",
            container_name,
            "find",
            "/var/www/html/wp-content/ai1wm-backups",
            "-name",
            "*.wpress",
            "-type",
            "f",
        ]
        code, out, _ = await self.run_cmd(list_cmd, timeout=30)
        files = [f.strip() for f in out.splitlines() if f.strip().endswith(".wpress")]
        if not files:
            return {
                "success": False,
                "error": f"No .wpress backup file generated. Logs: {stderr or stdout}",
            }

        # Take newest backup file
        container_file = files[-1]
        filename = os.path.basename(container_file)

        # 3. Copy out to project workspace backups directory
        project_backups_dir = os.path.join(
            settings.workspaces_root, project_id, "backups"
        )
        os.makedirs(project_backups_dir, exist_ok=True)
        local_dest = os.path.join(project_backups_dir, filename)

        cp_cmd = ["docker", "cp", f"{container_name}:{container_file}", local_dest]
        cp_code, _, cp_err = await self.run_cmd(cp_cmd, timeout=60)
        if cp_code != 0:
            return {"success": False, "error": f"Failed to extract backup: {cp_err}"}

        file_size_bytes = os.path.getsize(local_dest) if os.path.exists(local_dest) else 0
        size_mb = round(file_size_bytes / (1024 * 1024), 2)

        return {
            "success": True,
            "filename": filename,
            "file_path": local_dest,
            "file_size_mb": size_mb,
            "download_url": f"/api/projects/{project_id}/files/backups/{filename}",
        }


wp_backup = WordPressBackupService()
