"""Playwright previews for workspace pages with their local assets enabled."""

import os
from typing import Any, Dict

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from playwright.async_api import async_playwright

from app.browser_security import guard_browser_request
from app.path_security import resolve_workspace_path

router = APIRouter()


class WorkspaceRenderRequest(BaseModel):
    """Workspace HTML and screenshot paths for a responsive preview."""

    html_path: str
    desktop_output_path: str
    mobile_output_path: str


@router.post("/render-workspace-html")
async def render_workspace_html(req: WorkspaceRenderRequest) -> Dict[str, Any]:
    """Render a workspace page as a file URL so relative CSS/JS/images resolve."""
    html_path = resolve_workspace_path(req.html_path)
    desktop_path = resolve_workspace_path(req.desktop_output_path)
    mobile_path = resolve_workspace_path(req.mobile_output_path)
    if not os.path.isfile(html_path) or not html_path.lower().endswith((".html", ".htm")):
        raise HTTPException(status_code=400, detail="Workspace page must be an existing HTML file")
    os.makedirs(os.path.dirname(desktop_path), exist_ok=True)
    os.makedirs(os.path.dirname(mobile_path), exist_ok=True)
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        await _capture_viewport(browser, html_path, desktop_path, 1440, 900)
        await _capture_viewport(browser, html_path, mobile_path, 390, 844)
        await browser.close()
    return {"status": "success", "desktop_png": desktop_path, "mobile_png": mobile_path}


async def _capture_viewport(
    browser: Any, html_path: str, output_path: str, width: int, height: int
) -> None:
    """Capture one full-page viewport while guarding local and remote requests."""
    page = await browser.new_page(viewport={"width": width, "height": height})
    await page.route("**/*", guard_browser_request)
    await page.goto(f"file://{html_path}", wait_until="networkidle")
    await page.screenshot(path=output_path, full_page=True, animations="disabled")
    await page.close()
