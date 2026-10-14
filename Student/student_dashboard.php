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

function toSeconds($time)
{
    $p = array_pad(explode(':', (string)$time), 3, 0);
    return ((int)$p[0] * 3600) + ((int)$p[1] * 60) + (int)$p[2];
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

    // A QR attendance session is optional for automatic Absent.
    // If no session exists, session_id will be NULL.
    $session_id = $attendanceSession['id'] ?? null;

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
        'session_id' => $session_id,
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
   REPORT ABSENT / LEAVE ABSENT
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_absent_report'])) {

    $attendance_id = (int)($_POST['report_attendance_id'] ?? 0);
    $report_status = trim($_POST['report_status'] ?? '');
    $report_remarks = trim($_POST['report_remarks'] ?? '');

    $allowedStatuses = ['Absent', 'Leave Absent'];

    if (
        $attendance_id > 0 &&
        in_array($report_status, $allowedStatuses, true)
    ) {

        $stmt = $conn->prepare("
            UPDATE teacher_attendance
            SET
                status = :status,
                remarks = :remarks
            WHERE id = :attendance_id
              AND beader_id = :beader_id
        ");

        $stmt->execute([
            'status' => $report_status,
            'remarks' => $report_remarks !== '' ? $report_remarks : null,
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

        (
            SELECT ta1.id
            FROM teacher_attendance ta1
            WHERE ta1.schedule_id = s.id
              AND ta1.beader_id = :attendance_beader_id
              AND ta1.attendance_date = :attendance_date
            ORDER BY ta1.id DESC
            LIMIT 1
        ) AS attendance_id,

        (
            SELECT ta2.status
            FROM teacher_attendance ta2
            WHERE ta2.schedule_id = s.id
              AND ta2.beader_id = :attendance_beader_id_status
              AND ta2.attendance_date = :attendance_date_status
            ORDER BY ta2.id DESC
            LIMIT 1
        ) AS attendance_status,

        (
            SELECT ta3.remarks
            FROM teacher_attendance ta3
            WHERE ta3.schedule_id = s.id
              AND ta3.beader_id = :attendance_beader_id_remarks
              AND ta3.attendance_date = :attendance_date_remarks
            ORDER BY ta3.id DESC
            LIMIT 1
        ) AS attendance_remarks,

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
    'beader_id' => $student_id,

    'attendance_beader_id' => $student_id,
    'attendance_date' => $today,

    'attendance_beader_id_status' => $student_id,
    'attendance_date_status' => $today,

    'attendance_beader_id_remarks' => $student_id,
    'attendance_date_remarks' => $today
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

/* =========================================================
   ATTENDANCE SUMMARY (donut) + TODAY'S TIMELINE
   ========================================================= */

// Donut counts ("Leave Absent" counts as Absent)
$statusCounts = ['Present' => 0, 'Late' => 0, 'Absent' => 0];

foreach ($teacherAttendance as $att) {
    $s = $att['status'] === 'Leave Absent' ? 'Absent' : $att['status'];
    if (isset($statusCounts[$s])) {
        $statusCounts[$s]++;
    }
}

$statusTotal = array_sum($statusCounts);

// Today's classes, in time order
$todayClasses = array_values(array_filter($subjects, function ($r) use ($currentDay) {
    return strtolower(trim($r['day'])) === $currentDay;
}));

usort($todayClasses, function ($a, $b) {
    return strcmp($a['start_time'], $b['start_time']);
});

$nowSeconds = toSeconds($currentTime);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BeadleQR | Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        :root {
            --ink: #0f172a;
            --ink-2: #334155;
            --muted: #64748b;
            --line: #e5e7eb;
            --line-soft: #f1f5f9;
            --bg: #f6f7f9;
            --surface: #ffffff;

            --brand: #ea580c;
            --brand-dark: #c2410c;
            --brand-tint: #fff4ec;

            --ok-bg: #dcfce7;   --ok-fg: #166534;
            --warn-bg: #fef3c7; --warn-fg: #92400e;
            --bad-bg: #fee2e2;  --bad-fg: #991b1b;
            --neutral-bg: #f1f5f9; --neutral-fg: #475569;

            --radius: 10px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 2px;
        }

        /* =====================================================
           TOP NAVIGATION
           ===================================================== */

        .topnav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
        }

        .topnav-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 28px;
            height: 64px;
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 18px;
            color: var(--ink);
            text-decoration: none;
            letter-spacing: -.01em;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 16px;
        }

        .nav-links {
            display: flex;
            align-items: stretch;
            gap: 4px;
            height: 100%;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 14px;
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid transparent;
            transition: color .15s ease, border-color .15s ease;
        }

        .nav-links a:hover { color: var(--ink); }

        .nav-links a.active {
            color: var(--brand-dark);
            border-bottom-color: var(--brand);
        }

        .nav-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--ink);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 13px;
            font-weight: 700;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
        }

        .user-role {
            font-size: 12px;
            color: var(--muted);
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--ink-2);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .logout-link:hover {
            background: var(--bad-bg);
            border-color: #fecaca;
            color: var(--bad-fg);
        }

        /* =====================================================
           PAGE
           ===================================================== */

        .page {
            max-width: 1320px;
            margin: 0 auto;
            padding: 32px 28px 56px;
        }

        .page-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -.02em;
            margin: 0 0 4px;
        }

        .page-sub {
            color: var(--muted);
            margin: 0;
            font-size: 14px;
        }

        .today-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 999px;
            font-weight: 600;
            font-size: 13px;
            color: var(--ink-2);
        }

        .today-pill i { color: var(--brand); }

        /* =====================================================
           STAT STRIP
           ===================================================== */

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
        }

        .stat {
            padding: 20px 24px;
            border-right: 1px solid var(--line);
        }

        .stat:last-child { border-right: none; }

        .stat-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .stat-label i {
            color: var(--brand);
            width: 16px;
            text-align: center;
        }

        .stat-number {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.1;
        }

        /* =====================================================
           PANELS
           ===================================================== */

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            margin-top: 24px;
            overflow: hidden;
        }

        .panel-head {
            padding: 18px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .panel-title {
            font-size: 17px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -.01em;
        }

        .panel-sub {
            color: var(--muted);
            font-size: 13px;
            margin: 2px 0 0;
        }

        .count-pill {
            background: var(--neutral-bg);
            color: var(--neutral-fg);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .panel-body-pad { padding: 18px 24px; }

        .section-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            margin: 0 8px 8px 0;
            background: var(--brand-tint);
            border: 1px solid #fed7aa;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--brand-dark);
        }

        /* =====================================================
           TABLE
           ===================================================== */

        .table-wrap { overflow-x: auto; }

        .data-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            min-width: 820px;
        }

        .data-table thead th {
            background: #fafbfc;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            padding: 12px 20px;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
            text-align: left;
        }

        .data-table tbody td {
            padding: 14px 20px;
            vertical-align: middle;
            border-bottom: 1px solid var(--line-soft);
            color: var(--ink-2);
        }

        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: #fcfcfd; }

        .data-table tbody tr.is-editing {
            background: var(--brand-tint);
        }

        .subject-code {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            color: var(--brand-dark);
            background: var(--brand-tint);
            border-radius: 5px;
            padding: 2px 7px;
            margin-bottom: 3px;
        }

        .subject-name {
            font-weight: 700;
            color: var(--ink);
        }

        .teacher-name {
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
        }

        .teacher-name i { color: var(--muted); margin-right: 6px; }

        .nowrap { white-space: nowrap; }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            background: var(--neutral-bg);
            color: var(--neutral-fg);
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .tag-day {
            background: #eef2ff;
            color: #4338ca;
        }

        .muted-small {
            color: var(--muted);
            font-size: 12px;
        }

        /* =====================================================
           STATUS
           ===================================================== */

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-present { background: var(--ok-bg);   color: var(--ok-fg); }
        .status-late    { background: var(--warn-bg); color: var(--warn-fg); }
        .status-absent  { background: var(--bad-bg);  color: var(--bad-fg); }
        .status-total   { background: var(--neutral-bg); color: var(--neutral-fg); }

        /* =====================================================
           BUTTONS
           ===================================================== */

        .btn-app {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }

        .btn-primary-app { background: var(--brand); color: #fff; }
        .btn-primary-app:hover { background: var(--brand-dark); color: #fff; }

        .btn-ghost {
            background: var(--surface);
            border-color: var(--line);
            color: var(--ink-2);
        }
        .btn-ghost:hover { background: var(--line-soft); color: var(--ink); }

        .btn-danger-soft {
            background: var(--bad-bg);
            color: var(--bad-fg);
        }
        .btn-danger-soft:hover { background: #fecaca; color: var(--bad-fg); }

        .btn-disabled {
            background: var(--neutral-bg);
            color: #94a3b8;
            cursor: not-allowed;
            pointer-events: none;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* =====================================================
           REMARKS (edit / save)
           ===================================================== */

        .remarks-input {
            width: 100%;
            min-width: 220px;
            padding: 7px 11px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: transparent;
            font-family: inherit;
            font-size: 13px;
            color: var(--ink);
            text-overflow: ellipsis;
        }

        .remarks-input[readonly] { cursor: default; }
        .remarks-input[readonly]::placeholder { color: #94a3b8; }

        .remarks-input:not([readonly]) {
            background: var(--surface);
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, .12);
        }

        .row-actions { display: flex; gap: 6px; }
        .row-actions [data-role="editing"] { display: none; }
        tr.is-editing .row-actions [data-role="viewing"] { display: none; }
        tr.is-editing .row-actions [data-role="editing"] { display: inline-flex; }

        /* =====================================================
           ACTIVE SESSIONS
           ===================================================== */

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--bad-fg);
            font-size: 12px;
            font-weight: 700;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            animation: pulse 1.6s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .35; }
        }

        .session-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 24px;
            border-bottom: 1px solid var(--line-soft);
        }

        .session-row:last-child { border-bottom: none; }

        .session-meta {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .session-meta i { margin-right: 5px; }

        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .empty-state {
            padding: 48px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 32px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .empty-state h6 {
            color: var(--ink-2);
            font-weight: 700;
            margin-bottom: 2px;
        }

        .empty-state p { margin: 0; font-size: 13px; }

        /* =====================================================
           MODALS / TOAST
           ===================================================== */

        .modal-content {
            border: none;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .2);
            font-family: 'Manrope', sans-serif;
        }

        .modal-header { border-bottom: 1px solid var(--line); padding: 18px 22px; }
        .modal-body   { padding: 22px; }
        .modal-footer { border-top: 1px solid var(--line); padding: 14px 22px; gap: 8px; }
        .modal-title  { font-weight: 800; font-size: 18px; }

        .form-label { font-weight: 700; font-size: 13px; }

        .confirm-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--brand-tint);
            color: var(--brand);
            display: grid;
            place-items: center;
            font-size: 20px;
            margin: 0 auto 14px;
        }

        .toast-stack {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .app-toast {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 280px;
            max-width: 380px;
            padding: 14px 16px;
            background: var(--ink);
            color: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .25);
            font-size: 14px;
            font-weight: 600;
            animation: toast-in .25s ease-out;
        }

        .app-toast i { color: #4ade80; font-size: 18px; }
        .app-toast.info i { color: #93c5fd; }

        @keyframes toast-in {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* =====================================================
           OVERVIEW ROW (timeline + donut)
           ===================================================== */

        .overview-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            margin-top: 24px;
        }

        .overview-grid .panel {
            margin-top: 0;
            min-width: 0;
        }

        /* ---------- Today's timeline ---------- */

        .timeline {
            display: flex;
            gap: 14px;
            padding: 18px 24px;
            overflow-x: auto;
        }

        .tl-card {
            flex: 0 0 240px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 14px 16px;
            background: var(--surface);
            position: relative;
            transition: opacity .2s ease;
        }

        .tl-card.is-ongoing {
            border-color: var(--brand);
            background: var(--brand-tint);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, .12);
        }

        .tl-card.is-ended { opacity: .6; }

        .tl-time {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .tl-state {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--neutral-bg);
            color: var(--neutral-fg);
            margin-bottom: 8px;
        }

        .is-ongoing .tl-state { background: var(--brand); color: #fff; }
        .is-next .tl-state { background: var(--brand-tint); color: var(--brand-dark); }

        .tl-meta {
            color: var(--muted);
            font-size: 12px;
            margin: 4px 0 12px;
        }

        .tl-card .btn-app { padding: 5px 12px; }

        /* ---------- Donut ---------- */

        .donut-wrap {
            padding: 18px 24px 22px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .donut {
            position: relative;
            width: 130px;
            height: 130px;
            flex: none;
        }

        .donut svg { transform: rotate(-90deg); }

        .donut-center {
            position: absolute;
            inset: 0;
            display: grid;
            place-content: center;
            text-align: center;
        }

        .donut-center strong { font-size: 24px; font-weight: 800; line-height: 1; }
        .donut-center span { font-size: 11px; color: var(--muted); font-weight: 600; }

        .legend {
            list-style: none;
            margin: 0;
            padding: 0;
            font-size: 13px;
            font-weight: 600;
            flex: 1;
        }

        .legend li {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .legend li:last-child { margin-bottom: 0; }

        .legend i {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .legend b { margin-left: auto; padding-left: 14px; }

        /* ---------- Filters ---------- */

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            border-bottom: 1px solid var(--line);
            background: #fafbfc;
        }

        .search-box {
            position: relative;
            flex: 1 1 240px;
            max-width: 340px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 13px;
        }

        .search-box input,
        .date-filter {
            width: 100%;
            padding: 8px 12px 8px 34px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            background: var(--surface);
            color: var(--ink);
        }

        .date-filter { width: auto; padding-left: 12px; }

        .chip-group { display: flex; gap: 6px; flex-wrap: wrap; }

        .chip {
            border: 1px solid var(--line);
            background: var(--surface);
            border-radius: 999px;
            padding: 6px 14px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            color: var(--ink-2);
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }

        .chip:hover { background: var(--line-soft); }

        .chip.active {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .chip .n { opacity: .7; margin-left: 6px; }

        .filter-reset {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--brand-dark);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
        }

        .filter-reset:hover { text-decoration: underline; }

        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 992px) {
            .stat-strip { grid-template-columns: repeat(2, 1fr); }
            .stat:nth-child(2) { border-right: none; }
            .stat:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
            .overview-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .topnav-inner { padding: 0 16px; gap: 12px; }
            .page { padding: 22px 16px 40px; }
            .nav-links a span,
            .user-chip .user-text,
            .logout-link span { display: none; }
            .nav-links a { padding: 0 12px; }
            .page-title { font-size: 22px; }
            .session-row { flex-direction: column; align-items: flex-start; }
            .panel-head, .panel-body-pad, .session-row { padding-left: 16px; padding-right: 16px; }
            .timeline, .donut-wrap, .filter-bar { padding-left: 16px; padding-right: 16px; }
            .search-box { max-width: none; }
        }

        @media (max-width: 480px) {
            .stat-strip { grid-template-columns: 1fr; }
            .stat { border-right: none; border-bottom: 1px solid var(--line); }
            .stat:last-child { border-bottom: none; }
            .brand span { display: none; }
            .donut-wrap { flex-direction: column; align-items: flex-start; }
            .legend { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .live-dot, .app-toast { animation: none; }
            .tl-card, .chip { transition: none; }
        }

    </style>

</head>

<body>

<!-- =========================================================
     TOP NAVIGATION (replaces sidebar)
     ========================================================= -->

<header class="topnav">

    <div class="topnav-inner">

        <a href="student_dashboard.php" class="brand">
            <div class="brand-mark">
                <i class="fa-solid fa-qrcode"></i>
            </div>
            <span>BeadleQR</span>
        </a>

        <nav class="nav-links" aria-label="Main">

            <a href="student_dashboard.php" class="active" aria-current="page">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>

            <a href="scan_qr.php">
                <i class="fa-solid fa-qrcode"></i>
                <span>Scan QR</span>
            </a>

        </nav>

        <div class="nav-right">

            <div class="user-chip">

                <div class="user-avatar">
                    <?= e(strtoupper(mb_substr($beader_name, 0, 1))) ?>
                </div>

                <div class="user-text">
                    <div class="user-name"><?= e($beader_name) ?></div>
                    <div class="user-role">Student / Beadle</div>
                </div>

            </div>

            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </div>

    </div>

</header>


<main class="page">

    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="page-head">

        <div>
            <h1 class="page-title">Welcome back, <?= e($beader_name) ?></h1>
            <p class="page-sub">
                Scan teacher QR codes and keep track of attendance for your assigned classes.
            </p>
        </div>

        <div class="today-pill">
            <i class="fa-solid fa-calendar-day"></i>
            <?= e(date('l, F j, Y')) ?>
        </div>

    </div>


    <!-- =====================================================
         STATS
         ===================================================== -->

    <section class="stat-strip" aria-label="Summary">

        <div class="stat">
            <div class="stat-label">
                <i class="fa-solid fa-book-open"></i>
                Assigned subjects
            </div>
            <div class="stat-number"><?= $totalSubjects ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">
                <i class="fa-solid fa-clipboard-check"></i>
                Total attendance
            </div>
            <div class="stat-number"><?= $totalAttendance ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">
                <i class="fa-solid fa-calendar-day"></i>
                Today's attendance
            </div>
            <div class="stat-number"><?= $totalToday ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">
                <i class="fa-solid fa-circle-check"></i>
                Completed sessions
            </div>
            <div class="stat-number"><?= $totalCompleted ?></div>
        </div>

    </section>


    <!-- =====================================================
         OVERVIEW: TODAY'S TIMELINE + ATTENDANCE DONUT
         ===================================================== -->

    <div class="overview-grid">

        <!-- TODAY'S CLASSES -->
        <section class="panel">

            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Today's classes</h2>
                    <p class="panel-sub"><?= e(date('l')) ?> schedule, in time order</p>
                </div>
                <span class="count-pill"><?= count($todayClasses) ?> classes</span>
            </div>

            <?php if (count($todayClasses) > 0): ?>

                <div class="timeline" id="timeline">

                    <?php foreach ($todayClasses as $c):

                        $st = trim((string)($c['attendance_status'] ?? ''));
                        $recorded = in_array($st, ['Present', 'Late', 'Absent', 'Leave Absent'], true);

                        $badgeClass = 'status-total';
                        if ($st === 'Present') {
                            $badgeClass = 'status-present';
                        } elseif ($st === 'Late') {
                            $badgeClass = 'status-late';
                        } elseif ($st === 'Absent' || $st === 'Leave Absent') {
                            $badgeClass = 'status-absent';
                        }
                    ?>

                        <div class="tl-card"
                             data-start="<?= toSeconds($c['start_time']) ?>"
                             data-end="<?= toSeconds($c['end_time']) ?>">

                            <div class="tl-time">
                                <?= formatTime($c['start_time']) ?> - <?= formatTime($c['end_time']) ?>
                            </div>

                            <span class="tl-state" data-state>--</span>

                            <div>
                                <?php if (!empty($c['subject_code'])): ?>
                                    <div class="subject-code"><?= e($c['subject_code']) ?></div>
                                <?php endif; ?>
                                <div class="subject-name"><?= e($c['subject_name']) ?></div>
                            </div>

                            <div class="tl-meta">
                                <i class="fa-solid fa-user-tie"></i> <?= e($c['teacher_name']) ?><br>
                                <i class="fa-solid fa-door-open"></i> <?= e($c['room'] ?: 'No room') ?>
                            </div>

                            <?php if ($recorded): ?>

                                <span class="status-badge <?= $badgeClass ?>">
                                    <?= e($st) ?>
                                </span>

                            <?php else: ?>

                                <a href="scan_qr.php?schedule_id=<?= (int)$c['schedule_id'] ?>"
                                   class="btn-app btn-primary-app">
                                    <i class="fa-solid fa-qrcode"></i> Scan
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-solid fa-mug-hot"></i>
                    <h6>No classes today</h6>
                    <p>Check the schedule below for your upcoming classes.</p>
                </div>

            <?php endif; ?>

        </section>


        <!-- ATTENDANCE DONUT -->
        <section class="panel">

            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Attendance overview</h2>
                    <p class="panel-sub">All recorded classes</p>
                </div>
            </div>

            <?php
            $radius = 54;
            $circ = 2 * M_PI * $radius;
            $segments = [
                ['Present', '#22c55e'],
                ['Late',    '#f59e0b'],
                ['Absent',  '#ef4444'],
            ];
            $offset = 0;
            ?>

            <div class="donut-wrap">

                <div class="donut">

                    <svg width="130" height="130" viewBox="0 0 130 130" role="img"
                         aria-label="Attendance breakdown">

                        <circle cx="65" cy="65" r="<?= $radius ?>" fill="none"
                                stroke="#f1f5f9" stroke-width="16"></circle>

                        <?php if ($statusTotal > 0): ?>

                            <?php foreach ($segments as $seg):

                                $label = $seg[0];
                                $color = $seg[1];
                                $len = ($statusCounts[$label] / $statusTotal) * $circ;

                                if ($len <= 0) {
                                    continue;
                                }
                            ?>

                                <circle cx="65" cy="65" r="<?= $radius ?>" fill="none"
                                        stroke="<?= $color ?>" stroke-width="16"
                                        stroke-dasharray="<?= round($len, 2) ?> <?= round($circ - $len, 2) ?>"
                                        stroke-dashoffset="<?= round(-$offset, 2) ?>"></circle>

                                <?php $offset += $len; ?>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </svg>

                    <div class="donut-center">
                        <strong>
                            <?= $statusTotal > 0
                                ? round(($statusCounts['Present'] / $statusTotal) * 100) . '%'
                                : '--' ?>
                        </strong>
                        <span>Present</span>
                    </div>

                </div>

                <ul class="legend">
                    <?php foreach ($segments as $seg): ?>
                        <li>
                            <i style="background:<?= $seg[1] ?>"></i>
                            <?= e($seg[0]) ?>
                            <b><?= (int)$statusCounts[$seg[0]] ?></b>
                        </li>
                    <?php endforeach; ?>
                </ul>

            </div>

        </section>

    </div>


    <!-- =====================================================
         ASSIGNED SECTIONS
         ===================================================== -->

    <section class="panel">

        <div class="panel-head">
            <div>
                <h2 class="panel-title">Assigned sections</h2>
                <p class="panel-sub">Sections currently assigned to you</p>
            </div>
        </div>

        <div class="panel-body-pad">

            <?php if (count($assignedSections) > 0): ?>

                <?php foreach ($assignedSections as $section): ?>

                    <span class="section-chip">
                        <i class="fa-solid fa-users"></i>
                        <?= e($section['year_name']) ?> - <?= e($section['section_name']) ?>
                    </span>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="muted-small">No assigned sections.</div>

            <?php endif; ?>

        </div>

    </section>


    <!-- =====================================================
         ACTIVE ATTENDANCE SESSIONS
         ===================================================== -->

    <?php if (count($activeSessions) > 0): ?>

        <section class="panel">

            <div class="panel-head">

                <div>
                    <h2 class="panel-title">Active attendance sessions</h2>
                    <p class="panel-sub">Sessions waiting for your scan</p>
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

                    <div class="session-row">

                        <div>

                            <div class="subject-code">
                                <?= e($session['subject_code']) ?>
                            </div>

                            <div class="subject-name">
                                <?= e($session['subject_name']) ?>
                            </div>

                            <div class="session-meta">

                                <i class="fa-solid fa-user-tie"></i>
                                <?= e($session['teacher_name']) ?>

                                &nbsp;&nbsp;

                                <i class="fa-solid fa-clock"></i>
                                <?= formatTime($session['start_time']) ?>
                                -
                                <?= formatTime($session['end_time']) ?>

                                &nbsp;&nbsp;

                                <i class="fa-solid fa-door-open"></i>
                                <?= e($session['room'] ?: 'No room') ?>

                            </div>

                        </div>

                        <div>

                            <?php if ($isCorrectDay): ?>

                                <a
                                    href="scan_qr.php?schedule_id=<?= (int)$session['schedule_id'] ?>"
                                    class="btn-app btn-primary-app">

                                    <i class="fa-solid fa-qrcode"></i>
                                    Scan QR

                                </a>

                            <?php else: ?>

                                <span class="btn-app btn-disabled">

                                    <i class="fa-solid fa-ban"></i>
                                    Not today

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         ASSIGNED SUBJECTS
         ===================================================== -->

    <section class="panel">

        <div class="panel-head">

            <div>
                <h2 class="panel-title">Assigned subjects</h2>
                <p class="panel-sub">Your assigned class schedules</p>
            </div>

            <span class="count-pill"><?= $totalSubjects ?> subjects</span>

        </div>

        <div class="table-wrap">

            <table class="data-table">

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
                                    <div class="subject-code"><?= e($row['subject_code']) ?></div>
                                <?php endif; ?>
                                <div class="subject-name"><?= e($row['subject_name']) ?></div>
                            </td>

                            <td>
                                <div class="teacher-name">
                                    <i class="fa-solid fa-user-tie"></i><?= e($row['teacher_name']) ?>
                                </div>
                            </td>

                            <td>
                                <span class="tag">
                                    <?= e($row['year_name']) ?> - <?= e($row['section_name']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="tag">
                                    <i class="fa-solid fa-door-open"></i>
                                    <?= e($row['room'] ?: 'No room') ?>
                                </span>
                            </td>

                            <td>
                                <span class="tag tag-day"><?= e($row['day']) ?></span>
                            </td>

                            <td class="nowrap">
                                <?= formatTime($row['start_time']) ?>
                                -
                                <?= formatTime($row['end_time']) ?>
                            </td>

                            <td>

                                <div class="action-buttons">

                                    <?php if ($isCorrectDay): ?>

                                        <a
                                            href="scan_qr.php?schedule_id=<?= (int)$row['schedule_id'] ?>"
                                            class="btn-app btn-primary-app">

                                            <i class="fa-solid fa-qrcode"></i>
                                            Scan

                                        </a>

                                    <?php else: ?>

                                        <span class="btn-app btn-disabled">

                                            <i class="fa-solid fa-ban"></i>
                                            Not today

                                        </span>

                                    <?php endif; ?>


                                    <?php
                                    $attendanceStatus = trim((string)($row['attendance_status'] ?? ''));
                                    $attendanceId = (int)($row['attendance_id'] ?? 0);

                                    $showReportButton =
                                        $attendanceId > 0
                                        && in_array(
                                            $attendanceStatus,
                                            ['Absent', 'Leave Absent'],
                                            true
                                        );
                                    ?>

                                    <?php if ($showReportButton): ?>

                                        <button
                                            type="button"
                                            class="btn-app btn-danger-soft"
                                            data-bs-toggle="modal"
                                            data-bs-target="#absentReportModal"
                                            data-attendance-id="<?= $attendanceId ?>"
                                            data-teacher="<?= e($row['teacher_name']) ?>"
                                            data-subject="<?= e($row['subject_name']) ?>"
                                            data-status="<?= e($attendanceStatus) ?>"
                                            data-remarks="<?= e($row['attendance_remarks'] ?? '') ?>">

                                            <i class="fa-solid fa-file-pen"></i>
                                            Report

                                        </button>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fa-solid fa-book-open"></i>
                                <h6>No assigned subjects</h6>
                                <p>You currently have no assigned schedules.</p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


    <!-- =====================================================
         TEACHER ATTENDANCE MONITORING
         ===================================================== -->

    <section class="panel">

        <div class="panel-head">
            <div>
                <h2 class="panel-title">Teacher attendance monitoring</h2>
                <p class="panel-sub">Attendance records for your assigned schedules</p>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">

            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                    type="search"
                    id="filterSearch"
                    placeholder="Search teacher or subject..."
                    aria-label="Search teacher or subject">
            </div>

            <div class="chip-group" id="statusChips" role="group" aria-label="Filter by status">
                <button type="button" class="chip active" data-status="all">All<span class="n"><?= $statusTotal ?></span></button>
                <button type="button" class="chip" data-status="present">Present<span class="n"><?= $statusCounts['Present'] ?></span></button>
                <button type="button" class="chip" data-status="late">Late<span class="n"><?= $statusCounts['Late'] ?></span></button>
                <button type="button" class="chip" data-status="absent">Absent<span class="n"><?= $statusCounts['Absent'] ?></span></button>
            </div>

            <input type="date" id="filterDate" class="date-filter" aria-label="Filter by date">

            <button type="button" class="filter-reset" id="filterReset">Reset</button>

        </div>

        <div class="table-wrap">

            <table class="data-table" style="min-width: 1180px;">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Teacher</th>
                        <th>Subject</th>
                        <th>Section</th>
                        <th>Room</th>
                        <th>Schedule</th>
                        <th>Time in</th>
                        <th>Time out</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($teacherAttendance) > 0): ?>

                    <?php foreach ($teacherAttendance as $row): ?>

                        <?php
                        $rowStatus = $row['status'] === 'Leave Absent'
                            ? 'absent'
                            : strtolower((string)$row['status']);

                        $rowSearch = mb_strtolower(
                            $row['teacher_name'] . ' ' .
                            $row['subject_code'] . ' ' .
                            $row['subject_name']
                        );
                        ?>

                        <tr
                            data-row
                            data-status="<?= e($rowStatus) ?>"
                            data-date="<?= e(date('Y-m-d', strtotime($row['attendance_date']))) ?>"
                            data-search="<?= e($rowSearch) ?>">

                            <!-- DATE -->
                            <td class="nowrap" style="font-weight:600;color:var(--ink);">
                                <?= e(date('M d, Y', strtotime($row['attendance_date']))) ?>
                            </td>

                            <!-- TEACHER -->
                            <td>
                                <div class="teacher-name">
                                    <i class="fa-solid fa-user-tie"></i><?= e($row['teacher_name']) ?>
                                </div>
                            </td>

                            <!-- SUBJECT -->
                            <td>
                                <?php if (!empty($row['subject_code'])): ?>
                                    <div class="subject-code"><?= e($row['subject_code']) ?></div>
                                <?php endif; ?>
                                <div class="subject-name"><?= e($row['subject_name']) ?></div>
                            </td>

                            <!-- SECTION -->
                            <td>
                                <span class="tag">
                                    <?= e($row['year_name']) ?> - <?= e($row['section_name']) ?>
                                </span>
                            </td>

                            <!-- ROOM -->
                            <td>
                                <span class="tag">
                                    <i class="fa-solid fa-door-open"></i>
                                    <?= e($row['room'] ?: 'No room') ?>
                                </span>
                            </td>

                            <!-- SCHEDULE -->
                            <td class="nowrap">
                                <span class="tag tag-day"><?= e($row['day']) ?></span>
                                <div class="muted-small mt-1">
                                    <?= formatTime($row['start_time']) ?>
                                    -
                                    <?= formatTime($row['end_time']) ?>
                                </div>
                            </td>

                            <!-- TIME IN -->
                            <td class="nowrap"><?= formatTime($row['time_in']) ?></td>

                            <!-- TIME OUT -->
                            <td class="nowrap"><?= formatTime($row['time_out']) ?></td>

                            <!-- STATUS -->
                            <td>
                                <?php

                                $statusClass = 'status-total';

                                $status = $row['status'];

                                if ($status === 'Present') {
                                    $statusClass = 'status-present';
                                } elseif ($status === 'Late') {
                                    $statusClass = 'status-late';
                                } elseif (
                                    $status === 'Absent' ||
                                    $status === 'Leave Absent'
                                ) {
                                    $statusClass = 'status-absent';
                                }

                                ?>

                                <span class="status-badge <?= $statusClass ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <!-- REMARKS (read-only until Edit is clicked) -->
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
                                        type="hidden"
                                        name="update_remarks"
                                        value="1">

                                    <input
                                        type="text"
                                        name="remarks"
                                        class="remarks-input"
                                        value="<?= e($row['remarks'] ?? '') ?>"
                                        data-original="<?= e($row['remarks'] ?? '') ?>"
                                        placeholder="No remarks"
                                        aria-label="Remarks"
                                        readonly>

                                </form>

                            </td>

                            <!-- EDIT / SAVE / CANCEL -->
                            <td>

                                <div class="row-actions">

                                    <button
                                        type="button"
                                        class="btn-app btn-ghost"
                                        data-role="viewing"
                                        data-action="edit">

                                        <i class="fa-solid fa-pen"></i>
                                        Edit

                                    </button>

                                    <button
                                        type="button"
                                        class="btn-app btn-primary-app"
                                        data-role="editing"
                                        data-action="save">

                                        <i class="fa-solid fa-floppy-disk"></i>
                                        Save

                                    </button>

                                    <button
                                        type="button"
                                        class="btn-app btn-ghost"
                                        data-role="editing"
                                        data-action="cancel">

                                        Cancel

                                    </button>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <!-- Shown by the filters when nothing matches -->
                    <tr id="noResults" hidden>
                        <td colspan="11">
                            <div class="empty-state">
                                <i class="fa-solid fa-filter-circle-xmark"></i>
                                <h6>No matching records</h6>
                                <p>Try a different search or status, or reset the filters.</p>
                            </div>
                        </td>
                    </tr>

                <?php else: ?>

                    <tr>
                        <td colspan="11">
                            <div class="empty-state">
                                <i class="fa-solid fa-chart-column"></i>
                                <h6>No attendance data</h6>
                                <p>No teacher attendance records are available yet.</p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<!-- =========================================================
     ABSENT REPORT MODAL
     ========================================================= -->

<div
    class="modal fade"
    id="absentReportModal"
    tabindex="-1"
    aria-labelledby="absentReportModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h5 class="modal-title" id="absentReportModalLabel">
                        Attendance report
                    </h5>
                    <small class="text-muted">Report the teacher's absence</small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close">
                </button>

            </div>

            <form method="POST" id="absentReportForm">

                <div class="modal-body">

                    <input type="hidden" name="submit_absent_report" value="1">

                    <input
                        type="hidden"
                        name="report_attendance_id"
                        id="reportAttendanceId">

                    <div class="mb-3">
                        <label class="form-label">Teacher</label>
                        <input type="text" class="form-control" id="reportTeacher" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" id="reportSubject" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attendance status</label>
                        <select
                            name="report_status"
                            id="reportStatus"
                            class="form-select"
                            required>
                            <option value="Absent">Absent</option>
                            <option value="Leave Absent">Leave Absent</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Remarks</label>
                        <textarea
                            name="report_remarks"
                            id="reportRemarks"
                            class="form-control"
                            rows="4"
                            placeholder="Enter additional remarks..."></textarea>
                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn-app btn-ghost" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn-app btn-primary-app">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save report
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     CONFIRMATION MODAL
     ========================================================= -->

<div
    class="modal fade"
    id="confirmModal"
    tabindex="-1"
    aria-labelledby="confirmTitle"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-sm">

        <div class="modal-content">

            <div class="modal-body text-center pb-2">

                <div class="confirm-icon">
                    <i class="fa-solid fa-circle-question"></i>
                </div>

                <h5 class="fw-bold mb-1" id="confirmTitle">Save changes?</h5>

                <p class="text-muted mb-0" id="confirmMessage" style="font-size:13px;">
                    Please confirm to continue.
                </p>

            </div>

            <div class="modal-footer justify-content-center border-0 pt-3">

                <button type="button" class="btn-app btn-ghost" data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="button" class="btn-app btn-primary-app" id="confirmYes">
                    Yes, save
                </button>

            </div>

        </div>

    </div>

</div>


<!-- TOASTS -->
<div class="toast-stack" id="toastStack" aria-live="polite"></div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ---------------------------------------------------------
       Toast
       --------------------------------------------------------- */

    const toastStack = document.getElementById('toastStack');

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'app-toast' + (type === 'info' ? ' info' : '');
        toast.innerHTML =
            '<i class="fa-solid ' +
            (type === 'info' ? 'fa-circle-info' : 'fa-circle-check') +
            '"></i><span></span>';
        toast.querySelector('span').textContent = message;
        toastStack.appendChild(toast);

        setTimeout(function () {
            toast.style.transition = 'opacity .3s ease';
            toast.style.opacity = '0';
            setTimeout(function () { toast.remove(); }, 300);
        }, 3500);
    }

    // Show success message after the page reloads from a save.
    try {
        const pending = sessionStorage.getItem('beadleToast');
        if (pending) {
            sessionStorage.removeItem('beadleToast');
            showToast(pending);
        }
    } catch (err) {}

    function queueToast(message) {
        try { sessionStorage.setItem('beadleToast', message); } catch (err) {}
    }

    /* ---------------------------------------------------------
       Confirmation modal
       --------------------------------------------------------- */

    const confirmEl = document.getElementById('confirmModal');
    const confirmModal = new bootstrap.Modal(confirmEl);
    const confirmYes = document.getElementById('confirmYes');
    let confirmCallback = null;

    function askConfirm(options, onConfirm) {
        document.getElementById('confirmTitle').textContent = options.title;
        document.getElementById('confirmMessage').textContent = options.message;
        confirmYes.textContent = options.confirmText || 'Yes, save';
        confirmCallback = onConfirm;
        confirmModal.show();
    }

    confirmYes.addEventListener('click', function () {
        const callback = confirmCallback;
        confirmCallback = null;
        confirmModal.hide();
        if (callback) { callback(); }
    });

    /* ---------------------------------------------------------
       Remarks: Edit -> Save (with confirmation)
       --------------------------------------------------------- */

    function setEditing(row, editing) {
        const input = row.querySelector('.remarks-input');
        row.classList.toggle('is-editing', editing);
        input.readOnly = !editing;
        if (editing) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    }

    function saveRow(row) {
        const input = row.querySelector('.remarks-input');
        const form = row.querySelector('form.remarks-form');

        if (input.value.trim() === input.dataset.original.trim()) {
            setEditing(row, false);
            showToast('No changes to save.', 'info');
            return;
        }

        askConfirm(
            {
                title: 'Save remarks?',
                message: 'Your changes to this attendance record will be saved.',
                confirmText: 'Yes, save'
            },
            function () {
                queueToast('Remarks saved successfully.');
                form.submit();
            }
        );
    }

    document.querySelectorAll('tr[data-row]').forEach(function (row) {

        const input = row.querySelector('.remarks-input');

        row.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const action = btn.getAttribute('data-action');

                if (action === 'edit') {
                    setEditing(row, true);
                } else if (action === 'cancel') {
                    input.value = input.dataset.original;
                    setEditing(row, false);
                } else if (action === 'save') {
                    saveRow(row);
                }
            });
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (!input.readOnly) { saveRow(row); }
            } else if (event.key === 'Escape' && !input.readOnly) {
                input.value = input.dataset.original;
                setEditing(row, false);
            }
        });

        row.querySelector('form.remarks-form').addEventListener('submit', function (event) {
            event.preventDefault();
        });
    });

    /* ---------------------------------------------------------
       Absent report modal
       --------------------------------------------------------- */

    const reportModal = document.getElementById('absentReportModal');
    const reportForm = document.getElementById('absentReportForm');

    reportModal.addEventListener('show.bs.modal', function (event) {

        const button = event.relatedTarget;

        if (!button) {
            return;
        }

        document.getElementById('reportAttendanceId').value =
            button.getAttribute('data-attendance-id') || '';

        document.getElementById('reportTeacher').value =
            button.getAttribute('data-teacher') || '';

        document.getElementById('reportSubject').value =
            button.getAttribute('data-subject') || '';

        document.getElementById('reportRemarks').value =
            button.getAttribute('data-remarks') || '';

        document.getElementById('reportStatus').value =
            button.getAttribute('data-status') === 'Leave Absent'
                ? 'Leave Absent'
                : 'Absent';
    });

    // Confirm before the report is saved.
    reportForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const reportModalInstance = bootstrap.Modal.getInstance(reportModal);
        reportModalInstance.hide();

        reportModal.addEventListener('hidden.bs.modal', function openConfirm() {
            reportModal.removeEventListener('hidden.bs.modal', openConfirm);

            askConfirm(
                {
                    title: 'Save report?',
                    message: 'The attendance status and remarks will be updated.',
                    confirmText: 'Yes, save'
                },
                function () {
                    queueToast('Report saved successfully.');
                    reportForm.submit();
                }
            );
        });
    });

    /* ---------------------------------------------------------
       Today's timeline (Ongoing / Starts in ... / Ended)
       Uses the server's Manila time as the starting point, so it
       stays correct even if the device clock or timezone differs.
       --------------------------------------------------------- */

    const timeline = document.getElementById('timeline');

    if (timeline) {

        const serverNow = <?= (int)$nowSeconds ?>;
        const loadedAt = Date.now();
        const cards = Array.from(timeline.querySelectorAll('.tl-card'));

        function currentSeconds() {
            return serverNow + Math.floor((Date.now() - loadedAt) / 1000);
        }

        function formatGap(totalMinutes) {
            if (totalMinutes < 60) {
                return totalMinutes + ' min';
            }
            const h = Math.floor(totalMinutes / 60);
            const m = totalMinutes % 60;
            return h + 'h' + (m ? ' ' + m + 'm' : '');
        }

        function updateTimeline() {

            const now = currentSeconds();
            let nextFound = false;

            cards.forEach(function (card) {

                const start = parseInt(card.dataset.start, 10);
                const end = parseInt(card.dataset.end, 10);
                const label = card.querySelector('[data-state]');

                card.classList.remove('is-ongoing', 'is-ended', 'is-next');

                if (now >= end) {
                    card.classList.add('is-ended');
                    label.textContent = 'Ended';
                } else if (now >= start) {
                    card.classList.add('is-ongoing');
                    label.textContent = 'Ongoing · ' + formatGap(Math.ceil((end - now) / 60)) + ' left';
                } else {
                    if (!nextFound) {
                        card.classList.add('is-next');
                        nextFound = true;
                    }
                    label.textContent = 'Starts in ' + formatGap(Math.ceil((start - now) / 60));
                }
            });
        }

        updateTimeline();
        setInterval(updateTimeline, 30000);

        // Bring the ongoing (or next) class into view.
        const focusCard = timeline.querySelector('.is-ongoing') || timeline.querySelector('.is-next');

        if (focusCard) {
            timeline.scrollLeft = Math.max(0, focusCard.offsetLeft - 24);
        }
    }

    /* ---------------------------------------------------------
       Attendance table filters: search + status chips + date
       --------------------------------------------------------- */

    const filterSearch = document.getElementById('filterSearch');
    const filterDate = document.getElementById('filterDate');
    const filterReset = document.getElementById('filterReset');
    const statusChips = document.getElementById('statusChips');
    const noResults = document.getElementById('noResults');
    const dataRows = Array.from(document.querySelectorAll('tr[data-row]'));

    let activeStatus = 'all';

    function applyFilters() {

        const query = filterSearch.value.trim().toLowerCase();
        const date = filterDate.value;
        let visible = 0;

        dataRows.forEach(function (row) {

            const matchesSearch = query === '' || row.dataset.search.indexOf(query) !== -1;
            const matchesStatus = activeStatus === 'all' || row.dataset.status === activeStatus;
            const matchesDate = date === '' || row.dataset.date === date;

            const show = matchesSearch && matchesStatus && matchesDate;

            row.hidden = !show;

            if (show) { visible++; }
        });

        if (noResults) {
            noResults.hidden = visible !== 0;
        }
    }

    if (filterSearch && statusChips) {

        filterSearch.addEventListener('input', applyFilters);
        filterDate.addEventListener('change', applyFilters);

        statusChips.addEventListener('click', function (event) {

            const chip = event.target.closest('.chip');

            if (!chip) {
                return;
            }

            statusChips.querySelectorAll('.chip').forEach(function (c) {
                c.classList.remove('active');
            });

            chip.classList.add('active');
            activeStatus = chip.dataset.status;
            applyFilters();
        });

        filterReset.addEventListener('click', function () {

            filterSearch.value = '';
            filterDate.value = '';
            activeStatus = 'all';

            statusChips.querySelectorAll('.chip').forEach(function (c) {
                c.classList.toggle('active', c.dataset.status === 'all');
            });

            applyFilters();
        });
    }

});
</script>

</body>
</html>
