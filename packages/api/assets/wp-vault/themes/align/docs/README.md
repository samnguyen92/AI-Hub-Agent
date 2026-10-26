# Tài Liệu Hướng Dẫn & Kỹ Thuật Theme Align

Chào mừng bạn đến với trung tâm tài liệu kỹ thuật của **Theme Align**. Hệ thống tài liệu được phân loại khoa học thành 6 nhóm chuyên mục chính dưới đây để hỗ trợ lập trình viên và AI Agent tra cứu, vận hành và phát triển hệ thống nhanh chóng.

> [!IMPORTANT]
> **Quy Chuẩn Bắt Buộc Dành Cho AI Agent & Developer**: Mọi tác vụ phát triển trong hệ thống đều phải tuân thủ nghiêm ngặt theo [**`AGENTS.md` (Strict Development & Agent Execution Rules)**](../../AGENTS.md):
> 1. **Bước 0**: Bắt buộc đọc Docs & Manifest trước khi phân tích yêu cầu.
> 2. **RCA**: Phân tích, tìm chính xác file liên quan, debug tìm nguyên nhân gốc rễ (Root Cause) bằng chứng cớ thực tế (có thể dùng `curl` kiểm chứng).
> 3. **Anti-Hallucination**: Cấm overthinking, cấm ảo giác, cấm bịa code/hàm không tồn tại.
> 4. **Observability**: Bắt buộc thêm debug log chi tiết cho cả Backend (`error_log` có tag tiền tố) và Frontend (`console.log/error` có cấu trúc).

---

## Tra Cứu Nhanh Dành Cho AI Agent

| File | Mục Đích |
| :--- | :--- |
| [**`CONTEXT.md`**](./CONTEXT.md) | **Master Index** — Load 1 file này là AI hiểu toàn bộ hệ thống, biết ngay cần đọc file nào theo keyword của prompt. Bao gồm bảng cross-reference theo query pattern thường gặp. |
| [**`manifest.json`**](./manifest.json) | **Structured JSON Manifest** — Machine-readable index với keywords[], path, category, summary cho từng file. Dùng cho programmatic search hoặc script lookup. |

> [!TIP]
> **AI Workflow chuẩn**: Load `CONTEXT.md` một lần → tra bảng FILE INDEX theo keyword → đọc đúng file cần thiết. Không đọc toàn bộ docs khi không cần thiết.

---

## Bản Đồ Mục Lục Tài Liệu (Documentation Hub)

```text
align/docs/
├── CONTEXT.md                              <--- Master Index AI: load 1 file, lookup 360 degrees
├── manifest.json                           <--- Machine-readable keyword manifest (JSON)
├── README.md                               <--- Mục lục & Hướng dẫn tra cứu tổng quan (file này)
│
├── architecture/                           <--- [1] Kiến Trúc & Quản Trị Hệ Thống
│   ├── overview.md                         (Tổng quan kiến trúc Theme Align & 4 Module lõi)
│   ├── settings-guide.md                   (Hướng dẫn hệ thống Admin Settings & Subsystems)
│   ├── settings-style-guide.md             (Quy chuẩn thiết kế UI: Button transparent, Card, Accordion)
│   ├── html-zip-importer.md                (Hệ thống HTML Project ZIP Importer & Auto Assembly)
│   ├── template-zip-rules.md               (Quy chuẩn cấu trúc thư mục file ZIP template upload)
│   ├── git-updater.md                      (Hệ thống Git Theme Core Updater & Push-to-Deploy)
│   ├── git-workflow.md                     (Quy trình Git, Chuẩn commit & Push code)
│   ├── plugin-menu-integration-guide.md    (Quy chuẩn tích hợp menu cho Plugin hệ sinh thái Align)
│   └── local-agent-cli.md                  (Giao thức Local Agent CLI Bridge & SOT Database Direct Engine)
│
├── builder/                                <-- [2] Hệ Thống Align Visual Builder
│   ├── builder-guide.md                    (Hướng dẫn Visual Builder & Template Assets Loader)
│   ├── builder-deep-dive.md                (Tài liệu kiến trúc kỹ thuật chuyên sâu cho Dev/AI)
│   ├── builder-components-and-styles.md    (Danh mục Blocks, Components, Styles & Animations)
│   ├── deterministic-clean-state.md        (Kiến trúc Deterministic Clean State & Serializer)
│   ├── dynamic-block-studio-plan.md        (Kế hoạch Convert to Dynamic Block Studio với AI)
│   ├── align-studio-guide.md               (Tài liệu chuyên sâu Micro-Workspace Align Studio)
│   ├── gutenberg-embedded-builder.md       (Gutenberg Embedded Align Builder Canvas & Two-Way Save Bridge)
│   ├── word-editor-and-custom-templates.md (Word Editor Mode & Custom Layout Templates Subsystem)
│   ├── dynamic-studio-live-preview-arch-spec.md  (Stub - Architecture spec Live Preview)
│   └── responsive-media-shortcode-guide.md (Quy chuẩn shortcode responsive media, Ratio-Aware scoring & Tag Picker)
│
├── api/                                    <--- [3] API & Dịch Vụ Microservices
│   ├── align-cloud-api-reference.md        (Tài liệu Align Cloud Microservice API)
│   └── ai-rest-api-reference.md            (Tài liệu Dedicated AI REST API Endpoint)
│
├── translate/                              <--- [4] Da Ngon Ngu & Dich Thuat (Multilingual)
│   ├── README.md                           (Tổng quan phân hệ dịch thuật & bản đồ mã nguồn)
│   ├── architecture-and-routing.md         (Kiến trúc URL routing, rewrite rules, root page)
│   ├── post-and-page-management.md         (Quản lý bài viết, trang Builder & post meta)
│   ├── translation-matrix.md               (Translation Matrix Dashboard)
│   ├── bulk-ai-translator.md               (Dịch hàng loạt & tự động tạo AI SEO Rank Math)
│   └── seo-and-hreflang.md                 (Chuẩn SEO đa ngôn ngữ, hreflang, canonical)
│
├── modules/                                <--- [5] Các Module Mở Rộng & Tính Năng Chuyên Biệt
│   ├── checkout-page.md                    (Module Checkout Page & FluentCart)
│   ├── live-search.md                      (Block Live Search align/live-search)
│   └── media-normalizer.md                 (Media Normalizer & Auto-Sanitization Engine)
│
└── issues/                                 <--- [6] Sự Cố Kỹ Thuật, Bug Tracker & Kế Hoạch
    ├── bug-logs.md                         (Sổ tay Nhật ký lỗi & Bộ nhớ chống lặp lỗi / Anti-Regression Memory)
    ├── critical-issues-gotchas.md          (Sổ tay sự cố trọng yếu & Lưu ý kỹ thuật / Gotchas)
    ├── critical-security-fixes.md          (Bug & Lỗ hổng bảo mật cần fix F-01..F-06)
    ├── resolved-issues.md                  (Nhật ký 190+ sự cố kỹ thuật đã khắc phục — 900KB)
    ├── pending-issues.md                   (Danh sách sự cố tạm hoãn / Backlog)
    └── plan-sortablejs-swap.md             (Kế hoạch triển khai SortableJS Swap)
```

---

## 1. Kiến Trúc & Quản Trị Hệ Thống (`architecture/`)

Tài liệu nền tảng về cấu trúc theme, trang quản trị WP Admin và quy chuẩn phát triển mã nguồn:

| Tài liệu | Mô tả tóm tắt |
| :--- | :--- |
| [**`overview.md`**](./architecture/overview.md) | Tổng quan kiến trúc Hybrid/Block Theme Align, phân tích 4 module lõi (`core/blocks`, `core/inc`, `core/includes`, Cloud API) và triết lý thiết kế. |
| [**`settings-guide.md`**](./architecture/settings-guide.md) | Hướng dẫn cấu hình trang Admin Align Settings (8 Master Hubs: Site Protection, Visual Builder & AI, SEO & Languages, Performance, Developer Tools, Integrations, AI REST API, Git Updates), cơ chế Tab Persistence và non-blocking validation. |
| [**`settings-style-guide.md`**](./architecture/settings-style-guide.md) | **Quy chuẩn thiết kế giao diện (UI Design System & Style Guide)**: Button standard (100% transparent / không background), Card system, Accordion (default closed), Typography-First, Zero Emojis và Notice Isolation. |
| [**`html-zip-importer.md`**](./architecture/html-zip-importer.md) | Tài liệu kỹ thuật chi tiết hệ thống HTML Project ZIP Importer: Tự động nhận diện Header/Footer parts, Reusable Blocks (`wp_block`), sideload WebP media, dummy fallback, nested assets và nút tải starter template. |
| [**`template-zip-rules.md`**](./architecture/template-zip-rules.md) | Quy chuẩn chi tiết cấu trúc thư mục, quy ước đặt tên và checklist kiểm tra file ZIP template upload vào Align Importer. |
| [**`git-updater.md`**](./architecture/git-updater.md) | Tài liệu kỹ thuật chi tiết hệ thống Git Theme Core Updater: Tải zipball, xác thực GitHub API, WordPress Maintenance Mode, Clean Replace và Rollback Guard. |
| [**`git-workflow.md`**](./architecture/git-workflow.md) | Quy định cấu trúc repository Git tại `themes/`, danh mục file loại trừ trong `.gitignore` và quy trình 6 bước commit/push chuẩn. |
| [**`local-agent-cli.md`**](./architecture/local-agent-cli.md) | **Giao thức Local Agent CLI (`align/bin/agent-page`)**: Thao tác trực tiếp với Database WordPress, đồng bộ kép DB ↔ Đĩa, Dynamic Block Discovery, bypass KSES và bảo vệ dữ liệu. |

---

## 2. Hệ Thống Align Visual Builder (`builder/`)

Toàn bộ tài liệu kỹ thuật, thành phần giao diện và cơ chế vận hành của Visual Builder:

| Tài liệu | Mô tả tóm tắt |
| :--- | :--- |
| [**`builder-guide.md`**](./builder/builder-guide.md) | Hướng dẫn toàn diện Visual Builder Engine (`class-align-builder.php`) và Template Assets Loader, cấu trúc 8 cụm module hóa, và luồng REST API. |
| [**`builder-deep-dive.md`**](./builder/builder-deep-dive.md) | Bản đồ vị trí file chi tiết (Quick Location Map), phân tích sâu từng class PHP, modules JS, CSS modular architecture và UI Assembler dành cho Developers & AI Agents. |
| [**`builder-components-and-styles.md`**](./builder/builder-components-and-styles.md) | Danh mục chi tiết toàn bộ Base HTML components, Elements components, Bootstrap utilities, Styles & Visual controls, Animations (AOS, Swiper, GSAP) và các nhóm thuộc tính sidebar. |
| [**`deterministic-clean-state.md`**](./builder/deterministic-clean-state.md) | Kiến trúc Deterministic Clean State & Schema Serializer Engine (`align-serializer.js`) giúp loại bỏ rác từ Browser Extension mà vẫn bảo toàn 100% shortcodes, SVGs và datasets. |
| [**`dynamic-block-studio-plan.md`**](./builder/dynamic-block-studio-plan.md) | Tài liệu đặc tả kỹ thuật và lộ trình triển khai tính năng Convert to Dynamic Block Studio với 1-Click AI Auto-Bind, JS Client-side Inspector và Export Reusable Block. |
| [**`align-studio-guide.md`**](./builder/align-studio-guide.md) | **Tài liệu chuyên sâu hệ thống Align Studio (`admin.php?page=align-studio`)**: Micro-workspace độc lập, Live 2-way sync CodeMirror 6, Whitelisted Dynamic Tokens, 1-Click AI Auto-Bind (kèm custom prompt), SSR Token Hydration, và In-Place Update Reusable Block (`wp_block`). |
| [**`gutenberg-embedded-builder.md`**](./builder/gutenberg-embedded-builder.md) | **Gutenberg Embedded Align Builder Canvas & Two-Way Save Bridge**: Nhúng Iframe tràn viền 100% không cuộn, bảo toàn Topbar/Sidebar bản địa WordPress và Toolbar của Builder, Dynamic Fallback cho Blog/CPTs, và Two-Way Save Bridge. |
| [**`word-editor-and-custom-templates.md`**](./builder/word-editor-and-custom-templates.md) | **Word Editor Mode & Custom Layout Templates Subsystem**: Soạn thảo văn bản tự do chuẩn Word/Notion trên Blank Canvas, Floating Bubble Toolbar, Anti-Overlap Engine, và hệ thống quản lý/chỉnh sửa HTML, CSS, JS cho Custom Templates. |
| [**`responsive-media-shortcode-guide.md`**](./builder/responsive-media-shortcode-guide.md) | **Quy chuẩn xây dựng Shortcode Responsive Media**: Cấu trúc thẻ `<picture>`, `<source>` không tự đóng, thuật toán tiệm cận kích thước (Closest-to-Media), cơ chế `data-slot-width` và quy tắc vàng cho AI & Developers. |
| [**`export-import-page.md`**](./builder/export-import-page.md) | **Export / Import Page Package (ZIP)**: Đóng gói trang thành bộ file vật lý `index.html`, `page.css`, `page.js` với URL tuyệt đối, kháng Zip Slip, bảo toàn shortcodes & overrides, và xem trước trước khi Save. |

> [!TIP]
> Bản đồ ma trận chi tiết của hệ thống builder engine được định nghĩa tại [**`core/builder/MANIFEST.md`**](../core/builder/MANIFEST.md) và trung tâm tra cứu tại [**`core/builder/manifest/`**](../core/builder/manifest/README.md). Để tra cứu tiết kiệm token cho AI, sử dụng công cụ tìm kiếm ngữ nghĩa `node align/core/builder/manifest/search.js "<query>"` hoặc tra cứu trực tiếp qua [**`core/builder/manifest/manifest.jsonl`**](../core/builder/manifest/manifest.jsonl) / [**`manifest.json`**](../core/builder/manifest/manifest.json).

---

## 3. API & Dịch Vụ Microservices (`api/`)

Tài liệu tích hợp các dịch vụ API đám mây:

| Tài liệu | Mô tả tóm tắt |
| :--- | :--- |
| [**`ai-rest-api-reference.md`**](./api/ai-rest-api-reference.md) | **Đặc tả Dedicated AI REST API (`/wp-json/align-ai/v1/`)**: Dual-mode auth (WP Application Passwords & REST Nonce), phân rã 3 tầng (HTML/CSS/JS), CRUD pages phục vụ AI/Postman và tab quản trị Interactive REST Console. |
| [**`align-cloud-api-reference.md`**](./api/align-cloud-api-reference.md) | Đặc tả kỹ thuật đầy đủ cho Align Cloud API (`https://convertb.alignvn.cloud`): Convert HTML sang Gutenberg blocks, WebP compression, SCSS compiler, Minify/Unminify và Health check. |

---

## 4. Da Ngon Ngu & Dich Thuat (`translate/`)

Tài liệu chuyên sâu về phân hệ đa ngôn ngữ native, bảng điều khiển dịch thuật và chuẩn SEO quốc tế:

| Tài liệu | Mô tả tóm tắt |
| :--- | :--- |
| [**`README.md`**](./translate/README.md) | Tổng quan phân hệ đa ngôn ngữ, triết lý thiết kế (Native-First, Zero-Bloat) và bản đồ file mã nguồn PHP/JS. |
| [**`architecture-and-routing.md`**](./translate/architecture-and-routing.md) | Kiến trúc định tuyến URL: Phân cấp trang tĩnh (`/zh/...`), Rewrite Rules cho bài viết (`/zh/insights/...`) và cẩm nang xử lý lỗi 404 sau deploy (Flush Permalinks). |
| [**`post-and-page-management.md`**](./translate/post-and-page-management.md) | Quản lý bản dịch bài viết Gutenberg, sidebar Language panel, bộ lọc WP Admin, tích hợp Visual Builder và template parts. |
| [**`translation-matrix.md`**](./translate/translation-matrix.md) | Hướng dẫn sử dụng và cấu trúc kỹ thuật của Translation Matrix Dashboard trên trang Align Settings. |
| [**`bulk-ai-translator.md`**](./translate/bulk-ai-translator.md) | **Dịch Hàng Loạt & Tạo AI SEO Bằng AI**: Quy trình tuần tự 4 bước kháng timeout, dịch blocks bảo toàn style và tự động trích xuất SEO Metadata (Rank Math) cho toàn bộ trang/bài viết. |
| [**`seo-and-hreflang.md`**](./translate/seo-and-hreflang.md) | Tiêu chuẩn SEO đa ngôn ngữ: Thẻ `hreflang`, canonical URLs độc lập, bộ chuyển đổi ngôn ngữ `[align_language_switcher]` và giải đáp trùng lặp nội dung. |

---

## 5. Các Module Mở Rộng & Tính Năng Chuyên Biệt (`modules/`)

Tài liệu về các phân hệ tính năng mở rộng trong theme con (`align-child`):

| Tài liệu | Mô tả tóm tắt |
| :--- | :--- |
| [**`media-normalizer.md`**](./modules/media-normalizer.md) | **Media Filename Normalizer & Auto-Sanitization Engine**: Chuẩn hóa tên tệp upload (lowercase, kebab-case, accents removal), batch rename kháng Windows NTFS hai bước, đồng bộ sub-sizes/WebP và giao diện Media Library. |
| [**`checkout-page.md`**](./modules/checkout-page.md) | Kiến trúc module Checkout Page (`inc/checkout-page/`), tích hợp FluentCart, shortcodes, endpoints AJAX và cơ chế an toàn không phụ thuộc WooCommerce. |
| [**`live-search.md`**](./modules/live-search.md) | Block Live Search động (`align/live-search`): tìm kiếm real-time đa post type, lọc taxonomy, transient cache server-side, tích hợp Lenis smooth scroll, Inspector Controls Gutenberg. |

---

## 6. Su Co Ky Thuat, Bug Tracker & Ke Hoach (`issues/`)

Nhật ký xử lý sự cố, danh sách bug audit bảo mật, và các kế hoạch nâng cấp:

| Tài liệu | Mô tả tóm tắt |
| :--- | :--- |
| [**`bug-logs.md`**](./issues/bug-logs.md) | **Nhật ký lỗi & Bộ nhớ chống lặp lỗi (Anti-Regression Memory)**: Ghi lại triệu chứng, nguyên nhân gốc rễ (Root Cause) và chỉ dẫn bắt buộc cho AI sau khi xử lý sự cố. |
| [**`critical-issues-gotchas.md`**](./issues/critical-issues-gotchas.md) | Sổ tay các sự cố trọng yếu: AJAX Query Page bằng Shortcode, libxml HTML4 parser lồng thẻ `<source>`, Slot-Aware Picture Rendering (`data-slot-width`) và quy trình 3 bước debug DB/Disk/Frontend. |
| [**`critical-security-fixes.md`**](./issues/critical-security-fixes.md) | Danh sách các lỗi và lỗ hổng bảo mật đã audit đang tồn đọng (F-01 đến F-06: DOM XSS, delete_item arbitrary post, API return, debounce...) kèm test case. |
| [**`resolved-issues.md`**](./issues/resolved-issues.md) | Nhật ký 190+ sự cố kỹ thuật & nâng cấp lớn đã khắc phục dứt điểm trong các đợt refactor. **File lớn (900KB) — tìm kiếm bằng grep, không đọc toàn bộ.** |
| [**`pending-issues.md`**](./issues/pending-issues.md) | Danh sách các sự cố và giới hạn tạm hoãn xử lý (Backlog) cùng phương án khắc phục tương lai (ví dụ: Gutenberg Site Editor Title delete sync). |
| [**`plan-sortablejs-swap.md`**](./issues/plan-sortablejs-swap.md) | Kế hoạch kỹ thuật tích hợp SortableJS v1.15.6 hỗ trợ hoán đổi vị trí section (Swap mode), Re-parenting, 3-Zone Tree nesting và Undo/Redo mutations. |

---

## 7. AI Coding with Align Builder (`coding-with-builder/`)

Tài liệu thực chiến dành cho AI Agent và Developer khi build trang, viết CSS/JS, truy vấn dữ liệu, và tích hợp shortcodes trong Align Builder:

| Tài liệu | Mô tả tóm tắt |
| :--- | :--- |
| [**`README.md`**](./coding-with-builder/README.md) | **Trung tâm điều hướng**: Decision tree "cần gì thì đọc file nào" và 6 core rules bắt buộc (zero hardcoded hex, dual-storage, lazyload sau AJAX, shortcode container, KSES bypass, file size). |
| [**`content-types.md`**](./coding-with-builder/content-types.md) | **Phân biệt 4 loại content** — Page (`post_id`), Template (`tpl_id`), Template Part (`tp_id`), Reusable Block (`wp_block`): storage, asset naming convention (`style-page-*`, `style-template-*`, `style-part-*`, `style-block-*`), cách nhúng shortcode `[reuse-block id="N"]` với container `data-component-shortcode`, per-page overrides, và KSES bypass. |
| [**`asset-system.md`**](./coding-with-builder/asset-system.md) | **File-First SOT & 5-Tier CSS**: Kiến trúc Single Source of Truth vật lý tại `assets/builder/`, cơ chế Clean Database (loại bỏ inline `<style>`/`<script>`), quy chuẩn đặt tên file, tổ chức 5-tier CSS, và cơ chế backward compatibility an toàn cho web cũ. |
| [**`css-design-tokens.md`**](./coding-with-builder/css-design-tokens.md) | **Toàn bộ Design Tokens từ `root.css` thực tế**: 10 màu sắc (--color-1 đến --color-10), spacing scale, typography responsive, grid `.container-1440` + Bootstrap 5, z-index, utility classes, các shared section classes (`.glass-box`, `.btn-custom-quote`, `.newsletter-form`, `.floating-btn`). Rule: Zero hardcoded hex. |
| [**`js-patterns.md`**](./coding-with-builder/js-patterns.md) | **JS Patterns từ `script.js` thực tế**: Splide v3 slider (full config, custom navigator, alignSplideInit event), QB AJAX pagination (load more, infinite scroll, search), `lazyLoadInstance.update()` bắt buộc sau AJAX, shortcode container DOM pattern, debounce utility, và PHP static styles guard. |
| [**`data-query-patterns.md`**](./coding-with-builder/data-query-patterns.md) | **Truy vấn dữ liệu**: URL → post ID extraction, SQL an toàn với slug collision guard, Builder REST API đọc/ghi trang, WP-CLI wrapper cho Local by Flywheel, định vị file CSS/JS vật lý, post meta keys, và quy trình debug 3 bước. |
| [**`shortcode-reference.md`**](./coding-with-builder/shortcode-reference.md) | **Tất cả shortcodes đã đăng ký**: Pattern container bắt buộc trong Builder, Reusable Block (`[reuse-block id="N"]`), các module shortcode (`[align_shop_page]`, `[align_kit_builder]`, `[align_builder_highlights]`, `[align_garage_blog]`), single post fields, checkout, login, language switcher, và best practices PHP renderer. |

---

## Nguyen Tac Viet Tai Lieu (Documentation Guidelines)

1. **Ngôn ngữ**: Sử dụng Tiếng Việt cho tiêu đề và phần giải thích tổng quan; giữ nguyên các thuật ngữ kỹ thuật, tên class, method, mã code và commit messages bằng Tiếng Anh.
2. **Liên kết (Links)**: Sử dụng đường dẫn tương đối (relative paths) bắt đầu từ vị trí tài liệu hiện tại (ví dụ `../architecture/overview.md` hoặc `../../core/init.php`), không sử dụng đường dẫn tuyệt đối gắn với môi trường máy cá nhân.
3. **Phân loại**: Khi tạo mới tài liệu, bắt buộc phải đặt vào đúng 1 trong 7 thư mục chuyên mục (`architecture/`, `builder/`, `api/`, `translate/`, `modules/`, `issues/`, `coding-with-builder/`) và cập nhật liên kết vào file `README.md` này và vào `manifest.json`.
4. **Zero Emoji Policy**: Tuyệt đối không dùng emoji trong tiêu đề tài liệu mới hoặc giao diện Settings. Typography-First.

