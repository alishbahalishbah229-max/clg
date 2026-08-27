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
| GET EVENT ID
|--------------------------------------------------------------------------
*/

$eventId = trim(
    $_GET['event_id']
    ?? $_POST['event_id']
    ?? ''
);

$errors = [];
$successMessage = '';

if ($eventId === '') {

    header(
        'Location: manage-events.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['edit_event_token'])) {

    $_SESSION['edit_event_token'] =
        bin2hex(
            random_bytes(32)
        );

}

$csrfToken =
    $_SESSION['edit_event_token'];


/*
|--------------------------------------------------------------------------
| CATEGORIES
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


/*
|--------------------------------------------------------------------------
| VENUES
|--------------------------------------------------------------------------
*/

$venues = [

    [
        'venue_id' => 1,
        'name' => 'Main Auditorium'
    ],

    [
        'venue_id' => 2,
        'name' => 'Seminar Hall'
    ],

    [
        'venue_id' => 3,
        'name' => 'Open Ground'
    ],

    [
        'venue_id' => 4,
        'name' => 'Conference Room'
    ]

];


/*
|--------------------------------------------------------------------------
| LOAD EVENT
|--------------------------------------------------------------------------
*/

$event = null;

if (!$pdoConnection instanceof PDO) {

    $errors[] =
        'Database connection is not available.';

} else {

    try {

        $stmt = $pdoConnection->prepare("
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
                rejection_reason
            FROM events
            WHERE event_id = :event_id
            AND organizer_id = :organizer_id
            LIMIT 1
        ");

        $stmt->execute([
            ':event_id' =>
                $eventId,

            ':organizer_id' =>
                $userId
        ]);

        $event =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$event) {

            $errors[] =
                'Event was not found or does not belong to your account.';

        }

    } catch (PDOException $e) {

        error_log(
            'Edit Event Load Error: ' .
            $e->getMessage()
        );

        $errors[] =
            'Unable to load the event.';

    }

}


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

if ($event) {

    $title =
        $event['title'] ?? '';

    $subtitle =
        $event['subtitle'] ?? '';

    $description =
        $event['description'] ?? '';

    $category =
        $event['category'] ?? '';

    $departmentId =
        $event['department_id'] ?? '';

    $venueId =
        (string)($event['venue_id'] ?? '');

    $maxSeats =
        (string)($event['max_seats'] ?? '');

    $waitlistCapacity =
        (string)($event['waitlist_capacity'] ?? '50');

    $startDate =
        !empty($event['start_date'])
            ? date(
                'Y-m-d',
                strtotime($event['start_date'])
            )
            : '';

    $startTime =
        !empty($event['start_date'])
            ? date(
                'H:i',
                strtotime($event['start_date'])
            )
            : '';

    $endDate =
        !empty($event['end_date'])
            ? date(
                'Y-m-d',
                strtotime($event['end_date'])
            )
            : '';

    $endTime =
        !empty($event['end_date'])
            ? date(
                'H:i',
                strtotime($event['end_date'])
            )
            : '';

    $codeOfConduct =
        $event['code_of_conduct'] ?? '';

    $dressCode =
        $event['dress_code'] ?? '';

    $requiredMaterials =
        $event['required_materials'] ?? '';

}


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $event
) {


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['edit_event_token'],
            $_POST['csrf_token']
        )
    ) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | READ INPUT
    |--------------------------------------------------------------------------
    */

    $title =
        trim(
            $_POST['title'] ?? ''
        );

    $subtitle =
        trim(
            $_POST['subtitle'] ?? ''
        );

    $description =
        trim(
            $_POST['description'] ?? ''
        );

    $category =
        trim(
            $_POST['category'] ?? ''
        );

    $departmentId =
        trim(
            $_POST['department_id'] ?? ''
        );

    $venueId =
        trim(
            $_POST['venue_id'] ?? ''
        );

    $maxSeats =
        trim(
            $_POST['max_seats'] ?? ''
        );

    $waitlistCapacity =
        trim(
            $_POST['waitlist_capacity'] ?? '50'
        );

    $startDate =
        trim(
            $_POST['start_date'] ?? ''
        );

    $startTime =
        trim(
            $_POST['start_time'] ?? ''
        );

    $endDate =
        trim(
            $_POST['end_date'] ?? ''
        );

    $endTime =
        trim(
            $_POST['end_time'] ?? ''
        );

    $codeOfConduct =
        trim(
            $_POST['code_of_conduct'] ?? ''
        );

    $dressCode =
        trim(
            $_POST['dress_code'] ?? ''
        );

    $requiredMaterials =
        trim(
            $_POST['required_materials'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($title === '') {

        $errors[] =
            'Event title is required.';

    } elseif (
        mb_strlen($title) > 200
    ) {

        $errors[] =
            'Event title cannot exceed 200 characters.';

    }


    if (
        !array_key_exists(
            $category,
            $categories
        )
    ) {

        $errors[] =
            'Please select a valid event category.';

    }


    $validVenueIds =
        array_map(
            'strval',
            array_column(
                $venues,
                'venue_id'
            )
        );


    if (
        !in_array(
            $venueId,
            $validVenueIds,
            true
        )
    ) {

        $errors[] =
            'Please select a valid venue.';

    }


    if (
        $maxSeats === '' ||
        !ctype_digit($maxSeats) ||
        (int)$maxSeats < 1
    ) {

        $errors[] =
            'Maximum seats must be a valid positive number.';

    }


    if (
        $waitlistCapacity === '' ||
        !ctype_digit($waitlistCapacity) ||
        (int)$waitlistCapacity < 0
    ) {

        $errors[] =
            'Waitlist capacity must be a valid number.';

    }


    $startDateTime = null;
    $endDateTime = null;


    if (
        $startDate === '' ||
        $startTime === ''
    ) {

        $errors[] =
            'Start date and time are required.';

    } else {

        $startDateTime =
            DateTime::createFromFormat(
                'Y-m-d H:i',
                $startDate . ' ' . $startTime
            );

        if (
            !$startDateTime ||
            $startDateTime->format('Y-m-d H:i')
                !== $startDate . ' ' . $startTime
        ) {

            $errors[] =
                'Invalid start date or time.';

        }

    }


    if (
        $endDate === '' ||
        $endTime === ''
    ) {

        $errors[] =
            'End date and time are required.';

    } else {

        $endDateTime =
            DateTime::createFromFormat(
                'Y-m-d H:i',
                $endDate . ' ' . $endTime
            );

        if (
            !$endDateTime ||
            $endDateTime->format('Y-m-d H:i')
                !== $endDate . ' ' . $endTime
        ) {

            $errors[] =
                'Invalid end date or time.';

        }

    }


    if (
        $startDateTime instanceof DateTime &&
        $endDateTime instanceof DateTime &&
        $endDateTime <= $startDateTime
    ) {

        $errors[] =
            'End date and time must be after the start date and time.';

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $stmt = $pdoConnection->prepare("
                UPDATE events
                SET
                    title = :title,
                    subtitle = :subtitle,
                    description = :description,
                    category = :category,
                    department_id = :department_id,
                    venue_id = :venue_id,
                    max_seats = :max_seats,
                    waitlist_capacity = :waitlist_capacity,
                    start_date = :start_date,
                    end_date = :end_date,
                    code_of_conduct = :code_of_conduct,
                    dress_code = :dress_code,
                    required_materials = :required_materials,
                    updated_at = CURRENT_TIMESTAMP
                WHERE event_id = :event_id
                AND organizer_id = :organizer_id
            ");

            $stmt->execute([

                ':title' =>
                    $title,

                ':subtitle' =>
                    $subtitle !== ''
                        ? $subtitle
                        : null,

                ':description' =>
                    $description !== ''
                        ? $description
                        : null,

                ':category' =>
                    $category,

                ':department_id' =>
                    $departmentId !== ''
                        ? $departmentId
                        : null,

                ':venue_id' =>
                    (int)$venueId,

                ':max_seats' =>
                    (int)$maxSeats,

                ':waitlist_capacity' =>
                    (int)$waitlistCapacity,

                ':start_date' =>
                    $startDateTime->format(
                        'Y-m-d H:i:s'
                    ),

                ':end_date' =>
                    $endDateTime->format(
                        'Y-m-d H:i:s'
                    ),

                ':code_of_conduct' =>
                    $codeOfConduct !== ''
                        ? $codeOfConduct
                        : null,

                ':dress_code' =>
                    $dressCode !== ''
                        ? $dressCode
                        : null,

                ':required_materials' =>
                    $requiredMaterials !== ''
                        ? $requiredMaterials
                        : null,

                ':event_id' =>
                    $eventId,

                ':organizer_id' =>
                    $userId

            ]);


            $_SESSION['event_success'] =
                'Event updated successfully.';

            header(
                'Location: manage-events.php'
            );

            exit;

        } catch (PDOException $e) {

            error_log(
                'Edit Event Update Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Database error: ' .
                $e->getMessage();

        }

    }

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
        Edit Event | EventSphere
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

            --navy:#071a36;
            --blue:#123761;
            --gold:#c99a3e;
            --gold-light:#e5c16f;
            --cream:#f5f7fa;
            --white:#ffffff;
            --ink:#172338;
            --muted:#697386;
            --line:#e4e8ee;
            --red:#b33a3a;
            --red-bg:#fff0f0;
            --gold-bg:#fff8e9;
            --shadow:0 18px 50px rgba(7,26,54,.07);

        }


        * {

            box-sizing:border-box;
            margin:0;
            padding:0;

        }


        body {

            font-family:"DM Sans",sans-serif;
            background:var(--cream);
            color:var(--ink);
            line-height:1.6;

        }


        a {

            color:inherit;
            text-decoration:none;

        }


        input,
        textarea,
        select,
        button {

            font-family:inherit;

        }


        /* SIDEBAR */

        .sidebar {

            position:fixed;
            top:0;
            left:0;
            width:250px;
            height:100vh;
            padding:24px 16px;
            background:var(--navy);
            color:white;
            z-index:100;

        }


        .brand {

            display:flex;
            align-items:center;
            gap:12px;
            padding:4px 12px 25px;
            border-bottom:1px solid rgba(255,255,255,.1);

        }


        .brand-mark {

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


        .brand-text strong {

            display:block;
            font-family:"Playfair Display",serif;
            font-size:17px;
            letter-spacing:1px;

        }


        .brand-text small {

            display:block;
            color:var(--gold-light);
            font-size:7px;
            letter-spacing:2px;

        }


        .nav-section {

            margin-top:30px;

        }


        .nav-title {

            padding:0 12px 10px;
            color:#718198;
            font-size:9px;
            font-weight:700;
            letter-spacing:1.7px;
            text-transform:uppercase;

        }


        .nav-link {

            display:flex;
            align-items:center;
            gap:12px;
            margin-bottom:5px;
            padding:12px;
            border-radius:7px;
            color:#b8c4d3;
            font-size:12px;
            transition:.25s;

        }


        .nav-link:hover {

            background:rgba(255,255,255,.07);
            color:white;

        }


        .nav-link.active {

            background:rgba(255,255,255,.09);
            color:white;
            border-left:3px solid var(--gold);
            padding-left:9px;

        }


        .nav-icon {

            width:25px;
            height:25px;
            display:grid;
            place-items:center;

        }


        /* MAIN */

        .main {

            min-height:100vh;
            margin-left:250px;

        }


        .topbar {

            height:76px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 38px;
            background:white;
            border-bottom:1px solid var(--line);

        }


        .topbar-left {

            display:flex;
            flex-direction:column;

        }


        .topbar-label {

            color:var(--gold);
            font-size:9px;
            font-weight:700;
            letter-spacing:1.7px;
            text-transform:uppercase;

        }


        .page-title {

            color:var(--navy);
            font-family:"Playfair Display",serif;
            font-size:25px;

        }


        .user-area {

            display:flex;
            align-items:center;
            gap:12px;

        }


        .user-details {

            text-align:right;

        }


        .user-details strong {

            display:block;
            font-size:12px;

        }


        .user-details span {

            display:block;
            color:var(--muted);
            font-size:9px;

        }


        .avatar {

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

        .content {

            max-width:1100px;
            margin:auto;
            padding:42px 40px 60px;

        }


        .intro {

            margin-bottom:25px;

        }


        .eyebrow {

            color:var(--gold);
            font-size:10px;
            font-weight:700;
            letter-spacing:2px;
            text-transform:uppercase;
            margin-bottom:7px;

        }


        h1 {

            color:var(--navy);
            font-family:"Playfair Display",serif;
            font-size:38px;
            line-height:1.15;

        }


        .intro p {

            margin-top:8px;
            color:var(--muted);
            font-size:12px;

        }


        /* ALERT */

        .alert {

            margin-bottom:20px;
            padding:14px 17px;
            border-radius:8px;
            background:var(--red-bg);
            border:1px solid #efcccc;
            color:var(--red);
            font-size:10px;

        }


        .alert strong {

            display:block;
            margin-bottom:6px;

        }


        .alert ul {

            padding-left:18px;

        }


        /* FORM */

        .form-card {

            overflow:hidden;
            background:white;
            border:1px solid var(--line);
            border-radius:12px;
            box-shadow:var(--shadow);

        }


        .form-header {

            padding:22px 26px;
            border-bottom:1px solid var(--line);

        }


        .form-header h2 {

            color:var(--navy);
            font-family:"Playfair Display",serif;
            font-size:21px;

        }


        .form-header p {

            margin-top:4px;
            color:var(--muted);
            font-size:10px;

        }


        .form-body {

            padding:28px 26px;

        }


        .section {

            margin-bottom:30px;

        }


        .section:last-child {

            margin-bottom:0;

        }


        .section-title {

            margin-bottom:15px;
            color:var(--navy);
            font-size:10px;
            font-weight:700;
            letter-spacing:1.2px;
            text-transform:uppercase;

        }


        .grid {

            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:18px;

        }


        .group {

            display:flex;
            flex-direction:column;

        }


        .full {

            grid-column:1/-1;

        }


        label {

            margin-bottom:7px;
            color:var(--ink);
            font-size:10px;
            font-weight:700;

        }


        .required {

            color:var(--red);

        }


        .control {

            width:100%;
            padding:12px 13px;
            outline:none;
            border:1px solid var(--line);
            border-radius:6px;
            background:#fbfcfd;
            color:var(--ink);
            font-size:11px;

        }


        .control:focus {

            border-color:var(--gold);
            background:white;
            box-shadow:
                0 0 0 3px
                rgba(201,154,62,.10);

        }


        textarea.control {

            min-height:130px;
            resize:vertical;

        }


        .hint {

            margin-top:5px;
            color:var(--muted);
            font-size:8px;

        }


        /* FOOTER */

        .footer {

            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:15px;
            padding:20px 26px;
            background:#fbfcfd;
            border-top:1px solid var(--line);

        }


        .footer-note {

            color:var(--muted);
            font-size:8px;

        }


        .buttons {

            display:flex;
            gap:9px;

        }


        .btn {

            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:11px 18px;
            border-radius:6px;
            font-size:9px;
            font-weight:700;
            letter-spacing:.7px;
            cursor:pointer;

        }


        .cancel {

            background:white;
            border:1px solid var(--line);
            color:var(--muted);

        }


        .save {

            border:none;
            background:var(--navy);
            color:white;

        }


        .save:hover {

            background:var(--blue);

        }


        @media(max-width:800px) {

            .sidebar {

                width:72px;
                padding:20px 8px;

            }


            .brand {

                justify-content:center;
                padding:4px 0 25px;

            }


            .brand-text,
            .nav-title {

                display:none;

            }


            .nav-link {

                justify-content:center;

            }


            .nav-link span:last-child {

                display:none;

            }


            .main {

                margin-left:72px;

            }


            .content {

                padding:30px 24px;

            }

        }


        @media(max-width:600px) {

            .topbar {

                height:68px;
                padding:0 18px;

            }


            .topbar-label {

                display:none;

            }


            .user-details {

                display:none;

            }


            .page-title {

                font-size:21px;

            }


            .content {

                padding:25px 17px;

            }


            h1 {

                font-size:31px;

            }


            .form-body {

                padding:22px 18px;

            }


            .grid {

                grid-template-columns:1fr;

            }


            .full {

                grid-column:auto;

            }


            .footer {

                align-items:flex-start;
                flex-direction:column;

            }


            .buttons {

                width:100%;

            }


            .btn {

                flex:1;

                text-align:center;

            }

        }

    </style>

</head>


<body>


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
                <span class="nav-icon">▦</span>
                <span>Dashboard</span>
            </a>


            <a
                href="create-event.php"
                class="nav-link"
            >
                <span class="nav-icon">+</span>
                <span>Create Event</span>
            </a>


            <a
                href="manage-events.php"
                class="nav-link active"
            >
                <span class="nav-icon">◈</span>
                <span>Manage Events</span>
            </a>


            <!-- <a
                href="qr-scanner.php"
                class="nav-link"
            >
                <span class="nav-icon">▣</span>
                <span>QR Scanner</span>
            </a> -->


            <a
                href="media-upload.php"
                class="nav-link"
            >
                <span class="nav-icon">▧</span>
                <span>Media Upload</span>
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



    <main class="main">


        <header class="topbar">

            <div class="topbar-left">

                <span class="topbar-label">
                    Organizer Portal
                </span>

                <div class="page-title">
                    Edit Event
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


            <div class="intro">

                <div class="eyebrow">
                    Event Management
                </div>

                <h1>
                    Edit Event
                </h1>

                <p>
                    Update the event information and save
                    your latest changes.
                </p>

            </div>



            <?php if (!empty($errors)): ?>

                <div class="alert">

                    <strong>
                        Event could not be updated.
                    </strong>

                    <ul>

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= sanitize($error) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>



            <?php if ($event): ?>

                <form
                    method="POST"
                    action=""
                    class="form-card"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="event_id"
                        value="<?= sanitize($eventId) ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= sanitize($csrfToken) ?>"
                    >


                    <div class="form-header">

                        <h2>
                            Event Information
                        </h2>

                        <p>
                            Modify the fields below and
                            save your changes.
                        </p>

                    </div>



                    <div class="form-body">


                        <!-- BASIC -->

                        <div class="section">

                            <div class="section-title">
                                Basic Information
                            </div>


                            <div class="grid">


                                <div class="group full">

                                    <label for="title">
                                        Event Title
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        id="title"
                                        name="title"
                                        class="control"
                                        maxlength="200"
                                        value="<?= sanitize($title) ?>"
                                        required
                                    >

                                </div>


                                <div class="group full">

                                    <label for="subtitle">
                                        Subtitle
                                    </label>

                                    <input
                                        type="text"
                                        id="subtitle"
                                        name="subtitle"
                                        class="control"
                                        maxlength="255"
                                        value="<?= sanitize($subtitle) ?>"
                                    >

                                </div>


                                <div class="group">

                                    <label for="category">
                                        Category
                                        <span class="required">*</span>
                                    </label>

                                    <select
                                        id="category"
                                        name="category"
                                        class="control"
                                        required
                                    >

                                        <?php foreach (
                                            $categories
                                            as $value => $label
                                        ): ?>

                                            <option
                                                value="<?= sanitize($value) ?>"
                                                <?= $category === $value
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= sanitize($label) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="group">

                                    <label for="department_id">
                                        Department ID
                                    </label>

                                    <input
                                        type="text"
                                        id="department_id"
                                        name="department_id"
                                        class="control"
                                        maxlength="50"
                                        value="<?= sanitize($departmentId) ?>"
                                    >

                                </div>


                                <div class="group full">

                                    <label for="description">
                                        Description
                                    </label>

                                    <textarea
                                        id="description"
                                        name="description"
                                        class="control"
                                        maxlength="5000"
                                    ><?= sanitize($description) ?></textarea>

                                </div>


                            </div>

                        </div>



                        <!-- SCHEDULE -->

                        <div class="section">

                            <div class="section-title">
                                Schedule & Venue
                            </div>


                            <div class="grid">


                                <div class="group">

                                    <label for="start_date">
                                        Start Date
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        type="date"
                                        id="start_date"
                                        name="start_date"
                                        class="control"
                                        value="<?= sanitize($startDate) ?>"
                                        required
                                    >

                                </div>


                                <div class="group">

                                    <label for="start_time">
                                        Start Time
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        type="time"
                                        id="start_time"
                                        name="start_time"
                                        class="control"
                                        value="<?= sanitize($startTime) ?>"
                                        required
                                    >

                                </div>


                                <div class="group">

                                    <label for="end_date">
                                        End Date
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        type="date"
                                        id="end_date"
                                        name="end_date"
                                        class="control"
                                        value="<?= sanitize($endDate) ?>"
                                        required
                                    >

                                </div>


                                <div class="group">

                                    <label for="end_time">
                                        End Time
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        type="time"
                                        id="end_time"
                                        name="end_time"
                                        class="control"
                                        value="<?= sanitize($endTime) ?>"
                                        required
                                    >

                                </div>


                                <div class="group full">

                                    <label for="venue_id">
                                        Venue
                                        <span class="required">*</span>
                                    </label>

                                    <select
                                        id="venue_id"
                                        name="venue_id"
                                        class="control"
                                        required
                                    >

                                        <option value="">
                                            Select Venue
                                        </option>

                                        <?php foreach (
                                            $venues as $venue
                                        ): ?>

                                            <option
                                                value="<?= (int)$venue['venue_id'] ?>"
                                                <?= $venueId ===
                                                    (string)$venue['venue_id']
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= sanitize(
                                                    $venue['name']
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                            </div>

                        </div>



                        <!-- CAPACITY -->

                        <div class="section">

                            <div class="section-title">
                                Registration Capacity
                            </div>


                            <div class="grid">


                                <div class="group">

                                    <label for="max_seats">
                                        Maximum Seats
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        type="number"
                                        id="max_seats"
                                        name="max_seats"
                                        class="control"
                                        min="1"
                                        value="<?= sanitize($maxSeats) ?>"
                                        required
                                    >

                                </div>


                                <div class="group">

                                    <label for="waitlist_capacity">
                                        Waitlist Capacity
                                    </label>

                                    <input
                                        type="number"
                                        id="waitlist_capacity"
                                        name="waitlist_capacity"
                                        class="control"
                                        min="0"
                                        value="<?= sanitize($waitlistCapacity) ?>"
                                    >

                                </div>


                            </div>

                        </div>



                        <!-- ADDITIONAL -->

                        <div class="section">

                            <div class="section-title">
                                Additional Information
                            </div>


                            <div class="grid">


                                <div class="group full">

                                    <label for="code_of_conduct">
                                        Code of Conduct
                                    </label>

                                    <textarea
                                        id="code_of_conduct"
                                        name="code_of_conduct"
                                        class="control"
                                    ><?= sanitize($codeOfConduct) ?></textarea>

                                </div>


                                <div class="group">

                                    <label for="dress_code">
                                        Dress Code
                                    </label>

                                    <input
                                        type="text"
                                        id="dress_code"
                                        name="dress_code"
                                        class="control"
                                        maxlength="255"
                                        value="<?= sanitize($dressCode) ?>"
                                    >

                                </div>


                                <div class="group">

                                    <label for="required_materials">
                                        Required Materials
                                    </label>

                                    <input
                                        type="text"
                                        id="required_materials"
                                        name="required_materials"
                                        class="control"
                                        value="<?= sanitize($requiredMaterials) ?>"
                                    >

                                </div>


                            </div>

                        </div>


                    </div>



                    <div class="footer">

                        <div class="footer-note">

                            Event ID:
                            <?= sanitize($eventId) ?>

                        </div>


                        <div class="buttons">

                            <a
                                href="manage-events.php"
                                class="btn cancel"
                            >
                                CANCEL
                            </a>


                            <button
                                type="submit"
                                class="btn save"
                            >
                                SAVE CHANGES
                            </button>

                        </div>

                    </div>


                </form>

            <?php endif; ?>


        </section>


    </main>



    <script>

        const startDate =
            document.getElementById(
                "start_date"
            );

        const endDate =
            document.getElementById(
                "end_date"
            );


        if (
            startDate &&
            endDate
        ) {

            function syncDates() {

                if (
                    startDate.value
                ) {

                    endDate.min =
                        startDate.value;

                }

            }


            startDate.addEventListener(
                "change",
                syncDates
            );


            syncDates();

        }

    </script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

</body>

</html>