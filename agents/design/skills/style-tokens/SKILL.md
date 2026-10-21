---
name: style-tokens
description: "Trích xuất và định nghĩa bộ Design Tokens (colors, typography, spacing, shadows) theo chuẩn Align Design System."
---

# Style Tokens Skill

## Bộ tokens theo hướng visual đã chọn

Chọn palette theo brief, thương hiệu và reference đã duyệt. Không mặc định dark mode hoặc glassmorphism. Ví dụ dưới đây là một hướng dark, không phải preset bắt buộc của mọi project.

```json
{
  "colors": {
    "background_base": "#0B0F19",
    "background_surface": "rgba(17, 24, 39, 0.75)",
    "primary": "#6366F1",
    "primary_hover": "#4F46E5",
    "secondary": "#EC4899",
    "text_primary": "#F9FAFB",
    "text_secondary": "#9CA3AF",
    "border_subtle": "rgba(255, 255, 255, 0.1)"
  },
  "typography": {
    "font_family": "Plus Jakarta Sans, Inter, system-ui, sans-serif",
    "h1": "2.5rem / 1.2",
    "h2": "2rem / 1.25",
    "body": "1rem / 1.5",
    "caption": "0.875rem / 1.4"
  },
  "spacing": {
    "xs": "4px",
    "sm": "8px",
    "md": "16px",
    "lg": "24px",
    "xl": "32px",
    "xxl": "48px"
  }
}
```

## CSS Custom Properties
```css
:root {
  --bg-base: #0B0F19;
  --bg-surface: rgba(17, 24, 39, 0.75);
  --primary: #6366F1;
  --primary-hover: #4F46E5;
  --secondary: #EC4899;
  --text-main: #F9FAFB;
  --text-muted: #9CA3AF;
  --border: rgba(255, 255, 255, 0.1);
  --glass-blur: blur(12px);
  --radius-card: 16px;
  --radius-btn: 10px;
}
```
