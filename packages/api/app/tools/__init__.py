"""Tool package initialization and tool registration."""

def register_all_tools() -> None:
    """Import all tool modules so each registers its tools with tool_registry."""
    import app.tools.library_tools  # noqa: F401
    import app.tools.file_tools  # noqa: F401
    import app.tools.render_tools  # noqa: F401
    import app.tools.image_tools  # noqa: F401
    import app.tools.skill_tools  # noqa: F401
    import app.tools.web_tools  # noqa: F401
    import app.tools.seo_tools  # noqa: F401
    import app.tools.design_tools  # noqa: F401
    import app.tools.gate_tools  # noqa: F401
    import app.tools.report_tools  # noqa: F401
    import app.tools.memory_tools  # noqa: F401
    import app.tools.reference_tools  # noqa: F401
    import app.tools.reference_proposal_tools  # noqa: F401
    import app.tools.design_package_tools  # noqa: F401
    import app.tools.vision_tools  # noqa: F401
    import app.tools.quality_review_tools  # noqa: F401
    import app.tools.chat_attachment_tools  # noqa: F401
    import app.tools.wp_tools  # noqa: F401
