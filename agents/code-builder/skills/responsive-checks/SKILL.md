---
name: responsive-checks
description: "Quy chuẩn kiểm tra tính tương thích đa thiết bị (Desktop 1440px, Tablet 768px, Mobile 390px)."
---

# Responsive Checks Skill

## Tiêu chí kiểm tra đáp ứng (Responsive)
1. **Viewport Meta Tag**: Luôn khai báo `<meta name="viewport" content="width=device-width, initial-scale=1.0">`.
2. **Breakpoints chuẩn**:
   - Desktop: `>= 1024px`
   - Tablet: `768px - 1023px`
   - Mobile: `< 768px` (ưu tiên kiểm thử ở `390px` chiều rộng)
3. **Các lỗi thường gặp và cách khắc phục**:
   - *Lỗi tràn màn hình ngang (horizontal overflow)*: Đặt `max-width: 100%` trên ảnh, video; không dùng `width` cố định tính bằng pixel lớn trên container.
   - *Cỡ chữ quá nhỏ trên mobile*: Không dùng font size nhỏ hơn 12px. Body font lý tưởng từ 14px đến 16px.
   - *Khoảng cách bấm quá sát*: Nút bấm trên mobile cần tối thiểu 44px chiều cao để dễ chạm ngón tay.
