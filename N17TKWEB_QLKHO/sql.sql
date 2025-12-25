-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th12 25, 2025 lúc 02:24 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quanlykhotrangsuc`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `baidang`
--

CREATE TABLE `baidang` (
  `MaBD` int(11) NOT NULL,
  `MaTK` char(7) NOT NULL,
  `TenNguoiDang` varchar(100) NOT NULL,
  `NoiDung` text NOT NULL,
  `DanhTinh` enum('Ẩn danh','Hữu danh') DEFAULT 'Hữu danh',
  `PhanLoai` enum('Bảng tin công ty','Diễn đàn nhân viên','Góc hỏi đáp') DEFAULT 'Diễn đàn nhân viên',
  `TrangThai` enum('Hiển thị','Ẩn','Đã xóa') DEFAULT 'Hiển thị',
  `FileDinhKem` text DEFAULT NULL COMMENT 'JSON array của các file đính kèm',
  `ThoiGianDang` datetime DEFAULT current_timestamp(),
  `ThoiGianCapNhat` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `LuotCamXuc` int(11) DEFAULT 0,
  `LuotBinhLuan` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `binhluan`
--

CREATE TABLE `binhluan` (
  `MaBL` int(11) NOT NULL,
  `MaBD` int(11) NOT NULL,
  `MaTK` char(7) NOT NULL,
  `TenNguoiBinhLuan` varchar(100) NOT NULL,
  `NoiDung` text NOT NULL,
  `FileDinhKem` text DEFAULT NULL COMMENT 'JSON array của các file đính kèm',
  `ThoiGianBinhLuan` datetime DEFAULT current_timestamp(),
  `LuotCamXuc` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Bẫy `binhluan`
--
DELIMITER $$
CREATE TRIGGER `trg_giam_luot_binhluan` AFTER DELETE ON `binhluan` FOR EACH ROW BEGIN
  UPDATE `BAIDANG` 
  SET `LuotBinhLuan` = GREATEST(`LuotBinhLuan` - 1, 0)
  WHERE `MaBD` = OLD.MaBD;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_tang_luot_binhluan` AFTER INSERT ON `binhluan` FOR EACH ROW BEGIN
  UPDATE `BAIDANG` 
  SET `LuotBinhLuan` = `LuotBinhLuan` + 1 
  WHERE `MaBD` = NEW.MaBD;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `camxuc`
--

CREATE TABLE `camxuc` (
  `MaCX` int(11) NOT NULL,
  `MaTK` char(7) NOT NULL,
  `LoaiDoiTuong` enum('BaiDang','BinhLuan') NOT NULL,
  `MaDoiTuong` int(11) NOT NULL COMMENT 'MaBD hoặc MaBL',
  `LoaiCamXuc` enum('Like','Love','Haha','Wow','Sad','Angry') DEFAULT 'Like',
  `ThoiGian` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Bẫy `camxuc`
--
DELIMITER $$
CREATE TRIGGER `trg_giam_camxuc_baidang` AFTER DELETE ON `camxuc` FOR EACH ROW BEGIN
  IF OLD.LoaiDoiTuong = 'BaiDang' THEN
    UPDATE `BAIDANG` 
    SET `LuotCamXuc` = GREATEST(`LuotCamXuc` - 1, 0)
    WHERE `MaBD` = OLD.MaDoiTuong;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_giam_camxuc_binhluan` AFTER DELETE ON `camxuc` FOR EACH ROW BEGIN
  IF OLD.LoaiDoiTuong = 'BinhLuan' THEN
    UPDATE `BINHLUAN` 
    SET `LuotCamXuc` = GREATEST(`LuotCamXuc` - 1, 0)
    WHERE `MaBL` = OLD.MaDoiTuong;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_tang_camxuc_baidang` AFTER INSERT ON `camxuc` FOR EACH ROW BEGIN
  IF NEW.LoaiDoiTuong = 'BaiDang' THEN
    UPDATE `BAIDANG` 
    SET `LuotCamXuc` = `LuotCamXuc` + 1 
    WHERE `MaBD` = NEW.MaDoiTuong;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_tang_camxuc_binhluan` AFTER INSERT ON `camxuc` FOR EACH ROW BEGIN
  IF NEW.LoaiDoiTuong = 'BinhLuan' THEN
    UPDATE `BINHLUAN` 
    SET `LuotCamXuc` = `LuotCamXuc` + 1 
    WHERE `MaBL` = NEW.MaDoiTuong;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietphieunhap`
--

CREATE TABLE `chitietphieunhap` (
  `MaCTPN` char(7) NOT NULL,
  `MaPN` char(7) DEFAULT NULL,
  `MaSP` char(7) DEFAULT NULL,
  `SLN` int(11) DEFAULT NULL CHECK (`SLN` > 0),
  `ThanhTien` decimal(15,2) DEFAULT 0.00,
  `SLN_MOI` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitietphieunhap`
--

INSERT INTO `chitietphieunhap` (`MaCTPN`, `MaPN`, `MaSP`, `SLN`, `ThanhTien`, `SLN_MOI`) VALUES
('CTPN001', 'PN00001', 'SP00001', 100, 250000000.00, NULL),
('CTPN002', 'PN00001', 'SP00002', 50, 90000000.00, NULL),
('CTPN003', 'PN00002', 'SP00003', 80, 280000000.00, NULL),
('CTPN004', 'PN00003', 'SP00004', 120, 624000000.00, NULL),
('CTPN005', 'PN00004', 'SP00005', 100, 210000000.00, NULL),
('CTPN006', 'PN00005', 'SP00006', 150, 1020000000.00, NULL),
('CTPN007', 'PN00006', 'SP00007', 60, 270000000.00, NULL),
('CTPN008', 'PN00007', 'SP00008', 90, 207000000.00, NULL),
('CTPN009', 'PN00008', 'SP00009', 70, 119000000.00, NULL),
('CTPN010', 'PN00009', 'SP00010', 50, 255000000.00, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietphieuxuat`
--

CREATE TABLE `chitietphieuxuat` (
  `MaCTPX` char(7) NOT NULL,
  `MaPX` char(7) DEFAULT NULL,
  `MaSP` char(7) DEFAULT NULL,
  `SLX` int(11) DEFAULT NULL CHECK (`SLX` > 0),
  `ThanhTien` decimal(15,2) DEFAULT 0.00,
  `SLX_MOI` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitietphieuxuat`
--

INSERT INTO `chitietphieuxuat` (`MaCTPX`, `MaPX`, `MaSP`, `SLX`, `ThanhTien`, `SLX_MOI`) VALUES
('CTPX001', 'PX00001', 'SP00001', 30, 75000000.00, NULL),
('CTPX002', 'PX00001', 'SP00002', 20, 36000000.00, NULL),
('CTPX003', 'PX00002', 'SP00003', 40, 140000000.00, NULL),
('CTPX004', 'PX00003', 'SP00004', 30, 156000000.00, NULL),
('CTPX005', 'PX00004', 'SP00005', 50, 105000000.00, NULL),
('CTPX006', 'PX00005', 'SP00006', 20, 136000000.00, NULL),
('CTPX007', 'PX00006', 'SP00007', 30, 135000000.00, NULL),
('CTPX008', 'PX00007', 'SP00008', 40, 92000000.00, NULL),
('CTPX009', 'PX00008', 'SP00009', 20, 34000000.00, NULL),
('CTPX010', 'PX00009', 'SP00010', 30, 153000000.00, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `cuahang`
--

CREATE TABLE `cuahang` (
  `MaCH` char(7) NOT NULL,
  `TenCH` varchar(100) NOT NULL,
  `DiaChi` varchar(150) NOT NULL,
  `SoDienThoai` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `cuahang`
--

INSERT INTO `cuahang` (`MaCH`, `TenCH`, `DiaChi`, `SoDienThoai`) VALUES
('CH00001', 'Cửa hàng Trang sức Tink Phố Huế', '123 Phố Huế, Hai Bà Trưng, Hà Nội', '02438761234'),
('CH00002', 'Cửa hàng Trang sức Tink Hàng Bông', '45 Hàng Bông, Hoàn Kiếm, Hà Nội', '02439284567'),
('CH00003', 'Cửa hàng Trang sức Tink Nguyễn Trãi', '88 Nguyễn Trãi, Thanh Xuân, Hà Nội', '02435678900'),
('CH00004', 'Cửa hàng Trang sức Tink Cầu Giấy', '12 Cầu Giấy, Cầu Giấy, Hà Nội', '02432221111'),
('CH00005', 'Cửa hàng Trang sức Tink La Thành', '66 Đê La Thành, Đống Đa, Hà Nội', '02433445566'),
('CH00006', 'Cửa hàng Trang sức Tink Kim Mã', '101 Kim Mã, Ba Đình, Hà Nội', '02439887766'),
('CH00007', 'Cửa hàng Trang sức Tink Tràng Tiền', '25 Tràng Tiền, Hoàn Kiếm, Hà Nội', '02436668888'),
('CH00008', 'Cửa hàng Trang sức Tink Láng Hạ', '88 Láng Hạ, Đống Đa, Hà Nội', '02437779999'),
('CH00009', 'Cửa hàng Trang sức Tink Nguyễn Du', '9 Nguyễn Du, Hai Bà Trưng, Hà Nội', '02439994444'),
('CH00010', 'Cửa hàng Trang sức Tink Tôn Đức Thắng', '15 Tôn Đức Thắng, Đống Đa, Hà Nội', '02434446666'),
('CH00011', 'GS22', 'Bắc Ninh', '0888999888');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lich_su_hoat_dong`
--

CREATE TABLE `lich_su_hoat_dong` (
  `MaLS` int(11) NOT NULL,
  `MaTK` char(7) NOT NULL,
  `TenNhanVien` varchar(100) NOT NULL,
  `LoaiHanhDong` varchar(50) NOT NULL,
  `DoiTuong` varchar(100) NOT NULL,
  `ChiTiet` text DEFAULT NULL,
  `ThoiGian` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `lich_su_hoat_dong`
--

INSERT INTO `lich_su_hoat_dong` (`MaLS`, `MaTK`, `TenNhanVien`, `LoaiHanhDong`, `DoiTuong`, `ChiTiet`, `ThoiGian`) VALUES
(1, 'TK00001', 'Nguyen Van A', 'Đổi trạng thái', 'PX: PX00008', 'Từ: Đã duyệt → Tới: Đang xử lý', '2025-12-25 16:02:16');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieunhap`
--

CREATE TABLE `phieunhap` (
  `MaPN` char(7) NOT NULL,
  `NgayNhap` date NOT NULL,
  `MaTK` char(7) DEFAULT NULL,
  `TinhTrang_PN` enum('Đang xử lý','Đã duyệt','Bị từ chối','Hoàn thành','Có thay đổi') DEFAULT 'Đang xử lý'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `phieunhap`
--

INSERT INTO `phieunhap` (`MaPN`, `NgayNhap`, `MaTK`, `TinhTrang_PN`) VALUES
('PN00001', '2025-10-01', 'TK00002', 'Đang xử lý'),
('PN00002', '2025-10-02', 'TK00003', 'Đã duyệt'),
('PN00003', '2025-10-03', 'TK00004', 'Hoàn thành'),
('PN00004', '2025-10-04', 'TK00005', 'Có thay đổi'),
('PN00005', '2025-10-05', 'TK00006', 'Bị từ chối'),
('PN00006', '2025-10-06', 'TK00007', 'Hoàn thành'),
('PN00007', '2025-10-07', 'TK00008', 'Đang xử lý'),
('PN00008', '2025-10-08', 'TK00009', 'Đã duyệt'),
('PN00009', '2025-10-09', 'TK00010', 'Hoàn thành');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieuxuat`
--

CREATE TABLE `phieuxuat` (
  `MaPX` char(7) NOT NULL,
  `NgayXuat` date NOT NULL,
  `MaCH` char(7) DEFAULT NULL,
  `MaTK` char(7) DEFAULT NULL,
  `TinhTrang_PX` enum('Đang xử lý','Đã duyệt','Bị từ chối','Hoàn thành','Có thay đổi') DEFAULT 'Đang xử lý'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `phieuxuat`
--

INSERT INTO `phieuxuat` (`MaPX`, `NgayXuat`, `MaCH`, `MaTK`, `TinhTrang_PX`) VALUES
('PX00001', '2025-10-05', 'CH00001', 'TK00003', 'Đang xử lý'),
('PX00002', '2025-10-06', 'CH00002', 'TK00004', 'Đã duyệt'),
('PX00003', '2025-10-07', 'CH00003', 'TK00005', 'Hoàn thành'),
('PX00004', '2025-10-08', 'CH00004', 'TK00006', 'Bị từ chối'),
('PX00005', '2025-10-09', 'CH00005', 'TK00007', 'Hoàn thành'),
('PX00006', '2025-10-10', 'CH00006', 'TK00008', 'Có thay đổi'),
('PX00007', '2025-10-11', 'CH00007', 'TK00009', 'Đang xử lý'),
('PX00008', '2025-10-12', 'CH00008', 'TK00010', 'Đang xử lý'),
('PX00009', '2025-10-13', 'CH00009', 'TK00002', 'Hoàn thành');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sanpham`
--

CREATE TABLE `sanpham` (
  `MaSP` char(7) NOT NULL,
  `TenSP` varchar(100) NOT NULL,
  `TheLoai` enum('Vòng tay','Vòng cổ','Khuyên tai','Nhẫn') NOT NULL,
  `MauSP` varchar(50) DEFAULT NULL,
  `TinhTrang` enum('Còn hàng','Hết hàng','Ngừng kinh doanh') DEFAULT 'Còn hàng',
  `SLTK` int(11) DEFAULT NULL CHECK (`SLTK` >= 0),
  `GiaBan` decimal(12,2) DEFAULT NULL CHECK (`GiaBan` > 0),
  `HinhAnh` varchar(255) DEFAULT NULL COMMENT 'Đường dẫn ảnh sản phẩm'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sanpham`
--

INSERT INTO `sanpham` (`MaSP`, `TenSP`, `TheLoai`, `MauSP`, `TinhTrang`, `SLTK`, `GiaBan`, `HinhAnh`) VALUES
('SP00001', 'Bông tai vàng hồng 14K dáng giọt lệ', 'Khuyên tai', 'Bông tai mẫu 1', 'Còn hàng', 200, 2500000.00, 'photos/anhsp1.jpg'),
('SP00002', 'Bông tai bạc Ý đính đá Swarovski', 'Khuyên tai', 'Bông tai mẫu 2', 'Còn hàng', 300, 1800000.00, 'photos/anhsp1.jpg'),
('SP00003', 'Bông tai ngọc trai tự nhiên cao cấp', 'Khuyên tai', 'Bông tai mẫu 3', 'Còn hàng', 150, 3500000.00, 'photos/anhsp1.jpg'),
('SP00004', 'Vòng cổ vàng trắng 18K mặt trái tim', 'Vòng cổ', 'Vòng cổ mẫu 1', 'Còn hàng', 250, 5200000.00, 'photos/anhsp1.jpg'),
('SP00005', 'Vòng cổ bạc Ý mảnh nhẹ đính charm', 'Vòng cổ', 'Vòng cổ mẫu 2', 'Còn hàng', 400, 2100000.00, 'photos/anhsp1.jpg'),
('SP00006', 'Vòng cổ bạch kim cao cấp đá CZ', 'Vòng cổ', 'Vòng cổ mẫu 3', 'Còn hàng', 180, 6800000.00, 'photos/anhsp1.jpg'),
('SP00007', 'Vòng tay vàng 18K kiểu trơn đơn giản', 'Vòng tay', 'Vòng tay mẫu 1', 'Còn hàng', 220, 4500000.00, 'photos/anhsp1.jpg'),
('SP00008', 'Vòng tay bạc đính đá xanh ngọc', 'Vòng tay', 'Vòng tay mẫu 2', 'Còn hàng', 350, 2300000.00, 'photos/anhsp1.jpg'),
('SP00009', 'Vòng tay da phong cách unisex', 'Vòng tay', 'Vòng tay mẫu 3', 'Còn hàng', 280, 1700000.00, 'photos/anhsp1.jpg'),
('SP00010', 'Nhẫn bạch kim đơn giản nam', 'Nhẫn', 'Nhẫn mẫu 1', 'Còn hàng', 200, 5100000.00, 'photos/anhsp1.jpg'),
('SP00011', 'Nhẫn vàng sang trọng', 'Nhẫn', 'Vàng', 'Còn hàng', 5, 500000.00, 'photos/anhsp1.jpg');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sanpham_backup`
--

CREATE TABLE `sanpham_backup` (
  `MaSP` char(7) NOT NULL,
  `TenSP` varchar(100) NOT NULL,
  `TheLoai` enum('Vòng tay','Vòng cổ','Khuyên tai','Nhẫn') NOT NULL,
  `MauSP` varchar(50) DEFAULT NULL,
  `TinhTrang` enum('Còn hàng','Hết hàng','Ngừng kinh doanh') DEFAULT 'Còn hàng',
  `SLTK` int(11) DEFAULT NULL CHECK (`SLTK` >= 0),
  `GiaBan` decimal(12,2) DEFAULT NULL CHECK (`GiaBan` > 0),
  `HinhAnh` varchar(255) DEFAULT NULL COMMENT 'Đường dẫn ảnh sản phẩm'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sanpham_backup`
--

INSERT INTO `sanpham_backup` (`MaSP`, `TenSP`, `TheLoai`, `MauSP`, `TinhTrang`, `SLTK`, `GiaBan`, `HinhAnh`) VALUES
('', 'Nhẫn vàng sang trọng', 'Nhẫn', 'Vàng', 'Còn hàng', 5, 500000.00, 'photos/anhsp7.jpg'),
('SP00001', 'Bông tai vàng hồng 14K dáng giọt lệ', '', 'Bông tai mẫu 1', 'Còn hàng', 200, 2500000.00, NULL),
('SP00002', 'Bông tai bạc Ý đính đá Swarovski', '', 'Bông tai mẫu 2', 'Còn hàng', 300, 1800000.00, NULL),
('SP00003', 'Bông tai ngọc trai tự nhiên cao cấp', '', 'Bông tai mẫu 3', 'Còn hàng', 150, 3500000.00, NULL),
('SP00004', 'Vòng cổ vàng trắng 18K mặt trái tim', 'Vòng cổ', 'Vòng cổ mẫu 1', 'Còn hàng', 250, 5200000.00, NULL),
('SP00005', 'Vòng cổ bạc Ý mảnh nhẹ đính charm', 'Vòng cổ', 'Vòng cổ mẫu 2', 'Còn hàng', 400, 2100000.00, NULL),
('SP00006', 'Vòng cổ bạch kim cao cấp đá CZ', 'Vòng cổ', 'Vòng cổ mẫu 3', 'Còn hàng', 180, 6800000.00, NULL),
('SP00007', 'Vòng tay vàng 18K kiểu trơn đơn giản', 'Vòng tay', 'Vòng tay mẫu 1', 'Còn hàng', 220, 4500000.00, NULL),
('SP00008', 'Vòng tay bạc đính đá xanh ngọc', 'Vòng tay', 'Vòng tay mẫu 2', 'Còn hàng', 350, 2300000.00, NULL),
('SP00009', 'Vòng tay da phong cách unisex', 'Vòng tay', 'Vòng tay mẫu 3', 'Còn hàng', 280, 1700000.00, NULL),
('SP00010', 'Nhẫn bạch kim đơn giản nam', 'Nhẫn', 'Nhẫn mẫu 1', 'Còn hàng', 200, 5100000.00, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `taikhoan`
--

CREATE TABLE `taikhoan` (
  `MaTK` char(7) NOT NULL,
  `TenTK` varchar(50) NOT NULL,
  `MatKhau` varchar(50) NOT NULL,
  `VaiTro` enum('Quản lý','Nhân viên') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `taikhoan`
--

INSERT INTO `taikhoan` (`MaTK`, `TenTK`, `MatKhau`, `VaiTro`) VALUES
('TK00001', 'Nguyen Van A', '123456', 'Quản lý'),
('TK00002', 'Tran Thi B', '123456', 'Nhân viên'),
('TK00003', 'Le Van C', '123456', 'Nhân viên'),
('TK00004', 'Pham Thi D', '123456', 'Nhân viên'),
('TK00005', 'Do Van E', '123456', 'Nhân viên'),
('TK00006', 'Nguyen Thi F', '123456', 'Nhân viên'),
('TK00007', 'Bui Van G', '123456', 'Nhân viên'),
('TK00008', 'Vo Thi H', '123456', 'Nhân viên'),
('TK00009', 'Pham Van I', '123456', 'Nhân viên'),
('TK00010', 'Hoang Thi K', '123456', 'Nhân viên');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `theodoi_baidang`
--

CREATE TABLE `theodoi_baidang` (
  `MaTheoDoi` int(11) NOT NULL,
  `MaTK` char(7) NOT NULL,
  `MaBD` int(11) NOT NULL,
  `ThoiGian` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thongbao`
--

CREATE TABLE `thongbao` (
  `MaTB` int(11) NOT NULL,
  `MaTK` char(7) NOT NULL COMMENT 'Người nhận thông báo',
  `LoaiThongBao` enum('BinhLuanBaiTheoDoi','BinhLuanBaiCuaBan','BaiHot') NOT NULL,
  `MaBD` int(11) NOT NULL,
  `MaBL` int(11) DEFAULT NULL COMMENT 'Nếu là thông báo bình luận',
  `NguoiTacDong` char(7) DEFAULT NULL COMMENT 'Người gây ra thông báo (bình luận, ...)',
  `TenNguoiTacDong` varchar(100) DEFAULT NULL,
  `NoiDungRutGon` varchar(255) DEFAULT NULL,
  `DaDoc` tinyint(1) DEFAULT 0,
  `ThoiGian` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `baidang`
--
ALTER TABLE `baidang`
  ADD PRIMARY KEY (`MaBD`),
  ADD KEY `MaTK` (`MaTK`),
  ADD KEY `idx_phanloai` (`PhanLoai`),
  ADD KEY `idx_trangthai` (`TrangThai`),
  ADD KEY `idx_thoigiandang` (`ThoiGianDang`);

--
-- Chỉ mục cho bảng `binhluan`
--
ALTER TABLE `binhluan`
  ADD PRIMARY KEY (`MaBL`),
  ADD KEY `MaTK` (`MaTK`),
  ADD KEY `idx_mabd` (`MaBD`),
  ADD KEY `idx_thoigian` (`ThoiGianBinhLuan`);

--
-- Chỉ mục cho bảng `camxuc`
--
ALTER TABLE `camxuc`
  ADD PRIMARY KEY (`MaCX`),
  ADD UNIQUE KEY `unique_camxuc` (`MaTK`,`LoaiDoiTuong`,`MaDoiTuong`),
  ADD KEY `idx_doituong` (`LoaiDoiTuong`,`MaDoiTuong`);

--
-- Chỉ mục cho bảng `chitietphieunhap`
--
ALTER TABLE `chitietphieunhap`
  ADD PRIMARY KEY (`MaCTPN`),
  ADD KEY `MaPN` (`MaPN`),
  ADD KEY `MaSP` (`MaSP`);

--
-- Chỉ mục cho bảng `chitietphieuxuat`
--
ALTER TABLE `chitietphieuxuat`
  ADD PRIMARY KEY (`MaCTPX`),
  ADD KEY `MaPX` (`MaPX`),
  ADD KEY `MaSP` (`MaSP`);

--
-- Chỉ mục cho bảng `cuahang`
--
ALTER TABLE `cuahang`
  ADD PRIMARY KEY (`MaCH`);

--
-- Chỉ mục cho bảng `lich_su_hoat_dong`
--
ALTER TABLE `lich_su_hoat_dong`
  ADD PRIMARY KEY (`MaLS`),
  ADD KEY `idx_matk` (`MaTK`),
  ADD KEY `idx_thoigian` (`ThoiGian`),
  ADD KEY `idx_loaihanhdong` (`LoaiHanhDong`);

--
-- Chỉ mục cho bảng `phieunhap`
--
ALTER TABLE `phieunhap`
  ADD PRIMARY KEY (`MaPN`),
  ADD KEY `MaTK` (`MaTK`);

--
-- Chỉ mục cho bảng `phieuxuat`
--
ALTER TABLE `phieuxuat`
  ADD PRIMARY KEY (`MaPX`),
  ADD KEY `MaCH` (`MaCH`),
  ADD KEY `MaTK` (`MaTK`);

--
-- Chỉ mục cho bảng `sanpham`
--
ALTER TABLE `sanpham`
  ADD PRIMARY KEY (`MaSP`);

--
-- Chỉ mục cho bảng `taikhoan`
--
ALTER TABLE `taikhoan`
  ADD PRIMARY KEY (`MaTK`);

--
-- Chỉ mục cho bảng `theodoi_baidang`
--
ALTER TABLE `theodoi_baidang`
  ADD PRIMARY KEY (`MaTheoDoi`),
  ADD UNIQUE KEY `unique_theodoi` (`MaTK`,`MaBD`),
  ADD KEY `MaBD` (`MaBD`);

--
-- Chỉ mục cho bảng `thongbao`
--
ALTER TABLE `thongbao`
  ADD PRIMARY KEY (`MaTB`),
  ADD KEY `MaBD` (`MaBD`),
  ADD KEY `MaBL` (`MaBL`),
  ADD KEY `idx_matk_dadoc` (`MaTK`,`DaDoc`),
  ADD KEY `idx_thoigian` (`ThoiGian`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `baidang`
--
ALTER TABLE `baidang`
  MODIFY `MaBD` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `binhluan`
--
ALTER TABLE `binhluan`
  MODIFY `MaBL` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `camxuc`
--
ALTER TABLE `camxuc`
  MODIFY `MaCX` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `lich_su_hoat_dong`
--
ALTER TABLE `lich_su_hoat_dong`
  MODIFY `MaLS` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `theodoi_baidang`
--
ALTER TABLE `theodoi_baidang`
  MODIFY `MaTheoDoi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `thongbao`
--
ALTER TABLE `thongbao`
  MODIFY `MaTB` int(11) NOT NULL AUTO_INCREMENT;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `baidang`
--
ALTER TABLE `baidang`
  ADD CONSTRAINT `baidang_ibfk_1` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `binhluan`
--
ALTER TABLE `binhluan`
  ADD CONSTRAINT `binhluan_ibfk_1` FOREIGN KEY (`MaBD`) REFERENCES `baidang` (`MaBD`) ON DELETE CASCADE,
  ADD CONSTRAINT `binhluan_ibfk_2` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `camxuc`
--
ALTER TABLE `camxuc`
  ADD CONSTRAINT `camxuc_ibfk_1` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `chitietphieunhap`
--
ALTER TABLE `chitietphieunhap`
  ADD CONSTRAINT `chitietphieunhap_ibfk_1` FOREIGN KEY (`MaPN`) REFERENCES `phieunhap` (`MaPN`),
  ADD CONSTRAINT `chitietphieunhap_ibfk_2` FOREIGN KEY (`MaSP`) REFERENCES `sanpham` (`MaSP`);

--
-- Các ràng buộc cho bảng `chitietphieuxuat`
--
ALTER TABLE `chitietphieuxuat`
  ADD CONSTRAINT `chitietphieuxuat_ibfk_1` FOREIGN KEY (`MaPX`) REFERENCES `phieuxuat` (`MaPX`),
  ADD CONSTRAINT `chitietphieuxuat_ibfk_2` FOREIGN KEY (`MaSP`) REFERENCES `sanpham` (`MaSP`);

--
-- Các ràng buộc cho bảng `lich_su_hoat_dong`
--
ALTER TABLE `lich_su_hoat_dong`
  ADD CONSTRAINT `fk_ls_tk` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `phieunhap`
--
ALTER TABLE `phieunhap`
  ADD CONSTRAINT `phieunhap_ibfk_1` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`);

--
-- Các ràng buộc cho bảng `phieuxuat`
--
ALTER TABLE `phieuxuat`
  ADD CONSTRAINT `phieuxuat_ibfk_1` FOREIGN KEY (`MaCH`) REFERENCES `cuahang` (`MaCH`),
  ADD CONSTRAINT `phieuxuat_ibfk_2` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`);

--
-- Các ràng buộc cho bảng `theodoi_baidang`
--
ALTER TABLE `theodoi_baidang`
  ADD CONSTRAINT `theodoi_baidang_ibfk_1` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`) ON DELETE CASCADE,
  ADD CONSTRAINT `theodoi_baidang_ibfk_2` FOREIGN KEY (`MaBD`) REFERENCES `baidang` (`MaBD`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `thongbao`
--
ALTER TABLE `thongbao`
  ADD CONSTRAINT `thongbao_ibfk_1` FOREIGN KEY (`MaTK`) REFERENCES `taikhoan` (`MaTK`) ON DELETE CASCADE,
  ADD CONSTRAINT `thongbao_ibfk_2` FOREIGN KEY (`MaBD`) REFERENCES `baidang` (`MaBD`) ON DELETE CASCADE,
  ADD CONSTRAINT `thongbao_ibfk_3` FOREIGN KEY (`MaBL`) REFERENCES `binhluan` (`MaBL`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
