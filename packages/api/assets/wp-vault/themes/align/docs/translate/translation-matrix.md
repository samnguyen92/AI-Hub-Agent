# 📊 Ma Trận Dịch Thuật (Translation Matrix Dashboard)

Tài liệu này giới thiệu trung tâm quản lý dịch thuật tập trung **Translation Matrix Dashboard** trên trang quản trị Align Settings.

---

## 🎯 1. Mục Đích & Ý Nghĩa

Khi website mở rộng ra nhiều ngôn ngữ (Anh, Trung, Pháp, Nhật...), việc kiểm tra thủ công xem trang nào đã dịch, trang nào chưa dịch trở nên cực kỳ phức tạp và dễ sót.

**Translation Matrix Dashboard** sinh ra để giải quyết triệt để vấn đề này:
- Cung cấp **góc nhìn tổng thể (Bird’s-eye view)** toàn bộ nội dung website (Pages, Posts, Custom Post Types).
- Hiển thị ma trận dạng bảng với các cột tương ứng với từng ngôn ngữ đang kích hoạt.
- Thao tác nhanh: Tạo bản dịch mới, mở trình biên tập hoặc chạy dịch tự động AI chỉ với 1 click.

---

## 📍 2. Vị Trí & Kiến Trúc Mã Nguồn

- **Đường dẫn truy cập**:  
  `WP Admin` $\rightarrow$ `Align Settings` $\rightarrow$ Tab `Translation Matrix`  
  *(URL: `/wp-admin/admin.php?page=align-settings&tab=translation-matrix`)*
- **Thành phần mã nguồn**:
  - Controller: [`align/core/includes/admin/class-align-translation-matrix.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/class-align-translation-matrix.php)
  - Giao diện (View): [`align/core/includes/admin/views/tab-translation-matrix.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/views/tab-translation-matrix.php)

---

## 🗃️ 3. Các Thành Phần Chính Trên Giao Diện

```text
┌───────────────────────────────────────────────────────────────────────────────────┐
│  Translation Matrix Dashboard                                                     │
│  [ Filter: All Content ▼ ]   [ Search title...       ]   [ Status: All ▼ ]       │
├──────────────────────┬──────────────────────┬─────────────────────────────────────┤
│ Nội dung gốc (EN 🇺🇸)  │ Tiếng Trung (ZH 🇨🇳)   │ Trạng thái & Thao tác               │
├──────────────────────┼──────────────────────┼─────────────────────────────────────┤
│ About Us             │ 关于我们              │ [✔ Đã dịch] [✏ Sửa] [👁 Xem]         │
│ Contact Sales        │ [Chưa có bản dịch]    │ [➕ Tạo bản dịch] [🤖 AI Dịch nhanh] │
│ Critical Illness...  │ 重疾险定义解读        │ [✔ Đã dịch] [✏ Sửa]                 │
└──────────────────────┴──────────────────────┴─────────────────────────────────────┘
```

### 3.1. Bộ Lọc Thông Minh (Filters & Search)
- **Lọc theo loại nội dung**: Toàn bộ (`all`), Trang tĩnh (`page`), Bài viết (`post`).
- **Lọc theo trạng thái**: Đã dịch đầy đủ (`complete`), Thiếu bản dịch (`missing`).
- **Tìm kiếm theo từ khóa**: Tìm nhanh theo tiêu đề trang hoặc slug.

### 3.2. Trạng Thái Bản Dịch (Status Chips)
- 🟢 **Translated (Đã dịch)**: Đã có bài viết tương ứng liên kết trong cùng `_align_lang_group`.
- ⚪ **Missing (Chưa dịch)**: Chưa có bản dịch cho ngôn ngữ tương ứng.
- 🟡 **Draft (Bản nháp)**: Bản dịch đã được tạo nhưng đang ở trạng thái nháp, chưa xuất bản công khai.

### 3.3. Các Nút Thao Tác Nhanh (Quick Actions)
- **Nút Tạo Bản Dịch (`+`)**: Tự động nhân bản bài gốc, gán đúng `_align_lang_group` và chuyển hướng đến màn hình biên tập.
- **Nút Biên Tập (`✏`)**: Mở trực tiếp bài viết trong Gutenberg hoặc Visual Builder.
- **Nút AI Translate (`🤖`)**: Kích hoạt dịch tự động toàn bộ nội dung qua AI microservice.

---

## ⚡ 4. Tối Ưu Hiệu Năng Truy Vấn (Performance)

Để bảng ma trận luôn tải mượt mà ngay cả khi website có hàng nghìn bài viết:
1. **Phân trang dữ liệu (Pagination)**: Truy vấn mặc định giới hạn 20-50 mục/trang.
2. **Batch Query Meta**: Thay vì lặp `get_post_meta()` từng bài, controller tải toàn bộ dữ liệu `_align_lang` và `_align_lang_group` theo lô (batch) trong một truy vấn duy nhất.
3. **Zero Heavy SQL JOINs**: Tận dụng cơ chế index của WordPress và hạn chế tối đa các câu lệnh subquery nặng.
