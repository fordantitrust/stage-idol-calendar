<?php
/**
 * Admin UI for Event Management
 */
require_once __DIR__ . '/../config.php';
send_security_headers();

// IP Whitelist check - ต้องผ่านก่อน login check
require_allowed_ip();

// Require login
require_login();

// Generate CSRF token for this session
$csrfToken = csrf_token();
$adminUsername = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
$adminUserId = $_SESSION['admin_user_id'] ?? null;
$adminRole = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Idol Stage Timetable</title>
    <link rel="stylesheet" href="<?php echo asset_url('../styles/common.css'); ?>">
    <style>
        /* Admin Color Palette - Professional Blue/Gray Theme */
        :root {
            --admin-primary: #2563eb;        /* blue-600 */
            --admin-primary-dark: #1e40af;   /* blue-800 */
            --admin-primary-light: #dbeafe;  /* blue-100 */
            --admin-accent: #3b82f6;         /* blue-500 */
            --admin-gradient: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            --admin-bg: #f8fafc;             /* slate-50 */
            --admin-surface: #ffffff;
            --admin-border: #cbd5e1;         /* slate-300 */
            --admin-border-light: #e2e8f0;   /* slate-200 */
            --admin-text: #1e293b;           /* slate-800 */
            --admin-text-light: #64748b;     /* slate-500 */
        }

        body {
            background: var(--admin-bg);
        }

        /* Admin specific styles */
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--admin-gradient);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            flex-wrap: wrap;
            gap: 15px;
        }

        .admin-header h1 {
            color: white;
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-header h1::before {
            content: '⚙️';
            font-size: 1.5rem;
        }

        .admin-header a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .admin-header a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        /* Toolbar */
        .admin-toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--admin-surface);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            align-items: center;
        }

        .admin-toolbar input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid var(--admin-border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .admin-toolbar input[type="text"]:focus {
            outline: none;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .admin-toolbar select {
            padding: 10px 15px;
            border: 2px solid var(--admin-border);
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .admin-toolbar select:focus {
            outline: none;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--admin-gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: var(--admin-text);
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        .btn-lg {
            padding: 14px 28px;
            font-size: 1.1rem;
        }

        /* Table */
        .events-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .events-table th,
        .events-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--admin-border-light);
        }

        .events-table th {
            background: var(--admin-gradient);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .events-table tbody tr {
            transition: background-color 0.2s;
        }

        .events-table tbody tr:hover {
            background: var(--admin-primary-light);
        }

        .events-table .actions {
            white-space: nowrap;
        }

        .events-table .actions button {
            margin-right: 5px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
        }

        .pagination-info {
            color: #666;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--admin-border-light);
            background: var(--admin-gradient);
        }

        .modal-header h2 {
            margin: 0;
            color: white;
            font-size: 1.35rem;
            font-weight: 700;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid var(--admin-border);
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 10px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #eee;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: #4caf50;
        }

        .toast.error {
            background: #f44336;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Search wrapper with clear button */
        .search-wrapper {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .search-wrapper input {
            width: 100%;
            padding-right: 35px;
        }

        .clear-search {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #999;
            cursor: pointer;
            display: none;
        }

        .clear-search:hover {
            color: #333;
        }

        .search-wrapper input:not(:placeholder-shown) + .clear-search {
            display: block;
        }

        /* Date inputs */
        .admin-toolbar input[type="date"] {
            padding: 10px 15px;
            border: 2px solid var(--admin-border);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }

        .admin-toolbar input[type="date"]:focus {
            outline: none;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Sortable columns */
        .sortable {
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
        }

        .sortable:hover {
            background: rgba(30, 64, 175, 0.2);
            /* สีน้ำเงินเข้มขึ้นเพื่อให้ตัวหนังสือสีขาวอ่านง่าย */
        }

        .sort-icon::after {
            content: '↕';
            opacity: 0.3;
            margin-left: 5px;
        }

        .sort-icon.asc::after {
            content: '↑';
            opacity: 1;
        }

        .sort-icon.desc::after {
            content: '↓';
            opacity: 1;
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 3000;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--admin-border-light);
            border-top: 4px solid var(--admin-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Info/Copy button */
        .btn-info {
            background: #0ea5e9;
            color: white;
        }

        .btn-info:hover {
            background: #0284c7;
        }

        /* Tabs - Enhanced Design */
        .admin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 25px;
            background: var(--admin-surface);
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: var(--admin-text-light);
            border-radius: 8px;
            transition: all 0.2s;
            position: relative;
        }

        .tab-btn:hover {
            color: var(--admin-primary);
            background: var(--admin-primary-light);
        }

        .tab-btn.active {
            color: white;
            background: var(--admin-gradient);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
        }
        .badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:#f44336;color:#fff;font-size:.7rem;border-radius:9px;margin-left:6px}
        .status-pending{background:#ff9800;color:#fff;padding:3px 8px;border-radius:10px;font-size:.75rem}
        .status-approved{background:#4caf50;color:#fff;padding:3px 8px;border-radius:10px;font-size:.75rem}
        .status-rejected{background:#f44336;color:#fff;padding:3px 8px;border-radius:10px;font-size:.75rem}
        .type-add{color:#4caf50;font-weight:500}
        .type-modify{color:#2196f3;font-weight:500}

        /* Request Detail Styles */
        .req-detail-grid{display:grid;gap:12px}
        .req-detail-row{display:grid;grid-template-columns:120px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #eee}
        .req-detail-row:last-child{border-bottom:none}
        .req-detail-label{font-weight:600;color:#666;font-size:.9rem}
        .req-detail-value{color:#333;word-break:break-word}
        .req-detail-section{background:var(--admin-primary-light);padding:12px;border-radius:8px;margin-top:12px;border:1px solid var(--admin-border-light)}
        .req-detail-section h4{margin:0 0 10px;color:var(--admin-primary-dark);font-size:.95rem;font-weight:700}
        .req-detail-highlight{background:#fff3e0;padding:10px;border-radius:6px;border-left:3px solid #ff9800;margin-bottom:12px}

        /* Comparison View Styles */
        .comparison-container{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px}
        @media(max-width:768px){.comparison-container{grid-template-columns:1fr;gap:12px}}
        .comparison-col{background:#f8f9fa;padding:12px;border-radius:8px}
        .comparison-col.original{border-left:3px solid #9e9e9e}
        .comparison-col.requested{border-left:3px solid #4caf50}
        .comparison-col h4{margin:0 0 10px;font-size:.95rem}
        .comparison-col.original h4{color:#666}
        .comparison-col.requested h4{color:#2e7d32}
        .compare-row{display:grid;grid-template-columns:90px 1fr;gap:6px;padding:6px 0;border-bottom:1px solid #e0e0e0;font-size:.9rem}
        .compare-row:last-child{border-bottom:none}
        .compare-label{font-weight:600;color:#888;font-size:.8rem}
        .compare-value{word-break:break-word}
        .compare-value.changed{background:#fff9c4;padding:2px 6px;border-radius:4px;border:1px solid #fbc02d}
        .compare-value.added{background:#c8e6c9;padding:2px 6px;border-radius:4px;border:1px solid #66bb6a}
        .compare-legend{display:flex;gap:16px;margin:12px 0;font-size:.85rem;flex-wrap:wrap}
        .compare-legend span{display:flex;align-items:center;gap:4px}
        .legend-box{width:14px;height:14px;border-radius:3px;display:inline-block}
        .legend-box.changed{background:#fff9c4;border:1px solid #fbc02d}
        .legend-box.added{background:#c8e6c9;border:1px solid #66bb6a}

        .btn-info{background:#0ea5e9;color:#fff;font-weight:600}
        .btn-info:hover{background:#0284c7;transform:translateY(-1px)}

        /* Bulk Actions Bar */
        .bulk-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #fff9c4 0%, #fff59d 100%);
            border: 2px solid #fbc02d;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(251, 192, 45, 0.2);
        }

        .bulk-selection-info {
            font-size: 1rem;
            font-weight: 600;
            color: #f57f17;
        }

        .bulk-selection-info span {
            font-size: 1.2rem;
            color: #e65100;
        }

        .bulk-actions-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .event-checkbox, #eventSelectAllCheckbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--admin-primary);
        }

        .event-row.selected {
            background: rgba(37, 99, 235, 0.08);
        }

        .bulk-edit-info {
            padding: 12px;
            background: var(--admin-primary-light);
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            color: var(--admin-primary-dark);
            text-align: center;
        }

        .form-warning {
            padding: 12px;
            background: #fff9c4;
            border-left: 4px solid #f57f17;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #e65100;
        }

        .bulk-delete-warning {
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 15px;
        }

        .bulk-delete-warning strong {
            color: #dc2626;
            font-size: 1.3rem;
        }

        .bulk-delete-message {
            text-align: center;
            color: #dc2626;
            font-weight: 600;
        }

        .form-hint {
            display: block;
            margin-top: 5px;
            color: var(--admin-text-light);
            font-size: 0.85rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                padding: 10px;
            }

            .events-table {
                display: block;
                overflow-x: auto;
            }

            .form-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>Admin - Idol Stage Timetable</h1>
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="color: rgba(255, 255, 255, 0.95); font-weight: 600;">สวัสดี, <?php echo htmlspecialchars($adminUsername); ?> <small style="opacity:0.7; font-weight:400;">(<?php echo htmlspecialchars($adminRole); ?>)</small></span>
                <?php if ($adminUserId !== null): ?>
                <a href="#" onclick="showChangePasswordModal(); return false;" style="background: rgba(255, 255, 255, 0.15); color: white;">🔑 Change Password</a>
                <?php endif; ?>
                <a href="../index.php">&larr; กลับหน้าหลัก</a>
                <a href="login.php?logout=1" style="background: rgba(239, 68, 68, 0.2); color: white;">Logout</a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="switchTab('events')">Events</button>
            <button class="tab-btn" onclick="switchTab('requests')">Requests <span class="badge" id="pendingBadge" style="display:none">0</span></button>
            <button class="tab-btn" onclick="switchTab('import')">📤 Import ICS</button>
            <button class="tab-btn" onclick="switchTab('credits')">📋 Credits</button>
            <button class="tab-btn" onclick="switchTab('conventions')">🏟️ Conventions</button>
            <?php if ($adminRole === 'admin'): ?>
            <button class="tab-btn" onclick="switchTab('users')">👤 Users</button>
            <button class="tab-btn" onclick="switchTab('backup')">💾 Backup</button>
            <?php endif; ?>
        </div>

        <!-- Events Section -->
        <div id="eventsSection">
        <!-- Toolbar -->
        <div class="admin-toolbar">
            <select id="eventMetaFilter" onchange="currentPage=1;loadEvents()">
                <option value="">All Conventions</option>
            </select>
            <div class="search-wrapper">
                <input type="text" id="searchInput" placeholder="ค้นหา..." onkeyup="handleSearch(event)">
                <button type="button" class="clear-search" onclick="clearSearch()" title="Clear">&times;</button>
            </div>
            <select id="venueFilter" onchange="loadEvents()">
                <option value="">ทุกเวที</option>
            </select>
            <input type="date" id="dateFrom" onchange="loadEvents()" title="จากวันที่">
            <input type="date" id="dateTo" onchange="loadEvents()" title="ถึงวันที่">
            <button class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
            <select id="perPageSelect" onchange="changePerPage()" title="จำนวนต่อหน้า">
                <option value="20" selected>20 / หน้า</option>
                <option value="50">50 / หน้า</option>
                <option value="100">100 / หน้า</option>
            </select>
            <button class="btn btn-primary" onclick="openAddModal()">+ เพิ่ม Event</button>
        </div>

        <!-- Bulk Actions Toolbar (initially hidden) -->
        <div class="bulk-actions-bar" id="bulkActionsBar" style="display:none">
            <div class="bulk-selection-info">
                <span id="bulkSelectionCount">0</span> รายการที่เลือก
            </div>
            <div class="bulk-actions-buttons">
                <button class="btn btn-secondary btn-sm" onclick="bulkSelectAll()">เลือกทั้งหมด</button>
                <button class="btn btn-secondary btn-sm" onclick="bulkClearSelection()">ยกเลิกทั้งหมด</button>
                <button class="btn btn-warning btn-sm" onclick="openBulkEditModal()">✏️ แก้ไขหลายรายการ</button>
                <button class="btn btn-danger btn-sm" onclick="openBulkDeleteModal()">🗑️ ลบหลายรายการ</button>
            </div>
        </div>

        <!-- Events Table -->
        <table class="events-table">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="eventSelectAllCheckbox" onchange="toggleAllEventCheckboxes()"></th>
                    <th class="sortable" onclick="sortBy('id')"># <span class="sort-icon" data-col="id"></span></th>
                    <th class="sortable" onclick="sortBy('title')">Title <span class="sort-icon" data-col="title"></span></th>
                    <th class="sortable" onclick="sortBy('start')">Date/Time <span class="sort-icon" data-col="start"></span></th>
                    <?php if (VENUE_MODE === 'multi'): ?>
                    <th class="sortable" onclick="sortBy('location')">Venue <span class="sort-icon" data-col="location"></span></th>
                    <?php endif; ?>
                    <th class="sortable" onclick="sortBy('organizer')">Organizer <span class="sort-icon" data-col="organizer"></span></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="eventsTableBody">
                <tr>
                    <td colspan="6" class="loading">Loading...</td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination" id="pagination"></div>
        </div><!-- End eventsSection -->

        <!-- Requests Section -->
        <div id="requestsSection" style="display:none">
            <div class="admin-toolbar">
                <select id="reqEventMetaFilter" onchange="reqPage=1;loadRequests()">
                    <option value="">All Conventions</option>
                </select>
                <select id="reqStatusFilter" onchange="loadRequests()">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" selected>รอดำเนินการ</option>
                    <option value="approved">อนุมัติแล้ว</option>
                    <option value="rejected">ปฏิเสธแล้ว</option>
                </select>
            </div>
            <table class="events-table">
                <thead>
                    <tr><th>#</th><th>ประเภท</th><th>ชื่อ Event</th><th>ผู้แจ้ง</th><th>วันที่</th><th>สถานะ</th><th>Actions</th></tr>
                </thead>
                <tbody id="requestsBody"><tr><td colspan="7">Loading...</td></tr></tbody>
            </table>
            <div class="pagination" id="reqPagination"></div>
        </div>

        <!-- Import ICS Section -->
        <div id="importSection" style="display:none">
            <!-- Upload Area -->
            <div class="upload-area" id="uploadArea">
                <div class="form-group" style="max-width: 400px; margin: 0 auto 20px;">
                    <label for="icsImportEventMeta" style="font-weight: 600; margin-bottom: 6px; display: block;">📦 Import ไปยัง Convention:</label>
                    <select id="icsImportEventMeta" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95em;">
                        <option value="">-- เลือก Convention --</option>
                    </select>
                    <small class="form-hint" style="color: #888; font-size: 0.85em;">เลือก convention ที่ต้องการ import events เข้าไป</small>
                </div>
                <div class="upload-box" id="uploadBox" onclick="document.getElementById('icsFileInput').click()">
                    <input type="file" id="icsFileInput" accept=".ics" style="display:none" onchange="handleFileSelect(event)">
                    <div class="upload-placeholder">
                        <div class="upload-icon">📁</div>
                        <p><strong>คลิกเพื่ออัปโหลด</strong> หรือ ลากไฟล์มาวาง</p>
                        <p class="upload-hint">รองรับเฉพาะไฟล์ .ics (สูงสุด 5MB)</p>
                    </div>
                </div>
                <div id="uploadProgress" style="display:none">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <p id="progressText">กำลังอัปโหลด...</p>
                </div>
            </div>

            <!-- Preview Section (shown after upload) -->
            <div id="previewSection" style="display:none">
                <div class="preview-header">
                    <h3>📋 ตัวอย่าง Events (<span id="previewCount">0</span>)</h3>
                    <div class="preview-stats">
                        <span class="stat-badge" style="background:#e3f2fd;color:#1565c0;">📦 <span id="previewConventionName">-</span></span>
                        <span class="stat-badge stat-new">➕ <span id="statNew">0</span> ใหม่</span>
                        <span class="stat-badge stat-duplicate">⚠️ <span id="statDup">0</span> ซ้ำ</span>
                        <span class="stat-badge stat-error">❌ <span id="statError">0</span> ผิดพลาด</span>
                    </div>
                </div>

                <div class="preview-toolbar">
                    <button class="btn btn-sm btn-secondary" onclick="selectAllEvents()">เลือกทั้งหมด</button>
                    <button class="btn btn-sm btn-secondary" onclick="deselectAllEvents()">ยกเลิกทั้งหมด</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteSelectedEvents()">ลบที่เลือก</button>
                    <button class="btn btn-sm btn-secondary" onclick="resetUpload()">ยกเลิก</button>
                </div>

                <div style="overflow-x:auto">
                    <table class="events-table preview-table">
                        <thead>
                            <tr>
                                <th style="width:40px"><input type="checkbox" id="selectAllCheckbox" onchange="toggleAllCheckboxes()"></th>
                                <th style="width:100px">สถานะ</th>
                                <th>ชื่อ Event</th>
                                <th>วันที่/เวลา</th>
                                <th>สถานที่</th>
                                <th>ผู้จัด</th>
                                <th style="width:140px">การจัดการซ้ำ</th>
                                <th style="width:100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>

                <div class="preview-footer">
                    <button class="btn btn-primary btn-lg" onclick="confirmImport()">
                        ✅ ยืนยันการ Import (<span id="importCount">0</span> events)
                    </button>
                </div>
            </div>

            <!-- Import Summary (shown after import) -->
            <div id="summarySection" style="display:none">
                <div class="summary-box">
                    <h3>📊 สรุปผลการ Import</h3>
                    <div class="summary-stats">
                        <div class="summary-item">
                            <span class="summary-icon">✅</span>
                            <span class="summary-label">เพิ่มใหม่:</span>
                            <span class="summary-value" id="summaryInserted">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-icon">🔄</span>
                            <span class="summary-label">อัปเดต:</span>
                            <span class="summary-value" id="summaryUpdated">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-icon">⏭️</span>
                            <span class="summary-label">ข้าม:</span>
                            <span class="summary-value" id="summarySkipped">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-icon">❌</span>
                            <span class="summary-label">ผิดพลาด:</span>
                            <span class="summary-value" id="summaryErrors">0</span>
                        </div>
                    </div>
                    <div id="errorsList"></div>
                    <button class="btn btn-primary" onclick="resetUpload(); switchTab('events')">
                        ดู Events ที่ Import แล้ว
                    </button>
                </div>
            </div>
        </div>

        <!-- Credits Section -->
        <div id="creditsSection" style="display:none">
            <div class="admin-toolbar">
                <select id="creditsEventMetaFilter" onchange="creditsCurrentPage=1;loadCredits()">
                    <option value="">All Conventions</option>
                </select>
                <div class="search-wrapper">
                    <input type="text" id="creditsSearchInput" placeholder="ค้นหา credits..." onkeyup="handleCreditsSearch(event)">
                    <button type="button" class="clear-search" onclick="clearCreditsSearch()" title="Clear">&times;</button>
                </div>
                <select id="creditsPerPageSelect" onchange="changeCreditsPerPage()">
                    <option value="20" selected>20 / หน้า</option>
                    <option value="50">50 / หน้า</option>
                    <option value="100">100 / หน้า</option>
                </select>
                <button class="btn btn-primary" onclick="openAddCreditModal()">+ เพิ่ม Credit</button>
            </div>

            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar" id="creditsBulkActionsBar" style="display:none">
                <div class="bulk-selection-info">
                    <span id="creditsBulkSelectionCount">0</span> รายการที่เลือก
                </div>
                <div class="bulk-actions-buttons">
                    <button class="btn btn-secondary btn-sm" onclick="creditsBulkSelectAll()">เลือกทั้งหมด</button>
                    <button class="btn btn-secondary btn-sm" onclick="creditsBulkClearSelection()">ยกเลิกทั้งหมด</button>
                    <button class="btn btn-danger btn-sm" onclick="openCreditsBulkDeleteModal()">🗑️ ลบหลายรายการ</button>
                </div>
            </div>

            <!-- Credits Table -->
            <table class="events-table">
                <thead>
                    <tr>
                        <th style="width:40px"><input type="checkbox" id="creditsSelectAllCheckbox" onchange="toggleAllCreditsCheckboxes()"></th>
                        <th class="sortable" onclick="sortCreditsBy('id')"># <span class="sort-icon" data-col="id"></span></th>
                        <th class="sortable" onclick="sortCreditsBy('title')">Title <span class="sort-icon" data-col="title"></span></th>
                        <th>Link</th>
                        <th>Description</th>
                        <th class="sortable" onclick="sortCreditsBy('display_order')">Order <span class="sort-icon" data-col="display_order"></span></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="creditsTableBody">
                    <tr>
                        <td colspan="7" class="loading">Loading...</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination" id="creditsPagination"></div>
        </div>

        <!-- Conventions Section -->
        <div id="conventionsSection" style="display:none">
            <div class="admin-toolbar">
                <div class="search-wrapper">
                    <input type="text" id="conventionsSearchInput" placeholder="ค้นหา conventions..." onkeyup="handleConventionsSearch(event)">
                    <button type="button" class="clear-search" onclick="clearConventionsSearch()" title="Clear">&times;</button>
                </div>
                <button class="btn btn-primary" onclick="openAddConventionModal()">+ เพิ่ม Convention</button>
            </div>

            <!-- Conventions Table -->
            <table class="events-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Venue Mode</th>
                        <th>Active</th>
                        <th>Events</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="conventionsTableBody">
                    <tr>
                        <td colspan="9" class="loading">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Users Section (admin only) -->
        <?php if ($adminRole === 'admin'): ?>
        <div id="usersSection" style="display:none">
            <div class="admin-toolbar">
                <button class="btn btn-primary" onclick="openAddUserModal()">+ Add User</button>
            </div>

            <table class="events-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Display Name</th>
                        <th>Role</th>
                        <th>Active</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="7" class="loading">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Backup Section -->
        <?php if ($adminRole === 'admin'): ?>
        <div id="backupSection" style="display:none">
            <div class="admin-toolbar">
                <button class="btn btn-primary" onclick="createBackup()">💾 สร้าง Backup</button>
                <button class="btn btn-secondary" onclick="openUploadRestoreModal()">📤 Upload & Restore</button>
            </div>

            <table class="events-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="backupTableBody">
                    <tr>
                        <td colspan="5" class="loading">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- User Modal (admin only) -->
    <?php if ($adminRole === 'admin'): ?>
    <div class="modal-overlay" id="userModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h2 id="userModalTitle">Add User</h2>
                <button class="modal-close" onclick="closeUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="userModalError" style="display:none; background:#fef2f2; color:#dc2626; padding:10px; border-radius:6px; margin-bottom:15px; border:1px solid #fecaca;"></div>
                <form id="userForm" onsubmit="submitUserForm(event)">
                    <input type="hidden" id="userId" value="">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="userUsername" required maxlength="50" pattern="[a-zA-Z0-9_\-\.]+">
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" id="userDisplayName" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label id="userPasswordLabel">Password (min 8 characters)</label>
                        <input type="password" id="userPassword" minlength="8">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select id="userRole">
                            <option value="admin">Admin - Full access</option>
                            <option value="agent">Agent - Events management only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="userIsActive" checked> Active
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="userSubmitBtn">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Confirmation Modal -->
    <div class="modal-overlay" id="deleteUserModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Delete User</h2>
                <button class="modal-close" onclick="closeDeleteUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user?</p>
                <p><strong id="deleteUserName"></strong></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteUserModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteUser()">Delete</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Backup Restore Confirmation Modal -->
    <?php if ($adminRole === 'admin'): ?>
    <div class="modal-overlay" id="restoreModal">
        <div class="modal">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <h2>⚠️ Restore Database</h2>
                <button class="modal-close" onclick="closeRestoreModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color: #dc2626; font-weight: bold; margin-bottom: 10px;">คำเตือน: การ Restore จะแทนที่ข้อมูลปัจจุบันทั้งหมด!</p>
                <p>ระบบจะสร้าง auto-backup ก่อน restore อัตโนมัติ</p>
                <p style="margin-top: 10px;">Restore จากไฟล์: <strong id="restoreFilename"></strong></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeRestoreModal()">ยกเลิก</button>
                <button class="btn btn-danger" onclick="confirmRestore()">Restore</button>
            </div>
        </div>
    </div>

    <!-- Backup Upload Restore Modal -->
    <div class="modal-overlay" id="uploadRestoreModal">
        <div class="modal">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <h2>📤 Upload & Restore</h2>
                <button class="modal-close" onclick="closeUploadRestoreModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color: #dc2626; font-weight: bold; margin-bottom: 10px;">คำเตือน: การ Restore จะแทนที่ข้อมูลปัจจุบันทั้งหมด!</p>
                <p>ระบบจะสร้าง auto-backup ก่อน restore อัตโนมัติ</p>
                <div class="form-group" style="margin-top: 15px;">
                    <label for="backupFileInput">เลือกไฟล์ .db</label>
                    <input type="file" id="backupFileInput" accept=".db">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeUploadRestoreModal()">ยกเลิก</button>
                <button class="btn btn-danger" onclick="confirmUploadRestore()">Upload & Restore</button>
            </div>
        </div>
    </div>

    <!-- Backup Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteBackupModal">
        <div class="modal">
            <div class="modal-header">
                <h2>ลบ Backup</h2>
                <button class="modal-close" onclick="closeDeleteBackupModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>ต้องการลบไฟล์ backup นี้?</p>
                <p><strong id="deleteBackupFilename"></strong></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteBackupModal()">ยกเลิก</button>
                <button class="btn btn-danger" onclick="confirmDeleteBackup()">ลบ</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitle">เพิ่ม Event</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="eventForm" onsubmit="saveEvent(event)">
                    <input type="hidden" id="eventId">

                    <div class="form-group">
                        <label for="eventConvention">Convention</label>
                        <select id="eventConvention">
                            <option value="">-- ไม่ระบุ --</option>
                            <!-- populated from event_meta_list -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">ชื่อ Event *</label>
                        <input type="text" id="title" required>
                    </div>

                    <div class="form-group">
                        <label for="organizer">Organizer</label>
                        <input type="text" id="organizer">
                    </div>

                    <div class="form-group">
                        <label for="location">เวที</label>
                        <input type="text" id="location" list="venuesListMain">
                        <datalist id="venuesListMain">
                            <!-- Venues loaded dynamically -->
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label for="eventDate">วันที่ *</label>
                        <input type="date" id="eventDate" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="startTime">เวลาเริ่ม *</label>
                            <input type="time" id="startTime" required>
                        </div>
                        <div class="form-group">
                            <label for="endTime">เวลาสิ้นสุด *</label>
                            <input type="time" id="endTime" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="categories">Categories</label>
                        <input type="text" id="categories" placeholder="แยกด้วย comma">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">ยกเลิก</button>
                <button type="submit" form="eventForm" class="btn btn-primary">บันทึก</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h2>ยืนยันการลบ</h2>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบ event "<span id="deleteEventTitle"></span>" หรือไม่?</p>
                <input type="hidden" id="deleteEventId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">ยกเลิก</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">ลบ</button>
            </div>
        </div>
    </div>

    <!-- Bulk Edit Modal -->
    <div class="modal-overlay" id="bulkEditModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h2>✏️ แก้ไขหลายรายการ</h2>
                <button class="modal-close" onclick="closeBulkEditModal()">&times;</button>
            </div>
            <form id="bulkEditForm" onsubmit="submitBulkEdit(event)">
                <div class="modal-body">
                    <div class="bulk-edit-info">
                        กำลังแก้ไข <strong><span id="bulkEditCount">0</span></strong> events
                    </div>

                    <div class="form-group">
                        <label for="bulkEditVenue">Venue (สถานที่)</label>
                        <input type="text" id="bulkEditVenue" class="form-control"
                               list="venuesList"
                               placeholder="-- ไม่เปลี่ยนแปลง --">
                        <datalist id="venuesList">
                            <!-- Venues loaded dynamically -->
                        </datalist>
                        <small class="form-hint">เลือกจาก dropdown หรือพิมพ์ชื่อเวทีใหม่</small>
                    </div>

                    <div class="form-group">
                        <label for="bulkEditOrganizer">Organizer (ผู้จัด)</label>
                        <input type="text" id="bulkEditOrganizer" class="form-control"
                               placeholder="-- ไม่เปลี่ยนแปลง --">
                        <small class="form-hint">กรอกเพื่ออัปเดต organizer ของ events ทั้งหมดที่เลือก</small>
                    </div>

                    <div class="form-group">
                        <label for="bulkEditCategories">Categories</label>
                        <input type="text" id="bulkEditCategories" class="form-control"
                               placeholder="-- ไม่เปลี่ยนแปลง --">
                        <small class="form-hint">กรอกเพื่ออัปเดต categories ของ events ทั้งหมดที่เลือก</small>
                    </div>

                    <div class="form-warning">
                        ⚠️ การแก้ไขจะเปลี่ยนค่าของ events ทั้งหมดที่เลือกทันที
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeBulkEditModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal-overlay" id="bulkDeleteModal">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h2>⚠️ ยืนยันการลบ</h2>
                <button class="modal-close" onclick="closeBulkDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="bulk-delete-warning">
                    คุณกำลังจะลบ <strong><span id="bulkDeleteCount">0</span></strong> events
                </p>
                <p class="bulk-delete-message">
                    การกระทำนี้ไม่สามารถย้อนกลับได้ คุณแน่ใจหรือไม่?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkDeleteModal()">ยกเลิก</button>
                <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">ลบทั้งหมด</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Credit Modal -->
    <div class="modal-overlay" id="creditModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="creditModalTitle">เพิ่ม Credit</h2>
                <button class="modal-close" onclick="closeCreditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="creditForm" onsubmit="saveCredit(event)">
                    <input type="hidden" id="creditId">

                    <div class="form-group">
                        <label for="creditTitle">Title *</label>
                        <input type="text" id="creditTitle" required maxlength="200" placeholder="ชื่อ/หัวข้อ">
                    </div>

                    <div class="form-group">
                        <label for="creditLink">Link (URL)</label>
                        <input type="url" id="creditLink" maxlength="500" placeholder="https://example.com">
                        <small class="form-hint">ลิงก์ไปยังเว็บไซต์/โปรไฟล์</small>
                    </div>

                    <div class="form-group">
                        <label for="creditDescription">Description</label>
                        <textarea id="creditDescription" rows="3" maxlength="1000" placeholder="คำอธิบายหรือข้อมูลเพิ่มเติม"></textarea>
                        <small class="form-hint">รายละเอียด ขอบคุณสำหรับอะไร</small>
                    </div>

                    <div class="form-group">
                        <label for="creditDisplayOrder">Display Order</label>
                        <input type="number" id="creditDisplayOrder" min="0" value="0" placeholder="0">
                        <small class="form-hint">ลำดับการแสดงผล (เลขน้อยขึ้นก่อน)</small>
                    </div>

                    <div class="form-group">
                        <label for="creditEventMetaId">Convention</label>
                        <select id="creditEventMetaId">
                            <option value="">-- ทุก Convention (Global) --</option>
                        </select>
                        <small class="form-hint">เลือก convention ที่ credit นี้สังกัด (ว่าง = แสดงทุก convention)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreditModal()">ยกเลิก</button>
                <button type="submit" form="creditForm" class="btn btn-primary">บันทึก</button>
            </div>
        </div>
    </div>

    <!-- Delete Credit Modal -->
    <div class="modal-overlay" id="deleteCreditModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h2>ยืนยันการลบ</h2>
                <button class="modal-close" onclick="closeDeleteCreditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบ "<span id="deleteCreditTitle"></span>" หรือไม่?</p>
                <input type="hidden" id="deleteCreditId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteCreditModal()">ยกเลิก</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteCredit()">ลบ</button>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Credits Modal -->
    <div class="modal-overlay" id="creditsBulkDeleteModal">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h2>⚠️ ยืนยันการลบ</h2>
                <button class="modal-close" onclick="closeCreditsBulkDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="bulk-delete-warning">
                    คุณกำลังจะลบ <strong><span id="creditsBulkDeleteCount">0</span></strong> credits
                </p>
                <p class="bulk-delete-message">
                    การกระทำนี้ไม่สามารถย้อนกลับได้ คุณแน่ใจหรือไม่?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreditsBulkDeleteModal()">ยกเลิก</button>
                <button type="button" class="btn btn-danger" onclick="confirmCreditsBulkDelete()">ลบทั้งหมด</button>
            </div>
        </div>
    </div>

    <!-- Request Detail Modal -->
    <div class="modal-overlay" id="requestDetailModal">
        <div class="modal" style="max-width: 650px;">
            <div class="modal-header">
                <h2>📋 รายละเอียดคำขอ</h2>
                <button class="modal-close" onclick="closeRequestDetailModal()">&times;</button>
            </div>
            <div class="modal-body" id="requestDetailBody" style="max-height: 70vh; overflow-y: auto;">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer" id="requestDetailFooter">
                <button type="button" class="btn btn-secondary" onclick="closeRequestDetailModal()">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Convention Modal -->
    <div class="modal-overlay" id="conventionModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="conventionModalTitle">เพิ่ม Convention</h2>
                <button class="modal-close" onclick="closeConventionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="conventionForm" onsubmit="saveConvention(event)">
                    <input type="hidden" id="conventionId">

                    <div class="form-group">
                        <label for="conventionName">Name *</label>
                        <input type="text" id="conventionName" required maxlength="200" placeholder="ชื่อ Convention">
                    </div>

                    <div class="form-group">
                        <label for="conventionSlug">Slug *</label>
                        <input type="text" id="conventionSlug" required maxlength="100" placeholder="convention-slug" pattern="[a-z0-9\-]+">
                        <small class="form-hint">ตัวอักษรพิมพ์เล็ก ตัวเลข และ - เท่านั้น</small>
                    </div>

                    <div class="form-group">
                        <label for="conventionDescription">Description</label>
                        <textarea id="conventionDescription" rows="3" maxlength="1000" placeholder="รายละเอียด"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="conventionStartDate">Start Date *</label>
                            <input type="date" id="conventionStartDate" required>
                        </div>
                        <div class="form-group">
                            <label for="conventionEndDate">End Date *</label>
                            <input type="date" id="conventionEndDate" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="conventionVenueMode">Venue Mode</label>
                            <select id="conventionVenueMode">
                                <option value="multi">Multi</option>
                                <option value="single">Single</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="conventionIsActive">Active</label>
                            <div style="padding-top: 8px;">
                                <input type="checkbox" id="conventionIsActive" checked style="width: auto; margin-right: 8px;">
                                <label for="conventionIsActive" style="display: inline; font-weight: normal;">เปิดใช้งาน</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeConventionModal()">ยกเลิก</button>
                <button type="submit" form="conventionForm" class="btn btn-primary">บันทึก</button>
            </div>
        </div>
    </div>

    <!-- Delete Convention Modal -->
    <div class="modal-overlay" id="deleteConventionModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h2>ยืนยันการลบ</h2>
                <button class="modal-close" onclick="closeDeleteConventionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบ convention "<span id="deleteConventionName"></span>" หรือไม่?</p>
                <p class="form-warning">⚠️ Events ที่อยู่ใน convention นี้จะไม่ถูกลบ แต่จะไม่มี convention อ้างอิง</p>
                <input type="hidden" id="deleteConventionId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteConventionModal()">ยกเลิก</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteConvention()">ลบ</button>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <?php if ($adminUserId !== null): ?>
    <div class="modal-overlay" id="changePasswordModal">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h2>🔑 Change Password</h2>
                <button class="modal-close" onclick="closeChangePasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="changePasswordError" style="display:none; background:#fef2f2; color:#dc2626; padding:10px; border-radius:6px; margin-bottom:15px; border:1px solid #fecaca;"></div>
                <div id="changePasswordSuccess" style="display:none; background:#f0fdf4; color:#16a34a; padding:10px; border-radius:6px; margin-bottom:15px; border:1px solid #bbf7d0;"></div>
                <form id="changePasswordForm" onsubmit="submitChangePassword(event)">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" id="cpCurrentPassword" required>
                    </div>
                    <div class="form-group">
                        <label>New Password (min 8 characters)</label>
                        <input type="password" id="cpNewPassword" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" id="cpConfirmPassword" required minlength="8">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeChangePasswordModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <script>
        // CSRF Token for secure requests
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';

        // Configuration
        const VENUE_MODE = '<?php echo VENUE_MODE; ?>';
        const ADMIN_ROLE = '<?php echo $adminRole; ?>';

        // State
        let currentPage = 1;
        let perPage = 20;
        let venues = [];
        let searchTimeout = null;
        let sortColumn = 'start';
        let sortDirection = 'desc';
        let formChanged = false;
        let originalFormData = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadEventMetaOptions();
            loadVenues();
            loadEvents();
            loadPendingCount();
            setupFormChangeTracking();
            setupKeyboardShortcuts();
        });

        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
            document.getElementById('eventsSection').style.display = tab === 'events' ? 'block' : 'none';
            document.getElementById('requestsSection').style.display = tab === 'requests' ? 'block' : 'none';
            document.getElementById('importSection').style.display = tab === 'import' ? 'block' : 'none';
            document.getElementById('creditsSection').style.display = tab === 'credits' ? 'block' : 'none';
            document.getElementById('conventionsSection').style.display = tab === 'conventions' ? 'block' : 'none';
            const usersEl = document.getElementById('usersSection');
            if (usersEl) usersEl.style.display = tab === 'users' ? 'block' : 'none';
            const backupEl = document.getElementById('backupSection');
            if (backupEl) backupEl.style.display = tab === 'backup' ? 'block' : 'none';
            if (tab === 'requests') loadRequests();
            if (tab === 'credits') loadCredits();
            if (tab === 'conventions') loadConventions();
            if (tab === 'users' && ADMIN_ROLE === 'admin') loadUsers();
            if (tab === 'backup' && ADMIN_ROLE === 'admin') loadBackups();
        }

        // Pending count
        async function loadPendingCount() {
            try {
                const res = await fetch('api.php?action=pending_count');
                const result = await res.json();
                const badge = document.getElementById('pendingBadge');
                if (result.success && result.data.count > 0) {
                    badge.textContent = result.data.count;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            } catch (e) {}
        }

        // Requests
        let reqPage = 1;
        let requestsData = []; // Store requests for detail view

        async function loadRequests() {
            const status = document.getElementById('reqStatusFilter').value;
            const eventMetaId = document.getElementById('reqEventMetaFilter').value;
            showLoading();
            try {
                let reqUrl = `api.php?action=requests&page=${reqPage}`;
                if (status) reqUrl += `&status=${status}`;
                if (eventMetaId) reqUrl += `&event_meta_id=${encodeURIComponent(eventMetaId)}`;
                const res = await fetch(reqUrl);
                const result = await res.json();
                if (result.success) {
                    requestsData = result.data.requests;
                    renderRequests(result.data.requests);
                    renderReqPagination(result.data.pagination);
                }
            } catch (e) {}
            hideLoading();
        }

        function renderRequests(reqs) {
            const tbody = document.getElementById('requestsBody');
            if (!reqs.length) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999">ไม่มี requests</td></tr>'; return; }
            tbody.innerHTML = reqs.map(r => {
                const d = new Date(r.created_at).toLocaleDateString('th-TH');
                const type = r.type === 'add' ? '<span class="type-add">➕ เพิ่ม</span>' : '<span class="type-modify">✏️ แก้ไข</span>';
                const status = r.status === 'pending' ? '<span class="status-pending">รอ</span>' : r.status === 'approved' ? '<span class="status-approved">อนุมัติ</span>' : '<span class="status-rejected">ปฏิเสธ</span>';
                const viewBtn = `<button class="btn btn-sm btn-info" onclick="viewRequestDetail(${r.id})">👁️ ดู</button>`;
                const actions = r.status === 'pending' ? `${viewBtn} <button class="btn btn-sm btn-primary" onclick="approveReq(${r.id})">อนุมัติ</button> <button class="btn btn-sm btn-danger" onclick="rejectReq(${r.id})">ปฏิเสธ</button>` : viewBtn;
                return `<tr><td>${r.id}</td><td>${type}</td><td>${escapeHtml(r.title)}</td><td>${escapeHtml(r.requester_name)}</td><td>${d}</td><td>${status}</td><td class="actions">${actions}</td></tr>`;
            }).join('');
        }

        function viewRequestDetail(id) {
            const r = requestsData.find(x => String(x.id) === String(id));
            if (!r) { console.error('Request not found:', id, requestsData); return; }

            const formatDate = (d) => d ? new Date(d).toLocaleString('th-TH') : '-';
            const typeText = r.type === 'add' ? '➕ เพิ่ม Event ใหม่' : '✏️ แก้ไข Event ที่มีอยู่';
            const statusText = r.status === 'pending' ? '🟡 รอดำเนินการ' : r.status === 'approved' ? '✅ อนุมัติแล้ว' : '❌ ปฏิเสธแล้ว';

            // ฟังก์ชันเปรียบเทียบค่า
            const isChanged = (oldVal, newVal) => {
                const o = (oldVal || '').toString().trim();
                const n = (newVal || '').toString().trim();
                return o !== n;
            };

            let html = `
                <div class="req-detail-highlight">
                    <strong>ประเภท:</strong> ${typeText}<br>
                    <strong>สถานะ:</strong> ${statusText}
                    ${r.event_id ? `<br><strong>Event ID อ้างอิง:</strong> ${r.event_id}` : ''}
                </div>
            `;

            // ถ้าเป็น modify และมีข้อมูล event เดิม ให้แสดงเปรียบเทียบ
            if (r.type === 'modify' && r.original_event) {
                const orig = r.original_event;
                const fields = [
                    { label: 'ชื่อ Event', key: 'title', format: 'text' },
                    { label: 'Organizer', key: 'organizer', format: 'text' },
                    { label: 'เวที', key: 'location', format: 'text' },
                    { label: 'เวลาเริ่ม', key: 'start', format: 'date' },
                    { label: 'เวลาสิ้นสุด', key: 'end', format: 'date' },
                    { label: 'Categories', key: 'categories', format: 'text' },
                    { label: 'รายละเอียด', key: 'description', format: 'text' }
                ];

                const formatVal = (val, format) => format === 'date' ? formatDate(val) : escapeHtml(val || '-');

                html += `
                    <div class="compare-legend">
                        <span><span class="legend-box changed"></span> แก้ไข</span>
                        <span><span class="legend-box added"></span> เพิ่มใหม่</span>
                    </div>
                    <div class="comparison-container">
                        <div class="comparison-col original">
                            <h4>📄 ข้อมูลเดิม</h4>
                            ${fields.map(f => `
                                <div class="compare-row">
                                    <div class="compare-label">${f.label}</div>
                                    <div class="compare-value">${formatVal(orig[f.key], f.format)}</div>
                                </div>
                            `).join('')}
                        </div>
                        <div class="comparison-col requested">
                            <h4>📝 ข้อมูลที่ขอแก้ไข</h4>
                            ${fields.map(f => {
                                const oldVal = f.format === 'date' ? formatDate(orig[f.key]) : (orig[f.key] || '');
                                const newVal = f.format === 'date' ? formatDate(r[f.key]) : (r[f.key] || '');
                                const changed = isChanged(oldVal, newVal);
                                const wasEmpty = !(orig[f.key] || '').toString().trim();
                                const cssClass = changed ? (wasEmpty ? 'added' : 'changed') : '';
                                return `
                                    <div class="compare-row">
                                        <div class="compare-label">${f.label}</div>
                                        <div class="compare-value ${cssClass}">${formatVal(r[f.key], f.format)}</div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            } else {
                // แสดงแบบปกติสำหรับ add request หรือ modify ที่ไม่มี original_event
                html += `
                    <div class="req-detail-section">
                        <h4>📅 ข้อมูล Event ที่ขอ</h4>
                        <div class="req-detail-grid">
                            <div class="req-detail-row"><div class="req-detail-label">ชื่อ Event</div><div class="req-detail-value">${escapeHtml(r.title || '-')}</div></div>
                            <div class="req-detail-row"><div class="req-detail-label">Organizer</div><div class="req-detail-value">${escapeHtml(r.organizer || '-')}</div></div>
                            <div class="req-detail-row"><div class="req-detail-label">เวที</div><div class="req-detail-value">${escapeHtml(r.location || '-')}</div></div>
                            <div class="req-detail-row"><div class="req-detail-label">วัน-เวลาเริ่ม</div><div class="req-detail-value">${formatDate(r.start)}</div></div>
                            <div class="req-detail-row"><div class="req-detail-label">วัน-เวลาสิ้นสุด</div><div class="req-detail-value">${formatDate(r.end)}</div></div>
                            <div class="req-detail-row"><div class="req-detail-label">Categories</div><div class="req-detail-value">${escapeHtml(r.categories || '-')}</div></div>
                            <div class="req-detail-row"><div class="req-detail-label">รายละเอียด</div><div class="req-detail-value">${escapeHtml(r.description || '-')}</div></div>
                        </div>
                    </div>
                `;
            }

            html += `
                <div class="req-detail-section">
                    <h4>👤 ข้อมูลผู้แจ้ง</h4>
                    <div class="req-detail-grid">
                        <div class="req-detail-row"><div class="req-detail-label">ชื่อผู้แจ้ง</div><div class="req-detail-value">${escapeHtml(r.requester_name || '-')}</div></div>
                        <div class="req-detail-row"><div class="req-detail-label">Email</div><div class="req-detail-value">${escapeHtml(r.requester_email || '-')}</div></div>
                        <div class="req-detail-row"><div class="req-detail-label">หมายเหตุ</div><div class="req-detail-value">${escapeHtml(r.requester_note || '-')}</div></div>
                    </div>
                </div>
                <div class="req-detail-section" style="background:#f0f0f0;">
                    <h4>📝 ข้อมูลระบบ</h4>
                    <div class="req-detail-grid">
                        <div class="req-detail-row"><div class="req-detail-label">วันที่ส่งคำขอ</div><div class="req-detail-value">${formatDate(r.created_at)}</div></div>
                        ${r.reviewed_at ? `<div class="req-detail-row"><div class="req-detail-label">วันที่ตรวจสอบ</div><div class="req-detail-value">${formatDate(r.reviewed_at)}</div></div>` : ''}
                        ${r.reviewed_by ? `<div class="req-detail-row"><div class="req-detail-label">ตรวจสอบโดย</div><div class="req-detail-value">${escapeHtml(r.reviewed_by)}</div></div>` : ''}
                        ${r.admin_note ? `<div class="req-detail-row"><div class="req-detail-label">หมายเหตุ Admin</div><div class="req-detail-value">${escapeHtml(r.admin_note)}</div></div>` : ''}
                    </div>
                </div>
            `;

            document.getElementById('requestDetailBody').innerHTML = html;

            // Update footer with action buttons if pending
            let footerHtml = '<button type="button" class="btn btn-secondary" onclick="closeRequestDetailModal()">ปิด</button>';
            if (r.status === 'pending') {
                footerHtml = `
                    <button type="button" class="btn btn-secondary" onclick="closeRequestDetailModal()">ปิด</button>
                    <button type="button" class="btn btn-danger" onclick="closeRequestDetailModal();rejectReq(${r.id})">❌ ปฏิเสธ</button>
                    <button type="button" class="btn btn-primary" onclick="closeRequestDetailModal();approveReq(${r.id})">✅ อนุมัติ</button>
                `;
            }
            document.getElementById('requestDetailFooter').innerHTML = footerHtml;

            document.getElementById('requestDetailModal').classList.add('active');
        }

        function closeRequestDetailModal() {
            document.getElementById('requestDetailModal').classList.remove('active');
        }

        function renderReqPagination(p) {
            const el = document.getElementById('reqPagination');
            if (p.totalPages <= 1) { el.innerHTML = ''; return; }
            el.innerHTML = (p.page > 1 ? `<button onclick="reqPage=${p.page-1};loadRequests()">«</button>` : '') +
                `<span class="page-info">${p.page}/${p.totalPages}</span>` +
                (p.page < p.totalPages ? `<button onclick="reqPage=${p.page+1};loadRequests()">»</button>` : '');
        }

        async function approveReq(id) {
            if (!confirm('อนุมัติ?')) return;
            showLoading();
            try {
                const res = await fetch(`api.php?action=request_approve&id=${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }, body: '{}' });
                const result = await res.json();
                if (result.success) { showToast('อนุมัติแล้ว', 'success'); loadRequests(); loadPendingCount(); }
                else showToast(result.message, 'error');
            } catch (e) { showToast('Error', 'error'); }
            hideLoading();
        }

        async function rejectReq(id) {
            if (!confirm('ปฏิเสธ?')) return;
            showLoading();
            try {
                const res = await fetch(`api.php?action=request_reject&id=${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }, body: '{}' });
                const result = await res.json();
                if (result.success) { showToast('ปฏิเสธแล้ว', 'success'); loadRequests(); loadPendingCount(); }
                else showToast(result.message, 'error');
            } catch (e) { showToast('Error', 'error'); }
            hideLoading();
        }

        // Setup keyboard shortcuts (ESC to close modal)
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (document.getElementById('requestDetailModal').classList.contains('active')) {
                        closeRequestDetailModal();
                    } else if (document.getElementById('deleteModal').classList.contains('active')) {
                        closeDeleteModal();
                    } else if (document.getElementById('eventModal').classList.contains('active')) {
                        closeModal();
                    }
                }
            });
        }

        // Setup form change tracking
        function setupFormChangeTracking() {
            const form = document.getElementById('eventForm');
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('change', () => { formChanged = true; });
                input.addEventListener('input', () => { formChanged = true; });
            });
        }

        // Show/hide loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Clear search
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            currentPage = 1;
            loadEvents();
        }

        // Clear all filters
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('venueFilter').value = '';
            document.getElementById('eventMetaFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            currentPage = 1;
            loadEvents();
        }

        // Sort by column
        function sortBy(column) {
            if (sortColumn === column) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = column;
                sortDirection = 'asc';
            }
            updateSortIcons();
            loadEvents();
        }

        // Update sort icons
        function updateSortIcons() {
            document.querySelectorAll('.sort-icon').forEach(icon => {
                icon.classList.remove('asc', 'desc');
                if (icon.dataset.col === sortColumn) {
                    icon.classList.add(sortDirection);
                }
            });
        }

        // Load venues for filters and form
        async function loadVenues() {
            try {
                const response = await fetch('api.php?action=venues');
                const result = await response.json();

                if (result.success) {
                    venues = result.data;

                    // Update filter dropdown
                    const filterSelect = document.getElementById('venueFilter');
                    venues.forEach(venue => {
                        const option = document.createElement('option');
                        option.value = venue;
                        option.textContent = venue;
                        filterSelect.appendChild(option);
                    });

                    // Update form datalist
                    const datalist = document.getElementById('venuesListMain');
                    datalist.innerHTML = ''; // Clear existing options
                    venues.forEach(venue => {
                        const option = document.createElement('option');
                        option.value = venue;
                        datalist.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load venues:', error);
            }
        }

        // Load events
        async function loadEvents() {
            const search = document.getElementById('searchInput').value;
            const venue = document.getElementById('venueFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const eventMetaId = document.getElementById('eventMetaFilter').value;

            let url = `api.php?action=list&page=${currentPage}&limit=${perPage}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (venue) url += `&venue=${encodeURIComponent(venue)}`;
            if (dateFrom) url += `&date_from=${encodeURIComponent(dateFrom)}`;
            if (dateTo) url += `&date_to=${encodeURIComponent(dateTo)}`;
            if (eventMetaId) url += `&event_meta_id=${encodeURIComponent(eventMetaId)}`;
            url += `&sort=${sortColumn}&order=${sortDirection}`;

            showLoading();
            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    renderEvents(result.data.events);
                    renderPagination(result.data.pagination);
                    attachCheckboxListeners();
                    updateBulkActionsBar();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to load events:', error);
                showToast('Failed to load events', 'error');
            } finally {
                hideLoading();
            }
        }

        // Render events table
        function renderEvents(events) {
            const tbody = document.getElementById('eventsTableBody');

            if (events.length === 0) {
                const colspan = VENUE_MODE === 'multi' ? 7 : 6;
                tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty-state">ไม่พบ events</td></tr>`;
                return;
            }

            tbody.innerHTML = events.map((event, index) => {
                const startDate = new Date(event.start);
                const endDate = new Date(event.end);

                const dateStr = startDate.toLocaleDateString('th-TH', {
                    day: '2-digit',
                    month: '2-digit',
                    year: '2-digit'
                });

                const startTime = startDate.toLocaleTimeString('th-TH', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const endTime = endDate.toLocaleTimeString('th-TH', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                return `
                    <tr class="event-row" data-event-id="${event.id}">
                        <td><input type="checkbox" class="event-checkbox" data-event-id="${event.id}"></td>
                        <td>${event.id}</td>
                        <td>${escapeHtml(event.title)}</td>
                        <td>${dateStr}<br>${startTime} - ${endTime}</td>
                        ${VENUE_MODE === 'multi' ? `<td>${escapeHtml(event.location || '-')}</td>` : ''}
                        <td>${escapeHtml(event.organizer || '-')}</td>
                        <td class="actions">
                            <button class="btn btn-secondary btn-sm" onclick="openEditModal(${event.id})">แก้ไข</button>
                            <button class="btn btn-info btn-sm" onclick="duplicateEvent(${event.id})" title="Duplicate">Copy</button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteModal(${event.id}, '${escapeHtml(event.title)}')">ลบ</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Render pagination
        function renderPagination(pagination) {
            const container = document.getElementById('pagination');
            const { page, totalPages, total } = pagination;

            if (totalPages <= 1) {
                container.innerHTML = `<span class="pagination-info">ทั้งหมด ${total} รายการ</span>`;
                return;
            }

            container.innerHTML = `
                <button class="btn btn-secondary btn-sm" onclick="goToPage(${page - 1})" ${page <= 1 ? 'disabled' : ''}>
                    &laquo; ก่อนหน้า
                </button>
                <span class="pagination-info">หน้า ${page} / ${totalPages} (ทั้งหมด ${total} รายการ)</span>
                <button class="btn btn-secondary btn-sm" onclick="goToPage(${page + 1})" ${page >= totalPages ? 'disabled' : ''}>
                    ถัดไป &raquo;
                </button>
            `;
        }

        // Go to page
        function goToPage(page) {
            currentPage = page;
            loadEvents();
        }

        // Change per page
        function changePerPage() {
            perPage = parseInt(document.getElementById('perPageSelect').value);
            currentPage = 1; // Reset to first page
            loadEvents();
        }

        // ========================================
        // Bulk Operations - Selection Management
        // ========================================

        // Toggle all event checkboxes
        function toggleAllEventCheckboxes() {
            const masterCheckbox = document.getElementById('eventSelectAllCheckbox');
            const checkboxes = document.querySelectorAll('.event-checkbox');
            checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
            updateBulkActionsBar();
            updateRowSelection();
        }

        function bulkSelectAll() {
            document.getElementById('eventSelectAllCheckbox').checked = true;
            toggleAllEventCheckboxes();
        }

        function bulkClearSelection() {
            document.getElementById('eventSelectAllCheckbox').checked = false;
            toggleAllEventCheckboxes();
        }

        function getSelectedEventIds() {
            const selectedIds = [];
            document.querySelectorAll('.event-checkbox:checked').forEach(cb => {
                selectedIds.push(parseInt(cb.dataset.eventId));
            });
            return selectedIds;
        }

        function updateBulkActionsBar() {
            const selectedIds = getSelectedEventIds();
            const count = selectedIds.length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const countSpan = document.getElementById('bulkSelectionCount');

            if (count > 0) {
                bulkBar.style.display = 'flex';
                countSpan.textContent = count;
            } else {
                bulkBar.style.display = 'none';
            }
        }

        function updateRowSelection() {
            document.querySelectorAll('.event-row').forEach(row => {
                const checkbox = row.querySelector('.event-checkbox');
                if (checkbox && checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }

        function attachCheckboxListeners() {
            document.querySelectorAll('.event-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                    updateBulkActionsBar();
                    updateRowSelection();

                    const allCheckboxes = document.querySelectorAll('.event-checkbox');
                    const checkedCheckboxes = document.querySelectorAll('.event-checkbox:checked');
                    const masterCheckbox = document.getElementById('eventSelectAllCheckbox');

                    if (checkedCheckboxes.length === 0) {
                        masterCheckbox.checked = false;
                        masterCheckbox.indeterminate = false;
                    } else if (checkedCheckboxes.length === allCheckboxes.length) {
                        masterCheckbox.checked = true;
                        masterCheckbox.indeterminate = false;
                    } else {
                        masterCheckbox.checked = false;
                        masterCheckbox.indeterminate = true;
                    }
                });
            });
        }

        // ========================================
        // Bulk Delete Operations
        // ========================================

        function openBulkDeleteModal() {
            const selectedIds = getSelectedEventIds();
            if (selectedIds.length === 0) {
                showToast('กรุณาเลือก events ที่ต้องการลบ', 'error');
                return;
            }
            document.getElementById('bulkDeleteCount').textContent = selectedIds.length;
            document.getElementById('bulkDeleteModal').classList.add('active');
        }

        function closeBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').classList.remove('active');
        }

        async function confirmBulkDelete() {
            const selectedIds = getSelectedEventIds();
            if (selectedIds.length === 0) {
                closeBulkDeleteModal();
                return;
            }

            showLoading();

            try {
                const response = await fetch('api.php?action=bulk_delete', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ ids: selectedIds })
                });

                const result = await response.json();
                hideLoading();
                closeBulkDeleteModal();

                if (result.success) {
                    const { deleted_count, failed_count } = result.data;
                    if (failed_count > 0) {
                        showToast(`ลบสำเร็จ ${deleted_count} รายการ, ล้มเหลว ${failed_count} รายการ`, 'warning');
                    } else {
                        showToast(`ลบสำเร็จ ${deleted_count} รายการ`, 'success');
                    }
                    loadEvents();
                } else {
                    showToast(result.message || 'เกิดข้อผิดพลาดในการลบ', 'error');
                }
            } catch (error) {
                console.error('Bulk delete failed:', error);
                hideLoading();
                closeBulkDeleteModal();
                showToast('เกิดข้อผิดพลาดในการลบ', 'error');
            }
        }

        // ========================================
        // Bulk Edit Operations
        // ========================================

        async function openBulkEditModal() {
            const selectedIds = getSelectedEventIds();
            if (selectedIds.length === 0) {
                showToast('กรุณาเลือก events ที่ต้องการแก้ไข', 'error');
                return;
            }

            document.getElementById('bulkEditCount').textContent = selectedIds.length;
            document.getElementById('bulkEditVenue').value = '';
            document.getElementById('bulkEditOrganizer').value = '';
            document.getElementById('bulkEditCategories').value = '';

            await loadVenuesForBulkEdit();
            document.getElementById('bulkEditModal').classList.add('active');
        }

        function closeBulkEditModal() {
            document.getElementById('bulkEditModal').classList.remove('active');
        }

        async function loadVenuesForBulkEdit() {
            try {
                const response = await fetch('api.php?action=venues');
                const result = await response.json();

                if (result.success) {
                    const datalist = document.getElementById('venuesList');
                    datalist.innerHTML = ''; // Clear existing options
                    result.data.forEach(venue => {
                        const option = document.createElement('option');
                        option.value = venue;
                        datalist.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load venues:', error);
            }
        }

        async function submitBulkEdit(event) {
            event.preventDefault();

            const selectedIds = getSelectedEventIds();
            const venue = document.getElementById('bulkEditVenue').value;
            const organizer = document.getElementById('bulkEditOrganizer').value.trim();
            const categories = document.getElementById('bulkEditCategories').value.trim();

            if (!venue && !organizer && !categories) {
                showToast('กรุณาเลือกอย่างน้อย 1 ฟิลด์ที่ต้องการเปลี่ยนแปลง', 'error');
                return;
            }

            const updateData = { ids: selectedIds };
            if (venue) updateData.location = venue;
            if (organizer) updateData.organizer = organizer;
            if (categories) updateData.categories = categories;

            showLoading();

            try {
                const response = await fetch('api.php?action=bulk_update', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(updateData)
                });

                const result = await response.json();
                hideLoading();
                closeBulkEditModal();

                if (result.success) {
                    const { updated_count, failed_count } = result.data;
                    if (failed_count > 0) {
                        showToast(`อัปเดตสำเร็จ ${updated_count} รายการ, ล้มเหลว ${failed_count} รายการ`, 'warning');
                    } else {
                        showToast(`อัปเดตสำเร็จ ${updated_count} รายการ`, 'success');
                    }
                    loadEvents();
                } else {
                    showToast(result.message || 'เกิดข้อผิดพลาดในการอัปเดต', 'error');
                }
            } catch (error) {
                console.error('Bulk edit failed:', error);
                hideLoading();
                closeBulkEditModal();
                showToast('เกิดข้อผิดพลาดในการอัปเดต', 'error');
            }
        }

        // Handle search with debounce
        function handleSearch(event) {
            if (event.key === 'Enter') {
                currentPage = 1;
                loadEvents();
                return;
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadEvents();
            }, 300);
        }

        // Open add modal
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'เพิ่ม Event';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';

            // Set default date to today
            document.getElementById('eventDate').value = new Date().toISOString().split('T')[0];

            // Pre-select convention from filter dropdown
            const filterVal = document.getElementById('eventMetaFilter')?.value || '';
            document.getElementById('eventConvention').value = filterVal;

            formChanged = false;
            document.getElementById('eventModal').classList.add('active');
        }

        // Open edit modal
        async function openEditModal(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=get&id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                const event = result.data;
                const startDate = new Date(event.start);
                const endDate = new Date(event.end);

                document.getElementById('modalTitle').textContent = 'แก้ไข Event';
                document.getElementById('eventId').value = event.id;
                document.getElementById('eventConvention').value = event.event_meta_id || '';
                document.getElementById('title').value = event.title;
                document.getElementById('organizer').value = event.organizer || '';
                document.getElementById('location').value = event.location || '';
                document.getElementById('eventDate').value = startDate.toISOString().split('T')[0];
                document.getElementById('startTime').value = startDate.toTimeString().substring(0, 5);
                document.getElementById('endTime').value = endDate.toTimeString().substring(0, 5);
                document.getElementById('description').value = event.description || '';
                document.getElementById('categories').value = event.categories || '';

                formChanged = false;
                document.getElementById('eventModal').classList.add('active');
            } catch (error) {
                console.error('Failed to load event:', error);
                showToast('Failed to load event', 'error');
            } finally {
                hideLoading();
            }
        }

        // Duplicate event
        async function duplicateEvent(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=get&id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                const event = result.data;
                const startDate = new Date(event.start);
                const endDate = new Date(event.end);

                document.getElementById('modalTitle').textContent = 'Duplicate Event';
                document.getElementById('eventId').value = ''; // No ID = create new
                document.getElementById('eventConvention').value = event.event_meta_id || '';
                document.getElementById('title').value = event.title + ' (Copy)';
                document.getElementById('organizer').value = event.organizer || '';
                document.getElementById('location').value = event.location || '';
                document.getElementById('eventDate').value = startDate.toISOString().split('T')[0];
                document.getElementById('startTime').value = startDate.toTimeString().substring(0, 5);
                document.getElementById('endTime').value = endDate.toTimeString().substring(0, 5);
                document.getElementById('description').value = event.description || '';
                document.getElementById('categories').value = event.categories || '';

                formChanged = false;
                document.getElementById('eventModal').classList.add('active');
            } catch (error) {
                console.error('Failed to load event:', error);
                showToast('Failed to load event', 'error');
            } finally {
                hideLoading();
            }
        }

        // Close modal with unsaved changes warning
        function closeModal() {
            if (formChanged) {
                if (!confirm('คุณมีการแก้ไขที่ยังไม่ได้บันทึก ต้องการปิดหรือไม่?')) {
                    return;
                }
            }
            formChanged = false;
            document.getElementById('eventModal').classList.remove('active');
        }

        // Save event
        async function saveEvent(e) {
            e.preventDefault();

            const id = document.getElementById('eventId').value;
            const date = document.getElementById('eventDate').value;
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;

            const conventionVal = document.getElementById('eventConvention').value;
            const data = {
                title: document.getElementById('title').value,
                organizer: document.getElementById('organizer').value,
                location: document.getElementById('location').value,
                start: `${date}T${startTime}:00`,
                end: `${date}T${endTime}:00`,
                description: document.getElementById('description').value,
                categories: document.getElementById('categories').value,
                event_meta_id: conventionVal ? parseInt(conventionVal) : null
            };

            const isEdit = !!id;

            const url = isEdit ? `api.php?action=update&id=${id}` : 'api.php?action=create';
            const method = isEdit ? 'PUT' : 'POST';

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    closeModal();
                    loadEvents();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to save event:', error);
                showToast('Failed to save event', 'error');
            }
        }

        // Open delete modal
        function openDeleteModal(id, title) {
            document.getElementById('deleteEventId').value = id;
            document.getElementById('deleteEventTitle').textContent = title;
            document.getElementById('deleteModal').classList.add('active');
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Confirm delete
        async function confirmDelete() {
            const id = document.getElementById('deleteEventId').value;

            try {
                const response = await fetch(`api.php?action=delete&id=${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    closeDeleteModal();
                    loadEvents();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to delete event:', error);
                showToast('Failed to delete event', 'error');
            }
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ========================================
        // Import ICS Functions
        // ========================================

        // Global state for import
        let uploadedEvents = [];
        let previewData = {};

        // Handle file selection
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Client-side validation
            if (!file.name.endsWith('.ics')) {
                showToast('รองรับเฉพาะไฟล์ .ics เท่านั้น', 'error');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                showToast('ไฟล์ใหญ่เกินไป (สูงสุด 5MB)', 'error');
                return;
            }

            uploadFile(file);
        }

        // Upload file to server
        async function uploadFile(file) {
            showLoading();

            const formData = new FormData();
            formData.append('ics_file', file);

            try {
                const response = await fetch('api.php?action=upload_ics', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: formData
                });

                const result = await response.json();
                hideLoading();

                if (result.success) {
                    previewData = result.data;
                    uploadedEvents = result.data.events;
                    showPreview();
                    showToast(result.message, 'success');
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                hideLoading();
                showToast('Upload failed: ' + error.message, 'error');
            }
        }

        // Display preview table
        function showPreview() {
            // Hide upload area, show preview section
            document.getElementById('uploadArea').style.display = 'none';
            document.getElementById('previewSection').style.display = 'block';

            // Show selected convention name
            const icsSelect = document.getElementById('icsImportEventMeta');
            const conventionName = icsSelect.value ? icsSelect.options[icsSelect.selectedIndex].text : '(ไม่ได้เลือก)';
            document.getElementById('previewConventionName').textContent = conventionName;

            // Update stats
            document.getElementById('previewCount').textContent = uploadedEvents.length;
            document.getElementById('statNew').textContent = uploadedEvents.filter(e => !e.is_duplicate).length;
            document.getElementById('statDup').textContent = previewData.stats.duplicates;
            document.getElementById('statError').textContent = uploadedEvents.filter(e => e.validation_errors && e.validation_errors.length > 0).length;

            // Render table
            renderPreviewTable();
            updateImportCount();
        }

        // Render preview table rows
        function renderPreviewTable() {
            const tbody = document.getElementById('previewTableBody');
            if (uploadedEvents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">ไม่มีข้อมูล</td></tr>';
                return;
            }

            tbody.innerHTML = uploadedEvents.map((event, index) => {
                const hasErrors = event.validation_errors && event.validation_errors.length > 0;
                const statusClass = hasErrors ? 'status-invalid' : (event.is_duplicate ? 'status-duplicate' : 'status-new');
                const statusText = hasErrors ? '❌ ผิดพลาด' : (event.is_duplicate ? '⚠️ ซ้ำ' : '✅ ใหม่');

                const dupAction = event.is_duplicate ? `
                    <select class="dup-action-select" data-index="${index}">
                        <option value="skip" selected>ข้าม</option>
                        <option value="update">อัปเดต</option>
                    </select>
                ` : '<span>—</span>';

                const errorTooltip = hasErrors ? `title="${event.validation_errors.join(', ')}"` : '';

                return `
                    <tr data-index="${index}" ${errorTooltip}>
                        <td><input type="checkbox" ${hasErrors ? 'disabled' : 'checked'} data-index="${index}" onchange="updateImportCount()"></td>
                        <td><span class="status-indicator ${statusClass}">${statusText}</span></td>
                        <td>${escapeHtml(event.title || '')}</td>
                        <td>${formatDateTime(event.start)} - ${formatDateTime(event.end)}</td>
                        <td>${escapeHtml(event.location || '')}</td>
                        <td>${escapeHtml(event.organizer || '')}</td>
                        <td>${dupAction}</td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="editPreviewEvent(${index})" ${hasErrors ? 'disabled' : ''}>✏️</button>
                            <button class="btn btn-sm btn-danger" onclick="deletePreviewEvent(${index})">🗑️</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Format datetime for display
        function formatDateTime(datetime) {
            if (!datetime) return '';
            const date = new Date(datetime);
            return date.toLocaleString('th-TH', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Edit preview event
        function editPreviewEvent(index) {
            const event = uploadedEvents[index];
            if (!event) return;

            // Open event modal with preview data
            document.getElementById('modalTitle').textContent = 'แก้ไข Event (Preview)';
            document.getElementById('eventId').value = ''; // No ID yet
            document.getElementById('eventTitle').value = event.title || '';
            document.getElementById('organizer').value = event.organizer || '';
            document.getElementById('location').value = event.location || '';

            const startDate = new Date(event.start);
            document.getElementById('eventDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('startTime').value = startDate.toTimeString().slice(0, 5);

            const endDate = new Date(event.end);
            document.getElementById('endTime').value = endDate.toTimeString().slice(0, 5);

            document.getElementById('description').value = event.description || '';
            document.getElementById('categories').value = event.categories || '';

            document.getElementById('eventModal').classList.add('active');

            // Override save to update preview instead
            window.previewEditIndex = index;
        }

        // Delete preview event
        function deletePreviewEvent(index) {
            if (!confirm('ลบ event นี้จาก preview?')) return;

            uploadedEvents.splice(index, 1);
            renderPreviewTable();
            updateImportCount();

            // Update stats
            document.getElementById('previewCount').textContent = uploadedEvents.length;
            document.getElementById('statNew').textContent = uploadedEvents.filter(e => !e.is_duplicate).length;
            document.getElementById('statDup').textContent = uploadedEvents.filter(e => e.is_duplicate).length;
            document.getElementById('statError').textContent = uploadedEvents.filter(e => e.validation_errors && e.validation_errors.length > 0).length;
        }

        // Select/Deselect all events
        function toggleAllCheckboxes() {
            const checked = document.getElementById('selectAllCheckbox').checked;
            document.querySelectorAll('input[type="checkbox"][data-index]').forEach(cb => {
                if (!cb.disabled) cb.checked = checked;
            });
            updateImportCount();
        }

        function selectAllEvents() {
            document.getElementById('selectAllCheckbox').checked = true;
            toggleAllCheckboxes();
        }

        function deselectAllEvents() {
            document.getElementById('selectAllCheckbox').checked = false;
            toggleAllCheckboxes();
        }

        // Delete selected events
        function deleteSelectedEvents() {
            const selectedIndexes = [];
            document.querySelectorAll('input[type="checkbox"][data-index]:checked').forEach(cb => {
                selectedIndexes.push(parseInt(cb.dataset.index));
            });

            if (selectedIndexes.length === 0) {
                showToast('กรุณาเลือก events ที่ต้องการลบ', 'error');
                return;
            }

            if (!confirm(`ลบ ${selectedIndexes.length} events?`)) return;

            // Sort descending to avoid index shift issues
            selectedIndexes.sort((a, b) => b - a);
            selectedIndexes.forEach(index => {
                uploadedEvents.splice(index, 1);
            });

            renderPreviewTable();
            updateImportCount();

            // Update stats
            document.getElementById('previewCount').textContent = uploadedEvents.length;
            document.getElementById('statNew').textContent = uploadedEvents.filter(e => !e.is_duplicate).length;
            document.getElementById('statDup').textContent = uploadedEvents.filter(e => e.is_duplicate).length;
            document.getElementById('statError').textContent = uploadedEvents.filter(e => e.validation_errors && e.validation_errors.length > 0).length;
        }

        // Update import count
        function updateImportCount() {
            const count = document.querySelectorAll('input[type="checkbox"][data-index]:checked').length;
            document.getElementById('importCount').textContent = count;
        }

        // Confirm import
        async function confirmImport() {
            const checkedEvents = uploadedEvents.filter((event, index) => {
                const checkbox = document.querySelector(`input[type="checkbox"][data-index="${index}"]`);
                return checkbox && checkbox.checked && !checkbox.disabled;
            });

            if (checkedEvents.length === 0) {
                showToast('กรุณาเลือก events ที่ต้องการ import', 'error');
                return;
            }

            // Add action field based on duplicate dropdown
            const eventsToImport = checkedEvents.map((event) => {
                const realIndex = uploadedEvents.indexOf(event);
                const dupSelect = document.querySelector(`select[data-index="${realIndex}"]`);

                let action = 'insert';
                if (event.is_duplicate && dupSelect) {
                    action = dupSelect.value; // 'update' or 'skip'
                }

                return { ...event, action };
            });

            if (!confirm(`Import ${eventsToImport.length} events?`)) return;

            showLoading();

            // Include event_meta_id from ICS import selector
            const importEventMetaId = document.getElementById('icsImportEventMeta').value;
            const importBody = {
                events: eventsToImport,
                save_file: true
            };
            if (importEventMetaId) {
                importBody.event_meta_id = parseInt(importEventMetaId);
            }

            try {
                const response = await fetch('api.php?action=import_ics_confirm', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(importBody)
                });

                const result = await response.json();
                hideLoading();

                if (result.success) {
                    showSummary(result.data);
                    showToast('Import สำเร็จ!', 'success');
                    loadEvents(); // Reload events table
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                hideLoading();
                showToast('Import failed: ' + error.message, 'error');
            }
        }

        // Show import summary
        function showSummary(data) {
            document.getElementById('previewSection').style.display = 'none';
            document.getElementById('summarySection').style.display = 'block';

            document.getElementById('summaryInserted').textContent = data.stats.inserted;
            document.getElementById('summaryUpdated').textContent = data.stats.updated;
            document.getElementById('summarySkipped').textContent = data.stats.skipped;
            document.getElementById('summaryErrors').textContent = data.stats.errors;

            // Show errors if any
            const errorsList = document.getElementById('errorsList');
            if (data.errors && data.errors.length > 0) {
                errorsList.innerHTML = '<div class="errors-box"><h4>⚠️ ข้อผิดพลาด:</h4><ul>' +
                    data.errors.map(err => `<li>${escapeHtml(err)}</li>`).join('') +
                    '</ul></div>';
            } else {
                errorsList.innerHTML = '';
            }
        }

        // Reset upload state
        function resetUpload() {
            uploadedEvents = [];
            previewData = {};
            document.getElementById('uploadArea').style.display = 'block';
            document.getElementById('previewSection').style.display = 'none';
            document.getElementById('summarySection').style.display = 'none';
            document.getElementById('icsFileInput').value = '';
        }

        // Setup drag and drop for upload box
        document.addEventListener('DOMContentLoaded', function() {
            const uploadBox = document.getElementById('uploadBox');
            if (uploadBox) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadBox.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadBox.addEventListener(eventName, () => {
                        uploadBox.classList.add('dragover');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    uploadBox.addEventListener(eventName, () => {
                        uploadBox.classList.remove('dragover');
                    }, false);
                });

                uploadBox.addEventListener('drop', (e) => {
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        document.getElementById('icsFileInput').files = files;
                        handleFileSelect({ target: { files: files } });
                    }
                }, false);
            }
        });

        // ========================================================================
        // CREDITS MANAGEMENT
        // ========================================================================

        // Credits State
        let creditsCurrentPage = 1;
        let creditsPerPage = 20;
        let creditsSearchTimeout = null;
        let creditsSortColumn = 'display_order';
        let creditsSortDirection = 'asc';
        let creditsFormChanged = false;

        // Load Credits
        async function loadCredits() {
            showLoading();

            const search = document.getElementById('creditsSearchInput')?.value || '';
            const eventMetaId = document.getElementById('creditsEventMetaFilter')?.value || '';
            let url = `api.php?action=credits_list&page=${creditsCurrentPage}&limit=${creditsPerPage}&sort=${creditsSortColumn}&order=${creditsSortDirection}&search=${encodeURIComponent(search)}`;
            if (eventMetaId) url += `&event_meta_id=${encodeURIComponent(eventMetaId)}`;

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    renderCredits(result.data.credits);
                    renderCreditsPagination(result.data.pagination);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to load credits:', error);
                showToast('Failed to load credits', 'error');
            } finally {
                hideLoading();
            }
        }

        // Render Credits Table
        function renderCredits(credits) {
            const tbody = document.getElementById('creditsTableBody');

            if (credits.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">ไม่พบ credits</td></tr>';
                return;
            }

            tbody.innerHTML = credits.map(credit => {
                const linkDisplay = credit.link ?
                    `<a href="${escapeHtml(credit.link)}" target="_blank" rel="noopener" style="color: var(--admin-primary); text-decoration: none;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                        Link
                    </a>` : '-';

                return `
                    <tr class="event-row" data-credit-id="${credit.id}">
                        <td><input type="checkbox" class="credit-checkbox" data-credit-id="${credit.id}"></td>
                        <td>${credit.id}</td>
                        <td>${escapeHtml(credit.title)}</td>
                        <td>${linkDisplay}</td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(credit.description || '-')}</td>
                        <td style="text-align: center;">${credit.display_order}</td>
                        <td class="actions">
                            <button class="btn btn-secondary btn-sm" onclick="openEditCreditModal(${credit.id})">แก้ไข</button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteCreditModal(${credit.id}, '${escapeHtml(credit.title).replace(/'/g, "\\'")}')">ลบ</button>
                        </td>
                    </tr>
                `;
            }).join('');

            attachCreditsCheckboxListeners();
        }

        // Render Pagination
        function renderCreditsPagination(pagination) {
            const container = document.getElementById('creditsPagination');
            const { page, totalPages, total } = pagination;

            if (totalPages <= 1) {
                container.innerHTML = `<span class="pagination-info">ทั้งหมด ${total} รายการ</span>`;
                return;
            }

            container.innerHTML = `
                <button class="btn btn-secondary btn-sm" onclick="goToCreditPage(${page - 1})" ${page <= 1 ? 'disabled' : ''}>
                    &laquo; ก่อนหน้า
                </button>
                <span class="pagination-info">หน้า ${page} / ${totalPages} (ทั้งหมด ${total} รายการ)</span>
                <button class="btn btn-secondary btn-sm" onclick="goToCreditPage(${page + 1})" ${page >= totalPages ? 'disabled' : ''}>
                    ถัดไป &raquo;
                </button>
            `;
        }

        function goToCreditPage(page) {
            creditsCurrentPage = page;
            loadCredits();
        }

        function changeCreditsPerPage() {
            creditsPerPage = parseInt(document.getElementById('creditsPerPageSelect').value);
            creditsCurrentPage = 1;
            loadCredits();
        }

        // Search
        function handleCreditsSearch(event) {
            if (event.key === 'Enter') {
                creditsCurrentPage = 1;
                loadCredits();
                return;
            }

            clearTimeout(creditsSearchTimeout);
            creditsSearchTimeout = setTimeout(() => {
                creditsCurrentPage = 1;
                loadCredits();
            }, 300);
        }

        function clearCreditsSearch() {
            document.getElementById('creditsSearchInput').value = '';
            creditsCurrentPage = 1;
            loadCredits();
        }

        // Sorting
        function sortCreditsBy(column) {
            if (creditsSortColumn === column) {
                creditsSortDirection = creditsSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                creditsSortColumn = column;
                creditsSortDirection = 'asc';
            }
            updateCreditsSortIcons();
            loadCredits();
        }

        function updateCreditsSortIcons() {
            document.querySelectorAll('#creditsSection .sort-icon').forEach(icon => {
                icon.classList.remove('asc', 'desc');
                if (icon.dataset.col === creditsSortColumn) {
                    icon.classList.add(creditsSortDirection);
                }
            });
        }

        // Modals
        function openAddCreditModal() {
            document.getElementById('creditModalTitle').textContent = 'เพิ่ม Credit';
            document.getElementById('creditForm').reset();
            document.getElementById('creditId').value = '';
            // Pre-select current filter convention
            const filterVal = document.getElementById('creditsEventMetaFilter')?.value || '';
            document.getElementById('creditEventMetaId').value = filterVal;
            creditsFormChanged = false;
            document.getElementById('creditModal').classList.add('active');
        }

        async function openEditCreditModal(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=credits_get&id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                const credit = result.data;

                document.getElementById('creditModalTitle').textContent = 'แก้ไข Credit';
                document.getElementById('creditId').value = credit.id;
                document.getElementById('creditTitle').value = credit.title;
                document.getElementById('creditLink').value = credit.link || '';
                document.getElementById('creditDescription').value = credit.description || '';
                document.getElementById('creditDisplayOrder').value = credit.display_order || 0;
                document.getElementById('creditEventMetaId').value = credit.event_meta_id || '';

                creditsFormChanged = false;
                document.getElementById('creditModal').classList.add('active');
            } catch (error) {
                showToast('Failed to load credit', 'error');
            } finally {
                hideLoading();
            }
        }

        function closeCreditModal() {
            if (creditsFormChanged) {
                if (!confirm('คุณมีการแก้ไขที่ยังไม่ได้บันทึก ต้องการปิดหรือไม่?')) {
                    return;
                }
            }
            creditsFormChanged = false;
            document.getElementById('creditModal').classList.remove('active');
        }

        async function saveCredit(e) {
            e.preventDefault();

            const id = document.getElementById('creditId').value;
            const eventMetaVal = document.getElementById('creditEventMetaId').value;
            const data = {
                title: document.getElementById('creditTitle').value,
                link: document.getElementById('creditLink').value,
                description: document.getElementById('creditDescription').value,
                display_order: parseInt(document.getElementById('creditDisplayOrder').value) || 0,
                event_meta_id: eventMetaVal ? parseInt(eventMetaVal) : null
            };

            const isEdit = !!id;
            const url = isEdit ? `api.php?action=credits_update&id=${id}` : 'api.php?action=credits_create';
            const method = isEdit ? 'PUT' : 'POST';

            showLoading();

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    closeCreditModal();
                    loadCredits();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to save credit:', error);
                showToast('Failed to save credit', 'error');
            } finally {
                hideLoading();
            }
        }

        // Delete Single
        function openDeleteCreditModal(id, title) {
            document.getElementById('deleteCreditId').value = id;
            document.getElementById('deleteCreditTitle').textContent = title;
            document.getElementById('deleteCreditModal').classList.add('active');
        }

        function closeDeleteCreditModal() {
            document.getElementById('deleteCreditModal').classList.remove('active');
        }

        async function confirmDeleteCredit() {
            const id = document.getElementById('deleteCreditId').value;

            showLoading();

            try {
                const response = await fetch(`api.php?action=credits_delete&id=${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    closeDeleteCreditModal();
                    loadCredits();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to delete credit:', error);
                showToast('Failed to delete credit', 'error');
            } finally {
                hideLoading();
            }
        }

        // Bulk Selection
        function toggleAllCreditsCheckboxes() {
            const masterCheckbox = document.getElementById('creditsSelectAllCheckbox');
            const checkboxes = document.querySelectorAll('.credit-checkbox');
            checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
            updateCreditsBulkActionsBar();
            updateCreditsRowSelection();
        }

        function getSelectedCreditIds() {
            const selectedIds = [];
            document.querySelectorAll('.credit-checkbox:checked').forEach(cb => {
                selectedIds.push(parseInt(cb.dataset.creditId));
            });
            return selectedIds;
        }

        function updateCreditsBulkActionsBar() {
            const selectedIds = getSelectedCreditIds();
            const count = selectedIds.length;
            const bulkBar = document.getElementById('creditsBulkActionsBar');
            const countSpan = document.getElementById('creditsBulkSelectionCount');

            if (count > 0) {
                bulkBar.style.display = 'flex';
                countSpan.textContent = count;
            } else {
                bulkBar.style.display = 'none';
            }
        }

        function updateCreditsRowSelection() {
            document.querySelectorAll('#creditsTableBody tr').forEach(row => {
                const checkbox = row.querySelector('.credit-checkbox');
                if (checkbox && checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }

        function attachCreditsCheckboxListeners() {
            document.querySelectorAll('.credit-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                    updateCreditsBulkActionsBar();
                    updateCreditsRowSelection();

                    const allCheckboxes = document.querySelectorAll('.credit-checkbox');
                    const checkedCheckboxes = document.querySelectorAll('.credit-checkbox:checked');
                    const masterCheckbox = document.getElementById('creditsSelectAllCheckbox');

                    if (checkedCheckboxes.length === 0) {
                        masterCheckbox.checked = false;
                        masterCheckbox.indeterminate = false;
                    } else if (checkedCheckboxes.length === allCheckboxes.length) {
                        masterCheckbox.checked = true;
                        masterCheckbox.indeterminate = false;
                    } else {
                        masterCheckbox.checked = false;
                        masterCheckbox.indeterminate = true;
                    }
                });
            });
        }

        function creditsBulkSelectAll() {
            document.getElementById('creditsSelectAllCheckbox').checked = true;
            toggleAllCreditsCheckboxes();
        }

        function creditsBulkClearSelection() {
            document.getElementById('creditsSelectAllCheckbox').checked = false;
            toggleAllCreditsCheckboxes();
        }

        // Bulk Delete
        function openCreditsBulkDeleteModal() {
            const selectedIds = getSelectedCreditIds();
            if (selectedIds.length === 0) {
                showToast('กรุณาเลือก credits ที่ต้องการลบ', 'error');
                return;
            }
            document.getElementById('creditsBulkDeleteCount').textContent = selectedIds.length;
            document.getElementById('creditsBulkDeleteModal').classList.add('active');
        }

        function closeCreditsBulkDeleteModal() {
            document.getElementById('creditsBulkDeleteModal').classList.remove('active');
        }

        async function confirmCreditsBulkDelete() {
            const selectedIds = getSelectedCreditIds();
            if (selectedIds.length === 0) {
                closeCreditsBulkDeleteModal();
                return;
            }

            showLoading();

            try {
                const response = await fetch('api.php?action=credits_bulk_delete', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ ids: selectedIds })
                });

                const result = await response.json();
                hideLoading();
                closeCreditsBulkDeleteModal();

                if (result.success) {
                    const { deleted_count, failed_count } = result.data;
                    if (failed_count > 0) {
                        showToast(`ลบสำเร็จ ${deleted_count} รายการ, ล้มเหลว ${failed_count} รายการ`, 'warning');
                    } else {
                        showToast(`ลบสำเร็จ ${deleted_count} รายการ`, 'success');
                    }
                    loadCredits();
                } else {
                    showToast(result.message || 'เกิดข้อผิดพลาดในการลบ', 'error');
                }
            } catch (error) {
                console.error('Bulk delete failed:', error);
                hideLoading();
                closeCreditsBulkDeleteModal();
                showToast('เกิดข้อผิดพลาดในการลบ', 'error');
            }
        }

        // Form Change Tracking for Credits
        const creditFormInputs = document.querySelectorAll('#creditForm input, #creditForm textarea');
        creditFormInputs.forEach(input => {
            input.addEventListener('change', () => { creditsFormChanged = true; });
            input.addEventListener('input', () => { creditsFormChanged = true; });
        });

        // ========================================================================
        // EVENT META (CONVENTIONS) - Populate filter dropdowns
        // ========================================================================

        async function loadEventMetaOptions() {
            try {
                const response = await fetch('api.php?action=event_meta_list');
                const result = await response.json();

                if (result.success) {
                    const metas = result.data;
                    const selectors = [
                        'eventMetaFilter',
                        'reqEventMetaFilter',
                        'creditsEventMetaFilter',
                        'eventConvention',
                        'creditEventMetaId',
                        'icsImportEventMeta'
                    ];

                    selectors.forEach(selectorId => {
                        const select = document.getElementById(selectorId);
                        if (!select) return;
                        // Keep the first "All Conventions" option
                        while (select.options.length > 1) {
                            select.remove(1);
                        }
                        metas.forEach(meta => {
                            const option = document.createElement('option');
                            option.value = meta.id;
                            option.textContent = meta.name + (meta.is_active ? '' : ' (inactive)');
                            select.appendChild(option);
                        });
                    });
                }
            } catch (error) {
                console.error('Failed to load event meta options:', error);
            }
        }

        // ========================================================================
        // CONVENTIONS MANAGEMENT
        // ========================================================================

        let conventionsFormChanged = false;

        // Load Conventions
        async function loadConventions() {
            showLoading();

            const search = document.getElementById('conventionsSearchInput')?.value || '';
            let url = `api.php?action=event_meta_list`;
            if (search) url += `&search=${encodeURIComponent(search)}`;

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    renderConventions(result.data);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to load conventions:', error);
                showToast('Failed to load conventions', 'error');
            } finally {
                hideLoading();
            }
        }

        // Render Conventions Table
        function renderConventions(conventions) {
            const tbody = document.getElementById('conventionsTableBody');

            if (!conventions || conventions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="empty-state">ไม่พบ conventions</td></tr>';
                return;
            }

            tbody.innerHTML = conventions.map(conv => {
                const activeLabel = conv.is_active
                    ? '<span class="status-approved">Active</span>'
                    : '<span class="status-rejected">Inactive</span>';

                const startDate = conv.start_date || '-';
                const endDate = conv.end_date || '-';

                return `
                    <tr>
                        <td>${conv.id}</td>
                        <td>${escapeHtml(conv.name)}</td>
                        <td><code>${escapeHtml(conv.slug)}</code></td>
                        <td>${escapeHtml(startDate)}</td>
                        <td>${escapeHtml(endDate)}</td>
                        <td>${escapeHtml(conv.venue_mode || 'multi')}</td>
                        <td>${activeLabel}</td>
                        <td>${conv.event_count !== undefined ? conv.event_count : '-'}</td>
                        <td class="actions">
                            <button class="btn btn-secondary btn-sm" onclick="openEditConventionModal(${conv.id})">แก้ไข</button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteConventionModal(${conv.id}, '${escapeHtml(conv.name).replace(/'/g, "\\'")}')">ลบ</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Search Conventions
        let conventionsSearchTimeout = null;

        function handleConventionsSearch(event) {
            if (event.key === 'Enter') {
                loadConventions();
                return;
            }
            clearTimeout(conventionsSearchTimeout);
            conventionsSearchTimeout = setTimeout(() => {
                loadConventions();
            }, 300);
        }

        function clearConventionsSearch() {
            document.getElementById('conventionsSearchInput').value = '';
            loadConventions();
        }

        // Open Add Convention Modal
        function openAddConventionModal() {
            document.getElementById('conventionModalTitle').textContent = 'เพิ่ม Convention';
            document.getElementById('conventionForm').reset();
            document.getElementById('conventionId').value = '';
            document.getElementById('conventionIsActive').checked = true;
            document.getElementById('conventionVenueMode').value = 'multi';
            conventionsFormChanged = false;
            document.getElementById('conventionModal').classList.add('active');
        }

        // Open Edit Convention Modal
        async function openEditConventionModal(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=event_meta_get&id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                const conv = result.data;

                document.getElementById('conventionModalTitle').textContent = 'แก้ไข Convention';
                document.getElementById('conventionId').value = conv.id;
                document.getElementById('conventionName').value = conv.name || '';
                document.getElementById('conventionSlug').value = conv.slug || '';
                document.getElementById('conventionDescription').value = conv.description || '';
                document.getElementById('conventionStartDate').value = conv.start_date || '';
                document.getElementById('conventionEndDate').value = conv.end_date || '';
                document.getElementById('conventionVenueMode').value = conv.venue_mode || 'multi';
                document.getElementById('conventionIsActive').checked = !!conv.is_active;

                conventionsFormChanged = false;
                document.getElementById('conventionModal').classList.add('active');
            } catch (error) {
                showToast('Failed to load convention', 'error');
            } finally {
                hideLoading();
            }
        }

        // Close Convention Modal
        function closeConventionModal() {
            if (conventionsFormChanged) {
                if (!confirm('คุณมีการแก้ไขที่ยังไม่ได้บันทึก ต้องการปิดหรือไม่?')) {
                    return;
                }
            }
            conventionsFormChanged = false;
            document.getElementById('conventionModal').classList.remove('active');
        }

        // Save Convention
        async function saveConvention(e) {
            e.preventDefault();

            const id = document.getElementById('conventionId').value;
            const data = {
                name: document.getElementById('conventionName').value,
                slug: document.getElementById('conventionSlug').value,
                description: document.getElementById('conventionDescription').value,
                start_date: document.getElementById('conventionStartDate').value,
                end_date: document.getElementById('conventionEndDate').value,
                venue_mode: document.getElementById('conventionVenueMode').value,
                is_active: document.getElementById('conventionIsActive').checked ? 1 : 0
            };

            const isEdit = !!id;
            const url = isEdit ? `api.php?action=event_meta_update&id=${id}` : 'api.php?action=event_meta_create';
            const method = isEdit ? 'PUT' : 'POST';

            showLoading();

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message || (isEdit ? 'อัปเดตสำเร็จ' : 'สร้างสำเร็จ'), 'success');
                    closeConventionModal();
                    loadConventions();
                    loadEventMetaOptions(); // Refresh filter dropdowns
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to save convention:', error);
                showToast('Failed to save convention', 'error');
            } finally {
                hideLoading();
            }
        }

        // Delete Convention
        function openDeleteConventionModal(id, name) {
            document.getElementById('deleteConventionId').value = id;
            document.getElementById('deleteConventionName').textContent = name;
            document.getElementById('deleteConventionModal').classList.add('active');
        }

        function closeDeleteConventionModal() {
            document.getElementById('deleteConventionModal').classList.remove('active');
        }

        async function confirmDeleteConvention() {
            const id = document.getElementById('deleteConventionId').value;

            showLoading();

            try {
                const response = await fetch(`api.php?action=event_meta_delete&id=${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message || 'ลบสำเร็จ', 'success');
                    closeDeleteConventionModal();
                    loadConventions();
                    loadEventMetaOptions(); // Refresh filter dropdowns
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to delete convention:', error);
                showToast('Failed to delete convention', 'error');
            } finally {
                hideLoading();
            }
        }

        // Form Change Tracking for Conventions
        document.addEventListener('DOMContentLoaded', function() {
            const convFormInputs = document.querySelectorAll('#conventionForm input, #conventionForm textarea, #conventionForm select');
            convFormInputs.forEach(input => {
                input.addEventListener('change', () => { conventionsFormChanged = true; });
                input.addEventListener('input', () => { conventionsFormChanged = true; });
            });
        });

        // Auto-generate slug from name
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('conventionName');
            const slugInput = document.getElementById('conventionSlug');
            if (nameInput && slugInput) {
                nameInput.addEventListener('input', function() {
                    // Only auto-generate if slug is empty or was auto-generated
                    if (!document.getElementById('conventionId').value) {
                        slugInput.value = nameInput.value
                            .toLowerCase()
                            .replace(/[^a-z0-9\s-]/g, '')
                            .replace(/\s+/g, '-')
                            .replace(/-+/g, '-')
                            .trim();
                    }
                });
            }
        });
        // ============================================================
        // BACKUP/RESTORE
        // ============================================================

        let pendingRestoreFilename = '';
        let pendingDeleteBackupFilename = '';

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0) + ' ' + units[i];
        }

        async function loadBackups() {
            try {
                const res = await fetch('api.php?action=backup_list');
                const result = await res.json();
                const tbody = document.getElementById('backupTableBody');

                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="5" class="loading">Error loading backups</td></tr>';
                    return;
                }

                const backups = result.data.backups;
                if (backups.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="loading">ยังไม่มีไฟล์ backup</td></tr>';
                    return;
                }

                tbody.innerHTML = backups.map((b, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(b.filename)}</td>
                        <td>${formatFileSize(b.size)}</td>
                        <td>${b.created_at}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="downloadBackup('${escapeHtml(b.filename)}')">⬇️ Download</button>
                            <button class="btn btn-warning btn-sm" onclick="openRestoreModal('${escapeHtml(b.filename)}')">🔄 Restore</button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteBackupModal('${escapeHtml(b.filename)}')">🗑️ ลบ</button>
                        </td>
                    </tr>
                `).join('');
            } catch (e) {
                document.getElementById('backupTableBody').innerHTML = '<tr><td colspan="5" class="loading">Error loading backups</td></tr>';
            }
        }

        async function createBackup() {
            try {
                const res = await fetch('api.php?action=backup_create', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                });
                const result = await res.json();
                if (result.success) {
                    alert('Backup created: ' + result.data.filename);
                    loadBackups();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (e) {
                alert('Failed to create backup');
            }
        }

        function downloadBackup(filename) {
            window.location.href = 'api.php?action=backup_download&filename=' + encodeURIComponent(filename);
        }

        // Restore Modal
        function openRestoreModal(filename) {
            pendingRestoreFilename = filename;
            document.getElementById('restoreFilename').textContent = filename;
            document.getElementById('restoreModal').classList.add('active');
        }

        function closeRestoreModal() {
            document.getElementById('restoreModal').classList.remove('active');
            pendingRestoreFilename = '';
        }

        async function confirmRestore() {
            if (!pendingRestoreFilename) return;
            closeRestoreModal();

            try {
                const res = await fetch('api.php?action=backup_restore', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ filename: pendingRestoreFilename })
                });
                const result = await res.json();
                if (result.success) {
                    alert(result.message);
                    loadBackups();
                    loadEvents();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (e) {
                alert('Failed to restore database');
            }
        }

        // Upload Restore Modal
        function openUploadRestoreModal() {
            document.getElementById('backupFileInput').value = '';
            document.getElementById('uploadRestoreModal').classList.add('active');
        }

        function closeUploadRestoreModal() {
            document.getElementById('uploadRestoreModal').classList.remove('active');
        }

        async function confirmUploadRestore() {
            const fileInput = document.getElementById('backupFileInput');
            if (!fileInput.files.length) {
                alert('กรุณาเลือกไฟล์ .db');
                return;
            }

            closeUploadRestoreModal();

            const formData = new FormData();
            formData.append('backup_file', fileInput.files[0]);

            try {
                const res = await fetch('api.php?action=backup_upload_restore', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    alert(result.message);
                    loadBackups();
                    loadEvents();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (e) {
                alert('Failed to upload and restore');
            }
        }

        // Delete Backup Modal
        function openDeleteBackupModal(filename) {
            pendingDeleteBackupFilename = filename;
            document.getElementById('deleteBackupFilename').textContent = filename;
            document.getElementById('deleteBackupModal').classList.add('active');
        }

        function closeDeleteBackupModal() {
            document.getElementById('deleteBackupModal').classList.remove('active');
            pendingDeleteBackupFilename = '';
        }

        async function confirmDeleteBackup() {
            const filename = pendingDeleteBackupFilename;
            if (!filename) return;
            closeDeleteBackupModal();

            try {
                const res = await fetch('api.php?action=backup_delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ filename: filename })
                });
                const result = await res.json();
                if (result.success) {
                    loadBackups();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (e) {
                alert('Failed to delete backup');
            }
        }

        // =============================================================================
        // CHANGE PASSWORD
        // =============================================================================

        // =============================================================================
        // USER MANAGEMENT (admin only)
        // =============================================================================

        let pendingDeleteUserId = null;

        async function loadUsers() {
            if (ADMIN_ROLE !== 'admin') return;
            const tbody = document.getElementById('usersTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="7" class="loading">Loading...</td></tr>';

            try {
                const res = await fetch('api.php?action=users_list', {
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                });
                const result = await res.json();
                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="7">Error: ' + (result.message || 'Failed') + '</td></tr>';
                    return;
                }

                const users = result.data.users;
                if (users.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7">No users found</td></tr>';
                    return;
                }

                tbody.innerHTML = users.map(function(user) {
                    const roleBadge = user.role === 'admin'
                        ? '<span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;">Admin</span>'
                        : '<span style="background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;">Agent</span>';
                    const activeBadge = user.is_active == 1
                        ? '<span style="color:#16a34a;">Active</span>'
                        : '<span style="color:#dc2626;">Inactive</span>';
                    const lastLogin = user.last_login_at || '-';

                    return '<tr>' +
                        '<td>' + user.id + '</td>' +
                        '<td><strong>' + user.username + '</strong></td>' +
                        '<td>' + (user.display_name || '-') + '</td>' +
                        '<td>' + roleBadge + '</td>' +
                        '<td>' + activeBadge + '</td>' +
                        '<td>' + lastLogin + '</td>' +
                        '<td>' +
                            '<button class="btn btn-secondary" onclick="openEditUserModal(' + user.id + ')" style="padding:4px 10px;font-size:12px;">Edit</button> ' +
                            '<button class="btn btn-danger" onclick="openDeleteUserModal(' + user.id + ', \'' + user.username.replace(/'/g, "\\'") + '\')" style="padding:4px 10px;font-size:12px;">Delete</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7">Network error</td></tr>';
            }
        }

        function openAddUserModal() {
            document.getElementById('userModalTitle').textContent = 'Add User';
            document.getElementById('userSubmitBtn').textContent = 'Create';
            document.getElementById('userId').value = '';
            document.getElementById('userUsername').value = '';
            document.getElementById('userUsername').readOnly = false;
            document.getElementById('userDisplayName').value = '';
            document.getElementById('userPassword').value = '';
            document.getElementById('userPassword').required = true;
            document.getElementById('userPasswordLabel').textContent = 'Password (min 8 characters)';
            document.getElementById('userRole').value = 'agent';
            document.getElementById('userIsActive').checked = true;
            document.getElementById('userModalError').style.display = 'none';
            document.getElementById('userModal').style.display = 'flex';
        }

        async function openEditUserModal(id) {
            try {
                const res = await fetch('api.php?action=users_get&id=' + id, {
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                });
                const result = await res.json();
                if (!result.success) {
                    showToast(result.message || 'Failed to load user', 'error');
                    return;
                }

                const user = result.data;
                document.getElementById('userModalTitle').textContent = 'Edit User';
                document.getElementById('userSubmitBtn').textContent = 'Save';
                document.getElementById('userId').value = user.id;
                document.getElementById('userUsername').value = user.username;
                document.getElementById('userUsername').readOnly = true;
                document.getElementById('userDisplayName').value = user.display_name || '';
                document.getElementById('userPassword').value = '';
                document.getElementById('userPassword').required = false;
                document.getElementById('userPasswordLabel').textContent = 'Password (leave blank to keep current)';
                document.getElementById('userRole').value = user.role || 'agent';
                document.getElementById('userIsActive').checked = user.is_active == 1;
                document.getElementById('userModalError').style.display = 'none';
                document.getElementById('userModal').style.display = 'flex';
            } catch (e) {
                showToast('Network error', 'error');
            }
        }

        async function submitUserForm(e) {
            e.preventDefault();
            const errorEl = document.getElementById('userModalError');
            errorEl.style.display = 'none';

            const id = document.getElementById('userId').value;
            const isEdit = !!id;

            const data = {
                username: document.getElementById('userUsername').value.trim(),
                display_name: document.getElementById('userDisplayName').value.trim(),
                password: document.getElementById('userPassword').value,
                role: document.getElementById('userRole').value,
                is_active: document.getElementById('userIsActive').checked ? 1 : 0
            };

            // Client-side validation
            if (!isEdit && data.password.length < 8) {
                errorEl.textContent = 'Password must be at least 8 characters';
                errorEl.style.display = 'block';
                return;
            }
            if (isEdit && data.password && data.password.length < 8) {
                errorEl.textContent = 'Password must be at least 8 characters';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const url = isEdit ? 'api.php?action=users_update&id=' + id : 'api.php?action=users_create';
                const method = isEdit ? 'PUT' : 'POST';

                const res = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    closeUserModal();
                    loadUsers();
                    showToast(result.message || 'Success', 'success');
                } else {
                    errorEl.textContent = result.message || 'Failed';
                    errorEl.style.display = 'block';
                }
            } catch (err) {
                errorEl.textContent = 'Network error';
                errorEl.style.display = 'block';
            }
        }

        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        function openDeleteUserModal(id, username) {
            pendingDeleteUserId = id;
            document.getElementById('deleteUserName').textContent = username;
            document.getElementById('deleteUserModal').classList.add('active');
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').classList.remove('active');
            pendingDeleteUserId = null;
        }

        async function confirmDeleteUser() {
            if (!pendingDeleteUserId) return;

            try {
                const res = await fetch('api.php?action=users_delete&id=' + pendingDeleteUserId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                });
                const result = await res.json();

                closeDeleteUserModal();
                if (result.success) {
                    loadUsers();
                    showToast(result.message || 'User deleted', 'success');
                } else {
                    showToast(result.message || 'Failed to delete user', 'error');
                }
            } catch (e) {
                closeDeleteUserModal();
                showToast('Network error', 'error');
            }
        }

        // =============================================================================
        // CHANGE PASSWORD
        // =============================================================================

        function showChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'flex';
            document.getElementById('changePasswordForm').reset();
            document.getElementById('changePasswordError').style.display = 'none';
            document.getElementById('changePasswordSuccess').style.display = 'none';
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
        }

        async function submitChangePassword(e) {
            e.preventDefault();
            const currentPassword = document.getElementById('cpCurrentPassword').value;
            const newPassword = document.getElementById('cpNewPassword').value;
            const confirmPassword = document.getElementById('cpConfirmPassword').value;
            const errorEl = document.getElementById('changePasswordError');
            const successEl = document.getElementById('changePasswordSuccess');

            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            if (newPassword !== confirmPassword) {
                errorEl.textContent = 'New passwords do not match';
                errorEl.style.display = 'block';
                return;
            }

            if (newPassword.length < 8) {
                errorEl.textContent = 'New password must be at least 8 characters';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const res = await fetch('api.php?action=change_password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword
                    })
                });
                const result = await res.json();
                if (result.success) {
                    successEl.textContent = result.message || 'Password changed successfully';
                    successEl.style.display = 'block';
                    document.getElementById('changePasswordForm').reset();
                } else {
                    errorEl.textContent = result.message || 'Failed to change password';
                    errorEl.style.display = 'block';
                }
            } catch (err) {
                errorEl.textContent = 'Network error';
                errorEl.style.display = 'block';
            }
        }

    </script>
</body>
</html>
