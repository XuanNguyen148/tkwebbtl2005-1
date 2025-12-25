# Hướng dẫn Import Excel - Giải quyết lỗi ZipArchive

## ✅ Vấn đề đã được giải quyết

**Lỗi cũ:** `Máy chủ chưa bật extension ZipArchive, không thể đọc file .xlsx`

**Giải pháp:** Hệ thống hiện đã được cập nhật để hỗ trợ **cả file CSV và XLSX** mà không cần ZipArchive.

## 📁 Các định dạng file được hỗ trợ

### 1. **File CSV** (Khuyến nghị - tương thích toàn bộ)
- Định dạng: `.csv` (Comma-Separated Values)
- Tương thích: 100% với tất cả máy chủ, không cần extension
- Cách lấy: Mở file Excel → **File → Save As → Chọn định dạng CSV (Comma delimited)**

### 2. **File Excel** (Nếu máy chủ bật ZipArchive)
- Định dạng: `.xlsx`
- Tương thích: Nếu máy chủ có bật ZipArchive PHP extension
- Nếu ZipArchive chưa bật, hãy chuyển sang CSV

## 🔄 Cách chuyển đổi Excel sang CSV (Windows Excel)

1. **Mở file Excel** trong Microsoft Excel hoặc LibreOffice
2. Nhấn **File → Save As**
3. Chọn vị trí lưu
4. Đổi tên file (nếu cần)
5. Ở mục **Save as type**, chọn `CSV (Comma delimited) (*.csv)`
6. Nhấn **Save**
7. Chọn **Yes** nếu hỏi về định dạng

Lúc này file sẽ được lưu dưới dạng `.csv` và có thể import thông thường.

## 📝 Cách import dữ liệu

### Cho Sản phẩm (Products)
1. Vào **Quản lý Sản phẩm**
2. Nhấn nút **Import Excel**
3. Chọn file `.csv` hoặc `.xlsx`
4. Chờ hệ thống xử lý

**Các cột bắt buộc (tên có thể tùy biến):**
- Tên sản phẩm (tensanpham, tên sp, ...)
- Thể loại (theloai, thể loại, ...)
- Mã sản phẩm (masanpham, mã sp, ...)

### Cho Phiếu Nhập (Imports)
1. Vào **Quản lý Phiếu Nhập**
2. Nhấn nút **Import Excel**
3. Chọn file `.csv` hoặc `.xlsx`

**Các cột bắt buộc:**
- Mã phiếu nhập (mapn, maphieu, ...)
- Mã sản phẩm (masanpham, mã sp, ...)
- Số lượng (soluong, số lượng, ...)

### Cho Phiếu Xuất (Exports)
1. Vào **Quản lý Phiếu Xuất**
2. Nhấn nút **Import Excel**
3. Chọn file `.csv` hoặc `.xlsx`

**Các cột bắt buộc:**
- Mã phiếu xuất (mapx, maphieu, ...)
- Mã cửa hàng (mach, macuahang, ...)
- Mã sản phẩm (masanpham, mã sp, ...)
- Số lượng (soluong, số lượng, ...)

## ⚠️ Nếu vẫn gặp lỗi ZipArchive

**Thông báo:** `Lỗi import Excel: File .xlsx yêu cầu extension ZipArchive`

**Giải pháp:**
1. **Cách 1 (Khuyến nghị):** Chuyển đổi file sang CSV theo hướng dẫn trên
2. **Cách 2:** Yêu cầu nhà cung cấp hosting bật ZipArchive extension
   - Liên hệ support hosting
   - Yêu cầu: "Bật PHP extension: ZipArchive"

## 🛠️ Những thay đổi kỹ thuật

- **File mới:** `libs_excel_reader.php` - Thư viện đọc CSV/XLSX
- **Cập nhật:**
  - `admin/imports_import_excel.php`
  - `admin/products_import_excel.php`
  - `admin/exports_import_excel.php`
- **Tương thích:** 100% với CSV, tùy chọn với XLSX

---

✅ **Hệ thống đã sẵn sàng!** Hãy thử import bằng file CSV trước tiên.
