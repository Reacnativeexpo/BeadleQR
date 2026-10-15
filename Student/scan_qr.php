<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
| scan_qr.php is inside:
| Beader/Teacher/view/
|
| db.php is inside:
| Beader/
|--------------------------------------------------------------------------
*/

require "../db.php";


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
|
| Only logged-in students can use the Beader QR scanner.
|
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: ../../login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| CURRENT BEADER
|--------------------------------------------------------------------------
*/

$beadle_id = (int) $_SESSION['user_id'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Beadle QR Scanner</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- QR SCANNER -->

    <script src="https://unpkg.com/html5-qrcode"></script>


    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            min-height: 100vh;

            background:
                linear-gradient(
                    135deg,
                    #0f172a,
                    #1e293b
                );

            color: white;

            font-family:
                Arial,
                sans-serif;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;

        }


        .scanner-container {

            width: 100%;

            max-width: 500px;

            background: white;

            color: #1e293b;

            border-radius: 20px;

            padding: 25px;

            box-shadow:
                0 20px 50px rgba(0,0,0,0.35);

        }


        .scanner-header {

            text-align: center;

            margin-bottom: 20px;

        }


        .scanner-icon {

            width: 65px;

            height: 65px;

            margin: 0 auto 12px;

            border-radius: 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff7ed;

            color: #f97316;

            font-size: 30px;

        }


        .scanner-header h2 {

            font-size: 22px;

            font-weight: 700;

            margin-bottom: 5px;

        }


        .scanner-header p {

            margin: 0;

            color: #64748b;

            font-size: 13px;

        }


        #reader {

            width: 100%;

            margin: 0 auto;

            overflow: hidden;

            border-radius: 15px;

        }


        #result {

            margin-top: 20px;

        }


        .result-box {

            padding: 18px;

            border-radius: 12px;

            text-align: center;

            font-weight: 600;

        }


        .success {

            background: #dcfce7;

            color: #15803d;

            border: 1px solid #86efac;

        }


        .error {

            background: #fee2e2;

            color: #b91c1c;

            border: 1px solid #fca5a5;

        }


        .loading {

            background: #eff6ff;

            color: #1d4ed8;

            border: 1px solid #93c5fd;

        }


        .scanner-info {

            margin-top: 20px;

            padding: 14px;

            background: #f8fafc;

            border-radius: 10px;

            text-align: center;

            color: #64748b;

            font-size: 12px;

        }


        .back-btn {

            display: block;

            text-align: center;

            margin-top: 15px;

            text-decoration: none;

            color: #64748b;

            font-size: 13px;

        }


        .back-btn:hover {

            color: #f97316;

        }


        /*

        |--------------------------------------------------------------
        | HIDE SOME HTML5 QR CODE DEFAULT ELEMENTS
        |--------------------------------------------------------------
        */

        #reader__dashboard_section_csr button {

            background: #f97316 !important;

            color: white !important;

            border: none !important;

            border-radius: 8px !important;

            padding: 8px 15px !important;

        }


        #reader__dashboard_section_swaplink {

            color: #f97316 !important;

        }


        @media(max-width:500px) {

            body {

                padding: 10px;

            }

            .scanner-container {

                padding: 18px;

                border-radius: 15px;

            }

        }

    </style>

</head>


<body>


<div class="scanner-container">


    <!-- HEADER -->

    <div class="scanner-header">

        <div class="scanner-icon">

            <i class="fa-solid fa-qrcode"></i>

        </div>

        <h2>Beadle QR Scanner</h2>

        <p>
            Scan the teacher's QR code to record attendance
        </p>

    </div>


    <!-- SCANNER -->

    <div id="reader"></div>


    <!-- RESULT -->

    <div id="result"></div>


    <!-- INFORMATION -->

    <div class="scanner-info">

        <i class="fa-solid fa-circle-info"></i>

        Scan the QR code generated by your teacher.

        <br>

        Your first scan records your
        <strong>Time In</strong>.

        <br>

        Your second scan records your
        <strong>Time Out</strong>.

    </div>


    <a
        href="../../dashboard.php"
        class="back-btn"
    >

        <i class="fa-solid fa-arrow-left"></i>

        Back to Dashboard

    </a>


</div>


<!-- AUDIO -->

<audio
    id="beepSuccess"
    src="https://www.soundjay.com/button/sounds/button-3.mp3"
    preload="auto"
></audio>


<audio
    id="beepError"
    src="https://www.soundjay.com/button/sounds/button-10.mp3"
    preload="auto"
></audio>


<script>

/*
|--------------------------------------------------------------------------
| BEADLE ID
|--------------------------------------------------------------------------
|
| This comes from the PHP session.
|
*/

const beadleId = <?php echo $beadle_id; ?>;


/*
|--------------------------------------------------------------------------
| PREVENT MULTIPLE SCANS
|--------------------------------------------------------------------------
*/

let processingScan = false;


/*
|--------------------------------------------------------------------------
| SUCCESS SOUND
|--------------------------------------------------------------------------
*/

function successBeep() {

    const audio =
        document.getElementById("beepSuccess");

    audio.currentTime = 0;

    audio.play().catch(() => {});

}


/*
|--------------------------------------------------------------------------
| ERROR SOUND
|--------------------------------------------------------------------------
*/

function errorBeep() {

    const audio =
        document.getElementById("beepError");

    audio.currentTime = 0;

    audio.play().catch(() => {});

}


/*
|--------------------------------------------------------------------------
| SHOW SUCCESS
|--------------------------------------------------------------------------
*/

function showSuccess(message) {

    document.getElementById("result").innerHTML = `

        <div class="result-box success">

            <i class="fa-solid fa-circle-check"></i>

            <br><br>

            ${message}

        </div>

    `;

}


/*
|--------------------------------------------------------------------------
| SHOW ERROR
|--------------------------------------------------------------------------
*/

function showError(message) {

    document.getElementById("result").innerHTML = `

        <div class="result-box error">

            <i class="fa-solid fa-circle-xmark"></i>

            <br><br>

            ${message}

        </div>

    `;

}


/*
|--------------------------------------------------------------------------
| SHOW LOADING
|--------------------------------------------------------------------------
*/

function showLoading() {

    document.getElementById("result").innerHTML = `

        <div class="result-box loading">

            <i class="fa-solid fa-spinner fa-spin"></i>

            <br><br>

            Processing attendance...

        </div>

    `;

}


/*
|--------------------------------------------------------------------------
| QR SCAN SUCCESS
|--------------------------------------------------------------------------
*/

function onScanSuccess(decodedText) {


    /*
    |--------------------------------------------------------------------------
    | PREVENT DUPLICATE SCANS
    |--------------------------------------------------------------------------
    */

    if (processingScan) {

        return;

    }


    processingScan = true;


    console.log(
        "QR SCANNED:",
        decodedText
    );


    showLoading();


    /*
    |--------------------------------------------------------------------------
    | SEND QR TO SERVER
    |--------------------------------------------------------------------------
    */

    fetch("process_scan.php", {

        method: "POST",

        headers: {

            "Content-Type":
                "application/json",

            "Accept":
                "application/json"

        },

        body: JSON.stringify({

            qr: decodedText

        })

    })


    .then(response => {

        if (!response.ok) {

            throw new Error(
                "Server returned HTTP " +
                response.status
            );

        }

        return response.json();

    })


    .then(data => {


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        if (data.status === "success") {

            successBeep();

            showSuccess(
                data.message
            );


        }

        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        else {

            errorBeep();

            showError(
                data.message ||
                "Unable to record attendance."
            );

        }

    })


    .catch(error => {

        console.error(
            "SCAN ERROR:",
            error
        );

        errorBeep();

        showError(
            "Unable to process the QR code. Please try again."
        );

    })


    .finally(() => {


        /*
        |--------------------------------------------------------------------------
        | ALLOW SCANNING AGAIN
        |--------------------------------------------------------------------------
        |
        | Wait 2 seconds so the same QR isn't immediately scanned
        | multiple times.
        |
        */

        setTimeout(() => {

            processingScan = false;

        }, 2000);

    });

}


/*
|--------------------------------------------------------------------------
| QR SCAN ERROR
|--------------------------------------------------------------------------
*/

function onScanFailure(error) {

    /*
    | Do not display every scanner frame error.
    | html5-qrcode continuously reports "not found" while scanning.
    */

}


/*
|--------------------------------------------------------------------------
| START SCANNER
|--------------------------------------------------------------------------
*/

const scanner =
    new Html5QrcodeScanner(

        "reader",

        {

            fps: 10,

            qrbox: {

                width: 250,

                height: 250

            },

            rememberLastUsedCamera: true,

            supportedScanTypes: [
                Html5QrcodeScanType.SCAN_TYPE_CAMERA
            ]

        },

        false

    );


scanner.render(
    onScanSuccess,
    onScanFailure
);

</script>


<!-- FONT AWESOME -->

<script
    src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js"
></script>


</body>
</html>

