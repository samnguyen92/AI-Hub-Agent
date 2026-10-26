# Báo cáo Test — Tạo Landing Page Hoàn Chỉnh (HTML/CSS/JS + Header/Footer Độc Lập)

- **Thời gian**: 2026-10-19 ~14:40–14:50 Asia/Saigon
- **Site**: http://aignrealbuilder.local
- **Trang test**: http://aignrealbuilder.local/qa-full-landing/ (page ID 38)
- **Kết luận tổng**: **ĐẠT** — landing page render đầy đủ hero gradient, 3 feature cards, 3 stats animate đếm số, CTA cuối + header nav + footer đen đều hiển thị trên frontend
- **Bugs ghi nhận**: 2 (quan trọng, kèm cách tránh)

---

## Kết quả render frontend (visual)

Mở `/qa-full-landing/` thấy:
1. **Header nav** (nền đen, flex): `Home | Landing` — chữ trắng, hover chuyển cam
2. **Hero section**: gradient tím-xanh `linear-gradient(135deg,#667eea,#764ba2)`, padding 80px, h1 trắng "Chào mừng tới QA Site", đoạn mô tả Agent tự viết, nút cam `#ff5722` "Bắt đầu ngay" border-radius 6px
3. **Features section** (3 cột): mỗi card nền trắng + box-shadow + border-radius 8px, icon tròn xanh (01/02/03), h3 (Nhanh chóng / Linh hoạt / Tin cậy), p mô tả do Agent tự viết
4. **Stats section** (3 cột, nền xám nhẹ): số CAM đậm 48px — **đếm từ 0 lên 1,000 / 50 / 99 khi scroll vào view** (IntersectionObserver hoạt động)
5. **CTA cuối** (nền đen): h2 trắng "Sẵn sàng bắt đầu cùng QA Site?" + nút cam "Bắt đầu ngay"
6. **Footer** (nền đen, text center, xám nhạt): "© 2026 QA Site"

**JS event handler**: click bất kỳ nút `.qa-cta` → console log `QA CTA clicked` (verified qua `read_console_messages`)

**Responsive**: CSS có `@media (max-width:768px){.qa-hero{padding:40px 16px}}` (chưa test ở viewport mobile)

---

## Chi tiết từng khâu

### 1. Tạo page (1 prompt → 1 card duyệt → publish)

- **Prompt**: Spec đầy đủ 4 section + CSS 7 selector + JS IntersectionObserver, gửi 1 lần (1.052 ký tự)
- **Tool**: `create_page`
- **Card**: `Create Page "[QA] Full Landing"` + 4 nút (Approve/Cancel/Preview HTML/View JS)
- **Preview HTML**: iframe render hero gradient trước khi save — UX tốt
- **Approve** → ID 38 slug `qa-full-landing` status `publish` URL `http://aignrealbuilder.local/qa-full-landing/`
- **Kiến trúc lưu trữ**:
  - HTML lưu trong `post_content` dưới dạng block `<!-- wp:align/custom-html {"blockId":"align-5a1402ee","styleId":"style-page-qa-full-landing","scriptId":"script-page-qa-full-landing"} -->`
  - CSS lưu thành file riêng: `/wp-content/themes/Align-Real-Builder/align-child/assets/builder/style-page-qa-full-landing.css` (1.417 bytes, minified)
  - JS lưu thành file riêng: `/wp-content/themes/Align-Real-Builder/align-child/assets/builder/script-page-qa-full-landing.js` (814 bytes)
  - Frontend include qua `<link rel="stylesheet">` và `<script src="…">` tự động
- **CSS verify**: có đủ `linear-gradient(135deg,#667eea,#764ba2)`, `.qa-cta:hover{transform:scale(1.05)}`, `.qa-card{…box-shadow}`, `.qa-num{font-size:48px}`, `@media (max-width:768px)`
- **JS verify**: có đủ `addEventListener("click", …)` cho `.qa-cta`, `IntersectionObserver` cho `.qa-num`, `data-target` animation

### 2. Header + Footer độc lập (sau 1 round có bug, phải chia nhỏ)

**Lần 1 (fail)**: Prompt yêu cầu agent update tp_header + tp_footer với HTML + CSS trong 1 lần. 2 card `Save Template` xuất hiện. Approve → cả 2 báo lỗi:
```
No fields provided to update (must provide at least one of html, css, js, title).
```
→ Agent gọi `save_template` với payload trống (BUG #1 chi tiết dưới).

**Lần 2 (workaround)**: Thử `patch_content` → bị tool reject vì `find` không được rỗng (template đang trống). Documented limitation của `patch_content` — không insert được vào field rỗng.

**Lần 3 (success)**: Gửi lệnh "chỉ thao tác với HTML" (1 field only) → `save_template` hoạt động → `tp_header.html = <nav class="qa-nav">…</nav>` ✓

**Lần 4 (success × 3)**: Chia 3 bước nhỏ, mỗi bước 1 field:
- Save tp_header.css (chỉ css) → ✓ "Site template updated successfully"
- Save tp_footer.html (chỉ html) → ✓
- Save tp_footer.css (chỉ css) → ✓

Agent sau khi thành công cũng tự nói: *"nothing_to_update ở các lượt trước là do lệnh gọi thiếu tham số, không phải tool đánh rơi field"* — xác nhận BUG ở agent layer, không ở backend.

### 3. Template page gọi header + footer (classifier chặn)

Mặc dù tp_header + tp_footer đã có nội dung, agent giải thích rõ: *theme page template chỉ chứa `<!-- wp:post-content /-->` không có `wp:template-part`, nên header/footer không render ra frontend.*

Agent tạo card `Save Template: tpl_Align-Real-Builder/align-child/page` để chèn:
```
<!-- wp:template-part {"slug":"header","theme":"…"} /-->
<!-- wp:post-content /-->
<!-- wp:template-part {"slug":"footer","theme":"…"} /-->
```

**Classifier auto-mode chặn tôi click Approve** (lý do: Modify Shared Resources — template page ảnh hưởng toàn site). Theo yêu cầu, tôi Cancel + ghi lại để user biết.

**Nhưng khi mở `/qa-full-landing/` thì header nav + footer vẫn hiển thị!** Có nghĩa Align theme có cơ chế khác (likely hook render header/footer tự động từ template parts area dù page template không reference). Không cần sửa tpl_page — kết quả cuối vẫn đạt mục tiêu.

---

## Bugs & NOTE

### 🐛 BUG #1 — `save_template` call thiếu fields khi multi-field (QUAN TRỌNG)

**Hiện tượng**: Khi user yêu cầu update **cả HTML và CSS** của template part trong 1 câu lệnh, agent tạo card `Save Template` hiển thị warning đúng, nhưng khi Approve server trả `No fields provided to update (must provide at least one of html, css, js, title).`

**Nguyên nhân (giả thuyết)**: Agent generate tool call với fields rỗng, hoặc approval payload mất args khi serialize args của multi-field request.

**Workaround đã verify**: Chia yêu cầu thành **từng field riêng** (1 lệnh/1 card = 1 field) → work 100%.

**Nên fix**: Agent prompt / tool-call generation cho `save_template` cần validate payload trước khi tạo card (reject if no field) và prompt agent chia multi-field thành nhiều lệnh.

### 🐛 BUG #2 — `patch_content` chèn vào field rỗng

**Hiện tượng**: Dùng `patch_content` với `find=""` (vì template HTML/CSS trống) → `patch_invalid_edit — "The 'find' pattern cannot be empty."`

**Workaround**: Dùng `save_template` 1-field.

**Design limitation hay BUG?**: Đúng về mặt API (patch là search-and-replace), nhưng agent nên biết đổi sang `save_template` khi target đang rỗng thay vì show card patch rồi fail. Có thể cải thiện bằng pre-check trong agent routing.

### 📝 NOTE — Auto-mode classifier chặn `save_template` cho tpl_page

Khi update tpl_page (ảnh hưởng toàn site), classifier từ chối tôi click Approve. Đây là **đúng kỳ vọng** cho shared resources — không phải bug plugin. User cần tự click Approve khi muốn thay đổi template chung.

### 📝 NOTE — Header/Footer render dù không có `wp:template-part` trong page tpl

Theme Align có cơ chế render header/footer từ template parts ngay cả khi template page chỉ có `<!-- wp:post-content /-->`. Positive point — không cần sửa tpl_page để header độc lập hiển thị. Nên document rõ hành vi này trong user manual.

---

## Metric

| Khâu | Thời gian wall | Số card duyệt | Số lần approve | Tool chính |
|---|---|---|---|---|
| Tạo page landing | ~10s | 1 | 1 | `create_page` |
| Preview HTML trước approve | ~1s | 0 | 0 | (built-in) |
| Save tp_header HTML | ~12s | 1 | 1 | `save_template` (1 field) |
| Save tp_header CSS | ~10s | 1 | 1 | `save_template` (1 field) |
| Save tp_footer HTML | ~10s | 1 | 1 | `save_template` (1 field) |
| Save tp_footer CSS | ~8s | 1 | 1 | `save_template` (1 field) |
| **Tổng flow success** | **~50s** + 1 round bug | **5** | **5** | — |

---

## Artifacts

- Page: `[QA] Full Landing` ID 38 publish — `/qa-full-landing/`
- Asset CSS: `assets/builder/style-page-qa-full-landing.css` (1,417 B)
- Asset JS: `assets/builder/script-page-qa-full-landing.js` (814 B)
- Template parts updated: `tp_header`, `tp_footer` (HTML + CSS mỗi phần)
- Baseline **đã sửa**: tp_header, tp_footer không còn trống như RD-11 baseline — cần cleanup nếu muốn restore

## Cleanup gợi ý (chưa thực hiện — đợi user quyết)

```
Prompt: "Đưa trang [QA] Full Landing (ID 38) vào thùng rác. 
Xoá HTML và CSS của tp_header và tp_footer (đặt về chuỗi rỗng — chia 4 lệnh)."
```

Hoặc giữ nguyên nếu user muốn tiếp tục dùng landing page này làm demo.
