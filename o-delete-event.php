<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
requireRole('organizer');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

$userId = (string)($user['user_id'] ?? '');

$eventId = trim(
    $_GET['event_id'] ?? ''
);


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($eventId === '' || $userId === '') {

    $_SESSION['event_error'] =
        'Invalid event or organizer account.';

    header(
        'Location: manage-events.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
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


if (!$pdoConnection instanceof PDO) {

    $_SESSION['event_error'] =
        'Database connection is not available.';

    header(
        'Location: manage-events.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE EVENT
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Verify ownership first
    |--------------------------------------------------------------------------
    */

    $checkStmt =
        $pdoConnection->prepare(
            "
            SELECT event_id
            FROM events
            WHERE event_id = :event_id
            AND organizer_id = :organizer_id
            LIMIT 1
            "
        );

    $checkStmt->execute([

        ':event_id' =>
            $eventId,

        ':organizer_id' =>
            $userId

    ]);


    $event =
        $checkStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$event) {

        $_SESSION['event_error'] =
            'Event not found or you do not have permission to delete it.';

        header(
            'Location: manage-events.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    $deleteStmt =
        $pdoConnection->prepare(
            "
            DELETE FROM events
            WHERE event_id = :event_id
            AND organizer_id = :organizer_id
            "
        );


    $deleteStmt->execute([

        ':event_id' =>
            $eventId,

        ':organizer_id' =>
            $userId

    ]);


    if (
        $deleteStmt->rowCount() > 0
    ) {

        $_SESSION['event_success'] =
            'Event deleted successfully.';

    }

    else {

        $_SESSION['event_error'] =
            'Event could not be deleted.';

    }


}

catch (PDOException $e) {

    error_log(
        'Delete Event Error: ' .
        $e->getMessage()
    );


    $_SESSION['event_error'] =
        'Unable to delete the event. The event may have related records.';
}


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    'Location: manage-events.php'
);

exit;