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

if (
    isset($pdo) &&
    $pdo instanceof PDO
) {
    $pdoConnection = $pdo;

} elseif (
    isset($db) &&
    $db instanceof PDO
) {
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

$actionFilter = trim(
    $_GET['action'] ?? ''
);


/*
|--------------------------------------------------------------------------
| AUDIT LOGS
|--------------------------------------------------------------------------
*/

$logs = [];

$errorMessage = '';


if (
    $pdoConnection instanceof PDO
) {

    try {

        $sql = "
            SELECT

                a.log_id,
                a.user_id,
                a.action,
                a.details,
                a.ip_address,
                a.created_at,

                u.full_name,
                u.email,
                u.role

            FROM audit_logs a

            LEFT JOIN users u
                ON u.user_id = a.user_id

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
                    a.log_id LIKE :search
                    OR a.action LIKE :search
                    OR a.ip_address LIKE :search
                    OR a.user_id LIKE :search
                    OR u.full_name LIKE :search
                    OR u.email LIKE :search
                    OR CAST(a.details AS CHAR) LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | ACTION FILTER
        |--------------------------------------------------------------------------
        */

        if ($actionFilter !== '') {

            $sql .= "
                AND a.action = :action
            ";

            $params[':action'] =
                $actionFilter;
        }


        $sql .= "
            ORDER BY
                a.created_at DESC
        ";


        $stmt =
            $pdoConnection->prepare(
                $sql
            );

        $stmt->execute(
            $params
        );


        $logs =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


    } catch (PDOException $e) {

        error_log(
            'Admin Audit Logs Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load audit logs.';
    }

} else {

    $errorMessage =
        'Database connection is not available.';
}


/*
|--------------------------------------------------------------------------
| ACTION LIST
|--------------------------------------------------------------------------
*/

$actionNames = [];

foreach (
    $logs as $log
) {

    $action =
        trim(
            (string)(
                $log['action'] ?? ''
            )
        );

    if (
        $action !== '' &&
        !in_array(
            $action,
            $actionNames,
            true
        )
    ) {

        $actionNames[] =
            $action;
    }
}

sort(
    $actionNames
);


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalLogs =
    count($logs);

$todayLogs = 0;

$today =
    date('Y-m-d');


foreach (
    $logs as $log
) {

    if (
        !empty(
            $log['created_at']
        ) &&
        date(
            'Y-m-d',
            strtotime(
                $log['created_at']
            )
        ) === $today
    ) {

        $todayLogs++;
    }
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function auditDate(
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


function auditActionLabel(
    string $action
): string {

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


function auditActionClass(
    string $action
): string {

    $action =
        strtolower($action);

    if (
        str_contains(
            $action,
            'delete'
        )
    ) {
        return 'action-danger';
    }

    if (
        str_contains(
            $action,
            'reject'
        )
    ) {
        return 'action-danger';
    }

    if (
        str_contains(
            $action,
            'approve'
        )
    ) {
        return 'action-success';
    }

    if (
        str_contains(
            $action,
            'create'
        )
    ) {
        return 'action-create';
    }

    if (
        str_contains(
            $action,
            'update'
        )
    ) {
        return 'action-update';
    }

    if (
        str_contains(
            $action,
            'publish'
        )
    ) {
        return 'action-success';
    }

    return 'action-default';
}


function auditDetails(
    $details
): string {

    if (
        $details === null ||
        $details === ''
    ) {
        return 'No additional details.';
    }


    if (
        is_array($details)
    ) {

        return json_encode(
            $details,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES
        );
    }


    $decoded =
        json_decode(
            $details,
            true
        );


    if (
        json_last_error() === JSON_ERROR_NONE &&
        is_array($decoded)
    ) {

        return json_encode(
            $decoded,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES
        );
    }


    return (string)$details;
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
    Audit Logs | EventSphere
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

    padding:
        24px 16px;

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

    max-width:760px;

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

    background:
        var(--red-bg);

    border:
        1px solid #efcccc;

    border-radius:7px;

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


.stat{

    padding:18px;

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

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

}


/* LOG CARD */

.logs-card{

    overflow:hidden;

    background:white;

    border:
        1px solid var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);

}


.logs-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        21px 22px;

    border-bottom:
        1px solid var(--line);

}


.logs-header h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}


.logs-header p{

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;

}


.log-count{

    color:
        var(--gold);

    font-size:9px;

    font-weight:700;

    text-transform:uppercase;

}


/* TABLE */

.table-wrapper{

    width:100%;

    overflow-x:auto;

}


.logs-table{

    width:100%;

    min-width:1150px;

    border-collapse:collapse;

}


.logs-table th{

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


.logs-table td{

    padding:
        14px;

    border-bottom:
        1px solid #edf0f3;

    vertical-align:top;

    font-size:8px;

}


.logs-table tbody tr:hover{

    background:#fcfdff;

}


/* USER */

.log-user{

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;

}


.log-email{

    margin-top:3px;

    color:
        var(--muted);

    font-size:7px;

}


.log-role{

    display:inline-flex;

    margin-top:5px;

    padding:
        3px 6px;

    border-radius:20px;

    background:
        var(--blue-bg);

    color:
        var(--blue);

    font-size:6px;

    font-weight:700;

    text-transform:uppercase;

}


/* ACTION */

.action-badge{

    display:inline-flex;

    padding:
        5px 8px;

    border-radius:20px;

    font-size:6px;

    font-weight:700;

    letter-spacing:.4px;

    text-transform:uppercase;

}


.action-default{

    background:
        #eef0f3;

    color:
        var(--muted);

}


.action-success{

    background:
        var(--green-bg);

    color:
        var(--green);

}


.action-danger{

    background:
        var(--red-bg);

    color:
        var(--red);

}


.action-create{

    background:
        var(--gold-bg);

    color:
        #986e17;

}


.action-update{

    background:
        var(--blue-bg);

    color:
        var(--blue);

}


/* ID */

.log-id{

    max-width:160px;

    overflow:hidden;

    color:#8791a1;

    font-family:monospace;

    font-size:7px;

    text-overflow:ellipsis;

    white-space:nowrap;

}


/* DETAILS */

.details{

    max-width:320px;

}


.details pre{

    max-height:110px;

    overflow:auto;

    margin:0;

    padding:9px;

    border:
        1px solid var(--line);

    border-radius:6px;

    background:
        #fafbfd;

    color:
        #566174;

    font-family:
        monospace;

    font-size:7px;

    line-height:1.5;

    white-space:pre-wrap;

    word-break:break-word;

}


/* IP */

.ip{

    color:
        var(--ink);

    font-family:
        monospace;

    font-size:8px;

}


/* TIME */

.log-time{

    color:
        var(--muted);

    font-size:8px;

    line-height:1.5;

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

@media(max-width:1000px){

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
        class="nav-link active"
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
            Audit Logs
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
        System Security
    </div>

    <h1>
        Audit Logs
    </h1>

    <p>
        Review administrative and system activity
        recorded across EventSphere platform.
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


<!-- STATS -->

<div class="stats">


    <div class="stat">

        <div class="stat-label">
            Filtered Records
        </div>

        <div class="stat-value">
            <?= number_format(
                $totalLogs
            ) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Today's Activity
        </div>

        <div class="stat-value">
            <?= number_format(
                $todayLogs
            ) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Action Types
        </div>

        <div class="stat-value">
            <?= number_format(
                count($actionNames)
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

        <label for="search">
            Search Activity
        </label>

        <input
            type="text"
            id="search"
            name="search"
            class="filter-control"
            value="<?= sanitize(
                $search
            ) ?>"
            placeholder="User, action, IP, details..."
        >

    </div>


    <div class="filter-group">

        <label for="action">
            Action
        </label>

        <select
            id="action"
            name="action"
            class="filter-control"
        >

            <option value="">
                All Actions
            </option>


            <?php foreach (
                $actionNames
                as $action
            ): ?>

                <option
                    value="<?= sanitize(
                        $action
                    ) ?>"
                    <?= $actionFilter === $action
                        ? 'selected'
                        : '' ?>
                >

                    <?= sanitize(
                        auditActionLabel(
                            $action
                        )
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
            href="audit-logs.php"
            class="clear-button"
        >
            CLEAR
        </a>

    </div>


</form>


</div>


<!-- LOGS -->

<div class="logs-card">


<div class="logs-header">


    <div>

        <h2>
            Activity History
        </h2>

        <p>
            Latest recorded system actions.
        </p>

    </div>


    <div class="log-count">

        <?= number_format(
            count($logs)
        ) ?>

        Records

    </div>


</div>


<?php if (
    !empty($logs)
): ?>


<div class="table-wrapper">


<table class="logs-table">


<thead>

<tr>

    <th>
        User
    </th>

    <th>
        Action
    </th>

    <th>
        Details
    </th>

    <th>
        IP Address
    </th>

    <th>
        Date & Time
    </th>

    <th>
        Log ID
    </th>

</tr>

</thead>


<tbody>


<?php foreach (
    $logs
    as $log
): ?>


<tr>


<!-- USER -->

<td>

    <?php if (
        !empty(
            $log['full_name']
        )
    ): ?>

        <div class="log-user">

            <?= sanitize(
                $log['full_name']
            ) ?>

        </div>


        <div class="log-email">

            <?= sanitize(
                $log['email']
                ?? ''
            ) ?>

        </div>


        <?php if (
            !empty(
                $log['role']
            )
        ): ?>

            <span class="log-role">

                <?= sanitize(
                    ucfirst(
                        $log['role']
                    )
                ) ?>

            </span>

        <?php endif; ?>


    <?php else: ?>

        <div class="log-user">
            System
        </div>

    <?php endif; ?>

</td>


<!-- ACTION -->

<td>

    <span
        class="
            action-badge
            <?= auditActionClass(
                $log['action']
                ?? ''
            ) ?>
        "
    >

        <?= sanitize(
            auditActionLabel(
                $log['action']
                ?? ''
            )
        ) ?>

    </span>

</td>


<!-- DETAILS -->

<td>

    <div class="details">

        <pre><?= sanitize(
            auditDetails(
                $log['details']
                ?? null
            )
        ) ?></pre>

    </div>

</td>


<!-- IP -->

<td>

    <div class="ip">

        <?= !empty(
            $log['ip_address']
        )
            ? sanitize(
                $log['ip_address']
            )
            : '—' ?>

    </div>

</td>


<!-- TIME -->

<td>

    <div class="log-time">

        <?= sanitize(
            auditDate(
                $log['created_at']
            )
        ) ?>

    </div>

</td>


<!-- ID -->

<td>

    <div
        class="log-id"
        title="<?= sanitize(
            $log['log_id']
        ) ?>"
    >

        <?= sanitize(
            $log['log_id']
        ) ?>

    </div>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">

    No audit activity matched the selected filters.

</div>


<?php endif; ?>


</div>


</section>


</main>

<?php require_once __DIR__ . '/footer.php'; ?>
</body>

</html>
