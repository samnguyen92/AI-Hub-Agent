# Hướng Dẫn Vận Hành Local Agent CLI (Align Theme)

Tài liệu này định nghĩa giao thức chuẩn để AI Agent tương tác trực tiếp với Database WordPress và tệp đĩa theo đúng luồng của Align Builder.

---

## 1. Kiến Trúc Phân Tách Dữ Liệu (SOT Architecture)

| Thành Phần | Nguồn Chân Lý (SOT) | Cơ Chế Nạp & Đồng Bộ |
| :--- | :--- | :--- |
| **HTML Body** | `wp_posts.post_content` (DB) | WordPress đọc trực tiếp từ database khi biên tập hoặc hiển thị ngoài frontend. |
| **CSS tạo kiểu** | `assets/builder/style-*.css` (Đĩa) | `template-assets-loader.php` tự động nạp thẻ `<link rel="stylesheet">` ngoài frontend. |
| **JS điều khiển** | `assets/builder/script-*.js` (Đĩa) | `template-assets-loader.php` tự động nạp thẻ `<script>` ở chân trang. |
| **Block Template** | DB (`wp_template`) + `templates/*.html` (Đĩa) | **Đồng bộ kép:** Cập nhật đồng thời DB và tệp tĩnh trên đĩa để chống ghi đè tại `wp_loaded`. |

---

## 2. Nguyên Tắc Dynamic Shortcode & Block Discovery

Mỗi website WordPress có thể sở hữu hệ thống block và shortcode riêng biệt:
1. **Không giả định trước:** AI không mặc định một danh sách shortcode cố định cho mọi dự án.
2. **Thứ tự ưu tiên khối:**
   - **WordPress Core Blocks:** `core/post-title`, `core/post-featured-image`, `core/post-content`.
   - **Reusable Blocks (`wp_block`):** Quét bảng `wp_posts` theo post_type `wp_block` để lấy đúng ID hoặc slug.
   - **Custom Shortcodes:** Quét mảng `$shortcode_tags` toàn cục để xác nhận hàm xử lý trước khi nhúng.
3. **Bọc khối chuẩn:** Mọi mã HTML bên ngoài đưa vào template phải nằm trong comment block hợp lệ (`<!-- wp:group -->`, `<!-- wp:align/custom-html -->`).

---

## 3. Bảng Lệnh CLI Bridge (`align/bin/agent-page`)

Mọi thao tác được thực thi qua tập lệnh CLI bridge tự phát hiện môi trường PHP và MySQL socket của LocalWP:

```bash
# Xem danh mục bài viết và template
align/bin/agent-page list [page|post|wp_block]          # Liệt kê bài viết kèm template đang dùng
align/bin/agent-page template-list                       # Liệt kê toàn bộ template FSE trong hệ thống

# Quản lý Template FSE
align/bin/agent-page template-create <slug> "<title>"    # Tạo mới template trong DB và theme.json
align/bin/agent-page template-assign <id> <template>     # Gán template cho bài viết hoặc trang
align/bin/agent-page template-delete <slug>              # Xóa template khỏi DB và theme.json

# Luồng biên tập nội dung an toàn (Pull - Edit - Push)
align/bin/agent-page pull <slug|id>                      # Rút HTML ra content.html, CSS/JS ra tệp tương ứng
align/bin/agent-page push <slug|id>                      # Đẩy dữ liệu đã sửa vào DB và đồng bộ tệp đĩa
align/bin/agent-page publish <slug|id>                   # Xuất bản bài viết trực tiếp qua DB

# Kiểm tra và dọn dẹp
align/bin/agent-page preview <slug|id>                   # In thông tin URL live để kiểm thử
align/bin/agent-page rollback <slug|id>                  # Khôi phục nguyên trạng từ backup.json
align/bin/agent-page cleanup <slug|id>                   # Xóa sạch thư mục workspace tạm sau khi xong
```

---

## 4. Cơ Chế An Toàn & Bảo Vệ Dữ Liệu (Safety Guards)

1. **Auto-Backup:** Khi chạy lệnh `pull`, hệ thống tự động ghi lại trạng thái gốc vào `backup.json`.
2. **Bypass KSES trong CLI:** Khi cập nhật `wp_template`, CLI tháo bộ lọc `kses_remove_filters()` để bảo toàn nguyên vẹn cấu trúc comment block Gutenberg.
3. **Flushing Cache:** Mọi lệnh ghi dữ liệu đều tự động kích hoạt `clean_post_cache($id)`.
4. **Fallback ID & Slug:** Hệ thống tự động phân giải qua lại giữa Post ID và Post Slug, ngăn chặn lỗi trôi đường dẫn khi chuyển trạng thái bài viết từ nháp sang xuất bản.
