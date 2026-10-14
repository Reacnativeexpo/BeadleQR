<?php
session_start();
require "../db.php";


// =========================================================
// ADMIN ACCESS CHECK
// =========================================================

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';


// =========================================================
// ATTENDANCE STATS BY STATUS - TODAY
// =========================================================

$statusQuery = $conn->query("
    SELECT
        status,
        COUNT(*) AS count
    FROM public.teacher_attendance
    WHERE attendance_date = CURRENT_DATE
    GROUP BY status
");

$statusData = [
    'Present' => 0,
    'Absent' => 0,
    'Late' => 0,
    'Excused' => 0,
    'Pending' => 0
];

while ($row = $statusQuery->fetch(PDO::FETCH_ASSOC)) {

    if (isset($statusData[$row['status']])) {
        $statusData[$row['status']] = (int)$row['count'];
    }
}

$totalToday = array_sum($statusData);


// =========================================================
// ATTENDANCE TREND - LAST 7 DAYS
// =========================================================

$trendQuery = $conn->query("
    SELECT
        attendance_date,
        status,
        COUNT(*) AS count
    FROM public.teacher_attendance
    WHERE attendance_date >= CURRENT_DATE - INTERVAL '6 days'
    GROUP BY attendance_date, status
    ORDER BY attendance_date ASC
");

$trendData = [];

while ($row = $trendQuery->fetch(PDO::FETCH_ASSOC)) {

    $date = $row['attendance_date'];

    if (!isset($trendData[$date])) {

        $trendData[$date] = [
            'Present' => 0,
            'Absent' => 0,
            'Late' => 0,
            'Excused' => 0,
            'Pending' => 0
        ];
    }

    if (isset($trendData[$date][$row['status']])) {
        $trendData[$date][$row['status']] = (int)$row['count'];
    }
}


// =========================================================
// FILL MISSING DATES
// =========================================================

$dates = [];

for ($i = 6; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}


$chartLabels = [];
$chartPresent = [];
$chartAbsent = [];
$chartLate = [];
$chartExcused = [];
$chartPending = [];


foreach ($dates as $date) {

    $chartLabels[] = date('M d', strtotime($date));

    $chartPresent[] =
        $trendData[$date]['Present'] ?? 0;

    $chartAbsent[] =
        $trendData[$date]['Absent'] ?? 0;

    $chartLate[] =
        $trendData[$date]['Late'] ?? 0;

    $chartExcused[] =
        $trendData[$date]['Excused'] ?? 0;

    $chartPending[] =
        $trendData[$date]['Pending'] ?? 0;
}


// =========================================================
// TEACHER LEADERS BY STATUS
// =========================================================


// ---------------------------------------------------------
// PRESENT LEADERS
// ---------------------------------------------------------

$presentLeaders = $conn->query("
    SELECT
        u.id,
        u.fullname,
        u.employee_id,
        COUNT(*) AS present_count
    FROM public.teacher_attendance a
    JOIN public.users u
        ON a.teacher_id = u.id
    WHERE
        a.status = 'Present'
        AND a.attendance_date = CURRENT_DATE
        AND u.role = 'teacher'
    GROUP BY
        u.id,
        u.fullname,
        u.employee_id
    ORDER BY present_count DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// ABSENT LEADERS
// ---------------------------------------------------------

$absentLeaders = $conn->query("
    SELECT
        u.id,
        u.fullname,
        u.employee_id,
        COUNT(*) AS absent_count
    FROM public.teacher_attendance a
    JOIN public.users u
        ON a.teacher_id = u.id
    WHERE
        a.status = 'Absent'
        AND a.attendance_date = CURRENT_DATE
        AND u.role = 'teacher'
    GROUP BY
        u.id,
        u.fullname,
        u.employee_id
    ORDER BY absent_count DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// LATE LEADERS
// ---------------------------------------------------------

$lateLeaders = $conn->query("
    SELECT
        u.id,
        u.fullname,
        u.employee_id,
        COUNT(*) AS late_count
    FROM public.teacher_attendance a
    JOIN public.users u
        ON a.teacher_id = u.id
    WHERE
        a.status = 'Late'
        AND a.attendance_date = CURRENT_DATE
        AND u.role = 'teacher'
    GROUP BY
        u.id,
        u.fullname,
        u.employee_id
    ORDER BY late_count DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// EXCUSED LEADERS
// ---------------------------------------------------------

$excusedLeaders = $conn->query("
    SELECT
        u.id,
        u.fullname,
        u.employee_id,
        COUNT(*) AS excused_count
    FROM public.teacher_attendance a
    JOIN public.users u
        ON a.teacher_id = u.id
    WHERE
        a.status = 'Excused'
        AND a.attendance_date = CURRENT_DATE
        AND u.role = 'teacher'
    GROUP BY
        u.id,
        u.fullname,
        u.employee_id
    ORDER BY excused_count DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// OVERALL COUNTS
// =========================================================

$teacherCount = $conn->query("
    SELECT COUNT(*)
    FROM public.users
    WHERE role = 'teacher'
")->fetchColumn();


$studentCount = $conn->query("
    SELECT COUNT(*)
    FROM public.users
    WHERE role = 'student'
")->fetchColumn();


$scheduleCount = $conn->query("
    SELECT COUNT(*)
    FROM public.schedules
")->fetchColumn();


$subjectCount = $conn->query("
    SELECT COUNT(*)
    FROM public.subjects
")->fetchColumn();


// =========================================================
// OPTIONAL: BEADER ASSIGNMENT COUNT
// =========================================================

$beaderAssignmentCount = $conn->query("
    SELECT COUNT(*)
    FROM public.beader_assignments
")->fetchColumn();


// =========================================================
// OPTIONAL: TODAY'S ACTIVE SESSIONS
// =========================================================

$activeSessionCount = $conn->query("
    SELECT COUNT(*)
    FROM public.attendance_sessions
    WHERE session_date = CURRENT_DATE
      AND status = 'active'
")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Smart Attendance</title>

    <!-- GOOGLE FONT -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- CHART.JS -->
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--body-bg);
            min-height: 100vh;
        }

        /* =========================
           SIDEBAR
        ========================= */
       
        /* =========================
           MAIN CONTENT
        ========================= */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        /* TOPBAR */
        .topbar {
            background: white;
            height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--body-bg);
            border: none;
            color: var(--text-muted);
            font-size: 16px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }

        .notification-btn:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
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
            gap: 12px;
            padding: 8px 15px;
            background: var(--body-bg);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-profile:hover {
            background: #e2e8f0;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info {
            line-height: 1.3;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .user-role {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: capitalize;
        }

        /* CONTENT AREA */
        .content-area {
            padding: 30px 35px;
        }

        /* =========================
           WELCOME BANNER
        ========================= */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 50%, #c2410c 100%);
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
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -30%;
            right: 10%;
            width: 250px;
            height: 250px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
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
        }

        .welcome-text {
            font-size: 14px;
            opacity: 0.85;
            max-width: 500px;
        }

        .welcome-icon {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 100px;
            opacity: 0.1;
            z-index: 0;
        }

        /* =========================
           STATS CARDS
        ========================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-card.teachers::before { background: var(--primary); }
        .stat-card.students::before { background: var(--info); }
        .stat-card.schedules::before { background: var(--success); }
        .stat-card.subjects::before { background: var(--purple); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-card.teachers .stat-icon { background: #fff7ed; color: var(--primary); }
        .stat-card.students .stat-icon { background: #eff6ff; color: var(--info); }
        .stat-card.schedules .stat-icon { background: #ecfdf5; color: var(--success); }
        .stat-card.subjects .stat-icon { background: #f5f3ff; color: var(--purple); }

        .stat-trend {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .stat-trend.up { background: var(--success-light); color: var(--success); }
        .stat-trend.down { background: var(--danger-light); color: var(--danger); }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* =========================
           CHART SECTION
        ========================= */
        .section-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title i {
            color: var(--primary);
        }

        .chart-legend {
            display: flex;
            gap: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
        }

        .chart-container {
            position: relative;
            height: 320px;
        }

        /* =========================
           TODAY STATUS PANEL
        ========================= */
        .status-panel {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }

        .status-panel-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
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
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .status-icon.present { background: var(--success-light); color: var(--success); }
        .status-icon.absent { background: var(--danger-light); color: var(--danger); }
        .status-icon.late { background: var(--warning-light); color: var(--warning); }
        .status-icon.leave { background: var(--info-light); color: var(--info); }

        .status-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .status-info p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .status-count {
            font-size: 22px;
            font-weight: 700;
        }

        .status-count.present { color: var(--success); }
        .status-count.absent { color: var(--danger); }
        .status-count.late { color: var(--warning); }
        .status-count.leave { color: var(--info); }

        .status-bar {
            width: 100%;
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }

        .status-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease;
        }

        .status-bar-fill.present { background: var(--success); }
        .status-bar-fill.absent { background: var(--danger); }
        .status-bar-fill.late { background: var(--warning); }
        .status-bar-fill.leave { background: var(--info); }

        /* =========================
           TEACHER LEADERS SECTION
        ========================= */
        .leaders-section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .leaders-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .leader-card {
            background: white;
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .leader-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
        }

        .leader-header {
            padding: 20px 20px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .leader-header.present { background: linear-gradient(135deg, var(--success), #059669); }
        .leader-header.absent { background: linear-gradient(135deg, var(--danger), #b91c1c); }
        .leader-header.late { background: linear-gradient(135deg, var(--warning), #d97706); }
        .leader-header.leave { background: linear-gradient(135deg, var(--info), #1d4ed8); }

        .leader-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .leader-header-info {
            color: white;
        }

        .leader-header-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .leader-header-info span {
            font-size: 11px;
            opacity: 0.9;
        }

        .leader-body {
            padding: 15px 20px;
        }

        .leader-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .leader-item:last-child {
            border-bottom: none;
        }

        .leader-rank {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }

        .leader-rank.gold { background: #fef3c7; color: #d97706; }
        .leader-rank.silver { background: #f1f5f9; color: #64748b; }
        .leader-rank.bronze { background: #fff7ed; color: #c2410c; }

        .leader-details {
            flex: 1;
            min-width: 0;
        }

        .leader-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .leader-id {
            font-size: 11px;
            color: var(--text-muted);
        }

        .leader-score {
            font-size: 14px;
            font-weight: 700;
        }

        .leader-score.present { color: var(--success); }
        .leader-score.absent { color: var(--danger); }
        .leader-score.late { color: var(--warning); }
        .leader-score.leave { color: var(--info); }

        .leader-empty {
            text-align: center;
            padding: 25px 10px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .leader-empty i {
            font-size: 28px;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        /* =========================
           QUICK ACTIONS
        ========================= */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .action-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 12px;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
            color: inherit;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            transition: all 0.3s;
        }

        .action-card:nth-child(1) .action-icon { background: #fff7ed; color: var(--primary); }
        .action-card:nth-child(2) .action-icon { background: #eff6ff; color: var(--info); }
        .action-card:nth-child(3) .action-icon { background: #ecfdf5; color: var(--success); }
        .action-card:nth-child(4) .action-icon { background: #f5f3ff; color: var(--purple); }

        .action-card:hover .action-icon {
            transform: scale(1.1);
        }

        .action-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .action-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* =========================
           RESPONSIVE
        ========================= */
        @media (max-width: 1200px) {
            .stats-grid,
            .leaders-grid,
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .section-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid,
            .leaders-grid,
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>
<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="page-title">Dashboard</div>
            <div class="breadcrumb">
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                <span>Dashboard</span>
            </div>
        </div>
        <div class="topbar-right">
            <button class="notification-btn">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-badge">3</span>
            </button>
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($admin_name); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENT AREA -->
    <div class="content-area">

        <!-- WELCOME BANNER -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <div class="welcome-greeting">Welcome back,</div>
                <div class="welcome-name"><?= htmlspecialchars($admin_name); ?>!</div>
                <div class="welcome-text">
                    Here's what's happening with your school's attendance today. 
                    Monitor teacher presence, track schedules, and manage everything from one place.
                </div>
            </div>
            <i class="fa-solid fa-shapes welcome-icon"></i>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card teachers">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <span class="stat-trend up">
                        <i class="fa-solid fa-arrow-up" style="font-size: 9px;"></i> Active
                    </span>
                </div>
                <div class="stat-value"><?= $teacherCount; ?></div>
                <div class="stat-label">Total Teachers</div>
            </div>

            <div class="stat-card students">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <span class="stat-trend up">
                        <i class="fa-solid fa-arrow-up" style="font-size: 9px;"></i> Active
                    </span>
                </div>
                <div class="stat-value"><?= $studentCount; ?></div>
                <div class="stat-label">Total Students</div>
            </div>

            <div class="stat-card schedules">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <span class="stat-trend up">
                        <i class="fa-solid fa-arrow-up" style="font-size: 9px;"></i> Today
                    </span>
                </div>
                <div class="stat-value"><?= $scheduleCount; ?></div>
                <div class="stat-label">Active Schedules</div>
            </div>

            <div class="stat-card subjects">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <span class="stat-trend up">
                        <i class="fa-solid fa-arrow-up" style="font-size: 9px;"></i> All
                    </span>
                </div>
                <div class="stat-value"><?= $subjectCount; ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>
        </div>

        <!-- CHART + TODAY STATUS -->
        <div class="section-grid">
            <!-- AREA CHART -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fa-solid fa-chart-area"></i>
                        Attendance Trend (Last 7 Days)
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #10b981;"></div>
                            Present
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #ef4444;"></div>
                            Absent
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #f59e0b;"></div>
                            Late
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #3b82f6;"></div>
                            Leave
                        </div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>

            <!-- TODAY'S STATUS -->
            <div class="status-panel">
                <div class="status-panel-title">
                    <i class="fa-solid fa-calendar-day"></i>
                    Today's Status
                </div>

                <div class="status-item">
                    <div class="status-left">
                        <div class="status-icon present">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div class="status-info">
                            <h4>Present</h4>
                            <p>On time arrivals</p>
                        </div>
                    </div>
                    <div class="status-count present"><?= $statusData['Present']; ?></div>
                </div>
                <div class="status-bar">
                    <div class="status-bar-fill present" style="width: <?= $totalToday > 0 ? ($statusData['Present'] / $totalToday * 100) : 0; ?>%"</div>
                </div>

                <div class="status-item">
                    <div class="status-left">
                        <div class="status-icon absent">
                            <i class="fa-solid fa-xmark"></i>
                        </div>
                        <div class="status-info">
                            <h4>Absent</h4>
                            <p>Did not show up</p>
                        </div>
                    </div>
                    <div class="status-count absent"><?= $statusData['Absent']; ?></div>
                </div>
                <div class="status-bar">
                    <div class="status-bar-fill absent" style="width: <?= $totalToday > 0 ? ($statusData['Absent'] / $totalToday * 100) : 0; ?>%"></div>
                </div>

                <div class="status-item">
                    <div class="status-left">
                        <div class="status-icon late">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="status-info">
                            <h4>Late</h4>
                            <p>Arrived after time</p>
                        </div>
                    </div>
                    <div class="status-count late"><?= $statusData['Late']; ?></div>
                </div>
                <div class="status-bar">
                    <div class="status-bar-fill late" style="width: <?= $totalToday > 0 ? ($statusData['Late'] / $totalToday * 100) : 0; ?>%"></div>
                </div>

                <div class="status-item">
                    <div class="status-left">
                        <div class="status-icon leave">
                            <i class="fa-solid fa-file-medical"></i>
                        </div>
                        <div class="status-info">
                            <h4>Leave</h4>
                            <p>Approved absence</p>
                        </div>
                    </div>
                    <div class="status-count leave"><?= $statusData['Leave']; ?></div>
                </div>
                <div class="status-bar">
                    <div class="status-bar-fill leave" style="width: <?= $totalToday > 0 ? ($statusData['Leave'] / $totalToday * 100) : 0; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- TEACHER LEADERS BY STATUS -->
        <div class="leaders-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-trophy"></i>
                    Teacher Leaders by Status
                </div>
            </div>

            <div class="leaders-grid">
                <!-- PRESENT LEADERS -->
                <div class="leader-card">
                    <div class="leader-header present">
                        <div class="leader-avatar">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div class="leader-header-info">
                            <h4>Present Leaders</h4>
                            <span>Most on-time today</span>
                        </div>
                    </div>
                    <div class="leader-body">
                        <?php if (empty($presentLeaders)): ?>
                            <div class="leader-empty">
                                <i class="fa-solid fa-inbox"></i>
                                <div>No present records today</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($presentLeaders as $index => $leader): ?>
                                <div class="leader-item">
                                    <div class="leader-rank <?= $index == 0 ? 'gold' : ($index == 1 ? 'silver' : 'bronze'); ?>">
                                        <?= $index + 1; ?>
                                    </div>
                                    <div class="leader-details">
                                        <div class="leader-name"><?= htmlspecialchars($leader['fullname']); ?></div>
                                        <div class="leader-id"><?= htmlspecialchars($leader['employee_id']); ?></div>
                                    </div>
                                    <div class="leader-score present"><?= $leader['present_count']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ABSENT LEADERS -->
                <div class="leader-card">
                    <div class="leader-header absent">
                        <div class="leader-avatar">
                            <i class="fa-solid fa-xmark"></i>
                        </div>
                        <div class="leader-header-info">
                            <h4>Absent Leaders</h4>
                            <span>Most absences today</span>
                        </div>
                    </div>
                    <div class="leader-body">
                        <?php if (empty($absentLeaders)): ?>
                            <div class="leader-empty">
                                <i class="fa-solid fa-inbox"></i>
                                <div>No absent records today</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($absentLeaders as $index => $leader): ?>
                                <div class="leader-item">
                                    <div class="leader-rank <?= $index == 0 ? 'gold' : ($index == 1 ? 'silver' : 'bronze'); ?>">
                                        <?= $index + 1; ?>
                                    </div>
                                    <div class="leader-details">
                                        <div class="leader-name"><?= htmlspecialchars($leader['fullname']); ?></div>
                                        <div class="leader-id"><?= htmlspecialchars($leader['employee_id']); ?></div>
                                    </div>
                                    <div class="leader-score absent"><?= $leader['absent_count']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- LATE LEADERS -->
                <div class="leader-card">
                    <div class="leader-header late">
                        <div class="leader-avatar">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="leader-header-info">
                            <h4>Late Leaders</h4>
                            <span>Most late arrivals today</span>
                        </div>
                    </div>
                    <div class="leader-body">
                        <?php if (empty($lateLeaders)): ?>
                            <div class="leader-empty">
                                <i class="fa-solid fa-inbox"></i>
                                <div>No late records today</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($lateLeaders as $index => $leader): ?>
                                <div class="leader-item">
                                    <div class="leader-rank <?= $index == 0 ? 'gold' : ($index == 1 ? 'silver' : 'bronze'); ?>">
                                        <?= $index + 1; ?>
                                    </div>
                                    <div class="leader-details">
                                        <div class="leader-name"><?= htmlspecialchars($leader['fullname']); ?></div>
                                        <div class="leader-id"><?= htmlspecialchars($leader['employee_id']); ?></div>
                                    </div>
                                    <div class="leader-score late"><?= $leader['late_count']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- LEAVE LEADERS -->
                <div class="leader-card">
                    <div class="leader-header leave">
                        <div class="leader-avatar">
                            <i class="fa-solid fa-file-medical"></i>
                        </div>
                        <div class="leader-header-info">
                            <h4>Leave Leaders</h4>
                            <span>Most on leave today</span>
                        </div>
                    </div>
                    <div class="leader-body">
                        <?php if (empty($leaveLeaders)): ?>
                            <div class="leader-empty">
                                <i class="fa-solid fa-inbox"></i>
                                <div>No leave records today</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($leaveLeaders as $index => $leader): ?>
                                <div class="leader-item">
                                    <div class="leader-rank <?= $index == 0 ? 'gold' : ($index == 1 ? 'silver' : 'bronze'); ?>">
                                        <?= $index + 1; ?>
                                    </div>
                                    <div class="leader-details">
                                        <div class="leader-name"><?= htmlspecialchars($leader['fullname']); ?></div>
                                        <div class="leader-id"><?= htmlspecialchars($leader['employee_id']); ?></div>
                                    </div>
                                    <div class="leader-score leave"><?= $leader['leave_count']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="section-header">
            <div class="section-title">
                <i class="fa-solid fa-bolt"></i>
                Quick Actions
            </div>
        </div>

        <div class="actions-grid">
            <a href="add_teacher.php" class="action-card">
                <div class="action-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div class="action-title">Add Teacher</div>
                <div class="action-desc">Register new teachers and manage their accounts</div>
            </a>

            <a href="attendance.php" class="action-card">
                <div class="action-icon">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div class="action-title">View Attendance</div>
                <div class="action-desc">Check daily attendance records and reports</div>
            </a>

            <a href="create_subject.php" class="action-card">
                <div class="action-icon">
                    <i class="fa-solid fa-book"></i>
                </div>
                <div class="action-title">Create Subject</div>
                <div class="action-desc">Add new subjects to the curriculum</div>
            </a>

            <a href="reports.php" class="action-card">
                <div class="action-icon">
                    <i class="fa-solid fa-chart-bar"></i>
                </div>
                <div class="action-title">Generate Reports</div>
                <div class="action-desc">Export attendance and performance reports</div>
            </a>
        </div>

    </div>
</div>

<script>
// =========================
// AREA CHART
// =========================
const ctx = document.getElementById('attendanceChart').getContext('2d');

// Gradient fills
const gradientPresent = ctx.createLinearGradient(0, 0, 0, 320);
gradientPresent.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
gradientPresent.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

const gradientAbsent = ctx.createLinearGradient(0, 0, 0, 320);
gradientAbsent.addColorStop(0, 'rgba(239, 68, 68, 0.4)');
gradientAbsent.addColorStop(1, 'rgba(239, 68, 68, 0.0)');

const gradientLate = ctx.createLinearGradient(0, 0, 0, 320);
gradientLate.addColorStop(0, 'rgba(245, 158, 11, 0.4)');
gradientLate.addColorStop(1, 'rgba(245, 158, 11, 0.0)');

const gradientLeave = ctx.createLinearGradient(0, 0, 0, 320);
gradientLeave.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
gradientLeave.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels); ?>,
        datasets: [
            {
                label: 'Present',
                data: <?= json_encode($chartPresent); ?>,
                borderColor: '#10b981',
                backgroundColor: gradientPresent,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            },
            {
                label: 'Absent',
                data: <?= json_encode($chartAbsent); ?>,
                borderColor: '#ef4444',
                backgroundColor: gradientAbsent,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ef4444',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            },
            {
                label: 'Late',
                data: <?= json_encode($chartLate); ?>,
                borderColor: '#f59e0b',
                backgroundColor: gradientLate,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            },
            {
                label: 'Leave',
                data: <?= json_encode($chartLeave); ?>,
                borderColor: '#3b82f6',
                backgroundColor: gradientLeave,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: '#1e293b',
                titleFont: { family: 'Poppins', size: 13 },
                bodyFont: { family: 'Poppins', size: 12 },
                padding: 12,
                cornerRadius: 10,
                displayColors: true,
                boxPadding: 4
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: { family: 'Poppins', size: 11 },
                    color: '#94a3b8'
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: '#f1f5f9',
                    drawBorder: false
                },
                ticks: {
                    font: { family: 'Poppins', size: 11 },
                    color: '#94a3b8',
                    padding: 10
                }
            }
        }
    }
});
</script>

</body>
</html>