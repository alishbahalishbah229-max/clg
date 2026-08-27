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

$userName = $user['full_name'] ?? 'Organizer';

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

} elseif (
    isset($db) &&
    $db instanceof PDO
) {

    $pdoConnection = $db;

}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['organizer_profile_token']
    )
) {

    $_SESSION['organizer_profile_token'] =
        bin2hex(
            random_bytes(32)
        );

}

$csrfToken =
    $_SESSION['organizer_profile_token'];


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$fullName =
    $user['full_name'] ?? '';

$email =
    $user['email'] ?? '';

$deptId =
    $user['dept_id'] ?? '';

$phone =
    $user['phone'] ?? '';

$profileImage =
    $user['profile_image'] ?? '';

$status =
    $user['status'] ?? 'active';

$errors = [];

$successMessage = '';


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty(
            $_POST['csrf_token']
        ) ||
        !hash_equals(
            $_SESSION[
                'organizer_profile_token'
            ],
            $_POST['csrf_token']
        )
    ) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | READ INPUT
    |--------------------------------------------------------------------------
    */

    $fullName =
        trim(
            $_POST['full_name'] ?? ''
        );

    $phone =
        trim(
            $_POST['phone'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {

        $errors[] =
            'Full name is required.';

    }

    elseif (
        mb_strlen($fullName) > 100
    ) {

        $errors[] =
            'Full name cannot exceed 100 characters.';

    }


    if (
        $phone !== '' &&
        mb_strlen($phone) > 20
    ) {

        $errors[] =
            'Phone number cannot exceed 20 characters.';

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $pdoConnection instanceof PDO
    ) {

        try {

            $stmt =
                $pdoConnection->prepare("
                    UPDATE users

                    SET
                        full_name = :full_name,
                        phone = :phone,
                        updated_at = CURRENT_TIMESTAMP

                    WHERE user_id = :user_id
                    AND role = 'organizer'

                    LIMIT 1
                ");

            $stmt->execute([

                ':full_name' =>
                    $fullName,

                ':phone' =>
                    $phone !== ''
                        ? $phone
                        : null,

                ':user_id' =>
                    $userId

            ]);


            $successMessage =
                'Profile updated successfully.';


            /*
            |--------------------------------------------------------------------------
            | REFRESH USER
            |--------------------------------------------------------------------------
            */

            $user['full_name'] =
                $fullName;

            $user['phone'] =
                $phone;


            $userName =
                $fullName;


            $initial =
                strtoupper(
                    substr(
                        trim($fullName),
                        0,
                        1
                    )
                );

        }

        catch (PDOException $e) {

            error_log(
                'Organizer Profile Update Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to update your profile. Please try again.';

        }

    }

    elseif (
        empty($errors)
    ) {

        $errors[] =
            'Database connection is not available.';

    }

}


/*
|--------------------------------------------------------------------------
| PROFILE IMAGE
|--------------------------------------------------------------------------
*/

$profileDisplay = '';

if (
    !empty($profileImage)
) {

    $profileDisplay =
        $profileImage;

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
    Organizer Profile | EventSphere
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

    line-height:1.6;

}


a{

    color:inherit;

    text-decoration:none;

}


input{

    font-family:inherit;

}


/* SIDEBAR */

.sidebar{

    position:fixed;

    top:0;

    left:0;

    width:250px;

    height:100vh;

    padding:24px 16px;

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

    font-size:12px;

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

    margin-left:250px;

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

    max-width:1000px;

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

    max-width:650px;

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


.alert ul{

    padding-left:18px;

}


/* PROFILE GRID */

.profile-grid{

    display:grid;

    grid-template-columns:
        .72fr
        1.28fr;

    gap:20px;

}


/* PROFILE CARD */

.profile-card{

    padding:28px;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);

    text-align:center;

}


.profile-avatar{

    width:100px;

    height:100px;

    display:grid;

    place-items:center;

    margin:
        5px auto 15px;

    border-radius:50%;

    background:
        var(--navy);

    color:
        var(--gold-light);

    font-family:
        "Playfair Display",
        serif;

    font-size:34px;

    font-weight:700;

}


.profile-name{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:21px;

}


.profile-role{

    margin-top:3px;

    color:
        var(--gold);

    font-size:9px;

    font-weight:700;

    letter-spacing:1px;

    text-transform:uppercase;

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

    font-size:8px;

    font-weight:700;

    text-transform:uppercase;

}


.profile-meta{

    margin-top:22px;

    padding-top:17px;

    border-top:
        1px solid
        var(--line);

    text-align:left;

}


.meta-row{

    display:flex;

    justify-content:space-between;

    gap:10px;

    padding:9px 0;

    border-bottom:
        1px solid
        #edf0f3;

}


.meta-row:last-child{

    border-bottom:none;

}


.meta-label{

    color:
        var(--muted);

    font-size:8px;

}


.meta-value{

    color:
        var(--ink);

    font-size:8px;

    font-weight:700;

    text-align:right;

}


/* FORM CARD */

.form-card{

    overflow:hidden;

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:12px;

    box-shadow:
        var(--shadow);

}


.form-header{

    padding:
        22px 25px;

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

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;

}


.form-body{

    padding:25px;

}


.field{

    display:flex;

    flex-direction:column;

    margin-bottom:17px;

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

    outline:none;

    border:
        1px solid
        var(--line);

    border-radius:6px;

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


.control:disabled{

    background:
        #f1f3f6;

    color:
        var(--muted);

    cursor:not-allowed;

}


/* FOOTER */

.form-footer{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        18px 25px;

    background:
        #fbfcfd;

    border-top:
        1px solid
        var(--line);

}


.footer-note{

    color:
        var(--muted);

    font-size:8px;

}


.save-button{

    padding:
        11px 18px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    cursor:pointer;

    font-size:9px;

    font-weight:700;

    letter-spacing:.7px;

}


.save-button:hover{

    background:
        var(--blue);

}


/* SECURITY */

.security-card{

    margin-top:20px;

    padding:
        20px 22px;

    background:
        var(--navy);

    border-radius:10px;

    color:white;

}


.security-card h3{

    color:
        var(--gold-light);

    font-family:
        "Playfair Display",
        serif;

    font-size:17px;

}


.security-card p{

    margin-top:5px;

    color:#c8d2df;

    font-size:9px;

}


.security-link{

    display:inline-flex;

    margin-top:13px;

    padding:
        9px 13px;

    border:
        1px solid
        var(--gold);

    border-radius:5px;

    color:
        var(--gold-light);

    font-size:8px;

    font-weight:700;

}


@media(max-width:950px){

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


    .form-footer{

        align-items:flex-start;

        flex-direction:column;

    }


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
        Organizer Portal
    </div>


    <a
        href="dashboard.php"
        class="nav-link"
    >
        <span class="nav-icon">▦</span>
        <span>Dashboard</span>
    </a>


    <a
        href="create-event.php"
        class="nav-link"
    >
        <span class="nav-icon">+</span>
        <span>Create Event</span>
    </a>


    <a
        href="manage-events.php"
        class="nav-link"
    >
        <span class="nav-icon">◈</span>
        <span>Manage Events</span>
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


    <!-- <a
        href="qr-scanner.php"
        class="nav-link"
    >
        <span class="nav-icon">▣</span>
        <span>QR Scanner</span>
    </a> -->


    <a
        href="media-upload.php"
        class="nav-link"
    >
        <span class="nav-icon">▧</span>
        <span>Media Upload</span>
    </a>


    <a
        href="media-manage.php"
        class="nav-link"
    >
        <span class="nav-icon">◫</span>
        <span>Manage Media</span>
    </a>


    <a
        href="profile.php"
        class="nav-link active"
    >
        <span class="nav-icon">♙</span>
        <span>Profile</span>
    </a>
    <a
        href="logout.php"
        class="nav-link"
    >

        <span class="nav-icon">
            ⎋
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
            Organizer Portal
        </span>

        <div class="page-title">
            Profile
        </div>

    </div>


    <div class="user-area">


        <div class="user-details">

            <strong>
                <?= sanitize($userName) ?>
            </strong>

            <span>
                Event Organizer
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
            Account Settings
        </div>


        <h1>
            Organizer Profile
        </h1>


        <p>
            Review your account information and update
            your contact details.
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
        !empty($errors)
    ): ?>

        <div class="alert alert-error">

            <ul>

                <?php foreach (
                    $errors as $error
                ): ?>

                    <li>
                        <?= sanitize($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>



    <div class="profile-grid">


        <!-- PROFILE SUMMARY -->

        <div class="profile-card">


            <div class="profile-avatar">

                <?= sanitize(
                    $initial
                ) ?>

            </div>


            <div class="profile-name">

                <?= sanitize(
                    $fullName
                ) ?>

            </div>


            <div class="profile-role">
                Event Organizer
            </div>


            <div class="profile-status">

                <?= sanitize(
                    ucfirst(
                        $status
                    )
                ) ?>

            </div>


            <div class="profile-meta">


                <div class="meta-row">

                    <span class="meta-label">
                        Account ID
                    </span>

                    <span
                        class="meta-value"
                        title="<?= sanitize(
                            $userId
                        ) ?>"
                    >

                        <?= sanitize(
                            substr(
                                $userId,
                                0,
                                12
                            )
                        ) ?>

                    </span>

                </div>


                <div class="meta-row">

                    <span class="meta-label">
                        Role
                    </span>

                    <span class="meta-value">
                        Organizer
                    </span>

                </div>


                <div class="meta-row">

                    <span class="meta-label">
                        Department
                    </span>

                    <span class="meta-value">

                        <?= !empty($deptId)
                            ? sanitize($deptId)
                            : 'Not assigned' ?>

                    </span>

                </div>


            </div>


        </div>



        <!-- EDIT PROFILE -->

        <div class="form-card">


            <div class="form-header">

                <h2>
                    Personal Information
                </h2>

                <p>
                    Update the information associated
                    with your organizer account.
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


                <div class="form-body">


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
                            value="<?= sanitize(
                                $fullName
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label for="email">
                            Email Address
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="control"
                            value="<?= sanitize(
                                $email
                            ) ?>"
                            disabled
                        >


                    </div>


                    <div class="field">

                        <label for="department">
                            Department
                        </label>


                        <input
                            type="text"
                            id="department"
                            name="department"
                            class="control"
                            value="<?= sanitize(
                                $deptId
                            ) ?>"
                            disabled
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
                                $phone
                            ) ?>"
                            placeholder="Enter your phone number"
                        >

                    </div>


                </div>


                <div class="form-footer">


                    <div class="footer-note">

                        Email and role are managed by
                        the system administrator.

                    </div>


                    <button
                        type="submit"
                        class="save-button"
                    >
                        SAVE CHANGES
                    </button>


                </div>


            </form>


        </div>


    </div>



    <!-- SECURITY -->

    <div class="security-card">


        <h3>
            Account Security
        </h3>


        <p>
            Keep your EventSphereorganizer account
            secure by using a strong password.
        </p>


        <a
            href="change-password.php"
            class="security-link"
        >
            CHANGE PASSWORD
        </a>


    </div>


</section>


</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>