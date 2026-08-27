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
$initial = strtoupper(substr(trim($userName), 0, 1));

/*
|--------------------------------------------------------------------------
| MESSAGE ACTIONS
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| MARK AS READ
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'read' &&
    !empty($_GET['id'])
) {

    try {

        $stmt = $pdo->prepare("
            UPDATE contact_messages
            SET status = 'read'
            WHERE message_id = ?
        ");

        $stmt->execute([
            $_GET['id']
        ]);

        $successMessage = 'Message marked as read.';

    } catch (PDOException $e) {

        error_log(
            'Contact Message Read Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to update the message.';
    }
}


/*
|--------------------------------------------------------------------------
| MARK AS REPLIED
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'replied' &&
    !empty($_GET['id'])
) {

    try {

        $stmt = $pdo->prepare("
            UPDATE contact_messages
            SET status = 'replied'
            WHERE message_id = ?
        ");

        $stmt->execute([
            $_GET['id']
        ]);

        $successMessage = 'Message marked as replied.';

    } catch (PDOException $e) {

        error_log(
            'Contact Message Replied Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to update the message.';
    }
}


/*
|--------------------------------------------------------------------------
| DELETE MESSAGE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'delete' &&
    !empty($_GET['id'])
) {

    try {

        $stmt = $pdo->prepare("
            DELETE FROM contact_messages
            WHERE message_id = ?
        ");

        $stmt->execute([
            $_GET['id']
        ]);

        $successMessage = 'Contact message deleted successfully.';

    } catch (PDOException $e) {

        error_log(
            'Contact Message Delete Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to delete the message.';
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH AND FILTER
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$statusFilter =
    $_GET['status'] ?? '';


/*
|--------------------------------------------------------------------------
| LOAD MESSAGES
|--------------------------------------------------------------------------
*/

$messages = [];

try {

    $sql = "
        SELECT
            message_id,
            name,
            email,
            subject,
            message,
            status,
            created_at,
            updated_at
        FROM contact_messages
        WHERE 1=1
    ";

    $params = [];


    /*
    | Search
    */

    if ($search !== '') {

        $sql .= "
            AND (
                name LIKE ?
                OR email LIKE ?
                OR subject LIKE ?
                OR message LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }


    /*
    | Status
    */

    if (
        in_array(
            $statusFilter,
            [
                'new',
                'read',
                'replied'
            ],
            true
        )
    ) {

        $sql .= "
            AND status = ?
        ";

        $params[] =
            $statusFilter;
    }


    $sql .= "
        ORDER BY created_at DESC
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    $messages =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    error_log(
        'Contact Messages Load Error: ' .
        $e->getMessage()
    );

    $errorMessage =
        'Contact messages could not be loaded.';
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalMessages = 0;
$newMessages = 0;
$readMessages = 0;
$repliedMessages = 0;

try {

    $totalMessages =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM contact_messages
            ")
            ->fetchColumn();


    $newMessages =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM contact_messages
                WHERE status = 'new'
            ")
            ->fetchColumn();


    $readMessages =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM contact_messages
                WHERE status = 'read'
            ")
            ->fetchColumn();


    $repliedMessages =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM contact_messages
                WHERE status = 'replied'
            ")
            ->fetchColumn();


} catch (PDOException $e) {

    error_log(
        'Contact Message Statistics Error: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function contactStatusClass(
    string $status
): string {

    switch (
        strtolower($status)
    ) {

        case 'new':
            return 'status-new';

        case 'read':
            return 'status-read';

        case 'replied':
            return 'status-replied';

        default:
            return 'status-new';
    }
}


function contactDateTime(
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
    Contact Messages | CEventSphere
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

/* =========================================================
   THEME
========================================================= */

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


/* =========================================================
   BASE
========================================================= */

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


/* =========================================================
   SIDEBAR
========================================================= */

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


/* =========================================================
   MAIN
========================================================= */

.main{

    min-height:100vh;

    margin-left:255px;
}


/* =========================================================
   TOPBAR
========================================================= */

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


.logout-link{

    display:inline-block;

    margin-top:2px;

    color:
        var(--gold);

    font-size:8px;

    font-weight:700;
}


.logout-link:hover{

    color:
        var(--navy);
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


/* =========================================================
   CONTENT
========================================================= */

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


/* =========================================================
   ALERT
========================================================= */

.alert{

    margin-bottom:20px;

    padding:
        13px 16px;

    border-radius:7px;

    font-size:10px;

    font-weight:600;
}


.alert-success{

    background:
        var(--green-bg);

    border:
        1px solid
        #cbe7d6;

    color:
        var(--green);
}


.alert-error{

    background:
        var(--red-bg);

    border:
        1px solid
        #efd0d0;

    color:
        var(--red);
}


/* =========================================================
   STATISTICS
========================================================= */

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


/* =========================================================
   MANAGEMENT CARD
========================================================= */

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


/* =========================================================
   TOOLBAR
========================================================= */

.toolbar{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;

    padding:
        17px 20px;

    background:
        #fafbfd;

    border-bottom:
        1px solid
        var(--line);

    flex-wrap:wrap;
}


.search-form{

    display:flex;

    align-items:center;

    gap:8px;

    flex:1;

    min-width:280px;
}


.search-input{

    width:100%;

    height:38px;

    padding:
        0 12px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--ink);

    font-family:
        "DM Sans",
        sans-serif;

    font-size:10px;

    outline:none;
}


.search-input:focus{

    border-color:
        var(--gold);

    box-shadow:
        0 0 0 3px
        rgba(201,154,62,.08);
}


.search-btn{

    height:38px;

    padding:
        0 17px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    font-family:
        "DM Sans",
        sans-serif;

    font-size:9px;

    font-weight:700;

    cursor:pointer;
}


.search-btn:hover{

    background:
        var(--blue);
}


.filter-form select{

    height:38px;

    min-width:130px;

    padding:
        0 10px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--ink);

    font-family:
        "DM Sans",
        sans-serif;

    font-size:9px;

    outline:none;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper{

    width:100%;

    overflow-x:auto;
}


.messages-table{

    width:100%;

    min-width:1050px;

    border-collapse:collapse;
}


.messages-table th{

    padding:
        12px 14px;

    background:
        #fafbfd;

    border-bottom:
        1px solid
        var(--line);

    color:
        var(--muted);

    font-size:7px;

    font-weight:700;

    letter-spacing:.8px;

    text-align:left;

    text-transform:uppercase;
}


.messages-table td{

    padding:
        15px 14px;

    border-bottom:
        1px solid
        #edf0f3;

    vertical-align:top;

    font-size:9px;
}


.messages-table tbody tr:hover{

    background:
        #fcfdff;
}


.sender-name{

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;
}


.sender-email{

    margin-top:2px;

    color:
        var(--muted);

    font-size:8px;
}


.message-subject{

    max-width:210px;

    color:
        var(--navy);

    font-size:9px;

    font-weight:700;
}


.message-text{

    max-width:360px;

    color:
        var(--muted);

    font-size:8px;

    line-height:1.55;
}


.message-date{

    white-space:nowrap;

    color:
        var(--muted);

    font-size:8px;

    line-height:1.5;
}


/* =========================================================
   STATUS
========================================================= */

.status{

    display:inline-flex;

    padding:
        5px 8px;

    border-radius:20px;

    font-size:6px;

    font-weight:700;

    letter-spacing:.6px;

    text-transform:uppercase;
}


.status-new{

    background:
        var(--gold-bg);

    color:
        #9a711d;
}


.status-read{

    background:
        var(--blue-bg);

    color:
        var(--blue);
}


.status-replied{

    background:
        var(--green-bg);

    color:
        var(--green);
}


/* =========================================================
   ACTIONS
========================================================= */

.actions{

    display:flex;

    align-items:center;

    gap:5px;

    flex-wrap:wrap;

    min-width:145px;
}


.action-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        6px 8px;

    border-radius:5px;

    font-size:7px;

    font-weight:700;

    transition:.2s;
}


.action-btn:hover{

    transform:
        translateY(-1px);
}


.read-btn{

    background:
        var(--blue-bg);

    color:
        var(--blue);
}


.reply-btn{

    background:
        var(--green-bg);

    color:
        var(--green);
}


.delete-btn{

    background:
        var(--red-bg);

    color:
        var(--red);
}


/* =========================================================
   EMPTY
========================================================= */

.empty{

    padding:
        55px 20px;

    color:
        var(--muted);

    font-size:9px;

    text-align:center;
}


.empty-title{

    margin-bottom:5px;

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:18px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1150px){

    .stat-grid{

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


    .toolbar{

        align-items:stretch;

        flex-direction:column;
    }


    .search-form{

        min-width:100%;
    }


    .filter-form{

        width:100%;
    }


    .filter-form select{

        width:100%;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

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

        <span class="nav-icon">
            ▦
        </span>

        <span>
            Dashboard
        </span>

    </a>


    <a
        href="contact-messages.php"
        class="nav-link active"
    >

        <span class="nav-icon">
            ✉
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


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<!-- TOPBAR -->

<header class="topbar">


    <div class="topbar-left">

        <span class="topbar-label">
            Administration
        </span>


        <div class="page-title">
            Contact Messages
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

            <a
                href="../../logout.php"
                class="logout-link"
            >
                Logout
            </a>

        </div>


        <div class="avatar">

            <?= sanitize($initial) ?>

        </div>

    </div>


</header>


<!-- CONTENT -->

<section class="content">


    <!-- INTRO -->

    <div class="intro">


        <div>

            <div class="eyebrow">
               EventSphere  Administration
            </div>


            <h1>
                Contact Messages
            </h1>


            <p>
                View and manage messages submitted
                through theEventSphere website contact form.
            </p>

        </div>


    </div>


    <!-- ALERTS -->


    <?php if ($successMessage !== ''): ?>

        <div class="alert alert-success">

            <?= sanitize(
                $successMessage
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-error">

            <?= sanitize(
                $errorMessage
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         STATISTICS
    ================================================= -->

    <div class="stat-grid">


        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Total Messages
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $totalMessages
                    ) ?>
                </div>

            </div>


            <div class="stat-icon">
                ✉
            </div>

        </div>


        <div class="stat-card">

            <div>

                <div class="stat-label">
                    New Messages
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $newMessages
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
                    Read Messages
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $readMessages
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
                    Replied
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $repliedMessages
                    ) ?>
                </div>

            </div>


            <div class="stat-icon">
                ✓
            </div>

        </div>


    </div>


    <!-- =================================================
         MESSAGE MANAGEMENT
    ================================================= -->

    <div class="card">


        <div class="card-header">


            <div>

                <h2>
                    Website Enquiries
                </h2>

                <p>
                    Messages received from website visitors.
                </p>

            </div>


        </div>


        <!-- SEARCH -->

        <div class="toolbar">


            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search name, email, subject or message..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <button
                    type="submit"
                    class="search-btn"
                >
                    Search
                </button>

            </form>


            <form
                method="GET"
                class="filter-form"
            >

                <?php if ($search !== ''): ?>

                    <input
                        type="hidden"
                        name="search"
                        value="<?= htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                <?php endif; ?>


                <select
                    name="status"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        All Status
                    </option>


                    <option
                        value="new"
                        <?= $statusFilter === 'new'
                            ? 'selected'
                            : '' ?>
                    >
                        New
                    </option>


                    <option
                        value="read"
                        <?= $statusFilter === 'read'
                            ? 'selected'
                            : '' ?>
                    >
                        Read
                    </option>


                    <option
                        value="replied"
                        <?= $statusFilter === 'replied'
                            ? 'selected'
                            : '' ?>
                    >
                        Replied
                    </option>

                </select>

            </form>


        </div>


        <!-- TABLE -->

        <?php if (!empty($messages)): ?>


            <div class="table-wrapper">


                <table class="messages-table">


                    <thead>

                        <tr>

                            <th>
                                Visitor
                            </th>

                            <th>
                                Subject
                            </th>

                            <th>
                                Message
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Received
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $messages
                        as $msg
                    ): ?>


                        <tr>


                            <!-- VISITOR -->

                            <td>

                                <div class="sender-name">

                                    <?= sanitize(
                                        $msg['name']
                                    ) ?>

                                </div>


                                <div class="sender-email">

                                    <?= sanitize(
                                        $msg['email']
                                    ) ?>

                                </div>

                            </td>


                            <!-- SUBJECT -->

                            <td>

                                <div class="message-subject">

                                    <?= sanitize(
                                        $msg['subject']
                                    ) ?>

                                </div>

                            </td>


                            <!-- MESSAGE -->

                            <td>

                                <div class="message-text">

                                    <?= nl2br(
                                        sanitize(
                                            $msg['message']
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        status
                                        <?= contactStatusClass(
                                            $msg['status']
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $msg['status']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- DATE -->

                            <td>

                                <div class="message-date">

                                    <?= sanitize(
                                        contactDateTime(
                                            $msg['created_at']
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <?php if (
                                        $msg['status']
                                        ===
                                        'new'
                                    ): ?>

                                        <a
                                            href="contact-messages.php?action=read&id=<?= urlencode(
                                                $msg['message_id']
                                            ) ?>"
                                            class="action-btn read-btn"
                                        >
                                            Mark Read
                                        </a>

                                    <?php endif; ?>


                                    <?php if (
                                        $msg['status']
                                        !==
                                        'replied'
                                    ): ?>

                                        <a
                                            href="contact-messages.php?action=replied&id=<?= urlencode(
                                                $msg['message_id']
                                            ) ?>"
                                            class="action-btn reply-btn"
                                        >
                                            Mark Replied
                                        </a>

                                    <?php endif; ?>


                                    <a
                                        href="contact-messages.php?action=delete&id=<?= urlencode(
                                            $msg['message_id']
                                        ) ?>"
                                        class="action-btn delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this contact message?');"
                                    >
                                        Delete
                                    </a>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <div class="empty">

                <div class="empty-title">
                    No Contact Messages
                </div>

                <div>
                    No messages were found for the
                    current search or filter.
                </div>

            </div>


        <?php endif; ?>


    </div>


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>
