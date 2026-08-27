<?php

require_once __DIR__ . '/database.php';


/*
|--------------------------------------------------------------------------
| PASSWORD
|--------------------------------------------------------------------------
*/

function hashPassword($password)
{
    return password_hash(
        $password,
        PASSWORD_BCRYPT,
        [
            'cost' => 12
        ]
    );
}


function verifyPassword($password, $hash)
{
    $hash = trim((string)$hash);

    return password_verify(
        $password,
        $hash
    );
}


/*
|--------------------------------------------------------------------------
| SANITIZE
|--------------------------------------------------------------------------
*/

function sanitize($input)
{
    if ($input === null) {
        return '';
    }

    return htmlspecialchars(
        (string)$input,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| QR HASH
|--------------------------------------------------------------------------
*/

function generateQRHash(
    $registration_id,
    $user_id,
    $event_id
) {

    $payload = json_encode([

        'reg_id' =>
            $registration_id,

        'user_id' =>
            $user_id,

        'event_id' =>
            $event_id,

        'timestamp' =>
            time()

    ]);

    return hash_hmac(
        'sha256',
        $payload,
        QR_SECRET_KEY
    );
}


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

function formatDate($datetime)
{
    if (!$datetime) {
        return '';
    }

    return date(
        'M j, Y',
        strtotime($datetime)
    );
}


function formatDateTime($datetime)
{
    if (!$datetime) {
        return '';
    }

    return date(
        'M j, Y g:i A',
        strtotime($datetime)
    );
}


/*
|--------------------------------------------------------------------------
| UUID
|--------------------------------------------------------------------------
*/

function generateUUID()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}


/*
|--------------------------------------------------------------------------
| ICS CALENDAR
|--------------------------------------------------------------------------
*/

function generateICS($event)
{
    $start =
        new DateTime(
            $event['start_date']
        );

    $end =
        new DateTime(
            $event['end_date']
        );


    $description =
        $event['description']
        ?? '';

    $venue =
        $event['venue_name']
        ?? '';


    $ics =
        "BEGIN:VCALENDAR\r\n";

    $ics .=
        "VERSION:2.0\r\n";

    $ics .=
        "PRODID:-//Campus360//EN\r\n";

    $ics .=
        "BEGIN:VEVENT\r\n";

    $ics .=
        "UID:" .
        md5(uniqid('', true)) .
        "@campus360.edu\r\n";

    $ics .=
        "DTSTAMP:" .
        gmdate('Ymd\THis\Z') .
        "\r\n";

    $ics .=
        "DTSTART:" .
        $start->format('Ymd\THis') .
        "\r\n";

    $ics .=
        "DTEND:" .
        $end->format('Ymd\THis') .
        "\r\n";

    $ics .=
        "SUMMARY:" .
        $event['title'] .
        "\r\n";

    $ics .=
        "DESCRIPTION:" .
        substr(
            $description,
            0,
            200
        ) .
        "\r\n";

    $ics .=
        "LOCATION:" .
        $venue .
        "\r\n";

    $ics .=
        "END:VEVENT\r\n";

    $ics .=
        "END:VCALENDAR\r\n";


    return $ics;
}

?>
