# AI Behavior Rules (Quy tắc hành vi cho AI Agent)

Quy tắc này hướng dẫn mọi trợ lý AI (bao gồm Antigravity, Claude, hay các subagents) khi thao tác trên codebase này.

## 1. Nguyên tắc "Thắc mắc phải hỏi" (Ask When Unsure)
- Nếu một yêu cầu có thể hiểu theo 2 hướng khác nhau, **không được đoán mò**.
- Hãy dùng công cụ tương tác hỏi người dùng để xác nhận ý định trước khi tiến hành code lớn.
- Nếu thiếu input cần thiết (vd: token, API key, domain, style guide), nêu rõ điều cần và chờ người dùng cung cấp.

## 2. Nguyên tắc "Không bịa đặt" (No Hallucination)
- **Không tự sáng chế thư viện (libraries)**: Chỉ sử dụng các package chính thống và đã được khai báo trong requirements.txt / package.json.
- **Không tự chế API/Schema**: Luôn đối chiếu schema thực tế trong thư mục `schemas/` hoặc models trong `packages/api/app/db/models.py`.
- **Dữ liệu thật**: Tool phân tích (SEO, Site Analyzer) không được tự bịa số liệu mẫu khi phân tích thực tế, phải trích xuất chính xác từ output của crawler/Lighthouse.

## 3. Nguyên tắc "Traceback & Context Check" (Kiểm tra trước khi sửa)
- Trước khi chỉnh sửa bất kỳ module nào, AI phải đọc (`view_file` hoặc `grep_search`) các hàm/file phụ thuộc liên quan.
- Đảm bảo giữ nguyên các chức năng đang hoạt động (không gây breaking changes ngoài ý muốn).

## 4. Kiểm soát phạm vi thay đổi (Atomic Changes)
- Mỗi lần thay đổi code phải giải quyết một bài toán cụ thể, có thông điệp rõ ràng.
- Không tự ý xóa code hoặc refactor các module không liên quan đến tác vụ đang được giao.
- Sau khi viết/sửa code, phải chủ động kiểm tra lint, type-check hoặc run tests tương ứng để bảo đảm tính đúng đắn.

## 5. Nguyên tắc kiểm thử sạch sẽ (Clean Test Execution & Zero Garbage)
- Khi AI thực hiện kiểm thử (chạy pytest, integration tests, custom test scripts) hoặc bổ sung test cases:
  - Bắt buộc bảo đảm cơ chế cleanup tự động hoạt động, tuyệt đối không để lại các demo/test project rác trong cơ sở dữ liệu làm ô nhiễm giao diện người dùng.
  - Sau khi kiểm thử, nếu phát hiện database phát sinh project demo ngoài ý muốn, phải dọn dẹp ngay bằng script dọn dẹp hoặc lệnh DB trước khi bàn giao cho người dùng.
