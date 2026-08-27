<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole('organizer');

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
| EVENT ID
|--------------------------------------------------------------------------
*/

$eventId = trim(
    $_GET['event_id'] ?? ''
);

$event = null;

$errorMessage = '';


/*
|--------------------------------------------------------------------------
| LOAD EVENT
|--------------------------------------------------------------------------
*/

if ($eventId === '') {

    $errorMessage =
        'No event was selected.';

} elseif (!$pdoConnection instanceof PDO) {

    $errorMessage =
        'Database connection is not available.';

} else {

    try {

        $stmt = $pdoConnection->prepare("
            SELECT

                e.event_id,
                e.title,
                e.subtitle,
                e.description,
                e.category,
                e.department_id,
                e.venue_id,
                e.max_seats,
                e.waitlist_capacity,
                e.start_date,
                e.end_date,
                e.approval_state,
                e.organizer_id,
                e.code_of_conduct,
                e.dress_code,
                e.required_materials,
                e.banner_image,
                e.rejection_reason,
                e.created_at,
                e.updated_at,

                v.venue_name,
                v.capacity AS venue_capacity,
                v.address AS venue_address,
                v.av_capabilities,
                v.status AS venue_status,

                (
                    SELECT COUNT(*)
                    FROM registrations r
                    WHERE r.event_id = e.event_id
                ) AS total_registrations,

                (
                    SELECT COUNT(*)
                    FROM registrations r
                    WHERE r.event_id = e.event_id
                    AND r.status = 'confirmed'
                ) AS confirmed_registrations,

                (
                    SELECT COUNT(*)
                    FROM registrations r
                    WHERE r.event_id = e.event_id
                    AND r.status = 'waitlisted'
                ) AS waitlisted_registrations

            FROM events e

            LEFT JOIN venues v
                ON v.venue_id = e.venue_id

            WHERE e.event_id = :event_id
            AND e.organizer_id = :organizer_id

            LIMIT 1
        ");

        $stmt->execute([

            ':event_id' =>
                $eventId,

            ':organizer_id' =>
                $userId

        ]);

        $event =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$event) {

            $errorMessage =
                'Event not found or you do not have permission to view it.';

        }

    } catch (PDOException $e) {

        error_log(
            'View Event Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load event details.';

    }
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function viewEventDate(
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


function viewEventTime(
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
        'h:i A',
        $timestamp
    );
}


function viewStatusClass(
    string $status
): string {

    switch (
        strtolower($status)
    ) {

        case 'approved':
            return 'status-approved';

        case 'pending':
            return 'status-pending';

        case 'rejected':
            return 'status-rejected';

        case 'completed':
            return 'status-completed';

        default:
            return 'status-draft';

    }
}


function viewStatusLabel(
    string $status
): string {

    return ucfirst(
        strtolower($status)
    );
}


function viewCategoryLabel(
    string $category
): string {

    return ucfirst(
        strtolower(
            $category
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
    Event Details | EventSphere
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

    line-height:1.6;

}


a{

    color:inherit;
    text-decoration:none;

}


/* SIDEBAR */

.sidebar{

    position:fixed;

    top:0;
    left:0;

    width:250px;
    height:100vh;

    padding:24px 16px;

    background:
        var(--navy);

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


/* TOPBAR */

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

    max-width:1150px;

    margin:auto;

    padding:
        42px 40px 60px;

}


/* ERROR */

.error-card{

    padding:
        45px 25px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:12px;

    text-align:center;

}


.error-card h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:24px;

}


.error-card p{

    margin:
        7px auto 18px;

    color:
        var(--muted);

    font-size:10px;

}


.back-button{

    display:inline-flex;

    padding:
        10px 16px;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    font-size:8px;

    font-weight:700;

}


/* INTRO */

.page-intro{

    display:flex;

    justify-content:space-between;

    align-items:flex-end;

    gap:20px;

    margin-bottom:24px;

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


.subtitle{

    max-width:700px;

    margin-top:7px;

    color:
        var(--muted);

    font-size:11px;

}


.action-buttons{

    display:flex;

    gap:8px;

}


.action-button{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        10px 14px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--navy);

    font-size:8px;

    font-weight:700;

    letter-spacing:.6px;

}


.action-button.primary{

    border-color:
        var(--navy);

    background:
        var(--navy);

    color:white;

}


.action-button:hover{

    border-color:
        var(--gold);

    color:
        var(--gold);

}


.action-button.primary:hover{

    background:
        var(--blue);

    color:white;

}


/* HERO */

.event-hero{

    position:relative;

    overflow:hidden;

    min-height:220px;

    display:flex;

    align-items:flex-end;

    padding:30px;

    margin-bottom:20px;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            #123761
        );

    border-radius:12px;

    color:white;

    box-shadow:var(--shadow);

}


.event-hero::before{

    content:"";

    position:absolute;

    right:-80px;
    top:-100px;

    width:300px;
    height:300px;

    border:
        1px solid
        rgba(229,193,111,.2);

    border-radius:50%;

}


.event-hero::after{

    content:"";

    position:absolute;

    right:45px;
    top:-50px;

    width:160px;
    height:160px;

    border:
        1px solid
        rgba(229,193,111,.15);

    border-radius:50%;

}


.hero-content{

    position:relative;

    z-index:2;

}


.hero-category{

    display:inline-flex;

    padding:
        5px 9px;

    margin-bottom:9px;

    border-radius:20px;

    background:
        rgba(229,193,111,.14);

    color:
        var(--gold-light);

    font-size:7px;

    font-weight:700;

    letter-spacing:.8px;

    text-transform:uppercase;

}


.hero-title{

    max-width:700px;

    font-family:
        "Playfair Display",
        serif;

    font-size:31px;

    line-height:1.2;

}


.hero-subtitle{

    margin-top:5px;

    color:#cbd4df;

    font-size:10px;

}


/* STATUS */

.status{

    position:absolute;

    right:25px;
    top:25px;

    z-index:3;

    padding:
        7px 10px;

    border-radius:20px;

    font-size:7px;

    font-weight:700;

    letter-spacing:.6px;

    text-transform:uppercase;

}


.status-approved{

    background:
        var(--green-bg);

    color:
        var(--green);

}


.status-pending{

    background:
        var(--gold-bg);

    color:
        #9a711d;

}


.status-rejected{

    background:
        var(--red-bg);

    color:
        var(--red);

}


.status-completed{

    background:
        var(--blue-bg);

    color:
        var(--blue);

}


.status-draft{

    background:
        #eef0f3;

    color:
        var(--muted);

}


/* STATS */

.stats{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:13px;

    margin-bottom:20px;

}


.stat{

    padding:
        17px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:9px;

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

    font-size:24px;

}


/* GRID */

.info-grid{

    display:grid;

    grid-template-columns:
        1.25fr
        .75fr;

    gap:20px;

}


.card{

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:11px;

    box-shadow:
        var(--shadow);

}


.card-header{

    padding:
        20px 21px;

    border-bottom:
        1px solid
        var(--line);

}


.card-header h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}


.card-header p{

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;

}


.card-body{

    padding:21px;

}


/* DESCRIPTION */

.description{

    color:
        var(--ink);

    font-size:10px;

    line-height:1.8;

    white-space:pre-line;

}


/* DETAIL ROWS */

.detail-row{

    display:flex;

    justify-content:space-between;

    gap:20px;

    padding:
        11px 0;

    border-bottom:
        1px solid
        #edf0f3;

}


.detail-row:last-child{

    border-bottom:none;

}


.detail-label{

    color:
        var(--muted);

    font-size:9px;

}


.detail-value{

    max-width:230px;

    color:
        var(--ink);

    font-size:9px;

    font-weight:700;

    text-align:right;

}


/* VENUE */

.venue-box{

    padding:17px;

    background:
        #fafbfd;

    border:
        1px solid
        var(--line);

    border-radius:8px;

}


.venue-name{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:17px;

}


.venue-address{

    margin-top:5px;

    color:
        var(--muted);

    font-size:9px;

}


.venue-capacity{

    margin-top:13px;

    padding-top:11px;

    border-top:
        1px solid
        var(--line);

    color:
        var(--ink);

    font-size:9px;

    font-weight:700;

}


.venue-capacity span{

    color:
        var(--muted);

    font-weight:400;

}


/* INFORMATION */

.info-section{

    margin-top:20px;

}


.info-section .card-body{

    padding:21px;

}


.text-block{

    color:
        var(--ink);

    font-size:9px;

    line-height:1.7;

    white-space:pre-line;

}


.rejection{

    margin-top:15px;

    padding:
        13px;

    background:
        var(--red-bg);

    border:
        1px solid
        #efcccc;

    border-radius:7px;

}


.rejection strong{

    display:block;

    margin-bottom:4px;

    color:
        var(--red);

    font-size:9px;

}


.rejection span{

    color:
        var(--red);

    font-size:8px;

}


/* FOOTER */

.event-footer{

    margin-top:20px;

    padding-top:17px;

    border-top:
        1px solid
        var(--line);

    color:
        var(--muted);

    font-size:8px;

}


@media(max-width:1000px){

    .stats{

        grid-template-columns:
            repeat(2,1fr);

    }


    .info-grid{

        grid-template-columns:
            1fr;

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


    .page-intro{

        align-items:flex-start;

        flex-direction:column;

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


    .event-hero{

        padding:23px;

        min-height:210px;

    }


    .hero-title{

        font-size:25px;

    }


    .status{

        position:static;

        display:inline-flex;

        margin-bottom:12px;

    }


    .stats{

        grid-template-columns:
            1fr;

    }


    .action-buttons{

        width:100%;

    }


    .action-button{

        flex:1;

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

        <span class="nav-icon">
            ▦
        </span>

        <span>
            Dashboard
        </span>

    </a>


    <a
        href="create-event.php"
        class="nav-link"
    >

        <span class="nav-icon">
            +
        </span>

        <span>
            Create Event
        </span>

    </a>


    <a
        href="manage-events.php"
        class="nav-link active"
    >

        <span class="nav-icon">
            ◈
        </span>

        <span>
            Manage Events
        </span>

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
            ✓
        </span>

        <span>
            Attendance
        </span>

    </a>


    <!-- <a
        href="qr-scanner.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▣
        </span>

        <span>
            QR Scanner
        </span>

    </a> -->


    <a
        href="media-upload.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▧
        </span>

        <span>
            Media Upload
        </span>

    </a>


    <a
        href="media-manage.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◫
        </span>

        <span>
            Manage Media
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
        href="./logout.php"
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
            Event Details
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


<?php if (!$event): ?>


    <div class="error-card">

        <h2>
            Event Not Found
        </h2>

        <p>
            <?= sanitize(
                $errorMessage
            ) ?>
        </p>

        <a
            href="manage-events.php"
            class="back-button"
        >
            BACK TO EVENTS
        </a>

    </div>


<?php else: ?>


    <!-- PAGE INTRO -->

    <div class="page-intro">


        <div>

            <div class="eyebrow">
                Event Management
            </div>

            <h1>
                Event Details
            </h1>

            <p class="subtitle">
                Complete overview of your CEventSphere event.
            </p>

        </div>


        <div class="action-buttons">


            <a
                href="manage-events.php"
                class="action-button"
            >
                BACK
            </a>


            <a
                href="edit-event.php?event_id=<?= urlencode(
                    $event['event_id']
                ) ?>"
                class="
                    action-button
                    primary
                "
            >
                EDIT EVENT
            </a>


        </div>


    </div>



    <!-- HERO -->

    <div class="event-hero">


        <div class="hero-content">


            <div class="hero-category">

                <?= sanitize(
                    viewCategoryLabel(
                        $event['category']
                    )
                ) ?>

            </div>


            <div class="hero-title">

                <?= sanitize(
                    $event['title']
                ) ?>

            </div>


            <?php if (
                !empty(
                    $event['subtitle']
                )
            ): ?>

                <div class="hero-subtitle">

                    <?= sanitize(
                        $event['subtitle']
                    ) ?>

                </div>

            <?php endif; ?>


        </div>


        <div
            class="
                status
                <?= viewStatusClass(
                    $event['approval_state']
                        ?? 'draft'
                ) ?>
            "
        >

            <?= sanitize(
                viewStatusLabel(
                    $event['approval_state']
                        ?? 'draft'
                )
            ) ?>

        </div>


    </div>



    <!-- STATS -->

    <div class="stats">


        <div class="stat">

            <div class="stat-label">
                Maximum Seats
            </div>

            <div class="stat-value">
                <?= number_format(
                    (int)(
                        $event['max_seats']
                        ?? 0
                    )
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Registrations
            </div>

            <div class="stat-value">
                <?= number_format(
                    (int)(
                        $event[
                            'total_registrations'
                        ]
                        ?? 0
                    )
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Confirmed
            </div>

            <div class="stat-value">
                <?= number_format(
                    (int)(
                        $event[
                            'confirmed_registrations'
                        ]
                        ?? 0
                    )
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Waitlisted
            </div>

            <div class="stat-value">
                <?= number_format(
                    (int)(
                        $event[
                            'waitlisted_registrations'
                        ]
                        ?? 0
                    )
                ) ?>
            </div>

        </div>


    </div>



    <!-- INFORMATION -->

    <div class="info-grid">


        <!-- LEFT -->

        <div>


            <div class="card">

                <div class="card-header">

                    <h2>
                        About This Event
                    </h2>

                    <p>
                        Event description and schedule.
                    </p>

                </div>


                <div class="card-body">


                    <?php if (
                        !empty(
                            $event['description']
                        )
                    ): ?>

                        <div class="description">

                            <?= sanitize(
                                $event['description']
                            ) ?>

                        </div>

                    <?php else: ?>

                        <div class="description">
                            No event description has been added.
                        </div>

                    <?php endif; ?>


                    <div
                        class="event-footer"
                    >

                        Created:

                        <?= sanitize(
                            viewEventDate(
                                $event['created_at']
                            )
                        ) ?>

                        &nbsp; · &nbsp;

                        Last Updated:

                        <?= sanitize(
                            viewEventDate(
                                $event['updated_at']
                            )
                        ) ?>

                    </div>


                </div>

            </div>



            <!-- ADDITIONAL -->

            <div class="card info-section">

                <div class="card-header">

                    <h2>
                        Event Guidelines
                    </h2>

                    <p>
                        Additional information for participants.
                    </p>

                </div>


                <div class="card-body">


                    <div class="detail-row">

                        <span class="detail-label">
                            Dress Code
                        </span>

                        <span class="detail-value">

                            <?= !empty(
                                $event['dress_code']
                            )
                                ? sanitize(
                                    $event['dress_code']
                                )
                                : 'Not specified' ?>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            Required Materials
                        </span>

                        <span class="detail-value">

                            <?= !empty(
                                $event['required_materials']
                            )
                                ? sanitize(
                                    $event[
                                        'required_materials'
                                    ]
                                )
                                : 'None specified' ?>

                        </span>

                    </div>


                    <div
                        class="detail-row"
                    >

                        <span class="detail-label">
                            Department
                        </span>

                        <span class="detail-value">

                            <?= !empty(
                                $event['department_id']
                            )
                                ? sanitize(
                                    $event[
                                        'department_id'
                                    ]
                                )
                                : 'General' ?>

                        </span>

                    </div>


                    <?php if (
                        !empty(
                            $event['code_of_conduct']
                        )
                    ): ?>

                        <div
                            class="info-section"
                            style="margin-top:15px;"
                        >

                            <div
                                class="text-block"
                            >

                                <?= sanitize(
                                    $event[
                                        'code_of_conduct'
                                    ]
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                </div>

            </div>


        </div>



        <!-- RIGHT -->

        <div>


            <!-- SCHEDULE -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Schedule
                    </h2>

                    <p>
                        Event timing information.
                    </p>

                </div>


                <div class="card-body">


                    <div class="detail-row">

                        <span class="detail-label">
                            Start Date
                        </span>

                        <span class="detail-value">

                            <?= sanitize(
                                viewEventDate(
                                    $event['start_date']
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            Start Time
                        </span>

                        <span class="detail-value">

                            <?= sanitize(
                                viewEventTime(
                                    $event['start_date']
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            End Date
                        </span>

                        <span class="detail-value">

                            <?= sanitize(
                                viewEventDate(
                                    $event['end_date']
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            End Time
                        </span>

                        <span class="detail-value">

                            <?= sanitize(
                                viewEventTime(
                                    $event['end_date']
                                )
                            ) ?>

                        </span>

                    </div>


                </div>

            </div>



            <!-- VENUE -->

            <div
                class="card"
                style="margin-top:20px;"
            >

                <div class="card-header">

                    <h2>
                        Venue
                    </h2>

                    <p>
                        Event location information.
                    </p>

                </div>


                <div class="card-body">


                    <?php if (
                        !empty(
                            $event['venue_name']
                        )
                    ): ?>


                        <div class="venue-box">


                            <div class="venue-name">

                                <?= sanitize(
                                    $event[
                                        'venue_name'
                                    ]
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $event['venue_address']
                                )
                            ): ?>

                                <div class="venue-address">

                                    <?= sanitize(
                                        $event[
                                            'venue_address'
                                        ]
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <div class="venue-capacity">

                                Venue Capacity:

                                <span>

                                    <?= number_format(
                                        (int)(
                                            $event[
                                                'venue_capacity'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                </span>

                            </div>


                        </div>


                    <?php else: ?>


                        <div class="venue-box">

                            <div class="venue-name">
                                Venue Not Available
                            </div>

                            <div class="venue-address">
                                No venue information is attached
                                to this event.
                            </div>

                        </div>


                    <?php endif; ?>


                </div>

            </div>



            <!-- REJECTION -->

            <?php if (
                strtolower(
                    $event['approval_state']
                        ?? ''
                ) === 'rejected' &&
                !empty(
                    $event['rejection_reason']
                )
            ): ?>


                <div
                    class="rejection"
                    style="margin-top:20px;"
                >

                    <strong>
                        Rejection Reason
                    </strong>

                    <span>

                        <?= sanitize(
                            $event[
                                'rejection_reason'
                            ]
                        ) ?>

                    </span>

                </div>


            <?php endif; ?>


        </div>


    </div>


<?php endif; ?>


</section>


</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>