---
name: write-report
description: Quy chuẩn định dạng báo cáo và phản hồi chuyên nghiệp, trực quan, thân thiện với người dùng
version: 1.0.0
---

# Quy chuẩn Báo cáo & Phản hồi (write-report)

## 1. Nguyên tắc giao tiếp thân thiện
- **Không bao giờ quăng raw JSON hoặc dump code block thô** vào tin nhắn phản hồi chính trừ khi người dùng yêu cầu xem mã nguồn cụ thể.
- **Không lạm dụng ký tự phân đoạn lộn xộn** (`###`, `**` dồn dập). Sử dụng tiêu đề phân cấp rõ ràng, đoạn văn ngắn gọn, dễ đọc.
- **Trình bày trực quan với Bảng Markdown (Markdown Tables)**: Khi cần liệt kê sitemap, danh sách trang, thông số kỹ thuật, lỗi phát hiện, hoặc bảng so sánh, luôn chuyển đổi thành Bảng rõ ràng thay vì danh sách gạch đầu dòng dài hoặc JSON.

## 2. Cấu trúc câu trả lời chuẩn (Executive Format)
Mỗi phản hồi tổng hợp nên tuân theo bố cục 3 phần:

### Phần 1: Tóm tắt tổng quan (Executive Summary)
- Tóm tắt kết quả chính trong 2-3 câu ngắn gọn, súc tích.
- Nêu rõ tình trạng hiện tại và mục tiêu đã hoàn thành.

### Phần 2: Nội dung chi tiết & Bảng trực quan
- Trình bày thông tin cốt lõi bằng bảng biểu (Table) có cột rõ ràng: `STT | Trang / Mục | Đường dẫn | Mục đích / Trạng thái`.
- Điểm nhấn bằng callout hoặc bullet point ngắn khi cần cảnh báo.

### Phần 3: Đề xuất bước tiếp theo (Next Actions)
- Gợi ý 2-3 hành động tiếp theo khả thi.
- Hướng dẫn người dùng cách duyệt hoặc yêu cầu chỉnh sửa.

## 3. Ví dụ chuyển đổi Sitemap
*Không xuất JSON:*
```json
{"pages": [{"path": "/", "name": "Trang chủ"}]}
```

*Thay vào đó, trình bày dạng Bảng:*

| Trang | Đường dẫn | Mục đích | Trạng thái |
| :--- | :--- | :--- | :--- |
| Trang chủ | `/` | Giới thiệu thương hiệu & sản phẩm nổi bật | Đã thiết kế mockup |
| Cửa hàng | `/shop` | Danh mục sản phẩm & bộ lọc tìm kiếm | Chờ duyệt wireframe |
| Giỏ hàng | `/cart` | Quản lý đơn hàng & thanh toán | Kế hoạch Sprint 2 |
