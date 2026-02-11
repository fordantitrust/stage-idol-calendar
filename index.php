<?php
require_once 'config.php';
require_once 'IcsParser.php';

// Security headers
send_security_headers();

$parser = new IcsParser('ics');

// ดึงข้อมูลทั้งหมด
$allEvents = $parser->getAllEvents();
$artists = $parser->getAllOrganizers();
$venues = $parser->getAllLocations();

// รับค่า filter จาก GET parameters (รองรับหลายค่า) with sanitization
$filterArtists = get_sanitized_array_param('artist', 200, 50);
$filterVenues = get_sanitized_array_param('venue', 200, 50);

// 🚀 Optimization P1: Pre-normalize categories + Create lookup arrays for O(1) search
$normalizedEvents = array_map(function($event) {
    $event['categoriesArray'] = !empty($event['categories'])
        ? array_map('trim', explode(',', $event['categories']))
        : [];
    return $event;
}, $allEvents);

// Create lookup arrays (O(1) search instead of O(n) with in_array)
$filterArtistsSet = array_flip($filterArtists);
$filterVenuesSet = array_flip($filterVenues);

// กรองข้อมูล (ใช้ CATEGORIES สำหรับศิลปิน - รองรับหลายค่าแยกด้วย comma)
$filteredEvents = array_filter($normalizedEvents, function($event) use ($filterArtistsSet, $filterVenuesSet) {
    // ตรวจสอบ artist/categories (รองรับหลายค่าแยกด้วย comma)
    $artistMatch = empty($filterArtistsSet);
    if (!$artistMatch) {
        // Use isset() instead of in_array() for O(1) lookup
        foreach ($event['categoriesArray'] as $category) {
            if (isset($filterArtistsSet[$category])) {
                $artistMatch = true;
                break;
            }
        }
    }

    // Check venue with O(1) lookup
    $venueMatch = empty($filterVenuesSet) || isset($filterVenuesSet[$event['location'] ?? null]);
    return $artistMatch && $venueMatch;
});

// จัดกลุ่มข้อมูลตามวัน
$eventsByDay = [];
foreach ($filteredEvents as $event) {
    $timestamp = strtotime($event['start']);
    $dayKey = date('Y-m-d', $timestamp);
    if (!isset($eventsByDay[$dayKey])) {
        $eventsByDay[$dayKey] = [];
    }
    $eventsByDay[$dayKey][] = $event;
}

ksort($eventsByDay);

// เรียงลำดับ events ภายในแต่ละวันตามเวลา start
foreach ($eventsByDay as $dayKey => &$dayEvents) {
    usort($dayEvents, function($a, $b) {
        return strtotime($a['start']) - strtotime($b['start']);
    });
}
unset($dayEvents); // ยกเลิก reference
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Idol Stage Timetable - Event Schedule Management</title>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-JBRL4XB417"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-JBRL4XB417');
    </script>
    <!-- Shared CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <style>
        /* Index page specific styles */
        .container {
            max-width: 1200px;
        }

        header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .version-display {
            position: absolute;
            top: 20px;
            left: 20px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filters {
            padding: 30px;
            background: #FFF8F9;
            border-bottom: 2px solid #FCE4EC;
        }

        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
        }

        .filter-item label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
            font-size: 0.95em;
        }

        .search-box-wrapper {
            position: relative;
            margin-bottom: 10px;
        }

        .search-box {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
            background: white;
        }

        .search-box:focus {
            outline: none;
            border-color: #E91E63;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        .search-box::placeholder {
            color: #adb5bd;
        }

        .search-clear-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            border: none;
            background: #dee2e6;
            color: #495057;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            padding: 0;
        }

        .search-clear-btn:hover {
            background: #E91E63;
            color: white;
        }

        .search-box-wrapper.has-text .search-clear-btn {
            display: flex;
        }

        /* Selected tags area */
        .selected-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
            min-height: 0;
        }

        .selected-tags:empty {
            display: none;
        }

        .selected-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px 4px 10px;
            background: linear-gradient(135deg, #FFB7C5 0%, #F48FB1 100%);
            color: #880E4F;
            border-radius: 16px;
            font-size: 0.8em;
            font-weight: 500;
            animation: tagAppear 0.2s ease-out;
        }

        @keyframes tagAppear {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        .selected-tag .tag-remove {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: rgba(136, 14, 79, 0.2);
            border: none;
            border-radius: 50%;
            color: #880E4F;
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
            padding: 0;
            transition: all 0.2s;
        }

        .selected-tag .tag-remove:hover {
            background: #C2185B;
            color: white;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 15px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 10px;
            border-radius: 6px;
            transition: all 0.2s;
            user-select: none;
        }

        .checkbox-label:hover {
            background: #FFF0F3;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #E91E63;
        }

        .checkbox-label span {
            color: #495057;
            font-size: 0.95em;
        }

        .checkbox-label input[type="checkbox"]:checked + span {
            color: #E91E63;
            font-weight: 600;
        }

        .no-options {
            color: #6c757d;
            font-size: 0.9em;
            text-align: center;
            padding: 20px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(86, 171, 47, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #ffc107 100%);
            color: white;
        }
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
        }

        .calendar-container {
            padding: 30px;
        }

        .day-section {
            margin-bottom: 40px;
        }

        .day-header {
            background: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 1.3em;
            font-weight: 600;
        }

        .events-table-container {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            -webkit-overflow-scrolling: touch;
        }

        .events-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }

        .events-table thead {
            background: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
            color: white;
        }

        .events-table thead th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95em;
            white-space: nowrap;
        }

        .events-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
        }

        .events-table tbody tr:hover {
            background: #FFF8F9;
        }

        .events-table tbody tr:last-child {
            border-bottom: none;
        }

        .events-table tbody td {
            padding: 15px 12px;
            vertical-align: top;
        }

        .event-datetime-cell {
            white-space: nowrap;
            width: 12%;
            vertical-align: middle;
        }

        .event-info-cell {
            width: 30%;
            vertical-align: middle;
        }

        .event-title-name {
            font-weight: 600;
            color: #212529;
            font-size: 1em;
            margin-bottom: 4px;
        }

        .event-description {
            font-size: 0.85em;
            color: #6c757d;
            font-style: italic;
            margin-top: 4px;
            line-height: 1.4;
        }

        .event-venue-cell {
            width: 35%;
            vertical-align: middle;
        }

        .event-categories-cell {
            width: 23%;
            vertical-align: middle;
        }

        .event-categories-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            background: #FFF0F3;
            color: #C2185B;
        }

        .no-events {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-events-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .version-display {
                font-size: 0.65em;
                padding: 5px 10px;
                top: 10px;
                left: 10px;
            }

            header h1 {
                font-size: 1.4em;
                margin-bottom: 8px;
            }

            header p {
                font-size: 0.85em;
            }

            .filters {
                padding: 20px 15px;
            }

            .filter-group {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .search-box {
                padding: 12px 40px 12px 12px;
                font-size: 16px;
                min-height: 44px;
            }

            .search-clear-btn {
                width: 28px;
                height: 28px;
                right: 8px;
            }

            .checkbox-group {
                max-height: 180px;
                padding: 10px;
            }

            .checkbox-label {
                padding: 10px;
                min-height: 44px;
            }

            .checkbox-label input[type="checkbox"] {
                width: 22px;
                height: 22px;
            }

            .checkbox-label span {
                font-size: 1em;
            }

            .filter-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
                padding: 14px 20px;
                font-size: 1em;
                min-height: 48px;
            }

            .calendar-container {
                padding: 15px 10px;
            }

            .day-section {
                margin-bottom: 30px;
            }

            .day-header {
                padding: 12px 15px;
                font-size: 1.1em;
                border-radius: 8px;
            }

            /* Mobile Card Layout */
            .events-table-container {
                background: transparent;
                box-shadow: none;
                border-radius: 0;
            }

            .events-table {
                display: block;
                font-size: 0.9em;
            }

            .events-table thead {
                display: none;
            }

            .events-table tbody {
                display: block;
            }

            .events-table tbody tr {
                display: block;
                background: white;
                border-radius: 10px;
                margin-bottom: 12px;
                padding: 15px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                border: none;
            }

            .events-table tbody tr:hover {
                background: white;
                box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            }

            .events-table tbody td {
                display: block;
                padding: 8px 0;
                width: 100% !important;
                border: none;
            }

            .event-datetime-cell {
                font-size: 0.95em;
                font-weight: 600;
                color: #E91E63;
                padding-bottom: 10px;
                border-bottom: 1px solid #FCE4EC;
                margin-bottom: 10px;
            }

            .event-datetime-cell::before {
                content: "🕐 ";
                margin-right: 4px;
            }

            .event-info-cell {
                padding-bottom: 8px;
            }

            .event-info-cell::before {
                content: "🎤 ";
                margin-right: 4px;
                vertical-align: top;
                display: inline-block;
                margin-top: 2px;
            }

            .event-title-name {
                font-size: 1.05em;
                font-weight: 600;
                color: #212529;
                display: inline;
            }

            .event-description {
                font-size: 0.9em;
                color: #6c757d;
                margin-top: 6px;
                padding-left: 20px;
                line-height: 1.5;
            }

            .event-venue-cell {
                font-size: 0.95em;
                color: #495057;
                padding: 8px 0;
            }

            .event-venue-cell::before {
                content: "📍 ";
                margin-right: 4px;
                font-size: 0.9em;
            }

            .event-categories-cell {
                padding-top: 10px;
            }

            .event-categories-badge {
                display: inline-block;
                width: 100%;
                text-align: center;
                padding: 8px 12px;
                margin-top: 5px;
            }

            .no-events {
                padding: 40px 15px;
            }

            .no-events-icon {
                font-size: 3em;
            }

            .no-events h2 {
                font-size: 1.3em;
            }
        }

        /* Extra small devices */
        @media (max-width: 375px) {
            header h1 {
                font-size: 1.2em;
            }

            header p {
                font-size: 0.8em;
            }

            .day-header {
                font-size: 1em;
                padding: 10px 12px;
            }

            .events-table tbody tr {
                padding: 12px;
            }

            .btn {
                padding: 12px 16px;
                font-size: 0.95em;
            }
        }

        /* Landscape mode on mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            header {
                padding: 40px 15px 15px;
            }

            header h1 {
                font-size: 1.3em;
            }

            .filters {
                padding: 15px;
            }

            .calendar-container {
                padding: 15px;
            }
        }

        /* ========================================
           View Toggle Switch Styles
           ======================================== */
        .view-toggle {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px 0;
            border-top: 1px solid #FCE4EC;
            margin-top: 15px;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }

        .toggle-text {
            font-size: 0.95em;
            color: #6c757d;
            font-weight: 500;
            transition: color 0.3s, font-weight 0.3s;
            min-width: 70px;
        }

        .toggle-text:first-child {
            text-align: right;
        }

        .toggle-text:last-child {
            text-align: left;
        }

        .toggle-text.active {
            color: var(--sakura-dark);
            font-weight: 600;
        }

        .toggle-switch {
            position: relative;
            width: 56px;
            height: 28px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #dee2e6;
            border-radius: 28px;
            transition: 0.3s;
        }

        .toggle-slider::before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: var(--sakura-gradient);
        }

        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(28px);
        }

        /* ========================================
           Horizontal Gantt Chart Styles
           ======================================== */
        .gantt-view {
            margin-top: 15px;
            overflow-x: auto;
            position: relative;
        }

        /* Scroll indicator shadows — hint that horizontal scroll is available (iOS has no scrollbar) */
        .gantt-view::before,
        .gantt-view::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 24px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 5;
        }
        .gantt-view::before {
            left: 0;
            background: linear-gradient(to right, rgba(233, 30, 99, 0.18), transparent);
        }
        .gantt-view::after {
            right: 0;
            background: linear-gradient(to left, rgba(233, 30, 99, 0.18), transparent);
        }
        .gantt-view.has-scroll-left::before {
            opacity: 1;
        }
        .gantt-view.has-scroll-right::after {
            opacity: 1;
        }

        .gantt-chart {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            min-width: 800px;
        }

        /* Gantt Header - Time axis */
        .gantt-header {
            display: flex;
            background: var(--sakura-gradient);
            color: white;
            font-weight: 600;
            font-size: 0.85em;
        }

        .gantt-header-venue {
            width: 120px;
            min-width: 120px;
            padding: 10px;
            border-right: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .gantt-header-timeline {
            flex: 1;
            display: flex;
        }

        .gantt-header-hour {
            flex: 1;
            text-align: center;
            padding: 10px 0;
            border-left: 1px solid rgba(255,255,255,0.2);
            min-width: 50px;
        }

        /* Gantt Body */
        .gantt-body {
            position: relative;
        }

        /* Venue Row */
        .gantt-row {
            display: flex;
            border-bottom: 1px solid #e9ecef;
            min-height: 60px;
        }

        .gantt-row:last-child {
            border-bottom: none;
        }

        .gantt-row:nth-child(even) {
            background: #fef7f9;
        }

        /* Venue Name */
        .gantt-venue-name {
            width: 120px;
            min-width: 120px;
            padding: 10px;
            background: #fff8f9;
            border-right: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 0.8em;
            color: var(--sakura-dark);
            word-break: break-word;
        }

        /* Timeline Container */
        .gantt-timeline {
            flex: 1;
            position: relative;
            min-height: 60px;
        }

        /* Time Grid Lines */
        .gantt-grid {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            pointer-events: none;
        }

        .gantt-grid-hour {
            flex: 1;
            border-left: 1px dashed #e9ecef;
            min-width: 50px;
        }

        .gantt-grid-hour:first-child {
            border-left: none;
        }

        /* Event Bar */
        .gantt-event {
            position: absolute;
            top: 5px;
            height: calc(100% - 10px);
            min-height: 50px;
            background: linear-gradient(135deg, #FFB7C5 0%, #F48FB1 50%, #E91E63 100%);
            border-radius: 6px;
            padding: 4px 8px;
            box-sizing: border-box;
            cursor: pointer;
            overflow: hidden;
            transition: transform 0.15s, box-shadow 0.15s, z-index 0s;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(233, 30, 99, 0.3);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .gantt-event:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.5);
            z-index: 10;
        }

        .gantt-event-title {
            font-weight: 600;
            color: white;
            font-size: 0.75em;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .gantt-event-time {
            color: rgba(255,255,255,0.9);
            font-size: 0.65em;
            margin-top: 2px;
            white-space: nowrap;
        }

        .gantt-event-categories {
            color: rgba(255,255,255,0.85);
            font-size: 0.6em;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Overlapping events indicator */
        .gantt-event.has-overlap {
            border: 2px solid white;
        }

        /* Stack multiple events in same time slot */
        .gantt-event.stack-1 { top: 5px; height: calc(50% - 7px); }
        .gantt-event.stack-2 { top: calc(50% + 2px); height: calc(50% - 7px); }
        .gantt-event.stack-3 { top: 5px; height: calc(33% - 5px); }
        .gantt-event.stack-4 { top: calc(33% + 2px); height: calc(33% - 5px); }
        .gantt-event.stack-5 { top: calc(66% + 2px); height: calc(33% - 5px); }

        /* Gantt Tooltip */
        .gantt-event-tooltip {
            position: fixed;
            background: white;
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 320px;
            font-size: 0.9em;
            display: none;
            border: 2px solid var(--sakura-light);
        }

        .gantt-event-tooltip.show {
            display: block;
        }

        .gantt-event-tooltip h4 {
            color: var(--sakura-dark);
            margin: 0 0 10px 0;
            font-size: 1.1em;
            padding-right: 25px;
        }

        .gantt-event-tooltip p {
            color: #495057;
            margin: 6px 0;
            line-height: 1.4;
        }

        .gantt-event-tooltip p strong {
            color: #212529;
        }

        .tooltip-close {
            position: absolute;
            top: 8px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.4em;
            color: #adb5bd;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .tooltip-close:hover {
            color: var(--sakura-dark);
            background: var(--sakura-light);
        }

        /* Legend for overlap */
        .gantt-legend {
            display: flex;
            gap: 15px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 0.8em;
            color: #6c757d;
            flex-wrap: wrap;
        }

        .gantt-legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .gantt-legend-bar {
            width: 30px;
            height: 14px;
            border-radius: 3px;
            background: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
        }

        .gantt-legend-bar.overlap {
            border: 2px solid white;
            box-shadow: 0 0 0 1px #E91E63;
        }

        /* ========================================
           Vertical Gantt Chart Styles
           ======================================== */
        .gantt-chart.vertical {
            min-width: auto;
            overflow-x: auto;
        }

        .gantt-header-vertical {
            display: flex;
            background: var(--sakura-gradient);
            color: white;
            font-weight: 600;
            font-size: 0.85em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .gantt-time-label {
            width: 60px;
            min-width: 60px;
            padding: 10px 5px;
            text-align: center;
            border-right: 1px solid rgba(255,255,255,0.2);
        }

        .gantt-venue-header {
            flex: 1;
            min-width: 100px;
            padding: 10px 5px;
            text-align: center;
            border-left: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .gantt-body-vertical {
            display: flex;
            position: relative;
            min-height: 720px;
        }

        .gantt-time-axis {
            width: 60px;
            min-width: 60px;
            background: #fff8f9;
            border-right: 1px solid #e9ecef;
        }

        .gantt-time-slot {
            height: 80px;
            padding: 5px;
            font-size: 0.75em;
            font-weight: 600;
            color: var(--sakura-dark);
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .gantt-venue-column {
            flex: 1;
            min-width: 100px;
            position: relative;
            border-left: 1px solid #e9ecef;
        }

        .gantt-venue-column:nth-child(even) {
            background: #fef7f9;
        }

        .gantt-grid-vertical {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
        }

        .gantt-grid-slot {
            height: 80px;
            border-bottom: 1px dashed #e9ecef;
        }

        /* Vertical Event Bar */
        .gantt-event-vertical {
            position: absolute;
            left: 5px;
            right: 5px;
            background: linear-gradient(180deg, #FFB7C5 0%, #F48FB1 50%, #E91E63 100%);
            border-radius: 6px;
            padding: 6px 8px;
            box-sizing: border-box;
            cursor: pointer;
            overflow: hidden;
            transition: transform 0.15s, box-shadow 0.15s, z-index 0s;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(233, 30, 99, 0.3);
            min-height: 38px;
        }

        .gantt-event-vertical:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.5);
            z-index: 10;
        }

        .gantt-event-vertical.has-overlap {
            border: 2px solid white;
        }

        /* Horizontal stacking for overlaps in vertical layout */
        .gantt-event-vertical.stack-h-1 { left: 5px; right: calc(50% + 2px); }
        .gantt-event-vertical.stack-h-2 { left: calc(50% + 2px); right: 5px; }
        .gantt-event-vertical.stack-h-3 { left: 5px; right: calc(66% + 2px); }
        .gantt-event-vertical.stack-h-4 { left: calc(33% + 2px); right: calc(33% + 2px); }
        .gantt-event-vertical.stack-h-5 { left: calc(66% + 2px); right: 5px; }

        .gantt-event-time-v {
            color: rgba(255,255,255,0.95);
            font-size: 0.7em;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .gantt-event-title-v {
            font-weight: 600;
            color: white;
            font-size: 0.75em;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        /* ========================================
           Gantt Mobile Responsive
           ======================================== */
        @media (max-width: 768px) {
            .view-toggle {
                padding: 12px 0;
            }

            .toggle-text {
                font-size: 0.85em;
                min-width: 55px;
            }

            .toggle-switch {
                width: 48px;
                height: 24px;
            }

            .toggle-slider::before {
                height: 18px;
                width: 18px;
            }

            .toggle-switch input:checked + .toggle-slider::before {
                transform: translateX(24px);
            }

            .gantt-chart {
                min-width: 600px;
            }

            .gantt-chart.vertical {
                min-width: auto;
            }

            .gantt-header-venue,
            .gantt-venue-name {
                width: 80px;
                min-width: 80px;
                font-size: 0.7em;
            }

            /* Vertical Gantt Mobile */
            .gantt-time-label {
                width: 50px;
                min-width: 50px;
                font-size: 0.75em;
            }

            .gantt-time-axis {
                width: 50px;
                min-width: 50px;
            }

            .gantt-venue-header {
                min-width: 80px;
                font-size: 0.75em;
            }

            .gantt-venue-column {
                min-width: 80px;
            }

            .gantt-time-slot {
                height: 65px;
                font-size: 0.7em;
            }

            .gantt-grid-slot {
                height: 65px;
            }

            .gantt-event-vertical {
                left: 3px;
                right: 3px;
                padding: 4px 5px;
                min-height: 32px;
            }

            .gantt-event-time-v {
                font-size: 0.65em;
            }

            .gantt-event-title-v {
                font-size: 0.65em;
                -webkit-line-clamp: 1;
            }

            .gantt-event-tooltip {
                max-width: 90%;
                left: 5% !important;
                right: 5%;
            }

            .gantt-row {
                min-height: 50px;
            }

            .gantt-event {
                min-height: 40px;
            }

            .gantt-event-title {
                font-size: 0.7em;
            }

            .gantt-event-time {
                font-size: 0.6em;
            }
        }

        @media (max-width: 375px) {
            .toggle-text {
                font-size: 0.8em;
                min-width: 45px;
            }

            .gantt-header-venue,
            .gantt-venue-name {
                width: 60px;
                min-width: 60px;
                font-size: 0.65em;
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="version-display">
                <span>v</span>
                <span><?php echo APP_VERSION; ?></span>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="header.title">Idol Stage Timetable - Idol Stage Event</h1>
            <h2 data-i18n="header.subtitle">Idol stage event calendar</h2>
            <p data-i18n="header.disclaimer">* Please check the latest information again. We are not responsible for any errors that may occur during the preparation of this document.</p>
            <nav class="header-nav">
                <a href="how-to-use.php" class="header-nav-link" data-i18n="footer.howToUse">📖 วิธีการใช้งาน</a>
                <a href="contact.php" class="header-nav-link" data-i18n="footer.contact">✉️ ติดต่อเรา</a>
                <a href="credits.php" class="header-nav-link" data-i18n="footer.credits">📋 Credits</a>
                <a href="#data-version" class="header-nav-link">🔄️ <?php echo get_data_version(); ?></a>
            </nav>
        </header>

        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <div class="filter-item">
                        <label data-i18n="filter.artist">🎤 กรองตามวง/ศิลปิน:</label>
                        <div class="search-box-wrapper" id="artistSearchWrapper">
                            <input type="text" class="search-box" id="artistSearch" data-i18n-placeholder="filter.searchArtist" placeholder="🔍 ค้นหาชื่อวง..." oninput="handleSearchInput('artistSearch', 'artistCheckboxes', 'artistSearchWrapper')" onfocus="this.select()">
                            <button type="button" class="search-clear-btn" onclick="clearSearch('artistSearch', 'artistCheckboxes', 'artistSearchWrapper')" title="ล้างการค้นหา">✕</button>
                        </div>
                        <?php if (!empty($filterArtists)): ?>
                        <div class="selected-tags" id="selectedArtists">
                            <?php foreach ($filterArtists as $artist): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($artist); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('artist', '<?php echo htmlspecialchars(addslashes($artist)); ?>')" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="artistCheckboxes">
                            <?php foreach ($artists as $artist): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="artist[]" value="<?php echo htmlspecialchars($artist); ?>"
                                           <?php echo (in_array($artist, $filterArtists)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($artist); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($artists)): ?>
                                <p class="no-options">ไม่มีข้อมูลศิลปิน</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (VENUE_MODE === 'multi'): ?>
                    <div class="filter-item">
                        <label data-i18n="filter.venue">🏛️ กรองตามเวที:</label>
                        <div class="search-box-wrapper" id="venueSearchWrapper">
                            <input type="text" class="search-box" id="venueSearch" data-i18n-placeholder="filter.searchVenue" placeholder="🔍 ค้นหาชื่อเวที..." oninput="handleSearchInput('venueSearch', 'venueCheckboxes', 'venueSearchWrapper')" onfocus="this.select()">
                            <button type="button" class="search-clear-btn" onclick="clearSearch('venueSearch', 'venueCheckboxes', 'venueSearchWrapper')" title="ล้างการค้นหา">✕</button>
                        </div>
                        <?php if (!empty($filterVenues)): ?>
                        <div class="selected-tags" id="selectedVenues">
                            <?php foreach ($filterVenues as $venue): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($venue); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('venue', '<?php echo htmlspecialchars(addslashes($venue)); ?>')" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="venueCheckboxes">
                            <?php foreach ($venues as $venue): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="venue[]" value="<?php echo htmlspecialchars($venue); ?>"
                                           <?php echo (in_array($venue, $filterVenues)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($venue); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($venues)): ?>
                                <p class="no-options">ไม่มีข้อมูลเวที</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary" data-i18n="button.search">🔍 ค้นหา</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'" data-i18n="button.reset">🔄 รีเซ็ต</button>
                    <button type="button" class="btn btn-success" onclick="saveAsImage()" data-i18n="button.saveImage">📸 บันทึกเป็นรูปภาพ</button>
                    <button type="button" class="btn btn-primary" onclick="exportToIcs()" data-i18n="button.exportIcs">📅 Export to Calendar</button>
                    <button type="button" class="btn btn-warning" onclick="openRequestModal()" data-i18n="button.requestAdd">📝 แจ้งเพิ่ม Event</button>
                </div>

                <!-- View Toggle Switch -->
                <?php if (VENUE_MODE === 'multi'): ?>
                <div class="view-toggle">
                    <label class="toggle-label">
                        <span class="toggle-text active" data-i18n="view.list">รายการ</span>
                        <div class="toggle-switch">
                            <input type="checkbox" id="viewToggle" onchange="toggleView(this.checked)">
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-text" data-i18n="view.gantt">ไทม์ไลน์</span>
                    </label>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="calendar-container">
            <?php if (empty($filteredEvents)): ?>
                <div class="no-events">
                    <div class="no-events-icon">📅</div>
                    <h2 data-i18n="message.noEvents">ไม่พบกิจกรรม</h2>
                </div>
            <?php else: ?>
                <?php foreach ($eventsByDay as $dayKey => $events): ?>
                    <?php
                        $firstEventTimestamp = strtotime($events[0]['start']);
                        $day = date('d', $firstEventTimestamp);
                        $month = date('m', $firstEventTimestamp);
                        $year = date('Y', $firstEventTimestamp);
                        $dayOfWeek = date('w', $firstEventTimestamp);
                    ?>
                    <div class="day-section">
                        <div class="day-header" data-day="<?php echo $day; ?>" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>" data-dayofweek="<?php echo $dayOfWeek; ?>">
                            📅 <span class="day-header-text"><?php echo $day . '/' . $month . '/' . $year; ?></span>
                            <span class="day-name-header" style="margin-left: 8px;"></span>
                        </div>

                        <div class="events-table-container">
                            <table class="events-table">
                                <thead>
                                    <tr>
                                        <th data-i18n="table.time">เวลา</th>
                                        <th data-i18n="table.event">การแสดง/ศิลปิน</th>
                                        <?php if (VENUE_MODE === 'multi'): ?>
                                        <th data-i18n="table.venue">เวที</th>
                                        <?php endif; ?>
                                        <th data-i18n="table.categories">ศิลปินที่เกี่ยวข้อง</th>
                                        <th class="col-edit-request" style="width:80px;text-align:center;" data-i18n="table.editRequest">แจ้งแก้ไข</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td class="event-datetime-cell">
                                                <span class="event-time"
                                                      data-start="<?php echo date('H:i', strtotime($event['start'])); ?>"
                                                      data-end="<?php echo date('H:i', strtotime($event['end'])); ?>"></span>
                                            </td>
                                            <td class="event-info-cell">
                                                <?php if (!empty($event['title'])): ?>
                                                    <div class="event-title-name">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </div>
                                                    <?php if (!empty($event['description'])): ?>
                                                        <div class="event-description">
                                                            <?php echo htmlspecialchars($event['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: #adb5bd;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (VENUE_MODE === 'multi'): ?>
                                            <td class="event-venue-cell">
                                                <?php if (!empty($event['location'])): ?>
                                                    <span style="color: #212529; font-size: 0.95em;">
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #adb5bd;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td class="event-categories-cell">
                                                <?php if (!empty($event['categories'])): ?>
                                                    <span class="event-categories-badge">
                                                        <?php echo htmlspecialchars($event['categories']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #adb5bd;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="event-action-cell" style="text-align:center;">
                                                <button type="button" class="btn-edit-request"
                                                    data-event='<?php echo htmlspecialchars(json_encode([
                                                        'id' => $event['id'] ?? null,
                                                        'title' => $event['title'] ?? '',
                                                        'location' => $event['location'] ?? '',
                                                        'organizer' => $event['organizer'] ?? '',
                                                        'categories' => $event['categories'] ?? '',
                                                        'description' => $event['description'] ?? '',
                                                        'start' => $event['start'] ?? '',
                                                        'end' => $event['end'] ?? ''
                                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
                                                    onclick="openModifyModal(JSON.parse(this.dataset.event))">
                                                    ✏️
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Events data for Gantt chart -->
                        <script type="application/json" class="events-data">
                        <?php echo json_encode(array_values($events), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>
                        </script>

                        <!-- Gantt view container -->
                        <div class="gantt-view" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 JP EXPO TH Unofficial Calendar. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <!-- Venues data for Gantt chart -->
    <script>
        window.VENUES_DATA = <?php echo json_encode(array_values($venues), JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <!-- Shared JavaScript (includes translations and common functions) -->
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>

    <!-- Request Modal -->
    <div id="requestModal" class="req-modal-overlay">
        <div class="req-modal">
            <div class="req-modal-header">
                <h2 id="modalTitle">📝 แจ้งเพิ่ม Event</h2>
                <button onclick="closeRequestModal()" class="req-close">&times;</button>
            </div>
            <form id="requestForm" onsubmit="submitRequest(event)">
                <input type="hidden" id="reqType" value="add">
                <input type="hidden" id="reqEventId" value="">
                <div class="req-modal-body">
                    <div class="req-row">
                        <div class="req-group"><label>ชื่อ Event *</label><input type="text" id="reqTitle" required maxlength="200"></div>
                        <div class="req-group"><label>Organizer</label><input type="text" id="reqOrganizer" maxlength="200"></div>
                    </div>
                    <div class="req-row">
                        <div class="req-group">
                            <label>เวที</label>
                            <select id="reqLocation">
                                <option value="">-- เลือก --</option>
                                <?php foreach ($venues as $v): ?><option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="req-group"><label>Categories</label><input type="text" id="reqCategories" maxlength="500"></div>
                    </div>
                    <div class="req-row">
                        <div class="req-group"><label>วันที่ *</label><input type="date" id="reqDate" required></div>
                        <div class="req-group"><label>เริ่ม *</label><input type="time" id="reqStart" required></div>
                        <div class="req-group"><label>สิ้นสุด *</label><input type="time" id="reqEnd" required></div>
                    </div>
                    <div class="req-group"><label>รายละเอียด</label><textarea id="reqDesc" rows="2" maxlength="2000"></textarea></div>
                    <hr style="margin:15px 0;border:none;border-top:1px solid #ddd;">
                    <div class="req-row">
                        <div class="req-group"><label>ชื่อผู้แจ้ง *</label><input type="text" id="reqName" required maxlength="100"></div>
                        <div class="req-group"><label>Email</label><input type="email" id="reqEmail" maxlength="200"></div>
                    </div>
                    <div class="req-group"><label>หมายเหตุ</label><textarea id="reqNote" rows="2" maxlength="1000" placeholder="แหล่งข้อมูล, เหตุผล"></textarea></div>
                </div>
                <div class="req-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="reqSubmitBtn">ส่งคำขอ</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    .req-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;justify-content:center;align-items:center;z-index:2000;padding:20px;box-sizing:border-box}
    .req-modal-overlay.active{display:flex}
    .req-modal{background:#fff;border-radius:12px;width:100%;max-width:600px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 15px 50px rgba(0,0,0,.3)}
    .req-modal-header{display:flex;justify-content:space-between;align-items:center;padding:15px 20px;background:var(--sakura-gradient);color:#fff;flex-shrink:0}
    .req-modal-header h2{margin:0;font-size:1.1rem}
    .req-close{background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer}
    .req-modal form{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
    .req-modal-body{padding:20px;overflow-y:auto;flex:1;min-height:0}
    .req-modal-footer{padding:12px 20px;background:#f8f9fa;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #ddd;flex-shrink:0}
    .req-group{margin-bottom:12px}
    .req-group label{display:block;margin-bottom:4px;font-weight:500;font-size:.9rem;color:#333}
    .req-group input,.req-group select,.req-group textarea{width:100%;padding:8px 10px;border:2px solid #ddd;border-radius:6px;font-size:.9rem;box-sizing:border-box}
    .req-group input:focus,.req-group select:focus,.req-group textarea:focus{outline:none;border-color:var(--sakura-medium)}
    .req-row{display:flex;gap:12px}
    .req-row .req-group{flex:1}
    .req-types{display:flex;gap:12px}
    .req-type{display:flex;align-items:center;gap:5px;padding:8px 12px;border:2px solid #ddd;border-radius:6px;cursor:pointer;font-size:.9rem}
    .req-type:has(input:checked){border-color:var(--sakura-medium);background:#fce4ec}
    .req-type input{width:auto}
    @media(max-width:600px){.req-row{flex-direction:column;gap:0}}
    .btn-edit-request{background:none;border:1px solid #ddd;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:1rem;transition:all .2s}
    .btn-edit-request:hover{background:#fff3e0;border-color:#ff9800}
    .event-action-cell{white-space:nowrap}
    @media(max-width:768px){
        .event-action-cell{text-align:right!important;padding-top:5px;border-top:1px dashed #eee;margin-top:5px}
        .event-action-cell::before{content:"";margin-right:0!important}
        .btn-edit-request{padding:8px 14px;font-size:1.1rem}
    }
    </style>

    <script>
    const VENUE_MODE = '<?php echo VENUE_MODE; ?>';

    function openRequestModal() {
        document.getElementById('requestForm').reset();
        document.getElementById('reqType').value = 'add';
        document.getElementById('reqEventId').value = '';
        document.getElementById('modalTitle').textContent = '📝 แจ้งเพิ่ม Event';
        document.getElementById('reqDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('requestModal').classList.add('active');
    }

    function openModifyModal(eventData) {
        document.getElementById('requestForm').reset();
        document.getElementById('reqType').value = 'modify';
        document.getElementById('reqEventId').value = eventData.id || '';
        document.getElementById('modalTitle').textContent = translations[currentLang]['modal.editTitle'] || '✏️ แจ้งแก้ไข Event';

        // Fill form with event data
        document.getElementById('reqTitle').value = eventData.title || '';
        document.getElementById('reqOrganizer').value = eventData.organizer || '';
        document.getElementById('reqLocation').value = eventData.location || '';
        document.getElementById('reqCategories').value = eventData.categories || '';
        document.getElementById('reqDesc').value = eventData.description || '';

        // Parse start datetime
        if (eventData.start) {
            const startDate = new Date(eventData.start);
            document.getElementById('reqDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('reqStart').value = startDate.toTimeString().substring(0, 5);
        }

        // Parse end datetime
        if (eventData.end) {
            const endDate = new Date(eventData.end);
            document.getElementById('reqEnd').value = endDate.toTimeString().substring(0, 5);
        }

        document.getElementById('requestModal').classList.add('active');
    }

    function closeRequestModal() {
        document.getElementById('requestModal').classList.remove('active');
    }

    async function submitRequest(ev) {
        ev.preventDefault();
        const btn = document.getElementById('reqSubmitBtn');
        const type = document.getElementById('reqType').value;
        const date = document.getElementById('reqDate').value;

        const data = {
            type,
            event_id: type === 'modify' ? document.getElementById('reqEventId').value : null,
            title: document.getElementById('reqTitle').value,
            start: date + ' ' + document.getElementById('reqStart').value + ':00',
            end: date + ' ' + document.getElementById('reqEnd').value + ':00',
            location: document.getElementById('reqLocation').value,
            organizer: document.getElementById('reqOrganizer').value,
            description: document.getElementById('reqDesc').value,
            categories: document.getElementById('reqCategories').value,
            requester_name: document.getElementById('reqName').value,
            requester_email: document.getElementById('reqEmail').value,
            requester_note: document.getElementById('reqNote').value
        };

        btn.disabled = true;
        btn.textContent = 'กำลังส่ง...';

        try {
            const res = await fetch('api/request.php?action=submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                alert('ส่งคำขอสำเร็จ! Admin จะตรวจสอบต่อไป');
                closeRequestModal();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            alert('ไม่สามารถส่งได้');
        } finally {
            btn.disabled = false;
            btn.textContent = 'ส่งคำขอ';
        }
    }

    // ลบ filter และ reload หน้า
    function removeFilter(type, value) {
        const url = new URL(window.location.href);
        const params = url.searchParams;

        // ดึงค่าปัจจุบันของ filter type นั้น
        const currentValues = params.getAll(type + '[]');

        // ลบค่าที่ต้องการออก
        const newValues = currentValues.filter(v => v !== value);

        // ลบ parameter เดิมทั้งหมด
        params.delete(type + '[]');

        // เพิ่ม parameter ใหม่ (ยกเว้นค่าที่ลบ)
        newValues.forEach(v => params.append(type + '[]', v));

        // reload หน้าด้วย URL ใหม่
        window.location.href = url.toString();
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('requestModal').classList.contains('active')) {
            closeRequestModal();
        }
    });
    </script>
</body>
</html>
