<?php

require_once __DIR__ . '/database.php';
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
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$pdoConnection = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $pdoConnection = $pdo;
}

elseif (isset($db) && $db instanceof PDO) {
    $pdoConnection = $db;
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$successMessage = $_SESSION['event_success'] ?? '';
unset($_SESSION['event_success']);


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$categoryFilter = trim(
    $_GET['category'] ?? ''
);

$statusFilter = trim(
    $_GET['status'] ?? ''
);


/*
|--------------------------------------------------------------------------
| ALLOWED FILTER VALUES
|--------------------------------------------------------------------------
*/

$categories = [
    'technical' => 'Technical',
    'cultural'  => 'Cultural',
    'sports'    => 'Sports',
    'academic'  => 'Academic',
    'workshop'  => 'Workshop',
    'seminar'   => 'Seminar'
];

$statuses = [
    'draft'     => 'Draft',
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'completed' => 'Completed'
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
                event_id,
                title,
                subtitle,
                description,
                category,
                department_id,
                venue_id,
                max_seats,
                waitlist_capacity,
                start_date,
                end_date,
                approval_state,
                organizer_id,
                code_of_conduct,
                dress_code,
                required_materials,
                banner_image,
                rejection_reason,
                created_at
            FROM events
            WHERE organizer_id = :organizer_id
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
                    title LIKE :search
                    OR subtitle LIKE :search
                    OR description LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
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
                AND category = :category
            ";

            $params[':category'] =
                $categoryFilter;
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
                AND approval_state = :approval_state
            ";

            $params[':approval_state'] =
                $statusFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | SORT
        |--------------------------------------------------------------------------
        */

        $sql .= "
            ORDER BY
                start_date DESC,
                created_at DESC
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


    }

    catch (PDOException $e) {

        error_log(
            'Manage Events Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load your events. Please try again.';

    }

}

else {

    $errorMessage =
        'Database connection is not available.';

}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalEvents =
    count($events);

$pendingCount = 0;
$approvedCount = 0;
$draftCount = 0;
$rejectedCount = 0;
$completedCount = 0;


foreach ($events as $event) {

    switch (
        strtolower(
            (string)($event['approval_state'] ?? '')
        )
    ) {

        case 'pending':
            $pendingCount++;
            break;

        case 'approved':
            $approvedCount++;
            break;

        case 'draft':
            $draftCount++;
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

function eventStatusClass(
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


function eventStatusLabel(
    string $status
): string {

    if ($status === '') {
        return 'Unknown';
    }

    return ucfirst(
        strtolower($status)
    );
}


function formatEventDate(
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


function formatEventTime(
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
        Manage Events | EventSphere
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

        :root {

            --navy: #071a36;
            --navy-light: #102c52;
            --blue: #123761;

            --gold: #c99a3e;
            --gold-light: #e5c16f;

            --cream: #f5f7fa;
            --white: #ffffff;

            --ink: #172338;
            --muted: #697386;

            --line: #e4e8ee;

            --green: #2f8f5b;
            --green-bg: #edf8f1;

            --red: #b33a3a;
            --red-bg: #fff0f0;

            --gold-bg: #fff8e9;

            --blue-bg: #eef4fb;

            --shadow:
                0 18px 50px
                rgba(7,26,54,.07);

        }


        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

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

            color: inherit;

            text-decoration: none;

        }


        input,
        select,
        button {

            font-family: inherit;

        }


        /* =====================================
           SIDEBAR
        ===================================== */

        .sidebar {

            position: fixed;

            top: 0;

            left: 0;

            width: 250px;

            height: 100vh;

            padding: 24px 16px;

            background: var(--navy);

            color: white;

            z-index: 100;

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                4px 12px 25px;

            border-bottom:
                1px solid
                rgba(255,255,255,.1);

        }


        .brand-mark {

            width: 42px;

            height: 48px;

            display: grid;

            place-items: center;

            background: #06152c;

            border:
                2px solid
                var(--gold);

            color:
                var(--gold-light);

            font-family: Georgia, serif;

            font-size: 20px;

            font-weight: bold;

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

            display: block;

            font-family:
                "Playfair Display",
                serif;

            font-size: 17px;

            letter-spacing: 1px;

        }


        .brand-text small {

            display: block;

            margin-top: 1px;

            color: var(--gold-light);

            font-size: 7px;

            letter-spacing: 2px;

        }


        .nav-section {

            margin-top: 30px;

        }


        .nav-title {

            padding:
                0 12px 10px;

            color:
                #718198;

            font-size: 9px;

            font-weight: 700;

            letter-spacing: 1.7px;

            text-transform: uppercase;

        }


        .nav-link {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 5px;

            padding: 12px;

            border-radius: 7px;

            color: #b8c4d3;

            font-size: 12px;

            transition: .25s;

        }


        .nav-link:hover {

            background:
                rgba(255,255,255,.07);

            color: white;

        }


        .nav-link.active {

            background:
                rgba(255,255,255,.09);

            color: white;

            border-left:
                3px solid
                var(--gold);

            padding-left: 9px;

        }


        .nav-icon {

            width: 25px;

            height: 25px;

            display: grid;

            place-items: center;

            font-size: 14px;

        }


        /* =====================================
           MAIN
        ===================================== */

        .main {

            min-height: 100vh;

            margin-left: 250px;

        }


        /* =====================================
           TOPBAR
        ===================================== */

        .topbar {

            height: 76px;

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            padding:
                0 38px;

            background: white;

            border-bottom:
                1px solid
                var(--line);

        }


        .topbar-left {

            display: flex;

            flex-direction: column;

        }


        .topbar-label {

            color:
                var(--gold);

            font-size: 9px;

            font-weight: 700;

            letter-spacing: 1.7px;

            text-transform: uppercase;

        }


        .page-title {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 25px;

            line-height: 1.2;

        }


        .user-area {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .user-details {

            text-align: right;

        }


        .user-details strong {

            display: block;

            color: var(--ink);

            font-size: 12px;

        }


        .user-details span {

            display: block;

            margin-top: 1px;

            color: var(--muted);

            font-size: 9px;

        }


        .avatar {

            width: 42px;

            height: 42px;

            display: grid;

            place-items: center;

            border-radius: 50%;

            background:
                var(--navy);

            color:
                var(--gold-light);

            font-size: 14px;

            font-weight: 700;

        }


        /* =====================================
           CONTENT
        ===================================== */

        .content {

            max-width: 1250px;

            margin: 0 auto;

            padding:
                42px 40px 60px;

        }


        .page-intro {

            display: flex;

            align-items:
                flex-end;

            justify-content:
                space-between;

            gap: 20px;

            margin-bottom: 25px;

        }


        .eyebrow {

            margin-bottom: 7px;

            color: var(--gold);

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 2px;

            text-transform: uppercase;

        }


        .page-intro h1 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 38px;

            line-height: 1.15;

        }


        .page-intro p {

            max-width: 650px;

            margin-top: 8px;

            color: var(--muted);

            font-size: 12px;

        }


        .create-button {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                12px 18px;

            border-radius: 6px;

            background:
                var(--navy);

            color: white;

            font-size: 9px;

            font-weight: 700;

            letter-spacing: .7px;

            transition: .2s;

        }


        .create-button:hover {

            background:
                var(--blue);

            transform:
                translateY(-1px);

        }


        /* =====================================
           SUCCESS / ERROR
        ===================================== */

        .alert {

            margin-bottom: 18px;

            padding:
                13px 16px;

            border-radius: 7px;

            font-size: 10px;

            font-weight: 600;

        }


        .alert-success {

            background:
                var(--green-bg);

            border:
                1px solid
                #ccebd8;

            color:
                var(--green);

        }


        .alert-error {

            background:
                var(--red-bg);

            border:
                1px solid
                #efcccc;

            color:
                var(--red);

        }


        /* =====================================
           SUMMARY
        ===================================== */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 14px;

            margin-bottom: 22px;

        }


        .summary-card {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            gap: 12px;

            padding: 17px;

            background:
                white;

            border:
                1px solid
                var(--line);

            border-radius: 9px;

            box-shadow:
                var(--shadow);

        }


        .summary-label {

            color: var(--muted);

            font-size: 8px;

            font-weight: 700;

            letter-spacing: .8px;

            text-transform: uppercase;

        }


        .summary-value {

            margin-top: 5px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 25px;

            line-height: 1;

        }


        .summary-icon {

            width: 34px;

            height: 34px;

            display: grid;

            place-items: center;

            border-radius: 8px;

            background:
                var(--gold-bg);

            color:
                var(--gold);

        }


        /* =====================================
           FILTER CARD
        ===================================== */

        .filter-card {

            padding: 17px;

            margin-bottom: 18px;

            background: white;

            border:
                1px solid
                var(--line);

            border-radius: 10px;

            box-shadow:
                var(--shadow);

        }


        .filter-form {

            display: grid;

            grid-template-columns:
                1.7fr
                1fr
                1fr
                auto;

            gap: 10px;

            align-items: end;

        }


        .filter-group {

            display: flex;

            flex-direction: column;

        }


        .filter-group label {

            margin-bottom: 6px;

            color:
                var(--muted);

            font-size: 8px;

            font-weight: 700;

            letter-spacing: .8px;

            text-transform:
                uppercase;

        }


        .filter-control {

            width: 100%;

            padding:
                10px 11px;

            border:
                1px solid
                var(--line);

            border-radius: 6px;

            outline: none;

            background:
                #fbfcfd;

            color:
                var(--ink);

            font-size: 10px;

        }


        .filter-control:focus {

            border-color:
                var(--gold);

            background:
                white;

        }


        .filter-buttons {

            display: flex;

            gap: 7px;

        }


        .filter-button {

            padding:
                10px 14px;

            border: none;

            border-radius: 6px;

            background:
                var(--navy);

            color: white;

            cursor: pointer;

            font-size: 8px;

            font-weight: 700;

            letter-spacing: .7px;

        }


        .clear-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                10px 14px;

            border:
                1px solid
                var(--line);

            border-radius: 6px;

            background: white;

            color: var(--muted);

            font-size: 8px;

            font-weight: 700;

            letter-spacing: .7px;

        }


        /* =====================================
           EVENTS CARD
        ===================================== */

        .events-card {

            overflow: hidden;

            background: white;

            border:
                1px solid
                var(--line);

            border-radius: 12px;

            box-shadow:
                var(--shadow);

        }


        .events-header {

            display: flex;

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


        .events-header h2 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 20px;

        }


        .events-header p {

            margin-top: 2px;

            color:
                var(--muted);

            font-size: 9px;

        }


        .event-count {

            color:
                var(--gold);

            font-size: 9px;

            font-weight: 700;

            letter-spacing: .7px;

            text-transform: uppercase;

        }


        /* =====================================
           EVENT TABLE
        ===================================== */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        .event-table {

            width: 100%;

            min-width: 900px;

            border-collapse:
                collapse;

        }


        .event-table th {

            padding:
                12px 16px;

            background:
                #fafbfd;

            border-bottom:
                1px solid
                var(--line);

            color:
                var(--muted);

            font-size: 8px;

            font-weight: 700;

            letter-spacing: .8px;

            text-align: left;

            text-transform:
                uppercase;

        }


        .event-table td {

            padding:
                15px 16px;

            border-bottom:
                1px solid
                #edf0f3;

            vertical-align:
                middle;

            font-size: 9px;

        }


        .event-table tbody tr:hover {

            background:
                #fcfdff;

        }


        .event-title-cell {

            min-width: 250px;

        }


        .event-title {

            color:
                var(--navy);

            font-size: 11px;

            font-weight: 700;

        }


        .event-subtitle {

            overflow:
                hidden;

            max-width: 280px;

            margin-top: 3px;

            color:
                var(--muted);

            font-size: 8px;

            text-overflow:
                ellipsis;

            white-space:
                nowrap;

        }


        .category {

            display: inline-flex;

            padding:
                4px 7px;

            border-radius:
                20px;

            background:
                var(--blue-bg);

            color:
                var(--blue);

            font-size: 7px;

            font-weight: 700;

            letter-spacing: .5px;

            text-transform:
                uppercase;

        }


        .status {

            display: inline-flex;

            padding:
                5px 8px;

            border-radius:
                20px;

            font-size: 7px;

            font-weight: 700;

            letter-spacing: .5px;

            text-transform:
                uppercase;

        }


        .status-approved {

            background:
                var(--green-bg);

            color:
                var(--green);

        }


        .status-pending {

            background:
                var(--gold-bg);

            color:
                #9a711d;

        }


        .status-rejected {

            background:
                var(--red-bg);

            color:
                var(--red);

        }


        .status-completed {

            background:
                var(--blue-bg);

            color:
                var(--blue);

        }


        .status-draft {

            background:
                #f0f2f5;

            color:
                var(--muted);

        }


        .date-primary {

            color:
                var(--navy);

            font-size: 9px;

            font-weight: 700;

        }


        .date-secondary {

            margin-top: 2px;

            color:
                var(--muted);

            font-size: 8px;

        }


        .seats {

            color:
                var(--navy);

            font-size: 10px;

            font-weight: 700;

        }


        .venue-id {

            color:
                var(--ink);

            font-size: 9px;

            font-weight: 600;

        }


        .action-link {

            display: inline-flex;

            align-items: center;

            justify-content:
                center;

            padding:
                7px 10px;

            border:
                1px solid
                var(--line);

            border-radius: 5px;

            background: white;

            color:
                var(--navy);

            font-size: 7px;

            font-weight: 700;

            letter-spacing: .5px;

            text-transform:
                uppercase;

        }


        .action-link:hover {

            border-color:
                var(--gold);

            color:
                var(--gold);

        }


        /* =====================================
           EMPTY STATE
        ===================================== */

        .empty-state {

            padding:
                65px 25px;

            text-align: center;

        }


        .empty-icon {

            width: 62px;

            height: 62px;

            display: grid;

            place-items: center;

            margin: 0 auto 15px;

            border-radius: 50%;

            background:
                var(--gold-bg);

            color:
                var(--gold);

            font-size: 25px;

        }


        .empty-state h3 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 20px;

        }


        .empty-state p {

            max-width: 390px;

            margin: 7px auto 17px;

            color:
                var(--muted);

            font-size: 10px;

        }


        .empty-create {

            display: inline-flex;

            padding:
                10px 15px;

            border-radius: 6px;

            background:
                var(--navy);

            color: white;

            font-size: 8px;

            font-weight: 700;

            letter-spacing: .7px;

        }


        /* =====================================
           REJECTION NOTE
        ===================================== */

        .rejection-note {

            max-width: 230px;

            margin-top: 5px;

            color:
                var(--red);

            font-size: 8px;

            line-height: 1.4;

        }


        /* =====================================
           RESPONSIVE
        ===================================== */

        @media (max-width: 1050px) {

            .summary-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

            .filter-form {

                grid-template-columns:
                    1fr 1fr;

            }

            .filter-buttons {

                grid-column:
                    1 / -1;

            }

        }


        @media (max-width: 800px) {

            .sidebar {

                width: 72px;

                padding:
                    20px 8px;

            }


            .brand {

                justify-content:
                    center;

                padding:
                    4px 0 25px;

            }


            .brand-text,
            .nav-title {

                display: none;

            }


            .nav-link {

                justify-content: center;

            }


            .nav-link span:last-child {

                display: none;

            }


            .main {

                margin-left: 72px;

            }


            .content {

                padding:
                    30px 24px;

            }


            .page-intro {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }

        }


        @media (max-width: 600px) {

            .topbar {

                height: 68px;

                padding:
                    0 18px;

            }


            .topbar-label {

                display: none;

            }


            .page-title {

                font-size: 21px;

            }


            .user-details {

                display: none;

            }


            .content {

                padding:
                    25px 17px;

            }


            .page-intro h1 {

                font-size: 31px;

            }


            .summary-grid {

                grid-template-columns:
                    1fr;

            }


            .filter-form {

                grid-template-columns:
                    1fr;

            }


            .filter-buttons {

                grid-column: auto;

            }


            .filter-buttons a,
            .filter-buttons button {

                flex: 1;

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
                class="nav-link active"
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
                    Manage Events
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


            <!-- PAGE INTRO -->

            <div class="page-intro">


                <div>

                    <div class="eyebrow">
                        Event Management
                    </div>


                    <h1>
                        Manage Your Events
                    </h1>


                    <p>
                        View, monitor and manage all events
                        created under your organizer account.
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



            <!-- FLASH SUCCESS -->

            <?php if ($successMessage !== ''): ?>

                <div
                    class="
                        alert
                        alert-success
                    "
                >

                    <?= sanitize(
                        $successMessage
                    ) ?>

                </div>

            <?php endif; ?>

<?php

$errorFlash =
    $_SESSION['event_error'] ?? '';

unset(
    $_SESSION['event_error']
);

?>

            <!-- ERROR -->

            <?php if ($errorMessage !== ''): ?>

                <div
                    class="
                        alert
                        alert-error
                    "
                >

                    <?= sanitize(
                        $errorMessage
                    ) ?>

                </div>

            <?php endif; ?>



            <!-- SUMMARY -->

            <div class="summary-grid">


                <div class="summary-card">

                    <div>

                        <div class="summary-label">
                            Total Events
                        </div>

                        <div class="summary-value">
                            <?= number_format($totalEvents) ?>
                        </div>

                    </div>


                    <div class="summary-icon">
                        ◈
                    </div>

                </div>



                <div class="summary-card">

                    <div>

                        <div class="summary-label">
                            Pending
                        </div>

                        <div class="summary-value">
                            <?= number_format($pendingCount) ?>
                        </div>

                    </div>


                    <div class="summary-icon">
                        ◷
                    </div>

                </div>



                <div class="summary-card">

                    <div>

                        <div class="summary-label">
                            Approved
                        </div>

                        <div class="summary-value">
                            <?= number_format($approvedCount) ?>
                        </div>

                    </div>


                    <div class="summary-icon">
                        ✓
                    </div>

                </div>



                <div class="summary-card">

                    <div>

                        <div class="summary-label">
                            Completed
                        </div>

                        <div class="summary-value">
                            <?= number_format($completedCount) ?>
                        </div>

                    </div>


                    <div class="summary-icon">
                        ◆
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
                            Search Events
                        </label>

                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="filter-control"
                            value="<?= sanitize($search) ?>"
                            placeholder="Search by title or description..."
                        >

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
                                $categories as $value => $label
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
                                $statuses as $value => $label
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



                    <div class="filter-buttons">

                        <button
                            type="submit"
                            class="filter-button"
                        >
                            FILTER
                        </button>


                        <a
                            href="manage-events.php"
                            class="clear-button"
                        >
                            CLEAR
                        </a>

                    </div>


                </form>


            </div>



            <!-- EVENTS -->

            <div class="events-card">


                <div class="events-header">


                    <div>

                        <h2>
                            Event Portfolio
                        </h2>

                        <p>
                            Events associated with your organizer account.
                        </p>

                    </div>


                    <div class="event-count">

                        <?= number_format($totalEvents) ?>

                        Events

                    </div>


                </div>



                <?php if (!empty($events)): ?>


                    <div class="table-wrapper">


                        <table class="event-table">


                            <thead>

                                <tr>

                                    <th>
                                        Event
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Schedule
                                    </th>

                                    <th>
                                        Venue
                                    </th>

                                    <th>
                                        Capacity
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $events as $event
                                ): ?>


                                    <?php

                                    $eventStatus =
                                        strtolower(
                                            (string)(
                                                $event['approval_state']
                                                    ?? ''
                                            )
                                        );

                                    $eventCategory =
                                        strtolower(
                                            (string)(
                                                $event['category']
                                                    ?? ''
                                            )
                                        );

                                    ?>


                                    <tr>


                                        <!-- EVENT -->

                                        <td
                                            class="event-title-cell"
                                        >

                                            <div
                                                class="event-title"
                                            >

                                                <?= sanitize(
                                                    $event['title']
                                                        ?? 'Untitled Event'
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


                                            <?php if (
                                                $eventStatus ===
                                                'rejected' &&
                                                !empty(
                                                    $event['rejection_reason']
                                                )
                                            ): ?>

                                                <div
                                                    class="rejection-note"
                                                >

                                                    <?= sanitize(
                                                        $event['rejection_reason']
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>


                                        </td>



                                        <!-- CATEGORY -->

                                        <td>

                                            <span
                                                class="category"
                                            >

                                                <?= sanitize(
                                                    ucfirst(
                                                        $eventCategory
                                                    )
                                                ) ?>

                                            </span>

                                        </td>



                                        <!-- SCHEDULE -->

                                        <td>

                                            <div
                                                class="date-primary"
                                            >

                                                <?= sanitize(
                                                    formatEventDate(
                                                        $event['start_date']
                                                            ?? null
                                                    )
                                                ) ?>

                                            </div>


                                            <div
                                                class="date-secondary"
                                            >

                                                <?= sanitize(
                                                    formatEventTime(
                                                        $event['start_date']
                                                            ?? null
                                                    )
                                                ) ?>

                                            </div>


                                        </td>



                                        <!-- VENUE -->

                                        <td>

                                            <div
                                                class="venue-id"
                                            >

                                                Venue #

                                                <?= (int)(
                                                    $event['venue_id']
                                                        ?? 0
                                                ) ?>

                                            </div>

                                        </td>



                                        <!-- CAPACITY -->

                                        <td>

                                            <div class="seats">

                                                <?= number_format(
                                                    (int)(
                                                        $event['max_seats']
                                                            ?? 0
                                                    )
                                                ) ?>

                                            </div>

                                            <div
                                                class="date-secondary"
                                            >
                                                Seats
                                            </div>

                                        </td>



                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="
                                                    status
                                                    <?= eventStatusClass(
                                                        $eventStatus
                                                    ) ?>
                                                "
                                            >

                                                <?= sanitize(
                                                    eventStatusLabel(
                                                        $eventStatus
                                                    )
                                                ) ?>

                                            </span>

                                        </td>



                                        <!-- ACTION -->

                                        <td>

                                           <div class="action-group">

    <a
        href="edit-event.php?event_id=<?= urlencode(
            $event['event_id'] ?? ''
        ) ?>"
        class="action-link"
    >
        EDIT
    </a>

    <a
        href="delete-event.php?event_id=<?= urlencode(
            $event['event_id'] ?? ''
        ) ?>"
        class="action-link delete-link"
        onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.');"
    >
        DELETE
    </a>
    <a
    href="view-event.php?event_id=<?= urlencode(
        $event['event_id'] ?? ''
    ) ?>"
    class="action-link"
>
    VIEW
</a>

</div>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="empty-state">


                        <div class="empty-icon">
                            ◈
                        </div>


                        <?php if (
                            $search !== '' ||
                            $categoryFilter !== '' ||
                            $statusFilter !== ''
                        ): ?>

                            <h3>
                                No Matching Events
                            </h3>


                            <p>
                                No events matched your current
                                search or filter settings.
                                Try clearing the filters.
                            </p>


                            <a
                                href="manage-events.php"
                                class="empty-create"
                            >
                                CLEAR FILTERS
                            </a>


                        <?php else: ?>

                            <h3>
                                No Events Created
                            </h3>


                            <p>
                                You have not created any events yet.
                                Start by creating your first
                                EventSphere event.
                            </p>


                            <a
                                href="create-event.php"
                                class="empty-create"
                            >
                                CREATE FIRST EVENT
                            </a>

                        <?php endif; ?>


                    </div>


                <?php endif; ?>


            </div>


        </section>


    </main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
