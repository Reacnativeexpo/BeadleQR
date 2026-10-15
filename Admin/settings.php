<?php
session_start();
require_once "../db.php";

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['role'], $_SESSION['user_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

$message = "";
$messageType = "";

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {
        $message = "Please fill in all password fields.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New password and confirmation do not match.";
        $messageType = "error";
    } elseif (strlen($newPassword) < 8) {
        $message = "Your new password must contain at least 8 characters.";
        $messageType = "error";
    } elseif ($currentPassword === $newPassword) {
        $message = "Your new password must be different from your current password.";
        $messageType = "error";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT password
                 FROM users
                 WHERE id = :id AND role = 'admin'
                 LIMIT 1"
            );

            $stmt->execute(['id' => $admin_id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                $message = "Administrator account not found.";
                $messageType = "error";
            } else {
                $storedPassword = $admin['password'];

                // Support password_hash() passwords and legacy plain-text passwords.
                $passwordIsValid = password_verify(
                    $currentPassword,
                    $storedPassword
                );

                if (
                    !$passwordIsValid &&
                    !password_get_info($storedPassword)['algo']
                ) {
                    $passwordIsValid = hash_equals(
                        $storedPassword,
                        $currentPassword
                    );
                }

                if (!$passwordIsValid) {
                    $message = "Your current password is incorrect.";
                    $messageType = "error";
                } else {
                    $hashedPassword = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    $update = $pdo->prepare(
                        "UPDATE users
                         SET password = :password
                         WHERE id = :id AND role = 'admin'"
                    );

                    $update->execute([
                        'password' => $hashedPassword,
                        'id' => $admin_id
                    ]);

                    $message = "Password changed successfully!";
                    $messageType = "success";
                }
            }
        } catch (PDOException $exception) {
            error_log(
                "BeadleQR password update error: " .
                $exception->getMessage()
            );

            $message = "Unable to change your password right now. Please try again.";
            $messageType = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT PAGE
|--------------------------------------------------------------------------
*/
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings | BeadleQR Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f6fa;
            color: #1f2937;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 35px;
            transition: margin-left .3s ease;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 27px;
            font-weight: 700;
            color: #111827;
        }

        .page-header p {
            color: #6b7280;
            font-size: 13px;
            margin-top: 5px;
        }

        /* =====================================================
           MOBILE MENU BUTTON
        ===================================================== */

        .mobile-menu {
            display: none;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
            color: #f97316;
            font-size: 20px;
            cursor: pointer;
            transition:
                background .2s ease,
                color .2s ease,
                border-color .2s ease;
        }

        .mobile-menu:hover {
            background: #fff1e8;
            border-color: #fed7aa;
        }

        .mobile-menu:focus-visible {
            outline: 3px solid rgba(249, 115, 22, .3);
            outline-offset: 2px;
        }

        /* =====================================================
           SIDEBAR OVERLAY
        ===================================================== */

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            z-index: 999;
            opacity: 0;
            transition: opacity .3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* =====================================================
           SETTINGS CARD
        ===================================================== */

        .settings-card {
            width: 100%;
            max-width: 760px;
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 5px 20px rgba(17, 24, 39, 0.04);
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 23px;
            margin-bottom: 25px;
            border-bottom: 1px solid #edf0f4;
        }

        .heading-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 13px;
            background: #fff1e8;
            color: #f97316;
            font-size: 20px;
        }

        .card-heading h2 {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .card-heading p {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        /* =====================================================
           FORM
        ===================================================== */

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .form-control {
            width: 100%;
            height: 48px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            padding: 0 46px 0 14px;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, .12);
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            font-size: 15px;
            padding: 5px;
        }

        .toggle-password:hover {
            color: #f97316;
        }

        .toggle-password:focus-visible {
            outline: 2px solid #f97316;
            border-radius: 4px;
        }

        .password-hint {
            margin-top: 7px;
            color: #6b7280;
            font-size: 11px;
        }

        /* =====================================================
           ALERT MESSAGES
        ===================================================== */

        .alert {
            padding: 13px 15px;
            border-radius: 9px;
            font-size: 13px;
            margin-bottom: 23px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            overflow-wrap: anywhere;
        }

        .alert i {
            margin-top: 3px;
            flex-shrink: 0;
        }

        .alert.success {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .alert.error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        /* =====================================================
           FORM ACTIONS
        ===================================================== */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #edf0f4;
        }

        .btn-save {
            border: none;
            border-radius: 9px;
            background: #f97316;
            color: #fff;
            padding: 13px 21px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .btn-save:hover {
            background: #ea580c;
            transform: translateY(-1px);
        }

        .btn-save:focus-visible {
            outline: 3px solid rgba(249, 115, 22, .3);
            outline-offset: 3px;
        }

        .btn-save i {
            margin-right: 7px;
        }

        /* =====================================================
           SECURITY NOTE
        ===================================================== */

        .security-note {
            display: flex;
            gap: 12px;
            margin-top: 22px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.7;
        }

        .security-note i {
            color: #f97316;
            margin-top: 3px;
            flex-shrink: 0;
        }

        /* =====================================================
           TABLET AND MOBILE SIDEBAR
        ===================================================== */

        @media (max-width: 992px) {
            /*
             * Slide the existing sidebar off-screen.
             * The sidebar must have id="sidebar".
             */
            #sidebar,
            .sidebar {
                position: fixed !important;
                top: 0;
                left: 0;
                height: 100vh;
                height: 100dvh;
                max-height: 100vh;
                overflow-y: auto;
                overflow-x: hidden;
                z-index: 1000;
                transform: translateX(-100%);
                transition: transform .3s ease;
                visibility: visible;
                -webkit-overflow-scrolling: touch;
            }

            #sidebar.show,
            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 25px 18px;
            }

            .mobile-menu {
                display: inline-flex;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        /* =====================================================
           SMALL MOBILE
        ===================================================== */

        @media (max-width: 500px) {
            .main-content {
                padding: 20px 13px;
            }

            .page-header {
                margin-bottom: 22px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .page-header p {
                font-size: 12px;
                line-height: 1.6;
            }

            .settings-card {
                padding: 20px 16px;
                border-radius: 13px;
            }

            .card-heading {
                gap: 11px;
                padding-bottom: 18px;
                margin-bottom: 21px;
            }

            .heading-icon {
                width: 42px;
                height: 42px;
                font-size: 18px;
            }

            .card-heading h2 {
                font-size: 16px;
            }

            .card-heading p {
                font-size: 11px;
                line-height: 1.6;
            }

            .form-group {
                margin-bottom: 19px;
            }

            .form-control {
                height: 46px;
                font-size: 12px;
            }

            .form-actions {
                margin-top: 23px;
                padding-top: 18px;
            }

            .form-actions .btn-save {
                width: 100%;
                padding: 13px 14px;
            }

            .security-note {
                padding: 12px;
                font-size: 11px;
            }
        }

        /* =====================================================
           REDUCED MOTION
        ===================================================== */

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>

<body>

<?php require_once __DIR__ . "/sidebar.php"; ?>

<!-- MOBILE SIDEBAR OVERLAY -->
<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    aria-hidden="true"
></div>

<main class="main-content">

    <header class="page-header">

        <!-- MOBILE MENU BUTTON -->
        <button
            type="button"
            class="mobile-menu"
            id="mobileMenu"
            aria-label="Open navigation menu"
            aria-controls="sidebar"
            aria-expanded="false"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <h1>Settings</h1>

        <p>
            Manage your administrator account password and security.
        </p>

    </header>

    <section class="settings-card">

        <div class="card-heading">

            <div class="heading-icon">
                <i class="fa-solid fa-lock"></i>
            </div>

            <div>
                <h2>Change Password</h2>
                <p>Update your password to keep your account secure.</p>
            </div>

        </div>

        <?php if ($message !== ''): ?>

            <div
                class="alert <?= e($messageType) ?>"
                role="alert"
                aria-live="polite"
            >
                <i class="fa-solid <?= $messageType === 'success'
                    ? 'fa-circle-check'
                    : 'fa-circle-exclamation' ?>"></i>

                <span><?= e($message) ?></span>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="settings.php"
            autocomplete="off"
        >

            <div class="form-group">

                <label for="current_password">
                    Current Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="form-control"
                        placeholder="Enter your current password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('current_password', this)"
                        aria-label="Show or hide current password"
                        aria-pressed="false"
                    >
                        <i class="fa-regular fa-eye"></i>
                    </button>

                </div>

            </div>

            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-control"
                        placeholder="Enter your new password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('new_password', this)"
                        aria-label="Show or hide new password"
                        aria-pressed="false"
                    >
                        <i class="fa-regular fa-eye"></i>
                    </button>

                </div>

                <p class="password-hint">
                    Use at least 8 characters for your new password.
                </p>

            </div>

            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Re-enter your new password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('confirm_password', this)"
                        aria-label="Show or hide password confirmation"
                        aria-pressed="false"
                    >
                        <i class="fa-regular fa-eye"></i>
                    </button>

                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-shield-halved"></i>
                    Update Password
                </button>

            </div>

        </form>

        <div class="security-note">

            <i class="fa-solid fa-circle-info"></i>

            <p>
                Your new password will be securely hashed before it is saved.
                Make sure you remember your new password the next time you log in.
            </p>

        </div>

    </section>

</main>

<script>
/* =========================================================
   SHOW / HIDE PASSWORD
========================================================= */
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');

    if (!input || !icon) {
        return;
    }

    if (input.type === 'password') {
        input.type = 'text';

        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');

        button.setAttribute('aria-pressed', 'true');
    } else {
        input.type = 'password';

        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');

        button.setAttribute('aria-pressed', 'false');
    }
}

/* =========================================================
   MOBILE SIDEBAR TOGGLE
========================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const mobileMenu = document.getElementById('mobileMenu');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Support either sidebar ID or sidebar class.
    const sidebar =
        document.getElementById('sidebar') ||
        document.querySelector('.sidebar');

    if (!mobileMenu || !sidebarOverlay || !sidebar) {
        console.warn(
            'Mobile sidebar: check that sidebar.php contains an element with id="sidebar" or class="sidebar".'
        );
        return;
    }

    function openSidebar() {
        sidebar.classList.add('show');
        sidebarOverlay.classList.add('show');

        mobileMenu.setAttribute('aria-expanded', 'true');
        mobileMenu.setAttribute('aria-label', 'Close navigation menu');

        sidebarOverlay.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';

        const icon = mobileMenu.querySelector('i');

        if (icon) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-xmark');
        }
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');

        mobileMenu.setAttribute('aria-expanded', 'false');
        mobileMenu.setAttribute('aria-label', 'Open navigation menu');

        sidebarOverlay.setAttribute('aria-hidden', 'true');

        document.body.style.overflow = '';

        const icon = mobileMenu.querySelector('i');

        if (icon) {
            icon.classList.remove('fa-xmark');
            icon.classList.add('fa-bars');
        }
    }

    mobileMenu.addEventListener('click', function () {
        const isOpen = sidebar.classList.contains('show');

        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // Close when the user taps the dark overlay.
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Close with Escape.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    // Close the mobile sidebar after selecting a navigation link.
    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 992) {
                closeSidebar();
            }
        });
    });

    // Restore the desktop layout when resizing above the mobile breakpoint.
    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            closeSidebar();
        }
    });
});
</script>

</body>
</html>
