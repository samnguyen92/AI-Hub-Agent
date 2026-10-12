---
name: crawl-site
description: "Kỹ năng thu thập dữ liệu nội bộ website, phân tích cây liên kết và phát hiện broken links (404/500)."
---

# Crawl Site Skill

## Phương pháp Crawl an toàn và hiệu quả
1. Giới hạn độ sâu (Depth: 2) và số lượng trang (5-10 trang) để tránh quá tải tài nguyên hệ thống.
2. Theo dõi các liên kết:
   - Status 200: Trang hoạt động bình thường, bóc tách thẻ Title, Description, Canonical.
   - Status 4xx / 5xx: Ghi nhận vào danh sách `broken_links`.
3. Kiểm tra tính trùng lặp URL có hoặc không có dấu gạch chéo `/`.
