---
name: align-style
description: Quy chuẩn lập trình và thiết kế giao diện chuẩn phong cách align.vn
version: 1.0.0
---

# Quy chuẩn Align Style (align-style)

## 1. Nguyên tắc cốt lõi
- **HTML/CSS/JS thuần túy**: Không thêm các thư viện JS nặng ngoài (như jQuery, Bootstrap) trừ khi được người dùng phê duyệt rõ ràng.
- **Mobile-first**: Bắt đầu styling từ màn hình nhỏ (390px), mở rộng lên tablet (768px) và desktop (1440px).
- **Semantic HTML5**: Luôn sử dụng đúng các thẻ `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<aside>`, `<footer>`.

## 2. Hệ thống Design Tokens (CSS Variables)
Mọi file CSS hoặc `<style>` phải định nghĩa biến màu và typography trong `:root`:
```css
:root {
  /* Bảng màu chính */
  --color-primary: #0f172a;      /* Slate đậm */
  --color-accent: #6366f1;       /* Indigo hiện đại */
  --color-accent-hover: #4f46e5;
  --color-bg: #ffffff;
  --color-surface: #f8fafc;
  --color-text: #0f172a;
  --color-text-muted: #64748b;
  --color-border: #e2e8f0;

  /* Typography */
  --font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  --font-size-base: 16px;
  --line-height-base: 1.6;

  /* Khoảng cách (Spacing) */
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 48px;

  /* Bo góc (Radius) */
  --radius-sm: 6px;
  --radius-md: 12px;
  --radius-lg: 20px;
}
```

## 3. Khả năng truy cập (Accessibility - WCAG AA)
- Độ tương phản chữ và nền tối thiểu đạt tỷ lệ 4.5:1.
- Mọi hình ảnh `<img>` bắt buộc có thuộc tính `alt` mô tả rõ ràng.
- Các nút tương tác hoặc link phải có focus state rõ nét (`:focus-visible`).

## 4. Kiểm tra trước khi bàn giao
1. Chạy tool `run_checks` để bảo đảm không thiếu thẻ meta viewport, title, alt.
2. Render ảnh với `render_html_to_png` để rà soát trực quan trên cả Desktop và Mobile.
