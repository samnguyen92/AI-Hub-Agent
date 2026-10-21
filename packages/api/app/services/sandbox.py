"""Sandbox client communicating with the isolated Playwright container."""

from typing import Any, Dict
import httpx


class SandboxClient:
    """HTTP client communicating with the Sandbox service."""

    def __init__(self, base_url: str = "http://sandbox:8001") -> None:
        self.base_url = base_url

    async def render_html(
        self, html_content: str, desktop_output: str, mobile_output: str
    ) -> Dict[str, Any]:
        """Request sandbox to render HTML into desktop and mobile PNGs."""
        async with httpx.AsyncClient(timeout=60.0) as client:
            res = await client.post(
                f"{self.base_url}/render-html",
                json={
                    "html_content": html_content,
                    "desktop_output_path": desktop_output,
                    "mobile_output_path": mobile_output,
                },
            )
            res.raise_for_status()
            return res.json()

    async def render_workspace_html(
        self, html_path: str, desktop_output: str, mobile_output: str
    ) -> Dict[str, Any]:
        """Render a workspace HTML file with its relative assets available."""
        async with httpx.AsyncClient(timeout=60.0) as client:
            res = await client.post(
                f"{self.base_url}/render-workspace-html",
                json={
                    "html_path": html_path,
                    "desktop_output_path": desktop_output,
                    "mobile_output_path": mobile_output,
                },
            )
            res.raise_for_status()
            return res.json()

    async def take_screenshot(
        self, url: str, desktop_output: str, mobile_output: str
    ) -> Dict[str, Any]:
        """Request sandbox to capture screenshots of a live URL."""
        async with httpx.AsyncClient(timeout=60.0) as client:
            res = await client.post(
                f"{self.base_url}/screenshot-url",
                json={
                    "url": url,
                    "desktop_output_path": desktop_output,
                    "mobile_output_path": mobile_output,
                },
            )
            res.raise_for_status()
            return res.json()

    async def run_checks(self, html_content: str) -> Dict[str, Any]:
        """Request sandbox to audit HTML content for standards and A11y."""
        async with httpx.AsyncClient(timeout=30.0) as client:
            res = await client.post(
                f"{self.base_url}/run-checks",
                json={"html_content": html_content},
            )
            res.raise_for_status()
            return res.json()

    async def compare_images(self, img1: str, img2: str) -> Dict[str, Any]:
        """Request sandbox to calculate visual diff between two images."""
        async with httpx.AsyncClient(timeout=30.0) as client:
            res = await client.post(
                f"{self.base_url}/compare-images",
                json={"image1_path": img1, "image2_path": img2},
            )
            res.raise_for_status()
            return res.json()

    async def prepare_image(self, image_path: str) -> Dict[str, str]:
        """Resize a workspace image into a bounded data URL for vision input."""
        async with httpx.AsyncClient(timeout=30.0) as client:
            res = await client.post(
                f"{self.base_url}/prepare-image", json={"image_path": image_path}
            )
            res.raise_for_status()
            return res.json()


sandbox_client = SandboxClient()
