"""WordPress lifecycle, import, content, and All-in-One WP Migration backup tools."""

import os
from typing import Any, Dict, Optional

from app.config import settings
from app.services.wp_backup import wp_backup
from app.services.wp_importer import wp_importer
from app.services.wp_runner import wp_runner
from app.tools.base import BaseTool, ToolContext, tool_registry


class WPCreateEnvironmentTool(BaseTool):
    """Tool to provision WordPress container environment."""

    name = "wp_create_environment"
    description = (
        "Khởi tạo môi trường WordPress container riêng biệt với Theme Align, "
        "plugin Align-Agent, All-in-One WP Migration, và Fluent Forms."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "site_title": {
                "type": "string",
                "description": "Tiêu đề của website WordPress",
                "default": "Align WordPress Site",
            },
            "subdomain": {
                "type": "string",
                "description": "Subdomain mong muốn cho website (ví dụ: cafe, shop, portfolio). Mặc định tự sinh từ project.",
            },
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site_title = kwargs.get("site_title", "Align WordPress Site")
        subdomain = kwargs.get("subdomain")
        return await wp_runner.create_site(
            context.project_id, site_title, context.db, subdomain=subdomain
        )


class WPImportHtmlPackageTool(BaseTool):
    """Tool to import HTML zip package into Align theme."""

    name = "wp_import_html_package"
    description = (
        "Import file zip mã nguồn HTML (site-package.zip) vào theme Align, "
        "tự động chuyển đổi thành template và trang WordPress động."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "zip_path": {
                "type": "string",
                "description": "Đường dẫn file ZIP trên workspace (mặc định tìm site-package.zip)",
            },
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site or site.status != "running":
            return {"success": False, "error": "Môi trường WordPress chưa được tạo hoặc đang dừng."}

        target_zip = kwargs.get("zip_path")
        if not target_zip:
            default_zip = os.path.join(context.workspace_path, "site-package.zip")
            alt_zip = os.path.join(settings.workspaces_root, context.project_id, "site-package.zip")
            target_zip = default_zip if os.path.exists(default_zip) else alt_zip

        if not os.path.exists(target_zip):
            return {"success": False, "error": f"Không tìm thấy gói ZIP tại {target_zip}"}

        return await wp_importer.import_zip_package(site.container_name, target_zip)


class WPTuneSeoAndMetaTool(BaseTool):
    """Tool to update SEO meta fields for a WordPress page or post."""

    name = "wp_tune_seo_and_meta"
    description = "Cập nhật thẻ SEO Title, Meta Description và Keywords cho bài viết hoặc trang WordPress."
    parameters_schema = {
        "type": "object",
        "properties": {
            "post_id": {"type": "integer", "description": "ID bài viết hoặc trang cần cập nhật"},
            "seo_title": {"type": "string", "description": "Tiêu đề SEO mới"},
            "meta_description": {"type": "string", "description": "Thẻ mô tả meta SEO"},
            "keywords": {"type": "string", "description": "Từ khoá SEO"},
        },
        "required": ["post_id"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site:
            return {"success": False, "error": "Không tìm thấy môi trường WordPress."}

        post_id = kwargs["post_id"]
        seo_title = kwargs.get("seo_title")
        meta_desc = kwargs.get("meta_description")
        keywords = kwargs.get("keywords")

        if seo_title:
            await wp_runner.run_wp_cli(site.container_name, ["post", "meta", "update", str(post_id), "_align_seo_title", seo_title])
            await wp_runner.run_wp_cli(site.container_name, ["post", "update", str(post_id), f"--post_title={seo_title}"])
        if meta_desc:
            await wp_runner.run_wp_cli(site.container_name, ["post", "meta", "update", str(post_id), "_align_seo_description", meta_desc])
        if keywords:
            await wp_runner.run_wp_cli(site.container_name, ["post", "meta", "update", str(post_id), "_align_seo_keywords", keywords])

        return {
            "success": True,
            "post_id": post_id,
            "updated": {"seo_title": seo_title, "meta_description": meta_desc, "keywords": keywords},
        }


class WPEditContentTool(BaseTool):
    """Tool to update page or post content in WordPress."""

    name = "wp_edit_content"
    description = "Chỉnh sửa nội dung văn bản hoặc HTML của bài viết/trang WordPress qua ID."
    parameters_schema = {
        "type": "object",
        "properties": {
            "post_id": {"type": "integer", "description": "ID bài viết hoặc trang"},
            "post_title": {"type": "string", "description": "Tiêu đề mới (nếu muốn đổi)"},
            "post_content": {"type": "string", "description": "Nội dung HTML/văn bản mới"},
        },
        "required": ["post_id"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site:
            return {"success": False, "error": "Không tìm thấy môi trường WordPress."}

        post_id = kwargs["post_id"]
        args = ["post", "update", str(post_id)]
        if kwargs.get("post_title"):
            args.append(f"--post_title={kwargs['post_title']}")
        if kwargs.get("post_content"):
            args.append(f"--post_content={kwargs['post_content']}")

        code, out, err = await wp_runner.run_wp_cli(site.container_name, args)
        if code != 0:
            return {"success": False, "error": err or out}
        return {"success": True, "post_id": post_id, "message": out.strip()}


class WPPatchTemplateTool(BaseTool):
    """Tool to write or patch a child theme template file."""

    name = "wp_patch_template"
    description = "Chỉnh sửa hoặc ghi đè file template/CSS bên trong Align Child Theme (ví dụ style.css)."
    parameters_schema = {
        "type": "object",
        "properties": {
            "relative_path": {"type": "string", "description": "Đường dẫn file tương đối trong child theme (vd: style.css)"},
            "content": {"type": "string", "description": "Nội dung đầy đủ của file"},
        },
        "required": ["relative_path", "content"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rel_path = kwargs["relative_path"].strip().lstrip("/")
        if ".." in rel_path:
            return {"success": False, "error": "Đường dẫn không hợp lệ."}

        theme_dir = os.path.join(
            settings.workspaces_root, context.project_id, "wordpress", "wp-content", "themes", "align-child"
        )
        dest_path = os.path.join(theme_dir, rel_path)
        os.makedirs(os.path.dirname(dest_path), exist_ok=True)
        with open(dest_path, "w", encoding="utf-8") as f:
            f.write(kwargs["content"])

        return {"success": True, "file": rel_path, "size_bytes": len(kwargs["content"])}


class WPGenerateAiowpBackupTool(BaseTool):
    """Tool to trigger All-in-One WP Migration backup and return download link."""

    name = "wp_generate_aiowp_backup"
    description = (
        "Kích hoạt All-in-One WP Migration để xuất bản sao lưu trọn gói (.wpress) "
        "chứa toàn bộ Database, Theme, Plugin, Media để migrate lên hosting khác."
    )
    parameters_schema = {
        "type": "object",
        "properties": {},
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site:
            return {"success": False, "error": "Không tìm thấy môi trường WordPress."}

        res = await wp_backup.create_backup(site.container_name, context.project_id)
        if res.get("success"):
            site.last_backup_path = res.get("file_path")
            context.db.add(site)
            await context.db.commit()
        return res


# Register all tools into global registry
tool_registry.register(WPCreateEnvironmentTool())
tool_registry.register(WPImportHtmlPackageTool())
tool_registry.register(WPTuneSeoAndMetaTool())
tool_registry.register(WPEditContentTool())
tool_registry.register(WPPatchTemplateTool())
tool_registry.register(WPGenerateAiowpBackupTool())
