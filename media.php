<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole('student');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

if (!$user) {
    header('Location: ../../login.php');
    exit;
}

$userName = $user['full_name'] ?? 'Student';

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

} elseif (
    isset($db) &&
    $db instanceof PDO
) {

    $pdoConnection = $db;

}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$mediaFilter = strtolower(
    trim(
        $_GET['type'] ?? ''
    )
);

$allowedTypes = [
    '',
    'photo',
    'video'
];

if (
    !in_array(
        $mediaFilter,
        $allowedTypes,
        true
    )
) {

    $mediaFilter = '';

}


/*
|--------------------------------------------------------------------------
| MEDIA
|--------------------------------------------------------------------------
*/

$mediaItems = [];

$errorMessage = '';

$totalMedia = 0;
$totalPhotos = 0;
$totalVideos = 0;


if (
    $pdoConnection instanceof PDO
) {

    try {

        $sql = "
            SELECT

                m.media_id,
                m.event_id,
                m.media_type,
                m.file_url,
                m.caption,
                m.is_published,
                m.uploaded_by,
                m.created_at,

                e.title AS event_title,

                u.full_name AS uploader_name

            FROM media_gallery m

            LEFT JOIN events e
                ON e.event_id = m.event_id

            LEFT JOIN users u
                ON u.user_id = m.uploaded_by

            WHERE m.is_published = 1
        ";

        $params = [];


        /*
        |--------------------------------------------------------------------------
        | MEDIA TYPE FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $mediaFilter !== ''
        ) {

            $sql .= "
                AND m.media_type = :media_type
            ";

            $params[':media_type'] =
                $mediaFilter;

        }


        $sql .= "
            ORDER BY
                m.created_at DESC
        ";


        $stmt =
            $pdoConnection->prepare(
                $sql
            );

        $stmt->execute(
            $params
        );


        $mediaItems =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | COUNTS
        |--------------------------------------------------------------------------
        */

        $totalMedia =
            count($mediaItems);


        foreach (
            $mediaItems as $media
        ) {

            if (
                strtolower(
                    (string)(
                        $media['media_type']
                        ?? ''
                    )
                ) === 'photo'
            ) {

                $totalPhotos++;

            } elseif (
                strtolower(
                    (string)(
                        $media['media_type']
                        ?? ''
                    )
                ) === 'video'
            ) {

                $totalVideos++;

            }

        }


    } catch (PDOException $e) {

        error_log(
            'Student Media Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load campus media.';

    }

} else {

    $errorMessage =
        'Database connection is not available.';

}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function studentMediaDate(
    ?string $date
): string {

    if (!$date) {
        return '—';
    }

    $timestamp =
        strtotime($date);

    if (!$timestamp) {
        return '—';
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function studentMediaTypeClass(
    string $type
): string {

    return strtolower(
        $type
    ) === 'video'
        ? 'type-video'
        : 'type-photo';

}


function studentMediaTypeLabel(
    string $type
): string {

    return ucfirst(
        strtolower(
            $type
        )
    );

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
    Campus Media | EventSphere
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
    --white:#ffffff;

    --ink:#172338;
    --muted:#697386;

    --line:#e4e8ee;

    --green:#2f8f5b;
    --green-bg:#edf8f1;

    --red:#b33a3a;
    --red-bg:#fff0f0;

    --gold-bg:#fff8e9;
    --blue-bg:#eef4fb;

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

    font-family:
        "DM Sans",
        sans-serif;

    background:
        var(--cream);

    color:
        var(--ink);

}


a{

    color:inherit;

    text-decoration:none;

}


button,
select{

    font-family:inherit;

}


/* SIDEBAR */

.sidebar{

    position:fixed;

    top:0;

    left:0;

    width:255px;

    height:100vh;

    padding:
        24px 16px;

    background:
        var(--navy);

    color:#fff;

    z-index:100;

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

    color:
        var(--gold-light);

    font-family:
        Georgia,
        serif;

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

    margin-top:2px;

    color:
        var(--gold-light);

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

    font-size:11px;

    transition:.2s;

}


.nav-link:hover{

    background:
        rgba(255,255,255,.07);

    color:#fff;

}


.nav-link.active{

    background:
        rgba(255,255,255,.09);

    color:#fff;

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

    font-size:13px;

}


/* MAIN */

.main{

    min-height:100vh;

    margin-left:255px;

}


/* TOPBAR */

.topbar{

    height:76px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        0 38px;

    background:#fff;

    border-bottom:
        1px solid
        var(--line);

}


.topbar-left{

    display:flex;

    flex-direction:column;

}


.topbar-label{

    color:
        var(--gold);

    font-size:9px;

    font-weight:700;

    letter-spacing:1.7px;

    text-transform:uppercase;

}


.page-title{

    color:
        var(--navy);

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

    color:
        var(--muted);

    font-size:9px;

}


.logout-link{

    display:inline-block;

    margin-top:4px;

    color:
        var(--red);

    font-size:8px;

    font-weight:700;

    text-transform:uppercase;

}


.avatar{

    width:42px;

    height:42px;

    display:grid;

    place-items:center;

    border-radius:50%;

    background:
        var(--navy);

    color:
        var(--gold-light);

    font-size:14px;

    font-weight:700;

}


/* CONTENT */

.content{

    max-width:1400px;

    margin:0 auto;

    padding:
        42px 40px 20px;

}


.intro{

    display:flex;

    align-items:flex-end;

    justify-content:space-between;

    gap:20px;

    margin-bottom:25px;

}


.eyebrow{

    margin-bottom:7px;

    color:
        var(--gold);

    font-size:10px;

    font-weight:700;

    letter-spacing:2px;

    text-transform:uppercase;

}


h1{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:38px;

    line-height:1.15;

}


.intro p{

    max-width:720px;

    margin-top:8px;

    color:
        var(--muted);

    font-size:12px;

}


/* ALERT */

.alert{

    margin-bottom:20px;

    padding:
        13px 16px;

    border-radius:7px;

    background:
        var(--red-bg);

    border:
        1px solid
        #efcccc;

    color:
        var(--red);

    font-size:10px;

}


/* STATS */

.stats{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:14px;

    margin-bottom:22px;

}


.stat-card{

    padding:18px;

    background:#fff;

    border:
        1px solid
        var(--line);

    border-radius:10px;

    box-shadow:
        var(--shadow);

}


.stat-label{

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.8px;

    text-transform:uppercase;

}


.stat-value{

    margin-top:5px;

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:25px;

}


/* FILTER */

.filter-card{

    margin-bottom:20px;

    padding:17px;

    background:#fff;

    border:
        1px solid
        var(--line);

    border-radius:10px;

    box-shadow:
        var(--shadow);

}


.filter-form{

    display:flex;

    align-items:end;

    gap:10px;

}


.filter-group{

    display:flex;

    flex-direction:column;

    min-width:240px;

}


.filter-group label{

    margin-bottom:6px;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.8px;

    text-transform:uppercase;

}


.filter-select{

    padding:
        10px 11px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    outline:none;

    background:
        #fbfcfd;

    color:
        var(--ink);

    font-size:10px;

}


.filter-select:focus{

    border-color:
        var(--gold);

    background:#fff;

}


.filter-button{

    padding:
        10px 14px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:#fff;

    cursor:pointer;

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

}


.clear-button{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        10px 14px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:#fff;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

}


/* MEDIA GRID */

.media-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:18px;

}


.media-card{

    overflow:hidden;

    background:#fff;

    border:
        1px solid
        var(--line);

    border-radius:11px;

    box-shadow:
        var(--shadow);

    transition:.25s;

}


.media-card:hover{

    transform:
        translateY(-3px);

    box-shadow:
        0 24px 55px
        rgba(7,26,54,.11);

}


/* PREVIEW */

.media-preview{

    position:relative;

    height:220px;

    overflow:hidden;

    background:
        linear-gradient(
            135deg,
            #071a36,
            #123761
        );

}


.media-preview img{

    width:100%;

    height:100%;

    display:block;

    object-fit:cover;

}


.media-preview video{

    width:100%;

    height:100%;

    display:block;

    object-fit:cover;

    background:#071a36;

}


.media-type{

    position:absolute;

    top:12px;

    left:12px;

    z-index:2;

    padding:
        5px 8px;

    border-radius:20px;

    background:
        rgba(255,255,255,.94);

    font-size:6px;

    font-weight:700;

    letter-spacing:.6px;

    text-transform:uppercase;

}


.type-photo{

    color:
        var(--blue);

}


.type-video{

    color:
        var(--gold);

}


.media-body{

    padding:17px;

}


.media-caption{

    min-height:32px;

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;

    line-height:1.5;

}


.media-event{

    margin-top:8px;

    color:
        var(--muted);

    font-size:8px;

}


.media-event strong{

    color:
        var(--ink);

}


.media-date{

    margin-top:5px;

    color:#8a94a4;

    font-size:7px;

}


.media-footer{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    margin-top:13px;

    padding-top:11px;

    border-top:
        1px solid
        var(--line);

}


.view-media{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        8px 10px;

    border-radius:5px;

    background:
        var(--navy);

    color:#fff;

    font-size:6px;

    font-weight:700;

    letter-spacing:.5px;

}


.view-media:hover{

    background:
        var(--blue);

}


/* EMPTY */

.empty{

    grid-column:
        1 / -1;

    padding:
        70px 25px;

    background:#fff;

    border:
        1px solid
        var(--line);

    border-radius:11px;

    color:
        var(--muted);

    text-align:center;

    font-size:10px;

}


/* RESPONSIVE */

@media(max-width:1100px){

    .media-grid{

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media(max-width:850px){

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
            30px 24px 20px;

    }


    .intro{

        align-items:flex-start;

        flex-direction:column;

    }


    .filter-form{

        flex-wrap:wrap;

    }

}


@media(max-width:650px){

    .media-grid{

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
            25px 17px 15px;

    }


    h1{

        font-size:31px;

    }


    .stats{

        grid-template-columns:
            1fr;

    }


    .filter-form{

        flex-direction:column;

        align-items:stretch;

    }


    .filter-group{

        width:100%;

        min-width:0;

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
        Student Portal
    </div>


    <a
        href="dashboard.php"
        class="nav-link"
    >
        <span class="nav-icon">▦</span>
        <span>Dashboard</span>
    </a>


    <a
        href="events.php"
        class="nav-link"
    >
        <span class="nav-icon">◈</span>
        <span>Browse Events</span>
    </a>


    <a
        href="my-registrations.php"
        class="nav-link"
    >
        <span class="nav-icon">♙</span>
        <span>My Registrations</span>
    </a>


    <a
        href="my-tickets.php"
        class="nav-link"
    >
        <span class="nav-icon">▣</span>
        <span>My Tickets</span>
    </a>


    <a
        href="attendance.php"
        class="nav-link"
    >
        <span class="nav-icon">✓</span>
        <span>Attendance</span>
    </a>


    <a
        href="media.php"
        class="nav-link active"
    >
        <span class="nav-icon">▧</span>
        <span>Campus Media</span>
    </a>


    <a
        href="../../logout.php"
        class="nav-link"
    >
        <span class="nav-icon">↪</span>
        <span>Logout</span>
    </a>


</nav>


</aside>


<!-- MAIN -->

<main class="main">


<header class="topbar">


<div class="topbar-left">

    <span class="topbar-label">
        Student Portal
    </span>


    <div class="page-title">
        Campus Media
    </div>

</div>


<div class="user-area">


<div class="user-details">

    <strong>
        <?= sanitize(
            $userName
        ) ?>
    </strong>


    <span>
        Student
    </span>


    <a
        href="../../logout.php"
        class="logout-link"
    >
        Logout
    </a>

</div>


<div class="avatar">

    <?= sanitize(
        $initial
    ) ?>

</div>


</div>


</header>


<section class="content">


<div class="intro">


<div>

    <div class="eyebrow">
        Campus Moments
    </div>


    <h1>
        Campus Media
    </h1>


    <p>
        Explore published photos and videos from
        EventSphere events and campus activities.
    </p>

</div>


</div>


<?php if (
    $errorMessage !== ''
): ?>

<div class="alert">

    <?= sanitize(
        $errorMessage
    ) ?>

</div>

<?php endif; ?>


<!-- STATS -->

<div class="stats">


<div class="stat-card">

    <div class="stat-label">
        Published Media
    </div>

    <div class="stat-value">
        <?= number_format(
            $totalMedia
        ) ?>
    </div>

</div>


<div class="stat-card">

    <div class="stat-label">
        Photos
    </div>

    <div class="stat-value">
        <?= number_format(
            $totalPhotos
        ) ?>
    </div>

</div>


<div class="stat-card">

    <div class="stat-label">
        Videos
    </div>

    <div class="stat-value">
        <?= number_format(
            $totalVideos
        ) ?>
    </div>

</div>


</div>


<!-- FILTER -->

<div class="filter-card">


<form
    method="GET"
    action=""
    class="filter-form"
>


<div class="filter-group">

    <label for="type">
        Media Type
    </label>


    <select
        id="type"
        name="type"
        class="filter-select"
    >

        <option value="">
            All Media
        </option>


        <option
            value="photo"
            <?= $mediaFilter === 'photo'
                ? 'selected'
                : '' ?>
        >
            Photos
        </option>


        <option
            value="video"
            <?= $mediaFilter === 'video'
                ? 'selected'
                : '' ?>
        >
            Videos
        </option>

    </select>

</div>


<button
    type="submit"
    class="filter-button"
>
    FILTER
</button>


<a
    href="media.php"
    class="clear-button"
>
    CLEAR
</a>


</form>


</div>


<!-- MEDIA GRID -->

<div class="media-grid">


<?php if (
    !empty($mediaItems)
): ?>


<?php foreach (
    $mediaItems
    as $media
): ?>


<?php

$mediaType =
    strtolower(
        (string)(
            $media['media_type']
            ?? 'photo'
        )
    );

$fileUrl =
    trim(
        (string)(
            $media['file_url']
            ?? ''
        )
    );

$caption =
    trim(
        (string)(
            $media['caption']
            ?? ''
        )
    );

?>


<article class="media-card">


<div class="media-preview">


<span
    class="
        media-type
        <?= studentMediaTypeClass(
            $mediaType
        ) ?>
    "
>

    <?= sanitize(
        studentMediaTypeLabel(
            $mediaType
        )
    ) ?>

</span>


<?php if (
    $mediaType === 'video'
): ?>


    <video
        controls
        preload="metadata"
    >

        <source
            src="<?= sanitize(
                $fileUrl
            ) ?>"
        >

        Your browser does not support video playback.

    </video>


<?php else: ?>


    <img
        src="<?= sanitize(
            $fileUrl
        ) ?>"
        alt="<?= sanitize(
            $caption !== ''
                ? $caption
                : 'Campus360 photo'
        ) ?>"
        loading="lazy"
    >


<?php endif; ?>


</div>


<div class="media-body">


<div class="media-caption">

    <?= $caption !== ''
        ? sanitize(
            $caption
        )
        : 'Campus360 campus media' ?>

</div>


<div class="media-event">

    Event:

    <strong>

        <?= !empty(
            $media['event_title']
        )
            ? sanitize(
                $media['event_title']
            )
            : 'Campus Gallery' ?>

    </strong>

</div>


<div class="media-date">

    Published
    <?= sanitize(
        studentMediaDate(
            $media['created_at']
        )
    ) ?>

</div>


<div class="media-footer">

    <span
        style="
            color:#697386;
            font-size:7px;
        "
    >

        <?= !empty(
            $media['uploader_name']
        )
            ? 'By ' . sanitize(
                $media[
                    'uploader_name'
                ]
            )
            : 'Campus360' ?>

    </span>


    <a
        href="<?= sanitize(
            $fileUrl
        ) ?>"
        target="_blank"
        rel="noopener noreferrer"
        class="view-media"
    >
        OPEN
    </a>

</div>


</div>


</article>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    No published campus media is available yet.

    <br><br>

    New photos and videos will appear here after
    they are published by the organizer.

</div>


<?php endif; ?>


</div>


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>
