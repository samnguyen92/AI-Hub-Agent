# Git & Version Control Conventions

## 1. Commit Message Guidelines
Áp dụng quy ước Conventional Commits:
`<type>(<scope>): <mô tả ngắn bằng tiếng Anh hoặc tiếng Việt>`

Các `type` chuẩn:
- `feat`: Tính năng mới
- `fix`: Sửa lỗi
- `docs`: Tài liệu hướng dẫn
- `style`: Định dạng code, spacing, không ảnh hưởng logic
- `refactor`: Tái cấu trúc code (không thêm tính năng, không sửa bug)
- `test`: Viết thêm hoặc sửa unit/integration tests
- `chore`: Cập nhật cấu hình build, dependency, scripts

Ví dụ:
- `feat(api): add OpenRouter client with function calling`
- `fix(workspace): prevent path traversal in read_file tool`
- `docs(rules): initialize coding conventions and AI behavior rules`

## 2. Quy tắc Commit
- Commit theo từng đơn vị công việc hoàn chỉnh (atomic commits).
- Không gom nhiều tính năng hoặc sửa đổi không liên quan vào 1 commit lớn.
- Trước khi commit, đảm bảo code compile được và không có syntax error.

## 3. Git trong Workspace của Agent
- Mỗi dự án người dùng thao tác sẽ có workspace git riêng biệt trong volume dữ liệu.
- Agent tự động commit sau mỗi bước hoàn thành (`save_artifact`, `apply_patch`), cho phép người dùng rollback hoặc so sánh diff giữa các versions.
