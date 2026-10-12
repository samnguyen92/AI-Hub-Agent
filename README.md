# Align AI Hub — Multi-Agent Platform

Nền tảng Multi-Agent chuyên sâu dành cho đội ngũ thiết kế, phát triển và tối ưu website của Align, tối ưu cho môi trường Local Docker và hỗ trợ cộng tác người dùng với AI.

---

## 🌟 Tính năng Cốt lõi

1. **Bộ 4 Agents Chuyên Biệt + 1 Router Thông Minh**:
   - `design`: Tạo layout, wireframe HTML/CSS, đối chiếu Design System của Align.
   - `code-builder`: Hiện thực hóa mã nguồn hoàn chỉnh, lint checklist, render preview 1440px/390px.
   - `site-analyzer`: Crawl web, phân tích cấu trúc DOM, chụp ảnh trang từ xa và chạy Lighthouse.
   - `seo-auditor`: Audit Technical SEO, kiểm tra thẻ meta, schema và xuất báo cáo điểm số.
   - `router`: Phân loại ý định người dùng và tự động điều phối tới Agent tối ưu nhất.

2. **Sandbox Playwright Cô Lập An Toàn**:
   - Môi trường Sandbox riêng biệt (cổng 8001), render HTML/CSS sang PNG đa viewport (Desktop 1440px, Mobile 390px) và so khớp sai khác hình ảnh (Visual Diff).

3. **Bảo Mật Cao Cấp Toàn Diện**:
   - **Path Traversal Defense**: Mọi thao tác file đều bị giới hạn nghiêm ngặt trong workspace riêng của từng dự án.
   - **SSRF Defense Chuyên Sâu**: Chặn dải IP nội bộ (RFC 1918, Link-local, Loopback), chặn metadata cloud (AWS/GCP), và kiểm tra kép IP trước khi tạo kết nối để chống DNS Rebinding.

4. **Human-in-the-loop Approval Gate & Telegram Bot**:
   - Các hành động nhạy cảm (như `export_zip`) bắt buộc phải có sự phê duyệt của con người.
   - Tích hợp Bot Telegram: Liên kết tài khoản an toàn qua mã PIN `ALIGN-XXXXX` và phê duyệt trực tiếp trên điện thoại qua nút bấm Inline Keyboard.

5. **Bộ Nhớ Dài Hạn Theo Dự Án (Project Memory)**:
   - Cơ chế đề xuất ghi chú (`propose_note`), duyệt ghi chú (`approved`), và tự động nạp vào System Prompt của Agent trong các phiên làm việc tiếp theo.

---

## 🏗️ Kiến Trúc Hệ Thống

```
                    ┌─────────────────────────┐
                    │  Web Frontend (Next.js) │ (:3000)
                    └───────────┬─────────────┘
                                │ REST / SSE
                                ▼
┌──────────────┐     ┌─────────────────────────┐     ┌───────────────────────┐
│ Telegram Bot │◄───►│  Backend API (FastAPI)  │◄───►│ Playwright Sandbox    │
└──────────────┘     └───────────┬─────────────┘     │ (Render, Screenshots) │
                                │ SQLAlchemy        └───────────────────────┘
                                ▼
                     ┌────────────────────────┐
                     │ PostgreSQL 16 (DB)     │ (:5432)
                     └────────────────────────┘
```

---

## 🚀 Khởi Chạy Nhanh (Local Docker)

### 1. Chuẩn bị biến môi trường
Sao chép `.env.example` thành `.env` và cung cấp API Key OpenRouter:
```bash
cp .env.example .env
```
Điền key vào `.env`:
```env
OPENROUTER_API_KEY=sk-or-v1-...
DATABASE_URL=postgresql+asyncpg://postgres:postgres@postgres:5432/hub_ai_agent
INTERNAL_SANDBOX_URL=http://sandbox:8001
TELEGRAM_BOT_TOKEN=...
```

### 2. Khởi chạy toàn bộ hệ thống
```bash
docker compose up -d --build
```

Sau khi các container sẵn sàng:
- **Web UI**: [http://localhost:3000](http://localhost:3000)
- **API Documentation (Swagger)**: [http://localhost:8000/docs](http://localhost:8000/docs)
- **Playwright Sandbox**: [http://localhost:8001/health](http://localhost:8001/health)

---

## 🧪 Chạy Kiểm Thử (Full Test Suite)

Toàn bộ 37 ca kiểm thử bảo mật, tool calling và kịch bản toàn tuyến:
```bash
docker exec hub_ai_api pytest
```

---

## 📂 Cấu Trúc Mã Nguồn

```
├── .agents/              # AI rules, agent guidelines & specs
├── agents/               # 4 agents chuyên biệt & router rules
├── packages/
│   ├── api/              # FastAPI Backend, ReAct loop & Tool Registry
│   │   ├── app/api/      # API endpoints (projects, threads, agents, approvals...)
│   │   ├── app/tools/    # Tool calling (file, render, seo, web, memory...)
│   │   └── tests/        # 37 Unit & Integration test cases
│   ├── sandbox/          # Microservice Playwright (Screenshots, Visual Diff)
│   └── web/              # Next.js 14 App Router, Dark Theme & Glassmorphism
└── docker-compose.yml    # Docker Compose phối hợp 4 services
```

---

## 🛡️ Bản quyền & Tiêu chuẩn Phát triển
Phát triển bởi đội ngũ kỹ thuật **Align**. Tuân thủ nghiêm ngặt quy tắc:
- File code < 300 dòng, function < 50 dòng.
- Bảo mật kép và không cho phép thực thi tùy tiện trên môi trường production.
