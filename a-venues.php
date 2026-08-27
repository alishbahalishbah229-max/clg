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
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_venues_token'])) {

    $_SESSION['admin_venues_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken =
    $_SESSION['admin_venues_token'];


/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['admin_venues_success'] ?? '';

$errorMessage =
    $_SESSION['admin_venues_error'] ?? '';

unset(
    $_SESSION['admin_venues_success'],
    $_SESSION['admin_venues_error']
);


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

$editVenue = null;

$formVenueId = '';
$formName = '';
$formCapacity = '';
$formAddress = '';
$formCapabilities = '';

$showForm = false;


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !$postedToken ||
        !hash_equals(
            $_SESSION['admin_venues_token'],
            $postedToken
        )
    ) {

        $_SESSION['admin_venues_error'] =
            'Invalid security token. Please try again.';

        header(
            'Location: venues.php'
        );

        exit;
    }


    $action =
        $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | DATABASE CHECK
    |--------------------------------------------------------------------------
    */

    if (!$pdoConnection instanceof PDO) {

        $_SESSION['admin_venues_error'] =
            'Database connection is not available.';

        header(
            'Location: venues.php'
        );

        exit;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'create') {

            $name =
                trim(
                    $_POST['venue_name'] ?? ''
                );

            $capacity =
                filter_var(
                    $_POST['capacity'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $address =
                trim(
                    $_POST['address'] ?? ''
                );

            $capabilities =
                trim(
                    $_POST['av_capabilities'] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            if ($name === '') {

                throw new Exception(
                    'Venue name is required.'
                );

            }


            if (
                mb_strlen($name) > 100
            ) {

                throw new Exception(
                    'Venue name cannot exceed 100 characters.'
                );

            }


            if (
                $capacity === false ||
                $capacity < 1
            ) {

                throw new Exception(
                    'Capacity must be a positive number.'
                );

            }


            if (
                mb_strlen($address) > 5000
            ) {

                throw new Exception(
                    'Address is too long.'
                );

            }


            if (
                mb_strlen($capabilities) > 5000
            ) {

                throw new Exception(
                    'AV capabilities are too long.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdoConnection->prepare("
                    INSERT INTO venues
                    (
                        venue_name,
                        capacity,
                        address,
                        av_capabilities,
                        status
                    )
                    VALUES
                    (
                        :venue_name,
                        :capacity,
                        :address,
                        :av_capabilities,
                        'active'
                    )
                ");

            $stmt->execute([

                ':venue_name' =>
                    $name,

                ':capacity' =>
                    $capacity,

                ':address' =>
                    $address !== ''
                        ? $address
                        : null,

                ':av_capabilities' =>
                    $capabilities !== ''
                        ? $capabilities
                        : null

            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'venue_name' =>
                        $name,

                    'capacity' =>
                        $capacity
                ]);


            try {

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
                        'venue_created',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Venue Create Audit Error: ' .
                    $auditError->getMessage()
                );

            }


            $_SESSION['admin_venues_success'] =
                'Venue created successfully.';

        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update') {

            $venueId =
                filter_var(
                    $_POST['venue_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $name =
                trim(
                    $_POST['venue_name'] ?? ''
                );

            $capacity =
                filter_var(
                    $_POST['capacity'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $address =
                trim(
                    $_POST['address'] ?? ''
                );

            $capabilities =
                trim(
                    $_POST['av_capabilities'] ?? ''
                );


            if (
                $venueId === false ||
                $venueId < 1
            ) {

                throw new Exception(
                    'Invalid venue.'
                );

            }


            if ($name === '') {

                throw new Exception(
                    'Venue name is required.'
                );

            }


            if (
                mb_strlen($name) > 100
            ) {

                throw new Exception(
                    'Venue name cannot exceed 100 characters.'
                );

            }


            if (
                $capacity === false ||
                $capacity < 1
            ) {

                throw new Exception(
                    'Capacity must be a positive number.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK VENUE
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        venue_id,
                        venue_name
                    FROM venues
                    WHERE venue_id = :venue_id
                    LIMIT 1
                ");

            $check->execute([
                ':venue_id' =>
                    $venueId
            ]);

            $existing =
                $check->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$existing) {

                throw new Exception(
                    'Venue not found.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdoConnection->prepare("
                    UPDATE venues

                    SET
                        venue_name = :venue_name,
                        capacity = :capacity,
                        address = :address,
                        av_capabilities = :av_capabilities

                    WHERE venue_id = :venue_id
                    LIMIT 1
                ");

            $stmt->execute([

                ':venue_name' =>
                    $name,

                ':capacity' =>
                    $capacity,

                ':address' =>
                    $address !== ''
                        ? $address
                        : null,

                ':av_capabilities' =>
                    $capabilities !== ''
                        ? $capabilities
                        : null,

                ':venue_id' =>
                    $venueId

            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'venue_id' =>
                        $venueId,

                    'venue_name' =>
                        $name,

                    'capacity' =>
                        $capacity
                ]);


            try {

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
                        'venue_updated',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Venue Update Audit Error: ' .
                    $auditError->getMessage()
                );

            }


            $_SESSION['admin_venues_success'] =
                'Venue updated successfully.';

        }


        /*
        |--------------------------------------------------------------------------
        | ACTIVATE / DEACTIVATE
        |--------------------------------------------------------------------------
        */

        elseif (
            $action === 'activate' ||
            $action === 'deactivate'
        ) {

            $venueId =
                filter_var(
                    $_POST['venue_id'] ?? '',
                    FILTER_VALIDATE_INT
                );


            if (
                $venueId === false ||
                $venueId < 1
            ) {

                throw new Exception(
                    'Invalid venue.'
                );

            }


            $newStatus =
                $action === 'activate'
                    ? 'active'
                    : 'inactive';


            /*
            |--------------------------------------------------------------------------
            | CHECK VENUE
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        venue_id,
                        venue_name,
                        status
                    FROM venues
                    WHERE venue_id = :venue_id
                    LIMIT 1
                ");

            $check->execute([
                ':venue_id' =>
                    $venueId
            ]);

            $existing =
                $check->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$existing) {

                throw new Exception(
                    'Venue not found.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE STATUS
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdoConnection->prepare("
                    UPDATE venues

                    SET
                        status = :status

                    WHERE venue_id = :venue_id
                    LIMIT 1
                ");

            $stmt->execute([

                ':status' =>
                    $newStatus,

                ':venue_id' =>
                    $venueId

            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'venue_id' =>
                        $venueId,

                    'venue_name' =>
                        $existing['venue_name'],

                    'old_status' =>
                        $existing['status'],

                    'new_status' =>
                        $newStatus
                ]);


            try {

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
                        'venue_status_changed',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Venue Status Audit Error: ' .
                    $auditError->getMessage()
                );

            }


            $_SESSION['admin_venues_success'] =
                $newStatus === 'active'
                    ? 'Venue activated successfully.'
                    : 'Venue deactivated successfully.';

        }


        else {

            throw new Exception(
                'Unknown venue action.'
            );

        }

    } catch (Exception $e) {

        error_log(
            'Admin Venue Action Error: ' .
            $e->getMessage()
        );

        $_SESSION['admin_venues_error'] =
            $e->getMessage();

    } catch (PDOException $e) {

        error_log(
            'Admin Venue Database Error: ' .
            $e->getMessage()
        );

        $_SESSION['admin_venues_error'] =
            'Unable to process the venue request.';

    }


    header(
        'Location: venues.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EDIT VENUE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit']) &&
    ctype_digit(
        (string)$_GET['edit']
    )
) {

    $editId =
        (int)$_GET['edit'];

    if ($pdoConnection instanceof PDO) {

        try {

            $stmt =
                $pdoConnection->prepare("
                    SELECT
                        venue_id,
                        venue_name,
                        capacity,
                        address,
                        av_capabilities,
                        status
                    FROM venues
                    WHERE venue_id = :venue_id
                    LIMIT 1
                ");

            $stmt->execute([
                ':venue_id' =>
                    $editId
            ]);

            $editVenue =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if ($editVenue) {

                $showForm = true;

                $formVenueId =
                    $editVenue['venue_id'];

                $formName =
                    $editVenue['venue_name'];

                $formCapacity =
                    $editVenue['capacity'];

                $formAddress =
                    $editVenue['address'] ?? '';

                $formCapabilities =
                    $editVenue[
                        'av_capabilities'
                    ]
                    ?? '';

            } else {

                $errorMessage =
                    'Venue not found.';

            }

        } catch (PDOException $e) {

            error_log(
                'Edit Venue Error: ' .
                $e->getMessage()
            );

            $errorMessage =
                'Unable to load the venue.';

        }

    }
}


/*
|--------------------------------------------------------------------------
| ADD FORM
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['add'])
) {

    $showForm = true;

    $formVenueId = '';
    $formName = '';
    $formCapacity = '';
    $formAddress = '';
    $formCapabilities = '';

}


/*
|--------------------------------------------------------------------------
| LOAD VENUES
|--------------------------------------------------------------------------
*/

$venues = [];

if ($pdoConnection instanceof PDO) {

    try {

        $stmt =
            $pdoConnection->query("
                SELECT
                    venue_id,
                    venue_name,
                    capacity,
                    address,
                    av_capabilities,
                    status

                FROM venues

                ORDER BY
                    venue_id DESC
            ");

        $venues =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Load Venues Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load venues.';
    }

}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalVenues =
    count($venues);

$activeVenues = 0;
$inactiveVenues = 0;

$totalCapacity = 0;


foreach (
    $venues as $venue
) {

    $totalCapacity +=
        (int)(
            $venue['capacity']
            ?? 0
        );


    if (
        strtolower(
            (string)(
                $venue['status']
                ?? ''
            )
        ) === 'active'
    ) {

        $activeVenues++;

    } else {

        $inactiveVenues++;
    }

}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function venueStatusClass(
    string $status
): string {

    return strtolower($status) === 'active'
        ? 'status-active'
        : 'status-inactive';
}


function venueStatusLabel(
    string $status
): string {

    return ucfirst(
        strtolower($status)
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
    Venues | EventSphere
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
input,
textarea{

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

    max-width:1350px;

    margin:auto;

    padding:
        42px 40px 60px;
}


.page-intro{

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


.intro-text{

    margin-top:8px;

    color:
        var(--muted);

    font-size:12px;
}


.add-button{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        11px 16px;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;
}


.add-button:hover{

    background:
        var(--blue);
}


/* ALERT */

.alert{

    margin-bottom:18px;

    padding:
        13px 16px;

    border-radius:7px;

    font-size:10px;
}


.alert-success{

    background:
        var(--green-bg);

    border:
        1px solid #ccebd8;

    color:
        var(--green);
}


.alert-error{

    background:
        var(--red-bg);

    border:
        1px solid #efcccc;

    color:
        var(--red);
}


/* STATS */

.stats{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

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


/* FORM */

.form-card{

    margin-bottom:20px;

    overflow:hidden;

    background:white;

    border:
        1px solid var(--line);

    border-radius:11px;

    box-shadow:
        var(--shadow);
}


.form-header{

    padding:
        20px 22px;

    border-bottom:
        1px solid var(--line);
}


.form-header h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;
}


.form-header p{

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;
}


.form-body{

    padding:22px;
}


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


.control{

    width:100%;

    padding:
        11px 12px;

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


.control:focus{

    border-color:
        var(--gold);

    background:white;

    box-shadow:
        0 0 0 3px
        rgba(201,154,62,.1);
}


textarea.control{

    min-height:75px;

    resize:vertical;
}


.form-footer{

    display:flex;

    justify-content:flex-end;

    gap:8px;

    padding:
        16px 22px;

    background:
        #fbfcfd;

    border-top:
        1px solid var(--line);
}


.btn{

    padding:
        10px 15px;

    border-radius:6px;

    font-size:8px;

    font-weight:700;

    letter-spacing:.6px;
}


.btn-cancel{

    border:
        1px solid var(--line);

    color:
        var(--muted);

}


.btn-save{

    border:none;

    background:
        var(--navy);

    color:white;

    cursor:pointer;
}


/* TABLE */

.table-card{

    overflow:hidden;

    background:white;

    border:
        1px solid var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);
}


.table-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        21px 22px;

    border-bottom:
        1px solid var(--line);
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


.venues-table{

    width:100%;

    min-width:1050px;

    border-collapse:collapse;
}


.venues-table th{

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


.venues-table td{

    padding:
        14px;

    border-bottom:
        1px solid #edf0f3;

    vertical-align:middle;

    font-size:8px;
}


.venues-table tbody tr:hover{

    background:#fcfdff;
}


.venue-name{

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;
}


.venue-id{

    margin-top:3px;

    color:
        var(--muted);

    font-size:7px;
}


.capacity{

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;
}


.address{

    max-width:220px;

    color:
        var(--muted);

    font-size:8px;

}


.capabilities{

    max-width:260px;

    color:
        var(--muted);

    font-size:8px;
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


.status-active{

    background:
        var(--green-bg);

    color:
        var(--green);
}


.status-inactive{

    background:
        #eef0f3;

    color:
        var(--muted);
}


.actions{

    display:flex;

    gap:6px;

    flex-wrap:wrap;
}


.action-button{

    padding:
        7px 9px;

    border:
        1px solid var(--line);

    border-radius:5px;

    background:white;

    color:
        var(--navy);

    cursor:pointer;

    font-size:6px;

    font-weight:700;

    letter-spacing:.4px;
}


.action-button:hover{

    border-color:
        var(--gold);

    color:
        var(--gold);
}


.deactivate{

    color:
        var(--red);
}


.activate{

    color:
        var(--green);
}


/* EMPTY */

.empty{

    padding:
        60px 20px;

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
            30px 24px;
    }

    .page-intro{

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

    .stats{

        grid-template-columns:
            1fr;
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
        class="nav-link active"
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
        class="nav-link"
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
            Venues
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


<!-- INTRO -->

<div class="page-intro">


    <div>

        <div class="eyebrow">
            Campus Resources
        </div>

        <h1>
            Venue Management
        </h1>

        <p class="intro-text">
            Create and maintain campus venues used by
            CEventSphere events.
        </p>

    </div>


    <a
        href="venues.php?add=1"
        class="add-button"
    >
        + ADD VENUE
    </a>


</div>


<!-- MESSAGES -->

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


<!-- STATS -->

<div class="stats">


    <div class="stat">

        <div class="stat-label">
            Total Venues
        </div>

        <div class="stat-value">
            <?= number_format(
                $totalVenues
            ) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Active
        </div>

        <div class="stat-value">
            <?= number_format(
                $activeVenues
            ) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Inactive
        </div>

        <div class="stat-value">
            <?= number_format(
                $inactiveVenues
            ) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Total Capacity
        </div>

        <div class="stat-value">
            <?= number_format(
                $totalCapacity
            ) ?>
        </div>

    </div>


</div>


<!-- ADD / EDIT FORM -->

<?php if ($showForm): ?>


<div class="form-card">


    <div class="form-header">


        <h2>

            <?= $formVenueId !== ''
                ? 'Edit Venue'
                : 'Add New Venue' ?>

        </h2>


        <p>

            <?= $formVenueId !== ''
                ? 'Update venue information.'
                : 'Add a new campus venue to the system.' ?>

        </p>


    </div>


    <form
        method="POST"
        action="venues.php"
    >


        <input
            type="hidden"
            name="csrf_token"
            value="<?= sanitize(
                $csrfToken
            ) ?>"
        >


        <input
            type="hidden"
            name="action"
            value="<?= $formVenueId !== ''
                ? 'update'
                : 'create' ?>"
        >


        <?php if (
            $formVenueId !== ''
        ): ?>

            <input
                type="hidden"
                name="venue_id"
                value="<?= (int)(
                    $formVenueId
                ) ?>"
            >

        <?php endif; ?>


        <div class="form-body">


            <div class="form-grid">


                <div class="field">

                    <label for="venue_name">
                        Venue Name
                    </label>

                    <input
                        type="text"
                        id="venue_name"
                        name="venue_name"
                        class="control"
                        maxlength="100"
                        required
                        value="<?= sanitize(
                            $formName
                        ) ?>"
                        placeholder="e.g. Main Auditorium"
                    >

                </div>


                <div class="field">

                    <label for="capacity">
                        Capacity
                    </label>

                    <input
                        type="number"
                        id="capacity"
                        name="capacity"
                        class="control"
                        min="1"
                        required
                        value="<?= sanitize(
                            $formCapacity
                        ) ?>"
                        placeholder="e.g. 500"
                    >

                </div>


                <div class="field full">

                    <label for="address">
                        Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        class="control"
                        placeholder="Venue location / building details"
                    ><?= sanitize(
                        $formAddress
                    ) ?></textarea>

                </div>


                <div class="field full">

                    <label for="av_capabilities">
                        AV Capabilities
                    </label>

                    <textarea
                        id="av_capabilities"
                        name="av_capabilities"
                        class="control"
                        placeholder="Projector, AC, Sound System..."
                    ><?= sanitize(
                        $formCapabilities
                    ) ?></textarea>

                </div>


            </div>


        </div>


        <div class="form-footer">


            <a
                href="venues.php"
                class="btn btn-cancel"
            >
                CANCEL
            </a>


            <button
                type="submit"
                class="btn btn-save"
            >

                <?= $formVenueId !== ''
                    ? 'UPDATE VENUE'
                    : 'CREATE VENUE' ?>

            </button>


        </div>


    </form>


</div>


<?php endif; ?>


<!-- TABLE -->

<div class="table-card">


    <div class="table-header">


        <div>

            <h2>
                Campus Venues
            </h2>

            <p>
                All available venue records.
            </p>

        </div>


        <div class="table-count">

            <?= number_format(
                count($venues)
            ) ?>

            Venues

        </div>


    </div>


    <?php if (
        !empty($venues)
    ): ?>


        <div class="table-wrapper">


            <table class="venues-table">


                <thead>

                    <tr>

                        <th>
                            Venue
                        </th>

                        <th>
                            Capacity
                        </th>

                        <th>
                            Address
                        </th>

                        <th>
                            AV Capabilities
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $venues
                        as $venue
                    ): ?>


                        <?php

                        $venueStatus =
                            strtolower(
                                (string)(
                                    $venue['status']
                                    ?? 'active'
                                )
                            );

                        ?>


                        <tr>


                            <!-- NAME -->

                            <td>

                                <div
                                    class="venue-name"
                                >

                                    <?= sanitize(
                                        $venue[
                                            'venue_name'
                                        ]
                                    ) ?>

                                </div>


                                <div
                                    class="venue-id"
                                >

                                    ID:
                                    <?= (int)(
                                        $venue[
                                            'venue_id'
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <!-- CAPACITY -->

                            <td>

                                <div
                                    class="capacity"
                                >

                                    <?= number_format(
                                        (int)(
                                            $venue[
                                                'capacity'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- ADDRESS -->

                            <td>

                                <div
                                    class="address"
                                >

                                    <?= !empty(
                                        $venue[
                                            'address'
                                        ]
                                    )
                                        ? sanitize(
                                            $venue[
                                                'address'
                                            ]
                                        )
                                        : '—' ?>

                                </div>

                            </td>


                            <!-- CAPABILITIES -->

                            <td>

                                <div
                                    class="capabilities"
                                >

                                    <?= !empty(
                                        $venue[
                                            'av_capabilities'
                                        ]
                                    )
                                        ? sanitize(
                                            $venue[
                                                'av_capabilities'
                                            ]
                                        )
                                        : '—' ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        status
                                        <?= venueStatusClass(
                                            $venueStatus
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        venueStatusLabel(
                                            $venueStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>


                                <div class="actions">


                                    <a
                                        href="venues.php?edit=<?= (int)(
                                            $venue[
                                                'venue_id'
                                            ]
                                        ) ?>"
                                        class="action-button"
                                    >
                                        EDIT
                                    </a>


                                    <?php if (
                                        $venueStatus ===
                                        'active'
                                    ): ?>


                                        <form
                                            method="POST"
                                            style="margin:0;"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= sanitize(
                                                    $csrfToken
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate"
                                            >

                                            <input
                                                type="hidden"
                                                name="venue_id"
                                                value="<?= (int)(
                                                    $venue[
                                                        'venue_id'
                                                    ]
                                                ) ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    action-button
                                                    deactivate
                                                "
                                                onclick="
                                                    return confirm(
                                                        'Deactivate this venue?'
                                                    );
                                                "
                                            >
                                                DEACTIVATE
                                            </button>

                                        </form>


                                    <?php else: ?>


                                        <form
                                            method="POST"
                                            style="margin:0;"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= sanitize(
                                                    $csrfToken
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="activate"
                                            >

                                            <input
                                                type="hidden"
                                                name="venue_id"
                                                value="<?= (int)(
                                                    $venue[
                                                        'venue_id'
                                                    ]
                                                ) ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    action-button
                                                    activate
                                                "
                                            >
                                                ACTIVATE
                                            </button>

                                        </form>


                                    <?php endif; ?>


                                </div>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        </div>


    <?php else: ?>


        <div class="empty">

            No venues have been added yet.

        </div>


    <?php endif; ?>


</div>


</section>


</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
