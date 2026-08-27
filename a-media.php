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

if (empty($_SESSION['admin_media_token'])) {
    $_SESSION['admin_media_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['admin_media_token'];


/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['admin_media_success'] ?? '';

$errorMessage =
    $_SESSION['admin_media_error'] ?? '';

unset(
    $_SESSION['admin_media_success'],
    $_SESSION['admin_media_error']
);


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$eventFilter = trim(
    $_GET['event_id'] ?? ''
);

$publishFilter = trim(
    $_GET['published'] ?? ''
);

$typeFilter = trim(
    $_GET['media_type'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALID MEDIA TYPES
|--------------------------------------------------------------------------
*/

$mediaTypes = [
    'photo' => 'Photo',
    'video' => 'Video'
];


/*
|--------------------------------------------------------------------------
| EVENTS
|--------------------------------------------------------------------------
*/

$events = [];

if ($pdoConnection instanceof PDO) {

    try {

        $stmt = $pdoConnection->query("
            SELECT
                event_id,
                title
            FROM events
            ORDER BY start_date DESC
        ");

        $events =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Admin Media Events Error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| ACTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !$postedToken ||
        !hash_equals(
            $_SESSION['admin_media_token'],
            $postedToken
        )
    ) {

        $_SESSION['admin_media_error'] =
            'Invalid security token.';

        header(
            'Location: media.php'
        );

        exit;
    }


    $action =
        $_POST['action'] ?? '';

    $mediaId =
        trim(
            $_POST['media_id'] ?? ''
        );


    if ($mediaId === '') {

        $_SESSION['admin_media_error'] =
            'Invalid media item.';

        header(
            'Location: media.php'
        );

        exit;
    }


    if (!$pdoConnection instanceof PDO) {

        $_SESSION['admin_media_error'] =
            'Database connection is not available.';

        header(
            'Location: media.php'
        );

        exit;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | LOAD MEDIA
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                SELECT
                    mg.media_id,
                    mg.event_id,
                    mg.media_type,
                    mg.file_url,
                    mg.caption,
                    mg.is_published,
                    mg.uploaded_by,
                    e.title AS event_title
                FROM media_gallery mg
                INNER JOIN events e
                    ON e.event_id = mg.event_id
                WHERE mg.media_id = :media_id
                LIMIT 1
            ");

        $stmt->execute([
            ':media_id' => $mediaId
        ]);

        $media =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$media) {

            $_SESSION['admin_media_error'] =
                'Media item not found.';

            header(
                'Location: media.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | PUBLISH / UNPUBLISH
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'publish' ||
            $action === 'unpublish'
        ) {

            $newValue =
                $action === 'publish'
                    ? 1
                    : 0;


            $update =
                $pdoConnection->prepare("
                    UPDATE media_gallery
                    SET
                        is_published = :is_published
                    WHERE media_id = :media_id
                ");

            $update->execute([

                ':is_published' =>
                    $newValue,

                ':media_id' =>
                    $mediaId

            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'media_id' =>
                        $mediaId,

                    'event_id' =>
                        $media['event_id'],

                    'event_title' =>
                        $media['event_title'],

                    'new_published_state' =>
                        $newValue
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
                        $newValue
                            ? 'media_published'
                            : 'media_unpublished',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null
                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Admin Media Audit Error: ' .
                    $auditError->getMessage()
                );
            }


            $_SESSION['admin_media_success'] =
                $newValue
                    ? 'Media published successfully.'
                    : 'Media unpublished successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            $delete =
                $pdoConnection->prepare("
                    DELETE FROM media_gallery
                    WHERE media_id = :media_id
                ");

            $delete->execute([
                ':media_id' => $mediaId
            ]);


            if ($delete->rowCount() > 0) {

                $fileUrl =
                    $media['file_url'] ?? '';

                $filePath =
                    __DIR__ .
                    '/../../' .
                    ltrim(
                        $fileUrl,
                        './'
                    );


                if (is_file($filePath)) {
                    @unlink($filePath);
                }


                /*
                |--------------------------------------------------------------------------
                | AUDIT
                |--------------------------------------------------------------------------
                */

                $details =
                    json_encode([
                        'media_id' =>
                            $mediaId,

                        'event_id' =>
                            $media['event_id'],

                        'event_title' =>
                            $media['event_title']
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
                            'media_deleted',

                        ':details' =>
                            $details,

                        ':ip_address' =>
                            $_SERVER['REMOTE_ADDR']
                            ?? null

                    ]);

                } catch (PDOException $auditError) {

                    error_log(
                        'Admin Media Delete Audit Error: ' .
                        $auditError->getMessage()
                    );
                }


                $_SESSION['admin_media_success'] =
                    'Media deleted successfully.';

            } else {

                $_SESSION['admin_media_error'] =
                    'Media could not be deleted.';
            }
        }


        else {

            $_SESSION['admin_media_error'] =
                'Unknown media action.';
        }

    } catch (PDOException $e) {

        error_log(
            'Admin Media Action Error: ' .
            $e->getMessage()
        );

        $_SESSION['admin_media_error'] =
            'Unable to process the media action.';
    }


    header(
        'Location: media.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD MEDIA
|--------------------------------------------------------------------------
*/

$mediaItems = [];

$loadError = '';

if ($pdoConnection instanceof PDO) {

    try {

        $sql = "
            SELECT

                mg.media_id,
                mg.event_id,
                mg.media_type,
                mg.file_url,
                mg.caption,
                mg.is_published,
                mg.uploaded_by,
                mg.created_at,

                e.title AS event_title,

                uploader.full_name AS uploader_name,
                uploader.email AS uploader_email

            FROM media_gallery mg

            INNER JOIN events e
                ON e.event_id = mg.event_id

            LEFT JOIN users uploader
                ON uploader.user_id = mg.uploaded_by

            WHERE 1 = 1
        ";

        $params = [];


        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {

            $sql .= "
                AND (
                    e.title LIKE :search
                    OR mg.caption LIKE :search
                    OR uploader.full_name LIKE :search
                    OR uploader.email LIKE :search
                    OR mg.media_id LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | EVENT FILTER
        |--------------------------------------------------------------------------
        */

        if ($eventFilter !== '') {

            $sql .= "
                AND mg.event_id = :event_id
            ";

            $params[':event_id'] =
                $eventFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | PUBLISH FILTER
        |--------------------------------------------------------------------------
        */

        if ($publishFilter === 'published') {

            $sql .= "
                AND mg.is_published = 1
            ";

        } elseif ($publishFilter === 'unpublished') {

            $sql .= "
                AND mg.is_published = 0
            ";
        }


        /*
        |--------------------------------------------------------------------------
        | TYPE FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $typeFilter !== '' &&
            array_key_exists(
                $typeFilter,
                $mediaTypes
            )
        ) {

            $sql .= "
                AND mg.media_type = :media_type
            ";

            $params[':media_type'] =
                $typeFilter;
        }


        $sql .= "
            ORDER BY
                mg.created_at DESC
        ";


        $stmt =
            $pdoConnection->prepare(
                $sql
            );

        $stmt->execute(
            $params
        );

        $mediaItems =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Admin Media Load Error: ' .
            $e->getMessage()
        );

        $loadError =
            'Unable to load media gallery.';
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

$totalMedia =
    count($mediaItems);

$publishedCount = 0;
$unpublishedCount = 0;
$photoCount = 0;
$videoCount = 0;


foreach (
    $mediaItems as $item
) {

    if (
        (int)(
            $item['is_published'] ?? 0
        ) === 1
    ) {

        $publishedCount++;

    } else {

        $unpublishedCount++;
    }


    if (
        strtolower(
            (string)(
                $item['media_type'] ?? ''
            )
        ) === 'photo'
    ) {

        $photoCount++;

    } elseif (
        strtolower(
            (string)(
                $item['media_type'] ?? ''
            )
        ) === 'video'
    ) {

        $videoCount++;
    }
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function adminMediaDate(
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
    Media Gallery | EventSphere
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

    padding:24px 16px;

    background:var(--navy);
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

    color:var(--gold-light);

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

    max-width:1400px;

    margin:auto;

    padding:
        42px 40px 60px;
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

.intro{

    margin-bottom:25px;
}

.intro p{

    max-width:720px;

    margin-top:8px;

    color:
        var(--muted);

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

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:18px;

    background:white;

    border:
        1px solid var(--line);

    border-radius:10px;

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

.stat-icon{

    width:35px;
    height:35px;

    display:grid;
    place-items:center;

    border-radius:8px;

    background:
        var(--gold-bg);

    color:
        var(--gold);
}


/* FILTER */

.filter-card{

    margin-bottom:18px;

    padding:17px;

    background:white;

    border:
        1px solid var(--line);

    border-radius:10px;

    box-shadow:
        var(--shadow);
}

.filter-form{

    display:grid;

    grid-template-columns:
        1.4fr 1fr 1fr 1fr auto;

    gap:10px;

    align-items:end;
}

.filter-group{

    display:flex;

    flex-direction:column;
}

.filter-group label{

    margin-bottom:6px;

    color:
        var(--muted);

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

    background:
        #fbfcfd;

    color:
        var(--ink);

    font-size:10px;
}

.filter-control:focus{

    border-color:
        var(--gold);

    background:white;
}

.filter-actions{

    display:flex;

    gap:7px;
}

.filter-button{

    padding:
        10px 14px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    cursor:pointer;

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;
}

.clear-button{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        10px 14px;

    border:
        1px solid var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;
}


/* GALLERY */

.gallery-card{

    overflow:hidden;

    background:white;

    border:
        1px solid var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);
}

.gallery-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        21px 22px;

    border-bottom:
        1px solid var(--line);
}

.gallery-header h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:20px;
}

.gallery-header p{

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;
}

.gallery-count{

    color:
        var(--gold);

    font-size:9px;

    font-weight:700;

    text-transform:uppercase;
}

.media-grid{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:16px;

    padding:22px;
}


/* MEDIA CARD */

.media-card{

    overflow:hidden;

    background:#fbfcfd;

    border:
        1px solid var(--line);

    border-radius:9px;
}

.media-preview{

    position:relative;

    aspect-ratio:1;

    overflow:hidden;

    background:#edf1f5;
}

.media-preview img,
.media-preview video{

    width:100%;
    height:100%;

    display:block;

    object-fit:cover;
}

.media-type{

    position:absolute;

    left:8px;
    top:8px;

    padding:
        5px 7px;

    border-radius:20px;

    background:
        rgba(7,26,54,.8);

    color:white;

    font-size:6px;

    font-weight:700;

    letter-spacing:.5px;

    text-transform:uppercase;
}

.publish-status{

    position:absolute;

    right:8px;
    top:8px;

    padding:
        5px 7px;

    border-radius:20px;

    font-size:6px;

    font-weight:700;

    letter-spacing:.5px;

    text-transform:uppercase;
}

.published{

    background:
        var(--green-bg);

    color:
        var(--green);
}

.unpublished{

    background:
        var(--gold-bg);

    color:
        #9a711d;
}

.media-body{

    padding:12px;
}

.event-name{

    overflow:hidden;

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;

    text-overflow:ellipsis;

    white-space:nowrap;
}

.caption{

    min-height:28px;

    margin-top:5px;

    overflow:hidden;

    color:
        var(--muted);

    font-size:8px;

    line-height:1.45;
}

.uploader{

    margin-top:7px;

    color:
        #8a94a3;

    font-size:7px;
}

.media-date{

    margin-top:3px;

    color:
        #8a94a3;

    font-size:7px;
}

.media-actions{

    display:flex;

    gap:6px;

    margin-top:11px;
}

.action-form{

    flex:1;

    margin:0;
}

.action-button{

    width:100%;

    padding:
        7px 5px;

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

.delete-button{

    color:
        var(--red);
}

.delete-button:hover{

    border-color:
        var(--red);

    background:
        var(--red-bg);

}


/* EMPTY */

.empty{

    padding:
        65px 25px;

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

    .filter-form{

        grid-template-columns:
            1fr 1fr;
    }

    .filter-actions{

        grid-column:
            1 / -1;
    }

    .media-grid{

        grid-template-columns:
            repeat(3,1fr);
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

    .media-grid{

        grid-template-columns:
            repeat(2,1fr);

        padding:15px;
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
        class="nav-link active"
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
            Media Gallery
        </div>

    </div>


    <div class="user-area">

        <div class="user-details">

            <strong>
                <?= sanitize($userName) ?>
            </strong>

            <span>
                System Administrator
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
        Media Administration
    </div>

    <h1>
        Media Gallery
    </h1>

    <p>
        Review event media uploaded by organizers and
        control its publication status.
    </p>

</div>


<?php if ($successMessage !== ''): ?>

    <div class="alert alert-success">
        <?= sanitize($successMessage) ?>
    </div>

<?php endif; ?>


<?php if ($errorMessage !== ''): ?>

    <div class="alert alert-error">
        <?= sanitize($errorMessage) ?>
    </div>

<?php endif; ?>


<?php if ($loadError !== ''): ?>

    <div class="alert alert-error">
        <?= sanitize($loadError) ?>
    </div>

<?php endif; ?>


<!-- STATS -->

<div class="stats">


    <div class="stat">

        <div>

            <div class="stat-label">
                Total Media
            </div>

            <div class="stat-value">
                <?= number_format(
                    $totalMedia
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ▧
        </div>

    </div>


    <div class="stat">

        <div>

            <div class="stat-label">
                Published
            </div>

            <div class="stat-value">
                <?= number_format(
                    $publishedCount
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
                Unpublished
            </div>

            <div class="stat-value">
                <?= number_format(
                    $unpublishedCount
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
                Photos
            </div>

            <div class="stat-value">
                <?= number_format(
                    $photoCount
                ) ?>
            </div>

        </div>

        <div class="stat-icon">
            ◇
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
            Search
        </label>

        <input
            type="text"
            id="search"
            name="search"
            class="filter-control"
            value="<?= sanitize(
                $search
            ) ?>"
            placeholder="Event, caption, uploader..."
        >

    </div>


    <div class="filter-group">

        <label for="event_id">
            Event
        </label>

        <select
            id="event_id"
            name="event_id"
            class="filter-control"
        >

            <option value="">
                All Events
            </option>

            <?php foreach (
                $events as $event
            ): ?>

                <option
                    value="<?= sanitize(
                        $event['event_id']
                    ) ?>"
                    <?= $eventFilter ===
                        $event['event_id']
                        ? 'selected'
                        : '' ?>
                >

                    <?= sanitize(
                        $event['title']
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="filter-group">

        <label for="published">
            Visibility
        </label>

        <select
            id="published"
            name="published"
            class="filter-control"
        >

            <option value="">
                All
            </option>

            <option
                value="published"
                <?= $publishFilter === 'published'
                    ? 'selected'
                    : '' ?>
            >
                Published
            </option>

            <option
                value="unpublished"
                <?= $publishFilter === 'unpublished'
                    ? 'selected'
                    : '' ?>
            >
                Unpublished
            </option>

        </select>

    </div>


    <div class="filter-group">

        <label for="media_type">
            Type
        </label>

        <select
            id="media_type"
            name="media_type"
            class="filter-control"
        >

            <option value="">
                All Types
            </option>

            <?php foreach (
                $mediaTypes as
                $value => $label
            ): ?>

                <option
                    value="<?= sanitize(
                        $value
                    ) ?>"
                    <?= $typeFilter === $value
                        ? 'selected'
                        : '' ?>
                >

                    <?= sanitize(
                        $label
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="filter-actions">

        <button
            type="submit"
            class="filter-button"
        >
            FILTER
        </button>

        <a
            href="media.php"
            class="clear-button"
        >
            CLEAR
        </a>

    </div>


</form>


</div>


<!-- GALLERY -->

<div class="gallery-card">


<div class="gallery-header">

    <div>

        <h2>
            Event Media
        </h2>

        <p>
            Media uploaded through the organizer portal.
        </p>

    </div>

    <div class="gallery-count">

        <?= number_format(
            count($mediaItems)
        ) ?>

        Items

    </div>

</div>


<?php if (!empty($mediaItems)): ?>


<div class="media-grid">


<?php foreach (
    $mediaItems
    as $item
): ?>


<?php

$isPublished =
    (int)(
        $item['is_published']
        ?? 0
    ) === 1;

$mediaType =
    strtolower(
        (string)(
            $item['media_type']
            ?? 'photo'
        )
    );

?>


<div class="media-card">


<div class="media-preview">


<?php if (
    $mediaType === 'video'
): ?>

    <video
        src="<?= sanitize(
            $item['file_url']
        ) ?>"
        controls
        preload="metadata"
    ></video>

<?php else: ?>

    <img
        src="<?= sanitize(
            $item['file_url']
        ) ?>"
        alt="Event media"
        loading="lazy"
    >

<?php endif; ?>


<span class="media-type">

    <?= sanitize(
        ucfirst(
            $mediaType
        )
    ) ?>

</span>


<span
    class="
        publish-status
        <?= $isPublished
            ? 'published'
            : 'unpublished' ?>
    "
>

    <?= $isPublished
        ? 'Published'
        : 'Hidden' ?>

</span>


</div>


<div class="media-body">


<div
    class="event-name"
    title="<?= sanitize(
        $item['event_title']
    ) ?>"
>

    <?= sanitize(
        $item['event_title']
    ) ?>

</div>


<div class="caption">

    <?= !empty(
        $item['caption']
    )
        ? sanitize(
            $item['caption']
        )
        : 'No caption provided.' ?>

</div>


<div class="uploader">

    Uploaded by:
    <?= !empty(
        $item['uploader_name']
    )
        ? sanitize(
            $item['uploader_name']
        )
        : 'Unknown user' ?>

</div>


<div class="media-date">

    <?= sanitize(
        adminMediaDate(
            $item['created_at']
        )
    ) ?>

</div>


<div class="media-actions">


<form
    method="POST"
    class="action-form"
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
        value="<?= $isPublished
            ? 'unpublish'
            : 'publish' ?>"
    >

    <input
        type="hidden"
        name="media_id"
        value="<?= sanitize(
            $item['media_id']
        ) ?>"
    >


    <button
        type="submit"
        class="action-button"
    >

        <?= $isPublished
            ? 'UNPUBLISH'
            : 'PUBLISH' ?>

    </button>

</form>


<form
    method="POST"
    class="action-form"
    onsubmit="
        return confirm(
            'Delete this media permanently?'
        );
    "
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
        value="delete"
    >

    <input
        type="hidden"
        name="media_id"
        value="<?= sanitize(
            $item['media_id']
        ) ?>"
    >


    <button
        type="submit"
        class="
            action-button
            delete-button
        "
    >
        DELETE
    </button>

</form>


</div>


</div>


</div>


<?php endforeach; ?>


</div>


<?php else: ?>


<div class="empty">

    No media items matched the selected filters.

</div>


<?php endif; ?>


</div>


</section>


</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
