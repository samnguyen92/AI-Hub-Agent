"""Service for communicating with external WordPress sites via REST API."""

import base64
from typing import Any, Dict, Optional
import httpx


class ExternalWordPressService:
    """Handles REST API communication with external self-hosted or managed WordPress sites."""

    @staticmethod
    def _get_auth_header(username: str, app_password: str) -> Dict[str, str]:
        """Generate HTTP Basic Auth header for WordPress Application Password."""
        # Application passwords may have spaces which should be cleaned
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
                    return {
                        "success": False,
                        "error": "Xác thực thất bại (401): Username hoặc Application Password không đúng.",
                    }
                elif resp.status_code == 404:
                    return {
                        "success": False,
                        "error": "Không tìm thấy WordPress REST API (404) tại đường dẫn này.",
                    }
                else:
                    return {
                        "success": False,
                        "error": f"WordPress phản hồi mã lỗi {resp.status_code}: {resp.text[:200]}",
                    }
        except httpx.ConnectError:
            return {
                "success": False,
                "error": f"Không thể kết nối tới máy chủ WordPress tại {clean_url}. Vui lòng kiểm tra lại URL.",
            }
        except Exception as exc:
            return {"success": False, "error": f"Lỗi kết nối: {str(exc)}"}


wp_external_service = ExternalWordPressService()
