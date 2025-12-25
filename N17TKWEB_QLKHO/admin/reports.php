<?php
// admin/reports.php - Trang báo cáo thống kê
session_start();
require_once '../config/db.php';
require_once "../middleware.php";
if(!can('reports_manage')) die("Không có quyền truy cập trang này");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userName = $_SESSION['username'] ?? 'Người dùng';
$userRole = $_SESSION['role'] ?? 'Nhân viên';
if ($userRole != 'Quản lý') {
    // Tạm thời cho phép xem
}

// Lấy flash message (nếu có) và xóa khỏi session
$flash = $_SESSION['flash'] ?? null;
if (isset($_SESSION['flash'])) {
    unset($_SESSION['flash']);
}

// Xử lý form chọn tháng/năm (mặc định tháng hiện tại)
$month = $_POST['month'] ?? date('m');
$year = $_POST['year'] ?? date('Y');

// Tính tháng trước
$prev_month = (int)$month - 1;
$prev_year = (int)$year;
if ($prev_month == 0) {
    $prev_month = 12;
    $prev_year--;
}

// Lấy dữ liệu báo cáo (chỉ tính phiếu đã hoàn thành hoặc có thay đổi)
try {
    // Hàm lấy tổng số lượng nhập
    function getTotalImport($pdo, $m, $y) {
        $stmt = $pdo->prepare("
            SELECT SUM(CASE WHEN pn.TinhTrang_PN = 'Có thay đổi' THEN IFNULL(ct.SLN_MOI, ct.SLN) ELSE ct.SLN END) as total
            FROM CHITIETPHIEUNHAP ct
            JOIN PHIEUNHAP pn ON ct.MaPN = pn.MaPN
            WHERE pn.TinhTrang_PN IN ('Hoàn thành', 'Có thay đổi')
            AND MONTH(pn.NgayNhap) = :month AND YEAR(pn.NgayNhap) = :year
        ");
        $stmt->execute(['month' => $m, 'year' => $y]);
        return (int)$stmt->fetchColumn() ?: 0;
    }

    // Hàm lấy tổng số lượng xuất
    function getTotalExport($pdo, $m, $y) {
        $stmt = $pdo->prepare("
            SELECT SUM(CASE WHEN px.TinhTrang_PX = 'Có thay đổi' THEN IFNULL(ct.SLX_MOI, ct.SLX) ELSE ct.SLX END) as total
            FROM CHITIETPHIEUXUAT ct
            JOIN PHIEUXUAT px ON ct.MaPX = px.MaPX
            WHERE px.TinhTrang_PX IN ('Hoàn thành', 'Có thay đổi')
            AND MONTH(px.NgayXuat) = :month AND YEAR(px.NgayXuat) = :year
        ");
        $stmt->execute(['month' => $m, 'year' => $y]);
        return (int)$stmt->fetchColumn() ?: 0;
    }

    // Hàm lấy số phiếu nhập
    function getImportCount($pdo, $m, $y) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM PHIEUNHAP
            WHERE TinhTrang_PN IN ('Hoàn thành', 'Có thay đổi')
            AND MONTH(NgayNhap) = :month AND YEAR(NgayNhap) = :year
        ");
        $stmt->execute(['month' => $m, 'year' => $y]);
        return (int)$stmt->fetchColumn() ?: 0;
    }

    // Hàm lấy số phiếu xuất
    function getExportCount($pdo, $m, $y) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM PHIEUXUAT
            WHERE TinhTrang_PX IN ('Hoàn thành', 'Có thay đổi')
            AND MONTH(NgayXuat) = :month AND YEAR(NgayXuat) = :year
        ");
        $stmt->execute(['month' => $m, 'year' => $y]);
        return (int)$stmt->fetchColumn() ?: 0;
    }

    // Dữ liệu hiện tại
    $total_import = getTotalImport($pdo, $month, $year);
    $total_export = getTotalExport($pdo, $month, $year);
    $import_count = getImportCount($pdo, $month, $year);
    $export_count = getExportCount($pdo, $month, $year);

    // Dữ liệu tháng trước
    $prev_total_import = getTotalImport($pdo, $prev_month, $prev_year);
    $prev_total_export = getTotalExport($pdo, $prev_month, $prev_year);
    $prev_import_count = getImportCount($pdo, $prev_month, $prev_year);
    $prev_export_count = getExportCount($pdo, $prev_month, $prev_year);

    // Tính phần trăm thay đổi
    function calculatePercentChange($current, $prev) {
        if ($prev == 0) return $current > 0 ? 100 : 0;
        return round(($current - $prev) / $prev * 100, 1);
    }

    $import_percent = calculatePercentChange($total_import, $prev_total_import);
    $export_percent = calculatePercentChange($total_export, $prev_total_export);
    $import_count_percent = calculatePercentChange($import_count, $prev_import_count);
    $export_count_percent = calculatePercentChange($export_count, $prev_export_count);

    // 1. Sản phẩm nhập nhiều nhất
    $stmt = $pdo->prepare("
        SELECT sp.MaSP, sp.TenSP, 
               SUM(CASE WHEN pn.TinhTrang_PN = 'Có thay đổi' THEN IFNULL(ct.SLN_MOI, ct.SLN) ELSE ct.SLN END) as total
        FROM CHITIETPHIEUNHAP ct
        JOIN PHIEUNHAP pn ON ct.MaPN = pn.MaPN
        JOIN SANPHAM sp ON ct.MaSP = sp.MaSP
        WHERE pn.TinhTrang_PN IN ('Hoàn thành', 'Có thay đổi')
        AND MONTH(pn.NgayNhap) = :month AND YEAR(pn.NgayNhap) = :year
        GROUP BY ct.MaSP
        ORDER BY total DESC LIMIT 1
    ");
    $stmt->execute(['month' => $month, 'year' => $year]);
    $topImport = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['TenSP' => 'Không có', 'total' => 0];

    // 2. Sản phẩm xuất nhiều nhất
    $stmt = $pdo->prepare("
        SELECT sp.MaSP, sp.TenSP, 
               SUM(CASE WHEN px.TinhTrang_PX = 'Có thay đổi' THEN IFNULL(ct.SLX_MOI, ct.SLX) ELSE ct.SLX END) as total
        FROM CHITIETPHIEUXUAT ct
        JOIN PHIEUXUAT px ON ct.MaPX = px.MaPX
        JOIN SANPHAM sp ON ct.MaSP = sp.MaSP
        WHERE px.TinhTrang_PX IN ('Hoàn thành', 'Có thay đổi')
        AND MONTH(px.NgayXuat) = :month AND YEAR(px.NgayXuat) = :year
        GROUP BY ct.MaSP
        ORDER BY total DESC LIMIT 1
    ");
    $stmt->execute(['month' => $month, 'year' => $year]);
    $topExport = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['TenSP' => 'Không có', 'total' => 0];

    // 3. Top 3 cửa hàng (Xuất)
    $stmt = $pdo->prepare("
        SELECT ch.MaCH, ch.TenCH, ch.DiaChi, 
               SUM(CASE WHEN px.TinhTrang_PX = 'Có thay đổi' THEN IFNULL(ct.SLX_MOI, ct.SLX) ELSE ct.SLX END) as total
        FROM CHITIETPHIEUXUAT ct
        JOIN PHIEUXUAT px ON ct.MaPX = px.MaPX
        JOIN CUAHANG ch ON px.MaCH = ch.MaCH
        WHERE px.TinhTrang_PX IN ('Hoàn thành', 'Có thay đổi')
        AND MONTH(px.NgayXuat) = :month AND YEAR(px.NgayXuat) = :year
        GROUP BY px.MaCH
        ORDER BY total DESC LIMIT 3
    ");
    $stmt->execute(['month' => $month, 'year' => $year]);
    $topStores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. All store quantities for table (Xuất)
    $stmt = $pdo->prepare("
        SELECT ch.MaCH, ch.TenCH, 
               SUM(CASE WHEN px.TinhTrang_PX = 'Có thay đổi' THEN IFNULL(ct.SLX_MOI, ct.SLX) ELSE ct.SLX END) as total
        FROM CHITIETPHIEUXUAT ct
        JOIN PHIEUXUAT px ON ct.MaPX = px.MaPX
        JOIN CUAHANG ch ON px.MaCH = ch.MaCH
        WHERE px.TinhTrang_PX IN ('Hoàn thành', 'Có thay đổi')
        AND MONTH(px.NgayXuat) = :month AND YEAR(px.NgayXuat) = :year
        GROUP BY px.MaCH
        ORDER BY total DESC
    ");
    $stmt->execute(['month' => $month, 'year' => $year]);
    $storeQuantities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dữ liệu cho biểu đồ: Số lượng nhập theo tháng trong năm
    $monthly_imports = array_fill(1, 12, 0);
    $stmt = $pdo->prepare("
        SELECT MONTH(pn.NgayNhap) as mon, 
               SUM(CASE WHEN pn.TinhTrang_PN = 'Có thay đổi' THEN IFNULL(ct.SLN_MOI, ct.SLN) ELSE ct.SLN END) as total
        FROM CHITIETPHIEUNHAP ct
        JOIN PHIEUNHAP pn ON ct.MaPN = pn.MaPN
        WHERE pn.TinhTrang_PN IN ('Hoàn thành', 'Có thay đổi')
        AND YEAR(pn.NgayNhap) = :year
        GROUP BY mon
    ");
    $stmt->execute(['year' => $year]);
    while ($row = $stmt->fetch()) {
        $monthly_imports[(int)$row['mon']] = (int)$row['total'];
    }

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Lỗi khi lấy dữ liệu: ' . $e->getMessage()];
    $total_import = $total_export = $import_count = $export_count = 0;
    $import_percent = $export_percent = $import_count_percent = $export_count_percent = 0;
    $topImport = ['TenSP' => 'Lỗi', 'total' => 0];
    $topExport = ['TenSP' => 'Lỗi', 'total' => 0];
    $topStores = [];
    $storeQuantities = [];
    $monthly_imports = array_fill(1, 12, 0);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Báo Cáo - Hệ Thống Quản Lý Kho Tink</title>
        <!-- Liên kết CSS -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- Thêm Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    
    <style>
    /* Custom styles for report page, mimicking style.css elements */
    .report-grid-4 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    /* === THAY ĐỔI 1: XÓA BỎ report-grid-2 === */
    /* .report-grid-2 {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    @media (max-width: 900px) {
        .report-grid-2 {
            grid-template-columns: 1fr;
        }
    } 
    */
    /* === KẾT THÚC THAY ĐỔI 1 === */

    .report-grid-2-equal {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    @media (max-width: 600px) {
        .report-grid-2-equal {
            grid-template-columns: 1fr;
        }
    }

    .metric-card {
        background: #fff;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid transparent;
    }
    
    /* HOVER EFFECT FOR METRIC CARDS */
    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        border-color: #004080;
        background: linear-gradient(135deg, #f8f9fa, #ffffff);
    }
    
    .metric-card h3 {
        font-size: 16px;
        color: #888;
        margin-bottom: 10px;
        transition: color 0.3s ease;
    }
    
    .metric-card:hover h3 {
        color: #004080;
    }
    
    .metric-card .value {
        font-size: 24px;
        font-weight: bold;
        color: #004080;
        transition: color 0.3s ease;
    }
    
    .metric-card:hover .value {
        color: #002952;
    }
    
    .metric-card .change {
        font-size: 14px;
        margin-top: 5px;
        transition: transform 0.3s ease;
    }
    
    .metric-card:hover .change {
        transform: translateX(5px);
    }
    
    .metric-card .change.positive { color: #4caf50; }
    .metric-card .change.negative { color: #f44336; }

    /* Style cho form lọc */
    .filter-form {
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .filter-form label {
        font-weight: bold;
    }
    
    .filter-form select, .filter-form input {
        padding: 8px; /* Giảm padding để khớp với style.css */
        border: 1px solid #ddd;
        border-radius: 10px; /* Khớp với style.css */
        outline: none;
        width: auto;
        min-width: 100px;
        margin: 0; /* Ghi đè margin từ style.css */
        transition: all 0.3s ease;
    }
    
    /* HOVER EFFECT FOR FORM ELEMENTS */
    .filter-form select:hover, 
    .filter-form input:hover {
        border-color: #004080;
        box-shadow: 0 0 5px rgba(0, 64, 128, 0.2);
    }
    
    .filter-form select:focus, 
    .filter-form input:focus {
        border-color: #004080;
        box-shadow: 0 0 8px rgba(0, 64, 128, 0.3);
    }
    
    .filter-form .btn {
        margin: 0; /* Ghi đè margin từ style.css */
        transition: all 0.3s ease;
    }
    
    /* HOVER EFFECT FOR BUTTONS */
    .filter-form .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    /* Sửa lỗi vỡ bố cục (Giữ nguyên) */
    .store-list-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px;
        border-bottom: 1px solid #eee;
        transition: all 0.3s ease;
        cursor: pointer;
        border-radius: 8px;
        margin-bottom: 5px;
    }
    
    /* HOVER EFFECT FOR STORE LIST ITEMS */
    .store-list-item:hover {
        background: linear-gradient(135deg, #f8f9fa, #e3f2fd);
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border-bottom-color: transparent;
    }
    
    .store-list-item:last-child {
        border-bottom: none;
    }
    
    .store-icon {
        font-size: 24px;
        background: linear-gradient(45deg, #f0e68c, #d4af37);
        color: #fff;
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    
    /* HOVER EFFECT FOR STORE ICONS */
    .store-list-item:hover .store-icon {
        transform: scale(1.1);
        background: linear-gradient(45deg, #d4af37, #b8860b);
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.4);
    }
    
    .store-info {
        flex-grow: 1; 
        min-width: 0; 
    }
    
    .store-info .title {
        font-weight: bold;
        word-break: break-word;
        transition: color 0.3s ease;
    }
    
    /* HOVER EFFECT FOR STORE TITLES */
    .store-list-item:hover .store-info .title {
        color: #004080;
    }
    
    .store-info .location {
        font-size: 13px;
        color: #888;
        word-break: break-word;
        transition: color 0.3s ease;
    }
    
    .store-list-item:hover .store-info .location {
        color: #666;
    }
    
    .store-total {
        margin-left: 10px; 
        font-weight: bold;
        font-size: 16px;
        color: #004080;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    
    /* HOVER EFFECT FOR STORE TOTALS */
    .store-list-item:hover .store-total {
        transform: scale(1.1);
        color: #002952;
    }

    /* ADDITIONAL HOVER EFFECTS FOR OTHER ELEMENTS */
    
    /* Hover effect for charts/graphs containers */
    .chart-container {
        transition: all 0.3s ease;
    }
    
    .chart-container:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        transform: translateY(-3px);
    }
    
    /* Hover effect for table rows */
    .report-table tr {
        transition: all 0.3s ease;
    }
    
    .report-table tr:hover {
        background: linear-gradient(135deg, #f8f9fa, #e8f4fd) !important;
        transform: translateX(3px);
    }
    
    /* Hover effect for action buttons */
    .action-btn {
        transition: all 0.3s ease;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    /* CSS cho bảng báo cáo chi tiết cửa hàng */
    .table-container {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 20px;
    }

    .table-container h3 {
        font-size: 22px;
        font-weight: bold;
        color: #0056b3;
        margin: 0;
        padding: 25px 25px;
        border-bottom: 1px solid #ddd;
        background: #f8f9fa;
    }

    .table-container table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }

    .table-container thead {
        background: #fff;
    }

    .table-container thead th {
        font-size: 17px;
        font-weight: bold;
        color: #333;
        padding: 15px 25px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .table-container thead th:last-child {
        text-align: right;
    }

    .table-container tbody tr {
        border-bottom: none;
    }

    .table-container tbody td {
        font-size: 16px;
        color: #333;
        padding: 18px 25px;
        text-align: left;
        font-weight: normal;
    }

    .table-container tbody td:last-child {
        text-align: right;
        font-weight: normal;
    }

    .table-container tbody tr:not(:last-child) {
        border-bottom: none;
    }

    .table-container tbody tr:hover {
        background: #f8f9fa;
        transform: none;
    }

    .table-container p {
        padding: 20px 25px;
        color: #666;
        margin: 0;
        font-size: 15px;
    }

</style>
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
            <h2>Quản Lý Báo Cáo</h2> 
            <div class="management-tools"> 
                <form method="POST" class="filter-form">
            <div>
                <label>Tháng:</label>
                <select name="month" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label>Năm:</label>
                <input type="number" name="year" value="<?php echo $year; ?>" min="2000" max="<?php echo date('Y') + 1; ?>" required>
            </div>
            <button type="submit" class="add-btn" style="background: linear-gradient(135deg, var(--primary), #005bb5); box-shadow: 0 2px 8px rgba(12, 26, 100, 0.3);">Xem</button>
            <button type="button" class="add-btn" onclick="exportToPDF()">Xuất PDF</button>
        </form>
        </div>
    </div>

        <?php if ($flash): ?>
            <div id="flashMessage" style="margin: 15px 0; padding: 12px; background: <?php echo ($flash['type'] ?? '') === 'error' ? '#ffebee' : '#e8f5e8'; ?>; 
                        border: 1px solid <?php echo ($flash['type'] ?? '') === 'error' ? '#f44336' : '#4caf50'; ?>; 
                        border-radius: 8px; color: <?php echo ($flash['type'] ?? '') === 'error' ? '#c62828' : '#2e7d32'; ?>; transition: opacity 0.4s ease;">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div id="report-content">
        
            <h1 style="text-align: center; margin-bottom: 20px; color: #004080; padding-top: 10px;">
                Báo Cáo Thống Kê Tháng <?php echo $month; ?>/<?php echo $year; ?>
            </h1>

            <div class="report-grid-4">
                <div class="metric-card">
                    <h3>Tổng Số Lượng Nhập</h3>
                    <div class="value"><?php echo number_format($total_import); ?></div>
                    <div class="change <?php echo $import_percent >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $import_percent >= 0 ? '▲' : '▼'; ?> <?php echo $import_percent; ?>% so với tháng trước
                    </div>
                </div>
                <div class="metric-card">
                    <h3>Tổng Số Lượng Xuất</h3>
                    <div class="value"><?php echo number_format($total_export); ?></div>
                    <div class="change <?php echo $export_percent >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $export_percent >= 0 ? '▲' : '▼'; ?> <?php echo $export_percent; ?>% so với tháng trước
                    </div>
                </div>
                <div class="metric-card">
                    <h3>Số Phiếu Nhập</h3>
                    <div class="value"><?php echo number_format($import_count); ?></div>
                    <div class="change <?php echo $import_count_percent >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $import_count_percent >= 0 ? '▲' : '▼'; ?> <?php echo $import_count_percent; ?>% so với tháng trước
                    </div>
                </div>
                <div class="metric-card">
                    <h3>Số Phiếu Xuất</h3>
                    <div class="value"><?php echo number_format($export_count); ?></div>
                    <div class="change <?php echo $export_count_percent >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $export_count_percent >= 0 ? '▲' : '▼'; ?> <?php echo $export_count_percent; ?>% so với tháng trước
                    </div>
                </div>
            </div>

            <div class="metric-card" style="margin-bottom: 20px;">
                <h3 style="color: #333; font-size: 18px;">Tổng Quan Số Lượng Nhập (Năm <?php echo $year; ?>)</h3>
                <canvas id="importChart" data-html2canvas-ignore="false"></canvas>
            </div>
            
            <div class="metric-card" style="margin-bottom: 20px;">
                <h3 style="color: #333; font-size: 18px;">Top 3 Cửa Hàng (Xuất)</h3>
                <?php if (empty($topStores)): ?>
                    <p>Không có dữ liệu</p>
                <?php else: ?>
                    <?php foreach ($topStores as $store): ?>
                        <div class="store-list-item">
                            <div class="store-icon">🏬</div>
                            <div class="store-info">
                                <div class="title"><?php echo htmlspecialchars($store['TenCH']); ?></div>
                                <div class="location"><?php echo htmlspecialchars($store['DiaChi']); ?></div>
                            </div>
                            <span class="store-total">📦 <?php echo $store['total']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="report-grid-2-equal">
                <div class="metric-card">
                    <h3 style="color: #333; font-size: 18px;">Sản Phẩm Nhập Nhiều Nhất</h3>
                    <p style="font-size: 16px; margin-top: 15px;">
                        <strong><?php echo htmlspecialchars($topImport['TenSP']); ?></strong>
                        (<?php echo $topImport['total']; ?> sản phẩm)
                    </p>
                </div>
                <div class="metric-card">
                    <h3 style="color: #333; font-size: 18px;">Sản Phẩm Xuất Nhiều Nhất</h3>
                    <p style="font-size: 16px; margin-top: 15px;">
                        <strong><?php echo htmlspecialchars($topExport['TenSP']); ?></strong>
                        (<?php echo $topExport['total']; ?> sản phẩm)
                    </p>
                </div>
            </div>

            <div class="table-container" style="margin-top: 20px;">
                <h3>
                    Chi Tiết Lượng Xuất Của Các Cửa Hàng (Tháng <?php echo $month . '/' . $year; ?>)
                </h3>
                <?php if (empty($storeQuantities)): ?>
                    <p>Không có dữ liệu cho tháng này</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã CH</th>
                                <th>Tên Cửa Hàng</th>
                                <th>Tổng Số Lượng Xuất</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($storeQuantities as $store): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($store['MaCH']); ?></td>
                                    <td><?php echo htmlspecialchars($store['TenCH']); ?></td>
                                    <td><?php echo number_format($store['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div> </div> <script src="../assets/js/script.js"></script>
    <script>
        // Chart configuration (Giữ nguyên)
        const importData = {
            labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
            datasets: [{
                label: 'Số lượng nhập',
                data: <?php echo json_encode(array_values($monthly_imports)); ?>,
                borderColor: '#004080',
                backgroundColor: 'rgba(0, 64, 128, 0.2)',
                fill: true,
                tension: 0.4
            }]
        };

        const config = {
            type: 'line',
            data: importData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                },
                animation: {
                    duration: 0 
                },
                hover: {
                    animationDuration: 0 
                },
                responsiveAnimationDuration: 0 
            }
        };

        // Khai báo biến biểu đồ (Giữ nguyên)
        let myChart;

        // DOMContentLoaded (Giữ nguyên)
        document.addEventListener('DOMContentLoaded', function() {
            const flash = document.getElementById('flashMessage');
            if (flash) { 
                setTimeout(function() {
                    flash.style.opacity = '0';
                    setTimeout(function() {
                        if (flash.parentNode) flash.parentNode.removeChild(flash);
                    }, 500);
                }, 4000);
            }
            const ctx = document.getElementById('importChart');
            if (ctx) { 
                myChart = new Chart(ctx, config);
            }
        });

        
        // Hàm exportToPDF (Giữ nguyên)
        async function exportToPDF() {
            if (!myChart) {
                alert('Biểu đồ chưa tải xong, vui lòng thử lại.');
                return;
            }

            const element = document.getElementById('report-content');
            const canvas = document.getElementById('importChart'); 

            const month = '<?php echo $month; ?>';
            const year = '<?php echo $year; ?>';
            const filename = `baocao_thongke_thang_${month}_${year}.pdf`;

            const opt = {
                margin:       10, 
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 }, 
                html2canvas:  { scale: 2, useCORS: true, logging: true }, 
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] } 
            };

            document.body.style.cursor = 'wait';

            const chartImage = new Image();
            chartImage.src = myChart.toBase64Image(); 
            chartImage.style.width = '100%'; 
            chartImage.style.height = 'auto';

            canvas.style.display = 'none';
            canvas.parentNode.appendChild(chartImage);

            try {
                await html2pdf().set(opt).from(element).save();
            } catch (e) {
                console.error('Lỗi khi xuất PDF:', e);
                alert('Đã xảy ra lỗi khi tạo tệp PDF.');
            } finally {
                document.body.style.cursor = 'default';
                canvas.style.display = 'block'; 
                canvas.parentNode.removeChild(chartImage); 
            }
        }
    </script>
    <?php require_once 'chatbot_handler.php'; ?>
</body>
</html>