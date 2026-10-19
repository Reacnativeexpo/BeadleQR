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
// MESSAGES
// =========================================================

$message = "";
$success = "";

$toast_message = "";
$toast_type = "";

if (isset($_SESSION['toast_message'])) {

    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'] ?? 'success';

    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// =========================================================
// TEACHER IMAGE UPLOAD SETTINGS
// =========================================================

$uploadDir = "../uploads/teachers/";
$uploadDbPath = "uploads/teachers/";

// Create upload folder if it does not exist
if (!is_dir($uploadDir)) {

    mkdir($uploadDir, 0755, true);
}

// =========================================================
// SAVE TEACHER
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $employee_id = trim($_POST['employee_id'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $profileImagePath = null;

    // ---------------------------------------------------------
    // VALIDATION
    // ---------------------------------------------------------

    if (
        $employee_id === '' ||
        $fullname === '' ||
        $email === '' ||
        $username === '' ||
        $password === ''
    ) {

        $message = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";

    } elseif (strlen($password) < 6) {

        $message = "Password must be at least 6 characters long.";

    } else {

        try {

            // -----------------------------------------------------
            // CHECK DUPLICATES
            // -----------------------------------------------------

            $check = $conn->prepare("
                SELECT id
                FROM public.users
                WHERE employee_id = :employee_id
                   OR LOWER(email) = LOWER(:email)
                   OR LOWER(username) = LOWER(:username)
                LIMIT 1
            ");

            $check->execute([
                'employee_id' => $employee_id,
                'email' => $email,
                'username' => $username
            ]);

            $existingUser = $check->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {

                $message = "Employee ID, Email, or Username already exists.";
 
            } else {

                // -------------------------------------------------
                // PROFILE IMAGE
                // -------------------------------------------------

                if (
                    isset($_FILES['profile_image']) &&
                    $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
                ) {

                    $file = $_FILES['profile_image'];

                    // Check upload error
                    if ($file['error'] !== UPLOAD_ERR_OK) {

                        throw new Exception(
                            "There was a problem uploading the profile picture."
                        );
                    }

                    // Maximum 5 MB
                    if ($file['size'] > 5 * 1024 * 1024) {

                        throw new Exception(
                            "Profile picture must not exceed 5 MB."
                        );
                    }

                    // Verify actual image
                    $imageInfo = getimagesize($file['tmp_name']);

                    if ($imageInfo === false) {

                        throw new Exception(
                            "The uploaded file is not a valid image."
                        );
                    }

                    // Allowed MIME types
                    $allowedMimeTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    $mimeType = $imageInfo['mime'];

                    if (!isset($allowedMimeTypes[$mimeType])) {

                        throw new Exception(
                            "Only JPG, JPEG, PNG, and WEBP images are allowed."
                        );
                    }

                    // Generate random filename
                    $extension = $allowedMimeTypes[$mimeType];

                    $newFileName =
                        'teacher_' .
                        bin2hex(random_bytes(16)) .
                        '.' .
                        $extension;

                    $destination =
                        $uploadDir .
                        $newFileName;

                    // Move uploaded file
                    if (!move_uploaded_file(
                        $file['tmp_name'],
                        $destination
                    )) {

                        throw new Exception(
                            "Unable to save the profile picture."
                        );
                    }

                    // Path stored in database
                    $profileImagePath =
                        $uploadDbPath .
                        $newFileName;
                }

                // -------------------------------------------------
                // HASH PASSWORD
                // -------------------------------------------------

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // -------------------------------------------------
                // INSERT TEACHER
                // -------------------------------------------------

                $stmt = $conn->prepare("
                    INSERT INTO public.users
                    (
                        employee_id,
                        fullname,
                        email,
                        username,
                        password,
                        role,
                        profile_image
                    )
                    VALUES
                    (
                        :employee_id,
                        :fullname,
                        :email,
                        :username,
                        :password,
                        'teacher',
                        :profile_image
                    )
                ");

                $stmt->execute([
                    'employee_id' => $employee_id,
                    'fullname' => $fullname,
                    'email' => $email,
                    'username' => $username,
                    'password' => $hashed_password,
                    'profile_image' => $profileImagePath
                ]);

                $success = "Teacher added successfully.";

                // Clear form after successful save
                $_POST = [];
            }

        } catch (Exception $e) {

            $message = $e->getMessage();

        } catch (PDOException $e) {

            $message = "Unable to add teacher. Please try again.";

            // For debugging:
            // $message = $e->getMessage();
        }
    }
}

// =========================================================
// FETCH TEACHERS
// =========================================================

$teachers = $conn->query("
    SELECT
        id,
        employee_id,
        fullname,
        email,
        username,
        role,
        profile_image,
        created_at
    FROM public.users
    WHERE role = 'teacher'
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Teachers | BeadleQR Admin</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- Poppins -->
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

            overflow: hidden;
        }


        .profile-avatar img {

            width: 100%;

            height: 100%;

            object-fit: cover;
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
           MOBILE BUTTON
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


        /* =====================================================
           CONTENT
        ===================================================== */

        .content-area {

            padding: 30px 35px;
        }


        /* =====================================================
           WELCOME
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
           FORM
        ===================================================== */

        .form-section {

            background: #f8fafc;

            border-radius: 15px;

            padding: 20px;

            margin-bottom: 10px;
        }


        .form-label-modern {

            font-size: 13px;

            font-weight: 600;

            color: #374151;

            margin-bottom: 7px;

            display: block;
        }


        .form-label-modern i {

            color: var(--primary);

            margin-right: 5px;
        }


        .form-control {

            border-radius: 11px;

            padding: 11px 13px;

            border: 1px solid #dbe2ea;

            font-size: 13px;

            transition: all 0.2s;

            background: white;
        }


        .form-control:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 0.2rem
                rgba(249,115,22,0.12);
        }


        .form-text {

            font-size: 11px;
        }


        /* =====================================================
           PROFILE IMAGE UPLOAD
        ===================================================== */

        .profile-upload-wrapper {

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            padding: 10px 0 20px;
        }


        .profile-preview {

            width: 120px;

            height: 120px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #fff7ed,
                    #ffedd5
                );

            border:
                4px solid white;

            box-shadow:
                0 8px 25px
                rgba(15,23,42,0.12);

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            color: var(--primary);

            font-size: 45px;

            margin-bottom: 15px;
        }


        .profile-preview img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .profile-upload-label {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 9px 15px;

            background: white;

            border: 1px solid #fed7aa;

            color: var(--primary-dark);

            border-radius: 10px;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;

            transition: all 0.2s;
        }


        .profile-upload-label:hover {

            background: #fff7ed;

            border-color: var(--primary);
        }


        .profile-upload-input {

            display: none;
        }


        .profile-upload-help {

            font-size: 10px;

            color: var(--text-muted);

            margin-top: 7px;

            text-align: center;
        }


        /* =====================================================
           PASSWORD
        ===================================================== */

        .password-wrapper {

            position: relative;
        }


        .password-toggle {

            position: absolute;

            right: 13px;

            top: 50%;

            transform: translateY(-50%);

            color: #94a3b8;

            cursor: pointer;

            transition: color 0.2s;
        }


        .password-toggle:hover {

            color: var(--primary);
        }


        /* =====================================================
           BUTTONS
        ===================================================== */

        .btn-save {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            border: none;

            color: white;

            border-radius: 10px;

            padding: 10px 19px;

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


        .btn-back {

            border-radius: 10px;

            padding: 10px 19px;

            font-size: 13px;
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
           TEACHER PROFILE IN TABLE
        ===================================================== */

        .teacher-profile-cell {

            display: flex;

            align-items: center;

            gap: 12px;

            min-width: 220px;
        }


        .teacher-table-avatar {

            width: 45px;

            height: 45px;

            min-width: 45px;

            border-radius: 50%;

            overflow: hidden;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #fff7ed,
                    #ffedd5
                );

            color: var(--primary);

            border: 2px solid #fff;

            box-shadow:
                0 3px 10px
                rgba(15,23,42,0.10);
        }


        .teacher-table-avatar img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .teacher-name-wrapper {

            display: flex;

            flex-direction: column;

            min-width: 0;
        }


        .teacher-name {

            font-weight: 600;

            color: var(--text-dark);

            white-space: nowrap;
        }


        .teacher-role-small {

            font-size: 10px;

            color: var(--text-muted);

            margin-top: 2px;
        }


        .email-text {

            color: #64748b;

            font-size: 12px;
        }


        .teacher-badge {

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


        .username-badge {

            background: #f1f5f9;

            color: #475569;

            padding: 6px 10px;

            border-radius: 8px;

            font-size: 11px;

            font-weight: 500;
        }


        /* =====================================================
           ACTIONS
        ===================================================== */

        .action-buttons {

            display: flex;

            align-items: center;

            gap: 5px;
        }


        .action-btn {

            width: 35px;

            height: 35px;

            border-radius: 9px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border: none;

            transition: all 0.2s;

            text-decoration: none;
        }


        .action-btn:hover {

            transform: translateY(-2px);
        }


        .btn-assign {

            background: #eff6ff;

            color: #2563eb;
        }


        .btn-assign:hover {

            background: #dbeafe;

            color: #1d4ed8;
        }


        .btn-view {

            background: #ecfdf5;

            color: #059669;
        }


        .btn-view:hover {

            background: #d1fae5;

            color: #047857;
        }


        .btn-edit {

            background: #fffbeb;

            color: #d97706;
        }


        .btn-edit:hover {

            background: #fef3c7;

            color: #b45309;
        }


        .btn-delete {

            background: #fef2f2;

            color: #dc2626;
        }


        .btn-delete:hover {

            background: #fee2e2;

            color: #b91c1c;
        }


        /* =====================================================
           EMPTY
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
           ALERT
        ===================================================== */

        .alert {

            border-radius: 12px;

            border: none;

            font-size: 13px;
        }


        /* =====================================================
           MODAL
        ===================================================== */

        .modal-content {

            border: none;

            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 20px 60px
                rgba(15,23,42,0.18);
        }


        .modal-header {

            border: none;

            padding: 18px 22px;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: white;
        }


        .modal-header .modal-title {

            font-size: 16px;

            font-weight: 600;
        }


        .modal-body {

            padding: 25px;
        }


        .modal-footer {

            border-top:
                1px solid #f1f5f9;

            padding: 15px 22px;
        }


        /* =====================================================
           EDIT PROFILE IMAGE
        ===================================================== */

        .edit-profile-preview {

            width: 90px;

            height: 90px;

            border-radius: 50%;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #fff7ed,
                    #ffedd5
                );

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 35px;

            margin: 0 auto 15px;

            border: 3px solid white;

            box-shadow:
                0 6px 20px
                rgba(15,23,42,0.12);
        }


        .edit-profile-preview img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        /* =====================================================
           DELETE MODAL
        ===================================================== */

        .delete-modal .modal-header {

            background:
                linear-gradient(
                    135deg,
                    #ef4444,
                    #dc2626
                );
        }


        .delete-icon {

            width: 75px;

            height: 75px;

            border-radius: 50%;

            background: #fef2f2;

            color: #ef4444;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 32px;

            margin: 0 auto 18px;
        }


        /* =====================================================
           TOAST
        ===================================================== */

        .toast-container {

            position: fixed;

            top: 85px;

            right: 25px;

            z-index: 9999;
        }


        .custom-toast {

            min-width: 320px;

            max-width: 400px;

            border-radius: 15px;

            border: none;

            box-shadow:
                0 10px 40px
                rgba(0,0,0,0.15);

            overflow: hidden;

            animation:
                slideInRight 0.4s ease-out;

            margin-bottom: 10px;
        }


        .custom-toast.toast-success {

            background:
                linear-gradient(
                    135deg,
                    #10b981,
                    #059669
                );

            color: white;
        }


        .custom-toast.toast-error {

            background:
                linear-gradient(
                    135deg,
                    #ef4444,
                    #dc2626
                );

            color: white;
        }


        .custom-toast.toast-warning {

            background:
                linear-gradient(
                    135deg,
                    #f59e0b,
                    #d97706
                );

            color: white;
        }


        .custom-toast.toast-info {

            background:
                linear-gradient(
                    135deg,
                    #3b82f6,
                    #2563eb
                );

            color: white;
        }


        .toast-body {

            padding: 15px 18px;

            display: flex;

            align-items: center;

            gap: 11px;
        }


        .toast-icon {

            width: 35px;

            height: 35px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.2);

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;
        }


        .toast-content {

            flex: 1;
        }


        .toast-title {

            font-size: 14px;

            font-weight: 700;

            margin-bottom: 2px;
        }


        .toast-message {

            font-size: 12px;

            opacity: 0.95;
        }


        .toast-close {

            border: none;

            background: none;

            color: white;

            opacity: 0.8;

            cursor: pointer;
        }


        .toast-close:hover {

            opacity: 1;
        }


        .toast-progress {

            height: 3px;

            background:
                rgba(255,255,255,0.25);
        }


        .toast-progress-bar {

            height: 100%;

            background:
                rgba(255,255,255,0.7);

            animation:
                progressShrink 4s linear forwards;
        }


        @keyframes slideInRight {

            from {

                transform: translateX(100%);

                opacity: 0;
            }

            to {

                transform: translateX(0);

                opacity: 1;
            }
        }


        @keyframes slideOutRight {

            from {

                transform: translateX(0);

                opacity: 1;
            }

            to {

                transform: translateX(100%);

                opacity: 0;
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


        .toast-hiding {

            animation:
                slideOutRight 0.3s ease-in forwards;
        }

/* =====================================================
   INCREASE MAIN CONTENT FONT SIZES
   SIDEBAR REMAINS UNCHANGED
   ===================================================== */

/* Topbar */
.page-title {
    font-size: 21px;
}

.breadcrumb-text {
    font-size: 14px;
}

.profile-name {
    font-size: 15px;
}

.profile-role {
    font-size: 13px;
}

/* Welcome Section */
.welcome-title {
    font-size: 28px;
}

.welcome-text {
    font-size: 15px;
}

/* Card Headers */
.card-header-title {
    font-size: 19px;
}

.card-header-subtitle {
    font-size: 14px;
}

/* Form */
.form-label-modern {
    font-size: 15px;
}

.form-control {
    font-size: 15px;
}

.form-text {
    font-size: 13px;
}

/* Profile Upload */
.profile-upload-label {
    font-size: 14px;
}

.profile-upload-help {
    font-size: 12px;
}

/* Buttons */
.btn-save {
    font-size: 15px;
}

.btn-back {
    font-size: 15px;
}

/* Table */
.modern-table thead th {
    font-size: 13px;
}

.modern-table tbody td {
    font-size: 15px;
}

/* Teacher Information */
.teacher-name {
    font-size: 15px;
}

.teacher-role-small {
    font-size: 12px;
}

.email-text {
    font-size: 14px;
}

.teacher-badge {
    font-size: 13px;
}

.username-badge {
    font-size: 13px;
}

/* Empty State */
.empty-state h6 {
    font-size: 16px;
}

.empty-state p {
    font-size: 14px;
}

/* Alert */
.alert {
    font-size: 15px;
}

/* Modal */
.modal-header .modal-title {
    font-size: 18px;
}

/* Toast */
.toast-title {
    font-size: 16px;
}

.toast-message {
    font-size: 14px;
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


            .topbar {

                padding: 0 20px;
            }


            .content-area {

                padding: 25px 20px;
            }


            .sidebar-overlay.show {

                display: block;
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

                min-width: 950px;
            }


            .custom-toast {

                min-width: auto;

                width:
                    calc(100vw - 40px);
            }


            .toast-container {

                left: 20px;

                right: 20px;

                top: 75px;
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


            .form-section {

                padding: 15px;
            }


            .sidebar-logo {

                font-size: 18px;
            }


            .profile-preview {

                width: 105px;

                height: 105px;
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
     SIDEBAR OVERLAY
========================================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">

        <div class="page-heading">

            <div class="d-flex align-items-center gap-2">

                <button
                    class="mobile-menu-btn"
                    id="mobileMenuBtn"
                    type="button"
                >

                    <i class="fa-solid fa-bars"></i>

                </button>


                <h1 class="page-title">

                    Teachers

                </h1>

            </div>


            <div class="breadcrumb-text">

                Dashboard

                <span class="mx-1">/</span>

                Teachers

            </div>

        </div>


        <div class="topbar-right">


            <button
                type="button"
                class="notification-btn"
                title="Notifications"
            >

                <i class="fa-regular fa-bell"></i>

            </button>


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
             WELCOME
        ================================================== -->

        <div class="welcome-banner">

            <div class="welcome-content">

                <div class="welcome-title">

                    <i
                        class="fa-solid fa-chalkboard-user me-2"
                    ></i>

                    Teacher Management

                </div>


                <p class="welcome-text">

                    Add, manage, edit, and view teacher information
                    and teaching schedules.

                </p>

            </div>

        </div>


        <!-- =================================================
             ADD TEACHER
        ================================================= -->

        <div class="dashboard-card">


            <div class="card-header-modern">

                <div class="card-header-left">

                    <div class="header-icon">

                        <i class="fa-solid fa-user-plus"></i>

                    </div>


                    <div>

                        <h5 class="card-header-title">

                            Add Teacher

                        </h5>


                        <p class="card-header-subtitle">

                            Create a new teacher account

                        </p>

                    </div>

                </div>

            </div>


            <div class="card-body-modern">


                <!-- ERROR -->

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


                <!-- SUCCESS -->

                <?php if ($success): ?>

                    <div
                        class="alert alert-success alert-dismissible fade show"
                    >

                        <i
                            class="fa-solid fa-circle-check me-2"
                        ></i>

                        <?= htmlspecialchars($success); ?>


                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                        ></button>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <div class="form-section">

                    <form
                        method="POST"
                        action=""
                        id="addTeacherForm"
                        enctype="multipart/form-data"
                    >


                        <!-- =================================================
                             PROFILE IMAGE
                        ================================================== -->

                        <div class="profile-upload-wrapper">


                            <div
                                class="profile-preview"
                                id="profilePreview"
                            >

                                <i
                                    class="fa-solid fa-user-tie"
                                    id="defaultProfileIcon"
                                ></i>


                                <img
                                    id="profilePreviewImage"
                                    src=""
                                    alt="Profile Preview"
                                    style="display:none;"
                                >

                            </div>


                            <label
                                for="profile_image"
                                class="profile-upload-label"
                            >

                                <i class="fa-solid fa-camera"></i>

                                Choose Profile Picture

                            </label>


                            <input
                                type="file"
                                name="profile_image"
                                id="profile_image"
                                class="profile-upload-input"
                                accept="image/jpeg,image/png,image/webp"
                            >


                            <div class="profile-upload-help">

                                JPG, PNG, or WEBP • Maximum 5 MB

                            </div>

                        </div>


                        <!-- ROW 1 -->

                        <div class="row">


                            <!-- EMPLOYEE ID -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="employee_id"
                                    class="form-label-modern"
                                >

                                    <i
                                        class="fa-solid fa-id-card"
                                    ></i>

                                    Employee ID

                                </label>


                                <input
                                    type="text"
                                    name="employee_id"
                                    id="employee_id"
                                    class="form-control"
                                    required
                                    placeholder="Enter employee ID"
                                    maxlength="50"
                                    autocomplete="off"
                                >

                            </div>


                            <!-- FULL NAME -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="fullname"
                                    class="form-label-modern"
                                >

                                    <i
                                        class="fa-solid fa-user"
                                    ></i>

                                    Full Name

                                </label>


                                <input
                                    type="text"
                                    name="fullname"
                                    id="fullname"
                                    class="form-control"
                                    required
                                    placeholder="Enter full name"
                                    maxlength="100"
                                    autocomplete="name"
                                >

                            </div>

                        </div>


                        <!-- ROW 2 -->

                        <div class="row">


                            <!-- EMAIL -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="email"
                                    class="form-label-modern"
                                >

                                    <i
                                        class="fa-solid fa-envelope"
                                    ></i>

                                    Email

                                </label>


                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    required
                                    placeholder="Enter email address"
                                    maxlength="100"
                                    autocomplete="email"
                                >

                            </div>


                            <!-- USERNAME -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="username"
                                    class="form-label-modern"
                                >

                                    <i
                                        class="fa-solid fa-user-tag"
                                    ></i>

                                    Username

                                </label>


                                <input
                                    type="text"
                                    name="username"
                                    id="username"
                                    class="form-control"
                                    required
                                    placeholder="Enter username"
                                    maxlength="50"
                                    autocomplete="username"
                                >

                            </div>

                        </div>


                        <!-- ROW 3 -->

                        <div class="row">


                            <!-- PASSWORD -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="password"
                                    class="form-label-modern"
                                >

                                    <i
                                        class="fa-solid fa-lock"
                                    ></i>

                                    Password

                                </label>


                                <div class="password-wrapper">

                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        class="form-control"
                                        required
                                        placeholder="Min. 6 characters"
                                        minlength="6"
                                        autocomplete="new-password"
                                    >


                                    <i
                                        class="fa-solid fa-eye password-toggle"
                                        onclick="togglePassword('password', this)"
                                    ></i>

                                </div>


                                <div class="form-text text-muted mt-1">

                                    Password must be at least 6 characters.

                                </div>

                            </div>


                            <!-- CONFIRM -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="confirm_password"
                                    class="form-label-modern"
                                >

                                    <i
                                        class="fa-solid fa-lock"
                                    ></i>

                                    Confirm Password

                                </label>


                                <div class="password-wrapper">

                                    <input
                                        type="password"
                                        name="confirm_password"
                                        id="confirm_password"
                                        class="form-control"
                                        required
                                        placeholder="Confirm password"
                                        minlength="6"
                                        autocomplete="new-password"
                                    >


                                    <i
                                        class="fa-solid fa-eye password-toggle"
                                        onclick="togglePassword('confirm_password', this)"
                                    ></i>

                                </div>

                            </div>

                        </div>


                        <!-- BUTTONS -->

                        <div
                            class="d-flex flex-wrap gap-2 mt-2"
                        >

                            <button
                                type="submit"
                                name="save"
                                class="btn btn-save"
                            >

                                <i
                                    class="fa-solid fa-floppy-disk me-1"
                                ></i>

                                Save Teacher

                            </button>


                            <a
                                href="dashboard.php"
                                class="btn btn-secondary btn-back"
                            >

                                <i
                                    class="fa-solid fa-arrow-left me-1"
                                ></i>

                                Back to Dashboard

                            </a>

                        </div>


                    </form>

                </div>

            </div>

        </div>


        <!-- =================================================
             TEACHER LIST
        ================================================== -->

        <div class="dashboard-card">


            <div class="card-header-modern">

                <div class="card-header-left">

                    <div class="header-icon">

                        <i class="fa-solid fa-users"></i>

                    </div>


                    <div>

                        <h5 class="card-header-title">

                            Teacher List

                        </h5>


                        <p class="card-header-subtitle">

                            Manage all registered teachers

                        </p>

                    </div>

                </div>


                <div class="teacher-badge">

                    <i class="fa-solid fa-user-group"></i>

                    <?= count($teachers); ?>

                    Teachers

                </div>

            </div>


            <div class="card-body-modern">


                <div class="table-wrapper">


                    <table class="modern-table">


                        <thead>

                            <tr>

                            

                                <th>
                                    Employee ID
                                </th>

                                <th>
                                    Teacher
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Username
                                </th>

                                <th width="180">
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (empty($teachers)): ?>


                            <tr>

                                <td colspan="6">


                                    <div class="empty-state">


                                        <div class="empty-icon">

                                            <i
                                                class="fa-solid fa-user-slash"
                                            ></i>

                                        </div>


                                        <h6>

                                            No Teachers Found

                                        </h6>


                                        <p>

                                            Add a teacher using
                                            the form above.

                                        </p>


                                    </div>


                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($teachers as $row): ?>


                                <tr>


                                


                                    <!-- EMPLOYEE -->

                                    <td>

                                        <span class="teacher-badge">

                                            <i
                                                class="fa-solid fa-id-card"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $row['employee_id']
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- TEACHER PROFILE -->

                                    <td>

                                        <div class="teacher-profile-cell">


                                            <div class="teacher-table-avatar">

                                                <?php if (
                                                    !empty(
                                                        $row['profile_image']
                                                    )
                                                ): ?>

                                                    <img
                                                        src="../<?= htmlspecialchars(
                                                            $row['profile_image']
                                                        ); ?>"
                                                        alt="Teacher Profile"
                                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                                    >

                                                    <i
                                                        class="fa-solid fa-user-tie"
                                                        style="display:none;"
                                                    ></i>

                                                <?php else: ?>

                                                    <i
                                                        class="fa-solid fa-user-tie"
                                                    ></i>

                                                <?php endif; ?>

                                            </div>


                                            <div class="teacher-name-wrapper">

                                                <div class="teacher-name">

                                                    <?= htmlspecialchars(
                                                        $row['fullname']
                                                    ); ?>

                                                </div>


                                                <div class="teacher-role-small">

                                                    Teacher

                                                </div>

                                            </div>


                                        </div>

                                    </td>


                                    <!-- EMAIL -->

                                    <td>

                                        <span class="email-text">

                                            <i
                                                class="fa-regular fa-envelope me-1"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $row['email']
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- USERNAME -->

                                    <td>

                                        <span class="username-badge">

                                            <i
                                                class="fa-solid fa-user me-1"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $row['username']
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div class="action-buttons">


                                            <!-- ASSIGN -->

                                            <a
                                                href="assign_schedule.php?id=<?= urlencode($row['id']); ?>"
                                                class="action-btn btn-assign"
                                                title="Assign Schedule"
                                            >

                                                <i
                                                    class="fa-solid fa-calendar-days"
                                                ></i>

                                            </a>


                                            <!-- VIEW -->

                                            <button
                                                type="button"
                                                class="action-btn btn-view viewSchedule"
                                                data-id="<?= htmlspecialchars($row['id']); ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#scheduleModal"
                                                title="View Schedule"
                                            >

                                                <i
                                                    class="fa-solid fa-eye"
                                                ></i>

                                            </button>


                                            <!-- EDIT -->

                                            <button
                                                type="button"
                                                class="action-btn btn-edit editTeacher"

                                                data-id="<?= htmlspecialchars($row['id']); ?>"

                                                data-employee="<?= htmlspecialchars(
                                                    $row['employee_id'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"

                                                data-fullname="<?= htmlspecialchars(
                                                    $row['fullname'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"

                                                data-email="<?= htmlspecialchars(
                                                    $row['email'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"

                                                data-username="<?= htmlspecialchars(
                                                    $row['username'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"

                                                data-profile="<?= htmlspecialchars(
                                                    $row['profile_image'] ?? '',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"

                                                data-bs-toggle="modal"

                                                data-bs-target="#editTeacherModal"

                                                title="Edit Teacher"
                                            >

                                                <i
                                                    class="fa-solid fa-pen-to-square"
                                                ></i>

                                            </button>


                                            <!-- DELETE -->

                                            <button
                                                type="button"
                                                class="action-btn btn-delete"
                                                title="Delete Teacher"

                                                onclick="confirmDelete(
                                                    <?= (int)$row['id']; ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $row['fullname']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                )"
                                            >

                                                <i
                                                    class="fa-solid fa-trash"
                                                ></i>

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
     TOAST
========================================================= -->

<div
    class="toast-container"
    id="toastContainer"
></div>


<!-- =========================================================
     VIEW SCHEDULE MODAL
========================================================= -->

<div
    class="modal fade"
    id="scheduleModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    <i
                        class="fa-solid fa-calendar-days me-2"
                    ></i>

                    Teacher Schedule

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <div
                class="modal-body"
                id="scheduleContent"
            >

                <div class="text-center py-5">

                    <div
                        class="spinner-border"
                        style="color: var(--primary);"
                        role="status"
                    >

                        <span class="visually-hidden">

                            Loading...

                        </span>

                    </div>


                    <p class="mt-3 text-muted">

                        Loading schedule...

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     EDIT TEACHER MODAL
========================================================= -->

<div
    class="modal fade"
    id="editTeacherModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-lg modal-dialog-centered"
    >

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">

                    <i
                        class="fa-solid fa-pen-to-square me-2"
                    ></i>

                    Edit Teacher

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <form
                method="POST"
                action="update_teacher.php"
                id="editTeacherForm"
                enctype="multipart/form-data"
            >

                <div class="modal-body">


                    <input
                        type="hidden"
                        name="edit_id"
                        id="edit_id"
                    >


                    <!-- EDIT PROFILE IMAGE -->

                    <div class="profile-upload-wrapper">

                        <div
                            class="edit-profile-preview"
                            id="editProfilePreview"
                        >

                            <i
                                class="fa-solid fa-user-tie"
                                id="editDefaultIcon"
                            ></i>


                            <img
                                id="editProfileImage"
                                src=""
                                alt="Teacher Profile"
                                style="display:none;"
                            >

                        </div>


                        <label
                            for="edit_profile_image"
                            class="profile-upload-label"
                        >

                            <i class="fa-solid fa-camera"></i>

                            Change Profile Picture

                        </label>


                        <input
                            type="file"
                            name="edit_profile_image"
                            id="edit_profile_image"
                            class="profile-upload-input"
                            accept="image/jpeg,image/png,image/webp"
                        >


                        <div class="profile-upload-help">

                            JPG, PNG, or WEBP • Maximum 5 MB

                        </div>

                    </div>


                    <div class="row">


                        <!-- EMPLOYEE -->

                        <div class="col-md-6 mb-3">

                            <label
                                for="edit_employee"
                                class="form-label-modern"
                            >

                                <i
                                    class="fa-solid fa-id-card"
                                ></i>

                                Employee ID

                            </label>


                            <input
                                type="text"
                                name="edit_employee"
                                id="edit_employee"
                                class="form-control"
                                required
                                maxlength="50"
                            >

                        </div>


                        <!-- FULL NAME -->

                        <div class="col-md-6 mb-3">

                            <label
                                for="edit_fullname"
                                class="form-label-modern"
                            >

                                <i
                                    class="fa-solid fa-user"
                                ></i>

                                Full Name

                            </label>


                            <input
                                type="text"
                                name="edit_fullname"
                                id="edit_fullname"
                                class="form-control"
                                required
                                maxlength="100"
                            >

                        </div>

                    </div>


                    <div class="row">


                        <!-- EMAIL -->

                        <div class="col-md-6 mb-3">

                            <label
                                for="edit_email"
                                class="form-label-modern"
                            >

                                <i
                                    class="fa-solid fa-envelope"
                                ></i>

                                Email

                            </label>


                            <input
                                type="email"
                                name="edit_email"
                                id="edit_email"
                                class="form-control"
                                required
                                maxlength="100"
                            >

                        </div>


                        <!-- USERNAME -->

                        <div class="col-md-6 mb-3">

                            <label
                                for="edit_username"
                                class="form-label-modern"
                            >

                                <i
                                    class="fa-solid fa-user-tag"
                                ></i>

                                Username

                            </label>


                            <input
                                type="text"
                                name="edit_username"
                                id="edit_username"
                                class="form-control"
                                required
                                maxlength="50"
                            >

                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="mb-3">

                        <label
                            for="edit_password"
                            class="form-label-modern"
                        >

                            <i
                                class="fa-solid fa-lock"
                            ></i>

                            New Password

                            <span class="text-muted fw-normal">

                                (Leave blank to keep current)

                            </span>

                        </label>


                        <div class="password-wrapper">

                            <input
                                type="password"
                                name="edit_password"
                                id="edit_password"
                                class="form-control"
                                placeholder="Enter new password (optional)"
                                minlength="6"
                                autocomplete="new-password"
                            >


                            <i
                                class="fa-solid fa-eye password-toggle"
                                onclick="togglePassword('edit_password', this)"
                            ></i>

                        </div>

                    </div>


                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary btn-back"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        name="update_teacher"
                        class="btn btn-save"
                    >

                        <i
                            class="fa-solid fa-floppy-disk me-1"
                        ></i>

                        Update Teacher

                    </button>

                </div>


            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     DELETE MODAL
========================================================= -->

<div
    class="modal fade delete-modal"
    id="deleteConfirmModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    <i
                        class="fa-solid fa-triangle-exclamation me-2"
                    ></i>

                    Confirm Delete

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <div class="modal-body text-center p-4">

                <div class="delete-icon">

                    <i
                        class="fa-solid fa-trash"
                    ></i>

                </div>


                <h5 class="mb-2 fw-bold">

                    Are you sure?

                </h5>


                <p class="text-muted mb-0">

                    You are about to delete

                    <strong id="deleteTeacherName"></strong>.

                    <br>

                    This action cannot be undone.

                </p>

            </div>


            <div class="modal-footer justify-content-center">

                <button
                    type="button"
                    class="btn btn-secondary btn-back"
                    data-bs-dismiss="modal"
                >

                    Cancel

                </button>


                <a
                    href="#"
                    id="deleteConfirmBtn"
                    class="btn btn-danger btn-back"
                >

                    <i
                        class="fa-solid fa-trash me-1"
                    ></i>

                    Yes, Delete

                </a>

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

// =========================================================
// MOBILE SIDEBAR
// =========================================================

const mobileMenuBtn =
    document.getElementById('mobileMenuBtn');

const sidebar =
    document.getElementById('sidebar');

const sidebarOverlay =
    document.getElementById('sidebarOverlay');


function openSidebar() {

    if (sidebar) {

        sidebar.classList.add('show');

    }

    if (sidebarOverlay) {

        sidebarOverlay.classList.add('show');

    }

}


function closeSidebar() {

    if (sidebar) {

        sidebar.classList.remove('show');

    }

    if (sidebarOverlay) {

        sidebarOverlay.classList.remove('show');

    }

}


if (mobileMenuBtn) {

    mobileMenuBtn.addEventListener(
        'click',
        openSidebar
    );

}


if (sidebarOverlay) {

    sidebarOverlay.addEventListener(
        'click',
        closeSidebar
    );

}


// =========================================================
// PROFILE IMAGE PREVIEW
// =========================================================

const profileImageInput =
    document.getElementById(
        'profile_image'
    );

const profilePreviewImage =
    document.getElementById(
        'profilePreviewImage'
    );

const defaultProfileIcon =
    document.getElementById(
        'defaultProfileIcon'
    );


if (profileImageInput) {

    profileImageInput.addEventListener(
        'change',
        function () {

            const file = this.files[0];

            if (!file) {

                profilePreviewImage.style.display =
                    'none';

                defaultProfileIcon.style.display =
                    'block';

                profilePreviewImage.src = '';

                return;
            }


            // Check file size
            if (
                file.size >
                5 * 1024 * 1024
            ) {

                showToast(
                    'Profile picture must not exceed 5 MB.',
                    'error'
                );

                this.value = '';

                return;
            }


            // Check file type
            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];


            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                showToast(
                    'Only JPG, PNG, and WEBP images are allowed.',
                    'error'
                );

                this.value = '';

                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    profilePreviewImage.src =
                        event.target.result;

                    profilePreviewImage.style.display =
                        'block';

                    defaultProfileIcon.style.display =
                        'none';

                };


            reader.readAsDataURL(file);

        }
    );

}


// =========================================================
// EDIT PROFILE IMAGE PREVIEW
// =========================================================

const editProfileImageInput =
    document.getElementById(
        'edit_profile_image'
    );

const editProfileImage =
    document.getElementById(
        'editProfileImage'
    );

const editDefaultIcon =
    document.getElementById(
        'editDefaultIcon'
    );


if (editProfileImageInput) {

    editProfileImageInput.addEventListener(
        'change',
        function () {

            const file = this.files[0];

            if (!file) {

                return;

            }


            if (
                file.size >
                5 * 1024 * 1024
            ) {

                showToast(
                    'Profile picture must not exceed 5 MB.',
                    'error'
                );

                this.value = '';

                return;
            }


            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];


            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                showToast(
                    'Only JPG, PNG, and WEBP images are allowed.',
                    'error'
                );

                this.value = '';

                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    editProfileImage.src =
                        event.target.result;

                    editProfileImage.style.display =
                        'block';

                    editDefaultIcon.style.display =
                        'none';

                };


            reader.readAsDataURL(file);

        }
    );

}


// =========================================================
// TOAST
// =========================================================

function showToast(
    message,
    type = 'success',
    duration = 4000
) {

    const container =
        document.getElementById(
            'toastContainer'
        );


    const icons = {

        success: 'fa-circle-check',

        error: 'fa-circle-xmark',

        warning: 'fa-triangle-exclamation',

        info: 'fa-circle-info'

    };


    const titles = {

        success: 'Success!',

        error: 'Error!',

        warning: 'Warning!',

        info: 'Info'

    };


    if (!icons[type]) {

        type = 'info';

    }


    const toast =
        document.createElement('div');


    toast.className =
        `custom-toast toast-${type}`;


    const body =
        document.createElement('div');

    body.className =
        'toast-body';


    const icon =
        document.createElement('div');

    icon.className =
        'toast-icon';


    const iconElement =
        document.createElement('i');

    iconElement.className =
        `fa-solid ${icons[type]}`;


    icon.appendChild(iconElement);


    const content =
        document.createElement('div');

    content.className =
        'toast-content';


    const title =
        document.createElement('div');

    title.className =
        'toast-title';

    title.textContent =
        titles[type];


    const messageElement =
        document.createElement('div');

    messageElement.className =
        'toast-message';

    messageElement.textContent =
        message;


    content.appendChild(title);

    content.appendChild(messageElement);


    const closeButton =
        document.createElement('button');

    closeButton.className =
        'toast-close';

    closeButton.type =
        'button';

    closeButton.innerHTML =
        '<i class="fa-solid fa-xmark"></i>';


    closeButton.addEventListener(
        'click',
        function () {

            dismissToast(closeButton);

        }
    );


    body.appendChild(icon);

    body.appendChild(content);

    body.appendChild(closeButton);


    const progress =
        document.createElement('div');

    progress.className =
        'toast-progress';


    const progressBar =
        document.createElement('div');

    progressBar.className =
        'toast-progress-bar';


    progress.appendChild(progressBar);


    toast.appendChild(body);

    toast.appendChild(progress);


    container.appendChild(toast);


    setTimeout(function () {

        if (toast.parentElement) {

            dismissToast(closeButton);

        }

    }, duration);

}


// =========================================================
// DISMISS TOAST
// =========================================================

function dismissToast(closeBtn) {

    const toast =
        closeBtn.closest(
            '.custom-toast'
        );


    if (!toast) {

        return;

    }


    toast.classList.add(
        'toast-hiding'
    );


    setTimeout(function () {

        if (toast.parentElement) {

            toast.remove();

        }

    }, 300);

}


// =========================================================
// PASSWORD TOGGLE
// =========================================================

function togglePassword(
    inputId,
    icon
) {

    const input =
        document.getElementById(
            inputId
        );


    if (!input) {

        return;

    }


    if (input.type === 'password') {

        input.type = 'text';

        icon.classList.remove(
            'fa-eye'
        );

        icon.classList.add(
            'fa-eye-slash'
        );

    } else {

        input.type = 'password';

        icon.classList.remove(
            'fa-eye-slash'
        );

        icon.classList.add(
            'fa-eye'
        );

    }

}


// =========================================================
// DOM READY
// =========================================================

document.addEventListener(
    'DOMContentLoaded',
    function () {


        // =====================================================
        // ADD TEACHER FORM
        // =====================================================

        const addTeacherForm =
            document.getElementById(
                'addTeacherForm'
            );


        if (addTeacherForm) {

            addTeacherForm.addEventListener(
                'submit',
                function (e) {

                    const password =
                        document.getElementById(
                            'password'
                        ).value;


                    const confirmPassword =
                        document.getElementById(
                            'confirm_password'
                        ).value;


                    if (
                        password !==
                        confirmPassword
                    ) {

                        e.preventDefault();


                        showToast(
                            'Passwords do not match!',
                            'error'
                        );


                        document.getElementById(
                            'confirm_password'
                        ).focus();

                    }

                }
            );

        }


        // =====================================================
        // VIEW SCHEDULE
        // =====================================================

        document
            .querySelectorAll('.viewSchedule')
            .forEach(function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const teacherId =
                            this.dataset.id;


                        const scheduleContent =
                            document.getElementById(
                                'scheduleContent'
                            );


                        scheduleContent.innerHTML = `

                            <div class="text-center py-5">

                                <div
                                    class="spinner-border"
                                    style="color: var(--primary);"
                                    role="status"
                                ></div>

                                <p class="mt-3 text-muted">
                                    Loading schedule...
                                </p>

                            </div>

                        `;


                        fetch(
                            'get_teacher_schedule.php?teacher_id='
                            + encodeURIComponent(
                                teacherId
                            )
                        )

                        .then(function (response) {

                            if (!response.ok) {

                                throw new Error(
                                    'Network response was not ok'
                                );

                            }

                            return response.text();

                        })

                        .then(function (data) {

                            scheduleContent.innerHTML =
                                data;

                        })

                        .catch(function (error) {

                            scheduleContent.innerHTML = `

                                <div class="alert alert-danger">

                                    <i
                                        class="fa-solid fa-triangle-exclamation me-2"
                                    ></i>

                                    Failed to load schedule.
                                    Please try again.

                                </div>

                            `;


                            console.error(
                                'Schedule error:',
                                error
                            );

                        });

                    }
                );

            });


        // =====================================================
        // EDIT TEACHER
        // =====================================================

        document
            .querySelectorAll('.editTeacher')
            .forEach(function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        document.getElementById(
                            'edit_id'
                        ).value =
                            this.dataset.id;


                        document.getElementById(
                            'edit_employee'
                        ).value =
                            this.dataset.employee;


                        document.getElementById(
                            'edit_fullname'
                        ).value =
                            this.dataset.fullname;


                        document.getElementById(
                            'edit_email'
                        ).value =
                            this.dataset.email;


                        document.getElementById(
                            'edit_username'
                        ).value =
                            this.dataset.username;


                        document.getElementById(
                            'edit_password'
                        ).value = '';


                        // -----------------------------------------
                        // CURRENT PROFILE IMAGE
                        // -----------------------------------------

                        const profilePath =
                            this.dataset.profile;


                        const editImage =
                            document.getElementById(
                                'editProfileImage'
                            );

                        const editIcon =
                            document.getElementById(
                                'editDefaultIcon'
                            );


                        document.getElementById(
                            'edit_profile_image'
                        ).value = '';


                        if (profilePath) {

                            editImage.src =
                                '../' + profilePath;

                            editImage.style.display =
                                'block';

                            editIcon.style.display =
                                'none';

                        } else {

                            editImage.src = '';

                            editImage.style.display =
                                'none';

                            editIcon.style.display =
                                'block';

                        }

                    }
                );

            });


        // =====================================================
        // EDIT FORM
        // =====================================================

        const editTeacherForm =
            document.getElementById(
                'editTeacherForm'
            );


        if (editTeacherForm) {

            editTeacherForm.addEventListener(
                'submit',
                function (e) {

                    const password =
                        document.getElementById(
                            'edit_password'
                        ).value;


                    if (
                        password !== '' &&
                        password.length < 6
                    ) {

                        e.preventDefault();


                        showToast(
                            'New password must be at least 6 characters.',
                            'error'
                        );

                    }

                }
            );

        }

    }
);


// =========================================================
// DELETE CONFIRMATION
// =========================================================

function confirmDelete(
    id,
    name
) {

    document.getElementById(
        'deleteTeacherName'
    ).textContent =
        name;


    document.getElementById(
        'deleteConfirmBtn'
    ).href =
        'delete_teacher.php?id='
        + encodeURIComponent(id);


    const modal =
        new bootstrap.Modal(
            document.getElementById(
                'deleteConfirmModal'
            )
        );


    modal.show();

}


// =========================================================
// PHP TOAST ON LOAD
// =========================================================

<?php if ($toast_message): ?>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        showToast(
            <?= json_encode($toast_message); ?>,
            <?= json_encode($toast_type); ?>
        );

    }
);

<?php endif; ?>

</script>


</body>

</html>
