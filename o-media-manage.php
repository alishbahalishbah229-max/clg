<?php

require_once __DIR__ . '/database.php';
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
| DATABASE
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
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['media_manage_token'])) {

    $_SESSION['media_manage_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken =
    $_SESSION['media_manage_token'];


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['media_success'] ?? '';

$errorMessage =
    $_SESSION['media_error'] ?? '';

unset(
    $_SESSION['media_success'],
    $_SESSION['media_error']
);


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !$postedToken ||
        !hash_equals(
            $_SESSION['media_manage_token'],
            $postedToken
        )
    ) {

        $_SESSION['media_error'] =
            'Invalid security token. Please try again.';

        header(
            'Location: media-manage.php'
        );

        exit;
    }


    $action =
        $_POST['action'] ?? '';

    $mediaId =
        trim(
            $_POST['media_id'] ?? ''
        );


    if (
        $mediaId === ''
    ) {

        $_SESSION['media_error'] =
            'Invalid media item.';

        header(
            'Location: media-manage.php'
        );

        exit;
    }


    if (!$pdoConnection instanceof PDO) {

        $_SESSION['media_error'] =
            'Database connection is not available.';

        header(
            'Location: media-manage.php'
        );

        exit;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | VERIFY MEDIA OWNERSHIP
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT
                    mg.media_id,
                    mg.event_id,
                    mg.file_url,
                    mg.is_published,
                    e.title
                FROM media_gallery mg

                INNER JOIN events e
                    ON e.event_id = mg.event_id

                WHERE mg.media_id = :media_id
                AND e.organizer_id = :organizer_id

                LIMIT 1
            ");

        $stmt->execute([

            ':media_id' =>
                $mediaId,

            ':organizer_id' =>
                $userId

        ]);

        $media =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$media) {

            $_SESSION['media_error'] =
                'Media not found or you do not have permission to manage it.';

            header(
                'Location: media-manage.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            $fileUrl =
                $media['file_url'] ?? '';

            $filePath =
                __DIR__ .
                '/../../' .
                ltrim(
                    $fileUrl,
                    './'
                );


            $deleteStmt =
                $pdoConnection->prepare("
                    DELETE FROM media_gallery
                    WHERE media_id = :media_id
                ");

            $deleteStmt->execute([
                ':media_id' =>
                    $mediaId
            ]);


            if (
                $deleteStmt->rowCount() > 0
            ) {

                /*
                |--------------------------------------------------------------------------
                | DELETE FILE
                |--------------------------------------------------------------------------
                */

                if (
                    is_file($filePath)
                ) {

                    @unlink(
                        $filePath
                    );

                }


                $_SESSION['media_success'] =
                    'Media deleted successfully.';

            } else {

                $_SESSION['media_error'] =
                    'Media could not be deleted.';

            }


            header(
                'Location: media-manage.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | PUBLISH / UNPUBLISH
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'publish' ||
            $action === 'unpublish'
        ) {

            $publishValue =
                $action === 'publish'
                    ? 1
                    : 0;


            $updateStmt =
                $pdoConnection->prepare("
                    UPDATE media_gallery

                    SET
                        is_published = :is_published

                    WHERE media_id = :media_id
                ");


            $updateStmt->execute([

                ':is_published' =>
                    $publishValue,

                ':media_id' =>
                    $mediaId

            ]);


            if ($action === 'publish') {

                $_SESSION['media_success'] =
                    'Media published successfully.';

            } else {

                $_SESSION['media_success'] =
                    'Media unpublished successfully.';

            }


            header(
                'Location: media-manage.php'
            );

            exit;
        }


        $_SESSION['media_error'] =
            'Unknown media action.';


    }

    catch (PDOException $e) {

        error_log(
            'Media Management Error: ' .
            $e->getMessage()
        );

        $_SESSION['media_error'] =
            'Unable to process the media action.';

    }


    header(
        'Location: media-manage.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD MEDIA
|--------------------------------------------------------------------------
*/

$mediaItems = [];

$loadError = '';

if ($pdoConnection instanceof PDO) {

    try {

        $stmt =
            $pdoConnection->prepare("
                SELECT
                    mg.media_id,
                    mg.event_id,
                    mg.media_type,
                    mg.file_url,
                    mg.caption,
                    mg.is_published,
                    mg.created_at,
                    e.title AS event_title

                FROM media_gallery mg

                INNER JOIN events e
                    ON e.event_id = mg.event_id

                WHERE e.organizer_id = :organizer_id

                ORDER BY
                    mg.created_at DESC
            ");

        $stmt->execute([
            ':organizer_id' =>
                $userId
        ]);

        $mediaItems =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    }

    catch (PDOException $e) {

        error_log(
            'Media Load Error: ' .
            $e->getMessage()
        );

        $loadError =
            'Unable to load your media gallery.';

    }

} else {

    $loadError =
        'Database connection is not available.';
}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalMedia =
    count($mediaItems);

$publishedMedia = 0;
$pendingMedia = 0;


foreach ($mediaItems as $item) {

    if (
        (int)$item['is_published'] === 1
    ) {

        $publishedMedia++;

    } else {

        $pendingMedia++;

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
    Media Management | EventSphere
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

:root{

    --navy:#071a36;
    --blue:#123761;
    --gold:#c99a3e;
    --gold-light:#e5c16f;

    --cream:#f5f7fa;
    --white:#fff;
    --ink:#172338;
    --muted:#697386;

    --line:#e4e8ee;

    --green:#2f8f5b;
    --green-bg:#edf8f1;

    --red:#b33a3a;
    --red-bg:#fff0f0;

    --gold-bg:#fff8e9;

    --shadow:
        0 18px 50px
        rgba(7,26,54,.07);
}


*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}


body{

    font-family:"DM Sans",sans-serif;

    background:var(--cream);

    color:var(--ink);

}


a{

    color:inherit;
    text-decoration:none;

}


button{
    font-family:inherit;
}


/* SIDEBAR */

.sidebar{

    position:fixed;

    top:0;
    left:0;

    width:250px;
    height:100vh;

    padding:24px 16px;

    background:var(--navy);

    color:white;

}


.brand{

    display:flex;

    align-items:center;

    gap:12px;

    padding:
        4px 12px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.1);

}


.brand-mark{

    width:42px;
    height:48px;

    display:grid;

    place-items:center;

    background:#06152c;

    border:
        2px solid
        var(--gold);

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


.brand-text strong{

    display:block;

    font-family:
        "Playfair Display",
        serif;

    font-size:17px;

    letter-spacing:1px;

}


.brand-text small{

    display:block;

    color:var(--gold-light);

    font-size:7px;

    letter-spacing:2px;

}


.nav-section{

    margin-top:30px;

}


.nav-title{

    padding:
        0 12px 10px;

    color:#718198;

    font-size:9px;

    font-weight:700;

    letter-spacing:1.7px;

    text-transform:uppercase;

}


.nav-link{

    display:flex;

    align-items:center;

    gap:12px;

    margin-bottom:5px;

    padding:12px;

    border-radius:7px;

    color:#b8c4d3;

    font-size:12px;

}


.nav-link:hover{

    background:
        rgba(255,255,255,.07);

    color:white;

}


.nav-link.active{

    background:
        rgba(255,255,255,.09);

    color:white;

    border-left:
        3px solid
        var(--gold);

    padding-left:9px;

}


.nav-icon{

    width:25px;
    height:25px;

    display:grid;

    place-items:center;

}


/* MAIN */

.main{

    min-height:100vh;

    margin-left:250px;

}


.topbar{

    height:76px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 38px;

    background:white;

    border-bottom:
        1px solid
        var(--line);

}


.topbar-left{

    display:flex;

    flex-direction:column;

}


.topbar-label{

    color:var(--gold);

    font-size:9px;

    font-weight:700;

    letter-spacing:1.7px;

    text-transform:uppercase;

}


.page-title{

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:25px;

}


.user-area{

    display:flex;

    align-items:center;

    gap:12px;

}


.user-details{

    text-align:right;

}


.user-details strong{

    display:block;

    font-size:12px;

}


.user-details span{

    display:block;

    color:var(--muted);

    font-size:9px;

}


.avatar{

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

.content{

    max-width:1250px;

    margin:auto;

    padding:
        42px 40px 60px;

}


.intro{

    display:flex;

    justify-content:space-between;

    align-items:flex-end;

    gap:20px;

    margin-bottom:25px;

}


.eyebrow{

    margin-bottom:7px;

    color:var(--gold);

    font-size:10px;

    font-weight:700;

    letter-spacing:2px;

    text-transform:uppercase;

}


h1{

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:38px;

    line-height:1.15;

}


.intro p{

    margin-top:8px;

    color:var(--muted);

    font-size:12px;

}


.upload-btn{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:
        12px 18px;

    background:var(--navy);

    color:white;

    border-radius:6px;

    font-size:9px;

    font-weight:700;

    letter-spacing:.7px;

}


.upload-btn:hover{

    background:var(--blue);

}


/* ALERTS */

.alert{

    margin-bottom:18px;

    padding:
        13px 16px;

    border-radius:7px;

    font-size:10px;

    font-weight:600;

}


.alert-success{

    color:var(--green);

    background:var(--green-bg);

    border:
        1px solid
        #ccebd8;

}


.alert-error{

    color:var(--red);

    background:var(--red-bg);

    border:
        1px solid
        #efcccc;

}


/* SUMMARY */

.summary{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:15px;

    margin-bottom:22px;

}


.summary-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:18px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:10px;

    box-shadow:var(--shadow);

}


.summary-label{

    color:var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.8px;

    text-transform:uppercase;

}


.summary-value{

    margin-top:6px;

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:25px;

}


.summary-icon{

    width:35px;
    height:35px;

    display:grid;

    place-items:center;

    border-radius:8px;

    background:var(--gold-bg);

    color:var(--gold);

}


/* GALLERY */

.gallery-card{

    overflow:hidden;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:12px;

    box-shadow:var(--shadow);

}


.gallery-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:
        21px 22px;

    border-bottom:
        1px solid
        var(--line);

}


.gallery-header h2{

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}


.gallery-header p{

    margin-top:3px;

    color:var(--muted);

    font-size:9px;

}


.media-grid{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:15px;

    padding:22px;

}


.media-card{

    overflow:hidden;

    border:
        1px solid
        var(--line);

    border-radius:9px;

    background:#fbfcfd;

}


.media-image{

    position:relative;

    aspect-ratio:1;

    overflow:hidden;

    background:#eef2f6;

}


.media-image img{

    width:100%;
    height:100%;

    display:block;

    object-fit:cover;

}


.publish-badge{

    position:absolute;

    top:8px;
    right:8px;

    padding:
        5px 7px;

    border-radius:20px;

    font-size:7px;

    font-weight:700;

    letter-spacing:.5px;

    text-transform:uppercase;

}


.published{

    background:var(--green-bg);
    color:var(--green);

}


.unpublished{

    background:var(--gold-bg);
    color:#9a711d;

}


.media-body{

    padding:12px;

}


.media-event{

    color:var(--navy);

    font-size:10px;

    font-weight:700;

}


.media-caption{

    margin-top:4px;

    color:var(--muted);

    font-size:8px;

    line-height:1.4;

}


.media-date{

    margin-top:7px;

    color:#8b95a6;

    font-size:7px;

}


.media-actions{

    display:flex;

    gap:6px;

    margin-top:11px;

}


.media-action{

    flex:1;

    padding:7px 5px;

    border:
        1px solid
        var(--line);

    border-radius:5px;

    background:white;

    color:var(--navy);

    cursor:pointer;

    font-size:7px;

    font-weight:700;

    letter-spacing:.4px;

}


.media-action:hover{

    border-color:var(--gold);

    color:var(--gold);

}


.media-action.delete{

    color:var(--red);

}


.media-action.delete:hover{

    border-color:var(--red);

    background:var(--red-bg);

}


/* EMPTY */

.empty{

    padding:
        65px 25px;

    text-align:center;

}


.empty-icon{

    width:62px;
    height:62px;

    display:grid;

    place-items:center;

    margin:
        0 auto 15px;

    border-radius:50%;

    background:var(--gold-bg);

    color:var(--gold);

    font-size:24px;

}


.empty h3{

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}


.empty p{

    max-width:380px;

    margin:
        7px auto 17px;

    color:var(--muted);

    font-size:10px;

}


.empty a{

    display:inline-flex;

    padding:
        10px 15px;

    border-radius:6px;

    background:var(--navy);

    color:white;

    font-size:8px;

    font-weight:700;

}


@media(max-width:1100px){

    .media-grid{

        grid-template-columns:
            repeat(3,1fr);

    }

}


@media(max-width:800px){

    .sidebar{

        width:72px;

        padding:
            20px 8px;

    }


    .brand{

        justify-content:center;

    }


    .brand-text,
    .nav-title{

        display:none;

    }


    .nav-link{

        justify-content:center;

    }


    .nav-link span:last-child{

        display:none;

    }


    .main{

        margin-left:72px;

    }


    .content{

        padding:
            30px 24px;

    }


    .intro{

        align-items:flex-start;

        flex-direction:column;

    }


    .summary{

        grid-template-columns:
            1fr;

    }

}


@media(max-width:600px){

    .topbar{

        height:68px;

        padding:
            0 18px;

    }


    .topbar-label,
    .user-details{

        display:none;

    }


    .page-title{

        font-size:21px;

    }


    .content{

        padding:
            25px 17px;

    }


    h1{

        font-size:31px;

    }


    .media-grid{

        grid-template-columns:
            repeat(2,1fr);

        padding:15px;

    }

}

</style>

</head>


<body>


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


<main class="main">


<header class="topbar">

    <div class="topbar-left">

        <span class="topbar-label">
            Organizer Portal
        </span>

        <div class="page-title">
            Media Management
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

        <div>

            <div class="eyebrow">
                Event Media
            </div>

            <h1>
                Media Management
            </h1>

            <p>
                Manage photos uploaded to your EventSphere
                events and control their publication status.
            </p>

        </div>


        <a
            href="media-upload.php"
            class="upload-btn"
        >
            +
            UPLOAD MEDIA
        </a>

    </div>


    <?php if ($successMessage !== ''): ?>

        <div class="alert alert-success">
            <?= sanitize($successMessage) ?>
        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-error">
            <?= sanitize($errorMessage) ?>
        </div>

    <?php endif; ?>


    <?php if ($loadError !== ''): ?>

        <div class="alert alert-error">
            <?= sanitize($loadError) ?>
        </div>

    <?php endif; ?>


    <div class="summary">


        <div class="summary-card">

            <div>

                <div class="summary-label">
                    Total Media
                </div>

                <div class="summary-value">
                    <?= number_format($totalMedia) ?>
                </div>

            </div>

            <div class="summary-icon">
                ▧
            </div>

        </div>


        <div class="summary-card">

            <div>

                <div class="summary-label">
                    Published
                </div>

                <div class="summary-value">
                    <?= number_format($publishedMedia) ?>
                </div>

            </div>

            <div class="summary-icon">
                ✓
            </div>

        </div>


        <div class="summary-card">

            <div>

                <div class="summary-label">
                    Unpublished
                </div>

                <div class="summary-value">
                    <?= number_format($pendingMedia) ?>
                </div>

            </div>

            <div class="summary-icon">
                ◷
            </div>

        </div>


    </div>


    <div class="gallery-card">


        <div class="gallery-header">

            <div>

                <h2>
                    Your Media Gallery
                </h2>

                <p>
                    Photos uploaded by your organizer account.
                </p>

            </div>

        </div>


        <?php if (!empty($mediaItems)): ?>


            <div class="media-grid">


                <?php foreach ($mediaItems as $item): ?>


                    <?php

                    $isPublished =
                        (int)(
                            $item['is_published'] ?? 0
                        ) === 1;

                    ?>


                    <div class="media-card">


                        <div class="media-image">


                            <img
                                src="<?= sanitize(
                                    $item['file_url']
                                ) ?>"
                                alt="Event media"
                                loading="lazy"
                            >


                            <span
                                class="
                                    publish-badge
                                    <?= $isPublished
                                        ? 'published'
                                        : 'unpublished' ?>
                                "
                            >

                                <?= $isPublished
                                    ? 'Published'
                                    : 'Unpublished' ?>

                            </span>


                        </div>


                        <div class="media-body">


                            <div class="media-event">

                                <?= sanitize(
                                    $item['event_title']
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $item['caption']
                                )
                            ): ?>

                                <div class="media-caption">

                                    <?= sanitize(
                                        $item['caption']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <div class="media-date">

                                <?= sanitize(
                                    date(
                                        'd M Y, h:i A',
                                        strtotime(
                                            $item['created_at']
                                        )
                                    )
                                ) ?>

                            </div>


                            <div class="media-actions">


                                <form
                                    method="POST"
                                    style="flex:1"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= sanitize(
                                            $csrfToken
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="media_id"
                                        value="<?= sanitize(
                                            $item['media_id']
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="<?= $isPublished
                                            ? 'unpublish'
                                            : 'publish' ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="media-action"
                                    >

                                        <?= $isPublished
                                            ? 'UNPUBLISH'
                                            : 'PUBLISH' ?>

                                    </button>

                                </form>


                                <form
                                    method="POST"
                                    style="flex:1"
                                    onsubmit="
                                        return confirm(
                                            'Delete this media permanently?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= sanitize(
                                            $csrfToken
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="media_id"
                                        value="<?= sanitize(
                                            $item['media_id']
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            media-action
                                            delete
                                        "
                                    >
                                        DELETE
                                    </button>

                                </form>


                            </div>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="empty">


                <div class="empty-icon">
                    ▧
                </div>


                <h3>
                    No Media Yet
                </h3>


                <p>
                    You haven't uploaded any event photos.
                    Upload your first event media to begin
                    building your gallery.
                </p>


                <a
                    href="media-upload.php"
                >
                    UPLOAD MEDIA
                </a>


            </div>


        <?php endif; ?>


    </div>


</section>


</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
