"""Router Agent classifying user requests to the appropriate specialized agent."""

import json
from typing import Any, Dict, List, Optional
from pydantic import BaseModel, Field
from app.services.openrouter import openrouter_client

ROUTER_PROMPT = """Bạn là Router Agent của Align AI Hub. Nhiệm vụ của bạn là đọc yêu cầu người dùng và quyết định giao việc cho Agent nào.

DANH MỤC AGENTS:
1. 'assistant': Trợ lý hội thoại toàn năng giống ChatGPT/Claude, giải thích, tư vấn, hỏi đáp, đàm thoại tự nhiên.
2. 'design': Chuyên tạo thiết kế, design tokens, mockup HTML/CSS cho trang chủ/landing page, style guide.
3. 'code-builder': Chuyên lập trình HTML/CSS/JS thuần theo chuẩn align.vn từ mockup PNG hoặc yêu cầu cụ thể, sửa code, linting.
4. 'site-analyzer': Chuyên phân tích website, crawl trang, kiểm tra sitemap/robots.txt, đo tốc độ Core Web Vitals (Lighthouse).
5. 'seo-auditor': Chuyên kiểm định SEO, phân tích file Search Console/GA4, rà soát noindex, canonical, nội dung mỏng.
6. 'quality-auditor': Chuyên kiểm tra độc lập chất lượng giao diện, accessibility, responsive và tuân thủ tiêu chuẩn.
7. 'image-generator': Tạo ảnh, minh họa, logo, poster hoặc hình sản phẩm theo brief người dùng.
8. 'wordpress-builder': Chuyên khởi tạo container WordPress, cài theme Align & plugins, import HTML ZIP, tinh chỉnh SEO/nội dung, và xuất backup All-in-One WP Migration.

QUY TẮC:
- Nếu yêu cầu là trò chuyện, chào hỏi, tư vấn chung, giải thích -> luôn giao cho 'assistant' với confidence 1.0.
- Bạn PHẢI trả về đúng định dạng JSON theo schema đã cho, không thêm bất kỳ văn bản nào khác.
"""

ROUTER_SCHEMA = {
    "type": "object",
    "properties": {
        "agent": {
            "type": "string",
            "enum": [
                "assistant",
                "design",
                "code-builder",
                "site-analyzer",
                "seo-auditor",
                "quality-auditor",
                "image-generator",
                "wordpress-builder",
            ],
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
    missing_info: List[str] = Field(default_factory=list)
    reason: str


class RouterAgent:
    """Agent router classifier directing user requests seamlessly."""

    @staticmethod
    def _explicit_decision(prompt: str) -> Optional[RouterDecision]:
        """Honor a user-selected agent before applying history-based routing."""
        routes = [
            (("@assistant", "@chat"), "assistant", None),
            (("@design",), "design", "homepage-mockup"),
            (("@code", "@code-builder"), "code-builder", "align-style"),
            (("@analyzer", "@site-analyzer"), "site-analyzer", "site-crawl"),
            (("@seo", "@seo-auditor"), "seo-auditor", "gsc-audit"),
            (("@quality", "@quality-auditor"), "quality-auditor", None),
            (("@image-generator",), "image-generator", None),
            (("@wp", "@wordpress", "@wordpress-builder"), "wordpress-builder", None),
        ]
        for tags, agent, skill in routes:
            if any(tag in prompt for tag in tags):
                return RouterDecision(agent=agent, skill_hint=skill, confidence=1.0, reason=f"Người dùng chỉ định {agent}")
        return None

    async def classify(
        self, user_prompt: str, recent_history: Optional[List[Dict[str, str]]] = None
    ) -> RouterDecision:
        """Analyze message and determine target agent."""
        prompt_lower = user_prompt.lower()
        explicit = self._explicit_decision(prompt_lower)
        return explicit or self._context_decision(prompt_lower, recent_history) or self._keyword_decision(prompt_lower)

    def _context_decision(
        self, prompt: str, recent_history: Optional[List[Dict[str, str]]]
    ) -> Optional[RouterDecision]:
        """Route follow-ups using the latest visible conversation context."""
        history_text = " ".join(message.get("content", "") for message in (recent_history or [])).lower()
        has_image = any(token in history_text for token in ["![", "images/", ".png", ".jpg", ".webp"])
        has_html = any(term in history_text for term in ["index.html", ".html", "website", "homepage"])
        website_feedback = any(term in prompt for term in [
            "screenshot", "ảnh chụp", "ảnh này", "sửa giao diện", "sửa trang", "chỉnh trang",
            "fix layout", "fix design", "lỗi thiết kế", "vấn đề về design", "theo ảnh",
        ])
        if has_image and has_html and website_feedback:
            return RouterDecision(
                agent="code-builder", skill_hint="responsive-checks", confidence=0.98,
                reason="Sửa website hiện tại theo screenshot được đính kèm",
            )
        image_edit = any(term in prompt for term in ["đổi màu", "chỉnh màu", "màu này", "chữ nhỏ", "chữ to", "chữ hơi", "giảm cỡ chữ", "giữ logo", "sửa logo", "chỉnh logo"])
        if has_image and image_edit and not has_html:
            return RouterDecision(agent="image-generator", confidence=0.97, reason="Chỉnh sửa ảnh đã có trong cuộc trò chuyện")

        has_wp = any(term in history_text for term in ["wordpress", "wp", "theme align", "align-child", "wpress", "môi trường wordpress"])
        continue_wp = any(term in prompt for term in ["tiến hành", "bắt đầu", "tạo đi", "làm đi", "triển khai", "đồng ý", "okay", "ok", "tiếp tục", "làm cho tôi"])
        if has_wp and continue_wp:
            return RouterDecision(
                agent="wordpress-builder",
                confidence=0.96,
                reason="Tiếp tục triển khai môi trường WordPress theo ngữ cảnh hội thoại",
            )

        continue_terms = ["tiếp tục", "chỉnh tiếp", "sửa trang", "hoàn thiện", "gửi file", "đổi màu"]
        if has_html and any(term in prompt for term in continue_terms):
            return RouterDecision(agent="code-builder", skill_hint="align-style", confidence=0.94, reason="Tiếp tục chỉnh sửa website trong ngữ cảnh cuộc trò chuyện")
        if has_image and any(term in prompt for term in ["tiếp tục", "dựng trang", "tạo trang"]):
            return RouterDecision(agent="design", skill_hint="homepage-mockup", confidence=0.92, reason="Tiếp tục thiết kế từ ảnh đã thống nhất")
        if any(term in prompt for term in ["tạo ảnh", "tạo hình", "generate image", "image generation", "vẽ ảnh", "tạo logo"]):
            return RouterDecision(agent="image-generator", confidence=0.95, reason="Yêu cầu tạo ảnh trực tiếp")
        return None

    @staticmethod
    def _keyword_decision(prompt: str) -> RouterDecision:
        """Classify a new request by action keywords when context is absent."""
        if any(w in prompt for w in ["thiết kế", "mockup", "giao diện", "màu sắc", "font", "layout", "banner"]):
            return RouterDecision(
                agent="design",
                skill_hint="homepage-mockup",
                confidence=0.92,
                reason="Yêu cầu liên quan đến thiết kế và mockup giao diện",
            )

        if any(w in prompt for w in ["lập trình", "viết code", "html", "css", "javascript", "build", "sửa code", "bug"]):
            return RouterDecision(
                agent="code-builder",
                skill_hint="align-style",
                confidence=0.90,
                reason="Yêu cầu lập trình mã nguồn website",
            )

        if any(w in prompt for w in ["lighthouse", "tốc độ", "crawl", "sitemap", "robots.txt", "kiểm tra web", "quét trang"]):
            return RouterDecision(
                agent="site-analyzer",
                skill_hint="site-crawl",
                confidence=0.91,
                reason="Yêu cầu đo đạc và phân tích kỹ thuật website",
            )

        if any(w in prompt for w in ["seo", "từ khóa", "search console", "ga4", "ranking", "index", "canonical"]):
            return RouterDecision(
                agent="seo-auditor",
                skill_hint="gsc-audit",
                confidence=0.93,
                reason="Yêu cầu kiểm định và tối ưu hóa SEO",
            )

        if any(w in prompt for w in ["kiểm định chất lượng", "quality audit", "accessibility", "wcag", "rà soát giao diện"]):
            return RouterDecision(
                agent="quality-auditor",
                confidence=0.91,
                reason="Yêu cầu kiểm định chất lượng và khả năng truy cập",
            )

        if any(w in prompt for w in ["wordpress", "wp", "wpress", "all-in-one", "ai1wm", "dựng wordpress", "tạo wordpress", "backup wordpress", "import html"]):
            return RouterDecision(
                agent="wordpress-builder",
                confidence=0.96,
                reason="Yêu cầu quản lý hoặc vận hành website WordPress",
            )

        return RouterDecision(
            agent="assistant",
            confidence=1.0,
            reason="Hội thoại tự nhiên cùng Trợ lý Align AI",
        )


router_agent = RouterAgent()
