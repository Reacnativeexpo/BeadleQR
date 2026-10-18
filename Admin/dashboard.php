<?php
session_start();
require "../db.php";

date_default_timezone_set('Asia/Manila');

// =========================================================
// ADMIN ACCESS CHECK
// =========================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// =========================================================
// ATTENDANCE STATS - TODAY
// =========================================================
$statusData = [
    'Present' => 0,
    'Absent'  => 0,
    'Late'    => 0,
    'Excused' => 0,
    'Pending' => 0
];

$statusQuery = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM public.teacher_attendance
    WHERE attendance_date = CURRENT_DATE
    GROUP BY status
");

while ($row = $statusQuery->fetch(PDO::FETCH_ASSOC)) {
    if (array_key_exists($row['status'], $statusData)) {
        $statusData[$row['status']] = (int) $row['total'];
    }
}

$totalToday = array_sum($statusData);

// =========================================================
// ATTENDANCE TREND - LAST 7 DAYS
// =========================================================
$trendQuery = $conn->query("
    SELECT attendance_date, status, COUNT(*) AS total
    FROM public.teacher_attendance
    WHERE attendance_date >= CURRENT_DATE - INTERVAL '6 days'
      AND attendance_date <= CURRENT_DATE
    GROUP BY attendance_date, status
    ORDER BY attendance_date ASC
");

$trendData = [];

while ($row = $trendQuery->fetch(PDO::FETCH_ASSOC)) {
    $date = $row['attendance_date'];

    if (!isset($trendData[$date])) {
        $trendData[$date] = [
            'Present' => 0,
            'Absent'  => 0,
            'Late'    => 0,
            'Excused' => 0,
            'Pending' => 0
        ];
    }

    if (isset($trendData[$date][$row['status']])) {
        $trendData[$date][$row['status']] = (int) $row['total'];
    }
}

$chartLabels  = [];
$chartPresent = [];
$chartAbsent  = [];
$chartLate    = [];
$chartExcused = [];
$chartPending = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));

    $chartLabels[]  = date('M d', strtotime($date));
    $chartPresent[] = $trendData[$date]['Present'] ?? 0;
    $chartAbsent[]  = $trendData[$date]['Absent'] ?? 0;
    $chartLate[]    = $trendData[$date]['Late'] ?? 0;
    $chartExcused[] = $trendData[$date]['Excused'] ?? 0;
    $chartPending[] = $trendData[$date]['Pending'] ?? 0;
}

// =========================================================
// OVERALL COUNTS
// =========================================================
$teacherCount = (int) $conn->query("
    SELECT COUNT(*) FROM public.users WHERE role = 'teacher'
")->fetchColumn();

$studentCount = (int) $conn->query("
    SELECT COUNT(*) FROM public.users WHERE role = 'student'
")->fetchColumn();

$scheduleCount = (int) $conn->query("
    SELECT COUNT(*) FROM public.schedules
")->fetchColumn();

$subjectCount = (int) $conn->query("
    SELECT COUNT(*) FROM public.subjects
")->fetchColumn();

$beaderAssignmentCount = (int) $conn->query("
    SELECT COUNT(*) FROM public.beader_assignments
")->fetchColumn();

$activeSessionCount = (int) $conn->query("
    SELECT COUNT(*)
    FROM public.attendance_sessions
    WHERE session_date = CURRENT_DATE
      AND status = 'active'
")->fetchColumn();

// =========================================================
// HELPER: ATTENDANCE PERCENTAGE
// =========================================================
function attendancePercentage($value, $total)
{
    if ($total <= 0) {
        return 0;
    }

    return min(100, round(($value / $total) * 100, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | BeadleQR</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --primary-light: #fb923c;

            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;

            --card-bg: #ffffff;
            --body-bg: #f1f5f9;

            --text-dark: #1e293b;
            --text-muted: #64748b;

            --success: #10b981;
            --success-light: #d1fae5;

            --danger: #ef4444;
            --danger-light: #fee2e2;

            --warning: #f59e0b;
            --warning-light: #fef3c7;

            --info: #3b82f6;
            --info-light: #dbeafe;

            --purple: #8b5cf6;
            --purple-light: #ede9fe;

            --radius: 18px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            background: var(--body-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            min-width: 0;
            transition: margin-left 0.3s ease;
        }

        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {
            min-height: 70px;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 12px 35px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 0;
        }

        .page-heading {
            min-width: 0;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 3px;
        }

        /* MOBILE MENU BUTTON */

        .mobile-menu {
            display: none;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            background: #f1f5f9;
            color: var(--text-dark);
            font-size: 18px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .mobile-menu:hover {
            background: #ffedd5;
            color: var(--primary-dark);
        }

        .mobile-menu:focus-visible {
            outline: 3px solid var(--primary-light);
            outline-offset: 2px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 15px;
            flex-shrink: 0;
        }

        .notification-btn {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--body-bg);
            border: none;
            color: var(--text-muted);
            font-size: 16px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .notification-btn:hover {
            background: #ffedd5;
            color: var(--primary-dark);
        }

        .notif-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 18px;
            height: 18px;
            background: var(--danger);
            color: white;
            font-size: 10px;
            font-weight: 700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            background: var(--body-bg);
            border-radius: 12px;
            min-width: 0;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            border-radius: 10px;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info {
            min-width: 0;
            line-height: 1.4;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            overflow-wrap: anywhere;
        }

        .user-role {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* =====================================================
           CONTENT AREA
        ===================================================== */

        .content-area {
            width: 100%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 30px 35px;
        }

        /* =====================================================
           WELCOME BANNER
        ===================================================== */

        .welcome-banner {
            background: linear-gradient(
                135deg,
                var(--primary) 0%,
                var(--primary-dark) 50%,
                #c2410c 100%
            );
            border-radius: 20px;
            padding: 35px 40px;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -30%;
            right: 10%;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 700px;
        }

        .welcome-greeting {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .welcome-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            overflow-wrap: anywhere;
        }

        .welcome-text {
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.8;
            max-width: 600px;
        }

        .welcome-icon {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 100px;
            opacity: 0.1;
            z-index: 0;
            pointer-events: none;
        }

        /* =====================================================
           STATISTICS CARDS
        ===================================================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            min-width: 0;
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-card.teachers::before {
            background: var(--primary);
        }

        .stat-card.students::before {
            background: var(--info);
        }

        .stat-card.schedules::before {
            background: var(--success);
        }

        .stat-card.subjects::before {
            background: var(--purple);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-card.teachers .stat-icon {
            background: #fff7ed;
            color: var(--primary);
        }

        .stat-card.students .stat-icon {
            background: #eff6ff;
            color: var(--info);
        }

        .stat-card.schedules .stat-icon {
            background: #ecfdf5;
            color: var(--success);
        }

        .stat-card.subjects .stat-icon {
            background: #f5f3ff;
            color: var(--purple);
        }

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 600;
            padding: 5px 9px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .stat-trend.up {
            background: var(--success-light);
            color: var(--success);
        }

        .stat-value {
            font-size: clamp(25px, 2.2vw, 32px);
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 5px;
            overflow-wrap: anywhere;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* =====================================================
           CHART AND STATUS LAYOUT
        ===================================================== */

        .section-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
            gap: 25px;
            margin-bottom: 30px;
            align-items: stretch;
        }

        .chart-card,
        .status-panel {
            min-width: 0;
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title i {
            color: var(--primary);
            flex-shrink: 0;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 14px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .legend-dot {
            width: 9px;
            height: 9px;
            flex-shrink: 0;
            border-radius: 3px;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 320px;
        }

        /* =====================================================
           STATUS PANEL
        ===================================================== */

        .status-panel-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-panel-title i {
            color: var(--primary);
        }

        .status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .status-item:last-of-type {
            border-bottom: none;
        }

        .status-left {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
            flex: 1;
        }

        .status-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }

        .status-icon.present {
            background: var(--success-light);
            color: var(--success);
        }

        .status-icon.absent {
            background: var(--danger-light);
            color: var(--danger);
        }

        .status-icon.late {
            background: var(--warning-light);
            color: var(--warning);
        }

        .status-icon.excused {
            background: var(--info-light);
            color: var(--info);
        }

        .status-icon.pending {
            background: var(--purple-light);
            color: var(--purple);
        }

        .status-info {
            min-width: 0;
            flex: 1;
        }

        .status-info h4 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .status-info p {
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .status-count {
            font-size: 21px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .status-count.present {
            color: var(--success);
        }

        .status-count.absent {
            color: var(--danger);
        }

        .status-count.late {
            color: var(--warning);
        }

        .status-count.excused {
            color: var(--info);
        }

        .status-count.pending {
            color: var(--purple);
        }

        .status-bar {
            width: 100%;
            height: 5px;
            background: #f1f5f9;
            border-radius: 5px;
            margin: 7px 0 5px;
            overflow: hidden;
        }

        .status-bar-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.8s ease;
        }

        .status-bar-fill.present {
            background: var(--success);
        }

        .status-bar-fill.absent {
            background: var(--danger);
        }

        .status-bar-fill.late {
            background: var(--warning);
        }

        .status-bar-fill.excused {
            background: var(--info);
        }

        .status-bar-fill.pending {
            background: var(--purple);
        }

        .status-note {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 12px;
            line-height: 1.7;
        }

        /* =====================================================
           QUICK MANAGEMENT
        ===================================================== */

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .action-card {
            min-width: 0;
            background: white;
            border-radius: 16px;
            padding: 25px 20px;
            box-shadow: var(--shadow);
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 12px;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
            color: inherit;
        }

        .action-card:focus-visible {
            outline: 3px solid var(--primary-light);
            outline-offset: 3px;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            transition: transform 0.3s ease;
        }

        .action-card:nth-child(1) .action-icon {
            background: #fff7ed;
            color: var(--primary);
        }

        .action-card:nth-child(2) .action-icon {
            background: #eff6ff;
            color: var(--info);
        }

        .action-card:nth-child(3) .action-icon {
            background: #ecfdf5;
            color: var(--success);
        }

        .action-card:nth-child(4) .action-icon {
            background: #f5f3ff;
            color: var(--purple);
        }

        .action-card:hover .action-icon {
            transform: scale(1.08);
        }

        .action-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .action-desc {
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.7;
        }

        /* =====================================================
           SIDEBAR OVERLAY
        ===================================================== */

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* =====================================================
           TABLET RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {
            .content-area {
                padding: 25px;
            }

            .topbar {
                padding: 12px 25px;
            }

            .stats-grid,
            .actions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .section-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .status-panel {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                column-gap: 25px;
                align-content: start;
            }

            .status-panel-title,
            .status-note {
                grid-column: 1 / -1;
            }

            .status-panel-title {
                margin-bottom: 10px;
            }
        }

        /* =====================================================
           MOBILE SIDEBAR - SAME BEHAVIOR AS ATTENDANCE PAGE
        ===================================================== */

        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 260px;
                max-width: 85vw;
                height: 100vh;
                height: 100dvh;
                overflow-y: auto;
                z-index: 1000;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                visibility: hidden;
                -webkit-overflow-scrolling: touch;
            }

            .sidebar.show {
                transform: translateX(0);
                visibility: visible;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                min-width: 0;
            }

            .mobile-menu {
                display: flex;
            }

            .topbar {
                position: sticky;
                top: 0;
                z-index: 100;
            }

            .topbar-left {
                flex: 1;
                min-width: 0;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        /* =====================================================
           MOBILE LAYOUT
        ===================================================== */

        @media (max-width: 768px) {
            .topbar {
                min-height: 70px;
                padding: 12px 16px;
                gap: 10px;
                align-items: center;
            }

            .topbar-left {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .page-title {
                font-size: 19px;
            }

            .breadcrumb {
                font-size: 10px;
                gap: 5px;
            }

            .topbar-right {
                gap: 9px;
            }

            .notification-btn {
                width: 38px;
                height: 38px;
            }

            .user-profile {
                padding: 0;
                background: transparent;
                gap: 7px;
            }

            .user-avatar {
                width: 36px;
                height: 36px;
            }

            .user-info {
                display: none;
            }

            .content-area {
                padding: 18px 15px 25px;
            }

            .welcome-banner {
                padding: 25px 22px;
                margin-bottom: 20px;
                border-radius: 17px;
            }

            .welcome-greeting {
                font-size: 12px;
            }

            .welcome-name {
                font-size: 23px;
            }

            .welcome-text {
                font-size: 12px;
                line-height: 1.8;
                max-width: 100%;
            }

            .welcome-icon {
                right: 12px;
                font-size: 65px;
                opacity: 0.08;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 17px 14px;
                border-radius: 14px;
            }

            .stat-header {
                margin-bottom: 12px;
                gap: 6px;
            }

            .stat-icon {
                width: 42px;
                height: 42px;
                font-size: 18px;
                border-radius: 12px;
            }

            .stat-trend {
                font-size: 9px;
                padding: 4px 7px;
            }

            .stat-value {
                font-size: 25px;
            }

            .stat-label {
                font-size: 11px;
                line-height: 1.6;
            }

            .section-grid {
                gap: 16px;
                margin-bottom: 25px;
            }

            .chart-card,
            .status-panel {
                padding: 18px 15px;
                border-radius: 16px;
            }

            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 13px;
                margin-bottom: 15px;
            }

            .chart-title {
                font-size: 14px;
                line-height: 1.6;
            }

            .chart-legend {
                gap: 9px 12px;
                width: 100%;
            }

            .legend-item {
                font-size: 10px;
            }

            .chart-container {
                height: 260px;
            }

            .status-panel {
                display: block;
            }

            .status-panel-title {
                font-size: 16px;
                margin-bottom: 12px;
            }

            .status-item {
                padding: 12px 0;
            }

            .status-icon {
                width: 38px;
                height: 38px;
                font-size: 15px;
                border-radius: 11px;
            }

            .status-info h4 {
                font-size: 12px;
            }

            .status-info p {
                font-size: 10px;
            }

            .status-count {
                font-size: 19px;
            }

            .status-note {
                font-size: 10px;
            }

            .section-header {
                margin-bottom: 15px;
            }

            .section-title {
                font-size: 17px;
                line-height: 1.5;
            }

            .actions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .action-card {
                padding: 20px 12px;
                border-radius: 14px;
                gap: 10px;
            }

            .action-icon {
                width: 48px;
                height: 48px;
                font-size: 21px;
                border-radius: 14px;
            }

            .action-title {
                font-size: 12px;
            }

            .action-desc {
                font-size: 10px;
                line-height: 1.6;
            }
        }

        /* =====================================================
           SMALL PHONES
        ===================================================== */

        @media (max-width: 380px) {
            .topbar {
                padding: 10px;
                gap: 7px;
            }

            .topbar-left {
                gap: 7px;
            }

            .mobile-menu {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            .page-title {
                font-size: 17px;
            }

            .breadcrumb {
                gap: 4px;
                font-size: 9px;
            }

            .topbar-right {
                gap: 6px;
            }

            .notification-btn {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .content-area {
                padding: 14px 10px 20px;
            }

            .welcome-banner {
                padding: 20px 16px;
            }

            .welcome-name {
                font-size: 21px;
            }

            .stats-grid {
                gap: 9px;
            }

            .stat-card {
                padding: 14px 10px;
            }

            .stat-trend {
                font-size: 8px;
                padding: 4px 5px;
            }

            .stat-value {
                font-size: 22px;
            }

            .chart-container {
                height: 230px;
            }

            .actions-grid {
                gap: 9px;
            }

            .action-card {
                padding: 17px 9px;
            }
        }

        /* =====================================================
           REDUCED MOTION
        ===================================================== */

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include 'sidebar.php'; ?>

<!-- MOBILE SIDEBAR OVERLAY -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">

    <!-- TOPBAR -->

    <header class="topbar">

        <div class="topbar-left">

            <!-- MOBILE MENU BUTTON -->

            <button
                type="button"
                class="mobile-menu"
                id="mobileMenu"
                aria-label="Open navigation menu"
                aria-controls="sidebar"
                aria-expanded="false"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="page-heading">

                <div class="page-title">Dashboard</div>

                <div class="breadcrumb">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                    <i class="fa-solid fa-chevron-right"
                       style="font-size: 9px;"></i>
                    <span>Dashboard</span>
                </div>

            </div>

        </div>

        <div class="topbar-right">

            <button
                type="button"
                class="notification-btn"
                aria-label="Notifications"
                title="Notifications"
            >
                <i class="fa-solid fa-bell"></i>
                <span class="notif-badge">3</span>
            </button>

            <div class="user-profile">

                <div class="user-avatar">
                    <?= htmlspecialchars(
                        strtoupper(substr($admin_name, 0, 1)),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

                <div class="user-info">

                    <div class="user-name">
                        <?= htmlspecialchars(
                            $admin_name,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </div>

                    <div class="user-role">Administrator</div>

                </div>

            </div>

        </div>

    </header>

    <!-- =====================================================
         CONTENT AREA
    ===================================================== -->

    <main class="content-area">

        <!-- WELCOME BANNER -->

        <section class="welcome-banner">

            <div class="welcome-content">

                <div class="welcome-greeting">
                    Welcome back, Admin
                </div>

                <div class="welcome-name">
                    <?= htmlspecialchars(
                        $admin_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

                <div class="welcome-text">
                    Here is the real-time overview of BeadleQR attendance
                    logs, system metrics, and teacher records for today.
                </div>

            </div>

            <i class="fa-solid fa-chart-line welcome-icon"></i>

        </section>

        <!-- =================================================
             STATISTICS CARDS
        ================================================= -->

        <section class="stats-grid">

            <div class="stat-card teachers">

                <div class="stat-header">

                    <div class="stat-icon">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>

                    <span class="stat-trend up">
                        <i class="fa-solid fa-arrow-up"></i>
                        Active
                    </span>

                </div>

                <div class="stat-value">
                    <?= number_format($teacherCount); ?>
                </div>

                <div class="stat-label">Total Teachers</div>

            </div>

            <div class="stat-card students">

                <div class="stat-header">

                    <div class="stat-icon">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>

                    <span class="stat-trend up">
                        <i class="fa-solid fa-arrow-up"></i>
                        Enrolled
                    </span>

                </div>

                <div class="stat-value">
                    <?= number_format($studentCount); ?>
                </div>

                <div class="stat-label">Total Students</div>

            </div>

            <div class="stat-card schedules">

                <div class="stat-header">

                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>

                    <span class="stat-trend up">
                        <i class="fa-solid fa-check"></i>
                        Current
                    </span>

                </div>

                <div class="stat-value">
                    <?= number_format($scheduleCount); ?>
                </div>

                <div class="stat-label">Total Schedules</div>

            </div>

            <div class="stat-card subjects">

                <div class="stat-header">

                    <div class="stat-icon">
                        <i class="fa-solid fa-book-open"></i>
                    </div>

                    <span class="stat-trend up">
                        <i class="fa-solid fa-book"></i>
                        Catalog
                    </span>

                </div>

                <div class="stat-value">
                    <?= number_format($subjectCount); ?>
                </div>

                <div class="stat-label">Total Subjects</div>

            </div>

        </section>

        <!-- =================================================
             CHART AND STATUS LAYOUT
        ================================================= -->

        <section class="section-grid">

            <!-- ATTENDANCE TREND CHART -->

            <div class="chart-card">

                <div class="chart-header">

                    <div class="chart-title">
                        <i class="fa-solid fa-chart-column"></i>
                        Teacher Attendance Trend (Last 7 Days)
                    </div>

                    <div class="chart-legend">

                        <div class="legend-item">
                            <span class="legend-dot"
                                  style="background: var(--success);"></span>
                            Present
                        </div>

                        <div class="legend-item">
                            <span class="legend-dot"
                                  style="background: var(--danger);"></span>
                            Absent
                        </div>

                        <div class="legend-item">
                            <span class="legend-dot"
                                  style="background: var(--warning);"></span>
                            Late
                        </div>

                        <div class="legend-item">
                            <span class="legend-dot"
                                  style="background: var(--info);"></span>
                            Excused
                        </div>

                        <div class="legend-item">
                            <span class="legend-dot"
                                  style="background: var(--purple);"></span>
                            Pending
                        </div>

                    </div>

                </div>

                <div class="chart-container">
                    <canvas id="attendanceTrendChart"></canvas>
                </div>

            </div>

            <!-- TODAY'S STATUS PANEL -->

            <div class="status-panel">

                <div class="status-panel-title">
                    <i class="fa-solid fa-chart-pie"></i>
                    Today's Breakdown
                </div>

                <?php foreach ($statusData as $status => $count):
                    $percent = attendancePercentage($count, $totalToday);
                    $cls = strtolower($status);
                ?>

                    <div class="status-item">

                        <div class="status-left">

                            <div class="status-icon <?= $cls; ?>">

                                <?php if ($status === 'Present'): ?>
                                    <i class="fa-solid fa-check"></i>

                                <?php elseif ($status === 'Absent'): ?>
                                    <i class="fa-solid fa-xmark"></i>

                                <?php elseif ($status === 'Late'): ?>
                                    <i class="fa-solid fa-clock"></i>

                                <?php elseif ($status === 'Excused'): ?>
                                    <i class="fa-solid fa-note-sticky"></i>

                                <?php else: ?>
                                    <i class="fa-solid fa-spinner"></i>
                                <?php endif; ?>

                            </div>

                            <div class="status-info">

                                <h4>
                                    <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </h4>

                                <div class="status-bar">
                                    <div
                                        class="status-bar-fill <?= $cls; ?>"
                                        style="width: <?= $percent; ?>%;"
                                    ></div>
                                </div>

                                <p><?= $percent; ?>% of total logs</p>

                            </div>

                        </div>

                        <div class="status-count <?= $cls; ?>">
                            <?= number_format($count); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

                <div class="status-note">
                    <i class="fa-solid fa-circle-info"></i>
                    Active Sessions Today:
                    <strong>
                        <?= number_format($activeSessionCount); ?>
                    </strong>
                </div>

            </div>

        </section>

        <!-- =================================================
             QUICK MANAGEMENT SECTION
        ================================================= -->

        <section>

            <div class="section-header">

                <div class="section-title">
                    <i class="fa-solid fa-bolt"></i>
                    Quick Management
                </div>

            </div>

            <div class="actions-grid">

                <a href="add_teacher.php" class="action-card">

                    <div class="action-icon">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>

                    <div class="action-title">
                        Manage Teachers
                    </div>

                    <div class="action-desc">
                        Add, edit, or view records and credentials for teachers.
                    </div>

                </a>

                <a href="students.php" class="action-card">

                    <div class="action-icon">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>

                    <div class="action-title">
                        Manage Students
                    </div>

                    <div class="action-desc">
                        Review student profiles, enrollments, and sections.
                    </div>

                </a>

                <a href="schedules.php" class="action-card">

                    <div class="action-icon">
                        <i class="fa-solid fa-calendar-alt"></i>
                    </div>

                    <div class="action-title">
                        Class Schedules
                    </div>

                    <div class="action-desc">
                        Set up and modify class timetables and periods.
                    </div>

                </a>

                <a href="reports.php" class="action-card">

                    <div class="action-icon">
                        <i class="fa-solid fa-file-excel"></i>
                    </div>

                    <div class="action-title">
                        Attendance Reports
                    </div>

                    <div class="action-desc">
                        Export daily, weekly, or monthly attendance logs.
                    </div>

                </a>

            </div>

        </section>

    </main>

</div>

<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    // =====================================================
    // MOBILE SIDEBAR
    // =====================================================

    const mobileMenu = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar || !sidebarOverlay || !mobileMenu) {
            return;
        }

        sidebar.classList.add('show');
        sidebarOverlay.classList.add('show');
        document.body.classList.add('sidebar-open');

        mobileMenu.setAttribute('aria-expanded', 'true');
        mobileMenu.setAttribute('aria-label', 'Close navigation menu');

        const menuIcon = mobileMenu.querySelector('i');

        if (menuIcon) {
            menuIcon.classList.remove('fa-bars');
            menuIcon.classList.add('fa-xmark');
        }
    }

    function closeSidebar() {
        if (!sidebar || !sidebarOverlay || !mobileMenu) {
            return;
        }

        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');

        mobileMenu.setAttribute('aria-expanded', 'false');
        mobileMenu.setAttribute('aria-label', 'Open navigation menu');

        const menuIcon = mobileMenu.querySelector('i');

        if (menuIcon) {
            menuIcon.classList.remove('fa-xmark');
            menuIcon.classList.add('fa-bars');
        }
    }

    if (mobileMenu && sidebar && sidebarOverlay) {

        mobileMenu.addEventListener('click', function () {

            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }

        });

        sidebarOverlay.addEventListener('click', closeSidebar);

        // Close the sidebar when navigating through a sidebar link.
        sidebar.querySelectorAll('a').forEach(function (link) {

            link.addEventListener('click', function () {
                closeSidebar();
            });

        });

        // Close the sidebar using the Escape key.
        document.addEventListener('keydown', function (event) {

            if (event.key === 'Escape') {
                closeSidebar();
            }

        });

        // Restore the desktop sidebar layout when the viewport expands.
        window.addEventListener('resize', function () {

            if (window.innerWidth > 992) {
                closeSidebar();
            }

        });
    }

    // =====================================================
    // CHART.JS - ATTENDANCE TREND
    // =====================================================

    const chartCanvas = document.getElementById('attendanceTrendChart');

    if (chartCanvas && typeof Chart !== 'undefined') {

        const chartContext = chartCanvas.getContext('2d');

        new Chart(chartContext, {
            type: 'bar',

            data: {
                labels: <?= json_encode(
                    $chartLabels,
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                ); ?>,

                datasets: [
                    {
                        label: 'Present',
                        data: <?= json_encode($chartPresent); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    },
                    {
                        label: 'Absent',
                        data: <?= json_encode($chartAbsent); ?>,
                        backgroundColor: '#ef4444',
                        borderRadius: 4
                    },
                    {
                        label: 'Late',
                        data: <?= json_encode($chartLate); ?>,
                        backgroundColor: '#f59e0b',
                        borderRadius: 4
                    },
                    {
                        label: 'Excused',
                        data: <?= json_encode($chartExcused); ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4
                    },
                    {
                        label: 'Pending',
                        data: <?= json_encode($chartPending); ?>,
                        backgroundColor: '#8b5cf6',
                        borderRadius: 4
                    }
                ]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: 'index'
                },

                plugins: {
                    legend: {
                        display: false
                    }
                },

                scales: {
                    x: {
                        grid: {
                            display: false
                        },

                        ticks: {
                            maxRotation: 0,
                            autoSkip: true
                        }
                    },

                    y: {
                        beginAtZero: true,

                        grid: {
                            color: '#f1f5f9'
                        },

                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

});
</script>

</body>
</html>
