"""SSRF protection utility to prevent connections to internal/private IP ranges."""

import ipaddress
import os
import socket
from typing import Tuple
from urllib.parse import urlsplit


def is_safe_public_url(url: str, allow_private: bool = False) -> Tuple[bool, str]:
    """Validate that a URL is a legitimate public web destination and not an internal IP."""
    if allow_private or os.getenv("ALLOW_PRIVATE_URLS", "").lower() in ("true", "1"):
        return True, ""

    try:
        parsed = urlsplit(url)
    except Exception as exc:
        return False, f"URL không hợp lệ: {str(exc)}"

    if parsed.scheme not in ("http", "https"):
        return False, f"Giao thức '{parsed.scheme}' không được hỗ trợ. Chỉ cho phép HTTP/HTTPS."

    hostname = parsed.hostname
    if not hostname:
        return False, "Thiếu hostname trong URL."

    # Block localhost literals immediately
    if hostname.lower() in ("localhost", "localhost.localdomain", "broadcasthost"):
        return False, "Không cho phép kết nối tới localhost."

    # Check port if specified
    if parsed.port and parsed.port in (22, 25, 110, 143, 3306, 5432, 6379, 9200, 27017):
        return False, f"Cổng {parsed.port} bị chặn vì lý do an ninh."

    try:
        # Resolve all IPs for hostname
        addr_info = socket.getaddrinfo(hostname, None)
        if not addr_info:
            return False, f"Không thể phân giải tên miền: {hostname}"

        for entry in addr_info:
            ip_str = entry[4][0]
            ip = ipaddress.ip_address(ip_str)

            if (
                ip.is_loopback
                or ip.is_private
                or ip.is_link_local
                or ip.is_multicast
                or ip.is_reserved
                or ip.is_unspecified
                or str(ip).startswith("169.254.")  # AWS metadata
                or str(ip).startswith("100.64.")  # Carrier-grade NAT
            ):
                return False, f"Địa chỉ IP ({ip_str}) thuộc dải mạng nội bộ hoặc bị hạn chế (Chặn SSRF)."
    except socket.gaierror:
        return False, f"Lỗi phân giải tên miền: {hostname}"
    except Exception as exc:
        return False, f"Lỗi xác thực địa chỉ máy chủ: {str(exc)}"

    return True, ""
