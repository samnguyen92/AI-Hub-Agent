# ⚡ Dịch Hàng Loạt & Tạo SEO Bằng AI (Bulk AI Translator & SEO)

Tài liệu này đặc tả kiến trúc và cách vận hành tính năng **Bulk AI Translator & SEO Assistant** nằm trong phân hệ **SEO & Multi-Language** của Align Theme Settings.

---

## 🎯 1. Mục Đích & Ý Nghĩa

Khi website mở rộng ra thị trường quốc tế với hàng chục trang Landing Page và hàng trăm bài viết Blog:
- Việc mở từng bài để tạo bản dịch thủ công và chạy AI SEO tốn rất nhiều thời gian.
- **Bulk AI Translator** giải quyết vấn đề này bằng một quy trình tự động hóa 2-trong-1:
  1. **Tự động quét**: Phát hiện toàn bộ trang và bài viết chưa có bản dịch trong ngôn ngữ đích.
  2. **Dịch nội dung & Tiêu đề**: Sử dụng AI để dịch toàn bộ blocks, markup, SVG, classes và layout.
  3. **Tự động sinh AI SEO (Rank Math)**: **Sau khi nội dung và tiêu đề đã được dịch hoàn tất**, AI SEO Generator phân tích nội dung ngôn ngữ đích để trích xuất Focus Keyword, SEO Title, Meta Description và Slug chuẩn ngôn ngữ đó.
  4. **Kháng Timeout 100%**: Sử dụng kiến trúc hàng đợi Client-side Queue, xử lý tuần tự từng bài viết một cách bất đồng bộ.

---

## 📍 2. Vị Trí Giao Diện & Điều Khiển

- **Truy cập**: `WP Admin` $\rightarrow$ `Align Settings` $\rightarrow$ Hub `SEO & Multi-Language` $\rightarrow$ Subtab `Bulk AI Translator`.
- **Thành phần điều khiển**:
  - **Target Language**: Danh sách chọn ngôn ngữ đích (ví dụ: Tiếng Trung `zh`).
  - **Content Types**: Lựa chọn loại nội dung:
    - *Pages* (Trang tĩnh)
    - *Posts* (Bài viết blog)
    - *Layout Templates* (`wp_template` - Giao diện trang `page`, bài viết `single`, trang lỗi `404`, lưu trữ `archive`)
    - *Template Parts* (`wp_template_part` - Header, Footer)
    - *Reusable Blocks & CTAs* (`wp_block` - Khối nút bấm, banner, CTA tái sử dụng)
  - **Translation Style & Instructions (Tùy biến Style & Chỉ dẫn dịch thuật)**:
    - Cho phép quản trị viên nhập trực tiếp các yêu cầu về văn phong, đối tượng độc giả (B2B corporate, HR directors, private clients), hoặc các thuật ngữ bắt buộc.
    - **Tự động đồng bộ**: Khi chọn Target Language, trường này tự động nạp `prompt_hint` được cấu hình từ trước trong tab **Active Languages**.
    - **Nút "Load HK Corporate Preset"**: 1-Click nạp sẵn bộ chỉ dẫn văn phong và thuật ngữ chuẩn Hồng Kông.
  - **Internal Link Localization (Tự động chuyển URL nội bộ & CTAs)**:
    - Mọi liên kết nội bộ trong Header, Footer, Block Templates (404, Page, Single), Bài viết, và khối CTA (ví dụ `href="/contact/"`) sẽ được **tự động chuyển hướng trỏ về trang tiếng đích tương ứng (ví dụ `/zh/contact/`)**, thay vì trỏ về trang tiếng Anh ban đầu.
  - **Translation Scope**:
    - *Missing & Untranslated Content* (Dịch các mục chưa có hoặc đã tạo nhưng nội dung còn là tiếng nguồn - Khuyến nghị).
    - *Missing Translations Only* (Chỉ dịch các mục chưa có bản dịch trong database).
    - *All Content (Overwrite Existing)* (Dịch lại toàn bộ và ghi đè nội dung cũ).
  - **AI SEO Generation Checkbox**: Bật/tắt tính năng tự động tạo metadata Rank Math.
  - **Nút điều khiển**: *Scan Translatable Content*, *Scan URL Problems*, *Start Bulk Translation*, *Pause*, *Resume*, *Stop*.

---

## 🇭🇰 3. Chuẩn Bản Địa Hóa Hong Kong (Hong Kong Localization & Terminology)

Hệ thống dịch thuật của Align được trang bị cơ chế nhận diện và bản địa hóa chuyên sâu cho **Hong Kong Traditional Chinese (`traditional-hk` / `zh-HK` / 繁體中文 - 香港)**, giải quyết triệt để vấn đề dịch máy móc Simplified $\rightarrow$ Traditional:

1. **Văn phong chuẩn mực (Tone & Register)**:
   - Sử dụng **Văn ngôn thư diện chuẩn mực (標準書面語)** của giới doanh nghiệp, tài chính và bảo hiểm Hong Kong.
   - Tuyệt đối không sử dụng khẩu ngữ thô (không dùng 嘅, 咗, 唔, 喺, 諗, 睇, 哋).
   - Tuyệt đối loại bỏ các từ lóng hành chính và thuật ngữ đặc thù của Trung Quốc Đại Lục (Zero Mainland Buzzwords: không dùng 打造, 抓手, 賦能, 優化, 理賠, 醫保, 工傷保險, 養老金, 鏈接, 項目, 質量).
2. **Bộ từ điển thuật ngữ doanh nghiệp & bảo hiểm bắt buộc (Glossary Enforcement)**:
   - *Employees' Compensation* $\rightarrow$ `僱員補償保險 / 勞保` (Tuyệt đối cấm: `工傷保險`, `勞動保險`)
   - *Public Liability* $\rightarrow$ `公眾責任保險 / 第三者責任保險`
   - *Directors & Officers (D&O)* $\rightarrow$ `董事及高級管理人員責任保險`
   - *Business Interruption* $\rightarrow$ `業務中斷保險`
   - *Medical / Health Insurance* $\rightarrow$ `團體醫療保險 / 醫療保障` (Tuyệt đối cấm: `醫保`)
   - *Claims / File a Claim* $\rightarrow$ `索償 / 提出索償` (Tuyệt đối cấm dùng riêng lẻ từ `理賠`)
   - *Pension / Retirement / MPF* $\rightarrow$ `強積金 / MPF / 強制性公積金` (Tuyệt đối cấm: `養老金`)
   - *Underwriting* $\rightarrow$ `核保`
   - *Policyholder / Insured* $\rightarrow$ `保單持有人 / 受保人`
   - *Deductible / Excess* $\rightarrow$ `自負額 / 墊底費`
   - *Quality* $\rightarrow$ `質素` (Tuyệt đối cấm: `質量` mang nghĩa khối lượng)
   - *Information* $\rightarrow$ `資訊 / 資料` (Thay vì `信息`)
   - *Link / URL* $\rightarrow$ `連結` (Thay vì `鏈接`)
   - *Clients / Customers* $\rightarrow$ `客戶`
   - *Brand Name* $\rightarrow$ Giữ nguyên thương hiệu tiếng Anh `Trusted Union`.

---

## ⚙️ 4. Quy Trình 4 Bước Thực Thi Chi Tiết (Pipeline Flow)

```text
[ JavaScript Queue ]
       │
       ▼ (Gửi AJAX align_bulk_process_item kèm custom_instructions)
┌────────────────────────────────────────────────────────────────────────┐
│ BƯỚC 1: Khởi tạo Bản dịch Phân cấp (Hierarchy Resolution)             │
│ - Kiểm tra post meta _align_lang_group                                 │
│ - Gọi Align_Language_Manager::create_translation()                     │
│ - Gán _align_lang, _align_lang_group, _align_lang_parent               │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│ BƯỚC 2: Dịch Nội Dung & Tiêu Đề (Block-level In-Place Translation)    │
│ - Gọi Align_Builder_Translator::rest_translate_post()                  │
│ - Nạp Align_Builder_Prompt_Helper tạo prompt kèm từ điển chuẩn HK     │
│ - Quét text slots, gửi batch dịch thuật sang AI                        │
│ - Ghi đè text vào slots mà không thay đổi bất kỳ HTML tag hay class nào│
│ - Tự động định tuyến link nội bộ sang URL ngôn ngữ đích qua Localizer  │
│ - Cập nhật wp_update_post(post_title, post_content) & xóa cache DB    │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼ (BẮT BUỘC: Nội dung tiếng đích đã lưu)
┌────────────────────────────────────────────────────────────────────────┐
│ BƯỚC 3: AI SEO Generator (Phân Tích Nội Dung Bản Dịch)                 │
│ - Gọi Align_Builder_Seo_Handler::generate_seo_meta()                   │
│ - AI đọc nội dung TIẾNG ĐÍCH (ví dụ tiếng Trung) trong Database       │
│ - Sinh Focus Keyword, SEO Title, Meta Description, URL Slug chuẩn      │
│ - Gọi save_seo_meta() ghi đè trực tiếp vào Rank Math Post Meta         │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│ BƯỚC 4: Phản Hồi Client & Cập Nhật Giao Diện Thời Gian Thực           │
│ - Cập nhật hàng trong bảng: Status Success, Điểm SEO, Keyword          │
│ - Cập nhật nút View (Xem bài dịch) và Edit (Mở trình biên tập)         │
│ - Nhích thanh tiến trình (% Progress Bar)                              │
│ - Chờ debounce 400ms $\rightarrow$ Tự động chuyển sang bài tiếp theo   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 🛠️ 5. Kiến Trúc Mã Nguồn (Code Architecture)

| Thành phần | Đường dẫn file | Vai trò |
| :--- | :--- | :--- |
| **Backend Controller** | [`align/core/includes/admin/class-align-bulk-translator.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/class-align-bulk-translator.php) | Xử lý các AJAX endpoints `align_bulk_scan_items` và `align_bulk_process_item`, chuyển tiếp `custom_instructions`. |
| **Prompt & Glossary Helper** | [`align/core/inc/builder/class-builder-prompt-helper.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/inc/builder/class-builder-prompt-helper.php) | Đóng gói bộ từ điển HK, chỉ thị ngữ hệ (`traditional-hk`), và ghép prompt đa tầng. |
| **Translation Engine** | [`align/core/inc/builder/class-builder-translator.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/inc/builder/class-builder-translator.php) | Thực hiện batch API call đến AI LLM (OpenRouter/OpenAI) và phân tích JSON kết quả. |
| **Giao diện Partial View** | [`align/core/includes/admin/views/part-bulk-ai-translator.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/views/part-bulk-ai-translator.php) | Khung giao diện chuẩn Button Transparent, ô nhập Translation Style, Progress Bar và Table status. |
| **Client Queue Engine** | [`align/core/includes/admin/views/tab-bulk-ai-translator.js`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/views/tab-bulk-ai-translator.js) | Quản lý hàng đợi bất đồng bộ, đồng bộ prompt theo ngôn ngữ, gửi payload `custom_instructions`. |
| **Master Tab Integration** | [`align/core/includes/admin/views/tab-seo-languages.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/views/tab-seo-languages.php) | Tích hợp Subtab thứ 4 vào Hub SEO & Multi-Language. |

---

## 🛡️ 6. Các Cơ Chế An Toàn (Safety Guards)

1. **Bảo Vệ Độc Lập Giữa Các Bài Viết**: Nếu 1 bài viết gặp lỗi dịch hoặc timeout từ phía AI bên ngoài, hệ thống sẽ đánh dấu `Error` kèm thông báo lỗi cụ thể trên bài đó và **tự động chuyển sang bài tiếp theo**, không làm sập toàn bộ tiến trình.
2. **Kháng Rate-Limit AI API**: Tích hợp khoảng dừng nghỉ (Debounce Delay 400ms) giữa các request để các dịch vụ AI như OpenRouter/OpenAI không chặn request vì spam tần suất cao.
3. **Phân Quyền Chặt Chẽ**: Tất cả các hành động quét và dịch đều kiểm tra Nonce bảo mật `align_settings_nonce` và quyền quản trị viên `current_user_can('manage_options')`.
4. **Giới Hạn File (< 500 Dòng)**: Toàn bộ các file trong phân hệ dịch thuật đều được phân rã tuân thủ nghiêm ngặt quy chuẩn dưới 500 dòng/file.

