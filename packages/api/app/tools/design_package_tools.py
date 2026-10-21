"""Package a complete multi-page design site and its local assets into a ZIP."""

import os
import re
import zipfile
from typing import Any, Dict, List, Optional
from urllib.parse import quote, unquote, urljoin, urlparse
from xml.etree import ElementTree as ET

from app.services.workspace import workspace_service
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path


class PackageDesignTool(BaseTool):
    """Validate a site's entry/sitemap pages, then archive its complete output folder."""

    max_archive_bytes = 100 * 1024 * 1024
    max_site_files = 200
    ignored_directories = {".git", "node_modules", "__pycache__", ".next", "exports"}

    name = "package_design"
    description = (
        "Đóng gói toàn bộ thư mục website nhiều trang thành ZIP, gồm HTML, sitemap, "
        "CSS/JS, hình ảnh và các asset local; không đóng gói workspace."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "site_root": {"type": "string", "description": "Thư mục gốc chỉ chứa các file của website."},
            "html_path": {"type": "string", "description": "Đường dẫn trang chủ/entry HTML."},
            "sitemap_path": {"type": "string", "description": "Đường dẫn sitemap.html có liên kết tới mọi trang."},
            "archive_name": {"type": "string", "description": "Tên ZIP đầu ra, không cần đuôi .zip."},
            "site_url": {"type": "string", "description": "Domain chính thức để tạo sitemap.xml chuẩn URL tuyệt đối."},
        },
        "required": ["site_root", "html_path", "sitemap_path"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        """Package every safe file in the site folder and return its page inventory."""
        try:
            site_root = self._resolve_site_root(context, kwargs["site_root"])
            entry_path = self._resolve_site_file(context, site_root, kwargs["html_path"])
            sitemap_path = self._resolve_site_file(context, site_root, kwargs["sitemap_path"])
            if not entry_path.lower().endswith(".html") or not sitemap_path.lower().endswith(".html"):
                raise ValueError("Trang chủ và sitemap_path phải là file HTML.")
            files = self._collect_site_files(site_root)
            self._validate_sitemap(site_root, sitemap_path, entry_path, files)
            html_pages = [path for path in files if path.lower().endswith(".html")]
            xml_sitemap = self._write_xml_sitemap(site_root, kwargs.get("site_url"), html_pages)
            if xml_sitemap:
                files = self._collect_site_files(site_root)
            archive_name = self._archive_name(str(kwargs.get("archive_name") or entry_path))
            archive_rel = f"exports/{archive_name}"
            archive_full = safe_resolve_path(context.workspace_path, archive_rel)
            os.makedirs(os.path.dirname(archive_full), exist_ok=True)
            self._write_archive(archive_full, site_root, files)
            commit = workspace_service.commit_step(context.project_id, f"design: package {archive_name}")
        except (KeyError, OSError, ValueError, zipfile.BadZipFile) as exc:
            return {"error": f"Không thể đóng gói website: {exc}"}
        site_files = [os.path.relpath(path, site_root).replace(os.sep, "/") for path in files]
        page_paths = [
            os.path.relpath(path, context.workspace_path).replace(os.sep, "/")
            for path in files
            if path.lower().endswith(".html") and os.path.realpath(path) != os.path.realpath(sitemap_path)
        ]
        workspace_sitemap = (
            os.path.relpath(sitemap_path, context.workspace_path).replace(os.sep, "/")
        )
        return {
            "status": "packaged",
            "html_file_path": kwargs["html_path"],
            "html_file_url": self._file_url(context.project_id, kwargs["html_path"]),
            "page_file_paths": page_paths,
            "page_file_urls": [
                {"path": path, "url": self._file_url(context.project_id, path)}
                for path in page_paths
            ],
            "sitemap_file_path": workspace_sitemap,
            "sitemap_file_url": self._file_url(context.project_id, workspace_sitemap),
            "sitemap_xml_path": (
                os.path.relpath(xml_sitemap, context.workspace_path).replace(os.sep, "/")
                if xml_sitemap else None
            ),
            "zip_file_path": archive_rel,
            "zip_download_url": self._file_url(context.project_id, archive_rel),
            "included_files": site_files,
            "git_commit": commit,
        }

    @staticmethod
    def _resolve_site_root(context: ToolContext, relative_path: str) -> str:
        """Require a dedicated child directory for website output."""
        site_root = safe_resolve_path(context.workspace_path, str(relative_path))
        workspace = os.path.realpath(context.workspace_path)
        if site_root == workspace or not os.path.isdir(site_root):
            raise ValueError("site_root phải là thư mục con riêng trong workspace.")
        return site_root

    @staticmethod
    def _resolve_site_file(context: ToolContext, site_root: str, relative_path: str) -> str:
        """Resolve a required HTML file and ensure it belongs to the site folder."""
        full_path = safe_resolve_path(context.workspace_path, str(relative_path))
        if os.path.commonpath((site_root, full_path)) != site_root or not os.path.isfile(full_path):
            raise ValueError(f"File không tồn tại trong site_root: {relative_path}")
        return full_path

    @classmethod
    def _collect_site_files(cls, site_root: str) -> List[str]:
        """Collect bounded website files, excluding secrets, caches and VCS data."""
        files: List[str] = []
        total_size = 0
        for root, directories, filenames in os.walk(site_root):
            directories[:] = [
                name for name in directories
                if name not in cls.ignored_directories and not name.startswith(".")
            ]
            for filename in filenames:
                if filename.startswith(".") or filename.lower().endswith((".env", ".pyc")):
                    continue
                path = os.path.join(root, filename)
                total_size += os.path.getsize(path)
                files.append(path)
                if len(files) > cls.max_site_files or total_size > cls.max_archive_bytes:
                    raise ValueError("Website vượt giới hạn 200 file hoặc 100 MB.")
        if not any(path.lower().endswith(".html") for path in files):
            raise ValueError("Thư mục site chưa có trang HTML.")
        return files

    @staticmethod
    def _validate_sitemap(site_root: str, sitemap_path: str, entry_path: str, files: List[str]) -> None:
        """Require the sitemap HTML to link to every generated page, including Home."""
        if entry_path not in files or sitemap_path not in files:
            raise ValueError("Trang chủ và sitemap phải nằm trong tập file được đóng gói.")
        with open(sitemap_path, "r", encoding="utf-8") as sitemap_file:
            html = sitemap_file.read()
        hrefs = re.findall(r"href\s*=\s*['\"]([^'\"]+)['\"]", html, flags=re.IGNORECASE)
        linked_pages = set()
        for href in hrefs:
            if href.startswith(("#", "http://", "https://", "mailto:", "tel:", "javascript:")):
                continue
            relative = unquote(href.split("#", 1)[0].split("?", 1)[0])
            target = os.path.realpath(os.path.join(os.path.dirname(sitemap_path), relative))
            if target in files and target.lower().endswith(".html"):
                linked_pages.add(target)
        html_pages = {path for path in files if path.lower().endswith(".html")}
        html_pages.discard(sitemap_path)
        missing = sorted(os.path.relpath(path, site_root) for path in html_pages - linked_pages)
        if missing:
            raise ValueError(f"sitemap.html chưa liên kết tới: {', '.join(missing)}")

    @staticmethod
    def _write_xml_sitemap(site_root: str, site_url: Any, pages: List[str]) -> Optional[str]:
        """Create a standards-based XML sitemap only when a real site origin is supplied."""
        if not site_url:
            return None
        parsed = urlparse(str(site_url).strip())
        if (
            parsed.scheme not in {"http", "https"}
            or not parsed.netloc
            or parsed.username
            or parsed.password
            or parsed.query
            or parsed.fragment
        ):
            raise ValueError("site_url phải là URL tuyệt đối HTTP(S).")
        namespace = "http://www.sitemaps.org/schemas/sitemap/0.9"
        ET.register_namespace("", namespace)
        root = ET.Element(f"{{{namespace}}}urlset")
        base_url = str(site_url).rstrip("/") + "/"
        for page in pages:
            relative = os.path.relpath(page, site_root).replace(os.sep, "/")
            url_node = ET.SubElement(root, f"{{{namespace}}}url")
            ET.SubElement(url_node, f"{{{namespace}}}loc").text = urljoin(base_url, relative)
        output_path = os.path.join(site_root, "sitemap.xml")
        ET.ElementTree(root).write(output_path, encoding="utf-8", xml_declaration=True)
        return output_path

    @staticmethod
    def _archive_name(raw_name: str) -> str:
        """Reduce an archive name to a safe single filename."""
        stem = os.path.splitext(os.path.basename(raw_name))[0]
        clean = re.sub(r"[^A-Za-z0-9._-]+", "-", stem).strip(".-_")[:100]
        return f"{clean or 'design-site'}.zip"

    @staticmethod
    def _file_url(project_id: str, relative_path: str) -> str:
        """Build a browser URL whose path preserves relative links inside HTML."""
        encoded_path = quote(relative_path, safe="/")
        return f"/api/backend/projects/{project_id}/files/{encoded_path}"

    @staticmethod
    def _write_archive(archive_path: str, site_root: str, files: List[str]) -> None:
        """Keep links valid by storing every file relative to the site root."""
        with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
            for full_path in files:
                archive.write(full_path, os.path.relpath(full_path, site_root))


tool_registry.register(PackageDesignTool())
