<?php
// admin/products.php - Trang quản lý sản phẩm
session_start();
require_once '../config/db.php';
require_once './activity_history.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userName = $_SESSION['username'] ?? 'Người dùng';
$userRole = $_SESSION['role'] ?? 'Nhân viên';
$userId = $_SESSION['user_id'] ?? null;

// Hàm tạo mã sản phẩm tự động
function generateMaSP($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(MaSP, 3) AS UNSIGNED)) as max_id FROM SANPHAM");
    $result = $stmt->fetch();
    $next_id = ($result['max_id'] ?? 0) + 1;
    return 'SP' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
}

// Lấy flash message (nếu có) và xóa khỏi session
$flash = $_SESSION['flash'] ?? null;
if (isset($_SESSION['flash'])) {
    unset($_SESSION['flash']);
}

// Lấy kết quả import Excel (nếu có) và xóa khỏi session
// Biến này sẽ chứa thông tin: tổng dòng thành công, tổng dòng lỗi, danh sách lỗi chi tiết
$importResult = $_SESSION['import_result'] ?? null;
if (isset($_SESSION['import_result'])) {
    unset($_SESSION['import_result']);
}

// ============================
//  XỬ LÝ AJAX: LẤY TỒN KHO (+ toàn bộ thông tin)
// ============================
if (isset($_GET['action']) && $_GET['action'] == 'get_stock') {
    header('Content-Type: application/json');
    $maSP = $_GET['maSP'] ?? '';

    if (!$maSP) {
        echo json_encode(['success' => false, 'message' => 'Thiếu mã sản phẩm']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM SANPHAM WHERE MaSP = ?");
        $stmt->execute([$maSP]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm']);
            exit;
        }

        // (Nếu chưa có bảng nhập/xuất thì đặt tongNhap, tongXuat = 0)
        echo json_encode([
            'success' => true,
            'data' => [
                'maSP' => $product['MaSP'],
                'tenSP' => $product['TenSP'],
                'theLoai' => $product['TheLoai'],
                'mauSP' => $product['MauSP'],
                'tinhTrang' => $product['TinhTrang'],
                'tonKho' => $product['SLTK'],
                'giaBan' => $product['GiaBan'],
                'hinhAnh' => $product['HinhAnh'],
                'tongNhap' => 0,
                'tongXuat' => 0
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================
//  XỬ LÝ AJAX: LẤY THÔNG TIN SẢN PHẨM ĐỂ SỬA
// ============================
if (isset($_GET['action']) && $_GET['action'] == 'get_product') {
    header('Content-Type: application/json');
    $maSP = $_GET['maSP'] ?? '';
    
    if (!$maSP) {
        echo json_encode(['success' => false, 'message' => 'Thiếu mã sản phẩm']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM SANPHAM WHERE MaSP = ?");
        $stmt->execute([$maSP]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $product]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================
//  XỬ LÝ AJAX: LẤY MÃ SẢN PHẨM MỚI
// ============================
if (isset($_GET['action']) && $_GET['action'] == 'get_new_maSP') {
    header('Content-Type: application/json');
    try {
        $newMaSP = generateMaSP($pdo);
        echo json_encode(['success' => true, 'data' => ['MaSP' => $newMaSP]]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================
//  XỬ LÝ THÊM / SỬA / XÓA
// ============================
if ($_POST['action'] ?? '') {
    $action = $_POST['action'];
    try {
        if ($action == 'add' || $action == 'edit') {
            $tenSP = trim($_POST['TenSP'] ?? '');
            $theLoai = trim($_POST['TheLoai'] ?? '');
            $mauSP = trim($_POST['MauSP'] ?? '');
            $sltk = $_POST['SLTK'] ?? '';
            $giaBan = $_POST['GiaBan'] ?? '';
            $tinhTrang = trim($_POST['TinhTrang'] ?? '');

            // Kiểm tra các trường bắt buộc
            if (empty($tenSP) || empty($theLoai) || empty($mauSP) || $sltk === '' || $giaBan === '' || empty($tinhTrang)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Vui lòng điền đầy đủ tất cả các trường!'];
                header("Location: products.php");
                exit();
            }

            // Kiểm tra số lượng tồn kho và giá bán phải là số không âm
            if ($sltk < 0 || $giaBan < 0) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Số lượng tồn kho và giá bán phải là số không âm!'];
                header("Location: products.php");
                exit();
            }

            // Ràng buộc tự động tình trạng theo SLTK (chỉ khi không phải "Ngừng kinh doanh")
            // Nếu người dùng chọn "Ngừng kinh doanh" thì giữ lại, không tự động đổi
            if ($tinhTrang !== 'Ngừng kinh doanh') {
                $tinhTrang = ((int)$sltk > 0) ? 'Còn hàng' : 'Hết hàng';
            }

            // Xử lý upload ảnh
            $hinhAnh = null;
            $uploadDir = '../uploads/images/';
            
            // Tạo thư mục nếu chưa tồn tại
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (isset($_FILES['HinhAnh']) && $_FILES['HinhAnh']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['HinhAnh'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                // Kiểm tra loại file
                if (!in_array($file['type'], $allowedTypes)) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)!'];
                    header("Location: products.php");
                    exit();
                }

                // Kiểm tra kích thước
                if ($file['size'] > $maxSize) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Kích thước ảnh không được vượt quá 5MB!'];
                    header("Location: products.php");
                    exit();
                }

                // Tạo tên file duy nhất
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('product_', true) . '.' . $extension;
                $filePath = $uploadDir . $fileName;

                // Upload file
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $hinhAnh = 'uploads/images/' . $fileName;
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Lỗi khi upload ảnh!'];
                    header("Location: products.php");
                    exit();
                }
            } elseif ($action == 'edit') {
                // Nếu là sửa và không upload ảnh mới, giữ nguyên ảnh cũ
                $maSP = $_POST['MaSP'] ?? '';
                $stmt = $pdo->prepare("SELECT HinhAnh FROM SANPHAM WHERE MaSP = ?");
                $stmt->execute([$maSP]);
                $oldProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($oldProduct) {
                    $hinhAnh = $oldProduct['HinhAnh'];
                }
            }

            if ($action == 'add') {
                $maSP = generateMaSP($pdo);
                if ($hinhAnh) {
                    $stmt = $pdo->prepare("INSERT INTO SANPHAM (MaSP, TenSP, TheLoai, MauSP, TinhTrang, SLTK, GiaBan, HinhAnh) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$maSP, $tenSP, $theLoai, $mauSP, $tinhTrang, $sltk, $giaBan, $hinhAnh]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO SANPHAM (MaSP, TenSP, TheLoai, MauSP, TinhTrang, SLTK, GiaBan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$maSP, $tenSP, $theLoai, $mauSP, $tinhTrang, $sltk, $giaBan]);
                }
                logActivity($pdo, $userId, $userName, 'Thêm', "SP: $maSP", "Tên: $tenSP, Thể loại: $theLoai, Màu: $mauSP, SL: $sltk, Giá: $giaBan");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Thêm sản phẩm thành công!'];
            } else {
                $maSP = $_POST['MaSP'] ?? '';
                if ($hinhAnh) {
                    // Xóa ảnh cũ nếu có
                    $stmt = $pdo->prepare("SELECT HinhAnh FROM SANPHAM WHERE MaSP = ?");
                    $stmt->execute([$maSP]);
                    $oldProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($oldProduct && $oldProduct['HinhAnh'] && file_exists('../' . $oldProduct['HinhAnh'])) {
                        unlink('../' . $oldProduct['HinhAnh']);
                    }
                    $stmt = $pdo->prepare("UPDATE SANPHAM SET TenSP=?, TheLoai=?, MauSP=?, TinhTrang=?, SLTK=?, GiaBan=?, HinhAnh=? WHERE MaSP=?");
                    $stmt->execute([$tenSP, $theLoai, $mauSP, $tinhTrang, $sltk, $giaBan, $hinhAnh, $maSP]);
                } else {
                    $stmt = $pdo->prepare("UPDATE SANPHAM SET TenSP=?, TheLoai=?, MauSP=?, TinhTrang=?, SLTK=?, GiaBan=? WHERE MaSP=?");
                    $stmt->execute([$tenSP, $theLoai, $mauSP, $tinhTrang, $sltk, $giaBan, $maSP]);
                }
                logActivity($pdo, $userId, $userName, 'Sửa', "SP: $maSP", "Tên: $tenSP, Thể loại: $theLoai, Màu: $mauSP, SL: $sltk, Giá: $giaBan");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cập nhật sản phẩm thành công!'];
            }
        } elseif ($action == 'delete') {
            $maSPs = $_POST['MaSP'] ?? [];
            if (empty($maSPs) || !is_array($maSPs)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Vui lòng chọn ít nhất một sản phẩm để xóa!'];
                header("Location: products.php");
                exit();
            }
            
            $deletedCount = 0;
            $errorMessages = [];
            
            foreach ($maSPs as $maSP) {
                // Kiểm tra xem sản phẩm có trong phiếu xuất không (bất kỳ trạng thái nào)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM CHITIETPHIEUXUAT ct
                    WHERE ct.MaSP = ?
                ");
                $stmt->execute([$maSP]);
                $hasExports = $stmt->fetchColumn() > 0;
                
                // Kiểm tra xem sản phẩm có trong phiếu nhập không (bất kỳ trạng thái nào)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM CHITIETPHIEUNHAP ct
                    WHERE ct.MaSP = ?
                ");
                $stmt->execute([$maSP]);
                $hasImports = $stmt->fetchColumn() > 0;
                
                if ($hasExports || $hasImports) {
                    $errorMessages[] = "Sản phẩm $maSP đã có phiếu xuất/nhập, không thể xóa.";
                    continue;
                }
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM SANPHAM WHERE MaSP=?");
                    $stmt->execute([$maSP]);
                    logActivity($pdo, $userId, $userName, 'Xóa', "SP: $maSP", "Xóa sản phẩm");
                    $deletedCount++;
                } catch (Exception $e) {
                    $errorMessages[] = "Lỗi khi xóa sản phẩm $maSP: " . $e->getMessage();
                }
            }
            
            if ($deletedCount > 0) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Đã xóa thành công $deletedCount sản phẩm!"];
            }
            if (!empty($errorMessages)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $errorMessages)];
            }
        }
    } catch (Exception $e) {
        // Kiểm tra nếu là lỗi foreign key constraint
        if (strpos($e->getMessage(), 'foreign key constraint') !== false || strpos($e->getMessage(), '1451') !== false) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Không thể xóa sản phẩm này vì đã có dữ liệu liên quan trong hệ thống. Bạn có thể đổi trạng thái sang \'Ngừng kinh doanh\'.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Lỗi khi xử lý: ' . $e->getMessage()];
        }
    }

    header("Location: products.php"); // Reload trang
    exit();
}

// ============================
//  LẤY DANH SÁCH SẢN PHẨM
// ============================
$search = $_GET['search'] ?? '';
$where = '';
$searchMessage = '';

if ($search) {
    $where = "WHERE TenSP LIKE '%$search%' OR MaSP LIKE '%$search%'";
    
    // Kiểm tra xem có sản phẩm nào khớp không
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM SANPHAM $where");
    $totalResults = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalResults == 0) {
        $searchMessage = "Không tìm thấy sản phẩm nào với từ khóa: '$search'";
    } else {
        $searchMessage = "Tìm thấy $totalResults sản phẩm với từ khóa: '$search'";
    }
}

// Phân trang: mỗi trang 10 dòng
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Tổng số bản ghi
$countStmt = $pdo->query("SELECT COUNT(*) as total FROM SANPHAM $where");
$totalRows = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Lấy dữ liệu trang hiện tại
$stmt = $pdo->prepare("SELECT * FROM SANPHAM $where ORDER BY MaSP LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm - Hệ Thống Quản Lý Kho Tink</title>
    <!-- Liên kết CSS -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <!-- Thêm Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body>
    <!-- Header --> 
    <header class="header"> 
        <button class="mobile-menu-toggle" onclick="toggleSidebar(); return false;" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="logo">TINK <span>Jewelry</span></div>
        <div class="user-section"> 
            <div class="user-info"> 
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div> 
                <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div> 
            </div> 
            <div class="user-avatar"> <i class="fas fa-user"></i> </div> 
        </div> 
    </header>

    <div class="dashboard-layout">
    <!-- SIDEBAR --> 
    <?php require_once './sidebar.php'; ?>

<!-- MAIN CONTENT --> 
 <main class="main-content"> 
    <div class="management-header"> 
        <div class="management-topbar"> 
            <h2>Quản Lý Sản Phẩm</h2> 
            <div class="management-tools"> 
                <form method="GET" class="search-form"> 
                    <input type="text" placeholder="Tìm kiếm..." name="search" value="<?php echo htmlspecialchars($search); ?>"> 
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i></button> 
                </form> 
                <button class="column-toggle-btn" onclick="openColumnToggle()">
                    <i class="fas fa-columns"></i> Tùy chọn cột
                </button>
                <button class="add-btn" onclick="addProduct()"> 
                    <i class="fas fa-plus"></i> Thêm Sản Phẩm 
                </button>
                <!-- Nút Import Excel -->
                <button class="add-btn import-btn" onclick="openModal('importModal')">
                    <i class="fas fa-file-excel"></i> Import Excel
                </button>
                <?php if($userRole == 'Quản lý'): ?>
                <button class="delete-btn" id="deleteSelectedBtn" onclick="deleteSelectedProducts()" disabled> 
                    <i class="fas fa-trash"></i> Xóa Đã Chọn </button> 
                <?php endif; ?> 
            </div> 
        </div> 
    </div> 
        <!-- Thông báo kết quả tìm kiếm --> 
         <?php if ($searchMessage): ?> 
            <div style="margin-bottom: 15px; padding: 12px; border-radius: 8px; 
                        background: <?php echo strpos($searchMessage, 'Không tìm thấy') !== false ? '#ffebee' : '#e8f5e8'; ?>; 
                        color: <?php echo strpos($searchMessage, 'Không tìm thấy') !== false ? '#c62828' : '#004080'; ?>;"> 
                <?php echo htmlspecialchars($searchMessage); ?> 
            </div> <?php endif; ?> 
        <!-- Flash message --> 
         <?php if ($flash): ?> 
            <div id="flashMessage" style="margin-bottom: 15px; padding: 12px; border-radius: 8px; 
                        background: <?php echo ($flash['type'] ?? '') === 'error' ? '#ffebee' : '#e8f5e8'; ?>; 
                        color: <?php echo ($flash['type'] ?? '') === 'error' ? '#c62828' : '#004080'; ?>;"> 
                <?php echo htmlspecialchars($flash['message']); ?> 
            </div> 
        <?php endif; ?> 

        <!-- Kết quả Import Excel (nếu có) -->
        <?php if ($importResult): ?>
            <div style="margin-bottom: 15px; padding: 12px; border-radius: 8px; background: #e3f2fd; color: #0d47a1;">
                <strong>Kết quả import Excel:</strong><br>
                - Dòng thành công: <?php echo (int)($importResult['success_count'] ?? 0); ?><br>
                - Dòng lỗi: <?php echo (int)($importResult['error_count'] ?? 0); ?>
                <?php if (!empty($importResult['errors'])): ?>
                    <details style="margin-top: 8px;">
                        <summary>Chi tiết lỗi</summary>
                        <ul style="margin-top: 6px; padding-left: 20px;">
                            <?php foreach ($importResult['errors'] as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <!-- Danh sách sản phẩm --> 
         <div class="management-container"> 
            <?php if (empty($products) && !$search): ?> 
                <div style="text-align: center; padding: 40px;"> 
                    <p style="font-size: 18px; margin-bottom: 10px;">Chưa có sản phẩm nào trong hệ thống</p> 
                    <button class="add-btn" onclick="addProduct()"><i class="fas fa-plus"></i> Thêm Sản Phẩm Đầu Tiên</button> 
                </div>
            <?php else: ?> 
                <table class="management-table" id="productsTable"> 
                    <thead> 
                        <tr> 
                            <th data-column="masp">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Mã SP</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th>
                            <th data-column="hinhanh">Ảnh</th>
                            <th data-column="tensp">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Tên SP</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th> 
                            <th data-column="theloai">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Thể Loại</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th> 
                            <th data-column="mausp">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Mẫu SP</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th> 
                            <th data-column="tinhtrang">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Tình Trạng</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th> 
                            <th data-column="tonkho">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Tồn Kho</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th> 
                            <th data-column="giaban">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Giá Bán</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th> 
                            <th data-column="thanhtien">
                                <div class="th-filter-wrapper">
                                    <span class="th-label">Thành Tiền</span>
                                    <button class="th-filter-btn" type="button">
                                        <i class="fas fa-filter caret"></i>
                                    </button>
                                    <div class="th-filter-menu">
                                        <button data-sort="asc">Tăng dần (A-Z, 0-9)</button>
                                        <button data-sort="desc">Giảm dần (Z-A, 9-0)</button>
                                        <div class="th-sep"></div>
                                        <div class="th-values-block">
                                            <div class="th-values-actions">
                                                <span>Lọc theo giá trị</span>
                                            </div>
                                            <div class="th-values-list"></div>
                                        </div>
                                        <div class="th-filter-actions">
                                            <button class="primary" data-action="filter">Áp dụng</button>
                                            <button class="ghost" data-action="clear">Xóa bộ lọc</button>
                                        </div>
                                    </div>
                                </div>
                            </th> 
                            <th class="actions-column" data-column="actions">Hành Động</th> 
                        </tr> 
                    </thead> 
                    <tbody> 
                        <?php foreach ($products as $product): ?> 
                            <tr class="selectable-row" data-id="<?php echo htmlspecialchars($product['MaSP']); ?>" onclick="toggleRowSelection(this, event)"> 
                                <td data-column="masp"><?php echo htmlspecialchars($product['MaSP']); ?></td> 
                                <td data-column="hinhanh" style="text-align: center; padding: 5px;">
                                    <?php if (!empty($product['HinhAnh']) && file_exists('../' . $product['HinhAnh'])): ?>
                                        <img src="../<?php echo htmlspecialchars($product['HinhAnh']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['TenSP']); ?>" 
                                             class="product-thumbnail" 
                                             onclick="viewImage('../<?php echo htmlspecialchars($product['HinhAnh']); ?>', '<?php echo htmlspecialchars($product['TenSP']); ?>')"
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid #ddd;">
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-column="tensp"><?php echo htmlspecialchars($product['TenSP']); ?></td> 
                                <td data-column="theloai"><?php echo htmlspecialchars($product['TheLoai']); ?></td> 
                                <td data-column="mausp"><?php echo htmlspecialchars($product['MauSP']); ?></td> 
                                <td data-column="tinhtrang"><?php echo htmlspecialchars($product['TinhTrang']); ?></td> 
                                <td data-column="tonkho"><?php echo $product['SLTK']; ?></td> 
                                <td data-column="giaban"><?php echo number_format($product['GiaBan'], 0, ',', '.'); ?> VNĐ</td> 
                                <td data-column="thanhtien"><?php echo number_format($product['SLTK'] * $product['GiaBan'], 0, ',', '.'); ?> VNĐ</td> 
                                <td data-column="actions"> <div class="management-actions"> <button class="edit-btn" onclick="editProduct('<?php echo $product['MaSP']; ?>')">Sửa</button> 
                                <button class="edit-btn" style="background: var(--warning);" onclick="viewStock('<?php echo $product['MaSP']; ?>')">Xem tồn kho</button> 
                            </div> 
                        </td> 
                    </tr> 
                    <?php endforeach; ?> 
                </tbody> 
            </table> 
            <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 12px; display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;">
                <?php
                    $baseUrl = 'products.php';
                    $params = $_GET;
                    unset($params['page']);
                    $queryBase = http_build_query($params);
                    function pageLink($p, $queryBase, $baseUrl) { 
                        $q = $queryBase ? ($queryBase . '&page=' . $p) : ('page=' . $p);
                        return $baseUrl . '?' . $q;
                    }
                ?>
                <a href="<?php echo pageLink(max(1, $page-1), $queryBase, $baseUrl); ?>" class="btn" style="padding: 6px 10px; background:#eee; border-radius:6px;<?php echo $page==1?' pointer-events:none; opacity:.5;':''; ?>">«</a>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="<?php echo pageLink($p, $queryBase, $baseUrl); ?>" class="btn" style="padding: 6px 10px; border-radius:6px; <?php echo $p==$page?'background: var(--primary); color:#fff;':'background:#eee;'; ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
                <a href="<?php echo pageLink(min($totalPages, $page+1), $queryBase, $baseUrl); ?>" class="btn" style="padding: 6px 10px; background:#eee; border-radius:6px;<?php echo $page==$totalPages?' pointer-events:none; opacity:.5;':''; ?>">»</a>
            </div>
            <?php endif; ?>
            <?php endif; ?> 
        </div> 
    </main>
    </div>

    <!-- Modal Tùy chọn cột -->
    <div id="columnToggleModal" class="column-toggle-modal" onclick="closeColumnToggleOnBackdrop(event)">
        <div class="column-toggle-content" onclick="event.stopPropagation()">
            <div class="column-toggle-header">
                <h3><i class="fas fa-columns"></i> Tùy chọn hiển thị cột</h3>
                <button class="column-toggle-close" onclick="closeColumnToggle()">&times;</button>
            </div>
            <div class="column-toggle-list" id="columnToggleList">
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-masp" data-column="masp" checked>
                    <label for="col-masp">Mã SP</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-hinhanh" data-column="hinhanh" checked>
                    <label for="col-hinhanh">Ảnh</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-tensp" data-column="tensp" checked>
                    <label for="col-tensp">Tên SP</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-theloai" data-column="theloai" checked>
                    <label for="col-theloai">Thể Loại</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-mausp" data-column="mausp" checked>
                    <label for="col-mausp">Mẫu SP</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-tinhtrang" data-column="tinhtrang" checked>
                    <label for="col-tinhtrang">Tình Trạng</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-tonkho" data-column="tonkho" checked>
                    <label for="col-tonkho">Tồn Kho</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-giaban" data-column="giaban" checked>
                    <label for="col-giaban">Giá Bán</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-thanhtien" data-column="thanhtien" checked>
                    <label for="col-thanhtien">Thành Tiền</label>
                </div>
                <div class="column-toggle-item">
                    <input type="checkbox" id="col-actions" data-column="actions" checked disabled>
                    <label for="col-actions" style="opacity: 0.6;">Hành Động (Không thể ẩn)</label>
                </div>
            </div>
            <div class="column-toggle-actions">
                <button class="column-toggle-reset" onclick="resetColumnToggle()">Đặt lại mặc định</button>
                <button class="column-toggle-apply" onclick="applyColumnToggle()">Áp dụng</button>
            </div>
        </div>
    </div>

    <!-- Modal Thêm/Sửa -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2 id="modalTitle" style="margin-bottom: 15px; color: var(--primary);">Thêm Sản Phẩm</h2>
            <form method="POST" action="products.php" enctype="multipart/form-data" onsubmit="return validateProductForm()">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="MaSP" id="MaSPHidden" value="<?php echo generateMaSP($pdo); ?>">
                
            <div class="form-group">
                <label>Mã Sản Phẩm:</label>
                <?php 
                $nextMaSP = generateMaSP($pdo);
                ?>
                <input type="text" id="MaSP" value="<?php echo $nextMaSP; ?>" disabled 
                       style="background-color: #f0f0f0;">
            </div>

            <div class="form-group">
                <label>Tên Sản Phẩm: <span style="color: red;">*</span></label>
                <input type="text" name="TenSP" id="TenSP" placeholder="Tên SP" required>
            </div>

            <div class="form-group">
                <label>Thể Loại: <span style="color: red;">*</span></label>
                <select name="TheLoai" id="TheLoai" required>
                    <option value="">Chọn Thể Loại</option>
                    <option value="Vòng tay">Vòng tay</option>
                    <option value="Vòng cổ">Vòng cổ</option>
                    <option value="Khuyên tai">Khuyên tai</option>
                    <option value="Nhẫn">Nhẫn</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Mẫu Sản Phẩm: <span style="color: red;">*</span></label>
                <input type="text" name="MauSP" id="MauSP" placeholder="Màu SP" required>
            </div>
            
            <div class="form-group">
                <label>Tình Trạng: <span style="color: red;">*</span></label>
                <select name="TinhTrang" id="TinhTrang" required>
                    <option value="">Chọn Tình Trạng</option>
                    <option value="Còn hàng">Còn hàng</option>
                    <option value="Hết hàng">Hết hàng</option>
                    <option value="Ngừng kinh doanh">Ngừng kinh doanh</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Số Lượng Tồn Kho: <span style="color: red;">*</span></label>
                <input type="number" name="SLTK" id="SLTK" placeholder="Số Lượng Tồn Kho" min="0" required>
            </div>
            
            <div class="form-group">
                <label>Giá Bán: <span style="color: red;">*</span></label>
                <input type="number" name="GiaBan" id="GiaBan" placeholder="Giá Bán" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label>Ảnh Sản Phẩm:</label>
                <input type="file" name="HinhAnh" id="HinhAnh" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                <small style="color: #666; font-size: 12px;">Chấp nhận: JPG, PNG, GIF, WEBP (tối đa 5MB)</small>
                <div id="imagePreview" style="margin-top: 10px; display: none;">
                    <img id="previewImg" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div id="currentImage" style="margin-top: 10px; display: none;">
                    <p style="font-size: 12px; color: #666;">Ảnh hiện tại:</p>
                    <img id="currentImg" src="" alt="Current" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn-save">Lưu</button>
        </form>
        </div>
    </div>

    <!-- Modal Import Excel Sản Phẩm -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('importModal')">&times;</span>
            <h2 style="margin-bottom: 15px; color: #004080;">
                <i class="fas fa-file-import"></i> Import Sản Phẩm từ Excel
            </h2>
            <form method="POST" action="products_import_excel.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Chọn file Excel (.xlsx): <span style="color: red;">*</span></label>
                    <input 
                        type="file" 
                        name="excel_file" 
                        id="excel_file" 
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        required
                    >
                    <small style="color: #666; font-size: 12px;">
                        File phải là định dạng .xlsx, dòng đầu tiên là tiêu đề cột.<br>
                        Cần có tối thiểu các cột: <strong>Mã sản phẩm</strong>, <strong>Tên sản phẩm</strong>, <strong>Thể loại</strong>, <strong>Số lượng tồn</strong>, <strong>Giá bán</strong>.<br>
                        Cột tùy chọn: <strong>Màu sản phẩm</strong>, <strong>Tình trạng</strong> (Còn hàng / Hết hàng / Ngừng kinh doanh), <strong>Ảnh sản phẩm</strong>.
                    </small>
                </div>
                <div class="form-group import-hint">
                    <strong>Quy tắc xử lý:</strong>
                    <ul>
                        <li>Bỏ qua dòng trống hoặc thiếu dữ liệu bắt buộc.</li>
                        <li>Mã sản phẩm trùng sẽ được báo lỗi và bỏ qua.</li>
                        <li>Số lượng âm bị coi là không hợp lệ; tình trạng tự suy ra từ số lượng nếu không cung cấp.</li>
                        <li>Kết quả import (dòng hợp lệ/lỗi, chi tiết lỗi) sẽ hiển thị sau khi xử lý.</li>
                    </ul>
                </div>
                <div class="modal-actions" style="margin-top: 18px;">
                    <button type="button" class="btn-cancel" onclick="closeModal('importModal')">Hủy</button>
                    <button type="submit" class="btn-save" style="background: #004080;">Thực hiện Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Xem Tồn Kho -->
    <div id="stockModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close" onclick="closeModal('stockModal')">&times;</span>
            <h2 style="margin-bottom: 20px; color: var(--primary);">📦 Chi tiết sản phẩm</h2>
            <div id="stockInfo"></div>
        </div>
    </div>

    <!-- Modal xác nhận xóa -->
    <div id="confirmDeleteModal" class="modal">
    <div class="modal-content modal-small">
        <span class="close" onclick="closeModal('confirmDeleteModal')">&times;</span>
        <h2 style="margin-bottom: 15px; color: var(--primary);">Xác nhận Xóa</h2>
        <p id="confirmDeleteMessage" style="margin-bottom: 25px; color: var(--text);">
            Bạn có chắc chắn muốn xóa sản phẩm này?
        </p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('confirmDeleteModal')">Hủy</button>
            <button class="btn-delete" onclick="confirmDelete()">Xóa</button>
        </div>
    </div>
</div>

    <!-- Modal Xem Ảnh -->
    <div id="imageModal" class="modal" onclick="closeImageModal(event)" style="display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.9);">
        <div class="modal-content" style="max-width: 90%; max-height: 90vh; background: transparent; box-shadow: none; position: relative;" onclick="event.stopPropagation()">
            <span class="close" onclick="closeImageModal()" style="color: white; font-size: 40px; z-index: 10001; position: absolute; top: -50px; right: 0; cursor: pointer;">&times;</span>
            <img id="modalImage" src="" alt="" style="max-width: 100%; max-height: 90vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <p id="modalImageTitle" style="color: white; text-align: center; margin-top: 15px; font-size: 18px; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);"></p>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script src="https://kit.fontawesome.com/a2e0b2b9f5.js" crossorigin="anonymous"></script>
    <script>
        function editProduct(maSP) {
            fetch(`products.php?action=get_product&maSP=${encodeURIComponent(maSP)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.data;
                        document.getElementById('modalTitle').innerText = "Sửa Sản Phẩm";
                        document.getElementById('action').value = "edit";
                        document.getElementById('MaSP').value = product.MaSP;
                        document.getElementById('MaSPHidden').value = product.MaSP;
                        document.getElementById('TenSP').value = product.TenSP;
                        document.getElementById('TheLoai').value = product.TheLoai;
                        document.getElementById('MauSP').value = product.MauSP;
                        document.getElementById('TinhTrang').value = product.TinhTrang;
                        document.getElementById('SLTK').value = product.SLTK;
                        document.getElementById('GiaBan').value = product.GiaBan;
                        // Hiển thị ảnh hiện tại nếu có
                        if (product.HinhAnh) {
                            document.getElementById('currentImg').src = '../' + product.HinhAnh;
                            document.getElementById('currentImage').style.display = 'block';
                        } else {
                            document.getElementById('currentImage').style.display = 'none';
                        }
                        document.getElementById('imagePreview').style.display = 'none';
                        document.getElementById('HinhAnh').value = '';
                        document.getElementById('submitBtn').innerText = "Cập nhật";
                        document.getElementById('submitBtn').className = "btn-update";
                        openModal('addModal');
                    } else {
                        alert('Không thể tải dữ liệu sản phẩm');
                    }
                })
                .catch(error => {
                    console.error('Lỗi:', error);
                    alert('Không thể tải dữ liệu sản phẩm');
                });
        }
        
        function addProduct() {
            // Lấy mã mới từ server
            fetch('products.php?action=get_new_maSP')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newMaSP = data.data.MaSP;
                        document.getElementById('modalTitle').innerText = "Thêm Sản Phẩm";
                        document.getElementById('action').value = "add";
                        document.getElementById('MaSP').value = newMaSP;
                        document.getElementById('MaSPHidden').value = newMaSP;
                        document.getElementById('TenSP').value = "";
                        document.getElementById('TheLoai').value = "";
                        document.getElementById('MauSP').value = "";
                        document.getElementById('TinhTrang').value = "";
                        document.getElementById('SLTK').value = "";
                        document.getElementById('GiaBan').value = "";
                        // Ẩn preview và ảnh hiện tại khi thêm mới
                        document.getElementById('imagePreview').style.display = 'none';
                        document.getElementById('currentImage').style.display = 'none';
                        document.getElementById('HinhAnh').value = '';
                        document.getElementById('submitBtn').innerText = "Lưu";
                        document.getElementById('submitBtn').className = "btn-save";
                        openModal('addModal');
                    } else {
                        alert('Không thể lấy mã sản phẩm mới');
                    }
                })
                .catch(error => {
                    console.error('Lỗi:', error);
                    alert('Không thể lấy mã sản phẩm mới');
                });
        }

        function toggleRowSelection(row, event) {
            // Ngăn chặn click khi click vào nút bên trong row
            if (event.target.tagName === 'BUTTON' || event.target.closest('button')) {
                return;
            }
            
            row.classList.toggle('selected');
            updateDeleteButton();
        }

        function updateDeleteButton() {
            const selectedRows = document.querySelectorAll('.selectable-row.selected');
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            if (deleteBtn) {
                deleteBtn.disabled = selectedRows.length === 0;
            }
        }

        function deleteSelectedProducts() {
            const selectedRows = document.querySelectorAll('.selectable-row.selected');
            if (selectedRows.length === 0) {
                alert('Vui lòng chọn ít nhất một sản phẩm để xóa!');
                return;
            }
            
            const selectedIds = Array.from(selectedRows).map(row => row.getAttribute('data-id'));
            const count = selectedIds.length;
            
            if (confirm(`Bạn có chắc chắn muốn xóa ${count} sản phẩm đã chọn?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete">';
                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'MaSP[]';
                    input.value = id;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            }
        }

        function validateProductForm() {
            const tenSP = document.querySelector('input[name="TenSP"]').value.trim();
            const theLoai = document.querySelector('select[name="TheLoai"]').value;
            const mauSP = document.querySelector('input[name="MauSP"]').value.trim();
            const tinhTrang = document.querySelector('select[name="TinhTrang"]').value;
            const sltk = document.querySelector('input[name="SLTK"]').value;
            const giaBan = document.querySelector('input[name="GiaBan"]').value;
            
            if (!tenSP || !theLoai || !mauSP || !tinhTrang || sltk === '' || giaBan === '') {
                alert('Vui lòng điền đầy đủ tất cả các trường!');
                return false;
            }
            
            if (parseFloat(sltk) < 0 || parseFloat(giaBan) < 0) {
                alert('Số lượng tồn kho và giá bán phải là số không âm!');
                return false;
            }
            
            return true;
        }

        function viewStock(maSP) {
            fetch(`products.php?action=get_stock&maSP=${encodeURIComponent(maSP)}`)
                .then(response => response.json())
                .then(data => {
                    const stockInfo = document.getElementById('stockInfo');
                    if (data.success) {
                        const product = data.data;
                        const imageHtml = product.hinhAnh 
                            ? `<div style="text-align: center; margin-bottom: 20px;">
                                <img src="../${product.hinhAnh}" alt="${product.tenSP}" 
                                     style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); cursor: pointer;"
                                     onclick="openImageModal(this)">
                              </div>`
                            : '';
                        
                        stockInfo.innerHTML = `
                            <div class="stock-info" style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                                ${imageHtml}
                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px; font-weight: bold; color: var(--primary); width: 40%;">Mã SP:</td>
                                        <td style="padding: 10px;">${product.maSP}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                                        <td style="padding: 10px; font-weight: bold; color: var(--primary);">Tên sản phẩm:</td>
                                        <td style="padding: 10px;">${product.tenSP}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px; font-weight: bold; color: var(--primary);">Thể loại:</td>
                                        <td style="padding: 10px;">${product.theLoai}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                                        <td style="padding: 10px; font-weight: bold; color: var(--primary);">Màu sắc:</td>
                                        <td style="padding: 10px;">${product.mauSP}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px; font-weight: bold; color: var(--primary);">Tình trạng:</td>
                                        <td style="padding: 10px;"><strong style="color: ${product.tinhTrang === 'Còn hàng' ? '#28a745' : product.tinhTrang === 'Hết hàng' ? '#dc3545' : '#ffc107'};">${product.tinhTrang}</strong></td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                                        <td style="padding: 10px; font-weight: bold; color: #e74c3c;">📊 Tồn kho:</td>
                                        <td style="padding: 10px; font-weight: bold; font-size: 18px; color: #e74c3c;">${product.tonKho}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px; font-weight: bold; color: var(--primary);">Giá bán:</td>
                                        <td style="padding: 10px; font-weight: bold; color: #27ae60;">${parseFloat(product.giaBan).toLocaleString('vi-VN')} đ</td>
                                    </tr>
                                </table>
                            </div>`;
                    } else {
                        stockInfo.innerText = 'Không thể lấy thông tin sản phẩm: ' + data.message;
                    }
                    openModal('stockModal');
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('stockInfo').innerText = 'Có lỗi xảy ra khi lấy thông tin tồn kho';
                    openModal('stockModal');
                });
        }

        function openImageModal(imgElement) {
            document.getElementById('modalImage').src = imgElement.src;
            document.getElementById('modalImageTitle').innerText = 'Xem ảnh sản phẩm';
            document.getElementById('imageModal').style.display = 'flex';
        }

        function closeImageModal(event) {
            if (event && event.target !== document.getElementById('imageModal')) {
                return;
            }
            document.getElementById('imageModal').style.display = 'none';
        }

        // Tự ẩn flash message sau 4s (nếu có)
        document.addEventListener('DOMContentLoaded', function() {
            const flash = document.getElementById('flashMessage');
            if (!flash) return;
            // Hiện opacity mặc định (1) -> chuyển xuống 0 rồi display none
            setTimeout(function() {
                flash.style.opacity = '0';
                setTimeout(function() {
                    if (flash.parentNode) flash.parentNode.removeChild(flash);
                }, 500); // khớp với transition
            }, 4000); // 4 giây trước khi ẩn
            
            // Load column preferences
            loadColumnPreferences();
        });

        // ========== Tùy chọn cột ==========
        const STORAGE_KEY = 'products_column_preferences';

        function openColumnToggle() {
            const modal = document.getElementById('columnToggleModal');
            modal.classList.add('show');
            // Load current state
            loadCheckboxStates();
        }

        function closeColumnToggle() {
            const modal = document.getElementById('columnToggleModal');
            modal.classList.remove('show');
        }

        function closeColumnToggleOnBackdrop(event) {
            if (event.target.id === 'columnToggleModal') {
                closeColumnToggle();
            }
        }

        function loadCheckboxStates() {
            const preferences = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            const checkboxes = document.querySelectorAll('#columnToggleList input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                const col = checkbox.getAttribute('data-column');
                if (preferences.hasOwnProperty(col)) {
                    checkbox.checked = preferences[col];
                } else {
                    checkbox.checked = true; // Default: all visible
                }
            });
        }

        function resetColumnToggle() {
            const checkboxes = document.querySelectorAll('#columnToggleList input[type="checkbox"]:not([disabled])');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function applyColumnToggle() {
            const preferences = {};
            const checkboxes = document.querySelectorAll('#columnToggleList input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                const col = checkbox.getAttribute('data-column');
                preferences[col] = checkbox.checked;
                // Đảm bảo cột actions luôn là true
                if (col === 'actions') {
                    preferences[col] = true;
                }
            });
            
            // Save to localStorage
            localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
            
            // Apply to table
            applyColumnVisibility(preferences);
            
            // Close modal
            closeColumnToggle();
        }

        function applyColumnVisibility(preferences) {
            const table = document.getElementById('productsTable');
            if (!table) return;
            
            Object.keys(preferences).forEach(col => {
                // Đảm bảo cột actions luôn được hiển thị
                if (col === 'actions') {
                    const cells = table.querySelectorAll(`[data-column="${col}"]`);
                    cells.forEach(cell => cell.classList.remove('hidden'));
                    return;
                }
                const isVisible = preferences[col];
                const cells = table.querySelectorAll(`[data-column="${col}"]`);
                
                cells.forEach(cell => {
                    if (isVisible) {
                        cell.classList.remove('hidden');
                    } else {
                        cell.classList.add('hidden');
                    }
                });
            });
            // Đảm bảo cột actions luôn visible (trong trường hợp preferences không có key 'actions')
            const actionsCells = table.querySelectorAll(`[data-column="actions"]`);
            actionsCells.forEach(cell => cell.classList.remove('hidden'));
        }

        function loadColumnPreferences() {
            const preferences = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            if (Object.keys(preferences).length > 0) {
                applyColumnVisibility(preferences);
            }
        }

        // Preview ảnh khi chọn file
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                    document.getElementById('currentImage').style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                document.getElementById('imagePreview').style.display = 'none';
            }
        }

        // Xem ảnh lớn
        function viewImage(imageSrc, productName) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('modalImageTitle').textContent = productName || 'Ảnh sản phẩm';
            document.getElementById('imageModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Đóng modal ảnh
        function closeImageModal(event) {
            if (!event || event.target.id === 'imageModal' || event.target.classList.contains('close')) {
                document.getElementById('imageModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Đóng modal ảnh bằng phím ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const imageModal = document.getElementById('imageModal');
                if (imageModal && imageModal.style.display === 'flex') {
                    closeImageModal();
                }
            }
        });
    </script>
    <?php require_once 'chatbot_handler.php'; ?>
</body>
</html>