# Hướng Dẫn Quy Trình Git & Chuẩn Push Code (Git Workflow Guide)

> [!IMPORTANT]
> **Quy Tắc Vàng**: Repository Git của dự án này là **`Align-Themes`** với thư mục gốc nằm tại **`wp-content/themes/`**. Tuyệt đối **KHÔNG** khởi tạo `.git` riêng bên trong thư mục con `align/` hay `align-child/`.

---

## 1. Cấu Trúc Repository Git (`Align-Themes`)

Repository chứa toàn bộ hệ thống theme của dự án với cấu trúc phân cấp chuẩn:

```text
wp-content/themes/                      <--- ROOT CỦA GIT REPOSITORY (.git tại đây)
├── .gitignore                          <--- File cấu hình loại trừ rác & FSE templates
├── index.php                           <--- File index bảo vệ thư mục
├── align/                              <--- THEME CHA (Parent Theme)
│   ├── core/                           <--- Toàn bộ nhân xử lý (Builder, Blocks, CPT, Includes)
│   ├── docs/                           <--- Tài liệu hướng dẫn & tra cứu kỹ thuật
│   ├── functions.php                   <--- Entry point khởi tạo theme
│   ├── style.css                       <--- Khai báo metadata theme
│   └── CHANGELOG.md                    <--- Lịch sử phiên bản & nâng cấp
└── align-child/                        <--- THEME CON (Child Theme)
    ├── assets/                         <--- File font, script, styles riêng của site
    ├── functions.php                   <--- Custom hook & filter của site
    └── style.css                       <--- Khai báo child theme
```

---

## 2. Các Thư Mục Tuyệt Đối Không Track (`.gitignore`)

File `.gitignore` tại thư mục gốc `wp-content/themes/.gitignore` đã được cấu hình loại trừ các thư mục sau:

| Thư Mục / File | Lý Do Loại Trừ |
| :--- | :--- |
| `templates/`, `parts/` | FSE Dynamic Templates do WordPress sinh ra / người dùng sửa trong FSE, không track để tránh conflict. |
| `align-child/assets/dist/`, `src/` | Các thư mục build cũ không còn sử dụng trong child theme. |
| `align-backups/` | Thư mục backup tự động của builder engine. |
| `logs/*.log`, `*.tmp` | Log runtime và file tạm hệ thống. |
| `node_modules/`, `vendor/` | Dependencies bên ngoài. |
| `.DS_Store`, `Thumbs.db` | File rác của hệ điều hành macOS / Windows. |

---

## 3. Quy Trình Commit & Push Chuẩn (Standard Workflow)

Mỗi khi hoàn thành một tính năng hoặc sửa lỗi, thực hiện theo 6 bước sau:

### Bước 1: Mở terminal và chuyển đến đúng thư mục gốc `themes`
```bash
cd /Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes
```

### Bước 2: Kiểm tra trạng thái Git
```bash
git status
```
*Đảm bảo không có các file rác hoặc file nằm sai vị trí.*

### Bước 3: Kéo cập nhật mới nhất từ GitHub về (nếu có)
```bash
git pull --rebase origin main
```

### Bước 4: Thêm toàn bộ file thay đổi vào Staging
```bash
git add -A
```

### Bước 5: Commit với thông điệp rõ ràng (Conventional Commits)
```bash
# Ví dụ khi thêm tính năng mới
git commit -m "feat(builder): overhaul LinkInput dark mode and add compact locked card"

# Ví dụ khi sửa lỗi
git commit -m "fix(builder): resolve canvas helper leak on save and isolate flexbox"

# Ví dụ khi cập nhật tài liệu
git commit -m "docs: update git workflow guide and resolved-issues.md"
```

### Bước 6: Push lên GitHub
```bash
git push origin main
```

---

## 4. Xử Lý Các Sự Cố Thường Gặp (Troubleshooting)

### ⚠️ Sự Cố 1: IDE (VS Code / Antigravity) báo hàng trăm file ảo `align/align/...`
* **Nguyên nhân**: IDE còn lưu cache in-memory của sub-repo cũ.
* **Cách xử lý**:
  1. Bấm vào nút **Refresh 🔄** (hình mũi tên xoay tròn ở góc phải thanh Source Control).
  2. Hoặc nhấn `Cmd + Shift + P` ➔ Chọn `Git: Refresh` (hoặc `Developer: Reload Window`).

---

### ⚠️ Sự Cố 2: Lỡ tạo nhầm `.git` trong thư mục con `themes/align/`
* **Cách xử lý**:
  ```bash
  # Xóa bỏ thư mục .git con
  rm -rf /Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/.git
  
  # Quay lại thư mục gốc themes và kiểm tra
  cd /Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes
  git status
  ```

---

### ⚠️ Sự Cố 3: Push bị từ chối do Remote có commit mới (`Updates were rejected`)
* **Cách xử lý**:
  ```bash
  cd /Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes
  git pull --rebase origin main
  # Nếu có conflict -> sửa conflict -> git add <file> -> git rebase --continue
  git push origin main
  ```

---

### ⚠️ Sự Cố 4: File trong `templates/` hoặc `parts/` vô tình bị Git theo dõi
* **Cách xử lý**:
  ```bash
  cd /Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes
  git rm -r --cached align-child/templates align-child/parts align/templates align/parts 2>/dev/null || true
  git commit -m "chore: untrack dynamic templates and parts"
  git push origin main
  ```
