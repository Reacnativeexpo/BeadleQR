<?php
session_start();
require "../db.php";

/*
|--------------------------------------------------------------------------
| BEADER / STUDENT QR SCANNER
|--------------------------------------------------------------------------
| First scan  = Time In
| Second scan = Time Out
|
| Uses only:
| attendance_sessions
| teacher_attendance
| schedules
| subjects
| users
| year_levels
| sections
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'danger';
$attendance = null;

/*
|--------------------------------------------------------------------------
| GET QR TOKEN
|--------------------------------------------------------------------------
|
| Example:
| scan_qr.php?token=xxxxxxxx
|
*/

$qr_token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if ($qr_token === '') {
    $message = "Invalid QR code. No QR token was provided.";
} else {

    try {

        /*
        |--------------------------------------------------------------------------
        | FIND ACTIVE QR SESSION
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                ats.id AS session_id,
                ats.schedule_id,
                ats.beader_id,
                ats.session_date,
                ats.qr_token,
                ats.qr_expires_at,
                ats.started_at,
                ats.status AS session_status,

                s.start_time,
                s.end_time,
                s.teacher_id,
                s.year_level_id,
                s.section_id,
                s.room,

                sub.subject_code,
                sub.subject_name,

                y.year_name,

                sec.section_name,

                teacher.fullname AS teacher_name,

                beader.fullname AS beader_name

            FROM attendance_sessions ats

            INNER JOIN schedules s
                ON s.id = ats.schedule_id

            INNER JOIN subjects sub
                ON sub.id = s.subject_id

            INNER JOIN year_levels y
                ON y.id = s.year_level_id

            INNER JOIN sections sec
                ON sec.id = s.section_id

            INNER JOIN users teacher
                ON teacher.id = s.teacher_id

            INNER JOIN users beader
                ON beader.id = ats.beader_id

            WHERE ats.qr_token = :qr_token
            LIMIT 1
        ");

        $stmt->execute([
            ':qr_token' => $qr_token
        ]);

        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new Exception("QR code not found.");
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK SESSION STATUS
        |--------------------------------------------------------------------------
        */

        if ($session['session_status'] !== 'active') {
            throw new Exception("This QR code is no longer active.");
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK QR EXPIRATION
        |--------------------------------------------------------------------------
        */

        if (
            !empty($session['qr_expires_at']) &&
            strtotime($session['qr_expires_at']) < time()
        ) {

            // Automatically mark expired session as completed
            $updateSession = $pdo->prepare("
                UPDATE attendance_sessions
                SET
                    status = 'completed',
                    ended_at = COALESCE(ended_at, NOW())
                WHERE id = :id
                  AND status = 'active'
            ");

            $updateSession->execute([
                ':id' => $session['session_id']
            ]);

            throw new Exception("This QR code has expired.");
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK THAT THE CURRENT USER IS THE ASSIGNED BEADER
        |--------------------------------------------------------------------------
        |
        | Your attendance_sessions table already stores beader_id.
        |
        */

        if ($session['beader_id'] != $user_id) {
            throw new Exception(
                "This QR code is assigned to another Beader."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FIND EXISTING ATTENDANCE
        |--------------------------------------------------------------------------
        */

        $attendanceStmt = $pdo->prepare("
            SELECT
                id,
                teacher_id,
                schedule_id,
                session_id,
                beader_id,
                attendance_date,
                time_in,
                time_out,
                status,
                qr_code,
                scan_source,
                validated_at,
                remarks
            FROM teacher_attendance
            WHERE session_id = :session_id
              AND teacher_id = :teacher_id
              AND schedule_id = :schedule_id
              AND beader_id = :beader_id
              AND attendance_date = :attendance_date
            ORDER BY id DESC
            LIMIT 1
        ");

        $attendanceStmt->execute([
            ':session_id'     => $session['session_id'],
            ':teacher_id'     => $session['teacher_id'],
            ':schedule_id'    => $session['schedule_id'],
            ':beader_id'      => $session['beader_id'],
            ':attendance_date'=> $session['session_date']
        ]);

        $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | FIRST SCAN = TIME IN
        |--------------------------------------------------------------------------
        */

        if (!$attendance) {

            /*
            |--------------------------------------------------------------------------
            | DETERMINE PRESENT / LATE
            |--------------------------------------------------------------------------
            |
            | Compare current time with schedule start_time.
            |
            | If scanned at or before start time:
            | Present
            |
            | If scanned after start time:
            | Late
            |
            */

            $currentTime = date('H:i:s');
            $startTime = $session['start_time'];

            $status = 'Present';

            if ($currentTime > $startTime) {
                $status = 'Late';
            }

            $insert = $pdo->prepare("
                INSERT INTO teacher_attendance (
                    teacher_id,
                    schedule_id,
                    session_id,
                    beader_id,
                    attendance_date,
                    time_in,
                    status,
                    qr_code,
                    scan_source,
                    validated_at
                )
                VALUES (
                    :teacher_id,
                    :schedule_id,
                    :session_id,
                    :beader_id,
                    CURRENT_DATE,
                    NOW(),
                    :status,
                    :qr_code,
                    'beader_qr',
                    NOW()
                )
                RETURNING id
            ");

            $insert->execute([
                ':teacher_id'  => $session['teacher_id'],
                ':schedule_id' => $session['schedule_id'],
                ':session_id'  => $session['session_id'],
                ':beader_id'   => $session['beader_id'],
                ':status'      => $status,
                ':qr_code'     => $qr_token
            ]);

            $attendanceId = $insert->fetchColumn();

            /*
            |--------------------------------------------------------------------------
            | UPDATE SESSION STARTED_AT
            |--------------------------------------------------------------------------
            */

            $updateSession = $pdo->prepare("
                UPDATE attendance_sessions
                SET started_at = COALESCE(started_at, NOW())
                WHERE id = :id
            ");

            $updateSession->execute([
                ':id' => $session['session_id']
            ]);

            $messageType = 'success';

            if ($status === 'Late') {
                $message = "Time In recorded successfully. You are marked LATE.";
            } else {
                $message = "Time In recorded successfully. You are marked PRESENT.";
            }

            /*
            |--------------------------------------------------------------------------
            | LOAD ATTENDANCE
            |--------------------------------------------------------------------------
            */

            $attendanceStmt->execute([
                ':session_id'      => $session['session_id'],
                ':teacher_id'      => $session['teacher_id'],
                ':schedule_id'     => $session['schedule_id'],
                ':beader_id'       => $session['beader_id'],
                ':attendance_date' => $session['session_date']
            ]);

            $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

        }

        /*
        |--------------------------------------------------------------------------
        | SECOND SCAN = TIME OUT
        |--------------------------------------------------------------------------
        */

        elseif (!empty($attendance['time_in']) && empty($attendance['time_out'])) {

            $update = $pdo->prepare("
                UPDATE teacher_attendance
                SET
                    time_out = NOW(),
                    validated_at = NOW()
                WHERE id = :id
                  AND time_out IS NULL
                RETURNING id
            ");

            $update->execute([
                ':id' => $attendance['id']
            ]);

            $updated = $update->fetchColumn();

            if ($updated) {

                /*
                |--------------------------------------------------------------------------
                | END ATTENDANCE SESSION
                |--------------------------------------------------------------------------
                */

                $updateSession = $pdo->prepare("
                    UPDATE attendance_sessions
                    SET
                        ended_at = NOW(),
                        status = 'completed'
                    WHERE id = :id
                      AND status = 'active'
                ");

                $updateSession->execute([
                    ':id' => $session['session_id']
                ]);

                $messageType = 'success';
                $message = "Time Out recorded successfully. Attendance completed.";

                /*
                |--------------------------------------------------------------------------
                | RELOAD ATTENDANCE
                |--------------------------------------------------------------------------
                */

                $attendanceStmt->execute([
                    ':session_id'      => $session['session_id'],
                    ':teacher_id'      => $session['teacher_id'],
                    ':schedule_id'     => $session['schedule_id'],
                    ':beader_id'       => $session['beader_id'],
                    ':attendance_date' => $session['session_date']
                ]);

                $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

            } else {
                $messageType = 'warning';
                $message = "Time Out has already been recorded.";
            }

        }

        /*
        |--------------------------------------------------------------------------
        | ALREADY COMPLETED
        |--------------------------------------------------------------------------
        */

        elseif (
            !empty($attendance['time_in']) &&
            !empty($attendance['time_out'])
        ) {

            $messageType = 'info';
            $message = "Attendance for this subject has already been completed.";

        }

    } catch (Exception $e) {

        $message = $e->getMessage();

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

    <title>QR Attendance</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --body-bg: #f1f5f9;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .attendance-card {
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo i {
            color: var(--primary);
            font-size: 42px;
        }

        .logo h1 {
            margin-top: 10px;
            color: var(--text-dark);
            font-size: 25px;
            font-weight: 700;
        }

        .logo p {
            color: var(--text-muted);
            font-size: 13px;
        }

        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .alert-info {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .subject-box {
            background: #f8fafc;
            border-radius: 15px;
            padding: 22px;
            margin-bottom: 25px;
        }

        .subject-code {
            display: inline-block;
            background: #fff7ed;
            color: var(--primary-dark);
            padding: 5px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subject-name {
            font-size: 21px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .detail {
            font-size: 13px;
            color: var(--text-muted);
        }

        .detail strong {
            display: block;
            color: var(--text-dark);
            margin-top: 3px;
        }

        .attendance-result {
            border-top: 1px solid #e2e8f0;
            padding-top: 25px;
        }

        .attendance-result h3 {
            color: var(--text-dark);
            margin-bottom: 18px;
            font-size: 17px;
        }

        .time-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .time-box {
            padding: 20px;
            border-radius: 14px;
            background: #f8fafc;
            text-align: center;
        }

        .time-box i {
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .time-label {
            font-size: 12px;
            color: var(--text-muted);
        }

        .time-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 4px;
        }

        .status {
            text-align: center;
            margin-top: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 9px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
        }

        .status-present {
            background: #dcfce7;
            color: #15803d;
        }

        .status-late {
            background: #fef3c7;
            color: #b45309;
        }

        .back-btn {
            display: block;
            width: 100%;
            text-align: center;
            margin-top: 25px;
            padding: 12px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back-btn:hover {
            background: var(--primary-dark);
        }

        @media(max-width:600px) {

            .attendance-card {
                padding: 25px 20px;
            }

            .details,
            .time-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="attendance-card">

    <div class="logo">

        <i class="fa-solid fa-qrcode"></i>

        <h1>BeadleQR</h1>

        <p>Teacher Attendance System</p>

    </div>

    <?php if ($message !== ''): ?>

        <div class="alert alert-<?php echo e($messageType); ?>">

            <?php if ($messageType === 'success'): ?>

                <i class="fa-solid fa-circle-check"></i>

            <?php elseif ($messageType === 'warning'): ?>

                <i class="fa-solid fa-triangle-exclamation"></i>

            <?php elseif ($messageType === 'info'): ?>

                <i class="fa-solid fa-circle-info"></i>

            <?php else: ?>

                <i class="fa-solid fa-circle-exclamation"></i>

            <?php endif; ?>

            <?php echo e($message); ?>

        </div>

    <?php endif; ?>


    <?php if (isset($session)): ?>

        <div class="subject-box">

            <?php if (!empty($session['subject_code'])): ?>

                <span class="subject-code">

                    <?php echo e($session['subject_code']); ?>

                </span>

            <?php endif; ?>

            <div class="subject-name">

                <?php echo e($session['subject_name']); ?>

            </div>

            <div class="details">

                <div class="detail">
                    Teacher
                    <strong>
                        <?php echo e($session['teacher_name']); ?>
                    </strong>
                </div>

                <div class="detail">
                    Beader
                    <strong>
                        <?php echo e($session['beader_name']); ?>
                    </strong>
                </div>

                <div class="detail">
                    Year Level
                    <strong>
                        <?php echo e($session['year_name']); ?>
                    </strong>
                </div>

                <div class="detail">
                    Section
                    <strong>
                        <?php echo e($session['section_name']); ?>
                    </strong>
                </div>

                <div class="detail">
                    Room
                    <strong>
                        <?php echo e($session['room'] ?: 'N/A'); ?>
                    </strong>
                </div>

                <div class="detail">
                    Schedule
                    <strong>
                        <?php
                        echo e(
                            date('h:i A', strtotime($session['start_time']))
                            . ' - ' .
                            date('h:i A', strtotime($session['end_time']))
                        );
                        ?>
                    </strong>
                </div>

            </div>

        </div>


        <?php if ($attendance): ?>

            <div class="attendance-result">

                <h3>
                    <i class="fa-solid fa-clipboard-check"></i>
                    Attendance Record
                </h3>

                <div class="time-grid">

                    <div class="time-box">

                        <i class="fa-solid fa-right-to-bracket"></i>

                        <div class="time-label">
                            Time In
                        </div>

                        <div class="time-value">

                            <?php
                            echo !empty($attendance['time_in'])
                                ? date('h:i A', strtotime($attendance['time_in']))
                                : '--';
                            ?>

                        </div>

                    </div>

                    <div class="time-box">

                        <i class="fa-solid fa-right-from-bracket"></i>

                        <div class="time-label">
                            Time Out
                        </div>

                        <div class="time-value">

                            <?php
                            echo !empty($attendance['time_out'])
                                ? date('h:i A', strtotime($attendance['time_out']))
                                : '--';
                            ?>

                        </div>

                    </div>

                </div>

                <div class="status">

                    <?php
                    $statusClass =
                        strtolower($attendance['status']) === 'late'
                        ? 'status-late'
                        : 'status-present';
                    ?>

                    <span class="status-badge <?php echo $statusClass; ?>">

                        <?php if ($attendance['status'] === 'Present'): ?>

                            <i class="fa-solid fa-circle-check"></i>

                        <?php else: ?>

                            <i class="fa-solid fa-clock"></i>

                        <?php endif; ?>

                        <?php echo e($attendance['status']); ?>

                    </span>

                </div>

            </div>

        <?php endif; ?>

    <?php endif; ?>


    <a href="dashboard.php" class="back-btn">

        <i class="fa-solid fa-house"></i>

        Back to Dashboard

    </a>

</div>

</body>
</html>