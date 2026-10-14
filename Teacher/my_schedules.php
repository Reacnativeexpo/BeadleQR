<?php

session_start();

require "../db.php";

/*
|--------------------------------------------------------------------------
| TEACHER ACCESS
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'teacher'
) {
    header("Location: ../login.php");
    exit();
}

$teacher_id = (int) $_SESSION['user_id'];

$message = "";
$generatedQR = null;


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| GENERATE QR
|--------------------------------------------------------------------------
*/

if (
    isset($_POST['generate_qr']) &&
    isset($_POST['schedule_id']) &&
    is_numeric($_POST['schedule_id'])
) {

    $schedule_id = (int) $_POST['schedule_id'];

    try {

        /*
        |--------------------------------------------------------------------------
        | VERIFY SCHEDULE
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                s.id,
                s.teacher_id,
                s.subject_id,
                s.year_level_id,
                s.section_id,
                s.day,
                s.start_time,
                s.end_time,
                s.room,

                sub.subject_code,
                sub.subject_name,

                y.year_name,

                sec.section_name

            FROM public.schedules s

            JOIN public.subjects sub
                ON sub.id = s.subject_id

            JOIN public.year_levels y
                ON y.id = s.year_level_id

            JOIN public.sections sec
                ON sec.id = s.section_id

            WHERE s.id = ?
            AND s.teacher_id = ?
        ");

        $stmt->execute([
            $schedule_id,
            $teacher_id
        ]);

        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$schedule) {

            $message = "
                <div class='alert alert-danger'>
                    <i class='fa-solid fa-circle-exclamation me-2'></i>
                    You are not authorized to generate a QR for this schedule.
                </div>
            ";

        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK TODAY
            |--------------------------------------------------------------------------
            */

            $today = date('l');

            if ($schedule['day'] !== $today) {

                $message = "
                    <div class='alert alert-warning'>
                        <i class='fa-solid fa-calendar-xmark me-2'></i>
                        This class is not scheduled for today.
                    </div>
                ";

            } else {

                /*
                |--------------------------------------------------------------------------
                | CHECK ASSIGNED BEADER
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    SELECT
                        ba.id,
                        ba.beader_id,
                        u.fullname AS beader_name,
                        u.student_no,
                        u.username

                    FROM public.beader_assignments ba

                    JOIN public.users u
                        ON u.id = ba.beader_id

                    WHERE ba.section_id = ?
                    AND ba.year_level_id = ?

                    LIMIT 1
                ");

                $stmt->execute([
                    $schedule['section_id'],
                    $schedule['year_level_id']
                ]);

                $beader = $stmt->fetch(PDO::FETCH_ASSOC);


                if (!$beader) {

                    $message = "
                        <div class='alert alert-warning'>
                            <strong>
                                <i class='fa-solid fa-triangle-exclamation me-2'></i>
                                No Beader assigned.
                            </strong>
                            <br>
                            This section does not have a Beader assigned yet.
                            Please contact the administrator.
                        </div>
                    ";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK EXISTING TODAY'S SESSION
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $conn->prepare("
                        SELECT
                            id,
                            schedule_id,
                            beader_id,
                            session_date,
                            qr_token,
                            qr_expires_at,
                            started_at,
                            ended_at,
                            status

                        FROM public.attendance_sessions

                        WHERE schedule_id = ?
                        AND session_date = CURRENT_DATE

                        ORDER BY id DESC

                        LIMIT 1
                    ");

                    $stmt->execute([
                        $schedule_id
                    ]);

                    $existingSession = $stmt->fetch(PDO::FETCH_ASSOC);


                    /*
                    |--------------------------------------------------------------------------
                    | ACTIVE SESSION
                    |--------------------------------------------------------------------------
                    |
                    | If a QR already exists and is active,
                    | DO NOT create another QR.
                    |
                    */

                    if (
                        $existingSession &&
                        $existingSession['status'] === 'active'
                    ) {

                        $message = "
                            <div class='alert alert-info'>
                                <i class='fa-solid fa-qrcode me-2'></i>
                                A QR code has already been generated for this class.
                                Please use <strong>View QR</strong>.
                            </div>
                        ";

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | COMPLETED SESSION
                    |--------------------------------------------------------------------------
                    */

                    elseif (
                        $existingSession &&
                        $existingSession['status'] === 'completed'
                    ) {

                        $message = "
                            <div class='alert alert-secondary'>
                                <i class='fa-solid fa-circle-check me-2'></i>
                                Attendance for this class has already been completed today.
                            </div>
                        ";

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CLASS END TIME CHECK
                    |--------------------------------------------------------------------------
                    */

                    else {

                        $currentTime = date('H:i:s');

                        if (
                            $currentTime >=
                            $schedule['end_time']
                        ) {

                            $message = "
                                <div class='alert alert-danger'>
                                    <i class='fa-solid fa-clock me-2'></i>
                                    This class schedule has already ended for today.
                                    You cannot generate a QR after the scheduled end time.
                                </div>
                            ";

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | GENERATE NEW TOKEN
                            |--------------------------------------------------------------------------
                            */

                            $qr_token =
                                bin2hex(
                                    random_bytes(32)
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | EXISTING CANCELLED SESSION
                            |--------------------------------------------------------------------------
                            |
                            | Because schedule_id + session_date is UNIQUE,
                            | we reuse the cancelled row instead of inserting
                            | another row.
                            |
                            */

                            if (
                                $existingSession &&
                                $existingSession['status'] === 'cancelled'
                            ) {

                                $stmt = $conn->prepare("
                                    UPDATE public.attendance_sessions

                                    SET
                                        beader_id = ?,
                                        qr_token = ?,
                                        qr_expires_at = NULL,
                                        started_at = NULL,
                                        ended_at = NULL,
                                        status = 'active'

                                    WHERE id = ?

                                    RETURNING id
                                ");

                                $stmt->execute([
                                    $beader['beader_id'],
                                    $qr_token,
                                    $existingSession['id']
                                ]);

                                $session_id =
                                    $stmt->fetchColumn();

                            }

                            /*
                            |--------------------------------------------------------------------------
                            | NO EXISTING SESSION
                            |--------------------------------------------------------------------------
                            */

                            else {

                                $stmt = $conn->prepare("
                                    INSERT INTO public.attendance_sessions
                                    (
                                        schedule_id,
                                        beader_id,
                                        session_date,
                                        qr_token,
                                        qr_expires_at,
                                        started_at,
                                        ended_at,
                                        status
                                    )

                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        CURRENT_DATE,
                                        ?,
                                        NULL,
                                        NULL,
                                        NULL,
                                        'active'
                                    )

                                    RETURNING id
                                ");

                                $stmt->execute([
                                    $schedule_id,
                                    $beader['beader_id'],
                                    $qr_token
                                ]);

                                $session_id =
                                    $stmt->fetchColumn();
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | GENERATED QR DATA
                            |--------------------------------------------------------------------------
                            */

                            $generatedQR = [

                                'session_id' =>
                                    $session_id,

                                'schedule_id' =>
                                    $schedule_id,

                                'token' =>
                                    $qr_token,

                                'subject_code' =>
                                    $schedule['subject_code'],

                                'subject_name' =>
                                    $schedule['subject_name'],

                                'year_name' =>
                                    $schedule['year_name'],

                                'section_name' =>
                                    $schedule['section_name'],

                                'room' =>
                                    $schedule['room'],

                                'start_time' =>
                                    $schedule['start_time'],

                                'end_time' =>
                                    $schedule['end_time'],

                                'beader_name' =>
                                    $beader['beader_name'],

                                'expires_at' =>
                                    null
                            ];


                            /*
                            |--------------------------------------------------------------------------
                            | SUCCESS MESSAGE
                            |--------------------------------------------------------------------------
                            */

                            $message = "
                                <div class='alert alert-success'>
                                    <strong>
                                        <i class='fa-solid fa-circle-check me-2'></i>
                                        QR code generated successfully.
                                    </strong>

                                    <br>

                                    {$schedule['subject_code']}
                                    -
                                    {$schedule['subject_name']}

                                    <br>

                                    {$schedule['year_name']}
                                    -
                                    {$schedule['section_name']}

                                    <br>

                                    <small>
                                        This QR is valid for the entire attendance session.
                                    </small>
                                </div>
                            ";
                        }
                    }
                }
            }
        }

    } catch (PDOException $e) {

        $message = "
            <div class='alert alert-danger'>
                <i class='fa-solid fa-database me-2'></i>
                Database error:
                " . e($e->getMessage()) . "
            </div>
        ";
    }
}


/*
|--------------------------------------------------------------------------
| GET TEACHER SCHEDULES
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
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

        sub.subject_code,
        sub.subject_name,

        y.year_name,

        sec.section_name,

        ats.id AS session_id,
        ats.qr_token,
        ats.qr_expires_at,
        ats.session_date,
        ats.started_at,
        ats.ended_at,
        ats.status AS session_status,

        beader.fullname AS beader_name

    FROM public.schedules s

    JOIN public.subjects sub
        ON s.subject_id = sub.id

    JOIN public.year_levels y
        ON s.year_level_id = y.id

    JOIN public.sections sec
        ON s.section_id = sec.id

    LEFT JOIN LATERAL
    (
        SELECT
            ats.*

        FROM public.attendance_sessions ats

        WHERE ats.schedule_id = s.id
        AND ats.session_date = CURRENT_DATE

        ORDER BY ats.id DESC

        LIMIT 1

    ) ats
        ON TRUE

    LEFT JOIN public.users beader
        ON beader.id = ats.beader_id

    WHERE s.teacher_id = ?

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

        s.start_time ASC
");

$stmt->execute([
    $teacher_id
]);

$schedules =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TODAY
|--------------------------------------------------------------------------
*/

$today = date('l');


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$activeQR = 0;
$todayCount = 0;


foreach ($schedules as $schedule) {

    if (
        $schedule['day'] === $today
    ) {
        $todayCount++;
    }


    if (
        !empty($schedule['qr_token']) &&
        $schedule['session_status'] === 'active'
    ) {
        $activeQR++;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        My Schedules - BeadleQR
    </title>


    <!-- GOOGLE FONT -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- BOOTSTRAP -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- QR CODE -->

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js">
    </script>


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

            font-family:
                'Poppins',
                sans-serif;
        }


        body {

            background:
                var(--body-bg);

            min-height: 100vh;

            color:
                var(--text-dark);
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

            padding:
                0 35px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,.04);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .topbar-title {

            font-size: 16px;

            font-weight: 600;

            color:
                var(--text-dark);
        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .teacher-badge {

            display: flex;

            align-items: center;

            gap: 10px;

            background:
                #f8fafc;

            border:
                1px solid
                #e2e8f0;

            padding:
                7px 13px;

            border-radius: 30px;

            font-size: 13px;

            font-weight: 500;
        }


        .teacher-avatar {

            width: 34px;
            height: 34px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        /* =====================================================
           CONTENT
        ===================================================== */

        .content-area {

            padding:
                30px 35px;
        }


        .page-title {

            font-size: 26px;

            font-weight: 700;

            color:
                var(--text-dark);

            margin-bottom: 5px;
        }


        .page-subtitle {

            color:
                var(--text-muted);

            font-size: 14px;
        }


        /* =====================================================
           STAT CARDS
        ===================================================== */

        .stat-card {

            background: white;

            border: none;

            border-radius: 15px;

            padding: 22px;

            height: 100%;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,.04);

            transition:
                .3s ease;
        }


        .stat-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 8px 20px
                rgba(0,0,0,.07);
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


        .stat-icon.orange {

            background:
                rgba(249,115,22,.12);

            color:
                var(--primary);
        }


        .stat-icon.blue {

            background:
                rgba(59,130,246,.12);

            color:
                var(--info);
        }


        .stat-icon.green {

            background:
                rgba(16,185,129,.12);

            color:
                var(--success);
        }


        .stat-label {

            color:
                var(--text-muted);

            font-size: 13px;

            margin-bottom: 4px;
        }


        .stat-number {

            font-size: 27px;

            font-weight: 700;

            color:
                var(--text-dark);
        }


        /* =====================================================
           SCHEDULE
        ===================================================== */

        .schedule-container {

            background: white;

            border-radius: 15px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,.04);

            overflow: hidden;
        }


        .schedule-header {

            padding:
                20px 25px;

            border-bottom:
                1px solid
                #e2e8f0;

            display: flex;

            justify-content:
                space-between;

            align-items: center;
        }


        .schedule-header h5 {

            margin: 0;

            font-size: 17px;

            font-weight: 600;
        }


        .schedule-header p {

            margin:
                3px 0 0;

            font-size: 12px;

            color:
                var(--text-muted);
        }


        .schedule-count {

            background:
                rgba(249,115,22,.1);

            color:
                var(--primary-dark);

            padding:
                7px 13px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;
        }


        .schedule-body {

            padding: 0;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table {

            margin-bottom: 0;
        }


        .table thead th {

            background:
                #f8fafc;

            color:
                #475569;

            border-bottom:
                1px solid
                #e2e8f0;

            border-top: none;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: .5px;

            padding:
                15px 12px;

            white-space: nowrap;
        }


        .table tbody td {

            padding:
                15px 12px;

            vertical-align: middle;

            border-color:
                #eef2f7;

            font-size: 13px;
        }


        .table tbody tr {

            transition: .2s;
        }


        .table tbody tr:hover {

            background:
                #fff7ed;
        }


        .today-row {

            background:
                #fff7ed !important;
        }


        .today-row:hover {

            background:
                #ffedd5 !important;
        }


        /* =====================================================
           SUBJECT
        ===================================================== */

        .subject-code {

            color:
                var(--primary-dark);

            font-weight: 700;

            font-size: 13px;
        }


        .subject-name {

            color:
                var(--text-muted);

            font-size: 11px;

            margin-top: 3px;

            display: block;
        }


        /* =====================================================
           BADGES
        ===================================================== */

        .today-badge {

            display: inline-block;

            margin-top: 5px;

            padding:
                4px 8px;

            border-radius: 6px;

            background:
                rgba(16,185,129,.12);

            color:
                #047857;

            font-size: 9px;

            font-weight: 700;

            letter-spacing: .5px;
        }


        .room-badge {

            background:
                rgba(245,158,11,.12);

            color:
                #b45309;

            padding:
                5px 9px;

            border-radius: 6px;

            font-size: 11px;

            font-weight: 600;
        }


        .active-qr {

            background:
                rgba(16,185,129,.12);

            color:
                #047857;

            padding:
                6px 9px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 600;
        }


        .completed-qr {

            background:
                rgba(59,130,246,.12);

            color:
                #1d4ed8;

            padding:
                6px 9px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 600;
        }


        .cancelled-qr {

            background:
                rgba(239,68,68,.1);

            color:
                #b91c1c;

            padding:
                6px 9px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 600;
        }


        .no-qr {

            background:
                #f1f5f9;

            color:
                #64748b;

            padding:
                6px 9px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 600;
        }


        .beader-info {

            display: block;

            margin-top: 5px;

            color:
                var(--text-muted);

            font-size: 10px;
        }


        /* =====================================================
           BUTTONS
        ===================================================== */

        .btn {

            border-radius: 8px;

            font-size: 11px;

            font-weight: 500;
        }


        .btn-generate {

            background:
                var(--success);

            border-color:
                var(--success);

            color: white;
        }


        .btn-generate:hover {

            background:
                #059669;

            border-color:
                #059669;

            color: white;
        }


        .btn-view {

            background:
                #3b82f6;

            border-color:
                #3b82f6;

            color: white;
        }


        .btn-view:hover {

            background:
                #2563eb;

            border-color:
                #2563eb;

            color: white;
        }


        .action-buttons {

            min-width: 210px;
        }


        .action-buttons .btn {

            margin:
                2px 1px;
        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            border: none;

            border-radius: 12px;

            font-size: 13px;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,.03);
        }


        /* =====================================================
           QR MODAL
        ===================================================== */

        .modal-content {

            border: none;

            border-radius: 15px;

            overflow: hidden;
        }


        .modal-header {

            border: none;

            padding:
                18px 22px;
        }


        .modal-title {

            font-weight: 600;
        }


        .qr-box {

            width: 260px;
            height: 260px;

            margin: 0 auto;

            display: flex;

            justify-content: center;

            align-items: center;

            background: white;

            padding: 15px;

            border-radius: 12px;

            border:
                1px solid
                #e2e8f0;
        }


        #qrcode img {

            max-width: 100%;

            height: auto;
        }


        .qr-info {

            background:
                #f8fafc;

            padding: 15px;

            border-radius: 10px;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        .mobile-menu-btn {

            display: none;

            border: none;

            background: transparent;

            font-size: 22px;

            color:
                var(--text-dark);
        }


        .sidebar-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background:
                rgba(15,23,42,.5);

            z-index: 999;
        }


        @media (max-width: 992px) {

            .sidebar {

                transform:
                    translateX(-100%);
            }


            .sidebar.show {

                transform:
                    translateX(0);
            }


            .main-content {

                margin-left: 0;
            }


            .mobile-menu-btn {

                display: block;
            }


            .sidebar-overlay.show {

                display: block;
            }


            .content-area {

                padding:
                    25px 20px;
            }


            .topbar {

                padding:
                    0 20px;
            }
        }


        @media (max-width: 768px) {

            .topbar-title {

                display: none;
            }


            .teacher-badge span {

                display: none;
            }


            .schedule-header {

                padding:
                    18px;
            }


            .schedule-body {

                overflow-x: auto;
            }


            .table {

                min-width: 1100px;
            }


            .page-title {

                font-size: 22px;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR OVERLAY
========================================================= -->

<?php include "../asset/teacher_sidebar.php"; ?>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main-content">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <div class="topbar">

        <div class="d-flex align-items-center gap-3">

            <button
                class="mobile-menu-btn"
                onclick="openSidebar()"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="topbar-title">

                Teacher Dashboard

            </div>

        </div>


        <div class="topbar-right">

            <div class="teacher-badge">

                <div class="teacher-avatar">

                    <i class="fa-solid fa-user-tie"></i>

                </div>

                <span>
                    Teacher
                </span>

            </div>

        </div>

    </div>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content-area">


        <?= $message ?>


        <!-- PAGE HEADER -->

        <div
            class="d-flex justify-content-between align-items-center mb-4"
        >

            <div>

                <h1 class="page-title">
                    My Teaching Schedule
                </h1>

                <p class="page-subtitle">
                    Generate attendance QR codes for your assigned classes.
                </p>

            </div>


            <div class="schedule-count">

                <i class="fa-solid fa-calendar-days me-1"></i>

                <?= count($schedules) ?>

                Schedule<?= count($schedules) != 1 ? 's' : '' ?>

            </div>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="row g-3 mb-4">


            <div class="col-lg-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon orange">

                        <i class="fa-solid fa-calendar-days"></i>

                    </div>


                    <div class="stat-label">
                        Total Schedules
                    </div>


                    <div class="stat-number">
                        <?= count($schedules) ?>
                    </div>

                </div>

            </div>


            <div class="col-lg-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon blue">

                        <i class="fa-solid fa-chalkboard-user"></i>

                    </div>


                    <div class="stat-label">
                        Today's Classes
                    </div>


                    <div class="stat-number">
                        <?= $todayCount ?>
                    </div>

                </div>

            </div>


            <div class="col-lg-4 col-md-12">

                <div class="stat-card">

                    <div class="stat-icon green">

                        <i class="fa-solid fa-qrcode"></i>

                    </div>


                    <div class="stat-label">
                        Active QR
                    </div>


                    <div class="stat-number">
                        <?= $activeQR ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             SCHEDULE TABLE
        ================================================== -->

        <div class="schedule-container">


            <div class="schedule-header">

                <div>

                    <h5>

                        <i
                            class="fa-solid fa-calendar-check me-2"
                            style="color:var(--primary);"
                        ></i>

                        My Schedules

                    </h5>


                    <p>
                        Manage QR codes and attendance for your classes.
                    </p>

                </div>


                <div class="schedule-count">

                    <?= count($schedules) ?>

                    Total

                </div>

            </div>


            <div class="schedule-body">


                <?php if (empty($schedules)): ?>


                    <div class="p-4">

                        <div class="alert alert-warning mb-0">

                            <i class="fa-solid fa-triangle-exclamation me-2"></i>

                            No schedules have been assigned to you yet.

                        </div>

                    </div>


                <?php else: ?>


                    <div class="table-responsive">

                        <table class="table table-hover">

                            <thead>

                                <tr>

                                    <th>
                                        Day
                                    </th>

                                    <th>
                                        Time
                                    </th>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Year Level
                                    </th>

                                    <th>
                                        Section
                                    </th>

                                    <th>
                                        Room
                                    </th>

                                    <th>
                                        QR Status
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($schedules as $row): ?>


                                <?php

                                /*
                                |--------------------------------------------------------------------------
                                | QR ACTIVE
                                |--------------------------------------------------------------------------
                                |
                                | NO EXPIRATION CHECK.
                                |
                                */

                                $qrActive =
                                    !empty($row['qr_token']) &&
                                    $row['session_status'] === 'active';


                                $qrCompleted =
                                    $row['session_status'] === 'completed';


                                $qrCancelled =
                                    $row['session_status'] === 'cancelled';

                                ?>


                                <tr
                                    class="<?= $row['day'] === $today
                                        ? 'today-row'
                                        : ''
                                    ?>"
                                >


                                    <!-- DAY -->

                                    <td>

                                        <strong>

                                            <?= e(
                                                $row['day']
                                            ) ?>

                                        </strong>


                                        <?php if (
                                            $row['day'] === $today
                                        ): ?>

                                            <span class="today-badge">
                                                TODAY
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- TIME -->

                                    <td>

                                        <strong>

                                            <?= date(
                                                'h:i A',
                                                strtotime(
                                                    $row['start_time']
                                                )
                                            ) ?>

                                        </strong>


                                        <br>


                                        <small class="text-muted">

                                            to

                                            <?= date(
                                                'h:i A',
                                                strtotime(
                                                    $row['end_time']
                                                )
                                            ) ?>

                                        </small>

                                    </td>


                                    <!-- SUBJECT -->

                                    <td>

                                        <span class="subject-code">

                                            <?= e(
                                                $row['subject_code']
                                            ) ?>

                                        </span>


                                        <span class="subject-name">

                                            <?= e(
                                                $row['subject_name']
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- YEAR -->

                                    <td>

                                        <?= e(
                                            $row['year_name']
                                        ) ?>

                                    </td>


                                    <!-- SECTION -->

                                    <td>

                                        <?= e(
                                            $row['section_name']
                                        ) ?>

                                    </td>


                                    <!-- ROOM -->

                                    <td>

                                        <?php if (
                                            !empty($row['room'])
                                        ): ?>

                                            <span class="room-badge">

                                                <i
                                                    class="fa-solid fa-door-open me-1"
                                                ></i>

                                                <?= e(
                                                    $row['room']
                                                ) ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- QR STATUS -->

                                    <td>


                                        <?php if ($qrActive): ?>


                                            <span class="active-qr">

                                                <i
                                                    class="fa-solid fa-circle-check me-1"
                                                ></i>

                                                QR Active

                                            </span>


                                            <?php if (
                                                !empty(
                                                    $row['beader_name']
                                                )
                                            ): ?>

                                                <span class="beader-info">

                                                    <i
                                                        class="fa-solid fa-user-check me-1"
                                                    ></i>

                                                    Beader:

                                                    <?= e(
                                                        $row['beader_name']
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                        <?php elseif ($qrCompleted): ?>


                                            <span class="completed-qr">

                                                <i
                                                    class="fa-solid fa-circle-check me-1"
                                                ></i>

                                                Completed

                                            </span>


                                        <?php elseif ($qrCancelled): ?>


                                            <span class="cancelled-qr">

                                                <i
                                                    class="fa-solid fa-circle-xmark me-1"
                                                ></i>

                                                Cancelled

                                            </span>


                                        <?php else: ?>


                                            <span class="no-qr">

                                                <i
                                                    class="fa-solid fa-qrcode me-1"
                                                ></i>

                                                No QR

                                            </span>


                                        <?php endif; ?>


                                    </td>


                                    <!-- ACTIONS -->

                                    <td class="action-buttons">


                                        <!-- =================================================
                                             ACTIVE = VIEW QR ONLY
                                        ================================================== -->

                                        <?php if ($qrActive): ?>


                                            <button
                                                type="button"
                                                class="btn btn-view btn-sm"
                                                onclick="showQR(
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['qr_token']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,

                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['subject_code']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,

                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['subject_name']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,

                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['year_name']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,

                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['section_name']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,

                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['room'] ?? ''
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,

                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['beader_name'] ?? ''
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                )"
                                            >

                                                <i
                                                    class="fa-solid fa-eye me-1"
                                                ></i>

                                                View QR

                                            </button>


                                        <!-- =================================================
                                             NO QR / CANCELLED = GENERATE
                                        ================================================== -->

                                        <?php elseif (
                                            !$qrCompleted
                                        ): ?>


                                            <form
                                                method="POST"
                                                style="display:inline;"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="schedule_id"
                                                    value="<?= e(
                                                        $row['id']
                                                    ) ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    name="generate_qr"
                                                    class="btn btn-generate btn-sm"
                                                    onclick="return confirm(
                                                        'Generate a QR code for <?= e(
                                                            $row['subject_code']
                                                        ) ?> - <?= e(
                                                            $row['section_name']
                                                        ) ?>?'
                                                    )"
                                                >

                                                    <i
                                                        class="fa-solid fa-qrcode me-1"
                                                    ></i>

                                                    Generate QR

                                                </button>

                                            </form>


                                        <?php else: ?>


                                            <span
                                                class="text-muted"
                                                style="font-size:11px;"
                                            >

                                                <i
                                                    class="fa-solid fa-lock me-1"
                                                ></i>

                                                Session Completed

                                            </span>


                                        <?php endif; ?>


                                        <!-- BEADER -->

                                        <button
                                            type="button"
                                            class="btn btn-info btn-sm viewBeader"
                                            data-id="<?= e(
                                                $row['id']
                                            ) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewBeaderModal"
                                        >

                                            <i
                                                class="fa-solid fa-user-check me-1"
                                            ></i>

                                            Beader

                                        </button>


                                        <!-- ATTENDANCE -->

                                        <a
                                            href="attendance.php?schedule_id=<?= e(
                                                $row['id']
                                            ) ?>"
                                            class="btn btn-dark btn-sm"
                                        >

                                            <i
                                                class="fa-solid fa-clipboard-check me-1"
                                            ></i>

                                            Attendance

                                        </a>


                                    </td>

                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>


            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     QR MODAL
========================================================= -->

<div
    class="modal fade"
    id="qrModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">


            <div
                class="modal-header bg-success text-white"
            >

                <h5 class="modal-title">

                    <i
                        class="fa-solid fa-qrcode me-2"
                    ></i>

                    Attendance QR Code

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body text-center">


                <h5
                    id="qrSubject"
                    class="fw-semibold"
                >
                    Subject
                </h5>


                <p
                    class="text-muted mb-3"
                    id="qrClass"
                >
                    Year / Section
                </p>


                <div
                    id="qrcode"
                    class="qr-box mb-3"
                ></div>


                <div class="alert alert-success">

                    <strong>

                        <i
                            class="fa-solid fa-circle-check me-1"
                        ></i>

                        QR is active

                    </strong>

                    <br>

                    This QR code is valid for the
                    <strong>entire attendance session</strong>.

                    <br>

                    The Beader can scan for
                    <strong>Time In</strong> and
                    <strong>Time Out</strong>.

                </div>


                <div class="qr-info text-start">


                    <p class="mb-2">

                        <strong>
                            Subject:
                        </strong>

                        <span id="qrSubjectFull"></span>

                    </p>


                    <p class="mb-2">

                        <strong>
                            Class:
                        </strong>

                        <span id="qrClassFull"></span>

                    </p>


                    <p class="mb-2">

                        <strong>
                            Room:
                        </strong>

                        <span id="qrRoom"></span>

                    </p>


                    <p class="mb-0">

                        <strong>
                            Beader:
                        </strong>

                        <span id="qrBeader"></span>

                    </p>

                </div>


                <input
                    type="hidden"
                    id="currentQRToken"
                >

            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >

                    Close

                </button>


            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     VIEW BEADER MODAL
========================================================= -->

<div
    class="modal fade"
    id="viewBeaderModal"
    tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">


            <div
                class="modal-header bg-info text-white"
            >

                <h5 class="modal-title">

                    <i
                        class="fa-solid fa-user-check me-2"
                    ></i>

                    Assigned Beader

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div
                class="modal-body"
                id="viewBeaderContent"
            >

                <div class="text-center py-3">

                    <div
                        class="spinner-border text-info"
                    ></div>

                    <p class="mt-2 mb-0">
                        Loading...
                    </p>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     BOOTSTRAP
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>

/*
|--------------------------------------------------------------------------
| MOBILE SIDEBAR
|--------------------------------------------------------------------------
*/

function openSidebar()
{
    document
        .getElementById('sidebar')
        .classList.add('show');

    document
        .getElementById('sidebarOverlay')
        .classList.add('show');
}


function closeSidebar()
{
    document
        .getElementById('sidebar')
        .classList.remove('show');

    document
        .getElementById('sidebarOverlay')
        .classList.remove('show');
}


/*
|--------------------------------------------------------------------------
| SHOW QR
|--------------------------------------------------------------------------
*/

function showQR(
    token,
    subjectCode,
    subjectName,
    yearName,
    sectionName,
    room,
    beader
) {

    /*
    |--------------------------------------------------------------------------
    | CLEAR OLD QR
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'qrcode'
    ).innerHTML = '';


    /*
    |--------------------------------------------------------------------------
    | BASIC INFORMATION
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'qrSubject'
    ).textContent =
        subjectCode;


    document.getElementById(
        'qrClass'
    ).textContent =
        yearName +
        ' - ' +
        sectionName;


    document.getElementById(
        'qrSubjectFull'
    ).textContent =
        subjectCode +
        ' - ' +
        subjectName;


    document.getElementById(
        'qrClassFull'
    ).textContent =
        yearName +
        ' - ' +
        sectionName;


    document.getElementById(
        'qrRoom'
    ).textContent =
        room ||
        'No room assigned';


    document.getElementById(
        'qrBeader'
    ).textContent =
        beader ||
        'No Beader';


    document.getElementById(
        'currentQRToken'
    ).value =
        token;


    /*
    |--------------------------------------------------------------------------
    | GENERATE QR IMAGE
    |--------------------------------------------------------------------------
    */

    new QRCode(

        document.getElementById(
            'qrcode'
        ),

        {
            text: token,

            width: 230,

            height: 230,

            correctLevel:
                QRCode.CorrectLevel.H
        }

    );


    /*
    |--------------------------------------------------------------------------
    | OPEN MODAL
    |--------------------------------------------------------------------------
    */

    const modalElement =
        document.getElementById(
            'qrModal'
        );


    const modal =
        new bootstrap.Modal(
            modalElement
        );


    modal.show();
}


/*
|--------------------------------------------------------------------------
| VIEW BEADER
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.viewBeader')
    .forEach(
        button => {

            button.addEventListener(
                'click',
                function() {

                    const scheduleId =
                        this.dataset.id;


                    document.getElementById(
                        'viewBeaderContent'
                    ).innerHTML = `

                        <div class="text-center py-3">

                            <div
                                class="spinner-border text-info"
                            ></div>

                            <p class="mt-2 mb-0">
                                Loading Beader...
                            </p>

                        </div>

                    `;


                    fetch(
                        'view_beader.php?schedule_id=' +
                        encodeURIComponent(
                            scheduleId
                        ) +
                        '&t=' +
                        new Date().getTime()
                    )

                    .then(
                        response => {

                            if (!response.ok) {

                                throw new Error(
                                    'HTTP ' +
                                    response.status
                                );

                            }

                            return response.text();

                        }
                    )

                    .then(
                        data => {

                            document.getElementById(
                                'viewBeaderContent'
                            ).innerHTML =
                                data;

                        }
                    )

                    .catch(
                        error => {

                            document.getElementById(
                                'viewBeaderContent'
                            ).innerHTML = `

                                <div
                                    class="alert alert-danger"
                                >

                                    <i
                                        class="fa-solid fa-circle-exclamation me-2"
                                    ></i>

                                    Failed to load Beader information.

                                </div>

                            `;

                            console.error(error);

                        }
                    );

                }
            );

        }
    );

</script>


<?php

/*
|--------------------------------------------------------------------------
| AUTOMATICALLY SHOW NEWLY GENERATED QR
|--------------------------------------------------------------------------
*/

if ($generatedQR):

?>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        showQR(

            <?= json_encode(
                $generatedQR['token']
            ) ?>,

            <?= json_encode(
                $generatedQR['subject_code']
            ) ?>,

            <?= json_encode(
                $generatedQR['subject_name']
            ) ?>,

            <?= json_encode(
                $generatedQR['year_name']
            ) ?>,

            <?= json_encode(
                $generatedQR['section_name']
            ) ?>,

            <?= json_encode(
                $generatedQR['room'] ?? ''
            ) ?>,

            <?= json_encode(
                $generatedQR['beader_name']
            ) ?>

        );

    }
);

</script>

<?php endif; ?>


</body>

</html>