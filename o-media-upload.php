<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole('organizer');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

$userName = $user['full_name'] ?? 'Organizer';
$userId   = (string)($user['user_id'] ?? '');

$initial = strtoupper(
    substr(
        trim($userName),
        0,
        1
    )
);


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$pdoConnection = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $pdoConnection = $pdo;
} elseif (isset($db) && $db instanceof PDO) {
    $pdoConnection = $db;
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['media_upload_token'])) {

    $_SESSION['media_upload_token'] =
        bin2hex(
            random_bytes(32)
        );

}

$csrfToken =
    $_SESSION['media_upload_token'];


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$selectedEventId =
    trim(
        $_POST['event_id']
        ?? $_GET['event_id']
        ?? ''
    );

$caption =
    trim(
        $_POST['caption']
        ?? ''
    );

$errors = [];

$successMessage = '';

$uploadedFiles = [];


/*
|--------------------------------------------------------------------------
| LOAD ORGANIZER EVENTS
|--------------------------------------------------------------------------
*/

$events = [];

if ($pdoConnection instanceof PDO) {

    try {

        $stmt =
            $pdoConnection->prepare("
                SELECT
                    event_id,
                    title,
                    start_date,
                    approval_state
                FROM events
                WHERE organizer_id = :organizer_id
                ORDER BY start_date DESC
            ");

        $stmt->execute([
            ':organizer_id' =>
                $userId
        ]);

        $events =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    }

    catch (PDOException $e) {

        error_log(
            'Media Event Load Error: ' .
            $e->getMessage()
        );

        $errors[] =
            'Unable to load your events.';

    }

}


/*
|--------------------------------------------------------------------------
| UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$allowedMimeTypes = [

    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'

];

$maxFileSize =
    5 * 1024 * 1024;


/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$uploadDirectory =
    __DIR__ .
    '/../../uploads/event-media/';


$uploadWebPath =
    '../../uploads/event-media/';


/*
|--------------------------------------------------------------------------
| GENERATE UUID
|--------------------------------------------------------------------------
*/

function generateMediaUuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff)
    );
}


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['media_upload_token'],
            $_POST['csrf_token']
        )
    ) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | EVENT VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($selectedEventId === '') {

        $errors[] =
            'Please select an event.';

    }


    /*
    |--------------------------------------------------------------------------
    | CAPTION VALIDATION
    |--------------------------------------------------------------------------
    */

    if (mb_strlen($caption) > 2000) {

        $errors[] =
            'Caption cannot exceed 2000 characters.';

    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY EVENT OWNERSHIP
    |--------------------------------------------------------------------------
    */

    $selectedEvent = null;

    if (
        empty($errors) &&
        $pdoConnection instanceof PDO
    ) {

        try {

            $stmt =
                $pdoConnection->prepare("
                    SELECT
                        event_id,
                        title
                    FROM events
                    WHERE event_id = :event_id
                    AND organizer_id = :organizer_id
                    LIMIT 1
                ");

            $stmt->execute([

                ':event_id' =>
                    $selectedEventId,

                ':organizer_id' =>
                    $userId

            ]);

            $selectedEvent =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$selectedEvent) {

                $errors[] =
                    'The selected event does not belong to your organizer account.';

            }

        }

        catch (PDOException $e) {

            error_log(
                'Media Event Verification Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to verify the selected event.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | FILE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        (
            !isset($_FILES['media']) ||
            !is_array(
                $_FILES['media']['name']
            )
        )
    ) {

        $errors[] =
            'Please select at least one image.';

    }


    /*
    |--------------------------------------------------------------------------
    | DIRECTORY
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        !is_dir(
            $uploadDirectory
        )
    ) {

        if (
            !mkdir(
                $uploadDirectory,
                0755,
                true
            )
        ) {

            $errors[] =
                'Unable to create the media upload directory.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | PROCESS IMAGES
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        isset($_FILES['media'])
    ) {

        $fileCount =
            count(
                $_FILES['media']['name']
            );


        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );


        for (
            $i = 0;
            $i < $fileCount;
            $i++
        ) {

            $originalName =
                $_FILES['media']['name'][$i]
                ?? '';

            $tmpName =
                $_FILES['media']['tmp_name'][$i]
                ?? '';

            $fileSize =
                (int)(
                    $_FILES['media']['size'][$i]
                    ?? 0
                );

            $errorCode =
                (int)(
                    $_FILES['media']['error'][$i]
                    ?? UPLOAD_ERR_NO_FILE
                );


            /*
            |--------------------------------------------------------------------------
            | SKIP EMPTY
            |--------------------------------------------------------------------------
            */

            if (
                $errorCode ===
                UPLOAD_ERR_NO_FILE
            ) {

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | UPLOAD ERROR
            |--------------------------------------------------------------------------
            */

            if (
                $errorCode !==
                UPLOAD_ERR_OK
            ) {

                $errors[] =
                    $originalName .
                    ': Upload failed.';

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | SIZE
            |--------------------------------------------------------------------------
            */

            if (
                $fileSize >
                $maxFileSize
            ) {

                $errors[] =
                    $originalName .
                    ': Maximum file size is 5 MB.';

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | MIME
            |--------------------------------------------------------------------------
            */

            $mimeType =
                $finfo->file(
                    $tmpName
                );


            if (
                !isset(
                    $allowedMimeTypes[
                        $mimeType
                    ]
                )
            ) {

                $errors[] =
                    $originalName .
                    ': Only JPG, PNG, WEBP and GIF images are allowed.';

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | IMAGE CHECK
            |--------------------------------------------------------------------------
            */

            if (
                @getimagesize(
                    $tmpName
                ) === false
            ) {

                $errors[] =
                    $originalName .
                    ': Invalid image file.';

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | SAFE FILE NAME
            |--------------------------------------------------------------------------
            */

            $extension =
                $allowedMimeTypes[
                    $mimeType
                ];


            $safeEventId =
                preg_replace(
                    '/[^a-zA-Z0-9]/',
                    '',
                    $selectedEventId
                );


            $safeFileName =
                'event_' .
                $safeEventId .
                '_' .
                bin2hex(
                    random_bytes(10)
                ) .
                '.' .
                $extension;


            $destination =
                $uploadDirectory .
                $safeFileName;


            /*
            |--------------------------------------------------------------------------
            | MOVE FILE
            |--------------------------------------------------------------------------
            */

            if (
                !move_uploaded_file(
                    $tmpName,
                    $destination
                )
            ) {

                $errors[] =
                    $originalName .
                    ': Unable to save the file.';

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | DATABASE RECORD
            |--------------------------------------------------------------------------
            */

            try {

                $mediaId =
                    generateMediaUuid();


                $fileUrl =
                    $uploadWebPath .
                    $safeFileName;


                $stmt =
                    $pdoConnection->prepare("
                        INSERT INTO media_gallery
                        (
                            media_id,
                            event_id,
                            media_type,
                            file_url,
                            caption,
                            is_published,
                            uploaded_by
                        )
                        VALUES
                        (
                            :media_id,
                            :event_id,
                            :media_type,
                            :file_url,
                            :caption,
                            :is_published,
                            :uploaded_by
                        )
                    ");


                $stmt->execute([

                    ':media_id' =>
                        $mediaId,

                    ':event_id' =>
                        $selectedEventId,

                    ':media_type' =>
                        'photo',

                    ':file_url' =>
                        $fileUrl,

                    ':caption' =>
                        $caption !== ''
                            ? $caption
                            : null,

                    ':is_published' =>
                        0,

                    ':uploaded_by' =>
                        $userId

                ]);


                $uploadedFiles[] = [

                    'original_name' =>
                        $originalName,

                    'file_name' =>
                        $safeFileName,

                    'path' =>
                        $fileUrl,

                    'media_id' =>
                        $mediaId

                ];


            }

            catch (PDOException $e) {

                /*
                |--------------------------------------------------------------------------
                | REMOVE FILE IF DATABASE INSERT FAILED
                |--------------------------------------------------------------------------
                */

                if (
                    file_exists(
                        $destination
                    )
                ) {

                    unlink(
                        $destination
                    );

                }


                error_log(
                    'Media Gallery Insert Error: ' .
                    $e->getMessage()
                );


                $errors[] =
                    $originalName .
                    ': File was uploaded but could not be registered in the database.';

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    */

    if (
        !empty($uploadedFiles)
    ) {

        $successMessage =
            count($uploadedFiles) .
            ' image(s) uploaded successfully for "' .
            ($selectedEvent['title'] ?? 'Event') .
            '".';

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

    <title>
        Media Upload | EventSphere
    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >


    <style>

        :root {

            --navy:#071a36;
            --blue:#123761;
            --gold:#c99a3e;
            --gold-light:#e5c16f;
            --cream:#f5f7fa;
            --white:#ffffff;
            --ink:#172338;
            --muted:#697386;
            --line:#e4e8ee;
            --green:#2f8f5b;
            --green-bg:#edf8f1;
            --red:#b33a3a;
            --red-bg:#fff0f0;
            --gold-bg:#fff8e9;
            --shadow:0 18px 50px rgba(7,26,54,.07);

        }


        * {

            box-sizing:border-box;
            margin:0;
            padding:0;

        }


        body {

            font-family:"DM Sans",sans-serif;
            background:var(--cream);
            color:var(--ink);
            line-height:1.6;

        }


        a {

            color:inherit;
            text-decoration:none;

        }


        input,
        select,
        textarea,
        button {

            font-family:inherit;

        }


        /* SIDEBAR */

        .sidebar {

            position:fixed;
            top:0;
            left:0;
            width:250px;
            height:100vh;
            padding:24px 16px;
            background:var(--navy);
            color:white;
            z-index:100;

        }


        .brand {

            display:flex;
            align-items:center;
            gap:12px;
            padding:4px 12px 25px;
            border-bottom:1px solid rgba(255,255,255,.1);

        }


        .brand-mark {

            width:42px;
            height:48px;
            display:grid;
            place-items:center;
            background:#06152c;
            border:2px solid var(--gold);
            color:var(--gold-light);
            font-family:Georgia,serif;
            font-size:20px;
            font-weight:bold;

            clip-path:
                polygon(
                    0 0,
                    100% 0,
                    100% 78%,
                    50% 100%,
                    0 78%
                );

        }


        .brand-text strong {

            display:block;
            font-family:"Playfair Display",serif;
            font-size:17px;
            letter-spacing:1px;

        }


        .brand-text small {

            display:block;
            color:var(--gold-light);
            font-size:7px;
            letter-spacing:2px;

        }


        .nav-section {

            margin-top:30px;

        }


        .nav-title {

            padding:0 12px 10px;
            color:#718198;
            font-size:9px;
            font-weight:700;
            letter-spacing:1.7px;
            text-transform:uppercase;

        }


        .nav-link {

            display:flex;
            align-items:center;
            gap:12px;
            margin-bottom:5px;
            padding:12px;
            border-radius:7px;
            color:#b8c4d3;
            font-size:12px;
            transition:.25s;

        }


        .nav-link:hover {

            background:rgba(255,255,255,.07);
            color:white;

        }


        .nav-link.active {

            background:rgba(255,255,255,.09);
            color:white;
            border-left:3px solid var(--gold);
            padding-left:9px;

        }


        .nav-icon {

            width:25px;
            height:25px;
            display:grid;
            place-items:center;

        }


        /* MAIN */

        .main {

            min-height:100vh;
            margin-left:250px;

        }


        .topbar {

            height:76px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 38px;
            background:white;
            border-bottom:1px solid var(--line);

        }


        .topbar-left {

            display:flex;
            flex-direction:column;

        }


        .topbar-label {

            color:var(--gold);
            font-size:9px;
            font-weight:700;
            letter-spacing:1.7px;
            text-transform:uppercase;

        }


        .page-title {

            color:var(--navy);
            font-family:"Playfair Display",serif;
            font-size:25px;

        }


        .user-area {

            display:flex;
            align-items:center;
            gap:12px;

        }


        .user-details {

            text-align:right;

        }


        .user-details strong {

            display:block;
            font-size:12px;

        }


        .user-details span {

            display:block;
            color:var(--muted);
            font-size:9px;

        }


        .avatar {

            width:42px;
            height:42px;
            display:grid;
            place-items:center;
            border-radius:50%;
            background:var(--navy);
            color:var(--gold-light);
            font-size:14px;
            font-weight:700;

        }


        /* CONTENT */

        .content {

            max-width:1100px;
            margin:auto;
            padding:42px 40px 60px;

        }


        .intro {

            margin-bottom:25px;

        }


        .eyebrow {

            color:var(--gold);
            font-size:10px;
            font-weight:700;
            letter-spacing:2px;
            text-transform:uppercase;
            margin-bottom:7px;

        }


        h1 {

            color:var(--navy);
            font-family:"Playfair Display",serif;
            font-size:38px;
            line-height:1.15;

        }


        .intro p {

            max-width:700px;
            margin-top:8px;
            color:var(--muted);
            font-size:12px;

        }


        /* ALERT */

        .alert {

            margin-bottom:20px;
            padding:14px 17px;
            border-radius:8px;
            font-size:10px;

        }


        .alert-success {

            background:var(--green-bg);
            border:1px solid #ccebd8;
            color:var(--green);

        }


        .alert-error {

            background:var(--red-bg);
            border:1px solid #efcccc;
            color:var(--red);

        }


        .alert strong {

            display:block;
            margin-bottom:6px;

        }


        .alert ul {

            padding-left:18px;

        }


        /* GRID */

        .upload-grid {

            display:grid;
            grid-template-columns:1.3fr .7fr;
            gap:22px;

        }


        .card {

            background:white;
            border:1px solid var(--line);
            border-radius:12px;
            box-shadow:var(--shadow);

        }


        .upload-card {

            padding:26px;

        }


        .card-heading {

            margin-bottom:20px;

        }


        .card-heading h2,
        .gallery-card h2 {

            color:var(--navy);
            font-family:"Playfair Display",serif;
            font-size:21px;

        }


        .card-heading p {

            margin-top:4px;
            color:var(--muted);
            font-size:10px;

        }


        .field {

            display:flex;
            flex-direction:column;
            margin-bottom:18px;

        }


        .field label {

            margin-bottom:7px;
            color:var(--ink);
            font-size:10px;
            font-weight:700;

        }


        .required {

            color:var(--red);

        }


        .control {

            width:100%;
            padding:12px 13px;
            outline:none;
            border:1px solid var(--line);
            border-radius:6px;
            background:#fbfcfd;
            color:var(--ink);
            font-size:11px;

        }


        .control:focus {

            border-color:var(--gold);
            background:white;
            box-shadow:
                0 0 0 3px
                rgba(201,154,62,.10);

        }


        textarea.control {

            min-height:90px;
            resize:vertical;

        }


        /* DROP AREA */

        .drop-area {

            position:relative;
            display:flex;
            align-items:center;
            justify-content:center;
            min-height:230px;
            padding:30px;
            overflow:hidden;
            border:2px dashed #d6dce5;
            border-radius:10px;
            background:#fafbfd;
            text-align:center;
            transition:.2s;

        }


        .drop-area:hover,
        .drop-area.dragging {

            border-color:var(--gold);
            background:var(--gold-bg);

        }


        .drop-content {

            pointer-events:none;

        }


        .upload-icon {

            width:58px;
            height:58px;
            display:grid;
            place-items:center;
            margin:0 auto 13px;
            border-radius:50%;
            background:var(--navy);
            color:var(--gold-light);
            font-size:23px;

        }


        .drop-content h3 {

            color:var(--navy);
            font-family:"Playfair Display",serif;
            font-size:19px;

        }


        .drop-content p {

            margin-top:4px;
            color:var(--muted);
            font-size:9px;

        }


        .select-text {

            margin-top:9px;
            color:var(--gold);
            font-size:9px;
            font-weight:700;
            letter-spacing:.6px;
            text-transform:uppercase;

        }


        #media {

            position:absolute;
            inset:0;
            width:100%;
            height:100%;
            opacity:0;
            cursor:pointer;

        }


        /* PREVIEW */

        .preview-section {

            display:none;
            margin-top:20px;

        }


        .preview-title {

            margin-bottom:10px;
            color:var(--navy);
            font-size:9px;
            font-weight:700;
            letter-spacing:1px;
            text-transform:uppercase;

        }


        .preview-grid {

            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:8px;

        }


        .preview-item {

            position:relative;
            aspect-ratio:1;
            overflow:hidden;
            border-radius:7px;
            background:#eef2f6;

        }


        .preview-item img {

            width:100%;
            height:100%;
            object-fit:cover;

        }


        .preview-name {

            position:absolute;
            right:0;
            bottom:0;
            left:0;
            padding:5px;
            overflow:hidden;
            background:rgba(7,26,54,.75);
            color:white;
            font-size:7px;
            text-overflow:ellipsis;
            white-space:nowrap;

        }


        .upload-button {

            width:100%;
            margin-top:18px;
            padding:13px;
            border:none;
            border-radius:6px;
            background:var(--navy);
            color:white;
            cursor:pointer;
            font-size:9px;
            font-weight:700;
            letter-spacing:.8px;

        }


        .upload-button:hover {

            background:var(--blue);

        }


        /* INFO */

        .info-card {

            padding:24px;

        }


        .info-card p {

            margin-top:5px;
            color:var(--muted);
            font-size:9px;

        }


        .rules {

            margin-top:20px;
            padding-top:15px;
            border-top:1px solid var(--line);

        }


        .rules-title {

            margin-bottom:9px;
            color:var(--navy);
            font-size:9px;
            font-weight:700;
            letter-spacing:1px;
            text-transform:uppercase;

        }


        .rule {

            display:flex;
            gap:9px;
            margin-bottom:10px;
            color:var(--muted);
            font-size:8px;

        }


        .rule:last-child {

            margin-bottom:0;

        }


        .rule-number {

            min-width:20px;
            height:20px;
            display:grid;
            place-items:center;
            border:1px solid var(--gold);
            border-radius:50%;
            color:var(--gold);
            font-size:7px;
            font-weight:700;

        }


        .supported {

            margin-top:20px;
            padding:15px;
            border-radius:8px;
            background:var(--gold-bg);

        }


        .supported strong {

            display:block;
            color:var(--navy);
            font-size:9px;

        }


        .supported span {

            display:block;
            margin-top:4px;
            color:var(--muted);
            font-size:8px;

        }


        /* GALLERY */

        .gallery-card {

            margin-top:22px;
            padding:22px;

        }


        .gallery-description {

            margin-top:4px;
            color:var(--muted);
            font-size:9px;

        }


        .gallery-grid {

            display:grid;
            grid-template-columns:
                repeat(4,1fr);

            gap:12px;

            margin-top:16px;

        }


        .media-item {

            overflow:hidden;
            border:1px solid var(--line);
            border-radius:8px;
            background:#fafbfd;

        }


        .media-item img {

            display:block;
            width:100%;
            aspect-ratio:1;
            object-fit:cover;

        }


        .media-caption {

            padding:8px;

        }


        .media-caption strong {

            display:block;
            color:var(--navy);
            font-size:8px;

        }


        .media-caption span {

            display:block;
            margin-top:3px;
            color:var(--muted);
            font-size:7px;

        }


        .empty-gallery {

            margin-top:15px;
            padding:35px;
            border:1px dashed var(--line);
            border-radius:8px;
            background:#fbfcfd;
            color:var(--muted);
            font-size:9px;
            text-align:center;

        }


        @media(max-width:950px) {

            .upload-grid {

                grid-template-columns:1fr;

            }


            .gallery-grid {

                grid-template-columns:
                    repeat(3,1fr);

            }

        }


        @media(max-width:800px) {

            .sidebar {

                width:72px;
                padding:20px 8px;

            }


            .brand {

                justify-content:center;
                padding:4px 0 25px;

            }


            .brand-text,
            .nav-title {

                display:none;

            }


            .nav-link {

                justify-content:center;

            }


            .nav-link span:last-child {

                display:none;

            }


            .main {

                margin-left:72px;

            }


            .content {

                padding:30px 24px;

            }

        }


        @media(max-width:600px) {

            .topbar {

                height:68px;
                padding:0 18px;

            }


            .topbar-label {

                display:none;

            }


            .page-title {

                font-size:21px;

            }


            .user-details {

                display:none;

            }


            .content {

                padding:25px 17px;

            }


            h1 {

                font-size:31px;

            }


            .upload-card,
            .info-card,
            .gallery-card {

                padding:19px;

            }


            .preview-grid,
            .gallery-grid {

                grid-template-columns:
                    repeat(2,1fr);

            }

        }

    </style>

</head>


<body>


    <!-- SIDEBAR -->

    <aside class="sidebar">


        <a
            href="dashboard.php"
            class="brand"
        >

            <div class="brand-mark">
                E
            </div>


            <div class="brand-text">

                <strong>
                    EventSphere
                </strong>

                <small>
                    COLLEGE COMMUNITY
                </small>

            </div>

        </a>


        <nav class="nav-section">


            <div class="nav-title">
                Organizer Portal
            </div>


            <a
                href="dashboard.php"
                class="nav-link"
            >
                <span class="nav-icon">▦</span>
                <span>Dashboard</span>
            </a>


            <a
                href="create-event.php"
                class="nav-link"
            >
                <span class="nav-icon">+</span>
                <span>Create Event</span>
            </a>


            <a
                href="manage-events.php"
                class="nav-link"
            >
                <span class="nav-icon">◈</span>
                <span>Manage Events</span>
            </a>


            <!-- <a
                href="qr-scanner.php"
                class="nav-link"
            >
                <span class="nav-icon">▣</span>
                <span>QR Scanner</span>
            </a> -->


            <a
                href="media-upload.php"
                class="nav-link active"
            >
                <span class="nav-icon">▧</span>
                <span>Media Upload</span>
            </a>

<a
    href="media-manage.php"
    class="nav-link"
>
    <span class="nav-icon">◫</span>
    <span>Manage Media</span>
</a>
<a
    href="registrations.php"
    class="nav-link"
>
    <span class="nav-icon">
        ♙
    </span>

    <span>
        Registrations
    </span>
</a>
 <a
    href="attendance.php"
    class="nav-link"
>
    <span class="nav-icon">
        ♙
    </span>

    <span>
        Attendance
    </span>
</a>
 <a
        href="profile.php"
        class="nav-link active"
    >
        <span class="nav-icon">♙</span>
        <span>Profile</span>
    </a>
        <a
        href="logout.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ⎋
        </span>

        <span>
            Logout
        </span>

    </a>
        </nav>

    </aside>



    <!-- MAIN -->

    <main class="main">


        <header class="topbar">


            <div class="topbar-left">

                <span class="topbar-label">
                    Organizer Portal
                </span>

                <div class="page-title">
                    Media Upload
                </div>

            </div>


            <div class="user-area">

                <div class="user-details">

                    <strong>
                        <?= sanitize($userName) ?>
                    </strong>

                    <span>
                        Event Organizer
                    </span>

                </div>


                <div class="avatar">
                    <?= sanitize($initial) ?>
                </div>

            </div>


        </header>



        <section class="content">


            <div class="intro">

                <div class="eyebrow">
                    Event Media
                </div>

                <h1>
                    Upload Event Media
                </h1>

                <p>
                    Upload event photographs and visual
                    content to the EventSphere media gallery.
                </p>

            </div>



            <?php if (
                $successMessage !== ''
            ): ?>

                <div class="alert alert-success">

                    <strong>
                        Upload Successful
                    </strong>

                    <?= sanitize(
                        $successMessage
                    ) ?>

                </div>

            <?php endif; ?>



            <?php if (
                !empty($errors)
            ): ?>

                <div class="alert alert-error">

                    <strong>
                        Upload Failed
                    </strong>

                    <ul>

                        <?php foreach (
                            $errors as $error
                        ): ?>

                            <li>
                                <?= sanitize($error) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>



            <div class="upload-grid">


                <!-- UPLOAD FORM -->

                <div class="card upload-card">


                    <div class="card-heading">

                        <h2>
                            Add Event Media
                        </h2>

                        <p>
                            Choose an event and upload one
                            or multiple photographs.
                        </p>

                    </div>



                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= sanitize($csrfToken) ?>"
                        >


                        <div class="field">

                            <label for="event_id">

                                Event

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select
                                id="event_id"
                                name="event_id"
                                class="control"
                                required
                            >

                                <option value="">
                                    Select Event
                                </option>


                                <?php foreach (
                                    $events
                                    as $event
                                ): ?>

                                    <option
                                        value="<?= sanitize(
                                            $event['event_id']
                                        ) ?>"
                                        <?= $selectedEventId ===
                                            $event['event_id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= sanitize(
                                            $event['title']
                                        ) ?>

                                        —
                                        <?= sanitize(
                                            ucfirst(
                                                $event['approval_state']
                                                    ?? ''
                                            )
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <div class="field">

                            <label for="caption">
                                Caption
                            </label>


                            <textarea
                                id="caption"
                                name="caption"
                                class="control"
                                maxlength="2000"
                                placeholder="Optional description for the uploaded photos..."
                            ><?= sanitize($caption) ?></textarea>

                        </div>



                        <div
                            class="drop-area"
                            id="dropArea"
                        >


                            <div class="drop-content">

                                <div class="upload-icon">
                                    ▧
                                </div>


                                <h3>
                                    Choose Images
                                </h3>


                                <p>
                                    Drag and drop or click
                                    to browse your files.
                                </p>


                                <div class="select-text">
                                    SELECT IMAGES
                                </div>

                            </div>


                            <input
                                type="file"
                                id="media"
                                name="media[]"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                multiple
                                required
                            >

                        </div>



                        <!-- PREVIEW -->

                        <div
                            class="preview-section"
                            id="previewSection"
                        >

                            <div class="preview-title">
                                Selected Images
                            </div>


                            <div
                                class="preview-grid"
                                id="previewGrid"
                            ></div>

                        </div>



                        <button
                            type="submit"
                            class="upload-button"
                        >
                            UPLOAD MEDIA
                        </button>


                    </form>


                </div>



                <!-- GUIDELINES -->

                <div class="card info-card">


                    <h2>
                        Upload Guidelines
                    </h2>


                    <p>
                        Upload clear and relevant media
                        belonging to your selected event.
                    </p>



                    <div class="rules">


                        <div class="rules-title">
                            Upload Process
                        </div>


                        <div class="rule">

                            <span class="rule-number">
                                1
                            </span>

                            <span>
                                Select one of your events.
                            </span>

                        </div>


                        <div class="rule">

                            <span class="rule-number">
                                2
                            </span>

                            <span>
                                Add an optional caption.
                            </span>

                        </div>


                        <div class="rule">

                            <span class="rule-number">
                                3
                            </span>

                            <span>
                                Select the event photographs.
                            </span>

                        </div>


                        <div class="rule">

                            <span class="rule-number">
                                4
                            </span>

                            <span>
                                Upload and store the media
                                in EventSphere gallery.
                            </span>

                        </div>


                    </div>



                    <div class="supported">

                        <strong>
                            Supported Formats
                        </strong>

                        <span>
                            JPG · PNG · WEBP · GIF
                        </span>

                        <span>
                            Maximum 5 MB per image
                        </span>

                    </div>


                </div>


            </div>



            <!-- RECENT UPLOADS -->

            <?php if (
                !empty($uploadedFiles)
            ): ?>

                <div class="card gallery-card">


                    <h2>
                        Uploaded Media
                    </h2>


                    <p class="gallery-description">
                        Media successfully registered
                        in the EventSpheregallery.
                    </p>


                    <div class="gallery-grid">


                        <?php foreach (
                            $uploadedFiles
                            as $file
                        ): ?>

                            <div class="media-item">


                                <img
                                    src="<?= sanitize(
                                        $file['path']
                                    ) ?>"
                                    alt="Uploaded event media"
                                >


                                <div class="media-caption">

                                    <strong>
                                        Photo
                                    </strong>

                                    <span>
                                        <?= sanitize(
                                            $file['original_name']
                                        ) ?>
                                    </span>

                                </div>


                            </div>

                        <?php endforeach; ?>


                    </div>


                </div>

            <?php endif; ?>


        </section>


    </main>



    <script>

        const mediaInput =
            document.getElementById(
                "media"
            );

        const dropArea =
            document.getElementById(
                "dropArea"
            );

        const previewSection =
            document.getElementById(
                "previewSection"
            );

        const previewGrid =
            document.getElementById(
                "previewGrid"
            );


        /*
        |--------------------------------------------------------------------------
        | SHOW PREVIEW
        |--------------------------------------------------------------------------
        */

        function showPreview(files) {

            previewGrid.innerHTML = "";


            if (!files || !files.length) {

                previewSection.style.display =
                    "none";

                return;

            }


            previewSection.style.display =
                "block";


            Array.from(files)
                .forEach(
                    function(file) {

                        if (
                            !file.type.startsWith(
                                "image/"
                            )
                        ) {

                            return;

                        }


                        const reader =
                            new FileReader();


                        reader.onload =
                            function(event) {

                                const item =
                                    document.createElement(
                                        "div"
                                    );

                                item.className =
                                    "preview-item";


                                const image =
                                    document.createElement(
                                        "img"
                                    );

                                image.src =
                                    event.target.result;

                                image.alt =
                                    file.name;


                                const name =
                                    document.createElement(
                                        "div"
                                    );

                                name.className =
                                    "preview-name";

                                name.textContent =
                                    file.name;


                                item.appendChild(
                                    image
                                );

                                item.appendChild(
                                    name
                                );

                                previewGrid.appendChild(
                                    item
                                );

                            };


                        reader.readAsDataURL(
                            file
                        );

                    }
                );

        }


        /*
        |--------------------------------------------------------------------------
        | INPUT CHANGE
        |--------------------------------------------------------------------------
        */

        mediaInput.addEventListener(
            "change",
            function() {

                showPreview(
                    this.files
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | DRAG ENTER / OVER
        |--------------------------------------------------------------------------
        */

        [
            "dragenter",
            "dragover"
        ].forEach(
            function(eventName) {

                dropArea.addEventListener(
                    eventName,
                    function(event) {

                        event.preventDefault();

                        dropArea.classList.add(
                            "dragging"
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | DRAG LEAVE / DROP
        |--------------------------------------------------------------------------
        */

        [
            "dragleave",
            "drop"
        ].forEach(
            function(eventName) {

                dropArea.addEventListener(
                    eventName,
                    function(event) {

                        event.preventDefault();

                        dropArea.classList.remove(
                            "dragging"
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | DROP FILES
        |--------------------------------------------------------------------------
        */

        dropArea.addEventListener(
            "drop",
            function(event) {

                const files =
                    event.dataTransfer.files;


                try {

                    const dataTransfer =
                        new DataTransfer();


                    Array.from(files)
                        .forEach(
                            function(file) {

                                dataTransfer.items.add(
                                    file
                                );

                            }
                        );


                    mediaInput.files =
                        dataTransfer.files;


                    showPreview(
                        mediaInput.files
                    );

                }
                catch (error) {

                    console.error(
                        "Drop error:",
                        error
                    );

                }

            }
        );

    </script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

</body>

</html>