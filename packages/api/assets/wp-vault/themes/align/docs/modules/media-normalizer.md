# 🖼️ Media Filename Normalizer & Auto-Sanitization Engine

> **Vị trí mã nguồn:**
> - Core Engine: [`core/includes/core/class-align-media-normalizer.php`](../../core/includes/core/class-align-media-normalizer.php)
> - Admin Controller & Endpoints: [`core/includes/admin/class-align-media-normalizer-admin.php`](../../core/includes/admin/class-align-media-normalizer-admin.php)
> - View (Accordion Card): [`core/includes/admin/views/part-media-normalizer.php`](../../core/includes/admin/views/part-media-normalizer.php)
> - Client Script: [`core/assets/admin/js/align-media-normalizer.js`](../../core/assets/admin/js/align-media-normalizer.js)
> - Admin Styles: [`core/assets/admin/css/align-media-normalizer.css`](../../core/assets/admin/css/align-media-normalizer.css)

---

## 🎯 Bối Cảnh & Vấn Đề (Problem Statement)

Trong quá trình phát triển web trên môi trường cục bộ Windows (sử dụng hệ điều hành NTFS vốn không phân biệt hoa/thường - Case-Insensitive), tên tệp hình ảnh có thể chứa chữ hoa, khoảng trắng hoặc ký tự đặc biệt (ví dụ: `Door-Panel.webp`, `High-Back Front Seats.PNG`). Cơ sở dữ liệu và đường dẫn lưu trữ có thể lệch hoa thường so với tệp thực tế trên đĩa cứng mà trên Windows vẫn tải bình thường.

Tuy nhiên, khi website được sao lưu (All-in-One WP Migration, Duplicator, v.v.) và deploy lên môi trường Production (Linux ext4 / ext3 / apfs - Case-Sensitive), toàn bộ hình ảnh có tên tệp không khớp chữ hoa/thường sẽ lập tức bị lỗi **HTTP 404 Not Found**, gây vỡ layout và lỗi tải tài nguyên giao diện (như Kit Builder, Header, Gallery).

---

## 🚀 Tính Năng & Giải Pháp (Core Features)

Hệ thống **Align Media Normalizer** giải quyết triệt để vấn đề trên theo hai cơ chế song hành:

1. **Auto-Sanitization on Upload (Chặn từ đầu vào)**:
   - Tự động hook vào bộ lọc chuẩn của WordPress: `sanitize_file_name`.
   - Tự động chuyển đổi tên tệp upload mới về chữ thường toàn bộ (`lowercase`), loại bỏ dấu tiếng Việt/accent (`remove_accents`), phân tách CamelCase thành kebab-case (`DoorPanel` -> `door-panel`), chuyển khoảng trắng và ký tự đặc biệt thành dấu gạch ngang (`-`).
   - Bảo toàn extension an toàn.

2. **Batch Filename Normalizer (Chuẩn hóa hồi tố tệp cũ)**:
   - Cung cấp giao diện chuẩn hóa hàng loạt (Batch Runner) trong **Align Settings > Tab Media** và nút **Format Filenames** trực tiếp trên trang quản lý thư viện tệp **WordPress Media Library (`upload.php`)**.
   - Cung cấp hành động theo từng dòng (**Row Action: "Format Name"**) cho từng tệp cụ thể trong danh sách Media.
   - Hỗ trợ đổi tên nguyên tử hai bước an toàn trên hệ thống tệp Windows (**Two-Step Atomic Rename**).

---

## ⚙️ Kiến Trúc Kỹ Thuật (Technical Architecture)

### 1. Cơ Chế Rename Hai Bước Kháng Windows NTFS (`safe_rename`)
Trên hệ điều hành Windows, việc gọi `rename('Door-Panel.webp', 'door-panel.webp')` trực tiếp bằng PHP sẽ thất bại hoặc không thay đổi tên file trên đĩa vì NTFS coi hai đường dẫn này là một.

`Align_Media_Normalizer` sử dụng quy trình đổi tên nguyên tử:

Step 1: Door-Panel.webp  ==>  door-panel.webp.tmp_case_65f0a8c2 (tệp tạm trung gian)
Step 2: door-panel.webp.tmp_case_65f0a8c2  ==>  door-panel.webp (đích đến chữ thường)

Nếu bước 2 gặp lỗi, hệ thống tự động hoàn tác (rollback) tệp tạm về tên ban đầu.

### 2. Đồng Bộ Toàn Bộ Sub-Sizes & WebP Companions
Khi đổi tên một tệp đính kèm (`attachment_id`), hệ thống xử lý đồng bộ:
- Tệp chính trên đĩa (`_wp_attached_file`).
- Tệp gốc kích thước lớn (`original_image` trong metadata nếu WordPress tự động thêm hậu tố `-scaled`).
- Toàn bộ kích thước trung gian trong `_wp_attachment_metadata['sizes']` (ví dụ: `thumbnail`, `medium`, `48x48`, `644x644`, v.v.).
- Các tệp nén WebP đồng hành đi kèm nếu có (ví dụ plugin tạo tệp `.webp` hoặc `.jpg.webp`).
- Cập nhật các trường dữ liệu tương ứng trong Database: post meta `_wp_attached_file`, `_wp_attachment_metadata`, và cột `guid` trong bảng `wp_posts`.

### 3. Kháng Timeout & Tránh Tắc Nghẽn (Batch Resilience & Concurrency)
- **Sequential Chunking**: Client-side JS (`align-media-normalizer.js`) chia nhỏ danh sách ID thành từng yêu cầu AJAX đơn lẻ với khoảng nghỉ micro-delay 50ms giữa các request, tránh làm treo giao diện trình duyệt hoặc quá tải máy chủ shared hosting.
- **Transient Locks**: Sử dụng `set_transient('align_norm_lock_' . $id, 1, 60)` để ngăn chặn race condition khi nhiều admin cùng kích hoạt hoặc click nhiều lần.
- **Resource Limits**: Tự động tăng giới hạn bộ nhớ hình ảnh (`wp_raise_memory_limit('image')`) và thời gian thực thi (`set_time_limit(300)`).
- **Progress Tracking & Logs**: Lưu trữ tiến trình vào transient `align_norm_checkpoint` để có thể tiếp tục xử lý (Resume) nếu quá trình bị gián đoạn; nhật ký hoạt động trên giao diện được giới hạn vòng lặp 200 dòng (FIFO) để bảo vệ RAM trình duyệt.

---

## 📋 Hướng Dẫn Sử Dụng (User Guide)

### Cách 1: Chuẩn Hóa Toàn Bộ Media Thư Viện (Align Settings)
1. Đăng nhập WP Admin, vào menu **Align Settings > Media**.
2. Cuộn xuống mục **Media Filename Normalizer & Auto-Sanitization** (Card Accordion).
3. Click để mở Accordion, xem thống kê trạng thái thư viện.
4. Nhấn **Scan & Start Normalizing** để bắt đầu. Theo dõi tiến trình phần trăm và khung log chi tiết theo thời gian thực.

### Cách 2: Chuẩn Hóa Nhanh Từ WordPress Media Library (`upload.php`)
- **Từng tệp**: Chuyển Media Library sang chế độ hiển thị danh sách (List view), rê chuột vào tệp cần sửa và click vào liên kết **Format Name**.
- **Nhiều tệp chọn lọc**: Tích chọn các tệp cần đổi tên, chọn hành động hàng loạt **Format Filenames** từ menu Bulk Actions và nhấn **Apply**.
- **Toàn bộ tệp**: Nhấn nút **Format Filenames** trên thanh tiêu đề của `upload.php` để mở modal xử lý hàng loạt.

---

## 🔍 Chuẩn Hóa Sâu Toàn Diện Hệ Thống (Deep System Normalization)

Ngoài việc xử lý tệp upload qua giao diện Admin, hệ thống đã thực hiện chuẩn hóa đồng bộ 4 lớp:

1. **Lớp Tệp Vật Lý Trên Ổ Cứng (`wp-content/uploads/`)**:
   - Quét và chuyển đổi toàn bộ 385 tệp hình ảnh chữ hoa sang 100% lowercase kebab-case.
   - Cơ chế hai bước nguyên tử (`old_path` -> `.tmp_case_*` -> `lowercase_path`) giải quyết dứt điểm rào cản NTFS trên Windows.

2. **Lớp Mã Nguồn Theme (`align-child`)**:
   - Rà soát và chuẩn hóa mọi đường dẫn ảnh hardcoded trong `parts/header.html`, `parts/footer.html`, các file template và JS/CSS của `inc/kit-builder/`, `inc/builder-highlights/`.

3. **Lớp Dữ Liệu Cơ Sở Dữ Liệu (`wp_posts`, `wp_postmeta`, `wp_options`)**:
   - Từ điển thay thế 2,715 cặp chuỗi sắp xếp giảm dần theo độ dài chuỗi để tránh xung đột chuỗi con.
   - Cập nhật 183 bài viết trong `wp_posts`.
   - Đệ quy phân giải và tái tuần tự hóa (unserialize -> replace -> serialize) 49 bản ghi phức tạp trong `wp_postmeta` (`_product_color_variations`, `_product_bundle_tree`, `_product_angle_options`), đảm bảo 0% hỏng cấu trúc byte (`s:XX:`).

4. **Nghiệm Thu Toàn Diện (Full Site Crawl Audit)**:
   - Crawl tự động 12 trang cốt lõi của website, kiểm tra 110 tài nguyên ảnh unique.
   - Đạt tiêu chuẩn **0 ảnh chữ hoa trong HTML** và **0 lỗi HTTP 404** trên đĩa.

5. **Cơ Chế Kháng Ghi Đè Đa Tầng (Multi-Layer Collision Guard)**:
   - **WordPress Core Mapping (`image_editor_output_format`)**: Khai báo ánh xạ `jpeg`/`png`/`gif` sang `webp` để `wp_unique_filename()` tự động dò tìm sự tồn tại của file WebP trên đĩa và tự sinh hậu tố `-1`, `-2` ngay khi upload file trùng tên.
   - **Phòng Thủ 3 Chiều (`normalize_attachment`)**: Kiểm tra DB claim (`_wp_attached_file`), phân biệt file vật lý thực tế trên Windows NTFS (`realpath`), và kiểm tra tệp companion `.webp` để kích hoạt `wp_unique_filename()` chống gộp nhầm attachment.
   - **Bảo Vệ Kích Thước Trùng Lặp (Shared Dimensions Guard)**: Tự động tái sử dụng `candidate_webp` khi nhiều image sizes đăng ký cùng kích thước (ví dụ cùng `150x150`), triệt tiêu 100% nguy cơ lỗi 404 và tiết kiệm tài nguyên Cloud API.

