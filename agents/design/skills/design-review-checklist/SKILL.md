---
name: design-review-checklist
description: "Checklist tiêu chí đánh giá chất lượng thiết kế giao diện trước khi xuất bản bản vẽ hoặc gửi sang Code Builder."
---

# Design Review Checklist Skill

## Danh mục kiểm tra thiết kế (Review Checklist)

Mỗi mockup được tạo ra cần được tự đánh giá qua các tiêu chí sau:

1. **Brand & Aesthetics Consistency**:
   - Có tuân thủ đúng bảng màu và triết lý Align (`shared/align-style`) không?
   - Độ mờ glassmorphism và viền vi tế (`rgba(255,255,255,0.08)`) có chuẩn không?

2. **Typography Hierarchy**:
   - Phân cấp tiêu đề (H1 > H2 > H3 > Body) rõ ràng, font size và line-height cân đối.
   - Khoảng cách đọc chữ dễ chịu, không bị quá nhỏ trên mobile.

3. **Responsive & Layout**:
   - Layout trên 1440px căn chỉnh cân đối, không bị lệch lề.
   - Trên 390px mobile không bị vỡ bố cục, không bị ngang thanh scroll (`overflow-x`).
   - Các nút bấm chạm (touch target) tối thiểu 44px trên mobile.

4. **Visual Polish & Micro-details**:
   - Hiệu ứng gradient, card hover shadow, badge trạng thái tinh tế.
   - Hình ảnh, icon (SVG hoặc unicode/css shapes) sắc nét.

## Output định dạng checklist
```json
[
  {"item": "Align Brand Styling", "status": "passed", "note": "Sử dụng dark glassmorphism & accent gradient chuẩn"},
  {"item": "Typography Hierarchy", "status": "passed", "note": "H1 48px, Body 16px, độ tương phản chuẩn"},
  {"item": "Responsive Adaptability", "status": "passed", "note": "Flex wrap mượt mà trên mobile 390px"}
]
```
