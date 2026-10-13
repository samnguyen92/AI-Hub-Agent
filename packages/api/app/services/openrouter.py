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
        if not response or not getattr(response, "choices", None) or len(response.choices) == 0:
            err_msg = getattr(response, "error", None) or "Empty response received from LLM model"
            return LLMResponse(
                content=f"⚠️ Notice: The model did not return text ({err_msg}). Please try again.",
                tool_calls=[],
                prompt_tokens=0,
                completion_tokens=0,
                total_tokens=0,
                estimated_cost_usd=0.0,
            )

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

    async def generate_image(
        self,
        prompt: str,
        aspect_ratio: str = "16:9",
        model: str = "openai/gpt-5.4-image-2",
    ) -> Dict[str, Any]:
        """Generate high-fidelity image via OpenRouter images endpoint."""
        if not self.api_key or self.api_key == "your_openrouter_api_key_here":
            return {
                "status": "mocked",
                "image_url": "https://images.unsplash.com/photo-1617814076367-b759c7d7e738?auto=format&fit=crop&w=1200&q=80",
                "model": model,
            }

        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "HTTP-Referer": "https://hub.align.vn",
            "X-Title": "Align AI Hub",
            "Content-Type": "application/json",
        }
        payload = {
            "model": model,
            "prompt": prompt,
            "aspect_ratio": aspect_ratio,
        }

        import httpx
        async with httpx.AsyncClient(timeout=90.0) as client:
            resp = await client.post(
                "https://openrouter.ai/api/v1/images",
                headers=headers,
                json=payload,
            )
            if resp.status_code != 200:
                raise RuntimeError(
                    f"OpenRouter image generation failed ({resp.status_code}): {resp.text}"
                )
            data = resp.json()

        items = data.get("data", [])
        first = items[0] if items else {}
        image_url = first.get("url")
        b64 = first.get("b64_json")
        media_type = first.get("media_type", "image/jpeg")

        if not image_url and b64:
            image_url = f"data:{media_type};base64,{b64}"

        if not image_url and isinstance(data.get("url"), str):
            image_url = data["url"]

        if not image_url:
            raise RuntimeError("No image URL or base64 returned by provider")

        return {
            "status": "success",
            "image_url": image_url,
            "model": model,
        }


openrouter_client = OpenRouterClient()

