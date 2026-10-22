"""Sandbox client communicating with the isolated Playwright container."""

import os
from typing import Any, Dict, Optional
import httpx
from app.config import settings
from app.services.workspace import workspace_service


class SandboxClient:
    """HTTP client communicating with the Sandbox service."""

    def __init__(self, base_url: Optional[str] = None) -> None:
        self.base_url = base_url or settings.sandbox_internal_url or settings.sandbox_url

    def _headers(self) -> Dict[str, str]:
        """Attach the shared secret required by the hosted sandbox service."""
        if not settings.sandbox_shared_secret:
            return {}
        return {"X-Sandbox-Secret": settings.sandbox_shared_secret}

    def _restore_workspace(self, path: str, commit_message: Optional[str] = None) -> None:
        """Pull sandbox output back into this API instance's scratch workspace."""
        root = os.path.realpath(settings.workspaces_root)
        resolved = os.path.realpath(path)
        if os.path.commonpath((root, resolved)) != root:
            return
        relative = os.path.relpath(resolved, root).split(os.sep, 1)
        if relative and relative[0] not in (".", ".."):
            workspace_service.init_project_workspace(relative[0], refresh=True)
            if commit_message:
                workspace_service.commit_step(relative[0], commit_message)

    async def render_html(
        self, html_content: str, desktop_output: str, mobile_output: str
    ) -> Dict[str, Any]:
        """Request sandbox to render HTML into desktop and mobile PNGs."""
        async with httpx.AsyncClient(timeout=60.0) as client:
            res = await client.post(
                f"{self.base_url}/render-html",
                headers=self._headers(),
                json={
                    "html_content": html_content,
                    "desktop_output_path": desktop_output,
                    "mobile_output_path": mobile_output,
                },
            )
            res.raise_for_status()
            self._restore_workspace(desktop_output, "render: save desktop and mobile previews")
            return res.json()

    async def render_workspace_html(
        self, html_path: str, desktop_output: str, mobile_output: str
    ) -> Dict[str, Any]:
        """Render a workspace HTML file with its relative assets available."""
        async with httpx.AsyncClient(timeout=60.0) as client:
            res = await client.post(
                f"{self.base_url}/render-workspace-html",
                headers=self._headers(),
                json={
                    "html_path": html_path,
                    "desktop_output_path": desktop_output,
                    "mobile_output_path": mobile_output,
                },
            )
            res.raise_for_status()
            self._restore_workspace(html_path, "render: save desktop and mobile previews")
            return res.json()

    async def take_screenshot(
        self, url: str, desktop_output: str, mobile_output: str
    ) -> Dict[str, Any]:
        """Request sandbox to capture screenshots of a live URL."""
        async with httpx.AsyncClient(timeout=60.0) as client:
            res = await client.post(
                f"{self.base_url}/screenshot-url",
                headers=self._headers(),
                json={
                    "url": url,
                    "desktop_output_path": desktop_output,
                    "mobile_output_path": mobile_output,
                },
            )
            res.raise_for_status()
            self._restore_workspace(desktop_output, "screenshot: save desktop and mobile captures")
            return res.json()

    async def run_checks(self, html_content: str) -> Dict[str, Any]:
        """Request sandbox to audit HTML content for standards and A11y."""
        async with httpx.AsyncClient(timeout=30.0) as client:
            res = await client.post(
                f"{self.base_url}/run-checks",
                headers=self._headers(),
                json={"html_content": html_content},
            )
            res.raise_for_status()
            return res.json()

    async def compare_images(self, img1: str, img2: str) -> Dict[str, Any]:
        """Request sandbox to calculate visual diff between two images."""
        async with httpx.AsyncClient(timeout=30.0) as client:
            res = await client.post(
                f"{self.base_url}/compare-images",
                headers=self._headers(),
                json={"image1_path": img1, "image2_path": img2},
            )
            res.raise_for_status()
            self._restore_workspace(img1)
            return res.json()

    async def prepare_image(self, image_path: str) -> Dict[str, str]:
        """Resize a workspace image into a bounded data URL for vision input."""
        async with httpx.AsyncClient(timeout=30.0) as client:
            res = await client.post(
                f"{self.base_url}/prepare-image",
                headers=self._headers(),
                json={"image_path": image_path},
            )
            res.raise_for_status()
            self._restore_workspace(image_path)
            return res.json()


sandbox_client = SandboxClient()
