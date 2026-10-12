---
name: lighthouse-audit
description: "Kỹ năng đánh giá Core Web Vitals, Performance, Accessibility và Best Practices theo chuẩn Google Lighthouse."
---

# Lighthouse Audit Skill

## Các chỉ số Core Web Vitals trọng yếu
- **LCP (Largest Contentful Paint)**: Tốt khi `< 2.5s`, Cần cải thiện: `2.5s - 4.0s`, Kém: `> 4.0s`.
- **FID / INP (Interaction to Next Paint)**: Tốt khi `< 200ms`.
- **CLS (Cumulative Layout Shift)**: Tốt khi `< 0.1`, Kém: `> 0.25`.

## Khắc phục các vấn đề phổ biến
1. Thiếu thẻ `alt` trên ảnh: Thêm văn bản mô tả ngữ nghĩa cho ảnh.
2. Trùng hoặc thiếu thẻ `<h1>`: Đảm bảo mỗi trang có duy nhất 1 thẻ `<h1>` chính.
3. Không có thẻ `meta description`: Bổ sung đoạn mô tả 120-160 ký tự.
