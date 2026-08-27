<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('organizer');

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
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['create_event_token'])) {

    $_SESSION['create_event_token'] =
        bin2hex(
            random_bytes(32)
        );

}

$csrfToken =
    $_SESSION['create_event_token'];


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$title               = '';
$subtitle            = '';
$description         = '';
$category            = '';
$departmentId        = '';
$venueId             = '';
$maxSeats            = '';
$waitlistCapacity    = '50';
$startDate           = '';
$startTime           = '';
$endDate             = '';
$endTime             = '';
$codeOfConduct       = '';
$dressCode           = '';
$requiredMaterials   = '';

$errors = [];


/*
|--------------------------------------------------------------------------
| EVENT CATEGORIES
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
| AVAILABLE VENUES
|--------------------------------------------------------------------------
|
| These IDs/names are based on your actual venues data.
|
*/

$venues = [

    [
        'venue_id' => 1,
        'name'     => 'Main Auditorium'
    ],

    [
        'venue_id' => 2,
        'name'     => 'Seminar Hall'
    ],

    [
        'venue_id' => 3,
        'name'     => 'Open Ground'
    ],

    [
        'venue_id' => 4,
        'name'     => 'Conference Room'
    ]

];


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


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
    | CSRF VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['create_event_token'],
            $_POST['csrf_token']
        )
    ) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | ORGANIZER VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($userId === '') {

        $errors[] =
            'Organizer account could not be identified.';

    }


    /*
    |--------------------------------------------------------------------------
    | TITLE
    |--------------------------------------------------------------------------
    */

    if ($title === '') {

        $errors[] =
            'Event title is required.';

    }

    elseif (mb_strlen($title) > 200) {

        $errors[] =
            'Event title cannot exceed 200 characters.';

    }


    /*
    |--------------------------------------------------------------------------
    | SUBTITLE
    |--------------------------------------------------------------------------
    */

    if (mb_strlen($subtitle) > 255) {

        $errors[] =
            'Subtitle cannot exceed 255 characters.';

    }


    /*
    |--------------------------------------------------------------------------
    | CATEGORY
    |--------------------------------------------------------------------------
    */

    if (
        !array_key_exists(
            $category,
            $categories
        )
    ) {

        $errors[] =
            'Please select a valid event category.';

    }


    /*
    |--------------------------------------------------------------------------
    | VENUE
    |--------------------------------------------------------------------------
    */

    $validVenueIds = array_map(
        'strval',
        array_column(
            $venues,
            'venue_id'
        )
    );


    if ($venueId === '') {

        $errors[] =
            'Please select a venue.';

    }

    elseif (
        !in_array(
            $venueId,
            $validVenueIds,
            true
        )
    ) {

        $errors[] =
            'Please select a valid venue.';

    }


    /*
    |--------------------------------------------------------------------------
    | MAX SEATS
    |--------------------------------------------------------------------------
    */

    if ($maxSeats === '') {

        $errors[] =
            'Maximum seats are required.';

    }

    elseif (
        !ctype_digit($maxSeats) ||
        (int)$maxSeats < 1
    ) {

        $errors[] =
            'Maximum seats must be a valid positive number.';

    }

    elseif (
        (int)$maxSeats > 100000
    ) {

        $errors[] =
            'Maximum seats cannot exceed 100,000.';

    }


    /*
    |--------------------------------------------------------------------------
    | WAITLIST
    |--------------------------------------------------------------------------
    */

    if ($waitlistCapacity === '') {

        $waitlistCapacity = '50';

    }

    elseif (
        !ctype_digit($waitlistCapacity) ||
        (int)$waitlistCapacity < 0
    ) {

        $errors[] =
            'Waitlist capacity must be a valid number.';

    }


    /*
    |--------------------------------------------------------------------------
    | START DATE/TIME
    |--------------------------------------------------------------------------
    */

    if (
        $startDate === '' ||
        $startTime === ''
    ) {

        $errors[] =
            'Start date and start time are required.';

    }


    /*
    |--------------------------------------------------------------------------
    | END DATE/TIME
    |--------------------------------------------------------------------------
    */

    if (
        $endDate === '' ||
        $endTime === ''
    ) {

        $errors[] =
            'End date and end time are required.';

    }


    /*
    |--------------------------------------------------------------------------
    | DATETIME OBJECTS
    |--------------------------------------------------------------------------
    */

    $startDateTime = null;
    $endDateTime   = null;


    if (
        $startDate !== '' &&
        $startTime !== ''
    ) {

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
                'Please enter a valid start date and time.';

        }

    }


    if (
        $endDate !== '' &&
        $endTime !== ''
    ) {

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
                'Please enter a valid end date and time.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DATE LOGIC
    |--------------------------------------------------------------------------
    */

    if (
        $startDateTime instanceof DateTime &&
        $endDateTime instanceof DateTime
    ) {

        if (
            $endDateTime <= $startDateTime
        ) {

            $errors[] =
                'End date and time must be after the start date and time.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DATABASE INSERT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {


        if (!$pdoConnection instanceof PDO) {

            $errors[] =
                'Database connection is not available.';

        }

        else {


            try {


                /*
                |--------------------------------------------------------------------------
                | GENERATE UUID
                |--------------------------------------------------------------------------
                */

                $eventId =
                    sprintf(
                        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        random_int(0, 0xffff),
                        random_int(0, 0xffff),
                        random_int(0, 0xffff),
                        random_int(0, 0x0fff) | 0x4000,
                        random_int(0, 0x3fff) | 0x8000,
                        random_int(0, 0xffff),
                        random_int(0, 0xffff),
                        random_int(0, 0xffff)
                    );


                /*
                |--------------------------------------------------------------------------
                | INSERT EVENT
                |--------------------------------------------------------------------------
                */

                $sql = "

                    INSERT INTO events
                    (
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
                        required_materials
                    )

                    VALUES
                    (
                        :event_id,
                        :title,
                        :subtitle,
                        :description,
                        :category,
                        :department_id,
                        :venue_id,
                        :max_seats,
                        :waitlist_capacity,
                        :start_date,
                        :end_date,
                        :approval_state,
                        :organizer_id,
                        :code_of_conduct,
                        :dress_code,
                        :required_materials
                    )

                ";


                $stmt =
                    $pdoConnection->prepare(
                        $sql
                    );


                $stmt->execute([

                    ':event_id' =>
                        $eventId,

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

                    ':approval_state' =>
                        'pending',

                    ':organizer_id' =>
                        $userId,

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
                            : null

                ]);


                /*
                |--------------------------------------------------------------------------
                | SUCCESS
                |--------------------------------------------------------------------------
                */

                $_SESSION['event_success'] =
                    'Event created successfully and submitted for approval.';


                header(
                    'Location: manage-events.php'
                );

                exit;


            }

            catch (PDOException $e) {

                error_log(
                    'Create Event PDO Error: ' .
                    $e->getMessage()
                );


                $errors[] =
                    'Database error: ' .
                    $e->getMessage();

            }

            catch (Throwable $e) {

                error_log(
                    'Create Event Error: ' .
                    $e->getMessage()
                );


                $errors[] =
                    'Unable to create the event. Please try again.';

            }

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
        Create Event | EventSphere
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


        input,
        textarea,
        select,
        button {

            font-family:
                inherit;

        }


        /* SIDEBAR */

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


        /* MAIN */

        .main {

            min-height:
                100vh;

            margin-left:
                250px;

        }


        /* TOPBAR */

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
                white;

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


        /* CONTENT */

        .content {

            max-width:
                1100px;

            margin:
                0 auto;

            padding:
                42px 40px 60px;

        }


        .page-intro {

            margin-bottom:
                26px;

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


        .page-intro h1 {

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


        .page-intro p {

            max-width:
                700px;

            margin-top:
                9px;

            color:
                var(--muted);

            font-size:
                12px;

        }


        /* ERROR */

        .alert {

            margin-bottom:
                20px;

            padding:
                14px 17px;

            border-radius:
                8px;

            font-size:
                10px;

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


        .alert-error strong {

            display:
                block;

            margin-bottom:
                6px;

        }


        .alert-error ul {

            padding-left:
                18px;

        }


        /* FORM CARD */

        .form-card {

            overflow:
                hidden;

            background:
                white;

            border:
                1px solid
                var(--line);

            border-radius:
                12px;

            box-shadow:
                var(--shadow);

        }


        .form-header {

            padding:
                22px 26px;

            border-bottom:
                1px solid
                var(--line);

        }


        .form-header h2 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                21px;

        }


        .form-header p {

            margin-top:
                4px;

            color:
                var(--muted);

            font-size:
                10px;

        }


        .form-body {

            padding:
                28px 26px;

        }


        .section {

            margin-bottom:
                30px;

        }


        .section:last-child {

            margin-bottom:
                0;

        }


        .section-title {

            margin-bottom:
                15px;

            color:
                var(--navy);

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                1.2px;

            text-transform:
                uppercase;

        }


        .form-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap:
                18px;

        }


        .form-group {

            display:
                flex;

            flex-direction:
                column;

        }


        .full {

            grid-column:
                1 / -1;

        }


        label {

            margin-bottom:
                7px;

            color:
                var(--ink);

            font-size:
                10px;

            font-weight:
                700;

        }


        .required {

            color:
                var(--red);

        }


        .form-control {

            width:
                100%;

            padding:
                12px 13px;

            outline:
                none;

            border:
                1px solid
                var(--line);

            border-radius:
                6px;

            background:
                #fbfcfd;

            color:
                var(--ink);

            font-size:
                11px;

            transition:
                .2s;

        }


        .form-control:focus {

            border-color:
                var(--gold);

            background:
                white;

            box-shadow:
                0 0 0 3px
                rgba(
                    201,
                    154,
                    62,
                    .10
                );

        }


        textarea.form-control {

            min-height:
                130px;

            resize:
                vertical;

        }


        .field-note {

            margin-top:
                5px;

            color:
                var(--muted);

            font-size:
                8px;

        }


        /* FOOTER */

        .form-footer {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                20px 26px;

            background:
                #fbfcfd;

            border-top:
                1px solid
                var(--line);

        }


        .footer-note {

            color:
                var(--muted);

            font-size:
                8px;

        }


        .button-group {

            display:
                flex;

            gap:
                9px;

        }


        .btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                11px 18px;

            border:
                none;

            border-radius:
                6px;

            cursor:
                pointer;

            font-size:
                9px;

            font-weight:
                700;

            letter-spacing:
                .7px;

            transition:
                .2s;

        }


        .btn-secondary {

            background:
                white;

            border:
                1px solid
                var(--line);

            color:
                var(--muted);

        }


        .btn-secondary:hover {

            border-color:
                var(--navy);

            color:
                var(--navy);

        }


        .btn-primary {

            background:
                var(--navy);

            color:
                white;

        }


        .btn-primary:hover {

            background:
                var(--blue);

            transform:
                translateY(-1px);

        }


        /* RESPONSIVE */

        @media (max-width: 800px) {

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


            .brand-text,
            .nav-title {

                display:
                    none;

            }


            .nav-link {

                justify-content:
                    center;

            }


            .nav-link span:last-child {

                display:
                    none;

            }


            .main {

                margin-left:
                    72px;

            }


            .content {

                padding:
                    30px 24px;

            }

        }


        @media (max-width: 600px) {

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


            .page-intro h1 {

                font-size:
                    31px;

            }


            .form-body {

                padding:
                    22px 18px;

            }


            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .full {

                grid-column:
                    auto;

            }


            .form-footer {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .button-group {

                width:
                    100%;

            }


            .btn {

                flex:
                    1;

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
                class="nav-link active"
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



    <!-- MAIN -->

    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <span class="topbar-label">
                    Organizer Portal
                </span>

                <div class="page-title">
                    Create Event
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


            <div class="page-intro">

                <div class="eyebrow">
                    Event Management
                </div>


                <h1>
                    Create New Event
                </h1>


                <p>
                    Add complete event information and
                    submit it through the CEventSphere event
                    management system.
                </p>

            </div>



            <?php if (!empty($errors)): ?>

                <div class="alert alert-error">

                    <strong>
                        Event could not be created.
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



            <!-- FORM -->

            <form
                method="POST"
                action=""
                class="form-card"
                autocomplete="off"
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
                        Complete the required information
                        before submitting your event.
                    </p>

                </div>



                <div class="form-body">


                    <!-- BASIC INFORMATION -->

                    <div class="section">

                        <div class="section-title">
                            Basic Information
                        </div>


                        <div class="form-grid">


                            <div class="form-group full">

                                <label for="title">
                                    Event Title
                                    <span class="required">*</span>
                                </label>


                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    class="form-control"
                                    maxlength="200"
                                    value="<?= sanitize($title) ?>"
                                    placeholder="e.g. Annual Technology Festival 2026"
                                    required
                                >

                            </div>



                            <div class="form-group full">

                                <label for="subtitle">
                                    Subtitle
                                </label>


                                <input
                                    type="text"
                                    id="subtitle"
                                    name="subtitle"
                                    class="form-control"
                                    maxlength="255"
                                    value="<?= sanitize($subtitle) ?>"
                                    placeholder="Short event tagline or summary"
                                >

                            </div>



                            <div class="form-group">

                                <label for="category">
                                    Category
                                    <span class="required">*</span>
                                </label>


                                <select
                                    id="category"
                                    name="category"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        Select Category
                                    </option>


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



                            <div class="form-group">

                                <label for="department_id">
                                    Department ID
                                </label>


                                <input
                                    type="text"
                                    id="department_id"
                                    name="department_id"
                                    class="form-control"
                                    maxlength="50"
                                    value="<?= sanitize($departmentId) ?>"
                                    placeholder="e.g. CS"
                                >

                            </div>



                            <div class="form-group full">

                                <label for="description">
                                    Description
                                </label>


                                <textarea
                                    id="description"
                                    name="description"
                                    class="form-control"
                                    maxlength="5000"
                                    placeholder="Describe the event, activities, purpose and important information..."
                                ><?= sanitize($description) ?></textarea>

                            </div>


                        </div>

                    </div>



                    <!-- SCHEDULE AND VENUE -->

                    <div class="section">

                        <div class="section-title">
                            Schedule & Venue
                        </div>


                        <div class="form-grid">


                            <div class="form-group">

                                <label for="start_date">
                                    Start Date
                                    <span class="required">*</span>
                                </label>


                                <input
                                    type="date"
                                    id="start_date"
                                    name="start_date"
                                    class="form-control"
                                    value="<?= sanitize($startDate) ?>"
                                    required
                                >

                            </div>



                            <div class="form-group">

                                <label for="start_time">
                                    Start Time
                                    <span class="required">*</span>
                                </label>


                                <input
                                    type="time"
                                    id="start_time"
                                    name="start_time"
                                    class="form-control"
                                    value="<?= sanitize($startTime) ?>"
                                    required
                                >

                            </div>



                            <div class="form-group">

                                <label for="end_date">
                                    End Date
                                    <span class="required">*</span>
                                </label>


                                <input
                                    type="date"
                                    id="end_date"
                                    name="end_date"
                                    class="form-control"
                                    value="<?= sanitize($endDate) ?>"
                                    required
                                >

                            </div>



                            <div class="form-group">

                                <label for="end_time">
                                    End Time
                                    <span class="required">*</span>
                                </label>


                                <input
                                    type="time"
                                    id="end_time"
                                    name="end_time"
                                    class="form-control"
                                    value="<?= sanitize($endTime) ?>"
                                    required
                                >

                            </div>



                            <div class="form-group full">

                                <label for="venue_id">
                                    Venue
                                    <span class="required">*</span>
                                </label>


                                <select
                                    id="venue_id"
                                    name="venue_id"
                                    class="form-control"
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


                                <span class="field-note">
                                    Select the venue where this event
                                    will take place.
                                </span>

                            </div>


                        </div>

                    </div>



                    <!-- CAPACITY -->

                    <div class="section">

                        <div class="section-title">
                            Registration Capacity
                        </div>


                        <div class="form-grid">


                            <div class="form-group">

                                <label for="max_seats">
                                    Maximum Seats
                                    <span class="required">*</span>
                                </label>


                                <input
                                    type="number"
                                    id="max_seats"
                                    name="max_seats"
                                    class="form-control"
                                    min="1"
                                    max="100000"
                                    value="<?= sanitize($maxSeats) ?>"
                                    placeholder="e.g. 200"
                                    required
                                >

                            </div>



                            <div class="form-group">

                                <label for="waitlist_capacity">
                                    Waitlist Capacity
                                </label>


                                <input
                                    type="number"
                                    id="waitlist_capacity"
                                    name="waitlist_capacity"
                                    class="form-control"
                                    min="0"
                                    value="<?= sanitize($waitlistCapacity) ?>"
                                    placeholder="50"
                                >


                                <span class="field-note">
                                    Maximum number of students allowed
                                    on the waitlist.
                                </span>

                            </div>


                        </div>

                    </div>



                    <!-- ADDITIONAL INFORMATION -->

                    <div class="section">

                        <div class="section-title">
                            Additional Information
                        </div>


                        <div class="form-grid">


                            <div class="form-group full">

                                <label for="code_of_conduct">
                                    Code of Conduct
                                </label>


                                <textarea
                                    id="code_of_conduct"
                                    name="code_of_conduct"
                                    class="form-control"
                                    placeholder="Rules and conduct guidelines for participants..."
                                ><?= sanitize($codeOfConduct) ?></textarea>

                            </div>



                            <div class="form-group">

                                <label for="dress_code">
                                    Dress Code
                                </label>


                                <input
                                    type="text"
                                    id="dress_code"
                                    name="dress_code"
                                    class="form-control"
                                    maxlength="255"
                                    value="<?= sanitize($dressCode) ?>"
                                    placeholder="e.g. Formal / College Uniform"
                                >

                            </div>



                            <div class="form-group">

                                <label for="required_materials">
                                    Required Materials
                                </label>


                                <input
                                    type="text"
                                    id="required_materials"
                                    name="required_materials"
                                    class="form-control"
                                    value="<?= sanitize($requiredMaterials) ?>"
                                    placeholder="e.g. Laptop, ID Card"
                                >

                            </div>


                        </div>

                    </div>


                </div>



                <!-- FOOTER -->

                <div class="form-footer">


                    <div class="footer-note">

                        <span class="required">
                            *
                        </span>

                        Required fields.

                        New events are submitted with
                        <strong>
                            Pending
                        </strong>
                        approval status.

                    </div>


                    <div class="button-group">


                        <a
                            href="dashboard.php"
                            class="btn btn-secondary"
                        >
                            CANCEL
                        </a>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            CREATE EVENT
                        </button>


                    </div>

                </div>


            </form>


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

    </script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>