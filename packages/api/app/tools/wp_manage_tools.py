"""WordPress management tools for checking website, managing posts, themes, and plugins."""

import json
from typing import Any, Dict, Optional
from app.services.wp_external import wp_external_service
from app.services.wp_runner import wp_runner
from app.tools.base import BaseTool, ToolContext, tool_registry


class WPCheckSiteTool(BaseTool):
    """Tool to inspect connected WordPress website details."""

    name = "wp_check_site"
    description = (
        "Kiểm tra website WordPress đã liên kết của dự án: phiên bản, theme đang hoạt động, "
        "danh sách bài viết gần nhất và trạng thái kết nối."
    )
    parameters_schema = {
        "type": "object",
        "properties": {},
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site:
            return {
                "success": False,
                "error": "Dự án chưa liên kết website WordPress. Hãy vào mục Cài đặt dự án -> WordPress để kết nối.",
            }

        if site.site_type == "external":
            return await wp_external_service.check_site(
                site.site_url, site.admin_user, site.admin_password
            )

        code, out, _ = await wp_runner.run_wp_cli(site.container_name, ["core", "version"])
        theme_code, theme_out, _ = await wp_runner.run_wp_cli(
            site.container_name, ["theme", "list", "--status=active", "--format=json"]
        )
        active_theme = None
        if theme_code == 0:
            try:
                active_theme = json.loads(theme_out)[0].get("name")
            except Exception:
                active_theme = "align"

        return {
            "success": True,
            "site_type": "internal",
            "site_url": site.site_url,
            "wp_version": out.strip() if code == 0 else "Unknown",
            "active_theme": active_theme,
            "status": site.status,
        }


class WPManagePostsTool(BaseTool):
    """Tool to create, update, or list WordPress blog posts."""

    name = "wp_manage_posts"
    description = (
        "Quản lý bài viết/blog trên WordPress: tạo bài viết mới (post/blog), cập nhật nội dung, "
        "hoặc xem danh sách bài viết hiện có."
    )
    parameters_schema = {
        "type": "object",
        "properties": {
            "action": {
                "type": "string",
                "enum": ["create", "update", "list"],
                "description": "Hành động: 'create' (tạo mới), 'update' (cập nhật), 'list' (danh sách)",
                "default": "create",
            },
            "title": {"type": "string", "description": "Tiêu đề bài viết"},
            "content": {"type": "string", "description": "Nội dung bài viết (văn bản hoặc HTML)"},
            "status": {
                "type": "string",
                "enum": ["publish", "draft"],
                "description": "Trạng thái: 'publish' (đăng ngay) hoặc 'draft' (bản nháp)",
                "default": "publish",
            },
            "post_id": {"type": "integer", "description": "ID bài viết cần cập nhật (bắt buộc khi action='update')"},
            "search": {"type": "string", "description": "Từ khóa tìm kiếm khi action='list'"},
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site:
            return {"success": False, "error": "Chưa có website WordPress nào được liên kết với dự án."}

        action = kwargs.get("action", "create")
        if site.site_type == "external":
            if action == "create":
                return await wp_external_service.create_post(
                    site.site_url, site.admin_user, site.admin_password,
                    title=kwargs.get("title", "Bài viết mới"),
                    content=kwargs.get("content", ""),
                    status=kwargs.get("status", "publish"),
                )
            elif action == "update":
                post_id = kwargs.get("post_id")
                if not post_id:
                    return {"success": False, "error": "Thiếu post_id để cập nhật bài viết."}
                return await wp_external_service.update_post(
                    site.site_url, site.admin_user, site.admin_password,
                    post_id=int(post_id),
                    title=kwargs.get("title"),
                    content=kwargs.get("content"),
                    status=kwargs.get("status"),
                )
            elif action == "list":
                return await wp_external_service.list_posts(
                    site.site_url, site.admin_user, site.admin_password,
                    search=kwargs.get("search"),
                )

        # Internal container handling
        if action == "create":
            args = ["post", "create", f"--post_title={kwargs.get('title', 'Bài viết')}", f"--post_content={kwargs.get('content', '')}", f"--post_status={kwargs.get('status', 'publish')}", "--porcelain"]
            code, out, err = await wp_runner.run_wp_cli(site.container_name, args)
            if code != 0:
                return {"success": False, "error": err or out}
            return {"success": True, "post_id": int(out.strip()), "message": f"Tạo bài viết thành công với ID #{out.strip()}"}
        elif action == "list":
            code, out, err = await wp_runner.run_wp_cli(site.container_name, ["post", "list", "--format=json"])
            if code != 0:
                return {"success": False, "error": err or out}
            return {"success": True, "posts": json.loads(out)}

        return {"success": False, "error": f"Hành động {action} chưa được hỗ trợ trên môi trường nội bộ."}


class WPManagePluginsTool(BaseTool):
    """Tool to inspect and toggle WordPress plugins."""

    name = "wp_manage_plugins"
    description = "Quản lý plugin WordPress: xem danh sách plugin, kích hoạt (activate) hoặc vô hiệu hóa (deactivate) plugin."
    parameters_schema = {
        "type": "object",
        "properties": {
            "action": {
                "type": "string",
                "enum": ["list", "activate", "deactivate"],
                "description": "Hành động với plugin",
                "default": "list",
            },
            "plugin_slug": {"type": "string", "description": "Tên định danh plugin (ví dụ: contact-form-7)"},
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site:
            return {"success": False, "error": "Chưa có website WordPress nào được liên kết với dự án."}

        action = kwargs.get("action", "list")
        plugin_slug = kwargs.get("plugin_slug")

        if site.site_type == "external":
            return await wp_external_service.manage_plugins(
                site.site_url, site.admin_user, site.admin_password,
                action=action, plugin_slug=plugin_slug
            )

        if action == "list":
            code, out, err = await wp_runner.run_wp_cli(site.container_name, ["plugin", "list", "--format=json"])
            if code != 0:
                return {"success": False, "error": err or out}
            return {"success": True, "plugins": json.loads(out)}
        elif action in ("activate", "deactivate") and plugin_slug:
            code, out, err = await wp_runner.run_wp_cli(site.container_name, ["plugin", action, plugin_slug])
            if code != 0:
                return {"success": False, "error": err or out}
            return {"success": True, "message": out.strip()}

        return {"success": False, "error": "Cần cung cấp plugin_slug để kích hoạt hoặc tắt."}


class WPManageThemesTool(BaseTool):
    """Tool to view installed themes and switch active theme."""

    name = "wp_manage_themes"
    description = "Quản lý theme WordPress: xem danh sách theme đã cài đặt và kích hoạt theme mong muốn."
    parameters_schema = {
        "type": "object",
        "properties": {
            "action": {
                "type": "string",
                "enum": ["list", "activate"],
                "description": "Hành động với theme",
                "default": "list",
            },
            "theme_stylesheet": {"type": "string", "description": "Tên stylesheet của theme cần kích hoạt (vd: astra, align)"},
        },
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        site = await wp_runner.get_site(context.project_id, context.db)
        if not site:
            return {"success": False, "error": "Chưa có website WordPress nào được liên kết với dự án."}

        action = kwargs.get("action", "list")
        stylesheet = kwargs.get("theme_stylesheet")

        if site.site_type == "external":
            return await wp_external_service.manage_themes(
                site.site_url, site.admin_user, site.admin_password,
                action=action, theme_stylesheet=stylesheet
            )

        if action == "list":
            code, out, err = await wp_runner.run_wp_cli(site.container_name, ["theme", "list", "--format=json"])
            if code != 0:
                return {"success": False, "error": err or out}
            return {"success": True, "themes": json.loads(out)}
        elif action == "activate" and stylesheet:
            code, out, err = await wp_runner.run_wp_cli(site.container_name, ["theme", "activate", stylesheet])
            if code != 0:
                return {"success": False, "error": err or out}
            return {"success": True, "message": out.strip()}

        return {"success": False, "error": "Cần cung cấp theme_stylesheet để kích hoạt theme."}


# Register all tools into global registry
tool_registry.register(WPCheckSiteTool())
tool_registry.register(WPManagePostsTool())
tool_registry.register(WPManagePluginsTool())
tool_registry.register(WPManageThemesTool())
