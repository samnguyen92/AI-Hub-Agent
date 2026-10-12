---
name: image-to-html
description: "Kỹ thuật tái hiện trung thực giao diện web từ hình ảnh hoặc mockup HTML với CSS Variables và layout chuẩn."
---

# Image to HTML Skill

## Hướng dẫn tái hiện giao diện
1. **Phân tích lưới & bố cục (Layout grid)**:
   - Xác định container width (thường là 1200px hoặc 1280px).
   - Chia khối (Header -> Hero -> Grid Features -> Content -> Footer).
2. **Trích xuất thuộc tính giao diện**:
   - Khoảng cách (padding, margin) theo thang 8pt (`8px`, `16px`, `24px`, `32px`, `48px`).
   - Bo góc (border-radius) cho buttons (`8px` hoặc `10px`), cards (`16px`).
   - Đổ bóng (box-shadow) và viền (border 1px subtle).
3. **Kỹ thuật viết code**:
   - Sử dụng Flexbox cho các thanh điều hướng và hàng đơn.
   - Sử dụng CSS Grid cho danh sách sản phẩm hoặc khối tính năng (`grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))`).
   - Đảm bảo font chữ fallback: `font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif`.
