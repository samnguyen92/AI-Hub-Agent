"""WordPress Runner Service for project-level Docker container orchestration."""

import asyncio
import os
import secrets
import shutil
import socket
from typing import Any, Dict, List, Optional, Tuple

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.db.wp_models import WordPressSite


class WordPressRunnerService:
    """Manages lifecycle, WP-CLI executions, and imports for WordPress project containers."""

    VAULT_DIR = os.path.abspath(
        os.path.join(os.path.dirname(__file__), "../../assets/wp-vault")
    )

    @staticmethod
    def _sanitize_slug(text: str) -> str:
        """Create a clean Docker-safe name from project ID."""
        return "".join(c if c.isalnum() else "_" for c in text.lower())[:24]

    @staticmethod
    def find_available_port(start_port: int = 8081, max_tries: int = 100) -> int:
        """Find an unallocated TCP port on the host."""
        for port in range(start_port, start_port + max_tries):
            with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
                if s.connect_ex(("127.0.0.1", port)) != 0:
                    return port
        return start_port

    async def run_cmd(self, cmd: List[str], timeout: int = 120) -> Tuple[int, str, str]:
        """Execute a shell command asynchronously and return code, stdout, stderr."""
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
            return (-1, "", "Command timed out.")

    async def run_wp_cli(
        self, container_name: str, args: List[str], timeout: int = 120
    ) -> Tuple[int, str, str]:
        """Execute WP-CLI inside the container as www-data user."""
        cmd = ["docker", "exec", "-u", "www-data", container_name, "wp"] + args
        return await self.run_cmd(cmd, timeout=timeout)

    def prepare_vault_assets(self, wp_content_dir: str) -> None:
        """Copy Align themes and plugins from vault into target wp-content directory."""
        themes_dir = os.path.join(wp_content_dir, "themes")
        plugins_dir = os.path.join(wp_content_dir, "plugins")
        os.makedirs(themes_dir, exist_ok=True)
        os.makedirs(plugins_dir, exist_ok=True)

        vault_themes = os.path.join(self.VAULT_DIR, "themes")
        vault_plugins = os.path.join(self.VAULT_DIR, "plugins")

        for theme_name in ("align", "align-child"):
            src = os.path.join(vault_themes, theme_name)
            dst = os.path.join(themes_dir, theme_name)
            if os.path.isdir(src) and not os.path.exists(dst):
                shutil.copytree(src, dst)

        for plugin_name in ("align-agent", "all-in-one-wp-migration", "fluentform"):
            src = os.path.join(vault_plugins, plugin_name)
            dst = os.path.join(plugins_dir, plugin_name)
            if os.path.isdir(src) and not os.path.exists(dst):
                shutil.copytree(src, dst)

    async def copy_vault_to_container(self, container_name: str) -> None:
        """Copy themes and plugins from vault directly into WordPress container."""
        vault_themes = os.path.join(self.VAULT_DIR, "themes")
        vault_plugins = os.path.join(self.VAULT_DIR, "plugins")

        for theme_name in ("align", "align-child"):
            src = os.path.join(vault_themes, theme_name)
            if os.path.isdir(src):
                await self.run_cmd(["docker", "cp", src, f"{container_name}:/var/www/html/wp-content/themes/"])

        for plugin_name in ("align-agent", "all-in-one-wp-migration", "fluentform"):
            src = os.path.join(vault_plugins, plugin_name)
            if os.path.isdir(src):
                await self.run_cmd(["docker", "cp", src, f"{container_name}:/var/www/html/wp-content/plugins/"])

    async def ensure_wp_cli(self, container_name: str) -> bool:
        """Download and install wp-cli binary inside container if missing."""
        code, _, _ = await self.run_cmd(["docker", "exec", container_name, "which", "wp"])
        if code == 0:
            return True

        install_cmds = [
            ["docker", "exec", container_name, "curl", "-sO", "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"],
            ["docker", "exec", container_name, "chmod", "+x", "wp-cli.phar"],
            ["docker", "exec", container_name, "mv", "wp-cli.phar", "/usr/local/bin/wp"],
        ]
        for cmd in install_cmds:
            ret, _, _ = await self.run_cmd(cmd)
            if ret != 0:
                return False
        return True

    async def _init_database(self, db_name: str) -> bool:
        """Create database inside hub_ai_mariadb if not already present."""
        sql = f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        cmd = ["docker", "exec", "hub_ai_mariadb", "mariadb", "-u", "root", "-pwp_secure_pass", "-e", sql]
        code, _, _ = await self.run_cmd(cmd)
        return code == 0

    async def _launch_container(
        self, container_name: str, port: int, db_name: str
    ) -> bool:
        """Run standard WordPress docker container connected to MariaDB."""
        net_code, net_out, _ = await self.run_cmd([
            "docker", "inspect", "hub_ai_mariadb",
            "--format", "{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}"
        ])
        network = net_out.strip() or "aihubagent_default"

        run_cmd = [
            "docker", "run", "-d",
            "--name", container_name,
            "--network", network,
            "-p", f"{port}:80",
            "-e", "WORDPRESS_DB_HOST=hub_ai_mariadb:3306",
            "-e", "WORDPRESS_DB_USER=root",
            "-e", "WORDPRESS_DB_PASSWORD=wp_secure_pass",
            "-e", f"WORDPRESS_DB_NAME={db_name}",
            "wordpress:php8.2-apache"
        ]
        code, _, _ = await self.run_cmd(run_cmd)
        return code == 0

    async def _configure_wp_instance(
        self, container_name: str, site_url: str, title: str, user: str, pwd: str
    ) -> bool:
        """Wait for WordPress container to be ready and configure theme & plugins."""
        for _ in range(15):
            await asyncio.sleep(2)
            chk, _, _ = await self.run_cmd(["docker", "exec", container_name, "curl", "-s", "http://localhost"])
            if chk == 0:
                break

        await self.ensure_wp_cli(container_name)

        chk_code, _, _ = await self.run_wp_cli(container_name, ["core", "is-installed"])
        if chk_code != 0:
            install_args = [
                "core", "install",
                f"--url={site_url}",
                f"--title={title}",
                f"--admin_user={user}",
                f"--admin_password={pwd}",
                "--admin_email=admin@example.com",
                "--skip-email"
            ]
            await self.run_wp_cli(container_name, install_args)

        # Copy vault themes and plugins into container
        await self.copy_vault_to_container(container_name)

        # Set permissions for www-data
        await self.run_cmd(["docker", "exec", container_name, "chown", "-R", "www-data:www-data", "/var/www/html/wp-content"])

        # Activate theme and plugins
        await self.run_wp_cli(container_name, ["theme", "activate", "align-child"])
        await self.run_wp_cli(container_name, ["plugin", "activate", "align-agent", "all-in-one-wp-migration", "fluentform"])
        return True

    async def create_site(
        self, project_id: str, site_title: str, db: AsyncSession
    ) -> Dict[str, Any]:
        """Create and provision a complete WordPress container environment for a project."""
        existing = await self.get_site(project_id, db)
        if existing and existing.status == "running":
            return {
                "success": True,
                "site_url": existing.site_url,
                "admin_url": existing.admin_url,
                "admin_user": existing.admin_user,
                "admin_password": existing.admin_password,
                "status": existing.status,
            }

        slug = self._sanitize_slug(project_id)
        container_name = f"hub_ai_wp_{slug}"
        db_name = f"wp_{slug}"
        port = self.find_available_port(8081)
        site_url = f"http://localhost:{port}"
        admin_user = "admin"
        admin_pass = secrets.token_urlsafe(12)

        # 1. Setup database & launch container
        await self._init_database(db_name)
        await self.run_cmd(["docker", "rm", "-f", container_name])
        await self._launch_container(container_name, port, db_name)

        # 2. Configure WP instance
        await self._configure_wp_instance(container_name, site_url, site_title, admin_user, admin_pass)

        # 3. Save to Database
        site = existing or WordPressSite(project_id=project_id, container_name=container_name)
        site.container_name = container_name
        site.site_url = site_url
        site.admin_url = f"{site_url}/wp-admin"
        site.admin_user = admin_user
        site.admin_password = admin_pass
        site.db_name = db_name
        site.port = port
        site.status = "running"
        db.add(site)
        await db.commit()
        await db.refresh(site)

        return {
            "success": True,
            "site_url": site.site_url,
            "admin_url": site.admin_url,
            "admin_user": site.admin_user,
            "admin_password": site.admin_password,
            "status": site.status,
        }

    async def get_site(self, project_id: str, db: AsyncSession) -> Optional[WordPressSite]:
        """Fetch existing WordPress site record for a project."""
        stmt = select(WordPressSite).where(WordPressSite.project_id == project_id)
        res = await db.execute(stmt)
        return res.scalar_one_or_none()

    async def destroy_site(self, project_id: str, db: AsyncSession) -> bool:
        """Stop and remove WordPress container and database record."""
        site = await self.get_site(project_id, db)
        if not site:
            return True
        await self.run_cmd(["docker", "rm", "-f", site.container_name])
        await db.delete(site)
        await db.commit()
        return True


wp_runner = WordPressRunnerService()
