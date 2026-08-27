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
    substr(trim($userName), 0, 1)
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

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');


$statuses = [
    'draft'     => 'Draft',
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'completed' => 'Completed'
];

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
                e.category,
                e.department_id,
                e.venue_id,
                e.max_seats,
                e.waitlist_capacity,
                e.start_date,
                e.end_date,
                e.approval_state,
                e.organizer_id,
                e.rejection_reason,
                e.created_at,

                u.full_name AS organizer_name,
                u.email AS organizer_email,

                v.venue_name

            FROM events e

            LEFT JOIN users u
                ON u.user_id = e.organizer_id

            LEFT JOIN venues v
                ON v.venue_id = e.venue_id

            WHERE 1 = 1
        ";

        $params = [];


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
                    OR u.full_name LIKE :search
                    OR u.email LIKE :search
                    OR e.event_id LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        if (
            $statusFilter !== '' &&
            array_key_exists(
                $statusFilter,
                $statuses
            )
        ) {

            $sql .= "
                AND e.approval_state = :approval_state
            ";

            $params[':approval_state'] =
                $statusFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | CATEGORY
        |--------------------------------------------------------------------------
        */

        if (
            $categoryFilter !== '' &&
            array_key_exists(
                $categoryFilter,
                $categories
            )
        ) {

            $sql .= "
                AND e.category = :category
            ";

            $params[':category'] =
                $categoryFilter;
        }


        $sql .= "
            ORDER BY
                e.created_at DESC
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
            'Admin Events Error: ' .
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
| COUNTS
|--------------------------------------------------------------------------
*/

$totalEvents = count($events);

$draftCount = 0;
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$completedCount = 0;


foreach ($events as $event) {

    switch (
        strtolower(
            (string)(
                $event['approval_state'] ?? ''
            )
        )
    ) {

        case 'draft':
            $draftCount++;
            break;

        case 'pending':
            $pendingCount++;
            break;

        case 'approved':
            $approvedCount++;
            break;

        case 'rejected':
            $rejectedCount++;
            break;

        case 'completed':
            $completedCount++;
            break;
    }
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function adminEventStatusClass(
    string $status
): string {

    switch (strtolower($status)) {

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


function adminEventDate(
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


function adminEventTime(
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
    Events | EventSphere
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

    font-size:11px;

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

    margin:auto;

    padding:
        42px 40px 60px;

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


.intro{

    margin-bottom:25px;

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

    margin-bottom:18px;

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
        repeat(5,1fr);

    gap:13px;

    margin-bottom:22px;

}


.stat{

    padding:17px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:9px;

    box-shadow:
        var(--shadow);

}


.stat-label{

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

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


/* FILTER */

.filter-card{

    margin-bottom:18px;

    padding:17px;

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
        1.5fr
        1fr
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
        10px 11px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    outline:none;

    background:#fbfcfd;

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
        10px 14px;

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
        10px 14px;

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


/* TABLE */

.table-card{

    overflow:hidden;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);

}


.table-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        21px 22px;

    border-bottom:
        1px solid
        var(--line);

}


.table-header h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}


.table-header p{

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;

}


.table-count{

    color:
        var(--gold);

    font-size:9px;

    font-weight:700;

    text-transform:uppercase;

}


.table-wrapper{

    width:100%;

    overflow-x:auto;

}


.events-table{

    width:100%;

    min-width:1200px;

    border-collapse:collapse;

}


.events-table th{

    padding:
        12px 14px;

    background:#fafbfd;

    border-bottom:
        1px solid
        var(--line);

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

    text-align:left;

    text-transform:uppercase;

}


.events-table td{

    padding:
        14px;

    border-bottom:
        1px solid
        #edf0f3;

    vertical-align:middle;

    font-size:8px;

}


.events-table tbody tr:hover{

    background:#fcfdff;

}


.event-title{

    max-width:230px;

    overflow:hidden;

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;

}


.event-subtitle{

    max-width:230px;

    margin-top:3px;

    overflow:hidden;

    color:
        var(--muted);

    font-size:7px;

    text-overflow:ellipsis;

    white-space:nowrap;

}


.organizer{

    color:
        var(--ink);

    font-size:8px;

    font-weight:700;

}


.organizer small{

    display:block;

    margin-top:3px;

    color:
        var(--muted);

    font-size:7px;

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

    text-transform:uppercase;

}


.schedule{

    color:
        var(--ink);

    font-size:8px;

    font-weight:700;

}


.schedule small{

    display:block;

    margin-top:3px;

    color:
        var(--muted);

    font-size:7px;

}


.venue{

    color:
        var(--muted);

    font-size:8px;

}


.seats{

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;

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


.status-draft{

    background:#eef0f3;
    color:var(--muted);

}


.status-pending{

    background:var(--gold-bg);
    color:#9a711d;

}


.status-approved{

    background:var(--green-bg);
    color:var(--green);

}


.status-rejected{

    background:var(--red-bg);
    color:var(--red);

}


.status-completed{

    background:var(--blue-bg);
    color:var(--blue);

}


.view-link{

    display:inline-flex;

    padding:
        7px 10px;

    border:
        1px solid
        var(--line);

    border-radius:5px;

    color:
        var(--navy);

    font-size:6px;

    font-weight:700;

    letter-spacing:.4px;

}


.view-link:hover{

    border-color:
        var(--gold);

    color:
        var(--gold);

}


.empty{

    padding:
        65px 25px;

    color:
        var(--muted);

    font-size:10px;

    text-align:center;

}


@media(max-width:1150px){

    .stats{

        grid-template-columns:
            repeat(3,1fr);

    }

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
            30px 24px;

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

    .stats{

        grid-template-columns:
            1fr;

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
        class="nav-link"
    >
        <span class="nav-icon">▦</span>
        <span>Dashboard</span>
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
        href="events.php"
        class="nav-link active"
    >
        <span class="nav-icon">◈</span>
        <span>Events</span>
    </a>


    <a
        href="event-approvals.php"
        class="nav-link"
    >
        <span class="nav-icon">✓</span>
        <span>Event Approvals</span>
    </a>


    <a
        href="registrations.php"
        class="nav-link"
    >
        <span class="nav-icon">♙</span>
        <span>Registrations</span>
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
        <span>Media Gallery</span>
    </a>


    <a
        href="venues.php"
        class="nav-link"
    >
        <span class="nav-icon">◫</span>
        <span>Venues</span>
    </a>


    <a
        href="departments.php"
        class="nav-link"
    >
        <span class="nav-icon">▤</span>
        <span>Departments</span>
    </a>


    <a
        href="categories.php"
        class="nav-link"
    >
        <span class="nav-icon">◆</span>
        <span>Categories</span>
    </a>


    <a
        href="audit-logs.php"
        class="nav-link"
    >
        <span class="nav-icon">◷</span>
        <span>Audit Logs</span>
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
            Events
        </div>

    </div>


    <div class="user-area">

        <div class="user-details">

            <strong>
                <?= sanitize($userName) ?>
            </strong>

            <span>
                System Administrator
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
        Event Administration
    </div>

    <h1>
        All Events
    </h1>

    <p>
        Review and monitor every event created across
        the EventSphere platform.
    </p>

</div>


<?php if ($errorMessage !== ''): ?>

    <div class="alert">
        <?= sanitize($errorMessage) ?>
    </div>

<?php endif; ?>


<!-- STATS -->

<div class="stats">


    <div class="stat">

        <div class="stat-label">
            Total
        </div>

        <div class="stat-value">
            <?= number_format($totalEvents) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Draft
        </div>

        <div class="stat-value">
            <?= number_format($draftCount) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Pending
        </div>

        <div class="stat-value">
            <?= number_format($pendingCount) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Approved
        </div>

        <div class="stat-value">
            <?= number_format($approvedCount) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Rejected
        </div>

        <div class="stat-value">
            <?= number_format($rejectedCount) ?>
        </div>

    </div>


</div>


<!-- FILTER -->

<div class="filter-card">

    <form
        method="GET"
        class="filter-form"
    >


        <div class="filter-group">

            <label for="search">
                Search
            </label>

            <input
                type="text"
                id="search"
                name="search"
                class="filter-control"
                value="<?= sanitize($search) ?>"
                placeholder="Event or organizer..."
            >

        </div>


        <div class="filter-group">

            <label for="status">
                Approval Status
            </label>

            <select
                id="status"
                name="status"
                class="filter-control"
            >

                <option value="">
                    All Statuses
                </option>

                <?php foreach (
                    $statuses as
                    $value => $label
                ): ?>

                    <option
                        value="<?= sanitize($value) ?>"
                        <?= $statusFilter === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= sanitize($label) ?>
                    </option>

                <?php endforeach; ?>

            </select>

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
                    $categories as
                    $value => $label
                ): ?>

                    <option
                        value="<?= sanitize($value) ?>"
                        <?= $categoryFilter === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= sanitize($label) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="filter-actions">

            <button
                type="submit"
                class="filter-button"
            >
                FILTER
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

<div class="table-card">


    <div class="table-header">

        <div>

            <h2>
                Event Directory
            </h2>

            <p>
                Complete administrative view of all events.
            </p>

        </div>


        <div class="table-count">

            <?= number_format(count($events)) ?>
            Events

        </div>

    </div>


    <?php if (!empty($events)): ?>


        <div class="table-wrapper">

            <table class="events-table">

                <thead>

                    <tr>

                        <th>Event</th>
                        <th>Organizer</th>
                        <th>Category</th>
                        <th>Schedule</th>
                        <th>Venue</th>
                        <th>Seats</th>
                        <th>Status</th>
                        <th>View</th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $events as $event
                    ): ?>


                        <?php

                        $state =
                            strtolower(
                                (string)(
                                    $event['approval_state']
                                    ?? 'draft'
                                )
                            );

                        ?>


                        <tr>


                            <td>

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


                                <?php if (
                                    !empty(
                                        $event['subtitle']
                                    )
                                ): ?>

                                    <div
                                        class="event-subtitle"
                                    >
                                        <?= sanitize(
                                            $event['subtitle']
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="organizer">

                                    <?= sanitize(
                                        $event[
                                            'organizer_name'
                                        ]
                                        ?? 'Unassigned'
                                    ) ?>

                                    <small>

                                        <?= sanitize(
                                            $event[
                                                'organizer_email'
                                            ]
                                            ?? ''
                                        ) ?>

                                    </small>

                                </div>

                            </td>


                            <td>

                                <span class="category">

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

                                <div class="schedule">

                                    <?= sanitize(
                                        adminEventDate(
                                            $event[
                                                'start_date'
                                            ]
                                        )
                                    ) ?>

                                    <small>

                                        <?= sanitize(
                                            adminEventTime(
                                                $event[
                                                    'start_date'
                                                ]
                                            )
                                        ) ?>

                                    </small>

                                </div>

                            </td>


                            <td>

                                <div class="venue">

                                    <?= !empty(
                                        $event['venue_name']
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

                                <div class="seats">

                                    <?= number_format(
                                        (int)(
                                            $event[
                                                'max_seats'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="
                                        status
                                        <?= adminEventStatusClass(
                                            $state
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        ucfirst($state)
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="view-event.php?event_id=<?= urlencode(
                                        $event['event_id']
                                    ) ?>"
                                    class="view-link"
                                >
                                    VIEW
                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>

            </table>

        </div>


    <?php else: ?>


        <div class="empty">

            No events matched the selected filters.

        </div>


    <?php endif; ?>


</div>


</section>


</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
