# 🗂️ Align Visual Builder Manifest & Knowledge Graph Hub

Thư mục này chứa toàn bộ cơ sở dữ liệu tri thức, chỉ mục tìm kiếm và công cụ tìm kiếm ngữ nghĩa dành riêng cho **AI Agent & Developer** khi làm việc với hệ sinh thái Align Visual Builder.

---

## 📁 Danh Mục Tệp Trong Thư Mục

| Tệp | Định dạng | Mục đích & Cách sử dụng tối ưu |
| :--- | :--- | :--- |
| [**`search.js`**](./search.js) | Node.js Script / Module | **Tối ưu số 1 cho AI Agent**: Tìm kiếm ngữ nghĩa theo ý định, chấm điểm độ khớp và tra cứu quan hệ 360 độ (< 150 tokens). Có thể chạy CLI hoặc `require('./search.js')`. |
| [**`manifest.jsonl`**](./manifest.jsonl) | JSON Lines (169 dòng) | **Tối ưu cho tra cứu chính xác**: Dùng với công cụ `grep_search` để lấy đúng 1 dòng JSON của file/class cụ thể (~50 tokens). |
| [**`manifest-summary.json`**](./manifest-summary.json) | JSON (~75 KB) | Bản tóm tắt phân bổ 169 file theo 12 cụm chức năng (Macro view). |
| [**`manifest.json`**](./manifest.json) | JSON Master (~295 KB) | Cơ sở dữ liệu tri thức đầy đủ 100% (Single Source of Truth) cho các script tự động. |

---

## ⚡ Hướng Dẫn Sử Dụng `search.js`

### 1. Dùng qua CLI (Command Line):
```bash
# Tìm kiếm file theo mô tả tính năng / ý định:
node align/core/builder/manifest/search.js "sortable tree"

# Tra cứu quan hệ phụ thuộc 360 độ (File này gọi file nào? Ai gọi file này?):
node align/core/builder/manifest/search.js --related "class-builder-crud-handler.php"

# Lọc theo cụm chức năng:
node align/core/builder/manifest/search.js --cluster "PHP Backend"

# Lọc theo từ khóa chính xác:
node align/core/builder/manifest/search.js --keyword "AST"

# Xuất định dạng JSON thô (phù hợp cho AI parse trực tiếp):
node align/core/builder/manifest/search.js "dual write save" --json
```

### 2. Dùng bằng cách nhúng vào mã nguồn Node.js (`require`):
```javascript
const { search, findRelated, listClusters, getFile } = require('./search.js');

// Tìm kiếm
const results = search('drag drop');

// Lấy quan hệ 360 độ
const rel = findRelated('builder-ast-parser.js');
console.log(rel.relationships.depends_on);
```
