<?php
session_start();
require "../db.php";

/* =========================================================
   BEADLE / STUDENT DASHBOARD
   ========================================================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = (int) $_SESSION['user_id'];

date_default_timezone_set('Asia/Manila');

/* =========================================================
   HELPER
   ========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatTime($time)
{
    if (!$time) {
        return '--';
    }

    return date('h:i A', strtotime($time));
}

/* =========================================================
   GET BEADLE INFORMATION
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        fullname,
        student_no,
        year_level_id,
        section_id
    FROM users
    WHERE id = :student_id
    LIMIT 1
");

$stmt->execute([
    'student_id' => $student_id
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student account not found.");
}

$beader_name = $student['fullname'];

/* =========================================================
   CHECK BEADLE ASSIGNMENT
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM beader_assignments
    WHERE beader_id = :beader_id
");

$stmt->execute([
    'beader_id' => $student_id
]);

$isBeadle = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

if ($isBeadle === 0) {
    die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>BeadleQR</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body class='bg-light'>
            <div class='container py-5'>
                <div class='alert alert-warning shadow-sm'>
                    <h5 class='mb-2'>Beadle Assignment Required</h5>
                    <p class='mb-0'>
                        You are not currently assigned as a Beadle.
                    </p>
                </div>
            </div>
        </body>
        </html>
    ");
}

/* =========================================================
   DATE / TIME
   ========================================================= */

$today = date('Y-m-d');
$currentTime = date('H:i:s');
$currentDay = strtolower(date('l'));

/* =========================================================
   AUTOMATIC ABSENT
   ---------------------------------------------------------
   If today's scheduled class has already passed its
   end_time and the teacher has NOT scanned/created an
   attendance record, create ONE Absent record.
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        s.id AS schedule_id,
        s.teacher_id,
        s.start_time,
        s.end_time,
        s.year_level_id,
        s.section_id
    FROM schedules s
    INNER JOIN beader_assignments ba
        ON ba.section_id = s.section_id
        AND ba.year_level_id = s.year_level_id
    WHERE ba.beader_id = :beader_id
      AND LOWER(s.day) = :current_day
");

$stmt->execute([
    'beader_id' => $student_id,
    'current_day' => $currentDay
]);

$todaySchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($todaySchedules as $schedule) {

    if ($currentTime <= $schedule['end_time']) {
        continue;
    }

    $attendanceCheck = $conn->prepare("
        SELECT id
        FROM teacher_attendance
        WHERE teacher_id = :teacher_id
          AND schedule_id = :schedule_id
          AND beader_id = :beader_id
          AND attendance_date = :attendance_date
        LIMIT 1
    ");

    $attendanceCheck->execute([
        'teacher_id' => $schedule['teacher_id'],
        'schedule_id' => $schedule['schedule_id'],
        'beader_id' => $student_id,
        'attendance_date' => $today
    ]);

    $existingAttendance = $attendanceCheck->fetch(PDO::FETCH_ASSOC);

    if ($existingAttendance) {
        continue;
    }

    $sessionStmt = $conn->prepare("
        SELECT
            id
        FROM attendance_sessions
        WHERE schedule_id = :schedule_id
          AND beader_id = :beader_id
          AND session_date = :session_date
        ORDER BY id DESC
        LIMIT 1
    ");

    $sessionStmt->execute([
        'schedule_id' => $schedule['schedule_id'],
        'beader_id' => $student_id,
        'session_date' => $today
    ]);

    $attendanceSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendanceSession) {
        continue;
    }

    $insertAbsent = $conn->prepare("
        INSERT INTO teacher_attendance (
            teacher_id,
            schedule_id,
            session_id,
            beader_id,
            attendance_date,
            status,
            scan_source,
            remarks
        )
        VALUES (
            :teacher_id,
            :schedule_id,
            :session_id,
            :beader_id,
            :attendance_date,
            'Absent',
            'system',
            NULL
        )
    ");

    $insertAbsent->execute([
        'teacher_id' => $schedule['teacher_id'],
        'schedule_id' => $schedule['schedule_id'],
        'session_id' => $attendanceSession['id'],
        'beader_id' => $student_id,
        'attendance_date' => $today
    ]);
}

/* =========================================================
   UPDATE TEACHER ATTENDANCE REMARKS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_remarks'])) {

    $attendance_id = (int)($_POST['attendance_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($attendance_id > 0) {

        $stmt = $conn->prepare("
            UPDATE teacher_attendance
            SET remarks = :remarks
            WHERE id = :attendance_id
              AND beader_id = :beader_id
        ");

        $stmt->execute([
            'remarks' => $remarks !== '' ? $remarks : null,
            'attendance_id' => $attendance_id,
            'beader_id' => $student_id
        ]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* =========================================================
   ASSIGNED SECTIONS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        ba.section_id,
        ba.year_level_id,
        sec.section_name,
        yl.year_name
    FROM beader_assignments ba
    INNER JOIN sections sec
        ON ba.section_id = sec.id
    INNER JOIN year_levels yl
        ON ba.year_level_id = yl.id
    WHERE ba.beader_id = :beader_id
    ORDER BY yl.id, sec.section_name
");

$stmt->execute([
    'beader_id' => $student_id
]);

$assignedSections = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   TOTAL ASSIGNED SUBJECTS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_subjects
    FROM schedules s
    INNER JOIN beader_assignments ba
        ON ba.section_id = s.section_id
        AND ba.year_level_id = s.year_level_id
    WHERE ba.beader_id = :beader_id
");

$stmt->execute([
    'beader_id' => $student_id
]);

$totalSubjects = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_subjects'];

/* =========================================================
   TOTAL ATTENDANCE RECORDS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_attendance
    FROM teacher_attendance ta
    INNER JOIN schedules s
        ON ta.schedule_id = s.id
    INNER JOIN beader_assignments ba
        ON ba.section_id = s.section_id
        AND ba.year_level_id = s.year_level_id
    WHERE ta.beader_id = :ta_beader_id
      AND ba.beader_id = :ba_beader_id
");

$stmt->execute([
    'ta_beader_id' => $student_id,
    'ba_beader_id' => $student_id
]);

$totalAttendance = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_attendance'];

/* =========================================================
   TODAY'S ATTENDANCE
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_today
    FROM teacher_attendance ta
    WHERE ta.beader_id = :beader_id
      AND ta.attendance_date = :today
");

$stmt->execute([
    'beader_id' => $student_id,
    'today' => $today
]);

$totalToday = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_today'];

/* =========================================================
   COMPLETED SESSIONS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_completed
    FROM attendance_sessions ats
    WHERE ats.beader_id = :beader_id
      AND ats.status = 'completed'
");

$stmt->execute([
    'beader_id' => $student_id
]);

$totalCompleted = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_completed'];

/* =========================================================
   ASSIGNED SUBJECTS / SCHEDULES
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        s.id AS schedule_id,
        s.subject_id,
        sub.subject_code,
        sub.subject_name,

        t.fullname AS teacher_name,

        s.room,
        s.day,
        s.start_time,
        s.end_time,

        yl.year_name,
        sec.section_name

    FROM schedules s

    INNER JOIN subjects sub
        ON s.subject_id = sub.id

    INNER JOIN users t
        ON s.teacher_id = t.id

    INNER JOIN year_levels yl
        ON s.year_level_id = yl.id

    INNER JOIN sections sec
        ON s.section_id = sec.id

    INNER JOIN beader_assignments ba
        ON ba.section_id = s.section_id
        AND ba.year_level_id = s.year_level_id

    WHERE ba.beader_id = :beader_id

    ORDER BY
        CASE
            WHEN LOWER(s.day) = 'monday' THEN 1
            WHEN LOWER(s.day) = 'tuesday' THEN 2
            WHEN LOWER(s.day) = 'wednesday' THEN 3
            WHEN LOWER(s.day) = 'thursday' THEN 4
            WHEN LOWER(s.day) = 'friday' THEN 5
            WHEN LOWER(s.day) = 'saturday' THEN 6
            WHEN LOWER(s.day) = 'sunday' THEN 7
            ELSE 8
        END,
        s.start_time
");

$stmt->execute([
    'beader_id' => $student_id
]);

$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   TEACHER ATTENDANCE MONITORING
   ========================================================= */

$stmt = $conn->prepare("
    SELECT

        ta.id AS attendance_id,
        ta.attendance_date,
        ta.time_in,
        ta.time_out,
        ta.status,
        ta.remarks,

        t.fullname AS teacher_name,

        sub.subject_code,
        sub.subject_name,

        s.room,
        s.day,
        s.start_time,
        s.end_time,

        yl.year_name,
        sec.section_name

    FROM teacher_attendance ta

    INNER JOIN schedules s
        ON ta.schedule_id = s.id

    INNER JOIN subjects sub
        ON s.subject_id = sub.id

    INNER JOIN users t
        ON s.teacher_id = t.id

    INNER JOIN year_levels yl
        ON s.year_level_id = yl.id

    INNER JOIN sections sec
        ON s.section_id = sec.id

    INNER JOIN beader_assignments ba
        ON ba.section_id = s.section_id
        AND ba.year_level_id = s.year_level_id

    WHERE ta.beader_id = :ta_beader_id
      AND ba.beader_id = :ba_beader_id

    ORDER BY
        ta.attendance_date DESC,
        CASE
            WHEN LOWER(s.day) = 'monday' THEN 1
            WHEN LOWER(s.day) = 'tuesday' THEN 2
            WHEN LOWER(s.day) = 'wednesday' THEN 3
            WHEN LOWER(s.day) = 'thursday' THEN 4
            WHEN LOWER(s.day) = 'friday' THEN 5
            WHEN LOWER(s.day) = 'saturday' THEN 6
            WHEN LOWER(s.day) = 'sunday' THEN 7
            ELSE 8
        END,
        s.start_time
");

$stmt->execute([
    'ta_beader_id' => $student_id,
    'ba_beader_id' => $student_id
]);

$teacherAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   TODAY'S ACTIVE SESSIONS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        ats.id,
        ats.qr_expires_at,
        ats.started_at,
        ats.status,

        s.id AS schedule_id,
        sub.subject_code,
        sub.subject_name,

        t.fullname AS teacher_name,

        s.room,
        s.day,
        s.start_time,
        s.end_time

    FROM attendance_sessions ats

    INNER JOIN schedules s
        ON ats.schedule_id = s.id

    INNER JOIN subjects sub
        ON s.subject_id = sub.id

    INNER JOIN users t
        ON s.teacher_id = t.id

    WHERE ats.beader_id = :beader_id
      AND ats.session_date = :today
      AND ats.status = 'active'

    ORDER BY ats.created_at DESC
");

$stmt->execute([
    'beader_id' => $student_id,
    'today' => $today
]);

$activeSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>BeadleQR | Dashboard</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

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
           SIDEBAR
           ===================================================== */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1000;
            transition: all .3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,.1);
            text-align: center;
        }

        .sidebar-logo {
            color: white;
            font-size: 20px;
            font-weight: 700;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar-logo i {
            color: var(--primary);
            font-size: 28px;
        }

        .sidebar-subtitle {
            color: rgba(255,255,255,.5);
            font-size: 11px;
            margin-top: 5px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            padding: 20px 15px;
        }

        .menu-label {
            color: rgba(255,255,255,.4);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0 12px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 12px 15px;

            color: rgba(255,255,255,.7);
            text-decoration: none;

            border-radius: 10px;
            margin-bottom: 5px;

            font-size: 14px;
            font-weight: 500;

            transition: all .3s ease;
        }

        .sidebar-menu a i {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--sidebar-hover);
            color: white;
        }

        .sidebar-menu a.active {
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );

            box-shadow: 0 4px 15px rgba(249,115,22,.3);
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;

            padding: 20px 15px;

            border-top: 1px solid rgba(255,255,255,.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 12px 15px;

            color: #ef4444;
            text-decoration: none;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 500;

            transition: all .3s ease;

            background: rgba(239,68,68,.1);
        }

        .logout-btn:hover {
            background: rgba(239,68,68,.2);
            color: #f87171;
        }

        /* =====================================================
           MAIN CONTENT
           ===================================================== */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        .topbar {
            background: white;
            height: 70px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            padding: 0 35px;

            box-shadow: 0 2px 10px rgba(0,0,0,.04);

            position: sticky;
            top: 0;

            z-index: 100;
        }

        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .topbar-title span {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 400;
            margin-left: 8px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
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

            font-size: 16px;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
        }

        .user-role {
            font-size: 11px;
            color: var(--text-muted);
        }

        .content-area {
            padding: 30px 35px;
        }

        /* =====================================================
           WELCOME
           ===================================================== */

        .welcome-card {
            background: linear-gradient(
                135deg,
                #ffffff,
                #fff7ed
            );

            border: 1px solid #fed7aa;

            border-radius: 16px;

            padding: 25px 28px;

            margin-bottom: 25px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            overflow: hidden;
        }

        .welcome-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .welcome-title span {
            color: var(--primary);
        }

        .welcome-text {
            color: var(--text-muted);
            font-size: 13px;
        }

        .welcome-icon {
            width: 70px;
            height: 70px;

            border-radius: 18px;

            background: rgba(249,115,22,.12);

            display: flex;
            align-items: center;
            justify-content: center;

            color: var(--primary);

            font-size: 32px;
        }

        /* =====================================================
           STAT CARDS
           ===================================================== */

        .stat-card {
            background: white;

            border-radius: 15px;

            padding: 22px;

            border: 1px solid #e2e8f0;

            height: 100%;

            transition: all .3s ease;

            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(15,23,42,.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;

            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;

            margin-bottom: 15px;
        }

        .stat-orange .stat-icon {
            background: rgba(249,115,22,.12);
            color: var(--primary);
        }

        .stat-green .stat-icon {
            background: rgba(16,185,129,.12);
            color: var(--success);
        }

        .stat-blue .stat-icon {
            background: rgba(59,130,246,.12);
            color: var(--info);
        }

        .stat-purple .stat-icon {
            background: rgba(139,92,246,.12);
            color: var(--purple);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* =====================================================
           SECTION CARD
           ===================================================== */

        .section-card {
            background: white;

            border-radius: 15px;

            border: 1px solid #e2e8f0;

            overflow: hidden;

            margin-top: 25px;
        }

        .section-header {
            padding: 18px 22px;

            border-bottom: 1px solid #e2e8f0;

            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;

            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        .section-subtitle {
            color: var(--text-muted);
            font-size: 12px;
        }

        .section-body {
            padding: 0;
        }

        /* =====================================================
           TABLE
           ===================================================== */

        .table-container {
            overflow-x: auto;
        }

        .custom-table {
            margin: 0;
            min-width: 850px;
        }

        .custom-table thead th {
            background: #f8fafc;

            color: #475569;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .5px;

            font-weight: 600;

            padding: 14px 18px;

            border-bottom: 1px solid #e2e8f0;

            white-space: nowrap;
        }

        .custom-table tbody td {
            padding: 15px 18px;

            vertical-align: middle;

            border-bottom: 1px solid #f1f5f9;

            font-size: 13px;

            color: #475569;
        }

        .custom-table tbody tr:last-child td {
            border-bottom: none;
        }

        .custom-table tbody tr:hover {
            background: #fffaf5;
        }

        .subject-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .subject-code {
            display: inline-block;

            background: #fff7ed;

            color: var(--primary-dark);

            border-radius: 6px;

            padding: 4px 7px;

            font-size: 10px;

            font-weight: 600;

            margin-bottom: 4px;
        }

        .teacher-name {
            font-weight: 500;
            color: var(--text-dark);
        }

        .schedule-text {
            white-space: nowrap;
        }

        .room-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            padding: 5px 9px;

            background: #f1f5f9;

            color: #475569;

            border-radius: 6px;

            font-size: 11px;
        }

        .day-badge {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 6px;

            background: #eef2ff;

            color: #4f46e5;

            font-size: 11px;

            font-weight: 600;
        }

        /* =====================================================
           SCAN BUTTON
           ===================================================== */

        .scan-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            background: var(--primary);

            color: white;

            text-decoration: none;

            padding: 8px 13px;

            border-radius: 8px;

            font-size: 12px;

            font-weight: 600;

            transition: all .3s ease;
        }

        .scan-btn:hover {
            background: var(--primary-dark);
            color: white;

            transform: translateY(-1px);
        }

        /* =====================================================
           DISABLED SCAN BUTTON
           ===================================================== */

        .scan-btn.disabled-scan {
            background: #cbd5e1;
            color: #64748b;
            cursor: not-allowed;
            pointer-events: none;
            opacity: .8;
        }

        .scan-btn.disabled-scan:hover {
            background: #cbd5e1;
            color: #64748b;
            transform: none;
        }

        /* =====================================================
           STATUS BADGES
           ===================================================== */

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-width: 32px;

            padding: 5px 9px;

            border-radius: 6px;

            font-size: 11px;

            font-weight: 600;
        }

        .status-present {
            background: #dcfce7;
            color: #15803d;
        }

        .status-late {
            background: #fef3c7;
            color: #b45309;
        }

        .status-absent {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-total {
            background: #f1f5f9;
            color: #475569;
        }

        /* =====================================================
           REMARKS
           ===================================================== */

        .remarks-form {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 280px;
        }

        .remarks-input {
            min-width: 220px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            font-size: 12px;
            padding: 7px 10px;
        }

        .remarks-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .15rem rgba(249,115,22,.12);
        }

        .save-remarks-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;

            background: var(--primary);
            color: white;

            border: none;
            border-radius: 7px;

            padding: 7px 11px;

            font-size: 11px;
            font-weight: 600;

            cursor: pointer;

            transition: all .2s ease;
        }

        .save-remarks-btn:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        /* =====================================================
           ACTIVE SESSION
           ===================================================== */

        .active-session {
            padding: 18px 22px;

            background: linear-gradient(
                135deg,
                #fff7ed,
                #ffffff
            );

            border-bottom: 1px solid #fed7aa;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;
        }

        .active-session:last-child {
            border-bottom: none;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            color: #dc2626;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;
        }

        .live-dot {
            width: 8px;
            height: 8px;

            background: #ef4444;

            border-radius: 50%;

            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .4;
                transform: scale(1.25);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .empty-state {
            padding: 45px 20px;

            text-align: center;

            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 40px;

            color: #cbd5e1;

            margin-bottom: 12px;
        }

        .empty-state h6 {
            color: #475569;
            font-weight: 600;
        }

        .empty-state p {
            font-size: 12px;
            margin-bottom: 0;
        }

        /* =====================================================
           SECTION PILLS
           ===================================================== */

        .assigned-section {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 7px 11px;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 8px;

            font-size: 11px;

            color: #475569;

            margin: 3px;
        }

        .assigned-section i {
            color: var(--primary);
        }

        /* =====================================================
           INCREASE MAIN CONTENT FONT SIZES
           SIDEBAR REMAINS UNCHANGED
           ===================================================== */

        .topbar-title {
            font-size: 21px;
        }

        .topbar-title span {
            font-size: 15px;
        }

        .user-name {
            font-size: 15px;
        }

        .user-role {
            font-size: 13px;
        }

        .welcome-title {
            font-size: 28px;
        }

        .welcome-text {
            font-size: 15px;
        }

        .stat-label {
            font-size: 15px;
        }

        .stat-number {
            font-size: 32px;
        }

        .section-title {
            font-size: 19px;
        }

        .section-subtitle {
            font-size: 14px;
        }

        .custom-table thead th {
            font-size: 13px;
        }

        .custom-table tbody td {
            font-size: 15px;
        }

        .subject-code {
            font-size: 12px;
        }

        .room-badge {
            font-size: 13px;
        }

        .day-badge {
            font-size: 13px;
        }

        .status-badge {
            font-size: 13px;
        }

        .scan-btn {
            font-size: 14px;
        }

        .remarks-input {
            font-size: 14px;
        }

        .save-remarks-btn {
            font-size: 13px;
        }

        .live-indicator {
            font-size: 13px;
        }

        .empty-state p {
            font-size: 14px;
        }

        .assigned-section {
            font-size: 13px;
        }

        /* =====================================================
           MOBILE
           ===================================================== */

        @media (max-width: 992px) {

            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
            }

            .topbar,
            .content-area {
                padding-left: 22px;
                padding-right: 22px;
            }
        }

        @media (max-width: 768px) {

            .sidebar {
                width: 70px;
            }

            .sidebar-header {
                padding: 18px 8px;
            }

            .sidebar-logo span,
            .sidebar-subtitle,
            .menu-label,
            .sidebar-menu a span,
            .logout-btn span {
                display: none;
            }

            .sidebar-logo {
                justify-content: center;
            }

            .sidebar-menu {
                padding: 15px 8px;
            }

            .sidebar-menu a {
                justify-content: center;
                padding: 12px;
            }

            .sidebar-menu a i {
                margin: 0;
            }

            .sidebar-footer {
                padding: 10px 8px;
            }

            .logout-btn {
                justify-content: center;
                padding: 12px;
            }

            .main-content {
                margin-left: 70px;
            }

            .topbar {
                padding: 0 15px;
            }

            .content-area {
                padding: 20px 15px;
            }

            .topbar-title span {
                display: none;
            }

            .user-name,
            .user-role {
                display: none;
            }

            .welcome-card {
                padding: 20px;
            }

            .welcome-title {
                font-size: 20px;
            }

            .welcome-icon {
                width: 55px;
                height: 55px;
                font-size: 24px;
            }

            .active-session {
                align-items: flex-start;
                flex-direction: column;
            }
        }

    </style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
     ========================================================= -->

<aside class="sidebar">

    <div class="sidebar-header">

        <div class="sidebar-logo">
            <i class="fa-solid fa-qrcode"></i>
            <span>BeadleQR</span>
        </div>

        <div class="sidebar-subtitle">
            Beadle Panel
        </div>

    </div>

    <div class="sidebar-menu">

        <div class="menu-label">
            Main Menu
        </div>

        <a href="student_dashboard.php" class="active">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>

        <a href="scan_qr.php">
            <i class="fa-solid fa-qrcode"></i>
            <span>Scan QR</span>
        </a>

    </div>

    <div class="sidebar-footer">

        <a href="../logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>

    </div>

</aside>


<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<main class="main-content">

    <div class="topbar">

        <div class="topbar-title">
            Dashboard
            <span>Beadle Attendance Management</span>
        </div>

        <div class="user-info">

            <div class="user-avatar">
                <i class="fa-solid fa-user"></i>
            </div>

            <div>
                <div class="user-name">
                    <?= e($beader_name) ?>
                </div>

                <div class="user-role">
                    Student / Beadle
                </div>
            </div>

        </div>

    </div>


    <div class="content-area">

        <!-- =================================================
             WELCOME
             ================================================= -->

        <div class="welcome-card">

            <div>

                <div class="welcome-title">
                    Welcome back,
                    <span><?= e($beader_name) ?></span>!
                </div>

                <div class="welcome-text">
                    Manage your assigned schedules and record teacher attendance using BeadleQR.
                </div>

            </div>

            <div class="welcome-icon">
                <i class="fa-solid fa-qrcode"></i>
            </div>

        </div>


        <!-- =================================================
             STATS
             ================================================= -->

        <div class="row g-4">

            <div class="col-xl-3 col-md-6">

                <div class="stat-card stat-orange">

                    <div class="stat-icon">
                        <i class="fa-solid fa-book-open"></i>
                    </div>

                    <div class="stat-label">
                        Assigned Subjects
                    </div>

                    <div class="stat-number">
                        <?= $totalSubjects ?>
                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card stat-green">

                    <div class="stat-icon">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>

                    <div class="stat-label">
                        Total Attendance
                    </div>

                    <div class="stat-number">
                        <?= $totalAttendance ?>
                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card stat-blue">

                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>

                    <div class="stat-label">
                        Today's Attendance
                    </div>

                    <div class="stat-number">
                        <?= $totalToday ?>
                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card stat-purple">

                    <div class="stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div class="stat-label">
                        Completed Sessions
                    </div>

                    <div class="stat-number">
                        <?= $totalCompleted ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             ASSIGNED SECTIONS
             ================================================= -->

        <div class="section-card">

            <div class="section-header">

                <div>

                    <div class="section-title">
                        <i class="fa-solid fa-layer-group"></i>
                        Assigned Sections
                    </div>

                    <div class="section-subtitle">
                        Sections currently assigned to you
                    </div>

                </div>

            </div>

            <div class="section-body p-3">

                <?php if (count($assignedSections) > 0): ?>

                    <?php foreach ($assignedSections as $section): ?>

                        <span class="assigned-section">

                            <i class="fa-solid fa-users"></i>

                            <?= e($section['year_name']) ?>
                            -
                            <?= e($section['section_name']) ?>

                        </span>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="text-muted small">
                        No assigned sections.
                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             ACTIVE ATTENDANCE SESSIONS
             ================================================= -->

        <?php if (count($activeSessions) > 0): ?>

            <div class="section-card">

                <div class="section-header">

                    <div>

                        <div class="section-title">
                            <i class="fa-solid fa-satellite-dish"></i>
                            Active Attendance Sessions
                        </div>

                        <div class="section-subtitle">
                            Attendance sessions currently waiting for your scan
                        </div>

                    </div>

                    <span class="live-indicator">
                        <span class="live-dot"></span>
                        Live
                    </span>

                </div>


                <div>

                    <?php foreach ($activeSessions as $session): ?>

                        <?php
                        /*
                         * Enable Scan QR only when the session's
                         * schedule day matches today's day.
                         */
                        $sessionDay = strtolower(trim($session['day']));
                        $isCorrectDay = ($sessionDay === $currentDay);
                        ?>

                        <div class="active-session">

                            <div>

                                <div class="subject-code">
                                    <?= e($session['subject_code']) ?>
                                </div>

                                <div class="subject-name mb-1">
                                    <?= e($session['subject_name']) ?>
                                </div>

                                <div class="teacher-name mb-1">
                                    <i class="fa-solid fa-user-tie me-1"></i>
                                    <?= e($session['teacher_name']) ?>
                                </div>

                                <div class="small text-muted">

                                    <i class="fa-solid fa-clock me-1"></i>

                                    <?= formatTime($session['start_time']) ?>
                                    -
                                    <?= formatTime($session['end_time']) ?>

                                    <span class="mx-2">•</span>

                                    <i class="fa-solid fa-door-open me-1"></i>

                                    <?= e($session['room'] ?: 'No room') ?>

                                </div>

                            </div>


                            <div>

                                <?php if ($isCorrectDay): ?>

                                    <a
                                        href="scan_qr.php?schedule_id=<?= (int)$session['schedule_id'] ?>"
                                        class="scan-btn">

                                        <i class="fa-solid fa-qrcode"></i>

                                        Scan QR

                                    </a>

                                <?php else: ?>

                                    <span class="scan-btn disabled-scan">

                                        <i class="fa-solid fa-ban"></i>

                                        Not Today

                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ASSIGNED SUBJECTS
             ================================================= -->

        <div class="section-card">

            <div class="section-header">

                <div>

                    <div class="section-title">
                        <i class="fa-solid fa-calendar-days"></i>
                        Assigned Subjects
                    </div>

                    <div class="section-subtitle">
                        Your assigned class schedules
                    </div>

                </div>

                <span class="badge rounded-pill text-bg-light">
                    <?= $totalSubjects ?> Subjects
                </span>

            </div>

            <div class="section-body">

                <div class="table-container">

                    <table class="table custom-table">

                        <thead>

                            <tr>

                                <th>Subject</th>

                                <th>Teacher</th>

                                <th>Section</th>

                                <th>Room</th>

                                <th>Day</th>

                                <th>Schedule</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (count($subjects) > 0): ?>

                            <?php foreach ($subjects as $row): ?>

                                <?php
                                /*
                                 * Enable Scan only when the
                                 * schedule is for today.
                                 */
                                $scheduleDay = strtolower(trim($row['day']));
                                $isCorrectDay = ($scheduleDay === $currentDay);
                                ?>

                                <tr>

                                    <td>

                                        <?php if (!empty($row['subject_code'])): ?>

                                            <div class="subject-code">
                                                <?= e($row['subject_code']) ?>
                                            </div>

                                        <?php endif; ?>

                                        <div class="subject-name">
                                            <?= e($row['subject_name']) ?>
                                        </div>

                                    </td>


                                    <td>

                                        <div class="teacher-name">

                                            <i class="fa-solid fa-user-tie me-1 text-muted"></i>

                                            <?= e($row['teacher_name']) ?>

                                        </div>

                                    </td>


                                    <td>

                                        <span class="day-badge">

                                            <?= e($row['year_name']) ?>

                                            -

                                            <?= e($row['section_name']) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="room-badge">

                                            <i class="fa-solid fa-door-open"></i>

                                            <?= e($row['room'] ?: 'No room') ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="day-badge">

                                            <?= e($row['day']) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="schedule-text">

                                            <?= formatTime($row['start_time']) ?>

                                            -

                                            <?= formatTime($row['end_time']) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php if ($isCorrectDay): ?>

                                            <a
                                                href="scan_qr.php?schedule_id=<?= (int)$row['schedule_id'] ?>"
                                                class="scan-btn">

                                                <i class="fa-solid fa-qrcode"></i>

                                                Scan

                                            </a>

                                        <?php else: ?>

                                            <span class="scan-btn disabled-scan">

                                                <i class="fa-solid fa-ban"></i>

                                                Not Today

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="7">

                                    <div class="empty-state">

                                        <i class="fa-solid fa-book-open"></i>

                                        <h6>No Assigned Subjects</h6>

                                        <p>
                                            You currently have no assigned schedules.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        <!-- =================================================
             TEACHER ATTENDANCE MONITORING
             ================================================= -->

        <div class="section-card">

            <div class="section-header">

                <div>

                    <div class="section-title">
                        <i class="fa-solid fa-chart-column"></i>
                        Teacher Attendance Monitoring
                    </div>

                    <div class="section-subtitle">
                        Attendance records for your assigned schedules
                    </div>

                </div>

            </div>


            <div class="section-body">

                <div class="table-container">

                    <table class="table custom-table">

                        <thead>

                            <tr>

                                <th>Date</th>

                                <th>Teacher</th>

                                <th>Subject</th>

                                <th>Section</th>

                                <th>Room</th>

                                <th>Schedule</th>

                                <th>Time In</th>

                                <th>Time Out</th>

                                <th>Status</th>

                                <th>Remarks</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (count($teacherAttendance) > 0): ?>

                            <?php foreach ($teacherAttendance as $row): ?>

                                <tr>

                                    <!-- DATE -->

                                    <td>

                                        <div class="small fw-semibold">

                                            <?= e(date(
                                                'M d, Y',
                                                strtotime($row['attendance_date'])
                                            )) ?>

                                        </div>

                                    </td>


                                    <!-- TEACHER -->

                                    <td>

                                        <div class="teacher-name">

                                            <i class="fa-solid fa-user-tie me-1 text-muted"></i>

                                            <?= e($row['teacher_name']) ?>

                                        </div>

                                    </td>


                                    <!-- SUBJECT -->

                                    <td>

                                        <?php if (!empty($row['subject_code'])): ?>

                                            <div class="subject-code">

                                                <?= e($row['subject_code']) ?>

                                            </div>

                                        <?php endif; ?>

                                        <div class="subject-name">

                                            <?= e($row['subject_name']) ?>

                                        </div>

                                    </td>


                                    <!-- SECTION -->

                                    <td>

                                        <span class="day-badge">

                                            <?= e($row['year_name']) ?>

                                            -

                                            <?= e($row['section_name']) ?>

                                        </span>

                                    </td>


                                    <!-- ROOM -->

                                    <td>

                                        <span class="room-badge">

                                            <i class="fa-solid fa-door-open"></i>

                                            <?= e($row['room'] ?: 'No room') ?>

                                        </span>

                                    </td>


                                    <!-- SCHEDULE -->

                                    <td>

                                        <div>

                                            <span class="day-badge">

                                                <?= e($row['day']) ?>

                                            </span>

                                        </div>

                                        <div class="small text-muted mt-1">

                                            <?= formatTime($row['start_time']) ?>

                                            -

                                            <?= formatTime($row['end_time']) ?>

                                        </div>

                                    </td>


                                    <!-- TIME IN -->

                                    <td>

                                        <span class="schedule-text">

                                            <?= formatTime($row['time_in']) ?>

                                        </span>

                                    </td>


                                    <!-- TIME OUT -->

                                    <td>

                                        <span class="schedule-text">

                                            <?= formatTime($row['time_out']) ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php

                                        $statusClass = 'status-total';

                                        $status = $row['status'];

                                        if ($status === 'Present') {
                                            $statusClass = 'status-present';
                                        } elseif ($status === 'Late') {
                                            $statusClass = 'status-late';
                                        } elseif ($status === 'Absent') {
                                            $statusClass = 'status-absent';
                                        }

                                        ?>

                                        <span class="status-badge <?= $statusClass ?>">

                                            <?= e($status) ?>

                                        </span>

                                    </td>


                                    <!-- REMARKS -->

                                    <td>

                                        <form
                                            method="POST"
                                            id="remarks-form-<?= (int)$row['attendance_id'] ?>"
                                            class="remarks-form">

                                            <input
                                                type="hidden"
                                                name="attendance_id"
                                                value="<?= (int)$row['attendance_id'] ?>">

                                            <input
                                                type="text"
                                                name="remarks"
                                                class="form-control form-control-sm remarks-input"
                                                value="<?= e($row['remarks'] ?? '') ?>"
                                                placeholder="Add remarks...">

                                        </form>

                                    </td>


                                    <!-- SAVE -->

                                    <td>

                                        <button
                                            type="submit"
                                            name="update_remarks"
                                            class="save-remarks-btn"
                                            form="remarks-form-<?= (int)$row['attendance_id'] ?>">

                                            <i class="fa-solid fa-floppy-disk"></i>

                                            Save

                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="11">

                                    <div class="empty-state">

                                        <i class="fa-solid fa-chart-column"></i>

                                        <h6>No Attendance Data</h6>

                                        <p>
                                            No teacher attendance records are available yet.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


    </div>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>