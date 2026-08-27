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

} elseif (
    isset($db) &&
    $db instanceof PDO
) {

    $pdoConnection = $db;
}


/*
|--------------------------------------------------------------------------
| EVENT ID
|--------------------------------------------------------------------------
*/

$eventId = trim(
    $_GET['event_id'] ??
    $_POST['event_id'] ??
    ''
);


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$formName =
    $user['full_name'] ?? '';

$formEmail =
    $user['email'] ?? '';

$formRoll =
    $user['roll_number'] ?? '';

$formPhone =
    $user['phone'] ?? '';

$formDepartment =
    $user['dept_id'] ?? '';

$agree = false;


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$successMessage = '';

$errorMessage = '';

$registrationResult = null;


/*
|--------------------------------------------------------------------------
| EVENT
|--------------------------------------------------------------------------
*/

$event = null;

if (
    $eventId !== '' &&
    $pdoConnection instanceof PDO
) {

    try {

        $stmt =
            $pdoConnection->prepare("
                SELECT

                    e.event_id,
                    e.title,
                    e.subtitle,
                    e.description,
                    e.category,
                    e.max_seats,
                    e.waitlist_capacity,
                    e.start_date,
                    e.end_date,
                    e.approval_state,

                    v.venue_name,
                    v.address AS venue_address

                FROM events e

                LEFT JOIN venues v
                    ON v.venue_id = e.venue_id

                WHERE e.event_id = :event_id

                LIMIT 1
            ");

        $stmt->execute([
            ':event_id' => $eventId
        ]);

        $event =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Student Registration Event Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load the selected event.';
    }
}


/*
|--------------------------------------------------------------------------
| REGISTRATION PROCESS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $event &&
    $pdoConnection instanceof PDO
) {

    $formName =
        trim(
            $_POST['full_name'] ?? ''
        );

    $formEmail =
        trim(
            $_POST['email'] ?? ''
        );

    $formRoll =
        trim(
            $_POST['roll_number'] ?? ''
        );

    $formPhone =
        trim(
            $_POST['phone'] ?? ''
        );

    $formDepartment =
        trim(
            $_POST['dept_id'] ?? ''
        );

    $agree =
        isset(
            $_POST['agree']
        );


    try {

        /*
        |--------------------------------------------------------------------------
        | BASIC VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($formName === '') {

            throw new Exception(
                'Full name is required.'
            );
        }


        if (
            mb_strlen($formName) > 100
        ) {

            throw new Exception(
                'Full name is too long.'
            );
        }


        if ($formEmail === '') {

            throw new Exception(
                'Email address is required.'
            );
        }


        if (
            !filter_var(
                $formEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            throw new Exception(
                'Please enter a valid email address.'
            );
        }


        if (
            mb_strlen($formRoll) > 50
        ) {

            throw new Exception(
                'Roll number is too long.'
            );
        }


        if (
            mb_strlen($formPhone) > 20
        ) {

            throw new Exception(
                'Phone number is too long.'
            );
        }


        if (!$agree) {

            throw new Exception(
                'Please confirm that the information provided is correct.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EVENT CHECK
        |--------------------------------------------------------------------------
        */

        if (
            $event['approval_state'] !== 'approved'
        ) {

            throw new Exception(
                'This event is not currently available for registration.'
            );
        }


        $startTimestamp =
            strtotime(
                $event['start_date']
            );


        if (
            !$startTimestamp ||
            $startTimestamp <= time()
        ) {

            throw new Exception(
                'Registration for this event is closed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EXISTING REGISTRATION
        |--------------------------------------------------------------------------
        */

        $existingStmt =
            $pdoConnection->prepare("
                SELECT
                    reg_id,
                    status
                FROM registrations
                WHERE user_id = :user_id
                AND event_id = :event_id
                LIMIT 1
            ");

        $existingStmt->execute([

            ':user_id' =>
                $userId,

            ':event_id' =>
                $eventId

        ]);

        $existing =
            $existingStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            $existing &&
            $existing['status'] !== 'cancelled'
        ) {

            throw new Exception(
                'You are already registered for this event.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdoConnection->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | UPDATE STUDENT DETAILS
        |--------------------------------------------------------------------------
        |
        | These values already belong to the logged-in user.
        | We keep the registration table normalized.
        |
        */

        $userUpdate =
            $pdoConnection->prepare("
                UPDATE users

                SET
                    full_name = :full_name,
                    phone = :phone,
                    roll_number = :roll_number,
                    dept_id = :dept_id

                WHERE user_id = :user_id

                LIMIT 1
            ");

        $userUpdate->execute([

            ':full_name' =>
                $formName,

            ':phone' =>
                $formPhone !== ''
                    ? $formPhone
                    : null,

            ':roll_number' =>
                $formRoll !== ''
                    ? $formRoll
                    : null,

            ':dept_id' =>
                $formDepartment !== ''
                    ? $formDepartment
                    : null,

            ':user_id' =>
                $userId

        ]);


        /*
        |--------------------------------------------------------------------------
        | CONFIRMED COUNT
        |--------------------------------------------------------------------------
        */

        $confirmedStmt =
            $pdoConnection->prepare("
                SELECT COUNT(*)
                FROM registrations
                WHERE event_id = :event_id
                AND status = 'confirmed'
            ");

        $confirmedStmt->execute([
            ':event_id' =>
                $eventId
        ]);

        $confirmedCount =
            (int)(
                $confirmedStmt->fetchColumn()
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | WAITLIST COUNT
        |--------------------------------------------------------------------------
        */

        $waitlistStmt =
            $pdoConnection->prepare("
                SELECT COUNT(*)
                FROM registrations
                WHERE event_id = :event_id
                AND status = 'waitlisted'
            ");

        $waitlistStmt->execute([
            ':event_id' =>
                $eventId
        ]);

        $waitlistCount =
            (int)(
                $waitlistStmt->fetchColumn()
                ?? 0
            );


        $maxSeats =
            max(
                0,
                (int)(
                    $event['max_seats']
                    ?? 0
                )
            );


        $waitlistCapacity =
            max(
                0,
                (int)(
                    $event['waitlist_capacity']
                    ?? 0
                )
            );


        /*
        |--------------------------------------------------------------------------
        | DETERMINE STATUS
        |--------------------------------------------------------------------------
        */

        if (
            $confirmedCount < $maxSeats
        ) {

            $newStatus =
                'confirmed';

            $queuePosition =
                null;

        } elseif (
            $waitlistCount <
            $waitlistCapacity
        ) {

            $newStatus =
                'waitlisted';

            $queuePosition =
                $waitlistCount + 1;

        } else {

            throw new Exception(
                'This event is full and the waitlist is also full.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE / REUSE REGISTRATION
        |--------------------------------------------------------------------------
        */

        if (
            $existing &&
            $existing['status'] === 'cancelled'
        ) {

            $regId =
                $existing['reg_id'];


            $stmt =
                $pdoConnection->prepare("
                    UPDATE registrations

                    SET
                        status = :status,
                        queue_position = :queue_position,
                        registered_at = CURRENT_TIMESTAMP

                    WHERE reg_id = :reg_id

                    LIMIT 1
                ");

            $stmt->execute([

                ':status' =>
                    $newStatus,

                ':queue_position' =>
                    $queuePosition,

                ':reg_id' =>
                    $regId

            ]);

        } else {

            $stmt =
                $pdoConnection->prepare("
                    INSERT INTO registrations
                    (
                        user_id,
                        event_id,
                        status,
                        queue_position
                    )
                    VALUES
                    (
                        :user_id,
                        :event_id,
                        :status,
                        :queue_position
                    )
                ");

            $stmt->execute([

                ':user_id' =>
                    $userId,

                ':event_id' =>
                    $eventId,

                ':status' =>
                    $newStatus,

                ':queue_position' =>
                    $queuePosition

            ]);


            /*
            |--------------------------------------------------------------------------
            | GET NEW REG ID
            |--------------------------------------------------------------------------
            */

            $idStmt =
                $pdoConnection->prepare("
                    SELECT
                        reg_id
                    FROM registrations

                    WHERE user_id = :user_id
                    AND event_id = :event_id

                    ORDER BY
                        registered_at DESC

                    LIMIT 1
                ");

            $idStmt->execute([

                ':user_id' =>
                    $userId,

                ':event_id' =>
                    $eventId

            ]);

            $created =
                $idStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$created ||
                empty(
                    $created['reg_id']
                )
            ) {

                throw new Exception(
                    'Registration was created but could not be retrieved.'
                );
            }


            $regId =
                $created['reg_id'];
        }


        /*
        |--------------------------------------------------------------------------
        | AUDIT
        |--------------------------------------------------------------------------
        */

        try {

            $details =
                json_encode([
                    'reg_id' =>
                        $regId,

                    'event_id' =>
                        $eventId,

                    'event_title' =>
                        $event['title'],

                    'status' =>
                        $newStatus
                ]);


            $audit =
                $pdoConnection->prepare("
                    INSERT INTO audit_logs
                    (
                        log_id,
                        user_id,
                        action,
                        details,
                        ip_address
                    )
                    VALUES
                    (
                        UUID(),
                        :user_id,
                        :action,
                        :details,
                        :ip_address
                    )
                ");

            $audit->execute([

                ':user_id' =>
                    $userId,

                ':action' =>
                    'student_registered_event',

                ':details' =>
                    $details,

                ':ip_address' =>
                    $_SERVER['REMOTE_ADDR']
                    ?? null

            ]);

        } catch (PDOException $auditError) {

            error_log(
                'Student Registration Audit Error: ' .
                $auditError->getMessage()
            );
        }


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $pdoConnection->commit();


        $registrationResult = [

            'reg_id' =>
                $regId,

            'status' =>
                $newStatus,

            'queue_position' =>
                $queuePosition
        ];


        if (
            $newStatus === 'confirmed'
        ) {

            $successMessage =
                'Registration submitted successfully. Your seat is confirmed.';

        } else {

            $successMessage =
                'Registration submitted successfully. You have been added to the waitlist.';
        }


    } catch (
        PDOException $e
    ) {

        if (
            $pdoConnection->inTransaction()
        ) {

            $pdoConnection->rollBack();
        }

        error_log(
            'Student Registration Database Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to complete registration. Please try again.';


    } catch (
        Exception $e
    ) {

        if (
            $pdoConnection->inTransaction()
        ) {

            $pdoConnection->rollBack();
        }

        $errorMessage =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function studentRegisterDate(
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


function studentRegisterTime(
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
    Event Registration | EventSphere
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

    --shadow:
        0 18px 50px
        rgba(7,26,54,.08);
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
input,
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

    max-width:1100px;

    margin:
        0 auto;

    padding:
        42px 40px 20px;
}


/* INTRO */

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

    margin-top:8px;

    color:
        var(--muted);

    font-size:12px;
}


/* ALERTS */

.alert{

    margin-bottom:18px;

    padding:
        14px 16px;

    border-radius:8px;

    font-size:10px;

    line-height:1.5;
}


.alert-error{

    background:
        var(--red-bg);

    border:
        1px solid
        #efcccc;

    color:
        var(--red);
}


.alert-success{

    background:
        var(--green-bg);

    border:
        1px solid
        #ccebd8;

    color:
        var(--green);
}


/* GRID */

.registration-grid{

    display:grid;

    grid-template-columns:
        1.25fr
        .75fr;

    gap:20px;
}


/* CARD */

.card{

    overflow:hidden;

    background:#fff;

    border:
        1px solid
        var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);
}


.card-header{

    padding:
        20px 22px;

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

    margin-top:4px;

    color:
        var(--muted);

    font-size:9px;
}


.card-body{

    padding:22px;
}


/* FORM */

.form-grid{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:15px;
}


.field{

    display:flex;

    flex-direction:column;
}


.field.full{

    grid-column:
        1 / -1;
}


.field label{

    margin-bottom:6px;

    color:
        var(--ink);

    font-size:9px;

    font-weight:700;
}


.field small{

    margin-top:5px;

    color:
        var(--muted);

    font-size:7px;
}


.control{

    width:100%;

    height:43px;

    padding:
        0 11px;

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


.control:focus{

    border-color:
        var(--gold);

    background:#fff;

    box-shadow:
        0 0 0 3px
        rgba(201,154,62,.09);
}


.control[readonly]{

    background:#f5f7f9;

    color:
        var(--muted);
}


.checkbox-row{

    display:flex;

    align-items:flex-start;

    gap:8px;

    margin-top:17px;

    color:
        var(--muted);

    font-size:8px;

    line-height:1.5;
}


.checkbox-row input{

    margin-top:2px;

    accent-color:
        var(--navy);
}


/* BUTTON */

.submit-button{

    width:100%;

    margin-top:18px;

    padding:
        13px 15px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    cursor:pointer;

    font-size:9px;

    font-weight:700;

    letter-spacing:.9px;
}


.submit-button:hover{

    background:
        var(--blue);
}


.back-button{

    display:block;

    margin-top:10px;

    padding:
        11px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    text-align:center;
}


/* EVENT SUMMARY */

.event-title{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:25px;

    line-height:1.2;
}


.event-subtitle{

    margin-top:6px;

    color:
        var(--muted);

    font-size:9px;
}


.summary-list{

    margin-top:18px;

    padding-top:14px;

    border-top:
        1px solid
        var(--line);
}


.summary-row{

    display:flex;

    justify-content:space-between;

    gap:12px;

    padding:
        10px 0;

    border-bottom:
        1px solid
        #edf0f3;
}


.summary-row:last-child{

    border-bottom:none;
}


.summary-label{

    color:
        var(--muted);

    font-size:8px;
}


.summary-value{

    max-width:180px;

    color:
        var(--ink);

    font-size:8px;

    font-weight:700;

    text-align:right;
}


/* RESULT */

.result-box{

    padding:
        22px;

    border-radius:9px;

    text-align:center;
}


.result-confirmed{

    background:
        var(--green-bg);

    border:
        1px solid
        #ccebd8;
}


.result-waitlisted{

    background:
        var(--gold-bg);

    border:
        1px solid
        #ead7a7;
}


.result-icon{

    width:50px;
    height:50px;

    display:grid;

    place-items:center;

    margin:
        0 auto 12px;

    border-radius:50%;

    background:
        var(--navy);

    color:
        var(--gold-light);

    font-size:19px;
}


.result-box strong{

    display:block;

    color:
        var(--navy);

    font-size:13px;
}


.result-box p{

    margin-top:6px;

    color:
        var(--muted);

    font-size:9px;

    line-height:1.55;
}


/* RESPONSIVE */

@media(max-width:900px){

    .registration-grid{

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


    .form-grid{

        grid-template-columns:
            1fr;
    }


    .field.full{

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
        Student Portal
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
        href="events.php"
        class="nav-link active"
    >

        <span class="nav-icon">
            ◈
        </span>

        <span>
            Browse Events
        </span>

    </a>


    <a
        href="my-registrations.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ♙
        </span>

        <span>
            My Registrations
        </span>

    </a>


    <a
        href="my-tickets.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▣
        </span>

        <span>
            My Tickets
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
            Campus Media
        </span>

    </a>


    <a
        href="../../logout.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ↪
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
        Student Portal
    </span>

    <div class="page-title">
        Event Registration
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
        Campus Events
    </div>

    <h1>
        Event Registration
    </h1>

    <p>
        Complete the form below to register for your selected event.
    </p>

</div>


<?php if (
    $successMessage !== ''
): ?>

    <div class="alert alert-success">

        <?= sanitize(
            $successMessage
        ) ?>

    </div>

<?php endif; ?>


<?php if (
    $errorMessage !== ''
): ?>

    <div class="alert alert-error">

        <?= sanitize(
            $errorMessage
        ) ?>

    </div>

<?php endif; ?>


<?php if (
    !$event
): ?>


    <div class="card">

        <div class="card-body">

            <div class="result-box result-waitlisted">

                <div class="result-icon">
                    !
                </div>

                <strong>
                    Event Not Available
                </strong>

                <p>
                    The selected event could not be loaded.
                </p>

                <a
                    href="events.php"
                    class="back-button"
                >
                    BACK TO EVENTS
                </a>

            </div>

        </div>

    </div>


<?php elseif (
    $registrationResult !== null
): ?>


    <!-- SUCCESS RESULT -->

    <div class="registration-grid">


        <div class="card">

            <div class="card-header">

                <h2>
                    Registration Submitted
                </h2>

                <p>
                    Your registration has been recorded successfully.
                </p>

            </div>


            <div class="card-body">


                <div
                    class="
                        result-box
                        <?= $registrationResult[
                            'status'
                        ] === 'confirmed'
                            ? 'result-confirmed'
                            : 'result-waitlisted'
                        ?>
                    "
                >


                    <div class="result-icon">

                        <?= $registrationResult[
                            'status'
                        ] === 'confirmed'
                            ? '✓'
                            : '◷' ?>

                    </div>


                    <strong>

                        <?= $registrationResult[
                            'status'
                        ] === 'confirmed'
                            ? 'Registration Confirmed'
                            : 'Added to Waitlist' ?>

                    </strong>


                    <p>

                        <?= sanitize(
                            $successMessage
                        ) ?>

                    </p>


                    <?php if (
                        $registrationResult[
                            'status'
                        ] === 'waitlisted'
                    ): ?>

                        <p
                            style="
                                margin-top:10px;
                                font-weight:700;
                            "
                        >

                            Queue Position:
                            #<?= (int)(
                                $registrationResult[
                                    'queue_position'
                                ] ?? 0
                            ) ?>

                        </p>

                    <?php endif; ?>


                </div>


                <a
                    href="my-registrations.php"
                    class="submit-button"
                    style="
                        display:block;
                        margin-top:16px;
                        text-align:center;
                    "
                >
                    VIEW MY REGISTRATIONS
                </a>


                <a
                    href="events.php"
                    class="back-button"
                >
                    BROWSE MORE EVENTS
                </a>


            </div>

        </div>


        <div class="card">

            <div class="card-header">

                <h2>
                    Event
                </h2>

            </div>


            <div class="card-body">


                <div class="event-title">

                    <?= sanitize(
                        $event['title']
                    ) ?>

                </div>


                <div class="event-subtitle">

                    <?= !empty(
                        $event['subtitle']
                    )
                        ? sanitize(
                            $event['subtitle']
                        )
                        : 'Campus360 Event' ?>

                </div>


                <div class="summary-list">


                    <div class="summary-row">

                        <span class="summary-label">
                            Date
                        </span>

                        <span class="summary-value">

                            <?= sanitize(
                                studentRegisterDate(
                                    $event[
                                        'start_date'
                                    ]
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="summary-row">

                        <span class="summary-label">
                            Time
                        </span>

                        <span class="summary-value">

                            <?= sanitize(
                                studentRegisterTime(
                                    $event[
                                        'start_date'
                                    ]
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="summary-row">

                        <span class="summary-label">
                            Venue
                        </span>

                        <span class="summary-value">

                            <?= !empty(
                                $event[
                                    'venue_name'
                                ]
                            )
                                ? sanitize(
                                    $event[
                                        'venue_name'
                                    ]
                                )
                                : 'TBA' ?>

                        </span>

                    </div>


                </div>

            </div>

        </div>


    </div>


<?php else: ?>


    <!-- REGISTRATION FORM -->

    <div class="registration-grid">


        <!-- FORM -->

        <div class="card">


            <div class="card-header">

                <h2>
                    Student Registration Form
                </h2>

                <p>
                    Enter and confirm your student information.
                </p>

            </div>


            <div class="card-body">


                <form
                    method="POST"
                    action=""
                    onsubmit="
                        return confirm(
                            'Submit your registration for this event?'
                        );
                    "
                >


                    <input
                        type="hidden"
                        name="event_id"
                        value="<?= sanitize(
                            $eventId
                        ) ?>"
                    >


                    <div class="form-grid">


                        <div class="field">

                            <label for="full_name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                class="control"
                                maxlength="100"
                                required
                                value="<?= sanitize(
                                    $formName
                                ) ?>"
                            >

                        </div>


                        <div class="field">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="control"
                                required
                                value="<?= sanitize(
                                    $formEmail
                                ) ?>"
                            >

                        </div>


                        <div class="field">

                            <label for="roll_number">
                                Roll Number
                            </label>

                            <input
                                type="text"
                                id="roll_number"
                                name="roll_number"
                                class="control"
                                maxlength="50"
                                value="<?= sanitize(
                                    $formRoll
                                ) ?>"
                                placeholder="Enter roll number"
                            >

                        </div>


                        <div class="field">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                class="control"
                                maxlength="20"
                                value="<?= sanitize(
                                    $formPhone
                                ) ?>"
                                placeholder="Enter phone number"
                            >

                        </div>


                        <div class="field full">

                            <label for="dept_id">
                                Department ID
                            </label>

                            <input
                                type="text"
                                id="dept_id"
                                name="dept_id"
                                class="control"
                                maxlength="50"
                                value="<?= sanitize(
                                    $formDepartment
                                ) ?>"
                                placeholder="e.g. CS"
                            >

                            <small>
                                Use your existing department ID.
                            </small>

                        </div>


                    </div>


                    <label class="checkbox-row">

                        <input
                            type="checkbox"
                            name="agree"
                            value="1"
                            required
                        >

                        <span>
                            I confirm that the information provided above
                            is correct and I agree to follow the rules
                            and requirements of this event.
                        </span>

                    </label>


                    <button
                        type="submit"
                        class="submit-button"
                    >
                        SUBMIT REGISTRATION
                    </button>


                    <a
                        href="event-details.php?event_id=<?= urlencode(
                            $eventId
                        ) ?>"
                        class="back-button"
                    >
                        CANCEL
                    </a>


                </form>


            </div>


        </div>


        <!-- EVENT SUMMARY -->

        <div>


            <div class="card">


                <div class="card-header">

                    <h2>
                        Selected Event
                    </h2>

                    <p>
                        Registration details
                    </p>

                </div>


                <div class="card-body">


                    <div class="event-title">

                        <?= sanitize(
                            $event['title']
                        ) ?>

                    </div>


                    <div class="event-subtitle">

                        <?= !empty(
                            $event['subtitle']
                        )
                            ? sanitize(
                                $event['subtitle']
                            )
                            : 'Campus360 Event' ?>

                    </div>


                    <div class="summary-list">


                        <div class="summary-row">

                            <span class="summary-label">
                                Category
                            </span>

                            <span class="summary-value">

                                <?= sanitize(
                                    ucfirst(
                                        strtolower(
                                            $event[
                                                'category'
                                            ]
                                        )
                                    )
                                ) ?>

                            </span>

                        </div>


                        <div class="summary-row">

                            <span class="summary-label">
                                Date
                            </span>

                            <span class="summary-value">

                                <?= sanitize(
                                    studentRegisterDate(
                                        $event[
                                            'start_date'
                                        ]
                                    )
                                ) ?>

                            </span>

                        </div>


                        <div class="summary-row">

                            <span class="summary-label">
                                Start
                            </span>

                            <span class="summary-value">

                                <?= sanitize(
                                    studentRegisterTime(
                                        $event[
                                            'start_date'
                                        ]
                                    )
                                ) ?>

                            </span>

                        </div>


                        <div class="summary-row">

                            <span class="summary-label">
                                Venue
                            </span>

                            <span class="summary-value">

                                <?= !empty(
                                    $event[
                                        'venue_name'
                                    ]
                                )
                                    ? sanitize(
                                        $event[
                                            'venue_name'
                                        ]
                                    )
                                    : 'TBA' ?>

                            </span>

                        </div>


                        <div class="summary-row">

                            <span class="summary-label">
                                Available Seats
                            </span>

                            <span class="summary-value">

                                <?= number_format(
                                    (int)(
                                        $event[
                                            'max_seats'
                                        ] ?? 0
                                    )
                                ) ?>

                            </span>

                        </div>


                    </div>


                </div>


            </div>


            <div
                class="card"
                style="margin-top:20px;"
            >


                <div class="card-header">

                    <h2>
                        Registration Note
                    </h2>

                </div>


                <div class="card-body">

                    <p
                        style="
                            color:#697386;
                            font-size:9px;
                            line-height:1.7;
                        "
                    >

                        Registration is subject to seat availability.
                        When all seats are occupied, eligible students
                        may be placed on the event waitlist.

                    </p>

                </div>


            </div>


        </div>


    </div>


<?php endif; ?>


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>
