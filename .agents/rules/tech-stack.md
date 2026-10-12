# Tech Stack Rules & Approved Dependencies

Tất cả các thành phần trong dự án phải tuân theo danh mục công nghệ đã được duyệt dưới đây. Mọi bổ sung thư viện mới phải được trao đổi trước.

## 1. Backend (packages/api/)
- **Python**: >= 3.11
- **Web Framework**: FastAPI (>= 0.110.0), Uvicorn
- **ORM & DB Access**: SQLAlchemy 2.0 (asyncio hỗ trợ asyncpg/psycopg3), Alembic
- **Validation**: Pydantic v2
- **HTTP Client**: HTTPX (hỗ trợ async, chống SSRF)
- **HTML Parsing**: Selectolax / BeautifulSoup4
- **Data Analysis**: Pandas (xử lý CSV Search Console, GA4)
- **LLM Client**: openai python sdk (kết nối endpoint OpenRouter)
- **Testing**: Pytest, pytest-asyncio, httpx-mock

## 2. Frontend (packages/web/)
- **Node.js**: >= 20.x
- **Framework**: Next.js 14+ (App Router)
- **Language**: TypeScript (Strict mode)
- **UI Components & Styling**: CSS Modules / Vanilla CSS / TailwindCSS theo cấu trúc quy củ
- **Icons**: Lucide React
- **Streaming & Realtime**: Native EventSource (Server-Sent Events) hoặc WebSocket

## 3. Sandboxing & Tooling
- **Sandbox Container**: Docker container độc lập chạy Playwright (Chromium), Lighthouse CLI
- **Linting & Audit Tools**: html-validate, stylelint, axe-core CLI

## 4. Database & Infrastructure
- **RDBMS**: PostgreSQL 16 (Local chạy qua Docker Compose)
- **Deployment Plan**:
  - Development: Local Docker Compose (Docker Desktop)
  - Demo: Frontend trên Vercel, Backend trên Railway/Render hoặc VPS
