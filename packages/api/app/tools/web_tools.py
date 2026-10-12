"""Web scanning and crawling tools with strict SSRF defense."""

import os
import re
from typing import Any, Dict, List, Set
from urllib.parse import urljoin, urlparse
import httpx

from app.services.workspace import workspace_service
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path
from app.tools.ssrf_guard import validate_safe_url


async def safe_fetch(client: httpx.AsyncClient, target_url: str, max_redirects: int = 5) -> httpx.Response:
    """Fetch URL securely while validating each hop against SSRF."""
    curr = target_url
    for _ in range(max_redirects):
        validated = validate_safe_url(curr)
        res = await client.get(validated, follow_redirects=False, timeout=12.0)
        if 300 <= res.status_code < 400 and "location" in res.headers:
            curr = urljoin(curr, res.headers["location"])
            continue
        return res
    raise PermissionError("Vượt quá số lần chuyển hướng tối đa (5 hops).")


def extract_metadata(html: str) -> Dict[str, Any]:
    """Simple regex extractor for title, meta description, and H1 tags."""
    title_m = re.search(r"<title[^>]*>(.*?)</title>", html, re.IGNORECASE | re.DOTALL)
    title = title_m.group(1).strip() if title_m else ""

    desc_m = re.search(r'<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']', html, re.IGNORECASE)
    desc = desc_m.group(1).strip() if desc_m else ""

    canonical_m = re.search(r'<link[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']*)["\']', html, re.IGNORECASE)
    canonical = canonical_m.group(1).strip() if canonical_m else ""

    h1_list = [h.strip() for h in re.findall(r"<h1[^>]*>(.*?)</h1>", html, re.IGNORECASE | re.DOTALL)]

    return {"title": title, "meta_description": desc, "canonical": canonical, "h1": h1_list}


class FetchUrlTool(BaseTool):
    """Tool to securely fetch page contents and save HTML snapshot."""

    name = "fetch_url"
    description = "Tải nội dung trang web an toàn (chống SSRF), bóc tách metadata và lưu snapshot HTML."
    parameters_schema = {
        "type": "object",
        "properties": {
            "url": {"type": "string", "description": "Địa chỉ URL cần tải (http/https)"},
            "save_snapshot": {"type": "boolean", "description": "Lưu file HTML snapshot vào workspace (mặc định true)"},
        },
        "required": ["url"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        url = kwargs["url"]
        save = kwargs.get("save_snapshot", True)

        try:
            headers = {"User-Agent": "AlignHubBot/1.0 (+https://align.vn)"}
            async with httpx.AsyncClient(headers=headers, verify=False) as client:
                res = await safe_fetch(client, url)

            meta = extract_metadata(res.text) if "text/html" in res.headers.get("content-type", "") else {}
            snapshot_path = None

            if save:
                parsed = urlparse(url)
                clean_name = re.sub(r"[^\w\-_]", "_", f"{parsed.netloc}{parsed.path}").strip("_") or "index"
                rel_path = f"snapshots/{clean_name}.html"
                full_path = safe_resolve_path(context.workspace_path, rel_path)
                os.makedirs(os.path.dirname(full_path), exist_ok=True)
                with open(full_path, "w", encoding="utf-8") as f:
                    f.write(res.text)
                workspace_service.commit_step(context.project_id, f"web: fetch snapshot {url}")
                snapshot_path = rel_path

            return {
                "status_code": res.status_code,
                "url": url,
                "content_type": res.headers.get("content-type", ""),
                "metadata": meta,
                "snapshot_path": snapshot_path,
                "size_bytes": len(res.content),
            }
        except Exception as exc:
            return {"error": f"Lỗi tải URL: {str(exc)}"}


class CrawlSiteTool(BaseTool):
    """Tool to crawl up to N internal pages and discover broken links."""

    name = "crawl_site"
    description = "Crawl trang web nội bộ (tối đa 10 trang) để thống kê tiêu đề, meta tags và broken links."
    parameters_schema = {
        "type": "object",
        "properties": {
            "start_url": {"type": "string", "description": "URL gốc bắt đầu crawl"},
            "max_pages": {"type": "integer", "description": "Số trang tối đa (mặc định 5, max 10)"},
        },
        "required": ["start_url"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        start_url = kwargs["start_url"]
        max_p = min(kwargs.get("max_pages", 5), 10)

        visited: Set[str] = set()
        queue: List[str] = [start_url]
        pages_report: List[Dict[str, Any]] = []
        broken_links: List[Dict[str, Any]] = []

        base_domain = urlparse(start_url).netloc
        headers = {"User-Agent": "AlignHubCrawler/1.0"}

        try:
            async with httpx.AsyncClient(headers=headers, verify=False) as client:
                while queue and len(visited) < max_p:
                    curr = queue.pop(0)
                    if curr in visited:
                        continue
                    visited.add(curr)

                    try:
                        res = await safe_fetch(client, curr)
                    except Exception as err:
                        broken_links.append({"source_url": start_url, "target_url": curr, "error": str(err)})
                        continue

                    if res.status_code >= 400:
                        broken_links.append({"source_url": start_url, "target_url": curr, "status_code": res.status_code})
                        continue

                    meta = extract_metadata(res.text)
                    pages_report.append({"url": curr, "status": res.status_code, "metadata": meta})

                    # Discover internal links
                    for link in re.findall(r'href=["\']([^"\']+)["\']', res.text):
                        full = urljoin(curr, link)
                        p_full = urlparse(full)
                        if p_full.netloc == base_domain and p_full.scheme in ("http", "https"):
                            clean = full.split("#")[0].rstrip("/")
                            if clean not in visited and clean not in queue:
                                queue.append(clean)

            return {
                "scanned_pages_count": len(pages_report),
                "pages": pages_report,
                "broken_links": broken_links,
            }
        except Exception as exc:
            return {"error": f"Lỗi crawl: {str(exc)}"}


class CheckSitemapTool(BaseTool):
    """Tool to inspect sitemap.xml and robots.txt."""

    name = "check_sitemap"
    description = "Kiểm tra robots.txt và sitemap.xml của website để phân tích indexability."
    parameters_schema = {
        "type": "object",
        "properties": {
            "site_url": {"type": "string", "description": "URL trang chủ website (vd: https://align.vn)"}
        },
        "required": ["site_url"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site_url = kwargs["site_url"].rstrip("/")
        robots_url = f"{site_url}/robots.txt"
        sitemap_url = f"{site_url}/sitemap.xml"

        headers = {"User-Agent": "AlignHubBot/1.0"}
        res_data: Dict[str, Any] = {"found": False, "url_count": 0, "issues": []}

        try:
            async with httpx.AsyncClient(headers=headers, verify=False) as client:
                try:
                    r_res = await safe_fetch(client, robots_url)
                    res_data["robots_txt_status"] = r_res.status_code
                    if "Sitemap:" in r_res.text:
                        res_data["has_sitemap_in_robots"] = True
                except Exception:
                    res_data["robots_txt_status"] = 404

                try:
                    s_res = await safe_fetch(client, sitemap_url)
                    if s_res.status_code == 200:
                        urls = re.findall(r"<loc>(.*?)</loc>", s_res.text)
                        res_data["found"] = True
                        res_data["url_count"] = len(urls)
                        res_data["sample_urls"] = urls[:10]
                    else:
                        res_data["issues"].append(f"sitemap.xml trả về mã {s_res.status_code}")
                except Exception as s_err:
                    res_data["issues"].append(f"Không thể truy cập sitemap.xml: {str(s_err)}")

            return res_data
        except Exception as exc:
            return {"error": f"Lỗi kiểm tra sitemap: {str(exc)}"}


# Register tools
tool_registry.register(FetchUrlTool())
tool_registry.register(CrawlSiteTool())
tool_registry.register(CheckSitemapTool())
