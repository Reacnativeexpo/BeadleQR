<?php
session_start();
require "../db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

/* =========================================================
   FETCH YEAR LEVELS
========================================================= */

$year_levels = $conn->query("
    SELECT *
    FROM year_levels
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   ADD SUBJECT
========================================================= */

if (isset($_POST['add_subject'])) {

    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $year_level_id = $_POST['year_level_id'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("
        INSERT INTO subjects
        (
            subject_code,
            subject_name,
            year_level_id,
            description
        )
        VALUES
        (
            :subject_code,
            :subject_name,
            :year_level_id,
            :description
        )
    ");

    $stmt->execute([
        'subject_code' => $subject_code,
        'subject_name' => $subject_name,
        'year_level_id' => $year_level_id,
        'description' => $description
    ]);

    $message = "Subject Added Successfully!";
}


/* =========================================================
   FETCH SUBJECTS
========================================================= */

$subjects = $conn->query("
    SELECT
        subjects.*,
        year_levels.year_name
    FROM subjects
    LEFT JOIN year_levels
        ON subjects.year_level_id = year_levels.id
    ORDER BY subjects.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Subjects | BeadleQR Admin</title>


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- =====================================================
         POPPINS
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
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
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --purple: #8b5cf6;
        }


        /* =====================================================
           GLOBAL
        ===================================================== */

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

            overflow-x: hidden;
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
                0 2px 10px
                rgba(0,0,0,0.04);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .page-heading {

            display: flex;

            flex-direction: column;
        }


        .page-title {

            font-size: 20px;

            font-weight: 700;

            margin: 0;

            color: var(--text-dark);
        }


        .breadcrumb-text {

            font-size: 12px;

            color: var(--text-muted);

            margin-top: 2px;
        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 20px;
        }


        .notification-btn {

            width: 40px;

            height: 40px;

            border-radius: 11px;

            border: 1px solid #e2e8f0;

            background: white;

            color: var(--text-muted);

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            transition: all 0.2s;
        }


        .notification-btn:hover {

            color: var(--primary);

            border-color: #fed7aa;

            background: #fff7ed;
        }


        /* =====================================================
           ADMIN PROFILE
        ===================================================== */

        .admin-profile {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .profile-avatar {

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

            font-weight: 700;
        }


        .profile-info {

            line-height: 1.2;
        }


        .profile-name {

            font-size: 13px;

            font-weight: 600;

            color: var(--text-dark);
        }


        .profile-role {

            font-size: 11px;

            color: var(--text-muted);
        }


        /* =====================================================
           MOBILE MENU
        ===================================================== */

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

            background:
                rgba(15,23,42,0.45);

            z-index: 999;
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

            color: white;

            border-radius: 20px;

            padding: 25px 30px;

            margin-bottom: 25px;

            position: relative;

            overflow: hidden;

            box-shadow:
                0 10px 30px
                rgba(249,115,22,0.18);
        }


        .welcome-banner::after {

            content: "";

            position: absolute;

            width: 220px;

            height: 220px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.08);

            right: -70px;

            top: -90px;
        }


        .welcome-banner::before {

            content: "";

            position: absolute;

            width: 120px;

            height: 120px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.06);

            right: 130px;

            bottom: -80px;
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

            opacity: 0.9;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .dashboard-card {

            background: var(--card-bg);

            border: none;

            border-radius: 18px;

            box-shadow:
                0 8px 25px
                rgba(15,23,42,0.06);

            overflow: hidden;

            margin-bottom: 25px;
        }


        .card-header-modern {

            padding: 20px 25px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            border-bottom:
                1px solid #f1f5f9;
        }


        .card-header-left {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .header-icon {

            width: 42px;

            height: 42px;

            border-radius: 12px;

            background: #fff7ed;

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 17px;
        }


        .card-header-title {

            margin: 0;

            font-size: 17px;

            font-weight: 700;

            color: var(--text-dark);
        }


        .card-header-subtitle {

            margin: 2px 0 0;

            font-size: 11px;

            color: var(--text-muted);
        }


        .card-body-modern {

            padding: 25px;
        }


        /* =====================================================
           SUCCESS MESSAGE
        ===================================================== */

        .success {

            display: flex;

            align-items: center;

            gap: 10px;

            background: #ecfdf5;

            color: #047857;

            padding: 14px 17px;

            margin-bottom: 20px;

            border-radius: 12px;

            border: 1px solid #d1fae5;

            font-size: 13px;

            font-weight: 500;
        }


        /* =====================================================
           ADD BUTTON
        ===================================================== */

        .btn-add {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: white;

            border: none;

            padding: 10px 17px;

            border-radius: 10px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 600;

            transition: all 0.25s ease;
        }


        .btn-add:hover {

            color: white;

            transform: translateY(-2px);

            box-shadow:
                0 6px 16px
                rgba(249,115,22,0.25);
        }


        /* =====================================================
           TABLE
        ===================================================== */

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

            letter-spacing: 0.4px;

            padding: 14px;

            border-bottom:
                1px solid #e2e8f0;

            white-space: nowrap;
        }


        .modern-table tbody td {

            padding: 15px 14px;

            font-size: 13px;

            color: #475569;

            border-bottom:
                1px solid #f1f5f9;

            vertical-align: middle;
        }


        .modern-table tbody tr {

            transition: all 0.2s;
        }


        .modern-table tbody tr:hover {

            background: #fffaf5;
        }


        .modern-table tbody tr:last-child td {

            border-bottom: none;
        }


        /* =====================================================
           SUBJECT CODE
        ===================================================== */

        .subject-code {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            background: #fff7ed;

            color: var(--primary-dark);

            padding: 7px 11px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        /* =====================================================
           SUBJECT NAME
        ===================================================== */

        .subject-name {

            font-weight: 600;

            color: var(--text-dark);
        }


        /* =====================================================
           YEAR LEVEL
        ===================================================== */

        .year-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            background: #eff6ff;

            color: #2563eb;

            padding: 7px 11px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .description-text {

            color: #64748b;

            font-size: 12px;

            max-width: 350px;
        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

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

            color: var(--text-dark);

            margin-bottom: 5px;
        }


        .empty-state p {

            font-size: 12px;

            color: var(--text-muted);

            margin: 0;
        }


        /* =====================================================
           MODAL
        ===================================================== */

        .modal {

            background:
                rgba(15,23,42,0.55);
        }


        .modal-content {

            background: white;

            width: 100%;

            border: none;

            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 20px 60px
                rgba(15,23,42,0.18);
        }


        .modal-header {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: white;

            padding: 18px 22px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .modal-header h3 {

            font-size: 16px;

            font-weight: 600;

            margin: 0;
        }


        .modal-close {

            border: none;

            background: transparent;

            color: white;

            font-size: 20px;

            cursor: pointer;

            opacity: 0.85;
        }


        .modal-close:hover {

            opacity: 1;
        }


        .modal-body {

            padding: 25px;
        }


        /* =====================================================
           FORM
        ===================================================== */

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


        .form-group input,
        .form-group select,
        .form-group textarea {

            width: 100%;

            padding: 11px 13px;

            border: 1px solid #dbe2ea;

            border-radius: 11px;

            font-size: 13px;

            outline: none;

            background: white;

            transition: all 0.2s;
        }


        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 0.2rem
                rgba(249,115,22,0.12);
        }


        .form-group textarea {

            height: 110px;

            resize: vertical;
        }


        /* =====================================================
           MODAL ACTIONS
        ===================================================== */

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

            cursor: pointer;

            font-size: 13px;

            font-weight: 600;

            transition: all 0.2s;
        }


        .btn-cancel:hover {

            background: #cbd5e1;

            color: #334155;
        }


        .btn-save {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: white;

            border: none;

            padding: 10px 18px;

            border-radius: 10px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 600;

            transition: all 0.25s;
        }


        .btn-save:hover {

            color: white;

            transform: translateY(-2px);

            box-shadow:
                0 6px 16px
                rgba(249,115,22,0.25);
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 992px) {

            .sidebar {

                transform: translateX(-100%);
            }


            .sidebar.show {

                transform: translateX(0);
            }


            .main-content {

                margin-left: 0;
            }


            .mobile-menu-btn {

                display: flex;
            }


            .sidebar-overlay.show {

                display: block;
            }


            .topbar {

                padding: 0 20px;
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

                height: 65px;
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

                min-width: 850px;
            }


            .modal-content {

                width: calc(100% - 30px);

                margin: auto;
            }
        }


        @media (max-width: 576px) {

            .content-area {

                padding: 18px 15px;
            }


            .topbar {

                padding: 0 15px;
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

                gap: 12px;

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


<!-- =========================================================
     SIDEBAR OVERLAY
========================================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


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
    ====================================================== -->

    <header class="topbar">


        <div class="page-heading">


            <div
                class="d-flex align-items-center gap-2"
            >


                <button
                    class="mobile-menu-btn"
                    id="mobileMenuBtn"
                    type="button"
                >

                    <i class="fa-solid fa-bars"></i>

                </button>


                <h1 class="page-title">

                    Subjects

                </h1>


            </div>


            <div class="breadcrumb-text">

                Dashboard

                <span class="mx-1">/</span>

                Subjects

            </div>


        </div>


        <!-- TOPBAR RIGHT -->

        <div class="topbar-right">


            <!-- NOTIFICATION -->

            <button
                type="button"
                class="notification-btn"
                title="Notifications"
            >

                <i class="fa-regular fa-bell"></i>

            </button>


            <!-- ADMIN PROFILE -->

            <div class="admin-profile">


                <div class="profile-avatar">

                    <?php

                    $adminName =
                        $_SESSION['fullname'] ?? 'Admin';

                    echo strtoupper(
                        substr(
                            trim($adminName),
                            0,
                            1
                        )
                    );

                    ?>

                </div>


                <div class="profile-info">


                    <div class="profile-name">

                        <?= htmlspecialchars($adminName); ?>

                    </div>


                    <div class="profile-role">

                        Administrator

                    </div>


                </div>


            </div>


        </div>


    </header>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="content-area">


        <!-- =================================================
             WELCOME BANNER
        ================================================== -->

        <div class="welcome-banner">


            <div class="welcome-content">


                <div class="welcome-title">

                    <i
                        class="fa-solid fa-book me-2"
                    ></i>

                    Subject Management

                </div>


                <p class="welcome-text">

                    Create and manage subjects assigned
                    to different year levels.

                </p>


            </div>


        </div>


        <!-- =================================================
             SUBJECT CARD
        ================================================== -->

        <div class="dashboard-card">


            <!-- CARD HEADER -->

            <div class="card-header-modern">


                <div class="card-header-left">


                    <div class="header-icon">

                        <i class="fa-solid fa-book"></i>

                    </div>


                    <div>


                        <h5 class="card-header-title">

                            Subject List

                        </h5>


                        <p class="card-header-subtitle">

                            Manage all registered subjects

                        </p>


                    </div>


                </div>


                <!-- ADD BUTTON -->

                <button
                    type="button"
                    class="btn-add"
                    onclick="openModal()"
                >

                    <i class="fa-solid fa-plus me-1"></i>

                    Add Subject

                </button>


            </div>


            <!-- CARD BODY -->

            <div class="card-body-modern">


                <!-- SUCCESS -->

                <?php if ($message != ""): ?>


                    <div class="success">


                        <i
                            class="fa-solid fa-circle-check"
                        ></i>


                        <?= htmlspecialchars($message); ?>


                    </div>


                <?php endif; ?>


                <!-- TABLE -->

                <div class="table-wrapper">


                    <table class="modern-table">


                        <thead>


                            <tr>


                                <th width="7%">

                                    ID

                                </th>


                                <th>

                                    Subject Code

                                </th>


                                <th>

                                    Subject Name

                                </th>


                                <th>

                                    Year Level

                                </th>


                                <th>

                                    Description

                                </th>


                            </tr>


                        </thead>


                        <tbody>


                        <?php if (empty($subjects)): ?>


                            <tr>


                                <td colspan="5">


                                    <div class="empty-state">


                                        <div class="empty-icon">

                                            <i
                                                class="fa-solid fa-book-open"
                                            ></i>

                                        </div>


                                        <h6>

                                            No Subjects Found

                                        </h6>


                                        <p>

                                            Add a subject using
                                            the button above.

                                        </p>


                                    </div>


                                </td>


                            </tr>


                        <?php else: ?>


                            <?php foreach ($subjects as $row): ?>


                                <tr>


                                    <!-- ID -->

                                    <td>


                                        <span
                                            class="text-muted fw-semibold"
                                        >

                                            #<?= htmlspecialchars(
                                                $row['id']
                                            ); ?>


                                        </span>


                                    </td>


                                    <!-- SUBJECT CODE -->

                                    <td>


                                        <span class="subject-code">


                                            <i
                                                class="fa-solid fa-book"
                                            ></i>


                                            <?= htmlspecialchars(
                                                $row['subject_code']
                                            ); ?>


                                        </span>


                                    </td>


                                    <!-- SUBJECT NAME -->

                                    <td>


                                        <div class="subject-name">


                                            <?= htmlspecialchars(
                                                $row['subject_name']
                                            ); ?>


                                        </div>


                                    </td>


                                    <!-- YEAR LEVEL -->

                                    <td>


                                        <span class="year-badge">


                                            <i
                                                class="fa-solid fa-graduation-cap"
                                            ></i>


                                            <?= htmlspecialchars(
                                                $row['year_name']
                                                    ?? 'N/A'
                                            ); ?>


                                        </span>


                                    </td>


                                    <!-- DESCRIPTION -->

                                    <td>


                                        <span
                                            class="description-text"
                                        >

                                            <?= htmlspecialchars(
                                                $row['description']
                                                    ?? 'No description'
                                            ); ?>


                                        </span>


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
     ADD SUBJECT MODAL
========================================================= -->

<div
    class="modal"
    id="subjectModal"
>


    <div
        class="modal-dialog modal-dialog-centered"
        style="
            max-width: 500px;
            width: calc(100% - 30px);
            margin: auto;
        "
    >


        <div class="modal-content">


            <!-- MODAL HEADER -->

            <div class="modal-header">


                <h3>


                    <i
                        class="fa-solid fa-book me-2"
                    ></i>

                    Add Subject


                </h3>


                <button
                    type="button"
                    class="modal-close"
                    onclick="closeModal()"
                    aria-label="Close"
                >

                    <i class="fa-solid fa-xmark"></i>

                </button>


            </div>


            <!-- MODAL BODY -->

            <div class="modal-body">


                <form method="POST">


                    <!-- SUBJECT CODE -->

                    <div class="form-group">


                        <label>


                            <i
                                class="fa-solid fa-hashtag"
                            ></i>

                            Subject Code


                        </label>


                        <input
                            type="text"
                            name="subject_code"
                            required
                            placeholder="Enter subject code"
                            autocomplete="off"
                        >


                    </div>


                    <!-- SUBJECT NAME -->

                    <div class="form-group">


                        <label>


                            <i
                                class="fa-solid fa-book"
                            ></i>

                            Subject Name


                        </label>


                        <input
                            type="text"
                            name="subject_name"
                            required
                            placeholder="Enter subject name"
                            autocomplete="off"
                        >


                    </div>


                    <!-- YEAR LEVEL -->

                    <div class="form-group">


                        <label>


                            <i
                                class="fa-solid fa-graduation-cap"
                            ></i>

                            Year Level


                        </label>


                        <select
                            name="year_level_id"
                            required
                        >


                            <option value="">

                                Select Year Level

                            </option>


                            <?php foreach ($year_levels as $year): ?>


                                <option
                                    value="<?= htmlspecialchars(
                                        $year['id']
                                    ); ?>"
                                >

                                    <?= htmlspecialchars(
                                        $year['year_name']
                                    ); ?>


                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>


                    <!-- DESCRIPTION -->

                    <div class="form-group">


                        <label>


                            <i
                                class="fa-solid fa-align-left"
                            ></i>

                            Description


                        </label>


                        <textarea
                            name="description"
                            placeholder="Enter subject description (optional)"
                        ></textarea>


                    </div>


                    <!-- ACTIONS -->

                    <div class="actions">


                        <button
                            type="button"
                            class="btn-cancel"
                            onclick="closeModal()"
                        >

                            Cancel

                        </button>


                        <button
                            type="submit"
                            name="add_subject"
                            class="btn-save"
                        >

                            <i
                                class="fa-solid fa-floppy-disk me-1"
                            ></i>

                            Save Subject

                        </button>


                    </div>


                </form>


            </div>


        </div>


    </div>


</div>


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/* =========================================================
   MOBILE SIDEBAR
========================================================= */

const mobileMenuBtn =
    document.getElementById('mobileMenuBtn');

const sidebar =
    document.getElementById('sidebar');

const sidebarOverlay =
    document.getElementById('sidebarOverlay');


if (mobileMenuBtn) {

    mobileMenuBtn.addEventListener(
        'click',
        function () {

            sidebar.classList.toggle('show');

            sidebarOverlay.classList.toggle('show');

        }
    );

}


if (sidebarOverlay) {

    sidebarOverlay.addEventListener(
        'click',
        function () {

            sidebar.classList.remove('show');

            sidebarOverlay.classList.remove('show');

        }
    );

}


/* =========================================================
   OPEN MODAL
========================================================= */

function openModal() {

    document.getElementById(
        "subjectModal"
    ).style.display = "flex";

}


/* =========================================================
   CLOSE MODAL
========================================================= */

function closeModal() {

    document.getElementById(
        "subjectModal"
    ).style.display = "none";

}


/* =========================================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
========================================================= */

window.addEventListener(
    "click",
    function(e) {

        const modal =
            document.getElementById(
                "subjectModal"
            );


        if (e.target === modal) {

            closeModal();

        }

    }
);


/* =========================================================
   ESC KEY CLOSE MODAL
========================================================= */

document.addEventListener(
    "keydown",
    function(e) {

        if (e.key === "Escape") {

            closeModal();

        }

    }
);

</script>


</body>

</html>
