"""Unit and integration tests for Assistant Agent, GPT-6 Luna model, and Self-Learning Evolution."""

import pytest
from httpx import ASGITransport, AsyncClient
from sqlalchemy import select

from app.db.models import Agent, Project, ProjectNote, User
from app.db.session import async_session_maker
from app.db.seed import seed_agents
from app.main import app
from app.services.agent_runner import agent_runner
from app.services.gateway import IncomingMessagePayload, message_gateway
from app.services.router_agent import router_agent
from app.tools.base import ToolContext
from app.tools.memory_tools import LearnRuleTool


@pytest.mark.asyncio
async def test_assistant_agent_seeded_with_gpt6_luna() -> None:
    """Verify that assistant agent exists and all default agents use openai/gpt-6-luna."""
    async with async_session_maker() as db:
        await seed_agents(db)
        res = await db.execute(select(Agent).where(Agent.key == "assistant"))
        assistant = res.scalars().first()
        assert assistant is not None
        assert assistant.model == "openai/gpt-6-luna"

        # Also verify other agents got updated to openai/gpt-6-luna
        d_res = await db.execute(select(Agent).where(Agent.key == "design"))
        design = d_res.scalars().first()
        assert design is not None
        assert design.model == "openai/gpt-6-luna"


@pytest.mark.asyncio
async def test_router_directs_general_chat_to_assistant() -> None:
    """Verify conversational messages route seamlessly to assistant with confidence 1.0."""
    queries = [
        "Xin chào bạn, hôm nay có thể làm được gì?",
        "Tư vấn cho tôi cấu trúc website cho phòng khám nha khoa",
        "Giải thích khái niệm Core Web Vitals cho người mới",
    ]
    for q in queries:
        decision = await router_agent.classify(q)
        assert decision.agent == "assistant"
        assert decision.confidence == 1.0


@pytest.mark.asyncio
async def test_teach_rule_api_and_learn_tool() -> None:
    """Test learning rule via REST API and LearnRuleTool."""
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        # Create a project
        p_res = await client.post("/projects", json={"name": "Evolution Proj"})
        assert p_res.status_code == 200
        proj_id = p_res.json()["id"]

        # 1. Teach rule via API
        l_res = await client.post(
            f"/projects/{proj_id}/learn",
            json={"rule": "Menu mobile luôn là dạng drawer trượt từ bên phải", "category": "ui_style"},
        )
        assert l_res.status_code == 200
        data = l_res.json()
        assert data["status"] == "approved"
        assert "[LEARNED_RULE:UI_STYLE]" in data["content"]

        # 2. Test LearnRuleTool execution
        async with async_session_maker() as db:
            tool_ctx = ToolContext(
                project_id=proj_id,
                run_id="run-evo-1",
                workspace_path="/tmp",
                db=db,
                agent_key="assistant",
            )
            learn_tool = LearnRuleTool()
            res = await learn_tool.execute(
                tool_ctx, rule="Các button luôn có bo góc 8px", category="style"
            )
            assert res["status"] == "learned"
            assert "[LEARNED_RULE:STYLE]" in res["rule"]


@pytest.mark.asyncio
async def test_gateway_learn_command() -> None:
    """Test /learn quick command via message gateway."""
    async with async_session_maker() as db:
        user_res = await db.execute(select(User).where(User.github_login == "tester-evo"))
        user = user_res.scalars().first()
        if not user:
            user = User(github_login="tester-evo", role="user")
            db.add(user)
            await db.flush()

        proj = Project(owner_id=user.id, name="Learn Cmd Proj", workspace_path="/tmp")
        db.add(proj)
        await db.flush()

        payload = IncomingMessagePayload(
            project_id=proj.id,
            content="/learn Luôn kiểm tra tính tương thích trên iOS Safari",
        )
        res = await message_gateway.process_incoming_message(payload, db)
        assert res.get("learned") is True
        assert res.get("status") == "completed"

        # Verify note in DB
        notes_res = await db.execute(select(ProjectNote).where(ProjectNote.project_id == proj.id))
        notes = notes_res.scalars().all()
        assert any("iOS Safari" in n.content for n in notes)


@pytest.mark.asyncio
async def test_agent_runner_includes_learned_rules_in_prompt() -> None:
    """Verify that agent_runner system prompt includes distinct learned rules section."""
    notes = [
        "[LEARNED_RULE:DESIGN] Banner luôn có hiệu ứng overlay tối 40%",
        "[STYLE] Font chữ chủ đạo Inter",
    ]
    prompt = agent_runner._build_system_prompt("assistant", "/tmp/ws", notes=notes)
    assert "=== PERSISTENT LEARNED RULES (SELF-EVOLVED RULES - MUST COMPLY) ===" in prompt
    assert "Banner luôn có hiệu ứng overlay tối 40%" in prompt
    assert "=== PROJECT MEMORY & APPROVED NOTES ===" in prompt
    assert "[STYLE] Font chữ chủ đạo Inter" in prompt
