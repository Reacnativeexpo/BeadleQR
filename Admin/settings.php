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

                // Supports passwords stored using password_hash().
                // The fallback supports an existing plain-text password
                // only if your current database still uses that format.
                $passwordIsValid = password_verify(
                    $currentPassword,
                    $storedPassword
                );

                if (!$passwordIsValid &&
                    !password_get_info($storedPassword)['algo']) {
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
            error_log("BeadleQR password update error: " . $exception->getMessage());

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 35px;
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
        }

        .toggle-password:hover {
            color: #f97316;
        }

        .password-hint {
            margin-top: 7px;
            color: #6b7280;
            font-size: 11px;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 9px;
            font-size: 13px;
            margin-bottom: 23px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
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

        .btn-save i {
            margin-right: 7px;
        }

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
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 25px 18px;
            }
        }

        @media (max-width: 500px) {
            .main-content {
                padding: 20px 13px;
            }

            .settings-card {
                padding: 20px 16px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .form-actions .btn-save {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . "/sidebar.php"; ?>

<main class="main-content">

    <header class="page-header">
        <h1>Settings</h1>
        <p>Manage your administrator account password and security.</p>
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
            <div class="alert <?= e($messageType) ?>">
                <i class="fa-solid <?= $messageType === 'success'
                    ? 'fa-circle-check'
                    : 'fa-circle-exclamation' ?>"></i>

                <span><?= e($message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php" autocomplete="off">

            <div class="form-group">
                <label for="current_password">Current Password</label>

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
                    >
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>

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
                    >
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>

                <p class="password-hint">
                    Use at least 8 characters for your new password.
                </p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>

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
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>
