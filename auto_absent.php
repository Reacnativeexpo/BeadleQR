<?php

/**
 * =========================================================
 * AUTO ABSENT SYSTEM
 * =========================================================
 *
 * Purpose:
 * Automatically mark a teacher as Absent when:
 *
 * 1. The schedule is for today.
 * 2. The scheduled end time has already passed.
 * 3. No teacher attendance record exists for today.
 *
 * This file DOES NOT require a logged-in user.
 *
 * It can be executed by:
 *
 * - Windows Task Scheduler
 * - Cron Job
 * - Server scheduler
 * - student_dashboard.php
 *
 * Recommended frequency:
 * Every 1 minute
 *
 * Timezone:
 * Asia/Manila
 * =========================================================
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require __DIR__ . '/db.php';


// =========================================================
// HELPER
// =========================================================

function outputMessage(string $message): void
{
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
}


// =========================================================
// CURRENT PHILIPPINE DATE / TIME
// =========================================================

$today = date('Y-m-d');
$currentTime = date('H:i:s');
$currentDay = strtolower(date('l'));

outputMessage("Auto Absent check started.");
outputMessage("Date: {$today}");
outputMessage("Day: {$currentDay}");
outputMessage("Time: {$currentTime}");


// =========================================================
// FIND TODAY'S SCHEDULES
// =========================================================
//
// Get all schedules assigned for today's day.
//
// We do NOT require an attendance session to exist.
// If the teacher never scans, the system can still
// create an Absent attendance record.
//

$scheduleStmt = $conn->prepare("
    SELECT
        s.id AS schedule_id,
        s.teacher_id,
        s.subject_id,
        s.room,
        s.day,
        s.start_time,
        s.end_time,
        s.year_level_id,
        s.section_id
    FROM schedules s
    WHERE LOWER(TRIM(s.day)) = :current_day
");

$scheduleStmt->execute([
    'current_day' => $currentDay
]);

$schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

outputMessage(
    "Today's schedules found: " . count($schedules)
);


// =========================================================
// PROCESS EACH SCHEDULE
// =========================================================

foreach ($schedules as $schedule) {

    $scheduleId = (int)$schedule['schedule_id'];
    $teacherId = (int)$schedule['teacher_id'];
    $sectionId = (int)$schedule['section_id'];
    $yearLevelId = (int)$schedule['year_level_id'];

    $startTime = substr(
        (string)$schedule['start_time'],
        0,
        8
    );

    $endTime = substr(
        (string)$schedule['end_time'],
        0,
        8
    );


    // =====================================================
    // CHECK IF CLASS HAS ALREADY ENDED
    // =====================================================

    if ($currentTime <= $endTime) {

        outputMessage(
            "Schedule #{$scheduleId}: " .
            "Not finished yet. End time: {$endTime}"
        );

        continue;
    }


    // =====================================================
    // FIND BEADLE ASSIGNMENT
    // =====================================================

    $beadleStmt = $conn->prepare("
        SELECT
            ba.beader_id
        FROM beader_assignments ba
        WHERE ba.section_id = :section_id
          AND ba.year_level_id = :year_level_id
    ");

    $beadleStmt->execute([
        'section_id' => $sectionId,
        'year_level_id' => $yearLevelId
    ]);

    $beadles = $beadleStmt->fetchAll(PDO::FETCH_ASSOC);


    if (!$beadles) {

        outputMessage(
            "Schedule #{$scheduleId}: " .
            "No Beadle assignment found."
        );

        continue;
    }


    // =====================================================
    // PROCESS EACH BEADLE
    // =====================================================

    foreach ($beadles as $beadle) {

        $beaderId = (int)$beadle['beader_id'];


        // =================================================
        // CHECK EXISTING ATTENDANCE
        // =================================================
        //
        // IMPORTANT:
        //
        // If ANY attendance record already exists for this
        // teacher, schedule, Beadle, and date, we STOP.
        //
        // This protects:
        //
        // - Present
        // - Late
        // - Absent
        // - Manually added Remarks
        //
        // The automatic system will NEVER modify an
        // existing record.
        // =================================================

        $attendanceCheck = $conn->prepare("
            SELECT
                id,
                status,
                remarks
            FROM teacher_attendance
            WHERE teacher_id = :teacher_id
              AND schedule_id = :schedule_id
              AND beader_id = :beader_id
              AND attendance_date = :attendance_date
            LIMIT 1
        ");

        $attendanceCheck->execute([
            'teacher_id' => $teacherId,
            'schedule_id' => $scheduleId,
            'beader_id' => $beaderId,
            'attendance_date' => $today
        ]);

        $existingAttendance = $attendanceCheck->fetch(
            PDO::FETCH_ASSOC
        );


        // =================================================
        // EXISTING RECORD
        // =================================================
        //
        // DO NOTHING.
        //
        // Most importantly:
        // We DO NOT update remarks.
        // We DO NOT update status.
        // We DO NOT delete anything.
        // =================================================

        if ($existingAttendance) {

            $status = (string)$existingAttendance['status'];

            $remarkStatus = (
                isset($existingAttendance['remarks']) &&
                trim((string)$existingAttendance['remarks']) !== ''
            )
                ? 'Remark exists'
                : 'No remark';

            outputMessage(
                "Schedule #{$scheduleId}: " .
                "Attendance already exists. " .
                "Status: {$status}. " .
                "{$remarkStatus}."
            );

            continue;
        }


        // =================================================
        // FIND EXISTING ATTENDANCE SESSION
        // =================================================
        //
        // Use an existing session if available.
        //
        // If none exists, create a system session so that
        // teacher_attendance.session_id can be populated.
        // =================================================

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
            'schedule_id' => $scheduleId,
            'beader_id' => $beaderId,
            'session_date' => $today
        ]);

        $attendanceSession = $sessionStmt->fetch(
            PDO::FETCH_ASSOC
        );


        // =================================================
        // CREATE SYSTEM SESSION IF NONE EXISTS
        // =================================================

        if (!$attendanceSession) {

            $sessionToken =
                'AUTO-' .
                $today . '-' .
                $scheduleId . '-' .
                $beaderId . '-' .
                bin2hex(random_bytes(8));


            $qrExpiresAt = date(
                'Y-m-d H:i:s',
                strtotime(
                    $today . ' ' . $endTime
                )
            );


            $createSession = $conn->prepare("
                INSERT INTO attendance_sessions (
                    schedule_id,
                    beader_id,
                    session_date,
                    qr_token,
                    qr_expires_at,
                    started_at,
                    ended_at,
                    status
                )
                VALUES (
                    :schedule_id,
                    :beader_id,
                    :session_date,
                    :qr_token,
                    :qr_expires_at,
                    NULL,
                    NOW(),
                    'completed'
                )
                RETURNING id
            ");


            $createSession->execute([
                'schedule_id' => $scheduleId,
                'beader_id' => $beaderId,
                'session_date' => $today,
                'qr_token' => $sessionToken,
                'qr_expires_at' => $qrExpiresAt
            ]);


            $attendanceSession = $createSession->fetch(
                PDO::FETCH_ASSOC
            );


            if (!$attendanceSession) {

                outputMessage(
                    "Schedule #{$scheduleId}: " .
                    "Failed to create system attendance session."
                );

                continue;
            }


            outputMessage(
                "Schedule #{$scheduleId}: " .
                "System attendance session created."
            );
        }


        $sessionId = (int)$attendanceSession['id'];


        // =================================================
        // INSERT ABSENT RECORD
        // =================================================
        //
        // This is the ONLY place where the automatic system
        // creates an attendance record.
        //
        // remarks starts as NULL.
        //
        // After this record is created, the Beadle can
        // manually add a remark from the dashboard.
        //
        // IMPORTANT:
        // This file NEVER updates that remark afterward.
        // =================================================

        $insertAbsent = $conn->prepare("
            INSERT INTO teacher_attendance (
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
            )
            VALUES (
                :teacher_id,
                :schedule_id,
                :session_id,
                :beader_id,
                :attendance_date,
                NULL,
                NULL,
                'Absent',
                NULL,
                'system',
                NOW(),
                NULL
            )
        ");


        $insertAbsent->execute([
            'teacher_id' => $teacherId,
            'schedule_id' => $scheduleId,
            'session_id' => $sessionId,
            'beader_id' => $beaderId,
            'attendance_date' => $today
        ]);


        outputMessage(
            "Schedule #{$scheduleId}: " .
            "Teacher #{$teacherId} marked ABSENT " .
            "for Beadle #{$beaderId}."
        );
    }
}


// =========================================================
// COMPLETE
// =========================================================

outputMessage("Auto Absent check completed.");

?>