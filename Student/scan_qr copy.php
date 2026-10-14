<?php
session_start();
require "../db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$beadle_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Beadle QR Scanner</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>
        body {
            background: #111;
            color: white;
            text-align: center;
        }

        #reader {
            width: 350px;
            margin: auto;
        }

        .success {
            background: #28a745;
            padding: 20px;
            border-radius: 10px;
        }

        .error {
            background: #dc3545;
            padding: 20px;
            border-radius: 10px;
        }
    </style>
</head>

<body>

<h2 class="mt-3">📷 Beadle Scanner</h2>

<div id="reader"></div>

<div id="result" class="mt-3"></div>

<audio id="beepSuccess" src="https://www.soundjay.com/button/sounds/button-3.mp3"></audio>
<audio id="beepError" src="https://www.soundjay.com/button/sounds/button-10.mp3"></audio>

<script>

function successBeep() {
    document.getElementById("beepSuccess").play();
}

function errorBeep() {
    document.getElementById("beepError").play();
}

function showSuccess(msg) {
    document.getElementById("result").innerHTML =
        `<div class="success">🟢 ${msg}</div>`;
}

function showError(msg) {
    document.getElementById("result").innerHTML =
        `<div class="error">🔴 ${msg}</div>`;
}

function onScanSuccess(decodedText) {

    console.log("SCANNED VALUE:", decodedText);
    alert(decodedText); // show what QR contains

    fetch("process_scan.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ qr: decodedText })
    })
    .then(res => res.json())
    .then(data => {

        if (data.status === "success") {
            successBeep();
            showSuccess(data.message);
        } else {
            errorBeep();
            showError(data.message);
        }

    });

}

const scanner = new Html5QrcodeScanner(
    "reader",
    { fps: 10, qrbox: 250 }
);

scanner.render(onScanSuccess);

</script>

</body>
</html>