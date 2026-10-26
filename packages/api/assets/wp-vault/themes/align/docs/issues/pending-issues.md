# Danh Sách Sự Cố Tạm Hoãn Xử Lý (Issues Pending / Not Fixed Yet)

> [!NOTE]
> Tài liệu này ghi nhận các sự cố kỹ thuật, vấn đề tương tác UI/UX hoặc giới hạn chưa xử lý ngay, được tạm hoãn để nghiên cứu và khắc phục trong các đợt refactor / cập nhật sau.

---

## 1. Không Thể Xóa Title Và Lưu Template Trực Tiếp Trong Gutenberg Site Editor (wp-admin/site-editor.php)

### 📌 Mô Tả Sự Cố
- **Triệu chứng**: Người dùng mở Gutenberg Site Editor (`wp-admin/site-editor.php`), chọn Block `Title` trong danh sách cấu trúc (**List View**) của `Pages · Template` và bấm xóa, sau đó chọn Save nhưng hệ thống không lưu được thay đổi hoặc không đồng bộ.
- **Thời gian ghi nhận**: 13/08/2026

### 🔍 Nguyên Nhân Gốc Rễ (Root Cause Analysis)
1. **Xung đột trình biên tập (Editor Conflict)**: Trang/Template được tạo và quản lý bởi **Align Builder** (Visual Builder riêng của Theme Align).
2. **Cơ chế Overlay & Chặn đồng bộ**: Script integration (`align-site-editor-integration.js`) tự động chèn overlay thông báo *"Designed with Align Builder. Please use Align Builder to edit and preserve the design."* và khóa bớt các thao tác can thiệp trực tiếp lên canvas Gutenberg.
3. **Quản lý Cấu trúc Template (FSE vs Align Builder)**: Block `Title` trong Gutenberg Site Editor đối với FSE Template không tự động phản ánh/được ghi đè chuẩn xác vào file HTML part/Database mà Align Builder quản lý khi lưu từ Gutenberg UI.

### 💡 Hướng Xử Lý Tạm Thời & Kế Hoạch Tiếp Theo (Workaround & Future Plan)
- **Thao tác tạm thời (Workaround)**: Người dùng bấm vào nút **"Edit with Align Builder"** trên popup/topbar để chuyển qua giao diện Align Builder, tiến hành xóa Title và bấm Save từ Align Builder.
- **Kế hoạch xử lý sau (Future Action)**:
  - Nghiên cứu bắt sự kiện lưu (`wp.data.subscribe`) từ Gutenberg Site Editor để đồng bộ 2 chiều (Bi-directional Sync) khi người dùng sửa/xóa `core/post-title` trong Gutenberg FSE.
  - Hoặc vô hiệu hóa/khóa thao tác xóa nút Title ở List View khi đang ở Gutenberg FSE và hiển thị Toast hướng dẫn chi tiết.

