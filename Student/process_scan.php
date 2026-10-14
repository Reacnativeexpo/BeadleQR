
<?php

session_start();

require "../db.php";

date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json; charset=utf-8");


// =========================================================
// PHILIPPINE TIME EXPRESSIONS
// =========================================================
//
// PostgreSQL columns:
// timestamp without time zone
//
// We explicitly use Asia/Manila for attendance time.
//
// =========================================================

$PH_TIME = "CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila'";
$PH_DATE = "(CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date";
$PH_TIME_ONLY = "(CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::time";


// =========================================================
// HELPER
// =========================================================

function response($success, $message, $extra = [])
{
    echo json_encode(
        array_merge(
            [
                'status' => $success ? 'success' : 'error',
                'success' => $success,
                'message' => $message
            ],
            $extra
        )
    );

    exit();
}


// =========================================================
// AUTHENTICATION
// =========================================================

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {

    response(
        false,
        'Unauthorized. Only the assigned Beader can scan attendance.'
    );
}

$beader_id = (int) $_SESSION['user_id'];


// =========================================================
// READ QR DATA
// =========================================================

$input = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($input)) {

    response(
        false,
        'Invalid request.'
    );
}

$qrToken = trim(
    $input['qr'] ?? ''
);

if ($qrToken === '') {

    response(
        false,
        'Invalid QR code.'
    );
}


// =========================================================
// FIND ATTENDANCE SESSION
// =========================================================

try {

    $stmt = $conn->prepare("
        SELECT

            ats.id AS session_id,
            ats.schedule_id,
            ats.beader_id,
            ats.session_date,
            ats.qr_token,
            ats.qr_expires_at,
            ats.started_at AS session_started_at,
            ats.ended_at AS session_ended_at,
            ats.status AS session_status,

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

            teacher.fullname AS teacher_name,

            beader.fullname AS beader_name

        FROM public.attendance_sessions ats

        INNER JOIN public.schedules s
            ON s.id = ats.schedule_id

        INNER JOIN public.subjects sub
            ON sub.id = s.subject_id

        INNER JOIN public.year_levels yl
            ON yl.id = s.year_level_id

        INNER JOIN public.sections sec
            ON sec.id = s.section_id

        INNER JOIN public.users teacher
            ON teacher.id = s.teacher_id

        INNER JOIN public.users beader
            ON beader.id = ats.beader_id

        WHERE ats.qr_token = :qr_token

        LIMIT 1
    ");

    $stmt->execute([
        ':qr_token' => $qrToken
    ]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$session) {

        response(
            false,
            'Invalid or unrecognized QR code.'
        );
    }


} catch (PDOException $e) {

    response(
        false,
        'Database error while checking QR code.'
    );
}


// =========================================================
// CHECK BEADER
// =========================================================

if (
    (int) $session['beader_id'] !== $beader_id
) {

    response(
        false,
        'This QR code is not assigned to you.'
    );
}


// =========================================================
// CHECK SESSION DATE
// =========================================================

try {

    $dateStmt = $conn->prepare("
        SELECT

            session_date,

            {$PH_DATE} AS philippines_date,

            CASE

                WHEN session_date = {$PH_DATE}
                THEN TRUE

                ELSE FALSE

            END AS is_today

        FROM public.attendance_sessions

        WHERE id = :session_id

        LIMIT 1
    ");

    $dateStmt->execute([
        ':session_id' =>
            $session['session_id']
    ]);

    $dateResult =
        $dateStmt->fetch(PDO::FETCH_ASSOC);


    if (!$dateResult) {

        response(
            false,
            'Unable to verify attendance date.'
        );
    }


    $isToday = filter_var(
        $dateResult['is_today'],
        FILTER_VALIDATE_BOOLEAN
    );


    if (!$isToday) {

        response(
            false,
            'This attendance QR code is not for today.'
        );
    }


} catch (PDOException $e) {

    response(
        false,
        'Unable to verify attendance date.'
    );
}


// =========================================================
// FIND EXISTING TEACHER ATTENDANCE
// =========================================================

try {

    $attendanceStmt = $conn->prepare("
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

        FROM public.teacher_attendance

        WHERE session_id = :session_id

          AND teacher_id = :teacher_id

          AND beader_id = :beader_id

        ORDER BY id DESC

        LIMIT 1
    ");

    $attendanceStmt->execute([

        ':session_id' =>
            $session['session_id'],

        ':teacher_id' =>
            $session['teacher_id'],

        ':beader_id' =>
            $beader_id
    ]);

    $attendance =
        $attendanceStmt->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    response(
        false,
        'Unable to check attendance record.'
    );
}


// =========================================================
// DETERMINE TIME-IN / TIME-OUT
// =========================================================

$hasTimeIn = (
    $attendance &&
    !empty($attendance['time_in'])
);

$hasTimeOut = (
    $attendance &&
    !empty($attendance['time_out'])
);


// =========================================================
// QR EXPIRATION
// =========================================================
//
// Intentionally not used.
//
// QR remains usable according to the attendance
// session and schedule rules below.
//
// =========================================================


// =========================================================
// SESSION STATUS
// =========================================================

if (
    $session['session_status'] === 'cancelled' &&
    !$hasTimeIn
) {

    response(
        false,
        'This attendance session has been cancelled.'
    );
}


// =========================================================
// COMPLETED ATTENDANCE
// =========================================================

if (
    $hasTimeIn &&
    $hasTimeOut
) {

    response(
        false,
        'Attendance has already been completed.',
        [
            'action' => 'already_completed'
        ]
    );
}


// =========================================================
// FIRST SCAN = TIME IN
// =========================================================

if (!$hasTimeIn) {

    try {

        // =================================================
        // GET CURRENT PHILIPPINE TIME + SCHEDULE
        // =================================================

        $timeStmt = $conn->prepare("
            SELECT

                {$PH_TIME} AS philippines_now,

                {$PH_DATE} AS philippines_date,

                {$PH_TIME_ONLY} AS philippines_time,

                start_time,

                end_time

            FROM public.schedules

            WHERE id = :schedule_id

            LIMIT 1
        ");

        $timeStmt->execute([
            ':schedule_id' =>
                $session['schedule_id']
        ]);

        $timeData =
            $timeStmt->fetch(PDO::FETCH_ASSOC);


        if (!$timeData) {

            response(
                false,
                'Unable to verify schedule time.'
            );
        }


        // =================================================
        // CONVERT TIMES
        // =================================================

        $currentTimeString =
            $timeData['philippines_time'];

        $startTimeString =
            $timeData['start_time'];

        $endTimeString =
            $timeData['end_time'];


        /*
         * PostgreSQL may return:
         *
         * 13:00:00
         * 14:00:00
         *
         * We only need HH:MM:SS for comparison.
         */

        $currentTime =
            substr(
                $currentTimeString,
                0,
                8
            );

        $startTime =
            substr(
                $startTimeString,
                0,
                8
            );

        $endTime =
            substr(
                $endTimeString,
                0,
                8
            );


        // =================================================
        // DISPLAY TIMES
        // =================================================

        $startDateTime =
            DateTime::createFromFormat(
                'H:i:s',
                $startTime
            );

        $endDateTime =
            DateTime::createFromFormat(
                'H:i:s',
                $endTime
            );

        $currentDateTime =
            DateTime::createFromFormat(
                'H:i:s',
                $currentTime
            );


        if (
            !$startDateTime ||
            !$endDateTime ||
            !$currentDateTime
        ) {

            response(
                false,
                'Invalid schedule time format.'
            );
        }


        $startDisplay =
            $startDateTime->format('h:i A');

        $endDisplay =
            $endDateTime->format('h:i A');


        // =================================================
        // CHECK: BEFORE START TIME
        // =================================================
        //
        // Example:
        //
        // Schedule: 1:00 PM - 2:00 PM
        //
        // Scan: 12:59 PM
        //
        // Result: BLOCKED
        //
        // =================================================

        if (
            $currentDateTime < $startDateTime
        ) {

            response(
                false,
                "Time-in is not yet available. Your scheduled class starts at {$startDisplay}.",
                [
                    'action' => 'time_in_not_started',
                    'schedule_start' => $startDisplay,
                    'schedule_end' => $endDisplay
                ]
            );
        }


        // =================================================
        // CHECK: AT OR AFTER END TIME
        // =================================================
        //
        // Example:
        //
        // Schedule: 1:00 PM - 2:00 PM
        //
        // Scan: 2:00 PM
        //
        // Result: BLOCKED
        //
        // =================================================

        if (
            $currentDateTime >= $endDateTime
        ) {

            response(
                false,
                "Time-in is closed. Your scheduled class ended at {$endDisplay}.",
                [
                    'action' => 'time_in_closed',
                    'schedule_start' => $startDisplay,
                    'schedule_end' => $endDisplay
                ]
            );
        }


        // =================================================
        // DETERMINE PRESENT / LATE
        // =================================================
        //
        // At or before start = Present
        // After start = Late
        //
        // =================================================

        if (
            $currentDateTime <= $startDateTime
        ) {

            $attendanceStatus =
                'Present';

        } else {

            $attendanceStatus =
                'Late';
        }


        // =================================================
        // INSERT ATTENDANCE
        // =================================================

        $insertStmt = $conn->prepare("
            INSERT INTO public.teacher_attendance
            (
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

            VALUES
            (
                :teacher_id,
                :schedule_id,
                :session_id,
                :beader_id,

                {$PH_DATE},

                {$PH_TIME},

                :status,

                :qr_code,

                'beader_qr',

                {$PH_TIME}
            )

            RETURNING

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
                validated_at
        ");

        $insertStmt->execute([

            ':teacher_id' =>
                $session['teacher_id'],

            ':schedule_id' =>
                $session['schedule_id'],

            ':session_id' =>
                $session['session_id'],

            ':beader_id' =>
                $beader_id,

            ':status' =>
                $attendanceStatus,

            ':qr_code' =>
                $qrToken
        ]);


        $newAttendance =
            $insertStmt->fetch(PDO::FETCH_ASSOC);


        if (!$newAttendance) {

            response(
                false,
                'Unable to create attendance record.'
            );
        }


        // =================================================
        // UPDATE SESSION START
        // =================================================

        $updateSession = $conn->prepare("
            UPDATE public.attendance_sessions

            SET

                started_at =
                    COALESCE(
                        started_at,
                        {$PH_TIME}
                    ),

                status = 'active'

            WHERE id = :id
        ");

        $updateSession->execute([
            ':id' =>
                $session['session_id']
        ]);


        // =================================================
        // DISPLAY TIME-IN
        // =================================================

        $timeInDisplay = null;

        if (
            !empty(
                $newAttendance['time_in']
            )
        ) {

            $dateTime = new DateTime(
                $newAttendance['time_in'],
                new DateTimeZone('Asia/Manila')
            );

            $dateTime->setTimezone(
                new DateTimeZone('Asia/Manila')
            );

            $timeInDisplay =
                $dateTime->format('h:i A');
        }


        // =================================================
        // RESPONSE
        // =================================================

        response(
            true,
            'Time-in recorded successfully.',
            [

                'action' =>
                    'time_in',

                'attendance_id' =>
                    (int)
                    $newAttendance['id'],

                'teacher_id' =>
                    (int)
                    $newAttendance['teacher_id'],

                'schedule_id' =>
                    (int)
                    $newAttendance['schedule_id'],

                'session_id' =>
                    (int)
                    $newAttendance['session_id'],

                'teacher_name' =>
                    $session['teacher_name'],

                'subject_code' =>
                    $session['subject_code'],

                'subject_name' =>
                    $session['subject_name'],

                'year_name' =>
                    $session['year_name'],

                'section_name' =>
                    $session['section_name'],

                'status' =>
                    $newAttendance['status'],

                'time_in' =>
                    $newAttendance['time_in'],

                'time_in_display' =>
                    $timeInDisplay,

                'schedule_start' =>
                    $startDisplay,

                'schedule_end' =>
                    $endDisplay,

                'room' =>
                    $session['room'] ?: 'TBA'
            ]
        );


    } catch (PDOException $e) {

        response(
            false,
            'Unable to record time-in. Please try scanning again.'
        );

    } catch (Exception $e) {

        response(
            false,
            'Unable to verify schedule time.'
        );
    }
}


// =========================================================
// SECOND SCAN = TIME OUT
// =========================================================

if (
    $hasTimeIn &&
    !$hasTimeOut
) {

    try {

        // =================================================
        // UPDATE TIME-OUT
        // =================================================

        $updateAttendance = $conn->prepare("
            UPDATE public.teacher_attendance

            SET

                time_out =
                    {$PH_TIME},

                validated_at =
                    {$PH_TIME}

            WHERE id = :id

              AND time_in IS NOT NULL

              AND time_out IS NULL

            RETURNING

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
                validated_at
        ");

        $updateAttendance->execute([
            ':id' =>
                $attendance['id']
        ]);


        $completedAttendance =
            $updateAttendance->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$completedAttendance) {

            response(
                false,
                'Attendance has already been completed.'
            );
        }


        // =================================================
        // COMPLETE SESSION
        // =================================================

        $updateSession = $conn->prepare("
            UPDATE public.attendance_sessions

            SET

                ended_at =
                    {$PH_TIME},

                status = 'completed'

            WHERE id = :id
        ");

        $updateSession->execute([
            ':id' =>
                $session['session_id']
        ]);


        // =================================================
        // CALCULATE DURATION
        // =================================================

        $durationStmt = $conn->prepare("
            SELECT

                EXTRACT(
                    EPOCH FROM
                    (
                        time_out - time_in
                    )
                ) AS duration_seconds

            FROM public.teacher_attendance

            WHERE id = :id

            LIMIT 1
        ");

        $durationStmt->execute([
            ':id' =>
                $completedAttendance['id']
        ]);


        $durationData =
            $durationStmt->fetch(PDO::FETCH_ASSOC);


        $durationSeconds =
            isset(
                $durationData['duration_seconds']
            )
                ? (int) round(
                    (float)
                    $durationData['duration_seconds']
                )
                : 0;


        // =================================================
        // FORMAT DURATION
        // =================================================

        $hours =
            intdiv(
                $durationSeconds,
                3600
            );

        $minutes =
            intdiv(
                $durationSeconds % 3600,
                60
            );

        $seconds =
            $durationSeconds % 60;


        $durationParts = [];


        if ($hours > 0) {

            $durationParts[] =
                $hours . ' hr';
        }


        if ($minutes > 0) {

            $durationParts[] =
                $minutes . ' min';
        }


        if (
            $hours === 0 &&
            $minutes === 0
        ) {

            $durationParts[] =
                $seconds . ' sec';
        }


        $duration =
            implode(
                ' ',
                $durationParts
            );


        // =================================================
        // FORMAT TIME-IN
        // =================================================

        $timeInDisplay = null;

        if (
            !empty(
                $completedAttendance['time_in']
            )
        ) {

            $dateTimeIn = new DateTime(
                $completedAttendance['time_in'],
                new DateTimeZone('Asia/Manila')
            );

            $dateTimeIn->setTimezone(
                new DateTimeZone('Asia/Manila')
            );

            $timeInDisplay =
                $dateTimeIn->format('h:i A');
        }


        // =================================================
        // FORMAT TIME-OUT
        // =================================================

        $timeOutDisplay = null;

        if (
            !empty(
                $completedAttendance['time_out']
            )
        ) {

            $dateTimeOut = new DateTime(
                $completedAttendance['time_out'],
                new DateTimeZone('Asia/Manila')
            );

            $dateTimeOut->setTimezone(
                new DateTimeZone('Asia/Manila')
            );

            $timeOutDisplay =
                $dateTimeOut->format('h:i A');
        }


        // =================================================
        // RESPONSE
        // =================================================

        response(
            true,
            'Time-out recorded successfully.',
            [

                'action' =>
                    'time_out',

                'attendance_id' =>
                    (int)
                    $completedAttendance['id'],

                'teacher_id' =>
                    (int)
                    $completedAttendance['teacher_id'],

                'schedule_id' =>
                    (int)
                    $completedAttendance['schedule_id'],

                'session_id' =>
                    (int)
                    $completedAttendance['session_id'],

                'teacher_name' =>
                    $session['teacher_name'],

                'subject_code' =>
                    $session['subject_code'],

                'subject_name' =>
                    $session['subject_name'],

                'year_name' =>
                    $session['year_name'],

                'section_name' =>
                    $session['section_name'],

                'status' =>
                    $completedAttendance['status'],

                'time_in' =>
                    $completedAttendance['time_in'],

                'time_out' =>
                    $completedAttendance['time_out'],

                'time_in_display' =>
                    $timeInDisplay,

                'time_out_display' =>
                    $timeOutDisplay,

                'duration_seconds' =>
                    $durationSeconds,

                'duration' =>
                    $duration,

                'room' =>
                    $session['room'] ?: 'TBA',

                'session_status' =>
                    'completed'
            ]
        );


    } catch (PDOException $e) {

        response(
            false,
            'Unable to record time-out. Please try again.'
        );

    } catch (Exception $e) {

        response(
            false,
            'Unable to calculate attendance duration.'
        );
    }
}


// =========================================================
// FALLBACK
// =========================================================

response(
    false,
    'Unable to determine attendance action.'
);

?>

