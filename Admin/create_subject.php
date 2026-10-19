<?php
session_start();
require "../db.php";

/* =========================================================
   ADMIN ACCESS
========================================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* =========================================================
   CSRF PROTECTION
========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
$message = "";
$messageType = "success";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   HANDLE ADD, EDIT, AND FORCE DELETE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($_SESSION['csrf_token'], $postedToken)
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "danger";
    } else {
        try {

            /* =================================================
               ADD SUBJECT
            ================================================= */
            if (isset($_POST['add_subject'])) {
                $subject_code = trim($_POST['subject_code'] ?? '');
                $subject_name = trim($_POST['subject_name'] ?? '');
                $year_level_id = filter_var(
                    $_POST['year_level_id'] ?? '',
                    FILTER_VALIDATE_INT
                );
                $description = trim($_POST['description'] ?? '');

                if (
                    $subject_code === '' ||
                    $subject_name === '' ||
                    !$year_level_id ||
                    $year_level_id < 1
                ) {
                    throw new Exception(
                        "Please complete all required fields."
                    );
                }

                $checkYear = $conn->prepare(
                    "SELECT id FROM public.year_levels WHERE id = :id"
                );
                $checkYear->execute(['id' => $year_level_id]);

                if (!$checkYear->fetchColumn()) {
                    throw new Exception(
                        "Please select a valid year level."
                    );
                }

                $checkCode = $conn->prepare("
                    SELECT id
                    FROM public.subjects
                    WHERE subject_code = :code
                ");
                $checkCode->execute(['code' => $subject_code]);

                if ($checkCode->fetchColumn()) {
                    throw new Exception(
                        "That subject code already exists."
                    );
                }

                $stmt = $conn->prepare("
                    INSERT INTO public.subjects
                        (subject_code, subject_name, year_level_id, description)
                    VALUES
                        (:code, :name, :year_id, :description)
                ");

                $stmt->execute([
                    'code' => $subject_code,
                    'name' => $subject_name,
                    'year_id' => $year_level_id,
                    'description' => $description !== ''
                        ? $description
                        : null
                ]);

                $message = "Subject added successfully!";
            }

            /* =================================================
               EDIT SUBJECT
            ================================================= */
            elseif (isset($_POST['edit_subject'])) {
                $id = filter_var(
                    $_POST['subject_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

                $subject_code = trim($_POST['subject_code'] ?? '');
                $subject_name = trim($_POST['subject_name'] ?? '');
                $year_level_id = filter_var(
                    $_POST['year_level_id'] ?? '',
                    FILTER_VALIDATE_INT
                );
                $description = trim($_POST['description'] ?? '');

                if (
                    !$id || $id < 1 ||
                    $subject_code === '' ||
                    $subject_name === '' ||
                    !$year_level_id || $year_level_id < 1
                ) {
                    throw new Exception(
                        "Please complete all required fields."
                    );
                }

                $checkSubject = $conn->prepare(
                    "SELECT id FROM public.subjects WHERE id = :id"
                );
                $checkSubject->execute(['id' => $id]);

                if (!$checkSubject->fetchColumn()) {
                    throw new Exception("Subject not found.");
                }

                $checkYear = $conn->prepare(
                    "SELECT id FROM public.year_levels WHERE id = :id"
                );
                $checkYear->execute(['id' => $year_level_id]);

                if (!$checkYear->fetchColumn()) {
                    throw new Exception(
                        "Please select a valid year level."
                    );
                }

                $checkCode = $conn->prepare("
                    SELECT id
                    FROM public.subjects
                    WHERE subject_code = :code
                      AND id <> :id
                ");
                $checkCode->execute([
                    'code' => $subject_code,
                    'id' => $id
                ]);

                if ($checkCode->fetchColumn()) {
                    throw new Exception(
                        "That subject code is already used by another subject."
                    );
                }

                $stmt = $conn->prepare("
                    UPDATE public.subjects
                    SET
                        subject_code = :code,
                        subject_name = :name,
                        year_level_id = :year_id,
                        description = :description
                    WHERE id = :id
                ");

                $stmt->execute([
                    'code' => $subject_code,
                    'name' => $subject_name,
                    'year_id' => $year_level_id,
                    'description' => $description !== ''
                        ? $description
                        : null,
                    'id' => $id
                ]);

                $message = "Subject updated successfully!";
            }

            /* =================================================
               PERMANENT FORCE DELETE
               Order:
               1. teacher_attendance
               2. attendance_sessions
               3. schedules
               4. subjects
            ================================================= */
            elseif (isset($_POST['force_delete_subject'])) {
                $id = filter_var(
                    $_POST['subject_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

                if (!$id || $id < 1) {
                    throw new Exception("Invalid subject selected.");
                }

                $conn->beginTransaction();

                try {
                    /* Lock and verify the subject. */
                    $checkSubject = $conn->prepare("
                        SELECT id, subject_name
                        FROM public.subjects
                        WHERE id = :id
                        FOR UPDATE
                    ");
                    $checkSubject->execute(['id' => $id]);
                    $subject = $checkSubject->fetch(PDO::FETCH_ASSOC);

                    if (!$subject) {
                        throw new Exception("Subject not found.");
                    }

                    /*
                     * Delete attendance records connected to schedules
                     * for this subject. Delete these before sessions and
                     * schedules because they reference those records.
                     */
                    $deleteAttendance = $conn->prepare("
                        DELETE FROM public.teacher_attendance
                        WHERE schedule_id IN (
                            SELECT id
                            FROM public.schedules
                            WHERE subject_id = :subject_id
                        )
                    ");
                    $deleteAttendance->execute([
                        'subject_id' => $id
                    ]);
                    $attendanceDeleted = $deleteAttendance->rowCount();

                    /*
                     * Delete QR sessions for the subject's schedules.
                     */
                    $deleteSessions = $conn->prepare("
                        DELETE FROM public.attendance_sessions
                        WHERE schedule_id IN (
                            SELECT id
                            FROM public.schedules
                            WHERE subject_id = :subject_id
                        )
                    ");
                    $deleteSessions->execute([
                        'subject_id' => $id
                    ]);
                    $sessionsDeleted = $deleteSessions->rowCount();

                    /*
                     * Remove teacher schedule assignments for this subject.
                     */
                    $deleteSchedules = $conn->prepare("
                        DELETE FROM public.schedules
                        WHERE subject_id = :subject_id
                    ");
                    $deleteSchedules->execute([
                        'subject_id' => $id
                    ]);
                    $schedulesDeleted = $deleteSchedules->rowCount();

                    /*
                     * Finally delete the subject.
                     */
                    $deleteSubject = $conn->prepare("
                        DELETE FROM public.subjects
                        WHERE id = :id
                    ");
                    $deleteSubject->execute(['id' => $id]);

                    if ($deleteSubject->rowCount() !== 1) {
                        throw new Exception(
                            "The subject could not be deleted."
                        );
                    }

                    $conn->commit();

                    $message = sprintf(
                        'Subject "%s" permanently deleted. Removed %d schedule(s), %d QR session(s), and %d attendance record(s).',
                        $subject['subject_name'],
                        $schedulesDeleted,
                        $sessionsDeleted,
                        $attendanceDeleted
                    );
                } catch (Throwable $deleteError) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }

                    throw $deleteError;
                }
            }

        } catch (PDOException $e) {
            error_log("Subject management error: " . $e->getMessage());

            if ($e->getCode() === '23503') {
                $message = "Deletion was blocked because another database record still references this subject or its related records. No partial deletion was committed. Check the database foreign-key relationships.";
            } else {
                $message = "A database error occurred. No partial deletion was committed where the operation was inside a transaction. Please check the server logs.";
            }

            $messageType = "danger";

        } catch (Throwable $e) {
            error_log("Subject operation error: " . $e->getMessage());
            $message = $e instanceof Exception
                ? $e->getMessage()
                : "An unexpected error occurred. Please try again.";
            $messageType = "danger";
        }
    }
}

/* =========================================================
   FETCH YEAR LEVELS
========================================================= */
$year_levels = $conn->query("
    SELECT id, year_name
    FROM public.year_levels
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   FETCH SUBJECTS
========================================================= */
$subjects = $conn->query("
    SELECT
        s.id,
        s.subject_code,
        s.subject_name,
        s.year_level_id,
        s.description,
        y.year_name
    FROM public.subjects s
    LEFT JOIN public.year_levels y
        ON s.year_level_id = y.id
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$adminName = $_SESSION['fullname'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Subjects | BeadleQR Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --sidebar-bg: #1e293b;
            --body-bg: #f1f5f9;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            margin: 0;
            background: var(--body-bg);
            min-height: 100vh;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        .topbar {
            background: white;
            min-height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 12px 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .breadcrumb-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .topbar-right, .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-btn {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            border: 1px solid #e2e8f0;
            background: white;
            color: var(--text-muted);
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg,#f97316,#ea580c);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .profile-name {
            font-size: 13px;
            font-weight: 600;
        }

        .profile-role {
            font-size: 11px;
            color: var(--text-muted);
        }

        .mobile-menu-btn {
            display: none;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 10px;
            background: #fff7ed;
            color: var(--primary);
            align-items: center;
            justify-content: center;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.45);
            z-index: 999;
        }

        .content-area {
            padding: 30px 35px;
        }

        .welcome-banner {
            background: linear-gradient(135deg,#f97316,#ea580c);
            color: white;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(249,115,22,.18);
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
            right: -70px;
            top: -90px;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .welcome-text {
            margin: 0;
            font-size: 13px;
            opacity: .92;
        }

        .dashboard-card {
            background: white;
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(15,23,42,.06);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .card-header-modern {
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 12px;
            background: #fff7ed;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-header-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .card-header-subtitle {
            margin: 3px 0 0;
            font-size: 11px;
            color: var(--text-muted);
        }

        .card-body-modern {
            padding: 25px;
        }

        .btn-add, .btn-save {
            background: linear-gradient(135deg,#f97316,#ea580c);
            color: white;
            border: none;
            padding: 10px 17px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .btn-add:hover, .btn-save:hover {
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(249,115,22,.25);
        }

        .alert-message {
            border-radius: 12px;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .modern-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .modern-table thead th {
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .modern-table tbody td {
            padding: 15px 14px;
            font-size: 13px;
            color: #475569;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .modern-table tbody tr:hover {
            background: #fffaf5;
        }

        .modern-table tbody tr:last-child td {
            border-bottom: none;
        }

        .subject-code, .year-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 11px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .subject-code {
            background: #fff7ed;
            color: #ea580c;
        }

        .year-badge {
            background: #eff6ff;
            color: #2563eb;
        }

        .subject-name {
            font-weight: 600;
            color: #1e293b;
        }

        .description-text {
            display: inline-block;
            color: #64748b;
            font-size: 12px;
            max-width: 300px;
            overflow-wrap: anywhere;
        }

        .action-buttons {
            display: flex;
            gap: 7px;
            align-items: center;
            white-space: nowrap;
        }

        .btn-edit, .btn-delete {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            border: none;
            transition: .2s;
        }

        .btn-edit {
            background: #eff6ff;
            color: #2563eb;
        }

        .btn-edit:hover {
            background: #2563eb;
            color: white;
        }

        .btn-delete {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 55px 20px;
        }

        .empty-icon {
            width: 65px;
            height: 65px;
            border-radius: 18px;
            background: #f8fafc;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 25px;
        }

        .empty-state h6 {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .empty-state p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
        }

        .modal-content {
            border: none;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(15,23,42,.18);
        }

        .modal-header {
            background: linear-gradient(135deg,#f97316,#ea580c);
            color: white;
            padding: 18px 22px;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 7px;
        }

        .form-group label i {
            color: var(--primary);
            margin-right: 5px;
        }

        .form-group input, .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #dbe2ea;
            border-radius: 11px;
            font-size: 13px;
            outline: none;
            background: white;
        }

        .form-group input:focus, .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .2rem rgba(249,115,22,.12);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #475569;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
        }

        .delete-warning-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: #fef2f2;
            color: #dc2626;
            font-size: 27px;
        }

        .delete-warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            border-radius: 12px;
            padding: 14px;
            font-size: 12px;
            line-height: 1.7;
        }

        .btn-permanent-delete {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-permanent-delete:hover {
            background: #b91c1c;
            color: white;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: inline-flex;
            }

            .sidebar-overlay.show {
                display: block;
            }

            .topbar {
                padding: 12px 20px;
            }

            .content-area {
                padding: 25px 20px;
            }

            .profile-info {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .topbar {
                min-height: 65px;
            }

            .page-title {
                font-size: 17px;
            }

            .welcome-title {
                font-size: 19px;
            }

            .card-header-modern {
                padding: 17px 18px;
            }

            .card-body-modern {
                padding: 18px;
            }

            .table-wrapper {
                margin: 0 -18px;
            }

            .modern-table {
                min-width: 800px;
            }
        }

        @media (max-width: 576px) {
            .content-area {
                padding: 18px 15px;
            }

            .topbar {
                padding: 10px 15px;
            }

            .notification-btn {
                display: none;
            }

            .welcome-banner {
                padding: 22px;
            }

            .welcome-title {
                font-size: 17px;
            }

            .welcome-text {
                font-size: 11px;
            }

            .card-header-modern {
                align-items: flex-start;
                flex-direction: column;
            }

            .btn-add {
                width: 100%;
            }

            .modal-body {
                padding: 20px;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .actions button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <header class="topbar">
        <div>
            <div class="d-flex align-items-center gap-2">
                <button class="mobile-menu-btn" id="mobileMenuBtn"
                        type="button" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="page-title">Subjects</h1>
            </div>

            <div class="breadcrumb-text">
                Dashboard <span class="mx-1">/</span> Subjects
            </div>
        </div>

        <div class="topbar-right">
            <button type="button" class="notification-btn"
                    title="Notifications">
                <i class="fa-regular fa-bell"></i>
            </button>

            <div class="admin-profile">
                <div class="profile-avatar">
                    <?= e(strtoupper(substr(trim($adminName), 0, 1))); ?>
                </div>

                <div class="profile-info">
                    <div class="profile-name"><?= e($adminName); ?></div>
                    <div class="profile-role">Administrator</div>
                </div>
            </div>
        </div>
    </header>

    <main class="content-area">

        <div class="welcome-banner">
            <div class="welcome-content">
                <div class="welcome-title">
                    <i class="fa-solid fa-book me-2"></i>
                    Subject Management
                </div>
                <p class="welcome-text">
                    Create, update, and manage subjects assigned to different year levels.
                </p>
            </div>
        </div>

        <div class="dashboard-card">

            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="header-icon">
                        <i class="fa-solid fa-book"></i>
                    </div>
                    <div>
                        <h5 class="card-header-title">Subject List</h5>
                        <p class="card-header-subtitle">
                            <?= count($subjects); ?> registered subject(s)
                        </p>
                    </div>
                </div>

                <button type="button" class="btn-add"
                        onclick="openAddModal()">
                    <i class="fa-solid fa-plus me-1"></i>
                    Add Subject
                </button>
            </div>

            <div class="card-body-modern">

                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?= e($messageType); ?> alert-dismissible fade show alert-message"
                         role="alert">
                        <i class="fa-solid <?= $messageType === 'success'
                            ? 'fa-circle-check'
                            : 'fa-circle-exclamation'; ?> me-2"></i>

                        <?= e($message); ?>

                        <button type="button" class="btn-close"
                                data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="table-wrapper">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Year Level</th>
                                <th>Description</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fa-solid fa-book-open"></i>
                                        </div>
                                        <h6>No Subjects Found</h6>
                                        <p>Add your first subject using the button above.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($subjects as $row): ?>
                                <tr>
                                    <td>
                                        <span class="subject-code">
                                            <i class="fa-solid fa-book"></i>
                                            <?= e($row['subject_code']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="subject-name">
                                            <?= e($row['subject_name']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="year-badge">
                                            <i class="fa-solid fa-graduation-cap"></i>
                                            <?= e($row['year_name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="description-text">
                                            <?= e(
                                                trim((string)($row['description'] ?? '')) !== ''
                                                    ? $row['description']
                                                    : 'No description'
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="action-buttons">

                                            <button type="button"
                                                class="btn-edit"
                                                title="Edit Subject"
                                                aria-label="Edit subject"
                                                data-id="<?= (int)$row['id']; ?>"
                                                data-code="<?= e($row['subject_code']); ?>"
                                                data-name="<?= e($row['subject_name']); ?>"
                                                data-year="<?= (int)$row['year_level_id']; ?>"
                                                data-description="<?= e($row['description'] ?? ''); ?>"
                                                onclick="openEditModal(this)">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>

                                            <button type="button"
                                                class="btn-delete"
                                                title="Permanently Delete"
                                                aria-label="Permanently delete subject"
                                                data-id="<?= (int)$row['id']; ?>"
                                                data-name="<?= e($row['subject_name']); ?>"
                                                onclick="openDeleteModal(this)">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- =========================================================
     ADD / EDIT SUBJECT MODAL
========================================================= -->
<div class="modal fade" id="subjectModal" tabindex="-1"
     aria-labelledby="subjectModalTitle" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered"
         style="max-width:500px;">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="subjectModalTitle">
                    <i class="fa-solid fa-book me-2"></i>
                    Add Subject
                </h5>

                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form method="POST" id="subjectForm">

                    <input type="hidden" name="csrf_token"
                           value="<?= e($csrf_token); ?>">

                    <input type="hidden" name="subject_id" id="subject_id">

                    <div class="form-group">
                        <label for="subject_code">
                            <i class="fa-solid fa-hashtag"></i> Subject Code
                        </label>
                        <input type="text" name="subject_code" id="subject_code"
                               required maxlength="100"
                               placeholder="Enter subject code"
                               autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="subject_name">
                            <i class="fa-solid fa-book"></i> Subject Name
                        </label>
                        <input type="text" name="subject_name" id="subject_name"
                               required maxlength="255"
                               placeholder="Enter subject name"
                               autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="year_level_id">
                            <i class="fa-solid fa-graduation-cap"></i> Year Level
                        </label>

                        <select name="year_level_id" id="year_level_id" required>
                            <option value="">Select Year Level</option>

                            <?php foreach ($year_levels as $year): ?>
                                <option value="<?= (int)$year['id']; ?>">
                                    <?= e($year['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">
                            <i class="fa-solid fa-align-left"></i> Description
                        </label>
                        <textarea name="description" id="description"
                                  placeholder="Enter subject description (optional)"></textarea>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn-cancel"
                                data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" name="add_subject"
                                id="saveSubjectButton" value="1"
                                class="btn-save">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Save Subject
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     PERMANENT DELETE CONFIRMATION MODAL
========================================================= -->
<div class="modal fade" id="deleteSubjectModal" tabindex="-1"
     aria-labelledby="deleteSubjectModalTitle" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered"
         style="max-width:480px;">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="deleteSubjectModalTitle">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    Permanently Delete Subject?
                </h5>

                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body text-center">

                <div class="delete-warning-icon">
                    <i class="fa-solid fa-trash-can"></i>
                </div>

                <h6 class="fw-bold mb-2" id="deleteSubjectName">
                    Selected Subject
                </h6>

                <p class="text-secondary small mb-3">
                    Are you sure you want to permanently delete this subject?
                </p>

                <div class="delete-warning text-start">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <strong>Warning:</strong>
                    This will permanently delete the subject, its schedules,
                    QR sessions, and attendance history. This cannot be undone.
                </div>

                <form method="POST" id="forceDeleteForm">
                    <input type="hidden" name="csrf_token"
                           value="<?= e($csrf_token); ?>">

                    <input type="hidden" name="subject_id"
                           id="delete_subject_id">

                    <div class="actions">
                        <button type="button" class="btn-cancel"
                                data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit"
                                name="force_delete_subject"
                                value="1"
                                class="btn-permanent-delete"
                                id="confirmPermanentDelete">
                            <i class="fa-solid fa-trash-can me-1"></i>
                            Permanently Delete
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* =========================================================
   MOBILE SIDEBAR
========================================================= */
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (mobileMenuBtn && sidebar) {
    mobileMenuBtn.addEventListener('click', function () {
        sidebar.classList.toggle('show');

        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('show');
        }
    });
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function () {
        if (sidebar) {
            sidebar.classList.remove('show');
        }

        sidebarOverlay.classList.remove('show');
    });
}

/* =========================================================
   ADD / EDIT SUBJECT MODAL
========================================================= */
const subjectModalElement = document.getElementById('subjectModal');
const subjectModal = new bootstrap.Modal(subjectModalElement);
const subjectForm = document.getElementById('subjectForm');
const saveSubjectButton = document.getElementById('saveSubjectButton');

function openAddModal() {
    subjectForm.reset();

    document.getElementById('subject_id').value = '';

    document.getElementById('subjectModalTitle').innerHTML =
        '<i class="fa-solid fa-book me-2"></i> Add Subject';

    saveSubjectButton.name = 'add_subject';
    saveSubjectButton.innerHTML =
        '<i class="fa-solid fa-floppy-disk me-1"></i> Save Subject';

    subjectModal.show();
}

function openEditModal(button) {
    subjectForm.reset();

    document.getElementById('subject_id').value = button.dataset.id;
    document.getElementById('subject_code').value = button.dataset.code;
    document.getElementById('subject_name').value = button.dataset.name;
    document.getElementById('year_level_id').value = button.dataset.year;
    document.getElementById('description').value =
        button.dataset.description || '';

    document.getElementById('subjectModalTitle').innerHTML =
        '<i class="fa-solid fa-pen-to-square me-2"></i> Edit Subject';

    saveSubjectButton.name = 'edit_subject';
    saveSubjectButton.innerHTML =
        '<i class="fa-solid fa-floppy-disk me-1"></i> Update Subject';

    subjectModal.show();
}

/* =========================================================
   PERMANENT DELETE CONFIRMATION
========================================================= */
const deleteModalElement =
    document.getElementById('deleteSubjectModal');

const deleteSubjectModal =
    new bootstrap.Modal(deleteModalElement);

const forceDeleteForm =
    document.getElementById('forceDeleteForm');

const deleteSubjectId =
    document.getElementById('delete_subject_id');

const deleteSubjectName =
    document.getElementById('deleteSubjectName');

const confirmDeleteButton =
    document.getElementById('confirmPermanentDelete');

function openDeleteModal(button) {
    deleteSubjectId.value = button.dataset.id;
    deleteSubjectName.textContent = button.dataset.name;

    confirmDeleteButton.disabled = false;
    confirmDeleteButton.innerHTML =
        '<i class="fa-solid fa-trash-can me-1"></i> Permanently Delete';

    deleteSubjectModal.show();
}

/* Prevent accidental double submission. */
forceDeleteForm.addEventListener('submit', function () {
    confirmDeleteButton.disabled = true;
    confirmDeleteButton.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Deleting...';
});
</script>

</body>
</html>
