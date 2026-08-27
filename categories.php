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

if (empty($_SESSION['admin_categories_token'])) {

    $_SESSION['admin_categories_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['admin_categories_token'];


/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['admin_categories_success'] ?? '';

$errorMessage =
    $_SESSION['admin_categories_error'] ?? '';

unset(
    $_SESSION['admin_categories_success'],
    $_SESSION['admin_categories_error']
);


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

$showForm = false;

$formCategoryId = '';
$formCategoryName = '';


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
            $_SESSION['admin_categories_token'],
            $postedToken
        )
    ) {

        $_SESSION['admin_categories_error'] =
            'Invalid security token. Please try again.';

        header(
            'Location: categories.php'
        );

        exit;
    }


    $action =
        $_POST['action'] ?? '';


    if (!$pdoConnection instanceof PDO) {

        $_SESSION['admin_categories_error'] =
            'Database connection is not available.';

        header(
            'Location: categories.php'
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
                    $_POST['name'] ?? ''
                );


            if ($name === '') {

                throw new Exception(
                    'Category name is required.'
                );
            }


            if (
                mb_strlen($name) > 100
            ) {

                throw new Exception(
                    'Category name cannot exceed 100 characters.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DUPLICATE CHECK
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        category_id
                    FROM categories
                    WHERE name = :name
                    LIMIT 1
                ");

            $check->execute([
                ':name' => $name
            ]);


            if ($check->fetch()) {

                throw new Exception(
                    'This category already exists.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdoConnection->prepare("
                    INSERT INTO categories
                    (
                        name
                    )
                    VALUES
                    (
                        :name
                    )
                ");

            $stmt->execute([
                ':name' => $name
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'category_id' =>
                        $pdoConnection->lastInsertId(),

                    'name' =>
                        $name
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
                        'category_created',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null
                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Category Create Audit Error: ' .
                    $auditError->getMessage()
                );
            }


            $_SESSION['admin_categories_success'] =
                'Category created successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update') {

            $categoryId =
                filter_var(
                    $_POST['category_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $name =
                trim(
                    $_POST['name'] ?? ''
                );


            if (
                $categoryId === false ||
                $categoryId < 1
            ) {

                throw new Exception(
                    'Invalid category.'
                );
            }


            if ($name === '') {

                throw new Exception(
                    'Category name is required.'
                );
            }


            if (
                mb_strlen($name) > 100
            ) {

                throw new Exception(
                    'Category name cannot exceed 100 characters.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK CATEGORY
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        category_id,
                        name
                    FROM categories
                    WHERE category_id = :category_id
                    LIMIT 1
                ");

            $check->execute([
                ':category_id' =>
                    $categoryId
            ]);


            $existing =
                $check->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$existing) {

                throw new Exception(
                    'Category not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DUPLICATE NAME CHECK
            |--------------------------------------------------------------------------
            */

            $duplicate =
                $pdoConnection->prepare("
                    SELECT
                        category_id
                    FROM categories
                    WHERE name = :name
                    AND category_id <> :category_id
                    LIMIT 1
                ");

            $duplicate->execute([

                ':name' =>
                    $name,

                ':category_id' =>
                    $categoryId
            ]);


            if ($duplicate->fetch()) {

                throw new Exception(
                    'Another category already has this name.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdoConnection->prepare("
                    UPDATE categories

                    SET
                        name = :name

                    WHERE category_id = :category_id

                    LIMIT 1
                ");

            $stmt->execute([

                ':name' =>
                    $name,

                ':category_id' =>
                    $categoryId
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'category_id' =>
                        $categoryId,

                    'old_name' =>
                        $existing['name'],

                    'new_name' =>
                        $name
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
                        'category_updated',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null
                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Category Update Audit Error: ' .
                    $auditError->getMessage()
                );
            }


            $_SESSION['admin_categories_success'] =
                'Category updated successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            $categoryId =
                filter_var(
                    $_POST['category_id'] ?? '',
                    FILTER_VALIDATE_INT
                );


            if (
                $categoryId === false ||
                $categoryId < 1
            ) {

                throw new Exception(
                    'Invalid category.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | LOAD CATEGORY
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        category_id,
                        name
                    FROM categories
                    WHERE category_id = :category_id
                    LIMIT 1
                ");

            $check->execute([
                ':category_id' =>
                    $categoryId
            ]);


            $category =
                $check->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$category) {

                throw new Exception(
                    'Category not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK EVENT USAGE
            |--------------------------------------------------------------------------
            |
            | Your events table currently stores category as an ENUM,
            | not category_id. Therefore we check the category name.
            |
            */

            $eventCheck =
                $pdoConnection->prepare("
                    SELECT COUNT(*)
                    FROM events
                    WHERE category = :category_name
                ");

            $eventCheck->execute([
                ':category_name' =>
                    $category['name']
            ]);


            $eventCount =
                (int)(
                    $eventCheck->fetchColumn()
                    ?? 0
                );


            if ($eventCount > 0) {

                throw new Exception(
                    'This category is currently used by existing events and cannot be deleted.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DELETE
            |--------------------------------------------------------------------------
            */

            $delete =
                $pdoConnection->prepare("
                    DELETE FROM categories
                    WHERE category_id = :category_id
                    LIMIT 1
                ");

            $delete->execute([
                ':category_id' =>
                    $categoryId
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'category_id' =>
                        $categoryId,

                    'name' =>
                        $category['name']
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
                        'category_deleted',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null
                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Category Delete Audit Error: ' .
                    $auditError->getMessage()
                );
            }


            $_SESSION['admin_categories_success'] =
                'Category deleted successfully.';
        }


        else {

            throw new Exception(
                'Unknown category action.'
            );
        }

    } catch (PDOException $e) {

        error_log(
            'Admin Category Database Error: ' .
            $e->getMessage()
        );

        if (
            isset($e->errorInfo[1]) &&
            (int)$e->errorInfo[1] === 1062
        ) {

            $_SESSION['admin_categories_error'] =
                'This category name already exists.';

        } elseif (
            isset($e->errorInfo[1]) &&
            (int)$e->errorInfo[1] === 1451
        ) {

            $_SESSION['admin_categories_error'] =
                'This category is linked to existing records and cannot be deleted.';

        } else {

            $_SESSION['admin_categories_error'] =
                'Unable to process the category request.';
        }

    } catch (Exception $e) {

        error_log(
            'Admin Category Error: ' .
            $e->getMessage()
        );

        $_SESSION['admin_categories_error'] =
            $e->getMessage();
    }


    header(
        'Location: categories.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EDIT MODE
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

    $showForm = true;

    $formCategoryId =
        (string)$editId;


    if (
        $pdoConnection instanceof PDO
    ) {

        try {

            $stmt =
                $pdoConnection->prepare("
                    SELECT
                        category_id,
                        name
                    FROM categories
                    WHERE category_id = :category_id
                    LIMIT 1
                ");

            $stmt->execute([
                ':category_id' =>
                    $editId
            ]);


            $category =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if ($category) {

                $formCategoryId =
                    (string)(
                        $category[
                            'category_id'
                        ]
                    );

                $formCategoryName =
                    $category['name'];

            } else {

                $showForm = false;

                $errorMessage =
                    'Category not found.';
            }

        } catch (PDOException $e) {

            error_log(
                'Category Edit Load Error: ' .
                $e->getMessage()
            );

            $showForm = false;

            $errorMessage =
                'Unable to load category.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADD MODE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['add'])
) {

    $showForm = true;

    $formCategoryId = '';
    $formCategoryName = '';
}


/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

if (
    $pdoConnection instanceof PDO
) {

    try {

        $stmt =
            $pdoConnection->query("
                SELECT
                    c.category_id,
                    c.name,
                    c.created_at,

                    (
                        SELECT COUNT(*)
                        FROM events e
                        WHERE e.category = c.name
                    ) AS event_count

                FROM categories c

                ORDER BY
                    c.name ASC
            ");

        $categories =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Load Categories Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load categories.';
    }
}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalCategories =
    count($categories);

$usedCategoryCount = 0;

foreach (
    $categories as $category
) {

    if (
        (int)(
            $category['event_count']
            ?? 0
        ) > 0
    ) {

        $usedCategoryCount++;
    }
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function categoryDate(
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
    Categories | EventSphere
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
input{

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

    padding:
        0 38px;

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

    max-width:1150px;

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
        repeat(2,1fr);

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


.field{

    display:flex;

    flex-direction:column;
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


.category-table{

    width:100%;

    min-width:800px;

    border-collapse:collapse;
}


.category-table th{

    padding:
        12px 15px;

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


.category-table td{

    padding:
        14px 15px;

    border-bottom:
        1px solid #edf0f3;

    vertical-align:middle;

    font-size:8px;
}


.category-table tbody tr:hover{

    background:#fcfdff;
}


.category-id{

    color:
        var(--gold);

    font-family:
        monospace;

    font-size:8px;

    font-weight:700;
}


.category-name{

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;
}


.count-badge{

    display:inline-flex;

    min-width:28px;

    align-items:center;

    justify-content:center;

    padding:
        5px 7px;

    border-radius:5px;

    background:
        var(--gold-bg);

    color:
        #8f6916;

    font-size:7px;

    font-weight:700;
}


.created-date{

    color:
        var(--muted);

    font-size:8px;
}


.actions{

    display:flex;

    gap:6px;
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


.in-use{

    color:
        #9aa3b1;

    font-size:6px;

    font-weight:700;

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
        class="nav-link active"
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
        Categories
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
        Event Classification
    </div>

    <h1>
        Category Management
    </h1>

    <p class="intro-text">
        Manage categories available forEventSphere  events.
    </p>

</div>


<a
    href="categories.php?add=1"
    class="add-button"
>
    + ADD CATEGORY
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
        Total Categories
    </div>

    <div class="stat-value">
        <?= number_format(
            $totalCategories
        ) ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Categories In Use
    </div>

    <div class="stat-value">
        <?= number_format(
            $usedCategoryCount
        ) ?>
    </div>

</div>


</div>


<!-- FORM -->

<?php if (
    $showForm
): ?>


<div class="form-card">


<div class="form-header">

    <h2>

        <?= $formCategoryId !== ''
            ? 'Edit Category'
            : 'Add Category' ?>

    </h2>


    <p>

        <?= $formCategoryId !== ''
            ? 'Update the event category name.'
            : 'Create a new event category.' ?>

    </p>

</div>


<form
    method="POST"
    action="categories.php"
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
    value="<?= $formCategoryId !== ''
        ? 'update'
        : 'create' ?>"
>


<?php if (
    $formCategoryId !== ''
): ?>

<input
    type="hidden"
    name="category_id"
    value="<?= (int)(
        $formCategoryId
    ) ?>"
>

<?php endif; ?>


<div class="form-body">


<div class="field">

    <label for="name">
        Category Name
    </label>


    <input
        type="text"
        id="name"
        name="name"
        class="control"
        maxlength="100"
        required
        value="<?= sanitize(
            $formCategoryName
        ) ?>"
        placeholder="e.g. Technical"
    >

</div>


</div>


<div class="form-footer">


<a
    href="categories.php"
    class="btn btn-cancel"
>
    CANCEL
</a>


<button
    type="submit"
    class="btn btn-save"
>

    <?= $formCategoryId !== ''
        ? 'UPDATE CATEGORY'
        : 'CREATE CATEGORY' ?>

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
        Event Categories
    </h2>

    <p>
        Categories currently available in the system.
    </p>

</div>


<div class="table-count">

    <?= number_format(
        count($categories)
    ) ?>

    Categories

</div>


</div>


<?php if (
    !empty($categories)
): ?>


<div class="table-wrapper">


<table
    class="category-table"
>


<thead>

<tr>

    <th>
        ID
    </th>

    <th>
        Category
    </th>

    <th>
        Events
    </th>

    <th>
        Created
    </th>

    <th>
        Actions
    </th>

</tr>

</thead>


<tbody>


<?php foreach (
    $categories
    as $category
): ?>


<tr>


<td>

    <div class="category-id">

        #<?= (int)(
            $category[
                'category_id'
            ]
        ) ?>

    </div>

</td>


<td>

    <div class="category-name">

        <?= sanitize(
            $category[
                'name'
            ]
        ) ?>

    </div>

</td>


<td>

    <span class="count-badge">

        <?= number_format(
            (int)(
                $category[
                    'event_count'
                ]
                ?? 0
            )
        ) ?>

    </span>

</td>


<td>

    <div class="created-date">

        <?= sanitize(
            categoryDate(
                $category[
                    'created_at'
                ]
            )
        ) ?>

    </div>

</td>


<td>


<div class="actions">


<a
    href="categories.php?edit=<?= (int)(
        $category[
            'category_id'
        ]
    ) ?>"
    class="action-button"
>
    EDIT
</a>


<?php if (
    (int)(
        $category[
            'event_count'
        ]
        ?? 0
    ) === 0
): ?>


<form
    method="POST"
    style="margin:0;"
    onsubmit="
        return confirm(
            'Delete this category permanently?'
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
    name="category_id"
    value="<?= (int)(
        $category[
            'category_id'
        ]
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


<?php else: ?>


<span class="in-use">
    IN USE
</span>


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

    No categories have been added yet.

</div>


<?php endif; ?>


</div>


</section>


</main>

<?php require_once __DIR__ . '/footer.php'; ?>
</body>

</html>
