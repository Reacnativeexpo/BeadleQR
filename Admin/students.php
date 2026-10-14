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


/*
|--------------------------------------------------------------------------
| ADMIN USER ID
|--------------------------------------------------------------------------
*/

$admin_id = $_SESSION['user_id'] ?? null;

if (!$admin_id) {
    die("Admin session ID not found.");
}


$message = "";
$error = "";


/*
|--------------------------------------------------------------------------
| FETCH YEAR LEVELS
|--------------------------------------------------------------------------
*/

$yearStmt = $conn->query("
    SELECT
        id,
        year_name
    FROM year_levels
    ORDER BY id
");

$yearLevels = $yearStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH SECTIONS
|--------------------------------------------------------------------------
*/

$sectionStmt = $conn->query("
    SELECT
        s.id,
        s.section_name,
        s.year_level_id,
        y.year_name,

        CASE
            WHEN ba.id IS NOT NULL THEN TRUE
            ELSE FALSE
        END AS occupied

    FROM sections s

    LEFT JOIN year_levels y
        ON y.id = s.year_level_id

    LEFT JOIN beader_assignments ba
        ON ba.section_id = s.id
        AND ba.year_level_id = s.year_level_id

    ORDER BY
        s.year_level_id,
        s.section_name
");

$sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| SAVE STUDENT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'add';

    $student_id = !empty($_POST['student_id'])
        ? (int) $_POST['student_id']
        : null;


    /*
    |--------------------------------------------------------------------------
    | DELETE STUDENT
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete' && $student_id) {

        try {

            $conn->beginTransaction();


            $deleteBeader = $conn->prepare("
                DELETE FROM beader_assignments
                WHERE beader_id = :student_id
            ");

            $deleteBeader->execute([
                ':student_id' => $student_id
            ]);


            $deleteStudent = $conn->prepare("
                DELETE FROM users
                WHERE id = :student_id
                  AND role = 'student'
            ");

            $deleteStudent->execute([
                ':student_id' => $student_id
            ]);


            if ($deleteStudent->rowCount() === 0) {
                throw new Exception("Student not found.");
            }


            $conn->commit();

            $message = "Student deleted successfully.";


        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = $e->getMessage();
        }


    } else {


        $fullname = trim(
            $_POST['fullname']
            ?? ($editStudent['fullname'] ?? '')
        );


        $student_no = trim(
            $_POST['student_no']
            ?? ($editStudent['student_no'] ?? '')
        );


        $email = trim(
            $_POST['email']
            ?? ($editStudent['email'] ?? '')
        );


        $username = trim(
            $_POST['username']
            ?? ($editStudent['username'] ?? '')
        );


        $password = $_POST['password'] ?? '';


        $year_level_id = !empty($_POST['year_level_id'])
            ? (int) $_POST['year_level_id']
            : null;


        $section_id = !empty($_POST['section_id'])
            ? (int) $_POST['section_id']
            : null;


        $is_edit = ($action === 'edit' && $student_id);


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            empty($fullname) ||
            empty($student_no) ||
            empty($username) ||
            (!$is_edit && empty($password)) ||
            empty($year_level_id) ||
            empty($section_id)
        ) {

            $error = "Please fill in all required fields.";


        } else {


            try {

                $conn->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | VERIFY SECTION BELONGS TO YEAR LEVEL
                |--------------------------------------------------------------------------
                */

                $verifySection = $conn->prepare("
                    SELECT id, year_level_id
                    FROM sections
                    WHERE id = :section_id
                      AND year_level_id = :year_level_id
                    LIMIT 1
                ");

                $verifySection->execute([
                    ':section_id'    => $section_id,
                    ':year_level_id' => $year_level_id
                ]);


                $section = $verifySection->fetch(PDO::FETCH_ASSOC);


                if (!$section) {

                    throw new Exception(
                        "The selected section does not belong to the selected year level."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CHECK DUPLICATE STUDENT NUMBER
                |--------------------------------------------------------------------------
                |
                | FIX:
                | Do not use:
                |
                | (:student_id IS NULL OR id <> :student_id)
                |
                | PostgreSQL cannot determine the data type of the
                | nullable PDO parameter in the IS NULL expression.
                |
                | Instead, use separate queries for ADD and EDIT.
                |--------------------------------------------------------------------------
                */

                if ($is_edit) {

                    $checkStudent = $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE student_no = :student_no
                          AND id <> :student_id
                        LIMIT 1
                    ");

                    $checkStudent->execute([
                        ':student_no' => $student_no,
                        ':student_id' => $student_id
                    ]);

                } else {

                    $checkStudent = $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE student_no = :student_no
                        LIMIT 1
                    ");

                    $checkStudent->execute([
                        ':student_no' => $student_no
                    ]);
                }


                if ($checkStudent->fetch()) {

                    throw new Exception(
                        "Student number already exists."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CHECK DUPLICATE USERNAME
                |--------------------------------------------------------------------------
                */

                if ($is_edit) {

                    $checkUsername = $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE username = :username
                          AND id <> :student_id
                        LIMIT 1
                    ");

                    $checkUsername->execute([
                        ':username'   => $username,
                        ':student_id' => $student_id
                    ]);

                } else {

                    $checkUsername = $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE username = :username
                        LIMIT 1
                    ");

                    $checkUsername->execute([
                        ':username' => $username
                    ]);
                }


                if ($checkUsername->fetch()) {

                    throw new Exception(
                        "Username already exists."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CHECK DUPLICATE EMAIL
                |--------------------------------------------------------------------------
                */

                if (!empty($email)) {

                    if ($is_edit) {

                        $checkEmail = $conn->prepare("
                            SELECT id
                            FROM users
                            WHERE email = :email
                              AND id <> :student_id
                            LIMIT 1
                        ");

                        $checkEmail->execute([
                            ':email'      => $email,
                            ':student_id' => $student_id
                        ]);

                    } else {

                        $checkEmail = $conn->prepare("
                            SELECT id
                            FROM users
                            WHERE email = :email
                            LIMIT 1
                        ");

                        $checkEmail->execute([
                            ':email' => $email
                        ]);
                    }


                    if ($checkEmail->fetch()) {

                        throw new Exception(
                            "Email address already exists."
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | ADD / EDIT STUDENT
                |--------------------------------------------------------------------------
                */

                if ($is_edit) {


                    /*
                    |--------------------------------------------------------------------------
                    | GET CURRENT STUDENT
                    |--------------------------------------------------------------------------
                    */

                    $getCurrent = $conn->prepare("
                        SELECT
                            id,
                            year_level_id,
                            section_id
                        FROM users
                        WHERE id = :student_id
                          AND role = 'student'
                        LIMIT 1
                    ");

                    $getCurrent->execute([
                        ':student_id' => $student_id
                    ]);


                    $currentStudent =
                        $getCurrent->fetch(PDO::FETCH_ASSOC);


                    if (!$currentStudent) {

                        throw new Exception(
                            "Student not found."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE WITH PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($password)) {

                        $hashedPassword = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                        $updateStudent = $conn->prepare("
                            UPDATE users
                            SET
                                fullname = :fullname,
                                email = :email,
                                username = :username,
                                password = :password,
                                student_no = :student_no,
                                year_level_id = :year_level_id,
                                section_id = :section_id
                            WHERE id = :student_id
                              AND role = 'student'
                        ");


                        $updateStudent->execute([
                            ':fullname'      => $fullname,
                            ':email'         => !empty($email)
                                ? $email
                                : null,
                            ':username'      => $username,
                            ':password'      => $hashedPassword,
                            ':student_no'    => $student_no,
                            ':year_level_id' => $year_level_id,
                            ':section_id'    => $section_id,
                            ':student_id'    => $student_id
                        ]);


                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE WITHOUT CHANGING PASSWORD
                        |--------------------------------------------------------------------------
                        */

                        $updateStudent = $conn->prepare("
                            UPDATE users
                            SET
                                fullname = :fullname,
                                email = :email,
                                username = :username,
                                student_no = :student_no,
                                year_level_id = :year_level_id,
                                section_id = :section_id
                            WHERE id = :student_id
                              AND role = 'student'
                        ");


                        $updateStudent->execute([
                            ':fullname'      => $fullname,
                            ':email'         => !empty($email)
                                ? $email
                                : null,
                            ':username'      => $username,
                            ':student_no'    => $student_no,
                            ':year_level_id' => $year_level_id,
                            ':section_id'    => $section_id,
                            ':student_id'    => $student_id
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK TARGET SECTION BEADER
                    |--------------------------------------------------------------------------
                    */

                    $checkTargetBeader = $conn->prepare("
                        SELECT
                            id,
                            beader_id
                        FROM beader_assignments
                        WHERE section_id = :section_id
                        LIMIT 1
                    ");


                    $checkTargetBeader->execute([
                        ':section_id' => $section_id
                    ]);


                    $targetBeader =
                        $checkTargetBeader->fetch(PDO::FETCH_ASSOC);


                    if (
                        $targetBeader &&
                        (int) $targetBeader['beader_id'] !== $student_id
                    ) {

                        throw new Exception(
                            "The selected section already has a Beader."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE BEADER ASSIGNMENT
                    |--------------------------------------------------------------------------
                    */

                    $deleteOldAssignment = $conn->prepare("
                        DELETE FROM beader_assignments
                        WHERE beader_id = :student_id
                    ");


                    $deleteOldAssignment->execute([
                        ':student_id' => $student_id
                    ]);


                    $insertBeader = $conn->prepare("
                        INSERT INTO beader_assignments (
                            year_level_id,
                            section_id,
                            beader_id,
                            assigned_by,
                            assigned_date
                        )
                        VALUES (
                            :year_level_id,
                            :section_id,
                            :beader_id,
                            :assigned_by,
                            CURRENT_DATE
                        )
                    ");


                    $insertBeader->execute([
                        ':year_level_id' => $year_level_id,
                        ':section_id'    => $section_id,
                        ':beader_id'     => $student_id,
                        ':assigned_by'   => $admin_id
                    ]);


                    $conn->commit();


                    $message =
                        "Student updated successfully.";


                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK EXISTING BEADER
                    |--------------------------------------------------------------------------
                    */

                    $existingBeaderStmt = $conn->prepare("
                        SELECT id
                        FROM beader_assignments
                        WHERE section_id = :section_id
                        LIMIT 1
                    ");


                    $existingBeaderStmt->execute([
                        ':section_id' => $section_id
                    ]);


                    $existingBeader =
                        $existingBeaderStmt->fetch(PDO::FETCH_ASSOC);


                    if ($existingBeader) {

                        throw new Exception(
                            "The selected section already has a Beader."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | HASH PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | INSERT STUDENT
                    |--------------------------------------------------------------------------
                    */

                    $insertStudent = $conn->prepare("
                        INSERT INTO users (
                            fullname,
                            email,
                            username,
                            password,
                            role,
                            student_no,
                            year_level_id,
                            section_id
                        )
                        VALUES (
                            :fullname,
                            :email,
                            :username,
                            :password,
                            'student',
                            :student_no,
                            :year_level_id,
                            :section_id
                        )
                        RETURNING id
                    ");


                    $insertStudent->execute([
                        ':fullname'      => $fullname,
                        ':email'         => !empty($email)
                            ? $email
                            : null,
                        ':username'      => $username,
                        ':password'      => $hashedPassword,
                        ':student_no'    => $student_no,
                        ':year_level_id' => $year_level_id,
                        ':section_id'    => $section_id
                    ]);


                    $student_id =
                        $insertStudent->fetchColumn();


                    /*
                    |--------------------------------------------------------------------------
                    | CREATE NEW BEADER
                    |--------------------------------------------------------------------------
                    */

                    $insertBeader = $conn->prepare("
                        INSERT INTO beader_assignments (
                            year_level_id,
                            section_id,
                            beader_id,
                            assigned_by,
                            assigned_date
                        )
                        VALUES (
                            :year_level_id,
                            :section_id,
                            :beader_id,
                            :assigned_by,
                            CURRENT_DATE
                        )
                    ");


                    $insertBeader->execute([
                        ':year_level_id' => $year_level_id,
                        ':section_id'    => $section_id,
                        ':beader_id'     => $student_id,
                        ':assigned_by'   => $admin_id
                    ]);


                    $conn->commit();


                    $message =
                        "Student added successfully and assigned as the Beader for the selected section.";
                }


                $_POST = [];


            } catch (Exception $e) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| FETCH STUDENT LIST
|--------------------------------------------------------------------------
*/

$studentStmt = $conn->query("
    SELECT
        u.id,
        u.student_no,
        u.fullname,
        u.email,
        u.username,
        u.year_level_id,
        u.section_id,

        y.year_name,

        s.section_name,

        CASE
            WHEN ba.id IS NOT NULL THEN TRUE
            ELSE FALSE
        END AS is_beader

    FROM users u

    LEFT JOIN year_levels y
        ON y.id = u.year_level_id

    LEFT JOIN sections s
        ON s.id = u.section_id

    LEFT JOIN beader_assignments ba
        ON ba.beader_id = u.id

    WHERE u.role = 'student'

    ORDER BY
        u.id DESC
");

$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH STUDENT FOR EDIT
|--------------------------------------------------------------------------
*/

$editStudent = null;


if (!empty($_GET['edit'])) {

    $editId = (int) $_GET['edit'];


    $editStmt = $conn->prepare("
        SELECT
            id,
            fullname,
            student_no,
            email,
            username,
            year_level_id,
            section_id
        FROM users
        WHERE id = :id
          AND role = 'student'
        LIMIT 1
    ");


    $editStmt->execute([
        ':id' => $editId
    ]);


    $editStudent =
        $editStmt->fetch(PDO::FETCH_ASSOC);


    if (!$editStudent) {
        $error = "Student not found.";
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

    <title>Students | BeadleQR</title>


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
           VARIABLES
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

        

        /* =====================================================
           MAIN CONTENT
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

            padding: 0 35px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.04);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .topbar-left {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .mobile-menu {

            display: none;

            border: none;

            background: #f1f5f9;

            width: 40px;

            height: 40px;

            border-radius: 8px;

            cursor: pointer;

            font-size: 18px;

            color: var(--text-dark);
        }


        .page-title {

            font-size: 20px;

            font-weight: 700;
        }


        .page-subtitle {

            color: var(--text-muted);

            font-size: 12px;
        }


        .admin-profile {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .admin-avatar {

            width: 40px;

            height: 40px;

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

            font-weight: 600;
        }


        .admin-info strong {

            display: block;

            font-size: 13px;
        }


        .admin-info span {

            font-size: 11px;

            color: var(--text-muted);
        }


        /* =====================================================
           CONTENT
        ===================================================== */

        .content-area {

            padding: 30px 35px;
        }


        /* =====================================================
           WELCOME BANNER
        ===================================================== */

        .welcome-banner {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            border-radius: 16px;

            padding: 25px 30px;

            color: white;

            margin-bottom: 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 8px 25px
                rgba(249, 115, 22, 0.2);
        }


        .welcome-banner h2 {

            font-size: 22px;

            margin-bottom: 5px;
        }


        .welcome-banner p {

            font-size: 13px;

            opacity: .9;
        }


        .welcome-icon {

            font-size: 55px;

            opacity: .2;
        }


        /* =====================================================
           FORM CARD
        ===================================================== */

        .form-card {

            background: white;

            border-radius: 16px;

            padding: 25px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.04);

            margin-bottom: 25px;
        }


        .card-header-custom {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;
        }


        .card-title {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .card-title-icon {

            width: 42px;

            height: 42px;

            border-radius: 10px;

            background:
                rgba(249,115,22,.1);

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .card-title h3 {

            font-size: 18px;

            margin: 0;
        }


        .card-title p {

            color: var(--text-muted);

            font-size: 11px;

            margin-top: 2px;
        }


        /* =====================================================
           FORM
        ===================================================== */

        .section-title {

            font-size: 14px;

            font-weight: 700;

            margin-bottom: 18px;

            color: var(--text-dark);
        }


        .section-title i {

            color: var(--primary);

            margin-right: 7px;
        }


        .form-label {

            display: block;

            font-size: 12px;

            font-weight: 600;

            margin-bottom: 7px;

            color: var(--text-dark);
        }


        .required {

            color: var(--danger);
        }


        .form-control,
        .form-select {

            width: 100%;

            height: 45px;

            border:
                1px solid #e2e8f0;

            border-radius: 9px;

            padding: 0 13px;

            font-size: 12px;

            color: var(--text-dark);

            background: white;

            outline: none;

            transition: all .2s ease;
        }


        textarea.form-control {

            height: 100px;

            padding-top: 12px;

            resize: vertical;
        }


        .form-control:focus,
        .form-select:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(249,115,22,.1);
        }


        .form-select:disabled {

            background: #f8fafc;

            color: #94a3b8;

            cursor: not-allowed;
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }


        .form-group {

            margin-bottom: 5px;
        }


        /* =====================================================
           INFO
        ===================================================== */

        .beader-info {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            padding: 15px;

            background:
                rgba(59,130,246,.07);

            border:
                1px solid rgba(59,130,246,.12);

            border-radius: 10px;

            margin-top: 20px;

            color: #1d4ed8;

            font-size: 11px;

            line-height: 1.6;
        }


        .beader-info i {

            font-size: 17px;

            margin-top: 2px;
        }


        .form-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 25px;

            padding-top: 20px;

            border-top:
                1px solid #f1f5f9;
        }


        .btn {

            height: 42px;

            border: none;

            border-radius: 9px;

            padding: 0 18px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            text-decoration: none;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;

            transition: all .2s ease;
        }


        .btn-secondary {

            background: #f1f5f9;

            color: var(--text-muted);
        }


        .btn-secondary:hover {

            background: #e2e8f0;

            color: var(--text-dark);
        }


        .btn-primary {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: white;

            box-shadow:
                0 4px 12px
                rgba(249,115,22,.2);
        }


        .btn-primary:hover {

            transform: translateY(-1px);

            box-shadow:
                0 6px 16px
                rgba(249,115,22,.3);
        }


        /* =====================================================
           ALERTS
        ===================================================== */

        .alert {

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 12px;

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .alert-success {

            background:
                rgba(16,185,129,.1);

            color: #047857;

            border:
                1px solid rgba(16,185,129,.15);
        }


        .alert-danger {

            background:
                rgba(239,68,68,.1);

            color: #b91c1c;

            border:
                1px solid rgba(239,68,68,.15);
        }


        /* =====================================================
           STUDENT LIST
        ===================================================== */

        .student-card {

            background: white;

            border-radius: 16px;

            padding: 25px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.04);
        }


        .student-list-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

            gap: 15px;
        }


        .student-count {

            background:
                rgba(249,115,22,.1);

            color: var(--primary-dark);

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;
        }


        .table-wrapper {

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 900px;
        }


        thead th {

            background: #f8fafc;

            color: var(--text-muted);

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: .6px;

            padding: 13px 14px;

            text-align: left;

            font-weight: 600;

            white-space: nowrap;
        }


        tbody td {

            padding: 14px;

            border-bottom:
                1px solid #f1f5f9;

            font-size: 12px;

            vertical-align: middle;
        }


        tbody tr:hover {

            background: #fafafa;
        }


        .student-cell {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .student-avatar {

            width: 36px;

            height: 36px;

            border-radius: 9px;

            background:
                rgba(249,115,22,.1);

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;

            font-size: 13px;

            flex-shrink: 0;
        }


        .student-name {

            font-weight: 600;

            color: var(--text-dark);
        }


        .student-email {

            color: var(--text-muted);

            font-size: 10px;

            margin-top: 2px;
        }


        .student-number {

            display: inline-block;

            background: #f8fafc;

            padding: 5px 8px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 600;

            color: var(--text-muted);
        }


        .year-badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            background:
                rgba(59,130,246,.1);

            color: #2563eb;

            padding: 5px 9px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 600;
        }


        .section-badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            background:
                rgba(139,92,246,.1);

            color: #7c3aed;

            padding: 5px 9px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 600;
        }


        .beader-badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 600;
        }


        .beader-badge.yes {

            background:
                rgba(16,185,129,.1);

            color: #059669;
        }


        .beader-badge.no {

            background:
                rgba(100,116,139,.1);

            color: #64748b;
        }


        .empty-state {

            text-align: center;

            padding: 55px 20px;

            color: var(--text-muted);
        }


        .empty-state i {

            font-size: 45px;

            color: #cbd5e1;

            margin-bottom: 15px;
        }


        .empty-state h3 {

            font-size: 16px;

            color: var(--text-dark);

            margin-bottom: 5px;
        }


        .empty-state p {

            font-size: 11px;
        }


        /* =====================================================
           ACTION BUTTONS
        ===================================================== */

        .student-actions {
            display: flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            font-size: 12px;
            transition: all .2s ease;
        }

        .action-btn.edit {
            background: rgba(59,130,246,.1);
            color: #2563eb;
        }

        .action-btn.edit:hover {
            background: #2563eb;
            color: white;
        }

        .action-btn.delete {
            background: rgba(239,68,68,.1);
            color: #dc2626;
        }

        .action-btn.delete:hover {
            background: #dc2626;
            color: white;
        }

        /* =====================================================
           OVERLAY
        ===================================================== */

        .sidebar-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background:
                rgba(15,23,42,.5);

            z-index: 999;
        }

/* =====================================================
   INCREASE MAIN CONTENT FONT SIZES
   SIDEBAR REMAINS UNCHANGED
   ===================================================== */

/* Topbar */
.page-title {
    font-size: 21px;
}

.page-subtitle {
    font-size: 14px;
}

.admin-info strong {
    font-size: 15px;
}

.admin-info span {
    font-size: 13px;
}

/* Welcome Banner */
.welcome-banner h2 {
    font-size: 28px;
}

.welcome-banner p {
    font-size: 15px;
}

/* Card Header */
.card-title h3 {
    font-size: 19px;
}

.card-title p {
    font-size: 14px;
}

/* Section Title */
.section-title {
    font-size: 16px;
}

/* Form */
.form-label {
    font-size: 15px;
}

.form-control,
.form-select {
    font-size: 15px;
}

/* Information Box */
.beader-info {
    font-size: 14px;
}

/* Buttons */
.btn {
    font-size: 15px;
}

/* Alerts */
.alert {
    font-size: 15px;
}

/* Student Count */
.student-count {
    font-size: 13px;
}

/* Table */
thead th {
    font-size: 13px;
}

tbody td {
    font-size: 15px;
}

/* Student Information */
.student-avatar {
    font-size: 15px;
}

.student-email {
    font-size: 12px;
}

.student-number {
    font-size: 12px;
}

.year-badge {
    font-size: 12px;
}

.section-badge {
    font-size: 12px;
}

.beader-badge {
    font-size: 12px;
}

/* Empty State */
.empty-state h3 {
    font-size: 18px;
}

.empty-state p {
    font-size: 14px;
}

/* Action Buttons */
.action-btn {
    font-size: 14px;
}
        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .form-grid {

                grid-template-columns: 1fr;
            }

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


            .mobile-menu {

                display: block;
            }


            .sidebar-overlay.show {

                display: block;
            }

        }


        @media (max-width: 768px) {

            .topbar {

                padding: 0 20px;
            }


            .content-area {

                padding: 20px;
            }


            .admin-info {

                display: none;
            }


            .welcome-banner {

                padding: 20px;
            }


            .welcome-banner h2 {

                font-size: 18px;
            }


            .welcome-icon {

                font-size: 40px;
            }


            .form-card,
            .student-card {

                padding: 18px;
            }


            .form-actions {

                flex-direction: column-reverse;
            }


            .form-actions .btn {

                width: 100%;
            }

        }


        @media (max-width: 480px) {

            .welcome-icon {

                display: none;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include 'sidebar.php'; ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">


    <!-- =====================================================
         TOPBAR
    ===================================================== -->

    <div class="topbar">

        <div class="topbar-left">

            <button
                class="mobile-menu"
                id="mobileMenu"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <div>

                <div class="page-title">

                    Students

                </div>

                <div class="page-subtitle">

                    Manage student accounts and Beader assignments

                </div>

            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">

                <i class="fa-solid fa-user-shield"></i>

            </div>


            <div class="admin-info">

                <strong>
                    Administrator
                </strong>

                <span>
                    Admin Panel
                </span>

            </div>

        </div>

    </div>


    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <div class="content-area">


        <!-- =================================================
             WELCOME BANNER
        ================================================= -->

        <div class="welcome-banner">

            <div>

                <h2>

                    Student Management

                </h2>

                <p>

                    Add students, manage academic information,
                    and automatically assign Beaders.

                </p>

            </div>


            <div class="welcome-icon">

                <i class="fa-solid fa-users"></i>

            </div>

        </div>


        <!-- =================================================
             ADD STUDENT
        ================================================= -->

        <div class="form-card">


            <div class="card-header-custom">

                <div class="card-title">

                    <div class="card-title-icon">

                        <?php if ($editStudent): ?>

                            <i class="fa-solid fa-user-pen"></i>

                        <?php else: ?>

                            <i class="fa-solid fa-user-plus"></i>

                        <?php endif; ?>

                    </div>


                    <div>

                        <h3>

                            <?= $editStudent
                                ? 'Edit Student'
                                : 'Add New Student'
                            ?>

                        </h3>

                        <p>

                            <?= $editStudent
                                ? 'Update student account and academic information.'
                                : 'Create a student account and assign a section.'
                            ?>

                        </p>

                    </div>

                </div>

            </div>


            <!-- SUCCESS -->

            <?php if (!empty($message)): ?>

                <div class="alert alert-success">

                    <i class="fa-solid fa-circle-check"></i>

                    <?= htmlspecialchars($message) ?>

                </div>

            <?php endif; ?>


            <!-- ERROR -->

            <?php if (!empty($error)): ?>

                <div class="alert alert-danger">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editStudent ? 'edit' : 'add' ?>"
                >


                <?php if ($editStudent): ?>

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?= (int) $editStudent['id'] ?>"
                    >

                <?php endif; ?>


                <!-- =================================================
                     STUDENT INFORMATION
                ================================================= -->

                <div class="section-title">

                    <i class="fa-solid fa-user"></i>

                    Student Information

                </div>


                <div class="form-grid">


                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label class="form-label">

                            Full Name

                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            name="fullname"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST['fullname']
                                ?? ($editStudent['fullname'] ?? '')
                            ) ?>"
                            placeholder="Enter full name"
                            required
                        >

                    </div>


                    <!-- STUDENT NUMBER -->

                    <div class="form-group">

                        <label class="form-label">

                            Student Number

                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            name="student_no"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST['student_no']
                                ?? ($editStudent['student_no'] ?? '')
                            ) ?>"
                            placeholder="Enter student number"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label class="form-label">

                            Email

                        </label>


                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST['email']
                                ?? ($editStudent['email'] ?? '')
                            ) ?>"
                            placeholder="Enter email address"
                        >

                    </div>


                    <!-- USERNAME -->

                    <div class="form-group">

                        <label class="form-label">

                            Username

                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST['username']
                                ?? ($editStudent['username'] ?? '')
                            ) ?>"
                            placeholder="Enter username"
                            required
                        >

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label class="form-label">

                            Password

                            <?php if (!$editStudent): ?>

                                <span class="required">*</span>

                            <?php endif; ?>

                        </label>


                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="<?= $editStudent
                                ? 'Leave blank to keep current password'
                                : 'Enter password'
                            ?>"
                            <?= $editStudent ? '' : 'required' ?>
                        >

                    </div>

                </div>


                <div
                    style="
                        height:1px;
                        background:#f1f5f9;
                        margin:25px 0;
                    "
                ></div>


                <!-- =================================================
                     ACADEMIC INFORMATION
                ================================================= -->

                <div class="section-title">

                    <i class="fa-solid fa-graduation-cap"></i>

                    Academic Information

                </div>


                <div class="form-grid">


                    <!-- YEAR LEVEL -->

                    <div class="form-group">

                        <label class="form-label">

                            Year Level

                            <span class="required">*</span>

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


                            <?php foreach ($yearLevels as $year): ?>

                                <option
                                    value="<?= $year['id'] ?>"

                                    <?= (
                                        (
                                            isset($_POST['year_level_id'])
                                                ? $_POST['year_level_id']
                                                : ($editStudent['year_level_id'] ?? '')
                                        ) == $year['id']
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $year['year_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SECTION -->

                    <div class="form-group">

                        <label class="form-label">

                            Section

                            <span class="required">*</span>

                        </label>


                        <select
                            name="section_id"
                            id="section_id"
                            class="form-select"
                            required
                            disabled
                        >

                            <option value="">

                                Select Year Level First

                            </option>

                        </select>

                    </div>

                </div>


                <!-- =================================================
                     BEADER INFORMATION
                ================================================= -->

                <div class="beader-info">

                    <i class="fa-solid fa-qrcode"></i>


                    <div>

                        <strong>
                            Automatic Beader Assignment
                        </strong>

                        <br>

                        The student will automatically become
                        the <strong>Beader for the selected section</strong>
                        after saving.

                        Sections that already have a Beader are marked
                        <strong>OCCUPIED</strong> and cannot be selected.

                    </div>

                </div>


                <!-- =================================================
                     BUTTONS
                ================================================= -->

                <div class="form-actions">


                    <a
                        href="students.php"
                        class="btn btn-secondary"
                    >

                        <i class="fa-solid fa-rotate-left"></i>

                        <?= $editStudent
                            ? 'Cancel'
                            : 'Reset'
                        ?>

                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid <?= $editStudent
                            ? 'fa-floppy-disk'
                            : 'fa-user-plus'
                        ?>"></i>

                        <?= $editStudent
                            ? 'Save Changes'
                            : 'Add Student'
                        ?>

                    </button>

                </div>


            </form>

        </div>


        <!-- =================================================
             STUDENT LIST
        ================================================= -->

        <div class="student-card">


            <div class="student-list-header">


                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fa-solid fa-users"></i>

                    </div>


                    <div>

                        <h3>

                            Student List

                        </h3>

                        <p>

                            All registered students

                        </p>

                    </div>

                </div>


                <div class="student-count">

                    <i class="fa-solid fa-user-group"></i>

                    <?= count($students) ?> Students

                </div>

            </div>


            <!-- =================================================
                 TABLE
            ================================================= -->

            <div class="table-wrapper">


                <?php if (count($students) > 0): ?>

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Student No.
                                </th>

                                <th>
                                    Username
                                </th>

                                <th>
                                    Year Level
                                </th>

                                <th>
                                    Section
                                </th>

                                <th>
                                    Beader
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($students as $student): ?>


                            <tr>


                                <!-- STUDENT -->

                                <td>

                                    <div class="student-cell">


                                        <div class="student-avatar">

                                            <?= strtoupper(
                                                substr(
                                                    $student['fullname']
                                                        ?? 'S',
                                                    0,
                                                    1
                                                )
                                            ) ?>

                                        </div>


                                        <div>

                                            <div class="student-name">

                                                <?= htmlspecialchars(
                                                    $student['fullname']
                                                ) ?>

                                            </div>


                                            <?php if (!empty($student['email'])): ?>

                                                <div class="student-email">

                                                    <?= htmlspecialchars(
                                                        $student['email']
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <!-- STUDENT NUMBER -->

                                <td>

                                    <span class="student-number">

                                        <i class="fa-solid fa-id-card"></i>

                                        <?= htmlspecialchars(
                                            $student['student_no']
                                                ?? '--'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- USERNAME -->

                                <td>

                                    <span
                                        style="
                                            font-size:11px;
                                            color:var(--text-muted);
                                        "
                                    >

                                        <i
                                            class="fa-solid fa-at"
                                            style="
                                                color:var(--primary);
                                                margin-right:3px;
                                            "
                                        ></i>

                                        <?= htmlspecialchars(
                                            $student['username']
                                                ?? '--'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- YEAR -->

                                <td>

                                    <?php if (!empty($student['year_name'])): ?>

                                        <span class="year-badge">

                                            <i class="fa-solid fa-graduation-cap"></i>

                                            <?= htmlspecialchars(
                                                $student['year_name']
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#94a3b8;
                                                font-size:11px;
                                            "
                                        >

                                            Not Assigned

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- SECTION -->

                                <td>

                                    <?php if (!empty($student['section_name'])): ?>

                                        <span class="section-badge">

                                            <i class="fa-solid fa-layer-group"></i>

                                            <?= htmlspecialchars(
                                                $student['section_name']
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#94a3b8;
                                                font-size:11px;
                                            "
                                        >

                                            Not Assigned

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- BEADER -->

                                <td>

                                    <?php

                                    $isBeader =
                                        $student['is_beader'] === true ||
                                        $student['is_beader'] === 't' ||
                                        $student['is_beader'] === '1' ||
                                        $student['is_beader'] === 1;

                                    ?>


                                    <?php if ($isBeader): ?>

                                        <span class="beader-badge yes">

                                            <i class="fa-solid fa-qrcode"></i>

                                            Beader

                                        </span>

                                    <?php else: ?>

                                        <span class="beader-badge no">

                                            <i class="fa-solid fa-user"></i>

                                            Student

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="student-actions">


                                        <a
                                            href="students.php?edit=<?= (int) $student['id'] ?>"
                                            class="action-btn edit"
                                            title="Edit Student"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                        </a>


                                        <form
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($student['fullname'])) ?>? This will also remove the Beader assignment.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >


                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value="<?= (int) $student['id'] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="action-btn delete"
                                                title="Delete Student"
                                            >

                                                <i class="fa-solid fa-trash"></i>

                                            </button>

                                        </form>

                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>

                        </tbody>

                    </table>


                <?php else: ?>


                    <div class="empty-state">

                        <i class="fa-solid fa-users-slash"></i>

                        <h3>

                            No Students Found

                        </h3>

                        <p>

                            Add your first student using the form above.

                        </p>

                    </div>


                <?php endif; ?>


            </div>

        </div>


    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>


/*
|--------------------------------------------------------------------------
| SECTION DATA
|--------------------------------------------------------------------------
*/

const sections = <?= json_encode(
    $sections,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;


const yearLevelSelect =
    document.getElementById("year_level_id");


const sectionSelect =
    document.getElementById("section_id");


/*
|--------------------------------------------------------------------------
| LOAD SECTIONS
|--------------------------------------------------------------------------
*/

function loadSections(
    yearLevelId,
    selectedSectionId = ""
) {

    sectionSelect.innerHTML = "";


    /*
    |--------------------------------------------------------------------------
    | NO YEAR LEVEL
    |--------------------------------------------------------------------------
    */

    if (!yearLevelId) {

        sectionSelect.disabled = true;


        const option =
            document.createElement("option");


        option.value = "";


        option.textContent =
            "Select Year Level First";


        sectionSelect.appendChild(option);


        return;
    }


    /*
    |--------------------------------------------------------------------------
    | FILTER SECTIONS
    |--------------------------------------------------------------------------
    */

    const filteredSections =
        sections.filter(section =>

            String(section.year_level_id)
            === String(yearLevelId)

        );


    /*
    |--------------------------------------------------------------------------
    | NO SECTIONS
    |--------------------------------------------------------------------------
    */

    if (!filteredSections.length) {

        sectionSelect.disabled = true;


        const option =
            document.createElement("option");


        option.value = "";


        option.textContent =
            "No sections available";


        sectionSelect.appendChild(option);


        return;
    }


    /*
    |--------------------------------------------------------------------------
    | ENABLE SECTION
    |--------------------------------------------------------------------------
    */

    sectionSelect.disabled = false;


    /*
    |--------------------------------------------------------------------------
    | DEFAULT OPTION
    |--------------------------------------------------------------------------
    */

    const defaultOption =
        document.createElement("option");


    defaultOption.value = "";


    defaultOption.textContent =
        "Select Section";


    sectionSelect.appendChild(
        defaultOption
    );


    /*
    |--------------------------------------------------------------------------
    | ADD SECTIONS
    |--------------------------------------------------------------------------
    */

    filteredSections.forEach(section => {


        const option =
            document.createElement("option");


        option.value =
            section.id;


        const isOccupied =
            (
                section.occupied === true ||
                section.occupied === "t" ||
                section.occupied === 1 ||
                section.occupied === "1"
            ) &&
            String(section.id) !== String(selectedSectionId);


        if (isOccupied) {

            option.textContent =
                `${section.section_name} - OCCUPIED`;

            option.disabled = true;

        } else {

            option.textContent =
                `${section.section_name} - AVAILABLE`;

        }


        if (
            selectedSectionId &&
            String(selectedSectionId)
            === String(section.id)
            &&
            !isOccupied
        ) {

            option.selected = true;

        }


        sectionSelect.appendChild(option);

    });

}


/*
|--------------------------------------------------------------------------
| YEAR LEVEL CHANGE
|--------------------------------------------------------------------------
*/

yearLevelSelect.addEventListener(
    "change",
    function () {

        loadSections(
            this.value
        );

    }
);


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

<?php

$initialYearLevel =
    !empty($_POST['year_level_id'])
        ? (int) $_POST['year_level_id']
        : (!empty($editStudent['year_level_id'])
            ? (int) $editStudent['year_level_id']
            : 0);


$initialSection =
    !empty($_POST['section_id'])
        ? (int) $_POST['section_id']
        : (!empty($editStudent['section_id'])
            ? (int) $editStudent['section_id']
            : 0);

?>


<?php if ($initialYearLevel): ?>

loadSections(
    <?= $initialYearLevel ?>,
    <?= $initialSection ?>
);

<?php endif; ?>


/*
|--------------------------------------------------------------------------
| MOBILE SIDEBAR
|--------------------------------------------------------------------------
*/

const mobileMenu =
    document.getElementById("mobileMenu");


const sidebar =
    document.getElementById("sidebar");


const sidebarOverlay =
    document.getElementById("sidebarOverlay");


mobileMenu.addEventListener(
    "click",
    function () {

        sidebar.classList.toggle("show");

        sidebarOverlay.classList.toggle("show");

    }
);


sidebarOverlay.addEventListener(
    "click",
    function () {

        sidebar.classList.remove("show");

        sidebarOverlay.classList.remove("show");

    }
);

</script>


</body>

</html>