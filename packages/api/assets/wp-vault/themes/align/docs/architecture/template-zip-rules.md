# 📦 Quy Chuẩn Cấu Trúc Thư Mục File ZIP Template Upload (Align Theme)

Tài liệu này định nghĩa toàn bộ quy tắc, tiêu chuẩn cấu trúc thư mục, quy ước đặt tên và cơ chế xử lý tự động khi tải lên file `.zip` giao diện HTML tĩnh vào hệ thống **Align HTML Project ZIP Importer** (`Align Settings -> Import HTML ZIP`).

---

## 📑 Mục Lục
1. [Tổng Quan & Nguyên Tắc Cơ Bản](#1-tổng-quan--nguyên-tắc-cơ-bản)
2. [Cấu Trúc Thư Mục Tiêu Chuẩn (Folder-Per-Page Hierarchy)](#2-cấu-trúc-thư-mục-tiêu-chuẩn-folder-per-page-hierarchy)
3. [Cấu Trúc Thư Mục Phẳng Hỗ Trợ (Flat Structure)](#3-cấu-trúc-thư-mục-phẳng-hỗ-trợ-flat-structure)
4. [Quy Tắc Đặt Tên & Nhận Diện Tự Động (Auto-Detection Rules)](#4-quy-tắc-đặt-tên--nhận-diện-tự-động-auto-detection-rules)
5. [Cơ Chế Phân Loại & Xử Lý Chi Tiết](#5-cơ-chế-phân-loại--xử-lý-chi-tiết)
6. [Quy Tắc Xử Lý Media, Assets & Liên Kết Nội Bộ](#6-quy-tắc-xử-lý-media-assets--liên-kết-nội-bộ)
7. [Các Ràng Buộc Kỹ Thuật & Bảo Mật](#7-các-ràng-buộc-kỹ-thuật--bảo-mật)
8. [Checklist Kiểm Tra File ZIP Trước Khi Tải Lên](#8-checklist-kiểm-tra-file-zip-trước-khi-tải-lên)

---

## 🌟 1. Tổng Quan & Nguyên Tắc Cơ Bản

Hệ thống **Align ZIP Importer** tự động chuyển đổi file ZIP chứa mã nguồn HTML, CSS, JavaScript và Media thành:
- **WordPress Pages** (`post_type = 'page'`) độc lập với Scoped CSS/JS.
- **Global Header / Footer Template Parts** (`wp_template_part`) trong chuẩn Gutenberg FSE (`parts/header.html`, `parts/footer.html`).
- **Reusable Blocks / Synced Patterns** (`post_type = 'wp_block'`) kèm Shortcode nhúng tự động `[reuse-block id="..."]`.
- **Global Shared Assets** tự động tối ưu hóa và đăng ký nạp vào hệ thống.

### Định Dạng Archive:
- Định dạng nén: **`.zip`** tiêu chuẩn.
- Bảng mã ký tự: **UTF-8**.
- Hỗ trợ giải nén tự động cả file ZIP lồng thư mục gốc (Single Wrapped Folder) hoặc thư mục dự án (`dist/`, `public/`, `src/`, `html/`, `site/`, `template/`, `pages/`, `app/`).

---

## 📁 2. Cấu Trúc Thư Mục Tiêu Chuẩn (Folder-Per-Page Hierarchy)

> [!TIP]
> **Đây là cấu trúc được khuyến nghị nhất** để dự án đạt độ phân tách module rõ ràng, tự động sinh scoped CSS/JS riêng cho từng trang và block.

```text
my-template.zip
│
├── 📁 assets/                          <--- Tài nguyên dùng chung toàn site (Global Assets)
│   ├── 📁 css/
│   │   └── global.css                  (CSS dùng chung cho toàn bộ website)
│   ├── 📁 js/
│   │   └── global.js                   (JS dùng chung cho toàn bộ website)
│   ├── 📁 fonts/                       (Font chữ: .woff2, .woff, .ttf, .otf)
│   └── 📁 img/                         (Hình ảnh dùng chung: logo, favicon, icons...)
│
├── 📁 header/                          <--- 🔷 Global Header Part (Gutenberg FSE Header)
│   ├── 📄 index.html                   (Mã HTML thanh điều hướng, logo, topbar)
│   ├── 🎨 style.css                    (Scoped CSS riêng cho Header)
│   └── ⚡ script.js                   (Scoped JS riêng cho Header - tùy chọn)
│
├── 📁 footer/                          <--- 🔷 Global Footer Part (Gutenberg FSE Footer)
│   ├── 📄 index.html                   (Mã HTML chân trang, copyright, liên kết)
│   ├── 🎨 style.css                    (Scoped CSS riêng cho Footer)
│   └── ⚡ script.js                   (Scoped JS riêng cho Footer - tùy chọn)
│
├── 📁 reuse-blocks/                    <--- 🧱 Thư mục Reusable Blocks (Synced Patterns)
│   │
│   ├── 📁 cta-banner/                  (Block: CTA Banner -> Sinh shortcode [reuse-block id="..."])
│   │   ├── 📄 index.html               (Mã HTML của block)
│   │   ├── 🎨 style.css                (Scoped CSS riêng cho block)
│   │   └── ⚡ script.js               (Scoped JS riêng cho block)
│   │
│   └── 📁 pricing-table/               (Block: Bảng giá)
│       ├── 📄 index.html
│       ├── 🎨 style.css
│       └── 🖼️ check-icon.svg           (Ảnh nội bộ của block)
│
├── 📁 home/                            <--- 📄 WordPress Page: Trang Chủ (URL: /)
│   ├── 📄 index.html                   (Nội dung HTML phần Body trang chủ)
│   ├── 🎨 style.css                    (Scoped CSS riêng cho trang chủ)
│   ├── ⚡ script.js                   (Scoped JS riêng cho trang chủ)
│   └── 🖼️ hero-banner.jpg             (Ảnh riêng của trang chủ -> tự động sideload WebP)
│
├── 📁 about-us/                        <--- 📄 WordPress Page: Giới Thiệu (URL: /about-us/)
│   ├── 📄 index.html
│   ├── 🎨 style.css
│   └── 🖼️ team-photo.png
│
├── 📁 services/                        <--- 📄 WordPress Page: Dịch Vụ (URL: /services/)
│   ├── 📄 index.html
│   └── 🎨 style.css
│
├── 📁 vi/                              <--- 🌐 Thư mục ngôn ngữ tiếng Việt (URL: /vi/...)
│   ├── 📄 index.html                   (Nội dung trang chủ tiếng Việt -> Root Lang Page)
│   ├── 📁 about-us/                    (Trang giới thiệu tiếng Việt -> /vi/about-us/)
│   │   └── 📄 index.html
│   ├── 📁 blog/                        (Bài viết tiếng Việt -> /vi/<slug>/)
│   │   └── 📄 xin-chao.html
│   └── 📁 header/                      (Header tiếng Việt -> parts/header-vi.html)
│       └── 📄 index.html
│
└── 📁 contact/                         <--- 📄 WordPress Page: Liên Hệ (URL: /contact/)
    ├── 📄 index.html
    ├── 🎨 style.css
    └── ⚡ form-handler.js
```

---

## 📄 3. Cấu Trúc Thư Mục Phẳng Hỗ Trợ (Flat Structure)

Hệ thống ZIP Importer của Align được thiết kế thông minh (Smart Scanner) để hỗ trợ cả các template dạng phẳng (Flat HTML templates) mà không bắt buộc chia từng folder:

```text
flat-template.zip
│
├── 📄 index.html                       (Tự động nhận diện là Trang Chủ -> /)
├── 📄 about.html                       (Tự động tạo trang -> /about/)
├── 📄 services.html                    (Tự động tạo trang -> /services/)
├── 📄 contact.html                     (Tự động tạo trang -> /contact/)
│
├── 📄 header.html                      (Tự động nhận diện là Global Header Part)
├── 📄 footer.html                      (Tự động nhận diện là Global Footer Part)
│
├── 📄 block-cta-banner.html            (Tự động nhận diện là Reusable Block "cta-banner")
├── 📄 block-pricing.html               (Tự động nhận diện là Reusable Block "pricing")
│
├── 📁 css/
│   └── style.css                       (Tự động nạp vào Global Assets)
├── 📁 js/
│   └── main.js                         (Tự động nạp vào Global Assets)
└── 📁 images/
    ├── logo.png
    └── hero.jpg
```

---

## 🌳 3.1 Cấu Trúc Cây Trang Phân Cấp Cha/Con (Hierarchical Pages)

Align ZIP Importer hỗ trợ cây thư mục lồng nhau tới độ sâu `max_depth = 5`:

```text
hierarchical-project.zip
│
├── 📄 index.html                      (Trang chủ: URL /)
├── 📄 about.html                      (Trang giới thiệu: URL /about/)
│
├── 📁 services/
│   ├── 📄 index.html                  (Trang cha: URL /services/)
│   ├── 📄 seo.html                    (Trang con: URL /services/seo/, post_parent trỏ tới services)
│   └── 📁 web-design/
│       └── 📄 index.html              (Trang con: URL /services/web-design/, post_parent trỏ tới services)
│
└── 📁 products/
    ├── 📄 item-a.html                 (Trang con: URL /products/item-a/)
    └── 📄 item-b.html                 (Trang con: URL /products/item-b/)
                                       (Trang cha "products" tự động sinh draft placeholder nếu thiếu index.html)
```

### Quy Tắc Xử Lý Phân Cấp:
1. **Liên kết `post_parent`**: Mọi trang trong thư mục con được gán `post_parent` bằng ID của trang cha trực tiếp.
2. **Quy tắc Thư mục nhiều HTML (D-2)**: File HTML nằm trong thư mục con (vd: `services/seo.html`) trở thành trang con mang đường dẫn `/services/seo/`.
3. **Quy tắc Thiếu Trang Cha (D-1)**: Nếu thư mục con tồn tại mà không có trang cha tương ứng (vd: có `products/item-a.html` nhưng không có `products/index.html`), hệ thống tự tạo trang cha placeholder với trạng thái `draft`, nội dung rỗng và meta `_align_zip_placeholder = 1`. Trang con vẫn hoạt động bình thường ở chế độ `publish`.
4. **Viết lại liên kết nội bộ**: Thẻ `<a href="web-design/">` trong `services/index.html` hoặc `<a href="../seo.html">` tự động chuyển đổi thành permalink chính xác `/services/web-design/` và kèm thuộc tính `data-align-link-id`.

---

## 🔍 4. Quy Tắc Đặt Tên & Nhận Diện Tự Động (Auto-Detection Rules)

### 4.1 Quy Tắc Thư Mục & File:

| Thành Phần | Tên Thư Mục Hỗ Trợ | Tên File Hỗ Trợ | Kết Quả Sau Import |
| :--- | :--- | :--- | :--- |
| **Global Header** | `header/`, `headers/`, `template-header/`, `template-parts/header`, `parts/header` | `header.html`, `index.html` (trong folder header) | Xuất file FSE `parts/header.html` & wp_template_part |
| **Global Footer** | `footer/`, `footers/`, `template-footer/`, `template-parts/footer`, `parts/footer` | `footer.html`, `index.html` (trong folder footer) | Xuất file FSE `parts/footer.html` & wp_template_part |
| **Reusable Blocks** | `reuse-blocks/`, `reuse-block/`, `reusable-blocks/`, `blocks/`, `patterns/`, `sections/`, `components/` | Thư mục con hoặc file có tiền tố: `block-*.html`, `reuse-*.html`, `pattern-*.html` | Tạo post `wp_block`, lưu Scoped CSS/JS, cấp Shortcode `[reuse-block id="..."]` |
| **Global Assets** | `assets/`, `asset/`, `public/`, `static/`, `dist/`, `css/`, `js/`, `fonts/`, `img/`, `images/`, `styles/`, `scripts/`, `lib/`, `libs/`, `vendor/` | `*.css`, `*.js`, `*.woff2`, `*.png`, `*.jpg`, `*.svg`... | Tự động phân loại Global CSS/JS, Fonts, Media |
| **Blog Posts** | `blog/`, `blogs/`, `posts/`, `post/`, `news/`, `articles/`, `tin-tuc/`, `bai-viet/` (chỉ cấp gốc) | `*.html` (trừ `index.html` cấp gốc) | Tạo bài viết WordPress (`post_type = 'post'`), mode Align Builder |
| **Trang Blog Archive** | Thư mục blog cấp gốc như trên | `index.html` trong thư mục blog cấp gốc | Tạo Trang tĩnh WordPress `blog` (`post_type = 'page'`), HTML nguyên bản |
| **Trang Chủ (Home)** | `home/`, `trang-chu/`, `front-page/`, `index/` | `index.html`, `home.html`, `front-page.html` | Tạo Trang WordPress đặt làm Trang Chủ (`is_home = true`, URL: `/`) |
| **Trang Thường (Pages)** | Bất kỳ thư mục nào khác (ví dụ `about-us/`, `contact/`, `pricing/`) | `index.html` hoặc `{tên-thư-mục}.html` | Tạo Trang WordPress (`post_type = 'page'`) với slug tương ứng |

### 4.2 Định Dạng Tên (Slug & Title Formatting):
- **Tên thư mục / Tên file**: Viết thường, không dấu, sử dụng dấu gạch ngang (`kebab-case`). Ví dụ: `about-us`, `dich-vu-seo`, `bang-gia`.
- **Tự động chuyển đổi Tiêu Đề Trang**: Hệ thống tự động định dạng tên hiển thị:
  - `about-us` -> **About Us**
  - `san-pham-noi-bat` -> **San Pham Noi Bat**
  - `home`, `index`, `trang-chu` -> **Home**

### 4.3 Thư Mục Bị Bỏ Qua (Excluded Folders):
Các thư mục sau sẽ được tự động bỏ qua để tránh rác:
`node_modules/`, `.git/`, `standalone/`, `backup/`, `backups/`, `archive/`, `archives/`, `old/`, `test/`, `tests/`, `docs/`, `temp/`, `tmp/`.

---

## ⚙️ 5. Cơ Chế Phân Loại & Xử Lý Chi Tiết

### 5.1 Reusable Block (`wp_block`, Shortcode & Data Overwrite):
- Mỗi thư mục con trong `reuse-blocks/` (ví dụ `reuse-blocks/cta-box/`) sẽ tạo thành một Gutenberg Synced Pattern (`wp_block`).
- Scoped CSS/JS được lưu riêng biệt tại:
  - `assets/builder/style-block-{slug}.css`
  - `assets/builder/script-block-{slug}.js`
- **Shortcode Độc Lập Môi Trường (Slug & Instance)**:
  - Dùng `slug`: `[reuse-block slug="cta-box"]` (hoặc kèm instance `[reuse-block slug="cta-box" instance="home-cta"]`).
  - Hỗ trợ cả `id`: `[reuse-block id="123"]`.
- **Cơ Chế Ghi Đè Nội Dung Từng Trang (`data-allow-overwrite`)**:
  - Gắn thuộc tính `data-allow-overwrite="true"` và `data-overwrite-id="ov-..."` lên các thẻ Typography (`h1`-`h6`, `p`, `span`), Link (`a`), Button (`button`), hoặc Media (`img`).
  - Cho phép người dùng chỉnh sửa riêng tiêu đề/mô tả/ảnh trên từng trang qua thanh **Content Overwrite** trong Visual Builder mà vẫn giữ nguyên 100% bố cục và kiểu dáng chung của block gốc.

### 5.2 Scoped CSS & JavaScript Per-Page:
- Nếu trong thư mục trang `about-us/` có file `style.css` hoặc `script.js`, hệ thống sẽ lưu chúng dưới dạng asset riêng:
  - `assets/builder/style-page-{post_id}.css`
  - `assets/builder/script-page-{post_id}.js`
- Các asset này chỉ tải khi người dùng truy cập đúng trang tương ứng, **hoàn toàn không gây ô nhiễm CSS toàn trang**.

### 5.3 Nested Assets & Thư Viện Bên Thứ Ba:
- Các thư viện lồng sâu như `lib/easing/easing.min.js`, `lib/owlcarousel/owl.carousel.min.js`, `vendor/bootstrap/` được quét đệ quy và bảo toàn nguyên vẹn cấu trúc.
- **Ưu tiên tải jQuery**: Nếu phát hiện file jQuery hoặc thư viện phụ thuộc jQuery, hệ thống tự động gán vị trí tải tại `Header` với chiến lược `Normal` (đồng bộ), loại bỏ triệt để lỗi `Uncaught ReferenceError: jQuery is not defined`.

---

## 🖼️ 6. Quy Tắc Xử Lý Media, Assets & Liên Kết Nội Bộ

### 6.1 Sideload Media & Cloud WebP Conversion:
- Tất cả hình ảnh trong ZIP (`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`, `.svg`, `.ico`) sẽ được tự động sideload vào **WordPress Media Library**.
- Tự động gọi **Align Cloud API** để nén và chuyển đổi sang chuẩn `.webp` hiện đại.
- Tự động cập nhật thuộc tính `src`, `srcset`, thẻ `<source>` và đường dẫn `url(...)` trong file CSS.

### 6.2 Dummy Image Placeholder Fallback:
- Nếu trong mã HTML/CSS có thẻ `<img src="images/missing-banner.jpg">` mà file đó **không có trong ZIP**, hệ thống sẽ tự động tạo ảnh **Dummy Placeholder SVG/PNG** giữ đúng kích thước (ví dụ `800x400`), ngăn chặn vỡ layout và link ảnh hỏng (broken links).

### 6.3 Bảo Vệ Thẻ `<script>` Khỏi Rewrite Nhầm:
- Bộ lọc mở rộng bảo vệ tuyệt đối các file mã nguồn: `.js`, `.mjs`, `.css`, `.json`, `.woff`, `.woff2`. Tuyệt đối không bao giờ rewrite nhầm thẻ `<script src="assets/js/main.js">` thành link ảnh dummy.

### 6.4 Internal Link Resolution (Ánh Xạ Liên Kết Nội Bộ):
- Các liên kết tĩnh trong HTML như `href="about.html"`, `href="/about-us/"`, `href="contact.html"` sẽ được hệ thống chạy lượt quét thứ hai (Second Pass) để chuyển đổi tự động thành WordPress Permalinks thực tế (ví dụ: `https://yoursite.local/about-us/`).

---

## 🔒 7. Các Ràng Buộc Kỹ Thuật & Bảo Mật

1. **Chống Tấn Công Zip Slip**: Mọi entry trong file ZIP chứa ký tự nguy hiểm `../` hoặc `..\` sẽ bị hệ thống từ chối và hủy upload ngay lập tức.
2. **Cấm Tuyệt Đối File Thực Thi (Executable Blocklist)**:
   - 🚫 **Nghiêm cấm** chứa các file: `.php`, `.phtml`, `.phar`, `.exe`, `.sh`, `.cgi`, `.py`, `.pl`, `.htaccess`, `.env`.
   - Nếu phát hiện dù chỉ 1 file bị cấm, toàn bộ quá trình Import sẽ bị hủy bỏ với thông báo lỗi bảo mật.
3. **Giới Hạn Bộ Nhớ & Tệp Rác**:
   - Thư mục giải nén tạm (`wp-content/uploads/align-temp-*`) sẽ tự động được dọn dẹp sạch sẽ ngay sau khi quá trình import kết thúc.

---

## ✅ 8. Checklist Kiểm Tra File ZIP Trước Khi Tải Lên

Trước khi upload file ZIP lên trang quản trị Align, hãy kiểm tra danh sách sau:

- [ ] File nén có đuôi mở rộng là `.zip`.
- [ ] Không chứa file thực thi nguy hiểm (`.php`, `.sh`, `.exe`...).
- [ ] Mỗi thư mục trang có chứa ít nhất 1 file `index.html` (hoặc `{tên-thư-mục}.html`).
- [ ] Các thư mục Reusable Blocks nằm trong thư mục `reuse-blocks/` hoặc có tiền tố `block-*.html`.
- [ ] Header và Footer nằm đúng trong thư mục `header/` và `footer/` (hoặc file `header.html`, `footer.html`).
- [ ] Các đường dẫn asset trong HTML sử dụng đường dẫn tương đối (ví dụ `assets/css/style.css` hoặc `../assets/img/logo.png`).
- [ ] Đã xóa các thư mục rác không cần thiết (`node_modules/`, `.git/`, `.DS_Store`).

---

> [!NOTE]
> Bạn có thể tải trực tiếp file mẫu tiêu chuẩn bằng cách nhấn nút **📥 Download Sample Template ZIP** tại trang **Align Settings -> Tab 8: Import HTML ZIP** để tham khảo mã nguồn thực tế.
