# 📸 Quy Chuẩn Xây Dựng Shortcode Responsive Media & Image Size (Align Theme)

> **MANDATORY POLICY FOR AI AGENTS & DEVELOPERS**: Mọi Shortcode trong Theme Align hoặc Child Theme khi render hình ảnh (`<img>`, `<picture>`, background banner, gallery, card grids) **BẮT BUỘC** phải tuân thủ nghiêm ngặt quy cách cấu trúc thẻ HTML và thuật toán tính toán kích thước trong tài liệu này.

---

## 🎯 1. Triết Lý & Mục Tiêu Kỹ Thuật

1. **Chuẩn Responsive Art Direction**: Thay vì nhúng thẻ `<img>` đơn thuần, mọi vị trí ảnh đều phải hỗ trợ đa kích thước thông qua thẻ `<picture>` đa tầng (`Mobile < 599px`, `Tablet < 992px`, `Desktop Fallback`).
2. **Tối Ưu Tuyệt Đối Core Web Vitals (LCP & CLS)**:
   - Loại bỏ triệt để hiện tượng phình tải trọng (Payload Bloat) khi màn hình Desktop tải ảnh gốc 4096px / 1.5MB cho một ô hiển thị 312px.
   - Bắt buộc khai báo đầy đủ `width`, `height`, `loading="lazy"`, `decoding="async"` để triệt tiêu hiện tượng giật layout (Cumulative Layout Shift - CLS).
3. **Tương Thích 100% Với Align Visual Builder**:
   - Thẻ `<picture>` và `<img>` phải mang class `wp-image-{id}` và thuộc tính `data-id="{id}"` để Visual Media Modal có thể nhận diện Attachment ID, cho phép user click đổi ảnh trực quan ngay trên canvas.
   - Khi có kích thước ô cố định, bắt buộc cung cấp `data-slot-width="{width}"`.

---

## 📐 2. Cấu Trúc HTML Chuẩn (Gold Standard HTML Structure)

Mọi shortcode khi trả về HTML hình ảnh phải tuân thủ đúng định dạng sau:

```html
<picture class="wp-image-102" data-id="102" data-slot-width="312">
  <source media="(max-width: 599px)" srcset="https://example.com/wp-content/uploads/2026/09/image-312x312.webp" width="312" height="312">
  <source media="(max-width: 992px)" srcset="https://example.com/wp-content/uploads/2026/09/image-312x312.webp" width="312" height="312">
  <img src="https://example.com/wp-content/uploads/2026/09/image-624x624.webp" alt="Mô tả hình ảnh" class="category-card-img wp-image-102" width="624" height="624" loading="lazy" decoding="async">
</picture>
```

### Chi Tiết Từng Thuộc Tính Bắt Buộc:

| Phần tử / Thuộc tính | Bắt buộc? | Ý nghĩa & Quy chuẩn |
| :--- | :---: | :--- |
| `<picture>` | **Có** | Thẻ bao ngoài cho Responsive Art Direction. |
| `class="wp-image-{id}"` | **Có** | Đặt trên **cả `<picture>` và `<img>`** để Builder và CSS xác định Attachment ID. |
| `data-id="{id}"` | **Có** | ID của Attachment trong WordPress Media Library. |
| `data-slot-width="{width}"` | *Khuyên dùng* | Chiều rộng hiển thị (CSS pixel) của ô chứa ảnh trên Desktop (ví dụ `312`, `644`). |
| `data-align-sizes="{sizes}"` | *Tùy chọn* | Cấu hình sub-sizes theo 3 Screensize Slots (ví dụ `mobile:thumbnail,tablet:medium,desktop:large` hoặc legacy CSV `mobile_375,large`). |
| `<source media="(max-width: 599px)">` | **Có** | Cung cấp sub-size tối ưu cho điện thoại di động (< 600px). |
| `<source media="(max-width: 992px)">` | **Có** | Cung cấp sub-size tối ưu cho máy tính bảng / tablet (< 993px). |
| `<img>` | **Có** | Fallback cho Desktop (> 992px) và trình duyệt cũ. |
| `<img> src` | **Có** | **CẤM** trỏ về ảnh gốc 4096px. Bắt buộc trỏ về sub-size Desktop Retina 2x (`slot * 2`) hoặc ~1440px. |
| `loading="lazy"` | **Có** | Trì hoãn nạp ảnh ngoài màn hình (trừ trường hợp ảnh LCP Hero Banner đầu trang). |
| `decoding="async"` | **Có** | Giải mã ảnh bất đồng bộ, tránh chặn main thread. |
| `width` & `height` | **Có** | Bắt buộc khai báo trên cả `<source>` và `<img>` tương ứng với kích thước thật của sub-size. |

> [!CAUTION]
> **Cạm Bẫy Thẻ Tự Đóng (Self-Closing `<source />`)**:
> Tuyệt đối **KHÔNG ĐƯỢC** viết `<source ... />` với dấu gạch chéo tự đóng XML. Thư viện PHP `DOMDocument::loadHTML()` sử dụng parser libxml HTML4 sẽ hiểu nhầm `<source>` là container và lồng các thẻ tiếp theo vào trong nó (`<picture><source><source><img></source></source></picture>`), phá vỡ toàn bộ cấu trúc DOM. Bắt buộc viết thẻ mở thuần túy: `<source ...>`.

---

## 🧮 3. Quy Tắc Phân Giải Kích Thước (Resolution & Breakpoint Rules)

Theme Align sử dụng hệ thống 3 điểm ngắt (Breakpoints) tiêu chuẩn:

```
[0px] ──────── Mobile ──────── [599px] ────── Tablet ────── [992px] ────── Desktop ────── [∞]
          max-width: 599px               max-width: 992px            Fallback <img>
```

### 3.1. Kịch Bản 1: Có Khai Báo Slot Width (`data-slot-width`)
Áp dụng cho các thành phần dạng lưới (Grid), Card sản phẩm, Danh mục, Slider:
- **Mobile Target**: $\min(\text{slot}, 599\text{px})$
- **Tablet Target**: $\min(\text{slot}, 992\text{px})$
- **Desktop Target (2x Retina)**: $\min(\text{full\_width}, \max(\text{slot} \times 2, 1440\text{px}))$

*Ví dụ: Ô Card danh mục có slot 312px*:
- Mobile: Tìm sub-size gần nhất với `312px` (ví dụ `312x312`).
- Tablet: Tìm sub-size gần nhất với `312px` (ví dụ `312x312`).
- Desktop Fallback: Tìm sub-size Retina 2x gần nhất với `624px` (`312 * 2`, ví dụ `624x624`).

### 3.2. Kịch Bản 2: Không Khai Báo Slot Width (Ảnh Hero / Full Banner)
Áp dụng cho các ảnh tràn màn hình, Hero Section, Banner đơn:
- **Mobile Target**: `599px`
- **Tablet Target**: `992px`
- **Desktop Target**: `1440px` (hoặc ảnh gốc nếu nhỏ hơn 1440px).

### 3.3. Thuật Toán Tiệm Cận Khoảng Cách Bám Sát Tỉ Lệ (Ratio-Aware Proximity Scoring)
Khi lựa chọn sub-size từ WordPress attachment metadata:
- **Bộ lọc loại rác (Garbage Filter)**: Loại bỏ toàn bộ sub-sizes có chiều rộng $< 150\text{px}$ (các thumbnail swatch li ti của plugin).
- **Công thức tính điểm (Scoring Formula)**:
  $$\text{score} = (|w - \text{target}| \times \text{penalty}) + \text{aspect\_diff}$$
  - $w$: Chiều rộng của sub-size.
  - $\text{penalty}$: Bằng **$1.25$** nếu $w < \text{target}$ (phạt nhẹ kích thước bị hụt để ưu tiên chọn ảnh nhỉnh hơn một chút, đảm bảo độ sắc nét trên màn hình Retina), và **$1.0$** nếu $w \ge \text{target}$.
  - $\text{ideal\_h} = w \times (\text{full\_height} / \text{full\_width})$: Chiều cao lý tưởng theo đúng tỉ lệ ảnh gốc.
  - $\text{aspect\_diff} = |h - \text{ideal\_h}| \times 0.01$: Tie-breaker bám sát tỉ lệ thực tế của ảnh gốc (loại bỏ hoàn toàn thiên kiến ép ảnh vuông $1:1$).

### 3.4. Cơ Chế 3 Screensize Slots (Mobile, Tablet, Desktop) & Manual Slot Mapping
Trong Align Visual Builder, người dùng có thể can thiệp trực tiếp vào từng tầng breakpoint thông qua 3 ô chọn chuyên biệt (**Screensize Breakpoints**) thay vì phụ thuộc 100% vào thuật toán tự động:
1. **3 Ô Chọn Chuyên Biệt (3 Screensize Slots)**:
   - **Mobile (< 600px)**: Điều khiển thẻ `<source media="(max-width: 599px)">`.
   - **Tablet (< 992px)**: Điều khiển thẻ `<source media="(max-width: 992px)">`.
   - **Desktop (> 992px)**: Điều khiển thẻ fallback `<img>` trên màn hình lớn.
2. **Tùy Chọn Auto (Smart Resolution)**:
   - Mọi slot đều có lựa chọn đầu tiên là `Auto (Smart Resolution)`.
   - Khi một slot được đặt là `Auto`, slot đó sẽ tự động áp dụng thuật toán Proximity Scoring bám sát tỉ lệ để tính ra kích thước tối ưu nhất cho breakpoint tương ứng.
   - Khi cả 3 slot đều ở chế độ `Auto`, thuộc tính `data-align-sizes` sẽ tự động được gỡ bỏ khỏi thẻ `<picture>`.
3. **Cú Pháp Key-Value Mới (`mobile:x,tablet:y,desktop:z`)**:
   - Khi có ít nhất một slot được tùy biến, cấu hình sẽ được lưu vào thuộc tính `data-align-sizes="mobile:val,tablet:val,desktop:val"`.
   - Cho phép điều khiển độc lập từng slot, ví dụ `data-align-sizes="mobile:thumbnail,tablet:auto,desktop:large"`.
   - Tương thích ngược 100% với định dạng Legacy Comma-Separated (CSV) cũ (`mobile_375,medium_large,large`).
4. **Nút "Reset to Auto"**:
   - Cung cấp nút thao tác nhanh để hoàn trả tức thì cả 3 slot về `Auto`, đồng bộ với Undo history (`Vvveb.Undo`) và kích hoạt trạng thái dirty trên nút Lưu của Builder.
5. **Lưu Trữ & Backend Sync**:
   - Thuộc tính `data-align-sizes` được backend PHP `Align_Builder_Image_Resolver` phân tích trực tiếp: các slot chỉ định tường minh sẽ map thẳng vào sub-size tương ứng (không sắp xếp ép buộc theo width), còn các slot `auto` sẽ kích hoạt thuật toán tiệm cận khoảng cách (Proximity Scoring).

---

## 💻 4. Hai Phương Pháp Xây Dựng Shortcode Chuẩn

Khi viết mã nguồn PHP cho Shortcode trong Theme Align, AI Agent & Developer có 2 lựa chọn:

### ✅ Phương Pháp 1: Tận Dụng Core Resolver (Recommended Pipeline)
Đây là phương pháp tối ưu, sạch sẽ và ít lỗi nhất. Shortcode chỉ cần render thẻ `<picture>` hoặc `<img>` cơ bản, sau đó chuyển giao chuỗi HTML qua hàm `Align_Builder_Image_Resolver::resolve_dynamic_image_srcset()`.

```php
<?php
/**
 * Shortcode render Product Category Card với Automated Resolver Pipeline.
 *
 * @param array $atts Shortcode attributes.
 * @return string Rendered HTML.
 */
function align_shortcode_category_card( $atts ) {
    $atts = shortcode_atts(
        array(
            'image_id'   => 0,
            'title'      => '',
            'slot_width' => 312,
        ),
        $atts,
        'align_category_card'
    );

    $image_id = absint( $atts['image_id'] );
    if ( ! $image_id ) {
        return '';
    }

    $image_url = wp_get_attachment_image_url( $image_id, 'large' );
    if ( ! $image_url ) {
        return '';
    }

    $alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
    if ( empty( $alt_text ) ) {
        $alt_text = sanitize_text_field( $atts['title'] );
    }

    // Render cấu trúc khung ban đầu (Resolver sẽ tự động lấp đầy <source>)
    ob_start();
    ?>
    <div class="align-category-card">
        <picture class="wp-image-<?php echo esc_attr( $image_id ); ?>" data-id="<?php echo esc_attr( $image_id ); ?>" data-slot-width="<?php echo esc_attr( $atts['slot_width'] ); ?>">
            <img src="<?php echo esc_url( $image_url ); ?>" 
                 alt="<?php echo esc_attr( $alt_text ); ?>" 
                 class="category-img wp-image-<?php echo esc_attr( $image_id ); ?>" 
                 loading="lazy" 
                 decoding="async">
        </picture>
        <h3 class="category-title"><?php echo esc_html( $atts['title'] ); ?></h3>
    </div>
    <?php
    $html = ob_get_clean();

    // Kích hoạt Core Resolver để nội suy tự động các sub-sizes chuẩn WordPress
    if ( class_exists( 'Align_Builder_Image_Resolver' ) ) {
        $html = Align_Builder_Image_Resolver::resolve_dynamic_image_srcset( $html );
    }

    return $html;
}
add_shortcode( 'align_category_card', 'align_shortcode_category_card' );
```

---

### 🛠️ Phương Pháp 2: Helper Trực Tiếp (Standalone Helper Method)
Sử dụng khi shortcode nằm độc lập và muốn kiểm soát trực tiếp mảng sub-sizes mà không cần thông qua DOM Parser của Resolver.

```php
<?php
/**
 * Helper render Responsive Picture tag trực tiếp từ Attachment ID.
 *
 * @param int   $image_id   Attachment ID.
 * @param array $args       Tùy chọn: slot_width, class, alt, loading.
 * @return string Rendered <picture> HTML.
 */
function align_render_responsive_picture( $image_id, $args = array() ) {
    $image_id = absint( $image_id );
    if ( ! $image_id ) {
        return '';
    }

    $meta = wp_get_attachment_metadata( $image_id );
    if ( empty( $meta['sizes'] ) ) {
        // Fallback về thẻ img thông thường nếu không có sub-sizes
        return wp_get_attachment_image( $image_id, 'full', false, array(
            'class'   => ( $args['class'] ?? '' ) . ' wp-image-' . $image_id,
            'loading' => $args['loading'] ?? 'lazy',
        ) );
    }

    $slot_width = ! empty( $args['slot_width'] ) ? absint( $args['slot_width'] ) : 0;
    $alt = ! empty( $args['alt'] ) ? esc_attr( $args['alt'] ) : esc_attr( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
    $extra_class = ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : '';
    $loading = ! empty( $args['loading'] ) ? esc_attr( $args['loading'] ) : 'lazy';

    // Xác định targets
    $mobile_target = $slot_width ? min( $slot_width, 599 ) : 599;
    $tablet_target = $slot_width ? min( $slot_width, 992 ) : 992;
    $desktop_target = $slot_width ? min( (int) ( $meta['width'] ?? 2048 ), max( $slot_width * 2, 1440 ) ) : 1440;

    // Sử dụng resolver method nếu có sẵn, hoặc fallback nội bộ
    if ( class_exists( 'Align_Builder_Image_Resolver' ) ) {
        $mobile_size = Align_Builder_Image_Resolver::find_closest_size( $meta['sizes'], $mobile_target );
        $tablet_size = Align_Builder_Image_Resolver::find_closest_size( $meta['sizes'], $tablet_target );
        $desktop_size = Align_Builder_Image_Resolver::find_closest_size( $meta['sizes'], $desktop_target );
    } else {
        $mobile_size = $tablet_size = $desktop_size = null;
    }

    $upload_dir = wp_upload_dir();
    $base_url = trailingslashit( $upload_dir['baseurl'] ) . dirname( $meta['file'] ) . '/';

    $mobile_url = $mobile_size ? ( $base_url . $mobile_size['file'] ) : wp_get_attachment_image_url( $image_id, 'medium' );
    $tablet_url = $tablet_size ? ( $base_url . $tablet_size['file'] ) : wp_get_attachment_image_url( $image_id, 'large' );
    $desktop_url = $desktop_size ? ( $base_url . $desktop_size['file'] ) : wp_get_attachment_image_url( $image_id, 'full' );

    $slot_attr = $slot_width ? ' data-slot-width="' . $slot_width . '"' : '';

    $html  = '<picture class="wp-image-' . $image_id . '" data-id="' . $image_id . '"' . $slot_attr . '>';
    $html .= '<source media="(max-width: 599px)" srcset="' . esc_url( $mobile_url ) . '" width="' . ( $mobile_size['width'] ?? 599 ) . '" height="' . ( $mobile_size['height'] ?? 599 ) . '">';
    $html .= '<source media="(max-width: 992px)" srcset="' . esc_url( $tablet_url ) . '" width="' . ( $tablet_size['width'] ?? 992 ) . '" height="' . ( $tablet_size['height'] ?? 992 ) . '">';
    $html .= '<img src="' . esc_url( $desktop_url ) . '" alt="' . $alt . '" class="' . $extra_class . ' wp-image-' . $image_id . '" width="' . ( $desktop_size['width'] ?? 1440 ) . '" height="' . ( $desktop_size['height'] ?? 900 ) . '" loading="' . $loading . '" decoding="async">';
    $html .= '</picture>';

    return $html;
}
```

---

## 🚫 5. Danh Sách Điều Cấm Kỵ (Anti-Patterns / Gotchas)

| Sai lầm thường gặp | Hậu quả nghiêm trọng | Giải pháp đúng |
| :--- | :--- | :--- |
| **Gán URL ảnh gốc 4096px vào `<img> src`** | Phình tải trọng desktop lên tới vài MB, điểm PageSpeed LCP rớt thảm hại. | Dùng sub-size Retina 2x (`slot * 2`) hoặc tiệm cận 1440px. |
| **Dùng thẻ tự đóng `<source ... />`** | Parser libxml HTML4 của PHP lồng thẻ sai: `<picture><source><source><img>` | Luôn dùng thẻ HTML5 không tự đóng: `<source ...>`. |
| **Thiếu class `wp-image-{id}` hoặc `data-id`** | Align Visual Builder Media Picker không nhận diện được ảnh khi user click chọn ảnh trên canvas. | Bắt buộc khai báo cả trên `<picture>` và `<img>`. |
| **Dùng bộ lọc cứng `<= 599`, `<= 992`** | Bị loại mất các size tối ưu (ví dụ size `1024x1024` bị loại trên tablet chỉ vì lệch 32px, làm tụt xuống size nhỏ vỡ hình). | Dùng thuật toán tính khoảng cách `find_closest_size` có penalty. |
| **Bỏ sót `loading="lazy"` & `decoding="async"`** | Chặn tiến trình tải trang và nghẽn luồng render của trình duyệt. | Luôn gán `loading="lazy" decoding="async"` (trừ banner trên cùng màn hình đầu). |
| **Không khai báo `width` / `height`** | Trình duyệt không thể tính aspect ratio trước khi tải ảnh, gây nhảy giao diện (CLS cao). | Khai báo đúng `width` và `height` của từng sub-size. |

---

## ✅ 6. Checklist Tự Kiểm Tra Dành Cho AI Agent Trước Khi Bàn Giao

Trước khi kết luận hoàn thành tác vụ viết shortcode có render media:

- [ ] 1. Thẻ bao bọc ngoài cùng là `<picture>`, có thuộc tính `class="wp-image-{id}"` và `data-id="{id}"`.
- [ ] 2. Nếu là layout ô cố định (card, grid, slider), đã có thuộc tính `data-slot-width="{width}"`.
- [ ] 3. Có đủ 2 thẻ `<source>` với media queries chuẩn `(max-width: 599px)` và `(max-width: 992px)`.
- [ ] 4. Không có ký tự tự đóng `/>` ở các thẻ `<source>`.
- [ ] 5. Thẻ `<img>` fallback có `class="... wp-image-{id}"`, `loading="lazy"`, `decoding="async"`, `width`, `height`.
- [ ] 6. File code tạo mới hoặc cập nhật tuân thủ giới hạn **`< 500 dòng/file`** theo quy định `AGENTS.md`.
