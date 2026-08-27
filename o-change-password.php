<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole('organizer');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

$userName = $user['full_name'] ?? 'Organizer';
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
| DATABASE CONNECTION
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
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['change_password_token'])) {

    $_SESSION['change_password_token'] =
        bin2hex(
            random_bytes(32)
        );

}

$csrfToken =
    $_SESSION['change_password_token'];


/*
|--------------------------------------------------------------------------
| FORM VARIABLES
|--------------------------------------------------------------------------
*/

$errors = [];

$successMessage = '';


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
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
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['change_password_token'],
            $_POST['csrf_token']
        )
    ) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | READ PASSWORDS
    |--------------------------------------------------------------------------
    */

    $currentPassword =
        $_POST['current_password']
        ?? '';

    $newPassword =
        $_POST['new_password']
        ?? '';

    $confirmPassword =
        $_POST['confirm_password']
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $currentPassword === ''
    ) {

        $errors[] =
            'Current password is required.';

    }


    if (
        $newPassword === ''
    ) {

        $errors[] =
            'New password is required.';

    }


    if (
        $confirmPassword === ''
    ) {

        $errors[] =
            'Please confirm your new password.';

    }


    /*
    |--------------------------------------------------------------------------
    | PASSWORD LENGTH
    |--------------------------------------------------------------------------
    */

    if (
        $newPassword !== '' &&
        strlen($newPassword) < 8
    ) {

        $errors[] =
            'New password must be at least 8 characters long.';

    }


    if (
        $newPassword !== '' &&
        strlen($newPassword) > 72
    ) {

        $errors[] =
            'New password cannot exceed 72 characters.';

    }


    /*
    |--------------------------------------------------------------------------
    | CONFIRM PASSWORD
    |--------------------------------------------------------------------------
    */

    if (
        $newPassword !== '' &&
        $confirmPassword !== '' &&
        !hash_equals(
            $newPassword,
            $confirmPassword
        )
    ) {

        $errors[] =
            'New password and confirmation password do not match.';

    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT PASSWORD MUST DIFFER
    |--------------------------------------------------------------------------
    */

    if (
        $currentPassword !== '' &&
        $newPassword !== '' &&
        hash_equals(
            $currentPassword,
            $newPassword
        )
    ) {

        $errors[] =
            'New password must be different from your current password.';

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PASSWORD
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors)
    ) {

        if (
            !$pdoConnection instanceof PDO
        ) {

            $errors[] =
                'Database connection is not available.';

        } else {

            try {


                /*
                |--------------------------------------------------------------------------
                | GET CURRENT HASH
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $pdoConnection->prepare("
                        SELECT
                            password_hash
                        FROM users
                        WHERE user_id = :user_id
                        AND role = 'organizer'
                        LIMIT 1
                    ");

                $stmt->execute([
                    ':user_id' =>
                        $userId
                ]);


                $account =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$account) {

                    $errors[] =
                        'Organizer account could not be found.';

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | VERIFY CURRENT PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !password_verify(
                            $currentPassword,
                            $account['password_hash']
                        )
                    ) {

                        $errors[] =
                            'Current password is incorrect.';

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | SAVE NEW PASSWORD
                |--------------------------------------------------------------------------
                */

                if (
                    empty($errors)
                ) {

                    $newHash =
                        password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        );


                    $update =
                        $pdoConnection->prepare("
                            UPDATE users

                            SET
                                password_hash = :password_hash,
                                updated_at = CURRENT_TIMESTAMP

                            WHERE user_id = :user_id
                            AND role = 'organizer'
                            LIMIT 1
                        ");


                    $update->execute([

                        ':password_hash' =>
                            $newHash,

                        ':user_id' =>
                            $userId

                    ]);


                    if (
                        $update->rowCount() > 0
                    ) {

                        $successMessage =
                            'Password changed successfully.';

                        /*
                        |--------------------------------------------------------------------------
                        | CLEAR FIELDS
                        |--------------------------------------------------------------------------
                        */

                        $currentPassword = '';
                        $newPassword = '';
                        $confirmPassword = '';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Hash could theoretically be same/no affected row.
                        |--------------------------------------------------------------------------
                        */

                        $successMessage =
                            'Password updated successfully.';

                        $currentPassword = '';
                        $newPassword = '';
                        $confirmPassword = '';

                    }

                }

            } catch (PDOException $e) {

                error_log(
                    'Change Password Error: ' .
                    $e->getMessage()
                );

                $errors[] =
                    'Unable to change your password. Please try again.';

            }

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
    Change Password | CEventSphere
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


input,
button{

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

    max-width:850px;

    margin:auto;

    padding:
        45px 40px 60px;

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

    max-width:620px;

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
        23px 26px;

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

    font-size:21px;

}


.form-header p{

    margin-top:4px;

    color:
        var(--muted);

    font-size:9px;

}


.form-body{

    padding:
        27px 26px;

}


.field{

    position:relative;

    display:flex;

    flex-direction:column;

    margin-bottom:20px;

}


.field:last-child{

    margin-bottom:0;

}


.field label{

    margin-bottom:7px;

    color:
        var(--ink);

    font-size:10px;

    font-weight:700;

}


.control{

    width:100%;

    padding:
        12px 42px 12px 13px;

    outline:none;

    border:
        1px solid
        var(--line);

    border-radius:6px;

    background:
        #fbfcfd;

    color:
        var(--ink);

    font-size:11px;

}


.control:focus{

    border-color:
        var(--gold);

    background:white;

    box-shadow:
        0 0 0 3px
        rgba(201,154,62,.1);

}


/* SHOW BUTTON */

.toggle-password{

    position:absolute;

    right:11px;

    bottom:9px;

    border:none;

    background:transparent;

    color:
        var(--muted);

    cursor:pointer;

    font-size:9px;

    font-weight:700;

}


.toggle-password:hover{

    color:
        var(--gold);

}


/* FOOTER */

.form-footer{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        20px 26px;

    background:
        #fbfcfd;

    border-top:
        1px solid
        var(--line);

}


.footer-note{

    max-width:430px;

    color:
        var(--muted);

    font-size:8px;

}


.button-group{

    display:flex;

    gap:8px;

}


.btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        11px 17px;

    border-radius:6px;

    font-size:9px;

    font-weight:700;

    letter-spacing:.7px;

    cursor:pointer;

}


.cancel{

    border:
        1px solid
        var(--line);

    background:white;

    color:
        var(--muted);

}


.save{

    border:none;

    background:
        var(--navy);

    color:white;

}


.save:hover{

    background:
        var(--blue);

}


/* SECURITY */

.security-note{

    margin-top:20px;

    padding:
        19px 21px;

    border-radius:10px;

    background:
        var(--navy);

}


.security-note h3{

    color:
        var(--gold-light);

    font-family:
        "Playfair Display",
        serif;

    font-size:17px;

}


.security-note p{

    margin-top:5px;

    color:
        #c8d2df;

    font-size:9px;

}


.security-list{

    margin-top:10px;

    padding-left:17px;

    color:
        #c8d2df;

    font-size:8px;

}


.security-list li{

    margin-bottom:4px;

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

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .button-group{

        width:100%;

    }


    .btn{

        flex:1;

        text-align:center;

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
        <span></span>
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
            Change Password
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
                Event Organizer
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
            Account Security
        </div>


        <h1>
            Change Password
        </h1>


        <p>
            Update your EventSphere organizer account
            password to keep your account secure.
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
                    $errors
                    as $error
                ): ?>

                    <li>
                        <?= sanitize(
                            $error
                        ) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>



    <div class="form-card">


        <div class="form-header">

            <h2>
                Password Settings
            </h2>

            <p>
                Enter your current password and choose
                a new password for your account.
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


                <!-- CURRENT -->

                <div class="field">

                    <label for="current_password">

                        Current Password

                    </label>


                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="control"
                        autocomplete="current-password"
                        required
                    >


                    <button
                        type="button"
                        class="toggle-password"
                        data-target="current_password"
                    >
                        SHOW
                    </button>

                </div>



                <!-- NEW -->

                <div class="field">

                    <label for="new_password">

                        New Password

                    </label>


                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="control"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >


                    <button
                        type="button"
                        class="toggle-password"
                        data-target="new_password"
                    >
                        SHOW
                    </button>

                </div>



                <!-- CONFIRM -->

                <div class="field">

                    <label for="confirm_password">

                        Confirm New Password

                    </label>


                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="control"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >


                    <button
                        type="button"
                        class="toggle-password"
                        data-target="confirm_password"
                    >
                        SHOW
                    </button>

                </div>


            </div>



            <div class="form-footer">


                <div class="footer-note">

                    Your new password must contain at least
                    8 characters and must be different from
                    your current password.

                </div>


                <div class="button-group">


                    <a
                        href="profile.php"
                        class="btn cancel"
                    >
                        CANCEL
                    </a>


                    <button
                        type="submit"
                        class="btn save"
                    >
                        CHANGE PASSWORD
                    </button>


                </div>


            </div>


        </form>


    </div>



    <div class="security-note">


        <h3>
            Password Security
        </h3>


        <p>
            EventSphere stores passwords using secure
            password hashing rather than plain text.
        </p>


        <ul class="security-list">

            <li>
                Use at least 8 characters.
            </li>

            <li>
                Avoid using easily guessed information.
            </li>

            <li>
                Do not share your EventSphere password.
            </li>

        </ul>


    </div>


</section>


</main>



<script>

const toggleButtons =
    document.querySelectorAll(
        ".toggle-password"
    );


toggleButtons.forEach(
    function(button) {

        button.addEventListener(
            "click",
            function() {

                const targetId =
                    button.dataset.target;

                const input =
                    document.getElementById(
                        targetId
                    );


                if (
                    input.type === "password"
                ) {

                    input.type =
                        "text";

                    button.textContent =
                        "HIDE";

                } else {

                    input.type =
                        "password";

                    button.textContent =
                        "SHOW";

                }

            }
        );

    }
);

</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

</body>

</html>
