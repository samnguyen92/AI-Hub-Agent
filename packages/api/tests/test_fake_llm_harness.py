"""Unit test verifying the FakeLLMClient test harness."""

import pytest
from app.services.openrouter import LLMResponse
from tests.fake_llm import FakeLLMClient


@pytest.mark.asyncio
async def test_fake_llm_sequential_responses() -> None:
    """Verify FakeLLMClient yields scripted responses in sequence."""
    fake = FakeLLMClient()
    fake.add_tool_call("read_file", {"path": "src/index.html"})
    fake.add_final_answer("I inspected your index.html file.")

    res1 = await fake.chat_completion([{"role": "user", "content": "hello"}])
    assert len(res1.tool_calls) == 1
    assert res1.tool_calls[0].name == "read_file"
    assert res1.tool_calls[0].arguments == {"path": "src/index.html"}

    res2 = await fake.chat_completion([{"role": "user", "content": "continue"}])
    assert len(res2.tool_calls) == 0
    assert res2.content == "I inspected your index.html file."

    # Exhausted queue returns default
    res3 = await fake.chat_completion([])
    assert "completed" in (res3.content or "").lower()
    assert fake.call_count == 3
    assert len(fake.recorded_calls) == 3
