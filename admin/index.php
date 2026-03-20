<?php
/**
 * Admin UI for Program Management
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
    <title>Admin - <?php echo htmlspecialchars(get_site_title()); ?></title>
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

        .program-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
            background: #e3f0ff;
            color: #1565c0;
            white-space: nowrap;
        }

        /* Artist Tag Input Widget */
        .tag-input-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 5px 8px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            min-height: 38px;
            cursor: text;
            align-items: center;
            position: relative;
        }
        .tag-input-wrapper:focus-within {
            border-color: #E91E63;
            box-shadow: 0 0 0 2px rgba(233,30,99,0.15);
            outline: none;
        }
        .artist-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px 2px 10px;
            background: linear-gradient(135deg, #FFB7C5, #E91E63);
            color: #fff;
            border-radius: 12px;
            font-size: 0.82em;
            font-weight: 600;
            white-space: nowrap;
            line-height: 1.5;
        }
        .artist-tag-remove {
            cursor: pointer;
            opacity: 0.75;
            font-size: 1em;
            line-height: 1;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            flex-shrink: 0;
        }
        .artist-tag-remove:hover { opacity: 1; background: rgba(255,255,255,0.55); }
        .tag-input-field {
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.92em;
            min-width: 120px;
            flex: 1;
            padding: 2px 4px;
            height: 26px;
        }
        .artist-suggestions {
            position: absolute;
            left: 0;
            top: calc(100% + 3px);
            z-index: 2000;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.13);
            max-height: 220px;
            overflow-y: auto;
            min-width: 220px;
            display: none;
        }
        .artist-suggestion-item {
            padding: 8px 14px;
            cursor: pointer;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .artist-suggestion-item:hover,
        .artist-suggestion-item.active { background: #fdf2f8; }
        .artist-suggestion-icon {
            font-size: 0.8em;
            color: #9ca3af;
            background: #f3f4f6;
            border-radius: 4px;
            padding: 1px 5px;
            flex-shrink: 0;
        }
        .artist-suggestion-new { color: #6366f1; font-style: italic; }
        .tag-input-field::placeholder { color: #adb5bd; }

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
            width: 44px;  /* iOS minimum touch target */
            height: 44px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
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
            font-size: 1rem; /* ≥16px: ป้องกัน iOS auto-zoom */
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

        /* Mobile tab dropdown (hamburger) */
        .tab-mobile-menu {
            display: none; /* shown only on mobile via media query */
            position: relative;
            margin-bottom: 16px;
        }
        .tab-mobile-btn {
            width: 100%;
            padding: 13px 16px;
            background: var(--admin-gradient);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 48px;
            box-shadow: 0 2px 8px rgba(37,99,235,.2);
        }
        .tab-mobile-btn-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-mobile-arrow {
            transition: transform 0.2s;
            font-size: 0.8rem;
            opacity: 0.85;
        }
        .tab-mobile-menu.open .tab-mobile-arrow { transform: rotate(180deg); }
        .tab-mobile-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: var(--admin-surface);
            border: 1px solid var(--admin-border-light);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            z-index: 200;
            overflow: hidden;
        }
        .tab-mobile-menu.open .tab-mobile-dropdown { display: block; }
        .tab-mobile-item {
            width: 100%;
            padding: 13px 18px;
            background: none;
            border: none;
            border-bottom: 1px solid var(--admin-border-light);
            text-align: left;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--admin-text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 48px;
            transition: background 0.15s;
        }
        .tab-mobile-item:last-child { border-bottom: none; }
        .tab-mobile-item:hover { background: var(--admin-primary-light); color: var(--admin-primary); }
        .tab-mobile-item.active {
            background: var(--admin-primary-light);
            color: var(--admin-primary);
            font-weight: 700;
        }
        .tab-mobile-item.active::before { content: '▶ '; font-size: 0.7rem; margin-right: 4px; }

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
            width: 20px;
            height: 20px;
            min-width: 20px;
            min-height: 20px;
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

        /* =====================================================
           RESPONSIVE - MOBILE (iOS + Android)
           ===================================================== */

        /* ── 768px: Tablet & large phones ── */
        @media (max-width: 768px) {
            .admin-container {
                padding: 10px;
            }

            /* Tables: horizontal scroll via wrapper div (prevents iOS scroll capture) */
            .table-scroll-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch; /* iOS momentum scroll */
                width: 100%;
            }
            .events-table {
                white-space: nowrap;
            }
            .events-table th,
            .events-table td {
                white-space: nowrap;
                font-size: 0.85rem;
                padding: 10px 12px;
            }
            /* Title column: ให้ text wrap ได้และมีความกว้างขั้นต่ำ */
            .events-table td:nth-child(3) {
                white-space: normal;
                min-width: 140px;
                max-width: 200px;
            }
            .events-table .actions {
                white-space: nowrap;
            }

            /* btn-sm: เพิ่ม touch target */
            .btn-sm {
                padding: 10px 14px;
                min-height: 40px;
            }
        }

        /* ── 600px: Small tablets & large phones ── */
        @media (max-width: 600px) {
            /* Header */
            .admin-header {
                padding: 14px;
                gap: 10px;
            }
            .admin-header h1 {
                font-size: 1.25rem;
            }
            .admin-header > div {
                gap: 8px;
                flex-wrap: wrap;
            }
            .admin-header a {
                padding: 8px 10px;
                font-size: 0.85rem;
            }

            /* Tab bar: ซ่อนบน mobile ใช้ dropdown แทน */
            .admin-tabs { display: none; }
            .tab-mobile-menu { display: block; }

            /* Toolbar: better stacking */
            .admin-toolbar {
                padding: 12px;
                gap: 8px;
            }
            .admin-toolbar select,
            .admin-toolbar input[type="date"] {
                min-width: 0;
                flex: 1 1 calc(50% - 4px);
            }
            .search-wrapper {
                flex: 1 1 100%;
                min-width: 0;
            }
            .admin-toolbar .btn {
                flex: 1 1 calc(50% - 4px);
            }
            .admin-toolbar .btn-primary {
                flex: 1 1 100%;
            }

            /* Bulk actions bar: stack vertically */
            .bulk-actions-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 12px;
            }
            .bulk-actions-buttons {
                width: 100%;
                justify-content: flex-start;
            }
            .bulk-actions-buttons .btn {
                flex: 1 1 calc(50% - 4px);
            }
        }

        /* ── 480px: Small phones (iPhone SE, Moto G) ── */
        @media (max-width: 480px) {
            .admin-container {
                padding: 6px;
            }
            .admin-header {
                border-radius: 8px;
            }
            .admin-header h1 {
                font-size: 1.1rem;
            }
            .admin-tabs {
                border-radius: 8px;
                margin-bottom: 16px;
            }
            .admin-toolbar {
                border-radius: 8px;
                padding: 10px;
            }

            /* Table: smaller text on very small phones */
            .events-table th,
            .events-table td {
                padding: 8px 10px;
                font-size: 0.8rem;
            }

            /* Modal: ขยายให้ใกล้เต็มจอ */
            .modal {
                width: 96%;
                border-radius: 10px;
            }
            .modal-body {
                padding: 14px;
            }
            .modal-footer {
                padding: 12px 14px;
                gap: 8px;
            }
            .modal-footer .btn {
                flex: 1;
            }

            /* Form row: stack เสมอบน mobile เล็ก */
            .form-row {
                flex-direction: column;
            }

            /* Badge */
            .badge {
                min-width: 16px;
                height: 16px;
                font-size: 0.65rem;
            }

            /* Pagination */
            .pagination {
                gap: 6px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>Admin - <?php echo htmlspecialchars(get_site_title()); ?></h1>
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="color: rgba(255, 255, 255, 0.95); font-weight: 600;">สวัสดี, <?php echo htmlspecialchars($adminUsername); ?> <small style="opacity:0.7; font-weight:400;">(<?php echo htmlspecialchars($adminRole); ?>)</small></span>
                <?php if ($adminUserId !== null): ?>
                <a href="#" onclick="showChangePasswordModal(); return false;" style="background: rgba(255, 255, 255, 0.15); color: white;">🔑 Change Password</a>
                <?php endif; ?>
                <a href="help.php">📖 Help</a>
                <a href="../index.php">&larr; กลับหน้าหลัก</a>
                <a href="login.php?logout=1" style="background: rgba(239, 68, 68, 0.2); color: white;">Logout</a>
            </div>
        </div>

        <!-- Mobile Tab Dropdown (hamburger) -->
        <div class="tab-mobile-menu" id="tabMobileMenu">
            <button class="tab-mobile-btn" id="tabMobileBtn"
                    onclick="toggleTabMobileMenu()"
                    aria-haspopup="true" aria-expanded="false">
                <span class="tab-mobile-btn-left">
                    <span>☰</span>
                    <span id="tabMobileLabel">🎵 Programs</span>
                    <span class="badge" id="pendingBadgeMobile" style="display:none">0</span>
                </span>
                <span class="tab-mobile-arrow">▼</span>
            </button>
            <div class="tab-mobile-dropdown" id="tabMobileDropdown" role="menu">
                <button class="tab-mobile-item active" onclick="switchTab('programs')" data-tab="programs" role="menuitem">🎵 Programs</button>
                <button class="tab-mobile-item" onclick="switchTab('events')" data-tab="events" role="menuitem">🎪 Events</button>
                <button class="tab-mobile-item" onclick="switchTab('requests')" data-tab="requests" role="menuitem">
                    📝 Requests <span class="badge" id="pendingBadgeMobile2" style="display:none">0</span>
                </button>
                <button class="tab-mobile-item" onclick="switchTab('credits')" data-tab="credits" role="menuitem">✨ Credits</button>
                <button class="tab-mobile-item" onclick="switchTab('import')" data-tab="import" role="menuitem">📤 Import</button>
                <?php if ($adminRole === 'admin'): ?>
                <button class="tab-mobile-item" onclick="switchTab('artists')" data-tab="artists" role="menuitem">🎤 Artists</button>                    
                <button class="tab-mobile-item" onclick="switchTab('users')" data-tab="users" role="menuitem">👤 Users</button>
                <button class="tab-mobile-item" onclick="switchTab('backup')" data-tab="backup" role="menuitem">💾 Backup</button>
                <button class="tab-mobile-item" onclick="switchTab('settings')" data-tab="settings" role="menuitem">⚙️ Settings</button>
                <button class="tab-mobile-item" onclick="switchTab('contact')" data-tab="contact" role="menuitem">✉️ Contact</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs (desktop) -->
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="switchTab('programs')">🎵 Programs</button>
            <button class="tab-btn" onclick="switchTab('events')">🎪 Events</button>
            <button class="tab-btn" onclick="switchTab('requests')">📝 Requests <span class="badge" id="pendingBadge" style="display:none">0</span></button>
            <button class="tab-btn" onclick="switchTab('credits')">✨ Credits</button>
            <button class="tab-btn" onclick="switchTab('import')">📤 Import</button>
            <?php if ($adminRole === 'admin'): ?>
            <button class="tab-btn" onclick="switchTab('artists')">🎤 Artists</button>
            <button class="tab-btn" onclick="switchTab('users')">👤 Users</button>
            <button class="tab-btn" onclick="switchTab('backup')">💾 Backup</button>
            <button class="tab-btn" onclick="switchTab('settings')">⚙️ Settings</button>
            <button class="tab-btn" onclick="switchTab('contact')">✉️ Contact</button>
            <?php endif; ?>
        </div>

        <!-- Programs Section -->
        <div id="programsSection">
        <!-- Toolbar -->
        <div class="admin-toolbar">
            <select id="eventMetaFilter" onchange="currentPage=1;loadPrograms()">
                <option value="">All Events</option>
            </select>
            <div class="search-wrapper">
                <input type="text" id="searchInput" placeholder="ค้นหา..." onkeyup="handleSearch(event)">
                <button type="button" class="clear-search" onclick="clearSearch()" title="Clear">&times;</button>
            </div>
            <select id="venueFilter" onchange="loadPrograms()">
                <option value="">ทุกเวที</option>
            </select>
            <input type="date" id="dateFrom" onchange="loadPrograms()" title="จากวันที่">
            <input type="date" id="dateTo" onchange="loadPrograms()" title="ถึงวันที่">
            <button class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
            <select id="perPageSelect" onchange="changePerPage()" title="จำนวนต่อหน้า">
                <option value="20" selected>20 / หน้า</option>
                <option value="50">50 / หน้า</option>
                <option value="100">100 / หน้า</option>
            </select>
            <button class="btn btn-primary" onclick="openAddModal()">+ เพิ่ม Program</button>
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
        <div class="table-scroll-wrapper">
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
                    <th class="sortable" onclick="sortBy('categories')">Artist / Group <span class="sort-icon" data-col="categories"></span></th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="eventsTableBody">
                <tr>
                    <td colspan="6" class="loading">Loading...</td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="pagination"></div>
        </div><!-- End programsSection -->

        <!-- Requests Section -->
        <div id="requestsSection" style="display:none">
            <div class="admin-toolbar">
                <select id="reqEventMetaFilter" onchange="reqPage=1;loadRequests()">
                    <option value="">All Events</option>
                </select>
                <select id="reqStatusFilter" onchange="loadRequests()">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" selected>รอดำเนินการ</option>
                    <option value="approved">อนุมัติแล้ว</option>
                    <option value="rejected">ปฏิเสธแล้ว</option>
                </select>
            </div>
            <div class="table-scroll-wrapper">
            <table class="events-table">
                <thead>
                    <tr><th>#</th><th>ประเภท</th><th>ชื่อ Program</th><th>ผู้แจ้ง</th><th>วันที่</th><th>สถานะ</th><th>Actions</th></tr>
                </thead>
                <tbody id="requestsBody"><tr><td colspan="7">Loading...</td></tr></tbody>
            </table>
            </div>
            <div class="pagination" id="reqPagination"></div>
        </div>

        <!-- Import ICS Section -->
        <div id="importSection" style="display:none">
            <!-- Upload Area -->
            <div class="upload-area" id="uploadArea">
                <div style="max-width: 400px; margin: 0 auto 20px; display: flex; flex-direction: column; gap: 14px;">
                    <div class="form-group">
                        <label for="icsImportEventMeta" style="font-weight: 600; margin-bottom: 6px; display: block;">📦 Import ไปยัง Event:</label>
                        <select id="icsImportEventMeta" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95em;">
                            <option value="">-- เลือก Event --</option>
                        </select>
                        <small class="form-hint" style="color: #888; font-size: 0.85em;">เลือก event ที่ต้องการ import programs เข้าไป</small>
                    </div>
                    <div class="form-group">
                        <label for="icsDefaultType" style="font-weight: 600; margin-bottom: 6px; display: block;">🏷️ Program Type (default):</label>
                        <input type="text" id="icsDefaultType" list="icsDefaultTypeList"
                               style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95em;"
                               placeholder="stage, booth, meet &amp; greet, ... (ไม่บังคับ)">
                        <datalist id="icsDefaultTypeList"></datalist>
                        <small class="form-hint" style="color: #888; font-size: 0.85em;">ใช้สำหรับ programs ที่ไม่มี <code>X-PROGRAM-TYPE</code> ในไฟล์ ICS</small>
                    </div>
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
                    <h3>📋 ตัวอย่าง Programs (<span id="previewCount">0</span>)</h3>
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

                <!-- Artist Mapping Section -->
                <div id="artistMappingSection" style="display:none; margin-bottom:20px; border:2px solid #fbbf24; border-radius:10px; overflow:hidden;">
                    <div style="background:#fef3c7; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <strong>🎤 Artist Mapping — พบชื่อศิลปินที่ยังไม่มีใน database (<span id="unmatchedCount">0</span> รายการ)</strong>
                        <small style="color:#92400e;">กำหนด mapping ก่อน confirm import เพื่อให้ระบบสร้าง artist links อัตโนมัติ</small>
                    </div>
                    <div style="overflow-x:auto; background:white;">
                        <table class="events-table" style="margin:0; border-radius:0;">
                            <thead>
                                <tr>
                                    <th>ชื่อใน ICS (categories)</th>
                                    <th style="width:60px; text-align:center;">Programs</th>
                                    <th style="width:160px;">Action</th>
                                    <th id="artistMappingTargetHeader" style="min-width:220px;">ปลายทาง</th>
                                </tr>
                            </thead>
                            <tbody id="artistMappingBody"></tbody>
                        </table>
                    </div>
                </div>

                <div style="overflow-x:auto">
                    <table class="events-table preview-table">
                        <thead>
                            <tr>
                                <th style="width:40px"><input type="checkbox" id="selectAllCheckbox" onchange="toggleAllCheckboxes()"></th>
                                <th style="width:100px">สถานะ</th>
                                <th>ชื่อ Program</th>
                                <th>วันที่/เวลา</th>
                                <th>สถานที่</th>
                                <th>ศิลปินที่เกี่ยวข้อง</th>
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
                        ✅ ยืนยันการ Import (<span id="importCount">0</span> programs)
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
                        <div class="summary-item">
                            <span class="summary-icon">🎤</span>
                            <span class="summary-label">Artist links:</span>
                            <span class="summary-value" id="summaryArtistLinks">0</span>
                        </div>
                    </div>
                    <div id="errorsList"></div>
                    <button class="btn btn-primary" onclick="resetUpload(); switchTab('programs')">
                        ดู Programs ที่ Import แล้ว
                    </button>
                </div>
            </div>
        </div>

        <!-- Credits Section -->
        <div id="creditsSection" style="display:none">
            <div class="admin-toolbar">
                <select id="creditsEventMetaFilter" onchange="creditsCurrentPage=1;loadCredits()">
                    <option value="">All Events</option>
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
            <div class="table-scroll-wrapper">
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
            </div>

            <!-- Pagination -->
            <div class="pagination" id="creditsPagination"></div>
        </div>

        <!-- Events Section -->
        <div id="eventsSection" style="display:none">
            <div class="admin-toolbar">
                <div class="search-wrapper">
                    <input type="text" id="conventionsSearchInput" placeholder="ค้นหา events..." onkeyup="handleEventsSearch(event)">
                    <button type="button" class="clear-search" onclick="clearEventsSearch()" title="Clear">&times;</button>
                </div>
                <button class="btn btn-primary" onclick="openAddEventModal()">+ เพิ่ม Event</button>
            </div>

            <!-- Events Table -->
            <div class="table-scroll-wrapper">
            <table class="events-table">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortEventsBy('id')"># <span class="sort-icon" data-col="id"></span></th>
                        <th class="sortable" onclick="sortEventsBy('name')">Name <span class="sort-icon" data-col="name"></span></th>
                        <th>Slug</th>
                        <th class="sortable" onclick="sortEventsBy('start_date')">Start Date <span class="sort-icon" data-col="start_date"></span></th>
                        <th class="sortable" onclick="sortEventsBy('end_date')">End Date <span class="sort-icon" data-col="end_date"></span></th>
                        <th>Venue Mode</th>
                        <th class="sortable" onclick="sortEventsBy('is_active')">Active <span class="sort-icon" data-col="is_active"></span></th>
                        <th class="sortable" onclick="sortEventsBy('event_count')">Programs <span class="sort-icon" data-col="event_count"></span></th>
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
        </div>

        <!-- Users Section (admin only) -->
        <?php if ($adminRole === 'admin'): ?>
        <div id="usersSection" style="display:none">
            <div class="admin-toolbar">
                <button class="btn btn-primary" onclick="openAddUserModal()">+ Add User</button>
            </div>

            <div class="table-scroll-wrapper">
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
        </div>
        <?php endif; ?>

        <!-- Backup Section -->
        <?php if ($adminRole === 'admin'): ?>
        <div id="backupSection" style="display:none">
            <div class="admin-toolbar">
                <button class="btn btn-primary" onclick="createBackup()">💾 สร้าง Backup</button>
                <button class="btn btn-secondary" onclick="openUploadRestoreModal()">📤 Upload & Restore</button>
            </div>

            <div class="table-scroll-wrapper">
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
        </div>
        <?php endif; ?>

        <!-- Contact Channels Section -->
        <?php if ($adminRole === 'admin'): ?>
        <div id="contactSection" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
                <h3 style="margin:0">✉️ ช่องทางติดต่อ</h3>
                <button class="btn btn-primary" onclick="openChannelModal(null)">➕ เพิ่มช่องทาง</button>
            </div>
            <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:50px">Icon</th>
                        <th>ชื่อ / รายละเอียด</th>
                        <th>URL</th>
                        <th style="width:60px;text-align:center">Active</th>
                        <th style="width:130px">Actions</th>
                    </tr>
                </thead>
                <tbody id="contactChannelsTbody">
                    <tr><td colspan="5" class="loading">Loading...</td></tr>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Artists Section -->
        <div id="artistsSection" style="display:none">
            <div class="admin-toolbar">
                <div class="search-wrapper">
                    <input type="text" id="artistsSearchInput" placeholder="ค้นหาศิลปิน..." onkeyup="handleArtistsSearch(event)">
                    <button type="button" class="clear-search" onclick="clearArtistsSearch()" title="Clear">&times;</button>
                </div>
                <select id="artistsTypeFilter" onchange="artistsCurrentPage=1;loadArtists()">
                    <option value="">ทั้งหมด</option>
                    <option value="1">กลุ่ม (Group)</option>
                    <option value="0">บุคคล (Solo/Member)</option>
                </select>
                <select id="artistsPerPageSelect" onchange="changeArtistsPerPage()">
                    <option value="50" selected>50 / หน้า</option>
                    <option value="100">100 / หน้า</option>
                </select>
                <button class="btn btn-primary" onclick="openAddArtistModal()">+ เพิ่มศิลปิน</button>
                <button class="btn btn-secondary" onclick="openImportArtistsModal()">📥 Import หลายคน</button>
            </div>

            <!-- Bulk Actions Toolbar -->
            <div id="artistsBulkToolbar" style="display:none;align-items:center;gap:8px;padding:8px 12px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;margin-bottom:8px;flex-wrap:wrap">
                <span id="artistsBulkCount" style="font-weight:600;color:#856404"></span>
                <button class="btn btn-secondary btn-sm" onclick="openBulkAddToGroupModal()">👥 เพิ่มเข้ากลุ่ม</button>
                <button class="btn btn-secondary btn-sm" onclick="artistsBulkClearGroup()">🚫 ถอดออกจากกลุ่ม</button>
                <button class="btn btn-secondary btn-sm" onclick="clearArtistSelection()">✕ ยกเลิก</button>
            </div>

            <div class="table-scroll-wrapper">
            <table class="events-table">
                <thead>
                    <tr>
                        <th style="width:36px;text-align:center"><input type="checkbox" id="artistsSelectAll" onchange="selectAllArtists(this.checked)" style="width:16px;height:16px;cursor:pointer" title="เลือกทั้งหมด"></th>
                        <th class="sortable" onclick="sortArtistsBy('id')"># <span class="sort-icon" data-col="id"></span></th>
                        <th class="sortable" onclick="sortArtistsBy('name')">ชื่อ <span class="sort-icon" data-col="name"></span></th>
                        <th class="sortable" onclick="sortArtistsBy('is_group')">ประเภท <span class="sort-icon" data-col="is_group"></span></th>
                        <th>กลุ่มที่สังกัด</th>
                        <th title="จำนวน variant names">Variants</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="artistsTableBody">
                    <tr><td colspan="7" class="loading">Loading...</td></tr>
                </tbody>
            </table>
            </div>
            <div class="pagination" id="artistsPagination"></div>
        </div>

        <!-- Settings Section -->
        <?php if ($adminRole === 'admin'): ?>
        <div id="settingsSection" style="display:none">
            <div style="max-width:600px;margin:0 auto;padding:20px 0">
                <h3 style="margin-bottom:8px">📝 Site Title</h3>
                <p style="color:#6c757d;margin-bottom:12px">ชื่อเว็บไซต์ที่แสดงใน browser tab, header และ ICS export</p>
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:32px;flex-wrap:wrap">
                    <input type="text" id="siteTitleInput" maxlength="100"
                           style="padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:1rem;flex:1;min-width:200px"
                           placeholder="Idol Stage Timetable">
                    <button class="btn btn-primary" onclick="saveTitleSetting()" id="titleSaveBtn">💾 บันทึก Title</button>
                    <span id="titleSaveMsg" style="display:none;color:green;font-weight:600">✅ บันทึกแล้ว</span>
                </div>

                <h3 style="margin-bottom:8px">🎨 Site Theme</h3>
                <p style="color:#6c757d;margin-bottom:24px">เลือก theme สำหรับหน้าเว็บ public ทั้งหมด</p>

                <div id="themePicker" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px">
                    <!-- populated by loadThemeSettings() -->
                </div>

                <button class="btn btn-primary" onclick="saveThemeSetting()" id="themeSaveBtn">💾 บันทึก Theme</button>
                <span id="themeSaveMsg" style="margin-left:12px;display:none;color:green;font-weight:600">✅ บันทึกแล้ว</span>

                <hr style="margin:32px 0;border:none;border-top:1px solid #e9ecef">
                <h3 style="margin-bottom:8px">⚠️ Disclaimer (ข้อจำกัดความรับผิดชอบ)</h3>
                <p style="color:#6c757d;margin-bottom:16px">ข้อความ disclaimer ที่แสดงในหน้า "ติดต่อเรา" รองรับ 3 ภาษา หากเว้นว่างจะใช้ค่า default จาก translations.js</p>

                <div style="margin-bottom:16px">
                    <label style="font-weight:600;display:block;margin-bottom:6px">🇹🇭 ภาษาไทย</label>
                    <textarea id="disclaimerTh" rows="3"
                        style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:0.95rem;box-sizing:border-box;font-family:inherit"
                        placeholder="ข้อความ disclaimer ภาษาไทย..."></textarea>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;display:block;margin-bottom:6px">🇬🇧 English</label>
                    <textarea id="disclaimerEn" rows="3"
                        style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:0.95rem;box-sizing:border-box;font-family:inherit"
                        placeholder="Disclaimer text in English..."></textarea>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;display:block;margin-bottom:6px">🇯🇵 日本語</label>
                    <textarea id="disclaimerJa" rows="3"
                        style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:0.95rem;box-sizing:border-box;font-family:inherit"
                        placeholder="免責事項（日本語）..."></textarea>
                </div>
                <button class="btn btn-primary" onclick="saveDisclaimerSetting()" id="disclaimerSaveBtn">💾 บันทึก Disclaimer</button>
                <span id="disclaimerSaveMsg" style="margin-left:12px;display:none;color:green;font-weight:600">✅ บันทึกแล้ว</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Artist Modal -->
    <div class="modal-overlay" id="artistModal">
        <div class="modal" style="max-width: 480px;">
            <div class="modal-header">
                <h2 id="artistModalTitle">เพิ่มศิลปิน</h2>
                <button class="modal-close" onclick="closeArtistModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="artistForm" onsubmit="saveArtist(event)">
                    <input type="hidden" id="artistId">
                    <input type="hidden" id="artistCopySourceId">

                    <div class="form-group">
                        <label for="artistName">ชื่อศิลปิน / กลุ่ม *</label>
                        <input type="text" id="artistName" required maxlength="200" placeholder="ชื่อศิลปินหรือกลุ่ม">
                    </div>

                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal">
                            <input type="checkbox" id="artistIsGroup" onchange="onArtistIsGroupChange()" style="width:18px;height:18px;accent-color:var(--admin-primary)">
                            เป็นกลุ่ม (Group)
                        </label>
                        <small class="form-hint">เปิดเมื่อนี่คือกลุ่ม/วง; ปิดเมื่อนี่คือสมาชิก/ศิลปินเดี่ยว</small>
                    </div>

                    <div class="form-group" id="artistGroupIdRow">
                        <label for="artistGroupId">กลุ่มที่สังกัด</label>
                        <select id="artistGroupId">
                            <option value="">-- ไม่สังกัดกลุ่ม / ศิลปินเดี่ยว --</option>
                        </select>
                        <small class="form-hint">เลือกกลุ่มที่ศิลปินนี้เป็นสมาชิก (ว่าง = ศิลปินเดี่ยว หรือสมาชิกที่ยังไม่ได้ระบุกลุ่ม)</small>
                    </div>

                    <!-- Copy Variants Section (shown only in copy mode) -->
                    <div id="artistCopyVariantsSection" style="display:none">
                        <hr style="margin:12px 0;border:none;border-top:1px solid #e9ecef">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <label style="font-weight:600;margin:0">Variants ที่จะ copy</label>
                            <div style="display:flex;gap:6px">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="copyVariantsSelectAll(true)">เลือกทั้งหมด</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="copyVariantsSelectAll(false)">ยกเลิกทั้งหมด</button>
                            </div>
                        </div>
                        <div id="artistCopyVariantsList" style="max-height:180px;overflow-y:auto;border:1px solid #e9ecef;border-radius:6px;padding:8px;background:#fafafa">
                            <span style="color:#9ca3af;font-size:0.9em">กำลังโหลด...</span>
                        </div>
                        <small style="color:#6c757d;font-size:0.85em;display:block;margin-top:6px">เลือก variants ที่ต้องการ copy ไปยังศิลปินใหม่ สามารถเพิ่ม/ลบเพิ่มเติมได้ภายหลัง</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeArtistModal()">ยกเลิก</button>
                <button type="submit" form="artistForm" class="btn btn-primary">บันทึก</button>
            </div>
        </div>
    </div>

    <!-- Delete Artist Modal -->
    <div class="modal-overlay" id="deleteArtistModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h2>ยืนยันการลบ</h2>
                <button class="modal-close" onclick="closeDeleteArtistModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบ "<span id="deleteArtistName"></span>" หรือไม่?</p>
                <input type="hidden" id="deleteArtistId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteArtistModal()">ยกเลิก</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteArtist()">ลบ</button>
            </div>
        </div>
    </div>

    <!-- Artist Variants Modal -->
    <div class="modal-overlay" id="artistVariantsModal">
        <div class="modal" style="max-width: 540px;">
            <div class="modal-header">
                <h2>Variants: <span id="artistVariantsName" style="font-weight:700"></span></h2>
                <button class="modal-close" onclick="closeArtistVariantsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:#6c757d;font-size:0.9em;margin-bottom:12px">
                    Variant names คือชื่อสะกดอื่นๆ ของศิลปินนี้ (เช่น ตัวพิมพ์ใหญ่/เล็กต่างกัน หรือรูปแบบ "ชื่อ - กลุ่ม")
                    ใช้สำหรับ auto-match ตอน ICS import
                </p>
                <div id="artistVariantsList" style="min-height:40px;margin-bottom:16px">
                    <span style="color:#9ca3af">Loading...</span>
                </div>
                <div style="display:flex;gap:8px;align-items:stretch">
                    <input type="text" id="newVariantInput" maxlength="200"
                        placeholder="เพิ่ม variant name..."
                        style="flex:1;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:0.95rem"
                        onkeydown="if(event.key==='Enter'){event.preventDefault();addArtistVariant();}">
                    <button class="btn btn-primary" onclick="addArtistVariant()">+ เพิ่ม</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeArtistVariantsModal()">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Bulk Add Artists to Group Modal -->
    <div class="modal-overlay" id="bulkAddToGroupModal">
        <div class="modal" style="max-width: 460px;">
            <div class="modal-header">
                <h2>👥 เพิ่มเข้ากลุ่ม</h2>
                <button class="modal-close" onclick="closeBulkAddToGroupModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:#6c757d;margin-bottom:16px">เลือกกลุ่มที่ต้องการเพิ่มศิลปินที่เลือกทั้งหมดเข้า</p>
                <div class="form-group">
                    <label for="bulkGroupSelect">กลุ่มปลายทาง *</label>
                    <select id="bulkGroupSelect" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:0.95rem">
                        <option value="">-- เลือกกลุ่ม --</option>
                    </select>
                </div>
                <p id="bulkGroupNote" style="font-size:0.85em;color:#6c757d">หมายเหตุ: ศิลปินที่เป็น "กลุ่ม" จะถูกข้ามไป เฉพาะ "บุคคล/Solo" เท่านั้นที่จะถูกอัปเดต</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkAddToGroupModal()">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="confirmBulkAddToGroup()">บันทึก</button>
            </div>
        </div>
    </div>

    <!-- Import Artists Modal -->
    <div class="modal-overlay" id="importArtistsModal">
        <div class="modal" style="max-width: 560px;">
            <div class="modal-header">
                <h2>📥 Import ศิลปินหลายคน</h2>
                <button class="modal-close" onclick="closeImportArtistsModal()">&times;</button>
            </div>
            <div class="modal-body">

                <!-- Step 1: Input -->
                <div id="importArtistsStep1">
                    <div class="form-group">
                        <label for="importArtistsTextarea" style="font-weight:600">รายชื่อศิลปิน <span style="font-weight:normal;color:#6c757d">(1 บรรทัด = 1 ศิลปิน)</span></label>
                        <textarea id="importArtistsTextarea" rows="10"
                            style="width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:0.95rem;font-family:inherit;box-sizing:border-box;resize:vertical"
                            placeholder="ชื่อศิลปิน 1&#10;ชื่อศิลปิน 2&#10;ชื่อศิลปิน 3&#10;..."></textarea>
                        <small style="color:#6c757d">บรรทัดว่างและชื่อที่ซ้ำกันจะถูกข้ามอัตโนมัติ</small>
                    </div>
                    <div class="form-group">
                        <label for="importArtistsIsGroup" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal">
                            <input type="checkbox" id="importArtistsIsGroup" onchange="onImportIsGroupChange()" style="width:16px;height:16px;accent-color:var(--admin-primary)">
                            <span style="font-weight:600">เป็นกลุ่ม (Group)</span>
                        </label>
                        <small class="form-hint">เปิดเมื่อรายชื่อทั้งหมดนี้คือกลุ่ม/วง</small>
                    </div>
                    <div class="form-group" id="importArtistsGroupRow">
                        <label for="importArtistsGroupSelect">เพิ่มเข้ากลุ่ม <span style="font-weight:normal;color:#6c757d">(ถ้าต้องการ)</span></label>
                        <select id="importArtistsGroupSelect" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:0.95rem">
                            <option value="">-- ไม่สังกัดกลุ่ม --</option>
                        </select>
                        <small class="form-hint">เลือกกลุ่มที่ศิลปินที่ import จะสังกัด (ไม่บังคับ)</small>
                    </div>
                </div>

                <!-- Step 2: Results -->
                <div id="importArtistsStep2" style="display:none">
                    <div id="importArtistsSummary" style="margin-bottom:12px;padding:10px 14px;border-radius:6px;background:#f0fdf4;border:1px solid #bbf7d0;font-weight:600"></div>
                    <div id="importArtistsResults" style="max-height:320px;overflow-y:auto;border:1px solid #e9ecef;border-radius:6px;background:#fafafa;padding:8px"></div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeImportArtistsModal()">ปิด</button>
                <button type="button" class="btn btn-secondary" id="importArtistsBackBtn" style="display:none" onclick="importArtistsGoBack()">← กลับ</button>
                <button type="button" class="btn btn-primary" id="importArtistsSubmitBtn" onclick="submitImportArtists()">นำเข้า</button>
            </div>
        </div>
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
                            <option value="agent">Agent - Programs management only</option>
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

    <!-- Contact Channel Modal -->
    <?php if ($adminRole === 'admin'): ?>
    <div class="modal-overlay" id="channelModal" style="display:none">
        <div class="modal" style="max-width:480px">
            <div class="modal-header">
                <h2 id="channelModalTitle">เพิ่มช่องทางติดต่อ</h2>
                <button class="modal-close" onclick="closeChannelModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="channelForm" onsubmit="submitChannelForm(event)">
                    <div class="form-group">
                        <label>Icon (emoji)</label>
                        <input type="text" id="chIcon" maxlength="10" placeholder="💬" style="font-size:1.3em;width:80px">
                    </div>
                    <div class="form-group">
                        <label>ชื่อช่องทาง <span style="color:red">*</span></label>
                        <input type="text" id="chTitle" required maxlength="100" placeholder="เช่น Twitter (X), Line, Email">
                    </div>
                    <div class="form-group">
                        <label>รายละเอียด</label>
                        <input type="text" id="chDescription" maxlength="200" placeholder="เช่น ติดตามข่าวสารและอัปเดต">
                    </div>
                    <div class="form-group">
                        <label>URL / Contact</label>
                        <input type="text" id="chUrl" maxlength="500" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label>ลำดับการแสดง</label>
                        <input type="number" id="chOrder" value="0" min="0" max="999" style="width:100px">
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" id="chActive" checked> แสดงในหน้าติดต่อเรา (Active)
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeChannelModal()">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">💾 บันทึก</button>
                    </div>
                </form>
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
                <h2 id="modalTitle">เพิ่ม Program</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="eventForm" onsubmit="saveEvent(event)">
                    <input type="hidden" id="eventId">

                    <div class="form-group">
                        <label for="eventConvention">Event</label>
                        <select id="eventConvention">
                            <option value="">-- ไม่ระบุ --</option>
                            <!-- populated from event_meta_list -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">ชื่อ Program *</label>
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
                        <label>Artist / Group</label>
                        <div class="tag-input-wrapper" id="artistTagWrapper">
                            <input type="text" id="categoriesInput" class="tag-input-field"
                                   placeholder="พิมพ์ชื่อ artist…" autocomplete="off">
                            <div class="artist-suggestions" id="artistSuggestions"></div>
                        </div>
                        <input type="hidden" id="categories">
                        <small class="form-hint">พิมพ์แล้วเลือกจาก dropdown หรือกด <kbd>Enter</kbd> / <kbd>,</kbd> เพื่อเพิ่ม · ศิลปินใหม่จะถูกสร้างอัตโนมัติ</small>
                    </div>

                    <div class="form-group">
                        <label for="programType">ประเภท (Program Type)</label>
                        <input type="text" id="programType" list="programTypesListMain" placeholder="stage, booth, meet &amp; greet, ...">
                        <datalist id="programTypesListMain">
                            <!-- Program types loaded dynamically -->
                        </datalist>
                        <small class="form-hint">เลือกจาก dropdown หรือพิมพ์ประเภทใหม่ได้</small>
                    </div>

                    <div class="form-group">
                        <label for="streamUrl">🔴 Live Stream URL</label>
                        <input type="url" id="streamUrl" placeholder="https://www.instagram.com/... หรือ https://x.com/...">
                        <small class="form-hint">ลิงก์ IG Live, X Spaces, YouTube Live ฯลฯ (เว้นว่างได้ถ้าไม่มี)</small>
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
                <p>คุณต้องการลบ program "<span id="deleteEventTitle"></span>" หรือไม่?</p>
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
                        กำลังแก้ไข <strong><span id="bulkEditCount">0</span></strong> programs
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
                        <small class="form-hint">กรอกเพื่ออัปเดต organizer ของ programs ทั้งหมดที่เลือก</small>
                    </div>

                    <div class="form-group">
                        <label>Artist / Group</label>
                        <div class="tag-input-wrapper" id="bulkArtistTagWrapper">
                            <input type="text" id="bulkCategoriesInput" class="tag-input-field"
                                   placeholder="-- ไม่เปลี่ยนแปลง --" autocomplete="off">
                            <div class="artist-suggestions" id="bulkArtistSuggestions"></div>
                        </div>
                        <input type="hidden" id="bulkEditCategories">
                        <small class="form-hint">เพิ่ม tag เพื่ออัปเดต artist/group ของ programs ทั้งหมดที่เลือก · ว่าง = ไม่เปลี่ยนแปลง</small>
                    </div>

                    <div class="form-group">
                        <label for="bulkEditProgramType">Program Type (ประเภท)</label>
                        <input type="text" id="bulkEditProgramType" class="form-control"
                               list="bulkProgramTypesList"
                               placeholder="-- ไม่เปลี่ยนแปลง --">
                        <datalist id="bulkProgramTypesList">
                            <!-- Program types loaded dynamically -->
                        </datalist>
                        <small class="form-hint">กรอกเพื่ออัปเดต program type ของ programs ทั้งหมดที่เลือก</small>
                    </div>

                    <div class="form-warning">
                        ⚠️ การแก้ไขจะเปลี่ยนค่าของ programs ทั้งหมดที่เลือกทันที
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
                    คุณกำลังจะลบ <strong><span id="bulkDeleteCount">0</span></strong> programs
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
                        <label for="creditEventMetaId">Event</label>
                        <select id="creditEventMetaId">
                            <option value="">-- ทุก Event (Global) --</option>
                        </select>
                        <small class="form-hint">เลือก event ที่ credit นี้สังกัด (ว่าง = แสดงทุก event)</small>
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

    <!-- Add/Edit Event Modal -->
    <div class="modal-overlay" id="conventionModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="conventionModalTitle">เพิ่ม Event</h2>
                <button class="modal-close" onclick="closeEventMetaModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="conventionForm" onsubmit="saveEventMeta(event)">
                    <input type="hidden" id="conventionId">

                    <div class="form-group">
                        <label for="conventionName">Name *</label>
                        <input type="text" id="conventionName" required maxlength="200" placeholder="ชื่อ Event">
                    </div>

                    <div class="form-group">
                        <label for="conventionSlug">Slug *</label>
                        <input type="text" id="conventionSlug" required maxlength="100" placeholder="event-slug" pattern="[a-z0-9\-]+">
                        <small class="form-hint">ตัวอักษรพิมพ์เล็ก ตัวเลข และ - เท่านั้น</small>
                    </div>

                    <div class="form-group">
                        <label for="conventionEmail">Contact Email</label>
                        <input type="email" id="conventionEmail" maxlength="200" placeholder="contact@event.com">
                        <small class="form-hint">ใช้ใน ICS export — <code>ORGANIZER;CN="ชื่องาน":mailto:email</code></small>
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
                                <option value="calendar">Calendar</option>
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

                    <div class="form-group">
                        <label for="conventionTheme">Theme</label>
                        <select id="conventionTheme">
                            <option value="">-- ใช้ Global Theme (จาก Settings) --</option>
                            <option value="sakura">🌸 Sakura</option>
                            <option value="ocean">🌊 Ocean</option>
                            <option value="forest">🌿 Forest</option>
                            <option value="midnight">🌙 Midnight</option>
                            <option value="sunset">☀️ Sunset</option>
                            <option value="dark">🖤 Dark</option>
                            <option value="gray">🩶 Gray</option>
                        </select>
                        <small class="form-hint">ธีมเฉพาะ event นี้ — ถ้าไม่เลือก จะใช้ Global Theme จาก Settings (fallback: Dark)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEventMetaModal()">ยกเลิก</button>
                <button type="submit" form="conventionForm" class="btn btn-primary">บันทึก</button>
            </div>
        </div>
    </div>

    <!-- Delete Event Modal -->
    <div class="modal-overlay" id="deleteConventionModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h2>ยืนยันการลบ</h2>
                <button class="modal-close" onclick="closeDeleteEventModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>คุณกำลังจะลบ event "<span id="deleteConventionName"></span>" หรือไม่?</p>
                <p class="form-warning">⚠️ Programs ที่อยู่ใน event นี้จะไม่ถูกลบ แต่จะไม่มี event อ้างอิง</p>
                <input type="hidden" id="deleteConventionId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteEventModal()">ยกเลิก</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteEventMeta()">ลบ</button>
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
        const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;
        const APP_ROOT = <?php echo json_encode(rtrim(dirname(get_base_path()), '/')); ?>;

        // State
        let currentPage = 1;
        let perPage = 20;
        let venues = [];
        let searchTimeout = null;
        let sortColumn = 'start';
        let sortDirection = 'desc';
        let formChanged = false;
        let originalFormData = null;

        // Artists state
        let artistsCurrentPage = 1;
        let artistsPerPage = 50;
        let artistsSortColumn = 'name';
        let artistsSortDirection = 'asc';
        let artistsSearchTimeout = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadEventMetaOptions();
            loadVenues();
            loadTypesForDatalist();
            loadPrograms();
            loadPendingCount();
            setupFormChangeTracking();
            setupKeyboardShortcuts();
        });

        // Tab switching
        function switchTab(tab) {
            // Desktop tabs
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');

            // Mobile dropdown: update active item + label
            document.querySelectorAll('.tab-mobile-item').forEach(b => b.classList.remove('active'));
            const mobileItem = document.querySelector(`.tab-mobile-item[data-tab="${tab}"]`);
            if (mobileItem) {
                mobileItem.classList.add('active');
                const labelMap = {
                    programs: '🎵 Programs', events: '🎪 Events', requests: '📝 Requests',
                    credits: '✨ Credits', import: '📤 Import', users: '👤 Users',
                    backup: '💾 Backup', settings: '⚙️ Settings', contact: '✉️ Contact',
                    artists: '🎤 Artists'
                };
                const labelEl = document.getElementById('tabMobileLabel');
                if (labelEl) labelEl.textContent = labelMap[tab] || tab;
            }
            closeTabMobileMenu();

            // Show/hide sections
            document.getElementById('programsSection').style.display = tab === 'programs' ? 'block' : 'none';
            document.getElementById('requestsSection').style.display = tab === 'requests' ? 'block' : 'none';
            document.getElementById('importSection').style.display = tab === 'import' ? 'block' : 'none';
            document.getElementById('creditsSection').style.display = tab === 'credits' ? 'block' : 'none';
            document.getElementById('eventsSection').style.display = tab === 'events' ? 'block' : 'none';
            const usersEl = document.getElementById('usersSection');
            if (usersEl) usersEl.style.display = tab === 'users' ? 'block' : 'none';
            const backupEl = document.getElementById('backupSection');
            if (backupEl) backupEl.style.display = tab === 'backup' ? 'block' : 'none';
            const settingsEl = document.getElementById('settingsSection');
            if (settingsEl) settingsEl.style.display = tab === 'settings' ? 'block' : 'none';
            const contactEl = document.getElementById('contactSection');
            if (contactEl) contactEl.style.display = tab === 'contact' ? 'block' : 'none';
            document.getElementById('artistsSection').style.display = tab === 'artists' ? 'block' : 'none';
            if (tab === 'requests') loadRequests();
            if (tab === 'credits') loadCredits();
            if (tab === 'events') loadEventsTab();
            if (tab === 'users' && ADMIN_ROLE === 'admin') loadUsers();
            if (tab === 'backup' && ADMIN_ROLE === 'admin') loadBackups();
            if (tab === 'settings' && ADMIN_ROLE === 'admin') { loadThemeSettings(); loadTitleSetting(); loadDisclaimerSetting(); }
            if (tab === 'contact' && ADMIN_ROLE === 'admin') loadContactChannels();
            if (tab === 'artists') loadArtists();
        }

        // Mobile tab dropdown controls
        function toggleTabMobileMenu() {
            const menu = document.getElementById('tabMobileMenu');
            const btn = document.getElementById('tabMobileBtn');
            const isOpen = menu.classList.toggle('open');
            btn.setAttribute('aria-expanded', isOpen);
        }
        function closeTabMobileMenu() {
            const menu = document.getElementById('tabMobileMenu');
            const btn = document.getElementById('tabMobileBtn');
            menu.classList.remove('open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
        // Close when tapping outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('tabMobileMenu');
            if (menu && !menu.contains(e.target)) closeTabMobileMenu();
        });

        // Pending count — sync to desktop tab, mobile label, and mobile dropdown item
        async function loadPendingCount() {
            try {
                const res = await fetch('api.php?action=pending_count');
                const result = await res.json();
                const count = (result.success && result.data.count > 0) ? result.data.count : 0;
                const show = count > 0;
                // Desktop tab badge
                const badge = document.getElementById('pendingBadge');
                badge.textContent = count; badge.style.display = show ? 'inline-flex' : 'none';
                // Mobile label badge (shown when Requests is active tab)
                const badgeMobile = document.getElementById('pendingBadgeMobile');
                badgeMobile.textContent = count; badgeMobile.style.display = show ? 'inline-flex' : 'none';
                // Mobile dropdown item badge
                const badgeMobile2 = document.getElementById('pendingBadgeMobile2');
                badgeMobile2.textContent = count; badgeMobile2.style.display = show ? 'inline-flex' : 'none';
                // Show label badge only when current tab is 'requests'
                const currentTab = document.querySelector('.tab-mobile-item.active');
                if (badgeMobile && currentTab && currentTab.dataset.tab !== 'requests') {
                    badgeMobile.style.display = 'none';
                }
            } catch (e) {}
        }

        // Requests
        let reqPage = 1;
        let requestsData = []; // Store requests for detail view

        async function loadRequests() {
            const status = document.getElementById('reqStatusFilter').value;
            const eventId = document.getElementById('reqEventMetaFilter').value;
            showLoading();
            try {
                let reqUrl = `api.php?action=requests&page=${reqPage}`;
                if (status) reqUrl += `&status=${status}`;
                if (eventId) reqUrl += `&event_id=${encodeURIComponent(eventId)}`;
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
            const typeText = r.type === 'add' ? '➕ เพิ่ม Program ใหม่' : '✏️ แก้ไข Program ที่มีอยู่';
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
                    ${r.program_id ? `<br><strong>Event ID อ้างอิง:</strong> ${r.program_id}` : ''}
                </div>
            `;

            // ถ้าเป็น modify และมีข้อมูล event เดิม ให้แสดงเปรียบเทียบ
            if (r.type === 'modify' && r.original_event) {
                const orig = r.original_event;
                const fields = [
                    { label: 'ชื่อ Program', key: 'title', format: 'text' },
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
                        <h4>📅 ข้อมูล Program ที่ขอ</h4>
                        <div class="req-detail-grid">
                            <div class="req-detail-row"><div class="req-detail-label">ชื่อ Program</div><div class="req-detail-value">${escapeHtml(r.title || '-')}</div></div>
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
            loadPrograms();
        }

        // Clear all filters
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('venueFilter').value = '';
            document.getElementById('eventMetaFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            currentPage = 1;
            loadPrograms();
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
            loadPrograms();
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
                const response = await fetch('api.php?action=programs_venues');
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

        // Load programs
        async function loadPrograms() {
            const search = document.getElementById('searchInput').value;
            const venue = document.getElementById('venueFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const eventId = document.getElementById('eventMetaFilter').value;

            let url = `api.php?action=programs_list&page=${currentPage}&limit=${perPage}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (venue) url += `&venue=${encodeURIComponent(venue)}`;
            if (dateFrom) url += `&date_from=${encodeURIComponent(dateFrom)}`;
            if (dateTo) url += `&date_to=${encodeURIComponent(dateTo)}`;
            if (eventId) url += `&event_id=${encodeURIComponent(eventId)}`;
            url += `&sort=${sortColumn}&order=${sortDirection}`;

            showLoading();
            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    renderPrograms(result.data.events);
                    renderPagination(result.data.pagination);
                    attachCheckboxListeners();
                    updateBulkActionsBar();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to load programs:', error);
                showToast('Failed to load programs', 'error');
            } finally {
                hideLoading();
            }
        }

        // Render programs table
        function renderPrograms(events) {
            const tbody = document.getElementById('eventsTableBody');

            if (events.length === 0) {
                const colspan = VENUE_MODE === 'multi' ? 8 : 7;
                tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty-state">ไม่พบ programs</td></tr>`;
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
                        <td>${event.stream_url ? `<a href="${escapeHtml(event.stream_url)}" target="_blank" class="stream-link-badge" title="${escapeHtml(event.stream_url)}">🔴</a> ` : ''}${escapeHtml(event.title)}</td>
                        <td>${dateStr}<br>${startTime === endTime ? startTime : startTime + ' - ' + endTime}</td>
                        ${VENUE_MODE === 'multi' ? `<td>${escapeHtml(event.location || '-')}</td>` : ''}
                        <td>${escapeHtml(event.categories || '-')}</td>
                        <td>${event.program_type ? `<span class="program-type-badge">${escapeHtml(event.program_type)}</span>` : '<span style="color:#adb5bd">-</span>'}</td>
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
            loadPrograms();
        }

        // Change per page
        function changePerPage() {
            perPage = parseInt(document.getElementById('perPageSelect').value);
            currentPage = 1; // Reset to first page
            loadPrograms();
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
                showToast('กรุณาเลือก programs ที่ต้องการลบ', 'error');
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
                const response = await fetch('api.php?action=programs_bulk_delete', {
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
                    loadPrograms();
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
                showToast('กรุณาเลือก programs ที่ต้องการแก้ไข', 'error');
                return;
            }

            document.getElementById('bulkEditCount').textContent = selectedIds.length;
            document.getElementById('bulkEditVenue').value = '';
            document.getElementById('bulkEditOrganizer').value = '';
            if (window.bulkArtistTagInput) window.bulkArtistTagInput.reset();
            document.getElementById('bulkEditProgramType').value = '';

            await loadVenuesForBulkEdit();
            await loadTypesForDatalist();
            document.getElementById('bulkEditModal').classList.add('active');
        }

        function closeBulkEditModal() {
            document.getElementById('bulkEditModal').classList.remove('active');
        }

        async function loadVenuesForBulkEdit() {
            try {
                const response = await fetch('api.php?action=programs_venues');
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

        async function loadTypesForDatalist() {
            try {
                const response = await fetch('api.php?action=programs_types');
                const result = await response.json();

                if (result.success) {
                    const ids = ['programTypesListMain', 'bulkProgramTypesList', 'icsDefaultTypeList'];
                    ids.forEach(id => {
                        const datalist = document.getElementById(id);
                        if (datalist) {
                            datalist.innerHTML = '';
                            result.data.forEach(type => {
                                const option = document.createElement('option');
                                option.value = type;
                                datalist.appendChild(option);
                            });
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to load program types:', error);
            }
        }

        async function submitBulkEdit(event) {
            event.preventDefault();

            const selectedIds = getSelectedEventIds();
            const venue = document.getElementById('bulkEditVenue').value;
            const organizer = document.getElementById('bulkEditOrganizer').value.trim();
            const categories = document.getElementById('bulkEditCategories').value.trim();
            const programType = document.getElementById('bulkEditProgramType').value.trim();

            if (!venue && !organizer && !categories && !programType) {
                showToast('กรุณาเลือกอย่างน้อย 1 ฟิลด์ที่ต้องการเปลี่ยนแปลง', 'error');
                return;
            }

            const updateData = { ids: selectedIds };
            if (venue) updateData.location = venue;
            if (organizer) updateData.organizer = organizer;
            if (categories) updateData.categories = categories;
            if (programType) updateData.program_type = programType;

            showLoading();

            try {
                const response = await fetch('api.php?action=programs_bulk_update', {
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
                    loadPrograms();
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
                loadPrograms();
                return;
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadPrograms();
            }, 300);
        }

        // Open add modal
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'เพิ่ม Program';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';

            // Set default date to today
            document.getElementById('eventDate').value = new Date().toISOString().split('T')[0];

            // Pre-select convention from filter dropdown
            const filterVal = document.getElementById('eventMetaFilter')?.value || '';
            document.getElementById('eventConvention').value = filterVal;

            if (window.artistTagInput) window.artistTagInput.reset();
            formChanged = false;
            document.getElementById('eventModal').classList.add('active');
        }

        // Open edit modal
        async function openEditModal(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=programs_get&id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                const event = result.data;
                const startDate = new Date(event.start);
                const endDate = new Date(event.end);

                document.getElementById('modalTitle').textContent = 'แก้ไข Program';
                document.getElementById('eventId').value = event.id;
                document.getElementById('eventConvention').value = event.event_id || '';
                document.getElementById('title').value = event.title;
                document.getElementById('organizer').value = event.organizer || '';
                document.getElementById('location').value = event.location || '';
                document.getElementById('eventDate').value = startDate.toISOString().split('T')[0];
                document.getElementById('startTime').value = startDate.toTimeString().substring(0, 5);
                document.getElementById('endTime').value = endDate.toTimeString().substring(0, 5);
                document.getElementById('description').value = event.description || '';
                if (window.artistTagInput) window.artistTagInput.setValue(event.categories || '');
                document.getElementById('programType').value = event.program_type || '';
                document.getElementById('streamUrl').value = event.stream_url || '';

                formChanged = false;
                document.getElementById('eventModal').classList.add('active');
            } catch (error) {
                console.error('Failed to load program:', error);
                showToast('Failed to load program', 'error');
            } finally {
                hideLoading();
            }
        }

        // Duplicate program
        async function duplicateEvent(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=programs_get&id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                const event = result.data;
                const startDate = new Date(event.start);
                const endDate = new Date(event.end);

                document.getElementById('modalTitle').textContent = 'Duplicate Program';
                document.getElementById('eventId').value = ''; // No ID = create new
                document.getElementById('eventConvention').value = event.event_id || '';
                document.getElementById('title').value = event.title + ' (Copy)';
                document.getElementById('organizer').value = event.organizer || '';
                document.getElementById('location').value = event.location || '';
                document.getElementById('eventDate').value = startDate.toISOString().split('T')[0];
                document.getElementById('startTime').value = startDate.toTimeString().substring(0, 5);
                document.getElementById('endTime').value = endDate.toTimeString().substring(0, 5);
                document.getElementById('description').value = event.description || '';
                if (window.artistTagInput) window.artistTagInput.setValue(event.categories || '');
                document.getElementById('programType').value = event.program_type || '';
                document.getElementById('streamUrl').value = event.stream_url || '';

                formChanged = false;
                document.getElementById('eventModal').classList.add('active');
            } catch (error) {
                console.error('Failed to load program:', error);
                showToast('Failed to load program', 'error');
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
            window.previewEditIndex = null;
            document.getElementById('eventModal').classList.remove('active');
        }

        // Save program
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
                program_type: document.getElementById('programType').value,
                stream_url: document.getElementById('streamUrl').value || null,
                event_id: conventionVal ? parseInt(conventionVal) : null
            };

            // Preview edit mode: update uploadedEvents array instead of saving to DB
            if (window.previewEditIndex !== undefined && window.previewEditIndex !== null) {
                const idx = window.previewEditIndex;
                window.previewEditIndex = null;
                uploadedEvents[idx] = Object.assign(uploadedEvents[idx], {
                    title: data.title,
                    organizer: data.organizer,
                    location: data.location,
                    start: data.start,
                    end: data.end,
                    description: data.description,
                    categories: data.categories,
                    program_type: data.program_type,
                    stream_url: data.stream_url
                });
                closeModal();
                renderPreviewTable();
                return;
            }

            const isEdit = !!id;

            const url = isEdit ? `api.php?action=programs_update&id=${id}` : 'api.php?action=programs_create';
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
                    loadPrograms();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to save program:', error);
                showToast('Failed to save program', 'error');
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
                const response = await fetch(`api.php?action=programs_delete&id=${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    closeDeleteModal();
                    loadPrograms();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to delete program:', error);
                showToast('Failed to delete program', 'error');
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
        let unmatchedCategories = [];
        let allArtistsForMapping = [];

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
                    previewData              = result.data;
                    uploadedEvents           = result.data.events;
                    unmatchedCategories      = result.data.unmatched_categories || [];
                    allArtistsForMapping     = result.data.all_artists || [];
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

            // Render artist mapping section
            renderArtistMappingSection();

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
                        <td>${formatDateTimeRange(event.start, event.end)}</td>
                        <td>${escapeHtml(event.location || '')}</td>
                        <td>${escapeHtml(event.categories || '')}</td>
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

        // Format a start–end datetime range (collapses same-date and same-time)
        function formatDateTimeRange(start, end) {
            if (!start) return '';
            if (!end || start === end) return formatDateTime(start);
            const s = new Date(start);
            const e = new Date(end);
            const dateOpts = { year: 'numeric', month: '2-digit', day: '2-digit' };
            const timeOpts = { hour: '2-digit', minute: '2-digit' };
            const sDate = s.toLocaleDateString('th-TH', dateOpts);
            const eDate = e.toLocaleDateString('th-TH', dateOpts);
            const sTime = s.toLocaleTimeString('th-TH', timeOpts);
            const eTime = e.toLocaleTimeString('th-TH', timeOpts);
            if (sDate === eDate) {
                return sTime === eTime ? `${sDate} ${sTime}` : `${sDate} ${sTime} - ${eTime}`;
            }
            return `${formatDateTime(start)} - ${formatDateTime(end)}`;
        }

        // Edit preview event
        function editPreviewEvent(index) {
            const event = uploadedEvents[index];
            if (!event) return;

            // Open event modal with preview data
            document.getElementById('modalTitle').textContent = 'แก้ไข Program (Preview)';
            document.getElementById('eventId').value = ''; // No ID yet
            document.getElementById('title').value = event.title || '';
            document.getElementById('organizer').value = event.organizer || '';
            document.getElementById('location').value = event.location || '';

            const startDate = new Date(event.start);
            document.getElementById('eventDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('startTime').value = startDate.toTimeString().slice(0, 5);

            const endDate = new Date(event.end);
            document.getElementById('endTime').value = endDate.toTimeString().slice(0, 5);

            document.getElementById('description').value = event.description || '';
            document.getElementById('categories').value = event.categories || '';
            document.getElementById('programType').value = event.program_type || '';
            document.getElementById('streamUrl').value = event.stream_url || '';

            document.getElementById('eventModal').classList.add('active');

            // Override save to update preview instead
            window.previewEditIndex = index;
        }

        // Delete preview event
        function deletePreviewEvent(index) {
            if (!confirm('ลบ program นี้จาก preview?')) return;

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
                showToast('กรุณาเลือก programs ที่ต้องการลบ', 'error');
                return;
            }

            if (!confirm(`ลบ ${selectedIndexes.length} programs?`)) return;

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
                showToast('กรุณาเลือก programs ที่ต้องการ import', 'error');
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

            if (!confirm(`Import ${eventsToImport.length} programs?`)) return;

            showLoading();

            // Include event_meta_id and default_type from ICS import selectors
            const importEventMetaId = document.getElementById('icsImportEventMeta').value;
            const importDefaultType = (document.getElementById('icsDefaultType')?.value || '').trim();
            const importBody = {
                events: eventsToImport,
                save_file: true,
                artist_mappings: collectArtistMappings(),
            };
            if (importEventMetaId) {
                importBody.event_id = parseInt(importEventMetaId);
            }
            if (importDefaultType) {
                importBody.default_type = importDefaultType;
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
                    loadPrograms(); // Reload events table
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
            document.getElementById('summaryArtistLinks').textContent = data.stats.artist_links || 0;

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
            uploadedEvents       = [];
            previewData          = {};
            unmatchedCategories  = [];
            allArtistsForMapping = [];
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
            const eventId = document.getElementById('creditsEventMetaFilter')?.value || '';
            let url = `api.php?action=credits_list&page=${creditsCurrentPage}&limit=${creditsPerPage}&sort=${creditsSortColumn}&order=${creditsSortDirection}&search=${encodeURIComponent(search)}`;
            if (eventId) url += `&event_id=${encodeURIComponent(eventId)}`;

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
                document.getElementById('creditEventMetaId').value = credit.event_id || '';

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
                event_id: eventMetaVal ? parseInt(eventMetaVal) : null
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
        // EVENT META (EVENTS) - Populate filter dropdowns
        // ========================================================================

        async function loadEventMetaOptions() {
            try {
                const response = await fetch('api.php?action=events_list');
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
                        // Keep the first "All Events" option
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
        // EVENTS MANAGEMENT (formerly Conventions)
        // ========================================================================

        let conventionsFormChanged = false;

        // Events Tab Sort State
        let eventsSortColumn    = 'start_date';
        let eventsSortDirection = 'desc';
        let _eventsData         = [];

        // Load Events Tab
        async function loadEventsTab() {
            showLoading();

            const search = document.getElementById('conventionsSearchInput')?.value || '';
            let url = `api.php?action=events_list`;
            if (search) url += `&search=${encodeURIComponent(search)}`;

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    _eventsData = result.data;
                    renderEventsTab(_eventsData);
                    updateEventsSortIcons();
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

        // Render Events Table
        function renderEventsTab(conventions) {
            const tbody = document.getElementById('conventionsTableBody');

            if (!conventions || conventions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="empty-state">ไม่พบ events</td></tr>';
                return;
            }

            // Client-side sort
            const sorted = [...conventions].sort((a, b) => {
                let va = a[eventsSortColumn] ?? '';
                let vb = b[eventsSortColumn] ?? '';
                if (eventsSortColumn === 'id' || eventsSortColumn === 'event_count' || eventsSortColumn === 'is_active') {
                    va = Number(va) || 0;
                    vb = Number(vb) || 0;
                }
                if (va < vb) return eventsSortDirection === 'asc' ? -1 : 1;
                if (va > vb) return eventsSortDirection === 'asc' ? 1 : -1;
                return 0;
            });

            tbody.innerHTML = sorted.map(conv => {
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
                            <button class="btn btn-secondary btn-sm" onclick="openEditEventModal(${conv.id})">แก้ไข</button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteEventModal(${conv.id}, '${escapeHtml(conv.name).replace(/'/g, "\\'")}')">ลบ</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Search Events
        let conventionsSearchTimeout = null;

        function handleEventsSearch(event) {
            if (event.key === 'Enter') {
                loadEventsTab();
                return;
            }
            clearTimeout(conventionsSearchTimeout);
            conventionsSearchTimeout = setTimeout(() => {
                loadEventsTab();
            }, 300);
        }

        function clearEventsSearch() {
            document.getElementById('conventionsSearchInput').value = '';
            loadEventsTab();
        }

        // Events Tab Sorting
        function sortEventsBy(column) {
            if (eventsSortColumn === column) {
                eventsSortDirection = eventsSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                eventsSortColumn = column;
                eventsSortDirection = (column === 'start_date' || column === 'end_date') ? 'desc' : 'asc';
            }
            updateEventsSortIcons();
            renderEventsTab(_eventsData);
        }

        function updateEventsSortIcons() {
            document.querySelectorAll('#eventsSection .sort-icon').forEach(icon => {
                icon.classList.remove('asc', 'desc');
                if (icon.dataset.col === eventsSortColumn) {
                    icon.classList.add(eventsSortDirection);
                }
            });
        }

        // Open Add Event Modal
        function openAddEventModal() {
            document.getElementById('conventionModalTitle').textContent = 'เพิ่ม Event';
            document.getElementById('conventionForm').reset();
            document.getElementById('conventionId').value = '';
            document.getElementById('conventionIsActive').checked = true;
            document.getElementById('conventionVenueMode').value = 'multi';
            document.getElementById('conventionTheme').value = '';
            conventionsFormChanged = false;
            document.getElementById('conventionModal').classList.add('active');
        }

        // Open Edit Event Modal
        async function openEditEventModal(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=events_get&id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                const conv = result.data;

                document.getElementById('conventionModalTitle').textContent = 'แก้ไข Event';
                document.getElementById('conventionId').value = conv.id;
                document.getElementById('conventionName').value = conv.name || '';
                document.getElementById('conventionSlug').value = conv.slug || '';
                document.getElementById('conventionEmail').value = conv.email || '';
                document.getElementById('conventionDescription').value = conv.description || '';
                document.getElementById('conventionStartDate').value = conv.start_date || '';
                document.getElementById('conventionEndDate').value = conv.end_date || '';
                document.getElementById('conventionVenueMode').value = conv.venue_mode || 'multi';
                document.getElementById('conventionIsActive').checked = !!conv.is_active;
                document.getElementById('conventionTheme').value = conv.theme || '';

                conventionsFormChanged = false;
                document.getElementById('conventionModal').classList.add('active');
            } catch (error) {
                showToast('Failed to load event', 'error');
            } finally {
                hideLoading();
            }
        }

        // Close Event Meta Modal
        function closeEventMetaModal() {
            if (conventionsFormChanged) {
                if (!confirm('คุณมีการแก้ไขที่ยังไม่ได้บันทึก ต้องการปิดหรือไม่?')) {
                    return;
                }
            }
            conventionsFormChanged = false;
            document.getElementById('conventionModal').classList.remove('active');
        }

        // Save Event Meta
        async function saveEventMeta(e) {
            e.preventDefault();

            const id = document.getElementById('conventionId').value;
            const themeVal = document.getElementById('conventionTheme').value;
            const data = {
                name: document.getElementById('conventionName').value,
                slug: document.getElementById('conventionSlug').value,
                email: document.getElementById('conventionEmail').value || null,
                description: document.getElementById('conventionDescription').value,
                start_date: document.getElementById('conventionStartDate').value,
                end_date: document.getElementById('conventionEndDate').value,
                venue_mode: document.getElementById('conventionVenueMode').value,
                is_active: document.getElementById('conventionIsActive').checked ? 1 : 0,
                theme: themeVal || null
            };

            const isEdit = !!id;
            const url = isEdit ? `api.php?action=events_update&id=${id}` : 'api.php?action=events_create';
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
                    closeEventMetaModal();
                    loadEventsTab();
                    loadEventMetaOptions(); // Refresh filter dropdowns
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to save event:', error);
                showToast('Failed to save event', 'error');
            } finally {
                hideLoading();
            }
        }

        // Delete Event Meta
        function openDeleteEventModal(id, name) {
            document.getElementById('deleteConventionId').value = id;
            document.getElementById('deleteConventionName').textContent = name;
            document.getElementById('deleteConventionModal').classList.add('active');
        }

        function closeDeleteEventModal() {
            document.getElementById('deleteConventionModal').classList.remove('active');
        }

        async function confirmDeleteEventMeta() {
            const id = document.getElementById('deleteConventionId').value;

            showLoading();

            try {
                const response = await fetch(`api.php?action=events_delete&id=${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message || 'ลบสำเร็จ', 'success');
                    closeDeleteEventModal();
                    loadEventsTab();
                    loadEventMetaOptions(); // Refresh filter dropdowns
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Failed to delete event:', error);
                showToast('Failed to delete event', 'error');
            } finally {
                hideLoading();
            }
        }

        // Form Change Tracking for Events
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
                    loadPrograms();
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
                    loadPrograms();
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

        // =====================================================================
        // Theme Settings
        // =====================================================================

        const THEME_OPTIONS = [
            { id: 'sakura',   label: '🌸 Sakura',   color: 'linear-gradient(135deg,#FFB7C5,#E91E63)' },
            { id: 'ocean',    label: '🌊 Ocean',    color: 'linear-gradient(135deg,#B3E5FC,#0288D1)' },
            { id: 'forest',   label: '🌿 Forest',   color: 'linear-gradient(135deg,#A5D6A7,#2E7D32)' },
            { id: 'midnight', label: '🌙 Midnight', color: 'linear-gradient(135deg,#CE93D8,#7B1FA2)' },
            { id: 'sunset',   label: '☀️ Sunset',   color: 'linear-gradient(135deg,#FFCC80,#F57C00)' },
            { id: 'dark',     label: '🖤 Dark',     color: 'linear-gradient(135deg,#78909C,#37474F)' },
            { id: 'gray',     label: '🩶 Gray',     color: 'linear-gradient(135deg,#BDBDBD,#757575)' },
        ];
        let currentTheme = 'sakura';

        function loadThemeSettings() {
            fetch('api.php?action=theme_get')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        currentTheme = data.data.theme;
                        renderThemePicker(currentTheme);
                    }
                });
        }

        function renderThemePicker(selected) {
            const container = document.getElementById('themePicker');
            if (!container) return;
            container.innerHTML = THEME_OPTIONS.map(t => `
                <div onclick="selectTheme('${t.id}')" id="theme_opt_${t.id}"
                     style="cursor:pointer;text-align:center;padding:12px;border-radius:12px;
                            border:3px solid ${t.id === selected ? '#333' : '#dee2e6'};
                            background:${t.id === selected ? '#f8f9fa' : 'white'};
                            min-width:100px;transition:all 0.2s">
                    <div style="width:60px;height:60px;border-radius:50%;background:${t.color};
                                margin:0 auto 8px;border:3px solid ${t.id === selected ? '#333' : 'transparent'}"></div>
                    <div style="font-weight:${t.id === selected ? '700' : '500'};font-size:0.9rem">${t.label}</div>
                </div>
            `).join('');
        }

        function selectTheme(themeId) {
            currentTheme = themeId;
            renderThemePicker(themeId);
            document.getElementById('themeSaveMsg').style.display = 'none';
        }

        function saveThemeSetting() {
            const btn = document.getElementById('themeSaveBtn');
            btn.disabled = true;
            fetch('api.php?action=theme_save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ theme: currentTheme })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    const msg = document.getElementById('themeSaveMsg');
                    msg.style.display = 'inline';
                    setTimeout(() => msg.style.display = 'none', 3000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => { btn.disabled = false; alert('Network error'); });
        }

        function loadTitleSetting() {
            fetch('api.php?action=title_get')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('siteTitleInput').value = data.data.site_title || '';
                    }
                });
        }

        function saveTitleSetting() {
            const input = document.getElementById('siteTitleInput');
            const title = input.value.trim();
            if (!title) { alert('กรุณากรอก Site Title'); return; }
            const btn = document.getElementById('titleSaveBtn');
            btn.disabled = true;
            fetch('api.php?action=title_save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ site_title: title })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    const msg = document.getElementById('titleSaveMsg');
                    msg.style.display = 'inline';
                    setTimeout(() => msg.style.display = 'none', 3000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => { btn.disabled = false; alert('Network error'); });
        }

        function loadDisclaimerSetting() {
            fetch('api.php?action=disclaimer_get')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('disclaimerTh').value = data.data.disclaimer_th || '';
                        document.getElementById('disclaimerEn').value = data.data.disclaimer_en || '';
                        document.getElementById('disclaimerJa').value = data.data.disclaimer_ja || '';
                    }
                });
        }

        function saveDisclaimerSetting() {
            const btn = document.getElementById('disclaimerSaveBtn');
            btn.disabled = true;
            fetch('api.php?action=disclaimer_save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({
                    disclaimer_th: document.getElementById('disclaimerTh').value.trim(),
                    disclaimer_en: document.getElementById('disclaimerEn').value.trim(),
                    disclaimer_ja: document.getElementById('disclaimerJa').value.trim(),
                })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    const msg = document.getElementById('disclaimerSaveMsg');
                    msg.style.display = 'inline';
                    setTimeout(() => msg.style.display = 'none', 3000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => { btn.disabled = false; alert('Network error'); });
        }

        // Contact Channels
        let contactChannels = [];
        let editingChannelId = null;

        function loadContactChannels() {
            fetch('api.php?action=contact_channels_list')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        contactChannels = data.data || [];
                        renderContactChannels();
                    }
                });
        }

        function renderContactChannels() {
            const tbody = document.getElementById('contactChannelsTbody');
            if (!tbody) return;
            if (contactChannels.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px">ยังไม่มีช่องทางติดต่อ</td></tr>';
                return;
            }
            tbody.innerHTML = contactChannels.map(ch => `
                <tr>
                    <td style="font-size:1.4em;text-align:center">${escHtml(ch.icon)}</td>
                    <td><strong>${escHtml(ch.title)}</strong>${ch.description ? '<br><small style="color:#666">' + escHtml(ch.description) + '</small>' : ''}</td>
                    <td>${ch.url ? '<a href="' + escHtml(ch.url) + '" target="_blank" rel="noopener noreferrer" style="color:#0d6efd">' + escHtml(ch.url) + '</a>' : '-'}</td>
                    <td style="text-align:center">${ch.is_active ? '<span style="color:green">✓</span>' : '<span style="color:#999">✗</span>'}</td>
                    <td style="white-space:nowrap">
                        <button class="btn btn-sm btn-secondary" onclick="openChannelModal(${ch.id})">✏️ แก้ไข</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteChannel(${ch.id}, '${escHtml(ch.title).replace(/'/g, "\\'")}')">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }

        function escHtml(str) {
            return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function openChannelModal(id) {
            editingChannelId = id || null;
            const modal = document.getElementById('channelModal');
            const title = document.getElementById('channelModalTitle');
            if (id) {
                const ch = contactChannels.find(c => c.id == id);
                if (!ch) return;
                title.textContent = '✏️ แก้ไขช่องทางติดต่อ';
                document.getElementById('chIcon').value = ch.icon || '';
                document.getElementById('chTitle').value = ch.title || '';
                document.getElementById('chDescription').value = ch.description || '';
                document.getElementById('chUrl').value = ch.url || '';
                document.getElementById('chOrder').value = ch.display_order || 0;
                document.getElementById('chActive').checked = ch.is_active == 1;
            } else {
                title.textContent = '➕ เพิ่มช่องทางติดต่อ';
                document.getElementById('chIcon').value = '';
                document.getElementById('chTitle').value = '';
                document.getElementById('chDescription').value = '';
                document.getElementById('chUrl').value = '';
                document.getElementById('chOrder').value = 0;
                document.getElementById('chActive').checked = true;
            }
            modal.style.display = 'flex';
        }

        function closeChannelModal() {
            document.getElementById('channelModal').style.display = 'none';
            editingChannelId = null;
        }

        function submitChannelForm(e) {
            e.preventDefault();
            const payload = {
                icon: document.getElementById('chIcon').value.trim(),
                title: document.getElementById('chTitle').value.trim(),
                description: document.getElementById('chDescription').value.trim(),
                url: document.getElementById('chUrl').value.trim(),
                display_order: parseInt(document.getElementById('chOrder').value) || 0,
                is_active: document.getElementById('chActive').checked ? 1 : 0,
            };
            if (!payload.title) { alert('กรุณากรอก Title'); return; }
            const isEdit = editingChannelId !== null;
            const url = isEdit ? 'api.php?action=contact_channels_update&id=' + editingChannelId : 'api.php?action=contact_channels_create';
            fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeChannelModal();
                    loadContactChannels();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => alert('Network error'));
        }

        function deleteChannel(id, title) {
            if (!confirm('ลบช่องทาง "' + title + '" ?')) return;
            fetch('api.php?action=contact_channels_delete&id=' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) loadContactChannels();
                else alert('Error: ' + data.message);
            })
            .catch(() => alert('Network error'));
        }

        // ============================================================
        // ICS Import — Artist Mapping
        // ============================================================

        function renderArtistMappingSection() {
            const section = document.getElementById('artistMappingSection');
            if (!unmatchedCategories.length) {
                section.style.display = 'none';
                return;
            }
            section.style.display = 'block';
            document.getElementById('unmatchedCount').textContent = unmatchedCategories.length;

            const artistOptions = allArtistsForMapping.map(a =>
                `<option value="${a.id}">${escapeHtml(a.name)}${a.is_group == 1 ? ' [กลุ่ม]' : ''}</option>`
            ).join('');

            const tbody = document.getElementById('artistMappingBody');
            tbody.innerHTML = unmatchedCategories.map((cat, i) => {
                const hasSuggestion = cat.suggested && cat.suggested.artist_id;
                const defaultAction = hasSuggestion ? 'map' : 'auto';
                return `
                <tr id="mappingRow_${i}">
                    <td><strong>${escapeHtml(cat.name)}</strong></td>
                    <td style="text-align:center;">${cat.count}</td>
                    <td>
                        <select onchange="onMappingActionChange(${i})" id="mappingAction_${i}"
                                style="width:100%; padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9em;">
                            <option value="auto" ${defaultAction==='auto'?'selected':''}>🔍 Auto (ข้ามถ้าไม่พบ)</option>
                            <option value="map"  ${defaultAction==='map' ?'selected':''}>🔗 Map → ศิลปินที่มีอยู่</option>
                            <option value="create">✨ สร้างศิลปินใหม่</option>
                            <option value="skip">⏭️ ข้าม (ไม่ link)</option>
                        </select>
                    </td>
                    <td id="mappingTarget_${i}"></td>
                </tr>`;
            }).join('');

            // Render target cells (including pre-filled suggestions)
            unmatchedCategories.forEach((cat, i) => renderMappingTarget(i));
        }

        function onMappingActionChange(i) {
            renderMappingTarget(i);
        }

        function renderMappingTarget(i) {
            const action = document.getElementById(`mappingAction_${i}`).value;
            const target = document.getElementById(`mappingTarget_${i}`);
            const cat    = unmatchedCategories[i];

            const artistOptions = allArtistsForMapping.map(a =>
                `<option value="${a.id}">${escapeHtml(a.name)}${a.is_group == 1 ? ' [กลุ่ม]' : ''}</option>`
            ).join('');

            if (action === 'map') {
                const suggestedId = cat.suggested ? cat.suggested.artist_id : null;
                const suggestedName = cat.suggested ? cat.suggested.artist_name : null;
                const hint = suggestedId
                    ? `<small style="color:#059669; font-size:0.8em;">📋 แนะนำจาก mapping file: <strong>${escapeHtml(suggestedName)}</strong></small>`
                    : '';
                const opts = allArtistsForMapping.map(a =>
                    `<option value="${a.id}" ${suggestedId && a.id == suggestedId ? 'selected' : ''}>${escapeHtml(a.name)}${a.is_group == 1 ? ' [กลุ่ม]' : ''}</option>`
                ).join('');
                target.innerHTML = `
                    <select id="mappingArtistId_${i}"
                        style="width:100%; padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9em;">
                        <option value="">-- เลือกศิลปิน --</option>${opts}
                    </select>${hint ? '<br>' + hint : ''}`;
            } else if (action === 'create') {
                target.innerHTML = `
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <input type="text" id="mappingNewName_${i}"
                            value="${escapeHtml(cat.name)}"
                            style="flex:1; min-width:120px; padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9em;"
                            placeholder="ชื่อศิลปิน">
                        <label style="display:flex; align-items:center; gap:4px; font-size:0.85em; white-space:nowrap; cursor:pointer;">
                            <input type="checkbox" id="mappingIsGroup_${i}" style="accent-color:var(--admin-primary)">
                            เป็นกลุ่ม
                        </label>
                    </div>`;
            } else if (action === 'skip') {
                target.innerHTML = `<span style="color:#ef4444; font-size:0.85em;">ไม่สร้าง artist link สำหรับชื่อนี้</span>`;
            } else {
                target.innerHTML = `<span style="color:#94a3b8; font-size:0.85em;">— ระบบจะพยายาม match อัตโนมัติ —</span>`;
            }
        }

        function collectArtistMappings() {
            const mappings = [];
            unmatchedCategories.forEach((cat, i) => {
                const actionEl = document.getElementById(`mappingAction_${i}`);
                if (!actionEl) return;
                const action = actionEl.value;
                if (action === 'auto') return; // ให้ API จัดการ auto-match เอง

                const entry = { category: cat.name, action };

                if (action === 'map') {
                    const sel = document.getElementById(`mappingArtistId_${i}`);
                    if (sel && sel.value) entry.artist_id = parseInt(sel.value);
                    else entry.action = 'skip'; // ไม่ได้เลือก → skip
                } else if (action === 'create') {
                    const nameEl = document.getElementById(`mappingNewName_${i}`);
                    const groupEl = document.getElementById(`mappingIsGroup_${i}`);
                    entry.new_name = nameEl ? nameEl.value.trim() : cat.name;
                    entry.is_group = groupEl && groupEl.checked ? 1 : 0;
                    if (!entry.new_name) entry.action = 'skip';
                }

                mappings.push(entry);
            });
            return mappings;
        }

        // ============================================================
        // Artists
        // ============================================================

        async function loadArtists() {
            showLoading();
            const search  = document.getElementById('artistsSearchInput')?.value || '';
            const isGroup = document.getElementById('artistsTypeFilter')?.value ?? '';
            let url = `api.php?action=artists_list&page=${artistsCurrentPage}&limit=${artistsPerPage}&sort=${artistsSortColumn}&order=${artistsSortDirection}&search=${encodeURIComponent(search)}`;
            if (isGroup !== '') url += `&is_group=${encodeURIComponent(isGroup)}`;

            try {
                const response = await fetch(url);
                const result   = await response.json();
                if (result.success) {
                    renderArtists(result.data.artists);
                    renderArtistsPagination(result.data.pagination);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Failed to load artists', 'error');
            } finally {
                hideLoading();
            }
        }

        function renderArtists(artists) {
            const tbody = document.getElementById('artistsTableBody');
            if (!artists.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">ไม่พบศิลปิน</td></tr>';
                return;
            }
            tbody.innerHTML = artists.map(a => {
                const typeBadge = a.is_group == 1
                    ? `<span style="background:#e3f0ff;color:#1565c0;padding:2px 8px;border-radius:10px;font-size:0.8em;font-weight:600">กลุ่ม</span>`
                    : `<span style="background:#f3f4f6;color:#374151;padding:2px 8px;border-radius:10px;font-size:0.8em;font-weight:600">บุคคล</span>`;
                const groupName = a.group_name ? escapeHtml(a.group_name) : '-';
                const variantCount = a.variant_count > 0
                    ? `<span style="background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-size:0.8em;font-weight:600">${a.variant_count}</span>`
                    : `<span style="color:#9ca3af;font-size:0.85em">-</span>`;
                const safeName = escapeHtml(a.name).replace(/'/g, "\\'");
                return `
                    <tr>
                        <td style="text-align:center"><input type="checkbox" class="artist-checkbox" data-id="${a.id}" data-is-group="${a.is_group}" onchange="updateArtistBulkToolbar()" style="width:16px;height:16px;cursor:pointer"></td>
                        <td>${a.id}</td>
                        <td><strong><a href="${APP_ROOT}/artist/${a.id}" target="_blank" style="color:inherit;text-decoration:none" title="เปิด profile">${escapeHtml(a.name)}</a></strong></td>
                        <td>${typeBadge}</td>
                        <td>${groupName}</td>
                        <td>${variantCount}</td>
                        <td class="actions">
                            <button class="btn btn-secondary btn-sm" onclick="openArtistVariantsModal(${a.id}, '${safeName}')">Variants</button>
                            <button class="btn btn-secondary btn-sm" onclick="openEditArtistModal(${a.id})">แก้ไข</button>
                            <button class="btn btn-secondary btn-sm" onclick="openCopyArtistModal(${a.id})" title="Copy artist">Copy</button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteArtistModal(${a.id}, '${safeName}')">ลบ</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderArtistsPagination(pagination) {
            const container = document.getElementById('artistsPagination');
            const { page, totalPages, total } = pagination;
            if (totalPages <= 1) {
                container.innerHTML = `<span class="pagination-info">ทั้งหมด ${total} รายการ</span>`;
                return;
            }
            container.innerHTML = `
                <button class="btn btn-secondary btn-sm" onclick="goToArtistPage(${page - 1})" ${page <= 1 ? 'disabled' : ''}>&laquo; ก่อนหน้า</button>
                <span class="pagination-info">หน้า ${page} / ${totalPages} (ทั้งหมด ${total} รายการ)</span>
                <button class="btn btn-secondary btn-sm" onclick="goToArtistPage(${page + 1})" ${page >= totalPages ? 'disabled' : ''}>ถัดไป &raquo;</button>
            `;
        }

        function goToArtistPage(page) {
            artistsCurrentPage = page;
            loadArtists();
        }

        function changeArtistsPerPage() {
            artistsPerPage = parseInt(document.getElementById('artistsPerPageSelect').value);
            artistsCurrentPage = 1;
            loadArtists();
        }

        function handleArtistsSearch(event) {
            if (event.key === 'Enter') {
                artistsCurrentPage = 1;
                loadArtists();
                return;
            }
            clearTimeout(artistsSearchTimeout);
            artistsSearchTimeout = setTimeout(() => {
                artistsCurrentPage = 1;
                loadArtists();
            }, 300);
        }

        function clearArtistsSearch() {
            document.getElementById('artistsSearchInput').value = '';
            artistsCurrentPage = 1;
            loadArtists();
        }

        function sortArtistsBy(column) {
            if (artistsSortColumn === column) {
                artistsSortDirection = artistsSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                artistsSortColumn = column;
                artistsSortDirection = 'asc';
            }
            loadArtists();
        }

        function onArtistIsGroupChange() {
            const isGroup = document.getElementById('artistIsGroup').checked;
            document.getElementById('artistGroupIdRow').style.display = isGroup ? 'none' : 'block';
        }

        async function loadGroupsIntoSelect(selectedGroupId) {
            try {
                const response = await fetch('api.php?action=artists_groups');
                const result   = await response.json();
                if (!result.success) return;

                const select = document.getElementById('artistGroupId');
                select.innerHTML = '<option value="">-- ไม่สังกัดกลุ่ม / ศิลปินเดี่ยว --</option>';
                result.data.groups.forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = g.name;
                    if (selectedGroupId && parseInt(g.id) === parseInt(selectedGroupId)) {
                        opt.selected = true;
                    }
                    select.appendChild(opt);
                });
            } catch (err) {
                console.error('Failed to load groups', err);
            }
        }

        function resetArtistCopyState() {
            document.getElementById('artistCopySourceId').value = '';
            document.getElementById('artistCopyVariantsSection').style.display = 'none';
            document.getElementById('artistCopyVariantsList').innerHTML = '<span style="color:#9ca3af;font-size:0.9em">กำลังโหลด...</span>';
        }

        async function openAddArtistModal() {
            document.getElementById('artistModalTitle').textContent = 'เพิ่มศิลปิน';
            document.getElementById('artistForm').reset();
            document.getElementById('artistId').value = '';
            document.getElementById('artistGroupIdRow').style.display = 'block';
            resetArtistCopyState();
            await loadGroupsIntoSelect(null);
            document.getElementById('artistModal').classList.add('active');
        }

        async function openEditArtistModal(id) {
            showLoading();
            try {
                const response = await fetch(`api.php?action=artists_get&id=${id}`);
                const result   = await response.json();
                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }
                const artist = result.data;

                document.getElementById('artistModalTitle').textContent = 'แก้ไขศิลปิน';
                document.getElementById('artistId').value = artist.id;
                document.getElementById('artistName').value = artist.name;
                document.getElementById('artistIsGroup').checked = artist.is_group == 1;
                document.getElementById('artistGroupIdRow').style.display = artist.is_group == 1 ? 'none' : 'block';
                resetArtistCopyState();

                await loadGroupsIntoSelect(artist.group_id);
                document.getElementById('artistModal').classList.add('active');
            } catch (err) {
                showToast('Failed to load artist', 'error');
            } finally {
                hideLoading();
            }
        }

        async function openCopyArtistModal(id) {
            showLoading();
            try {
                // Fetch artist data
                const [artistRes, variantsRes] = await Promise.all([
                    fetch(`api.php?action=artists_get&id=${id}`),
                    fetch(`api.php?action=artists_variants_list&artist_id=${id}`),
                ]);
                const artistResult   = await artistRes.json();
                const variantsResult = await variantsRes.json();

                if (!artistResult.success) {
                    showToast(artistResult.message, 'error');
                    return;
                }
                const artist   = artistResult.data;
                const variants = variantsResult.success ? variantsResult.data.variants : [];

                // Pre-fill modal
                document.getElementById('artistModalTitle').textContent = `Copy ศิลปิน: ${artist.name}`;
                document.getElementById('artistId').value     = '';          // create new
                document.getElementById('artistCopySourceId').value = id;
                document.getElementById('artistName').value   = artist.name + ' (copy)';
                document.getElementById('artistIsGroup').checked = artist.is_group == 1;
                document.getElementById('artistGroupIdRow').style.display = artist.is_group == 1 ? 'none' : 'block';

                await loadGroupsIntoSelect(artist.group_id);

                // Render variants checkboxes
                const section = document.getElementById('artistCopyVariantsSection');
                const listEl  = document.getElementById('artistCopyVariantsList');
                section.style.display = 'block';
                if (!variants.length) {
                    listEl.innerHTML = '<span style="color:#9ca3af;font-size:0.9em">ไม่มี variants</span>';
                } else {
                    listEl.innerHTML = variants.map(v => `
                        <label style="display:flex;align-items:center;gap:8px;padding:4px 6px;border-radius:4px;cursor:pointer;user-select:none">
                            <input type="checkbox" class="copy-variant-cb" data-variant="${escapeHtml(v.variant)}" checked style="width:15px;height:15px;cursor:pointer">
                            <span style="font-size:0.9em">${escapeHtml(v.variant)}</span>
                        </label>
                    `).join('');
                }

                document.getElementById('artistModal').classList.add('active');
            } catch (err) {
                showToast('Failed to load artist', 'error');
            } finally {
                hideLoading();
            }
        }

        function copyVariantsSelectAll(checked) {
            document.querySelectorAll('.copy-variant-cb').forEach(cb => { cb.checked = checked; });
        }

        function closeArtistModal() {
            document.getElementById('artistModal').classList.remove('active');
            resetArtistCopyState();
        }

        async function saveArtist(e) {
            e.preventDefault();
            const id          = document.getElementById('artistId').value;
            const copySourceId = document.getElementById('artistCopySourceId').value;
            const groupIdVal  = document.getElementById('artistGroupId').value;
            const data = {
                name:     document.getElementById('artistName').value,
                is_group: document.getElementById('artistIsGroup').checked ? 1 : 0,
                group_id: groupIdVal !== '' ? parseInt(groupIdVal) : null,
            };

            const isEdit = !!id;
            const url    = isEdit ? `api.php?action=artists_update&id=${id}` : 'api.php?action=artists_create';
            const method = isEdit ? 'PUT' : 'POST';

            showLoading();
            try {
                const response = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify(data),
                });
                const result = await response.json();
                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                // If this was a copy, create selected variants for the new artist
                if (!isEdit && copySourceId && result.data && result.data.id) {
                    const newId = result.data.id;
                    const checkedCbs = [...document.querySelectorAll('.copy-variant-cb:checked')];
                    for (const cb of checkedCbs) {
                        const variant = cb.dataset.variant;
                        try {
                            await fetch('api.php?action=artists_variants_create', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ artist_id: newId, variant }),
                            });
                        } catch (_) { /* continue on variant error */ }
                    }
                }

                showToast(result.message, 'success');
                closeArtistModal();
                loadArtists();
            } catch (err) {
                showToast('Failed to save artist', 'error');
            } finally {
                hideLoading();
            }
        }

        function openDeleteArtistModal(id, name) {
            document.getElementById('deleteArtistId').value = id;
            document.getElementById('deleteArtistName').textContent = name;
            document.getElementById('deleteArtistModal').classList.add('active');
        }

        function closeDeleteArtistModal() {
            document.getElementById('deleteArtistModal').classList.remove('active');
        }

        async function confirmDeleteArtist() {
            const id = document.getElementById('deleteArtistId').value;
            showLoading();
            try {
                const response = await fetch(`api.php?action=artists_delete&id=${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    closeDeleteArtistModal();
                    loadArtists();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Failed to delete artist', 'error');
            } finally {
                hideLoading();
            }
        }

        // ============================================================
        // Artist Variants
        // ============================================================

        let artistVariantsCurrentId = null;

        async function openArtistVariantsModal(artistId, artistName) {
            artistVariantsCurrentId = artistId;
            document.getElementById('artistVariantsName').textContent = artistName;
            document.getElementById('newVariantInput').value = '';
            document.getElementById('artistVariantsModal').classList.add('active');
            await loadArtistVariants();
        }

        function closeArtistVariantsModal() {
            document.getElementById('artistVariantsModal').classList.remove('active');
            artistVariantsCurrentId = null;
            // Refresh artists list to update variant count
            loadArtists();
        }

        async function loadArtistVariants() {
            if (!artistVariantsCurrentId) return;
            const listEl = document.getElementById('artistVariantsList');
            listEl.innerHTML = '<span style="color:#9ca3af">Loading...</span>';
            try {
                const r = await fetch(`api.php?action=artists_variants_list&artist_id=${artistVariantsCurrentId}`);
                const result = await r.json();
                if (!result.success) {
                    listEl.innerHTML = `<span style="color:#dc2626">${escapeHtml(result.message)}</span>`;
                    return;
                }
                renderVariantsList(result.data.variants);
            } catch (err) {
                listEl.innerHTML = '<span style="color:#dc2626">Failed to load variants</span>';
            }
        }

        function renderVariantsList(variants) {
            const listEl = document.getElementById('artistVariantsList');
            if (!variants.length) {
                listEl.innerHTML = '<span style="color:#9ca3af;font-size:0.9em">ยังไม่มี variants</span>';
                return;
            }
            listEl.innerHTML = variants.map(v => `
                <span style="display:inline-flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:16px;padding:4px 10px;margin:3px;font-size:0.9em">
                    ${escapeHtml(v.variant)}
                    <button onclick="deleteArtistVariant(${v.id})"
                        style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:1.1em;line-height:1;padding:0 0 0 2px;margin:0"
                        title="ลบ variant นี้">&times;</button>
                </span>
            `).join('');
        }

        async function addArtistVariant() {
            const input = document.getElementById('newVariantInput');
            const variant = input.value.trim();
            if (!variant) { showToast('กรุณากรอก variant name', 'error'); return; }
            if (!artistVariantsCurrentId) return;

            try {
                const r = await fetch('api.php?action=artists_variants_create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ artist_id: artistVariantsCurrentId, variant }),
                });
                const result = await r.json();
                if (result.success) {
                    input.value = '';
                    await loadArtistVariants();
                    showToast(result.message, 'success');
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Failed to add variant', 'error');
            }
        }

        async function deleteArtistVariant(id) {
            try {
                const r = await fetch(`api.php?action=artists_variants_delete&id=${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                });
                const result = await r.json();
                if (result.success) {
                    await loadArtistVariants();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Failed to delete variant', 'error');
            }
        }

        // ============================================================
        // Artist Bulk Selection & Bulk Add to Group
        // ============================================================

        function getSelectedArtistIds() {
            return [...document.querySelectorAll('.artist-checkbox:checked')].map(cb => parseInt(cb.dataset.id));
        }

        function updateArtistBulkToolbar() {
            const ids     = getSelectedArtistIds();
            const toolbar = document.getElementById('artistsBulkToolbar');
            const countEl = document.getElementById('artistsBulkCount');
            if (ids.length > 0) {
                toolbar.style.display = 'flex';
                countEl.textContent   = `เลือก ${ids.length} รายการ`;
            } else {
                toolbar.style.display = 'none';
            }
            // Sync select-all checkbox state
            const all  = document.querySelectorAll('.artist-checkbox');
            const chk  = document.getElementById('artistsSelectAll');
            if (chk) chk.checked = all.length > 0 && ids.length === all.length;
        }

        function selectAllArtists(checked) {
            document.querySelectorAll('.artist-checkbox').forEach(cb => { cb.checked = checked; });
            updateArtistBulkToolbar();
        }

        function clearArtistSelection() {
            document.querySelectorAll('.artist-checkbox').forEach(cb => { cb.checked = false; });
            const chk = document.getElementById('artistsSelectAll');
            if (chk) chk.checked = false;
            updateArtistBulkToolbar();
        }

        async function openBulkAddToGroupModal() {
            const ids = getSelectedArtistIds();
            if (!ids.length) { showToast('กรุณาเลือกศิลปินก่อน', 'error'); return; }
            // Load groups into select
            try {
                const r = await fetch('api.php?action=artists_groups');
                const result = await r.json();
                const sel = document.getElementById('bulkGroupSelect');
                sel.innerHTML = '<option value="">-- เลือกกลุ่ม --</option>';
                if (result.success) {
                    result.data.groups.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g.id;
                        opt.textContent = g.name;
                        sel.appendChild(opt);
                    });
                }
            } catch (err) { /* ignore */ }
            document.getElementById('bulkAddToGroupModal').classList.add('active');
        }

        function closeBulkAddToGroupModal() {
            document.getElementById('bulkAddToGroupModal').classList.remove('active');
        }

        async function confirmBulkAddToGroup() {
            const ids     = getSelectedArtistIds();
            const groupId = document.getElementById('bulkGroupSelect').value;
            if (!groupId) { showToast('กรุณาเลือกกลุ่ม', 'error'); return; }
            showLoading();
            try {
                const r = await fetch('api.php?action=artists_bulk_set_group', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ ids, group_id: parseInt(groupId) }),
                });
                const result = await r.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    closeBulkAddToGroupModal();
                    clearArtistSelection();
                    loadArtists();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Failed to update artists', 'error');
            } finally {
                hideLoading();
            }
        }

        async function artistsBulkClearGroup() {
            const ids = getSelectedArtistIds();
            if (!ids.length) { showToast('กรุณาเลือกศิลปินก่อน', 'error'); return; }
            if (!confirm(`ถอด ${ids.length} ศิลปินออกจากกลุ่มทั้งหมด?`)) return;
            showLoading();
            try {
                const r = await fetch('api.php?action=artists_bulk_set_group', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ ids, group_id: '' }),
                });
                const result = await r.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    clearArtistSelection();
                    loadArtists();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Failed to update artists', 'error');
            } finally {
                hideLoading();
            }
        }

        // ============================================================
        // Import Artists
        // ============================================================

        async function openImportArtistsModal() {
            // Reset to step 1
            document.getElementById('importArtistsTextarea').value = '';
            document.getElementById('importArtistsIsGroup').checked = false;
            document.getElementById('importArtistsGroupRow').style.display = 'block';
            document.getElementById('importArtistsStep1').style.display = 'block';
            document.getElementById('importArtistsStep2').style.display = 'none';
            document.getElementById('importArtistsSubmitBtn').style.display = '';
            document.getElementById('importArtistsSubmitBtn').textContent = 'นำเข้า';
            document.getElementById('importArtistsBackBtn').style.display = 'none';

            // Load groups
            try {
                const r = await fetch('api.php?action=artists_groups');
                const result = await r.json();
                const sel = document.getElementById('importArtistsGroupSelect');
                sel.innerHTML = '<option value="">-- ไม่สังกัดกลุ่ม --</option>';
                if (result.success) {
                    result.data.groups.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g.id;
                        opt.textContent = g.name;
                        sel.appendChild(opt);
                    });
                }
            } catch (_) {}

            document.getElementById('importArtistsModal').classList.add('active');
        }

        function closeImportArtistsModal() {
            document.getElementById('importArtistsModal').classList.remove('active');
        }

        function onImportIsGroupChange() {
            const isGroup = document.getElementById('importArtistsIsGroup').checked;
            // Groups cannot have a parent group
            document.getElementById('importArtistsGroupRow').style.display = isGroup ? 'none' : 'block';
        }

        function importArtistsGoBack() {
            document.getElementById('importArtistsStep1').style.display = 'block';
            document.getElementById('importArtistsStep2').style.display = 'none';
            document.getElementById('importArtistsSubmitBtn').style.display = '';
            document.getElementById('importArtistsSubmitBtn').textContent = 'นำเข้า';
            document.getElementById('importArtistsBackBtn').style.display = 'none';
        }

        async function submitImportArtists() {
            const raw     = document.getElementById('importArtistsTextarea').value;
            const isGroup = document.getElementById('importArtistsIsGroup').checked ? 1 : 0;
            const groupSel = document.getElementById('importArtistsGroupSelect');
            const groupId  = (!isGroup && groupSel.value) ? parseInt(groupSel.value) : null;

            // Parse names: split by newline, trim, deduplicate, remove empty
            const seen  = new Set();
            const names = raw.split('\n')
                .map(n => n.trim())
                .filter(n => n.length > 0)
                .filter(n => { if (seen.has(n)) return false; seen.add(n); return true; });

            if (!names.length) {
                showToast('กรุณากรอกชื่อศิลปินอย่างน้อย 1 ชื่อ', 'error');
                return;
            }

            showLoading();
            try {
                const r = await fetch('api.php?action=artists_bulk_import', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ names, is_group: isGroup, group_id: groupId }),
                });
                const result = await r.json();
                if (!result.success) {
                    showToast(result.message, 'error');
                    return;
                }

                // Show results step
                const results = result.data.results;
                const created = results.filter(r => r.status === 'created').length;
                const dupes   = results.filter(r => r.status === 'duplicate').length;
                const errors  = results.filter(r => r.status === 'error').length;

                const summaryEl = document.getElementById('importArtistsSummary');
                summaryEl.innerHTML = `✅ สร้างใหม่ <strong>${created}</strong> คน` +
                    (dupes  ? ` &nbsp;|&nbsp; ⚠️ ซ้ำ <strong>${dupes}</strong> คน`  : '') +
                    (errors ? ` &nbsp;|&nbsp; ❌ ข้อผิดพลาด <strong>${errors}</strong> คน` : '');
                summaryEl.style.background = errors ? '#fef2f2' : (dupes ? '#fffbeb' : '#f0fdf4');
                summaryEl.style.borderColor = errors ? '#fecaca' : (dupes ? '#fde68a' : '#bbf7d0');

                document.getElementById('importArtistsResults').innerHTML = results.map(r => {
                    const icon  = r.status === 'created' ? '✅' : r.status === 'duplicate' ? '⚠️' : '❌';
                    const color = r.status === 'created' ? '#166534' : r.status === 'duplicate' ? '#854d0e' : '#991b1b';
                    const label = r.status === 'created' ? 'สร้างแล้ว' : r.status === 'duplicate' ? 'ซ้ำ (ข้าม)' : `ผิดพลาด: ${escapeHtml(r.message || '')}`;
                    return `<div style="display:flex;align-items:center;gap:8px;padding:5px 6px;border-bottom:1px solid #f0f0f0">
                        <span>${icon}</span>
                        <span style="flex:1;font-size:0.9em">${escapeHtml(r.name)}</span>
                        <span style="font-size:0.8em;color:${color}">${label}</span>
                    </div>`;
                }).join('');

                document.getElementById('importArtistsStep1').style.display = 'none';
                document.getElementById('importArtistsStep2').style.display = 'block';
                document.getElementById('importArtistsSubmitBtn').style.display = 'none';
                document.getElementById('importArtistsBackBtn').style.display = '';

                if (created > 0) loadArtists();
            } catch (err) {
                showToast('Failed to import artists', 'error');
            } finally {
                hideLoading();
            }
        }

        // ================================================================
        // ARTIST TAG INPUT — factory (shared by single-edit + bulk-edit)
        // ================================================================
        function createArtistTagInput(wrapperId, textInputId, hiddenInputId, suggestionsId, publicName) {
            const wrapper      = document.getElementById(wrapperId);
            const textInput    = document.getElementById(textInputId);
            const hiddenInput  = document.getElementById(hiddenInputId);
            const suggestionsEl= document.getElementById(suggestionsId);

            if (!wrapper || !textInput || !hiddenInput || !suggestionsEl) return;

            let tags           = [];
            let suggestionData = [];
            let activeIdx      = -1;
            let debounceTimer  = null;

            function renderTags() {
                wrapper.querySelectorAll('.artist-tag').forEach(function (el) { el.remove(); });
                tags.forEach(function (name, i) {
                    const tag = document.createElement('span');
                    tag.className = 'artist-tag';
                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = name;
                    const removeBtn = document.createElement('span');
                    removeBtn.className = 'artist-tag-remove';
                    removeBtn.dataset.idx = i;
                    removeBtn.title = 'ลบ';
                    removeBtn.textContent = '×';
                    tag.appendChild(nameSpan);
                    tag.appendChild(removeBtn);
                    wrapper.insertBefore(tag, textInput);
                });
                hiddenInput.value = tags.join(', ');
            }

            function addTag(name) {
                name = name.trim();
                if (!name) return;
                if (tags.some(function (t) { return t.toLowerCase() === name.toLowerCase(); })) {
                    textInput.value = '';
                    hideSuggestions();
                    return;
                }
                tags.push(name);
                renderTags();
                textInput.value = '';
                hideSuggestions();
                if (typeof formChanged !== 'undefined') formChanged = true;
            }

            function removeTag(idx) {
                tags.splice(idx, 1);
                renderTags();
                if (typeof formChanged !== 'undefined') formChanged = true;
            }

            function showSuggestions(items) {
                suggestionData = items;
                activeIdx = -1;
                if (!items.length) { hideSuggestions(); return; }
                suggestionsEl.innerHTML = '';
                items.forEach(function (item, i) {
                    const div = document.createElement('div');
                    div.className = 'artist-suggestion-item';
                    div.dataset.idx = i;
                    const icon = item.is_group
                        ? '<span class="artist-suggestion-icon">🎵 group</span>'
                        : '<span class="artist-suggestion-icon">🎤 solo</span>';
                    div.innerHTML = icon + '<span>' + escapeHtml(item.name) + '</span>';
                    div.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        addTag(item.name);
                    });
                    suggestionsEl.appendChild(div);
                });
                suggestionsEl.style.display = 'block';
            }

            function hideSuggestions() {
                suggestionsEl.style.display = 'none';
                suggestionsEl.innerHTML = '';
                suggestionData = [];
                activeIdx = -1;
            }

            function highlightItem(idx) {
                suggestionsEl.querySelectorAll('.artist-suggestion-item').forEach(function (el, i) {
                    el.classList.toggle('active', i === idx);
                });
            }

            async function fetchSuggestions(q) {
                try {
                    const res = await fetch('api.php?action=artists_autocomplete&q=' + encodeURIComponent(q));
                    const result = await res.json();
                    if (!result.success) return [];
                    return result.data
                        .filter(function (a) {
                            return !tags.some(function (t) { return t.toLowerCase() === a.name.toLowerCase(); });
                        })
                        .map(function (a) { return { name: a.name, is_group: a.is_group }; });
                } catch (e) { return []; }
            }

            wrapper.addEventListener('click', function (e) {
                if (!e.target.classList.contains('artist-tag-remove')) { textInput.focus(); return; }
                removeTag(parseInt(e.target.dataset.idx, 10));
            });

            textInput.addEventListener('input', function () {
                const q = textInput.value.trim();
                clearTimeout(debounceTimer);
                if (!q) { hideSuggestions(); return; }
                debounceTimer = setTimeout(async function () {
                    showSuggestions(await fetchSuggestions(q));
                }, 180);
            });

            textInput.addEventListener('keydown', function (e) {
                const items = suggestionsEl.querySelectorAll('.artist-suggestion-item');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIdx = Math.min(activeIdx + 1, items.length - 1);
                    highlightItem(activeIdx); return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIdx = Math.max(activeIdx - 1, -1);
                    highlightItem(activeIdx); return;
                }
                if (e.key === 'Escape') { hideSuggestions(); return; }
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    if (activeIdx >= 0 && suggestionData[activeIdx]) {
                        addTag(suggestionData[activeIdx].name);
                    } else {
                        const q = textInput.value.replace(/,$/, '').trim();
                        if (q) addTag(q);
                    }
                    return;
                }
                if (e.key === 'Backspace' && textInput.value === '' && tags.length > 0) {
                    removeTag(tags.length - 1);
                }
            });

            textInput.addEventListener('blur', function () { setTimeout(hideSuggestions, 180); });
            textInput.addEventListener('focus', async function () {
                const q = textInput.value.trim();
                if (q) showSuggestions(await fetchSuggestions(q));
            });

            const api = {
                setValue: function (csv) {
                    tags = csv ? csv.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];
                    textInput.value = '';
                    hideSuggestions();
                    renderTags();
                },
                reset: function () {
                    tags = [];
                    textInput.value = '';
                    hiddenInput.value = '';
                    hideSuggestions();
                    renderTags();
                },
            };
            if (publicName) window[publicName] = api;
            if (hiddenInput.value) api.setValue(hiddenInput.value);
            return api;
        }

        // ================================================================
        // ARTIST TAG INPUT — initialise both instances via factory
        // ================================================================
        createArtistTagInput(
            'artistTagWrapper', 'categoriesInput', 'categories', 'artistSuggestions',
            'artistTagInput'
        );
        createArtistTagInput(
            'bulkArtistTagWrapper', 'bulkCategoriesInput', 'bulkEditCategories', 'bulkArtistSuggestions',
            'bulkArtistTagInput'
        );

    </script>
</body>
</html>
