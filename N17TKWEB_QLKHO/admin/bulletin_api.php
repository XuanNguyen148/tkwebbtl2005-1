<?php
// admin/bulletin_api.php - API xử lý các request cho Bảng tin
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userRole = $_SESSION['role'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function isManagerRole($role) {
    $role = trim($role ?? '');
    if ($role === '') {
        return false;
    }
    $lower = mb_strtolower($role, 'UTF-8');
    $lower = preg_replace('/\s+/u', ' ', $lower);
    if ($lower === 'quản lý') {
        return true;
    }
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
    if ($ascii !== false) {
        $ascii = preg_replace('/\s+/', ' ', trim(strtolower($ascii)));
        if ($ascii === 'quan ly') {
            return true;
        }
    }
    return false;
}

$isManager = isManagerRole($userRole);

try {
    switch ($action) {
        // ============ ĐĂNG BÀI ============
        case 'create_post':
            $noiDung = trim($_POST['noiDung'] ?? '');
            $danhTinh = $_POST['danhTinh'] ?? 'Hữu danh';
            $phanLoai = $_POST['phanLoai'] ?? 'Diễn đàn nhân viên';
            $trangThai = $_POST['trangThai'] ?? 'Hiển thị';
            
            // Kiểm tra quyền đăng bài "Bảng tin công ty"
            if ($phanLoai === 'Bảng tin công ty' && $userRole !== 'Quản lý') {
                echo json_encode(['success' => false, 'message' => 'Chỉ Quản lý mới được đăng bài vào Bảng tin công ty']);
                exit();
            }
            
            if (empty($noiDung)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập nội dung bài đăng']);
                exit();
            }
            
            // Xử lý file đính kèm
            $fileDinhKem = [];
            if (!empty($_FILES['files']['name'][0])) {
                $uploadDir = '../uploads/bulletin/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['files']['name'] as $key => $name) {
                    if ($_FILES['files']['error'][$key] === 0) {
                        $fileName = time() . '_' . uniqid() . '_' . basename($name);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $targetPath)) {
                            $fileDinhKem[] = [
                                'name' => $name,
                                'path' => 'uploads/bulletin/' . $fileName,
                                'type' => $_FILES['files']['type'][$key]
                            ];
                        }
                    }
                }
            }
            
            $tenNguoiDang = ($danhTinh === 'Ẩn danh') ? 'Ẩn danh' : $userName;
            
            $stmt = $pdo->prepare("
                INSERT INTO BAIDANG (MaTK, TenNguoiDang, NoiDung, DanhTinh, PhanLoai, TrangThai, FileDinhKem)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $tenNguoiDang,
                $noiDung,
                $danhTinh,
                $phanLoai,
                $trangThai,
                json_encode($fileDinhKem)
            ]);
            
            $maBD = $pdo->lastInsertId();
            
            // Nếu là bài hot (>3 tương tác) - tạo thông báo cho tất cả người dùng
            // (logic này sẽ được xử lý sau khi có tương tác)

            if ($phanLoai === 'Bảng tin công ty' && $trangThai === 'Hiển thị') {
                createCompanyPostNotifications($pdo, $maBD, $userId, $userName, $noiDung);
            }
            
            echo json_encode(['success' => true, 'message' => 'Đăng bài thành công', 'maBD' => $maBD]);
            break;
            
        // ============ LẤY DANH SÁCH BÀI ĐĂNG ============
        case 'get_posts':
            $filter = $_GET['filter'] ?? 'all';
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;
            
            $visibilityParams = [];
            if ($userRole === 'Quản lý') {
                $visibilityCondition = "bd.TrangThai IN ('Hiển thị', 'Ẩn')";
            } else {
                $visibilityCondition = "(bd.TrangThai = 'Hiển thị' OR (bd.TrangThai = 'Ẩn' AND bd.MaTK = ?))";
                $visibilityParams[] = $userId;
            }

            $where = "WHERE $visibilityCondition";
            $filterParams = [];
            
            switch ($filter) {
                case 'hot':
                    $where .= " AND (bd.LuotCamXuc >= 3 OR bd.LuotBinhLuan >= 3)";
                    break;
                case 'company':
                    $where .= " AND bd.PhanLoai = 'Bảng tin công ty'";
                    break;
                case 'forum':
                    $where .= " AND bd.PhanLoai = 'Diễn đàn nhân viên'";
                    break;
                case 'qa':
                    $where .= " AND bd.PhanLoai = 'Góc hỏi đáp'";
                    break;
                case 'mine':
                    $where .= " AND bd.MaTK = ?";
                    $filterParams[] = $userId;
                    break;
            }
            
            // Lấy danh sách bài đăng
            $stmt = $pdo->prepare("
                SELECT 
                    bd.*,
                    tk.VaiTro,
                    tk.TenTK as TenTaiKhoan,
                    (SELECT COUNT(*) FROM CAMXUC WHERE LoaiDoiTuong='BaiDang' AND MaDoiTuong=bd.MaBD AND MaTK=?) as DaThichBD,
                    (SELECT COUNT(*) FROM THEODOI_BAIDANG WHERE MaBD=bd.MaBD AND MaTK=?) as DangTheoDoi
                FROM BAIDANG bd
                JOIN TAIKHOAN tk ON bd.MaTK = tk.MaTK
                $where
                ORDER BY bd.ThoiGianDang DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute(array_merge([$userId, $userId], $visibilityParams, $filterParams));
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Lấy tổng số bài đăng
            $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM BAIDANG bd $where");
            $stmtCount->execute(array_merge($visibilityParams, $filterParams));
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Xử lý dữ liệu
            foreach ($posts as &$post) {
                $post['FileDinhKem'] = json_decode($post['FileDinhKem'], true) ?: [];
                $post['DaThichBD'] = (bool)$post['DaThichBD'];
                $post['DangTheoDoi'] = (bool)$post['DangTheoDoi'];
                
                // Kiểm tra quyền sửa/xóa
                $post['CoTheChinhSua'] = ($post['MaTK'] === $userId);
                $post['CoTheQuanLy'] = ($post['MaTK'] === $userId || $userRole === 'Quản lý');
            }
            
            echo json_encode([
                'success' => true,
                'posts' => $posts,
                'total' => $total,
                'page' => $page,
                'totalPages' => ceil($total / $perPage)
            ]);
            break;
            
        // ============ LẤY CHI TIẾT BÀI ĐĂNG ============
        case 'get_post_detail':
            $maBD = intval($_GET['maBD'] ?? 0);
            
            if ($userRole === 'Quản lý') {
                $visibilityCondition = "bd.TrangThai IN ('Hiển thị', 'Ẩn')";
                $detailParams = [$userId, $userId, $maBD];
            } else {
                $visibilityCondition = "(bd.TrangThai = 'Hiển thị' OR (bd.TrangThai = 'Ẩn' AND bd.MaTK = ?))";
                $detailParams = [$userId, $userId, $maBD, $userId];
            }

            $stmt = $pdo->prepare("
                SELECT 
                    bd.*,
                    tk.VaiTro,
                    tk.TenTK as TenTaiKhoan,
                    (SELECT COUNT(*) FROM CAMXUC WHERE LoaiDoiTuong='BaiDang' AND MaDoiTuong=bd.MaBD AND MaTK=?) as DaThichBD,
                    (SELECT COUNT(*) FROM THEODOI_BAIDANG WHERE MaBD=bd.MaBD AND MaTK=?) as DangTheoDoi
                FROM BAIDANG bd
                JOIN TAIKHOAN tk ON bd.MaTK = tk.MaTK
                WHERE bd.MaBD = ? AND $visibilityCondition
            ");
            $stmt->execute($detailParams);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$post) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài đăng']);
                exit();
            }
            
            $post['FileDinhKem'] = json_decode($post['FileDinhKem'], true) ?: [];
            $post['DaThichBD'] = (bool)$post['DaThichBD'];
            $post['DangTheoDoi'] = (bool)$post['DangTheoDoi'];
            $post['CoTheChinhSua'] = ($post['MaTK'] === $userId);
            $post['CoTheQuanLy'] = ($post['MaTK'] === $userId || $userRole === 'Quản lý');
            
            // Lấy danh sách bình luận
            $stmtComments = $pdo->prepare("
                SELECT 
                    bl.*,
                    tk.VaiTro,
                    tk.TenTK as TenTaiKhoan,
                    (SELECT COUNT(*) FROM CAMXUC WHERE LoaiDoiTuong='BinhLuan' AND MaDoiTuong=bl.MaBL AND MaTK=?) as DaThichBL
                FROM BINHLUAN bl
                JOIN TAIKHOAN tk ON bl.MaTK = tk.MaTK
                WHERE bl.MaBD = ?
                ORDER BY bl.ThoiGianBinhLuan ASC
            ");
            $stmtComments->execute([$userId, $maBD]);
            $comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($comments as &$comment) {
                $comment['FileDinhKem'] = json_decode($comment['FileDinhKem'], true) ?: [];
                $comment['DaThichBL'] = (bool)$comment['DaThichBL'];
                $comment['CoTheChinhSua'] = ($comment['MaTK'] === $userId || $userRole === 'Quản lý');
            }
            
            echo json_encode([
                'success' => true,
                'post' => $post,
                'comments' => $comments
            ]);
            break;
            
        // ============ BÌNH LUẬN ============
        case 'create_comment':
            $maBD = intval($_POST['maBD'] ?? 0);
            $noiDung = trim($_POST['noiDung'] ?? '');
            $danhTinh = $_POST['danhTinh'] ?? 'Hữu danh';
            
            if (empty($noiDung)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập nội dung bình luận']);
                exit();
            }

            $stmtPostInfo = $pdo->prepare("
                SELECT MaTK, DanhTinh FROM BAIDANG WHERE MaBD=?
            ");
            $stmtPostInfo->execute([$maBD]);
            $postInfo = $stmtPostInfo->fetch(PDO::FETCH_ASSOC);
            if (!$postInfo) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài đăng']);
                exit();
            }

            // Xử lý file đính kèm
            $fileDinhKem = [];
            if (!empty($_FILES['files']['name'][0])) {
                $uploadDir = '../uploads/bulletin/comments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['files']['name'] as $key => $name) {
                    if ($_FILES['files']['error'][$key] === 0) {
                        $fileName = time() . '_' . uniqid() . '_' . basename($name);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $targetPath)) {
                            $fileDinhKem[] = [
                                'name' => $name,
                                'path' => 'uploads/bulletin/comments/' . $fileName,
                                'type' => $_FILES['files']['type'][$key]
                            ];
                        }
                    }
                }
            }
            
            if ($postInfo['MaTK'] === $userId && $postInfo['DanhTinh'] === 'Ẩn danh') {
                $danhTinh = 'Ẩn danh';
            }

            if ($danhTinh === 'Ẩn danh') {
                if ($postInfo['MaTK'] === $userId && $postInfo['DanhTinh'] === 'Ẩn danh') {
                    $tenNguoiBinhLuan = 'Ẩn danh';
                } else {
                    $tenNguoiBinhLuan = generateAnonymousLabel($pdo, $maBD, $userId);
                }
            } else {
                $tenNguoiBinhLuan = $userName;
            }
            $stmt = $pdo->prepare("
                INSERT INTO BINHLUAN (MaBD, MaTK, TenNguoiBinhLuan, NoiDung, FileDinhKem)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $maBD,
                $userId,
                $tenNguoiBinhLuan,
                $noiDung,
                json_encode($fileDinhKem)
            ]);
            
            $maBL = $pdo->lastInsertId();
            
            // Tạo thông báo
            createCommentNotifications($pdo, $maBD, $maBL, $userId, $tenNguoiBinhLuan, $noiDung);
            
            // Tự động theo dõi bài đăng khi bình luận
            $stmtTheoDoi = $pdo->prepare("
                INSERT IGNORE INTO THEODOI_BAIDANG (MaTK, MaBD) VALUES (?, ?)
            ");
            $stmtTheoDoi->execute([$userId, $maBD]);
            
            echo json_encode(['success' => true, 'message' => 'Bình luận thành công', 'maBL' => $maBL]);
            break;
            
        // ============ THÍCH BÀI ĐĂNG/BÌNH LUẬN ============
        case 'toggle_reaction':
            $loaiDoiTuong = $_POST['loaiDoiTuong'] ?? 'BaiDang';
            $maDoiTuong = intval($_POST['maDoiTuong'] ?? 0);
            $loaiCamXuc = $_POST['loaiCamXuc'] ?? 'Like';
            
            // Kiểm tra đã thích chưa
            $stmtCheck = $pdo->prepare("
                SELECT MaCX FROM CAMXUC 
                WHERE MaTK=? AND LoaiDoiTuong=? AND MaDoiTuong=?
            ");
            $stmtCheck->execute([$userId, $loaiDoiTuong, $maDoiTuong]);
            $existing = $stmtCheck->fetch();
            
            if ($existing) {
                // Đã thích -> xóa
                $stmtDelete = $pdo->prepare("
                    DELETE FROM CAMXUC WHERE MaCX=?
                ");
                $stmtDelete->execute([$existing['MaCX']]);
                $action = 'removed';
            } else {
                // Chưa thích -> thêm
                $stmtInsert = $pdo->prepare("
                    INSERT INTO CAMXUC (MaTK, LoaiDoiTuong, MaDoiTuong, LoaiCamXuc)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtInsert->execute([$userId, $loaiDoiTuong, $maDoiTuong, $loaiCamXuc]);
                $action = 'added';
            }
            
            // Lấy số lượt thích mới
            $table = ($loaiDoiTuong === 'BaiDang') ? 'BAIDANG' : 'BINHLUAN';
            $idColumn = ($loaiDoiTuong === 'BaiDang') ? 'MaBD' : 'MaBL';
            
            $stmtCount = $pdo->prepare("
                SELECT LuotCamXuc FROM $table WHERE $idColumn=?
            ");
            $stmtCount->execute([$maDoiTuong]);
            $count = $stmtCount->fetch(PDO::FETCH_ASSOC)['LuotCamXuc'];
            
            echo json_encode([
                'success' => true,
                'action' => $action,
                'count' => $count
            ]);
            break;
            
        // ============ THEO DÕI BÀI ĐĂNG ============
        case 'toggle_follow':
            $maBD = intval($_POST['maBD'] ?? 0);
            
            // Kiểm tra đã theo dõi chưa
            $stmtCheck = $pdo->prepare("
                SELECT MaTheoDoi FROM THEODOI_BAIDANG WHERE MaTK=? AND MaBD=?
            ");
            $stmtCheck->execute([$userId, $maBD]);
            $existing = $stmtCheck->fetch();
            
            if ($existing) {
                // Đã theo dõi -> bỏ theo dõi
                $stmtDelete = $pdo->prepare("
                    DELETE FROM THEODOI_BAIDANG WHERE MaTheoDoi=?
                ");
                $stmtDelete->execute([$existing['MaTheoDoi']]);
                $isFollowing = false;
            } else {
                // Chưa theo dõi -> theo dõi
                $stmtInsert = $pdo->prepare("
                    INSERT INTO THEODOI_BAIDANG (MaTK, MaBD) VALUES (?, ?)
                ");
                $stmtInsert->execute([$userId, $maBD]);
                $isFollowing = true;
            }
            
            echo json_encode([
                'success' => true,
                'isFollowing' => $isFollowing
            ]);
            break;
            
        // ============ ẨN/HIỆN BÀI ĐĂNG ============
        case 'toggle_post_visibility':
            $maBD = intval($_POST['maBD'] ?? 0);
            
            // Kiểm tra quyền
            $stmtCheck = $pdo->prepare("SELECT MaTK, TrangThai FROM BAIDANG WHERE MaBD=?");
            $stmtCheck->execute([$maBD]);
            $post = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$post || ($post['MaTK'] !== $userId && $userRole !== 'Quản lý')) {
                echo json_encode(['success' => false, 'message' => 'Không có quyền']);
                exit();
            }
            
            $trangThaiMoi = ($post['TrangThai'] === 'Hiển thị') ? 'Ẩn' : 'Hiển thị';
            
            $stmtUpdate = $pdo->prepare("UPDATE BAIDANG SET TrangThai=? WHERE MaBD=?");
            $stmtUpdate->execute([$trangThaiMoi, $maBD]);
            
            echo json_encode([
                'success' => true,
                'trangThai' => $trangThaiMoi
            ]);
            break;

        // ============ CHỈNH SỬA BÀI ĐĂNG ============
        case 'update_post':
            $maBD = intval($_POST['maBD'] ?? 0);
            $noiDung = trim($_POST['noiDung'] ?? '');
            $danhTinh = $_POST['danhTinh'] ?? 'Hữu danh';
            $phanLoai = $_POST['phanLoai'] ?? 'Diễn đàn nhân viên';
            $trangThai = $_POST['trangThai'] ?? 'Hiển thị';

            if (empty($noiDung)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập nội dung bài đăng']);
                exit();
            }

            $stmtCheck = $pdo->prepare("SELECT MaTK FROM BAIDANG WHERE MaBD=?");
            $stmtCheck->execute([$maBD]);
            $post = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['MaTK'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'Không có quyền']);
                exit();
            }

            if ($phanLoai === 'Bảng tin công ty' && $userRole !== 'Quản lý') {
                echo json_encode(['success' => false, 'message' => 'Chỉ Quản lý mới được đăng bài vào Bảng tin công ty']);
                exit();
            }

            $tenNguoiDang = ($danhTinh === 'Ẩn danh') ? 'Ẩn danh' : $userName;
            $stmtUpdate = $pdo->prepare("
                UPDATE BAIDANG
                SET NoiDung=?, DanhTinh=?, PhanLoai=?, TrangThai=?, TenNguoiDang=?
                WHERE MaBD=?
            ");
            $stmtUpdate->execute([
                $noiDung,
                $danhTinh,
                $phanLoai,
                $trangThai,
                $tenNguoiDang,
                $maBD
            ]);

            echo json_encode(['success' => true, 'message' => 'Đã cập nhật bài đăng']);
            break;
            
        // ============ XÓA BÀI ĐĂNG ============
        case 'delete_post':
            $maBD = intval($_POST['maBD'] ?? 0);
            
            // Kiểm tra quyền
            $stmtCheck = $pdo->prepare("SELECT MaTK FROM BAIDANG WHERE MaBD=?");
            $stmtCheck->execute([$maBD]);
            $post = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$post || ($post['MaTK'] !== $userId && $userRole !== 'Quản lý')) {
                echo json_encode(['success' => false, 'message' => 'Không có quyền']);
                exit();
            }
            
            $stmtDelete = $pdo->prepare("UPDATE BAIDANG SET TrangThai='Đã xóa' WHERE MaBD=?");
            $stmtDelete->execute([$maBD]);
            
            echo json_encode(['success' => true, 'message' => 'Đã xóa bài đăng']);
            break;

        // ============ XOA TOAN BO BAI VIET THEO TAI KHOAN ============
        case 'delete_user_posts':
            if (!$isManager) {
                echo json_encode(['success' => false, 'message' => 'Khong co quyen']);
                exit();
            }

            $maTK = trim($_POST['maTK'] ?? '');
            if ($maTK === '') {
                echo json_encode(['success' => false, 'message' => 'Thieu ma tai khoan']);
                exit();
            }

            $stmtCheck = $pdo->prepare("SELECT MaTK FROM TAIKHOAN WHERE MaTK=?");
            $stmtCheck->execute([$maTK]);
            $userRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) {
                echo json_encode(['success' => false, 'message' => 'Khong tim thay tai khoan']);
                exit();
            }

            $stmtDelete = $pdo->prepare("UPDATE BAIDANG SET TrangThai='Đã xóa' WHERE MaTK=?");
            $stmtDelete->execute([$maTK]);

            echo json_encode([
                'success' => true,
                'count' => $stmtDelete->rowCount()
            ]);
            break;

        // ============ XOA TAI KHOAN ============
        case 'delete_user_account':
            if (!$isManager) {
                echo json_encode(['success' => false, 'message' => 'Khong co quyen']);
                exit();
            }

            $maTK = trim($_POST['maTK'] ?? '');
            if ($maTK === '') {
                echo json_encode(['success' => false, 'message' => 'Thieu ma tai khoan']);
                exit();
            }

            if ($maTK === $userId) {
                echo json_encode(['success' => false, 'message' => 'Khong the xoa tai khoan dang dang nhap']);
                exit();
            }

            $stmtCheck = $pdo->prepare("SELECT MaTK FROM TAIKHOAN WHERE MaTK=?");
            $stmtCheck->execute([$maTK]);
            $userRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) {
                echo json_encode(['success' => false, 'message' => 'Khong tim thay tai khoan']);
                exit();
            }

            $stmtDelete = $pdo->prepare("DELETE FROM TAIKHOAN WHERE MaTK=?");
            $stmtDelete->execute([$maTK]);

            echo json_encode(['success' => true, 'message' => 'Da xoa tai khoan']);
            break;

            
        // ============ XÓA BÌNH LUẬN ============
        case 'delete_comment':
            $maBL = intval($_POST['maBL'] ?? 0);
            
            // Kiểm tra quyền
            $stmtCheck = $pdo->prepare("SELECT MaTK FROM BINHLUAN WHERE MaBL=?");
            $stmtCheck->execute([$maBL]);
            $comment = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$comment || ($comment['MaTK'] !== $userId && $userRole !== 'Quản lý')) {
                echo json_encode(['success' => false, 'message' => 'Không có quyền']);
                exit();
            }
            
            $stmtDelete = $pdo->prepare("DELETE FROM BINHLUAN WHERE MaBL=?");
            $stmtDelete->execute([$maBL]);
            
            echo json_encode(['success' => true, 'message' => 'Đã xóa bình luận']);
            break;
            
        // ============ LẤY THÔNG BÁO ============
        case 'get_notifications':
            $limit = intval($_GET['limit'] ?? 7);
            
            $stmt = $pdo->prepare("
                SELECT 
                    tb.*,
                    bd.NoiDung as NoiDungBaiDang,
                    bd.TenNguoiDang
                FROM THONGBAO tb
                LEFT JOIN BAIDANG bd ON tb.MaBD = bd.MaBD
                WHERE tb.MaTK = ? AND tb.LoaiThongBao != 'BaoCaoBaiDang'
                ORDER BY tb.ThoiGian DESC
                LIMIT $limit
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Đếm số thông báo chưa đọc
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) as unread FROM THONGBAO WHERE MaTK=? AND DaDoc=0 AND LoaiThongBao != 'BaoCaoBaiDang'
            ");
            $stmtCount->execute([$userId]);
            $unreadCount = $stmtCount->fetch(PDO::FETCH_ASSOC)['unread'];
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unreadCount' => $unreadCount
            ]);
            break;

        case 'get_activity_updates':
            $stmtUnread = $pdo->prepare("
                SELECT COUNT(*) as unread FROM THONGBAO WHERE MaTK=? AND DaDoc=0 AND LoaiThongBao != 'BaoCaoBaiDang'
            ");
            $stmtUnread->execute([$userId]);
            $generalUnread = $stmtUnread->fetch(PDO::FETCH_ASSOC)['unread'];

            $latestNotification = null;
            $latestNotificationId = null;
            $stmtLatest = $pdo->prepare("
                SELECT * FROM THONGBAO
                WHERE MaTK=? AND LoaiThongBao != 'BaoCaoBaiDang'
                ORDER BY ThoiGian DESC, MaTB DESC
                LIMIT 1
            ");
            $stmtLatest->execute([$userId]);
            $latestNotification = $stmtLatest->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($latestNotification) {
                $latestNotificationId = $latestNotification['MaTB'];
            }

            $reportUnread = 0;
            $latestReportNotification = null;
            $latestReportNotificationId = null;
            if ($userRole === 'Quản lý') {
                $stmtReportUnread = $pdo->prepare("
                    SELECT COUNT(*) as unread FROM THONGBAO WHERE MaTK=? AND DaDoc=0 AND LoaiThongBao = 'BaoCaoBaiDang'
                ");
                $stmtReportUnread->execute([$userId]);
                $reportUnread = $stmtReportUnread->fetch(PDO::FETCH_ASSOC)['unread'];

                $stmtLatestReport = $pdo->prepare("
                    SELECT * FROM THONGBAO
                    WHERE MaTK=? AND LoaiThongBao = 'BaoCaoBaiDang'
                    ORDER BY ThoiGian DESC, MaTB DESC
                    LIMIT 1
                ");
                $stmtLatestReport->execute([$userId]);
                $latestReportNotification = $stmtLatestReport->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($latestReportNotification) {
                    $latestReportNotificationId = $latestReportNotification['MaTB'];
                }
            }

            $latestPost = null;
            $latestPostId = null;
            if ($userRole === 'Quản lý') {
                $stmtLatestPost = $pdo->prepare("
                    SELECT MaBD, TenNguoiDang, NoiDung
                    FROM BAIDANG
                    WHERE TrangThai IN ('Hiển thị', 'Ẩn') AND MaTK != ?
                    ORDER BY ThoiGianDang DESC, MaBD DESC
                    LIMIT 1
                ");
                $stmtLatestPost->execute([$userId]);
                $latestPost = $stmtLatestPost->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($latestPost) {
                    $latestPostId = $latestPost['MaBD'];
                    $noiDungRutGon = mb_substr($latestPost['NoiDung'], 0, 60);
                    if (mb_strlen($latestPost['NoiDung']) > 60) {
                        $noiDungRutGon .= '...';
                    }
                    $latestPost['NoiDungRutGon'] = $noiDungRutGon;
                }
            }

            echo json_encode([
                'success' => true,
                'unreadCount' => intval($generalUnread) + intval($reportUnread),
                'latestNotificationId' => $latestNotificationId,
                'latestNotification' => $latestNotification,
                'latestReportNotificationId' => $latestReportNotificationId,
                'latestReportNotification' => $latestReportNotification,
                'latestPostId' => $latestPostId,
                'latestPost' => $latestPost
            ]);
            break;

        case 'get_report_notifications':
            if ($userRole !== 'Quản lý') {
                echo json_encode(['success' => false, 'message' => 'Không có quyền']);
                exit();
            }

            $limit = intval($_GET['limit'] ?? 7);
            $stmt = $pdo->prepare("
                SELECT 
                    tb.*,
                    bd.NoiDung as NoiDungBaiDang,
                    bd.TenNguoiDang
                FROM THONGBAO tb
                LEFT JOIN BAIDANG bd ON tb.MaBD = bd.MaBD
                WHERE tb.MaTK = ? AND tb.LoaiThongBao = 'BaoCaoBaiDang'
                ORDER BY tb.ThoiGian DESC
                LIMIT $limit
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) as unread FROM THONGBAO WHERE MaTK=? AND DaDoc=0 AND LoaiThongBao = 'BaoCaoBaiDang'
            ");
            $stmtCount->execute([$userId]);
            $unreadCount = $stmtCount->fetch(PDO::FETCH_ASSOC)['unread'];

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unreadCount' => $unreadCount
            ]);
            break;

        case 'create_report':
            $maBD = intval($_POST['maBD'] ?? 0);
            $lyDo = trim($_POST['lyDo'] ?? '');

            if ($maBD <= 0 || empty($lyDo)) {
                echo json_encode(['success' => false, 'message' => 'Thiếu thông tin báo cáo']);
                exit();
            }

            $stmtCheck = $pdo->prepare("SELECT MaBD, TrangThai FROM BAIDANG WHERE MaBD=?");
            $stmtCheck->execute([$maBD]);
            $post = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['TrangThai'] === 'Đã xóa') {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài đăng']);
                exit();
            }

            $stmtManagers = $pdo->prepare("SELECT MaTK FROM TAIKHOAN WHERE VaiTro='Quản lý'");
            $stmtManagers->execute();
            $managers = $stmtManagers->fetchAll(PDO::FETCH_ASSOC);

            $noiDungRutGon = mb_substr($lyDo, 0, 120);
            if (mb_strlen($lyDo) > 120) {
                $noiDungRutGon .= '...';
            }

            $pdo->beginTransaction();
            try {
                $stmtReport = $pdo->prepare("
                    INSERT INTO BAOCAO_BAIDANG (MaBD, MaTK, LyDo)
                    VALUES (?, ?, ?)
                ");
                $stmtReport->execute([$maBD, $userId, $lyDo]);
                $maBC = $pdo->lastInsertId();

                $stmtInsert = $pdo->prepare("
                    INSERT INTO THONGBAO (MaTK, LoaiThongBao, MaBD, MaBC, NguoiTacDong, TenNguoiTacDong, NoiDungRutGon)
                    VALUES (?, 'BaoCaoBaiDang', ?, ?, ?, ?, ?)
                ");

                foreach ($managers as $manager) {
                    $stmtInsert->execute([
                        $manager['MaTK'],
                        $maBD,
                        $maBC,
                        $userId,
                        $userName,
                        $noiDungRutGon
                    ]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            echo json_encode(['success' => true, 'message' => 'Đã gửi báo cáo']);
            break;

        case 'delete_report_notification':
            if ($userRole !== 'Quản lý') {
                echo json_encode(['success' => false, 'message' => 'Không có quyền']);
                exit();
            }

            $maTB = intval($_POST['maTB'] ?? 0);
            if ($maTB <= 0) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy báo cáo']);
                exit();
            }

            $stmtFind = $pdo->prepare("
                SELECT MaBC FROM THONGBAO WHERE MaTB=? AND LoaiThongBao='BaoCaoBaiDang'
            ");
            $stmtFind->execute([$maTB]);
            $reportRow = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if (!$reportRow) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy báo cáo']);
                exit();
            }

            $maBC = $reportRow['MaBC'];
            $pdo->beginTransaction();
            try {
                if (!empty($maBC)) {
                    $stmtDeleteNotifs = $pdo->prepare("
                        DELETE FROM THONGBAO WHERE MaBC=? AND LoaiThongBao='BaoCaoBaiDang'
                    ");
                    $stmtDeleteNotifs->execute([$maBC]);

                    $stmtDeleteReport = $pdo->prepare("
                        DELETE FROM BAOCAO_BAIDANG WHERE MaBC=?
                    ");
                    $stmtDeleteReport->execute([$maBC]);
                } else {
                    $stmtDeleteNotifs = $pdo->prepare("
                        DELETE FROM THONGBAO WHERE MaTB=? AND LoaiThongBao='BaoCaoBaiDang'
                    ");
                    $stmtDeleteNotifs->execute([$maTB]);
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            echo json_encode(['success' => true, 'message' => 'Đã xóa báo cáo']);
            break;
            
        // ============ ĐÁNH DẤU ĐÃ ĐỌC THÔNG BÁO ============
        case 'mark_notification_read':
            $maTB = intval($_POST['maTB'] ?? 0);
            
            if ($maTB > 0) {
                $stmt = $pdo->prepare("UPDATE THONGBAO SET DaDoc=1 WHERE MaTB=? AND MaTK=?");
                $stmt->execute([$maTB, $userId]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ĐÁNH DẤU TẤT CẢ ĐÃ ĐỌC ============
        case 'mark_all_read':
            if ($userRole === 'Quản lý') {
                $stmt = $pdo->prepare("UPDATE THONGBAO SET DaDoc=1 WHERE MaTK=?");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE THONGBAO SET DaDoc=1
                    WHERE MaTK=? AND LoaiThongBao!='BaoCaoBaiDang'
                ");
                $stmt->execute([$userId]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Đã đánh dấu tất cả đã đọc']);
            break;
            
        // ============ XÓA TẤT CẢ THÔNG BÁO ============
        case 'delete_all_notifications':
            $stmt = $pdo->prepare("
                DELETE FROM THONGBAO
                WHERE MaTK=? AND LoaiThongBao!='BaoCaoBaiDang'
            ");
            $stmt->execute([$userId]);
            
            echo json_encode(['success' => true, 'message' => 'Đã xóa tất cả thông báo']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

function generateAnonymousLabel($pdo, $maBD, $maTK) {
    $stmtExisting = $pdo->prepare("
        SELECT TenNguoiBinhLuan
        FROM BINHLUAN
        WHERE MaBD=? AND MaTK=? AND TenNguoiBinhLuan LIKE 'Ẩn danh %'
        ORDER BY MaBL ASC
        LIMIT 1
    ");
    $stmtExisting->execute([$maBD, $maTK]);
    $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
    if ($existing && !empty($existing['TenNguoiBinhLuan'])) {
        return $existing['TenNguoiBinhLuan'];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT MaTK) as total
        FROM BINHLUAN
        WHERE MaBD=? AND TenNguoiBinhLuan LIKE 'Ẩn danh %' AND TenNguoiBinhLuan != 'Ẩn danh'
    ");
    $stmt->execute([$maBD]);
    $count = intval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $nextNumber = min($count + 1, 999);
    return 'Ẩn danh ' . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
}

// Hàm tạo thông báo khi có bình luận mới
function createCommentNotifications($pdo, $maBD, $maBL, $nguoiBinhLuan, $tenNguoiBinhLuan, $noiDung) {
    // Rút gọn nội dung
    $noiDungRutGon = mb_substr($noiDung, 0, 50);
    if (mb_strlen($noiDung) > 50) {
        $noiDungRutGon .= '...';
    }
    
    // Lấy thông tin bài đăng
    $stmtPost = $pdo->prepare("SELECT MaTK, NoiDung FROM BAIDANG WHERE MaBD=?");
    $stmtPost->execute([$maBD]);
    $post = $stmtPost->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) return;
    
    // 1. Thông báo cho chủ bài đăng (nếu không phải chính họ bình luận)
    if ($post['MaTK'] !== $nguoiBinhLuan) {
        $stmt = $pdo->prepare("
            INSERT INTO THONGBAO (MaTK, LoaiThongBao, MaBD, MaBL, NguoiTacDong, TenNguoiTacDong, NoiDungRutGon)
            VALUES (?, 'BinhLuanBaiCuaBan', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $post['MaTK'],
            $maBD,
            $maBL,
            $nguoiBinhLuan,
            $tenNguoiBinhLuan,
            $noiDungRutGon
        ]);
    }
    
    // 2. Thông báo cho những người đang theo dõi bài đăng (trừ chủ bài và người bình luận)
    $stmtFollowers = $pdo->prepare("
        SELECT DISTINCT MaTK FROM THEODOI_BAIDANG 
        WHERE MaBD=? AND MaTK NOT IN (?, ?)
    ");
    $stmtFollowers->execute([$maBD, $post['MaTK'], $nguoiBinhLuan]);
    $followers = $stmtFollowers->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtInsert = $pdo->prepare("
        INSERT INTO THONGBAO (MaTK, LoaiThongBao, MaBD, MaBL, NguoiTacDong, TenNguoiTacDong, NoiDungRutGon)
        VALUES (?, 'BinhLuanBaiTheoDoi', ?, ?, ?, ?, ?)
    ");
    
    foreach ($followers as $follower) {
        $stmtInsert->execute([
            $follower['MaTK'],
            $maBD,
            $maBL,
            $nguoiBinhLuan,
            $tenNguoiBinhLuan,
            $noiDungRutGon
        ]);
    }
    
    // 3. Kiểm tra nếu bài đăng trở thành HOT (>3 tương tác) -> thông báo cho tất cả
    $stmtCheck = $pdo->prepare("
        SELECT LuotCamXuc, LuotBinhLuan
        FROM BAIDANG WHERE MaBD=?
    ");
    $stmtCheck->execute([$maBD]);
    $counts = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    $luotCamXuc = intval($counts['LuotCamXuc'] ?? 0);
    $luotBinhLuan = intval($counts['LuotBinhLuan'] ?? 0);

    if ($luotCamXuc >= 3 || $luotBinhLuan >= 3) {
        $stmtCheckNotif = $pdo->prepare("
            SELECT COUNT(*) as total FROM THONGBAO WHERE MaBD=? AND LoaiThongBao='BaiHot'
        ");
        $stmtCheckNotif->execute([$maBD]);
        $existingHot = intval($stmtCheckNotif->fetch(PDO::FETCH_ASSOC)['total']);

        if ($existingHot > 0) {
            return;
        }

        // Lấy tất cả user (trừ chủ bài)
        $stmtAllUsers = $pdo->prepare("SELECT MaTK FROM TAIKHOAN WHERE MaTK != ?");
        $stmtAllUsers->execute([$post['MaTK']]);
        $allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);
        
        $noiDungBaiRutGon = mb_substr($post['NoiDung'], 0, 50);
        if (mb_strlen($post['NoiDung']) > 50) {
            $noiDungBaiRutGon .= '...';
        }
        
        $stmtHotNotif = $pdo->prepare("
            INSERT INTO THONGBAO (MaTK, LoaiThongBao, MaBD, NoiDungRutGon)
            VALUES (?, 'BaiHot', ?, ?)
        ");
        
        foreach ($allUsers as $user) {
            $stmtHotNotif->execute([
                $user['MaTK'],
                $maBD,
                $noiDungBaiRutGon
            ]);
        }
    }
}

function createCompanyPostNotifications($pdo, $maBD, $nguoiDang, $tenNguoiDang, $noiDung) {
    $noiDungRutGon = mb_substr($noiDung, 0, 50);
    if (mb_strlen($noiDung) > 50) {
        $noiDungRutGon .= '...';
    }

    $stmtUsers = $pdo->prepare("SELECT MaTK FROM TAIKHOAN WHERE MaTK != ?");
    $stmtUsers->execute([$nguoiDang]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $stmtInsert = $pdo->prepare("
        INSERT INTO THONGBAO (MaTK, LoaiThongBao, MaBD, NguoiTacDong, TenNguoiTacDong, NoiDungRutGon)
        VALUES (?, 'BaiDangCongTy', ?, ?, ?, ?)
    ");

    foreach ($users as $user) {
        $stmtInsert->execute([
            $user['MaTK'],
            $maBD,
            $nguoiDang,
            $tenNguoiDang,
            $noiDungRutGon
        ]);
    }
}
?>
