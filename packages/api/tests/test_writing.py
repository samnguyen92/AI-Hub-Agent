"""Unit tests for writing standards and presentation helpers."""

import pytest
from app.standards.writing import format_sitemap_as_table, get_writing_system_prompt


def test_get_writing_system_prompt() -> None:
    """Verify that writing standards prompt is non-empty and contains essential guidelines."""
    prompt = get_writing_system_prompt()
    assert "ALIGN WRITING & PRESENTATION STANDARDS" in prompt
    assert "NO RAW JSON DUMPING" in prompt
    assert "CONVERSATIONAL & REFINED TONE" in prompt


def test_format_sitemap_as_table_vi() -> None:
    """Verify conversion of sitemap pages into a clean markdown table in Vietnamese."""
    pages = [
        {"path": "/", "title": "Trang chủ", "description": "Trang chính Petking"},
        {"path": "/gio-hang", "title": "Giỏ hàng", "description": "Quản lý đơn hàng"},
    ]
    table = format_sitemap_as_table(pages, lang="vi")
    assert "| Đường dẫn | Tiêu đề | Mục đích / Nội dung chính |" in table
    assert "| `/` | **Trang chủ** | Trang chính Petking |" in table
    assert "| `/gio-hang` | **Giỏ hàng** | Quản lý đơn hàng |" in table


def test_format_sitemap_as_table_en() -> None:
    """Verify conversion of sitemap pages into a clean markdown table in English."""
    pages = [
        {"path": "/about", "title": "About Us", "description": "Company history"},
    ]
    table = format_sitemap_as_table(pages, lang="en")
    assert "| Path | Page Title | Description / Purpose |" in table
    assert "| `/about` | **About Us** | Company history |" in table


def test_format_sitemap_empty() -> None:
    """Verify empty list handling."""
    assert format_sitemap_as_table([]) == ""
