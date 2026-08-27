<?php

require_once __DIR__ . '/config/database.php';
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
| ALLOWED STATUS
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
| EVENTS FOR FILTER
|--------------------------------------------------------------------------
*/

$organizerEvents = [];

if ($pdoConnection instanceof PDO) {

    try {

        $stmt = $pdoConnection->prepare("
            SELECT
                event_id,
                title
            FROM events
            WHERE organizer_id = :organizer_id
            ORDER BY start_date DESC
        ");

        $stmt->execute([
            ':organizer_id' => $userId
        ]);

        $organizerEvents =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Registration Events Error: ' .
            $e->getMessage()
        );
    }
}


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
                e.approval_state

            FROM registrations r

            INNER JOIN users u
                ON u.user_id = r.user_id

            INNER JOIN events e
                ON e.event_id = r.event_id

            WHERE e.organizer_id = :organizer_id
        ";

        $params = [
            ':organizer_id' => $userId
        ];


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
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | EVENT FILTER
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
        | STATUS FILTER
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
            'Organizer Registration Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load registrations.';

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

function registrationStatusClass(
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


function registrationStatusLabel(
    string $status
): string {

    return ucfirst(
        strtolower(
            $status
        )
    );
}


function formatRegistrationDate(
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
    Registrations | EventSphere
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

    width:250px;
    height:100vh;

    padding:24px 16px;

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

    max-width:1250px;

    margin:auto;

    padding:
        42px 40px 60px;

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

    max-width:700px;

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


/* SUMMARY */

.summary{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:22px;

}


.summary-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:17px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:9px;

    box-shadow:
        var(--shadow);

}


.summary-label{

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.8px;

    text-transform:uppercase;

}


.summary-value{

    margin-top:5px;

    color:
        var(--navy);

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
        1px solid
        var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

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

    justify-content:space-between;

    align-items:center;

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

    letter-spacing:.7px;

    text-transform:uppercase;

}


.table-wrapper{

    width:100%;

    overflow-x:auto;

}


.registration-table{

    width:100%;

    min-width:1050px;

    border-collapse:collapse;

}


.registration-table th{

    padding:
        12px 15px;

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
        14px 15px;

    border-bottom:
        1px solid
        #edf0f3;

    vertical-align:middle;

    font-size:9px;

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

    font-size:8px;

}


.roll{

    color:
        var(--ink);

    font-size:9px;

    font-weight:600;

}


.event-name{

    max-width:220px;

    overflow:hidden;

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;

}


.registration-id{

    max-width:130px;

    overflow:hidden;

    color:
        var(--muted);

    font-family:
        monospace;

    font-size:8px;

    text-overflow:ellipsis;

    white-space:nowrap;

}


.reg-status{

    display:inline-flex;

    padding:
        5px 8px;

    border-radius:20px;

    font-size:7px;

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


.status-completed{

    background:
        var(--blue-bg);

    color:
        var(--blue);

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


.qr-status{

    font-size:8px;

    font-weight:700;

}


.qr-available{

    color:
        var(--green);

}


.qr-missing{

    color:
        var(--muted);

}


.registered{

    color:
        var(--muted);

    font-size:8px;

    line-height:1.5;

}


/* QUEUE */

.queue{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    min-width:24px;

    padding:
        4px 6px;

    border-radius:5px;

    background:#f1f3f6;

    color:
        var(--ink);

    font-size:8px;

    font-weight:700;

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

    background:
        var(--gold-bg);

    color:
        var(--gold);

    font-size:25px;

}


.empty h3{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}


.empty p{

    max-width:390px;

    margin:
        7px auto 17px;

    color:
        var(--muted);

    font-size:10px;

}


.empty a{

    display:inline-flex;

    padding:
        10px 15px;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    font-size:8px;

    font-weight:700;

}


@media(max-width:1100px){

    .summary{

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


    .summary{

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
        class="nav-link"
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
        class="nav-link active"
    >

        <span class="nav-icon">
            ♙
        </span>

        <span>
            Registrations
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


<!-- MAIN -->

<main class="main">


<header class="topbar">


    <div class="topbar-left">

        <span class="topbar-label">
            Organizer Portal
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
                Event Organizer
            </span>

        </div>


        <div class="avatar">
            <?= sanitize($initial) ?>
        </div>


    </div>


</header>


<section class="content">


    <!-- INTRO -->

    <div class="intro">


        <div class="eyebrow">
            Registration Management
        </div>


        <h1>
            Event Registrations
        </h1>


        <p>
            View and monitor students registered
            for your EventSphereevents.
        </p>


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



    <!-- SUMMARY -->

    <div class="summary">


        <div class="summary-card">

            <div>

                <div class="summary-label">
                    Total
                </div>

                <div class="summary-value">
                    <?= number_format(
                        $totalRegistrations
                    ) ?>
                </div>

            </div>

            <div class="summary-icon">
                ♙
            </div>

        </div>


        <div class="summary-card">

            <div>

                <div class="summary-label">
                    Confirmed
                </div>

                <div class="summary-value">
                    <?= number_format(
                        $confirmedCount
                    ) ?>
                </div>

            </div>

            <div class="summary-icon">
                ✓
            </div>

        </div>


        <div class="summary-card">

            <div>

                <div class="summary-label">
                    Waitlisted
                </div>

                <div class="summary-value">
                    <?= number_format(
                        $waitlistedCount
                    ) ?>
                </div>

            </div>

            <div class="summary-icon">
                ◷
            </div>

        </div>


        <div class="summary-card">

            <div>

                <div class="summary-label">
                    Completed
                </div>

                <div class="summary-value">
                    <?= number_format(
                        $completedCount
                    ) ?>
                </div>

            </div>

            <div class="summary-icon">
                ✓
            </div>

        </div>


    </div>



    <!-- FILTERS -->

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
                    placeholder="Student, email, roll number..."
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
                        $organizerEvents
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
                    Status
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
                    Registered Students
                </h2>

                <p>
                    Registration records belonging
                    to your events.
                </p>

            </div>


            <div class="table-count">

                <?= number_format(
                    $totalRegistrations
                ) ?>

                Registrations

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
                                Student
                            </th>

                            <th>
                                Roll Number
                            </th>

                            <th>
                                Event
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
                                        $registration['status']
                                        ?? ''
                                    )
                                );

                            $qrExists =
                                !empty(
                                    $registration['qr_hash']
                                );

                            ?>


                            <tr>


                                <!-- STUDENT -->

                                <td>

                                    <div
                                        class="student-name"
                                    >

                                        <?= sanitize(
                                            $registration[
                                                'full_name'
                                            ]
                                            ?? 'Unknown Student'
                                        ) ?>

                                    </div>


                                    <div
                                        class="student-email"
                                    >

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

                                    <div
                                        class="roll"
                                    >

                                        <?= sanitize(
                                            $registration[
                                                'roll_number'
                                            ]
                                            ?? '—'
                                        ) ?>

                                    </div>

                                </td>



                                <!-- EVENT -->

                                <td>

                                    <div
                                        class="event-name"
                                    >

                                        <?= sanitize(
                                            $registration[
                                                'event_title'
                                            ]
                                            ?? 'Unknown Event'
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
                                            reg-status
                                            <?= registrationStatusClass(
                                                $status
                                            ) ?>
                                        "
                                    >

                                        <?= sanitize(
                                            registrationStatusLabel(
                                                $status
                                            )
                                        ) ?>

                                    </span>

                                </td>



                                <!-- QR -->

                                <td>

                                    <?php if (
                                        $qrExists
                                    ): ?>

                                        <span
                                            class="
                                                qr-status
                                                qr-available
                                            "
                                        >
                                            READY
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                qr-status
                                                qr-missing
                                            "
                                        >
                                            NOT READY
                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- DATE -->

                                <td>

                                    <div
                                        class="registered"
                                    >

                                        <?= sanitize(
                                            formatRegistrationDate(
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

                                        <span
                                            class="queue"
                                        >

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


                <div class="empty-icon">
                    ♙
                </div>


                <?php if (
                    $search !== '' ||
                    $eventFilter !== '' ||
                    $statusFilter !== ''
                ): ?>


                    <h3>
                        No Matching Registrations
                    </h3>


                    <p>
                        No registration records match
                        your current filters.
                    </p>


                    <a
                        href="registrations.php"
                    >
                        CLEAR FILTERS
                    </a>


                <?php else: ?>


                    <h3>
                        No Registrations Yet
                    </h3>


                    <p>
                        Students who register for your
                        events will appear here.
                    </p>

                <?php endif; ?>


            </div>


        <?php endif; ?>


    </div>


</section>


</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

</body>

</html>