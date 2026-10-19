"""Single Source of Truth (SSOT) agent configuration loader."""

import os
from typing import Any, Dict, List, Optional
import yaml


class AgentConfigLoader:
    """Discovers, parses, validates, and serves agent definitions from agent.yaml files."""

    def __init__(self) -> None:
        self._manifests: Dict[str, Dict[str, Any]] = {}
        self._root_dirs = [
            "/app",
            os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../..")),
            os.getcwd(),
        ]
        self.reload()

    def _find_agents_dir(self) -> Optional[str]:
        """Locate directory containing agent.yaml configurations."""
        for r in self._root_dirs:
            cand = os.path.join(r, "agents")
            if os.path.isdir(cand):
                return cand
        return None

    def reload(self) -> None:
        """Scan agents directory and load all agent.yaml files into memory."""
        agents_dir = self._find_agents_dir()
        if not agents_dir:
            return

        self._manifests.clear()
        for entry in os.listdir(agents_dir):
            agent_folder = os.path.join(agents_dir, entry)
            if not os.path.isdir(agent_folder):
                continue
            yaml_path = os.path.join(agent_folder, "agent.yaml")
            if os.path.isfile(yaml_path):
                try:
                    with open(yaml_path, "r", encoding="utf-8") as f:
                        data = yaml.safe_load(f)
                    if isinstance(data, dict) and "key" in data:
                        self._manifests[data["key"]] = data
                except (yaml.YAMLError, OSError) as exc:
                    print(f"Warning: Failed to load {yaml_path}: {exc}")

    def get_agent_manifest(self, agent_key: str) -> Optional[Dict[str, Any]]:
        """Retrieve full YAML manifest for given agent."""
        return self._manifests.get(agent_key)

    def get_all_agents(self) -> List[Dict[str, Any]]:
        """Return list of all loaded agent manifests."""
        return list(self._manifests.values())

    def get_allowed_tool_names(self, agent_key: str) -> List[str]:
        """Return declared tools for agent, or empty list if none."""
        manifest = self.get_agent_manifest(agent_key)
        if manifest and "tools" in manifest and isinstance(manifest["tools"], list):
            return list(manifest["tools"])
        return []

    def _resolve_file(self, rel_path: str) -> bool:
        """Check if relative file exists across search roots."""
        for r in self._root_dirs:
            if os.path.isfile(os.path.join(r, rel_path)):
                return True
        return False

    def _resolve_skill(self, agent_key: str, skill_ref: str) -> bool:
        """Check if skill file exists either in shared or agent-specific skills."""
        clean = skill_ref.split("/")[-1]
        cands = [
            f"skills/shared/{skill_ref}/SKILL.md",
            f"skills/shared/{clean}/SKILL.md",
            f"agents/{agent_key}/skills/{skill_ref}/SKILL.md",
            f"agents/{agent_key}/skills/{clean}/SKILL.md",
        ]
        return any(self._resolve_file(c) for c in cands)

    def validate_agents(self) -> None:
        """Verify that all referenced rules and skills exist on filesystem; fail-fast if broken."""
        errors: List[str] = []
        for key, manifest in self._manifests.items():
            rules_path = manifest.get("rules_path") or f"agents/{key}/rules.md"
            if not self._resolve_file(rules_path):
                errors.append(f"Agent '{key}': rules_path '{rules_path}' not found on filesystem.")

            for skill in manifest.get("skills", []):
                if not self._resolve_skill(key, skill):
                    errors.append(f"Agent '{key}': skill '{skill}' not found on filesystem.")

        if errors:
            raise RuntimeError(f"Agent validation failed on startup:\n" + "\n".join(errors))


agent_loader = AgentConfigLoader()
