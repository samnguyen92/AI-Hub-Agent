"""SSRF defense utilities preventing agent access to internal or dangerous networks."""

import ipaddress
import socket
from urllib.parse import urlparse


def is_ip_private_or_reserved(ip_str: str) -> bool:
    """Check if an IP string belongs to private, loopback, link-local or reserved ranges."""
    try:
        ip = ipaddress.ip_address(ip_str)
        return (
            ip.is_private
            or ip.is_loopback
            or ip.is_reserved
            or ip.is_link_local
            or ip.is_multicast
            or ip.is_unspecified
        )
    except ValueError:
        return True


def validate_safe_url(url: str) -> str:
    """Validate that the target URL uses http(s) and does not resolve to an internal IP."""
    if not url:
        raise ValueError("URL cannot be empty")

    parsed = urlparse(url)
    if parsed.scheme.lower() not in ("http", "https"):
        raise PermissionError(f"Giao thức '{parsed.scheme}' không được hỗ trợ. Chỉ cho phép http/https.")

    hostname = parsed.hostname
    if not hostname:
        raise PermissionError("Không thể xác định hostname trong URL.")

    # Block well-known internal hostnames
    lowered = hostname.lower()
    if lowered in ("localhost", "127.0.0.1", "::1", "docker", "api", "postgres", "sandbox", "web"):
        raise PermissionError(f"Truy cập vào '{hostname}' bị chặn bởi chính sách SSRF.")

    # Resolve all IPs
    try:
        addr_info = socket.getaddrinfo(hostname, None)
    except socket.gaierror as exc:
        raise PermissionError(f"Không thể phân giải tên miền '{hostname}': {str(exc)}")

    for entry in addr_info:
        ip_str = entry[4][0]
        if is_ip_private_or_reserved(ip_str):
            raise PermissionError(f"SSRF Blocked: Địa chỉ IP '{ip_str}' của '{hostname}' thuộc dải mạng cấm.")

    return url
