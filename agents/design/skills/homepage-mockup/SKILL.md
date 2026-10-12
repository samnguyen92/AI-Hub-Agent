---
name: homepage-mockup
description: "Cấu trúc template HTML/CSS standalone cho trang chủ/homepage hiện đại, tối ưu cho Playwright screenshot."
---

# Homepage Mockup Skill

## Cấu trúc chuẩn của một standalone HTML file

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
1. CSS nhúng trực tiếp trong `<style>` tag để Playwright render không phụ thuộc assets ngoài.
2. Thiết kế responsive hoạt động tốt ở cả Desktop (1440px) và Mobile (390px).
3. Đảm bảo tỷ lệ padding/margin cân đối, không bị overflow ngang (`overflow-x: hidden`).
