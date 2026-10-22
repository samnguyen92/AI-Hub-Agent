"""Tool framework base abstractions and registry."""

from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Any, Dict, List, Optional
from sqlalchemy.ext.asyncio import AsyncSession


@dataclass
class ToolContext:
    """Runtime context passed into tool executions."""

    project_id: str
    run_id: str
    workspace_path: str
    db: AsyncSession
    agent_key: str
    image_model: Optional[str] = None


class BaseTool(ABC):
    """Base class for all executable agent tools."""

    name: str
    description: str
    parameters_schema: Dict[str, Any]

    @abstractmethod
    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        """Execute tool logic and return JSON-serializable dictionary."""
        pass

    def to_openai_schema(self) -> Dict[str, Any]:
        """Convert tool descriptor into OpenAI Function Calling format."""
        return {
            "type": "function",
            "function": {
                "name": self.name,
                "description": self.description,
                "parameters": self.parameters_schema,
            },
        }


class ToolRegistry:
    """Central registry holding all tools and enforcing agent permissions."""

    def __init__(self) -> None:
        self._tools: Dict[str, BaseTool] = {}
        # Mapping of allowed tools per agent
        self._agent_permissions: Dict[str, List[str]] = {
            "router": [],  # Router has no action tools
            "assistant": [
                "list_library",
                "read_item",
                "list_files",
                "read_file",
                "write_file",
                "generate_image",
                "propose_note",
                "list_notes",
                "learn_rule",
                "load_skill",
                "request_input",
            ],
            "design": [
                "list_library",
                "read_item",
                "save_artifact",
                "read_file",
                "write_file",
                "list_files",
                "generate_image",
                "view_image",
                "render_html_to_png",
                "take_screenshot",
                "capture_reference",
                "inspect_screenshots",
                "package_design",
                "fetch_url",
                "load_skill",
                "request_input",
                "export_zip",
                "propose_note",
                "list_notes",
                "learn_rule",
            ],
            "code-builder": [
                "list_library",
                "read_item",
                "save_artifact",
                "read_file",
                "write_file",
                "list_files",
                "apply_patch",
                "generate_image",
                "render_html_to_png",
                "run_checks",
                "compare_screenshots",
                "inspect_screenshots",
                "load_skill",
                "request_input",
                "export_zip",
                "propose_note",
                "list_notes",
                "learn_rule",
                "audit_web_design",
                "run_quality_review",
                "open_gate",
            ],
            "site-analyzer": [
                "list_library",
                "read_item",
                "save_artifact",
                "take_screenshot",
                "audit_web_design",
                "fetch_url",
                "crawl_site",
                "check_sitemap",
                "run_lighthouse",
                "read_file",
                "write_file",
                "load_skill",
                "request_input",
                "propose_note",
                "list_notes",
                "learn_rule",
                "open_gate",
            ],
            "seo-auditor": [
                "list_library",
                "read_item",
                "save_artifact",
                "fetch_url",
                "check_sitemap",
                "import_report",
                "query_report",
                "read_file",
                "write_file",
                "load_skill",
                "request_input",
                "propose_note",
                "list_notes",
                "learn_rule",
                "open_gate",
            ],
            "quality-auditor": [
                "list_library",
                "read_item",
                "list_files",
                "read_file",
                "audit_web_design",
                "take_screenshot",
                "run_checks",
                "compare_screenshots",
                "inspect_screenshots",
                "fetch_url",
                "check_sitemap",
                "open_gate",
                "propose_note",
                "list_notes",
                "learn_rule",
            ],
        }

    def register(self, tool: BaseTool) -> None:
        """Register a tool instance."""
        self._tools[tool.name] = tool

    def get(self, name: str) -> Optional[BaseTool]:
        """Retrieve a tool by name."""
        return self._tools.get(name)

    def get_allowed_tool_names(self, agent_key: str) -> List[str]:
        """Return list of allowed tool names for an agent loaded from agent.yaml (SSOT)."""
        from app.services.agent_loader import agent_loader

        declared = agent_loader.get_allowed_tool_names(agent_key)
        if declared:
            return declared
        return list(self._agent_permissions.get(agent_key, []))

    def get_tools_for_agent(self, agent_key: str) -> List[BaseTool]:
        """Return list of allowed tools for a specific agent."""
        allowed_names = self.get_allowed_tool_names(agent_key)
        return [self._tools[name] for name in allowed_names if name in self._tools]

    def get_openai_schemas_for_agent(self, agent_key: str) -> List[Dict[str, Any]]:
        """Return OpenAI function schemas for an agent."""
        tools = self.get_tools_for_agent(agent_key)
        return [tool.to_openai_schema() for tool in tools]


tool_registry = ToolRegistry()
AGENT_TOOL_PERMISSIONS = tool_registry._agent_permissions
