"""Unit tests for Message Gateway and Agent Runner."""

import pytest
from app.services.gateway import message_gateway, IncomingMessagePayload


def test_gateway_extract_agent_mention() -> None:
    """Verify @agent extraction from user message."""
    agent, clean = message_gateway.extract_agent_mention("Vui lòng @design thiết kế giao diện trang landing")
    assert agent == "design"
    assert clean == "Vui lòng thiết kế giao diện trang landing"

    agent2, clean2 = message_gateway.extract_agent_mention("@seo-auditor phân tích audit trang này")
    assert agent2 == "seo-auditor"
    assert clean2 == "phân tích audit trang này"

    no_agent, clean3 = message_gateway.extract_agent_mention("Tạo file html thuần")
    assert no_agent is None
    assert clean3 == "Tạo file html thuần"
