<?php
/*
|--------------------------------------------------------------------------
| BEADLEQR LOGIN
|--------------------------------------------------------------------------
| IMPORTANT:
| Save this file as UTF-8 without BOM.
| Do not place spaces or blank lines before this opening PHP tag.
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$error = '';

/*
|--------------------------------------------------------------------------
| PROCESS LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter your username and password.';

    } else {

        try {

            $stmt = $conn->prepare("
                SELECT id, fullname, username, password, role
                FROM users
                WHERE username = :username
                LIMIT 1
            ");

            $stmt->execute([
                ':username' => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                /*
                |--------------------------------------------------------------------------
                | REGENERATE SESSION ID
                |--------------------------------------------------------------------------
                */

                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }

                if (!session_regenerate_id(true)) {
                    throw new RuntimeException(
                        'Unable to regenerate the login session.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | SET SESSION VARIABLES
                |--------------------------------------------------------------------------
                */

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                /*
                |--------------------------------------------------------------------------
                | ROLE-BASED REDIRECTS
                |--------------------------------------------------------------------------
                */

                switch ($user['role']) {

                    case 'admin':
                        header('Location: admin/dashboard.php');
                        exit();

                    case 'teacher':
                        header('Location: teacher/dashboard.php');
                        exit();

                    case 'student':
                        header('Location: Student/student_dashboard.php');
                        exit();

                    default:
                        session_unset();
                        session_destroy();

                        session_start();
                        $error = 'Invalid user role.';
                        break;
                }

            } else {

                $error = 'Invalid username or password.';
            }

        } catch (PDOException $e) {

            error_log('BeadleQR login database error: ' . $e->getMessage());

            $error = 'Unable to process your login right now. Please try again.';

        } catch (RuntimeException $e) {

            error_log('BeadleQR session error: ' . $e->getMessage());

            $error = 'Unable to start your login session. Please try again.';
        }
    }
}
?>
