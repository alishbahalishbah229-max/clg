<?php

require_once __DIR__ . '/config/database.php';
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

if (empty($_SESSION['admin_event_approval_token'])) {

    $_SESSION['admin_event_approval_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken =
    $_SESSION['admin_event_approval_token'];


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['admin_event_success'] ?? '';

$errorMessage =
    $_SESSION['admin_event_error'] ?? '';

unset(
    $_SESSION['admin_event_success'],
    $_SESSION['admin_event_error']
);


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$statusFilter = trim(
    $_GET['status'] ?? 'pending'
);

$search = trim(
    $_GET['search'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALID STATUSES
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'draft',
    'pending',
    'approved',
    'rejected',
    'completed'
];


/*
|--------------------------------------------------------------------------
| PROCESS APPROVAL ACTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !$postedToken ||
        !hash_equals(
            $_SESSION['admin_event_approval_token'],
            $postedToken
        )
    ) {

        $_SESSION['admin_event_error'] =
            'Invalid security token. Please try again.';

        header(
            'Location: event-approvals.php'
        );

        exit;
    }


    $action =
        $_POST['action'] ?? '';

    $eventId =
        trim(
            $_POST['event_id'] ?? ''
        );

    $rejectionReason =
        trim(
            $_POST['rejection_reason'] ?? ''
        );


    if ($eventId === '') {

        $_SESSION['admin_event_error'] =
            'Invalid event selected.';

        header(
            'Location: event-approvals.php'
        );

        exit;
    }


    if (
        !$pdoConnection instanceof PDO
    ) {

        $_SESSION['admin_event_error'] =
            'Database connection is not available.';

        header(
            'Location: event-approvals.php'
        );

        exit;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | LOAD EVENT
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT
                    event_id,
                    title,
                    organizer_id,
                    approval_state
                FROM events
                WHERE event_id = :event_id
                LIMIT 1
            ");

        $stmt->execute([
            ':event_id' => $eventId
        ]);

        $event =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$event) {

            $_SESSION['admin_event_error'] =
                'Event not found.';

            header(
                'Location: event-approvals.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVE
        |--------------------------------------------------------------------------
        */

        if ($action === 'approve') {

            if (
                $event['approval_state'] !== 'pending'
            ) {

                $_SESSION['admin_event_error'] =
                    'Only pending events can be approved.';

            } else {

                $update =
                    $pdoConnection->prepare("
                        UPDATE events
                        SET
                            approval_state = 'approved',
                            rejection_reason = NULL,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE event_id = :event_id
                    ");

                $update->execute([
                    ':event_id' => $eventId
                ]);


                /*
                |--------------------------------------------------------------------------
                | AUDIT LOG
                |--------------------------------------------------------------------------
                */

                $details =
                    json_encode([
                        'event_id' =>
                            $eventId,

                        'event_title' =>
                            $event['title'],

                        'previous_state' =>
                            $event['approval_state'],

                        'new_state' =>
                            'approved'
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
                        'event_approved',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);


                $_SESSION['admin_event_success'] =
                    'Event "' .
                    $event['title'] .
                    '" approved successfully.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | REJECT
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'reject') {

            if (
                $event['approval_state'] !== 'pending'
            ) {

                $_SESSION['admin_event_error'] =
                    'Only pending events can be rejected.';

            } elseif (
                $rejectionReason === ''
            ) {

                $_SESSION['admin_event_error'] =
                    'Please provide a rejection reason.';

            } elseif (
                mb_strlen(
                    $rejectionReason
                ) > 2000
            ) {

                $_SESSION['admin_event_error'] =
                    'Rejection reason cannot exceed 2000 characters.';

            } else {

                $update =
                    $pdoConnection->prepare("
                        UPDATE events

                        SET
                            approval_state = 'rejected',
                            rejection_reason = :rejection_reason,
                            updated_at = CURRENT_TIMESTAMP

                        WHERE event_id = :event_id
                    ");

                $update->execute([

                    ':rejection_reason' =>
                        $rejectionReason,

                    ':event_id' =>
                        $eventId

                ]);


                /*
                |--------------------------------------------------------------------------
                | AUDIT LOG
                |--------------------------------------------------------------------------
                */

                $details =
                    json_encode([
                        'event_id' =>
                            $eventId,

                        'event_title' =>
                            $event['title'],

                        'previous_state' =>
                            $event['approval_state'],

                        'new_state' =>
                            'rejected',

                        'reason' =>
                            $rejectionReason
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
                        'event_rejected',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);


                $_SESSION['admin_event_success'] =
                    'Event "' .
                    $event['title'] .
                    '" rejected.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UNKNOWN ACTION
        |--------------------------------------------------------------------------
        */

        else {

            $_SESSION['admin_event_error'] =
                'Unknown event action.';
        }

    }

    catch (PDOException $e) {

        error_log(
            'Admin Event Approval Error: ' .
            $e->getMessage()
        );

        $_SESSION['admin_event_error'] =
            'Unable to process the event approval request.';
    }


    header(
        'Location: event-approvals.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD EVENTS
|--------------------------------------------------------------------------
*/

$events = [];

$loadError = '';

if (
    $pdoConnection instanceof PDO
) {

    try {

        $sql = "
            SELECT

                e.event_id,
                e.title,
                e.subtitle,
                e.category,
                e.department_id,
                e.venue_id,
                e.max_seats,
                e.start_date,
                e.end_date,
                e.approval_state,
                e.organizer_id,
                e.rejection_reason,
                e.created_at,

                u.full_name AS organizer_name,
                u.email AS organizer_email,

                v.venue_name

            FROM events e

            LEFT JOIN users u
                ON u.user_id = e.organizer_id

            LEFT JOIN venues v
                ON v.venue_id = e.venue_id

            WHERE 1 = 1
        ";

        $params = [];


        /*
        |--------------------------------------------------------------------------
        | STATUS FILTER
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $statusFilter,
                $allowedStatuses,
                true
            )
        ) {

            $sql .= "
                AND e.approval_state = :approval_state
            ";

            $params[':approval_state'] =
                $statusFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {

            $sql .= "
                AND (
                    e.title LIKE :search
                    OR e.subtitle LIKE :search
                    OR u.full_name LIKE :search
                    OR u.email LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        $sql .= "
            ORDER BY
                CASE
                    WHEN e.approval_state = 'pending'
                    THEN 0
                    ELSE 1
                END,
                e.created_at DESC
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

    } catch (PDOException $e) {

        error_log(
            'Admin Event Approval Load Error: ' .
            $e->getMessage()
        );

        $loadError =
            'Unable to load events.';

    }

} else {

    $loadError =
        'Database connection is not available.';
}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$completedCount = 0;


foreach (
    $events as $event
) {

    switch (
        strtolower(
            (string)(
                $event['approval_state']
                ?? ''
            )
        )
    ) {

        case 'pending':
            $pendingCount++;
            break;

        case 'approved':
            $approvedCount++;
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

function approvalStatusClass(
    string $status
): string {

    switch (
        strtolower($status)
    ) {

        case 'approved':
            return 'status-approved';

        case 'rejected':
            return 'status-rejected';

        case 'completed':
            return 'status-completed';

        case 'pending':
            return 'status-pending';

        default:
            return 'status-draft';
    }
}


function approvalDate(
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


function approvalDateTime(
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
    Event Approvals | EventSphere
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

    font-family:"DM Sans",sans-serif;

    background:var(--cream);

    color:var(--ink);

}

a{

    color:inherit;
    text-decoration:none;

}

button,
input,
select,
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

    padding:24px 16px;

    background:var(--navy);

    color:white;

}

.brand{

    display:flex;

    align-items:center;

    gap:12px;

    padding:4px 12px 25px;

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

.brand-text strong{

    display:block;

    font-family:"Playfair Display",serif;

    font-size:17px;

    letter-spacing:1px;

}

.brand-text small{

    display:block;

    color:var(--gold-light);

    font-size:7px;

    letter-spacing:2px;

}

.nav-section{

    margin-top:30px;

}

.nav-title{

    padding:0 12px 10px;

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

    color:var(--gold);

    font-size:9px;

    font-weight:700;

    letter-spacing:1.7px;

    text-transform:uppercase;

}

.page-title{

    color:var(--navy);

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

    color:var(--muted);

    font-size:9px;

}

.avatar{

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

.content{

    max-width:1350px;

    margin:auto;

    padding:
        42px 40px 60px;

}

.eyebrow{

    margin-bottom:7px;

    color:var(--gold);

    font-size:10px;

    font-weight:700;

    letter-spacing:2px;

    text-transform:uppercase;

}

h1{

    color:var(--navy);

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

    max-width:720px;

    margin-top:8px;

    color:var(--muted);

    font-size:12px;

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

    background:var(--green-bg);

    border:
        1px solid #ccebd8;

    color:var(--green);

}

.alert-error{

    background:var(--red-bg);

    border:
        1px solid #efcccc;

    color:var(--red);

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

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:18px;

    background:white;

    border:
        1px solid var(--line);

    border-radius:9px;

    box-shadow:var(--shadow);

}

.stat-label{

    color:var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.8px;

    text-transform:uppercase;

}

.stat-value{

    margin-top:5px;

    color:var(--navy);

    font-family:"Playfair Display",serif;

    font-size:25px;

}

.stat-icon{

    width:35px;
    height:35px;

    display:grid;

    place-items:center;

    border-radius:8px;

    background:var(--gold-bg);

    color:var(--gold);

}


/* FILTER */

.filter-card{

    margin-bottom:18px;

    padding:17px;

    background:white;

    border:
        1px solid var(--line);

    border-radius:10px;

    box-shadow:var(--shadow);

}

.filter-form{

    display:grid;

    grid-template-columns:
        1.6fr
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

    color:var(--muted);

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

    background:#fbfcfd;

    color:var(--ink);

    font-size:10px;

}

.filter-control:focus{

    border-color:var(--gold);

    background:white;

}

.filter-actions{

    display:flex;

    gap:7px;

}

.filter-btn{

    padding:
        10px 14px;

    border:none;

    border-radius:6px;

    background:var(--navy);

    color:white;

    cursor:pointer;

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

}

.clear-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        10px 14px;

    border:
        1px solid var(--line);

    border-radius:6px;

    background:white;

    color:var(--muted);

    font-size:8px;

    font-weight:700;

}


/* TABLE */

.table-card{

    overflow:hidden;

    background:white;

    border:
        1px solid var(--line);

    border-radius:12px;

    box-shadow:var(--shadow);

}

.table-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        21px 22px;

    border-bottom:
        1px solid var(--line);

}

.table-header h2{

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}

.table-header p{

    margin-top:3px;

    color:var(--muted);

    font-size:9px;

}

.table-count{

    color:var(--gold);

    font-size:9px;

    font-weight:700;

    letter-spacing:.7px;

    text-transform:uppercase;

}

.table-wrapper{

    width:100%;

    overflow-x:auto;

}

.approval-table{

    width:100%;

    min-width:1150px;

    border-collapse:collapse;

}

.approval-table th{

    padding:
        12px 14px;

    background:#fafbfd;

    border-bottom:
        1px solid var(--line);

    color:var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

    text-align:left;

    text-transform:uppercase;

}

.approval-table td{

    padding:
        15px 14px;

    border-bottom:
        1px solid #edf0f3;

    vertical-align:middle;

    font-size:8px;

}

.approval-table tbody tr:hover{

    background:#fcfdff;

}


.event-title{

    max-width:220px;

    overflow:hidden;

    color:var(--navy);

    font-size:10px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;

}

.event-subtitle{

    max-width:220px;

    margin-top:3px;

    overflow:hidden;

    color:var(--muted);

    font-size:7px;

    text-overflow:ellipsis;

    white-space:nowrap;

}

.organizer-name{

    color:var(--ink);

    font-size:8px;

    font-weight:700;

}

.organizer-email{

    margin-top:2px;

    color:var(--muted);

    font-size:7px;

}

.category{

    display:inline-flex;

    padding:
        4px 7px;

    border-radius:20px;

    background:var(--blue-bg);

    color:var(--blue);

    font-size:6px;

    font-weight:700;

    text-transform:uppercase;

}

.schedule{

    color:var(--ink);

    font-size:8px;

    font-weight:700;

}

.schedule small{

    display:block;

    margin-top:2px;

    color:var(--muted);

    font-size:7px;

}

.venue{

    color:var(--muted);

    font-size:8px;

}

.capacity{

    color:var(--navy);

    font-size:9px;

    font-weight:700;

}


/* STATUS */

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

.status-approved{

    background:var(--green-bg);

    color:var(--green);

}

.status-pending{

    background:var(--gold-bg);

    color:#9a711d;

}

.status-rejected{

    background:var(--red-bg);

    color:var(--red);

}

.status-completed{

    background:var(--blue-bg);

    color:var(--blue);

}

.status-draft{

    background:#eef0f3;

    color:var(--muted);

}


/* ACTION */

.action-area{

    display:flex;

    align-items:center;

    gap:6px;

    flex-wrap:wrap;

}

.action-btn{

    padding:
        7px 9px;

    border:
        1px solid var(--line);

    border-radius:5px;

    background:white;

    color:var(--navy);

    cursor:pointer;

    font-size:6px;

    font-weight:700;

    letter-spacing:.4px;

}

.action-btn:hover{

    border-color:var(--gold);

    color:var(--gold);

}

.approve-btn{

    border-color:#ccebd8;

    background:var(--green-bg);

    color:var(--green);

}

.reject-btn{

    border-color:#efcccc;

    background:var(--red-bg);

    color:var(--red);

}


/* VIEW */

.view-link{

    display:inline-flex;

    padding:
        7px 9px;

    border:
        1px solid var(--line);

    border-radius:5px;

    color:var(--navy);

    font-size:6px;

    font-weight:700;

    letter-spacing:.4px;

}

.view-link:hover{

    border-color:var(--gold);

    color:var(--gold);

}


/* EMPTY */

.empty{

    padding:
        65px 25px;

    color:var(--muted);

    text-align:center;

    font-size:10px;

}


/* MODAL */

.modal{

    position:fixed;

    inset:0;

    display:none;

    align-items:center;

    justify-content:center;

    padding:20px;

    background:
        rgba(7,26,54,.58);

    z-index:500;

}

.modal.show{

    display:flex;

}

.modal-card{

    width:100%;

    max-width:520px;

    background:white;

    border-radius:12px;

    box-shadow:
        0 30px 90px
        rgba(0,0,0,.20);

}

.modal-header{

    padding:
        20px 22px;

    border-bottom:
        1px solid var(--line);

}

.modal-header h2{

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;

}

.modal-header p{

    margin-top:3px;

    color:var(--muted);

    font-size:9px;

}

.modal-body{

    padding:21px 22px;

}

.modal-body label{

    display:block;

    margin-bottom:7px;

    color:var(--ink);

    font-size:9px;

    font-weight:700;

}

.modal-body textarea{

    width:100%;

    min-height:130px;

    padding:11px;

    resize:vertical;

    outline:none;

    border:
        1px solid var(--line);

    border-radius:6px;

    background:#fbfcfd;

    color:var(--ink);

    font-size:10px;

}

.modal-body textarea:focus{

    border-color:var(--gold);

    background:white;

}

.modal-footer{

    display:flex;

    justify-content:flex-end;

    gap:8px;

    padding:
        16px 22px;

    background:#fbfcfd;

    border-top:
        1px solid var(--line);

}

.modal-btn{

    padding:
        10px 14px;

    border-radius:6px;

    cursor:pointer;

    font-size:8px;

    font-weight:700;

    letter-spacing:.6px;

}

.modal-cancel{

    border:
        1px solid var(--line);

    background:white;

    color:var(--muted);

}

.modal-submit{

    border:none;

    background:var(--red);

    color:white;

}


@media(max-width:1100px){

    .stats{

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
        class="nav-link active"
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
            Event Approvals
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
        Event Administration
    </div>

    <h1>
        Event Approvals
    </h1>

    <p>
        Review organizer-submitted events and approve
        or reject them before they become active on EventSphere
    </p>

</div>


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


<?php if ($loadError !== ''): ?>

    <div class="alert alert-error">

        <?= sanitize(
            $loadError
        ) ?>

    </div>

<?php endif; ?>


<!-- STATS -->

<div class="stats">


    <div class="stat">

        <div>

            <div class="stat-label">
                Pending
            </div>

            <div class="stat-value">
                <?= number_format(
                    $pendingCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ◷
        </div>

    </div>


    <div class="stat">

        <div>

            <div class="stat-label">
                Approved
            </div>

            <div class="stat-value">
                <?= number_format(
                    $approvedCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ✓
        </div>

    </div>


    <div class="stat">

        <div>

            <div class="stat-label">
                Rejected
            </div>

            <div class="stat-value">
                <?= number_format(
                    $rejectedCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            !
        </div>

    </div>


    <div class="stat">

        <div>

            <div class="stat-label">
                Completed
            </div>

            <div class="stat-value">
                <?= number_format(
                    $completedCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ✓
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
                Search Events
            </label>

            <input
                type="text"
                id="search"
                name="search"
                class="filter-control"
                value="<?= sanitize(
                    $search
                ) ?>"
                placeholder="Event title or organizer..."
            >

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

                <?php foreach (
                    $allowedStatuses
                    as $status
                ): ?>

                    <option
                        value="<?= sanitize(
                            $status
                        ) ?>"
                        <?= $statusFilter ===
                            $status
                            ? 'selected'
                            : '' ?>
                    >

                        <?= sanitize(
                            ucfirst($status)
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="filter-actions">

            <button
                type="submit"
                class="filter-btn"
            >
                FILTER
            </button>

            <a
                href="event-approvals.php"
                class="clear-btn"
            >
                PENDING
            </a>

        </div>


    </form>


</div>


<!-- TABLE -->

<div class="table-card">


    <div class="table-header">


        <div>

            <h2>
                Submitted Events
            </h2>

            <p>
                Review event information before making an administrative decision.
            </p>

        </div>


        <div class="table-count">

            <?= number_format(
                count($events)
            ) ?>

            Events

        </div>


    </div>


    <?php if (!empty($events)): ?>


        <div class="table-wrapper">


            <table
                class="approval-table"
            >


                <thead>

                    <tr>

                        <th>
                            Event
                        </th>

                        <th>
                            Organizer
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
                            Seats
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
                        $events
                        as $event
                    ): ?>


                        <?php

                        $eventStatus =
                            strtolower(
                                (string)(
                                    $event[
                                        'approval_state'
                                    ]
                                    ?? ''
                                )
                            );

                        ?>


                        <tr>


                            <!-- EVENT -->

                            <td>

                                <div
                                    class="event-title"
                                    title="<?= sanitize(
                                        $event[
                                            'title'
                                        ]
                                    ) ?>"
                                >

                                    <?= sanitize(
                                        $event[
                                            'title'
                                        ]
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $event[
                                            'subtitle'
                                        ]
                                    )
                                ): ?>

                                    <div
                                        class="event-subtitle"
                                    >

                                        <?= sanitize(
                                            $event[
                                                'subtitle'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- ORGANIZER -->

                            <td>

                                <div
                                    class="organizer-name"
                                >

                                    <?= sanitize(
                                        $event[
                                            'organizer_name'
                                        ]
                                        ??
                                        'Unassigned'
                                    ) ?>

                                </div>


                                <div
                                    class="organizer-email"
                                >

                                    <?= sanitize(
                                        $event[
                                            'organizer_email'
                                        ]
                                        ??
                                        '—'
                                    ) ?>

                                </div>

                            </td>


                            <!-- CATEGORY -->

                            <td>

                                <span class="category">

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

                            </td>


                            <!-- SCHEDULE -->

                            <td>

                                <div
                                    class="schedule"
                                >

                                    <?= sanitize(
                                        approvalDate(
                                            $event[
                                                'start_date'
                                            ]
                                        )
                                    ) ?>

                                </div>


                                <small>

                                    <?= sanitize(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $event[
                                                    'start_date'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </small>

                            </td>


                            <!-- VENUE -->

                            <td>

                                <div
                                    class="venue"
                                >

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
                                        : '—' ?>

                                </div>

                            </td>


                            <!-- SEATS -->

                            <td>

                                <div
                                    class="capacity"
                                >

                                    <?= number_format(
                                        (int)(
                                            $event[
                                                'max_seats'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        status
                                        <?= approvalStatusClass(
                                            $eventStatus
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $eventStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>


                                <div
                                    class="action-area"
                                >


                                    <a
                                        href="view-event.php?event_id=<?= urlencode(
                                            $event[
                                                'event_id'
                                            ]
                                        ) ?>"
                                        class="view-link"
                                    >
                                        VIEW
                                    </a>


                                    <?php if (
                                        $eventStatus ===
                                        'pending'
                                    ): ?>


                                        <!-- APPROVE -->

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
                                                value="approve"
                                            >

                                            <input
                                                type="hidden"
                                                name="event_id"
                                                value="<?= sanitize(
                                                    $event[
                                                        'event_id'
                                                    ]
                                                ) ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    action-btn
                                                    approve-btn
                                                "
                                                onclick="
                                                    return confirm(
                                                        'Approve this event?'
                                                    );
                                                "
                                            >
                                                APPROVE
                                            </button>

                                        </form>


                                        <!-- REJECT -->

                                        <button
                                            type="button"
                                            class="
                                                action-btn
                                                reject-btn
                                            "
                                            onclick="
                                                openRejectModal(
                                                    '<?= htmlspecialchars(
                                                        $event['event_id'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>',
                                                    '<?= htmlspecialchars(
                                                        $event['title'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>'
                                                );
                                            "
                                        >
                                            REJECT
                                        </button>


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

            No events matched the selected filters.

        </div>


    <?php endif; ?>


</div>


</section>


</main>


<!-- REJECT MODAL -->

<div
    id="rejectModal"
    class="modal"
>


    <div class="modal-card">


        <div class="modal-header">

            <h2>
                Reject Event
            </h2>

            <p id="rejectEventTitle">
                Please provide a reason for rejection.
            </p>

        </div>


        <form
            method="POST"
            action=""
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
                value="reject"
            >


            <input
                type="hidden"
                name="event_id"
                id="rejectEventId"
                value=""
            >


            <div class="modal-body">


                <label for="rejection_reason">

                    Rejection Reason

                </label>


                <textarea
                    id="rejection_reason"
                    name="rejection_reason"
                    maxlength="2000"
                    placeholder="Explain why this event is being rejected..."
                    required
                ></textarea>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="modal-btn modal-cancel"
                    onclick="closeRejectModal()"
                >
                    CANCEL
                </button>


                <button
                    type="submit"
                    class="modal-btn modal-submit"
                >
                    REJECT EVENT
                </button>


            </div>


        </form>


    </div>


</div>


<script>

function openRejectModal(
    eventId,
    eventTitle
) {

    const modal =
        document.getElementById(
            "rejectModal"
        );

    const eventIdInput =
        document.getElementById(
            "rejectEventId"
        );

    const title =
        document.getElementById(
            "rejectEventTitle"
        );

    eventIdInput.value =
        eventId;

    title.textContent =
        'Reject "' +
        eventTitle +
        '" and provide a reason.';

    modal.classList.add(
        "show"
    );

    setTimeout(
        function() {

            document.getElementById(
                "rejection_reason"
            ).focus();

        },
        50
    );
}


function closeRejectModal() {

    const modal =
        document.getElementById(
            "rejectModal"
        );

    modal.classList.remove(
        "show"
    );

    document.getElementById(
        "rejection_reason"
    ).value = "";

}


document.getElementById(
    "rejectModal"
).addEventListener(
    "click",
    function(event) {

        if (
            event.target === this
        ) {

            closeRejectModal();

        }

    }
);


document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            closeRejectModal();

        }

    }
);

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
