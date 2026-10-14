<?php
session_start();
require "../db.php";

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getInitials($name)
{
    $name = trim((string)$name);

    if ($name === '') {
        return '??';
    }

    $parts = preg_split('/\s+/', $name);

    if (count($parts) === 1) {
        return strtoupper(substr($parts[0], 0, 2));
    }

    return strtoupper(
        substr($parts[0], 0, 1) .
        substr($parts[count($parts) - 1], 0, 1)
    );
}

/*
|--------------------------------------------------------------------------
| ADD / EDIT SCHEDULE
|--------------------------------------------------------------------------
*/
if (isset($_POST['save_schedule'])) {

    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

    $teacher_id = !empty($_POST['teacher_id'])
        ? (int)$_POST['teacher_id']
        : null;

    $subject_id = !empty($_POST['subject_id'])
        ? (int)$_POST['subject_id']
        : null;

    $room = trim($_POST['room'] ?? '');

    $day = trim($_POST['day'] ?? '');

    $start_time = trim($_POST['start_time'] ?? '');

    $end_time = trim($_POST['end_time'] ?? '');

    $year_level_id = !empty($_POST['year_level_id'])
        ? (int)$_POST['year_level_id']
        : null;

    $section_id = !empty($_POST['section_id'])
        ? (int)$_POST['section_id']
        : null;


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !$teacher_id ||
        !$subject_id ||
        !$day ||
        !$start_time ||
        !$end_time ||
        !$year_level_id ||
        !$section_id
    ) {

        $message = "
            <div class='alert-glass error'>
                All required fields must be filled.
            </div>
        ";

    } elseif ($start_time >= $end_time) {

        $message = "
            <div class='alert-glass error'>
                End time must be after start time.
            </div>
        ";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | VERIFY SECTION BELONGS TO YEAR LEVEL
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT id
                FROM sections
                WHERE id = ?
                AND year_level_id = ?
            ");

            $stmt->execute([
                $section_id,
                $year_level_id
            ]);

            if (!$stmt->fetch()) {

                $message = "
                    <div class='alert-glass error'>
                        The selected section does not belong to the selected year level.
                    </div>
                ";

            } else {

                /*
                |--------------------------------------------------------------------------
                | VERIFY SUBJECT BELONGS TO YEAR LEVEL
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM subjects
                    WHERE id = ?
                    AND (
                        year_level_id = ?
                        OR year_level_id IS NULL
                    )
                ");

                $stmt->execute([
                    $subject_id,
                    $year_level_id
                ]);

                if (!$stmt->fetch()) {

                    $message = "
                        <div class='alert-glass error'>
                            The selected subject does not belong to the selected year level.
                        </div>
                    ";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK TEACHER CONFLICT
                    |--------------------------------------------------------------------------
                    */

                    $teacherConflictSql = "
                        SELECT
                            s.id,
                            s.start_time,
                            s.end_time,
                            sub.subject_code,
                            sec.section_name
                        FROM schedules s
                        JOIN subjects sub
                            ON sub.id = s.subject_id
                        JOIN sections sec
                            ON sec.id = s.section_id
                        WHERE s.teacher_id = ?
                        AND s.day = ?
                        AND s.start_time < ?
                        AND s.end_time > ?
                    ";

                    $teacherConflictParams = [
                        $teacher_id,
                        $day,
                        $end_time,
                        $start_time
                    ];

                    if ($id) {
                        $teacherConflictSql .= " AND s.id != ?";
                        $teacherConflictParams[] = $id;
                    }

                    $stmt = $conn->prepare($teacherConflictSql);
                    $stmt->execute($teacherConflictParams);

                    $teacherConflict = $stmt->fetch(PDO::FETCH_ASSOC);


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK SECTION CONFLICT
                    |--------------------------------------------------------------------------
                    */

                    $sectionConflict = null;

                    if (!$teacherConflict) {

                        $sectionConflictSql = "
                            SELECT
                                s.id,
                                s.start_time,
                                s.end_time,
                                sub.subject_code,
                                t.fullname AS teacher_name
                            FROM schedules s
                            JOIN subjects sub
                                ON sub.id = s.subject_id
                            JOIN users t
                                ON t.id = s.teacher_id
                            WHERE s.section_id = ?
                            AND s.day = ?
                            AND s.start_time < ?
                            AND s.end_time > ?
                        ";

                        $sectionConflictParams = [
                            $section_id,
                            $day,
                            $end_time,
                            $start_time
                        ];

                        if ($id) {
                            $sectionConflictSql .= " AND s.id != ?";
                            $sectionConflictParams[] = $id;
                        }

                        $stmt = $conn->prepare($sectionConflictSql);
                        $stmt->execute($sectionConflictParams);

                        $sectionConflict = $stmt->fetch(PDO::FETCH_ASSOC);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | HANDLE CONFLICTS
                    |--------------------------------------------------------------------------
                    */

                    if ($teacherConflict) {

                        $message = "
                            <div class='alert-glass error'>
                                <strong>Teacher schedule conflict.</strong><br>
                                {$teacherConflict['subject_code']}
                                ({$teacherConflict['section_name']})
                                is already scheduled for this teacher on
                                {$day} from
                                " . date('h:i A', strtotime($teacherConflict['start_time'])) . "
                                -
                                " . date('h:i A', strtotime($teacherConflict['end_time'])) . ".
                            </div>
                        ";

                    } elseif ($sectionConflict) {

                        $message = "
                            <div class='alert-glass error'>
                                <strong>Section schedule conflict.</strong><br>
                                This section already has
                                {$sectionConflict['subject_code']}
                                scheduled with
                                {$sectionConflict['teacher_name']}
                                on {$day} from
                                " . date('h:i A', strtotime($sectionConflict['start_time'])) . "
                                -
                                " . date('h:i A', strtotime($sectionConflict['end_time'])) . ".
                            </div>
                        ";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | SAVE SCHEDULE
                        |--------------------------------------------------------------------------
                        */

                        if ($id) {

                            $stmt = $conn->prepare("
                                UPDATE schedules
                                SET
                                    teacher_id = ?,
                                    subject_id = ?,
                                    room = ?,
                                    day = ?,
                                    start_time = ?,
                                    end_time = ?,
                                    year_level_id = ?,
                                    section_id = ?
                                WHERE id = ?
                            ");

                            $stmt->execute([
                                $teacher_id,
                                $subject_id,
                                $room !== '' ? $room : null,
                                $day,
                                $start_time,
                                $end_time,
                                $year_level_id,
                                $section_id,
                                $id
                            ]);

                            $message = "
                                <div class='alert-glass success'>
                                    Schedule updated successfully.
                                </div>
                            ";

                        } else {

                            $stmt = $conn->prepare("
                                INSERT INTO schedules
                                (
                                    teacher_id,
                                    subject_id,
                                    room,
                                    day,
                                    start_time,
                                    end_time,
                                    year_level_id,
                                    section_id
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $stmt->execute([
                                $teacher_id,
                                $subject_id,
                                $room !== '' ? $room : null,
                                $day,
                                $start_time,
                                $end_time,
                                $year_level_id,
                                $section_id
                            ]);

                            $message = "
                                <div class='alert-glass success'>
                                    Schedule created successfully.
                                </div>
                            ";
                        }
                    }
                }
            }

        } catch (PDOException $e) {

            $message = "
                <div class='alert-glass error'>
                    Database error: " . e($e->getMessage()) . "
                </div>
            ";
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE SCHEDULE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $deleteId = (int)$_GET['delete'];

    try {

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM attendance_sessions
            WHERE schedule_id = ?
        ");

        $stmt->execute([$deleteId]);

        $sessionCount = (int)$stmt->fetchColumn();

        if ($sessionCount > 0) {

            $message = "
                <div class='alert-glass error'>
                    Cannot delete this schedule because attendance sessions
                    are already attached to it.
                </div>
            ";

        } else {

            $stmt = $conn->prepare("
                DELETE FROM schedules
                WHERE id = ?
            ");

            $stmt->execute([$deleteId]);

            $message = "
                <div class='alert-glass success'>
                    Schedule deleted successfully.
                </div>
            ";
        }

    } catch (PDOException $e) {

        $message = "
            <div class='alert-glass error'>
                Cannot delete schedule. It may have attendance records attached.
            </div>
        ";
    }
}


/*
|--------------------------------------------------------------------------
| CREATE / REFRESH TODAY'S QR SESSION
|--------------------------------------------------------------------------
*/
if (isset($_GET['refresh_qr']) && is_numeric($_GET['refresh_qr'])) {

    $scheduleId = (int)$_GET['refresh_qr'];

    try {

        $stmt = $conn->prepare("
            SELECT
                s.id,
                s.section_id,
                s.year_level_id,
                sec.section_name
            FROM schedules s
            JOIN sections sec
                ON sec.id = s.section_id
            WHERE s.id = ?
        ");

        $stmt->execute([$scheduleId]);

        $scheduleInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$scheduleInfo) {

            $message = "
                <div class='alert-glass error'>
                    Schedule not found.
                </div>
            ";

        } else {

            $stmt = $conn->prepare("
                SELECT
                    ba.beader_id,
                    u.fullname AS beader_name
                FROM beader_assignments ba
                JOIN users u
                    ON u.id = ba.beader_id
                WHERE ba.section_id = ?
                AND ba.year_level_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $scheduleInfo['section_id'],
                $scheduleInfo['year_level_id']
            ]);

            $beader = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$beader) {

                $message = "
                    <div class='alert-glass error'>
                        No Beader is assigned to this section yet.
                        Please assign a Beader first before generating a QR code.
                    </div>
                ";

            } else {

                $stmt = $conn->prepare("
                    UPDATE attendance_sessions
                    SET
                        status = 'cancelled',
                        ended_at = NOW()
                    WHERE schedule_id = ?
                    AND session_date = CURRENT_DATE
                    AND status = 'active'
                ");

                $stmt->execute([$scheduleId]);

                $qr_token = bin2hex(random_bytes(32));

                $stmt = $conn->prepare("
                    INSERT INTO attendance_sessions
                    (
                        schedule_id,
                        beader_id,
                        session_date,
                        qr_token,
                        qr_expires_at,
                        started_at,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        CURRENT_DATE,
                        ?,
                        NOW() + INTERVAL '7 days',
                        NOW(),
                        'active'
                    )
                ");

                $stmt->execute([
                    $scheduleId,
                    $beader['beader_id'],
                    $qr_token
                ]);

                $message = "
                    <div class='alert-glass success'>
                        QR session created successfully for
                        <strong>" . e($beader['beader_name']) . "</strong>.
                    </div>
                ";
            }
        }

    } catch (PDOException $e) {

        $message = "
            <div class='alert-glass error'>
                QR session error: " . e($e->getMessage()) . "
            </div>
        ";
    }
}


/*
|--------------------------------------------------------------------------
| GET SCHEDULE FOR EDIT
|--------------------------------------------------------------------------
*/
$editSchedule = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {

    $stmt = $conn->prepare("
        SELECT *
        FROM schedules
        WHERE id = ?
    ");

    $stmt->execute([
        (int)$_GET['edit']
    ]);

    $editSchedule = $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| FETCH TEACHERS
|--------------------------------------------------------------------------
*/
$teachers = $conn->query("
    SELECT
        id,
        fullname,
        employee_id
    FROM users
    WHERE role = 'teacher'
    ORDER BY fullname
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH SUBJECTS
|--------------------------------------------------------------------------
*/
$subjects = $conn->query("
    SELECT
        id,
        subject_code,
        subject_name,
        year_level_id
    FROM subjects
    ORDER BY subject_name
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH YEAR LEVELS
|--------------------------------------------------------------------------
*/
$yearLevels = $conn->query("
    SELECT
        id,
        year_name
    FROM year_levels
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH SECTIONS
|--------------------------------------------------------------------------
*/
$sections = $conn->query("
    SELECT
        id,
        section_name,
        year_level_id
    FROM sections
    ORDER BY section_name
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$filter_day = $_GET['filter_day'] ?? '';
$filter_teacher = $_GET['filter_teacher'] ?? '';
$filter_year = $_GET['filter_year'] ?? '';
$filter_section = $_GET['filter_section'] ?? '';
$search = trim($_GET['search'] ?? '');


/*
|--------------------------------------------------------------------------
| ALL SCHEDULES
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        s.id,
        s.teacher_id,
        s.subject_id,
        s.room,
        s.day,
        s.start_time,
        s.end_time,
        s.created_at,
        s.year_level_id,
        s.section_id,

        t.fullname AS teacher_name,
        t.employee_id,

        sub.subject_code,
        sub.subject_name,

        y.year_name,

        sec.section_name,

        ats.id AS session_id,
        ats.qr_token,
        ats.qr_expires_at,
        ats.session_date,
        ats.status AS session_status,

        beader.fullname AS beader_name

    FROM schedules s

    JOIN users t
        ON s.teacher_id = t.id

    JOIN subjects sub
        ON s.subject_id = sub.id

    JOIN year_levels y
        ON s.year_level_id = y.id

    JOIN sections sec
        ON s.section_id = sec.id

    LEFT JOIN LATERAL
    (
        SELECT
            ats.*
        FROM attendance_sessions ats
        WHERE ats.schedule_id = s.id
        AND ats.session_date = CURRENT_DATE
        ORDER BY ats.id DESC
        LIMIT 1
    ) ats ON TRUE

    LEFT JOIN users beader
        ON beader.id = ats.beader_id

    WHERE 1 = 1
";

$params = [];

if ($filter_day !== '') {
    $sql .= " AND s.day = ?";
    $params[] = $filter_day;
}

if ($filter_teacher !== '') {
    $sql .= " AND s.teacher_id = ?";
    $params[] = (int)$filter_teacher;
}

if ($filter_year !== '') {
    $sql .= " AND s.year_level_id = ?";
    $params[] = (int)$filter_year;
}

if ($filter_section !== '') {
    $sql .= " AND s.section_id = ?";
    $params[] = (int)$filter_section;
}

if ($search !== '') {

    $sql .= "
        AND
        (
            sub.subject_name ILIKE ?
            OR sub.subject_code ILIKE ?
            OR t.fullname ILIKE ?
            OR s.room ILIKE ?
            OR sec.section_name ILIKE ?
        )
    ";

    $searchValue = "%{$search}%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$sql .= "
    ORDER BY
        CASE s.day
            WHEN 'Monday' THEN 1
            WHEN 'Tuesday' THEN 2
            WHEN 'Wednesday' THEN 3
            WHEN 'Thursday' THEN 4
            WHEN 'Friday' THEN 5
            WHEN 'Saturday' THEN 6
            WHEN 'Sunday' THEN 7
            ELSE 8
        END,
        s.start_time ASC,
        s.year_level_id ASC,
        sec.section_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TODAY'S SCHEDULES
|--------------------------------------------------------------------------
*/
$today = date('l');

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.teacher_id,
        s.subject_id,
        s.room,
        s.day,
        s.start_time,
        s.end_time,
        s.year_level_id,
        s.section_id,

        t.fullname AS teacher_name,
        t.employee_id,

        sub.subject_code,
        sub.subject_name,

        y.year_name,

        sec.section_name,

        ats.id AS session_id,
        ats.qr_token,
        ats.qr_expires_at,
        ats.status AS session_status,

        beader.fullname AS beader_name

    FROM schedules s

    JOIN users t
        ON s.teacher_id = t.id

    JOIN subjects sub
        ON s.subject_id = sub.id

    JOIN year_levels y
        ON s.year_level_id = y.id

    JOIN sections sec
        ON s.section_id = sec.id

    LEFT JOIN LATERAL
    (
        SELECT
            ats.*
        FROM attendance_sessions ats
        WHERE ats.schedule_id = s.id
        AND ats.session_date = CURRENT_DATE
        ORDER BY ats.id DESC
        LIMIT 1
    ) ats ON TRUE

    LEFT JOIN users beader
        ON beader.id = ats.beader_id

    WHERE s.day = ?

    ORDER BY s.start_time ASC
");

$stmt->execute([$today]);

$todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/
$totalSchedules = count($schedules);
$totalTeachers = count($teachers);
$totalSubjects = count($subjects);

$activeQrCount = 0;

foreach ($schedules as $schedule) {

    if (
        !empty($schedule['qr_token']) &&
        $schedule['session_status'] === 'active' &&
        !empty($schedule['qr_expires_at']) &&
        strtotime($schedule['qr_expires_at']) > time()
    ) {
        $activeQrCount++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Schedule Management - Admin</title>

    <!-- FONT AWESOME -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --sidebar-bg: #111827;
            --sidebar-hover: #1f2937;
            --body-bg: #f5f7fb;
            --text-dark: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--body-bg);
            padding: 0;
            margin: 0;
            color: var(--text-dark);
        }


      

        /* =====================================================
           MAIN CONTENT
        ====================================================== */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }


        /* =====================================================
           PAGE HEADER
        ====================================================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .page-header h1 {
            margin: 0;
            color: #111827;
            font-size: 34px;
            font-weight: 750;
            letter-spacing: -0.5px;
        }

        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
        }


        /* =====================================================
           ALERT
        ====================================================== */

        .alert-glass {
            padding: 15px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 15px;
            line-height: 1.6;
        }

        .alert-glass.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-glass.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }


        /* =====================================================
           CARDS
        ====================================================== */

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 26px;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.06);
            border: 1px solid #eef0f4;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 22px;
            color: #111827;
            font-size: 22px;
            font-weight: 700;
        }


        /* =====================================================
           FORM
        ====================================================== */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        input,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d9dee7;
            border-radius: 9px;
            font-size: 15px;
            background: #fff;
            color: #1f2937;
            transition: all 0.2s ease;
        }

        input::placeholder {
            color: #9ca3af;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
        }


        /* =====================================================
           BUTTONS
        ====================================================== */

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );
            color: #fff;
            box-shadow: 0 4px 12px rgba(249,115,22,0.2);
        }

        .btn-success {
            background: #16a34a;
            color: #fff;
        }

        .btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .btn-warning {
            background: #f59e0b;
            color: #fff;
        }

        .btn-info {
            background: #0891b2;
            color: #fff;
        }

        .btn-secondary {
            background: #64748b;
            color: #fff;
        }

        .btn-sm {
            padding: 7px 12px;
            font-size: 12px;
            border-radius: 7px;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #4b5563;
        }

        .btn-outline:hover {
            background: #f8fafc;
        }


        /* =====================================================
           STATISTICS
        ====================================================== */

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 23px;
            box-shadow: 0 5px 20px rgba(15,23,42,0.06);
            border: 1px solid #eef0f4;
            display: flex;
            align-items: center;
            gap: 17px;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15,23,42,0.08);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 31px;
            font-weight: 750;
            color: #111827;
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 6px;
            font-weight: 500;
        }


        /* =====================================================
           TABLE
        ====================================================== */

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th,
        td {
            padding: 15px 13px;
            text-align: left;
            border-bottom: 1px solid #edf0f4;
        }

        th {
            background: #f8fafc;
            font-size: 13px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 750;
            letter-spacing: 0.3px;
        }

        td {
            font-size: 15px;
            color: #334155;
        }

        tr:hover {
            background: #fffaf5;
        }


        /* =====================================================
           BADGES
        ====================================================== */

        .badge {
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-gray {
            background: #f1f5f9;
            color: #64748b;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-orange {
            background: #ffedd5;
            color: #c2410c;
        }

        .badge-teal {
            background: #ccfbf1;
            color: #0f766e;
        }

        .badge-purple {
            background: #ede9fe;
            color: #6d28d9;
        }


        /* =====================================================
           ACTIONS
        ====================================================== */

        .actions {
            white-space: nowrap;
        }

        .actions a {
            margin-right: 4px;
            margin-bottom: 4px;
        }


        /* =====================================================
           TODAY
        ====================================================== */

        .today-highlight {
            border-left: 4px solid var(--primary);
        }


        /* =====================================================
           FILTERS
        ====================================================== */

        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
            margin-bottom: 18px;
        }

        .filter-bar input,
        .filter-bar select {
            width: auto;
            min-width: 180px;
            padding: 12px 13px;
        }


        /* =====================================================
           TEACHER
        ====================================================== */

        .teacher-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .teacher-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }


        /* =====================================================
           TIME / ROOM
        ====================================================== */

        .time-badge {
            background: #f1f5f9;
            padding: 6px 11px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            display: inline-block;
            white-space: nowrap;
        }

        .room-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 5px 9px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
        }


        /* =====================================================
           EMPTY STATE
        ====================================================== */

        .empty-state {
            text-align: center;
            padding: 50px !important;
            color: #888;
        }

        .empty-state p {
            font-size: 15px;
        }


        /* =====================================================
           QR
        ====================================================== */

        .qr-cell {
            font-family: monospace;
            font-size: 12px;
            color: #666;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .qr-token {
            display: block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-top: 4px;
        }


        /* =====================================================
           MOBILE MENU BUTTON
        ====================================================== */

        .mobile-menu-btn {
            display: none;
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 9px;
            background: var(--primary);
            color: white;
            font-size: 18px;
            cursor: pointer;
        }

        .sidebar-overlay {
            display: none;
        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1200px) {

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 900px) {

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .mobile-menu-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.45);
                z-index: 999;
            }

            .sidebar-overlay.show {
                display: block;
            }

            .page-header {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .card {
                padding: 20px;
                overflow-x: auto;
            }

            table {
                min-width: 1100px;
            }

        }


        @media (max-width: 600px) {

            .main-content {
                padding: 14px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .page-header {
                align-items: flex-start;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .page-header p {
                font-size: 14px;
            }

            .card {
                padding: 17px;
                border-radius: 13px;
            }

            .card h2 {
                font-size: 19px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<?php include 'sidebar.php'; ?>

<!-- =====================================================
     MAIN CONTENT
====================================================== -->

<main class="main-content">

    <div class="container">


        <!-- MOBILE HEADER -->

        <div
            style="
                display:flex;
                justify-content:flex-start;
                margin-bottom:15px;
            "
        >

            <button
                type="button"
                class="mobile-menu-btn"
                id="mobileMenuBtn"
            >

                <i class="fa-solid fa-bars"></i>

            </button>

        </div>


        <?= $message ?>


        <!-- =====================================================
             STATISTICS
        ====================================================== -->

        <div class="stats-row">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="background:#fff7ed;color:#f97316;"
                >
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>

                    <div class="stat-value">
                        <?= $totalSchedules ?>
                    </div>

                    <div class="stat-label">
                        Total Schedules
                    </div>

                </div>

            </div>


            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="background:#dcfce7;color:#10b981;"
                >
                    <i class="fa-solid fa-user-tie"></i>
                </div>

                <div>

                    <div class="stat-value">
                        <?= $totalTeachers ?>
                    </div>

                    <div class="stat-label">
                        Teachers
                    </div>

                </div>

            </div>


            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="background:#fef3c7;color:#f59e0b;"
                >
                    <i class="fa-solid fa-book"></i>
                </div>

                <div>

                    <div class="stat-value">
                        <?= $totalSubjects ?>
                    </div>

                    <div class="stat-label">
                        Subjects
                    </div>

                </div>

            </div>


            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="background:#d1ecf1;color:#0c5460;"
                >
                    <i class="fa-solid fa-qrcode"></i>
                </div>

                <div>

                    <div class="stat-value">
                        <?= $activeQrCount ?>
                    </div>

                    <div class="stat-label">
                        Active QR Sessions
                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="page-header">

            <div>

                <h1>

                    <i
                        class="fa-solid fa-calendar-days"
                        style="color:#f97316;"
                    ></i>

                    Schedule Management

                </h1>

                <p>
                    Manage class schedules, assign teachers,
                    and manage attendance QR sessions.
                </p>

            </div>

        </div>


        <!-- =====================================================
             ADD / EDIT FORM
        ====================================================== -->

        <div class="card">

            <h2>

                <i
                    class="fa-solid fa-calendar-plus"
                    style="color:#f97316;margin-right:8px;"
                ></i>

                <?= $editSchedule
                    ? 'Edit Schedule'
                    : 'Add New Schedule'
                ?>

            </h2>


            <form method="POST" action="">

                <input
                    type="hidden"
                    name="id"
                    value="<?= e($editSchedule['id'] ?? '') ?>"
                >


                <div class="form-grid">


                    <!-- TEACHER -->

                    <div>

                        <label>
                            Teacher
                            <span style="color:#dc3545;">*</span>
                        </label>

                        <select
                            name="teacher_id"
                            required
                        >

                            <option value="">
                                -- Select Teacher --
                            </option>

                            <?php foreach ($teachers as $t): ?>

                                <option
                                    value="<?= e($t['id']) ?>"
                                    <?= ($editSchedule['teacher_id'] ?? '') == $t['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e($t['fullname']) ?>

                                    <?php if (!empty($t['employee_id'])): ?>

                                        (<?= e($t['employee_id']) ?>)

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- YEAR LEVEL -->

                    <div>

                        <label>
                            Year Level
                            <span style="color:#dc3545;">*</span>
                        </label>

                        <select
                            name="year_level_id"
                            id="year_level"
                            required
                            onchange="filterYearData()"
                        >

                            <option value="">
                                -- Select Year --
                            </option>

                            <?php foreach ($yearLevels as $yl): ?>

                                <option
                                    value="<?= e($yl['id']) ?>"
                                    <?= ($editSchedule['year_level_id'] ?? '') == $yl['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e($yl['year_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SUBJECT -->

                    <div>

                        <label>
                            Subject
                            <span style="color:#dc3545;">*</span>
                        </label>

                        <select
                            name="subject_id"
                            id="subject"
                            required
                        >

                            <option value="">
                                -- Select Subject --
                            </option>

                            <?php foreach ($subjects as $sub): ?>

                                <option
                                    value="<?= e($sub['id']) ?>"
                                    data-year="<?= e($sub['year_level_id'] ?? '') ?>"
                                    <?= ($editSchedule['subject_id'] ?? '') == $sub['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e($sub['subject_code']) ?>
                                    -
                                    <?= e($sub['subject_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SECTION -->

                    <div>

                        <label>
                            Section
                            <span style="color:#dc3545;">*</span>
                        </label>

                        <select
                            name="section_id"
                            id="section"
                            required
                        >

                            <option value="">
                                -- Select Section --
                            </option>

                            <?php foreach ($sections as $sec): ?>

                                <option
                                    value="<?= e($sec['id']) ?>"
                                    data-year="<?= e($sec['year_level_id'] ?? '') ?>"
                                    <?= ($editSchedule['section_id'] ?? '') == $sec['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e($sec['section_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- DAY -->

                    <div>

                        <label>
                            Day
                            <span style="color:#dc3545;">*</span>
                        </label>

                        <select
                            name="day"
                            required
                        >

                            <?php

                            $days = [
                                'Monday',
                                'Tuesday',
                                'Wednesday',
                                'Thursday',
                                'Friday',
                                'Saturday',
                                'Sunday'
                            ];

                            ?>

                            <option value="">
                                -- Select Day --
                            </option>

                            <?php foreach ($days as $d): ?>

                                <option
                                    value="<?= e($d) ?>"
                                    <?= ($editSchedule['day'] ?? '') === $d
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e($d) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- START TIME -->

                    <div>

                        <label>
                            Start Time
                            <span style="color:#dc3545;">*</span>
                        </label>

                        <input
                            type="time"
                            name="start_time"
                            value="<?= e($editSchedule['start_time'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- END TIME -->

                    <div>

                        <label>
                            End Time
                            <span style="color:#dc3545;">*</span>
                        </label>

                        <input
                            type="time"
                            name="end_time"
                            value="<?= e($editSchedule['end_time'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- ROOM -->

                    <div>

                        <label>
                            Room
                        </label>

                        <input
                            type="text"
                            name="room"
                            value="<?= e($editSchedule['room'] ?? '') ?>"
                            placeholder="e.g. Room 101, Lab A"
                        >

                    </div>

                </div>


                <div style="margin-top:20px;">

                    <button
                        type="submit"
                        name="save_schedule"
                        class="btn btn-primary"
                    >

                        <i
                            class="fa-solid fa-floppy-disk"
                            style="margin-right:6px;"
                        ></i>

                        <?= $editSchedule
                            ? 'Update Schedule'
                            : 'Create Schedule'
                        ?>

                    </button>


                    <?php if ($editSchedule): ?>

                        <a
                            href="schedules.php"
                            class="btn btn-secondary"
                        >

                            <i
                                class="fa-solid fa-xmark"
                                style="margin-right:5px;"
                            ></i>

                            Cancel

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>


        <!-- =====================================================
             TODAY'S CLASSES
        ====================================================== -->

        <div class="card">

            <h2>

                <i
                    class="fa-solid fa-calendar-day"
                    style="color:#f97316;margin-right:8px;"
                ></i>

                Today's Classes
                <span
                    style="
                        color:#6b7280;
                        font-size:15px;
                        font-weight:500;
                    "
                >
                    (<?= e($today) ?>)
                </span>

            </h2>


            <?php if (empty($todayClasses)): ?>

                <p
                    style="
                        color:#888;
                        font-size:15px;
                    "
                >
                    No classes scheduled for today.
                </p>

            <?php else: ?>

                <table>

                    <tr>

                        <th>Time</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Room</th>
                        <th>Year & Section</th>
                        <th>QR Session</th>
                        <th>Actions</th>

                    </tr>


                    <?php foreach ($todayClasses as $tc): ?>

                        <tr class="today-highlight">


                            <td>

                                <span class="time-badge">

                                    <?= date(
                                        'h:i A',
                                        strtotime($tc['start_time'])
                                    ) ?>

                                    -

                                    <?= date(
                                        'h:i A',
                                        strtotime($tc['end_time'])
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <strong
                                    style="
                                        font-size:15px;
                                        color:#ea580c;
                                    "
                                >
                                    <?= e($tc['subject_code']) ?>
                                </strong>

                                <br>

                                <small
                                    style="
                                        color:#888;
                                        font-size:13px;
                                    "
                                >
                                    <?= e($tc['subject_name']) ?>
                                </small>

                            </td>


                            <td>

                                <div class="teacher-cell">

                                    <div class="teacher-avatar">

                                        <?= e(
                                            getInitials($tc['teacher_name'])
                                        ) ?>

                                    </div>

                                    <div>

                                        <div
                                            style="
                                                font-weight:600;
                                                font-size:14px;
                                            "
                                        >
                                            <?= e($tc['teacher_name']) ?>
                                        </div>

                                        <small
                                            style="
                                                color:#888;
                                                font-size:12px;
                                            "
                                        >

                                            <?= e(
                                                $tc['employee_id'] ?? 'N/A'
                                            ) ?>

                                        </small>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?php if (!empty($tc['room'])): ?>

                                    <span class="room-badge">
                                        <?= e($tc['room']) ?>
                                    </span>

                                <?php else: ?>

                                    <span style="color:#bbb;">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <strong>
                                    <?= e($tc['year_name']) ?>
                                </strong>

                                <br>

                                <small
                                    style="
                                        color:#888;
                                        font-size:13px;
                                    "
                                >

                                    <?= e($tc['section_name']) ?>

                                </small>

                            </td>


                            <td>

                                <?php if (
                                    !empty($tc['qr_token']) &&
                                    $tc['session_status'] === 'active' &&
                                    !empty($tc['qr_expires_at']) &&
                                    strtotime($tc['qr_expires_at']) > time()
                                ): ?>

                                    <span class="badge badge-green">
                                        Active
                                    </span>

                                    <br>

                                    <small
                                        style="
                                            color:#888;
                                            font-size:12px;
                                        "
                                    >

                                        Beader:
                                        <?= e(
                                            $tc['beader_name'] ?? 'N/A'
                                        ) ?>

                                    </small>

                                <?php elseif (!empty($tc['qr_token'])): ?>

                                    <span class="badge badge-red">
                                        Expired
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-gray">
                                        No Session
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td class="actions">

                                <a
                                    href="?refresh_qr=<?= e($tc['id']) ?>"
                                    class="btn btn-warning btn-sm"
                                    onclick="return confirm('Generate a new QR session for this class?')"
                                >

                                    <?= !empty($tc['qr_token'])
                                        ? 'Refresh QR'
                                        : 'Create QR'
                                    ?>

                                </a>


                                <a
                                    href="attendance.php?schedule_id=<?= e($tc['id']) ?>"
                                    class="btn btn-success btn-sm"
                                >
                                    Attendance
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </table>

            <?php endif; ?>

        </div>


        <!-- =====================================================
             ALL SCHEDULES
        ====================================================== -->

        <div class="card">

            <h2>

                <i
                    class="fa-solid fa-list-check"
                    style="color:#f97316;margin-right:8px;"
                ></i>

                All Schedules

            </h2>


            <!-- FILTERS -->

            <form
                method="GET"
                action=""
                class="filter-bar"
            >

                <div>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search subject, teacher, room, section..."
                        value="<?= e($search) ?>"
                    >

                </div>


                <div>

                    <select name="filter_day">

                        <option value="">
                            All Days
                        </option>

                        <?php foreach ($days as $d): ?>

                            <option
                                value="<?= e($d) ?>"
                                <?= $filter_day === $d
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($d) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <select name="filter_teacher">

                        <option value="">
                            All Teachers
                        </option>

                        <?php foreach ($teachers as $t): ?>

                            <option
                                value="<?= e($t['id']) ?>"
                                <?= $filter_teacher == $t['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($t['fullname']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <select
                        name="filter_year"
                        id="filter_year"
                    >

                        <option value="">
                            All Years
                        </option>

                        <?php foreach ($yearLevels as $yl): ?>

                            <option
                                value="<?= e($yl['id']) ?>"
                                <?= $filter_year == $yl['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($yl['year_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <select name="filter_section">

                        <option value="">
                            All Sections
                        </option>

                        <?php foreach ($sections as $sec): ?>

                            <option
                                value="<?= e($sec['id']) ?>"
                                <?= $filter_section == $sec['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($sec['section_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i
                            class="fa-solid fa-filter"
                            style="margin-right:5px;"
                        ></i>

                        Filter

                    </button>


                    <a
                        href="schedule.php"
                        class="btn btn-outline"
                    >

                        <i
                            class="fa-solid fa-rotate-left"
                            style="margin-right:5px;"
                        ></i>

                        Reset

                    </a>

                </div>

            </form>


            <!-- TABLE -->

            <div style="overflow-x:auto;">

                <table>

                    <tr>

                        <th>Day</th>
                        <th>Time</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Class</th>
                        <th>Room</th>
                       
                        <th>Created</th>
                        <th>Actions</th>

                    </tr>


                    <?php if (empty($schedules)): ?>

                        <tr>

                            <td
                                colspan="9"
                                class="empty-state"
                            >

                                <span
                                    style="
                                        font-size:40px;
                                        color:#f97316;
                                    "
                                >
                                    <i class="fa-solid fa-calendar-xmark"></i>
                                </span>

                                <p>
                                    No schedules found.
                                </p>

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($schedules as $sch): ?>


                            <?php

                            $dayClasses = [

                                'Monday' => 'badge-blue',

                                'Tuesday' => 'badge-green',

                                'Wednesday' => 'badge-orange',

                                'Thursday' => 'badge-purple',

                                'Friday' => 'badge-blue',

                                'Saturday' => 'badge-teal',

                                'Sunday' => 'badge-red'

                            ];

                            $dayClass =
                                $dayClasses[$sch['day']]
                                ?? 'badge-gray';

                            ?>


                            <tr>


                                <!-- DAY -->

                                <td>

                                    <span class="badge <?= e($dayClass) ?>">

                                        <?= e($sch['day']) ?>

                                    </span>

                                </td>


                                <!-- TIME -->

                                <td>

                                    <span class="time-badge">

                                        <?= date(
                                            'h:i A',
                                            strtotime($sch['start_time'])
                                        ) ?>

                                        -

                                        <?= date(
                                            'h:i A',
                                            strtotime($sch['end_time'])
                                        ) ?>

                                    </span>

                                </td>


                                <!-- SUBJECT -->

                                <td>

                                    <strong
                                        style="
                                            color:#ea580c;
                                            font-size:15px;
                                        "
                                    >

                                        <?= e(
                                            $sch['subject_code']
                                        ) ?>

                                    </strong>

                                    <br>

                                    <small
                                        style="
                                            color:#888;
                                            font-size:13px;
                                        "
                                    >

                                        <?= e(
                                            $sch['subject_name']
                                        ) ?>

                                    </small>

                                </td>


                                <!-- TEACHER -->

                                <td>

                                    <div class="teacher-cell">

                                        <div class="teacher-avatar">

                                            <?= e(
                                                getInitials(
                                                    $sch['teacher_name']
                                                )
                                            ) ?>

                                        </div>

                                        <div>

                                            <div
                                                style="
                                                    font-weight:600;
                                                    font-size:14px;
                                                "
                                            >

                                                <?= e(
                                                    $sch['teacher_name']
                                                ) ?>

                                            </div>

                                            <small
                                                style="
                                                    color:#888;
                                                    font-size:12px;
                                                "
                                            >

                                                <?= e(
                                                    $sch['employee_id']
                                                    ?? 'N/A'
                                                ) ?>

                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <!-- CLASS -->

                                <td>

                                    <div
                                        style="
                                            font-weight:600;
                                            font-size:14px;
                                        "
                                    >

                                        <?= e(
                                            $sch['year_name']
                                        ) ?>

                                    </div>

                                    <small
                                        style="
                                            color:#888;
                                            font-size:13px;
                                        "
                                    >

                                        Section:
                                        <?= e(
                                            $sch['section_name']
                                        ) ?>

                                    </small>

                                </td>


                                <!-- ROOM -->

                                <td>

                                    <?php if (!empty($sch['room'])): ?>

                                        <span class="room-badge">

                                            <?= e(
                                                $sch['room']
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span style="color:#bbb;">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>




                                <!-- CREATED -->

                                <td>

                                    <small
                                        style="
                                            color:#888;
                                            font-size:12px;
                                        "
                                    >

                                        <?= !empty($sch['created_at'])
                                            ? date(
                                                'M d, Y',
                                                strtotime(
                                                    $sch['created_at']
                                                )
                                            )
                                            : '—'
                                        ?>

                                    </small>

                                </td>


                                <!-- ACTIONS -->

                                <td class="actions">


                                    <a
                                        href="?edit=<?= e($sch['id']) ?>"
                                        class="btn btn-primary btn-sm"
                                    >

                                        <i class="fa-solid fa-pen"></i>

                                        Edit

                                    </a>


                                 


                                    <a
                                        href="attendance.php?schedule_id=<?= e($sch['id']) ?>"
                                        class="btn btn-success btn-sm"
                                    >

                                        Attendance

                                    </a>


                                    <a
                                        href="?delete=<?= e($sch['id']) ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete this schedule? This cannot be undone.')"
                                    >

                                        <i class="fa-solid fa-trash"></i>

                                        Delete

                                    </a>

                                </td>

                            </tr>


                        <?php endforeach; ?>

                    <?php endif; ?>

                </table>

            </div>

        </div>

    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| MOBILE SIDEBAR
|--------------------------------------------------------------------------
*/

const mobileMenuBtn =
    document.getElementById('mobileMenuBtn');

const sidebar =
    document.getElementById('sidebar');

const sidebarOverlay =
    document.getElementById('sidebarOverlay');


mobileMenuBtn.addEventListener(
    'click',
    function() {

        sidebar.classList.toggle('open');

        sidebarOverlay.classList.toggle('show');

    }
);


sidebarOverlay.addEventListener(
    'click',
    function() {

        sidebar.classList.remove('open');

        sidebarOverlay.classList.remove('show');

    }
);


/*
|--------------------------------------------------------------------------
| FILTER SUBJECTS AND SECTIONS BY YEAR LEVEL
|--------------------------------------------------------------------------
*/

function filterYearData() {

    const yearSelect =
        document.getElementById('year_level');

    const subjectSelect =
        document.getElementById('subject');

    const sectionSelect =
        document.getElementById('section');

    const yearId =
        yearSelect.value;


    /*
    |--------------------------------------------------------------------------
    | SUBJECTS
    |--------------------------------------------------------------------------
    */

    const subjectOptions =
        subjectSelect.querySelectorAll(
            'option[data-year]'
        );


    subjectOptions.forEach(function(option) {

        const subjectYear =
            option.dataset.year;


        if (
            !yearId ||
            subjectYear === '' ||
            subjectYear === yearId
        ) {

            option.style.display = '';

        } else {

            option.style.display = 'none';

            if (option.selected) {
                option.selected = false;
            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | SECTIONS
    |--------------------------------------------------------------------------
    */

    const sectionOptions =
        sectionSelect.querySelectorAll(
            'option[data-year]'
        );


    sectionOptions.forEach(function(option) {

        const sectionYear =
            option.dataset.year;


        if (
            !yearId ||
            sectionYear === yearId
        ) {

            option.style.display = '';

        } else {

            option.style.display = 'none';

            if (option.selected) {
                option.selected = false;
            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | RESET INVALID SUBJECT
    |--------------------------------------------------------------------------
    */

    if (subjectSelect.value) {

        const selectedSubject =
            subjectSelect.querySelector(
                'option[value="' +
                subjectSelect.value +
                '"]'
            );

        if (
            selectedSubject &&
            selectedSubject.style.display === 'none'
        ) {

            subjectSelect.value = '';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RESET INVALID SECTION
    |--------------------------------------------------------------------------
    */

    if (sectionSelect.value) {

        const selectedSection =
            sectionSelect.querySelector(
                'option[value="' +
                sectionSelect.value +
                '"]'
            );

        if (
            selectedSection &&
            selectedSection.style.display === 'none'
        ) {

            sectionSelect.value = '';

        }

    }

}


/*
|--------------------------------------------------------------------------
| RUN ON PAGE LOAD
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function() {

        filterYearData();

    }
);

</script>

</body>
</html>
