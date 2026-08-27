<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('student');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

if (!$user) {
    header('Location: ../../login.php');
    exit;
}

$userId = (string)($user['user_id'] ?? '');

$successMessage = '';
$errorMessage   = '';

$userName = $user['full_name'] ?? '';
$userEmail = $user['email'] ?? '';
$userRoll = $user['roll_number'] ?? '';
$userPhone = $user['phone'] ?? '';
$userDept = $user['dept_id'] ?? '';
$userStatus = $user['status'] ?? 'active';

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

if (
    isset($pdo) &&
    $pdo instanceof PDO
) {
    $pdoConnection = $pdo;

} elseif (
    isset($db) &&
    $db instanceof PDO
) {
    $pdoConnection = $db;
}


/*
|--------------------------------------------------------------------------
| DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments = [];


if ($pdoConnection instanceof PDO) {

    try {

        $stmt =
            $pdoConnection->query("
                SELECT
                    dept_id,
                    dept_name
                FROM departments
                ORDER BY dept_name ASC
            ");

        $departments =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        error_log(
            'Student Profile Departments Error: ' .
            $e->getMessage()
        );

    }
}


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $pdoConnection instanceof PDO
) {

    $submittedName =
        trim(
            $_POST['full_name'] ?? ''
        );

    $submittedPhone =
        trim(
            $_POST['phone'] ?? ''
        );

    $submittedRoll =
        trim(
            $_POST['roll_number'] ?? ''
        );

    $submittedDept =
        trim(
            $_POST['dept_id'] ?? ''
        );


    try {

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($submittedName === '') {

            throw new Exception(
                'Full name is required.'
            );
        }


        if (
            mb_strlen($submittedName) > 100
        ) {

            throw new Exception(
                'Full name cannot exceed 100 characters.'
            );
        }


        if (
            mb_strlen($submittedPhone) > 20
        ) {

            throw new Exception(
                'Phone number cannot exceed 20 characters.'
            );
        }


        if (
            mb_strlen($submittedRoll) > 50
        ) {

            throw new Exception(
                'Roll number cannot exceed 50 characters.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DEPARTMENT VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($submittedDept !== '') {

            $deptCheck =
                $pdoConnection->prepare("
                    SELECT
                        dept_id
                    FROM departments
                    WHERE dept_id = :dept_id
                    LIMIT 1
                ");

            $deptCheck->execute([
                ':dept_id' =>
                    $submittedDept
            ]);


            if (!$deptCheck->fetch()) {

                throw new Exception(
                    'Selected department does not exist.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE USER
        |--------------------------------------------------------------------------
        */

        $stmt =
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

        $stmt->execute([

            ':full_name' =>
                $submittedName,

            ':phone' =>
                $submittedPhone !== ''
                    ? $submittedPhone
                    : null,

            ':roll_number' =>
                $submittedRoll !== ''
                    ? $submittedRoll
                    : null,

            ':dept_id' =>
                $submittedDept !== ''
                    ? $submittedDept
                    : null,

            ':user_id' =>
                $userId

        ]);


        /*
        |--------------------------------------------------------------------------
        | AUDIT LOG
        |--------------------------------------------------------------------------
        */

        try {

            $details =
                json_encode([
                    'updated_fields' => [
                        'full_name',
                        'phone',
                        'roll_number',
                        'dept_id'
                    ]
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
                    'student_profile_updated',

                ':details' =>
                    $details,

                ':ip_address' =>
                    $_SERVER['REMOTE_ADDR']
                    ?? null

            ]);

        } catch (PDOException $auditError) {

            error_log(
                'Student Profile Audit Error: ' .
                $auditError->getMessage()
            );
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE LOCAL VALUES
        |--------------------------------------------------------------------------
        */

        $userName =
            $submittedName;

        $userPhone =
            $submittedPhone;

        $userRoll =
            $submittedRoll;

        $userDept =
            $submittedDept;


        $initial =
            strtoupper(
                substr(
                    trim($userName),
                    0,
                    1
                )
            );


        $successMessage =
            'Your profile has been updated successfully.';


    } catch (
        PDOException $e
    ) {

        error_log(
            'Student Profile Database Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'Unable to update your profile. Please try again.';


    } catch (
        Exception $e
    ) {

        $errorMessage =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| DEPARTMENT NAME
|--------------------------------------------------------------------------
*/

$departmentName = 'Not assigned';


if (
    $userDept !== ''
) {

    foreach (
        $departments as $department
    ) {

        if (
            (string)$department['dept_id']
            ===
            (string)$userDept
        ) {

            $departmentName =
                $department['dept_name'];

            break;
        }
    }
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
    My Profile | Campus360
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

    margin:0 auto;

    padding:
        42px 40px 20px;
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

    margin-top:8px;

    color:
        var(--muted);

    font-size:12px;
}


/* ALERTS */

.alert{

    margin-bottom:18px;

    padding:
        13px 16px;

    border-radius:7px;

    font-size:10px;

    line-height:1.5;
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


.alert-error{

    background:
        var(--red-bg);

    border:
        1px solid
        #efcccc;

    color:
        var(--red);
}


/* PROFILE LAYOUT */

.profile-grid{

    display:grid;

    grid-template-columns:
        .7fr
        1.3fr;

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


/* PROFILE CARD */

.profile-card{

    padding:28px 22px;

    text-align:center;
}


.profile-avatar{

    width:82px;
    height:82px;

    display:grid;

    place-items:center;

    margin:0 auto 15px;

    border-radius:50%;

    background:
        var(--navy);

    color:
        var(--gold-light);

    font-family:
        "Playfair Display",
        serif;

    font-size:28px;

    font-weight:700;
}


.profile-card h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:22px;
}


.profile-card p{

    margin-top:4px;

    color:
        var(--muted);

    font-size:9px;
}


.profile-status{

    display:inline-flex;

    margin-top:14px;

    padding:
        6px 10px;

    border-radius:20px;

    background:
        var(--green-bg);

    color:
        var(--green);

    font-size:7px;

    font-weight:700;

    letter-spacing:.6px;

    text-transform:uppercase;
}


.profile-info{

    margin-top:22px;

    padding-top:17px;

    border-top:
        1px solid
        var(--line);
}


.profile-info-row{

    display:flex;

    justify-content:space-between;

    gap:10px;

    padding:9px 0;

    border-bottom:
        1px solid
        #edf0f3;
}


.profile-info-row:last-child{

    border-bottom:none;
}


.profile-label{

    color:
        var(--muted);

    font-size:8px;
}


.profile-value{

    max-width:150px;

    overflow:hidden;

    color:
        var(--ink);

    font-size:8px;

    font-weight:600;

    text-align:right;

    text-overflow:ellipsis;

    white-space:nowrap;
}


/* FORM CARD */

.form-header{

    padding:
        20px 22px;

    border-bottom:
        1px solid
        var(--line);
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

    margin-top:4px;

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

    height:44px;

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


.control-readonly{

    background:
        #f2f4f7;

    color:
        var(--muted);

    cursor:not-allowed;
}


/* BUTTONS */

.form-actions{

    display:flex;

    justify-content:flex-end;

    gap:8px;

    margin-top:20px;

    padding-top:17px;

    border-top:
        1px solid
        var(--line);
}


.cancel-button{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        10px 15px;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;
}


.save-button{

    padding:
        10px 16px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:#fff;

    cursor:pointer;

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;
}


.save-button:hover{

    background:
        var(--blue);
}


/* SECURITY */

.security-note{

    margin-top:18px;

    padding:
        14px;

    border-radius:8px;

    background:
        var(--gold-bg);

    border:
        1px solid
        #ead7a7;

    color:
        #8f6b18;

    font-size:8px;

    line-height:1.6;
}


/* RESPONSIVE */

@media(max-width:900px){

    .profile-grid{

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


    .form-actions{

        flex-direction:column;
    }


    .cancel-button,
    .save-button{

        width:100%;
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
        class="nav-link"
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
        href="profile.php"
        class="nav-link active"
    >

        <span class="nav-icon">
            ◉
        </span>

        <span>
            My Profile
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
        My Profile
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
    Account Settings
</div>


<h1>
    My Profile
</h1>


<p>
    Manage your personal and student information
    connected to your CEventSphereaccount.
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


<div class="profile-grid">


<!-- PROFILE SUMMARY -->

<div class="card">


<div class="profile-card">


<div class="profile-avatar">

    <?= sanitize(
        $initial
    ) ?>

</div>


<h2>

    <?= sanitize(
        $userName
    ) ?>

</h2>


<p>

    <?= sanitize(
        $userEmail
    ) ?>

</p>


<span class="profile-status">

    <?= sanitize(
        ucfirst(
            $userStatus
        )
    ) ?>

    Account

</span>


<div class="profile-info">


<div class="profile-info-row">

    <span class="profile-label">
        Roll Number
    </span>


    <span class="profile-value">

        <?= $userRoll !== ''
            ? sanitize(
                $userRoll
            )
            : 'Not provided' ?>

    </span>

</div>


<div class="profile-info-row">

    <span class="profile-label">
        Department
    </span>


    <span class="profile-value">

        <?= sanitize(
            $departmentName
        ) ?>

    </span>

</div>


<div class="profile-info-row">

    <span class="profile-label">
        Email
    </span>


    <span class="profile-value">

        <?= sanitize(
            $userEmail
        ) ?>

    </span>

</div>


</div>


</div>


</div>


<!-- UPDATE FORM -->

<div class="card">


<div class="form-header">

    <h2>
        Personal Information
    </h2>


    <p>
        Update the information associated with your student account.
    </p>

</div>


<div class="form-body">


<form
    method="POST"
    action=""
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
        $userName
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
    class="
        control
        control-readonly
    "
    value="<?= sanitize(
        $userEmail
    ) ?>"
    readonly
>


<small>
    Email address cannot be changed from this page.
</small>

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
        $userRoll
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
        $userPhone
    ) ?>"
    placeholder="Enter phone number"
>

</div>


<div class="field">

<label for="dept_id">
    Department
</label>


<select
    id="dept_id"
    name="dept_id"
    class="control"
>

<option value="">
    Select Department
</option>


<?php foreach (
    $departments
    as $department
): ?>

<option
    value="<?= sanitize(
        $department[
            'dept_id'
        ]
    ) ?>"
    <?= (string)$userDept ===
        (string)$department[
            'dept_id'
        ]
        ? 'selected'
        : '' ?>
>

    <?= sanitize(
        $department[
            'dept_name'
        ]
    ) ?>

</option>

<?php endforeach; ?>


</select>


<small>
    Choose your academic department.
</small>

</div>


</div>


<div class="form-actions">


<a
    href="dashboard.php"
    class="cancel-button"
>
    CANCEL
</a>


<button
    type="submit"
    class="save-button"
>
    SAVE CHANGES
</button>


</div>


</form>


<div class="security-note">

    Your email address and password are protected.
    Password changes will be handled separately from
    your general profile information.

</div>


</div>


</div>


</div>


</section>


</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


</body>

</html>