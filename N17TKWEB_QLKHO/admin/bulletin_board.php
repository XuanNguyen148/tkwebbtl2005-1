<?php
// admin/bulletin_board.php - Trang Bảng tin
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userName = $_SESSION['username'] ?? 'Người dùng';
$userRole = $_SESSION['role'] ?? 'Nhân viên';
$userId = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Tin - Hệ Thống Quản Lý Kho Tink</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==================== BẢNG TIN STYLES ==================== */
        .bulletin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header với nút đăng bài và tabs */
        .bulletin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .create-post-btn {
            background: linear-gradient(135deg, var(--accent), #b8860b);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
            transition: all 0.3s ease;
        }

        .create-post-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.5);
        }

        .create-post-btn i {
            font-size: 20px;
        }

        /* Tabs filter */
        .bulletin-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Danh sách bài đăng */
        .posts-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Card bài đăng */
        .post-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .post-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .post-card.hidden-post {
            opacity: 0.6;
            border: 1px dashed #cbd5f5;
        }

        .post-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
            background: #f1f5f9;
            color: #64748b;
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .post-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .author-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .author-info {
            display: flex;
            flex-direction: column;
        }

        .author-name {
            font-weight: 700;
            font-size: 15px;
            color: var(--dark);
        }

        .post-time {
            font-size: 13px;
            color: var(--text-light);
        }

        .post-category {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        .category-company {
            background: #e3f2fd;
            color: #1976d2;
        }

        .category-forum {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .category-qa {
            background: #fff3e0;
            color: #f57c00;
        }

        .post-menu-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-light);
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .post-menu-btn:hover {
            background: #f0f0f0;
            color: var(--dark);
        }

        .post-content {
            margin-bottom: 15px;
            line-height: 1.6;
            color: var(--text);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .post-attachments {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .attachment-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 1;
        }

        .attachment-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }

        .attachment-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .attachment-file {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f5f5f5;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text);
            transition: background 0.2s;
        }

        .attachment-file:hover {
            background: #e0e0e0;
        }

        .attachment-file i {
            font-size: 24px;
            color: var(--primary);
        }

        .post-stats {
            display: flex;
            gap: 20px;
            padding: 12px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 12px;
            font-size: 14px;
            color: var(--text-light);
        }

        .post-actions {
            display: flex;
            gap: 10px;
            justify-content: space-around;
        }

        .action-btn {
            flex: 1;
            background: none;
            border: none;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: #f0f0f0;
        }

        .action-btn.active {
            color: var(--primary);
        }

        .action-btn i {
            font-size: 18px;
        }

        /* Comments section */
        .comments-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .comment-item {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
        }

        .comment-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #b8860b);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .comment-content {
            flex: 1;
        }

        .comment-bubble {
            background: #f0f0f0;
            padding: 10px 15px;
            border-radius: 15px;
            margin-bottom: 5px;
        }

        .comment-author {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .comment-text {
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .comment-actions {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: var(--text-light);
            margin-left: 15px;
        }

        .comment-action {
            cursor: pointer;
            transition: color 0.2s;
        }

        .comment-action:hover {
            color: var(--primary);
        }

        .comment-action.liked {
            color: var(--primary);
            font-weight: 600;
        }

        .comment-input-wrapper {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }

        .comment-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .comment-input:focus {
            border-color: var(--primary);
        }

        .comment-submit-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .comment-submit-btn:hover {
            transform: scale(1.05);
        }

        /* Modal đăng bài */
        .create-post-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .create-post-modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            padding: 25px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text-light);
            cursor: pointer;
            padding: 0;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #f0f0f0;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .post-textarea {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            resize: vertical;
            outline: none;
            transition: border-color 0.2s;
        }

        .post-textarea:focus {
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group select:focus {
            border-color: var(--primary);
        }

        .file-upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-upload-area:hover {
            border-color: var(--primary);
            background: #f8f9fa;
        }

        .file-upload-area i {
            font-size: 36px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .file-upload-area p {
            color: var(--text-light);
            font-size: 14px;
        }

        .file-input {
            display: none;
        }

        .selected-files {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .file-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
        }

        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .btn-cancel {
            padding: 10px 24px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            border-color: var(--text);
            color: var(--text);
        }

        .btn-submit {
            padding: 10px 24px;
            border: none;
            background: linear-gradient(135deg, var(--primary), #005bb5);
            color: white;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,64,128,0.3);
        }

        /* Notification bell */
        .notification-bell {
            position: fixed;
            top: 85px;
            right: 30px;
            z-index: 9999;
        }

        .bell-icon {
            position: relative;
            background: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transition: all 0.3s;
        }

        .bell-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .bell-icon i {
            font-size: 24px;
            color: var(--primary);
        }

        .bell-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
        }

        .notifications-dropdown {
            display: none;
            position: absolute;
            top: 60px;
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .notifications-dropdown.show {
            display: block;
        }

        .notif-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .notif-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--dark);
        }

        .notif-settings-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-light);
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .notif-settings-btn:hover {
            background: #f0f0f0;
            color: var(--dark);
        }

        .notif-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notif-item:hover {
            background: #f8f9fa;
        }

        .notif-item.unread {
            background: #e3f2fd;
        }

        .notif-content {
            font-size: 14px;
            line-height: 1.5;
            color: var(--text);
            margin-bottom: 5px;
        }

        .notif-time {
            font-size: 12px;
            color: var(--text-light);
        }

        .notif-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-light);
        }

        .notif-settings-menu {
            display: none;
            position: absolute;
            top: 50px;
            right: 10px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            overflow: hidden;
            z-index: 1;
        }

        .notif-settings-menu.show {
            display: block;
        }

        .notif-settings-menu button {
            display: block;
            width: 100%;
            padding: 12px 20px;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }

        .notif-settings-menu button:hover {
            background: #f0f0f0;
        }

        /* Post actions modal */
        .post-action-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10001;
            align-items: center;
            justify-content: center;
        }

        .post-action-modal.show {
            display: flex;
        }

        .post-action-content {
            background: white;
            border-radius: 16px;
            padding: 20px;
            width: 90%;
            max-width: 360px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .post-action-content h3 {
            margin: 0 0 15px;
            font-size: 18px;
            color: var(--dark);
        }

        .post-action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .post-action-buttons button {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .post-action-buttons button:hover {
            transform: translateY(-1px);
        }

        .post-action-toggle {
            background: #e0f2fe;
            color: #0369a1;
        }

        .post-action-delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .post-action-cancel {
            background: #f1f5f9;
            color: #334155;
        }

        /* Image modal */
        .image-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 10002;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .image-modal.show {
            display: flex;
        }

        .image-modal-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
        }

        .image-modal-content img {
            max-width: 90vw;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        }

        .image-modal-close {
            position: absolute;
            top: -15px;
            right: -15px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Post detail modal */
        .post-detail-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 10001;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .post-detail-modal.show {
            display: flex;
        }

        .post-detail-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 820px;
            max-height: 85vh;
            overflow-y: auto;
            padding: 20px;
        }

        .post-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .post-detail-header h3 {
            margin: 0;
            font-size: 20px;
            color: var(--primary);
        }

        /* Loading spinner */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        .spinner {
            border: 3px solid #f0f0f0;
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .bulletin-container {
                padding: 15px;
            }

            .bulletin-header {
                flex-direction: column;
                align-items: stretch;
            }

            .bulletin-tabs {
                justify-content: center;
            }

            .tab-btn {
                font-size: 13px;
                padding: 8px 15px;
            }

            .notification-bell {
                top: 75px;
                right: 15px;
            }

            .bell-icon {
                width: 45px;
                height: 45px;
            }

            .notifications-dropdown {
                width: calc(100vw - 30px);
                right: -15px;
            }

            .modal-content {
                width: 95%;
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
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
            <!-- Notification Bell -->
            <div class="notification-bell">
                <div class="bell-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="bell-badge" id="notifBadge" style="display: none;">0</span>
                </div>
                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div class="notif-header">
                        <span class="notif-title">Thông báo</span>
                        <button class="notif-settings-btn" onclick="toggleNotifSettings(event)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="notif-settings-menu" id="notifSettingsMenu">
                            <button onclick="markAllAsRead()">
                                <i class="fas fa-check-double"></i> Đánh dấu tất cả đã đọc
                            </button>
                            <button onclick="deleteAllNotifications()">
                                <i class="fas fa-trash"></i> Xóa tất cả
                            </button>
                        </div>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Đang tải thông báo...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bulletin-container">
                <!-- Header với nút đăng bài và tabs -->
                <div class="bulletin-header">
                    <button class="create-post-btn" onclick="openCreatePostModal()">
                        <i class="fas fa-plus-circle"></i>
                        <span>Đăng bài</span>
                    </button>
                    
                    <div class="bulletin-tabs">
                        <button class="tab-btn active" data-filter="all">Tất cả</button>
                        <button class="tab-btn" data-filter="hot">
                            <i class="fas fa-fire"></i> Bài Hot
                        </button>
                        <?php if ($userRole === 'Quản lý'): ?>
                        <button class="tab-btn" data-filter="company">Bảng tin công ty</button>
                        <?php endif; ?>
                        <button class="tab-btn" data-filter="forum">Diễn đàn nhân viên</button>
                        <button class="tab-btn" data-filter="qa">Góc hỏi đáp</button>
                    </div>
                </div>

                <!-- Danh sách bài đăng -->
                <div class="posts-list" id="postsList">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Đang tải bài đăng...</p>
                    </div>
                </div>

                <!-- Pagination sẽ được thêm động -->
                <div id="paginationContainer"></div>
            </div>

            <!-- Modal đăng bài -->
            <div class="create-post-modal" id="createPostModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Tạo bài đăng mới</h2>
                        <button class="modal-close" onclick="closeCreatePostModal()">&times;</button>
                    </div>
                    <form id="createPostForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Nội dung bài đăng <span style="color: red;">*</span></label>
                            <textarea class="post-textarea" name="noiDung" id="postContent" 
                                      placeholder="Bạn đang nghĩ gì?" required></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Danh tính</label>
                                <select name="danhTinh" id="postIdentity">
                                    <option value="Hữu danh">Hữu danh</option>
                                    <option value="Ẩn danh">Ẩn danh</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Phân loại</label>
                                <select name="phanLoai" id="postCategory">
                                    <?php if ($userRole === 'Quản lý'): ?>
                                    <option value="Bảng tin công ty">Bảng tin công ty</option>
                                    <?php endif; ?>
                                    <option value="Diễn đàn nhân viên" selected>Diễn đàn nhân viên</option>
                                    <option value="Góc hỏi đáp">Góc hỏi đáp</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Trạng thái</label>
                                <select name="trangThai" id="postStatus">
                                    <option value="Hiển thị">Hiển thị</option>
                                    <option value="Ẩn">Ẩn</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>File đính kèm (ảnh, video, tài liệu)</label>
                            <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Nhấp để chọn file hoặc kéo thả file vào đây</p>
                                <p style="font-size: 12px; margin-top: 5px;">Hỗ trợ: Ảnh, Video, PDF, Word, Excel</p>
                            </div>
                            <input type="file" id="fileInput" name="files[]" class="file-input" 
                                   multiple accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="selected-files" id="selectedFiles"></div>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn-cancel" onclick="closeCreatePostModal()">Hủy</button>
                            <button type="submit" class="btn-submit">Đăng bài</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Post action modal -->
            <div class="post-action-modal" id="postActionModal">
                <div class="post-action-content">
                    <h3>Tuỳ chọn bài đăng</h3>
                    <div class="post-action-buttons">
                        <button class="post-action-toggle" id="postActionToggleBtn" onclick="handlePostActionToggle()"></button>
                        <button class="post-action-delete" onclick="handlePostActionDelete()">
                            <i class="fas fa-trash"></i> Xóa bài đăng
                        </button>
                        <button class="post-action-cancel" onclick="closePostActionModal()">Hủy</button>
                    </div>
                </div>
            </div>

            <!-- Post detail modal -->
            <div class="post-detail-modal" id="postDetailModal">
                <div class="post-detail-content">
                    <div class="post-detail-header">
                        <h3>Chi tiết bài đăng</h3>
                        <button class="modal-close" onclick="closePostDetailModal()">&times;</button>
                    </div>
                    <div id="postDetailBody"></div>
                </div>
            </div>

            <!-- Image modal -->
            <div class="image-modal" id="imageModal">
                <div class="image-modal-content">
                    <button class="image-modal-close" onclick="closeImageModal()">&times;</button>
                    <img id="imageModalImg" src="" alt="">
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // ==================== GLOBAL VARIABLES ====================
        let currentFilter = 'all';
        let currentPage = 1;
        let selectedFiles = [];
        let notificationInterval = null;
        let activePostActionId = null;
        let activePostActionStatus = null;

        // ==================== INIT ====================
        document.addEventListener('DOMContentLoaded', function() {
            loadPosts();
            loadNotifications();
            startNotificationPolling();
            setupEventListeners();
        });

        // ==================== EVENT LISTENERS ====================
        function setupEventListeners() {
            // Tab filters
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFilter = this.dataset.filter;
                    currentPage = 1;
                    loadPosts();
                });
            });

            // File input
            document.getElementById('fileInput').addEventListener('change', handleFileSelect);

            // Form submit
            document.getElementById('createPostForm').addEventListener('submit', handleCreatePost);

            // Close modals on outside click
            document.getElementById('createPostModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCreatePostModal();
                }
            });

            document.getElementById('postActionModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closePostActionModal();
                }
            });

            document.getElementById('postDetailModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closePostDetailModal();
                }
            });

            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImageModal();
                }
            });

            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('notificationsDropdown');
                const bell = document.querySelector('.notification-bell');
                const settingsMenu = document.getElementById('notifSettingsMenu');

                if (dropdown.classList.contains('show') && !bell.contains(event.target)) {
                    dropdown.classList.remove('show');
                }

                if (settingsMenu.classList.contains('show') && !settingsMenu.contains(event.target)) {
                    settingsMenu.classList.remove('show');
                }
            });
        }

        // ==================== MODAL FUNCTIONS ====================
        function openCreatePostModal() {
            document.getElementById('createPostModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeCreatePostModal() {
            document.getElementById('createPostModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('createPostForm').reset();
            selectedFiles = [];
            document.getElementById('selectedFiles').innerHTML = '';
        }

        // ==================== FILE HANDLING ====================
        function handleFileSelect(e) {
            const files = Array.from(e.target.files);
            selectedFiles = [...selectedFiles, ...files];
            displaySelectedFiles();
        }

        function displaySelectedFiles() {
            const container = document.getElementById('selectedFiles');
            container.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const preview = document.createElement('div');
                preview.className = 'file-preview';
                
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    preview.appendChild(img);
                } else {
                    preview.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f0f0f0;">
                        <i class="fas fa-file" style="font-size:36px;color:var(--primary);"></i>
                    </div>`;
                }
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'file-remove';
                removeBtn.innerHTML = '&times;';
                removeBtn.onclick = () => removeFile(index);
                
                preview.appendChild(removeBtn);
                container.appendChild(preview);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displaySelectedFiles();
        }

        // ==================== CREATE POST ====================
        async function handleCreatePost(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'create_post');
            formData.append('noiDung', document.getElementById('postContent').value);
            formData.append('danhTinh', document.getElementById('postIdentity').value);
            formData.append('phanLoai', document.getElementById('postCategory').value);
            formData.append('trangThai', document.getElementById('postStatus').value);
            
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });
            
            try {
                const response = await fetch('bulletin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Đăng bài thành công!');
                    closeCreatePostModal();
                    loadPosts();
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi đăng bài');
            }
        }

        // ==================== LOAD POSTS ====================
        async function loadPosts() {
            const container = document.getElementById('postsList');
            const paginationContainer = document.getElementById('paginationContainer');
            container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Đang tải bài đăng...</p></div>';
            paginationContainer.innerHTML = '';
            
            try {
                const response = await fetch(`bulletin_api.php?action=get_posts&filter=${currentFilter}&page=${currentPage}`);
                const result = await response.json();
                
                if (result.success) {
                    if (result.posts.length === 0) {
                        container.innerHTML = '<div class="loading"><p>Chưa có bài đăng nào</p></div>';
                        return;
                    }
                    
                    container.innerHTML = '';
                    result.posts.forEach(post => {
                        container.appendChild(createPostCard(post));
                    });
                    
                    // Add pagination
                    if (result.totalPages > 1) {
                        displayPagination(result.page, result.totalPages);
                    }
                } else {
                    container.innerHTML = `<div class="loading"><p>Lỗi: ${result.message}</p></div>`;
                }
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="loading"><p>Có lỗi xảy ra khi tải bài đăng</p></div>';
            }
        }

        // ==================== CREATE POST CARD ====================
        function createPostCard(post) {
            const card = document.createElement('div');
            const isHidden = post.TrangThai === 'Ẩn';
            card.className = `post-card${isHidden ? ' hidden-post' : ''}`;
            card.dataset.postId = post.MaBD;
            card.dataset.status = post.TrangThai;
            
            // Category class
            let categoryClass = 'category-forum';
            if (post.PhanLoai === 'Bảng tin công ty') categoryClass = 'category-company';
            if (post.PhanLoai === 'Góc hỏi đáp') categoryClass = 'category-qa';
            
            // Author initials
            const initials = post.TenNguoiDang.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            
            // Time ago
            const timeAgo = getTimeAgo(post.ThoiGianDang);
            
            card.innerHTML = `
                <div class="post-header">
                    <div class="post-author">
                        <div class="author-avatar">${initials}</div>
                        <div class="author-info">
                            <div class="author-name">${escapeHtml(post.TenNguoiDang)}</div>
                            <div class="post-time">${timeAgo}</div>
                            <span class="post-category ${categoryClass}">${post.PhanLoai}</span>
                            ${isHidden ? '<span class="post-status-badge"><i class="fas fa-eye-slash"></i> Đang ẩn</span>' : ''}
                        </div>
                    </div>
                    ${post.CoTheChinhSua ? `
                        <button class="post-menu-btn" onclick="showPostMenu(${post.MaBD}, event)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    ` : ''}
                </div>
                
                <div class="post-content">${escapeHtml(post.NoiDung)}</div>
                
                ${post.FileDinhKem && post.FileDinhKem.length > 0 ? `
                    <div class="post-attachments">
                        ${post.FileDinhKem.map(file => {
                            if (file.type.startsWith('image/')) {
                                return `<div class="attachment-item"><img src="../${file.path}" alt="${escapeHtml(file.name)}" onclick="openImageModal('../${file.path}', '${escapeHtml(file.name)}')"></div>`;
                            } else if (file.type.startsWith('video/')) {
                                return `<div class="attachment-item"><video src="../${file.path}" controls></video></div>`;
                            } else {
                                return `<a href="../${file.path}" class="attachment-file" target="_blank">
                                    <i class="fas fa-file-alt"></i>
                                    <span>${file.name}</span>
                                </a>`;
                            }
                        }).join('')}
                    </div>
                ` : ''}
                
                <div class="post-stats">
                    <span><i class="fas fa-heart"></i> ${post.LuotCamXuc} lượt thích</span>
                    <span><i class="fas fa-comment"></i> ${post.LuotBinhLuan} bình luận</span>
                </div>
                
                <div class="post-actions">
                    <button class="action-btn ${post.DaThichBD ? 'active' : ''}" onclick="toggleReaction('BaiDang', ${post.MaBD})">
                        <i class="fas fa-heart"></i>
                        <span>Thích</span>
                    </button>
                    <button class="action-btn" onclick="toggleComments(${post.MaBD})">
                        <i class="fas fa-comment"></i>
                        <span>Bình luận</span>
                    </button>
                    <button class="action-btn">
                        <i class="fas fa-share"></i>
                        <span>Chia sẻ</span>
                    </button>
                    <button class="action-btn ${post.DangTheoDoi ? 'active' : ''}" onclick="toggleFollow(${post.MaBD})">
                        <i class="fas fa-bell"></i>
                        <span>Theo dõi</span>
                    </button>
                </div>
                
                <div class="comments-section" id="comments-${post.MaBD}" style="display:none;">
                    <div class="comments-list"></div>
                    <div class="comment-input-wrapper">
                        <input type="text" class="comment-input" placeholder="Viết bình luận..." 
                               onkeypress="if(event.key==='Enter') submitComment(${post.MaBD}, this.value, this)">
                        <button class="comment-submit-btn" onclick="submitComment(${post.MaBD}, this.previousElementSibling.value, this.previousElementSibling)">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            `;
            
            return card;
        }

        // ==================== COMMENTS ====================
        async function toggleComments(maBD) {
            const commentsSection = document.getElementById(`comments-${maBD}`);
            
            if (commentsSection.style.display === 'none') {
                commentsSection.style.display = 'block';
                await loadComments(maBD);
            } else {
                commentsSection.style.display = 'none';
            }
        }

        async function loadComments(maBD, rootElement = document) {
            try {
                const response = await fetch(`bulletin_api.php?action=get_post_detail&maBD=${maBD}`);
                const result = await response.json();
                
                if (result.success) {
                    const commentsList = rootElement.querySelector(`#comments-${maBD} .comments-list`);
                    commentsList.innerHTML = '';
                    
                    result.comments.forEach(comment => {
                        commentsList.appendChild(createCommentItem(comment));
                    });
                }
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        }

        function createCommentItem(comment) {
            const item = document.createElement('div');
            item.className = 'comment-item';
            
            const initials = comment.TenNguoiBinhLuan.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const timeAgo = getTimeAgo(comment.ThoiGianBinhLuan);
            
            item.innerHTML = `
                <div class="comment-avatar">${initials}</div>
                <div class="comment-content">
                    <div class="comment-bubble">
                        <div class="comment-author">${escapeHtml(comment.TenNguoiBinhLuan)}</div>
                        <div class="comment-text">${escapeHtml(comment.NoiDung)}</div>
                    </div>
                    <div class="comment-actions">
                        <span class="comment-action ${comment.DaThichBL ? 'liked' : ''}" 
                              onclick="toggleReaction('BinhLuan', ${comment.MaBL})">
                            <i class="fas fa-heart"></i> ${comment.LuotCamXuc > 0 ? comment.LuotCamXuc : 'Thích'}
                        </span>
                        <span class="comment-time">${timeAgo}</span>
                        ${comment.CoTheChinhSua ? `
                            <span class="comment-action" onclick="deleteComment(${comment.MaBL})">
                                <i class="fas fa-trash"></i> Xóa
                            </span>
                        ` : ''}
                    </div>
                </div>
            `;
            
            return item;
        }

        async function submitComment(maBD, noiDung, inputElement) {
            if (!noiDung.trim()) {
                alert('Vui lòng nhập nội dung bình luận');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'create_comment');
            formData.append('maBD', maBD);
            formData.append('noiDung', noiDung);
            
            try {
                const response = await fetch('bulletin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    inputElement.value = '';
                    const rootCard = inputElement.closest('.post-card') || document;
                    await loadComments(maBD, rootCard);
                    await loadPosts(); // Reload to update comment count
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi gửi bình luận');
            }
        }

        async function deleteComment(maBL) {
            if (!confirm('Bạn có chắc chắn muốn xóa bình luận này?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('maBL', maBL);
            
            try {
                const response = await fetch('bulletin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await loadPosts();
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi xóa bình luận');
            }
        }

        // ==================== REACTIONS ====================
        async function toggleReaction(loaiDoiTuong, maDoiTuong) {
            const formData = new FormData();
            formData.append('action', 'toggle_reaction');
            formData.append('loaiDoiTuong', loaiDoiTuong);
            formData.append('maDoiTuong', maDoiTuong);
            formData.append('loaiCamXuc', 'Like');
            
            try {
                const response = await fetch('bulletin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await loadPosts();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // ==================== FOLLOW ====================
        async function toggleFollow(maBD) {
            const formData = new FormData();
            formData.append('action', 'toggle_follow');
            formData.append('maBD', maBD);
            
            try {
                const response = await fetch('bulletin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await loadPosts();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // ==================== POST MENU ====================
        function showPostMenu(maBD, event) {
            event.stopPropagation();
            const card = document.querySelector(`[data-post-id="${maBD}"]`);
            activePostActionId = maBD;
            activePostActionStatus = card ? card.dataset.status : 'Hiển thị';

            const toggleBtn = document.getElementById('postActionToggleBtn');
            toggleBtn.innerHTML = activePostActionStatus === 'Ẩn'
                ? '<i class="fas fa-eye"></i> Hiện bài đăng'
                : '<i class="fas fa-eye-slash"></i> Ẩn bài đăng';

            openPostActionModal();
        }

        function openPostActionModal() {
            document.getElementById('postActionModal').classList.add('show');
        }

        function closePostActionModal() {
            document.getElementById('postActionModal').classList.remove('show');
            activePostActionId = null;
            activePostActionStatus = null;
        }

        async function handlePostActionToggle() {
            if (!activePostActionId) return;
            await togglePostVisibility(activePostActionId);
            closePostActionModal();
        }

        async function handlePostActionDelete() {
            if (!activePostActionId) return;
            await deletePost(activePostActionId);
            closePostActionModal();
        }

        async function togglePostVisibility(maBD) {
            const formData = new FormData();
            formData.append('action', 'toggle_post_visibility');
            formData.append('maBD', maBD);
            
            try {
                const response = await fetch('bulletin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Đã chuyển trạng thái bài đăng sang: ${result.trangThai}`);
                    await loadPosts();
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi cập nhật bài đăng');
            }
        }

        async function deletePost(maBD) {
            const formData = new FormData();
            formData.append('action', 'delete_post');
            formData.append('maBD', maBD);

            try {
                const response = await fetch('bulletin_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Đã xóa bài đăng');
                    await loadPosts();
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi xóa bài đăng');
            }
        }

        // ==================== POST DETAIL ====================
        async function openPostDetail(maBD) {
            try {
                const response = await fetch(`bulletin_api.php?action=get_post_detail&maBD=${maBD}`);
                const result = await response.json();

                if (result.success) {
                    const container = document.getElementById('postDetailBody');
                    container.innerHTML = '';
                    const card = createPostCard(result.post);
                    container.appendChild(card);

                    const commentsSection = card.querySelector(`#comments-${maBD}`);
                    if (commentsSection) {
                        commentsSection.style.display = 'block';
                        await loadComments(maBD, card);
                    }

                    document.getElementById('postDetailModal').classList.add('show');
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Không thể tải chi tiết bài đăng');
            }
        }

        function closePostDetailModal() {
            document.getElementById('postDetailModal').classList.remove('show');
            document.getElementById('postDetailBody').innerHTML = '';
        }

        // ==================== IMAGE MODAL ====================
        function openImageModal(src, altText) {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('imageModalImg');
            img.src = src;
            img.alt = altText || '';
            modal.classList.add('show');
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('imageModalImg');
            img.src = '';
            img.alt = '';
            modal.classList.remove('show');
        }

        // ==================== NOTIFICATIONS ====================
        async function loadNotifications() {
            try {
                const response = await fetch('bulletin_api.php?action=get_notifications');
                const result = await response.json();

                if (result.success) {
                    const list = document.getElementById('notifList');
                    list.innerHTML = '';

                    if (!result.notifications.length) {
                        list.innerHTML = '<div class="notif-empty">Không có thông báo</div>';
                    } else {
                        result.notifications.forEach(notif => {
                            const item = document.createElement('div');
                            item.className = `notif-item ${notif.DaDoc == 0 ? 'unread' : ''}`;
                            item.innerHTML = `
                                <div class="notif-content">${renderNotificationText(notif)}</div>
                                <div class="notif-time">${getTimeAgo(notif.ThoiGian)}</div>
                            `;
                            item.addEventListener('click', () => handleNotificationClick(notif));
                            list.appendChild(item);
                        });
                    }

                    const badge = document.getElementById('notifBadge');
                    if (result.unreadCount > 0) {
                        badge.style.display = 'flex';
                        badge.textContent = result.unreadCount;
                    } else {
                        badge.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function renderNotificationText(notif) {
            switch (notif.LoaiThongBao) {
                case 'BinhLuanBaiCuaBan':
                    return `<strong>${escapeHtml(notif.TenNguoiTacDong || '')}</strong> đã bình luận bài viết của bạn: “${escapeHtml(notif.NoiDungRutGon || '')}”`;
                case 'BinhLuanBaiTheoDoi':
                    return `<strong>${escapeHtml(notif.TenNguoiTacDong || '')}</strong> đã bình luận bài bạn theo dõi: “${escapeHtml(notif.NoiDungRutGon || '')}”`;
                case 'BaiHot':
                    return `Bài viết đang hot: “${escapeHtml(notif.NoiDungRutGon || '')}”`;
                default:
                    return escapeHtml(notif.NoiDungRutGon || 'Bạn có thông báo mới');
            }
        }

        async function handleNotificationClick(notif) {
            if (notif.MaTB) {
                const formData = new FormData();
                formData.append('action', 'mark_notification_read');
                formData.append('maTB', notif.MaTB);
                await fetch('bulletin_api.php', { method: 'POST', body: formData });
            }

            if (notif.MaBD) {
                await openPostDetail(notif.MaBD);
            }

            document.getElementById('notificationsDropdown').classList.remove('show');
            await loadNotifications();
        }

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                loadNotifications();
            }
        }

        function toggleNotifSettings(event) {
            event.stopPropagation();
            document.getElementById('notifSettingsMenu').classList.toggle('show');
        }

        async function markAllAsRead() {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            await fetch('bulletin_api.php', { method: 'POST', body: formData });
            await loadNotifications();
        }

        async function deleteAllNotifications() {
            if (!confirm('Bạn có chắc chắn muốn xóa tất cả thông báo?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_all_notifications');

            await fetch('bulletin_api.php', { method: 'POST', body: formData });
            await loadNotifications();
        }

        function startNotificationPolling() {
            if (notificationInterval) clearInterval(notificationInterval);
            notificationInterval = setInterval(loadNotifications, 30000);
        }

        // ==================== PAGINATION ====================
        function displayPagination(current, totalPages) {
            const container = document.getElementById('paginationContainer');
            container.innerHTML = '';

            const pagination = document.createElement('div');
            pagination.className = 'pagination';

            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `tab-btn ${i === current ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => changePage(i);
                pagination.appendChild(btn);
            }

            container.appendChild(pagination);
        }

        function changePage(page) {
            currentPage = page;
            loadPosts();
        }

        // ==================== UTILITIES ====================
        function getTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diffSeconds = Math.floor((now - time) / 1000);

            if (diffSeconds < 60) return 'Vừa xong';
            const diffMinutes = Math.floor(diffSeconds / 60);
            if (diffMinutes < 60) return `${diffMinutes} phút trước`;
            const diffHours = Math.floor(diffMinutes / 60);
            if (diffHours < 24) return `${diffHours} giờ trước`;
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return `${diffDays} ngày trước`;

            return time.toLocaleDateString('vi-VN');
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>
