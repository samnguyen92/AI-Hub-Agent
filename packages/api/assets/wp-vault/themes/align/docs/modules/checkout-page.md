# 🛒 Module Checkout Page (`inc/checkout-page/`)

Tài liệu kỹ thuật tổng quan về cấu trúc, cơ chế hoạt động, shortcode và các endpoint AJAX của module **Checkout Page** trong theme con `align-child`.

---

## 📌 1. Mục Tiêu & Thiết Kế
- **Mục tiêu**: Xây dựng module trang giỏ hàng & thanh toán (Checkout Page) độc lập, chuẩn semantic HTML theo thiết kế từ **Post ID 490** (`Checkout Page`), tương thích hoàn toàn với hệ thống thương mại điện tử **FluentCart**.
- **Vị trí thư mục**: `wp-content/themes/align-child/inc/checkout-page/`
- **File khởi tạo**: `class-checkout-page-init.php` (được nạp tự động trong `align-child/functions.php`).

---

## 🗂️ 2. Cấu Trúc Module

```text
align-child/inc/checkout-page/
├── class-checkout-page-init.php          <-- Singleton Khởi tạo, Đăng ký Shortcode & Enqueue Assets
├── class-checkout-custom-gateways.php    <-- Native FluentCart payment gateways: Wire Transfer & Check Payment
├── class-checkout-deposit-filter.php     <-- UI filter & radio selector cho deposit checkout
├── class-checkout-page-ajax.php          <-- Bộ xử lý AJAX cập nhật số lượng, xóa item & checkout order
├── class-checkout-order-processor.php    <-- Domain logic: Customer, Order, OrderAddress, OrderItem, Transaction, Clear Cart
├── class-checkout-cart-item-resolver.php <-- Format cart items, specs normalization & line item metadata
├── class-checkout-thumbnail-resolver.php <-- Chuyên biệt resolve thumbnail 48x48, swatch blacklist & dynamic sizing
├── template-checkout-page.php            <-- Template HTML semantic giỏ hàng, bảng giá & login drawer
├── deposit/                              <-- Module Đặt Cọc 50% & Thanh Toán 2 Đợt (Decoupled 100% trong align-child)
│   ├── class-deposit-bootstrap.php       <-- Bootstrap & Lifecycle Registrar
│   ├── class-deposit-helper.php          <-- Utility tính toán 50% deposit, snapshot metadata
│   ├── class-deposit-checkout.php        <-- Intercept CheckoutApi::placeOrder, gán total giao dịch cọc 50%
│   ├── class-deposit-status-sync.php     <-- Đồng bộ trạng thái đơn hàng (partially_paid -> paid)
│   └── class-deposit-balance-link.php    <-- Tạo order đợt 2 & sinh payment link bảo mật
└── assets/
    ├── css/
        ├── checkout-page.css             <-- Stylesheet thiết kế độc lập (chuẩn responsive 992px)
        └── receipt-page.css              <-- Stylesheet receipt & thank you hero
    └── js/
        ├── checkout-cart-manager.js      <-- Đóng gói quản lý giỏ hàng: Stepper, Remove, Totals, Fragments, Empty State
        └── checkout-page.js              <-- Tương tác Checkout 4-Block, Login Drawer, Address Switcher, History Replace

align-child/inc/core/
└── fluentcart-order-thumbnail-helper.php <-- Universal admin order view thumbnail resolver & 48x48px CSS injector
```

---

## 🔌 3. Shortcodes Được Đăng Ký

Module đăng ký 3 alias shortcode để linh hoạt chèn vào bất kỳ Trang (Page), Gutenberg Block hoặc Template nào:

1. `[align_checkout_page]` (Khuyên dùng)
2. `[fluentcart_checkout_page]`
3. `[align_checkout]`

### Tham số hỗ trợ:
- `preview="1"` (hoặc thêm param `?preview_checkout=1` trên URL): Cho phép render Mock Data giỏ hàng chuẩn thiết kế từ Post 490 phục vụ cho việc kiểm thử UI/UX khi giỏ hàng thực tế đang trống.

---

## ⚡ 4. Các Endpoint AJAX (`Align_Checkout_Page_Ajax`)

Tất cả endpoint đều được bảo vệ bằng Nonce bảo mật: `align_checkout_nonce`.

| Action Name | Method | Mô Tả | Tham Số |
| :--- | :--- | :--- | :--- |
| `align_checkout_update_quantity` | `POST` | Cập nhật số lượng của 1 mục trong giỏ hàng và tính lại tổng tiền. | `item_id`, `quantity`, `nonce` |
| `align_checkout_remove_item` | `POST` | Xóa 1 mục khỏi giỏ hàng FluentCart và trả về tổng tiền mới. | `item_id`, `nonce` |
| `align_checkout_get_cart` | `POST` | Lấy danh sách sản phẩm, tổng giá trị giỏ hàng và drawer fragments. | `nonce` |
| `align_checkout_process_order` | `POST` | Xử lý đặt hàng logged-in, tạo Order, OrderAddress, OrderItem, Transaction và xóa giỏ hàng. | `order_notes`, `shipping_*`, `payment_method`, `nonce` |
| `align_get_states_by_country` | `POST` | Tải danh sách bang/tỉnh theo mã quốc gia dynamically. | `country` |

---

## 🎨 5. Tương Thích & Phòng Thủ Lỗi (Defensive Architecture)

1. **Chính Sách Zero Silent Fallback (Anti-Corruption Policy)**:
   - Loại bỏ triệt để 100% truy vấn fallback latest order (`Order::query()->orderBy('id', 'DESC')->first()`).
   - Nếu quá trình tạo Order gặp exception, hệ thống bắt qua `try...catch (\Throwable $e)`, ghi log cấu trúc rõ ràng:
     ```text
     [Align Checkout] Order creation failed | user_id: %d | cart_items: %d | error: %s
     ```
     và phản hồi JSON error an toàn, tuyệt đối không redirect người dùng sang hóa đơn cũ của người khác.

2. **History Navigation & BFCache Guard**:
   - **History Replace State**: Khi hoàn tất đặt hàng thành công, dùng `window.location.replace(res.data.redirect_url)` thay vì `window.location.href` để thay thế mục lịch sử trình duyệt, ngăn chặn việc bấm nút "Back" quay lại checkout khi giỏ hàng đã bị xóa.
   - **BFCache Guard & Cross-Tab Sync**: Phía Kit Builder (`kit-builder-cart.js`) trang bị cờ `window.__alignKbHistoryGuardsBound`, bắt sự kiện `pageshow` (`event.persisted === true`) và `storage` (`align_kb_user_draft`) để tự động `window.location.reload()` giữ đồng bộ dữ liệu.
   - **Network Error Safety**: Khi gặp lỗi mạng hoặc thiếu config AJAX, UI tuyệt đối không chuyển hướng sang receipt mà mở lại nút checkout, gỡ spinner và hiển thị thông báo lỗi để người dùng thử lại.

3. **Không phụ thuộc WooCommerce (`Safe URL Resolution`)**:
   - Thay vì gọi trực tiếp `wc_get_page_id('shop')` gây Fatal Error trên site không dùng WooCommerce, module sử dụng cơ chế fallback:
     ```php
     $shop_url = home_url('/shop');
     if (function_exists('wc_get_page_id')) {
         $wc_id = wc_get_page_id('shop');
         if ($wc_id > 0) $shop_url = get_permalink($wc_id);
     } else {
         $shop_page = get_page_by_path('shop');
         if ($shop_page) $shop_url = get_permalink($shop_page->ID);
     }
     ```

4. **Khả năng tự hồi phục biến thể FluentCart (`Auto Variation Fallback`)**:
   - Khi sản phẩm đơn hoặc sản phẩm chưa có biến thể được đưa vào giỏ hàng, hệ thống tự động kiểm tra / khởi tạo biến thể mặc định trong `wp_fct_product_variations` để ngăn chặn hoàn toàn `TypeError: generateCartItemFromVariation()`.

5. **Chuẩn Hóa Thông Số Giỏ Hàng & Tinh Gọn Cấu Hình (`Specs Normalization & Vehicle/Year Merging`)**:
   - `Align_Checkout_Cart_Item_Resolver::resolve_item()` tự động chuẩn hóa thông số (`specs`), subtitle và thuộc tính cấu hình từ Kit Builder hoặc Shop catalog.
   - **Gộp Vehicle & Year**: Gộp Model xe và Year thành 1 line item $0 duy nhất dạng `Vehicle & Year: [Year] [Model]` thay vì 2 dòng riêng biệt gây rối giao diện.
   - **Bảo toàn $0 Items**: Toàn bộ các cấu hình không thu thêm phí ($0 items như Vehicle/Year, base options) được lưu vào giỏ hàng và đơn hàng để Admin nắm đầy đủ thông số khi xử lý sản xuất/lắp ráp.
   - **Loại bỏ Stitching Style trùng lặp**: Loại bỏ thuộc tính "Stitching Style" khỏi Step 7 Review và Place Order specs vì đã được định nghĩa trực tiếp bên trong Collection/Inserts.

6. **Quy Chuẩn Thumbnail 48x48 & Bóc Tách Resolver (`Align_Checkout_Thumbnail_Resolver`)**:
   - Tách toàn bộ logic giải quyết ảnh sang class chuyên biệt `Align_Checkout_Thumbnail_Resolver` (< 350 dòng) tuân thủ nghiêm ngặt nguyên lý Single Responsibility Principle (SRP) và giới hạn file < 500 dòng code.
   - **Chuẩn kích thước 48x48**: Tự động chuyển đổi và tìm nạp biến thể ảnh `-48x48.webp` (`thumbnail_variation` registered trong WordPress) thay vì nạp ảnh gốc kích thước lớn (1024x1024 / full).
   - **Phòng thủ Responsive Picture Override (`data-skip-picture="true"`)**: Thẻ thumbnail được gắn cờ `data-skip-picture="true"` và cố định `width="48" height="48"`. Cờ này ngăn chặn hook `Align_Builder_Image_Resolver::resolve_dynamic_image_srcset` tự ý wrap thẻ `<img>` bằng thẻ `<picture>` responsive 1024x1024 làm vỡ giao diện.
   - **Loại bỏ thẻ link `<a>` bọc thumbnail**: Vì các mục Kit Builder không có URL trang đơn (`single-product`), các thẻ bao bọc `<a>` quanh thumbnail được loại bỏ triệt để, render trực tiếp `<img>` 48x48 tối ưu.
   - **Phân định chính xác Flooring Addon vs Thảm sàn**: Khắc phục lỗi nhận diện nhầm sản phẩm Full Flooring Addon / Dynaliner (Post 1600) thành thảm sàn khiến hệ thống lấy nhầm URL shop `/shop` thay vì ảnh thực tế của sản phẩm.

7. **Bảo Vệ Chống Hồi Sinh Giỏ Hàng Khi F5 (`Cart Resurrection Guard`)**:
   - Khi giỏ hàng bị xóa hết sản phẩm về 0, hệ thống tự động xóa cờ draft sync `align_kb_cart_synced` và ngắt cơ chế tự động tái nạp $0 line items từ draft cũ khi reload (F5) trang Checkout.

8. **Giải Quyết Ảnh Thumbnail 48x48px Trong CMS Order FluentCart (`Align_Fluentcart_Order_Thumbnail_Helper`)**:
   - Khắc phục triệt để lỗi không hiển thị hình ảnh sản phẩm trong trang chi tiết đơn hàng quản trị FluentCart (`admin.php?page=fluent-cart#/orders/{id}`).
   - Lắng nghe filter `fluent_cart/order/view` ở độ ưu tiên 25, duyệt qua toàn bộ item và lấy ảnh thực tế chuẩn WebP `-48x48.webp` thông qua `Align_Checkout_Thumbnail_Resolver::resolve_item_thumbnail($item)`.
   - Cung cấp tính năng click mở popup preview cho các dòng cấu hình $0 Kit Builder (`is_custom = true`, `view_url = $thumb_url`).
   - Giữ nguyên mẫu hoa văn thảm đã chọn cho sản phẩm Step 4 Flooring (#1501 Chilewich / Squareweave).
   - Bảo vệ Step 5 Components và Step 6 Add-ons khỏi hiện tượng swatch texture bleed bằng cơ chế swatch blacklist.
   - Inject CSS quản trị cố định kích thước `.product-thumbnail` và thẻ `<img>` đạt chuẩn `48px x 48px`, `object-fit: cover`, `border-radius: 6px`.
   - Lưu trữ trực tiếp `$other_info['thumbnail']` và `$other_info['featured_media']` vào bảng `wp_fct_order_items` ngay lúc đặt hàng qua `Align_Checkout_Order_Processor::create_order_items()`.

---

## 💳 9. Cơ Chế Đặt Cọc 50% & Tích Hợp Native FluentCart 2 Đợt Thanh Toán (50% Deposit & 2-Installment Flow)

### 9.1 Tổng quan & Mục tiêu
- **Nguyên tắc kỹ thuật cốt lõi**: **100% Native Hook Integration — Không can thiệp sửa đổi plugin FluentCart (Plugin untouched)**.
- **Vị trí Module & Decoupling**: Toàn bộ logic Deposit Payment đã được đóng gói và bóc tách độc lập hoàn toàn bên trong theme con `align-child` (`inc/checkout-page/deposit/` và `inc/checkout-page/class-checkout-deposit-filter.php`), được nạp tự động thông qua `Align_Checkout_Page_Init::load_dependencies()`.
- **Align Core Clean 100%**: Thư mục Core của theme cha `align/core/` hoàn toàn sạch nguyên bản, không chứa bất kỳ hook hay file nào của FluentCart deposit (`align/core/inc/fluentcart/` đã được loại bỏ 100%).

### 9.2 Kiến trúc 2 Đợt Thanh Toán (End-to-End Architecture)

```text
[ĐỢT 1: ĐẶT HÀNG & THANH TOÁN CỌC 50%]
  1. Giỏ hàng có sản phẩm Kit Builder -> Tổng tiền $2,800.
  2. Khách nhấn "PAY 50% DEPOSIT ($1,400)" trên Checkout Page.
  3. FluentCart CheckoutApi::placeOrder() tạo Order ($2,800) và Draft Charge Transaction ($2,800).
  4. Hook 'fluent_cart/checkout/prepare_other_data' (priority 20) chặn lại:
     - Gán $txn->total = $1,400 (50% deposit).
     - Giữ nguyên $order->total_amount = $2,800 (100% full value).
     - Lưu snapshot intent vào Order config ('align_deposit': {percent: 50, amount: 140000, base: 280000}).
  5. Cổng thanh toán (Stripe Elements / Hosted) đọc $paymentInstance->transaction->total và CHỈ TRỪ ĐÚNG $1,400.
  6. Webhook / Browser Return xác nhận $1,400 thành công:
     - $order->total_paid = $1,400.
     - Align_Deposit_Status_Sync chuyển $order->payment_status = 'partially_paid'.
  7. FluentCart Admin & Khách hàng thấy rõ:
     - Total: $2,800 | Total Paid: $1,400 (50%) | Total Due: $1,400 (50%).

[ĐỢT 2: THANH TOÁN 50% CÒN LẠI (BALANCE RE-PAY)]
  1. Admin kích hoạt hoặc Khách hàng mở Payment Link:
     - URL: home_url('/receipt/?fluent-cart=custom_checkout&order_hash={uuid}')
  2. Hook 'fluent_cart_action_custom_checkout' (priority 5):
     - Snapshot bảo vệ giao dịch cọc 50% trước đó (tránh bị FluentCart createDraftOrder ghi đè).
  3. Hook 'fluent_cart/checkout/prepare_other_data' (priority 20):
     - Phục hồi giao dịch cọc 50% từ snapshot.
     - Tạo charge transaction mới cho số dư còn lại: $total_amount - $total_paid ($1,400).
  4. Khách thanh toán nốt $1,400 qua Stripe.
  5. FluentCart cộng dồn $total_paid = $2,800 (100%) và tự động chuyển $order->payment_status = 'paid'.
  6. Toàn bộ lifecycle hoàn tất (OrderPaid events, receipt, emails, cart_completed).
```

### 9.3 Điều Kiện Kích Hoạt Đặt Cọc (Eligibility Rules)
1. Đơn hàng là đơn hàng 1 lần (One-time standard/payment order), **tuyệt đối từ chối subscription/renewal**.
2. Filter `align/fluentcart/deposit/should_apply` trả về `true`.
3. Class `Align_Checkout_Deposit_Filter::should_apply()` tự động quét:
   - `$ctx['cart']`: Chứa item có `other_attributes['kit_bundle'] = true` hoặc được router xác nhận `is_kit_builder_cart()`.
   - `$order->order_items`: Line items có metadata `kit_bundle = true`.
   - Fallback: `Align_Kit_Builder_Price_Guard::has_kit_items()` còn tồn tại.

### 9.4 Chi Tiết Giao Diện Người Dùng (Frontend UI/UX Breakdown)
- **Bảng giá 3 dòng trực quan (`.deposit-breakdown-box`)**:
  - `Total Price` (`#summary-grand-total`): 100% tổng đơn hàng (ví dụ: $2,800.00).
  - `Remaining Balance (Due Upon Completion)` (`#summary-balance-due`): 50% số tiền còn lại (ví dụ: $1,400.00).
  - `Deposit Due Today (50%)` (`#summary-total` với `data-is-deposit="1"`): Số tiền thanh toán hôm nay (ví dụ: $1,400.00) font to đậm, màu đỏ thương hiệu, kèm ghi chú rõ ràng.
- **Nút hành động phản hồi theo thời gian thực (Dynamic Button)**:
  - Khi chọn Stripe / Thẻ: `PAY 50% DEPOSIT ($1,400)`.
  - Khi chọn Wire Transfer / Check: `PLACE ORDER (50% DEPOSIT)`.
  - Khi bấm Stepper (+ / -) số lượng, toàn bộ 3 dòng tiền và nhãn nút bấm **tự động tính toán lại ngay lập tức** qua `CheckoutCartManager.updateLiveTotals()`.
- **Khung Thẻ Stripe Tinh Chỉnh (Ảnh 1 Resolution)**:
  - `.payment-info-panel-inner` có padding rộng rãi `22px 24px 26px`, không còn bị dính sát viền dưới.
  - Tiêu đề "Card Information" kèm badge bảo mật "256-bit SSL Encrypted".
  - `#stripe-card-element` có border, padding, border-radius và hiệu ứng focus viền xanh `#0284c7`.

### 9.5 Các Lỗi Cốt Lõi Đã Được Xử Lý (Critical Root Causes Resolved)
1. **PHP Symbol Table Compile-time Early Return**:
   - *Nguyên nhân*: Trong `class-deposit-bootstrap.php`, câu lệnh `if (class_exists('Align_Deposit_Bootstrap')) return;` nằm ở đầu file. Do PHP đăng ký class ở thời điểm compile-time trước khi thực thi opcode, `class_exists()` luôn trả về `true` và thoát sớm trước khi chạy hàm `init()` ở cuối file.
   - *Khắc phục*: Bỏ guard return sớm, bọc khai báo class an toàn và đăng ký hook đa tầng (`plugins_loaded`, `after_setup_theme`, `init`).
2. **Lỗi Ràng Buộc Order Type**:
   - *Nguyên nhân*: `Align_Deposit_Helper::should_apply` trước đây kiểm tra cứng `Status::ORDER_TYPE_PAYMENT !== $order->type`. Thực tế FluentCart tạo đơn hàng Kit Builder với `type = 'standard'`, khiến helper trả về `false`.
   - *Khắc phục*: Đổi sang loại trừ có chọn lọc: chỉ từ chối `subscription` và `renewal`, chấp nhận tất cả đơn hàng `standard` và `payment`.
3. **Fail-Safe Pre-checkout Boot**:
   - *Khắc phục*: Bổ sung `Align_Deposit_Bootstrap::boot()` trực tiếp trong `Align_Checkout_Order_Router::process_native()` ngay trước khi gọi `CheckoutApi::placeOrder()` để đảm bảo 100% hook luôn được nạp.
