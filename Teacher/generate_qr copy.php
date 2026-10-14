<?php

session_start();
require "../db.php";
require "../vendor/autoload.php";

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;

/* =========================
   ROLE CHECK
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

if (!isset($_GET['schedule_id'])) {
    die("Invalid schedule.");
}

$schedule_id = (int) $_GET['schedule_id'];

/* =========================
   GET SCHEDULE (VERIFY OWNER)
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM schedules
    WHERE id = :id
    AND teacher_id = :teacher_id
");

$stmt->execute([
    'id' => $schedule_id,
    'teacher_id' => $teacher_id
]);

$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    die("Schedule not found.");
}

/* =========================
   AUTO REFRESH TOKEN (30 SEC SLOT)
========================= */
$timeSlot = floor(time() / 30);
$secret = "BEADER_SECRET_KEY_2026";

$token = hash('sha256', $schedule_id . $timeSlot . $secret);

/* =========================
   UPDATE QR TOKEN (POSTGRESQL FIXED)
========================= */
$update = $conn->prepare("
    UPDATE schedules
    SET qr_token = :token,
        qr_expires_at = NOW() + INTERVAL '30 seconds'
    WHERE id = :id
");

$update->execute([
    'token' => $token,
    'id' => $schedule_id
]);

/* =========================
   QR CONTENT (SECURE + SIMPLE)
========================= */
$qrText = $schedule_id . "|" . $token;

/* =========================
   GENERATE QR CODE
========================= */
try {

    $qr = new QrCode($qrText, new Encoding('UTF-8'));

    $writer = new PngWriter();
    $result = $writer->write($qr);

    $qrImage = $result->getDataUri();

} catch (Exception $e) {
    die("QR generation failed: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html>
<head>

    <title>Live QR Generator</title>

    <meta charset="utf-8">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <script>
        // AUTO REFRESH EVERY 30 SECONDS
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>

</head>

<body class="bg-dark text-white">

<div class="container text-center mt-5">

    <h3>
        <?= htmlspecialchars($schedule['subject_name'] ?? 'Class QR') ?>
    </h3>

    <p>
        Room: <?= htmlspecialchars($schedule['room'] ?? '') ?>
    </p>

    <p class="text-warning">
        🔄 QR auto-refreshes every 30 seconds
    </p>

    <!-- QR CODE -->
    <img src="<?= $qrImage ?>" width="300" class="shadow">

    <br><br>

    <div class="alert alert-success">
        LIVE QR ACTIVE
    </div>

</div>

</body>
</html>