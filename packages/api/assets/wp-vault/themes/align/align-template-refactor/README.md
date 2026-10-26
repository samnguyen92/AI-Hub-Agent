# 🚀 Align Template Refactor Skill & AI Engine

Bộ công cụ & Skill chuyên dụng cho AI Agent và lập trình viên để tự động tiếp nhận một file **`template.zip`** chứa mã nguồn HTML thô (hoặc thư mục HTML tĩnh bất kỳ), phân tích ngữ nghĩa thông minh và tái cấu trúc thành file ZIP chuẩn **Align Theme Importer**.

---

## 📦 Cấu Trúc Gói Skill

```text
align/align-template-refactor/
│
├── 📄 SKILL.md                          (Hệ thống Prompt & Quy chuẩn vận hành cho AI)
├── 📄 README.md                         (Hướng dẫn sử dụng nhanh)
├── 📄 TEMPLATE_ZIP_RULES.md             (Quy chuẩn cấu trúc thư mục chi tiết)
│
└── 📁 scripts/
    ├── ⚡ ai-refactor-zip.js            (AI-Powered: Kết nối LLM bóc tách thông minh Header/Footer/Blocks/Overwrite)
    ├── ⚡ refactor-zip.js               (Rule-based: Nhận file .zip thô -> Xuất ra .zip chuẩn Align)
    └── ⚡ scan-and-refactor.js          (Rule-based: Nhận thư mục thô -> Xuất ra thư mục chuẩn Align)
```

---

## ⚡ 1. Sử Dụng Trực Tiếp Bằng Dòng Lệnh (CLI)

### 🤖 Cách 1: Sử dụng AI-Powered Engine (Khuyến nghị - Thông minh & Chuẩn xác nhất):
Sử dụng model AI (mặc định proxy local `gemini-3.7-flash-high` tại `http://127.0.0.1:8317/v1/chat/completions`):
```bash
node align/align-template-refactor/scripts/ai-refactor-zip.js "path/to/my-raw-template.zip"
```
➡️ Tự động tạo file: `my-raw-template-align-ready.zip` với Header, Footer, Reusable Blocks (đã gán `data-allow-overwrite`) và shortcode chuẩn xác 100%!

### ⚡ Cách 2: Sử dụng Rule-based Refactor (Không cần mạng / API):
```bash
node align/align-template-refactor/scripts/refactor-zip.js "path/to/my-raw-template.zip"
```

### Chuyển đổi từ thư mục sang thư mục:
```bash
node align/align-template-refactor/scripts/scan-and-refactor.js "path/to/raw-folder" "path/to/output-folder"
```

---

## 🤖 2. Quy Trình Xử Lý Tự Động:
1. Giải nén và giữ nguyên 100% CSS/JS/Fonts/Images trong `assets/`.
2. Trích xuất `<header>` vào `header/index.html`.
3. Trích xuất `<footer>` vào `footer/index.html`.
4. Tìm các khối lặp lại (CTA banner, Pricing table, FAQ, Testimonials...) đưa vào `reuse-blocks/{slug}/index.html` và tự động gắn `data-allow-overwrite="true"` + `data-overwrite-id="ov-..."`.
5. Thay thế các vị trí đó trong trang bằng `[reuse-block slug="{slug}"]`.
6. Cắt sạch khung HTML ngoài và lưu thân trang vào `{slug}/index.html` (`home/`, `about-us/`, `contact/`...).
7. Đóng gói lại thành file `.zip` hoàn chỉnh sẵn sàng nạp vào WordPress!
