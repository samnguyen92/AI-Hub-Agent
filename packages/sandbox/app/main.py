"""Isolated Sandbox microservice running Playwright rendering and audits."""

import os
from typing import Any, Dict, List, Optional
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from playwright.async_api import async_playwright
from PIL import Image, ImageChops

app = FastAPI(title="Hub AI Sandbox", version="0.1.0")


class RenderHtmlRequest(BaseModel):
    html_content: str
    desktop_output_path: str
    mobile_output_path: str


class ScreenshotUrlRequest(BaseModel):
    url: str
    desktop_output_path: str
    mobile_output_path: str


class RunChecksRequest(BaseModel):
    html_content: str


class CompareImagesRequest(BaseModel):
    image1_path: str
    image2_path: str


@app.get("/health")
async def health_check() -> Dict[str, str]:
    """Liveness probe."""
    return {"status": "ok", "service": "hub-sandbox"}


@app.post("/render-html")
async def render_html(req: RenderHtmlRequest) -> Dict[str, Any]:
    """Render HTML content to Desktop (1440px) and Mobile (390px) PNG images."""
    os.makedirs(os.path.dirname(os.path.abspath(req.desktop_output_path)), exist_ok=True)
    os.makedirs(os.path.dirname(os.path.abspath(req.mobile_output_path)), exist_ok=True)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)

        # 1. Desktop Render (1440x900)
        page_desktop = await browser.new_page(viewport={"width": 1440, "height": 900})
        await page_desktop.set_content(req.html_content, wait_until="networkidle")
        await page_desktop.screenshot(path=req.desktop_output_path, full_page=True)
        await page_desktop.close()

        # 2. Mobile Render (390x844)
        page_mobile = await browser.new_page(viewport={"width": 390, "height": 844})
        await page_mobile.set_content(req.html_content, wait_until="networkidle")
        await page_mobile.screenshot(path=req.mobile_output_path, full_page=True)
        await page_mobile.close()

        await browser.close()

    return {
        "status": "success",
        "desktop_png": req.desktop_output_path,
        "mobile_png": req.mobile_output_path,
    }


@app.post("/screenshot-url")
async def screenshot_url(req: ScreenshotUrlRequest) -> Dict[str, Any]:
    """Capture live screenshots of an external URL."""
    os.makedirs(os.path.dirname(os.path.abspath(req.desktop_output_path)), exist_ok=True)
    os.makedirs(os.path.dirname(os.path.abspath(req.mobile_output_path)), exist_ok=True)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)

        # Desktop
        page_desktop = await browser.new_page(viewport={"width": 1440, "height": 900})
        await page_desktop.goto(req.url, wait_until="load", timeout=30000)
        await page_desktop.screenshot(path=req.desktop_output_path, full_page=True)
        await page_desktop.close()

        # Mobile
        page_mobile = await browser.new_page(viewport={"width": 390, "height": 844})
        await page_mobile.goto(req.url, wait_until="load", timeout=30000)
        await page_mobile.screenshot(path=req.mobile_output_path, full_page=True)
        await page_mobile.close()

        await browser.close()

    return {
        "status": "success",
        "url": req.url,
        "desktop_png": req.desktop_output_path,
        "mobile_png": req.mobile_output_path,
    }


@app.post("/run-checks")
async def run_checks(req: RunChecksRequest) -> Dict[str, Any]:
    """Audit HTML content for validity, accessibility and standards."""
    html = req.html_content.lower()
    issues: List[str] = []

    if "<!doctype html>" not in html:
        issues.append("Thiếu khai báo <!DOCTYPE html>")
    if "<meta name=\"viewport\"" not in html and "<meta name='viewport'" not in html:
        issues.append("Thiếu meta viewport responsive")
    if "<title>" not in html or "</title>" not in html:
        issues.append("Thiếu thẻ <title> SEO")
    if "<html lang=" not in html:
        issues.append("Thiếu thuộc tính lang trên thẻ <html>")

    has_img_without_alt = "<img" in html and "alt=" not in html
    if has_img_without_alt:
        issues.append("Phát hiện thẻ <img> thiếu thuộc tính alt (vi phạm A11y)")

    return {
        "html_valid": len(issues) == 0,
        "issues_count": len(issues),
        "issues": issues,
        "a11y_status": "passed" if not has_img_without_alt else "warning",
    }


@app.post("/compare-images")
async def compare_images(req: CompareImagesRequest) -> Dict[str, Any]:
    """Compute visual diff between two images."""
    if not os.path.exists(req.image1_path) or not os.path.exists(req.image2_path):
        raise HTTPException(status_code=400, detail="One or both image files not found")

    img1 = Image.open(req.image1_path).convert("RGB")
    img2 = Image.open(req.image2_path).convert("RGB")

    # Resize to match img1 if dimensions differ
    if img1.size != img2.size:
        img2 = img2.resize(img1.size)

    diff = ImageChops.difference(img1, img2)
    stat = diff.getbbox()

    is_identical = stat is None
    return {
        "is_identical": is_identical,
        "mismatch_detected": not is_identical,
        "dimensions": {"width": img1.size[0], "height": img1.size[1]},
    }
