<?php
session_start();

require "../db.php";

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

/* =========================================================
   ASSIGN EXISTING BEADER
========================================================= */
$message = '';

if (isset($_POST['assign_beader'])) {
    $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
    $student_id  = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    if (!$schedule_id || !$student_id) {
        $message = "<div class='alert-glass error'>
            <i class='fa-solid fa-triangle-exclamation'></i>
            Please select a valid schedule and student.
        </div>";
    } else {
        try {
            // Verify that the schedule belongs to the logged-in teacher.
            $verifySchedule = $conn->prepare("
                SELECT id
                FROM schedules
                WHERE id = ? AND teacher_id = ?
            ");
            $verifySchedule->execute([$schedule_id, $teacher_id]);

            // Verify that the selected account is a student.
            $verifyStudent = $conn->prepare("
                SELECT id
                FROM users
                WHERE id = ? AND role = 'student'
            ");
            $verifyStudent->execute([$student_id]);

            if (!$verifySchedule->fetch(PDO::FETCH_ASSOC)) {
                $message = "<div class='alert-glass error'>
                    <i class='fa-solid fa-triangle-exclamation'></i>
                    Invalid schedule or permission denied.
                </div>";
            } elseif (!$verifyStudent->fetch(PDO::FETCH_ASSOC)) {
                $message = "<div class='alert-glass error'>
                    <i class='fa-solid fa-triangle-exclamation'></i>
                    Please select a valid student account.
                </div>";
            } else {
                $check = $conn->prepare("
                    SELECT id
                    FROM schedule_monitors
                    WHERE schedule_id = ?
                    LIMIT 1
                ");
                $check->execute([$schedule_id]);
                $existingMonitor = $check->fetch(PDO::FETCH_ASSOC);

                if ($existingMonitor) {
                    $update = $conn->prepare("
                        UPDATE schedule_monitors
                        SET student_id = ?
                        WHERE schedule_id = ?
                    ");
                    $update->execute([$student_id, $schedule_id]);
                } else {
                    $insert = $conn->prepare("
                        INSERT INTO schedule_monitors (schedule_id, student_id)
                        VALUES (?, ?)
                    ");
                    $insert->execute([$schedule_id, $student_id]);
                }

                $message = "<div class='alert-glass success'>
                    <i class='fa-solid fa-circle-check'></i>
                    Beader assigned successfully.
                </div>";
            }
        } catch (PDOException $e) {
            error_log('Assign Beader error: ' . $e->getMessage());
            $message = "<div class='alert-glass error'>
                <i class='fa-solid fa-triangle-exclamation'></i>
                Unable to assign the Beader. Please try again.
            </div>";
        }
    }
}

/* =========================================================
   TOTAL SCHEDULES
========================================================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM schedules
    WHERE teacher_id = ?
");
$stmt->execute([$teacher_id]);
$totalSchedules = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

/* =========================================================
   TODAY
========================================================= */
$today = date('l');

/* =========================================================
   TODAY'S CLASSES
========================================================= */
$stmt = $conn->prepare("
    SELECT
        s.*,
        sub.subject_name,
        sub.subject_code,
        y.year_name,
        sec.section_name
    FROM schedules s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN year_levels y ON s.year_level_id = y.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.teacher_id = ?
      AND s.day = ?
    ORDER BY s.start_time ASC
");
$stmt->execute([$teacher_id, $today]);
$todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   STUDENT LIST
========================================================= */
$students = $conn->query("
    SELECT id, fullname, student_no
    FROM users
    WHERE role = 'student'
    ORDER BY fullname ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   ATTENDANCE PERFORMANCE GRAPHS
   MySQL / MariaDB compatible
========================================================= */
$chartToday = new DateTimeImmutable('today');

$dailyPerformance = [];
$weeklyPerformance = [];
$monthlyPerformance = [];

for ($i = 6; $i >= 0; $i--) {
    $key = $chartToday->modify("-{$i} days")->format('Y-m-d');
    $dailyPerformance[$key] = [
        'present' => 0,
        'late' => 0,
        'absent' => 0
    ];
}

for ($i = 7; $i >= 0; $i--) {
    $key = $chartToday->modify("-{$i} weeks")
        ->modify('monday this week')
        ->format('Y-m-d');

    $weeklyPerformance[$key] = [
        'present' => 0,
        'late' => 0,
        'absent' => 0
    ];
}

for ($i = 5; $i >= 0; $i--) {
    $key = $chartToday->modify('first day of this month')
        ->modify("-{$i} months")
        ->format('Y-m');

    $monthlyPerformance[$key] = [
        'present' => 0,
        'late' => 0,
        'absent' => 0
    ];
}

$performanceError = null;

try {
    $attendanceStart = $chartToday
        ->modify('-6 months')
        ->modify('first day of this month')
        ->format('Y-m-d');

    $attendanceEnd = $chartToday
        ->modify('+1 day')
        ->format('Y-m-d');

    $performanceStmt = $conn->prepare("
        SELECT DATE(attendance_date) AS attendance_day, status
        FROM teacher_attendance
        WHERE teacher_id = :teacher_id
          AND attendance_date >= :start_date
          AND attendance_date < :end_date
        ORDER BY attendance_date ASC
    ");

    $performanceStmt->execute([
        ':teacher_id' => $teacher_id,
        ':start_date' => $attendanceStart,
        ':end_date' => $attendanceEnd
    ]);

    foreach ($performanceStmt->fetchAll(PDO::FETCH_ASSOC) as $attendanceRow) {
        if (empty($attendanceRow['attendance_day'])) {
            continue;
        }

        $attendanceDate = new DateTimeImmutable(
            $attendanceRow['attendance_day']
        );

        $dayKey = $attendanceDate->format('Y-m-d');

        $weekKey = $attendanceDate
            ->modify('monday this week')
            ->format('Y-m-d');

        $monthKey = $attendanceDate->format('Y-m');

        $status = strtolower(trim((string) $attendanceRow['status']));

        if (!in_array($status, ['present', 'late', 'absent'], true)) {
            continue;
        }

        if (isset($dailyPerformance[$dayKey])) {
            $dailyPerformance[$dayKey][$status]++;
        }

        if (isset($weeklyPerformance[$weekKey])) {
            $weeklyPerformance[$weekKey][$status]++;
        }

        if (isset($monthlyPerformance[$monthKey])) {
            $monthlyPerformance[$monthKey][$status]++;
        }
    }
} catch (PDOException $e) {
    error_log('Attendance performance graph error: ' . $e->getMessage());

    $performanceError =
        'Attendance performance graphs could not be loaded. ' .
        'Please verify the teacher_attendance table and its column names.';
}

/* =========================================================
   ATTENDANCE RATE
========================================================= */
function performanceRate(array $bucket): float
{
    $total = $bucket['present'] + $bucket['late'] + $bucket['absent'];

    return $total > 0
        ? round((($bucket['present'] + $bucket['late']) / $total) * 100, 1)
        : 0;
}

/* =========================================================
   DAILY CHART DATA
========================================================= */
$dailyLabels = [];
$dailyRates = [];
$dailyPresent = [];
$dailyLate = [];
$dailyAbsent = [];

foreach ($dailyPerformance as $dateKey => $bucket) {
    $dailyLabels[] = date('M d', strtotime($dateKey));
    $dailyRates[] = performanceRate($bucket);
    $dailyPresent[] = $bucket['present'];
    $dailyLate[] = $bucket['late'];
    $dailyAbsent[] = $bucket['absent'];
}

/* =========================================================
   WEEKLY CHART DATA
========================================================= */
$weeklyLabels = [];
$weeklyRates = [];
$weeklyPresent = [];
$weeklyLate = [];
$weeklyAbsent = [];

foreach ($weeklyPerformance as $dateKey => $bucket) {
    $weeklyLabels[] = date('M d', strtotime($dateKey));
    $weeklyRates[] = performanceRate($bucket);
    $weeklyPresent[] = $bucket['present'];
    $weeklyLate[] = $bucket['late'];
    $weeklyAbsent[] = $bucket['absent'];
}

/* =========================================================
   MONTHLY CHART DATA
========================================================= */
$monthlyLabels = [];
$monthlyRates = [];
$monthlyPresent = [];
$monthlyLate = [];
$monthlyAbsent = [];

foreach ($monthlyPerformance as $monthKey => $bucket) {
    $monthlyLabels[] = date('M Y', strtotime($monthKey . '-01'));
    $monthlyRates[] = performanceRate($bucket);
    $monthlyPresent[] = $bucket['present'];
    $monthlyLate[] = $bucket['late'];
    $monthlyAbsent[] = $bucket['absent'];
}

/* =========================================================
   SAFE JSON FOR CHART.JS
========================================================= */
function safeChartJson($value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?: '[]';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Teacher Dashboard | BeadleQR</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --purple: #8b5cf6;
        }

        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            margin: 0;
            background: var(--body-bg);
            min-height: 100vh;
            color: var(--text-dark);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            transition: all .3s ease;
        }

        /* TOPBAR */
        .topbar {
            background: #fff;
            min-height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mobile-menu-btn {
            display: none;
            border: none;
            background: transparent;
            font-size: 22px;
            color: var(--text-dark);
            cursor: pointer;
        }

        .topbar-title {
            font-size: 21px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .topbar-subtitle {
            font-size: 14px;
            color: var(--text-muted);
        }

        .teacher-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .teacher-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .teacher-info strong {
            display: block;
            font-size: 15px;
            color: var(--text-dark);
        }

        .teacher-info span {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
        }

        /* CONTENT */
        .content-area {
            padding: 30px 35px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h2 {
            color: var(--text-dark);
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .page-header p {
            color: var(--text-muted);
            font-size: 15px;
            margin: 0;
        }

        .page-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(249,115,22,.1);
            color: var(--primary-dark);
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        /* ALERTS */
        .alert-glass {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-glass.success {
            background: rgba(16,185,129,.1);
            border: 1px solid rgba(16,185,129,.2);
            color: #047857;
        }

        .alert-glass.error {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.2);
            color: #b91c1c;
        }

        /* STAT CARDS */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(15,23,42,.04);
            transition: all .3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(15,23,42,.08);
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .stat-card h5 {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 10px;
        }

        .stat-card h2 {
            color: var(--text-dark);
            font-size: 34px;
            font-weight: 700;
            margin: 0;
        }

        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(249,115,22,.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        /* GENERAL CARD */
        .card-box {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(15,23,42,.04);
            overflow: hidden;
        }

        .card-header-custom {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header-custom i {
            color: var(--primary);
            font-size: 20px;
        }

        .card-header-custom h5 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* SCHEDULE TABLE */
        .table-wrapper {
            overflow-x: auto;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table thead th {
            background: #f8fafc;
            color: #64748b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .6px;
            font-weight: 600;
            padding: 14px 18px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .schedule-table tbody td {
            padding: 17px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            color: #475569;
            vertical-align: middle;
        }

        .schedule-table tbody tr:hover {
            background: #fff7ed;
        }

        .schedule-table tbody tr:last-child td {
            border-bottom: none;
        }

        .subject-code {
            color: var(--text-dark);
            font-size: 15px;
            font-weight: 700;
        }

        .subject-name {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 3px;
        }

        .year-name {
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 14px;
        }

        .section-name {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 2px;
        }

        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff7ed;
            color: #c2410c;
            padding: 7px 11px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ACTION BUTTONS */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 8px 13px;
            border-radius: 9px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all .2s ease;
            cursor: pointer;
        }

        .btn-qr {
            background: rgba(16,185,129,.1);
            color: #047857;
            border-color: rgba(16,185,129,.2);
        }

        .btn-qr:hover {
            background: #10b981;
            color: white;
        }

        .btn-attendance {
            background: rgba(59,130,246,.1);
            color: #2563eb;
            border-color: rgba(59,130,246,.2);
        }

        .btn-attendance:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-beader {
            background: rgba(249,115,22,.1);
            color: #c2410c;
            border-color: rgba(249,115,22,.2);
        }

        .btn-beader:hover {
            background: var(--primary);
            color: white;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 55px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 42px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .empty-state h5 {
            color: var(--text-dark);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .empty-state p {
            font-size: 14px;
            margin: 0;
        }

        /* MODAL */
        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(15,23,42,.2);
        }

        .modal-header {
            background: var(--sidebar-bg);
            color: white;
            padding: 20px 24px;
            border: none;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }

        .modal-title i {
            color: var(--primary-light);
            margin-right: 8px;
        }

        .modal-body {
            padding: 24px;
            background: white;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .form-label-custom {
            display: block;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .form-select-custom {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 14px;
            color: var(--text-dark);
            background: white;
            outline: none;
            transition: all .2s ease;
        }

        .form-select-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(249,115,22,.1);
        }

        .btn-submit {
            border: none;
            padding: 11px 18px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s ease;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(249,115,22,.25);
        }

        /* =====================================================
           ATTENDANCE PERFORMANCE LAYOUT
           LARGE DAILY GRAPH LEFT
           SMALL WEEKLY + MONTHLY GRAPHS RIGHT
        ===================================================== */
        .performance-section {
            margin: 30px 0;
            width: 100%;
        }

        .performance-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .performance-heading h3 {
            margin: 0 0 5px;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .performance-heading p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .performance-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr);
            grid-template-rows: repeat(2, minmax(0, 1fr));
            grid-template-areas:
                "daily weekly"
                "daily monthly";
            gap: 16px;
            height: 540px;
            align-items: stretch;
        }

        .performance-card {
            min-width: 0;
            min-height: 0;
            padding: 18px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(15,23,42,.04);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .daily-card {
            grid-area: daily;
        }

        .weekly-card {
            grid-area: weekly;
        }

        .monthly-card {
            grid-area: monthly;
        }

        .performance-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }

        .performance-card-header h4 {
            margin: 0 0 5px;
            color: var(--text-dark);
            font-size: 15px;
            font-weight: 700;
        }

        .performance-card-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .performance-icon {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: var(--primary-dark);
            background: rgba(249,115,22,.11);
            font-size: 15px;
        }

        .performance-chart {
            position: relative;
            width: 100%;
            min-width: 0;
            min-height: 0;
            flex: 1;
        }

        .performance-footnote {
            flex-shrink: 0;
            margin-top: 9px;
            padding-top: 9px;
            border-top: 1px solid #f1f5f9;
            color: var(--text-muted);
            font-size: 10px;
            line-height: 1.5;
        }

        .performance-footnote i {
            color: var(--primary);
            margin-right: 4px;
        }

        .performance-alert {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 10px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 13px;
        }

        /* MOBILE SIDEBAR OVERLAY */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.45);
            z-index: 999;
        }

        /* TABLET: STACK GRAPHS */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: block;
            }

            .content-area {
                padding: 25px 20px;
            }

            .topbar {
                padding: 12px 20px;
            }

            .performance-grid {
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: auto;
                grid-template-areas:
                    "daily"
                    "weekly"
                    "monthly";
                height: auto;
            }

            .performance-card {
                min-height: 300px;
            }

            .performance-chart {
                height: 220px;
                flex: none;
            }

            .daily-card .performance-chart {
                height: 300px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .topbar-subtitle,
            .teacher-info {
                display: none;
            }

            .page-header h2 {
                font-size: 24px;
            }

            .stat-card h2 {
                font-size: 28px;
            }

            .schedule-table thead th,
            .schedule-table tbody td {
                padding: 13px;
            }
        }

        @media (max-width: 576px) {
            .content-area {
                padding: 20px 14px;
            }

            .performance-heading h3 {
                font-size: 19px;
            }

            .performance-card {
                padding: 15px;
            }

            .performance-card-header h4 {
                font-size: 14px;
            }

            .performance-chart,
            .daily-card .performance-chart {
                height: 250px;
            }

            .performance-card {
                min-height: 0;
            }
        }
    </style>
</head>

<body>

<?php include "../asset/teacher_sidebar.php"; ?>

<div class="main-content">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Open menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div>
                <div class="topbar-title">Teacher Dashboard</div>
                <div class="topbar-subtitle">Manage your classes and attendance</div>
            </div>
        </div>

        <div class="teacher-profile">
            <div class="teacher-avatar">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div class="teacher-info">
                <strong><?= htmlspecialchars($_SESSION['fullname'] ?? 'Teacher') ?></strong>
                <span>Instructor</span>
            </div>
        </div>
    </div>

    <div class="content-area">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-badge">
                <i class="fa-solid fa-chalkboard-user"></i>
                Teacher Dashboard
            </div>

            <h2>Welcome Back, Teacher</h2>
            <p>Manage your schedules, attendance, and assigned Beaders.</p>
        </div>

        <?= $message ?>

        <!-- STATISTICS -->
        <div class="row g-4 mb-4">

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <h5>Total Schedules</h5>
                    <h2><?= $totalSchedules ?></h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-chalkboard"></i>
                    </div>
                    <h5>Today's Classes</h5>
                    <h2><?= count($todayClasses) ?></h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <h5>Today</h5>
                    <h2 style="font-size:22px; margin-top:8px;">
                        <?= htmlspecialchars($today) ?>
                    </h2>
                </div>
            </div>

        </div>

        <!-- =====================================================
             ATTENDANCE PERFORMANCE GRAPHS
        ====================================================== -->
        <section class="performance-section">

            <div class="performance-heading">
                <div>
                    <h3>
                        <i class="fa-solid fa-chart-column me-2" style="color:var(--primary);"></i>
                        Attendance Performance
                    </h3>
                    <p>Track your attendance rate by day, week, and month.</p>
                </div>

                <span class="page-badge">
                    <i class="fa-solid fa-chart-line"></i>
                    Performance Analytics
                </span>
            </div>

            <?php if ($performanceError): ?>
                <div class="performance-alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <?= htmlspecialchars($performanceError) ?>
                </div>
            <?php endif; ?>

            <div class="performance-grid">

                <!-- LARGE DAILY GRAPH ON THE LEFT -->
                <article class="performance-card daily-card">
                    <div class="performance-card-header">
                        <div>
                            <h4>Daily Performance</h4>
                            <p>Attendance rate over the last 7 days</p>
                        </div>
                        <div class="performance-icon">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                    </div>

                    <div class="performance-chart">
                        <canvas id="dailyPerformanceChart"></canvas>
                    </div>

                    <div class="performance-footnote">
                        <i class="fa-solid fa-circle-info"></i>
                        Daily attendance rate. Hover over a bar to see Present, Late, and Absent totals.
                    </div>
                </article>

                <!-- SMALL WEEKLY GRAPH ON THE TOP RIGHT -->
                <article class="performance-card weekly-card">
                    <div class="performance-card-header">
                        <div>
                            <h4>Weekly Performance</h4>
                            <p>Attendance rate over the last 8 weeks</p>
                        </div>
                        <div class="performance-icon">
                            <i class="fa-solid fa-calendar-week"></i>
                        </div>
                    </div>

                    <div class="performance-chart">
                        <canvas id="weeklyPerformanceChart"></canvas>
                    </div>

                    <div class="performance-footnote">
                        <i class="fa-solid fa-circle-info"></i>
                        Each week starts on Monday. Hover over a bar for attendance details.
                    </div>
                </article>

                <!-- SMALL MONTHLY GRAPH ON THE BOTTOM RIGHT -->
                <article class="performance-card monthly-card">
                    <div class="performance-card-header">
                        <div>
                            <h4>Monthly Performance</h4>
                            <p>Attendance rate over the last 6 months</p>
                        </div>
                        <div class="performance-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                    </div>

                    <div class="performance-chart">
                        <canvas id="monthlyPerformanceChart"></canvas>
                    </div>

                    <div class="performance-footnote">
                        <i class="fa-solid fa-circle-info"></i>
                        Review your longer-term attendance performance.
                    </div>
                </article>

            </div>
        </section>

        <!-- TODAY'S SCHEDULE -->
        <div class="card-box">

            <div class="card-header-custom">
                <i class="fa-solid fa-calendar-day"></i>
                <h5>Today's Schedule — <?= htmlspecialchars($today) ?></h5>
            </div>

            <div class="table-wrapper">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Year &amp; Section</th>
                            <th>Room</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($todayClasses)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fa-regular fa-calendar-xmark"></i>
                                    <h5>No Classes Today</h5>
                                    <p>You don't have any scheduled classes for today.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($todayClasses as $row): ?>
                            <tr>
                                <td>
                                    <div class="subject-code">
                                        <?= htmlspecialchars($row['subject_code']) ?>
                                    </div>
                                    <div class="subject-name">
                                        <?= htmlspecialchars($row['subject_name']) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="year-name">
                                        <?= htmlspecialchars($row['year_name']) ?>
                                    </div>
                                    <div class="section-name">
                                        Section <?= htmlspecialchars($row['section_name']) ?>
                                    </div>
                                </td>

                                <td>
                                    <strong><?= htmlspecialchars($row['room']) ?></strong>
                                </td>

                                <td>
                                    <span class="time-badge">
                                        <i class="fa-regular fa-clock"></i>
                                        <?= date('h:i A', strtotime($row['start_time'])) ?>
                                        -
                                        <?= date('h:i A', strtotime($row['end_time'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex gap-2 flex-wrap">

                                        <a
                                            href="generate_qr.php?schedule_id=<?= (int)$row['id'] ?>"
                                            class="btn-action btn-qr">
                                            <i class="fa-solid fa-qrcode"></i> QR
                                        </a>

                                        <a
                                            href="view_attendance.php?schedule_id=<?= (int)$row['id'] ?>"
                                            class="btn-action btn-attendance">
                                            <i class="fa-solid fa-clipboard-check"></i> Attendance
                                        </a>

                                        <button
                                            type="button"
                                            class="btn-action btn-beader"
                                            data-bs-toggle="modal"
                                            data-bs-target="#beaderModal"
                                            onclick="setSchedule(<?= (int)$row['id'] ?>)">
                                            <i class="fa-solid fa-user-check"></i> Beader
                                        </button>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ASSIGN EXISTING BEADER MODAL -->
<div class="modal fade" id="beaderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-user-check"></i>
                    Assign Existing Beader
                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>

            <form method="POST">
                <div class="modal-body">

                    <input type="hidden" name="schedule_id" id="schedule_id">

                    <div class="mb-3">
                        <label class="form-label-custom" for="student_id">
                            Select Existing Student
                        </label>

                        <select
                            name="student_id"
                            id="student_id"
                            class="form-select-custom"
                            required>
                            <option value="">Choose a student...</option>

                            <?php foreach ($students as $st): ?>
                                <option value="<?= (int)$st['id'] ?>">
                                    <?= htmlspecialchars($st['fullname']) ?>
                                    —
                                    <?= htmlspecialchars($st['student_no'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:12px 14px;border-radius:10px;font-size:12px;">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Select a student who already exists in the system.
                        No new Beader account will be created here.
                    </div>

                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        name="assign_beader"
                        class="btn-submit">
                        <i class="fa-solid fa-user-check me-1"></i>
                        Assign Beader
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* =========================================================
   SET SCHEDULE ID
========================================================= */
function setSchedule(id) {
    const input = document.getElementById('schedule_id');

    if (input) {
        input.value = id;
    }
}

/* =========================================================
   MOBILE SIDEBAR
========================================================= */
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (mobileMenuBtn && sidebar && sidebarOverlay) {
    mobileMenuBtn.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
    });

    sidebarOverlay.addEventListener('click', function () {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    });
}

/* =========================================================
   CHART DATA FROM PHP
========================================================= */
const performanceChartData = {
    daily: {
        labels: <?= safeChartJson($dailyLabels) ?>,
        rates: <?= safeChartJson($dailyRates) ?>,
        present: <?= safeChartJson($dailyPresent) ?>,
        late: <?= safeChartJson($dailyLate) ?>,
        absent: <?= safeChartJson($dailyAbsent) ?>
    },
    weekly: {
        labels: <?= safeChartJson($weeklyLabels) ?>,
        rates: <?= safeChartJson($weeklyRates) ?>,
        present: <?= safeChartJson($weeklyPresent) ?>,
        late: <?= safeChartJson($weeklyLate) ?>,
        absent: <?= safeChartJson($weeklyAbsent) ?>
    },
    monthly: {
        labels: <?= safeChartJson($monthlyLabels) ?>,
        rates: <?= safeChartJson($monthlyRates) ?>,
        present: <?= safeChartJson($monthlyPresent) ?>,
        late: <?= safeChartJson($monthlyLate) ?>,
        absent: <?= safeChartJson($monthlyAbsent) ?>
    }
};

/* =========================================================
   RENDER PERFORMANCE CHART
========================================================= */
function renderPerformanceChart(canvasId, dataset, compact = false) {
    const canvas = document.getElementById(canvasId);

    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        type: 'bar',

        data: {
            labels: dataset.labels,
            datasets: [{
                label: 'Attendance Rate',
                data: dataset.rates,

                backgroundColor: dataset.rates.map(rate =>
                    rate >= 90
                        ? '#f97316'
                        : rate >= 70
                            ? 'rgba(249,115,22,0.72)'
                            : 'rgba(249,115,22,0.42)'
                ),

                hoverBackgroundColor: '#ea580c',
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: compact ? 25 : 42
            }]
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
                    padding: compact ? 9 : 12,
                    cornerRadius: 8,

                    callbacks: {
                        label: function(context) {
                            const i = context.dataIndex;

                            return [
                                'Attendance rate: ' + context.raw + '%',
                                'Present: ' + dataset.present[i],
                                'Late: ' + dataset.late[i],
                                'Absent: ' + dataset.absent[i]
                            ];
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    min: 0,
                    max: 100,

                    ticks: {
                        stepSize: 20,
                        color: '#64748b',

                        callback: value => value + '%',

                        font: {
                            family: 'Poppins',
                            size: compact ? 9 : 11
                        }
                    },

                    grid: {
                        color: '#edf2f7'
                    },

                    border: {
                        display: false
                    }
                },

                x: {
                    ticks: {
                        color: '#64748b',
                        maxRotation: compact ? 45 : 35,
                        minRotation: 0,
                        autoSkip: false,

                        font: {
                            family: 'Poppins',
                            size: compact ? 8 : 10
                        }
                    },

                    grid: {
                        display: false
                    },

                    border: {
                        display: false
                    }
                }
            }
        }
    });
}

/* Large daily graph; compact weekly and monthly graphs */
renderPerformanceChart('dailyPerformanceChart', performanceChartData.daily, false);
renderPerformanceChart('weeklyPerformanceChart', performanceChartData.weekly, true);
renderPerformanceChart('monthlyPerformanceChart', performanceChartData.monthly, true);
</script>

</body>
</html>
