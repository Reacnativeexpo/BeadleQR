<?php

session_start();
require "../db.php";

// =========================================================
// TEACHER ACCESS CHECK
// =========================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    exit("Unauthorized");
}

$teacher_id = (int)($_SESSION['user_id'] ?? 0);

// =========================================================
// CHECK SCHEDULE ID
// =========================================================
if (!isset($_GET['schedule_id']) || empty($_GET['schedule_id'])) {
    exit("Invalid Schedule ID");
}

$schedule_id = (int)$_GET['schedule_id'];


// =========================================================
// GET SCHEDULE + ASSIGNED BEADER
// =========================================================
$stmt = $conn->prepare("
    SELECT
        s.id AS schedule_id,
        s.day,
        s.start_time,
        s.end_time,
        s.room,

        sub.subject_code,
        sub.subject_name,

        y.year_name,

        sec.section_name,

        ba.id AS assignment_id,
        ba.assigned_date,

        b.id AS beader_id,
        b.fullname AS beader_name,
        b.student_no AS beader_student_no,
        b.username AS beader_username,
        b.email AS beader_email,
        b.employee_id AS beader_employee_id

    FROM public.schedules s

    JOIN public.subjects sub
        ON sub.id = s.subject_id

    JOIN public.year_levels y
        ON y.id = s.year_level_id

    JOIN public.sections sec
        ON sec.id = s.section_id

    LEFT JOIN public.beader_assignments ba
        ON ba.section_id = s.section_id
       AND ba.year_level_id = s.year_level_id

    LEFT JOIN public.users b
        ON b.id = ba.beader_id
       AND b.role = 'student'

    WHERE s.id = ?
      AND s.teacher_id = ?

    LIMIT 1
");

$stmt->execute([
    $schedule_id,
    $teacher_id
]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);


// =========================================================
// SCHEDULE NOT FOUND
// =========================================================
if (!$data) {
    http_response_code(404);

    exit("
        <div style='
            font-family: Poppins, sans-serif;
            padding: 20px;
        '>
            <div style='
                background: #fee2e2;
                color: #b91c1c;
                padding: 15px 20px;
                border-radius: 12px;
            '>
                <strong>Schedule not found.</strong><br>
                You are not authorized to view this schedule.
            </div>
        </div>
    ");
}


// =========================================================
// HELPER
// =========================================================
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
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

    <title>Assigned Beader | BeadleQR</title>


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


    <style>

        /* =====================================================
           ROOT
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
            --success-light: #d1fae5;

            --danger: #ef4444;
            --danger-light: #fee2e2;

            --info: #3b82f6;
            --info-light: #dbeafe;

            --purple: #8b5cf6;
            --purple-light: #ede9fe;

        }


        /* =====================================================
           RESET
        ===================================================== */

        * {

            margin: 0;
            padding: 0;

            box-sizing: border-box;

            font-family: 'Poppins', sans-serif;

        }


        /* =====================================================
           BODY
        ===================================================== */

        body {

            background: var(--body-bg);

            min-height: 100vh;

        }


        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .content-area {

            padding: 5px;

        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .page-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 25px;

        }


        .page-title {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .page-title-icon {

            width: 45px;

            height: 45px;

            border-radius: 13px;

            background: #fff7ed;

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

        }


        .page-title-text h2 {

            font-size: 20px;

            font-weight: 700;

            color: var(--text-dark);

            margin-bottom: 2px;

        }


        .page-title-text p {

            font-size: 12px;

            color: var(--text-muted);

        }


        /* =====================================================
           BEADER MAIN CARD
        ===================================================== */

        .beader-card {

            background: white;

            border-radius: 20px;

            box-shadow: 0 4px 20px rgba(0,0,0,0.04);

            overflow: hidden;

            margin-bottom: 25px;

        }


        /* =====================================================
           CARD HEADER
        ===================================================== */

        .beader-card-header {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            padding: 25px;

            color: white;

            position: relative;

            overflow: hidden;

        }


        .beader-card-header::before {

            content: '';

            position: absolute;

            width: 180px;

            height: 180px;

            border-radius: 50%;

            background: rgba(255,255,255,0.07);

            right: -60px;

            top: -80px;

        }


        .beader-card-header::after {

            content: '';

            position: absolute;

            width: 100px;

            height: 100px;

            border-radius: 50%;

            background: rgba(255,255,255,0.05);

            right: 100px;

            bottom: -60px;

        }


        .header-content {

            position: relative;

            z-index: 2;

            display: flex;

            align-items: center;

            gap: 18px;

        }


        /* =====================================================
           BEADER AVATAR
        ===================================================== */

        .beader-avatar {

            width: 70px;

            height: 70px;

            border-radius: 18px;

            background: rgba(255,255,255,0.18);

            backdrop-filter: blur(10px);

            display: flex;

            align-items: center;

            justify-content: center;

            color: white;

            font-size: 30px;

            border: 1px solid rgba(255,255,255,0.2);

            flex-shrink: 0;

        }


        .beader-header-info h3 {

            font-size: 20px;

            font-weight: 700;

            margin-bottom: 5px;

        }


        .beader-header-info p {

            font-size: 12px;

            opacity: 0.85;

        }


        /* =====================================================
           CARD BODY
        ===================================================== */

        .beader-card-body {

            padding: 25px;

        }


        /* =====================================================
           SECTION TITLE
        ===================================================== */

        .section-title {

            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 16px;

            font-weight: 700;

            color: var(--text-dark);

            margin-bottom: 18px;

        }


        .section-title i {

            color: var(--primary);

        }


        /* =====================================================
           INFORMATION GRID
        ===================================================== */

        .info-grid {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 15px;

        }


        .info-item {

            background: #f8fafc;

            border-radius: 14px;

            padding: 16px;

            border: 1px solid #f1f5f9;

            transition: all 0.3s ease;

        }


        .info-item:hover {

            transform: translateY(-2px);

            box-shadow: 0 5px 15px rgba(0,0,0,0.04);

        }


        .info-label {

            font-size: 11px;

            color: var(--text-muted);

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            margin-bottom: 5px;

        }


        .info-value {

            font-size: 14px;

            color: var(--text-dark);

            font-weight: 600;

            word-break: break-word;

        }


        .info-icon {

            width: 35px;

            height: 35px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 10px;

            font-size: 14px;

        }


        .info-icon.orange {

            background: #fff7ed;

            color: var(--primary);

        }


        .info-icon.blue {

            background: var(--info-light);

            color: var(--info);

        }


        .info-icon.green {

            background: var(--success-light);

            color: var(--success);

        }


        .info-icon.purple {

            background: var(--purple-light);

            color: var(--purple);

        }


        /* =====================================================
           DIVIDER
        ===================================================== */

        .divider {

            height: 1px;

            background: #f1f5f9;

            margin: 25px 0;

        }


        /* =====================================================
           SCHEDULE CARD
        ===================================================== */

        .schedule-box {

            background: #f8fafc;

            border-radius: 16px;

            padding: 20px;

            border: 1px solid #f1f5f9;

        }


        .schedule-subject {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 18px;

        }


        .subject-icon {

            width: 45px;

            height: 45px;

            border-radius: 13px;

            background: var(--purple-light);

            color: var(--purple);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 19px;

        }


        .subject-info h4 {

            font-size: 15px;

            font-weight: 700;

            color: var(--text-dark);

            margin-bottom: 2px;

        }


        .subject-info span {

            font-size: 12px;

            color: var(--text-muted);

        }


        /* =====================================================
           SCHEDULE GRID
        ===================================================== */

        .schedule-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 12px;

        }


        .schedule-item {

            background: white;

            border-radius: 12px;

            padding: 13px;

            border: 1px solid #f1f5f9;

        }


        .schedule-item-label {

            font-size: 10px;

            color: var(--text-muted);

            text-transform: uppercase;

            font-weight: 600;

            margin-bottom: 4px;

        }


        .schedule-item-value {

            font-size: 13px;

            font-weight: 600;

            color: var(--text-dark);

        }


        /* =====================================================
           ASSIGNED BADGE
        ===================================================== */

        .assigned-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 12px;

            background: var(--success-light);

            color: #059669;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

        }


        /* =====================================================
           NO BEADER
        ===================================================== */

        .empty-state {

            text-align: center;

            padding: 40px 20px;

        }


        .empty-icon {

            width: 70px;

            height: 70px;

            border-radius: 20px;

            background: var(--warning-light);

            color: var(--warning);

            display: flex;

            align-items: center;

            justify-content: center;

            margin: 0 auto 15px;

            font-size: 28px;

        }


        .empty-state h4 {

            font-size: 17px;

            color: var(--text-dark);

            margin-bottom: 5px;

        }


        .empty-state p {

            font-size: 13px;

            color: var(--text-muted);

            max-width: 400px;

            margin: auto;

        }


        /* =====================================================
           ASSIGNMENT DATE
        ===================================================== */

        .assignment-date {

            display: flex;

            align-items: center;

            gap: 8px;

            color: var(--text-muted);

            font-size: 11px;

            margin-top: 18px;

        }


        .assignment-date i {

            color: var(--primary);

        }


        /* =====================================================
           FOOTER NOTE
        ===================================================== */

        .info-note {

            display: flex;

            align-items: flex-start;

            gap: 10px;

            background: #eff6ff;

            border: 1px solid #dbeafe;

            border-radius: 12px;

            padding: 13px 15px;

            margin-top: 20px;

            color: #1d4ed8;

            font-size: 11px;

            line-height: 1.5;

        }


        .info-note i {

            margin-top: 2px;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 768px) {

            .content-area {

                padding: 0;

            }

            .info-grid {

                grid-template-columns: 1fr;

            }

            .schedule-grid {

                grid-template-columns: 1fr;

            }

            .header-content {

                align-items: flex-start;

            }

            .beader-avatar {

                width: 60px;

                height: 60px;

            }

            .beader-header-info h3 {

                font-size: 17px;

            }

        }


    </style>

</head>


<body>


<div class="content-area">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">

        <div class="page-title">

            <div class="page-title-icon">

                <i class="fa-solid fa-user-check"></i>

            </div>

            <div class="page-title-text">

                <h2>Assigned Beader</h2>

                <p>View the Beader assigned to this schedule</p>

            </div>

        </div>

    </div>


    <!-- =====================================================
         MAIN BEADER CARD
    ====================================================== -->

    <div class="beader-card">


        <?php if (!empty($data['beader_id'])): ?>


            <!-- =================================================
                 BEADER HEADER
            ================================================== -->

            <div class="beader-card-header">

                <div class="header-content">


                    <div class="beader-avatar">

                        <i class="fa-solid fa-user"></i>

                    </div>


                    <div class="beader-header-info">

                        <h3>

                            <?= e($data['beader_name']); ?>

                        </h3>

                        <p>

                            <i class="fa-solid fa-circle-check"></i>

                            Assigned Beader

                        </p>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 BEADER BODY
            ================================================== -->

            <div class="beader-card-body">


                <!-- STATUS -->

                <div style="margin-bottom: 20px;">

                    <span class="assigned-badge">

                        <i class="fa-solid fa-check"></i>

                        Currently Assigned

                    </span>

                </div>


                <!-- =================================================
                     BEADER INFORMATION
                ================================================== -->

                <div class="section-title">

                    <i class="fa-solid fa-id-card"></i>

                    Beader Information

                </div>


                <div class="info-grid">


                    <!-- STUDENT NUMBER -->

                    <div class="info-item">

                        <div class="info-icon orange">

                            <i class="fa-solid fa-hashtag"></i>

                        </div>

                        <div class="info-label">
                            Student Number
                        </div>

                        <div class="info-value">

                            <?= e(
                                $data['beader_student_no']
                                ?: 'Not provided'
                            ); ?>

                        </div>

                    </div>


                    <!-- USERNAME -->

                    <div class="info-item">

                        <div class="info-icon blue">

                            <i class="fa-solid fa-user"></i>

                        </div>

                        <div class="info-label">
                            Username
                        </div>

                        <div class="info-value">

                            <?= e(
                                $data['beader_username']
                                ?: 'Not provided'
                            ); ?>

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <?php if (!empty($data['beader_email'])): ?>

                        <div class="info-item">

                            <div class="info-icon green">

                                <i class="fa-solid fa-envelope"></i>

                            </div>

                            <div class="info-label">
                                Email
                            </div>

                            <div class="info-value">

                                <?= e($data['beader_email']); ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- EMPLOYEE ID -->

                    <?php if (!empty($data['beader_employee_id'])): ?>

                        <div class="info-item">

                            <div class="info-icon purple">

                                <i class="fa-solid fa-id-badge"></i>

                            </div>

                            <div class="info-label">
                                Employee ID
                            </div>

                            <div class="info-value">

                                <?= e($data['beader_employee_id']); ?>

                            </div>

                        </div>

                    <?php endif; ?>


                </div>


                <div class="divider"></div>


                <!-- =================================================
                     SCHEDULE INFORMATION
                ================================================== -->

                <div class="section-title">

                    <i class="fa-solid fa-calendar-days"></i>

                    Schedule Information

                </div>


                <div class="schedule-box">


                    <!-- SUBJECT -->

                    <div class="schedule-subject">

                        <div class="subject-icon">

                            <i class="fa-solid fa-book-open"></i>

                        </div>


                        <div class="subject-info">

                            <h4>

                                <?= e($data['subject_code']); ?>

                            </h4>

                            <span>

                                <?= e($data['subject_name']); ?>

                            </span>

                        </div>

                    </div>


                    <!-- SCHEDULE DETAILS -->

                    <div class="schedule-grid">


                        <!-- YEAR LEVEL -->

                        <div class="schedule-item">

                            <div class="schedule-item-label">

                                Year Level

                            </div>

                            <div class="schedule-item-value">

                                <i
                                    class="fa-solid fa-layer-group"
                                    style="color:#8b5cf6;margin-right:5px;"
                                ></i>

                                <?= e($data['year_name']); ?>

                            </div>

                        </div>


                        <!-- SECTION -->

                        <div class="schedule-item">

                            <div class="schedule-item-label">

                                Section

                            </div>

                            <div class="schedule-item-value">

                                <i
                                    class="fa-solid fa-users"
                                    style="color:#3b82f6;margin-right:5px;"
                                ></i>

                                <?= e($data['section_name']); ?>

                            </div>

                        </div>


                        <!-- ROOM -->

                        <div class="schedule-item">

                            <div class="schedule-item-label">

                                Room

                            </div>

                            <div class="schedule-item-value">

                                <i
                                    class="fa-solid fa-door-open"
                                    style="color:#10b981;margin-right:5px;"
                                ></i>

                                <?= e(
                                    $data['room']
                                    ?: 'Not assigned'
                                ); ?>

                            </div>

                        </div>


                        <!-- DAY -->

                        <div class="schedule-item">

                            <div class="schedule-item-label">

                                Day

                            </div>

                            <div class="schedule-item-value">

                                <i
                                    class="fa-solid fa-calendar-day"
                                    style="color:#f97316;margin-right:5px;"
                                ></i>

                                <?= e($data['day']); ?>

                            </div>

                        </div>


                        <!-- START TIME -->

                        <div class="schedule-item">

                            <div class="schedule-item-label">

                                Start Time

                            </div>

                            <div class="schedule-item-value">

                                <i
                                    class="fa-solid fa-clock"
                                    style="color:#f59e0b;margin-right:5px;"
                                ></i>

                                <?= date(
                                    'h:i A',
                                    strtotime($data['start_time'])
                                ); ?>

                            </div>

                        </div>


                        <!-- END TIME -->

                        <div class="schedule-item">

                            <div class="schedule-item-label">

                                End Time

                            </div>

                            <div class="schedule-item-value">

                                <i
                                    class="fa-solid fa-clock"
                                    style="color:#ef4444;margin-right:5px;"
                                ></i>

                                <?= date(
                                    'h:i A',
                                    strtotime($data['end_time'])
                                ); ?>

                            </div>

                        </div>


                    </div>


                </div>


                <!-- =================================================
                     ASSIGNMENT DATE
                ================================================== -->

                <?php if (!empty($data['assigned_date'])): ?>

                    <div class="assignment-date">

                        <i class="fa-solid fa-calendar-check"></i>

                        Beader assigned on

                        <strong>

                            <?= date(
                                'F d, Y',
                                strtotime($data['assigned_date'])
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     INFORMATION NOTE
                ================================================== -->

                <div class="info-note">

                    <i class="fa-solid fa-circle-info"></i>

                    <div>

                        This Beader is assigned to the

                        <strong>
                            <?= e($data['section_name']); ?>
                        </strong>

                        section for

                        <strong>
                            <?= e($data['year_name']); ?>
                        </strong>.

                        The Beader is responsible for handling
                        attendance sessions and QR-based teacher
                        attendance for this section.

                    </div>

                </div>


            </div>


        <?php else: ?>


            <!-- =================================================
                 NO BEADER
            ================================================== -->

            <div class="beader-card-body">

                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-user-slash"></i>

                    </div>


                    <h4>
                        No Beader Assigned
                    </h4>


                    <p>

                        There is currently no Beader assigned to

                        <strong>
                            <?= e($data['section_name']); ?>
                        </strong>

                        for

                        <strong>
                            <?= e($data['year_name']); ?>
                        </strong>.

                    </p>


                </div>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>