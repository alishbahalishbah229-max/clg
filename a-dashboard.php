<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';


requireRole('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

$userName = $user['full_name'] ?? 'Administrator';
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

} elseif (
    isset($db) &&
    $db instanceof PDO
) {

    $pdoConnection = $db;

}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$stats = [

    'students'      => 0,
    'organizers'    => 0,
    'events'        => 0,
    'pending'       => 0,
    'registrations' => 0,
    'attendance'    => 0,
    'media'         => 0,
    'venues'        => 0

];


$eventStatusCounts = [

    'draft'     => 0,
    'pending'   => 0,
    'approved'  => 0,
    'rejected'  => 0,
    'completed' => 0

];


$recentEvents = [];

$recentActivity = [];

$errorMessage = '';


/*
|--------------------------------------------------------------------------
| HELPER QUERY
|--------------------------------------------------------------------------
*/

function adminCount(
    PDO $pdo,
    string $sql,
    array $params = []
): int {

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    return (int)(
        $stmt->fetchColumn()
        ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| LOAD DASHBOARD
|--------------------------------------------------------------------------
*/

if (
    $pdoConnection instanceof PDO
) {

    try {


        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        $stats['students'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM users
                WHERE role = 'student'
                "
            );


        $stats['organizers'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM users
                WHERE role = 'organizer'
                "
            );


        /*
        |--------------------------------------------------------------------------
        | EVENTS
        |--------------------------------------------------------------------------
        */

        $stats['events'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM events
                "
            );


        $stats['pending'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM events
                WHERE approval_state = 'pending'
                "
            );


        /*
        |--------------------------------------------------------------------------
        | REGISTRATIONS
        |--------------------------------------------------------------------------
        */

        $stats['registrations'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM registrations
                "
            );


        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE
        |--------------------------------------------------------------------------
        */

        $stats['attendance'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM attendance
                "
            );


        /*
        |--------------------------------------------------------------------------
        | MEDIA
        |--------------------------------------------------------------------------
        */

        $stats['media'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM media_gallery
                "
            );


        /*
        |--------------------------------------------------------------------------
        | VENUES
        |--------------------------------------------------------------------------
        */

        $stats['venues'] =
            adminCount(
                $pdoConnection,
                "
                SELECT COUNT(*)
                FROM venues
                WHERE status = 'active'
                "
            );


        /*
        |--------------------------------------------------------------------------
        | EVENT STATUS COUNTS
        |--------------------------------------------------------------------------
        */

        $statusStmt =
            $pdoConnection->query("
                SELECT
                    approval_state,
                    COUNT(*) AS total
                FROM events
                GROUP BY approval_state
            ");

        $statusRows =
            $statusStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $statusRows
            as $row
        ) {

            $state =
                strtolower(
                    (string)(
                        $row['approval_state']
                        ?? ''
                    )
                );


            if (
                array_key_exists(
                    $state,
                    $eventStatusCounts
                )
            ) {

                $eventStatusCounts[$state] =
                    (int)(
                        $row['total']
                        ?? 0
                    );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | RECENT EVENTS
        |--------------------------------------------------------------------------
        */

        $eventStmt =
            $pdoConnection->query("
                SELECT

                    e.event_id,
                    e.title,
                    e.category,
                    e.start_date,
                    e.end_date,
                    e.approval_state,
                    e.max_seats,

                    u.full_name AS organizer_name,

                    v.venue_name

                FROM events e

                LEFT JOIN users u
                    ON u.user_id = e.organizer_id

                LEFT JOIN venues v
                    ON v.venue_id = e.venue_id

                ORDER BY
                    e.created_at DESC

                LIMIT 6
            ");


        $recentEvents =
            $eventStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | RECENT AUDIT ACTIVITY
        |--------------------------------------------------------------------------
        */

        $activityStmt =
            $pdoConnection->query("
                SELECT

                    a.log_id,
                    a.user_id,
                    a.action,
                    a.details,
                    a.ip_address,
                    a.created_at,

                    u.full_name

                FROM audit_logs a

                LEFT JOIN users u
                    ON u.user_id = a.user_id

                ORDER BY
                    a.created_at DESC

                LIMIT 8
            ");


        $recentActivity =
            $activityStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


    }

    catch (PDOException $e) {

        error_log(
            'Admin Dashboard Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Some dashboard information could not be loaded.';

    }

}

else {

    $errorMessage =
        'Database connection is not available.';

}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function adminStatusClass(
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


function adminStatusLabel(
    string $status
): string {

    return ucfirst(
        strtolower($status)
    );
}


function adminDate(
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


function adminDateTime(
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


function adminActionLabel(
    string $action
): string {

    $action =
        trim($action);

    if ($action === '') {
        return 'System Activity';
    }

    return ucwords(
        str_replace(
            [
                '_',
                '-'
            ],
            ' ',
            strtolower($action)
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
    Admin Dashboard | EventSphere
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


/* BASE */

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

    max-width:1400px;

    margin:
        0 auto;

    padding:
        42px 40px 60px;

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


/* ALERT */

.alert{

    margin-bottom:20px;

    padding:
        13px 16px;

    border-radius:7px;

    background:
        var(--gold-bg);

    border:
        1px solid
        #ead7a7;

    color:
        #8f6b18;

    font-size:10px;

    font-weight:600;

}


/* STAT GRID */

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

    gap:12px;

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


/* MAIN GRID */

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

    letter-spacing:.6px;

    text-transform:uppercase;

}


/* STATUS OVERVIEW */

.status-overview{

    padding:21px;

}


.status-row{

    display:grid;

    grid-template-columns:
        100px
        1fr
        40px;

    align-items:center;

    gap:12px;

    margin-bottom:14px;

}


.status-row:last-child{

    margin-bottom:0;

}


.status-label{

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    text-transform:uppercase;

}


.progress{

    height:7px;

    overflow:hidden;

    border-radius:20px;

    background:
        #edf0f3;

}


.progress-fill{

    height:100%;

    min-width:0;

    border-radius:20px;

}


.fill-draft{
    background:#aab3c0;
}


.fill-pending{
    background:var(--gold);
}


.fill-approved{
    background:var(--green);
}


.fill-rejected{
    background:var(--red);
}


.fill-completed{
    background:var(--blue);
}


.status-number{

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;

    text-align:right;

}


/* EVENTS TABLE */

.table-wrapper{

    width:100%;

    overflow-x:auto;

}


.events-table{

    width:100%;

    min-width:730px;

    border-collapse:collapse;

}


.events-table th{

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


.events-table td{

    padding:
        13px 14px;

    border-bottom:
        1px solid
        #edf0f3;

    font-size:8px;

    vertical-align:middle;

}


.events-table tbody tr:hover{

    background:#fcfdff;

}


.event-name{

    max-width:220px;

    overflow:hidden;

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;

}


.organizer-name{

    color:
        var(--muted);

    font-size:8px;

}


.category{

    display:inline-flex;

    padding:
        4px 7px;

    border-radius:20px;

    background:
        var(--blue-bg);

    color:
        var(--blue);

    font-size:6px;

    font-weight:700;

    letter-spacing:.5px;

    text-transform:uppercase;

}


.event-date{

    color:
        var(--ink);

    font-size:8px;

    font-weight:700;

}


.event-venue{

    color:
        var(--muted);

    font-size:8px;

}


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


/* ACTIVITY */

.activity-list{

    padding:
        12px 20px 20px;

}


.activity-item{

    display:flex;

    gap:10px;

    padding:
        12px 0;

    border-bottom:
        1px solid
        #edf0f3;

}


.activity-item:last-child{

    border-bottom:none;

}


.activity-icon{

    min-width:30px;

    width:30px;

    height:30px;

    display:grid;

    place-items:center;

    border-radius:7px;

    background:
        var(--gold-bg);

    color:
        var(--gold);

    font-size:11px;

}


.activity-content{

    min-width:0;

}


.activity-action{

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;

}


.activity-user{

    margin-top:2px;

    color:
        var(--muted);

    font-size:8px;

}


.activity-time{

    margin-top:3px;

    color:#8a94a4;

    font-size:7px;

}


/* QUICK LINKS */

.quick-links{

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:10px;

    padding:20px;

}


.quick-link{

    display:flex;

    align-items:center;

    gap:8px;

    padding:
        11px;

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
            30px 24px;

    }


    .intro{

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


    .stat-grid{

        grid-template-columns:
            1fr;

    }


    .quick-links{

        grid-template-columns:
            1fr;

    }


    .status-row{

        grid-template-columns:
            85px
            1fr
            30px;

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
        C
    </div>


    <div class="brand-text">

        <strong>
            EventSphere
        </strong>

        <small>
            ADMINISTRATION
        </small>

    </div>

</a>


<nav class="nav-section">


    <div class="nav-title">
        Administration
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
        href="contact-messages.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ♙
        </span>

        <span>
            Contact Messages
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
            Events
        </span>

    </a>


    <a
        href="event-approvals.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ✓
        </span>

        <span>
            Event Approvals
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


    <a
        href="media.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▧
        </span>

        <span>
            Media Gallery
        </span>

    </a>


    <a
        href="venues.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◫
        </span>

        <span>
            Venues
        </span>

    </a>


    <a
        href="departments.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▤
        </span>

        <span>
            Departments
        </span>

    </a>


    <a
        href="categories.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◆
        </span>

        <span>
            Categories
        </span>

    </a>


    <a
        href="audit-logs.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◷
        </span>

        <span>
            Audit Logs
        </span>

    </a>

<a
    href="programs.php"
    class="nav-link"
>
    <span class="nav-icon">▤</span>
    <span>Programs</span>
</a>
</nav>


</aside>


<!-- MAIN -->

<main class="main">


<header class="topbar">


    <div class="topbar-left">

        <span class="topbar-label">
            Administration
        </span>


        <div class="page-title">
            Admin Dashboard
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
            System Administrator
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
                EventSphere Administration
            </div>


            <h1>
                System Overview
            </h1>


            <p>
                Monitor users, events, registrations,
                attendance and campus resources from
                the central administration dashboard.
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



    <!-- STATISTICS -->

    <div class="stat-grid">


        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Students
                </div>


                <div class="stat-value">
                    <?= number_format(
                        $stats['students']
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
                    Organizers
                </div>


                <div class="stat-value">
                    <?= number_format(
                        $stats['organizers']
                    ) ?>
                </div>

            </div>


            <div class="stat-icon">
                ◉
            </div>

        </div>



        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Total Events
                </div>


                <div class="stat-value">
                    <?= number_format(
                        $stats['events']
                    ) ?>
                </div>

            </div>


            <div class="stat-icon">
                ◈
            </div>

        </div>



        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Pending Approvals
                </div>


                <div class="stat-value">
                    <?= number_format(
                        $stats['pending']
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
                    Registrations
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
                    Attendance Records
                </div>


                <div class="stat-value">
                    <?= number_format(
                        $stats['attendance']
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
                    Media Items
                </div>


                <div class="stat-value">
                    <?= number_format(
                        $stats['media']
                    ) ?>
                </div>

            </div>


            <div class="stat-icon">
                ▧
            </div>

        </div>



        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Active Venues
                </div>


                <div class="stat-value">
                    <?= number_format(
                        $stats['venues']
                    ) ?>
                </div>

            </div>


            <div class="stat-icon">
                ◫
            </div>

        </div>


    </div>



    <div class="dashboard-grid">


        <!-- LEFT -->

        <div>


            <!-- EVENT STATUS -->

            <div class="card">


                <div class="card-header">

                    <div>

                        <h2>
                            Event Approval Overview
                        </h2>

                        <p>
                            Current distribution of all events.
                        </p>

                    </div>


                    <a
                        href="event-approvals.php"
                        class="card-link"
                    >
                        Review
                    </a>

                </div>


                <div class="status-overview">


                    <?php

                    $totalForProgress =
                        max(
                            1,
                            $stats['events']
                        );

                    ?>


                    <?php foreach (
                        $eventStatusCounts
                        as $status => $count
                    ): ?>


                        <div class="status-row">


                            <div class="status-label">

                                <?= sanitize(
                                    ucfirst($status)
                                ) ?>

                            </div>


                            <div class="progress">


                                <div
                                    class="
                                        progress-fill
                                        fill-<?= sanitize(
                                            $status
                                        ) ?>
                                    "
                                    style="
                                        width:
                                        <?= min(
                                            100,
                                            (
                                                $count /
                                                $totalForProgress
                                            ) * 100
                                        ) ?>%;
                                    "
                                ></div>


                            </div>


                            <div class="status-number">

                                <?= number_format(
                                    $count
                                ) ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            </div>



            <!-- RECENT EVENTS -->

            <div
                class="card"
                style="margin-top:20px;"
            >


                <div class="card-header">

                    <div>

                        <h2>
                            Recent Events
                        </h2>

                        <p>
                            Latest events added to the system.
                        </p>

                    </div>


                    <a
                        href="events.php"
                        class="card-link"
                    >
                        View All
                    </a>

                </div>



                <?php if (
                    !empty(
                        $recentEvents
                    )
                ): ?>


                    <div class="table-wrapper">


                        <table
                            class="events-table"
                        >


                            <thead>

                                <tr>

                                    <th>
                                        Event
                                    </th>

                                    <th>
                                        Organizer
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Venue
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $recentEvents
                                    as $event
                                ): ?>


                                    <tr>


                                        <td>

                                            <div
                                                class="event-name"
                                                title="<?= sanitize(
                                                    $event[
                                                        'title'
                                                    ]
                                                ) ?>"
                                            >

                                                <?= sanitize(
                                                    $event[
                                                        'title'
                                                    ]
                                                ) ?>

                                            </div>

                                        </td>


                                        <td>

                                            <div
                                                class="organizer-name"
                                            >

                                                <?= sanitize(
                                                    $event[
                                                        'organizer_name'
                                                    ]
                                                    ??
                                                    'Unassigned'
                                                ) ?>

                                            </div>

                                        </td>


                                        <td>

                                            <span
                                                class="category"
                                            >

                                                <?= sanitize(
                                                    ucfirst(
                                                        strtolower(
                                                            $event[
                                                                'category'
                                                            ]
                                                        )
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <div
                                                class="event-date"
                                            >

                                                <?= sanitize(
                                                    adminDate(
                                                        $event[
                                                            'start_date'
                                                        ]
                                                    )
                                                ) ?>

                                            </div>

                                        </td>


                                        <td>

                                            <div
                                                class="event-venue"
                                            >

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
                                                    : '—' ?>

                                            </div>

                                        </td>


                                        <td>

                                            <span
                                                class="
                                                    status
                                                    <?= adminStatusClass(
                                                        $event[
                                                            'approval_state'
                                                        ]
                                                        ?? 'draft'
                                                    ) ?>
                                                "
                                            >

                                                <?= sanitize(
                                                    adminStatusLabel(
                                                        $event[
                                                            'approval_state'
                                                        ]
                                                        ?? 'draft'
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="empty">

                        No events have been created yet.

                    </div>

                <?php endif; ?>


            </div>


        </div>



        <!-- RIGHT -->

        <div>


            <!-- QUICK LINKS -->

            <div class="card">


                <div class="card-header">

                    <div>

                        <h2>
                            Quick Management
                        </h2>

                        <p>
                            Frequently used administration areas.
                        </p>

                    </div>

                </div>


                <div class="quick-links">


                    <a
                        href="event-approvals.php"
                        class="quick-link"
                    >

                        <span class="quick-icon">
                            ✓
                        </span>

                        Event Approvals

                    </a>


                    <a
        href="contact-messages.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ♙
        </span>

        <span>
            Contact Messages
        </span>

    </a>
       <a
        href="users.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ♙
        </span>

        <span>
            Users
        </span>

    </a>


                    <a
                        href="venues.php"
                        class="quick-link"
                    >

                        <span class="quick-icon">
                            ◫
                        </span>

                        Manage Venues

                    </a>


                    <a
                        href="departments.php"
                        class="quick-link"
                    >

                        <span class="quick-icon">
                            ▤
                        </span>

                        Departments

                    </a>


                </div>


            </div>



            <!-- ACTIVITY -->

            <div
                class="card"
                style="margin-top:20px;"
            >


                <div class="card-header">

                    <div>

                        <h2>
                            Recent Activity
                        </h2>

                        <p>
                            Latest administrative actions.
                        </p>

                    </div>


                    <a
                        href="audit-logs.php"
                        class="card-link"
                    >
                        Logs
                    </a>

                </div>



                <?php if (
                    !empty(
                        $recentActivity
                    )
                ): ?>


                    <div class="activity-list">


                        <?php foreach (
                            $recentActivity
                            as $activity
                        ): ?>


                            <div class="activity-item">


                                <div class="activity-icon">
                                    ◷
                                </div>


                                <div class="activity-content">


                                    <div
                                        class="activity-action"
                                    >

                                        <?= sanitize(
                                            adminActionLabel(
                                                $activity[
                                                    'action'
                                                ]
                                                ?? ''
                                            )
                                        ) ?>

                                    </div>


                                    <div
                                        class="activity-user"
                                    >

                                        <?= !empty(
                                            $activity[
                                                'full_name'
                                            ]
                                        )
                                            ? sanitize(
                                                $activity[
                                                    'full_name'
                                                ]
                                            )
                                            : 'System' ?>

                                    </div>


                                    <div
                                        class="activity-time"
                                    >

                                        <?= sanitize(
                                            adminDateTime(
                                                $activity[
                                                    'created_at'
                                                ]
                                            )
                                        ) ?>

                                    </div>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="empty">

                        No administrative activity has been recorded yet.

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</section>


</main>

<?php require_once __DIR__ . '/footer.php'; ?>
</body>

</html>
