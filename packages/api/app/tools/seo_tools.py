"""SEO and web performance analysis tools with real PageSpeed Insights integration."""

import re
from typing import Any, Dict, Optional
import httpx

from app.config import settings
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.web_tools import safe_fetch


async def fetch_pagespeed_insights(url: str, strategy: str = "mobile") -> Optional[Dict[str, Any]]:
    """Query official Google PageSpeed Insights API for genuine audit metrics."""
    endpoint = f"https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url={url}&strategy={strategy}"
    if settings.pagespeed_api_key:
        endpoint += f"&key={settings.pagespeed_api_key}"

    try:
        async with httpx.AsyncClient(timeout=25.0) as client:
            resp = await client.get(endpoint)
            if resp.status_code == 200:
                data = resp.json()
                lh = data.get("lighthouseResult", {})
                cats = lh.get("categories", {})
                audits = lh.get("audits", {})

                return {
                    "source": "google_pagespeed_insights",
                    "strategy": strategy,
                    "lighthouse_scores": {
                        "performance": cats.get("performance", {}).get("score"),
                        "accessibility": cats.get("accessibility", {}).get("score"),
                        "best_practices": cats.get("best-practices", {}).get("score"),
                        "seo": cats.get("seo", {}).get("score"),
                    },
                    "core_web_vitals": {
                        "lcp": audits.get("largest-contentful-paint", {}).get("displayValue", "N/A"),
                        "cls": audits.get("cumulative-layout-shift", {}).get("displayValue", "N/A"),
                        "inp_or_tbt": audits.get("interaction-to-next-paint", {}).get("displayValue")
                        or audits.get("total-blocking-time", {}).get("displayValue", "N/A"),
                    },
                }
    except Exception:
        pass
    return None


def analyze_html_seo(html: str) -> Dict[str, Any]:
    """Perform real static HTML checks without fabricated metrics."""
    has_meta_desc = bool(re.search(r'<meta[^>]*name=["\']description["\']', html, re.I))
    has_title = bool(re.search(r"<title[^>]*>.+?</title>", html, re.I))
    has_viewport = "viewport" in html.lower()
    h1_count = len(re.findall(r"<h1[^>]*>", html, re.I))
    images_without_alt = len(re.findall(r"<img(?![^>]*\balt=)[^>]*>", html, re.I))

    return {
        "has_title": has_title,
        "has_meta_description": has_meta_desc,
        "has_viewport": has_viewport,
        "h1_count": h1_count,
        "images_without_alt": images_without_alt,
    }


class RunLighthouseTool(BaseTool):
    """Tool to perform Lighthouse audit via Google PageSpeed Insights or static analysis."""

    name = "run_lighthouse"
    description = "Đo lường Performance, Accessibility, SEO qua Google PageSpeed Insights hoặc phân tích tĩnh."
    parameters_schema = {
        "type": "object",
        "properties": {
            "url": {"type": "string", "description": "URL trang web cần kiểm tra"},
            "strategy": {"type": "string", "enum": ["mobile", "desktop"], "default": "mobile"},
        },
        "required": ["url"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        url = kwargs["url"]
        strategy = kwargs.get("strategy", "mobile")

        # 1. Attempt official Google PageSpeed Insights API
        psi_result = await fetch_pagespeed_insights(url, strategy=strategy)
        if psi_result:
            return {"url": url, **psi_result}

        # 2. Fallback to transparent static HTML inspection
        try:
            headers = {"User-Agent": "Mozilla/5.0 (AlignHubAuditor/1.0; Lighthouse emulation)"}
            async with httpx.AsyncClient(headers=headers) as client:
                res = await safe_fetch(client, url)

            checks = analyze_html_seo(res.text)
            seo_score = 1.0
            if not checks["has_title"]:
                seo_score -= 0.3
            if not checks["has_meta_description"]:
                seo_score -= 0.25
            if checks["h1_count"] != 1:
                seo_score -= 0.15
            a11y_score = 0.95 if checks["images_without_alt"] == 0 else 0.70

            return {
                "url": url,
                "source": "static_html_analysis",
                "notice": "Google PageSpeed Insights không khả dụng; kết quả dựa trên phân tích tĩnh thực tế.",
                "lighthouse_scores": {
                    "performance": None,
                    "accessibility": round(max(a11y_score, 0.0), 2),
                    "best_practices": 0.90 if url.startswith("https") else 0.60,
                    "seo": round(max(seo_score, 0.0), 2),
                },
                "audit_checks": checks,
                "core_web_vitals": {
                    "status": "unavailable_offline",
                    "note": "Core Web Vitals chỉ có thể đo lường qua Google PageSpeed với URL công khai.",
                },
            }
        except Exception as exc:
            return {"error": f"Lỗi chạy audit cho {url}: {str(exc)}"}


tool_registry.register(RunLighthouseTool())
