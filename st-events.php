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

$userId   = (string)($user['user_id'] ?? '');
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
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$category = trim(
    $_GET['category'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALID CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [
    'technical' => 'Technical',
    'cultural'  => 'Cultural',
    'sports'    => 'Sports',
    'academic'  => 'Academic',
    'workshop'  => 'Workshop',
    'seminar'   => 'Seminar'
];


/*
|--------------------------------------------------------------------------
| EVENTS
|--------------------------------------------------------------------------
*/

$events = [];

$errorMessage = '';

if ($pdoConnection instanceof PDO) {

    try {

        $sql = "
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
                e.banner_image,
                e.created_at,

                v.venue_name,
                v.address AS venue_address,

                organizer.full_name AS organizer_name,

                r.reg_id,
                r.status AS registration_status

            FROM events e

            LEFT JOIN venues v
                ON v.venue_id = e.venue_id

            LEFT JOIN users organizer
                ON organizer.user_id = e.organizer_id

            LEFT JOIN registrations r
                ON r.event_id = e.event_id
                AND r.user_id = :user_id

            WHERE e.approval_state = 'approved'
            AND e.end_date >= NOW()
        ";

        $params = [
            ':user_id' => $userId
        ];


        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {

            $sql .= "
                AND (
                    e.title LIKE :search
                    OR e.subtitle LIKE :search
                    OR e.description LIKE :search
                    OR organizer.full_name LIKE :search
                    OR v.venue_name LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | CATEGORY
        |--------------------------------------------------------------------------
        */

        if (
            $category !== '' &&
            array_key_exists(
                $category,
                $categories
            )
        ) {

            $sql .= "
                AND e.category = :category
            ";

            $params[':category'] =
                $category;
        }


        $sql .= "
            ORDER BY
                e.start_date ASC
        ";


        $stmt =
            $pdoConnection->prepare(
                $sql
            );

        $stmt->execute(
            $params
        );

        $events =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Student Events Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load events.';
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

function studentEventsDate(
    ?string $date
): string {

    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '—';
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function studentEventsTime(
    ?string $date
): string {

    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '—';
    }

    return date(
        'h:i A',
        $timestamp
    );
}


function studentEventCardStatus(
    ?string $status
): string {

    switch (
        strtolower(
            (string)$status
        )
    ) {

        case 'confirmed':
            return 'Registered';

        case 'waitlisted':
            return 'Waitlisted';

        case 'cancelled':
            return 'Cancelled';

        case 'completed':
            return 'Completed';

        default:
            return 'Open';
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
    Browse Events | EventSphere
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
input,
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

    max-width:1400px;

    margin:
        0 auto;

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

    max-width:710px;

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


/* FILTER */

.filter-card{

    margin-bottom:22px;

    padding:18px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:10px;

    box-shadow:
        var(--shadow);

}


.filter-form{

    display:grid;

    grid-template-columns:
        1.7fr
        1fr
        auto;

    gap:10px;

    align-items:end;

}


.filter-group{

    display:flex;

    flex-direction:column;

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


.filter-control{

    width:100%;

    padding:
        11px 12px;

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


.filter-control:focus{

    border-color:
        var(--gold);

    background:white;

}


.filter-actions{

    display:flex;

    gap:7px;

}


.filter-button{

    padding:
        11px 15px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

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
        11px 15px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

}


/* GRID */

.events-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:18px;

}


/* EVENT CARD */

.event-card{

    overflow:hidden;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:11px;

    box-shadow:
        var(--shadow);

    transition:
        .25s;

}


.event-card:hover{

    transform:
        translateY(-3px);

    box-shadow:
        0 24px 55px
        rgba(7,26,54,.11);

}


.event-banner{

    position:relative;

    height:190px;

    overflow:hidden;

    display:flex;

    align-items:flex-end;

    padding:16px;

    background:
        linear-gradient(
            135deg,
            #071a36,
            #123761
        );

}


.event-banner.has-image{

    background-size:cover;

    background-position:center;

}


.event-banner-overlay{

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            to top,
            rgba(7,26,54,.82),
            rgba(7,26,54,.08)
        );

}


.event-category{

    position:absolute;

    top:12px;
    left:12px;

    z-index:2;

    padding:
        5px 8px;

    border-radius:20px;

    background:
        rgba(255,255,255,.93);

    color:
        var(--navy);

    font-size:6px;

    font-weight:700;

    letter-spacing:.6px;

    text-transform:uppercase;

}


.event-date{

    position:relative;

    z-index:2;

    color:white;

}


.event-date-day{

    font-family:
        "Playfair Display",
        serif;

    font-size:25px;

    line-height:1;
}


.event-date-month{

    margin-top:3px;

    color:
        var(--gold-light);

    font-size:8px;

    font-weight:700;

    letter-spacing:1px;

    text-transform:uppercase;

}


.event-body{

    padding:18px;

}


.event-title{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

    line-height:1.25;

}


.event-subtitle{

    min-height:32px;

    margin-top:6px;

    color:
        var(--muted);

    font-size:9px;

    line-height:1.5;

}


.event-meta{

    margin-top:13px;

    padding-top:12px;

    border-top:
        1px solid
        var(--line);

}


.meta-row{

    display:flex;

    justify-content:space-between;

    gap:10px;

    padding:6px 0;

}


.meta-label{

    color:
        var(--muted);

    font-size:7px;

}


.meta-value{

    max-width:180px;

    overflow:hidden;

    color:
        var(--ink);

    font-size:8px;

    font-weight:600;

    text-align:right;

    text-overflow:ellipsis;

    white-space:nowrap;

}


.card-footer{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    margin-top:15px;

}


.details-button{

    flex:1;

    padding:
        10px 12px;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    text-align:center;

    font-size:8px;

    font-weight:700;

    letter-spacing:.6px;

}


.details-button:hover{

    background:
        var(--blue);

}


.registration-state{

    display:inline-flex;

    padding:
        7px 9px;

    border-radius:6px;

    font-size:6px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:.4px;

}


.state-registered{

    background:
        var(--green-bg);

    color:
        var(--green);

}


.state-waitlisted{

    background:
        var(--gold-bg);

    color:
        #9a711d;

}


.state-cancelled{

    background:
        var(--red-bg);

    color:
        var(--red);

}


.state-completed{

    background:
        var(--blue-bg);

    color:
        var(--blue);

}


.state-open{

    background:
        #eef0f3;

    color:
        var(--muted);

}


/* EMPTY */

.empty{

    grid-column:
        1 / -1;

    padding:
        70px 25px;

    background:white;

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

    .events-grid{

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media(max-width:900px){

    .filter-form{

        grid-template-columns:
            1fr 1fr;

    }

    .filter-actions{

        grid-column:
            1 / -1;

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

    .intro{

        align-items:flex-start;

        flex-direction:column;

    }

}


@media(max-width:650px){

    .events-grid{

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

    .filter-form{

        grid-template-columns:
            1fr;

    }

    .filter-actions{

        grid-column:auto;

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

        <span class="nav-icon">
            ▦
        </span>

        <span>
            Dashboard
        </span>

    </a>


    <a
        href="events.php"
        class="nav-link active"
    >

        <span class="nav-icon">
            ◈
        </span>

        <span>
            Browse Events
        </span>

    </a>


    <a
        href="my-registrations.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ♙
        </span>

        <span>
            My Registrations
        </span>

    </a>


    <a
        href="my-tickets.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▣
        </span>

        <span>
            My Tickets
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


    <a
        href="media.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▧
        </span>

        <span>
            Campus Media
        </span>

    </a>


    <a
        href="../../logout.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ↪
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
            Student Portal
        </span>

        <div class="page-title">
            Browse Events
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
            Campus Calendar
        </div>


        <h1>
            Discover Events
        </h1>


        <p>
            Explore approved campus events, workshops,
            seminars, competitions and student activities.
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


<!-- FILTERS -->

<div class="filter-card">


<form
    method="GET"
    action=""
    class="filter-form"
>


    <div class="filter-group">

        <label for="search">
            Search Events
        </label>

        <input
            type="text"
            id="search"
            name="search"
            class="filter-control"
            value="<?= sanitize(
                $search
            ) ?>"
            placeholder="Event, organizer, venue..."
        >

    </div>


    <div class="filter-group">

        <label for="category">
            Category
        </label>

        <select
            id="category"
            name="category"
            class="filter-control"
        >

            <option value="">
                All Categories
            </option>


            <?php foreach (
                $categories
                as $value => $label
            ): ?>

                <option
                    value="<?= sanitize(
                        $value
                    ) ?>"
                    <?= $category === $value
                        ? 'selected'
                        : '' ?>
                >

                    <?= sanitize(
                        $label
                    ) ?>

                </option>

            <?php endforeach; ?>


        </select>

    </div>


    <div class="filter-actions">


        <button
            type="submit"
            class="filter-button"
        >
            SEARCH
        </button>


        <a
            href="events.php"
            class="clear-button"
        >
            CLEAR
        </a>


    </div>


</form>


</div>


<!-- EVENTS -->

<div class="events-grid">


<?php if (
    !empty($events)
): ?>


    <?php foreach (
        $events
        as $event
    ): ?>


        <?php

        $eventTimestamp =
            strtotime(
                $event['start_date']
            );

        $day =
            $eventTimestamp
                ? date(
                    'd',
                    $eventTimestamp
                )
                : '—';

        $month =
            $eventTimestamp
                ? date(
                    'M Y',
                    $eventTimestamp
                )
                : '—';


        $status =
            $event[
                'registration_status'
            ]
            ?? null;


        $bannerStyle = '';

        if (
            !empty(
                $event['banner_image']
            )
        ) {

            $bannerUrl =
                trim(
                    (string)(
                        $event['banner_image']
                    )
                );

            $bannerStyle =
                "background-image:url('" .
                htmlspecialchars(
                    $bannerUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                "');";

        }

        ?>


        <article class="event-card">


            <div
                class="
                    event-banner
                    <?= $bannerStyle !== ''
                        ? 'has-image'
                        : '' ?>
                "
                <?= $bannerStyle !== ''
                    ? 'style="' .
                        $bannerStyle .
                        '"'
                    : '' ?>
            >


                <div class="event-banner-overlay"></div>


                <span class="event-category">

                    <?= sanitize(
                        ucfirst(
                            strtolower(
                                $event['category']
                            )
                        )
                    ) ?>

                </span>


                <div class="event-date">

                    <div class="event-date-day">

                        <?= sanitize(
                            $day
                        ) ?>

                    </div>


                    <div class="event-date-month">

                        <?= sanitize(
                            $month
                        ) ?>

                    </div>

                </div>


            </div>


            <div class="event-body">


                <h2 class="event-title">

                    <?= sanitize(
                        $event['title']
                    ) ?>

                </h2>


                <p class="event-subtitle">

                    <?= !empty(
                        $event['subtitle']
                    )
                        ? sanitize(
                            $event['subtitle']
                        )
                        : (
                            !empty(
                                $event['description']
                            )
                                ? sanitize(
                                    mb_substr(
                                        $event[
                                            'description'
                                        ],
                                        0,
                                        120
                                    )
                                ) . '...'
                                : 'Campus360 event'
                        ) ?>

                </p>


                <div class="event-meta">


                    <div class="meta-row">

                        <span class="meta-label">
                            Time
                        </span>

                        <span class="meta-value">

                            <?= sanitize(
                                studentEventsTime(
                                    $event[
                                        'start_date'
                                    ]
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="meta-row">

                        <span class="meta-label">
                            Venue
                        </span>

                        <span class="meta-value">

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


                    <div class="meta-row">

                        <span class="meta-label">
                            Organizer
                        </span>

                        <span class="meta-value">

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


                </div>


                <div class="card-footer">


                    <a
                        href="st-event-details.php?event_id=<?= urlencode(
                            $event[
                                'event_id'
                            ]
                        ) ?>"
                        class="details-button"
                    >
                        VIEW DETAILS
                    </a>


                    <?php if (
                        $status !== null
                    ): ?>


                        <span
                            class="
                                registration-state
                                state-<?=
                                    $status === 'confirmed'
                                        ? 'registered'
                                        : (
                                            $status === 'waitlisted'
                                                ? 'waitlisted'
                                                : (
                                                    $status === 'cancelled'
                                                        ? 'cancelled'
                                                        : 'completed'
                                                )
                                        )
                                ?>
                            "
                        >

                            <?= sanitize(
                                studentEventCardStatus(
                                    $status
                                )
                            ) ?>

                        </span>


                    <?php else: ?>


                        <span
                            class="
                                registration-state
                                state-open
                            "
                        >
                            OPEN
                        </span>


                    <?php endif; ?>


                </div>


            </div>


        </article>


    <?php endforeach; ?>


<?php else: ?>


    <div class="empty">

        <strong>
            No events found.
        </strong>

        <br>

        Try changing your search or category filter.

    </div>


<?php endif; ?>


</div>


</section>


</main>


<?php require_once __DIR__ . '/footer.php'; ?>


</body>

</html>
