<?php
session_start();
require "../db.php";


// =========================================================
// ADMIN ACCESS CHECK
// =========================================================

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}


// =========================================================
// GET TEACHER ID
// =========================================================

$teacher_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$teacher_id) {
    header("Location: teachers.php");
    exit();
}


// =========================================================
// GET TEACHER
// =========================================================

$stmt = $conn->prepare("
    SELECT
        id,
        employee_id,
        fullname,
        email,
        username
    FROM public.users
    WHERE id = :id
      AND role = 'teacher'
    LIMIT 1
");

$stmt->execute([
    'id' => $teacher_id
]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    header("Location: teachers.php");
    exit();
}


// =========================================================
// VARIABLES
// =========================================================

$message = "";
$success = "";

$selected_year =
    $_POST['year_level_id']
    ?? $_GET['year_level_id']
    ?? '';

$selected_subject =
    $_POST['subject_id']
    ?? '';

$selected_section =
    $_POST['section_id']
    ?? '';


// =========================================================
// SAVE SCHEDULE
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_schedule'])
) {

    $subject_id = filter_input(
        INPUT_POST,
        'subject_id',
        FILTER_VALIDATE_INT
    );

    $year_level_id = filter_input(
        INPUT_POST,
        'year_level_id',
        FILTER_VALIDATE_INT
    );

    $section_id = filter_input(
        INPUT_POST,
        'section_id',
        FILTER_VALIDATE_INT
    );

    $room = trim($_POST['room'] ?? '');
    $day = trim($_POST['day'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';


    // ---------------------------------------------------------
    // BASIC VALIDATION
    // ---------------------------------------------------------

    if (
        !$subject_id ||
        !$year_level_id ||
        !$section_id ||
        $room === '' ||
        $day === '' ||
        $start_time === '' ||
        $end_time === ''
    ) {

        $message =
            "Please complete all schedule fields.";

    } elseif ($start_time >= $end_time) {

        $message =
            "End time must be later than start time.";

    } else {

        try {

            // -----------------------------------------------------
            // CHECK YEAR LEVEL
            // -----------------------------------------------------

            $checkYear = $conn->prepare("
                SELECT id
                FROM public.year_levels
                WHERE id = :id
                LIMIT 1
            ");

            $checkYear->execute([
                'id' => $year_level_id
            ]);

            if (!$checkYear->fetch(PDO::FETCH_ASSOC)) {

                throw new Exception(
                    "Invalid year level."
                );
            }


            // -----------------------------------------------------
            // CHECK SUBJECT BELONGS TO YEAR LEVEL
            // -----------------------------------------------------

            $checkSubject = $conn->prepare("
                SELECT id
                FROM public.subjects
                WHERE id = :subject_id
                  AND year_level_id = :year_level_id
                LIMIT 1
            ");

            $checkSubject->execute([
                'subject_id' =>
                    $subject_id,

                'year_level_id' =>
                    $year_level_id
            ]);

            if (!$checkSubject->fetch(PDO::FETCH_ASSOC)) {

                throw new Exception(
                    "Invalid subject for the selected year level."
                );
            }


            // -----------------------------------------------------
            // CHECK SECTION BELONGS TO YEAR LEVEL
            // -----------------------------------------------------

            $checkSection = $conn->prepare("
                SELECT id
                FROM public.sections
                WHERE id = :section_id
                  AND year_level_id = :year_level_id
                LIMIT 1
            ");

            $checkSection->execute([
                'section_id' =>
                    $section_id,

                'year_level_id' =>
                    $year_level_id
            ]);

            if (!$checkSection->fetch(PDO::FETCH_ASSOC)) {

                throw new Exception(
                    "Invalid section for the selected year level."
                );
            }


            // -----------------------------------------------------
            // CHECK SAME SUBJECT OVERLAP
            // -----------------------------------------------------

            $checkDuplicate = $conn->prepare("
                SELECT
                    id
                FROM public.schedules
                WHERE year_level_id = :year_level_id
                  AND subject_id = :subject_id
                  AND section_id = :section_id
                  AND day = :day
                  AND start_time < :end_time
                  AND end_time > :start_time
                LIMIT 1
            ");

            $checkDuplicate->execute([
                'year_level_id' =>
                    $year_level_id,

                'subject_id' =>
                    $subject_id,

                'section_id' =>
                    $section_id,

                'day' =>
                    $day,

                'start_time' =>
                    $start_time,

                'end_time' =>
                    $end_time
            ]);

            if ($checkDuplicate->fetch(PDO::FETCH_ASSOC)) {

                throw new Exception(
                    "This subject already has a schedule that overlaps on {$day}."
                );
            }


            // -----------------------------------------------------
            // CHECK SECTION TIME CONFLICT
            // -----------------------------------------------------

            $checkSectionConflict = $conn->prepare("
                SELECT
                    s.id,
                    sub.subject_code,
                    sub.subject_name
                FROM public.schedules s

                INNER JOIN public.subjects sub
                    ON sub.id = s.subject_id

                WHERE s.section_id = :section_id
                  AND s.day = :day
                  AND s.start_time < :end_time
                  AND s.end_time > :start_time

                LIMIT 1
            ");

            $checkSectionConflict->execute([
                'section_id' =>
                    $section_id,

                'day' =>
                    $day,

                'start_time' =>
                    $start_time,

                'end_time' =>
                    $end_time
            ]);

            $sectionConflict =
                $checkSectionConflict->fetch(PDO::FETCH_ASSOC);


            if ($sectionConflict) {

                throw new Exception(
                    "The selected section already has "
                    . $sectionConflict['subject_name']
                    . " scheduled on {$day} at an overlapping time."
                );
            }


            // -----------------------------------------------------
            // CHECK TEACHER TIME CONFLICT
            // -----------------------------------------------------

            $checkTeacherConflict = $conn->prepare("
                SELECT
                    s.id,
                    sub.subject_code,
                    sub.subject_name
                FROM public.schedules s

                INNER JOIN public.subjects sub
                    ON sub.id = s.subject_id

                WHERE s.teacher_id = :teacher_id
                  AND s.day = :day
                  AND s.start_time < :end_time
                  AND s.end_time > :start_time

                LIMIT 1
            ");

            $checkTeacherConflict->execute([
                'teacher_id' =>
                    $teacher_id,

                'day' =>
                    $day,

                'start_time' =>
                    $start_time,

                'end_time' =>
                    $end_time
            ]);

            $teacherConflict =
                $checkTeacherConflict->fetch(PDO::FETCH_ASSOC);


            if ($teacherConflict) {

                throw new Exception(
                    "This teacher already has "
                    . $teacherConflict['subject_name']
                    . " scheduled on {$day} at an overlapping time."
                );
            }


            // -----------------------------------------------------
            // INSERT SCHEDULE
            // -----------------------------------------------------

            $save = $conn->prepare("
                INSERT INTO public.schedules
                (
                    teacher_id,
                    subject_id,
                    year_level_id,
                    section_id,
                    room,
                    day,
                    start_time,
                    end_time
                )
                VALUES
                (
                    :teacher_id,
                    :subject_id,
                    :year_level_id,
                    :section_id,
                    :room,
                    :day,
                    :start_time,
                    :end_time
                )
                RETURNING id
            ");

            $save->execute([
                'teacher_id' =>
                    $teacher_id,

                'subject_id' =>
                    $subject_id,

                'year_level_id' =>
                    $year_level_id,

                'section_id' =>
                    $section_id,

                'room' =>
                    $room,

                'day' =>
                    $day,

                'start_time' =>
                    $start_time,

                'end_time' =>
                    $end_time
            ]);

            $new_schedule_id =
                $save->fetchColumn();


            $_SESSION['toast_message'] =
                "Schedule assigned successfully.";

            $_SESSION['toast_type'] =
                "success";


            header(
                "Location: assign_schedule.php?id="
                . $teacher_id
                . "&schedule_id="
                . $new_schedule_id
            );

            exit();


        } catch (PDOException $e) {

            $message =
                "Unable to save schedule. Please try again.";

        } catch (Exception $e) {

            $message =
                $e->getMessage();
        }
    }
}


// =========================================================
// SELECTED SCHEDULE
// =========================================================

$selected_schedule_id = filter_input(
    INPUT_GET,
    'schedule_id',
    FILTER_VALIDATE_INT
);


// =========================================================
// ASSIGN BEADER
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['assign_beader'])
) {

    $schedule_id = filter_input(
        INPUT_POST,
        'schedule_id',
        FILTER_VALIDATE_INT
    );

    $beader_id = filter_input(
        INPUT_POST,
        'beader_id',
        FILTER_VALIDATE_INT
    );


    if (
        !$schedule_id ||
        !$beader_id
    ) {

        $message =
            "Please select a student.";

    } else {

        try {

            $conn->beginTransaction();


            // -----------------------------------------------------
            // CHECK SCHEDULE
            // -----------------------------------------------------

            $checkSchedule = $conn->prepare("
                SELECT
                    s.id,
                    s.year_level_id,
                    s.section_id
                FROM public.schedules s
                WHERE s.id = :schedule_id
                  AND s.teacher_id = :teacher_id
                LIMIT 1
            ");

            $checkSchedule->execute([
                'schedule_id' =>
                    $schedule_id,

                'teacher_id' =>
                    $teacher_id
            ]);

            $scheduleCheck =
                $checkSchedule->fetch(PDO::FETCH_ASSOC);


            if (!$scheduleCheck) {

                throw new Exception(
                    "Invalid schedule."
                );
            }


            // -----------------------------------------------------
            // CHECK STUDENT
            // -----------------------------------------------------

            $checkStudent = $conn->prepare("
                SELECT
                    id,
                    fullname,
                    student_no,
                    year_level_id,
                    section_id
                FROM public.users
                WHERE id = :student_id
                  AND role = 'student'
                LIMIT 1
            ");

            $checkStudent->execute([
                'student_id' =>
                    $beader_id
            ]);

            $student =
                $checkStudent->fetch(PDO::FETCH_ASSOC);


            if (!$student) {

                throw new Exception(
                    "Selected beader is not a valid student."
                );
            }


            // -----------------------------------------------------
            // CHECK STUDENT BELONGS TO SECTION
            // -----------------------------------------------------

            if (
                (int)$student['year_level_id']
                !==
                (int)$scheduleCheck['year_level_id']
                ||
                (int)$student['section_id']
                !==
                (int)$scheduleCheck['section_id']
            ) {

                throw new Exception(
                    "The selected student does not belong to the schedule's year level and section."
                );
            }


            // -----------------------------------------------------
            // ASSIGN / REPLACE BEADER
            // -----------------------------------------------------

            $assignBeader = $conn->prepare("
                INSERT INTO public.beader_assignments
                (
                    year_level_id,
                    section_id,
                    beader_id,
                    assigned_by,
                    assigned_date
                )
                VALUES
                (
                    :year_level_id,
                    :section_id,
                    :beader_id,
                    :assigned_by,
                    CURRENT_DATE
                )

                ON CONFLICT (section_id)

                DO UPDATE SET

                    year_level_id =
                        EXCLUDED.year_level_id,

                    beader_id =
                        EXCLUDED.beader_id,

                    assigned_by =
                        EXCLUDED.assigned_by,

                    assigned_date =
                        CURRENT_DATE
            ");

            $assignBeader->execute([
                'year_level_id' =>
                    $scheduleCheck['year_level_id'],

                'section_id' =>
                    $scheduleCheck['section_id'],

                'beader_id' =>
                    $beader_id,

                'assigned_by' =>
                    $_SESSION['user_id']
            ]);


            $conn->commit();


            $success =
                htmlspecialchars(
                    $student['fullname']
                )
                .
                " is now the Beader for this section.";


            $selected_schedule_id =
                $schedule_id;


        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }


            if ($e->getCode() === '23505') {

                $message =
                    "A Beader is already assigned to this section.";

            } else {

                $message =
                    "Unable to assign Beader. Please try again.";
            }


        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $message =
                $e->getMessage();
        }
    }
}


// =========================================================
// YEAR LEVELS
// =========================================================

$years = $conn->query("
    SELECT
        id,
        year_name
    FROM public.year_levels
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// SUBJECTS
// =========================================================

$subjects = [];

if ($selected_year) {

    $stmt = $conn->prepare("
        SELECT
            id,
            subject_code,
            subject_name
        FROM public.subjects
        WHERE year_level_id = :year_level_id
        ORDER BY subject_name ASC
    ");

    $stmt->execute([
        'year_level_id' =>
            $selected_year
    ]);

    $subjects =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// =========================================================
// SECTIONS
// =========================================================

$sections = [];

if ($selected_year) {

    $stmt = $conn->prepare("
        SELECT
            id,
            section_name
        FROM public.sections
        WHERE year_level_id = :year_level_id
        ORDER BY section_name ASC
    ");

    $stmt->execute([
        'year_level_id' =>
            $selected_year
    ]);

    $sections =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// =========================================================
// OCCUPIED SECTIONS
// =========================================================

$occupiedSections = [];


// =========================================================
// TEACHER SCHEDULES
// =========================================================

$scheduleStmt = $conn->prepare("
    SELECT
        s.id,
        s.year_level_id,
        s.section_id,
        s.room,
        s.day,
        s.start_time,
        s.end_time,

        sub.subject_code,
        sub.subject_name,

        yl.year_name,

        sec.section_name

    FROM public.schedules s

    JOIN public.subjects sub
        ON sub.id = s.subject_id

    JOIN public.year_levels yl
        ON yl.id = s.year_level_id

    JOIN public.sections sec
        ON sec.id = s.section_id

    WHERE s.teacher_id = :teacher_id

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
        s.start_time
");

$scheduleStmt->execute([
    'teacher_id' =>
        $teacher_id
]);

$teacherSchedules =
    $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// SELECTED SCHEDULE DETAILS
// =========================================================

$selectedSchedule = null;

if ($selected_schedule_id) {

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.year_level_id,
            s.section_id,
            s.room,
            s.day,
            s.start_time,
            s.end_time,

            sub.subject_code,
            sub.subject_name,

            yl.year_name,

            sec.section_name

        FROM public.schedules s

        JOIN public.subjects sub
            ON sub.id = s.subject_id

        JOIN public.year_levels yl
            ON yl.id = s.year_level_id

        JOIN public.sections sec
            ON sec.id = s.section_id

        WHERE s.id = :schedule_id
          AND s.teacher_id = :teacher_id

        LIMIT 1
    ");

    $stmt->execute([
        'schedule_id' =>
            $selected_schedule_id,

        'teacher_id' =>
            $teacher_id
    ]);

    $selectedSchedule =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$selectedSchedule) {

        $selected_schedule_id = null;
    }
}


// =========================================================
// STUDENTS FOR SELECTED SECTION
// =========================================================

$beaderStudents = [];

if ($selectedSchedule) {

    $stmt = $conn->prepare("
        SELECT
            id,
            student_no,
            fullname
        FROM public.users
        WHERE role = 'student'
          AND year_level_id = :year_level_id
          AND section_id = :section_id
        ORDER BY fullname ASC
    ");

    $stmt->execute([
        'year_level_id' =>
            $selectedSchedule['year_level_id'],

        'section_id' =>
            $selectedSchedule['section_id']
    ]);

    $beaderStudents =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// =========================================================
// CURRENT BEADER
// =========================================================

$currentBeader = null;

if ($selectedSchedule) {

    $stmt = $conn->prepare("
        SELECT
            ba.id,
            ba.assigned_date,

            u.id AS student_id,
            u.fullname,
            u.student_no

        FROM public.beader_assignments ba

        JOIN public.users u
            ON u.id = ba.beader_id

        WHERE ba.section_id = :section_id

        LIMIT 1
    ");

    $stmt->execute([
        'section_id' =>
            $selectedSchedule['section_id']
    ]);

    $currentBeader =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


// =========================================================
// TOAST
// =========================================================

if (isset($_SESSION['toast_message'])) {

    $toast_message =
        $_SESSION['toast_message'];

    $toast_type =
        $_SESSION['toast_type']
        ?? 'success';

    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);

} else {

    $toast_message = "";
    $toast_type = "success";
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Assign Schedule -
        <?= htmlspecialchars($teacher['fullname']); ?>
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
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
            --primary-light: #fb923c;
            --accent: #fbbf24;

        }


        body {

            background: #f8fafc;

            font-family: 'Inter', sans-serif;

        }


        .container {

            max-width: 1400px;

        }


        .card {

            border: none;

            border-radius: 20px;

            overflow: hidden;

            box-shadow:
                0 15px 35px
                rgba(0,0,0,.08);

        }


        .form-header {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: #fff;

            padding: 18px 25px;

        }


        .list-header {

            background:
                linear-gradient(
                    135deg,
                    #1f2937,
                    #111827
                );

            color: #fff;

            padding: 18px 25px;

        }


        .section-header {

            background:
                linear-gradient(
                    135deg,
                    #f59e0b,
                    #d97706
                );

            color: #fff;

            padding: 18px 25px;

        }


        .form-control,
        .form-select {

            border-radius: 12px;

            padding: 12px;

            border:
                1px solid #d1d5db;

        }


        .form-control:focus,
        .form-select:focus {

            border-color:
                var(--primary);

            box-shadow:
                0 0 0 .2rem
                rgba(249,115,22,.20);

        }


        label {

            font-weight: 600;

            color: #374151;

            margin-bottom: 6px;

        }


        .btn-save {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            border: none;

            color: #fff;

            border-radius: 10px;

            padding: 10px 22px;

            font-weight: 600;

            transition:
                all 0.3s ease;

        }


        .btn-save:hover {

            color: #fff;

            transform:
                translateY(-2px);

            box-shadow:
                0 5px 15px
                rgba(249,115,22,.3);

        }


        .btn-beader {

            background:
                linear-gradient(
                    135deg,
                    #f59e0b,
                    #d97706
                );

            border: none;

            color: #fff;

            border-radius: 10px;

            padding: 10px 22px;

            font-weight: 600;

        }


        .btn-beader:hover {

            color: #fff;

        }


        .btn-back {

            border-radius: 10px;

            padding: 10px 22px;

        }


        .teacher-info {

            background: #fff7ed;

            border:
                1px solid #fed7aa;

            border-radius: 15px;

            padding: 18px;

        }


        .teacher-icon {

            width: 50px;

            height: 50px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: #fff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 22px;

        }


        .schedule-card {

            border:
                1px solid #e5e7eb;

            border-radius: 15px;

            padding: 18px;

            margin-bottom: 15px;

            transition:
                all .2s ease;

            background: #fff;

        }


        .schedule-card:hover {

            border-color:
                var(--primary);

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(0,0,0,.07);

        }


        .schedule-card.selected {

            border:
                2px solid var(--primary);

            background: #fff7ed;

        }


        .subject-code {

            display: inline-block;

            background: #fff7ed;

            color:
                var(--primary-dark);

            padding: 6px 10px;

            border-radius: 8px;

            font-weight: 700;

            font-size: 13px;

        }


        .info-badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            background: #f3f4f6;

            padding: 6px 10px;

            border-radius: 8px;

            color: #374151;

            font-size: 13px;

        }


        .current-beader {

            background: #ecfdf5;

            border:
                1px solid #a7f3d0;

            border-radius: 15px;

            padding: 18px;

        }


        .current-beader-icon {

            width: 45px;

            height: 45px;

            border-radius: 50%;

            background: #10b981;

            color: #fff;

            display: flex;

            align-items: center;

            justify-content: center;

        }


        .empty-state {

            text-align: center;

            padding: 40px 20px;

            color: #6b7280;

        }


        .empty-state i {

            font-size: 40px;

            margin-bottom: 12px;

            color: #d1d5db;

        }


        .toast-container {

            position: fixed;

            top: 20px;

            right: 20px;

            z-index: 9999;

        }


        .custom-toast {

            min-width: 320px;

            max-width: 400px;

            border-radius: 16px;

            border: none;

            box-shadow:
                0 10px 40px
                rgba(0,0,0,.15);

            overflow: hidden;

            animation:
                slideInRight
                .4s ease-out;

        }


        .custom-toast.toast-success {

            background:
                linear-gradient(
                    135deg,
                    #10b981,
                    #059669
                );

            color: #fff;

        }


        .custom-toast.toast-error {

            background:
                linear-gradient(
                    135deg,
                    #ef4444,
                    #dc2626
                );

            color: #fff;

        }


        .toast-body {

            padding: 16px 20px;

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .toast-icon {

            width: 36px;

            height: 36px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.2);

            display: flex;

            align-items: center;

            justify-content: center;

        }


        .toast-content {

            flex: 1;

        }


        .toast-title {

            font-weight: 700;

            font-size: 15px;

        }


        .toast-message {

            font-size: 13px;

            opacity: .95;

        }


        .toast-close {

            background: none;

            border: none;

            color: #fff;

            cursor: pointer;

            opacity: .8;

        }


        .toast-progress {

            height: 3px;

            background:
                rgba(255,255,255,.3);

        }


        .toast-progress-bar {

            height: 100%;

            background:
                rgba(255,255,255,.7);

            animation:
                progressShrink
                4s linear forwards;

        }


        @keyframes slideInRight {

            from {

                transform:
                    translateX(100%);

                opacity: 0;

            }

            to {

                transform:
                    translateX(0);

                opacity: 1;

            }

        }


        @keyframes progressShrink {

            from {
                width: 100%;
            }

            to {
                width: 0%;
            }

        }


        @media (max-width: 576px) {

            .container {

                margin-top:
                    20px !important;

            }

            .custom-toast {

                min-width: auto;

                width:
                    calc(100vw - 40px);

            }

            .toast-container {

                left: 20px;

                right: 20px;

            }

        }

    </style>

</head>


<body>


<div
    class="toast-container"
    id="toastContainer"
></div>


<div class="container mt-5 mb-5">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="card mb-4">

        <div class="form-header">

            <div
                class="d-flex justify-content-between align-items-center flex-wrap gap-3"
            >

                <div>

                    <h4 class="mb-1">

                        <i
                            class="fa-solid fa-calendar-plus me-2"
                        ></i>

                        Assign Schedule

                    </h4>

                    <div class="opacity-75">

                        Teacher Schedule Management

                    </div>

                </div>


                <a
                    href="add_teacher.php"
                    class="btn btn-light btn-back"
                >

                    <i
                        class="fa-solid fa-arrow-left me-1"
                    ></i>

                    Back to Teachers

                </a>

            </div>

        </div>


        <div class="card-body">


            <div class="teacher-info">

                <div
                    class="d-flex align-items-center gap-3"
                >

                    <div class="teacher-icon">

                        <i
                            class="fa-solid fa-user-tie"
                        ></i>

                    </div>


                    <div>

                        <div
                            class="text-muted small"
                        >
                            Teacher
                        </div>

                        <h5 class="mb-0">

                            <?= htmlspecialchars(
                                $teacher['fullname']
                            ); ?>

                        </h5>


                        <small class="text-muted">

                            Employee ID:
                            <?= htmlspecialchars(
                                $teacher['employee_id']
                            ); ?>

                            &nbsp; • &nbsp;

                            <?= htmlspecialchars(
                                $teacher['email']
                            ); ?>

                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if ($message): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
        >

            <i
                class="fa-solid fa-circle-exclamation me-2"
            ></i>

            <?= htmlspecialchars($message); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
        >

            <i
                class="fa-solid fa-circle-check me-2"
            ></i>

            <?= $success; ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <div class="row g-4">


        <!-- =================================================
             CREATE SCHEDULE
        ================================================== -->

        <div class="col-lg-5">


            <div class="card">

                <div class="card-header form-header">

                    <h5 class="mb-0">

                        <i
                            class="fa-solid fa-calendar-plus me-2"
                        ></i>

                        Create Schedule

                    </h5>

                </div>


                <div class="card-body">


                    <form
                        method="POST"
                        id="scheduleForm"
                    >


                        <!-- YEAR -->

                        <div class="mb-3">

                            <label for="year_level_id">

                                <i
                                    class="fa-solid fa-graduation-cap me-1"
                                ></i>

                                Year Level

                            </label>


                            <select
                                name="year_level_id"
                                id="year_level_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Year Level
                                </option>


                                <?php foreach ($years as $year): ?>

                                    <option
                                        value="<?= (int)$year['id']; ?>"
                                        <?= (
                                            (string)$selected_year
                                            ===
                                            (string)$year['id']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $year['year_name']
                                        ); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- SUBJECT -->

                        <div class="mb-3">

                            <label for="subject_id">

                                <i
                                    class="fa-solid fa-book me-1"
                                ></i>

                                Subject

                            </label>


                            <select
                                name="subject_id"
                                id="subject_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Subject
                                </option>


                                <?php foreach ($subjects as $subject): ?>

                                    <option
                                        value="<?= (int)$subject['id']; ?>"
                                        <?= (
                                            (string)$selected_subject
                                            ===
                                            (string)$subject['id']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $subject['subject_code']
                                        ); ?>

                                        -

                                        <?= htmlspecialchars(
                                            $subject['subject_name']
                                        ); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- SECTION -->

                        <div class="mb-3">

                            <label for="section_id">

                                <i
                                    class="fa-solid fa-users me-1"
                                ></i>

                                Section

                            </label>


                            <select
                                name="section_id"
                                id="section_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Section
                                </option>


                                <?php foreach ($sections as $section): ?>

                                    <option
                                        value="<?= (int)$section['id']; ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $section['section_name']
                                        ); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- ROOM -->

                        <div class="mb-3">

                            <label for="room">

                                <i
                                    class="fa-solid fa-door-open me-1"
                                ></i>

                                Room

                            </label>


                            <input
                                type="text"
                                name="room"
                                id="room"
                                class="form-control"
                                required
                                maxlength="100"
                                placeholder="e.g. Room 101"
                            >

                        </div>


                        <!-- DAY -->

                        <div class="mb-3">

                            <label for="day">

                                <i
                                    class="fa-solid fa-calendar-day me-1"
                                ></i>

                                Day

                            </label>


                            <select
                                name="day"
                                id="day"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Day
                                </option>

                                <option>Monday</option>

                                <option>Tuesday</option>

                                <option>Wednesday</option>

                                <option>Thursday</option>

                                <option>Friday</option>

                                <option>Saturday</option>

                                <option>Sunday</option>

                            </select>

                        </div>


                        <!-- TIME -->

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label for="start_time">

                                    Start Time

                                </label>


                                <div class="d-flex gap-2">

                                    <select
                                        id="start_period"
                                        class="form-select"
                                        required
                                    >
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>


                                    <select
                                        id="start_hour"
                                        class="form-select"
                                        required
                                    >
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                        <option value="8">8</option>
                                        <option value="9">9</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>


                                    <span
                                        class="d-flex align-items-center fw-bold"
                                    >
                                        :
                                    </span>


                                    <!-- EVERY MINUTE: 00-59 -->

                                    <select
                                        id="start_minute"
                                        class="form-select"
                                        required
                                    >

                                        <?php for ($i = 0; $i <= 59; $i++): ?>

                                            <option
                                                value="<?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>"
                                            >
                                                <?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            </option>

                                        <?php endfor; ?>

                                    </select>

                                </div>


                                <input
                                    type="hidden"
                                    name="start_time"
                                    id="start_time"
                                    required
                                >

                            </div>


                            <div class="col-md-6 mb-3">

                                <label for="end_time">

                                    End Time

                                </label>


                                <div class="d-flex gap-2">

                                    <select
                                        id="end_period"
                                        class="form-select"
                                        required
                                    >
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>


                                    <select
                                        id="end_hour"
                                        class="form-select"
                                        required
                                    >
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                        <option value="8">8</option>
                                        <option value="9">9</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>


                                    <span
                                        class="d-flex align-items-center fw-bold"
                                    >
                                        :
                                    </span>


                                    <!-- EVERY MINUTE: 00-59 -->

                                    <select
                                        id="end_minute"
                                        class="form-select"
                                        required
                                    >

                                        <?php for ($i = 0; $i <= 59; $i++): ?>

                                            <option
                                                value="<?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>"
                                            >
                                                <?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            </option>

                                        <?php endfor; ?>

                                    </select>

                                </div>


                                <input
                                    type="hidden"
                                    name="end_time"
                                    id="end_time"
                                    required
                                >

                            </div>

                        </div>


                        <script>

                            function convertTo24Hour(
                                period,
                                hour,
                                minute
                            ) {

                                let h =
                                    parseInt(
                                        hour,
                                        10
                                    );


                                if (period === 'AM') {

                                    if (h === 12) {
                                        h = 0;
                                    }

                                } else {

                                    if (h !== 12) {
                                        h += 12;
                                    }

                                }


                                return (
                                    String(h).padStart(2, '0')
                                    +
                                    ':'
                                    +
                                    minute
                                    +
                                    ':00'
                                );

                            }


                            function updateScheduleTimes() {

                                document.getElementById(
                                    'start_time'
                                ).value =
                                    convertTo24Hour(

                                        document.getElementById(
                                            'start_period'
                                        ).value,

                                        document.getElementById(
                                            'start_hour'
                                        ).value,

                                        document.getElementById(
                                            'start_minute'
                                        ).value

                                    );


                                document.getElementById(
                                    'end_time'
                                ).value =
                                    convertTo24Hour(

                                        document.getElementById(
                                            'end_period'
                                        ).value,

                                        document.getElementById(
                                            'end_hour'
                                        ).value,

                                        document.getElementById(
                                            'end_minute'
                                        ).value

                                    );

                            }


                            document.querySelectorAll(
                                '#start_period, ' +
                                '#start_hour, ' +
                                '#start_minute, ' +
                                '#end_period, ' +
                                '#end_hour, ' +
                                '#end_minute'
                            )
                            .forEach(
                                function (select) {

                                    select.addEventListener(
                                        'change',
                                        updateScheduleTimes
                                    );

                                }
                            );


                            updateScheduleTimes();

                        </script>


                        <button
                            type="submit"
                            name="save_schedule"
                            class="btn btn-save w-100"
                        >

                            <i
                                class="fa-solid fa-floppy-disk me-1"
                            ></i>

                            Save Schedule

                        </button>


                    </form>

                </div>

            </div>


        </div>


        <!-- =================================================
             TEACHER SCHEDULES
        ================================================== -->

        <div class="col-lg-7">


            <div class="card">

                <div class="card-header list-header">

                    <h5 class="mb-0">

                        <i
                            class="fa-solid fa-calendar-days me-2"
                        ></i>

                        Teacher Schedules

                    </h5>

                </div>


                <div class="card-body">


                    <?php if (empty($teacherSchedules)): ?>

                        <div class="empty-state">

                            <i
                                class="fa-solid fa-calendar-xmark d-block"
                            ></i>

                            <h6>
                                No schedules assigned
                            </h6>

                            <p class="mb-0">
                                Create a schedule using the form.
                            </p>

                        </div>


                    <?php else: ?>


                        <?php foreach ($teacherSchedules as $schedule): ?>


                            <div
                                class="schedule-card
                                <?= (
                                    $selected_schedule_id
                                    ==
                                    $schedule['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>"
                            >


                                <div
                                    class="d-flex justify-content-between align-items-start gap-3"
                                >


                                    <div>

                                        <span
                                            class="subject-code"
                                        >

                                            <?= htmlspecialchars(
                                                $schedule['subject_code']
                                            ); ?>

                                        </span>


                                        <h6 class="mt-2 mb-2">

                                            <?= htmlspecialchars(
                                                $schedule['subject_name']
                                            ); ?>

                                        </h6>


                                        <div
                                            class="d-flex flex-wrap gap-2"
                                        >

                                            <span
                                                class="info-badge"
                                            >

                                                <i
                                                    class="fa-solid fa-graduation-cap"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $schedule['year_name']
                                                ); ?>

                                            </span>


                                            <span
                                                class="info-badge"
                                            >

                                                <i
                                                    class="fa-solid fa-users"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $schedule['section_name']
                                                ); ?>

                                            </span>


                                            <span
                                                class="info-badge"
                                            >

                                                <i
                                                    class="fa-solid fa-door-open"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $schedule['room']
                                                ); ?>

                                            </span>


                                            <span
                                                class="info-badge"
                                            >

                                                <i
                                                    class="fa-solid fa-calendar-day"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $schedule['day']
                                                ); ?>

                                            </span>


                                            <span
                                                class="info-badge"
                                            >

                                                <i
                                                    class="fa-solid fa-clock"
                                                ></i>

                                                <?= date(
                                                    'h:i A',
                                                    strtotime(
                                                        $schedule['start_time']
                                                    )
                                                ); ?>

                                                -

                                                <?= date(
                                                    'h:i A',
                                                    strtotime(
                                                        $schedule['end_time']
                                                    )
                                                ); ?>

                                            </span>

                                        </div>

                                    </div>


                                    <a
                                        href="assign_schedule.php?id=<?= (int)$teacher_id; ?>&schedule_id=<?= (int)$schedule['id']; ?>"
                                        class="btn btn-sm btn-warning"
                                        title="Assign Beader"
                                    >

                                        <i
                                            class="fa-solid fa-user-check"
                                        ></i>

                                        <span class="d-none d-md-inline">
                                            Beader
                                        </span>

                                    </a>


                                </div>

                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>

                </div>

            </div>


        </div>


    </div>


    <!-- =====================================================
         BEADER SECTION
    ====================================================== -->

    <?php if ($selectedSchedule): ?>


        <div class="card mt-4">


            <div class="card-header section-header">

                <h5 class="mb-1">

                    <i
                        class="fa-solid fa-user-check me-2"
                    ></i>

                    Assign Beader

                </h5>


                <small class="opacity-75">

                    One Beader is assigned to this section.
                    Assigning another student will replace
                    the current Beader.

                </small>

            </div>


            <div class="card-body">


                <div class="teacher-info mb-4">

                    <div
                        class="d-flex justify-content-between align-items-start flex-wrap gap-3"
                    >

                        <div>

                            <div
                                class="text-muted small"
                            >
                                Selected Schedule
                            </div>


                            <h5 class="mb-2">

                                <?= htmlspecialchars(
                                    $selectedSchedule['subject_code']
                                ); ?>

                                -

                                <?= htmlspecialchars(
                                    $selectedSchedule['subject_name']
                                ); ?>

                            </h5>


                            <div
                                class="d-flex flex-wrap gap-2"
                            >

                                <span
                                    class="info-badge"
                                >

                                    <i
                                        class="fa-solid fa-graduation-cap"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $selectedSchedule['year_name']
                                    ); ?>

                                </span>


                                <span
                                    class="info-badge"
                                >

                                    <i
                                        class="fa-solid fa-users"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $selectedSchedule['section_name']
                                    ); ?>

                                </span>


                                <span
                                    class="info-badge"
                                >

                                    <i
                                        class="fa-solid fa-door-open"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $selectedSchedule['room']
                                    ); ?>

                                </span>


                                <span
                                    class="info-badge"
                                >

                                    <i
                                        class="fa-solid fa-calendar-day"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $selectedSchedule['day']
                                    ); ?>

                                </span>

                            </div>

                        </div>


                        <a
                            href="assign_schedule.php?id=<?= (int)$teacher_id; ?>"
                            class="btn btn-secondary"
                        >

                            <i
                                class="fa-solid fa-xmark me-1"
                            ></i>

                            Close

                        </a>

                    </div>

                </div>


                <?php if ($currentBeader): ?>

                    <div class="current-beader mb-4">

                        <div
                            class="d-flex align-items-center gap-3"
                        >

                            <div class="current-beader-icon">

                                <i
                                    class="fa-solid fa-user-check"
                                ></i>

                            </div>


                            <div class="flex-grow-1">

                                <div
                                    class="text-muted small"
                                >

                                    Current Beader

                                </div>


                                <h6 class="mb-1">

                                    <?= htmlspecialchars(
                                        $currentBeader['fullname']
                                    ); ?>

                                </h6>


                                <small class="text-muted">

                                    Student No:
                                    <?= htmlspecialchars(
                                        $currentBeader['student_no']
                                        ?? '-'
                                    ); ?>

                                    &nbsp; • &nbsp;

                                    Assigned:
                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $currentBeader[
                                                'assigned_date'
                                            ]
                                        )
                                    ); ?>

                                </small>

                            </div>


                            <span
                                class="badge bg-success"
                            >

                                Active Beader

                            </span>

                        </div>

                    </div>

                <?php else: ?>

                    <div
                        class="alert alert-warning mb-4"
                    >

                        <i
                            class="fa-solid fa-triangle-exclamation me-2"
                        ></i>

                        This section currently has
                        <strong>no Beader</strong>.

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    class="row g-3"
                    id="beaderForm"
                >


                    <input
                        type="hidden"
                        name="schedule_id"
                        value="<?= (int)$selectedSchedule['id']; ?>"
                    >


                    <div class="col-md-4">

                        <label>

                            <i
                                class="fa-solid fa-graduation-cap me-1"
                            ></i>

                            Year Level

                        </label>


                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $selectedSchedule['year_name']
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="col-md-4">

                        <label>

                            <i
                                class="fa-solid fa-users me-1"
                            ></i>

                            Section

                        </label>


                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $selectedSchedule['section_name']
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="col-md-8">

                        <label for="beader_id">

                            <i
                                class="fa-solid fa-user-graduate me-1"
                            ></i>

                            Select Student / Beader

                        </label>


                        <select
                            name="beader_id"
                            id="beader_id"
                            class="form-select"
                            required
                        >

                            <option value="">

                                Select Student

                            </option>


                            <?php foreach (
                                $beaderStudents
                                as $student
                            ): ?>

                                <option
                                    value="<?= (int)$student['id']; ?>"
                                    <?= (
                                        $currentBeader
                                        &&
                                        (int)$currentBeader['student_id']
                                        ===
                                        (int)$student['id']
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $student['fullname']
                                    ); ?>

                                    <?php if (
                                        !empty(
                                            $student['student_no']
                                        )
                                    ): ?>

                                        -
                                        <?= htmlspecialchars(
                                            $student['student_no']
                                        ); ?>

                                    <?php endif; ?>


                                    <?php if (
                                        $currentBeader
                                        &&
                                        (int)$currentBeader['student_id']
                                        ===
                                        (int)$student['id']
                                    ): ?>

                                        (Current Beader)

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <?php if (empty($beaderStudents)): ?>

                            <div
                                class="form-text text-danger"
                            >

                                <i
                                    class="fa-solid fa-circle-exclamation me-1"
                                ></i>

                                No students were found for this
                                year level and section.

                            </div>

                        <?php else: ?>

                            <div
                                class="form-text"
                            >

                                Only students belonging to this
                                section are shown.

                            </div>

                        <?php endif; ?>

                    </div>


                    <div
                        class="col-md-4 d-flex align-items-end"
                    >

                        <button
                            type="submit"
                            name="assign_beader"
                            class="btn btn-beader w-100"
                            <?= empty($beaderStudents)
                                ? 'disabled'
                                : ''
                            ?>
                        >

                            <i
                                class="fa-solid fa-user-check me-1"
                            ></i>

                            <?= $currentBeader
                                ? 'Replace Beader'
                                : 'Assign Beader'
                            ?>

                        </button>

                    </div>


                </form>


            </div>

        </div>


    <?php endif; ?>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script>

// =========================================================
// ELEMENTS
// =========================================================

const yearSelect =
    document.getElementById('year_level_id');

const subjectSelect =
    document.getElementById('subject_id');

const sectionSelect =
    document.getElementById('section_id');


// =========================================================
// YEAR LEVEL CHANGE
// =========================================================

yearSelect.addEventListener(
    'change',
    async function () {

        const yearId = this.value;


        subjectSelect.innerHTML =
            '<option value="">Loading subjects...</option>';

        subjectSelect.disabled = true;


        sectionSelect.innerHTML =
            '<option value="">Select Section</option>';

        sectionSelect.disabled = true;


        if (!yearId) {

            subjectSelect.innerHTML =
                '<option value="">Select Subject</option>';

            sectionSelect.innerHTML =
                '<option value="">Select Section</option>';

            return;
        }


        try {

            const response = await fetch(
                'get_schedule_options.php?year_level_id='
                +
                encodeURIComponent(yearId)
            );


            const data =
                await response.json();


            if (!data.success) {

                throw new Error(
                    data.message
                    ||
                    'Unable to load data.'
                );

            }


            subjectSelect.innerHTML =
                '<option value="">Select Subject</option>';


            data.subjects.forEach(
                function (subject) {

                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value =
                        subject.id;

                    option.textContent =
                        subject.subject_code
                        +
                        ' - '
                        +
                        subject.subject_name;

                    subjectSelect.appendChild(
                        option
                    );

                }
            );


            subjectSelect.disabled =
                false;


            loadSections(
                data.sections,
                data.occupiedSections
            );


        } catch (error) {

            console.error(error);


            subjectSelect.innerHTML =
                '<option value="">Unable to load subjects</option>';

            sectionSelect.innerHTML =
                '<option value="">Unable to load sections</option>';

        }

    }
);


// =========================================================
// SUBJECT CHANGE
// =========================================================

subjectSelect.addEventListener(
    'change',
    async function () {

        const yearId =
            yearSelect.value;

        const subjectId =
            this.value;


        if (!yearId) {
            return;
        }


        if (!subjectId) {

            sectionSelect.innerHTML =
                '<option value="">Select Section</option>';

            sectionSelect.disabled =
                true;

            return;
        }


        sectionSelect.innerHTML =
            '<option value="">Loading sections...</option>';

        sectionSelect.disabled =
            true;


        try {

            const response = await fetch(
                'get_schedule_options.php'
                +
                '?year_level_id='
                +
                encodeURIComponent(yearId)
                +
                '&subject_id='
                +
                encodeURIComponent(subjectId)
            );


            const data =
                await response.json();


            if (!data.success) {

                throw new Error(
                    data.message
                    ||
                    'Unable to load sections.'
                );

            }


            loadSections(
                data.sections,
                data.occupiedSections
            );


        } catch (error) {

            console.error(error);


            sectionSelect.innerHTML =
                '<option value="">Unable to load sections</option>';

        }

    }
);


// =========================================================
// LOAD SECTIONS
// =========================================================

function loadSections(
    sections,
    occupiedSections = []
) {

    sectionSelect.innerHTML =
        '<option value="">Select Section</option>';


    sections.forEach(
        function (section) {

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                section.id;

            option.textContent =
                section.section_name;


            if (
                occupiedSections
                    .map(String)
                    .includes(
                        String(section.id)
                    )
            ) {

                option.disabled =
                    true;

                option.textContent +=
                    ' (Already Assigned)';

            }


            sectionSelect.appendChild(
                option
            );

        }
    );


    sectionSelect.disabled =
        sections.length === 0;

}


// =========================================================
// TIME VALIDATION
// =========================================================

document
    .getElementById('scheduleForm')
    .addEventListener(
        'submit',
        function (e) {

            const start =
                document.getElementById(
                    'start_time'
                ).value;

            const end =
                document.getElementById(
                    'end_time'
                ).value;


            if (
                start
                &&
                end
                &&
                start >= end
            ) {

                e.preventDefault();


                showToast(
                    'End time must be later than start time.',
                    'error'
                );

            }

        }
    );


// =========================================================
// BEADER CONFIRMATION
// =========================================================

const beaderForm =
    document.getElementById(
        'beaderForm'
    );


if (beaderForm) {

    beaderForm.addEventListener(
        'submit',
        function (e) {

            const currentBeader =
                <?= json_encode(
                    $currentBeader
                        ? $currentBeader['fullname']
                        : null
                ); ?>;


            const select =
                document.getElementById(
                    'beader_id'
                );


            const selectedText =
                select.options[
                    select.selectedIndex
                ]?.text
                || '';


            if (
                currentBeader
                &&
                selectedText
            ) {

                const confirmed =
                    confirm(
                        'This section already has '
                        +
                        currentBeader
                        +
                        ' as its Beader.\n\n'
                        +
                        'Do you want to replace the current Beader with '
                        +
                        selectedText
                        +
                        '?'
                    );


                if (!confirmed) {

                    e.preventDefault();

                }

            }

        }
    );

}


// =========================================================
// TOAST
// =========================================================

function showToast(
    message,
    type = 'success'
) {

    const container =
        document.getElementById(
            'toastContainer'
        );


    const toast =
        document.createElement(
            'div'
        );


    toast.className =
        `custom-toast toast-${type}`;


    const icon =
        type === 'success'
            ? 'fa-circle-check'
            : 'fa-circle-xmark';


    const title =
        type === 'success'
            ? 'Success!'
            : 'Error!';


    toast.innerHTML = `

        <div class="toast-body">

            <div class="toast-icon">

                <i
                    class="fa-solid ${icon}"
                ></i>

            </div>


            <div class="toast-content">

                <div class="toast-title">

                    ${title}

                </div>


                <div class="toast-message"></div>

            </div>


            <button
                type="button"
                class="toast-close"
            >

                <i
                    class="fa-solid fa-xmark"
                ></i>

            </button>

        </div>


        <div class="toast-progress">

            <div
                class="toast-progress-bar"
            ></div>

        </div>

    `;


    toast.querySelector(
        '.toast-message'
    ).textContent =
        message;


    toast.querySelector(
        '.toast-close'
    ).addEventListener(
        'click',
        function () {

            toast.remove();

        }
    );


    container.appendChild(
        toast
    );


    setTimeout(
        function () {

            if (toast.parentElement) {

                toast.remove();

            }

        },
        4000
    );

}


// =========================================================
// PHP TOAST
// =========================================================

<?php if ($toast_message): ?>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        showToast(
            <?= json_encode(
                $toast_message
            ); ?>,

            <?= json_encode(
                $toast_type
            ); ?>
        );

    }
);

<?php endif; ?>

</script>


</body>

</html>