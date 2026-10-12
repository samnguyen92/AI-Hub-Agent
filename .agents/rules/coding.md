# Coding Conventions & Rules

## 1. Giới hạn độ dài và phân tách Module
- **File size**: Tối đa 300 dòng code cho một file. Khi file đạt khoảng 250 dòng, chủ động tách thành các module/sub-components nhỏ.
- **Function/Method size**: Tối đa 50 dòng code. Chia nhỏ logic thành các helper functions thuần túy (pure functions) để dễ unit test.
- **Single Responsibility Principle**: Mỗi module/class/hàm chỉ làm đúng 1 nhiệm vụ rõ ràng.

## 2. Tiêu chuẩn Python (Backend)
- **Formatting & Style**: Tuân thủ chuẩn PEP 8.
- **Type Hints**: 100% hàm và method phải có type annotations đầy đủ cho tham số và kiểu trả về.
- **Pydantic**: Sử dụng Pydantic v2 cho request/response models và config validation.
- **Error Handling**:
  - Không bắt exception chung chung (`except Exception:` hoặc `except:` trần).
  - Bắt các lỗi cụ thể (`ValueError`, `HTTPException`, v.v.).
  - Ghi log chi tiết khi xảy ra lỗi bất thường.
- **Import Order**:
  1. Standard library (vd: `os`, `sys`, `typing`)
  2. Third-party packages (vd: `fastapi`, `pydantic`, `sqlalchemy`)
  3. Local app imports (vd: `app.core.config`, `app.db.models`)

## 3. Tiêu chuẩn TypeScript / React (Frontend)
- **Framework**: Next.js App Router.
- **TypeScript**: Bật strict mode (`strict: true`). Tuyệt đối không dùng kiểu `any` (sử dụng `unknown` nếu chưa rõ kiểu và ép kiểu an toàn).
- **Component Design**:
  - Tách UI components thành dumb/presentational components và smart/container components (hooks).
  - Tránh viết nested components quá sâu (> 3 cấp).
  - Đặt tên component theo PascalCase, hooks theo camelCase (`use...`).
- **Styling**: Vanilla CSS / CSS Modules hoặc TailwindCSS có kiểm soát, thiết kế UI hiện đại, responsive mobile-first.

## 4. Bảo mật ứng dụng
- **Path Traversal Protection**: Mọi thao tác đọc/ghi file trong workspace bắt buộc phải validate đường dẫn chuẩn (`os.path.abspath`, kiểm tra `startswith` của root thư mục cho phép).
- **Chống SSRF**: Bất kỳ tool nào fetch URL từ bên ngoài phải lọc IP nội bộ (`127.0.0.1`, `10.0.0.0/8`, `192.168.0.0/16`, `169.254.169.254`, v.v.).
- **Không thực thi mã tùy ý**: Không sử dụng `eval()`, `exec()` hoặc shell command injection từ input người dùng.
