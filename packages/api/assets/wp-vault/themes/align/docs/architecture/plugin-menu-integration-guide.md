# 🧭 Align Ecosystem Admin Menu Integration Guide

Tài liệu này quy định quy chuẩn kiến trúc và cơ chế hook bắt buộc dành cho mọi plugin trong hệ sinh thái **Align** (`align-files`, `align-seo-checker`, `align-deploy`, `align-agent`, v.v.) nhằm tích hợp đồng bộ vào thanh điều hướng quản trị của **Align Theme**.

---

## 🎯 1. Mục Tiêu Kiến Trúc

Thay vì để các plugin của Align đăng ký rải rác dưới menu mặc định của WordPress (`Settings` > `options-general.php`) hoặc tạo nhiều top-level menu gây rối sidebar:
- **Align Theme** tạo duy nhất **1 Top-Level Menu** ở dưới cùng thanh sidebar WordPress Admin:
  - **Title**: `Align Theme`
  - **Menu Slug**: `align-settings` (`admin.php?page=align-settings`)
  - **Icon**: `dashicons-align-left`
  - **Position**: `99` (dưới cùng, ngay trên nút Thu gọn menu)
- Toàn bộ các plugin Align khi được kích hoạt (`active`) sẽ **tự động trở thành submenu** nằm bên trong menu `Align Theme`:
  - 📊 **Dashboard** (`align-settings`): Bảng điều khiển Master của Theme.
  - 🗂️ **Align Files** (`align-files-settings`): Quản lý virtual folder và Image AI Vision SEO.
  - 🔍 **Align SEO Checker** (`align-seo-checker`): Kiểm tra, audit và sửa lỗi SEO on-page.
  - 🚀 **Align Deploy** (`align-deploy`): Đóng gói ZIP, staging to live deploy và rescue engine.
  - 🤖 **Align Agent Gateway** (`align-agent`): AI Agent & Telegram bot gateway.

---

## 🔌 2. Quy Chuẩn Hook Cho Plugin (Plugin Standard)

Khi xây dựng hoặc phát triển plugin mới cho Align, **BẮT BUỘC** áp dụng một trong hai cách thức sau để đăng ký menu quản trị:

### Cách 1: Tự Động Nhận Diện Theme (Recommended - Zero Dependencies)
Cách tiếp cận này giúp plugin vừa tích hợp hoàn hảo với Align Theme, vừa có cơ chế fallback an toàn nếu người dùng kích hoạt plugin trên theme khác.

```php
/**
 * Register admin menu for Align Plugin
 */
public function register_menu(): void {
    // 1. Kiểm tra hằng số đặc trưng của Align Theme
    $parent_slug = defined( 'ALIGN_CORE_PATH' ) ? 'align-settings' : 'options-general.php';

    // 2. Đăng ký submenu thông qua add_submenu_page
    add_submenu_page(
        $parent_slug,
        __( 'Align Files', 'align-files' ),
        __( 'Align Files', 'align-files' ),
        'manage_options',
        'align-files-settings',
        [ $this, 'render_settings_page' ]
    );
}
add_action( 'admin_menu', [ $this, 'register_menu' ] );
```

> [!TIP]
> **Quy tắc vàng**: Tuyệt đối **KHÔNG** dùng hàm `add_options_page()` trong các plugin Align vì hàm này ép cứng menu cha vào `options-general.php` (Settings). Hãy luôn dùng `add_submenu_page( $parent_slug, ... )`.

---

### Cách 2: Lắng Nghe Action Hook Từ Align Theme (`align_register_admin_submenus`)
Theme Align phát ra action hook tại hook `admin_menu` (priority 9):

```php
// Trong Align Theme: class-align-admin-menu.php
do_action( 'align_register_admin_submenus', self::MENU_SLUG );
```

Plugin chỉ cần đăng ký:
```php
add_action( 'align_register_admin_submenus', function( $parent_slug ) {
    add_submenu_page(
        $parent_slug,
        'My Align Tool',
        'My Align Tool',
        'manage_options',
        'my-align-tool',
        [ $this, 'render_page' ]
    );
} );
```

---

## 🛡️ 3. Cơ Chế Auto-Adoption & Dọn Dẹp Từ Align Theme

Để đảm bảo khả năng tương thích ngược (Backward Compatibility) ngay cả khi plugin cũ vô tình gọi `add_options_page()`, Theme Align đã trang bị bộ dọn dẹp tại `admin_menu` (priority 99) trong [`class-align-admin-menu.php`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/includes/admin/class-align-admin-menu.php):

```php
public static function adopt_and_clean_menus(): void {
    // Clean up any Align plugin submenus from under WordPress Settings (options-general.php)
    $align_submenus = [
        'align-files-settings',
        'align-seo-checker',
        'align-deploy',
        'align-agent',
    ];

    foreach ( $align_submenus as $slug ) {
        remove_submenu_page( 'options-general.php', $slug );
    }
}
```

Hàm này sẽ tự động:
1. Gỡ bỏ mọi submenu của Align khỏi menu `Settings` (`options-general.php`).
2. Giữ thanh menu WordPress luôn gọn gàng và phân cấp chuyên nghiệp.

---

## 📋 4. Danh Sách 4 Plugin Align Hiện Tại

| Plugin Directory | Tên Plugin | Submenu Slug | Trạng Thái Hook |
| :--- | :--- | :--- | :--- |
| `align-files` | Align Files | `align-files-settings` | ✅ Hook chuẩn `align-settings` |
| `align-seo-checker` | Align SEO Checker | `align-seo-checker` | ✅ Hook chuẩn `align-settings` |
| `align-deploy` | Align Deploy | `align-deploy` | ✅ Hook chuẩn `align-settings` |
| `align-agent` | Align Agent Gateway | `align-agent` | ✅ Hook chuẩn `align-settings` |
