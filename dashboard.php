<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('student');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

if (!$user) {
    header('Location: ../../login.php');
    exit;
}

$userId = (string)($user['user_id'] ?? '');
$userName = $user['full_name'] ?? 'Student';
$userEmail = $user['email'] ?? '';
$userDept = $user['dept_id'] ?? '';
$userRoll = $user['roll_number'] ?? '';

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
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$stats = [

    'registrations' => 0,
    'confirmed'    => 0,
    'waitlisted'   => 0,
    'attended'     => 0

];

$upcomingEvents = [];
$myRegistrations = [];

$errorMessage = '';


/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

if ($pdoConnection instanceof PDO) {

    try {

        /*
        |--------------------------------------------------------------------------
        | REGISTRATION COUNT
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT COUNT(*)
                FROM registrations
                WHERE user_id = :user_id
            ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $stats['registrations'] =
            (int)(
                $stmt->fetchColumn() ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | CONFIRMED
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT COUNT(*)
                FROM registrations
                WHERE user_id = :user_id
                AND status = 'confirmed'
            ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $stats['confirmed'] =
            (int)(
                $stmt->fetchColumn() ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | WAITLISTED
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT COUNT(*)
                FROM registrations
                WHERE user_id = :user_id
                AND status = 'waitlisted'
            ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $stats['waitlisted'] =
            (int)(
                $stmt->fetchColumn() ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT COUNT(*)

                FROM attendance a

                INNER JOIN registrations r
                    ON r.reg_id = a.reg_id

                WHERE r.user_id = :user_id
                AND a.verification_status = 'present'
            ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $stats['attended'] =
            (int)(
                $stmt->fetchColumn() ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | UPCOMING APPROVED EVENTS
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT

                    e.event_id,
                    e.title,
                    e.subtitle,
                    e.category,
                    e.start_date,
                    e.end_date,
                    e.max_seats,

                    v.venue_name,

                    r.reg_id,
                    r.status AS registration_status

                FROM registrations r

                INNER JOIN events e
                    ON e.event_id = r.event_id

                LEFT JOIN venues v
                    ON v.venue_id = e.venue_id

                WHERE r.user_id = :user_id

                AND e.approval_state = 'approved'

                AND e.start_date >= NOW()

                AND r.status IN (
                    'confirmed',
                    'waitlisted'
                )

                ORDER BY
                    e.start_date ASC

                LIMIT 5
            ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $upcomingEvents =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | MY RECENT REGISTRATIONS
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT

                    r.reg_id,
                    r.status,
                    r.registered_at,
                    r.qr_hash,

                    e.title,
                    e.category,
                    e.start_date,
                    e.approval_state

                FROM registrations r

                INNER JOIN events e
                    ON e.event_id = r.event_id

                WHERE r.user_id = :user_id

                ORDER BY
                    r.registered_at DESC

                LIMIT 6
            ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $myRegistrations =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


    } catch (PDOException $e) {

        error_log(
            'Student Dashboard Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Some dashboard information could not be loaded.';
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

function studentStatusClass(
    string $status
): string {

    switch (strtolower($status)) {

        case 'confirmed':
            return 'status-confirmed';

        case 'waitlisted':
            return 'status-waitlisted';

        case 'completed':
            return 'status-completed';

        case 'cancelled':
            return 'status-cancelled';

        default:
            return 'status-default';
    }
}


function studentEventDate(
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


function studentEventTime(
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
    Student Dashboard | EventSphere
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
    --navy-light:#102c52;
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


button,
input{

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

    color:
        var(--ink);

    font-size:12px;
}


.user-details span{

    display:block;

    margin-top:1px;

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

    max-width:700px;

    margin-top:8px;

    color:
        var(--muted);

    font-size:12px;
}


.profile-mini{

    padding:
        14px 18px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:9px;

    box-shadow:
        var(--shadow);

    text-align:right;
}


.profile-mini strong{

    display:block;

    color:
        var(--navy);

    font-size:10px;
}


.profile-mini span{

    display:block;

    margin-top:2px;

    color:
        var(--muted);

    font-size:8px;
}


/* ALERT */

.alert{

    margin-bottom:20px;

    padding:
        13px 16px;

    border-radius:7px;

    background:
        var(--gold-bg);

    border:
        1px solid #ead7a7;

    color:
        #8f6b18;

    font-size:10px;
}


/* STATS */

.stat-grid{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:22px;
}


.stat-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:18px;

    background:white;

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

    line-height:1;
}


.stat-icon{

    width:36px;
    height:36px;

    display:grid;

    place-items:center;

    border-radius:8px;

    background:
        var(--gold-bg);

    color:
        var(--gold);

    font-size:15px;
}


/* GRID */

.dashboard-grid{

    display:grid;

    grid-template-columns:
        1.35fr
        .65fr;

    gap:20px;
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

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

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


.card-link{

    color:
        var(--gold);

    font-size:8px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:.6px;
}


/* EVENT CARDS */

.event-list{

    padding:15px 20px 20px;
}


.event-item{

    display:grid;

    grid-template-columns:
        60px
        1fr
        auto;

    gap:14px;

    align-items:center;

    padding:
        13px 0;

    border-bottom:
        1px solid
        #edf0f3;
}


.event-item:last-child{

    border-bottom:none;
}


.event-date-box{

    padding:9px 5px;

    border:
        1px solid
        var(--line);

    border-radius:7px;

    background:
        #fbfcfd;

    text-align:center;
}


.event-day{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:18px;

    line-height:1;
}


.event-month{

    margin-top:2px;

    color:
        var(--gold);

    font-size:6px;

    font-weight:700;

    letter-spacing:.7px;

    text-transform:uppercase;
}


.event-title{

    max-width:330px;

    overflow:hidden;

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;
}


.event-meta{

    display:flex;

    flex-wrap:wrap;

    gap:8px;

    margin-top:4px;

    color:
        var(--muted);

    font-size:7px;
}


.event-action{

    display:inline-flex;

    padding:
        7px 9px;

    border:
        1px solid
        var(--line);

    border-radius:5px;

    color:
        var(--navy);

    font-size:6px;

    font-weight:700;

}


.event-action:hover{

    border-color:
        var(--gold);

    color:
        var(--gold);
}


/* STATUS */

.status{

    display:inline-flex;

    padding:
        5px 8px;

    border-radius:20px;

    font-size:6px;

    font-weight:700;

    letter-spacing:.5px;

    text-transform:uppercase;
}


.status-confirmed{

    background:
        var(--green-bg);

    color:
        var(--green);
}


.status-waitlisted{

    background:
        var(--gold-bg);

    color:
        #9a711d;
}


.status-completed{

    background:
        var(--blue-bg);

    color:
        var(--blue);
}


.status-cancelled{

    background:
        var(--red-bg);

    color:
        var(--red);
}


.status-default{

    background:
        #eef0f3;

    color:
        var(--muted);
}


/* REGISTRATION TABLE */

.table-wrapper{

    width:100%;

    overflow-x:auto;
}


.registration-table{

    width:100%;

    min-width:700px;

    border-collapse:collapse;
}


.registration-table th{

    padding:
        11px 14px;

    background:#fafbfd;

    border-bottom:
        1px solid
        var(--line);

    color:
        var(--muted);

    font-size:7px;

    font-weight:700;

    letter-spacing:.7px;

    text-align:left;

    text-transform:uppercase;
}


.registration-table td{

    padding:
        13px 14px;

    border-bottom:
        1px solid
        #edf0f3;

    font-size:8px;

    vertical-align:middle;
}


.registration-table tbody tr:hover{

    background:#fcfdff;
}


.registration-title{

    max-width:260px;

    overflow:hidden;

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;
}


.registration-date{

    color:
        var(--muted);

    font-size:7px;
}


.qr-state{

    font-size:7px;

    font-weight:700;
}


.qr-ready{

    color:
        var(--green);
}


.qr-not-ready{

    color:
        var(--muted);
}


/* RIGHT SIDE */

.info-card{

    padding:20px;
}


.profile-box{

    display:flex;

    align-items:center;

    gap:12px;

    padding-bottom:17px;

    border-bottom:
        1px solid
        var(--line);
}


.profile-avatar{

    width:48px;
    height:48px;

    display:grid;

    place-items:center;

    border-radius:50%;

    background:
        var(--navy);

    color:
        var(--gold-light);

    font-size:16px;

    font-weight:700;
}


.profile-box strong{

    display:block;

    color:
        var(--navy);

    font-size:11px;
}


.profile-box span{

    display:block;

    margin-top:2px;

    color:
        var(--muted);

    font-size:8px;
}


.info-list{

    padding-top:12px;
}


.info-row{

    display:flex;

    justify-content:space-between;

    gap:10px;

    padding:
        9px 0;

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

    max-width:150px;

    overflow:hidden;

    color:
        var(--ink);

    font-size:8px;

    font-weight:600;

    text-align:right;

    text-overflow:ellipsis;

    white-space:nowrap;
}


/* QUICK LINKS */

.quick-links{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;

    padding:20px;
}


.quick-link{

    display:flex;

    align-items:center;

    gap:8px;

    padding:11px;

    border:
        1px solid
        var(--line);

    border-radius:7px;

    background:
        #fbfcfd;

    color:
        var(--navy);

    font-size:8px;

    font-weight:700;

    transition:.2s;
}


.quick-link:hover{

    border-color:
        var(--gold);

    color:
        var(--gold);
}


.quick-icon{

    width:27px;
    height:27px;

    display:grid;

    place-items:center;

    border-radius:6px;

    background:
        var(--gold-bg);

    color:
        var(--gold);
}


/* EMPTY */

.empty{

    padding:
        35px 20px;

    color:
        var(--muted);

    font-size:9px;

    text-align:center;
}


/* RESPONSIVE */

@media(max-width:1150px){

    .stat-grid{

        grid-template-columns:
            repeat(2,1fr);
    }


    .dashboard-grid{

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


    .intro{

        align-items:flex-start;

        flex-direction:column;
    }


    .profile-mini{

        width:100%;

        text-align:left;
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


    .stat-grid{

        grid-template-columns:
            1fr;
    }


    .event-item{

        grid-template-columns:
            52px 1fr;

    }


    .event-action{

        grid-column:
            2;

        justify-self:start;
    }


    .quick-links{

        grid-template-columns:
            1fr;
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
        class="nav-link active"
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
        class="nav-link"
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
            Student Dashboard
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


<!-- INTRO -->

<div class="intro">


    <div>

        <div class="eyebrow">
            Welcome Back
        </div>


        <h1>
            Hello, <?= sanitize(
                $userName
            ) ?>.
        </h1>


        <p>
            Stay connected with your events,
            registrations and campus activities
            through your EventSphere student portal.
        </p>

    </div>


    <div class="profile-mini">

        <strong>
            Student Account
        </strong>

        <span>
            <?= sanitize(
                $userEmail
            ) ?>
        </span>

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

<div class="stat-grid">


    <div class="stat-card">

        <div>

            <div class="stat-label">
                My Registrations
            </div>

            <div class="stat-value">
                <?= number_format(
                    $stats['registrations']
                ) ?>
            </div>

        </div>


        <div class="stat-icon">
            ♙
        </div>

    </div>


    <div class="stat-card">

        <div>

            <div class="stat-label">
                Confirmed
            </div>

            <div class="stat-value">
                <?= number_format(
                    $stats['confirmed']
                ) ?>
            </div>

        </div>


        <div class="stat-icon">
            ✓
        </div>

    </div>


    <div class="stat-card">

        <div>

            <div class="stat-label">
                Waitlisted
            </div>

            <div class="stat-value">
                <?= number_format(
                    $stats['waitlisted']
                ) ?>
            </div>

        </div>


        <div class="stat-icon">
            ◷
        </div>

    </div>


    <div class="stat-card">

        <div>

            <div class="stat-label">
                Events Attended
            </div>

            <div class="stat-value">
                <?= number_format(
                    $stats['attended']
                ) ?>
            </div>

        </div>


        <div class="stat-icon">
            ✓
        </div>

    </div>


</div>


<!-- MAIN GRID -->

<div class="dashboard-grid">


<!-- LEFT COLUMN -->

<div>


    <!-- UPCOMING -->

    <div class="card">


        <div class="card-header">

            <div>

                <h2>
                    My Upcoming Events
                </h2>

                <p>
                    Your next registered EventSphere events.
                </p>

            </div>


            <a
                href="my-registrations.php"
                class="card-link"
            >
                View All
            </a>

        </div>


        <?php if (
            !empty($upcomingEvents)
        ): ?>


            <div class="event-list">


                <?php foreach (
                    $upcomingEvents
                    as $event
                ): ?>


                    <?php

                    $timestamp =
                        strtotime(
                            $event['start_date']
                        );

                    $day =
                        $timestamp
                            ? date(
                                'd',
                                $timestamp
                            )
                            : '—';

                    $month =
                        $timestamp
                            ? date(
                                'M',
                                $timestamp
                            )
                            : '—';

                    ?>


                    <div class="event-item">


                        <div class="event-date-box">

                            <div class="event-day">
                                <?= sanitize(
                                    $day
                                ) ?>
                            </div>


                            <div class="event-month">
                                <?= sanitize(
                                    $month
                                ) ?>
                            </div>

                        </div>


                        <div>


                            <div
                                class="event-title"
                                title="<?= sanitize(
                                    $event['title']
                                ) ?>"
                            >

                                <?= sanitize(
                                    $event['title']
                                ) ?>

                            </div>


                            <div class="event-meta">

                                <span>
                                    <?= sanitize(
                                        ucfirst(
                                            strtolower(
                                                $event['category']
                                            )
                                        )
                                    ) ?>
                                </span>


                                <span>
                                    <?= sanitize(
                                        studentEventTime(
                                            $event['start_date']
                                        )
                                    ) ?>
                                </span>


                                <span>
                                    <?= !empty(
                                        $event['venue_name']
                                    )
                                        ? sanitize(
                                            $event['venue_name']
                                        )
                                        : 'Venue TBA' ?>
                                </span>

                            </div>


                            <div
                                style="
                                    margin-top:5px;
                                "
                            >

                                <span
                                    class="
                                        status
                                        <?= studentStatusClass(
                                            $event[
                                                'registration_status'
                                            ]
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $event[
                                                'registration_status'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <a
                            href="event-details.php?event_id=<?= urlencode(
                                $event['event_id']
                            ) ?>"
                            class="event-action"
                        >
                            DETAILS
                        </a>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="empty">

                You do not have any upcoming registered events.

                <br><br>

                <a
                    href="events.php"
                    class="card-link"
                >
                    Browse Events →

                </a>

            </div>


        <?php endif; ?>


    </div>


    <!-- RECENT REGISTRATIONS -->

    <div
        class="card"
        style="margin-top:20px;"
    >


        <div class="card-header">


            <div>

                <h2>
                    Recent Registrations
                </h2>

                <p>
                    Your latest event registrations.
                </p>

            </div>


            <a
                href="my-registrations.php"
                class="card-link"
            >
                All Registrations
            </a>

        </div>


        <?php if (
            !empty($myRegistrations)
        ): ?>


            <div class="table-wrapper">


                <table
                    class="registration-table"
                >


                    <thead>

                        <tr>

                            <th>
                                Event
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                QR Ticket
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $myRegistrations
                            as $registration
                        ): ?>


                            <tr>


                                <td>

                                    <div
                                        class="registration-title"
                                    >

                                        <?= sanitize(
                                            $registration[
                                                'title'
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div
                                        class="registration-date"
                                    >

                                        <?= sanitize(
                                            studentEventDate(
                                                $registration[
                                                    'start_date'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <span
                                        class="
                                            status
                                            <?= studentStatusClass(
                                                $registration[
                                                    'status'
                                                ]
                                            ) ?>
                                        "
                                    >

                                        <?= sanitize(
                                            ucfirst(
                                                $registration[
                                                    'status'
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?php if (
                                        !empty(
                                            $registration[
                                                'qr_hash'
                                            ]
                                        )
                                    ): ?>

                                        <span
                                            class="
                                                qr-state
                                                qr-ready
                                            "
                                        >
                                            READY
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                qr-state
                                                qr-not-ready
                                            "
                                        >
                                            NOT READY
                                        </span>

                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <div class="empty">

                No registrations found.

            </div>


        <?php endif; ?>


    </div>


</div>


<!-- RIGHT COLUMN -->

<div>


    <!-- PROFILE -->

    <div class="card">


        <div class="card-header">

            <div>

                <h2>
                    My Profile
                </h2>

                <p>
                    Student account information.
                </p>

            </div>

        </div>


        <div class="info-card">


            <div class="profile-box">


                <div class="profile-avatar">

                    <?= sanitize(
                        $initial
                    ) ?>

                </div>


                <div>

                    <strong>
                        <?= sanitize(
                            $userName
                        ) ?>
                    </strong>


                    <span>
                        <?= sanitize(
                            $userEmail
                        ) ?>
                    </span>

                </div>


            </div>


            <div class="info-list">


                <div class="info-row">

                    <span class="info-label">
                        Roll Number
                    </span>


                    <span class="info-value">

                        <?= $userRoll !== ''
                            ? sanitize(
                                $userRoll
                            )
                            : 'Not provided' ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Department
                    </span>


                    <span class="info-value">

                        <?= $userDept !== ''
                            ? sanitize(
                                $userDept
                            )
                            : 'Not assigned' ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Account
                    </span>


                    <span class="info-value">
                        Active Student
                    </span>

                </div>


            </div>


        </div>


    </div>


    <!-- QUICK ACTIONS -->

    <div
        class="card"
        style="margin-top:20px;"
    >


        <div class="card-header">

            <div>

                <h2>
                    Quick Access
                </h2>

                <p>
                    Common student actions.
                </p>

            </div>

        </div>


        <div class="quick-links">


            <a
                href="events.php"
                class="quick-link"
            >

                <span class="quick-icon">
                    ◈
                </span>

                Browse Events

            </a>


            <a
                href="my-registrations.php"
                class="quick-link"
            >

                <span class="quick-icon">
                    ♙
                </span>

                My Registrations

            </a>


            <a
                href="my-tickets.php"
                class="quick-link"
            >

                <span class="quick-icon">
                    ▣
                </span>

                My Tickets

            </a>


            <a
                href="attendance.php"
                class="quick-link"
            >

                <span class="quick-icon">
                    ✓
                </span>

                Attendance

            </a>


        </div>


    </div>


</div>


</div>


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>