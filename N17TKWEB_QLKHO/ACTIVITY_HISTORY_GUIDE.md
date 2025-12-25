# Hệ Thống Ghi Lịch Sử Hoạt Động - Tài Liệu Triển Khai

## Tổng Quan
Đã triển khai hệ thống ghi lịch sử hoạt động để theo dõi 4 thao tác chính:
- ✅ **Thêm** (Thêm phiếu nhập/xuất)
- ✅ **Sửa** (Sửa phiếu nhập/xuất)
- ✅ **Xóa** (Xóa phiếu nhập/xuất)
- ✅ **Đổi trạng thái** (Thay đổi tình trạng phiếu)

## Cơ Sở Dữ Liệu

### Bảng LICH_SU_HOAT_DONG
```sql
CREATE TABLE LICH_SU_HOAT_DONG (
    MaLS INT AUTO_INCREMENT PRIMARY KEY,
    MaTK INT NOT NULL,
    TenNhanVien VARCHAR(100) NOT NULL,
    LoaiHanhDong VARCHAR(50) NOT NULL,
    DoiTuong VARCHAR(100) NOT NULL,
    ChiTiet TEXT,
    ThoiGian DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaTK) REFERENCES TAIKHOAN(MaTK),
    INDEX idx_matok (MaTK),
    INDEX idx_thoigian (ThoiGian),
    INDEX idx_loaihanhdong (LoaiHanhDong)
);
```

**Cách chạy SQL:**
```bash
mysql -u root -p quanlykhotrangsuc < admin/create_history_table.sql
```

## Các File Được Tạo/Sửa

### 1. **admin/create_history_table.sql** (MỚI)
- Script SQL để tạo bảng `LICH_SU_HOAT_DONG`
- Bảng lưu trữ tất cả lịch sử hoạt động

### 2. **admin/activity_history.php** (MỚI)
- File utility chứa các hàm:
  - `logActivity()` - Ghi lịch sử hoạt động
  - `getActivityHistory()` - Lấy danh sách lịch sử
  - `countActivityHistory()` - Đếm tổng số lịch sử

**Ứng dụng:**
```php
require_once './activity_history.php';

// Ghi lịch sử
logActivity($pdo, $userId, $userName, 'Thêm', 'PN: PN00001', 'Chi tiết thêm phiếu');
```

### 3. **admin/activity_log.php** (MỚI)
- Trang xem lịch sử hoạt động
- Hiển thị theo phân quyền:
  - **Nhân viên**: Chỉ thấy lịch sử của chính mình
  - **Quản lý**: Thấy lịch sử của tất cả nhân viên
- Có phân trang (20 bản ghi/trang)
- Có lọc theo loại hành động
- Giao diện chuyên nghiệp với:
  - Badge màu sắc cho từng loại hành động
  - Thống kê tổng hoạt động
  - Hiển thị chi tiết thao tác

### 4. **admin/imports.php** (SỬA)
- Thêm `require_once './activity_history.php'`
- Thêm ghi lịch sử vào các hành động:
  - **Thêm phiếu**: Ghi loại hành động, mã PN, danh sách sản phẩm
  - **Sửa phiếu**: Ghi loại hành động, mã PN, thông tin sửa
  - **Xóa phiếu**: Ghi loại hành động, mã PN
  - **Đổi trạng thái**: Ghi loại hành động, mã PN, trạng thái cũ → mới
- Thêm link "Lịch Sử Hoạt Động" vào sidebar

### 5. **admin/exports.php** (SỬA)
- Thêm `require_once './activity_history.php'`
- Thêm ghi lịch sử vào các hành động:
  - **Thêm phiếu**: Ghi loại hành động, mã PX, thông tin cửa hàng
  - **Sửa phiếu**: Ghi loại hành động, mã PX
  - **Xóa phiếu**: Ghi loại hành động, mã PX
  - **Đổi trạng thái**: Ghi loại hành động, mã PX, trạng thái cũ → mới
- Thêm link "Lịch Sử Hoạt Động" vào sidebar

## Cấu Trúc Dữ Liệu Được Ghi

### Ví dụ Thêm Phiếu Nhập:
```
MaTK: 1
TenNhanVien: Nguyễn Văn A
LoaiHanhDong: Thêm
DoiTuong: PN: PN00001
ChiTiet: Ngày: 2025-11-17, Sản phẩm: MaSP: SP001, MaSP: SP002
ThoiGian: 2025-11-17 14:30:45
```

### Ví dụ Đổi Trạng Thái:
```
MaTK: 1
TenNhanVien: Nguyễn Văn A
LoaiHanhDong: Đổi trạng thái
DoiTuong: PN: PN00001
ChiTiet: Từ: Đang xử lý → Tới: Đã duyệt
ThoiGian: 2025-11-17 15:00:00
```

## Cách Sử Dụng

### Xem Lịch Sử Hoạt Động
1. Đăng nhập vào hệ thống
2. Click vào "Lịch Sử Hoạt Động" ở sidebar
3. Nếu là **Nhân viên**: Chỉ thấy lịch sử của chính mình
4. Nếu là **Quản lý**: Thấy lịch sử của tất cả nhân viên
5. Có thể lọc theo loại hành động (Thêm, Sửa, Xóa, Đổi trạng thái)

### Ghi Lịch Sử (Tự động)
- Mỗi khi thực hiện 1 trong 4 hành động, hệ thống tự động ghi lại
- Không cần nhân viên bấm nút gì thêm
- Ghi kĩ loại hành động, đối tượng, chi tiết, thời gian, và nhân viên thực hiện

## Tính Năng Chi Tiết

### Trang activity_log.php
- **Hiển thị các cột:**
  - STT (Số thứ tự)
  - Thời Gian (Định dạng: dd/mm/yyyy hh:mm:ss)
  - Nhân Viên (Tên người thực hiện)
  - Hành Động (Thêm/Sửa/Xóa/Đổi trạng thái)
  - Đối Tượng (Mã phiếu)
  - Chi Tiết (Mô tả chi tiết thao tác)

- **Badge Màu Sắc:**
  - 🟢 Thêm: Xanh lá
  - 🔵 Sửa: Xanh dương
  - 🔴 Xóa: Đỏ
  - 🟡 Đổi trạng thái: Vàng

- **Tính Năng:**
  - Phân trang (20 bản ghi/trang)
  - Lọc theo loại hành động
  - Hiển thị tổng số hoạt động
  - Hiển thị giao diện responsive
  - Empty state khi không có lịch sử

### Phân Quyền
- **Nhân viên:**
  - Chỉ thấy lịch sử của chính mình
  - Không thể thấy hoạt động của nhân viên khác

- **Quản lý:**
  - Thấy toàn bộ lịch sử hoạt động của tất cả nhân viên
  - Có thể lọc và xem chi tiết mọi thao tác

## Cài Đặt Ban Đầu

1. **Tạo bảng:**
   ```bash
   mysql -u root -p quanlykhotrangsuc < admin/create_history_table.sql
   ```

2. **Các file đã được cập nhật:**
   - ✅ admin/imports.php
   - ✅ admin/exports.php
   - ✅ admin/activity_history.php (mới)
   - ✅ admin/activity_log.php (mới)
   - ✅ admin/create_history_table.sql (mới)

3. **Kiểm tra:**
   - Vào `activity_log.php` để xem lịch sử
   - Hoặc vào Nhập/Xuất để thực hiện hành động và check xem có được ghi hay không

## Lưu Ý Quan Trọng

- ⚠️ **Tạo bảng trước khi sử dụng:**
  ```bash
  mysql -u root -p quanlykhotrangsuc < admin/create_history_table.sql
  ```

- ✅ Lịch sử được ghi **tự động** khi thao tác thành công
- ✅ Chỉ ghi lịch sử khi hành động **không có lỗi**
- ✅ Hỗ trợ **phân quyền** theo vai trò (Nhân viên/Quản lý)
- ✅ **Chi tiết đầy đủ** được ghi lại (hành động, đối tượng, thông tin chi tiết, thời gian, người thực hiện)

## Các Hành Động Được Ghi

### Imports (Phiếu Nhập)
| Hành động | Đối tượng | Chi tiết ghi |
|-----------|-----------|----------|
| Thêm | PN: [MaPN] | Ngày, danh sách sản phẩm |
| Sửa | PN: [MaPN] | Ngày, tình trạng |
| Xóa | PN: [MaPN] | "Xóa phiếu nhập" |
| Đổi trạng thái | PN: [MaPN] | Trạng thái cũ → mới |

### Exports (Phiếu Xuất)
| Hành động | Đối tượng | Chi tiết ghi |
|-----------|-----------|----------|
| Thêm | PX: [MaPX] | Cửa hàng, số sản phẩm |
| Sửa | PX: [MaPX] | Cửa hàng |
| Xóa | PX: [MaPX] | "Xóa phiếu xuất" |
| Đổi trạng thái | PX: [MaPX] | Trạng thái cũ → mới |

---

**Người triển khai:** GitHub Copilot  
**Ngày:** 17/11/2025  
**Trạng thái:** ✅ Hoàn tất
