"""API Router for agent selection and classification."""

from typing import Optional
from fastapi import APIRouter
from pydantic import BaseModel

from app.services.router_agent import RouterDecision, router_agent

router = APIRouter(prefix="/router", tags=["router"])


class ClassifyRequest(BaseModel):
    prompt: str
    project_id: Optional[str] = None
    recent_history: list[dict[str, str]] = []


@router.post("/classify", response_model=RouterDecision)
async def classify_prompt(req: ClassifyRequest) -> RouterDecision:
    """Classify user prompt to identify optimal agent and skill."""
    return await router_agent.classify(req.prompt, req.recent_history[-10:])
