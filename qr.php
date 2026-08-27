<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


header(
    'Content-Type: application/json'
);


/*
 * Only organizer or admin
 * can scan tickets.
 */

if (
    !isset($_SESSION['user_id']) ||
    !in_array(
        $_SESSION['role'],
        ['organizer', 'admin']
    )
) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;

}


if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);

    exit;

}


$qrHash =
    trim(
        $_POST['qr_hash'] ?? ''
    );


if ($qrHash === '') {

    echo json_encode([
        'success' => false,
        'message' => 'QR code is empty.'
    ]);

    exit;

}


try {

    global $pdo;


    /*
     * Find registration
     * using QR hash.
     */

    $stmt = $pdo->prepare(
        "
        SELECT

            r.reg_id,

            r.user_id,

            r.event_id,

            r.status AS registration_status,

            r.qr_hash,

            u.full_name,

            e.title AS event_title

        FROM registrations r

        INNER JOIN users u
            ON u.user_id = r.user_id

        INNER JOIN events e
            ON e.event_id = r.event_id

        WHERE r.qr_hash = ?

        LIMIT 1
        "
    );


    $stmt->execute([
        $qrHash
    ]);


    $registration =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$registration) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid QR ticket.'
        ]);

        exit;

    }


    /*
     * Only confirmed registrations
     * can be marked present.
     */

    if (
        $registration['registration_status']
        !== 'confirmed'
    ) {

        echo json_encode([
            'success' => false,
            'message' =>
                'This registration is not confirmed.'
        ]);

        exit;

    }


    /*
     * Check whether attendance
     * was already recorded.
     */

    $attendanceStmt =
        $pdo->prepare(
            "
            SELECT

                attendance_id,
                verification_status,
                scanned_at

            FROM attendance

            WHERE reg_id = ?

            LIMIT 1
            "
        );


    $attendanceStmt->execute([
        $registration['reg_id']
    ]);


    $attendance =
        $attendanceStmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
     * Already scanned
     */

    if ($attendance) {

        echo json_encode([

            'success' => true,

            'already_scanned' => true,

            'message' =>
                'Attendance has already been recorded for this ticket.',

            'student' =>
                $registration['full_name'],

            'event' =>
                $registration['event_title'],

            'reg_id' =>
                $registration['reg_id'],

            'status' =>
                $attendance['verification_status'],

            'scanned_at' =>
                $attendance['scanned_at']

        ]);

        exit;

    }


    /*
     * Create attendance record.
     */

    $attendanceId =
        generateUUID();


    $insert =
        $pdo->prepare(
            "
            INSERT INTO attendance
            (
                attendance_id,
                reg_id,
                scanned_by,
                verification_status,
                scanned_at
            )

            VALUES
            (
                ?,
                ?,
                ?,
                'present',
                NOW()
            )
            "
        );


    $insert->execute([

        $attendanceId,

        $registration['reg_id'],

        $_SESSION['user_id']

    ]);


    /*
     * Get newly created
     * attendance record.
     */

    $newAttendanceStmt =
        $pdo->prepare(
            "
            SELECT

                verification_status,
                scanned_at

            FROM attendance

            WHERE attendance_id = ?

            LIMIT 1
            "
        );


    $newAttendanceStmt->execute([
        $attendanceId
    ]);


    $newAttendance =
        $newAttendanceStmt->fetch(
            PDO::FETCH_ASSOC
        );


    echo json_encode([

        'success' => true,

        'already_scanned' => false,

        'message' =>
            'Student attendance marked successfully.',

        'student' =>
            $registration['full_name'],

        'event' =>
            $registration['event_title'],

        'reg_id' =>
            $registration['reg_id'],

        'status' =>
            $newAttendance['verification_status'],

        'scanned_at' =>
            $newAttendance['scanned_at']

    ]);

    exit;


} catch (PDOException $e) {

    error_log(
        'QR Scanner Error: ' .
        $e->getMessage()
    );


    echo json_encode([

        'success' => false,

        'message' =>
            'Database error occurred while verifying the ticket.'

    ]);

    exit;

}