"""OpenRouter client handling LLM calls, function calling, tokens and cost tracking."""

from typing import Any, Dict, List, Optional
from openai import AsyncOpenAI
from pydantic import BaseModel

from app.config import settings


class ToolCallResult(BaseModel):
    """Normalized tool call returned by the LLM."""

    id: str
    name: str
    arguments: Dict[str, Any]


class LLMResponse(BaseModel):
    """Normalized output from OpenRouter LLM."""

    content: Optional[str] = None
    tool_calls: List[ToolCallResult] = []
    prompt_tokens: int = 0
    completion_tokens: int = 0
    total_tokens: int = 0
    estimated_cost_usd: float = 0.0


class OpenRouterClient:
    """Async wrapper for OpenRouter API."""

    def __init__(self, api_key: Optional[str] = None) -> None:
        self.api_key = api_key or settings.openrouter_api_key
        self.client = AsyncOpenAI(
            base_url="https://openrouter.ai/api/v1",
            api_key=self.api_key or "sk-dummy-key",
            default_headers={
                "HTTP-Referer": "https://hub.align.vn",
                "X-Title": "Align AI Hub",
            },
        )

    def calculate_cost(
        self, model: str, prompt_tokens: int, completion_tokens: int
    ) -> float:
        """Estimate cost in USD based on standard blended token rates."""
        # Baseline rough rate per 1k tokens: $0.0015 input / $0.002 output
        cost = (prompt_tokens * 0.0015 / 1000.0) + (completion_tokens * 0.002 / 1000.0)
        return round(cost, 6)

    async def chat_completion(
        self,
        messages: List[Dict[str, Any]],
        model: Optional[str] = None,
        tools: Optional[List[Dict[str, Any]]] = None,
        temperature: float = 0.2,
    ) -> LLMResponse:
        """Call OpenRouter chat completion with function calling support."""
        target_model = model or settings.default_model

        # Fallback simulation if no API key is provided
        if not self.api_key or self.api_key == "your_openrouter_api_key_here":
            return self._mock_response(messages)

        kwargs: Dict[str, Any] = {
            "model": target_model,
            "messages": messages,
            "temperature": temperature,
        }
        if tools:
            kwargs["tools"] = tools
            kwargs["tool_choice"] = "auto"

        response = await self.client.chat.completions.create(**kwargs)
        choice = response.choices[0]
        usage = response.usage

        prompt_tokens = usage.prompt_tokens if usage else 0
        completion_tokens = usage.completion_tokens if usage else 0
        total_tokens = usage.total_tokens if usage else 0
        cost = self.calculate_cost(target_model, prompt_tokens, completion_tokens)

        tool_calls: List[ToolCallResult] = []
        if choice.message.tool_calls:
            import json
            for tc in choice.message.tool_calls:
                args = json.loads(tc.function.arguments) if tc.function.arguments else {}
                tool_calls.append(
                    ToolCallResult(id=tc.id, name=tc.function.name, arguments=args)
                )

        return LLMResponse(
            content=choice.message.content,
            tool_calls=tool_calls,
            prompt_tokens=prompt_tokens,
            completion_tokens=completion_tokens,
            total_tokens=total_tokens,
            estimated_cost_usd=cost,
        )

    def _mock_response(self, messages: List[Dict[str, Any]]) -> LLMResponse:
        """Provide fallback response for testing without active API key."""
        last_msg = messages[-1]["content"] if messages else ""
        return LLMResponse(
            content=f"[Hub AI Simulated Response] Received: {last_msg[:100]}",
            tool_calls=[],
            prompt_tokens=10,
            completion_tokens=20,
            total_tokens=30,
            estimated_cost_usd=0.00005,
        )


openrouter_client = OpenRouterClient()
