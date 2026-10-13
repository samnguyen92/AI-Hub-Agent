"""Unit tests for GenerateImageTool and Seedream 4.5 image integration."""

import pytest
from unittest.mock import AsyncMock, patch

from app.tools.base import tool_registry, ToolContext
from app.tools.image_tools import GenerateImageTool


@pytest.mark.asyncio
async def test_generate_image_tool_mocked(tmp_path):
    tool = tool_registry.get("generate_image")
    assert tool is not None
    assert isinstance(tool, GenerateImageTool)

    ctx = ToolContext(
        project_id="test-proj-img",
        run_id="run-img-001",
        workspace_path=str(tmp_path),
        db=AsyncMock(),
        agent_key="design",
    )

    with patch(
        "app.services.openrouter.openrouter_client.generate_image",
        new_callable=AsyncMock,
    ) as mock_gen:
        mock_gen.return_value = {
            "status": "success",
            "image_url": "https://example.com/test-car.png",
            "model": "openai/gpt-5.4-image-2",
        }

        with patch("httpx.AsyncClient") as mock_httpx:
            mock_client_instance = AsyncMock()
            mock_resp = AsyncMock()
            mock_resp.status_code = 200
            mock_resp.content = b"FAKE_PNG_BINARY_CONTENT"
            mock_client_instance.get.return_value = mock_resp
            mock_httpx.return_value.__aenter__.return_value = mock_client_instance

            res = await tool.execute(
                ctx,
                prompt="Modern red Toyota Camry showroom banner",
                aspect_ratio="16:9",
                filename="toyota_camry_banner",
            )

    assert res["status"] == "success"
    assert res["model"] == "openai/gpt-5.4-image-2"
    assert "images/toyota_camry_banner" in res["local_path"]
    assert "markdown" in res
    assert "![Modern red Toyota Camry showroom banner]" in res["markdown"]
