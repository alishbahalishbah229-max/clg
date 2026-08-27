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
| TICKETS
|--------------------------------------------------------------------------
*/

$tickets = [];

$errorMessage = '';

if ($pdoConnection instanceof PDO) {

    try {

        $stmt =
            $pdoConnection->prepare("
                SELECT

                    r.reg_id,
                    r.status,
                    r.registered_at,
                    r.queue_position,

                    e.event_id,
                    e.title,
                    e.subtitle,
                    e.category,
                    e.start_date,
                    e.end_date,
                    e.banner_image,

                    v.venue_name,
                    v.address AS venue_address

                FROM registrations r

                INNER JOIN events e
                    ON e.event_id = r.event_id

                LEFT JOIN venues v
                    ON v.venue_id = e.venue_id

                WHERE r.user_id = :user_id

                AND r.status IN (
                    'confirmed',
                    'waitlisted',
                    'completed'
                )

                ORDER BY
                    e.start_date DESC
            ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $tickets =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Student Tickets Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load your tickets.';
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

function ticketStatusClass(
    string $status
): string {

    switch (
        strtolower($status)
    ) {

        case 'confirmed':
            return 'status-confirmed';

        case 'waitlisted':
            return 'status-waitlisted';

        case 'completed':
            return 'status-completed';

        default:
            return 'status-default';
    }
}


function ticketDate(
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


function ticketTime(
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
    My Tickets | Campus360
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

    --gold-bg:#fff8e9;

    --red:#b33a3a;
    --red-bg:#fff0f0;

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


/* TICKETS */

.ticket-grid{

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:20px;
}


.ticket{

    overflow:hidden;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:13px;

    box-shadow:
        var(--shadow);
}


.ticket-top{

    position:relative;

    min-height:160px;

    display:flex;

    align-items:flex-end;

    padding:22px;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--blue)
        );

}


.ticket-top.has-image{

    background-size:cover;

    background-position:center;
}


.ticket-overlay{

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            to top,
            rgba(7,26,54,.90),
            rgba(7,26,54,.15)
        );
}


.ticket-top-content{

    position:relative;

    z-index:2;

    color:white;

}


.ticket-label{

    color:
        var(--gold-light);

    font-size:7px;

    font-weight:700;

    letter-spacing:1.5px;

    text-transform:uppercase;
}


.ticket-title{

    margin-top:7px;

    color:white;

    font-family:
        "Playfair Display",
        serif;

    font-size:23px;

    line-height:1.2;
}


.ticket-body{

    padding:21px;
}


.ticket-status{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    padding-bottom:15px;

    border-bottom:
        1px solid
        var(--line);
}


.status{

    display:inline-flex;

    padding:
        6px 9px;

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


.status-default{

    background:
        #eef0f3;

    color:
        var(--muted);
}


.ticket-number{

    color:
        var(--muted);

    font-family:monospace;

    font-size:7px;
}


.ticket-info{

    margin-top:14px;
}


.info-row{

    display:flex;

    justify-content:space-between;

    gap:15px;

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

    max-width:220px;

    overflow:hidden;

    color:
        var(--ink);

    font-size:8px;

    font-weight:700;

    text-align:right;

    text-overflow:ellipsis;

    white-space:nowrap;
}


.ticket-footer{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-top:15px;

    padding-top:14px;

    border-top:
        1px solid
        var(--line);
}


.ticket-note{

    color:
        var(--muted);

    font-size:7px;

    line-height:1.4;
}


.event-button{

    display:inline-flex;

    padding:
        9px 11px;

    border-radius:5px;

    background:
        var(--navy);

    color:white;

    font-size:7px;

    font-weight:700;

    letter-spacing:.5px;

}


.event-button:hover{

    background:
        var(--blue);
}


/* EMPTY */

.empty{

    padding:
        70px 25px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:12px;

    color:
        var(--muted);

    text-align:center;

    font-size:10px;
}


/* RESPONSIVE */

@media(max-width:1000px){

    .ticket-grid{

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


    h1{

        font-size:31px;
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
        class="nav-link"
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
        class="nav-link active"
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
        My Tickets
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
    Event Passes
</div>


<h1>
    My Tickets
</h1>


<p>
    View your confirmed and waitlisted event registrations
    in one place.
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


<?php if (
    !empty($tickets)
): ?>


<div class="ticket-grid">


<?php foreach (
    $tickets
    as $ticket
): ?>


<?php

$bannerStyle = '';

if (
    !empty(
        $ticket['banner_image']
    )
) {

    $bannerStyle =
        "background-image:url('" .
        htmlspecialchars(
            $ticket['banner_image'],
            ENT_QUOTES,
            'UTF-8'
        ) .
        "');";
}

?>


<article class="ticket">


<div
    class="
        ticket-top
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


<div class="ticket-overlay"></div>


<div class="ticket-top-content">


<div class="ticket-label">
    EVENTSPHERE EVENT PASS
</div>


<div class="ticket-title">

    <?= sanitize(
        $ticket['title']
    ) ?>

</div>


</div>


</div>


<div class="ticket-body">


<div class="ticket-status">


<span
    class="
        status
        <?= ticketStatusClass(
            $ticket['status']
        ) ?>
    "
>

    <?= sanitize(
        ucfirst(
            $ticket['status']
        )
    ) ?>

</span>


<span class="ticket-number">

    <?= sanitize(
        $ticket['reg_id']
    ) ?>

</span>


</div>


<div class="ticket-info">


<div class="info-row">

<span class="info-label">
    Category
</span>


<span class="info-value">

    <?= sanitize(
        ucfirst(
            strtolower(
                $ticket['category']
            )
        )
    ) ?>

</span>

</div>


<div class="info-row">

<span class="info-label">
    Date
</span>


<span class="info-value">

    <?= sanitize(
        ticketDate(
            $ticket['start_date']
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
        ticketTime(
            $ticket['start_date']
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
        $ticket['venue_name']
    )
        ? sanitize(
            $ticket['venue_name']
        )
        : 'Venue TBA' ?>

</span>

</div>


<?php if (
    !empty(
        $ticket['queue_position']
    )
): ?>


<div class="info-row">

<span class="info-label">
    Queue Position
</span>


<span class="info-value">

    #
    <?= (int)(
        $ticket['queue_position']
    ) ?>

</span>

</div>


<?php endif; ?>


<div class="info-row">

<span class="info-label">
    Registered
</span>


<span class="info-value">

    <?= sanitize(
        ticketDate(
            $ticket['registered_at']
        )
    ) ?>

</span>

</div>


</div>


<div class="ticket-footer">


<div class="ticket-note">

    Keep your registration ID available
    when attending the event.

</div>


<a
    href="event-details.php?event_id=<?= urlencode(
        $ticket['event_id']
    ) ?>"
    class="event-button"
>
    VIEW EVENT
</a>


</div>


</div>


</article>


<?php endforeach; ?>


</div>


<?php else: ?>


<div class="empty">

    You do not have any event tickets yet.

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


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>