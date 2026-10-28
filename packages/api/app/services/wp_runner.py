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
        """Create a clean DNS-safe and Docker-safe name from project ID or subdomain."""
        clean = "".join(c if (c.isalnum() or c in ("-", "_")) else "-" for c in text.lower())
        clean = clean.strip("-_")
        return clean[:24] or "site"

    @staticmethod
    def find_available_port(start_port: int = 8501, max_tries: int = 100) -> int:
        """Find an unallocated TCP port on the host (defaults to 8501+)."""
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
        """Execute WP-CLI inside the container as www-data user with strict sanitization."""
        import re
        if not re.match(r"^[a-zA-Z0-9_\-]+$", container_name):
            return (-1, "", f"Tên container không hợp lệ: {container_name}")

        blocked_prefixes = ("--exec", "--require", "--eval", "--ssh", "--http")
        for arg in args:
            if any(c in arg for c in ("\x00", "\n", "\r")):
                return (-1, "", "Tham số chứa ký tự không hợp lệ.")
            arg_lower = arg.lower()
            for prefix in blocked_prefixes:
                if arg_lower == prefix or arg_lower.startswith(f"{prefix}="):
                    return (-1, "", f"Tham số WP-CLI '{prefix}' bị từ chối vì lý do bảo mật.")

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

    async def _init_database(
        self, db_name: str, db_user: Optional[str] = None, db_pass: Optional[str] = None
    ) -> bool:
        """Create isolated database and user inside hub_ai_mariadb if not already present."""
        root_pass = os.getenv("MARIADB_ROOT_PASSWORD", "wp_secure_pass")
        sql = f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        if db_user and db_pass:
            sql += (
                f" CREATE USER IF NOT EXISTS '{db_user}'@'%' IDENTIFIED BY '{db_pass}';"
                f" ALTER USER '{db_user}'@'%' IDENTIFIED BY '{db_pass}';"
                f" GRANT ALL PRIVILEGES ON `{db_name}`.* TO '{db_user}'@'%';"
                " FLUSH PRIVILEGES;"
            )
        cmd = ["docker", "exec", "hub_ai_mariadb", "mariadb", "-u", "root", f"-p{root_pass}", "-e", sql]
        code, _, _ = await self.run_cmd(cmd)
        return code == 0

    async def _launch_container(
        self,
        container_name: str,
        port: int,
        db_name: str,
        db_user: Optional[str] = None,
        db_pass: Optional[str] = None,
    ) -> bool:
        """Run standard WordPress docker container connected to MariaDB with least-privilege DB user."""
        net_code, net_out, _ = await self.run_cmd([
            "docker", "inspect", "hub_ai_mariadb",
            "--format", "{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}"
        ])
        network = net_out.strip() or "aihubagent_default"
        wp_db_user = db_user or "root"
        wp_db_pass = db_pass or os.getenv("MARIADB_ROOT_PASSWORD", "wp_secure_pass")

        run_cmd = [
            "docker", "run", "-d",
            "--name", container_name,
            "--network", network,
            "-p", f"{port}:80",
            "-e", "WORDPRESS_DB_HOST=hub_ai_mariadb:3306",
            "-e", f"WORDPRESS_DB_USER={wp_db_user}",
            "-e", f"WORDPRESS_DB_PASSWORD={wp_db_pass}",
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

        # Configure dynamic host & HTTPS reverse proxy support in wp-config.php
        php_inject = (
            "$f='/var/www/html/wp-config.php';"
            "if(file_exists($f)){"
            "$c=file_get_contents($f);"
            "if(strpos($c,'HTTP_X_FORWARDED_PROTO')===false){"
            "$s=\"\\nif(isset(\\$_SERVER['HTTP_X_FORWARDED_PROTO'])&&\\$_SERVER['HTTP_X_FORWARDED_PROTO']==='https'){\\$_SERVER['HTTPS']='on';}\\n\";"
            "$s.=\"if(isset(\\$_SERVER['HTTP_HOST'])){\\$p=(isset(\\$_SERVER['HTTPS'])&&\\$_SERVER['HTTPS']==='on')?'https':'http';if(!defined('WP_HOME'))define('WP_HOME',\\$p.'://'.\\$_SERVER['HTTP_HOST']);if(!defined('WP_SITEURL'))define('WP_SITEURL',\\$p.'://'.\\$_SERVER['HTTP_HOST']);}\\n\";"
            "$c=preg_replace('/(\\/\\* That\\'s all, stop editing!)/',$s.'$1',$c);"
            "file_put_contents($f,$c);"
            "}}"
        )
        await self.run_cmd(["docker", "exec", container_name, "php", "-r", php_inject])
        return True

    def format_site_url(self, slug: str) -> str:
        """Format public site URL as 1-level subdomain compatible with Cloudflare Universal SSL."""
        if not settings.wp_base_domain:
            return f"http://{slug}.localhost:8500"

        base = settings.wp_base_domain.strip().lstrip(".")
        if base.startswith("demo."):
            root = base[5:]
            return f"https://demo-{slug}.{root}"
        elif base.startswith("wp."):
            root = base[3:]
            return f"https://wp-{slug}.{root}"
        return f"https://demo-{slug}.{base}"

    async def create_site(
        self,
        project_id: str,
        site_title: str,
        db: AsyncSession,
        subdomain: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Create and provision a complete WordPress container environment for a project."""
        slug = self._sanitize_slug(subdomain or project_id)
        existing = await self.get_site(project_id, db)
        if existing and existing.status == "running":
            expected_url = self.format_site_url(slug)
            if existing.site_url != expected_url:
                existing.site_url = expected_url
                existing.admin_url = f"{expected_url}/wp-admin"
                await db.commit()
            return {
                "success": True,
                "site_url": existing.site_url,
                "admin_url": existing.admin_url,
                "admin_user": existing.admin_user,
                "admin_password": existing.admin_password,
                "status": existing.status,
            }

        container_name = f"hub_ai_wp_{slug}"
        # Check if container_name/slug is already used by another project
        conflict = (
            await db.execute(
                select(WordPressSite).where(
                    WordPressSite.container_name == container_name,
                    WordPressSite.project_id != project_id,
                )
            )
        ).scalars().first()
        if conflict:
            short_id = project_id[:6].lower()
            slug = f"{slug[:17]}-{short_id}"
            container_name = f"hub_ai_wp_{slug}"

        db_name = f"wp_{slug.replace('-', '_')}"
        db_user = f"wpu_{slug.replace('-', '_')[:24]}"
        db_pass = secrets.token_hex(16)
        port = self.find_available_port(8501)
        site_url = self.format_site_url(slug)

        admin_user = "admin"
        admin_pass = secrets.token_urlsafe(12)

        # 1. Setup database & launch container
        await self._init_database(db_name, db_user=db_user, db_pass=db_pass)
        # Only remove if it belongs to this project
        await self.run_cmd(["docker", "rm", "-f", container_name])
        await self._launch_container(container_name, port, db_name, db_user=db_user, db_pass=db_pass)

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
            "direct_port_url": f"http://localhost:{port}",
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
