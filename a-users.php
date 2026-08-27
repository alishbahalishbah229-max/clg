<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CURRENT ADMIN
|--------------------------------------------------------------------------
*/

$user = getCurrentUser();

$userName = $user['full_name'] ?? 'Administrator';

$initial = strtoupper(
    substr(
        trim($userName),
        0,
        1
    )
);


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['users_csrf_token'])) {

    $_SESSION['users_csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['users_csrf_token'];


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';



/*
|--------------------------------------------------------------------------
| USER ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {

        $errorMessage =
            'Invalid security token. Please try again.';

    } else {

        $action =
            $_POST['action'] ?? '';

        $targetUserId =
            trim(
                $_POST['user_id'] ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDATE TARGET USER
        |--------------------------------------------------------------------------
        */

        $targetUser = null;

        if ($targetUserId !== '') {

            try {

                $stmt = $pdo->prepare("
                    SELECT
                        user_id,
                        email,
                        role,
                        full_name,
                        dept_id,
                        roll_number,
                        phone,
                        status
                    FROM users
                    WHERE user_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $targetUserId
                ]);

                $targetUser =
                    $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {

                error_log(
                    'User lookup error: ' .
                    $e->getMessage()
                );

                $errorMessage =
                    'Unable to find the selected user.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DO ACTION
        |--------------------------------------------------------------------------
        */

        if (
            $errorMessage === '' &&
            !$targetUser
        ) {

            $errorMessage =
                'Selected user was not found.';

        } elseif (
            $errorMessage === '' &&
            $targetUser
        ) {


            /*
            |--------------------------------------------------------------------------
            | NEVER MODIFY ADMIN
            |--------------------------------------------------------------------------
            */

            if (
                $targetUser['role'] === 'admin'
            ) {

                $errorMessage =
                    'Administrator accounts cannot be modified from this page.';

            } else {


                /*
                |--------------------------------------------------------------------------
                | SUSPEND
                |--------------------------------------------------------------------------
                */

                if (
                    $action === 'suspend'
                ) {

                    try {

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET status = 'suspended'
                            WHERE user_id = ?
                            AND role <> 'admin'
                        ");

                        $stmt->execute([
                            $targetUserId
                        ]);

                        $successMessage =
                            'User account suspended successfully.';

                    } catch (PDOException $e) {

                        error_log(
                            'Suspend user error: ' .
                            $e->getMessage()
                        );

                        $errorMessage =
                            'Unable to suspend the account.';
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | ACTIVATE
                |--------------------------------------------------------------------------
                */

                elseif (
                    $action === 'activate'
                ) {

                    try {

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET status = 'active'
                            WHERE user_id = ?
                            AND role <> 'admin'
                        ");

                        $stmt->execute([
                            $targetUserId
                        ]);

                        $successMessage =
                            'User account activated successfully.';

                    } catch (PDOException $e) {

                        error_log(
                            'Activate user error: ' .
                            $e->getMessage()
                        );

                        $errorMessage =
                            'Unable to activate the account.';
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | PROMOTE STUDENT TO ORGANIZER
                |--------------------------------------------------------------------------
                */

                elseif (
                    $action === 'promote'
                ) {

                    if (
                        $targetUser['role']
                        !==
                        'student'
                    ) {

                        $errorMessage =
                            'Only student accounts can be promoted to organizer.';

                    } else {

                        try {

                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET role = 'organizer'
                                WHERE user_id = ?
                                AND role = 'student'
                            ");

                            $stmt->execute([
                                $targetUserId
                            ]);

                            $successMessage =
                                'Student promoted to organizer successfully.';

                        } catch (PDOException $e) {

                            error_log(
                                'Promote user error: ' .
                                $e->getMessage()
                            );

                            $errorMessage =
                                'Unable to promote the student.';
                        }
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | CHANGE ORGANIZER TO STUDENT
                |--------------------------------------------------------------------------
                */

                elseif (
                    $action === 'demote'
                ) {

                    if (
                        $targetUser['role']
                        !==
                        'organizer'
                    ) {

                        $errorMessage =
                            'Only organizer accounts can be changed to student.';

                    } else {

                        try {

                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET role = 'student'
                                WHERE user_id = ?
                                AND role = 'organizer'
                            ");

                            $stmt->execute([
                                $targetUserId
                            ]);

                            $successMessage =
                                'Organizer changed to student successfully.';

                        } catch (PDOException $e) {

                            error_log(
                                'Demote user error: ' .
                                $e->getMessage()
                            );

                            $errorMessage =
                                'Unable to change the organizer role.';
                        }
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search'] ?? ''
    );

$roleFilter =
    $_GET['role'] ?? '';

$statusFilter =
    $_GET['status'] ?? '';

$departmentFilter =
    $_GET['department'] ?? '';



/*
|--------------------------------------------------------------------------
| DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments = [];

try {

    $stmt = $pdo->query("
        SELECT
            dept_id,
            dept_name
        FROM departments
        ORDER BY dept_name ASC
    ");

    $departments =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'Department load error: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| USERS QUERY
|--------------------------------------------------------------------------
*/

$users = [];

try {

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.role,
            u.full_name,
            u.dept_id,
            u.roll_number,
            u.phone,
            u.status,
            d.dept_name
        FROM users u
        LEFT JOIN departments d
            ON d.dept_id = u.dept_id
        WHERE 1 = 1
    ";

    $params = [];


    /*
    | Search
    */

    if ($search !== '') {

        $sql .= "
            AND (
                u.full_name LIKE ?
                OR u.email LIKE ?
                OR u.roll_number LIKE ?
                OR u.phone LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }


    /*
    | Role
    */

    if (
        in_array(
            $roleFilter,
            [
                'student',
                'organizer',
                'admin'
            ],
            true
        )
    ) {

        $sql .= "
            AND u.role = ?
        ";

        $params[] =
            $roleFilter;
    }


    /*
    | Status
    */

    if (
        in_array(
            $statusFilter,
            [
                'active',
                'suspended'
            ],
            true
        )
    ) {

        $sql .= "
            AND u.status = ?
        ";

        $params[] =
            $statusFilter;
    }


    /*
    | Department
    */

    if (
        $departmentFilter !== ''
    ) {

        $sql .= "
            AND u.dept_id = ?
        ";

        $params[] =
            $departmentFilter;
    }


    $sql .= "
        ORDER BY
            u.full_name ASC
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    $users =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'Users load error: ' .
        $e->getMessage()
    );

    $errorMessage =
        'Unable to load users from the database.';
}



/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$stats = [
    'total' => 0,
    'students' => 0,
    'organizers' => 0,
    'active' => 0
];

try {

    $stats['total'] =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM users
                WHERE role <> 'admin'
            ")
            ->fetchColumn();


    $stats['students'] =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM users
                WHERE role = 'student'
            ")
            ->fetchColumn();


    $stats['organizers'] =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM users
                WHERE role = 'organizer'
            ")
            ->fetchColumn();


    $stats['active'] =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM users
                WHERE status = 'active'
                AND role <> 'admin'
            ")
            ->fetchColumn();

} catch (PDOException $e) {

    error_log(
        'User statistics error: ' .
        $e->getMessage()
    );
}



/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function userRoleClass(
    string $role
): string {

    switch (
        strtolower($role)
    ) {

        case 'organizer':
            return 'role-organizer';

        case 'admin':
            return 'role-admin';

        default:
            return 'role-student';
    }
}


function userStatusClass(
    string $status
): string {

    return strtolower($status)
        === 'active'
        ? 'status-active'
        : 'status-suspended';
}


function userInitials(
    string $name
): string {

    $name =
        trim($name);

    if ($name === '') {
        return 'U';
    }

    $parts =
        preg_split(
            '/\s+/',
            $name
        );

    if (count($parts) >= 2) {

        return strtoupper(
            substr($parts[0], 0, 1) .
            substr(
                $parts[count($parts) - 1],
                0,
                1
            )
        );
    }

    return strtoupper(
        substr($name, 0, 1)
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
    User Management | EventSphere
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

/* =========================================================
   THEME
========================================================= */

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

    --blue-bg:#eef4fb;

    --gold-bg:#fff8e9;

    --shadow:
        0 18px 50px
        rgba(7,26,54,.07);
}


/* =========================================================
   RESET
========================================================= */

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

    line-height:1.6;
}


a{
    text-decoration:none;
    color:inherit;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar{

    position:fixed;

    left:0;
    top:0;

    width:255px;
    height:100vh;

    padding:
        24px 16px;

    background:
        var(--navy);

    color:white;

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

    transition:.2s;
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

    font-size:13px;
}


/* =========================================================
   MAIN
========================================================= */

.main{

    min-height:100vh;

    margin-left:255px;
}


/* =========================================================
   TOPBAR
========================================================= */

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

    color:
        var(--ink);

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

    color:
        var(--gold);

    font-size:8px;

    font-weight:700;
}


.logout-link:hover{

    color:
        var(--navy);
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


/* =========================================================
   CONTENT
========================================================= */

.content{

    max-width:1450px;

    margin:auto;

    padding:
        42px 40px 60px;
}


.intro{

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


.intro p{

    max-width:700px;

    margin-top:8px;

    color:
        var(--muted);

    font-size:12px;
}


/* =========================================================
   ALERT
========================================================= */

.alert{

    margin-bottom:20px;

    padding:
        13px 16px;

    border-radius:7px;

    font-size:10px;

    font-weight:600;
}


.alert-success{

    background:
        var(--green-bg);

    border:
        1px solid
        #cbe7d6;

    color:
        var(--green);
}


.alert-error{

    background:
        var(--red-bg);

    border:
        1px solid
        #efd0d0;

    color:
        var(--red);
}


/* =========================================================
   STAT CARDS
========================================================= */

.stat-grid{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:22px;
}


.stat-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:19px;

    background:white;

    border:
        1px solid
        var(--line);

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

    font-size:26px;

    line-height:1;
}


.stat-icon{

    width:38px;
    height:38px;

    display:grid;
    place-items:center;

    border-radius:8px;

    background:
        var(--gold-bg);

    color:
        var(--gold);

    font-size:15px;
}


/* =========================================================
   CARD
========================================================= */

.card{

    overflow:hidden;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:11px;

    box-shadow:
        var(--shadow);
}


.card-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        20px 21px;

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

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;
}


/* =========================================================
   TOOLBAR
========================================================= */

.toolbar{

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        17px 20px;

    background:
        #fafbfd;

    border-bottom:
        1px solid
        var(--line);

    flex-wrap:wrap;
}


.search-form{

    display:flex;

    gap:8px;

    flex:1;

    min-width:260px;
}


.search-input{

    width:100%;

    height:38px;

    padding:
        0 12px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--ink);

    font-family:
        "DM Sans",
        sans-serif;

    font-size:10px;

    outline:none;
}


.search-input:focus{

    border-color:
        var(--gold);

    box-shadow:
        0 0 0 3px
        rgba(201,154,62,.08);
}


.search-btn{

    height:38px;

    padding:
        0 17px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    font-size:9px;

    font-weight:700;

    cursor:pointer;
}


.filter-select{

    height:38px;

    min-width:135px;

    padding:
        0 10px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:white;

    color:
        var(--ink);

    font-family:
        "DM Sans",
        sans-serif;

    font-size:9px;

    outline:none;
}


.filter-select:focus{

    border-color:
        var(--gold);
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper{

    width:100%;

    overflow-x:auto;
}


.users-table{

    width:100%;

    min-width:1200px;

    border-collapse:collapse;
}


.users-table th{

    padding:
        12px 14px;

    background:
        #fafbfd;

    border-bottom:
        1px solid
        var(--line);

    color:
        var(--muted);

    font-size:7px;

    font-weight:700;

    letter-spacing:.8px;

    text-align:left;

    text-transform:uppercase;
}


.users-table td{

    padding:
        14px;

    border-bottom:
        1px solid
        #edf0f3;

    vertical-align:middle;

    font-size:9px;
}


.users-table tbody tr:hover{

    background:
        #fcfdff;
}


/* =========================================================
   USER
========================================================= */

.user-cell{

    display:flex;

    align-items:center;

    gap:10px;

    min-width:200px;
}


.user-mini{

    width:35px;
    height:35px;

    flex:none;

    display:grid;

    place-items:center;

    border-radius:50%;

    background:
        var(--navy);

    color:
        var(--gold-light);

    font-size:10px;

    font-weight:700;
}


.user-name{

    color:
        var(--navy);

    font-size:10px;

    font-weight:700;
}


.user-email{

    margin-top:1px;

    color:
        var(--muted);

    font-size:8px;
}


.department{

    color:
        var(--ink);

    font-size:8px;
}


.roll{

    color:
        var(--muted);

    font-size:8px;
}


.phone{

    white-space:nowrap;

    color:
        var(--muted);

    font-size:8px;
}


/* =========================================================
   ROLE
========================================================= */

.role-badge{

    display:inline-flex;

    align-items:center;

    padding:
        5px 8px;

    border-radius:20px;

    font-size:6px;

    font-weight:800;

    letter-spacing:.6px;

    text-transform:uppercase;
}


.role-student{

    background:
        var(--blue-bg);

    color:
        var(--blue);
}


.role-organizer{

    background:
        var(--gold-bg);

    color:
        #9a711d;
}


.role-admin{

    background:
        #f0edf9;

    color:
        #604aa2;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge{

    display:inline-flex;

    padding:
        5px 8px;

    border-radius:20px;

    font-size:6px;

    font-weight:800;

    letter-spacing:.6px;

    text-transform:uppercase;
}


.status-active{

    background:
        var(--green-bg);

    color:
        var(--green);
}


.status-suspended{

    background:
        var(--red-bg);

    color:
        var(--red);
}


/* =========================================================
   ACTIONS
========================================================= */

.actions{

    display:flex;

    align-items:center;

    gap:5px;

    flex-wrap:wrap;

    min-width:175px;
}


.action-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        6px 8px;

    border-radius:5px;

    border:none;

    font-family:
        "DM Sans",
        sans-serif;

    font-size:7px;

    font-weight:700;

    cursor:pointer;

    transition:.2s;
}


.action-btn:hover{

    transform:
        translateY(-1px);
}


.view-btn{

    background:
        var(--blue-bg);

    color:
        var(--blue);
}


.suspend-btn{

    background:
        var(--red-bg);

    color:
        var(--red);
}


.activate-btn{

    background:
        var(--green-bg);

    color:
        var(--green);
}


.promote-btn{

    background:
        var(--gold-bg);

    color:
        #956d18;
}


.demote-btn{

    background:
        #f1f2f5;

    color:
        #596273;
}


/* =========================================================
   VIEW MODAL
========================================================= */

.modal{

    position:fixed;

    inset:0;

    display:none;

    align-items:center;

    justify-content:center;

    padding:20px;

    background:
        rgba(7,26,54,.55);

    backdrop-filter:
        blur(5px);

    z-index:500;
}


.modal.show{

    display:flex;
}


.modal-box{

    width:
        min(620px,100%);

    max-height:
        90vh;

    overflow-y:auto;

    background:white;

    border-radius:12px;

    box-shadow:
        0 30px 90px
        rgba(0,0,0,.25);
}


.modal-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        20px 22px;

    border-bottom:
        1px solid
        var(--line);
}


.modal-header h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:21px;
}


.modal-close{

    width:32px;
    height:32px;

    border:none;

    border-radius:50%;

    background:
        #f1f3f6;

    color:
        var(--ink);

    cursor:pointer;

    font-size:16px;
}


.modal-body{

    padding:22px;
}


.detail-top{

    display:flex;

    align-items:center;

    gap:14px;

    padding-bottom:18px;

    border-bottom:
        1px solid
        var(--line);
}


.detail-avatar{

    width:54px;
    height:54px;

    display:grid;

    place-items:center;

    border-radius:50%;

    background:
        var(--navy);

    color:
        var(--gold-light);

    font-weight:700;
}


.detail-name{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:21px;
}


.detail-email{

    color:
        var(--muted);

    font-size:9px;
}


.detail-grid{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:12px;

    margin-top:20px;
}


.detail-item{

    padding:13px;

    border:
        1px solid
        var(--line);

    border-radius:7px;

    background:
        #fafbfd;
}


.detail-label{

    margin-bottom:3px;

    color:
        var(--gold);

    font-size:7px;

    font-weight:800;

    letter-spacing:1px;

    text-transform:uppercase;
}


.detail-value{

    color:
        var(--ink);

    font-size:9px;

    font-weight:600;

    word-break:break-word;
}


/* =========================================================
   EMPTY
========================================================= */

.empty{

    padding:
        55px 20px;

    color:
        var(--muted);

    font-size:9px;

    text-align:center;
}


.empty-title{

    margin-bottom:5px;

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:18px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .stat-grid{

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


    .stat-grid{

        grid-template-columns:
            1fr;
    }


    .toolbar{

        align-items:stretch;

        flex-direction:column;
    }


    .search-form{

        min-width:100%;
    }


    .filter-select{

        width:100%;
    }


    .detail-grid{

        grid-template-columns:
            1fr;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

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

        <span class="nav-icon">
            ▦
        </span>

        <span>
            Dashboard
        </span>

    </a>


    <a
        href="users.php"
        class="nav-link active"
    >

        <span class="nav-icon">
            ♙
        </span>

        <span>
            Users
        </span>

    </a>


    <a
        href="contact-messages.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ✉
        </span>

        <span>
            Contact Messages
        </span>

    </a>


    <a
        href="events.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◈
        </span>

        <span>
            Events
        </span>

    </a>


    <a
        href="event-approvals.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ✓
        </span>

        <span>
            Event Approvals
        </span>

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
            Media Gallery
        </span>

    </a>


    <a
        href="venues.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◫
        </span>

        <span>
            Venues
        </span>

    </a>


    <a
        href="departments.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ▤
        </span>

        <span>
            Departments
        </span>

    </a>


    <a
        href="categories.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◆
        </span>

        <span>
            Categories
        </span>

    </a>


    <a
        href="audit-logs.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ◷
        </span>

        <span>
            Audit Logs
        </span>

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



<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<header class="topbar">


    <div class="topbar-left">

        <span class="topbar-label">
            Administration
        </span>

        <div class="page-title">
            User Management
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

            <a
                href="../../logout.php"
                class="logout-link"
            >
                Logout
            </a>

        </div>


        <div class="avatar">

            <?= sanitize($initial) ?>

        </div>

    </div>


</header>



<section class="content">


    <!-- INTRO -->

    <div class="intro">

        <div class="eyebrow">
           EventSphere Administration
        </div>


        <h1>
            Users & Accounts
        </h1>


        <p>
            Manage registered students and event organizers,
            review account information and control account status
            from one central directory.
        </p>

    </div>



    <!-- ALERTS -->

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



    <!-- =================================================
         STATISTICS
    ================================================= -->

    <div class="stat-grid">


        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Total Users
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $stats['total']
                    ) ?>
                </div>

            </div>

            <div class="stat-icon">
                ♙
            </div>

        </div>


        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Students
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $stats['students']
                    ) ?>
                </div>

            </div>

            <div class="stat-icon">
                🎓
            </div>

        </div>


        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Organizers
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $stats['organizers']
                    ) ?>
                </div>

            </div>

            <div class="stat-icon">
                ◉
            </div>

        </div>


        <div class="stat-card">

            <div>

                <div class="stat-label">
                    Active Accounts
                </div>

                <div class="stat-value">
                    <?= number_format(
                        $stats['active']
                    ) ?>
                </div>

            </div>

            <div class="stat-icon">
                ✓
            </div>

        </div>


    </div>



    <!-- =================================================
         USER DIRECTORY
    ================================================= -->

    <div class="card">


        <div class="card-header">

            <div>

                <h2>
                    User Directory
                </h2>

                <p>
                    Registered students and organizers in EventSphere.
                </p>

            </div>

        </div>



        <!-- FILTERS -->

        <div class="toolbar">


            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search name, email, roll number or phone..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <button
                    type="submit"
                    class="search-btn"
                >
                    Search
                </button>

            </form>


            <select
                class="filter-select"
                onchange="changeFilter('role', this.value)"
            >

                <option value="">
                    All Roles
                </option>

                <option
                    value="student"
                    <?= $roleFilter === 'student'
                        ? 'selected'
                        : '' ?>
                >
                    Students
                </option>

                <option
                    value="organizer"
                    <?= $roleFilter === 'organizer'
                        ? 'selected'
                        : '' ?>
                >
                    Organizers
                </option>

            </select>


            <select
                class="filter-select"
                onchange="changeFilter('status', this.value)"
            >

                <option value="">
                    All Status
                </option>

                <option
                    value="active"
                    <?= $statusFilter === 'active'
                        ? 'selected'
                        : '' ?>
                >
                    Active
                </option>

                <option
                    value="suspended"
                    <?= $statusFilter === 'suspended'
                        ? 'selected'
                        : '' ?>
                >
                    Suspended
                </option>

            </select>


            <select
                class="filter-select"
                onchange="changeFilter('department', this.value)"
            >

                <option value="">
                    All Departments
                </option>


                <?php foreach (
                    $departments
                    as $dept
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $dept['dept_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= $departmentFilter === $dept['dept_id']
                            ? 'selected'
                            : '' ?>
                    >

                        <?= sanitize(
                            $dept['dept_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>


            </select>


        </div>



        <!-- TABLE -->

        <?php if (
            !empty($users)
        ): ?>


            <div class="table-wrapper">


                <table class="users-table">


                    <thead>

                        <tr>

                            <th>
                                User
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Department
                            </th>

                            <th>
                                Roll Number
                            </th>

                            <th>
                                Phone
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
                        $users
                        as $account
                    ): ?>


                        <tr>


                            <!-- USER -->

                            <td>

                                <div class="user-cell">


                                    <div class="user-mini">

                                        <?= sanitize(
                                            userInitials(
                                                $account[
                                                    'full_name'
                                                ]
                                            )
                                        ) ?>

                                    </div>


                                    <div>

                                        <div class="user-name">

                                            <?= sanitize(
                                                $account[
                                                    'full_name'
                                                ]
                                            ) ?>

                                        </div>


                                        <div class="user-email">

                                            <?= sanitize(
                                                $account[
                                                    'email'
                                                ]
                                            ) ?>

                                        </div>

                                    </div>


                                </div>

                            </td>


                            <!-- ROLE -->

                            <td>

                                <span
                                    class="
                                        role-badge
                                        <?= userRoleClass(
                                            $account['role']
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $account[
                                                'role'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- DEPARTMENT -->

                            <td>

                                <div class="department">

                                    <?= !empty(
                                        $account[
                                            'dept_name'
                                        ]
                                    )
                                        ? sanitize(
                                            $account[
                                                'dept_name'
                                            ]
                                        )
                                        : '—' ?>

                                </div>

                            </td>


                            <!-- ROLL -->

                            <td>

                                <div class="roll">

                                    <?= !empty(
                                        $account[
                                            'roll_number'
                                        ]
                                    )
                                        ? sanitize(
                                            $account[
                                                'roll_number'
                                            ]
                                        )
                                        : '—' ?>

                                </div>

                            </td>


                            <!-- PHONE -->

                            <td>

                                <div class="phone">

                                    <?= !empty(
                                        $account[
                                            'phone'
                                        ]
                                    )
                                        ? sanitize(
                                            $account[
                                                'phone'
                                            ]
                                        )
                                        : '—' ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        status-badge
                                        <?= userStatusClass(
                                            $account[
                                                'status'
                                            ]
                                        ) ?>
                                    "
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $account[
                                                'status'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <!-- VIEW -->

                                    <button
                                        type="button"
                                        class="action-btn view-btn"
                                        onclick='viewUser(
                                            <?= json_encode(
                                                $account,
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ) ?>
                                        )'
                                    >
                                        View
                                    </button>



                                    <?php if (
                                        $account['role']
                                        !==
                                        'admin'
                                    ): ?>


                                        <!-- STATUS -->

                                        <form
                                            method="POST"
                                            style="display:inline"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $csrfToken,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= htmlspecialchars(
                                                    $account[
                                                        'user_id'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                            >


                                            <?php if (
                                                $account[
                                                    'status'
                                                ]
                                                ===
                                                'active'
                                            ): ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="suspend"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn suspend-btn"
                                                    onclick="return confirm('Suspend this user account?');"
                                                >
                                                    Suspend
                                                </button>

                                            <?php else: ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="activate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn activate-btn"
                                                >
                                                    Activate
                                                </button>

                                            <?php endif; ?>


                                        </form>



                                        <!-- ROLE -->

                                        <form
                                            method="POST"
                                            style="display:inline"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $csrfToken,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= htmlspecialchars(
                                                    $account[
                                                        'user_id'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                            >


                                            <?php if (
                                                $account[
                                                    'role'
                                                ]
                                                ===
                                                'student'
                                            ): ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="promote"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn promote-btn"
                                                    onclick="return confirm('Promote this student to organizer?');"
                                                >
                                                    Make Organizer
                                                </button>

                                            <?php elseif (
                                                $account[
                                                    'role'
                                                ]
                                                ===
                                                'organizer'
                                            ): ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="demote"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn demote-btn"
                                                    onclick="return confirm('Change this organizer back to student?');"
                                                >
                                                    Make Student
                                                </button>

                                            <?php endif; ?>


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

                <div class="empty-title">
                    No Users Found
                </div>

                No users match the selected
                search or filters.

            </div>


        <?php endif; ?>


    </div>


</section>


</main>



<!-- =====================================================
     USER DETAILS MODAL
===================================================== -->

<div
    class="modal"
    id="userModal"
    onclick="closeModal(event)"
>


    <div
        class="modal-box"
        onclick="event.stopPropagation()"
    >


        <div class="modal-header">

            <h2>
                User Details
            </h2>


            <button
                type="button"
                class="modal-close"
                onclick="closeUserModal()"
            >
                ×
            </button>

        </div>


        <div
            class="modal-body"
            id="modalContent"
        >
        </div>


    </div>


</div>



<?php require_once __DIR__ . '/footer.php'; ?>



<script>

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

function changeFilter(
    name,
    value
) {

    const url =
        new URL(
            window.location.href
        );

    if (value) {

        url.searchParams.set(
            name,
            value
        );

    } else {

        url.searchParams.delete(
            name
        );
    }

    url.searchParams.delete(
        'page'
    );

    window.location.href =
        url.toString();
}



/*
|--------------------------------------------------------------------------
| VIEW USER
|--------------------------------------------------------------------------
*/

function viewUser(user) {

    const modal =
        document.getElementById(
            'userModal'
        );

    const content =
        document.getElementById(
            'modalContent'
        );


    const role =
        user.role
            ? user.role.charAt(0).toUpperCase()
              + user.role.slice(1)
            : '—';


    const status =
        user.status
            ? user.status.charAt(0).toUpperCase()
              + user.status.slice(1)
            : '—';


    const initials =
        user.full_name
            ? getInitials(
                user.full_name
            )
            : 'U';


    content.innerHTML = `

        <div class="detail-top">

            <div class="detail-avatar">
                ${escapeHtml(initials)}
            </div>

            <div>

                <div class="detail-name">
                    ${escapeHtml(
                        user.full_name || 'Unknown User'
                    )}
                </div>

                <div class="detail-email">
                    ${escapeHtml(
                        user.email || '—'
                    )}
                </div>

            </div>

        </div>


        <div class="detail-grid">


            <div class="detail-item">

                <div class="detail-label">
                    User ID
                </div>

                <div class="detail-value">
                    ${escapeHtml(
                        user.user_id || '—'
                    )}
                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Role
                </div>

                <div class="detail-value">
                    ${escapeHtml(role)}
                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Department
                </div>

                <div class="detail-value">
                    ${escapeHtml(
                        user.dept_name || user.dept_id || '—'
                    )}
                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Roll Number
                </div>

                <div class="detail-value">
                    ${escapeHtml(
                        user.roll_number || '—'
                    )}
                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Phone
                </div>

                <div class="detail-value">
                    ${escapeHtml(
                        user.phone || '—'
                    )}
                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Account Status
                </div>

                <div class="detail-value">
                    ${escapeHtml(status)}
                </div>

            </div>


        </div>

    `;


    modal.classList.add(
        'show'
    );
}



/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeUserModal() {

    document
        .getElementById(
            'userModal'
        )
        .classList.remove(
            'show'
        );
}


function closeModal(event) {

    if (
        event.target.id
        ===
        'userModal'
    ) {

        closeUserModal();
    }
}



/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

function getInitials(
    name
) {

    const parts =
        name
            .trim()
            .split(/\s+/);


    if (
        parts.length >= 2
    ) {

        return (
            parts[0][0] +
            parts[
                parts.length - 1
            ][0]
        ).toUpperCase();

    }


    return name
        .substring(0,1)
        .toUpperCase();
}



/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(
    value
) {

    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}

</script>


</body>

</html>
