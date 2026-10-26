"""Comprehensive tests for WordPress Runner, Importer, Backup, and Tool Registry."""

import os
import shutil
import tempfile
import pytest
from app.services.router_agent import router_agent
from app.services.wp_backup import wp_backup
from app.services.wp_importer import wp_importer
from app.services.wp_runner import wp_runner
from app.tools.base import tool_registry
from app.tools.wp_tools import WPPatchTemplateTool


def test_wp_runner_slug_and_port():
    """Verify project ID sanitization and port discovery."""
    slug = wp_runner._sanitize_slug("Project-Alpha-123_Test#XYZ!")
    assert slug == "project-alpha-123_test-x"
    assert len(slug) <= 24

    sub_slug = wp_runner._sanitize_slug("cafe-quan-1")
    assert sub_slug == "cafe-quan-1"

    port = wp_runner.find_available_port(8501)
    assert 8501 <= port < 8700

    from app.config import settings
    orig_base = settings.wp_base_domain
    try:
        settings.wp_base_domain = "alignvn.cloud"
        assert wp_runner.format_site_url("vietthanh") == "https://demo-vietthanh.alignvn.cloud"
        settings.wp_base_domain = "demo.alignvn.cloud"
        assert wp_runner.format_site_url("vietthanh") == "https://demo-vietthanh.alignvn.cloud"
        settings.wp_base_domain = ""
        assert wp_runner.format_site_url("vietthanh") == "http://vietthanh.localhost:8500"
    finally:
        settings.wp_base_domain = orig_base


def test_wp_runner_prepare_vault_assets():
    """Verify that themes and plugins are correctly staged from vault."""
    with tempfile.TemporaryDirectory() as temp_dir:
        wp_runner.prepare_vault_assets(temp_dir)
        themes_dir = os.path.join(temp_dir, "themes")
        plugins_dir = os.path.join(temp_dir, "plugins")

        assert os.path.isdir(os.path.join(themes_dir, "align"))
        assert os.path.isdir(os.path.join(themes_dir, "align-child"))
        assert os.path.isdir(os.path.join(plugins_dir, "align-agent"))
        assert os.path.isdir(os.path.join(plugins_dir, "all-in-one-wp-migration"))
        assert os.path.isdir(os.path.join(plugins_dir, "fluentform"))


def test_wp_importer_script_generation():
    """Verify generated PHP importer script contains Align batch runner logic."""
    php_code = wp_importer._generate_import_php_script()
    assert "Align_Zip_Batch_Runner::init_session" in php_code
    assert "Align_Zip_Batch_Runner::process_parts_and_blocks" in php_code
    assert "Align_Zip_Batch_Runner::process_pages_batch" in php_code
    assert "Align_Zip_Batch_Runner::finalize_session" in php_code


@pytest.mark.asyncio
async def test_wp_importer_missing_file():
    """Ensure nonexistent ZIP path returns failure gracefully."""
    res = await wp_importer.import_zip_package("dummy_container", "/nonexistent/test.zip")
    assert res["success"] is False
    assert "not found" in res["error"].lower()


@pytest.mark.asyncio
async def test_router_agent_wordpress_builder_routing():
    """Verify explicit and keyword dispatch to wordpress-builder agent."""
    dec_explicit = await router_agent.classify("@wp dựng web giúp tôi")
    assert dec_explicit.agent == "wordpress-builder"

    dec_create = await router_agent.classify("Hãy tạo wordpress và cài đặt theme align")
    assert dec_create.agent == "wordpress-builder"

    dec_backup = await router_agent.classify("Site này đẹp rồi, hãy backup wordpress ra file all-in-one cho tôi")
    assert dec_backup.agent == "wordpress-builder"

    dec_context = await router_agent.classify(
        "okay tiến hành cho tôi",
        recent_history=[{"role": "assistant", "content": "Bạn muốn tạo môi trường WordPress chứ?"}],
    )
    assert dec_context.agent == "wordpress-builder"


def test_tool_registry_wordpress_builder():
    """Verify that all WordPress tools are properly registered and allowed."""
    expected_tools = [
        "wp_create_environment",
        "wp_import_html_package",
        "wp_tune_seo_and_meta",
        "wp_edit_content",
        "wp_patch_template",
        "wp_generate_aiowp_backup",
    ]
    for tool_name in expected_tools:
        tool = tool_registry.get(tool_name)
        assert tool is not None, f"Tool {tool_name} not registered in tool_registry"

    agent_tools = tool_registry.get_tools_for_agent("wordpress-builder")
    agent_tool_names = [t.name for t in agent_tools]
    for tool_name in expected_tools:
        assert tool_name in agent_tool_names


@pytest.mark.asyncio
async def test_wp_patch_template_traversal_guard():
    """Verify that path traversal attempts in WPPatchTemplateTool are rejected."""
    tool = WPPatchTemplateTool()

    class MockContext:
        project_id = "test_proj"

    res = await tool.execute(MockContext(), relative_path="../../evil.php", content="<?php evil(); ?>")
    assert res["success"] is False
    assert "không hợp lệ" in res["error"]
