<?php
session_start();
require "../db.php";

// =========================================================
// ADMIN ACCESS
// =========================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// =========================================================
// FILTERS
// =========================================================
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$date = trim($_GET['date'] ?? date('Y-m-d'));

// =========================================================
// SUMMARY FOR SELECTED DATE
// =========================================================
$summarySql = "
    SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE ta.status = 'Present') AS present,
        COUNT(*) FILTER (WHERE ta.status = 'Late') AS late,
        COUNT(*) FILTER (WHERE ta.status = 'Absent') AS absent,
        COUNT(*) FILTER (WHERE ta.status = 'Excused') AS excused
    FROM teacher_attendance ta
    INNER JOIN users u
        ON u.id = ta.teacher_id
    WHERE ta.attendance_date = :attendance_date
";

$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->execute([
    ':attendance_date' => $date
]);

$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$total = (int)($summary['total'] ?? 0);
$present = (int)($summary['present'] ?? 0);
$late = (int)($summary['late'] ?? 0);
$absent = (int)($summary['absent'] ?? 0);
$excused = (int)($summary['excused'] ?? 0);

// =========================================================
// ATTENDANCE QUERY
// =========================================================
$sql = "
    SELECT
        ta.id,
        ta.teacher_id,
        ta.schedule_id,
        ta.session_id,
        ta.attendance_date,
        ta.time_in,
        ta.time_out,
        ta.status,
        ta.scan_source,
        ta.validated_at,
        ta.remarks,

        u.employee_id,
        u.fullname AS teacher_name,

        s.subject_code,
        s.subject_name,

        sch.room,
        sch.day,
        sch.start_time,
        sch.end_time,

        yl.year_name,
        sec.section_name

    FROM teacher_attendance ta

    INNER JOIN users u
        ON u.id = ta.teacher_id

    LEFT JOIN schedules sch
        ON sch.id = ta.schedule_id

    LEFT JOIN subjects s
        ON s.id = sch.subject_id

    LEFT JOIN year_levels yl
        ON yl.id = sch.year_level_id

    LEFT JOIN sections sec
        ON sec.id = sch.section_id

    WHERE ta.attendance_date = :attendance_date
";

$params = [
    ':attendance_date' => $date
];

// =========================================================
// SEARCH
// =========================================================
if ($search !== '') {
    $sql .= "
        AND (
            u.fullname ILIKE :search
            OR u.employee_id ILIKE :search
            OR s.subject_code ILIKE :search
            OR s.subject_name ILIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}

// =========================================================
// STATUS FILTER
// =========================================================
if ($status !== '') {
    $sql .= " AND ta.status = :status ";
    $params[':status'] = $status;
}

// =========================================================
// ORDER
// =========================================================
$sql .= "
    ORDER BY
        ta.time_in ASC NULLS LAST,
        u.fullname ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// FORMAT TIME
// =========================================================
function formatTime($time)
{
    if (!$time) {
        return '--';
    }

    return date('h:i A', strtotime($time));
}

// =========================================================
// FORMAT DURATION
// =========================================================
function calculateDuration($timeIn, $timeOut)
{
    if (!$timeIn || !$timeOut) {
        return '--';
    }

    $start = strtotime($timeIn);
    $end = strtotime($timeOut);

    if ($end <= $start) {
        return '--';
    }

    $minutes = floor(($end - $start) / 60);

    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;

    if ($hours > 0) {
        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    return $remainingMinutes . 'm';
}

// =========================================================
// STATUS BADGE
// =========================================================
function statusBadge($status)
{
    switch (strtolower($status)) {

        case 'present':
            return '<span class="status-badge present">
                        <i class="fa-solid fa-circle-check"></i>
                        Present
                    </span>';

        case 'late':
            return '<span class="status-badge late">
                        <i class="fa-solid fa-clock"></i>
                        Late
                    </span>';

        case 'absent':
            return '<span class="status-badge absent">
                        <i class="fa-solid fa-circle-xmark"></i>
                        Absent
                    </span>';

        case 'excused':
            return '<span class="status-badge excused">
                        <i class="fa-solid fa-file-circle-check"></i>
                        Excused
                    </span>';

        case 'pending':
            return '<span class="status-badge pending">
                        <i class="fa-solid fa-hourglass-half"></i>
                        Pending
                    </span>';

        default:
            return '<span class="status-badge">
                        ' . htmlspecialchars($status ?: 'Unknown') . '
                    </span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Teacher Attendance | BeadleQR</title>

    <!-- GOOGLE FONT -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">

    <!-- FONT AWESOME -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        /* =====================================================
           VARIABLES
        ===================================================== */

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
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --purple: #8b5cf6;
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
            color: var(--text-dark);
        }

       
        /* =====================================================
           MAIN
        ===================================================== */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        /* =====================================================
           TOPBAR
        ===================================================== */

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

        .mobile-menu {
            display: none;
            border: none;
            background: #f1f5f9;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            color: var(--text-dark);
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 12px;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .admin-info strong {
            display: block;
            font-size: 13px;
        }

        .admin-info span {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .content-area {
            padding: 30px 35px;
        }

        .welcome-banner {
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );

            border-radius: 16px;
            padding: 25px 30px;
            color: white;
            margin-bottom: 25px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow:
                0 8px 25px rgba(249, 115, 22, 0.2);
        }

        .welcome-banner h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }

        .welcome-banner p {
            font-size: 13px;
            opacity: 0.9;
        }

        .welcome-icon {
            font-size: 55px;
            opacity: 0.2;
        }

        /* =====================================================
           SUMMARY CARDS
        ===================================================== */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .summary-icon.total {
            background: rgba(59,130,246,.1);
            color: var(--info);
        }

        .summary-icon.present {
            background: rgba(16,185,129,.1);
            color: var(--success);
        }

        .summary-icon.late {
            background: rgba(245,158,11,.1);
            color: var(--warning);
        }

        .summary-icon.absent {
            background: rgba(239,68,68,.1);
            color: var(--danger);
        }

        .summary-content span {
            color: var(--text-muted);
            font-size: 12px;
        }

        .summary-content h3 {
            font-size: 24px;
            margin-top: 2px;
        }

        /* =====================================================
           ATTENDANCE CARD
        ===================================================== */

        .attendance-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
        }

        .card-title h3 {
            font-size: 18px;
            margin-bottom: 3px;
        }

        .card-title p {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* =====================================================
           FILTERS
        ===================================================== */

        .filters {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-input,
        .filter-select {
            height: 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 12px;
            color: var(--text-dark);
            background: white;
            outline: none;
        }

        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(249,115,22,.1);
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 13px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .search-box input {
            padding-left: 35px;
            width: 220px;
        }

        .filter-btn {
            height: 40px;
            border: none;
            border-radius: 8px;
            padding: 0 16px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
        }

        .clear-btn {
            height: 40px;
            display: flex;
            align-items: center;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 12px;
            background: white;
        }

        .clear-btn:hover {
            background: #f8fafc;
            color: var(--text-dark);
        }

        /* =====================================================
           TABLE
        ===================================================== */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        thead th {
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 13px 14px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        tbody td {
            padding: 15px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        .teacher-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .teacher-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(249,115,22,.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
        }

        .teacher-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .employee-id {
            color: var(--text-muted);
            font-size: 10px;
            margin-top: 2px;
        }

        .subject-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .subject-code {
            display: inline-block;
            margin-top: 3px;
            font-size: 10px;
            color: var(--primary-dark);
            background: rgba(249,115,22,.1);
            padding: 3px 7px;
            border-radius: 5px;
            font-weight: 600;
        }

        .schedule-time {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .time-in {
            color: var(--success);
            font-weight: 700;
        }

        .time-out {
            color: var(--danger);
            font-weight: 700;
        }

        .no-time {
            color: #94a3b8;
        }

        /* =====================================================
           STATUS
        ===================================================== */

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .status-badge.present {
            background: rgba(16,185,129,.1);
            color: #059669;
        }

        .status-badge.late {
            background: rgba(245,158,11,.1);
            color: #d97706;
        }

        .status-badge.absent {
            background: rgba(239,68,68,.1);
            color: #dc2626;
        }

        .status-badge.excused {
            background: rgba(139,92,246,.1);
            color: #7c3aed;
        }

        .status-badge.pending {
            background: rgba(59,130,246,.1);
            color: #2563eb;
        }

        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 45px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }

        .empty-state h3 {
            color: var(--text-dark);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .empty-state p {
            font-size: 12px;
        }

        /* =====================================================
           OVERLAY
        ===================================================== */

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.5);
            z-index: 999;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .card-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .filters {
                width: 100%;
            }

        }

        @media (max-width: 992px) {

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu {
                display: block;
            }

            .sidebar-overlay.show {
                display: block;
            }

        }

        @media (max-width: 768px) {

            .topbar {
                padding: 0 20px;
            }

            .content-area {
                padding: 20px;
            }

            .admin-info {
                display: none;
            }

            .welcome-banner {
                padding: 20px;
            }

            .welcome-banner h2 {
                font-size: 18px;
            }

            .welcome-icon {
                font-size: 40px;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .summary-card {
                padding: 15px;
            }

            .summary-content h3 {
                font-size: 20px;
            }

            .filters {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
            }

            .search-box {
                grid-column: span 2;
            }

            .search-box input {
                width: 100%;
            }

            .filter-input,
            .filter-select,
            .filter-btn,
            .clear-btn {
                width: 100%;
            }

        }

        @media (max-width: 480px) {

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .search-box {
                grid-column: span 1;
            }

            .welcome-icon {
                display: none;
            }

        }

    </style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include 'sidebar.php'; ?>


<!-- OVERLAY -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">

    <!-- =====================================================
         TOPBAR
    ===================================================== -->

    <div class="topbar">

        <div class="topbar-left">

            <button class="mobile-menu" id="mobileMenu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div>
                <div class="page-title">
                    Teacher Attendance
                </div>

                <div class="page-subtitle">
                    Monitor teacher time-in and time-out records
                </div>
            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div class="admin-info">
                <strong>Administrator</strong>
                <span>Admin Panel</span>
            </div>

        </div>

    </div>


    <!-- =====================================================
         CONTENT AREA
    ===================================================== -->

    <div class="content-area">

        <!-- WELCOME -->

        <div class="welcome-banner">

            <div>

                <h2>
                    Teacher Attendance Monitoring
                </h2>

                <p>
                    View teacher attendance, subjects, time-in and time-out records.
                </p>

            </div>

            <div class="welcome-icon">
                <i class="fa-solid fa-user-clock"></i>
            </div>

        </div>


        <!-- =================================================
             SUMMARY
        ================================================= -->

        <div class="summary-grid">

            <!-- TOTAL -->

            <div class="summary-card">

                <div class="summary-icon total">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div class="summary-content">

                    <span>Total Records</span>

                    <h3>
                        <?= $total ?>
                    </h3>

                </div>

            </div>


            <!-- PRESENT -->

            <div class="summary-card">

                <div class="summary-icon present">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div class="summary-content">

                    <span>Present</span>

                    <h3>
                        <?= $present ?>
                    </h3>

                </div>

            </div>


            <!-- LATE -->

            <div class="summary-card">

                <div class="summary-icon late">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div class="summary-content">

                    <span>Late</span>

                    <h3>
                        <?= $late ?>
                    </h3>

                </div>

            </div>


            <!-- ABSENT -->

            <div class="summary-card">

                <div class="summary-icon absent">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>

                <div class="summary-content">

                    <span>Absent</span>

                    <h3>
                        <?= $absent ?>
                    </h3>

                </div>

            </div>

        </div>


        <!-- =================================================
             ATTENDANCE TABLE
        ================================================= -->

        <div class="attendance-card">

            <div class="card-header">

                <div class="card-title">

                    <h3>
                        <i class="fa-solid fa-clipboard-user"
                           style="color: var(--primary); margin-right: 7px;">
                        </i>

                        Attendance Records
                    </h3>

                    <p>
                        Teacher attendance for
                        <?= date('F d, Y', strtotime($date)) ?>
                    </p>

                </div>


                <!-- FILTER -->

                <form method="GET" class="filters">

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            class="filter-input"
                            placeholder="Search teacher or subject..."
                            value="<?= htmlspecialchars($search) ?>"
                        >

                    </div>


                    <input
                        type="date"
                        name="date"
                        class="filter-input"
                        value="<?= htmlspecialchars($date) ?>"
                    >


                    <select name="status"
                            class="filter-select">

                        <option value="">
                            All Status
                        </option>

                        <option value="Present"
                            <?= $status === 'Present' ? 'selected' : '' ?>>
                            Present
                        </option>

                        <option value="Late"
                            <?= $status === 'Late' ? 'selected' : '' ?>>
                            Late
                        </option>

                        <option value="Absent"
                            <?= $status === 'Absent' ? 'selected' : '' ?>>
                            Absent
                        </option>

                        <option value="Excused"
                            <?= $status === 'Excused' ? 'selected' : '' ?>>
                            Excused
                        </option>

                        <option value="Pending"
                            <?= $status === 'Pending' ? 'selected' : '' ?>>
                            Pending
                        </option>

                    </select>


                    <button type="submit"
                            class="filter-btn">

                        <i class="fa-solid fa-filter"></i>
                        Filter

                    </button>


                    <a href="attendance.php"
                       class="clear-btn">

                        <i class="fa-solid fa-rotate-left"
                           style="margin-right: 5px;">
                        </i>

                        Reset

                    </a>

                </form>

            </div>


            <!-- =================================================
                 TABLE
            ================================================= -->

            <div class="table-wrapper">

                <?php if (count($attendance) > 0): ?>

                    <table>

                        <thead>

                            <tr>

                                <th>Teacher</th>

                                <th>Subject</th>

                                <th>Class</th>

                                <th>Schedule</th>

                                <th>Time In</th>

                                <th>Time Out</th>

                                <th>Duration</th>

                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($attendance as $row): ?>

                            <tr>

                                <!-- TEACHER -->

                                <td>

                                    <div class="teacher-cell">

                                        <div class="teacher-avatar">

                                            <?= strtoupper(
                                                substr(
                                                    $row['teacher_name'] ?? 'T',
                                                    0,
                                                    1
                                                )
                                            ) ?>

                                        </div>

                                        <div>

                                            <div class="teacher-name">

                                                <?= htmlspecialchars(
                                                    $row['teacher_name'] ?? 'Unknown Teacher'
                                                ) ?>

                                            </div>

                                            <div class="employee-id">

                                                Employee ID:
                                                <?= htmlspecialchars(
                                                    $row['employee_id'] ?? '--'
                                                ) ?>

                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- SUBJECT -->

                                <td>

                                    <?php if (!empty($row['subject_name'])): ?>

                                        <div class="subject-name">

                                            <?= htmlspecialchars(
                                                $row['subject_name']
                                            ) ?>

                                        </div>

                                        <span class="subject-code">

                                            <?= htmlspecialchars(
                                                $row['subject_code'] ?? '--'
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span style="color:#94a3b8;">
                                            No Subject
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- CLASS -->

                                <td>

                                    <?php if (!empty($row['year_name'])): ?>

                                        <div style="font-weight:600;">

                                            <?= htmlspecialchars(
                                                $row['year_name']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php if (!empty($row['section_name'])): ?>

                                        <div style="
                                            font-size:11px;
                                            color:var(--text-muted);
                                            margin-top:2px;
                                        ">

                                            Section:
                                            <?= htmlspecialchars(
                                                $row['section_name']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php if (!empty($row['room'])): ?>

                                        <div style="
                                            font-size:10px;
                                            color:var(--text-muted);
                                            margin-top:2px;
                                        ">

                                            <i class="fa-solid fa-door-open"></i>

                                            <?= htmlspecialchars(
                                                $row['room']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- SCHEDULE -->

                                <td>

                                    <?php if (!empty($row['start_time'])): ?>

                                        <div style="
                                            font-weight:600;
                                            font-size:12px;
                                        ">

                                            <?= formatTime(
                                                $row['start_time']
                                            ) ?>

                                            -

                                            <?= formatTime(
                                                $row['end_time']
                                            ) ?>

                                        </div>

                                        <?php if (!empty($row['day'])): ?>

                                            <div class="schedule-time">

                                                <?= htmlspecialchars(
                                                    $row['day']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <span class="no-time">
                                            No Schedule
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- TIME IN -->

                                <td>

                                    <?php if (!empty($row['time_in'])): ?>

                                        <span class="time-in">

                                            <i class="fa-solid fa-right-to-bracket"></i>

                                            <?= formatTime(
                                                $row['time_in']
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="no-time">
                                            --
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- TIME OUT -->

                                <td>

                                    <?php if (!empty($row['time_out'])): ?>

                                        <span class="time-out">

                                            <i class="fa-solid fa-right-from-bracket"></i>

                                            <?= formatTime(
                                                $row['time_out']
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="no-time">
                                            Still In
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- DURATION -->

                                <td>

                                    <span style="
                                        font-weight:600;
                                        color:var(--text-muted);
                                    ">

                                        <i class="fa-solid fa-hourglass-half"
                                           style="margin-right:4px;">
                                        </i>

                                        <?= calculateDuration(
                                            $row['time_in'],
                                            $row['time_out']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?= statusBadge(
                                        $row['status'] ?? ''
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <!-- EMPTY -->

                    <div class="empty-state">

                        <i class="fa-solid fa-calendar-xmark"></i>

                        <h3>
                            No Attendance Records
                        </h3>

                        <p>
                            No teacher attendance records were found
                            for the selected date and filters.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

    const mobileMenu =
        document.getElementById('mobileMenu');

    const sidebar =
        document.getElementById('sidebar');

    const sidebarOverlay =
        document.getElementById('sidebarOverlay');


    mobileMenu.addEventListener('click', function () {

        sidebar.classList.toggle('show');

        sidebarOverlay.classList.toggle('show');

    });


    sidebarOverlay.addEventListener('click', function () {

        sidebar.classList.remove('show');

        sidebarOverlay.classList.remove('show');

    });

</script>

</body>
</html>