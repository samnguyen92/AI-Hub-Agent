# 🛣️ Kiến Trúc URL Routing & Phân Cấp Ngôn Ngữ (Architecture & Routing)

Tài liệu này giải thích chi tiết cơ chế định tuyến URL (URL Routing), cơ chế phân cấp WordPress (Page Hierarchy), và các quy tắc Rewrite Rules cho hệ thống đa ngôn ngữ trong Theme Align.

---

## 🧭 1. Mô Hình Định Tuyến Tổng Thể (Routing Model)

Hệ thống Align phân tách rõ ràng 2 luồng xử lý URL tương ứng với 2 kiểu nội dung:

```text
                                  Yêu Cầu Truy Cập (Incoming URL)
                                                │
                 ┌──────────────────────────────┴──────────────────────────────┐
                 ▼                                                             ▼
       Trang Tĩnh (Pages)                                             Bài Viết (Posts / Insights)
    (Ví dụ: /zh/about-us/)                                     (Ví dụ: /zh/insights/tieu-de-bai-viet/)
                 │                                                             │
                 ▼                                                             ▼
   Native Page Hierarchy Router                                      Theme Custom Rewrite Rules
 (Parent: Page 'zh' ID -> Child)                             (^zh/insights/([^/]+)/?$ -> name=$1&align_lang=zh)
                 │                                                             │
                 ▼                                                             ▼
    Render Template Trang Con                                         Resolve Post ID & Query
```

---

## 📄 2. Định Tuyến Trang Tĩnh (Pages): Native Page Hierarchy

Khác với các plugin thông thường chèn rewrite rule phức tạp cho từng page, Align sử dụng chính **cơ chế phân cấp trang tự nhiên của WordPress**:

### Cơ Chế Hoạt Động:
1. Khi một ngôn ngữ mới được kích hoạt (ví dụ: `zh` - Tiếng Trung) với URL slug là `zh`, hệ thống gọi:
   `Align_Language_Manager::ensure_root_lang_page( 'zh' )`
2. WordPress tự động tạo một trang tĩnh ẩn mang tiêu đề `zh` và `post_name = 'zh'` (Root Lang Page).
3. Mọi trang con được dịch sang tiếng Trung (ví dụ trang Giới thiệu) sẽ được đặt `post_parent` trỏ về trang Root Page `zh`.
4. URL tự nhiên được sinh ra là:
   `https://yourdomain.com/zh/about-us/`
5. **Ưu điểm**:
   - Zero Custom Rewrite Rules: Không làm phình bảng `wp_options` `rewrite_rules`.
   - Tốc độ truy vấn cực nhanh (tận dụng cơ chế cache phân cấp trang sẵn có của lõi WordPress).
   - An toàn tuyệt đối, không sợ xung đột slug.

---

## 📰 3. Định Tuyến Bài Viết (Posts): Rewrite Rules & Query Vars

Bài viết (`post`) trong WordPress không có tính năng phân cấp cha-con (`post_parent = 0`). Do đó, Align sử dụng cơ chế **Dynamic Prefix Rewrite Rules**:

Trong [`class-align-post-language-meta.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/core/class-align-post-language-meta.php):

```php
public static function register_post_lang_rewrites() {
    $langs = Align_Language_Manager::get_languages();
    $prefix_pattern = 'insights/([^/]+)'; // Cấu hình tiền tố bài viết

    foreach ( $langs as $lang ) {
        if ( ! empty( $lang['is_default'] ) || empty( $lang['slug'] ) ) {
            continue;
        }
        $code       = $lang['code'];
        $slug_regex = preg_quote( $lang['slug'], '/' );

        // Quy tắc rewrite: ^zh/insights/([^/]+)/?$
        add_rewrite_rule(
            '^' . $slug_regex . '/' . $prefix_pattern . '/?$',
            'index.php?name=$matches[1]&align_lang=' . $code,
            'top'
        );
    }
}
```

### Bộ Lọc Query (`pre_get_posts`):
Khi truy vấn URL `/zh/insights/my-article/`:
1. WordPress nhận diện query vars: `name = 'my-article'`, `align_lang = 'zh'`.
2. Hàm `Align_Post_Language_Meta::resolve_post_by_lang_slug()` bắt hook `pre_get_posts`:
   - So khớp bài viết có slug `my-article` và meta `_align_lang = 'zh'`.
   - Nếu tìm thấy bài viết, WordPress nạp đúng bài viết tiếng Trung.

---

## ⚠️ 4. Sự Cố Phổ Biến & Gotchas: Lỗi 404 Sau Khi Deploy (Staging / Production)

> [!CAUTION]
> **Hiện Tượng Thực Tế**:
> - Truy cập: `https://staging.domain.com/zh/insights/bai-viet-zh/` $\rightarrow$ **HTTP 404 Not Found**.
> - Truy cập: `https://staging.domain.com/insights/bai-viet-zh/` $\rightarrow$ **HTTP 200 OK** (vào bình thường).

### Nguyên Nhân Gốc Rễ (Root Cause Analysis):
1. **Chưa Flush Rewrite Rules**: 
   - Hàm `add_rewrite_rule()` trong mã PHP **chỉ đăng ký tạm thời vào bộ nhớ runtime**.
   - WordPress chỉ thực thi các quy tắc này khi danh sách rule được lưu vào cơ sở dữ liệu (`wp_options` $\rightarrow$ `rewrite_rules`).
   - Nếu bạn deploy code mới lên Staging hoặc Production qua Git mà **chưa gọi `flush_rewrite_rules()`**, WordPress hoàn toàn không biết quy tắc `^zh/insights/...` tồn tại.
2. **Xung Đột Phân Cấp (Hierarchy Fallback Trap)**:
   - Khi không có rule `^zh/insights/...`, WordPress sẽ đoán đây là một trang tĩnh phân cấp:
     Tìm Page `zh` $\rightarrow$ tìm Page con `insights` $\rightarrow$ tìm Page cháu `bai-viet-zh`.
   - Vì `bai-viet-zh` là bài viết (post) chứ không phải page con, WordPress kết luận không tìm thấy trang $\rightarrow$ **Trả về 404**.
   - Khi bỏ `/zh/`, WordPress khớp ngay với rewrite rule mặc định `^insights/([^/]+)/?$` $\rightarrow$ tìm ra bài viết (200 OK).
3. **LiteSpeed Cache / Reverse Proxy Cache**:
   - Khi bị 404 lần đầu, server LiteSpeed hoặc Cloudflare sẽ gắn header cache 404 (`x-litespeed-tag: ..._HTTP.404`). Cần purge cache sau khi fix.

### Quy Trình Khắc Phục Chuẩn (Standard Fix Procedure):
- **Bước 1 (Nhanh nhất - 5 giây)**: Đăng nhập WP Admin $\rightarrow$ **Settings (Cài đặt)** $\rightarrow$ **Permalinks (Đường dẫn tĩnh)** $\rightarrow$ Cuộn xuống và bấm **Save Changes (Lưu thay đổi)**.
- **Bước 2 (Xóa Cache)**: Bấm Purge All Cache trên thanh quản trị LiteSpeed Cache / Nginx FastCGI Cache.
- **Bước 3 (Kiểm tra lại qua Terminal)**:
  ```bash
  curl -I "https://staging.trustedunion.com/zh/insights/critical-illness-insurance-definitions-zh/"
  ```
  Kết quả phải trả về **`HTTP/2 200`**.
