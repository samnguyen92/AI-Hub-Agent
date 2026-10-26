# 🔄 Tài Liệu Kỹ Thuật: Hệ Thống Git Theme Core Updater

> [!NOTE]
> Module **Git Theme Core Updater** (`core/includes/admin/class-align-git-updater.php` & `tab-git-updater.php`) cung cấp giải pháp kéo (pull) và cập nhật mã nguồn trực tiếp từ GitHub Repository về môi trường WordPress mà **không yêu cầu cài đặt Git CLI trên máy chủ**.

---

## 1. Tổng Quan Kiến Trúc & Luồng Dữ Liệu (System Architecture)

```mermaid
graph TD
    UserClick[Admin Click: Update Theme] --> AjaxVerify[1. AJAX Verify: align_git_test_connection]
    AjaxVerify --> GhBranchAPI[GitHub API: /repos/.../branches/main]
    GhBranchAPI --> ConfirmModal[Display Latest Commit & Confirm]
    
    ConfirmModal --> AjaxPull[2. AJAX Pull: align_git_pull_code]
    AjaxPull --> DownloadZip[GitHub API: /repos/.../zipball/main]
    DownloadZip --> TempZip[Stream to Temp File: align_git_zip_*]
    
    TempZip --> MaintOn[3. Enable WordPress Maintenance Mode (.maintenance)]
    MaintOn --> ExtractZip[4. Extract Zip via WP_Filesystem to temp dir]
    ExtractZip --> BackupCurrent[5. Safety Backup current /themes/align/]
    
    BackupCurrent --> CleanWipe[6. Wipe existing /themes/align/ completely]
    CleanWipe --> CopyFresh[7. Copy fresh align/ into /wp-content/themes/align/]
    CopyFresh --> CopyRootFiles[8. Sync AGENTS.md, .gitignore, index.php to themes root]
    
    CopyFresh -- Success --> CleanupTemp[9. Cleanup Temp & Backup Dirs]
    CopyFresh -- Error --> Rollback[Rollback from Backup to /themes/align/]
    
    CleanupTemp --> MaintOff[10. Disable Maintenance Mode]
    Rollback --> MaintOff
    MaintOff --> LogUI[11. Live Terminal Console Update & Alert Success]
```

---

## 2. Bản Đồ File & Trách Nhiệm (Component Map)

| File | Trách Nhiệm / Chức Năng | Quy Chuẩn |
| :--- | :--- | :--- |
| [`class-align-git-updater.php`](../../core/includes/admin/class-align-git-updater.php) | Backend Engine: Tải zipball, xác thực GitHub API, bật/tắt `.maintenance`, clean replace thư mục, rollback an toàn, webhook endpoint. | `< 500 dòng` (320 dòng), SRP |
| [`tab-git-updater.php`](../../core/includes/admin/views/tab-git-updater.php) | Admin UI View: Giao diện tối giản 1 nút bấm (Single Button), thẻ thời gian cập nhật, Live Terminal Console hiển thị log thời gian thực. | `< 500 dòng` (125 dòng) |
| [`class-align-admin-settings.php`](../../core/includes/admin/class-align-admin-settings.php) | Register Settings & Nonces: Quản lý nhóm cấu hình `align_settings_group`. | `< 500 dòng` |
| [`layout.php`](../../core/includes/admin/views/layout.php) | Admin Layout: Điều hướng tab `#git-updater` và persistence state. | `< 500 dòng` |

---

## 3. Các Cơ Chế Kỹ Thuật Trọng Yếu

### 3.1. Nhận Diện Cấu Trúc Themes Root & Định Vị Đệ Quy (Smart Recursive Package Locator)
- Repository trên GitHub ánh xạ tương ứng với cấp thư mục `/wp-content/themes/`:
  ```text
  GitHub Repository (bibikeo1996/Align-Real-Builder)
  ├── align/          <── Thư mục Theme Core (được Clean Replace)
  ├── align-child/    <── Theme con (được bảo vệ nguyên vẹn)
  ├── AGENTS.md       <── Quy chuẩn và tài liệu agent
  ├── .gitignore
  └── index.php
  ```
- **Thuật toán định vị đệ quy (`locate_source_align_dir`)**:
  - GitHub zipball đóng gói toàn bộ repo dưới một thư mục commit hash ngẫu nhiên.
  - Bộ định vị đệ quy (`RecursiveDirectoryIterator`) duyệt tìm chính xác thư mục chứa `functions.php`, `style.css` và `core/init.php`.
  - Phân biệt chính xác giữa theme cha `Align` và `align-child` thông qua kiểm tra header metadata `Theme Name: Align` trong `style.css`.
  - Loại bỏ hoàn toàn lỗi *"Downloaded package is corrupted or missing essential theme files"* trên mọi môi trường máy chủ khác nhau (LocalWP, Linux, Windows, Docker).
- **Bộ định vị gốc repo (`locate_github_root`)**:
  - Nhận diện đúng vị trí chứa `AGENTS.md`, `.gitignore`, `index.php` để đồng bộ ra thư mục gốc `wp-content/themes/`.

### 3.2. Chế Độ Bảo Trì Tự Động (WordPress Maintenance Mode)
- Trước khi thực hiện xóa và thay thế file, hệ thống gọi `Align_Git_Updater::set_maintenance_mode( true )` tạo file `ABSPATH . '.maintenance'`.
- Giúp ngăn chặn người dùng ngoài frontend truy cập trúng thời điểm file đang được ghi đè, loại trừ 100% lỗi PHP fatal do thiếu file tạm thời.
- Tự động xóa file `.maintenance` ngay sau khi cập nhật thành công hoặc khi xảy ra ngoại lệ (khối `finally` / `catch`).

### 3.3. Clean Replace, Pre-flight Validation & Strict Rollback Guard
- **Nguyên tắc "Fail-Safe & Zero-Downtime"**: Bất kỳ bước nào không đạt mã HTTP 200 hoặc phát hiện gói tải về không toàn vẹn đều bị **HỦY BỎ NGAY LẬP TỨC** trước khi chạm vào file của hệ thống.
- **Quy trình 5 tầng kiểm soát an toàn**:
  1. **Strict HTTP 200 & Non-Empty Check**: Nếu GitHub API trả về mã lỗi (khác 200) hoặc file zip rỗng (0 bytes) -> Abort ngay lập tức, không thay đổi bất cứ file nào.
  2. **Pre-flight Integrity Check**: Sau khi giải nén vào thư mục tạm, kiểm tra bắt buộc gói mã nguồn mới phải có các file cốt lõi (`functions.php`, `style.css`, `core/init.php`). Nếu phát hiện gói hỏng/thiếu file -> Abort ngay lập tức.
  3. **Verified Safety Backup**: Trước khi xóa thư mục cũ, sao chép toàn bộ thư mục `/wp-content/themes/align` hiện tại vào thư mục tạm `align_theme_backup_{id}` và xác minh bản backup hợp lệ.
  4. **Post-Copy Sanity Verification**: Sau khi copy file mới vào `/wp-content/themes/align`, kiểm tra lại sự hiện diện của `functions.php` và `core/init.php`. Nếu không đạt -> Kích hoạt Rollback.
  5. **Automatic Atomic Rollback**: Nếu xảy ra bất kỳ ngoại lệ hoặc lỗi ghi file -> Hệ thống tự động xóa phần copy dở, phục hồi 100% nguyên trạng từ thư mục backup và tắt Maintenance Mode để website tiếp tục hoạt động bình thường.

### 3.4. Cơ Chế Lưu Snapshot Độc Lập & Nút Phục Hồi 1-Click (Persistent Backup & One-Click Revert)
- **Lưu trữ Snapshot độc lập**: Trước mỗi lần cập nhật, hệ thống tự động nén toàn bộ thư mục theme hiện tại thành file `align-theme-backup-latest.zip` và lưu trữ tại thư mục an toàn:
  ```text
  /wp-content/uploads/align-backups/align-theme-backup-latest.zip
  ```
  *(Thư mục này nằm hoàn toàn bên ngoài theme và được bảo vệ bằng file `.htaccess` chặn truy cập trực tiếp từ bên ngoài)*.
- **Nút "Revert to Backup" chủ động**: Nếu sau khi cập nhật, lập trình viên/admin phát hiện code mới trên GitHub có function bị lỗi logic hoặc gây xung đột với plugin khác:
  - Chỉ cần vào **Align Settings ➔ Git Updater**.
  - Nhấn nút **`[ ↩️ Revert to Backup ]`**.
  - Hệ thống sẽ tự động bung bản snapshot trước đó ra và phục hồi nguyên vẹn 100% theme sạch sẽ trong vòng 1 giây.

### 3.5. Lối Thoát Hiểm Khẩn Cấp (Emergency Standalone URL Revert)
- Trong kịch bản xấu nhất (ví dụ code mới trên GitHub bị lỗi Fatal PHP Error làm sập hoặc trắng trang admin):
  - Bản snapshot ZIP `align-theme-backup-latest.zip` nằm an toàn tại `wp-content/uploads/align-backups/`.
  - Quản trị viên chỉ cần truy cập trực tiếp đường link khẩn cấp:
    ```text
    https://your-domain.com/?align-emergency-revert=1
    ```
    *(Chỉ tài khoản Admin đã đăng nhập hoặc request có secret webhook token mới có quyền kích hoạt)*.
  - Hệ thống tự động phục hồi lại 100% theme sạch sẽ từ bản snapshot mà không cần truy cập vào trang Admin Settings.

### 3.6. Cấu Hình Cố Định, Bảo Mật Token & Cơ Chế Elvis Fallback
- **Repository mặc định**: `bibikeo1996/Align-Real-Builder`
- **Branch mặc định**: `main`
- **Personal Access Token**: Sử dụng header `Authorization: token {PAT}` đối với các request tải zipball từ Private Repo.
- **Cơ chế Elvis Fallback chống ghi đè option rỗng**:
  - Khi form Settings lưu từ các tab khác, WordPress có thể lưu `align_git_token = ""` vào `wp_options`.
  - `Align_Git_Updater` sử dụng cú pháp `get_option('align_git_token') ?: self::DEFAULT_TOKEN` để luôn ưu tiên lấy token mặc định trong code nếu option trong DB rỗng, bảo vệ 100% luồng xác thực Private Repo.
- **Security Check**: Toàn bộ AJAX requests đều bắt buộc kiểm tra quyền `current_user_can('manage_options')` và xác thực `wp_verify_nonce(..., 'align_settings_nonce')`.

### 3.7. Push-to-Deploy Webhook (Tùy Chọn)
- Endpoint listener tại `init` hook:
  ```text
  https://your-domain.com/?align-git-webhook=1&token={SECRET_TOKEN}
  ```
- Khi có webhook push event từ GitHub, hệ thống xác thực secret token và kích hoạt ngầm quy trình `perform_pull()`.

### 3.8. Tương Thích Tuyệt Đối Mọi Môi Trường Hosting (Universal Hosting Compatibility)
- **Kháng giới hạn `open_basedir` (`get_temp_working_dir`)**:
  - Tự động sử dụng thư mục tạm an toàn `wp-content/uploads/align-temp/` thay cho `/tmp/` hệ thống. Đảm bảo 100% tuân thủ cấu hình `open_basedir` trên các Shared Hosting (cPanel, DirectAdmin, Plesk, Cloudways).
- **Định vị theme cha tuyệt đối bằng `get_template_directory()`**:
  - Không dựa vào chuỗi tĩnh. Sử dụng hàm chuẩn WordPress `get_template_directory()` để luôn trỏ đúng thư mục cha `align` dù người dùng đang active child theme hay tùy biến `WP_CONTENT_DIR` (Bedrock, Roots, Symlinks).
- **Làm sạch Zend OPcache (`opcache_reset`)**:
  - Tự động gọi `@opcache_reset()` ngay sau khi thay thế file, loại bỏ triệt để tình trạng máy chủ PHP 8+ phục vụ file bytecode cũ từ bộ nhớ RAM.
- **Chuẩn hóa đường dẫn đa nền tảng (`wp_normalize_path`)**:
  - Tự động chuẩn hóa dấu phân cách thư mục trên Windows (`\`), macOS và Linux (`/`) về định dạng thống nhất.
- **Trực quan hóa môi trường thực thi (Environment Observability)**:
  - Bảng điều khiển hiển thị trực tiếp `Current Site URL`, `Theme Path` và badge hệ điều hành (`OS: DARWIN`, `OS: WINNT`, `OS: LINUX`) giúp quản trị viên nhận biết chính xác máy chủ đang thao tác.

---

## 4. Quy Chuẩn Observability & Debug Log

Toàn bộ quá trình chạy đều được ghi nhận song song:
1. **Frontend**: Xuất trực tiếp lên **Dark Terminal Console** với định dạng chuẩn:
   ```text
   [10:00:32 PM] [Align GitUpdater] Ready. Site: http://localhost:10070 | OS: DARWIN
   [10:00:33 PM] [Align GitUpdater] Connecting to GitHub repository: bibikeo1996/Align-Real-Builder (branch: main)...
   [10:00:34 PM] [Align GitUpdater] Connection verified! Latest commit: 8581c93 by JoePham
   [10:00:35 PM] [Align GitUpdater] Downloading repository zipball from GitHub API...
   [10:00:36 PM] [Align GitUpdater] Archive downloaded (1250.4 KB).
   [10:00:36 PM] [Align GitUpdater] Maintenance mode enabled & files cleanly replaced.
   [10:00:37 PM] [Align GitUpdater] Align Theme Core updated successfully!
   ```
2. **Backend PHP `error_log`**: Luôn sử dụng tiền tố tag `[Align GitUpdater]` phục vụ tra cứu hệ thống.
