
<?php

use PHPMailer\PHPMailer\PHPMailer;
use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

// Load .env from the project root.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

function sendTeacherCredentials(
    string $email,
    string $fullname,
    string $username,
    string $password
): bool {

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('BeadleQR: Invalid recipient email.');
        return false;
    }

    $apiKey = $_ENV['RESEND_API_KEY'] ?? '';
    $fromEmail = $_ENV['RESEND_FROM_EMAIL'] ?? '';

    if ($apiKey === '' || $fromEmail === '') {
        error_log('BeadleQR: Resend settings are missing from .env.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Resend SMTP settings.
        $mail->isSMTP();
        $mail->Host       = 'smtp.resend.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'resend';
        $mail->Password   = $apiKey;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;

        // Sender must be authorized by Resend.
        $mail->setFrom(
            $fromEmail,
            'BeadleQR Administration'
        );

        // Dynamic recipient from Add Teacher form.
        $mail->addAddress($email, $fullname);

        $safeName = htmlspecialchars(
            $fullname,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeUsername = htmlspecialchars(
            $username,
            ENT_QUOTES,
            'UTF-8'
        );

        $safePassword = htmlspecialchars(
            $password,
            ENT_QUOTES,
            'UTF-8'
        );

        $mail->isHTML(true);
        $mail->Subject = 'Your BeadleQR Teacher Account';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;
                        max-width:600px;margin:auto;
                        padding:25px;color:#222;'>

                <h2 style='color:#f97316;'>
                    Welcome to BeadleQR!
                </h2>

                <p>Hello {$safeName},</p>

                <p>
                    Your teacher account has been created.
                    Use the credentials below to sign in.
                </p>

                <div style='background:#f5f5f5;
                            padding:20px;border-radius:8px;'>

                    <p>
                        <strong>Username:</strong>
                        {$safeUsername}
                    </p>

                    <p>
                        <strong>Temporary password:</strong>
                        {$safePassword}
                    </p>
                </div>

                <p>
                    For your security, change your password
                    after your first login.
                </p>

                <p>
                    Regards,<br>
                    BeadleQR Administration
                </p>
            </div>
        ";

        $mail->AltBody =
            "Hello {$fullname},\n\n" .
            "Your BeadleQR teacher account has been created.\n\n" .
            "Username: {$username}\n" .
            "Temporary password: {$password}\n\n" .
            "Please change your password after your first login.";

        $mail->send();

        return true;

    } catch (\Throwable $e) {
        error_log('BeadleQR email failed: ' . $e->getMessage());

        if ($mail->ErrorInfo !== '') {
            error_log('PHPMailer details: ' . $mail->ErrorInfo);
        }

        return false;
    }
}
