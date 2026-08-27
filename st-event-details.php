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
    header('Location: /login.php');
    exit;
}

$userId = (string)($user['user_id'] ?? '');
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

$registration = null;

$errorMessage = '';


/*
|--------------------------------------------------------------------------
| LOAD EVENT
|--------------------------------------------------------------------------
*/

if (
    $eventId !== '' &&
    $pdoConnection instanceof PDO
) {

    try {

        $stmt =
            $pdoConnection->prepare("
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

                    v.venue_name,
                    v.capacity AS venue_capacity,
                    v.address AS venue_address,
                    v.av_capabilities,

                    organizer.full_name AS organizer_name,
                    organizer.email AS organizer_email,

                    d.dept_name

                FROM events e

                LEFT JOIN venues v
                    ON v.venue_id = e.venue_id

                LEFT JOIN users organizer
                    ON organizer.user_id = e.organizer_id

                LEFT JOIN departments d
                    ON d.dept_id = e.department_id

                WHERE e.event_id = :event_id

                LIMIT 1
            ");

        $stmt->execute([
            ':event_id' => $eventId
        ]);

        $event =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | STUDENT REGISTRATION
        |--------------------------------------------------------------------------
        */

        if ($event) {

            $regStmt =
                $pdoConnection->prepare("
                    SELECT

                        reg_id,
                        status,
                        qr_hash,
                        queue_position,
                        registered_at

                    FROM registrations

                    WHERE user_id = :user_id
                    AND event_id = :event_id

                    ORDER BY registered_at DESC

                    LIMIT 1
                ");

            $regStmt->execute([

                ':user_id' =>
                    $userId,

                ':event_id' =>
                    $eventId

            ]);

            $registration =
                $regStmt->fetch(
                    PDO::FETCH_ASSOC
                );
        }

    } catch (PDOException $e) {

        error_log(
            'Student Event Details Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load event details.';
    }

} elseif (
    $eventId === ''
) {

    $errorMessage =
        'No event was selected.';

} else {

    $errorMessage =
        'Database connection is not available.';
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function studentDetailDate(
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


function studentDetailTime(
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


function studentDetailDateTime(
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
        'd M Y, h:i A',
        $timestamp
    );
}


function studentRegistrationClass(
    ?string $status
): string {

    switch (
        strtolower(
            (string)$status
        )
    ) {

        case 'confirmed':
            return 'confirmed';

        case 'waitlisted':
            return 'waitlisted';

        case 'cancelled':
            return 'cancelled';

        case 'completed':
            return 'completed';

        default:
            return 'none';
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
    --white:#fff;

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

button{

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

    color:white;

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

    max-width:1250px;

    margin:
        0 auto;

    padding:
        40px 40px 20px;
}


/* BACK */

.back-link{

    display:inline-flex;

    align-items:center;

    gap:6px;

    margin-bottom:17px;

    color:
        var(--muted);

    font-size:9px;

    font-weight:700;

}

.back-link:hover{

    color:
        var(--gold);
}


/* HERO */

.event-hero{

    position:relative;

    min-height:340px;

    overflow:hidden;

    display:flex;

    align-items:flex-end;

    padding:30px;

    border-radius:14px;

    background:
        linear-gradient(
            135deg,
            #071a36,
            #123761
        );

    box-shadow:
        var(--shadow);

}


.event-hero.has-image{

    background-size:cover;

    background-position:center;

}


.hero-overlay{

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            to top,
            rgba(7,26,54,.92),
            rgba(7,26,54,.15)
        );

}


.hero-content{

    position:relative;

    z-index:2;

    max-width:800px;

    color:white;
}


.category-badge{

    display:inline-flex;

    padding:
        6px 9px;

    border-radius:20px;

    background:
        rgba(255,255,255,.94);

    color:
        var(--navy);

    font-size:6px;

    font-weight:700;

    letter-spacing:.8px;

    text-transform:uppercase;
}


.hero-content h1{

    margin-top:13px;

    color:white;

    font-family:
        "Playfair Display",
        serif;

    font-size:42px;

    line-height:1.12;
}


.hero-subtitle{

    margin-top:7px;

    color:
        #d8e0eb;

    font-size:11px;
}


.hero-meta{

    display:flex;

    flex-wrap:wrap;

    gap:15px;

    margin-top:17px;

    color:
        #cbd6e3;

    font-size:8px;

}


.hero-meta strong{

    color:
        var(--gold-light);
}


/* GRID */

.details-grid{

    display:grid;

    grid-template-columns:
        1.4fr
        .6fr;

    gap:20px;

    margin-top:20px;
}


/* CARD */

.card{

    overflow:hidden;

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
        19px 21px;

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
        #536074;

    font-size:10px;

    line-height:1.8;

    white-space:pre-line;
}


/* INFO ROWS */

.info-row{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:20px;

    padding:
        11px 0;

    border-bottom:
        1px solid
        #edf0f3;
}


.info-row:last-child{

    border-bottom:none;
}


.info-label{

    color:
        var(--muted);

    font-size:8px;

}


.info-value{

    max-width:260px;

    color:
        var(--ink);

    font-size:9px;

    font-weight:600;

    text-align:right;
}


/* REGISTRATION CARD */

.registration-card{

    padding:21px;

}


.registration-status{

    margin-bottom:17px;

    padding:
        14px;

    border-radius:8px;

    background:
        #f8fafc;

    text-align:center;
}


.registration-status strong{

    display:block;

    color:
        var(--navy);

    font-size:11px;
}


.registration-status span{

    display:block;

    margin-top:4px;

    color:
        var(--muted);

    font-size:8px;
}


.register-button{

    width:100%;

    padding:
        12px 14px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    cursor:pointer;

    font-size:8px;

    font-weight:700;

    letter-spacing:.8px;
}


.register-button:hover{

    background:
        var(--blue);
}


.registered-button{

    width:100%;

    padding:
        12px 14px;

    border:
        1px solid
        #ccebd8;

    border-radius:6px;

    background:
        var(--green-bg);

    color:
        var(--green);

    text-align:center;

    font-size:8px;

    font-weight:700;
}


.waitlist-button{

    width:100%;

    padding:
        12px 14px;

    border:
        1px solid
        #ead7a7;

    border-radius:6px;

    background:
        var(--gold-bg);

    color:
        #9a711d;

    text-align:center;

    font-size:8px;

    font-weight:700;
}


.cancelled-button{

    width:100%;

    padding:
        12px 14px;

    border:
        1px solid
        #efcccc;

    border-radius:6px;

    background:
        var(--red-bg);

    color:
        var(--red);

    text-align:center;

    font-size:8px;

    font-weight:700;
}


.registration-note{

    margin-top:12px;

    color:
        var(--muted);

    font-size:7px;

    line-height:1.5;

    text-align:center;
}


/* SECTIONS */

.section-block{

    margin-top:20px;
}


.section-title{

    margin-bottom:10px;

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:18px;
}


.simple-text{

    color:
        var(--muted);

    font-size:9px;

    line-height:1.7;

}


/* ERROR */

.error-card{

    padding:60px 30px;

    background:white;

    border:
        1px solid var(--line);

    border-radius:12px;

    text-align:center;

    box-shadow:
        var(--shadow);
}


.error-card h1{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:30px;
}


.error-card p{

    margin-top:8px;

    color:
        var(--muted);

    font-size:10px;
}


.error-button{

    display:inline-flex;

    margin-top:18px;

    padding:
        10px 15px;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    font-size:8px;

    font-weight:700;
}


/* RESPONSIVE */

@media(max-width:950px){

    .details-grid{

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
            30px 24px 20px;
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

    .event-hero{

        min-height:300px;

        padding:22px;
    }

    .hero-content h1{

        font-size:31px;
    }

    .hero-meta{

        gap:9px;
    }

}

</style>

</head>


<body>


<!-- SIDEBAR -->

<aside class="sidebar">

<a
    href="student-dashboard.php"
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
        class="nav-link active"
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
        class="nav-link"
    >
        <span class="nav-icon">▧</span>
        <span>Campus Media</span>
    </a>


    <a
        href="logout.php"
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
            Event Details
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


<a
    href="events.php"
    class="back-link"
>
    ← Back to Events
</a>


<?php if (
    $errorMessage !== ''
    || !$event
): ?>


    <div class="error-card">

        <h1>
            Event Not Found
        </h1>

        <p>

            <?= sanitize(
                $errorMessage !== ''
                    ? $errorMessage
                    : 'The selected event could not be found.'
            ) ?>

        </p>


        <a
            href="events.php"
            class="error-button"
        >
            BACK TO EVENTS
        </a>

    </div>


<?php else: ?>


    <?php

    $heroStyle = '';

    if (
        !empty(
            $event['banner_image']
        )
    ) {

        $heroStyle =
            "background-image:url('" .
            htmlspecialchars(
                $event['banner_image'],
                ENT_QUOTES,
                'UTF-8'
            ) .
            "');";
    }


    $registrationStatus =
        $registration['status']
        ?? null;

    ?>


    <!-- HERO -->

    <div
        class="
            event-hero
            <?= $heroStyle !== ''
                ? 'has-image'
                : '' ?>
        "
        <?= $heroStyle !== ''
            ? 'style="' .
                $heroStyle .
                '"'
            : '' ?>
    >


        <div class="hero-overlay"></div>


        <div class="hero-content">


            <span class="category-badge">

                <?= sanitize(
                    ucfirst(
                        strtolower(
                            $event['category']
                        )
                    )
                ) ?>

            </span>


            <h1>

                <?= sanitize(
                    $event['title']
                ) ?>

            </h1>


            <?php if (
                !empty(
                    $event['subtitle']
                )
            ): ?>

                <p class="hero-subtitle">

                    <?= sanitize(
                        $event['subtitle']
                    ) ?>

                </p>

            <?php endif; ?>


            <div class="hero-meta">

                <span>

                    <strong>
                        Date
                    </strong>

                    <?= sanitize(
                        studentDetailDate(
                            $event['start_date']
                        )
                    ) ?>

                </span>


                <span>

                    <strong>
                        Starts
                    </strong>

                    <?= sanitize(
                        studentDetailTime(
                            $event['start_date']
                        )
                    ) ?>

                </span>


                <span>

                    <strong>
                        Ends
                    </strong>

                    <?= sanitize(
                        studentDetailTime(
                            $event['end_date']
                        )
                    ) ?>

                </span>


                <span>

                    <strong>
                        Venue
                    </strong>

                    <?= !empty(
                        $event['venue_name']
                    )
                        ? sanitize(
                            $event['venue_name']
                        )
                        : 'TBA' ?>

                </span>

            </div>


        </div>


    </div>


    <!-- DETAILS GRID -->

    <div class="details-grid">


        <!-- LEFT -->

        <div>


            <div class="card">


                <div class="card-header">

                    <h2>
                        About This Event
                    </h2>

                    <p>
                        Event information
                    </p>

                </div>


                <div class="card-body">


                    <div class="description">

                        <?= !empty(
                            $event[
                                'description'
                            ]
                        )
                            ? sanitize(
                                $event[
                                    'description'
                                ]
                            )
                            : 'No description has been provided for this event.' ?>

                    </div>


                </div>


            </div>


            <!-- EVENT INFORMATION -->

            <div
                class="card section-block"
            >


                <div class="card-header">

                    <h2>
                        Event Information
                    </h2>

                    <p>
                        Schedule and campus details
                    </p>

                </div>


                <div class="card-body">


                    <div class="info-row">

                        <span class="info-label">
                            Date
                        </span>

                        <span class="info-value">

                            <?= sanitize(
                                studentDetailDate(
                                    $event[
                                        'start_date'
                                    ]
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Time
                        </span>

                        <span class="info-value">

                            <?= sanitize(
                                studentDetailTime(
                                    $event[
                                        'start_date'
                                    ]
                                )
                            ) ?>

                            —

                            <?= sanitize(
                                studentDetailTime(
                                    $event[
                                        'end_date'
                                    ]
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Venue
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'venue_name'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'venue_name'
                                    ]
                                )
                                : 'Venue TBA' ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Address
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'venue_address'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'venue_address'
                                    ]
                                )
                                : '—' ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Organizer
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'organizer_name'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'organizer_name'
                                    ]
                                )
                                : 'Campus360' ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Department
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'dept_name'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'dept_name'
                                    ]
                                )
                                : '—' ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Maximum Seats
                        </span>

                        <span class="info-value">

                            <?= number_format(
                                (int)(
                                    $event[
                                        'max_seats'
                                    ]
                                    ?? 0
                                )
                            ) ?>

                        </span>

                    </div>


                </div>


            </div>


            <!-- CODE OF CONDUCT -->

            <?php if (
                !empty(
                    $event[
                        'code_of_conduct'
                    ]
                )
            ): ?>


                <div
                    class="card section-block"
                >

                    <div class="card-header">

                        <h2>
                            Code of Conduct
                        </h2>

                    </div>


                    <div class="card-body">

                        <p class="simple-text">

                            <?= nl2br(
                                sanitize(
                                    $event[
                                        'code_of_conduct'
                                    ]
                                )
                            ) ?>

                        </p>

                    </div>

                </div>


            <?php endif; ?>


            <!-- DRESS CODE -->

            <?php if (
                !empty(
                    $event[
                        'dress_code'
                    ]
                )
            ): ?>


                <div
                    class="card section-block"
                >

                    <div class="card-header">

                        <h2>
                            Dress Code
                        </h2>

                    </div>


                    <div class="card-body">

                        <p class="simple-text">

                            <?= sanitize(
                                $event[
                                    'dress_code'
                                ]
                            ) ?>

                        </p>

                    </div>

                </div>


            <?php endif; ?>


            <!-- REQUIRED MATERIALS -->

            <?php if (
                !empty(
                    $event[
                        'required_materials'
                    ]
                )
            ): ?>


                <div
                    class="card section-block"
                >

                    <div class="card-header">

                        <h2>
                            Required Materials
                        </h2>

                    </div>


                    <div class="card-body">

                        <p class="simple-text">

                            <?= nl2br(
                                sanitize(
                                    $event[
                                        'required_materials'
                                    ]
                                )
                            ) ?>

                        </p>

                    </div>

                </div>


            <?php endif; ?>


        </div>


        <!-- RIGHT -->

        <div>


            <!-- REGISTRATION -->

            <div class="card">


                <div class="card-header">

                    <h2>
                        Registration
                    </h2>

                    <p>
                        Your event registration status
                    </p>

                </div>


                <div class="registration-card">


                    <?php if (
                        $registrationStatus === null
                    ): ?>


                        <div class="registration-status">

                            <strong>
                                Registration Available
                            </strong>

                            <span>
                                You are not registered for this event.
                            </span>

                        </div>


                        <a
                            href="register-event.php?event_id=<?= urlencode(
                                $event['event_id']
                            ) ?>"
                            class="register-button"
                        >
                            REGISTER FOR EVENT
                        </a>


                        <div class="registration-note">

                            Registration will be subject to available
                            seats and event rules.

                        </div>


                    <?php elseif (
                        $registrationStatus === 'confirmed'
                    ): ?>


                        <div
                            class="registration-status"
                            style="
                                background:#edf8f1;
                            "
                        >

                            <strong
                                style="
                                    color:#2f8f5b;
                                "
                            >
                                Registration Confirmed
                            </strong>

                            <span>
                                You are registered for this event.
                            </span>

                        </div>


                        <div class="registered-button">

                            ✓ REGISTERED

                        </div>


                        <?php if (
                            !empty(
                                $registration[
                                    'qr_hash'
                                ]
                            )
                        ): ?>

                            <a
                                href="my-tickets.php"
                                class="register-button"
                                style="
                                    display:block;
                                    margin-top:9px;
                                    text-align:center;
                                "
                            >
                                VIEW MY TICKET
                            </a>

                        <?php endif; ?>


                    <?php elseif (
                        $registrationStatus === 'waitlisted'
                    ): ?>


                        <div
                            class="registration-status"
                            style="
                                background:#fff8e9;
                            "
                        >

                            <strong
                                style="
                                    color:#9a711d;
                                "
                            >
                                Waitlisted
                            </strong>

                            <span>
                                Your registration is currently on the waitlist.
                            </span>

                        </div>


                        <div class="waitlist-button">

                            WAITLISTED

                        </div>


                    <?php elseif (
                        $registrationStatus === 'cancelled'
                    ): ?>


                        <div
                            class="registration-status"
                            style="
                                background:#fff0f0;
                            "
                        >

                            <strong
                                style="
                                    color:#b33a3a;
                                "
                            >
                                Registration Cancelled
                            </strong>

                            <span>
                                Your previous registration was cancelled.
                            </span>

                        </div>


                        <div class="cancelled-button">

                            CANCELLED

                        </div>


                        <a
                            href="register-event.php?event_id=<?= urlencode(
                                $event['event_id']
                            ) ?>"
                            class="register-button"
                            style="
                                display:block;
                                margin-top:9px;
                                text-align:center;
                            "
                        >
                            REGISTER AGAIN
                        </a>


                    <?php elseif (
                        $registrationStatus === 'completed'
                    ): ?>


                        <div
                            class="registration-status"
                            style="
                                background:#eef4fb;
                            "
                        >

                            <strong
                                style="
                                    color:#123761;
                                "
                            >
                                Event Completed
                            </strong>

                            <span>
                                Your registration was completed.
                            </span>

                        </div>


                        <div class="registered-button">

                            COMPLETED

                        </div>


                    <?php endif; ?>


                </div>


            </div>


            <!-- VENUE -->

            <div
                class="card section-block"
            >


                <div class="card-header">

                    <h2>
                        Venue
                    </h2>

                    <p>
                        Location information
                    </p>

                </div>


                <div class="card-body">


                    <div class="info-row">

                        <span class="info-label">
                            Venue
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'venue_name'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'venue_name'
                                    ]
                                )
                                : 'TBA' ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Capacity
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'venue_capacity'
                                ]
                            )
                                ? number_format(
                                    (int)(
                                        $event[
                                            'venue_capacity'
                                        ]
                                    )
                                )
                                : '—' ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Facilities
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'av_capabilities'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'av_capabilities'
                                    ]
                                )
                                : '—' ?>

                        </span>

                    </div>


                </div>


            </div>


            <!-- ORGANIZER -->

            <div
                class="card section-block"
            >


                <div class="card-header">

                    <h2>
                        Organizer
                    </h2>

                    <p>
                        Event contact
                    </p>

                </div>


                <div class="card-body">


                    <div class="info-row">

                        <span class="info-label">
                            Name
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'organizer_name'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'organizer_name'
                                    ]
                                )
                                : 'Campus360' ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Email
                        </span>

                        <span class="info-value">

                            <?= !empty(
                                $event[
                                    'organizer_email'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'organizer_email'
                                    ]
                                )
                                : '—' ?>

                        </span>

                    </div>


                </div>


            </div>


        </div>


    </div>


<?php endif; ?>


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>