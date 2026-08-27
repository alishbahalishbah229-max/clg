<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('organizer');

$user = getCurrentUser();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>QR Scanner | Campus360</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <!-- QR Scanner Library -->

    <script
        src="https://unpkg.com/html5-qrcode"
    ></script>

    <style>

        :root {
            --navy: #071a36;
            --blue: #123761;
            --gold: #c99a3e;
            --gold-light: #e5c16f;
            --cream: #f5f7fa;
            --white: #ffffff;
            --ink: #172338;
            --muted: #697386;
            --line: #e5e9ef;
            --green: #2f8f5b;
            --red: #b33a3a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "DM Sans", sans-serif;
            background: var(--cream);
            color: var(--ink);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            padding: 28px 18px;
            background: var(--navy);
            color: white;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 12px 30px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .brand-mark {
            width: 40px;
            height: 46px;
            display: grid;
            place-items: center;
            background: #06152c;
            border: 2px solid var(--gold);
            color: var(--gold-light);
            font-family: Georgia, serif;
            font-weight: bold;

            clip-path:
                polygon(
                    0 0,
                    100% 0,
                    100% 78%,
                    50% 100%,
                    0 78%
                );
        }

        .brand strong {
            display: block;
            font-family: "Playfair Display", serif;
            font-size: 16px;
            letter-spacing: 1px;
        }

        .brand small {
            display: block;
            color: var(--gold-light);
            font-size: 7px;
            letter-spacing: 2px;
        }

        .sidebar-nav {
            margin-top: 30px;
        }

        .nav-label {
            padding: 0 12px 10px;
            color: #718198;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin-bottom: 5px;
            border-radius: 7px;
            color: #b9c5d4;
            font-size: 12px;
            transition: .25s;
        }

        .sidebar-nav a:hover {
            background: rgba(255,255,255,.07);
            color: white;
        }

        .sidebar-nav a.active {
            background: rgba(255,255,255,.09);
            color: white;
            border-left: 3px solid var(--gold);
        }

        .nav-icon {
            width: 25px;
            text-align: center;
        }

        /* MAIN */

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        .topbar {
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 38px;
            background: white;
            border-bottom: 1px solid var(--line);
        }

        .page-title {
            color: var(--navy);
            font-family: "Playfair Display", serif;
            font-size: 25px;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info {
            text-align: right;
        }

        .user-info strong {
            display: block;
            font-size: 12px;
        }

        .user-info small {
            color: var(--muted);
            font-size: 9px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--navy);
            color: var(--gold-light);
            font-weight: 700;
        }

        /* CONTENT */

        .content {
            max-width: 1100px;
            padding: 40px;
        }

        .eyebrow {
            margin-bottom: 8px;
            color: var(--gold);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        h1 {
            color: var(--navy);
            font-family: "Playfair Display", serif;
            font-size: 38px;
        }

        .intro {
            margin-bottom: 30px;
        }

        .intro p {
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
        }

        /* SCANNER CARD */

        .scanner-card {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 25px;
            padding: 25px;
            background: white;
            border: 1px solid var(--line);
            border-radius: 14px;

            box-shadow:
                0 20px 50px rgba(7,26,54,.07);
        }

        /* CAMERA */

        .scanner-area {
            padding: 20px;
            background: #f7f9fb;
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        .scanner-heading {
            text-align: center;
            margin-bottom: 18px;
        }

        .scanner-heading h2 {
            color: var(--navy);
            font-family: "Playfair Display", serif;
            font-size: 23px;
        }

        .scanner-heading p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
        }

        #reader {
            width: 100%;
            max-width: 480px;
            margin: auto;
            overflow: hidden;
            border-radius: 10px;
        }

        .scanner-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .btn-primary {
            background: var(--navy);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue);
        }

        .btn-danger {
            background: var(--red);
            color: white;
        }

        /* RESULT */

        .result {
            padding: 25px;
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        .result h3 {
            margin-bottom: 18px;
            color: var(--navy);
            font-family: "Playfair Display", serif;
            font-size: 21px;
        }

        .result-box {
            padding: 20px;
            background: #f7f9fb;
            border-radius: 8px;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        .result-box.success {
            background: #eaf7ef;
            color: var(--green);
        }

        .result-box.error {
            background: #fceded;
            color: var(--red);
        }

        .result-box.warning {
            background: #fff7e6;
            color: #996d16;
        }

        .result-box strong {
            display: block;
            margin-bottom: 5px;
            color: var(--ink);
            font-size: 14px;
        }

        .info {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #edf0f3;
            font-size: 10px;
        }

        .info-row span:first-child {
            color: var(--muted);
        }

        .info-row span:last-child {
            font-weight: 600;
            text-align: right;
        }

        @media (max-width: 850px) {

            .sidebar {
                width: 70px;
                padding: 20px 8px;
            }

            .brand {
                justify-content: center;
            }

            .brand > div:last-child {
                display: none;
            }

            .nav-label {
                display: none;
            }

            .sidebar-nav a {
                justify-content: center;
            }

            .sidebar-nav a span:last-child {
                display: none;
            }

            .main {
                margin-left: 70px;
            }

            .scanner-card {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {

            .topbar {
                padding: 0 18px;
            }

            .user-info {
                display: none;
            }

            .content {
                padding: 22px;
            }

            h1 {
                font-size: 30px;
            }

        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <a
            href="../../index.php"
            class="brand"
        >

            <div class="brand-mark">
                C
            </div>

            <div>

                <strong>
                    CAMPUS360
                </strong>

                <small>
                    COLLEGE COMMUNITY
                </small>

            </div>

        </a>

        <nav class="sidebar-nav">

            <div class="nav-label">
                Organizer Portal
            </div>

            <a href="dashboard.php">
                <span class="nav-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a href="create-event.php">
                <span class="nav-icon">+</span>
                <span>Create Event</span>
            </a>

            <a href="manage-events.php">
                <span class="nav-icon">◈</span>
                <span>Manage Events</span>
            </a>

            <a
                href="qr-scanner.php"
                class="active"
            >
                <span class="nav-icon">▣</span>
                <span>QR Scanner</span>
            </a>

            <a href="media-upload.php">
                <span class="nav-icon">▧</span>
                <span>Media Upload</span>
            </a>

        </nav>

    </aside>

    <!-- MAIN -->

    <main class="main">

        <header class="topbar">

            <div class="page-title">
                QR Scanner
            </div>

            <div class="user">

                <div class="user-info">

                    <strong>
                        <?= sanitize($user['full_name']) ?>
                    </strong>

                    <small>
                        Event Organizer
                    </small>

                </div>

                <div class="avatar">

                    <?= strtoupper(
                        substr(
                            $user['full_name'],
                            0,
                            1
                        )
                    ) ?>

                </div>

            </div>

        </header>

        <section class="content">

            <div class="intro">

                <div class="eyebrow">
                    Attendance Management
                </div>

                <h1>
                    Scan Event Tickets
                </h1>

                <p>
                    Scan a student's Campus360 QR ticket
                    to verify registration and record attendance.
                </p>

            </div>

            <div class="scanner-card">

                <!-- SCANNER -->

                <div class="scanner-area">

                    <div class="scanner-heading">

                        <h2>
                            QR Code Scanner
                        </h2>

                        <p>
                            Position the student's QR code
                            inside the camera frame.
                        </p>

                    </div>

                    <div id="reader"></div>

                    <div class="scanner-buttons">

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="startScanner"
                        >
                            START CAMERA
                        </button>

                        <button
                            type="button"
                            class="btn btn-danger"
                            id="stopScanner"
                            style="display:none;"
                        >
                            STOP CAMERA
                        </button>

                    </div>

                </div>

                <!-- RESULT -->

                <div class="result">

                    <h3>
                        Scan Result
                    </h3>

                    <div
                        class="result-box"
                        id="scanResult"
                    >

                        <strong>
                            Ready to Scan
                        </strong>

                        Start the camera and scan
                        a valid Campus360 ticket.

                    </div>

                    <div class="info">

                        <div class="info-row">

                            <span>
                                Student
                            </span>

                            <span id="studentName">
                                —
                            </span>

                        </div>

                        <div class="info-row">

                            <span>
                                Event
                            </span>

                            <span id="eventName">
                                —
                            </span>

                        </div>

                        <div class="info-row">

                            <span>
                                Registration
                            </span>

                            <span id="registrationId">
                                —
                            </span>

                        </div>

                        <div class="info-row">

                            <span>
                                Status
                            </span>

                            <span id="attendanceStatus">
                                —
                            </span>

                        </div>

                        <div class="info-row">

                            <span>
                                Scanned At
                            </span>

                            <span id="scannedAt">
                                —
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    </main>

 <script>
    console.log("QR SCANNER JAVASCRIPT LOADED");
let scanner = null;
let scannerRunning = false;
let processingScan = false;

const startButton = document.getElementById("startScanner");
const stopButton = document.getElementById("stopScanner");
const resultBox = document.getElementById("scanResult");

startButton.addEventListener("click", startScanner);
stopButton.addEventListener("click", stopScanner);


/* =========================
   START CAMERA
========================= */

async function startScanner() {
console.log("START CAMERA BUTTON CLICKED");
    if (scannerRunning) {
        return;
    }

    showMessage(
        "Connecting Camera...",
        "Please allow camera access when your browser asks.",
        "normal"
    );

    try {

        // Check HTTPS / localhost
        const isSecure =
            window.isSecureContext ||
            location.hostname === "localhost" ||
            location.hostname === "127.0.0.1";

        if (!isSecure) {
            throw new Error(
                "Camera requires HTTPS. Open this website using HTTPS."
            );
        }


        // Check browser support
        if (
            !navigator.mediaDevices ||
            !navigator.mediaDevices.getUserMedia
        ) {
            throw new Error(
                "Camera API is not supported by this browser."
            );
        }


        // Create scanner
        scanner = new Html5Qrcode("reader");


        // Get cameras
        const cameras = await Html5Qrcode.getCameras();


        if (!cameras || cameras.length === 0) {
            throw new Error(
                "No camera was detected on this device."
            );
        }


        console.log("Available cameras:", cameras);


        // Select camera
        let selectedCamera = cameras[0].id;

        for (const camera of cameras) {

            const label =
                (camera.label || "").toLowerCase();

            if (
                label.includes("back") ||
                label.includes("rear") ||
                label.includes("environment")
            ) {
                selectedCamera = camera.id;
                break;
            }
        }


        // Start scanner
        await scanner.start(

            selectedCamera,

            {
                fps: 10,

                qrbox: {
                    width: 250,
                    height: 250
                },

                aspectRatio: 1.0,

                disableFlip: false
            },

            onScanSuccess,

            onScanFailure

        );


        scannerRunning = true;

        startButton.style.display = "none";
        stopButton.style.display = "inline-block";


        showMessage(
            "Camera Connected",
            "Camera is ready. Place the QR code inside the scanning box.",
            "success"
        );


    } catch (error) {

        console.error("CAMERA ERROR:", error);

        scannerRunning = false;

        if (scanner) {

            try {
                await scanner.clear();
            } catch (e) {
                console.error(e);
            }

        }

        scanner = null;

        startButton.style.display = "inline-block";
        stopButton.style.display = "none";


        let message =
            "Unable to connect to the camera.";


        if (error.name === "NotAllowedError") {

            message =
                "Camera permission was denied. Please allow camera access in browser settings.";

        }

        else if (error.name === "NotFoundError") {

            message =
                "No camera was found on this device.";

        }

        else if (error.name === "NotReadableError") {

            message =
                "The camera is already being used by another application.";

        }

        else if (error.name === "OverconstrainedError") {

            message =
                "The selected camera is not available.";

        }

        else if (error.message) {

            message = error.message;

        }


        showMessage(
            "Camera Connection Failed",
            message,
            "error"
        );
    }
}


/* =========================
   STOP CAMERA
========================= */

stopButton.addEventListener("click", stopScanner);

async function stopScanner() {

    if (!scanner) {
        return;
    }

    try {

        if (scannerRunning) {
            await scanner.stop();
        }

        await scanner.clear();

    } catch (error) {

        console.error(
            "Scanner stop error:",
            error
        );

    }

    scannerRunning = false;

    startButton.style.display = "inline-block";
    stopButton.style.display = "none";
}


/* =========================
   QR SUCCESS
========================= */

async function onScanSuccess(decodedText, decodedResult) {

    if (processingScan) {
        return;
    }

    processingScan = true;

    console.log(
        "QR detected:",
        decodedText
    );

    await stopScanner();

    await verifyQR(decodedText);

    setTimeout(function () {

        processingScan = false;

    }, 2500);
}


/* =========================
   QR FAILURE
========================= */

function onScanFailure(error) {
    // Ignore normal scanning failures
}


/* =========================
   VERIFY QR
========================= */

async function verifyQR(qrHash) {

    showMessage(
        "Verifying Ticket...",
        "Checking registration details.",
        "normal"
    );


    const formData = new FormData();

    formData.append(
        "qr_hash",
        qrHash
    );


    try {

        const response = await fetch(
            "../../api/qr.php",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            }
        );


        if (!response.ok) {

            throw new Error(
                "Server returned HTTP " +
                response.status
            );

        }


        const data = await response.json();

        console.log(
            "QR API Response:",
            data
        );


        if (!data.success) {

            showMessage(
                "Invalid Ticket",
                data.message ||
                "QR ticket could not be verified.",
                "error"
            );

            clearStudentInfo();

            return;
        }


        if (data.already_scanned) {

            showMessage(
                "Already Scanned",
                data.message ||
                "This ticket has already been scanned.",
                "warning"
            );

            updateStudentInfo(data);

            return;
        }


        showMessage(
            "Attendance Marked",
            data.message ||
            "Student attendance has been recorded successfully.",
            "success"
        );

        updateStudentInfo(data);


    } catch (error) {

        console.error(
            "QR verification error:",
            error
        );


        showMessage(
            "Verification Error",
            "QR was detected but the server could not be reached.",
            "error"
        );
    }
}


/* =========================
   UPDATE INFORMATION
========================= */

function updateStudentInfo(data) {

    document.getElementById("studentName").textContent =
        data.student || "—";

    document.getElementById("eventName").textContent =
        data.event || "—";

    document.getElementById("registrationId").textContent =
        data.reg_id || "—";

    document.getElementById("attendanceStatus").textContent =
        data.status || "—";

    document.getElementById("scannedAt").textContent =
        data.scanned_at || "—";
}


/* =========================
   CLEAR INFORMATION
========================= */

function clearStudentInfo() {

    document.getElementById("studentName").textContent = "—";
    document.getElementById("eventName").textContent = "—";
    document.getElementById("registrationId").textContent = "—";
    document.getElementById("attendanceStatus").textContent = "—";
    document.getElementById("scannedAt").textContent = "—";
}


/* =========================
   RESULT MESSAGE
========================= */

function showMessage(title, message, type) {

    resultBox.className = "result-box";

    if (type === "success") {
        resultBox.classList.add("success");
    }

    if (type === "error") {
        resultBox.classList.add("error");
    }

    if (type === "warning") {
        resultBox.classList.add("warning");
    }

    resultBox.innerHTML =
        "<strong>" +
        escapeHtml(title) +
        "</strong>" +
        escapeHtml(message);
}


/* =========================
   HTML ESCAPE
========================= */

function escapeHtml(text) {

    const div = document.createElement("div");

    div.textContent = text;

    return div.innerHTML;
}
</script>
</body>

</html>