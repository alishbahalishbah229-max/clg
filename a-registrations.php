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

$eventFilter = trim(
    $_GET['event_id'] ?? ''
);

$statusFilter = trim(
    $_GET['status'] ?? ''
);


/*
|--------------------------------------------------------------------------
| STATUS OPTIONS
|--------------------------------------------------------------------------
*/

$registrationStatuses = [
    'confirmed' => 'Confirmed',
    'waitlisted' => 'Waitlisted',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed'
];


/*
|--------------------------------------------------------------------------
| EVENTS
|--------------------------------------------------------------------------
*/

$events = [];

$loadError = '';

if ($pdoConnection instanceof PDO) {

    try {

        $stmt = $pdoConnection->query("
            SELECT
                event_id,
                title
            FROM events
            ORDER BY start_date DESC
        ");

        $events =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Admin Registration Events Error: ' .
            $e->getMessage()
        );

        $loadError =
            'Unable to load event filters.';
    }
}


/*
|--------------------------------------------------------------------------
| REGISTRATIONS
|--------------------------------------------------------------------------
*/

$registrations = [];

if (
    $pdoConnection instanceof PDO
) {

    try {

        $sql = "
            SELECT

                r.reg_id,
                r.user_id,
                r.event_id,
                r.status,
                r.qr_hash,
                r.queue_position,
                r.registered_at,

                u.full_name,
                u.email,
                u.roll_number,
                u.dept_id,

                e.title AS event_title,
                e.start_date,
                e.end_date,
                e.approval_state,

                organizer.full_name AS organizer_name

            FROM registrations r

            INNER JOIN users u
                ON u.user_id = r.user_id

            INNER JOIN events e
                ON e.event_id = r.event_id

            LEFT JOIN users organizer
                ON organizer.user_id = e.organizer_id

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
                    u.full_name LIKE :search
                    OR u.email LIKE :search
                    OR u.roll_number LIKE :search
                    OR e.title LIKE :search
                    OR r.reg_id LIKE :search
                    OR organizer.full_name LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | EVENT
        |--------------------------------------------------------------------------
        */

        if ($eventFilter !== '') {

            $sql .= "
                AND r.event_id = :event_id
            ";

            $params[':event_id'] =
                $eventFilter;
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
                $registrationStatuses
            )
        ) {

            $sql .= "
                AND r.status = :status
            ";

            $params[':status'] =
                $statusFilter;
        }


        $sql .= "
            ORDER BY
                r.registered_at DESC
        ";


        $stmt =
            $pdoConnection->prepare(
                $sql
            );

        $stmt->execute(
            $params
        );

        $registrations =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Admin Registrations Error: ' .
            $e->getMessage()
        );

        $loadError =
            'Unable to load registrations.';
    }

} elseif ($loadError === '') {

    $loadError =
        'Database connection is not available.';
}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalRegistrations =
    count($registrations);

$confirmedCount = 0;
$waitlistedCount = 0;
$cancelledCount = 0;
$completedCount = 0;

foreach (
    $registrations as $registration
) {

    switch (
        strtolower(
            (string)(
                $registration['status'] ?? ''
            )
        )
    ) {

        case 'confirmed':
            $confirmedCount++;
            break;

        case 'waitlisted':
            $waitlistedCount++;
            break;

        case 'cancelled':
            $cancelledCount++;
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

function adminRegistrationStatusClass(
    string $status
): string {

    switch (
        strtolower($status)
    ) {

        case 'confirmed':
            return 'status-confirmed';

        case 'completed':
            return 'status-completed';

        case 'cancelled':
            return 'status-cancelled';

        default:
            return 'status-waitlisted';
    }
}


function adminRegistrationDate(
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
    Registrations EventSphere
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


input,
select,
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

    padding:24px 16px;

    background:var(--navy);

    color:white;

}


.brand{

    display:flex;
    align-items:center;
    gap:12px;

    padding:4px 12px 25px;

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

    max-width:1400px;

    margin:auto;

    padding:
        42px 40px 60px;

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


.intro{

    margin-bottom:25px;

}


.intro p{

    max-width:720px;

    margin-top:8px;

    color:var(--muted);

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
        1px solid #efcccc;

    color:
        var(--red);

    font-size:10px;

}


/* STATS */

.stats{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:22px;

}


.stat{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:17px;

    background:white;

    border:
        1px solid var(--line);

    border-radius:9px;

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


.stat-icon{

    width:35px;
    height:35px;

    display:grid;
    place-items:center;

    border-radius:8px;

    background:
        var(--gold-bg);

    color:
        var(--gold);

}


/* FILTER */

.filter-card{

    margin-bottom:18px;

    padding:17px;

    background:white;

    border:
        1px solid var(--line);

    border-radius:10px;

    box-shadow:
        var(--shadow);

}


.filter-form{

    display:grid;

    grid-template-columns:
        1.6fr
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
        1px solid var(--line);

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
        1px solid var(--line);

    border-radius:6px;

    background:white;

    color:var(--muted);

    font-size:8px;

    font-weight:700;

}


/* TABLE */

.table-card{

    overflow:hidden;

    background:white;

    border:
        1px solid var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);

}


.table-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        21px 22px;

    border-bottom:
        1px solid var(--line);

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

    letter-spacing:.7px;

    text-transform:uppercase;

}


.table-wrapper{

    width:100%;

    overflow-x:auto;

}


.registration-table{

    width:100%;

    min-width:1200px;

    border-collapse:collapse;

}


.registration-table th{

    padding:
        12px 14px;

    background:#fafbfd;

    border-bottom:
        1px solid var(--line);

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

    text-align:left;

    text-transform:uppercase;

}


.registration-table td{

    padding:
        14px;

    border-bottom:
        1px solid #edf0f3;

    vertical-align:middle;

    font-size:8px;

}


.registration-table tbody tr:hover{

    background:#fcfdff;

}


.student-name{

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;

}


.student-email{

    margin-top:3px;

    color:
        var(--muted);

    font-size:7px;

}


.roll-number{

    color:
        var(--ink);

    font-size:8px;

    font-weight:600;

}


.event-name{

    max-width:210px;

    overflow:hidden;

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;

}


.organizer-name{

    margin-top:3px;

    color:
        var(--muted);

    font-size:7px;

}


.registration-id{

    max-width:135px;

    overflow:hidden;

    color:#8791a1;

    font-family:monospace;

    font-size:7px;

    text-overflow:ellipsis;

    white-space:nowrap;

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


.status-confirmed{

    background:
        var(--green-bg);

    color:
        var(--green);

}


.status-waitlisted{

    background:
        var(--gold-bg);

    color:#9a711d;

}


.status-cancelled{

    background:
        var(--red-bg);

    color:var(--red);

}


.status-completed{

    background:
        var(--blue-bg);

    color:var(--blue);

}


.qr-ready{

    color:
        var(--green);

    font-size:7px;

    font-weight:700;

}


.qr-missing{

    color:
        var(--muted);

    font-size:7px;

    font-weight:700;

}


.registered-date{

    color:
        var(--muted);

    font-size:8px;

    line-height:1.4;

}


.queue{

    display:inline-flex;

    min-width:23px;

    align-items:center;

    justify-content:center;

    padding:
        4px 6px;

    border-radius:5px;

    background:#f0f2f5;

    color:
        var(--ink);

    font-size:7px;

    font-weight:700;

}


/* EMPTY */

.empty{

    padding:
        65px 25px;

    color:
        var(--muted);

    font-size:10px;

    text-align:center;

}


/* RESPONSIVE */

@media(max-width:1100px){

    .stats{

        grid-template-columns:
            repeat(2,1fr);

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
            CAMPUS360
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
        class="nav-link"
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
        class="nav-link active"
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
            Registrations
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
        Registration Administration
    </div>

    <h1>
        All Registrations
    </h1>

    <p>
        Monitor student registrations across every
        EventSphere event.
    </p>

</div>


<?php if ($loadError !== ''): ?>

    <div class="alert">
        <?= sanitize($loadError) ?>
    </div>

<?php endif; ?>


<!-- STATS -->

<div class="stats">


    <div class="stat">

        <div>

            <div class="stat-label">
                Total
            </div>

            <div class="stat-value">
                <?= number_format(
                    $totalRegistrations
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ♙
        </div>

    </div>


    <div class="stat">

        <div>

            <div class="stat-label">
                Confirmed
            </div>

            <div class="stat-value">
                <?= number_format(
                    $confirmedCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ✓
        </div>

    </div>


    <div class="stat">

        <div>

            <div class="stat-label">
                Waitlisted
            </div>

            <div class="stat-value">
                <?= number_format(
                    $waitlistedCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ◷
        </div>

    </div>


    <div class="stat">

        <div>

            <div class="stat-label">
                Completed
            </div>

            <div class="stat-value">
                <?= number_format(
                    $completedCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ✓
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

            <label for="search">
                Search
            </label>

            <input
                type="text"
                id="search"
                name="search"
                class="filter-control"
                value="<?= sanitize(
                    $search
                ) ?>"
                placeholder="Student, email, roll number, event..."
            >

        </div>


        <div class="filter-group">

            <label for="event_id">
                Event
            </label>

            <select
                id="event_id"
                name="event_id"
                class="filter-control"
            >

                <option value="">
                    All Events
                </option>


                <?php foreach (
                    $events
                    as $event
                ): ?>

                    <option
                        value="<?= sanitize(
                            $event['event_id']
                        ) ?>"
                        <?= $eventFilter ===
                            $event['event_id']
                            ? 'selected'
                            : '' ?>
                    >

                        <?= sanitize(
                            $event['title']
                        ) ?>

                    </option>

                <?php endforeach; ?>


            </select>

        </div>


        <div class="filter-group">

            <label for="status">
                Registration Status
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
                    $registrationStatuses
                    as $value => $label
                ): ?>

                    <option
                        value="<?= sanitize(
                            $value
                        ) ?>"
                        <?= $statusFilter ===
                            $value
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
                FILTER
            </button>


            <a
                href="registrations.php"
                class="clear-button"
            >
                CLEAR
            </a>

        </div>


    </form>


</div>


<!-- TABLE -->

<div class="table-card">


    <div class="table-header">


        <div>

            <h2>
                Registration Directory
            </h2>

            <p>
                Complete registration records across the platform.
            </p>

        </div>


        <div class="table-count">

            <?= number_format(
                count($registrations)
            ) ?>

            Registrations

        </div>


    </div>


    <?php if (!empty($registrations)): ?>


        <div class="table-wrapper">


            <table class="registration-table">


                <thead>

                    <tr>

                        <th>
                            Student
                        </th>

                        <th>
                            Roll Number
                        </th>

                        <th>
                            Event
                        </th>

                        <th>
                            Organizer
                        </th>

                        <th>
                            Registration ID
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            QR
                        </th>

                        <th>
                            Registered
                        </th>

                        <th>
                            Queue
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $registrations
                        as $registration
                    ): ?>


                        <?php

                        $status =
                            strtolower(
                                (string)(
                                    $registration[
                                        'status'
                                    ] ?? ''
                                )
                            );

                        $qrAvailable =
                            !empty(
                                $registration[
                                    'qr_hash'
                                ]
                            );

                        ?>


                        <tr>


                            <!-- STUDENT -->

                            <td>

                                <div class="student-name">

                                    <?= sanitize(
                                        $registration[
                                            'full_name'
                                        ]
                                        ??
                                        'Unknown Student'
                                    ) ?>

                                </div>


                                <div class="student-email">

                                    <?= sanitize(
                                        $registration[
                                            'email'
                                        ]
                                        ?? '—'
                                    ) ?>

                                </div>

                            </td>


                            <!-- ROLL -->

                            <td>

                                <div class="roll-number">

                                    <?= !empty(
                                        $registration[
                                            'roll_number'
                                        ]
                                    )
                                        ? sanitize(
                                            $registration[
                                                'roll_number'
                                            ]
                                        )
                                        : '—' ?>

                                </div>

                            </td>


                            <!-- EVENT -->

                            <td>

                                <div class="event-name">

                                    <?= sanitize(
                                        $registration[
                                            'event_title'
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <!-- ORGANIZER -->

                            <td>

                                <div class="organizer-name">

                                    <?= sanitize(
                                        $registration[
                                            'organizer_name'
                                        ]
                                        ?? 'Unassigned'
                                    ) ?>

                                </div>

                            </td>


                            <!-- REG ID -->

                            <td>

                                <div
                                    class="registration-id"
                                    title="<?= sanitize(
                                        $registration[
                                            'reg_id'
                                        ]
                                    ) ?>"
                                >

                                    <?= sanitize(
                                        $registration[
                                            'reg_id'
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        status
                                        <?= adminRegistrationStatusClass(
                                            $status
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $status
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- QR -->

                            <td>

                                <?php if (
                                    $qrAvailable
                                ): ?>

                                    <span
                                        class="qr-ready"
                                    >
                                        READY
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="qr-missing"
                                    >
                                        NOT READY
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <div
                                    class="registered-date"
                                >

                                    <?= sanitize(
                                        adminRegistrationDate(
                                            $registration[
                                                'registered_at'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- QUEUE -->

                            <td>

                                <?php if (
                                    !empty(
                                        $registration[
                                            'queue_position'
                                        ]
                                    )
                                ): ?>

                                    <span class="queue">

                                        #
                                        <?= (int)(
                                            $registration[
                                                'queue_position'
                                            ]
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        </div>


    <?php else: ?>


        <div class="empty">

            No registrations matched the selected filters.

        </div>


    <?php endif; ?>


</div>


</section>


</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
