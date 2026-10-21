"""Isolated Sandbox microservice running Playwright rendering and audits."""

import os
import re
from typing import Any, Dict, List, Optional
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from playwright.async_api import async_playwright
from PIL import Image, ImageChops
from app.image_routes import router as image_router
from app.path_security import resolve_workspace_path
from app.browser_routes import router as browser_router
from app.browser_security import guard_browser_request, validate_public_url

app = FastAPI(title="Hub AI Sandbox", version="0.1.0")
app.include_router(image_router)
app.include_router(browser_router)


async def inspect_rendered_page(page: Any) -> Dict[str, Any]:
    """Extract concise visible structure and computed style observations."""
    return await page.evaluate(
        """() => {
          const visible = (element) => {
            const rect = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
          };
          const summarize = (element) => {
            const rect = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return {
              tag: element.tagName.toLowerCase(),
              text: (element.innerText || element.getAttribute('aria-label') || '').trim().replace(/\\s+/g, ' ').slice(0, 100),
              x: Math.round(rect.x), y: Math.round(rect.y),
              width: Math.round(rect.width), height: Math.round(rect.height),
              display: style.display, layout: style.display === 'grid' ? style.gridTemplateColumns : style.flexDirection,
              color: style.color, background: style.backgroundColor,
              font: style.fontFamily.split(',')[0].replaceAll('"', ''), fontSize: style.fontSize,
              radius: style.borderRadius
            };
          };
          const selector = 'header, nav, main, footer, h1, h2, h3, button, [role="button"]';
          const elements = [...document.querySelectorAll(selector)].filter(visible).slice(0, 28);
          const headings = [...document.querySelectorAll('h1,h2,h3')].filter(visible).slice(0, 16).map((el) => ({
            level: el.tagName.toLowerCase(), text: el.innerText.trim().replace(/\\s+/g, ' ').slice(0, 120)
          }));
          return {
            viewport: { width: innerWidth, height: innerHeight },
            page: { width: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight },
            title: document.title,
            headings,
            visible_elements: elements.map(summarize),
            fonts: [...new Set(elements.map((el) => getComputedStyle(el).fontFamily.split(',')[0].replaceAll('"', '')))].slice(0, 8)
          };
        }"""
    )


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
    desktop_path = resolve_workspace_path(req.desktop_output_path)
    mobile_path = resolve_workspace_path(req.mobile_output_path)
    os.makedirs(os.path.dirname(desktop_path), exist_ok=True)
    os.makedirs(os.path.dirname(mobile_path), exist_ok=True)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)

        # 1. Desktop Render (1440x900)
        page_desktop = await browser.new_page(viewport={"width": 1440, "height": 900})
        await page_desktop.route("**/*", guard_browser_request)
        await page_desktop.set_content(req.html_content, wait_until="networkidle")
        await page_desktop.screenshot(path=desktop_path, full_page=True, animations="disabled")
        await page_desktop.close()

        # 2. Mobile Render (390x844)
        page_mobile = await browser.new_page(viewport={"width": 390, "height": 844})
        await page_mobile.route("**/*", guard_browser_request)
        await page_mobile.set_content(req.html_content, wait_until="networkidle")
        await page_mobile.screenshot(path=mobile_path, full_page=True, animations="disabled")
        await page_mobile.close()

        await browser.close()

    return {
        "status": "success",
        "desktop_png": desktop_path,
        "mobile_png": mobile_path,
    }


@app.post("/screenshot-url")
async def screenshot_url(req: ScreenshotUrlRequest) -> Dict[str, Any]:
    """Capture live screenshots of an external URL."""
    validate_public_url(req.url)
    desktop_path = resolve_workspace_path(req.desktop_output_path)
    mobile_path = resolve_workspace_path(req.mobile_output_path)
    os.makedirs(os.path.dirname(desktop_path), exist_ok=True)
    os.makedirs(os.path.dirname(mobile_path), exist_ok=True)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)

        # Desktop
        page_desktop = await browser.new_page(viewport={"width": 1440, "height": 900})
        await page_desktop.route("**/*", guard_browser_request)
        await page_desktop.goto(req.url, wait_until="load", timeout=30000)
        desktop_inspection = await inspect_rendered_page(page_desktop)
        await page_desktop.screenshot(path=desktop_path, full_page=True, animations="disabled")
        await page_desktop.close()

        # Mobile
        page_mobile = await browser.new_page(viewport={"width": 390, "height": 844})
        await page_mobile.route("**/*", guard_browser_request)
        await page_mobile.goto(req.url, wait_until="load", timeout=30000)
        mobile_inspection = await inspect_rendered_page(page_mobile)
        await page_mobile.screenshot(path=mobile_path, full_page=True, animations="disabled")
        await page_mobile.close()

        await browser.close()

    return {
        "status": "success",
        "url": req.url,
        "desktop_png": desktop_path,
        "mobile_png": mobile_path,
        "desktop_inspection": desktop_inspection,
        "mobile_inspection": mobile_inspection,
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

    has_css_motion = bool(re.search(r"(?:^|[;{}])\s*(?:animation|transition)\s*:", html))
    has_js_motion = bool(re.search(r"requestAnimationFrame|\.animate\s*\(|setInterval\s*\(", html))
    has_visual_animation = bool(re.search(r"<lottie-player|<animate(?:Transform|Motion)?\b", html, re.I))
    has_webgl = bool(re.search(r"getContext\s*\(\s*['\"]webgl2?['\"]|gl\.shaderSource", html, re.I))
    families = []
    if re.search(r"transition\s*:", html):
        families.append("css_transition")
    if re.search(r"animation\s*:", html):
        families.append("css_keyframes")
    if has_js_motion:
        families.append("javascript_runtime")
    if has_visual_animation:
        families.append("visual_animation")
    if has_webgl:
        families.append("webgl_glsl")
    has_motion = has_css_motion or has_js_motion or has_webgl
    has_reduced_motion = "prefers-reduced-motion" in html
    if has_css_motion and not has_reduced_motion:
        issues.append("Có motion nhưng thiếu media query prefers-reduced-motion")
    if re.search(r"transition\s*:\s*all\b", html):
        issues.append("Tránh transition: all; chỉ chuyển động các thuộc tính cần thiết")
    if re.search(r"animation-iteration-count\s*:\s*infinite", html):
        issues.append("Animation lặp vô hạn cần được rà soát mục đích và tùy chọn tắt")
    if has_webgl and not re.search(r"fallback|<noscript|<img|poster=", html, re.I):
        issues.append("WebGL cần fallback tĩnh hoặc nội dung thay thế khi thiết bị không hỗ trợ")
    if has_webgl and "visibilitychange" not in html:
        issues.append("WebGL cần dừng render khi tab bị ẩn để giảm tải GPU")
    if has_webgl and not has_reduced_motion:
        issues.append("WebGL animation cần có chế độ reduced motion hoặc dừng chuyển động")
    if has_js_motion and not has_reduced_motion:
        issues.append("JavaScript motion cần tôn trọng prefers-reduced-motion")

    return {
        "html_valid": len(issues) == 0,
        "issues_count": len(issues),
        "issues": issues,
        "a11y_status": "passed" if not has_img_without_alt else "warning",
        "motion_status": _motion_status(has_motion, issues),
        "motion_detected": has_motion,
        "motion_families": families,
    }


def _motion_status(has_motion: bool, issues: List[str]) -> str:
    """Summarize motion findings without treating static pages as failures."""
    motion_findings = [
        issue for issue in issues
        if any(term in issue.lower() for term in ("motion", "animation", "webgl"))
    ]
    if not has_motion:
        return "not_applicable"
    return "warning" if motion_findings else "passed"


@app.post("/compare-images")
async def compare_images(req: CompareImagesRequest) -> Dict[str, Any]:
    """Compute visual diff between two images."""
    image1_path = resolve_workspace_path(req.image1_path)
    image2_path = resolve_workspace_path(req.image2_path)
    if not os.path.exists(image1_path) or not os.path.exists(image2_path):
        raise HTTPException(status_code=400, detail="One or both image files not found")

    img1 = Image.open(image1_path).convert("RGB")
    img2 = Image.open(image2_path).convert("RGB")

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
