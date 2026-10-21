---
name: homepage-mockup
description: "Cấu trúc template HTML/CSS standalone cho trang chủ/homepage hiện đại, tối ưu cho Playwright screenshot."
---

# Homepage Mockup Skill

## Cấu trúc cho homepage preview

Đây là ví dụ cấu trúc kỹ thuật; hãy chọn visual direction, màu nền và typography theo brief/reference đã duyệt thay vì sao chép nguyên giao diện dark trong ví dụ.

```html
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Align AI Landing Page</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #0B0F19;
      color: #F9FAFB;
      min-height: 100vh;
      line-height: 1.6;
    }
    .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
    /* Navbar, Hero, Features, CTA, Footer styling */
  </style>
</head>
<body>
  <!-- Semantic layout -->
</body>
</html>
```

## Yêu cầu khi tạo mockup
1. Có thể dùng CSS nội tuyến cho một mockup độc lập. Với website nhiều trang, dùng file CSS/JS và assets local có cấu trúc rõ ràng; không hy sinh khả năng bảo trì chỉ để chụp screenshot.
2. Dùng `render_html_to_png` trên file nằm trong workspace để Playwright mở file cùng local CSS, JavaScript và ảnh. Xem screenshot thực tế trước khi kết luận; nếu render lỗi hoặc thiếu asset thì sửa hoặc nêu giới hạn.
3. Thiết kế responsive hoạt động ở Desktop (1440px) và Mobile (390px); kiểm tra các mục quan trọng không bị ẩn trên mobile.
4. Sau khi lưu mockup, dùng `package_design` cho website nhiều trang, hoặc công cụ đóng gói phù hợp cho landing page; xác nhận HTML cùng mọi asset local đều có trong output.
