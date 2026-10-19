"""Fake LLM client for deterministic, token-free integration testing."""

from typing import Any, Dict, List, Optional
from app.services.openrouter import LLMResponse, ToolCallResult


class FakeLLMClient:
    """Mock LLM client executing pre-scripted tool calls and responses."""

    def __init__(self, responses: Optional[List[LLMResponse]] = None) -> None:
        self.responses: List[LLMResponse] = list(responses or [])
        self.recorded_calls: List[Dict[str, Any]] = []
        self.call_count: int = 0

    def add_tool_call(self, tool_name: str, arguments: Dict[str, Any], call_id: str = "call_mock_1") -> "FakeLLMClient":
        """Helper to append a tool call response to the sequence."""
        tc = ToolCallResult(id=call_id, name=tool_name, arguments=arguments)
        self.responses.append(
            LLMResponse(
                content=None,
                tool_calls=[tc],
                prompt_tokens=50,
                completion_tokens=20,
                total_tokens=70,
                estimated_cost_usd=0.0001,
            )
        )
        return self

    def add_final_answer(self, content: str) -> "FakeLLMClient":
        """Helper to append a final assistant response without tool calls."""
        self.responses.append(
            LLMResponse(
                content=content,
                tool_calls=[],
                prompt_tokens=100,
                completion_tokens=50,
                total_tokens=150,
                estimated_cost_usd=0.0002,
            )
        )
        return self

    async def chat_completion(
        self,
        messages: List[Dict[str, Any]],
        model: Optional[str] = None,
        tools: Optional[List[Dict[str, Any]]] = None,
        temperature: float = 0.2,
    ) -> LLMResponse:
        """Simulate LLM invocation by popping from scripted responses."""
        self.recorded_calls.append({
            "messages": messages,
            "model": model,
            "tools": tools,
            "temperature": temperature,
        })
        self.call_count += 1

        if self.responses:
            return self.responses.pop(0)

        # Default fallback response if script is exhausted
        return LLMResponse(
            content="Task completed successfully.",
            tool_calls=[],
            prompt_tokens=10,
            completion_tokens=10,
            total_tokens=20,
            estimated_cost_usd=0.00005,
        )
