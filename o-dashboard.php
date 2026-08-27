<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
requireRole('organizer');

$user = getCurrentUser();

$userName = $user['full_name'] ?? 'Organizer';
$userId = (string)($user['user_id'] ?? '');

$initial = strtoupper(
    substr(
        trim($userName),
        0,
        1
    )
);


/*
|--------------------------------------------------------------------------
| DATABASE HELPERS
|--------------------------------------------------------------------------
*/

function dashboardQuery(
    $pdo,
    string $sql,
    array $params = []
) {
    try {

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;

    } catch (Throwable $e) {

        error_log(
            'Organizer Dashboard Query Error: ' .
            $e->getMessage()
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
|
| This dashboard expects PDO in database.php.
|
*/

$pdoConnection = null;

if (isset($pdo) && $pdo instanceof PDO) {

    $pdoConnection = $pdo;

}

elseif (
    isset($db) &&
    $db instanceof PDO
) {

    $pdoConnection = $db;

}


/*
|--------------------------------------------------------------------------
| DEFAULT DASHBOARD VALUES
|--------------------------------------------------------------------------
*/

$totalEvents         = 0;
$upcomingEvents      = 0;
$totalRegistrations  = 0;
$totalAttendance     = 0;

$recentEvents        = [];
$nextEvent           = null;


/*
|--------------------------------------------------------------------------
| LOAD DASHBOARD DATA
|--------------------------------------------------------------------------
*/

if ($pdoConnection instanceof PDO) {


    /*
    |--------------------------------------------------------------------------
    | TOTAL EVENTS
    |--------------------------------------------------------------------------
    */

    $stmt = dashboardQuery(
        $pdoConnection,
        "
        SELECT COUNT(*) AS total
        FROM events
        WHERE organizer_id = ?
        ",
        [$userId]
    );

    if ($stmt) {

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalEvents =
            (int)($row['total'] ?? 0);

    }


    /*
    |--------------------------------------------------------------------------
    | UPCOMING EVENTS
    |--------------------------------------------------------------------------
    */

    $stmt = dashboardQuery(
        $pdoConnection,
        "
        SELECT COUNT(*) AS total
        FROM events
        WHERE organizer_id = ?
        AND event_date >= CURDATE()
        ",
        [$userId]
    );

    if ($stmt) {

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $upcomingEvents =
            (int)($row['total'] ?? 0);

    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL REGISTRATIONS
    |--------------------------------------------------------------------------
    */

    $stmt = dashboardQuery(
        $pdoConnection,
        "
        SELECT COUNT(*) AS total
        FROM registrations r
        INNER JOIN events e
            ON e.id = r.event_id
        WHERE e.organizer_id = ?
        ",
        [$userId]
    );

    if ($stmt) {

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalRegistrations =
            (int)($row['total'] ?? 0);

    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL ATTENDANCE
    |--------------------------------------------------------------------------
    */

    $stmt = dashboardQuery(
        $pdoConnection,
        "
        SELECT COUNT(*) AS total
        FROM attendance a
        INNER JOIN registrations r
            ON r.id = a.registration_id
        INNER JOIN events e
            ON e.id = r.event_id
        WHERE e.organizer_id = ?
        ",
        [$userId]
    );

    if ($stmt) {

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalAttendance =
            (int)($row['total'] ?? 0);

    }


    /*
    |--------------------------------------------------------------------------
    | RECENT EVENTS
    |--------------------------------------------------------------------------
    */

    $stmt = dashboardQuery(
        $pdoConnection,
        "
        SELECT
            e.id,
            e.title,
            e.event_date,
            e.event_time,
            e.venue,
            e.status,

            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.id
            ) AS registrations

        FROM events e

        WHERE e.organizer_id = ?

        ORDER BY
            e.event_date DESC,
            e.id DESC

        LIMIT 6
        ",
        [$userId]
    );

    if ($stmt) {

        $recentEvents =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


    /*
    |--------------------------------------------------------------------------
    | NEXT UPCOMING EVENT
    |--------------------------------------------------------------------------
    */

    $stmt = dashboardQuery(
        $pdoConnection,
        "
        SELECT
            e.id,
            e.title,
            e.event_date,
            e.event_time,
            e.venue,
            e.status,

            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.id
            ) AS registrations

        FROM events e

        WHERE e.organizer_id = ?

        AND e.event_date >= CURDATE()

        ORDER BY
            e.event_date ASC,
            e.event_time ASC,
            e.id ASC

        LIMIT 1
        ",
        [$userId]
    );

    if ($stmt) {

        $nextEvent =
            $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    }

}


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function formatDashboardDate(
    ?string $date
): string {

    if (!$date) {
        return '—';
    }

    $timestamp =
        strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function formatDashboardTime(
    ?string $time
): string {

    if (!$time) {
        return '';
    }

    $timestamp =
        strtotime($time);

    if (!$timestamp) {
        return $time;
    }

    return date(
        'h:i A',
        $timestamp
    );
}


function getEventStatusClass(
    ?string $status
): string {

    $status =
        strtolower(
            trim(
                (string)$status
            )
        );

    if (
        in_array(
            $status,
            [
                'published',
                'active',
                'upcoming',
                'approved'
            ],
            true
        )
    ) {

        return 'status-green';

    }

    if (
        in_array(
            $status,
            [
                'cancelled',
                'canceled',
                'rejected'
            ],
            true
        )
    ) {

        return 'status-red';

    }

    return 'status-gold';
}


function getEventStatusLabel(
    ?string $status
): string {

    if (!$status) {
        return 'Draft';
    }

    return ucwords(
        str_replace(
            '_',
            ' ',
            strtolower($status)
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
        Organizer Dashboard | EventSphere
    </title>


    <!-- GOOGLE FONTS -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >


    <style>

        :root {

            --navy:
                #071a36;

            --navy-light:
                #102c52;

            --blue:
                #123761;

            --gold:
                #c99a3e;

            --gold-light:
                #e5c16f;

            --cream:
                #f5f7fa;

            --white:
                #ffffff;

            --ink:
                #172338;

            --muted:
                #697386;

            --line:
                #e4e8ee;

            --green:
                #2f8f5b;

            --green-bg:
                #edf8f1;

            --red:
                #b33a3a;

            --red-bg:
                #fff0f0;

            --gold-bg:
                #fff8e9;

            --shadow:
                0 18px 50px
                rgba(
                    7,
                    26,
                    54,
                    .07
                );

        }


        * {

            box-sizing:
                border-box;

            margin:
                0;

            padding:
                0;

        }


        body {

            font-family:
                "DM Sans",
                sans-serif;

            background:
                var(--cream);

            color:
                var(--ink);

            line-height:
                1.6;

        }


        a {

            color:
                inherit;

            text-decoration:
                none;

        }


        button {

            font-family:
                inherit;

        }


        /* =====================================
           SIDEBAR
        ===================================== */

        .sidebar {

            position:
                fixed;

            top:
                0;

            left:
                0;

            width:
                250px;

            height:
                100vh;

            padding:
                24px 16px;

            background:
                var(--navy);

            color:
                white;

            z-index:
                100;

        }


        .brand {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            padding:
                4px 12px 25px;

            border-bottom:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .1
                );

        }


        .brand-mark {

            width:
                42px;

            height:
                48px;

            display:
                grid;

            place-items:
                center;

            background:
                #06152c;

            border:
                2px solid
                var(--gold);

            color:
                var(--gold-light);

            font-family:
                Georgia,
                serif;

            font-size:
                20px;

            font-weight:
                bold;

            clip-path:
                polygon(
                    0 0,
                    100% 0,
                    100% 78%,
                    50% 100%,
                    0 78%
                );

        }


        .brand-text strong {

            display:
                block;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                17px;

            letter-spacing:
                1px;

        }


        .brand-text small {

            display:
                block;

            margin-top:
                1px;

            color:
                var(--gold-light);

            font-size:
                7px;

            letter-spacing:
                2px;

        }


        .nav-section {

            margin-top:
                30px;

        }


        .nav-title {

            padding:
                0 12px 10px;

            color:
                #718198;

            font-size:
                9px;

            font-weight:
                700;

            letter-spacing:
                1.7px;

            text-transform:
                uppercase;

        }


        .nav-link {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            margin-bottom:
                5px;

            padding:
                12px;

            border-radius:
                7px;

            color:
                #b8c4d3;

            font-size:
                12px;

            transition:
                .25s;

        }


        .nav-link:hover {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .07
                );

            color:
                white;

        }


        .nav-link.active {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .09
                );

            color:
                white;

            border-left:
                3px solid
                var(--gold);

            padding-left:
                9px;

        }


        .nav-icon {

            width:
                25px;

            height:
                25px;

            display:
                grid;

            place-items:
                center;

            font-size:
                14px;

        }


        /* =====================================
           MAIN
        ===================================== */

        .main {

            min-height:
                100vh;

            margin-left:
                250px;

        }


        /* =====================================
           TOPBAR
        ===================================== */

        .topbar {

            height:
                76px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 38px;

            background:
                var(--white);

            border-bottom:
                1px solid
                var(--line);

        }


        .topbar-left {

            display:
                flex;

            flex-direction:
                column;

        }


        .topbar-label {

            color:
                var(--gold);

            font-size:
                9px;

            font-weight:
                700;

            letter-spacing:
                1.7px;

            text-transform:
                uppercase;

        }


        .page-title {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                25px;

            line-height:
                1.2;

        }


        .user-area {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

        }


        .user-details {

            text-align:
                right;

        }


        .user-details strong {

            display:
                block;

            color:
                var(--ink);

            font-size:
                12px;

        }


        .user-details span {

            display:
                block;

            margin-top:
                1px;

            color:
                var(--muted);

            font-size:
                9px;

        }


        .avatar {

            width:
                42px;

            height:
                42px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                50%;

            background:
                var(--navy);

            color:
                var(--gold-light);

            font-size:
                14px;

            font-weight:
                700;

        }


        /* =====================================
           CONTENT
        ===================================== */

        .content {

            max-width:
                1250px;

            margin:
                0 auto;

            padding:
                42px 40px 60px;

        }


        .welcome {

            display:
                flex;

            align-items:
                flex-end;

            justify-content:
                space-between;

            gap:
                25px;

            margin-bottom:
                28px;

        }


        .welcome-text {

            max-width:
                700px;

        }


        .eyebrow {

            margin-bottom:
                7px;

            color:
                var(--gold);

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                2px;

            text-transform:
                uppercase;

        }


        .welcome h1 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                38px;

            line-height:
                1.15;

        }


        .welcome p {

            margin-top:
                9px;

            color:
                var(--muted);

            font-size:
                12px;

        }


        .create-button {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                12px 18px;

            border:
                none;

            border-radius:
                6px;

            background:
                var(--navy);

            color:
                white;

            cursor:
                pointer;

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                .6px;

            transition:
                .25s;

        }


        .create-button:hover {

            background:
                var(--blue);

            transform:
                translateY(-2px);

        }


        /* =====================================
           STAT CARDS
        ===================================== */

        .stats-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap:
                16px;

            margin-bottom:
                22px;

        }


        .stat-card {

            position:
                relative;

            overflow:
                hidden;

            padding:
                20px;

            background:
                var(--white);

            border:
                1px solid
                var(--line);

            border-radius:
                10px;

            box-shadow:
                var(--shadow);

        }


        .stat-card::after {

            content:
                "";

            position:
                absolute;

            right:
                -20px;

            bottom:
                -35px;

            width:
                95px;

            height:
                95px;

            border:
                1px solid
                rgba(
                    201,
                    154,
                    62,
                    .12
                );

            border-radius:
                50%;

        }


        .stat-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

        }


        .stat-label {

            color:
                var(--muted);

            font-size:
                9px;

            font-weight:
                700;

            letter-spacing:
                1px;

            text-transform:
                uppercase;

        }


        .stat-icon {

            width:
                35px;

            height:
                35px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                8px;

            background:
                var(--gold-bg);

            color:
                var(--gold);

            font-size:
                15px;

        }


        .stat-value {

            margin-top:
                14px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                29px;

            line-height:
                1;

        }


        .stat-description {

            margin-top:
                7px;

            color:
                var(--muted);

            font-size:
                9px;

        }


        /* =====================================
           MAIN GRID
        ===================================== */

        .dashboard-grid {

            display:
                grid;

            grid-template-columns:
                1.45fr
                .75fr;

            gap:
                22px;

        }


        .card {

            background:
                var(--white);

            border:
                1px solid
                var(--line);

            border-radius:
                12px;

            box-shadow:
                var(--shadow);

        }


        .events-card {

            overflow:
                hidden;

        }


        .card-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                21px 22px;

            border-bottom:
                1px solid
                var(--line);

        }


        .card-header h2 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                20px;

        }


        .card-header p {

            margin-top:
                2px;

            color:
                var(--muted);

            font-size:
                9px;

        }


        .view-link {

            color:
                var(--gold);

            font-size:
                9px;

            font-weight:
                700;

            letter-spacing:
                .7px;

            text-transform:
                uppercase;

        }


        .view-link:hover {

            color:
                var(--blue);

        }


        /* =====================================
           EVENTS
        ===================================== */

        .event-list {

            padding:
                6px 22px 12px;

        }


        .event-row {

            display:
                grid;

            grid-template-columns:
                58px
                1fr
                auto;

            align-items:
                center;

            gap:
                15px;

            padding:
                16px 0;

            border-bottom:
                1px solid
                #edf0f3;

        }


        .event-row:last-child {

            border-bottom:
                none;

        }


        .date-box {

            width:
                58px;

            height:
                61px;

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            background:
                var(--navy);

            border-radius:
                8px;

            color:
                white;

        }


        .date-box .day {

            font-size:
                20px;

            font-weight:
                700;

            line-height:
                1;

        }


        .date-box .month {

            margin-top:
                3px;

            color:
                var(--gold-light);

            font-size:
                8px;

            font-weight:
                700;

            letter-spacing:
                1px;

            text-transform:
                uppercase;

        }


        .event-info {

            min-width:
                0;

        }


        .event-title {

            overflow:
                hidden;

            color:
                var(--navy);

            font-size:
                12px;

            font-weight:
                700;

            text-overflow:
                ellipsis;

            white-space:
                nowrap;

        }


        .event-meta {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                12px;

            margin-top:
                5px;

            color:
                var(--muted);

            font-size:
                9px;

        }


        .event-meta span {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                4px;

        }


        .event-side {

            text-align:
                right;

        }


        .registration-count {

            color:
                var(--navy);

            font-size:
                12px;

            font-weight:
                700;

        }


        .registration-label {

            color:
                var(--muted);

            font-size:
                8px;

        }


        .status {

            display:
                inline-flex;

            align-items:
                center;

            margin-top:
                5px;

            padding:
                4px 7px;

            border-radius:
                20px;

            font-size:
                7px;

            font-weight:
                700;

            letter-spacing:
                .5px;

            text-transform:
                uppercase;

        }


        .status-green {

            background:
                var(--green-bg);

            color:
                var(--green);

        }


        .status-red {

            background:
                var(--red-bg);

            color:
                var(--red);

        }


        .status-gold {

            background:
                var(--gold-bg);

            color:
                #9a711d;

        }


        /* =====================================
           EMPTY STATE
        ===================================== */

        .empty-state {

            padding:
                55px 25px;

            text-align:
                center;

        }


        .empty-icon {

            width:
                58px;

            height:
                58px;

            display:
                grid;

            place-items:
                center;

            margin:
                0 auto 14px;

            border-radius:
                50%;

            background:
                var(--gold-bg);

            color:
                var(--gold);

            font-size:
                24px;

        }


        .empty-state h3 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                18px;

        }


        .empty-state p {

            max-width:
                330px;

            margin:
                6px auto 15px;

            color:
                var(--muted);

            font-size:
                10px;

        }


        .empty-button {

            display:
                inline-block;

            padding:
                9px 14px;

            border-radius:
                5px;

            background:
                var(--navy);

            color:
                white;

            font-size:
                9px;

            font-weight:
                700;

        }


        /* =====================================
           RIGHT SIDE
        ===================================== */

        .side-card {

            padding:
                22px;

        }


        .side-card h2 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                20px;

        }


        .side-subtitle {

            margin-top:
                3px;

            color:
                var(--muted);

            font-size:
                9px;

        }


        /* NEXT EVENT */

        .next-event {

            margin-top:
                18px;

            padding:
                18px;

            background:
                var(--navy);

            border-radius:
                10px;

            color:
                white;

        }


        .next-event-label {

            color:
                var(--gold-light);

            font-size:
                8px;

            font-weight:
                700;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;

        }


        .next-event h3 {

            margin-top:
                8px;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                19px;

            line-height:
                1.25;

        }


        .next-event-date {

            margin-top:
                9px;

            color:
                #d1d9e4;

            font-size:
                9px;

        }


        .next-event-date strong {

            color:
                white;

        }


        .next-event-venue {

            margin-top:
                5px;

            color:
                #b7c3d3;

            font-size:
                9px;

        }


        .next-event-bottom {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

            margin-top:
                17px;

            padding-top:
                13px;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .11
                );

        }


        .next-event-bottom span {

            color:
                #b7c3d3;

            font-size:
                8px;

        }


        .next-event-bottom strong {

            color:
                var(--gold-light);

            font-size:
                11px;

        }


        /* QUICK ACTIONS */

        .quick-actions {

            margin-top:
                22px;

        }


        .quick-actions h3 {

            margin-bottom:
                11px;

            color:
                var(--navy);

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                1px;

            text-transform:
                uppercase;

        }


        .action-grid {

            display:
                grid;

            grid-template-columns:
                1fr
                1fr;

            gap:
                8px;

        }


        .action {

            display:
                flex;

            flex-direction:
                column;

            align-items:
                flex-start;

            gap:
                7px;

            padding:
                13px;

            background:
                #f8fafc;

            border:
                1px solid
                var(--line);

            border-radius:
                7px;

            transition:
                .2s;

        }


        .action:hover {

            background:
                var(--gold-bg);

            border-color:
                rgba(
                    201,
                    154,
                    62,
                    .35
                );

            transform:
                translateY(-1px);

        }


        .action-icon {

            color:
                var(--gold);

            font-size:
                17px;

        }


        .action strong {

            color:
                var(--navy);

            font-size:
                9px;

        }


        .action span {

            color:
                var(--muted);

            font-size:
                8px;

            line-height:
                1.4;

        }


        /* =====================================
           BOTTOM METRICS
        ===================================== */

        .bottom-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                16px;

            margin-top:
                22px;

        }


        .metric-card {

            padding:
                18px;

            background:
                white;

            border:
                1px solid
                var(--line);

            border-radius:
                10px;

        }


        .metric-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

        }


        .metric-label {

            color:
                var(--muted);

            font-size:
                9px;

            font-weight:
                700;

            letter-spacing:
                .8px;

            text-transform:
                uppercase;

        }


        .metric-icon {

            color:
                var(--gold);

            font-size:
                16px;

        }


        .metric-value {

            margin-top:
                9px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                25px;

        }


        .metric-note {

            margin-top:
                2px;

            color:
                var(--muted);

            font-size:
                8px;

        }


        /* =====================================
           RESPONSIVE
        ===================================== */

        @media (
            max-width: 1100px
        ) {

            .stats-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );

            }


            .dashboard-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (
            max-width: 800px
        ) {

            .sidebar {

                width:
                    72px;

                padding:
                    20px 8px;

            }


            .brand {

                justify-content:
                    center;

                padding:
                    4px 0 25px;

            }


            .brand-text {

                display:
                    none;

            }


            .nav-title {

                display:
                    none;

            }


            .nav-link {

                justify-content:
                    center;

                padding:
                    12px 8px;

            }


            .nav-link span:last-child {

                display:
                    none;

            }


            .nav-icon {

                width:
                    auto;

            }


            .main {

                margin-left:
                    72px;

            }


            .content {

                padding:
                    30px 24px;

            }


            .welcome {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .bottom-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (
            max-width: 600px
        ) {

            .topbar {

                height:
                    68px;

                padding:
                    0 18px;

            }


            .topbar-label {

                display:
                    none;

            }


            .page-title {

                font-size:
                    21px;

            }


            .user-details {

                display:
                    none;

            }


            .content {

                padding:
                    25px 17px;

            }


            .welcome h1 {

                font-size:
                    31px;

            }


            .stats-grid {

                grid-template-columns:
                    1fr;

            }


            .event-row {

                grid-template-columns:
                    50px
                    1fr;

            }


            .event-side {

                display:
                    none;

            }


            .date-box {

                width:
                    50px;

                height:
                    56px;

            }


            .date-box .day {

                font-size:
                    18px;

            }

        }

    </style>

</head>


<body>


    <!-- =====================================
         SIDEBAR
    ===================================== -->

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
    <span class="nav-icon">◫</span>
    <span>Manage Media</span>
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



    <!-- =====================================
         MAIN
    ===================================== -->

    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-left">

                <span class="topbar-label">
                    Organizer Portal
                </span>

                <div class="page-title">
                    Dashboard
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



        <!-- CONTENT -->

        <section class="content">


            <!-- WELCOME -->

            <div class="welcome">


                <div class="welcome-text">

                    <div class="eyebrow">
                        Organizer Overview
                    </div>


                    <h1>
                        Welcome back,
                        <?= sanitize($userName) ?>.
                    </h1>


                    <p>
                        Manage your college events,
                        monitor registrations and keep
                        attendance organized from one place.
                    </p>

                </div>


                <a
                    href="create-event.php"
                    class="create-button"
                >

                    <span>
                        +
                    </span>

                    CREATE EVENT

                </a>


            </div>



            <!-- STATISTICS -->

            <div class="stats-grid">


                <!-- EVENTS -->

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">
                            Total Events
                        </span>

                        <div class="stat-icon">
                            ◈
                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($totalEvents) ?>
                    </div>


                    <div class="stat-description">
                        Events created by you
                    </div>

                </div>



                <!-- UPCOMING -->

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">
                            Upcoming
                        </span>

                        <div class="stat-icon">
                            ◷
                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($upcomingEvents) ?>
                    </div>


                    <div class="stat-description">
                        Events scheduled ahead
                    </div>

                </div>



                <!-- REGISTRATIONS -->

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">
                            Registrations
                        </span>

                        <div class="stat-icon">
                            ♙
                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($totalRegistrations) ?>
                    </div>


                    <div class="stat-description">
                        Student registrations
                    </div>

                </div>



                <!-- ATTENDANCE -->

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">
                            Attendance
                        </span>

                        <div class="stat-icon">
                            ✓
                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($totalAttendance) ?>
                    </div>


                    <div class="stat-description">
                        Verified attendance records
                    </div>

                </div>


            </div>



            <!-- MAIN DASHBOARD GRID -->

            <div class="dashboard-grid">


                <!-- RECENT EVENTS -->

                <div class="card events-card">


                    <div class="card-header">


                        <div>

                            <h2>
                                Recent Events
                            </h2>

                            <p>
                                Latest activity from your event portfolio.
                            </p>

                        </div>


                        <a
                            href="manage-events.php"
                            class="view-link"
                        >
                            View All
                        </a>


                    </div>



                    <div class="event-list">


                        <?php if (!empty($recentEvents)): ?>


                            <?php foreach ($recentEvents as $event): ?>


                                <?php

                                $eventDate =
                                    !empty($event['event_date'])
                                        ? strtotime(
                                            $event['event_date']
                                        )
                                        : false;


                                $day =
                                    $eventDate
                                        ? date(
                                            'd',
                                            $eventDate
                                        )
                                        : '--';


                                $month =
                                    $eventDate
                                        ? date(
                                            'M',
                                            $eventDate
                                        )
                                        : '---';


                                $statusClass =
                                    getEventStatusClass(
                                        $event['status'] ?? null
                                    );


                                $statusLabel =
                                    getEventStatusLabel(
                                        $event['status'] ?? null
                                    );

                                ?>


                                <div class="event-row">


                                    <div class="date-box">

                                        <span class="day">
                                            <?= sanitize($day) ?>
                                        </span>

                                        <span class="month">
                                            <?= sanitize($month) ?>
                                        </span>

                                    </div>



                                    <div class="event-info">


                                        <div class="event-title">

                                            <?= sanitize(
                                                $event['title']
                                                    ?? 'Untitled Event'
                                            ) ?>

                                        </div>


                                        <div class="event-meta">


                                            <span>
                                                ◷

                                                <?= sanitize(
                                                    formatDashboardTime(
                                                        $event['event_time']
                                                            ?? null
                                                    )
                                                ) ?>
                                            </span>


                                            <span>
                                                ◉

                                                <?= sanitize(
                                                    $event['venue']
                                                        ?? 'Venue not set'
                                                ) ?>
                                            </span>


                                        </div>


                                    </div>



                                    <div class="event-side">


                                        <div class="registration-count">

                                            <?= number_format(
                                                (int)(
                                                    $event['registrations']
                                                        ?? 0
                                                )
                                            ) ?>

                                        </div>


                                        <div class="registration-label">
                                            Registrations
                                        </div>


                                        <div
                                            class="
                                                status
                                                <?= $statusClass ?>
                                            "
                                        >
                                            <?= sanitize(
                                                $statusLabel
                                            ) ?>
                                        </div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <div class="empty-state">


                                <div class="empty-icon">
                                    ◈
                                </div>


                                <h3>
                                    No Events Yet
                                </h3>


                                <p>
                                    You have not created any events.
                                    Start by creating your first
                                    EventSphere event.
                                </p>


                                <a
                                    href="create-event.php"
                                    class="empty-button"
                                >
                                    CREATE FIRST EVENT
                                </a>


                            </div>


                        <?php endif; ?>


                    </div>


                </div>



                <!-- RIGHT PANEL -->

                <div class="card side-card">


                    <h2>
                        Next Event
                    </h2>


                    <p class="side-subtitle">
                        Your nearest scheduled event.
                    </p>



                    <?php if ($nextEvent): ?>


                        <div class="next-event">


                            <div class="next-event-label">
                                Upcoming Event
                            </div>


                            <h3>

                                <?= sanitize(
                                    $nextEvent['title']
                                        ?? 'Untitled Event'
                                ) ?>

                            </h3>


                            <div class="next-event-date">

                                <strong>
                                    <?= sanitize(
                                        formatDashboardDate(
                                            $nextEvent['event_date']
                                                ?? null
                                        )
                                    ) ?>
                                </strong>


                                <?php

                                if (
                                    !empty(
                                        $nextEvent['event_time']
                                    )
                                ):

                                ?>

                                    ·

                                    <?= sanitize(
                                        formatDashboardTime(
                                            $nextEvent['event_time']
                                        )
                                    ) ?>

                                <?php endif; ?>


                            </div>


                            <div class="next-event-venue">

                                ◉

                                <?= sanitize(
                                    $nextEvent['venue']
                                        ?? 'Venue not set'
                                ) ?>

                            </div>


                            <div class="next-event-bottom">

                                <span>
                                    Registered Students
                                </span>


                                <strong>

                                    <?= number_format(
                                        (int)(
                                            $nextEvent['registrations']
                                                ?? 0
                                        )
                                    ) ?>

                                </strong>

                            </div>


                        </div>


                    <?php else: ?>


                        <div class="next-event">


                            <div class="next-event-label">
                                Schedule
                            </div>


                            <h3>
                                No Upcoming Event
                            </h3>


                            <div class="next-event-date">
                                There are currently no upcoming
                                events in your organizer account.
                            </div>


                            <div class="next-event-bottom">

                                <span>
                                    Ready to create one?
                                </span>

                                <strong>
                                    YES
                                </strong>

                            </div>


                        </div>


                    <?php endif; ?>



                    <!-- QUICK ACTIONS -->

                    <div class="quick-actions">


                        <h3>
                            Quick Actions
                        </h3>


                        <div class="action-grid">


                            <a
                                href="create-event.php"
                                class="action"
                            >

                                <div class="action-icon">
                                    +
                                </div>

                                <strong>
                                    Create Event
                                </strong>

                                <span>
                                    Add a new college event.
                                </span>

                            </a>


                            <a
                                href="manage-events.php"
                                class="action"
                            >

                                <div class="action-icon">
                                    ◈
                                </div>

                                <strong>
                                    Manage Events
                                </strong>

                                <span>
                                    Edit and monitor events.
                                </span>

                            </a>


                            <a
                                href="qr-scanner.php"
                                class="action"
                            >

                                <div class="action-icon">
                                    ▣
                                </div>

                                <strong>
                                    QR Scanner
                                </strong>

                                <span>
                                    Verify event tickets.
                                </span>

                            </a>


                            <a
                                href="media-upload.php"
                                class="action"
                            >

                                <div class="action-icon">
                                    ▧
                                </div>

                                <strong>
                                    Upload Media
                                </strong>

                                <span>
                                    Add event photos and media.
                                </span>

                            </a>


                        </div>


                    </div>


                </div>


            </div>



            <!-- BOTTOM METRICS -->

            <div class="bottom-grid">


                <div class="metric-card">


                    <div class="metric-header">

                        <span class="metric-label">
                            Events Created
                        </span>

                        <span class="metric-icon">
                            ◈
                        </span>

                    </div>


                    <div class="metric-value">
                        <?= number_format($totalEvents) ?>
                    </div>


                    <div class="metric-note">
                        Total events under your account
                    </div>


                </div>



                <div class="metric-card">


                    <div class="metric-header">

                        <span class="metric-label">
                            Student Reach
                        </span>

                        <span class="metric-icon">
                            ♙
                        </span>

                    </div>


                    <div class="metric-value">
                        <?= number_format($totalRegistrations) ?>
                    </div>


                    <div class="metric-note">
                        Total registrations across your events
                    </div>


                </div>



                <div class="metric-card">


                    <div class="metric-header">

                        <span class="metric-label">
                            Verified Attendance
                        </span>

                        <span class="metric-icon">
                            ✓
                        </span>

                    </div>


                    <div class="metric-value">
                        <?= number_format($totalAttendance) ?>
                    </div>


                    <div class="metric-note">
                        Attendance records confirmed
                    </div>


                </div>


            </div>


        </section>


    </main>

<?php require_once __DIR__ . '/footer.php'; ?>
</body>

</html>
