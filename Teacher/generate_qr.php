
<?php

session_start();

require "../db.php";
require "../vendor/autoload.php";

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

// =========================================================
// PHILIPPINE TIMEZONE
// =========================================================

date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');

// =========================================================
// AUTHENTICATION
// =========================================================

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'teacher' ||
    !isset($_SESSION['user_id'])
) {
    header("Location: ../login.php");
    exit();
}

$teacher_id = (int) $_SESSION['user_id'];

// =========================================================
// GET SCHEDULE ID
// =========================================================

$schedule_id = isset($_GET['schedule_id'])
    ? (int) $_GET['schedule_id']
    : 0;

if ($schedule_id <= 0) {
    http_response_code(400);
    exit("Invalid schedule.");
}

// =========================================================
// GET SCHEDULE DETAILS
// =========================================================

try {
    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.teacher_id,
            s.subject_id,
            s.year_level_id,
            s.section_id,
            s.room,
            s.day,
            s.start_time,
            s.end_time,
            sub.subject_code,
            sub.subject_name,
            yl.year_name,
            sec.section_name,
            teacher.fullname AS teacher_name
        FROM schedules s
        INNER JOIN subjects sub
            ON sub.id = s.subject_id
        INNER JOIN year_levels yl
            ON yl.id = s.year_level_id
        INNER JOIN sections sec
            ON sec.id = s.section_id
        INNER JOIN users teacher
            ON teacher.id = s.teacher_id
        WHERE s.id = :schedule_id
          AND s.teacher_id = :teacher_id
        LIMIT 1
    ");

    $stmt->execute([
        ':schedule_id' => $schedule_id,
        ':teacher_id' => $teacher_id
    ]);

    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        http_response_code(404);
        exit(
            "Schedule not found or you are not authorized to access it."
        );
    }

} catch (PDOException $e) {
    error_log("Schedule lookup error: " . $e->getMessage());
    exit("Unable to load schedule details.");
}

// =========================================================
// FIND ASSIGNED BEADLE
// =========================================================

try {
    $beaderStmt = $conn->prepare("
        SELECT
            ba.id AS assignment_id,
            ba.year_level_id,
            ba.section_id,
            ba.beader_id,
            u.fullname AS beader_name,
            u.student_no
        FROM beader_assignments ba
        INNER JOIN users u
            ON u.id = ba.beader_id
        WHERE ba.year_level_id = :year_level_id
          AND ba.section_id = :section_id
          AND u.role = 'student'
        LIMIT 1
    ");

    $beaderStmt->execute([
        ':year_level_id' => $schedule['year_level_id'],
        ':section_id' => $schedule['section_id']
    ]);

    $beader = $beaderStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Beadle lookup error: " . $e->getMessage());
    exit("Unable to retrieve the assigned Beadle.");
}

if (!$beader) {
    exit("No Beadle is assigned to this section.");
}

$beader_id = (int) $beader['beader_id'];

// =========================================================
// HELPER: GET SESSION FOR SCHEDULE AND DATE
// =========================================================

$getSession = function () use ($conn, $schedule_id, $today) {
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
            status,
            created_at
        FROM attendance_sessions
        WHERE schedule_id = :schedule_id
          AND session_date = CAST(:session_date AS date)
        LIMIT 1
    ");

    $stmt->execute([
        ':schedule_id' => $schedule_id,
        ':session_date' => $today
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
};

// =========================================================
// FIND OR CREATE TODAY'S SESSION
// =========================================================

try {
    $session = $getSession();

    if (!$session) {
        $token = bin2hex(random_bytes(32));

        try {
            $insertStmt = $conn->prepare("
                INSERT INTO attendance_sessions (
                    schedule_id,
                    beader_id,
                    session_date,
                    qr_token,
                    qr_expires_at,
                    status
                )
                VALUES (
                    :schedule_id,
                    :beader_id,
                    CAST(:session_date AS date),
                    :qr_token,
                    NULL,
                    'active'
                )
                RETURNING
                    id,
                    schedule_id,
                    beader_id,
                    session_date,
                    qr_token,
                    qr_expires_at,
                    started_at,
                    ended_at,
                    status,
                    created_at
            ");

            $insertStmt->execute([
                ':schedule_id' => $schedule_id,
                ':beader_id' => $beader_id,
                ':session_date' => $today,
                ':qr_token' => $token
            ]);

            $session = $insertStmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $insertError) {
            // Another request may have created the session
            // between the SELECT and INSERT.
            if ($insertError->getCode() !== '23505') {
                throw $insertError;
            }

            $session = $getSession();

            if (!$session) {
                throw $insertError;
            }
        }
    }

} catch (Throwable $e) {
    error_log("Attendance session error: " . $e->getMessage());
    exit("Unable to find or create today's attendance session. Check the PHP error log.");
}

// =========================================================
// VALIDATE SESSION STATUS
// =========================================================

if ($session['status'] === 'cancelled') {
    exit("Today's attendance session has been cancelled.");
}

if (!in_array(
    $session['status'],
    ['active', 'completed'],
    true
)) {
    exit("The attendance session has an invalid status.");
}

// =========================================================
// VALIDATE BEADLE OWNERSHIP
// =========================================================

if ((int) $session['beader_id'] !== $beader_id) {
    if (
        empty($session['started_at']) &&
        $session['status'] === 'active'
    ) {
        try {
            $updateStmt = $conn->prepare("
                UPDATE attendance_sessions
                SET beader_id = :beader_id
                WHERE id = :session_id
                  AND status = 'active'
                  AND started_at IS NULL
                RETURNING
                    id,
                    schedule_id,
                    beader_id,
                    session_date,
                    qr_token,
                    qr_expires_at,
                    started_at,
                    ended_at,
                    status,
                    created_at
            ");

            $updateStmt->execute([
                ':beader_id' => $beader_id,
                ':session_id' => $session['id']
            ]);

            $updatedSession = $updateStmt->fetch(PDO::FETCH_ASSOC);

            if (!$updatedSession) {
                exit("The session ownership changed. Reload the page.");
            }

            $session = $updatedSession;

        } catch (PDOException $e) {
            error_log("Beadle assignment update error: " . $e->getMessage());
            exit("Unable to validate the assigned Beadle.");
        }
    } else {
        exit(
            "This session belongs to another Beadle and has already started."
        );
    }
}

// =========================================================
// GENERATE NEW QR TOKEN
// =========================================================

$newQr = isset($_GET['new_qr']) && $_GET['new_qr'] === '1';

if ($newQr) {
    if ($session['status'] !== 'active') {
        exit("A new QR code cannot be generated for a completed session.");
    }

    try {
        $newToken = bin2hex(random_bytes(32));

        $rotateStmt = $conn->prepare("
            UPDATE attendance_sessions
            SET qr_token = :qr_token,
                qr_expires_at = NULL
            WHERE id = :session_id
              AND status = 'active'
            RETURNING
                id,
                schedule_id,
                beader_id,
                session_date,
                qr_token,
                qr_expires_at,
                started_at,
                ended_at,
                status,
                created_at
        ");

        $rotateStmt->execute([
            ':qr_token' => $newToken,
            ':session_id' => $session['id']
        ]);

        $rotatedSession = $rotateStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rotatedSession) {
            exit("Unable to regenerate the QR code. Reload the page.");
        }

        $session = $rotatedSession;

    } catch (PDOException $e) {
        error_log("QR token regeneration error: " . $e->getMessage());
        exit("Unable to regenerate the QR code.");
    }
}

// =========================================================
// GET PHILIPPINE DATABASE TIME
// =========================================================

try {
    $timeStmt = $conn->query("
        SELECT
            CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila'
                AS database_now,
            (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date
                AS database_date
    ");

    $timeData = $timeStmt->fetch(PDO::FETCH_ASSOC);
    $databaseNow = $timeData['database_now'] ?? null;

} catch (PDOException $e) {
    error_log("Database time error: " . $e->getMessage());
    $databaseNow = null;
}

$databaseNowDisplay = 'N/A';

if ($databaseNow) {
    $timestamp = strtotime($databaseNow);

    if ($timestamp !== false) {
        $databaseNowDisplay = date('h:i:s A', $timestamp);
    }
}

// =========================================================
// QR IMAGE
// =========================================================

$qrToken = $session['qr_token'];

try {
    $builder = new Builder(
        writer: new PngWriter(),
        data: $qrToken,
        size: 350,
        margin: 15
    );

    $result = $builder->build();
    $qrImage = base64_encode($result->getString());

} catch (Throwable $e) {
    error_log("QR image generation error: " . $e->getMessage());
    exit("Unable to generate the QR image.");
}

// =========================================================
// SESSION STATUS DISPLAY
// =========================================================

if ($session['status'] === 'completed') {
    $displayStatus = 'Completed';
    $statusClass = 'completed';
} elseif ($session['status'] === 'cancelled') {
    $displayStatus = 'Cancelled';
    $statusClass = 'cancelled';
} else {
    $displayStatus = 'Active';
    $statusClass = 'active';
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
        Generate QR | BeadleQR
    </title>


    <!-- =================================================
         GOOGLE FONT
    ================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- =================================================
         FONT AWESOME
    ================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


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
        }


        body {

            font-family:
                'Poppins',
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f8fafc,
                    #f1f5f9
                );

            color:
                var(--text-dark);

            min-height: 100vh;
        }


        /* =================================================
           SIDEBAR
        ================================================== */

        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

            width: 250px;

            height: 100vh;

            background:
                var(--sidebar-bg);

            color: white;

            padding: 25px 18px;

            z-index: 1000;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                5px 12px 30px;
        }


        .brand-icon {

            width: 42px;

            height: 42px;

            border-radius: 12px;

            background:
                var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;
        }


        .brand h2 {

            font-size: 20px;

            font-weight: 700;
        }


        .panel-title {

            color: #94a3b8;

            font-size: 11px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 1px;

            padding:
                0 12px 10px;
        }


        .nav-link {

            display: flex;

            align-items: center;

            gap: 13px;

            color: #cbd5e1;

            text-decoration: none;

            padding: 12px;

            border-radius: 10px;

            margin-bottom: 5px;

            transition: .2s;
        }


        .nav-link:hover {

            background:
                var(--sidebar-hover);

            color: white;
        }


        .nav-link.active {

            background:
                var(--primary);

            color: white;
        }


        .nav-link i {

            width: 20px;

            text-align: center;
        }


        /* =================================================
           MAIN
        ================================================== */

        .main {

            margin-left: 250px;

            padding: 35px;

            min-height: 100vh;
        }


        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;
        }


        .page-title h1 {

            font-size: 28px;

            font-weight: 700;

            margin-bottom: 5px;
        }


        .page-title p {

            color:
                var(--text-muted);

            font-size: 14px;
        }


        .back-btn {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            text-decoration: none;

            background: white;

            color:
                var(--text-dark);

            border:
                1px solid #e2e8f0;

            padding:
                10px 16px;

            border-radius: 10px;

            font-size: 14px;

            font-weight: 500;
        }


        .back-btn:hover {

            border-color:
                var(--primary);

            color:
                var(--primary);
        }


        /* =================================================
           CARD
        ================================================== */

        .qr-card {

            max-width: 850px;

            margin: 0 auto;

            background: white;

            border-radius: 20px;

            padding: 30px;

            box-shadow:
                0 10px 30px
                rgba(15, 23, 42, .08);

            border:
                1px solid #e2e8f0;
        }


        .schedule-info {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

            margin-bottom: 25px;
        }


        .info-box {

            background: #f8fafc;

            border:
                1px solid #e2e8f0;

            border-radius: 12px;

            padding: 14px;
        }


        .info-label {

            color:
                var(--text-muted);

            font-size: 11px;

            font-weight: 600;

            text-transform: uppercase;

            margin-bottom: 4px;
        }


        .info-value {

            font-size: 14px;

            font-weight: 600;
        }


        /* =================================================
           QR AREA
        ================================================== */

        .qr-area {

            text-align: center;

            padding: 25px;

            border-radius: 18px;

            background:
                linear-gradient(
                    135deg,
                    #fff7ed,
                    #ffffff
                );

            border:
                1px solid #fed7aa;
        }


        .qr-wrapper {

            width: 390px;

            max-width: 100%;

            margin: 20px auto;

            padding: 20px;

            background: white;

            border-radius: 18px;

            box-shadow:
                0 10px 30px
                rgba(0,0,0,.08);
        }


        .qr-wrapper img {

            width: 100%;

            display: block;
        }


        /* =================================================
           STATUS
        ================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                7px 14px;

            border-radius: 30px;

            font-size: 13px;

            font-weight: 600;
        }


        .status.active {

            background: #dcfce7;

            color: #15803d;
        }


        .status.completed {

            background: #e0e7ff;

            color: #4338ca;
        }


        .status.cancelled {

            background: #fef3c7;

            color: #92400e;
        }


        /* =================================================
           SESSION NOTE
        ================================================== */

        .session-note {

            margin-top: 12px;

            color:
                var(--text-muted);

            font-size: 13px;
        }


        .session-note strong {

            color:
                var(--text-dark);
        }


        /* =================================================
           BUTTONS
        ================================================== */

        .actions {

            display: flex;

            justify-content: center;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 25px;
        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding:
                11px 18px;

            border-radius: 10px;

            text-decoration: none;

            border: none;

            cursor: pointer;

            font-family: inherit;

            font-size: 14px;

            font-weight: 600;

            transition: .2s;
        }


        .btn-primary {

            background:
                var(--primary);

            color: white;
        }


        .btn-primary:hover {

            background:
                var(--primary-dark);

            color: white;
        }


        .btn-secondary {

            background: #e2e8f0;

            color:
                var(--text-dark);
        }


        .btn-secondary:hover {

            background: #cbd5e1;
        }


        /* =================================================
           BEADER
        ================================================== */

        .beader-box {

            margin-top: 20px;

            padding: 16px;

            background: #f8fafc;

            border:
                1px solid #e2e8f0;

            border-radius: 12px;

            text-align: center;
        }


        .beader-box i {

            color:
                var(--primary);

            margin-right: 5px;
        }


        .beader-box strong {

            font-weight: 600;
        }


        /* =================================================
           MOBILE
        ================================================== */

        @media (max-width: 900px) {

            .sidebar {

                width: 220px;
            }


            .main {

                margin-left: 220px;

                padding: 25px;
            }


            .schedule-info {

                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        @media (max-width: 650px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;
            }


            .main {

                margin-left: 0;

                padding: 20px;
            }


            .page-header {

                align-items: flex-start;

                gap: 15px;

                flex-direction: column;
            }


            .schedule-info {

                grid-template-columns: 1fr;
            }


            .qr-card {

                padding: 18px;
            }


            .qr-area {

                padding: 15px;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">

            <i class="fa-solid fa-qrcode"></i>

        </div>

        <h2>
            BeadleQR
        </h2>

    </div>


    <div class="panel-title">
        Teacher Panel
    </div>


    <a
        href="teacher_dashboard.php"
        class="nav-link"
    >

        <i class="fa-solid fa-gauge"></i>

        <span>
            Dashboard
        </span>

    </a>


    <a
        href="my_schedules.php"
        class="nav-link active"
    >

        <i class="fa-solid fa-calendar-days"></i>

        <span>
            My Schedules
        </span>

    </a>


    <a
        href="view_attendance.php"
        class="nav-link"
    >

        <i class="fa-solid fa-clipboard-check"></i>

        <span>
            Attendance
        </span>

    </a>


    <a
        href="../logout.php"
        class="nav-link"
    >

        <i class="fa-solid fa-right-from-bracket"></i>

        <span>
            Logout
        </span>

    </a>

</aside>


<!-- =====================================================
     MAIN CONTENT
====================================================== -->

<main class="main">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                Generate Attendance QR
            </h1>

            <p>
                Scan this QR code to record teacher attendance.
            </p>

        </div>


        <a
            href="my_schedules.php"
            class="back-btn"
        >

            <i class="fa-solid fa-arrow-left"></i>

            Back to Schedules

        </a>

    </div>


    <!-- =================================================
         QR CARD
    ================================================== -->

    <div class="qr-card">


        <!-- =================================================
             SCHEDULE INFORMATION
        ================================================== -->

        <div class="schedule-info">


            <div class="info-box">

                <div class="info-label">
                    Subject
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $schedule['subject_code']
                    ) ?>

                    -

                    <?= htmlspecialchars(
                        $schedule['subject_name']
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Year Level
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $schedule['year_name']
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Section
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $schedule['section_name']
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Room
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $schedule['room'] ?: 'TBA'
                    ) ?>

                </div>

            </div>


        </div>


        <!-- =================================================
             QR DISPLAY
        ================================================== -->

        <div class="qr-area">


            <!-- STATUS -->

            <?php if (
                $displayStatus === 'Active'
            ): ?>

                <span class="status active">

                    <i class="fa-solid fa-circle-check"></i>

                    Active

                </span>

            <?php elseif (
                $displayStatus === 'Completed'
            ): ?>

                <span class="status completed">

                    <i class="fa-solid fa-check-double"></i>

                    Completed

                </span>

            <?php else: ?>

                <span class="status cancelled">

                    <i class="fa-solid fa-ban"></i>

                    Cancelled

                </span>

            <?php endif; ?>


            <!-- QR IMAGE -->

            <div class="qr-wrapper">

                <img
                    src="data:image/png;base64,<?= $qrImage ?>"
                    alt="Attendance QR Code"
                >

            </div>


            <!-- QR VALIDITY -->

            <div class="session-note">

                <strong>
                    QR Status:
                </strong>

                Valid for the entire attendance session.

            </div>


            <div class="session-note">

                <strong>
                    Database Time:
                </strong>

                <?= htmlspecialchars(
                    $databaseNowDisplay
                ) ?>

            </div>


            <!-- =================================================
                 ACTION BUTTONS
            ================================================== -->

            <div class="actions">


                <?php if (
                    $session['status'] !== 'completed' &&
                    $session['status'] !== 'cancelled'
                ): ?>

                    <a
                        href="generate_qr.php?schedule_id=<?= (int) $schedule_id ?>&new_qr=1"
                        class="btn btn-primary"
                        onclick="return confirm('Generate a new QR code for this attendance session?');"
                    >

                        <i class="fa-solid fa-rotate"></i>

                        Generate New QR

                    </a>

                <?php endif; ?>


                <a
                    href="my_schedules.php"
                    class="btn btn-secondary"
                >

                    <i class="fa-solid fa-calendar-days"></i>

                    My Schedules

                </a>


            </div>


            <!-- =================================================
                 BEADER INFORMATION
            ================================================== -->

            <div class="beader-box">

                <i class="fa-solid fa-user-check"></i>

                Assigned Beader:

                <strong>

                    <?= htmlspecialchars(
                        $beader['beader_name']
                    ) ?>

                </strong>


                <?php if (
                    !empty(
                        $beader['student_no']
                    )
                ): ?>

                    <span>

                        (
                        <?= htmlspecialchars(
                            $beader['student_no']
                        ) ?>
                        )

                    </span>

                <?php endif; ?>

            </div>


        </div>


    </div>


</main>


</body>

</html>