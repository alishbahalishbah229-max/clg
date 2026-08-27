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

if (empty($_SESSION['admin_departments_token'])) {

    $_SESSION['admin_departments_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken =
    $_SESSION['admin_departments_token'];


/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['admin_departments_success'] ?? '';

$errorMessage =
    $_SESSION['admin_departments_error'] ?? '';

unset(
    $_SESSION['admin_departments_success'],
    $_SESSION['admin_departments_error']
);


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

$showForm = false;

$formDeptId = '';
$formDeptName = '';


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
            $_SESSION['admin_departments_token'],
            $postedToken
        )
    ) {

        $_SESSION['admin_departments_error'] =
            'Invalid security token. Please try again.';

        header(
            'Location: departments.php'
        );

        exit;
    }


    $action =
        $_POST['action'] ?? '';


    if (!$pdoConnection instanceof PDO) {

        $_SESSION['admin_departments_error'] =
            'Database connection is not available.';

        header(
            'Location: departments.php'
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

            $deptId =
                trim(
                    $_POST['dept_id'] ?? ''
                );

            $deptName =
                trim(
                    $_POST['dept_name'] ?? ''
                );


            if ($deptId === '') {

                throw new Exception(
                    'Department ID is required.'
                );

            }


            if (
                mb_strlen($deptId) > 50
            ) {

                throw new Exception(
                    'Department ID cannot exceed 50 characters.'
                );

            }


            if ($deptName === '') {

                throw new Exception(
                    'Department name is required.'
                );

            }


            if (
                mb_strlen($deptName) > 100
            ) {

                throw new Exception(
                    'Department name cannot exceed 100 characters.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        dept_id
                    FROM departments
                    WHERE dept_id = :dept_id
                    LIMIT 1
                ");

            $check->execute([
                ':dept_id' =>
                    $deptId
            ]);


            if ($check->fetch()) {

                throw new Exception(
                    'A department with this ID already exists.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdoConnection->prepare("
                    INSERT INTO departments
                    (
                        dept_id,
                        dept_name
                    )
                    VALUES
                    (
                        :dept_id,
                        :dept_name
                    )
                ");

            $stmt->execute([

                ':dept_id' =>
                    $deptId,

                ':dept_name' =>
                    $deptName

            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'dept_id' =>
                        $deptId,

                    'dept_name' =>
                        $deptName
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
                        'department_created',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Department Create Audit Error: ' .
                    $auditError->getMessage()
                );

            }


            $_SESSION['admin_departments_success'] =
                'Department created successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update') {

            $originalDeptId =
                trim(
                    $_POST['original_dept_id']
                    ?? ''
                );

            $deptId =
                trim(
                    $_POST['dept_id'] ?? ''
                );

            $deptName =
                trim(
                    $_POST['dept_name'] ?? ''
                );


            if (
                $originalDeptId === ''
            ) {

                throw new Exception(
                    'Invalid department.'
                );

            }


            if ($deptId === '') {

                throw new Exception(
                    'Department ID is required.'
                );

            }


            if (
                mb_strlen($deptId) > 50
            ) {

                throw new Exception(
                    'Department ID cannot exceed 50 characters.'
                );

            }


            if ($deptName === '') {

                throw new Exception(
                    'Department name is required.'
                );

            }


            if (
                mb_strlen($deptName) > 100
            ) {

                throw new Exception(
                    'Department name cannot exceed 100 characters.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK ORIGINAL
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        dept_id,
                        dept_name
                    FROM departments
                    WHERE dept_id = :dept_id
                    LIMIT 1
                ");

            $check->execute([
                ':dept_id' =>
                    $originalDeptId
            ]);


            $existing =
                $check->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$existing) {

                throw new Exception(
                    'Department not found.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK NEW ID
            |--------------------------------------------------------------------------
            */

            if (
                $deptId !== $originalDeptId
            ) {

                $duplicate =
                    $pdoConnection->prepare("
                        SELECT
                            dept_id
                        FROM departments
                        WHERE dept_id = :dept_id
                        LIMIT 1
                    ");

                $duplicate->execute([
                    ':dept_id' =>
                        $deptId
                ]);


                if (
                    $duplicate->fetch()
                ) {

                    throw new Exception(
                        'The new Department ID already exists.'
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdoConnection->prepare("
                    UPDATE departments

                    SET
                        dept_id = :dept_id,
                        dept_name = :dept_name

                    WHERE dept_id = :original_dept_id

                    LIMIT 1
                ");

            $stmt->execute([

                ':dept_id' =>
                    $deptId,

                ':dept_name' =>
                    $deptName,

                ':original_dept_id' =>
                    $originalDeptId

            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'old_dept_id' =>
                        $originalDeptId,

                    'new_dept_id' =>
                        $deptId,

                    'dept_name' =>
                        $deptName
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
                        'department_updated',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Department Update Audit Error: ' .
                    $auditError->getMessage()
                );

            }


            $_SESSION['admin_departments_success'] =
                'Department updated successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            $deptId =
                trim(
                    $_POST['dept_id'] ?? ''
                );


            if ($deptId === '') {

                throw new Exception(
                    'Invalid department.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DEPARTMENT
            |--------------------------------------------------------------------------
            */

            $check =
                $pdoConnection->prepare("
                    SELECT
                        dept_id,
                        dept_name
                    FROM departments
                    WHERE dept_id = :dept_id
                    LIMIT 1
                ");

            $check->execute([
                ':dept_id' =>
                    $deptId
            ]);


            $department =
                $check->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$department) {

                throw new Exception(
                    'Department not found.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK USERS
            |--------------------------------------------------------------------------
            */

            $userCheck =
                $pdoConnection->prepare("
                    SELECT COUNT(*)
                    FROM users
                    WHERE dept_id = :dept_id
                ");

            $userCheck->execute([
                ':dept_id' =>
                    $deptId
            ]);

            $userCount =
                (int)(
                    $userCheck->fetchColumn()
                    ?? 0
                );


            /*
            |--------------------------------------------------------------------------
            | CHECK EVENTS
            |--------------------------------------------------------------------------
            */

            $eventCheck =
                $pdoConnection->prepare("
                    SELECT COUNT(*)
                    FROM events
                    WHERE department_id = :dept_id
                ");

            $eventCheck->execute([
                ':dept_id' =>
                    $deptId
            ]);

            $eventCount =
                (int)(
                    $eventCheck->fetchColumn()
                    ?? 0
                );


            if (
                $userCount > 0 ||
                $eventCount > 0
            ) {

                throw new Exception(
                    'This department cannot be deleted because it is being used by existing users or events.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | DELETE
            |--------------------------------------------------------------------------
            */

            $delete =
                $pdoConnection->prepare("
                    DELETE FROM departments
                    WHERE dept_id = :dept_id
                    LIMIT 1
                ");

            $delete->execute([
                ':dept_id' =>
                    $deptId
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $details =
                json_encode([
                    'dept_id' =>
                        $department[
                            'dept_id'
                        ],

                    'dept_name' =>
                        $department[
                            'dept_name'
                        ]
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
                        'department_deleted',

                    ':details' =>
                        $details,

                    ':ip_address' =>
                        $_SERVER['REMOTE_ADDR']
                        ?? null

                ]);

            } catch (PDOException $auditError) {

                error_log(
                    'Department Delete Audit Error: ' .
                    $auditError->getMessage()
                );

            }


            $_SESSION['admin_departments_success'] =
                'Department deleted successfully.';
        }


        else {

            throw new Exception(
                'Unknown department action.'
            );

        }

    } catch (PDOException $e) {

        error_log(
            'Admin Department Database Error: ' .
            $e->getMessage()
        );

        /*
        | Foreign-key errors are handled with a friendly message.
        */

        if (
            (int)$e->errorInfo[1] === 1451
        ) {

            $_SESSION['admin_departments_error'] =
                'This department is linked to existing records and cannot be deleted or changed in this way.';

        } else {

            $_SESSION['admin_departments_error'] =
                'Unable to process the department request.';
        }

    } catch (Exception $e) {

        error_log(
            'Admin Department Error: ' .
            $e->getMessage()
        );

        $_SESSION['admin_departments_error'] =
            $e->getMessage();
    }


    header(
        'Location: departments.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EDIT MODE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit'])
) {

    $editId =
        trim(
            $_GET['edit']
        );


    if ($editId !== '') {

        $showForm = true;

        $formDeptId =
            $editId;


        if (
            $pdoConnection instanceof PDO
        ) {

            try {

                $stmt =
                    $pdoConnection->prepare("
                        SELECT
                            dept_id,
                            dept_name
                        FROM departments
                        WHERE dept_id = :dept_id
                        LIMIT 1
                    ");

                $stmt->execute([
                    ':dept_id' =>
                        $editId
                ]);

                $department =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if ($department) {

                    $formDeptId =
                        $department[
                            'dept_id'
                        ];

                    $formDeptName =
                        $department[
                            'dept_name'
                        ];

                } else {

                    $showForm = false;

                    $errorMessage =
                        'Department not found.';

                }

            } catch (PDOException $e) {

                error_log(
                    'Department Edit Load Error: ' .
                    $e->getMessage()
                );

                $showForm = false;

                $errorMessage =
                    'Unable to load department.';
            }

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

    $formDeptId = '';
    $formDeptName = '';

}


/*
|--------------------------------------------------------------------------
| LOAD DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments = [];

if (
    $pdoConnection instanceof PDO
) {

    try {

        $stmt =
            $pdoConnection->query("
                SELECT
                    d.dept_id,
                    d.dept_name,
                    d.created_at,

                    (
                        SELECT COUNT(*)
                        FROM users u
                        WHERE u.dept_id = d.dept_id
                    ) AS user_count,

                    (
                        SELECT COUNT(*)
                        FROM events e
                        WHERE e.department_id = d.dept_id
                    ) AS event_count

                FROM departments d

                ORDER BY
                    d.dept_name ASC
            ");

        $departments =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Load Departments Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to load departments.';
    }

}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalDepartments =
    count($departments);

$assignedUsers = 0;
$assignedEvents = 0;


foreach (
    $departments as $department
) {

    $assignedUsers +=
        (int)(
            $department[
                'user_count'
            ]
            ?? 0
        );

    $assignedEvents +=
        (int)(
            $department[
                'event_count'
            ]
            ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function departmentDate(
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
    Departments | EventSphere
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


/* TOPBAR */

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

    max-width:1250px;

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
        repeat(3,1fr);

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
        1fr 1.5fr;

    gap:15px;
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


.department-table{

    width:100%;

    min-width:850px;

    border-collapse:collapse;
}


.department-table th{

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


.department-table td{

    padding:
        14px 15px;

    border-bottom:
        1px solid #edf0f3;

    font-size:8px;

    vertical-align:middle;
}


.department-table tbody tr:hover{

    background:#fcfdff;
}


.dept-id{

    color:
        var(--gold);

    font-family:
        monospace;

    font-size:8px;

    font-weight:700;
}


.dept-name{

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

@media(max-width:900px){

    .form-grid{

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
        class="nav-link active"
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
        Departments
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
        Academic Structure
    </div>

    <h1>
        Department Management
    </h1>

    <p class="intro-text">
        Manage academic departments used across users
        and EventSphereevents.
    </p>

</div>


<a
    href="departments.php?add=1"
    class="add-button"
>
    + ADD DEPARTMENT
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
        Total Departments
    </div>

    <div class="stat-value">
        <?= number_format(
            $totalDepartments
        ) ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Assigned Users
    </div>

    <div class="stat-value">
        <?= number_format(
            $assignedUsers
        ) ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Department Events
    </div>

    <div class="stat-value">
        <?= number_format(
            $assignedEvents
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

    <?= $formDeptId !== ''
        ? 'Edit Department'
        : 'Add Department' ?>

</h2>


<p>

    <?= $formDeptId !== ''
        ? 'Update the department information.'
        : 'Create a new academic department.' ?>

</p>


</div>


<form
    method="POST"
    action="departments.php"
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
    value="<?= $formDeptId !== ''
        ? 'update'
        : 'create' ?>"
>


<?php if (
    $formDeptId !== ''
): ?>

<input
    type="hidden"
    name="original_dept_id"
    value="<?= sanitize(
        $formDeptId
    ) ?>"
>

<?php endif; ?>


<div class="form-body">


<div class="form-grid">


<div class="field">

<label for="dept_id">
    Department ID
</label>


<input
    type="text"
    id="dept_id"
    name="dept_id"
    class="control"
    maxlength="50"
    required
    value="<?= sanitize(
        $formDeptId
    ) ?>"
    placeholder="e.g. CS"
>


</div>


<div class="field">

<label for="dept_name">
    Department Name
</label>


<input
    type="text"
    id="dept_name"
    name="dept_name"
    class="control"
    maxlength="100"
    required
    value="<?= sanitize(
        $formDeptName
    ) ?>"
    placeholder="e.g. Computer Science"
>


</div>


</div>


</div>


<div class="form-footer">


<a
    href="departments.php"
    class="btn btn-cancel"
>
    CANCEL
</a>


<button
    type="submit"
    class="btn btn-save"
>

    <?= $formDeptId !== ''
        ? 'UPDATE DEPARTMENT'
        : 'CREATE DEPARTMENT' ?>

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
    Academic Departments
</h2>


<p>
    Departments currently registered in CEventSphere
</p>


</div>


<div class="table-count">

    <?= number_format(
        count($departments)
    ) ?>

    Departments

</div>


</div>


<?php if (
    !empty($departments)
): ?>


<div class="table-wrapper">


<table
    class="department-table"
>


<thead>

<tr>

    <th>
        Department ID
    </th>

    <th>
        Department
    </th>

    <th>
        Users
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
    $departments
    as $department
): ?>


<tr>


<td>

    <div class="dept-id">

        <?= sanitize(
            $department[
                'dept_id'
            ]
        ) ?>

    </div>

</td>


<td>

    <div class="dept-name">

        <?= sanitize(
            $department[
                'dept_name'
            ]
        ) ?>

    </div>

</td>


<td>

    <span class="count-badge">

        <?= number_format(
            (int)(
                $department[
                    'user_count'
                ]
                ?? 0
            )
        ) ?>

    </span>

</td>


<td>

    <span class="count-badge">

        <?= number_format(
            (int)(
                $department[
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
            departmentDate(
                $department[
                    'created_at'
                ]
            )
        ) ?>

    </div>

</td>


<td>


<div class="actions">


<a
    href="departments.php?edit=<?= urlencode(
        $department[
            'dept_id'
        ]
    ) ?>"
    class="action-button"
>
    EDIT
</a>


<?php if (
    (int)(
        $department[
            'user_count'
        ] ?? 0
    ) === 0 &&
    (int)(
        $department[
            'event_count'
        ] ?? 0
    ) === 0
): ?>


<form
    method="POST"
    style="margin:0;"
    onsubmit="
        return confirm(
            'Delete this department permanently?'
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
    name="dept_id"
    value="<?= sanitize(
        $department[
            'dept_id'
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


<span
    style="
        color:#9aa3b1;
        font-size:6px;
        font-weight:700;
    "
>
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

    No departments have been added yet.

</div>


<?php endif; ?>


</div>


</section>


</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
