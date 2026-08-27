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
| FILTER
|--------------------------------------------------------------------------
*/

$statusFilter = trim(
    $_GET['status'] ?? ''
);

$allowedStatuses = [
    'confirmed',
    'waitlisted',
    'cancelled',
    'completed'
];


/*
|--------------------------------------------------------------------------
| REGISTRATIONS
|--------------------------------------------------------------------------
*/

$registrations = [];

$errorMessage = '';

if ($pdoConnection instanceof PDO) {

    try {

        $sql = "
            SELECT

                r.reg_id,
                r.event_id,
                r.status,
                r.qr_hash,
                r.queue_position,
                r.registered_at,

                e.title,
                e.subtitle,
                e.category,
                e.start_date,
                e.end_date,
                e.approval_state,
                e.banner_image,

                v.venue_name

            FROM registrations r

            INNER JOIN events e
                ON e.event_id = r.event_id

            LEFT JOIN venues v
                ON v.venue_id = e.venue_id

            WHERE r.user_id = :user_id
        ";

        $params = [
            ':user_id' => $userId
        ];


        if (
            $statusFilter !== '' &&
            in_array(
                $statusFilter,
                $allowedStatuses,
                true
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
            'Student Registrations Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load your registrations.';
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

$totalCount     = count($registrations);
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

function myRegistrationStatusClass(
    string $status
): string {

    switch (strtolower($status)) {

        case 'confirmed':
            return 'status-confirmed';

        case 'waitlisted':
            return 'status-waitlisted';

        case 'cancelled':
            return 'status-cancelled';

        case 'completed':
            return 'status-completed';

        default:
            return 'status-default';
    }
}


function myRegistrationDate(
    ?string $date
): string {

    if (!$date) {
        return '—';
    }

    $time = strtotime($date);

    if (!$time) {
        return '—';
    }

    return date(
        'd M Y',
        $time
    );
}


function myRegistrationTime(
    ?string $date
): string {

    if (!$date) {
        return '—';
    }

    $time = strtotime($date);

    if (!$time) {
        return '—';
    }

    return date(
        'h:i A',
        $time
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
    My Registrations | Campus360
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

    max-width:1350px;

    margin:0 auto;

    padding:
        42px 40px 20px;
}


.intro{

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


/* STATS */

.stats{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

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

    margin-bottom:18px;

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

    width:260px;
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

    letter-spacing:.6px;
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


/* TABLE */

.table-card{

    overflow:hidden;

    background:#fff;

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

    gap:15px;

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


.registration-table{

    width:100%;

    min-width:1000px;

    border-collapse:collapse;
}


.registration-table th{

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


.registration-table td{

    padding:
        15px 14px;

    border-bottom:
        1px solid
        #edf0f3;

    vertical-align:middle;

    font-size:8px;
}


.registration-table tbody tr:hover{

    background:#fcfdff;
}


.event-name{

    max-width:250px;

    overflow:hidden;

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;
}


.event-category{

    display:inline-flex;

    margin-top:4px;

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

    font-weight:600;
}


.event-time{

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


.registration-id{

    max-width:140px;

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

    color:
        #9a711d;
}


.status-cancelled{

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


.status-default{

    background:
        #eef0f3;

    color:
        var(--muted);
}


.qr-ready{

    color:
        var(--green);

    font-size:7px;

    font-weight:700;
}


.qr-none{

    color:
        var(--muted);

    font-size:7px;

    font-weight:700;
}


.queue{

    display:inline-flex;

    min-width:27px;

    align-items:center;

    justify-content:center;

    padding:
        4px 7px;

    border-radius:5px;

    background:
        var(--gold-bg);

    color:
        #946c17;

    font-size:7px;

    font-weight:700;
}


.action{

    display:inline-flex;

    padding:
        7px 9px;

    border-radius:5px;

    background:
        var(--navy);

    color:#fff;

    font-size:6px;

    font-weight:700;

    letter-spacing:.5px;
}


.action:hover{

    background:
        var(--blue);
}


/* EMPTY */

.empty{

    padding:
        70px 25px;

    color:
        var(--muted);

    text-align:center;

    font-size:10px;
}


/* RESPONSIVE */

@media(max-width:1100px){

    .stats{

        grid-template-columns:
            repeat(2,1fr);
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

    .filter-form{

        flex-wrap:wrap;
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

    .filter-group{

        width:100%;
    }

    .filter-form{

        flex-direction:column;

        align-items:stretch;
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
            CAMPUS360
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
        class="nav-link active"
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
            My Registrations
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

    <div class="eyebrow">
        Student Activity
    </div>

    <h1>
        My Registrations
    </h1>

    <p>
        View and track all events you have registered
        for through Campus360.
    </p>

</div>


<?php if (
    $errorMessage !== ''
): ?>

    <div
        style="
            margin-bottom:20px;
            padding:13px 16px;
            border-radius:7px;
            background:#fff0f0;
            border:1px solid #efcccc;
            color:#b33a3a;
            font-size:10px;
        "
    >

        <?= sanitize(
            $errorMessage
        ) ?>

    </div>

<?php endif; ?>


<!-- STATS -->

<div class="stats">


    <div class="stat-card">

        <div class="stat-label">
            Total
        </div>

        <div class="stat-value">
            <?= number_format(
                $totalCount
            ) ?>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Confirmed
        </div>

        <div class="stat-value">
            <?= number_format(
                $confirmedCount
            ) ?>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Waitlisted
        </div>

        <div class="stat-value">
            <?= number_format(
                $waitlistedCount
            ) ?>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Completed
        </div>

        <div class="stat-value">
            <?= number_format(
                $completedCount
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

        <label for="status">
            Registration Status
        </label>


        <select
            id="status"
            name="status"
            class="filter-select"
        >

            <option value="">
                All Registrations
            </option>


            <?php foreach (
                $allowedStatuses
                as $status
            ): ?>

                <option
                    value="<?= sanitize(
                        $status
                    ) ?>"
                    <?= $statusFilter === $status
                        ? 'selected'
                        : '' ?>
                >

                    <?= sanitize(
                        ucfirst(
                            $status
                        )
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <button
        type="submit"
        class="filter-button"
    >
        FILTER
    </button>


    <a
        href="my-registrations.php"
        class="clear-button"
    >
        CLEAR
    </a>


</form>


</div>


<!-- TABLE -->

<div class="table-card">


<div class="table-header">


    <div>

        <h2>
            Registration History
        </h2>

        <p>
            Your event registration records.
        </p>

    </div>


    <div class="table-count">

        <?= number_format(
            count($registrations)
        ) ?>

        Records

    </div>


</div>


<?php if (
    !empty($registrations)
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
        Event Date
    </th>

    <th>
        Venue
    </th>

    <th>
        Status
    </th>

    <th>
        QR Ticket
    </th>

    <th>
        Queue
    </th>

    <th>
        Registered
    </th>

    <th>
        Action
    </th>

</tr>

</thead>


<tbody>


<?php foreach (
    $registrations
    as $registration
): ?>


<tr>


<td>

    <div
        class="event-name"
        title="<?= sanitize(
            $registration['title']
        ) ?>"
    >

        <?= sanitize(
            $registration['title']
        ) ?>

    </div>


    <span class="event-category">

        <?= sanitize(
            ucfirst(
                strtolower(
                    $registration['category']
                )
            )
        ) ?>

    </span>

</td>


<td>

    <div class="event-date">

        <?= sanitize(
            myRegistrationDate(
                $registration[
                    'start_date'
                ]
            )
        ) ?>

    </div>

    <div class="event-time">

        <?= sanitize(
            myRegistrationTime(
                $registration[
                    'start_date'
                ]
            )
        ) ?>

    </div>

</td>


<td>

    <div class="venue">

        <?= !empty(
            $registration[
                'venue_name'
            ]
        )
            ? sanitize(
                $registration[
                    'venue_name'
                ]
            )
            : 'TBA' ?>

    </div>

</td>


<td>

    <span
        class="
            status
            <?= myRegistrationStatusClass(
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

        <span class="qr-ready">
            READY
        </span>

    <?php else: ?>

        <span class="qr-none">
            NOT READY
        </span>

    <?php endif; ?>

</td>


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


<td>

    <div class="event-time">

        <?= sanitize(
            myRegistrationDate(
                $registration[
                    'registered_at'
                ]
            )
        ) ?>

    </div>

</td>


<td>

    <a
        href="event-details.php?event_id=<?= urlencode(
            $registration[
                'event_id'
            ]
        ) ?>"
        class="action"
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

    You do not have any registrations matching this filter.

    <br><br>

    <a
        href="events.php"
        style="
            color:#c99a3e;
            font-weight:700;
        "
    >
        Browse Events →

    </a>

</div>


<?php endif; ?>


</div>


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>
