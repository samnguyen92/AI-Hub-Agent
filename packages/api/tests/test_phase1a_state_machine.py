"""Phase 1a State Machine & Checkpoint Resume Acceptance Tests."""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch

from app.main import app  # noqa: F401 - ensure tools are registered
from app.db.models import Agent, Message, Project, Run, Thread
from app.services.agent_runner import AgentRunner
from tests.fake_llm import FakeLLMClient


@pytest.mark.asyncio
async def test_gate_pause_saves_checkpoint_and_does_not_complete() -> None:
    """TC-03: When open_gate is called, run must pause, save checkpoint, and stay in waiting_gate_*."""
    runner = AgentRunner(max_steps=5)

    mock_db = MagicMock()
    mock_db.add = MagicMock()
    mock_db.commit = AsyncMock()
    mock_db.refresh = AsyncMock()

    mock_run = MagicMock()
    mock_run.id = "run-state-1"
    mock_run.thread_id = "th-1"
    mock_run.agent_key = "code-builder"
    mock_run.status = "queued"
    mock_run.checkpoint_state = None

    mock_thread = MagicMock()
    mock_thread.id = "th-1"
    mock_thread.project_id = "proj-1"

    mock_agent = MagicMock()
    mock_agent.key = "code-builder"
    mock_agent.model = "test-model"
    mock_agent.config = {"max_steps": 5, "max_cost_usd": 1.0}

    def execute_mock(stmt):
        m = MagicMock()
        text_stmt = str(stmt)
        if "FROM runs" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_run)))
        elif "FROM threads" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_thread)))
        elif "FROM agents" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_agent)))
        elif "FROM project_notes" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[])))
        elif "FROM messages" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[
                MagicMock(role="user", content="Deploy the website")
            ])))
        else:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[]), first=MagicMock(return_value=None)))
        return m

    mock_db.execute = AsyncMock(side_effect=execute_mock)

    # Fake LLM that opens a plan gate
    fake_llm = FakeLLMClient()
    fake_llm.add_tool_call(
        tool_name="open_gate",
        arguments={
            "gate_type": "plan",
            "title": "Architecture Approval",
            "slug": "arch-plan",
            "decision_needed": "Do you approve the database schema?",
        },
    )

    with patch("app.services.agent_runner.openrouter_client.chat_completion", side_effect=fake_llm.chat_completion):
        res = await runner.execute_run("run-state-1", mock_db)

    for call in mock_db.add.call_args_list:
        obj = call.args[0]
        if hasattr(obj, "tool"):
            print("STEP TOOL:", obj.tool, "RESULT:", obj.result)

    assert res["status"] == "waiting_gate_plan"
    assert mock_run.checkpoint_state is not None
    assert "messages" in mock_run.checkpoint_state
    assert mock_run.status == "waiting_gate_plan"


@pytest.mark.asyncio
async def test_multi_tool_call_deferred_on_pause() -> None:
    """TC-04: Remaining tool calls in a turn must be deferred so message history is valid."""
    runner = AgentRunner(max_steps=5)

    mock_db = MagicMock()
    mock_db.add = MagicMock()
    mock_db.commit = AsyncMock()

    mock_run = MagicMock()
    mock_run.id = "run-multi-1"
    mock_run.thread_id = "th-1"
    mock_run.agent_key = "code-builder"
    mock_run.status = "queued"
    mock_run.checkpoint_state = None

    mock_thread = MagicMock()
    mock_thread.id = "th-1"
    mock_thread.project_id = "proj-1"

    mock_agent = MagicMock()
    mock_agent.key = "code-builder"
    mock_agent.model = "test-model"
    mock_agent.config = {}

    def execute_mock(stmt):
        m = MagicMock()
        text_stmt = str(stmt)
        if "FROM runs" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_run)))
        elif "FROM threads" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_thread)))
        elif "FROM agents" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_agent)))
        elif "FROM project_notes" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[])))
        elif "FROM messages" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[])))
        else:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[]), first=MagicMock(return_value=None)))
        return m

    mock_db.execute = AsyncMock(side_effect=execute_mock)

    from app.services.openrouter import LLMResponse, ToolCallResult
    # 2 tool calls in the same turn: export_zip (pauses) and read_file
    tc1 = ToolCallResult(id="call_export", name="export_zip", arguments={"archive_name": "release.zip"})
    tc2 = ToolCallResult(id="call_read", name="read_file", arguments={"path": "README.md"})
    response_with_2_calls = LLMResponse(content="I will export and then read", tool_calls=[tc1, tc2])

    fake_llm = FakeLLMClient([response_with_2_calls])

    with patch("app.services.agent_runner.openrouter_client.chat_completion", side_effect=fake_llm.chat_completion):
        res = await runner.execute_run("run-multi-1", mock_db)

    assert res["status"] == "waiting_approval"
    saved_messages = mock_run.checkpoint_state["messages"]

    # Verify both tool_calls received response in messages (call_export and call_read)
    tool_resp_ids = [m.get("tool_call_id") for m in saved_messages if m.get("role") == "tool"]
    assert "call_export" in tool_resp_ids
    assert "call_read" in tool_resp_ids

    # Second tool call must be deferred
    deferred_msg = next(m for m in saved_messages if m.get("tool_call_id") == "call_read")
    assert "deferred" in deferred_msg["content"]


@pytest.mark.asyncio
async def test_consecutive_errors_fail_run() -> None:
    """TC-06: 3 consecutive tool errors should fail the run with explanation."""
    runner = AgentRunner(max_steps=5)

    mock_db = MagicMock()
    mock_db.add = MagicMock()
    mock_db.commit = AsyncMock()

    mock_run = MagicMock()
    mock_run.id = "run-err-1"
    mock_run.thread_id = "th-1"
    mock_run.agent_key = "code-builder"
    mock_run.status = "queued"
    mock_run.checkpoint_state = None

    mock_thread = MagicMock()
    mock_thread.id = "th-1"
    mock_thread.project_id = "proj-1"

    mock_agent = MagicMock()
    mock_agent.key = "code-builder"
    mock_agent.model = "test-model"
    mock_agent.config = {}

    def execute_mock(stmt):
        m = MagicMock()
        text_stmt = str(stmt)
        if "FROM runs" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_run)))
        elif "FROM threads" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_thread)))
        elif "FROM agents" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_agent)))
        elif "FROM project_notes" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[])))
        elif "FROM messages" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[])))
        else:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[]), first=MagicMock(return_value=None)))
        return m

    mock_db.execute = AsyncMock(side_effect=execute_mock)

    # LLM calls non-existent tool 3 times consecutively
    fake_llm = FakeLLMClient()
    fake_llm.add_tool_call("non_existent_tool_1", {})
    fake_llm.add_tool_call("non_existent_tool_2", {})
    fake_llm.add_tool_call("non_existent_tool_3", {})

    with patch("app.services.agent_runner.openrouter_client.chat_completion", side_effect=fake_llm.chat_completion):
        res = await runner.execute_run("run-err-1", mock_db)

    assert res["status"] == "failed"
    assert mock_run.status == "failed"
    assert "3 lỗi" in (mock_run.error_detail or "")


@pytest.mark.asyncio
async def test_max_steps_limit_reached_not_empty() -> None:
    """TC-05: When max_steps reached, final_content must not be empty and status must be limit_reached."""
    runner = AgentRunner(max_steps=2)

    mock_db = MagicMock()
    mock_db.add = MagicMock()
    mock_db.commit = AsyncMock()

    mock_run = MagicMock()
    mock_run.id = "run-limit-1"
    mock_run.thread_id = "th-1"
    mock_run.agent_key = "code-builder"
    mock_run.status = "queued"
    mock_run.checkpoint_state = None

    mock_thread = MagicMock()
    mock_thread.id = "th-1"
    mock_thread.project_id = "proj-1"

    mock_agent = MagicMock()
    mock_agent.key = "code-builder"
    mock_agent.model = "test-model"
    mock_agent.config = {"max_steps": 2}

    def execute_mock(stmt):
        m = MagicMock()
        text_stmt = str(stmt)
        if "FROM runs" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_run)))
        elif "FROM threads" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_thread)))
        elif "FROM agents" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_agent)))
        elif "FROM project_notes" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[])))
        elif "FROM messages" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[])))
        else:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[]), first=MagicMock(return_value=None)))
        return m

    mock_db.execute = AsyncMock(side_effect=execute_mock)

    # LLM always returns a tool call without ever finishing
    fake_llm = FakeLLMClient()
    fake_llm.add_tool_call("read_file", {"path": "file1.txt"})
    fake_llm.add_tool_call("read_file", {"path": "file2.txt"})

    with patch("app.services.agent_runner.openrouter_client.chat_completion", side_effect=fake_llm.chat_completion):
        res = await runner.execute_run("run-limit-1", mock_db)

    assert res["status"] == "limit_reached"
    assert res["output"] != ""
    assert "giới hạn số bước" in res["output"].lower()


@pytest.mark.asyncio
async def test_resume_from_checkpoint_executes_remaining_steps() -> None:
    """TC-03 & B3: Verify runner can resume from a saved checkpoint_state."""
    runner = AgentRunner(max_steps=5)

    mock_db = MagicMock()
    mock_db.add = MagicMock()
    mock_db.commit = AsyncMock()
    mock_db.refresh = AsyncMock()

    mock_run = MagicMock()
    mock_run.id = "run-resume-1"
    mock_run.thread_id = "th-1"
    mock_run.agent_key = "code-builder"
    mock_run.status = "queued"
    mock_run.checkpoint_state = {
        "messages": [
            {"role": "system", "content": "You are code-builder"},
            {"role": "user", "content": "Build homepage"},
            {"role": "assistant", "content": "Pausing for gate"},
        ],
        "step_idx": 1,
        "total_cost": 0.0005,
    }

    mock_thread = MagicMock()
    mock_thread.id = "th-1"
    mock_thread.project_id = "proj-1"

    mock_agent = MagicMock()
    mock_agent.key = "code-builder"
    mock_agent.model = "test-model"
    mock_agent.config = {}

    def execute_mock(stmt):
        m = MagicMock()
        text_stmt = str(stmt)
        if "FROM runs" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_run)))
        elif "FROM threads" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_thread)))
        elif "FROM agents" in text_stmt:
            m.scalars = MagicMock(return_value=MagicMock(first=MagicMock(return_value=mock_agent)))
        else:
            m.scalars = MagicMock(return_value=MagicMock(all=MagicMock(return_value=[]), first=MagicMock(return_value=None)))
        return m

    mock_db.execute = AsyncMock(side_effect=execute_mock)

    fake_llm = FakeLLMClient()
    fake_llm.add_final_answer("Homepage completed after resumption.")

    with patch("app.services.agent_runner.openrouter_client.chat_completion", side_effect=fake_llm.chat_completion):
        res = await runner.execute_run("run-resume-1", mock_db)

    assert res["status"] == "completed"
    assert "Homepage completed" in (res["output"] or "")
    assert mock_run.checkpoint_state is None
