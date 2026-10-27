"""Service for communicating with external WordPress sites via REST API."""

import base64
from typing import Any, Dict, List, Optional
import httpx


class ExternalWordPressService:
    """Handles REST API communication with external self-hosted or managed WordPress sites."""

    @staticmethod
    def _get_auth_header(username: str, app_password: str) -> Dict[str, str]:
        """Generate HTTP Basic Auth header for WordPress Application Password."""
        clean_pw = app_password.replace(" ", "")
        token = base64.b64encode(f"{username}:{clean_pw}".encode("utf-8")).decode("utf-8")
        return {"Authorization": f"Basic {token}"}

    async def verify_connection(
        self, site_url: str, username: str, app_password: str
    ) -> Dict[str, Any]:
        """Verify REST API credentials against external WordPress site."""
        clean_url = site_url.rstrip("/")
        endpoint = f"{clean_url}/wp-json/wp/v2/users/me"
        headers = self._get_auth_header(username, app_password)

        try:
            async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
                resp = await client.get(endpoint, headers=headers)
                if resp.status_code == 200:
                    data = resp.json()
                    return {
                        "success": True,
                        "name": data.get("name", username),
                        "slug": data.get("slug", username),
                        "roles": data.get("roles", []),
                        "message": f"Kết nối thành công! Đăng nhập với tư cách: {data.get('name', username)}",
                    }
                elif resp.status_code == 401:
                    return {"success": False, "error": "Xác thực thất bại (401): Username hoặc Application Password không đúng."}
                elif resp.status_code == 404:
                    return {"success": False, "error": "Không tìm thấy WordPress REST API (404) tại đường dẫn này."}
                return {"success": False, "error": f"WordPress phản hồi mã lỗi {resp.status_code}: {resp.text[:200]}"}
        except httpx.ConnectError:
            return {"success": False, "error": f"Không thể kết nối tới máy chủ WordPress tại {clean_url}."}
        except Exception as exc:
            return {"success": False, "error": f"Lỗi kết nối: {str(exc)}"}

    async def check_site(
        self, site_url: str, username: str, app_password: str
    ) -> Dict[str, Any]:
        """Inspect site health, title, active theme, and recent posts."""
        clean_url = site_url.rstrip("/")
        headers = self._get_auth_header(username, app_password)

        try:
            async with httpx.AsyncClient(timeout=12.0, follow_redirects=True) as client:
                site_info_resp = await client.get(f"{clean_url}/wp-json", headers=headers)
                site_data = site_info_resp.json() if site_info_resp.status_code == 200 else {}

                posts_resp = await client.get(f"{clean_url}/wp-json/wp/v2/posts?per_page=5", headers=headers)
                recent_posts = []
                if posts_resp.status_code == 200:
                    recent_posts = [
                        {"id": p.get("id"), "title": p.get("title", {}).get("rendered"), "link": p.get("link"), "status": p.get("status")}
                        for p in posts_resp.json()
                    ]

                themes_resp = await client.get(f"{clean_url}/wp-json/wp/v2/themes?status=active", headers=headers)
                active_theme = None
                if themes_resp.status_code == 200 and themes_resp.json():
                    active_theme = themes_resp.json()[0].get("name", {}).get("rendered", "Unknown")

                return {
                    "success": True,
                    "site_title": site_data.get("name", "WordPress Site"),
                    "description": site_data.get("description", ""),
                    "site_url": clean_url,
                    "active_theme": active_theme,
                    "recent_posts": recent_posts,
                    "namespaces": site_data.get("namespaces", []),
                }
        except Exception as exc:
            return {"success": False, "error": f"Không thể kiểm tra website: {str(exc)}"}

    async def list_posts(
        self, site_url: str, username: str, app_password: str, per_page: int = 10, search: Optional[str] = None
    ) -> Dict[str, Any]:
        """Fetch posts from WordPress site."""
        clean_url = site_url.rstrip("/")
        headers = self._get_auth_header(username, app_password)
        params: Dict[str, Any] = {"per_page": min(per_page, 50), "status": "any"}
        if search:
            params["search"] = search

        try:
            async with httpx.AsyncClient(timeout=12.0, follow_redirects=True) as client:
                resp = await client.get(f"{clean_url}/wp-json/wp/v2/posts", headers=headers, params=params)
                if resp.status_code != 200:
                    return {"success": False, "error": f"Lỗi lấy bài viết ({resp.status_code}): {resp.text[:200]}"}
                items = [
                    {"id": p.get("id"), "title": p.get("title", {}).get("rendered"), "status": p.get("status"), "link": p.get("link"), "date": p.get("date")}
                    for p in resp.json()
                ]
                return {"success": True, "count": len(items), "posts": items}
        except Exception as exc:
            return {"success": False, "error": str(exc)}

    async def create_post(
        self, site_url: str, username: str, app_password: str, title: str, content: str, status: str = "publish", excerpt: Optional[str] = None
    ) -> Dict[str, Any]:
        """Create a new WordPress post or draft."""
        clean_url = site_url.rstrip("/")
        headers = self._get_auth_header(username, app_password)
        payload: Dict[str, Any] = {"title": title, "content": content, "status": status}
        if excerpt:
            payload["excerpt"] = excerpt

        try:
            async with httpx.AsyncClient(timeout=20.0, follow_redirects=True) as client:
                resp = await client.post(f"{clean_url}/wp-json/wp/v2/posts", headers=headers, json=payload)
                if resp.status_code in (200, 201):
                    data = resp.json()
                    return {
                        "success": True,
                        "post_id": data.get("id"),
                        "title": data.get("title", {}).get("rendered", title),
                        "status": data.get("status"),
                        "link": data.get("link"),
                        "message": f"Tạo bài viết '{title}' thành công!",
                    }
                return {"success": False, "error": f"Lỗi tạo bài viết ({resp.status_code}): {resp.text[:200]}"}
        except Exception as exc:
            return {"success": False, "error": str(exc)}

    async def update_post(
        self, site_url: str, username: str, app_password: str, post_id: int, title: Optional[str] = None, content: Optional[str] = None, status: Optional[str] = None
    ) -> Dict[str, Any]:
        """Update existing post content, title, or status."""
        clean_url = site_url.rstrip("/")
        headers = self._get_auth_header(username, app_password)
        payload: Dict[str, Any] = {}
        if title:
            payload["title"] = title
        if content:
            payload["content"] = content
        if status:
            payload["status"] = status

        try:
            async with httpx.AsyncClient(timeout=20.0, follow_redirects=True) as client:
                resp = await client.post(f"{clean_url}/wp-json/wp/v2/posts/{post_id}", headers=headers, json=payload)
                if resp.status_code == 200:
                    data = resp.json()
                    return {
                        "success": True,
                        "post_id": post_id,
                        "title": data.get("title", {}).get("rendered"),
                        "link": data.get("link"),
                        "status": data.get("status"),
                        "message": f"Đã cập nhật bài viết #{post_id} thành công!",
                    }
                return {"success": False, "error": f"Lỗi cập nhật ({resp.status_code}): {resp.text[:200]}"}
        except Exception as exc:
            return {"success": False, "error": str(exc)}

    async def manage_plugins(
        self, site_url: str, username: str, app_password: str, action: str = "list", plugin_slug: Optional[str] = None, status: Optional[str] = None
    ) -> Dict[str, Any]:
        """List or activate/deactivate plugins via REST API."""
        clean_url = site_url.rstrip("/")
        headers = self._get_auth_header(username, app_password)

        try:
            async with httpx.AsyncClient(timeout=15.0, follow_redirects=True) as client:
                if action == "list":
                    resp = await client.get(f"{clean_url}/wp-json/wp/v2/plugins", headers=headers)
                    if resp.status_code != 200:
                        return {"success": False, "error": f"Không thể lấy danh sách plugin ({resp.status_code}): {resp.text[:200]}"}
                    plugins = [
                        {"plugin": p.get("plugin"), "name": p.get("name"), "status": p.get("status"), "version": p.get("version")}
                        for p in resp.json()
                    ]
                    return {"success": True, "count": len(plugins), "plugins": plugins}
                elif action in ("activate", "deactivate") and plugin_slug:
                    target_status = "active" if action == "activate" else "inactive"
                    resp = await client.post(f"{clean_url}/wp-json/wp/v2/plugins/{plugin_slug}", headers=headers, json={"status": target_status})
                    if resp.status_code == 200:
                        return {"success": True, "plugin": plugin_slug, "status": target_status, "message": f"Plugin {plugin_slug} đã được chuyển sang trạng thái {target_status}."}
                    return {"success": False, "error": f"Lỗi cập nhật plugin ({resp.status_code}): {resp.text[:200]}"}
                return {"success": False, "error": "Hành động không hợp lệ hoặc thiếu tên plugin."}
        except Exception as exc:
            return {"success": False, "error": str(exc)}

    async def manage_themes(
        self, site_url: str, username: str, app_password: str, action: str = "list", theme_stylesheet: Optional[str] = None
    ) -> Dict[str, Any]:
        """List themes or activate a specific theme."""
        clean_url = site_url.rstrip("/")
        headers = self._get_auth_header(username, app_password)

        try:
            async with httpx.AsyncClient(timeout=15.0, follow_redirects=True) as client:
                if action == "list":
                    resp = await client.get(f"{clean_url}/wp-json/wp/v2/themes", headers=headers)
                    if resp.status_code != 200:
                        return {"success": False, "error": f"Không thể lấy danh sách themes ({resp.status_code}): {resp.text[:200]}"}
                    themes = [
                        {"stylesheet": t.get("stylesheet"), "name": t.get("name", {}).get("rendered"), "status": t.get("status"), "version": t.get("version")}
                        for t in resp.json()
                    ]
                    return {"success": True, "count": len(themes), "themes": themes}
                elif action == "activate" and theme_stylesheet:
                    resp = await client.post(f"{clean_url}/wp-json/wp/v2/themes/{theme_stylesheet}", headers=headers, json={"status": "active"})
                    if resp.status_code == 200:
                        return {"success": True, "theme": theme_stylesheet, "message": f"Theme {theme_stylesheet} đã được kích hoạt thành công!"}
                    return {"success": False, "error": f"Lỗi kích hoạt theme ({resp.status_code}): {resp.text[:200]}"}
                return {"success": False, "error": "Hành động không hợp lệ hoặc thiếu tên stylesheet của theme."}
        except Exception as exc:
            return {"success": False, "error": str(exc)}


wp_external_service = ExternalWordPressService()
