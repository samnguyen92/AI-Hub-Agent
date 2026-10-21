"""Router Agent classifying user requests to the appropriate specialized agent."""

import json
from typing import Any, Dict, List, Optional
from pydantic import BaseModel
from app.services.openrouter import openrouter_client

ROUTER_PROMPT = """Bạn là Router Agent của Align AI Hub. Nhiệm vụ của bạn là đọc yêu cầu người dùng và quyết định giao việc cho Agent nào.

DANH MỤC AGENTS:
1. 'assistant': Trợ lý hội thoại toàn năng giống ChatGPT/Claude, giải thích, tư vấn, hỏi đáp, đàm thoại tự nhiên.
2. 'design': Chuyên tạo thiết kế, design tokens, mockup HTML/CSS cho trang chủ/landing page, style guide.
3. 'code-builder': Chuyên lập trình HTML/CSS/JS thuần theo chuẩn align.vn từ mockup PNG hoặc yêu cầu cụ thể, sửa code, linting.
4. 'site-analyzer': Chuyên phân tích website, crawl trang, kiểm tra sitemap/robots.txt, đo tốc độ Core Web Vitals (Lighthouse).
5. 'seo-auditor': Chuyên kiểm định SEO, phân tích file Search Console/GA4, rà soát noindex, canonical, nội dung mỏng.
6. 'quality-auditor': Chuyên kiểm tra độc lập chất lượng giao diện, accessibility, responsive và tuân thủ tiêu chuẩn.

QUY TẮC:
- Nếu yêu cầu là trò chuyện, chào hỏi, tư vấn chung, giải thích -> luôn giao cho 'assistant' với confidence 1.0.
- Bạn PHẢI trả về đúng định dạng JSON theo schema đã cho, không thêm bất kỳ văn bản nào khác.
"""

ROUTER_SCHEMA = {
    "type": "object",
    "properties": {
        "agent": {
            "type": "string",
            "enum": ["assistant", "design", "code-builder", "site-analyzer", "seo-auditor", "quality-auditor"],
        },
        "skill_hint": {"type": "string"},
        "confidence": {"type": "number", "minimum": 0.0, "maximum": 1.0},
        "missing_info": {"type": "array", "items": {"type": "string"}},
        "reason": {"type": "string"},
    },
    "required": ["agent", "confidence", "reason"],
}


class RouterDecision(BaseModel):
    """Output structure of router classification."""

    agent: str
    skill_hint: Optional[str] = None
    confidence: float
    missing_info: List[str] = []
    reason: str


class RouterAgent:
    """Agent router classifier directing user requests seamlessly."""

    async def classify(
        self, user_prompt: str, recent_history: Optional[List[Dict[str, str]]] = None
    ) -> RouterDecision:
        """Analyze message and determine target agent."""
        prompt_lower = user_prompt.lower()

        # Deterministic override rules
        if "@assistant" in prompt_lower or "@chat" in prompt_lower:
            return RouterDecision(
                agent="assistant",
                confidence=1.0,
                reason="Người dùng chỉ định Trợ lý hội thoại",
            )
        if "@design" in prompt_lower:
            return RouterDecision(
                agent="design",
                skill_hint="homepage-mockup",
                confidence=1.0,
                reason="Người dùng chỉ định @design",
            )
        if "@code" in prompt_lower or "@code-builder" in prompt_lower:
            return RouterDecision(
                agent="code-builder",
                skill_hint="align-style",
                confidence=1.0,
                reason="Người dùng chỉ định @code-builder",
            )
        if "@analyzer" in prompt_lower or "@site-analyzer" in prompt_lower:
            return RouterDecision(
                agent="site-analyzer",
                skill_hint="site-crawl",
                confidence=1.0,
                reason="Người dùng chỉ định @site-analyzer",
            )
        if "@seo" in prompt_lower or "@seo-auditor" in prompt_lower:
            return RouterDecision(
                agent="seo-auditor",
                skill_hint="gsc-audit",
                confidence=1.0,
                reason="Người dùng chỉ định @seo-auditor",
            )
        if "@quality" in prompt_lower or "@quality-auditor" in prompt_lower:
            return RouterDecision(
                agent="quality-auditor",
                confidence=1.0,
                reason="Người dùng chỉ định @quality-auditor",
            )

        # Heuristic keywords matching for specialized actions
        if any(w in prompt_lower for w in ["thiết kế", "mockup", "giao diện", "màu sắc", "font", "layout", "banner"]):
            return RouterDecision(
                agent="design",
                skill_hint="homepage-mockup",
                confidence=0.92,
                reason="Yêu cầu liên quan đến thiết kế và mockup giao diện",
            )

        if any(w in prompt_lower for w in ["lập trình", "viết code", "html", "css", "javascript", "build", "sửa code", "bug"]):
            return RouterDecision(
                agent="code-builder",
                skill_hint="align-style",
                confidence=0.90,
                reason="Yêu cầu lập trình mã nguồn website",
            )

        if any(w in prompt_lower for w in ["lighthouse", "tốc độ", "crawl", "sitemap", "robots.txt", "kiểm tra web", "quét trang"]):
            return RouterDecision(
                agent="site-analyzer",
                skill_hint="site-crawl",
                confidence=0.91,
                reason="Yêu cầu đo đạc và phân tích kỹ thuật website",
            )

        if any(w in prompt_lower for w in ["seo", "từ khóa", "search console", "ga4", "ranking", "index", "canonical"]):
            return RouterDecision(
                agent="seo-auditor",
                skill_hint="gsc-audit",
                confidence=0.93,
                reason="Yêu cầu kiểm định và tối ưu hóa SEO",
            )

        if any(w in prompt_lower for w in ["kiểm định chất lượng", "quality audit", "accessibility", "wcag", "rà soát giao diện"]):
            return RouterDecision(
                agent="quality-auditor",
                confidence=0.91,
                reason="Yêu cầu kiểm định chất lượng và khả năng truy cập",
            )

        # Default: General conversational assistant (ChatGPT/Claude experience)
        return RouterDecision(
            agent="assistant",
            confidence=1.0,
            reason="Hội thoại tự nhiên cùng Trợ lý Align AI",
        )


router_agent = RouterAgent()
