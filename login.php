
<?php

/*
|--------------------------------------------------------------------------
| BEADLEQR LOGIN
|--------------------------------------------------------------------------
| Save this file as UTF-8 without BOM.
| Nothing should appear before this opening PHP tag.
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

                if (headers_sent($sentFile, $sentLine)) {
                    error_log(
                        "BeadleQR: Output started at "
                        . $sentFile . ':' . $sentLine
                    );

                    $error = 'A server response error occurred. Please try again.';

                } elseif (!session_regenerate_id(true)) {

                    $error = 'Unable to start your login session. Please try again.';

                } else {

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role'] = $user['role'];

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
                            $_SESSION = [];
                            session_destroy();
                            $error = 'Invalid user role.';
                            break;
                    }
                }

            } else {

                $error = 'Invalid username or password.';
            }

        } catch (Throwable $e) {

            error_log('BeadleQR login error: ' . $e->getMessage());

            $error = 'Unable to process your login right now. Please try again.';
        }
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

    <title>Login - BeadleQR</title>

    <link
        rel="icon"
        type="image/png"
        href="images/beadle.png"
    >

    <!-- Google Fonts -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* =========================================================
   ROOT
========================================================= */

:root {

    --primary: #f97316;
    --primary-dark: #ea580c;
    --primary-light: #fb923c;
    --accent: #fbbf24;

    --bg-dark: #0c0a09;
    --bg-soft: #171310;

    --text-primary: #fafaf9;
    --text-secondary: #d6d3d1;
    --text-muted: #a8a29e;

    --border: rgba(255,255,255,0.10);

    --glass:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.075),
            rgba(255,255,255,0.035)
        );
}


/* =========================================================
   BODY
========================================================= */

html,
body {

    min-height: 100%;

    font-family: "Inter", sans-serif;

    background:
        radial-gradient(
            circle at 15% 20%,
            rgba(249,115,22,0.12),
            transparent 35%
        ),
        radial-gradient(
            circle at 85% 80%,
            rgba(251,191,36,0.07),
            transparent 35%
        ),
        #0c0a09;

    color: var(--text-primary);
}


/* =========================================================
   BACKGROUND PARTICLES
========================================================= */

.particles {

    position: fixed;

    inset: 0;

    overflow: hidden;

    pointer-events: none;

    z-index: 0;
}

.particle {

    position: absolute;

    bottom: -10px;

    width: 4px;
    height: 4px;

    border-radius: 50%;

    background: rgba(251,191,36,0.35);

    animation:
        floatUp linear infinite;
}

.particle:nth-child(1) {

    left: 8%;

    animation-duration: 22s;
}

.particle:nth-child(2) {

    left: 18%;

    width: 6px;
    height: 6px;

    animation-duration: 27s;
    animation-delay: 2s;
}

.particle:nth-child(3) {

    left: 31%;

    animation-duration: 20s;
    animation-delay: 5s;
}

.particle:nth-child(4) {

    left: 45%;

    width: 3px;
    height: 3px;

    animation-duration: 24s;
}

.particle:nth-child(5) {

    left: 60%;

    animation-duration: 28s;
    animation-delay: 4s;
}

.particle:nth-child(6) {

    left: 74%;

    width: 5px;
    height: 5px;

    animation-duration: 21s;
}

.particle:nth-child(7) {

    left: 87%;

    animation-duration: 26s;
    animation-delay: 3s;
}

.particle:nth-child(8) {

    left: 52%;

    animation-duration: 30s;
    animation-delay: 6s;
}


@keyframes floatUp {

    0% {
        transform: translateY(20vh);
        opacity: 0;
    }

    10% {
        opacity: 0.8;
    }

    90% {
        opacity: 0.8;
    }

    100% {
        transform: translateY(-110vh);
        opacity: 0;
    }
}


/* =========================================================
   MAIN WRAPPER
========================================================= */

.login-wrapper {

    position: relative;

    z-index: 2;

    width: min(1050px, calc(100% - 40px));

    min-height: 650px;

    margin: 35px auto;

    display: flex;

    background: var(--glass);

    border: 1px solid var(--border);

    border-radius: 28px;

    overflow: hidden;

    backdrop-filter: blur(25px);

    -webkit-backdrop-filter: blur(25px);

    box-shadow:
        0 35px 90px rgba(0,0,0,0.45),
        inset 0 1px 0 rgba(255,255,255,0.06);

    animation: wrapperIn 0.8s ease-out both;
}


@keyframes wrapperIn {

    from {

        opacity: 0;

        transform:
            translateY(30px)
            scale(0.98);
    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}


/* =========================================================
   LEFT INFORMATION SECTION
========================================================= */

.info-section {

    position: relative;

    flex: 1;

    padding: 55px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    overflow: hidden;

    background:
        linear-gradient(
            145deg,
            rgba(249,115,22,0.12),
            rgba(41,37,36,0.55)
        );
}


.info-section::before {

    content: "";

    position: absolute;

    width: 500px;
    height: 500px;

    top: -220px;
    right: -220px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(249,115,22,0.20),
            transparent 70%
        );

    filter: blur(15px);

    animation:
        glowMove 6s ease-in-out infinite;
}


@keyframes glowMove {

    0%,
    100% {

        transform: scale(1);

        opacity: 0.7;
    }

    50% {

        transform: scale(1.12);

        opacity: 1;
    }
}


/* =========================================================
   BRAND
========================================================= */

.brand {

    position: relative;

    z-index: 2;

    margin-bottom: 32px;
}


.brand-logo {

    font-family: "Space Grotesk", sans-serif;

    font-size: 34px;

    font-weight: 700;

    letter-spacing: -1px;

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #fdba74,
            #f97316
        );

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;
}


.brand-tagline {

    margin-top: 5px;

    color: var(--text-muted);

    font-size: 11px;

    font-weight: 600;

    letter-spacing: 2px;

    text-transform: uppercase;
}


/* =========================================================
   INFORMATION
========================================================= */

.system-title {

    position: relative;

    z-index: 2;

    max-width: 480px;

    font-family: "Space Grotesk", sans-serif;

    font-size: 29px;

    line-height: 1.3;

    margin-bottom: 18px;
}


.system-description {

    position: relative;

    z-index: 2;

    max-width: 500px;

    color: var(--text-secondary);

    font-size: 14px;

    line-height: 1.8;

    margin-bottom: 28px;
}


/* =========================================================
   FEATURES
========================================================= */

.features-list {

    position: relative;

    z-index: 2;

    list-style: none;
}


.features-list li {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 14px;

    color: var(--text-secondary);

    font-size: 13px;

    transition:
        transform 0.3s ease;
}


.features-list li:hover {

    transform: translateX(5px);
}


.features-list svg {

    width: 19px;

    height: 19px;

    flex-shrink: 0;

    color: var(--accent);
}


/* =========================================================
   STATS
========================================================= */

.stats {

    position: relative;

    z-index: 2;

    display: flex;

    gap: 35px;

    margin-top: 35px;

    padding-top: 25px;

    border-top:
        1px solid
        rgba(255,255,255,0.09);
}


.stat-item {

    text-align: center;
}


.stat-number {

    font-family: "Space Grotesk", sans-serif;

    font-size: 22px;

    font-weight: 700;

    color: var(--primary-light);
}


.stat-label {

    margin-top: 3px;

    color: var(--text-muted);

    font-size: 10px;

    font-weight: 600;

    letter-spacing: 1px;

    text-transform: uppercase;
}


/* =========================================================
   RIGHT FORM SECTION
   WHITE BACKGROUND
========================================================= */

.form-section {

    flex: 0 0 440px;

    padding: 48px 55px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background: #ffffff;

    color: #292524;
}


/* =========================================================
   LOGIN BRAND LOGO
========================================================= */

.login-logo {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    margin-bottom: 25px;
}


.login-logo-image {

    width: 250px;

    height: 250px;

    object-fit: contain;

    margin-bottom: 13px;

    filter:
        drop-shadow(
            0 10px 25px
            rgba(249,115,22,0.25)
        );

    transition:
        transform 0.35s ease,
        filter 0.35s ease;
}


.login-logo-image:hover {

    transform: scale(1.05);

    filter:
        drop-shadow(
            0 14px 30px
            rgba(249,115,22,0.40)
        );
}


.login-logo-name {

    font-family: "Space Grotesk", sans-serif;

    font-size: 21px;

    font-weight: 700;

    letter-spacing: -0.4px;

    color: #292524;
}


.login-logo-name span {

    color: var(--primary);
}


/* =========================================================
   FORM HEADER
========================================================= */

.form-header {

    text-align: center;

    margin-bottom: 30px;
}


.form-header h2 {

    font-family: "Space Grotesk", sans-serif;

    font-size: 28px;

    font-weight: 600;

    margin-bottom: 7px;

    color: #1c1917;
}


.form-header p {

    color: #78716c;

    font-size: 13px;
}


/* =========================================================
   FORM GROUP
========================================================= */

.form-group {

    margin-bottom: 21px;
}


.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #44403c;

    font-size: 11px;

    font-weight: 700;

    letter-spacing: 0.8px;

    text-transform: uppercase;
}


.input-wrapper {

    position: relative;
}


.input-wrapper svg {

    position: absolute;

    left: 15px;

    top: 50%;

    transform: translateY(-50%);

    width: 19px;

    height: 19px;

    color: #a8a29e;

    transition:
        color 0.3s ease;
}


.input-wrapper:focus-within svg {

    color: var(--primary);
}


.form-group input {

    width: 100%;

    height: 52px;

    padding:
        0 16px 0 47px;

    background: #f8fafc;

    border:
        1px solid
        #e7e5e4;

    border-radius: 12px;

    outline: none;

    color: #292524;

    font-family: "Inter", sans-serif;

    font-size: 14px;

    transition:
        border-color 0.3s ease,
        background 0.3s ease,
        box-shadow 0.3s ease;
}


.form-group input:focus {

    border-color: var(--primary);

    background: #ffffff;

    box-shadow:
        0 0 0 3px
        rgba(249,115,22,0.10);
}


.form-group input::placeholder {

    color: #a8a29e;
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.btn-login {

    position: relative;

    width: 100%;

    height: 53px;

    margin-top: 5px;

    border: none;

    border-radius: 12px;

    cursor: pointer;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            var(--primary-light),
            var(--primary-dark)
        );

    color: #fff;

    font-family: "Inter", sans-serif;

    font-size: 15px;

    font-weight: 700;

    box-shadow:
        0 10px 25px
        rgba(249,115,22,0.25);

    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
}


.btn-login::before {

    content: "";

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            110deg,
            transparent 25%,
            rgba(255,255,255,0.25) 50%,
            transparent 75%
        );

    transform:
        translateX(-120%);

    transition:
        transform 0.6s ease;
}


.btn-login:hover {

    transform: translateY(-2px);

    box-shadow:
        0 14px 32px
        rgba(249,115,22,0.35);
}


.btn-login:hover::before {

    transform:
        translateX(120%);
}


.btn-login:active {

    transform: translateY(0);
}


/* =========================================================
   ERROR
========================================================= */

.error-message {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-top: 18px;

    padding: 12px 14px;

    border-radius: 10px;

    color: #dc2626;

    background:
        rgba(239,68,68,0.07);

    border:
        1px solid
        rgba(239,68,68,0.18);

    font-size: 12px;

    animation:
        shake 0.45s ease;
}


.error-message svg {

    width: 17px;

    height: 17px;

    flex-shrink: 0;
}


@keyframes shake {

    0%,
    100% {
        transform: translateX(0);
    }

    25% {
        transform: translateX(-4px);
    }

    75% {
        transform: translateX(4px);
    }
}


/* =========================================================
   BACK LINK
========================================================= */

.back-link {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 7px;

    margin-top: 25px;

    color: #78716c;

    text-decoration: none;

    font-size: 12px;

    transition:
        color 0.3s ease;
}


.back-link:hover {

    color: var(--primary);
}


.back-link svg {

    width: 15px;

    height: 15px;
}


/* =========================================================
   LOADING OVERLAY
========================================================= */

.loading-overlay {

    position: fixed;

    inset: 0;

    z-index: 9999;

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    background:
        rgba(12,10,9,0.96);

    backdrop-filter: blur(12px);

    opacity: 0;

    visibility: hidden;

    transition:
        opacity 0.3s ease,
        visibility 0.3s ease;
}


.loading-overlay.active {

    opacity: 1;

    visibility: visible;
}


.loading-brand {

    display: flex;

    flex-direction: column;

    align-items: center;

    margin-bottom: 35px;
}


.loading-brand img {

    width: 78px;

    height: 78px;

    object-fit: contain;

    margin-bottom: 12px;

    filter:
        drop-shadow(
            0 10px 25px
            rgba(249,115,22,0.35)
        );
}


.loading-brand-text {

    font-family: "Space Grotesk", sans-serif;

    font-size: 25px;

    font-weight: 700;
}


.loading-brand-text span {

    color: var(--primary);
}


.loading-spinner {

    width: 52px;

    height: 52px;

    border:
        3px solid
        rgba(249,115,22,0.20);

    border-top-color:
        var(--primary);

    border-radius: 50%;

    animation:
        spin 0.9s linear infinite;
}


@keyframes spin {

    to {
        transform: rotate(360deg);
    }
}


.loading-text {

    margin-top: 22px;

    color: var(--text-secondary);

    font-size: 13px;

    letter-spacing: 1px;
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 850px) {

    .login-wrapper {

        flex-direction: column;

        width: min(600px, calc(100% - 30px));
    }

    .info-section {

        padding: 40px;
    }

    .form-section {

        flex: auto;

        padding: 42px;

        background: #ffffff;
    }

    .stats {

        margin-top: 25px;
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 520px) {

    body {

        align-items: flex-start;
    }

    .login-wrapper {

        width: calc(100% - 22px);

        margin: 11px auto;

        border-radius: 20px;
    }

    .info-section {

        padding: 30px 24px;
    }

    .brand {

        margin-bottom: 22px;
    }

    .brand-logo {

        font-size: 27px;
    }

    .system-title {

        font-size: 22px;
    }

    .system-description {

        font-size: 13px;
    }

    .features-list li {

        font-size: 12px;
    }

    .stats {

        gap: 20px;
    }

    .form-section {

        padding: 35px 24px 30px;

        background: #ffffff;
    }

    .login-logo-image {

        width: 78px;

        height: 78px;
    }

    .form-header h2 {

        font-size: 25px;
    }
}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 360px) {

    .info-section {

        padding: 25px 20px;
    }

    .form-section {

        padding: 30px 20px;

        background: #ffffff;
    }

    .stats {

        gap: 14px;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     PARTICLES
===================================================== -->

<div class="particles">

    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>

</div>


<!-- =====================================================
     LOGIN WRAPPER
===================================================== -->

<div class="login-wrapper">


    <!-- =================================================
         LEFT SIDE
    ================================================== -->

    <section class="info-section">

        <div class="brand">

            <div class="brand-logo">
                BeadleQR
            </div>

            <div class="brand-tagline">
                Teacher Attendance System
            </div>

        </div>


        <h1 class="system-title">

            Smart Teacher Attendance & Classroom Management

        </h1>


        <p class="system-description">

            BeadleQR provides a secure and efficient way to
            record and monitor teacher attendance using
            QR-based technology.

        </p>


        <ul class="features-list">

            <li>

                <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                    ></path>

                </svg>

                QR-Based Teacher Attendance

            </li>


            <li>

                <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    ></path>

                </svg>

                Schedule-Based Attendance

            </li>


            <li>

                <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"
                    ></path>

                </svg>

                Classroom & Section Management

            </li>


            <li>

                <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 19v-6a2 2 0 01-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    ></path>

                </svg>

                Attendance Reports & Monitoring

            </li>


            <li>

                <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                    ></path>

                </svg>

                Secure Role-Based Access

            </li>

        </ul>


        <div class="stats">

            <div class="stat-item">

                <div class="stat-number">
                    3
                </div>

                <div class="stat-label">
                    User Roles
                </div>

            </div>


            <div class="stat-item">

                <div class="stat-number">
                    QR
                </div>

                <div class="stat-label">
                    Powered
                </div>

            </div>


            <div class="stat-item">

                <div class="stat-number">
                    24/7
                </div>

                <div class="stat-label">
                    Access
                </div>

            </div>

        </div>

    </section>


    <!-- =================================================
         RIGHT SIDE LOGIN
    ================================================== -->

    <section class="form-section">


        <!-- =================================================
             LOGO INSIDE LOGIN CARD
        ================================================== -->

        <div class="login-logo">

            <img
                src="images/beadle.png"
                alt="BeadleQR Logo"
                class="login-logo-image"
            >

            <div class="login-logo-name">

                Beadle<span>QR</span>

            </div>

        </div>


        <!-- FORM HEADER -->

        <div class="form-header">

            <h2>
                Welcome Back
            </h2>

            <p>
                Sign in to access your attendance dashboard
            </p>

        </div>


        <!-- LOGIN FORM -->

        <form
            method="POST"
            action=""
        >


            <!-- USERNAME -->

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <div class="input-wrapper">

                    <svg
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        ></path>

                    </svg>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrapper">

                    <svg
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                        ></path>

                    </svg>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                name="login"
                class="btn-login"
            >

                Sign In

                <svg
                    width="19"
                    height="19"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    style="vertical-align:middle;margin-left:7px;"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 7l5 5m0 0l-5 5m5-5H6"
                    ></path>

                </svg>

            </button>

        </form>


        <!-- ERROR -->

        <?php if (!empty($error)): ?>

            <div class="error-message">

                <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>

                </svg>

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <!-- BACK -->

        <a
            href="index.php"
            class="back-link"
        >

            <svg
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >

                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                ></path>

            </svg>

            Back to Home

        </a>


    </section>

</div>


<!-- =====================================================
     LOADING OVERLAY
===================================================== -->

<div
    class="loading-overlay"
    id="loadingOverlay"
>

    <div class="loading-brand">

        <img
            src="images/beadle.png"
            alt="BeadleQR"
        >

        <div class="loading-brand-text">

            Beadle<span>QR</span>

        </div>

    </div>

    <div class="loading-spinner"></div>

    <div class="loading-text">
        Signing in...
    </div>

</div>


<script>

/* =========================================================
   LOGIN LOADING
========================================================= */

const loginForm =
    document.querySelector("form");

const loadingOverlay =
    document.getElementById("loadingOverlay");


loginForm.addEventListener(
    "submit",
    function () {

        const username =
            document
                .getElementById("username")
                .value
                .trim();

        const password =
            document
                .getElementById("password")
                .value
                .trim();


        if (username && password) {

            loadingOverlay.classList.add(
                "active"
            );

        }

    }
);

</script>


</body>

</html>
