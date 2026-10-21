# Quy ước dự án Hub AI Agent (Align)

## 1. Kiến trúc tổng quan
- **Backend**: FastAPI (`packages/api/`)
- **Frontend**: Next.js 14+ App Router (`packages/web/`)
- **Database**: PostgreSQL
- **Orchestration / Worker**: Python service (`packages/api/app/services/` & `worker.py`)
- **Agent Definitions**: Cấu hình tách biệt trong `agents/<agent_key>/` (agent.yaml, rules.md, skills/)
- **Skills chung**: `skills/shared/`
- **Output Schemas**: `schemas/`
- **Environment**: Local-first với Docker Desktop

## 2. Quy tắc Coding cốt lõi (Bắt buộc)
- **Kích thước file**: Mỗi file **KHÔNG quá 300 dòng**. Nếu phình to, tách file / module con ngay lập tức.
- **Kích thước function**: Mỗi function / method **KHÔNG quá 50 dòng**. Đảm bảo single responsibility.
- **Ngôn ngữ code**: Đặt tên code, biến, hàm, class, types hoàn toàn bằng tiếng Anh.
- **Type Safety**:
  - Python: Type annotations đầy đủ (Pydantic v2 cho schema, SQLAlchemy 2.0 type mapping).
  - TypeScript: Strict mode, cấm dùng `any` bừa bãi.
- **Docstrings & Comments**: Mỗi file / class / API endpoint phải có chú thích ngắn gọn mục đích và cách dùng.
- **Test Data Cleanup**: Các bài test (pytest) BẮT BUỘC phải dọn dẹp sạch sẽ project/user tạo ra, không được commit project rác làm ô nhiễm database và giao diện Web UI.

## 3. Điều cấm kỵ (Strict Don'ts)
- ❌ **KHÔNG tự bịa API, library hay dependencies** mà không có trong tech-stack hoặc chưa được xác nhận.
- ❌ **KHÔNG viết code tạm bợ (quick dirty hack)** với lời hứa "sau này refactor".
- ❌ **KHÔNG hardcode secrets, token, API keys, credentials** vào code. Luôn đọc từ environment variables.
- ❌ **KHÔNG bỏ qua error handling** hoặc `except Exception: pass`.
- ❌ **KHÔNG thực hiện hành động ghi đè nguy hiểm** (force push, drop database, rm -rf ngoài thư mục chỉ định).
- ❌ **KHÔNG để sót dữ liệu test rác** trong database sau khi chạy kiểm thử.

## 4. Hành vi của AI khi làm việc
- **Thắc mắc phải hỏi ngay**: Nếu yêu cầu không rõ, business logic mơ hồ hoặc có nhiều phương án kỹ thuật khả thi, PHẢI dùng công cụ hỏi người dùng làm rõ trước khi code.
- **Truy vết trước khi sửa (Traceback first)**: Đọc và hiểu các file liên quan trước khi sửa, tránh sửa mù quáng gây regression.
- **Giải thích có trọng tâm**: Khi sửa code, tóm tắt chính xác lý do và những điểm đã thay đổi.

## 5. Lệnh chạy và kiểm thử (Cheat Sheet)
```bash
# Chạy local với docker compose
docker compose up -d

# Xem logs
docker compose logs -f api
docker compose logs -f web

# Backend Tests (Tự động cleanup sau test)
pytest packages/api/tests/

# Dọn dẹp thủ công toàn bộ demo/test project rác trong database
python packages/api/scripts/cleanup_test_data.py
# Hoặc chạy trong docker:
docker exec hub_ai_api python scripts/cleanup_test_data.py

# Database migration (Alembic)
alembic upgrade head
```
