# 🧩 Align Page Builder - Danh Sách Blocks, Style & Animation

Tài liệu này tổng hợp toàn bộ hệ thống **Blocks**, **Components**, **Styles**, **Animations** và **Input Controls** có trong **Align Page Builder** (dựa trên VvvebJs & WordPress Integration).

---

## 1. 📦 Danh Sách Blocks & Components (Phân theo Nhóm)

### 🟢 Base / HTML Components (Thành phần Cơ Bản)
Các phần tử HTML chuẩn được tích hợp trình chỉnh sửa trực quan:
- **Heading (`html/heading`)**: Thẻ tiêu đề (H1 - H6) với tùy chỉnh kích thước, font chữ, màu sắc.
- **Paragraph (`html/paragraph`)**: Đoạn văn bản với định dạng rich text.
- **Link (`html/link`)**: Thẻ liên kết `<a>` hỗ trợ URL, Target (`_self`, `_blank`), Rel, Download.
- **Image (`html/image`)**: Khối hình ảnh chuẩn responsive Art Direction đa tầng với thẻ `<picture>` bao bọc `<source media="(max-width: 599px)">` (Mobile), `<source media="(max-width: 992px)">` (Tablet) và fallback `<img>` (Desktop). Tích hợp bộ điều khiển **3 Screensize Slots độc lập** trên Sidebar Inspector cho phép người dùng tự do chọn bất kỳ kích thước thực tế nào từ WordPress Media Library (kể cả kích thước lớn/Desktop cho Mobile) hoặc chọn `Auto (Smart Resolution)`, kèm nút "Reset to Auto" 1-click. Thuộc tính đồng bộ ngầm qua `data-align-sizes="mobile:val,tablet:val,desktop:val"`, hỗ trợ auto-lazyload (`loading="lazy" decoding="async"`), toggle **Featured** (eager LCP preload), và tương thích 100% với định dạng cũ.
- **Button (`html/button`)**: Nút bấm với nhiều kiểu dáng Bootstrap (Primary, Secondary, Success, v.v.).
- **Horizontal Rule (`html/hr`)**: Đường kẻ phân cách ngang với tùy chọn độ dày, màu sắc, opacity.
- **Video (`html/video`)**: Trình phát Video HTML5 trực tiếp.
- **Audio (`html/audio`)**: Trình phát Audio HTML5.
- **PDF (`html/pdf`)**: Trình xem tài liệu PDF nhúng.
- **Iframe (`html/iframe`)**: Khối nhúng Iframe tùy biến URL và kích thước.
- **Form Components**:
  - Form Container (`html/form`)
  - Label (`html/label`)
  - Text Input (`html/textinput`)
  - Textarea (`html/textareainput`)
  - Select Input (`html/selectinput`)
  - Checkbox (`html/checkbox`)
  - Radio Button (`html/radiobutton`)
  - File Input (`html/fileinput`)
- **Table Components**:
  - Table (`html/table`), Table Row (`html/tablerow`), Table Cell (`html/tablecell`), Table Header Cell (`html/tableheadercell`), Table Body (`html/tablebody`), Table Head (`html/tablehead`), Table Footer (`html/tablefooter`).
- **Khác**: Blockquote (`html/blockquote`), List & List Item (`html/list`, `html/list-item`), Preformatted (`html/preformatted`), Custom HTML (`html/html`), Embed (`html/embed`).

---

### 🟡 Elements Components (Thành phần Nâng cao)
- **Font Icon (`elements/font-icon`)**: Icon phông chữ (Lucide / FontAwesome / LineAwesome).
- **SVG Image & SVG Element (`elements/svg-image`, `elements/svg-element`)**: Khối chèn mã SVG & hình ảnh vector.
- **Figure (`elements/figure`)**: Khối hình ảnh kèm chú thích (caption).
- **Gallery (`elements/gallery`)**: Bộ sưu tập hình ảnh lưới (Grid Gallery).
- **Tabs & Tab (`elements/tabs`, `elements/tab`)**: Khối Tab chuyển đổi nội dung linh hoạt.
- **Accordion (`elements/accordion`)**: Khối danh sách thu gọn / mở rộng (Collapse).
- **Flip Box (`elements/flip-box`)**: Khối lật 3D hai mặt khi di chuột (Hover flip animation).
- **Counter (`elements/counter`)**: Bộ đếm số hiệu ứng chạy số.
- **Testimonial (`elements/testimonial`)**: Khối đánh giá khách hàng (Ảnh đại diện, tên, chức danh, lời nhận xét).
- **Social Icons (`elements/social-icons`)**: Danh sách biểu tượng mạng xã hội.
- **Carousel & Slider (`elements/carousel`, `elements/slider`)**: Khối trình chiếu ảnh / slide nội dung.
- **Icon List (`elements/icon-list`)**: Danh sách kèm icon tùy chỉnh từng dòng.
- **Divider & Separator (`elements/divider`, `elements/separator`)**: Đường phân cách trang trí.
- **Image Box (`elements/Image box`)**: Khối kết hợp Hình ảnh + Tiêu đề + Mô tả.
- **Icon Box (`elements/Icon box`)**: Khối kết hợp Icon + Tiêu đề + Mô tả.
- **Animated Headline (`elements/animated-headline`)**: Tiêu đề có hiệu ứng chữ chạy / gõ chữ.
- **Price Table & Price List (`elements/price-table`, `elements/price-list`)**: Bảng giá dịch vụ / danh sách thực đơn.
- **Reviews (`elements/reviews`)**: Khối hiển thị điểm số & nhận xét.
- **Code (`elements/code`)**: Khối hiển thị mã nguồn (Code syntax highlighting).
- **Image Compare (`elements/image-compare`)**: So sánh 2 ảnh Before / After kéo trượt.
- **Rating (`elements/rating`)**: Khối đánh giá sao (Star rating).

---

### 🔵 Bootstrap Layout & Controls (Bố cục Grid Bootstrap 4 & 5)
- **Container (`html/container`)**: Khối bao ngoài (`container` / `container-fluid`).
- **Grid Row (`html/gridrow`)**: Dòng chứa các cột grid.
- **Grid Column (`html/gridcolumn`)**: Cột Bootstrap tùy chỉnh độ rộng linh hoạt trên Mobile, Tablet, Desktop.
- **Button Group & Toolbar (`html/buttongroup`, `html/buttontoolbar`)**: Nhóm các nút bấm liền kề.
- **Alert (`html/alert`)**: Khối thông báo (Success, Warning, Danger, Info).
- **Badge (`html/badge`)**: Nhãn nhãn nhỏ (Pill badge).
- **Card (`html/card`)**: Khối thẻ nội dung (Card header, body, footer, image).
- **List Group & Item (`html/listgroup`, `html/listitem`)**: Danh sách nhóm kiểu Bootstrap.
- **Breadcrumbs (`html/breadcrumbs`, `html/breadcrumbitem`)**: Thanh điều hướng phân cấp.
- **Pagination (`html/pagination`, `html/pageitem`)**: Phân trang bài viết.
- **Progress Bar (`html/progress`)**: Thanh tiến trình phần trăm.
- **Navbar (`html/navbar`)**: Thanh menu điều hướng website.

---

### 🟣 Embeds & Social Media Widgets (Nhúng & Tiện ích)
- **OEmbed Support**: Nhúng link tự động hơn 25+ nền tảng:
  - YouTube, Vimeo, Dailymotion, Flickr, SmugMug, Scribd, Twitter, Soundcloud, Slideshare, Spotify, Imgur, Issuu, Mixcloud, TED, Animoto, Tumblr, Kickstarter, ReverbNation, Reddit, SpeakerDeck, Screencast, Amazon, Someecards, TikTok, Pinterest, Wolfram, Anghami.
- **Interactive Widgets**:
  - **Google Maps (`widgets/googlemaps`)**: Bản đồ Google Map tương tác.
  - **OpenStreetMap (`widgets/openstreetmap`)**: Bản đồ OpenSource miễn phí.
  - **Embed Video (`widgets/embed-video`)**: Video nhúng tùy chỉnh ratio.
  - **Facebook Comments & Page (`widgets/facebookcomments`, `widgets/facebookpage`)**: Fanpage & Bình luận Facebook.
  - **Instagram (`widgets/instagram`)**: Widget hiển thị ảnh Instagram.
  - **Twitter Feed (`widgets/twitter`)**: Khối bài viết Twitter.
  - **PayPal Button (`widgets/paypal`)**: Nút thanh toán PayPal.
  - **Chart.js (`widgets/chartjs`)**: Biểu đồ dữ liệu động (Bar, Line, Pie, Doughnut).
  - **Lottie Animation (`widgets/lottie`)**: Hiệu ứng chuyển động vector Lottie JSON.

---

### 🔴 WordPress & Align Theme Dynamic Blocks (Khối Động WordPress & Theme)
- **Khối Tích Hợp WordPress**:
  - **WP Reusable Blocks (`wp_block`)**: Khối Reusable Block / Synced Pattern độc lập với shortcode mặc định `[reuse-block id="123"]` (hỗ trợ `[reuse_block]`, `[align_block]`, `[align_reusable_block]`, `[reusable_block]`) và hiển thị badge 1-click copy ở thanh Top Panel Header bar.
  - **WP Shortcode (`wordpress/shortcode`)**: Chèn Shortcode WordPress với xem thử thời gian thực (Live REST API rendering).
  - **WP Gutenberg Block (`wordpress/block`)**: Chèn mã block Gutenberg hoặc Synced Pattern.
  - **WP Live Search (`wordpress/live-search`)**: Khối tìm kiếm trực tiếp tích hợp Align Live Search (`align/live-search`) với Live REST/AJAX Preview (Shadow DOM). Clone 1:1 toàn bộ 4 nhóm Inspector Controls từ Gutenberg (Search & Query Settings, Display Options, Technical & Redirection, Gutenberg Block Markup 2-way sync) với thiết kế Stacked Full-Width (`col: 12, inline: false`), lọc Post Types (All/Post/Page/Product/Guide/Custom), Taxonomy & Term IDs, Range Slider Max Results, Mode Toggle switch (Live vs Normal), và cơ chế tự động nhận diện Block Matching trong `builder-components-registry.js`.
  - **WP Post Content (`wordpress/post-content`)**: Khối hiển thị nội dung bài viết động Universal ("Chỉ cần gọi là chạy") trong FSE Templates, Pages, và Blocks (hỗ trợ tag atomic `<div data-component-post-content>`, bộ 4 shortcodes `[wp_content]`, `[post_content]`, `[align_content]`, `[align_post_content]` với tham số `id`, `slug`, `words`, `strip_tags`, `raw`, `tag`, `class`, cơ chế phân giải ngữ cảnh 7 tầng, Zero-Bloat DOM replacement và Anti-Recursion Stack).
- **Khối Động Độc Quyền Của Align Theme (`core/blocks/blocks/dynamic/`)**:
  1. `align/custom-html`: Khối chuyển đổi và hiển thị toàn bộ HTML, Scoped CSS, Inline JS (không ép bọc IIFE để lập trình viên tự do định nghĩa global/local scope) và tự động thực thi WordPress Shortcode (`do_shortcode()`) từ Align Builder sang WordPress.
  2. `align/breadcrumb`: Thanh điều hướng Breadcrumb động tự lấy danh mục / phân cấp trang.
  3. `align/element`: Khối Element linh hoạt hỗ trợ Tab Effects và bộ lọc hiệu ứng.
  4. `align/icon`: Khối Icon tương thích Lucide Icons với SVG mượt mà.
  5. `align/live-search`: Khối tìm kiếm trực tiếp với Live AJAX Preview & Auto-complete.
  6. `align/marquee`: Khối chạy chữ / logo trượt vô tận (Infinite Marquee).
  7. `align/media`: Khối media đa năng (ảnh, video, hình nền responsive).
  8. `align/query-builder`: Trình tạo truy vấn bài viết / sản phẩm / CPT động chuyên sâu với Splide Slider, Grid Layout, Pagination, Filter và tích hợp Rank Math / ACF.
  9. `align/share`: Khối chia sẻ mạng xã hội nhanh.

---

### 🟠 Template Blocks & Sections (Các Mẫu Dựng Sẵn)
- **Bootstrap 4 Pre-built Blocks**:
  - Product Card (`bootstrap4/product-card`)
  - User Online Status (`bootstrap4/user-online`)
  - Our Team (`bootstrap4/our-team`)
  - Login Form (`bootstrap4/login-form`)
  - About Team (`bootstrap4/about-team`)
  - Pricing Table (`bootstrap4/pricing-1`)
  - Loading Circle (`bootstrap4/loading-circle`)
  - Block Quote (`bootstrap4/block-quote`)
  - Subscribe Newsletter (`bootstrap4/subscribe-newsletter`)
- **Sections**:
  - About Team Section (`bootstrap4/about-team`)
  - Portfolio 1 Column (`bootstrap4/portfolio-one-column`)
  - Portfolio 2 Columns (`bootstrap4/portfolio-two-column`)
  - Portfolio 3 Columns (`bootstrap4/portfolio-three-column`)
  - Portfolio 4 Columns (`bootstrap4/portfolio-four-column`)
  - Reusable Saved Sections (`reusable/*`)

---

## 2. 🎨 Bảng Điều Khiển Tùy Chỉnh Style (Styling Capabilities)

Mỗi phần tử trong Editor đều có thể tinh chỉnh CSS chi tiết thông qua tab **Style** và **Properties**:

### 1. General & Content (Nội dung & Nhãn)
- **ID (`id`)**: Định danh duy nhất cho phần tử.
- **Title (`title`)**: Thẻ thuộc tính tiêu đề HTML.
- **Class (`class`)**: Thêm bớt class CSS với gợi ý tự động (TagsInput auto-complete từ toàn trang).

### 2. Display & Layout (Bố cục & Hiển thị)
- **Display**: `block`, `inline`, `inline-block`, `flex`, `inline-flex`, `grid`, `inline-grid`, `none`.
- **Flexbox Controls**:
  - `flex-direction`: row, column, row-reverse, column-reverse.
  - `justify-content`: flex-start, center, flex-end, space-between, space-around, space-evenly.
  - `align-items`: flex-start, center, flex-end, stretch, baseline.
  - `flex-wrap`: wrap, nowrap, wrap-reverse.
- **Grid Layout Controls**: Số cột grid, gap giữa các cột/dòng.
- **Positioning**: `static`, `relative`, `absolute`, `fixed`, `sticky`.
- **Offsets & Z-Index**: Top, Right, Bottom, Left, Z-Index, Float, Clear.

### 3. Typography (Kiểu Chữ & Văn Bản)
- **Font Family**: Tích hợp Google Fonts tự động tải font chữ + các phông hệ thống.
- **Font Size & Unit**: Cho phép chọn đơn vị (`px`, `rem`, `em`, `%`, `vh`, `vw`).
- **Font Weight**: 100 đến 900, Bold, Normal, Lighter.
- **Text Color**: Bộ chọn màu **Coloris** chuyên nghiệp (HEX, RGB, HSL, RGBA).
- **Line Height & Letter Spacing**: Khoảng cách dòng và khoảng cách chữ.
- **Text Align**: `left`, `center`, `right`, `justify`.
- **Text Transform**: `capitalize`, `uppercase`, `lowercase`, `none`.
- **Text Decoration**: `underline`, `line-through`, `overline`, `none`.
- **Text Shadow**: Đổ bóng chữ.

### 4. Size & Dimensions (Kích Thước)
- Width, Height, Min-Width, Max-Width, Min-Height, Max-Height.
- Hỗ trợ đa đơn vị tính: `px`, `%`, `rem`, `em`, `vh`, `vw`, `auto`.

### 5. Spacing (Margin & Padding)
- **Margin**: Top, Right, Bottom, Left (Hỗ trợ khóa đồng bộ 4 chiều hoặc chỉnh độc lập).
- **Padding**: Top, Right, Bottom, Left (Hỗ trợ khóa đồng bộ 4 chiều hoặc chỉnh độc lập).

### 6. Border & Radius (Viền & Bo Góc)
- **Border Style**: `solid`, `dashed`, `dotted`, `double`, `groove`, `ridge`, `inset`, `outset`, `none`.
- **Border Width & Color**: Độ dày đường viền và màu sắc viền.
- **Border Radius**: Bo góc độc lập 4 góc (Top-Left, Top-Right, Bottom-Right, Bottom-Left) hoặc các preset bo tròn.

### 7. Background (Nền)
- **Background Color**: Màu nền đơn sắc.
- **Background Image**: Chọn ảnh từ WordPress Media Library hoặc kho ảnh miễn phí Openverse. Hỗ trợ cơ chế quét 3 tầng thông minh (Inline style $\rightarrow$ Lazyload `data-bg-src` $\rightarrow$ Iframe Computed Style từ class CSS độc lập) và bộ bóc tách URL đa lớp (Quoted/Unquoted URL, an toàn với tên file có dấu ngoặc đơn `()` và query strings).
- **Cách Ly Ngữ Cảnh (Target Isolation)**: Khi gán ảnh nền cho Section/Container có chứa ảnh con `<img>`, hệ thống chỉ gán background cho container cha và không làm biến đổi cấu trúc các thẻ ảnh con bên trong.
- **Background Repeat**: `no-repeat`, `repeat`, `repeat-x`, `repeat-y`.
- **Background Position & Size**: `cover`, `contain`, `auto`, căn vị trí nền.
- **Background Attachment**: `scroll`, `fixed` (hiệu ứng Parallax).
- **Background Gradient**: Nền dải màu chuyển tuyến tính (Linear) hoặc dải màu tâm (Radial).

### 8. Responsive Visibility (Ẩn/Hiện Thiết Bị)
- Công tắc bật/tắt ẩn element trên **Mobile**, **Tablet**, **Desktop**.

---

## 3. ✨ Hệ Thống Hiệu Ứng Animation (AOS - Animate On Scroll)

Tất cả các phần tử trong builder đều được tích hợp thư viện **AOS (Animate On Scroll)** trong tab Advanced:

### 🎭 Các Kiểu Hiệu Ứng (Animation Types)
- **Fade Animations (Hiệu ứng mờ dần)**:
  - `fade`: Mờ dần tại chỗ
  - `fade-up`: Mờ dần từ dưới lên
  - `fade-down`: Mờ dần từ trên xuống
  - `fade-left`: Mờ dần từ trái sang
  - `fade-right`: Mờ dần từ phải sang
  - `fade-up-right`: Mờ dần từ góc dưới trái lên phải
  - `fade-up-left`: Mờ dần từ góc dưới phải lên trái
  - `fade-down-right`: Mờ dần từ góc trên trái xuống phải
  - `fade-down-left`: Mờ dần từ góc trên phải xuống trái
- **Flip Animations (Hiệu ứng lật)**:
  - `flip-up`: Lật ngửa lên
  - `flip-down`: Lật úp xuống
  - `flip-left`: Lật từ trái qua
  - `flip-right`: Lật từ phải qua
- **Slide Animations (Hiệu ứng trượt)**:
  - `slide-up`: Trượt từ dưới lên
  - `slide-down`: Trượt từ trên xuống
  - `slide-left`: Trượt từ trái qua
  - `slide-right`: Trượt từ phải qua
- **Zoom Animations (Hiệu ứng phóng to / Thu nhỏ)**:
  - `zoom-in`, `zoom-in-up`, `zoom-in-down`, `zoom-in-left`, `zoom-in-right`
  - `zoom-out`, `zoom-out-up`, `zoom-out-down`, `zoom-out-left`, `zoom-out-right`

### ⚙️ Cấu Hình Animation Controls
- **Duration (`data-aos-duration`)**: Thời gian chạy hiệu ứng (0ms - 10000ms, bước nhảy 100ms, mặc định: 1000ms).
- **Delay (`data-aos-delay`)**: Thời gian chờ trước khi chạy (0ms - 10000ms, mặc định: 0ms).
- **Play Animation Preview**: Nút bấm phát thử hiệu ứng trực tiếp ngay trong màn hình soạn thảo.

---

## 4. 🎛️ Bộ Input Controls (Thành Phần Nhập Liệu Trong Sidebar Builder)

Builder sở hữu bộ Input Controls vô cùng phong phú để phục vụ thiết lập thuộc tính:
- `TextInput`: Ô nhập văn bản dòng đơn.
- `TextareaInput`: Ô nhập văn bản nhiều dòng.
- `CheckboxInput` & `ToggleInput`: Công tắc bật/tắt tùy chọn.
- `RadioInput` & `RadioButtonInput`: Chọn 1 trong nhiều tùy chọn dạng nút.
- `SelectInput`: Danh sách xổ xuống.
- `IconSelectInput`: Chọn biểu tượng xem trước.
- `HtmlListSelectInput`: Danh sách chọn có định dạng HTML.
- `LinkInput`: Ô nhập liên kết.
- `DateInput`: Chọn ngày tháng.
- `RangeInput`: Thanh trượt chọn giá trị số.
- `NumberInput`: Ô nhập số.
- `CssUnitInput`: Ô nhập thông số CSS kèm chọn đơn vị (`px`, `rem`, `em`, `%`, `vh`, `vw`).
- `ColorInput`: Ô chọn màu sắc tích hợp Coloris Color Picker.
- `ImageInput` / `VideoInput` / `SvgInput`: Ô chọn media tích hợp thư viện WordPress Media Manager.
- `FileUploadInput`: Tải file trực tiếp.
- `GridLayoutInput` & `GridInput`: Điều chỉnh chia cột giao diện.
- `TagsInput`: Thêm thẻ tag / class CSS tự động gợi ý.
- `AutocompleteInput`: Nhập tự động hoàn thành từ dữ liệu API.
- `NoticeInput`: Khối hiển thị cảnh báo/hướng dẫn trong sidebar.
- `SectionInput`: Nhóm phân vùng các tùy chọn trong sidebar.

---
*Tài liệu được quét và tổng hợp tự động từ nguồn Align Theme Page Builder.*
