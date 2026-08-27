
<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';


// ============================================
// VARIABLES
// ============================================

$error = '';


// ============================================
// REGISTRATION PROCESS
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');

    $email = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';

    $confirm_password = $_POST['confirm_password'] ?? '';

    $phone = trim($_POST['phone'] ?? '');

    $roll_number = trim($_POST['roll_number'] ?? '');

    $dept_id = trim($_POST['dept_id'] ?? '');


    // ========================================
    // VALIDATION
    // ========================================

    if (
        empty($full_name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error =
            'Please fill in all required fields.';

    }


    elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    }


    elseif (strlen($password) < 6) {

        $error =
            'Password must be at least 6 characters long.';

    }


    elseif ($password !== $confirm_password) {

        $error =
            'Passwords do not match.';

    }


    else {

        // ====================================
        // CHECK EXISTING EMAIL
        // ====================================

        $stmt = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE email = ?"
        );

        $stmt->execute([$email]);

        $existingUser =
            $stmt->fetch();


        if ($existingUser) {

            $error =
                'An account with this email already exists.';

        }


        else {

            // =================================
            // GENERATE USER ID
            // =================================

            $user_id =
                bin2hex(random_bytes(16));


            // =================================
            // HASH PASSWORD
            // =================================

            $password_hash =
                hashPassword($password);


            // =================================
            // INSERT STUDENT
            // =================================

            $stmt = $pdo->prepare(
                "INSERT INTO users
                (
                    user_id,
                    email,
                    password_hash,
                    role,
                    full_name,
                    dept_id,
                    roll_number,
                    phone,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'student',
                    ?,
                    ?,
                    ?,
                    ?,
                    'active'
                )"
            );


            try {

                $stmt->execute([
                    $user_id,
                    $email,
                    $password_hash,
                    $full_name,
                    $dept_id ?: null,
                    $roll_number ?: null,
                    $phone ?: null
                ]);


                // =================================
                // SUCCESS
                // =================================

                header(
                    'Location: login.php?registered=1'
                );

                exit;


            } catch (PDOException $e) {

                $error =
                    'Something went wrong while creating your account.';

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
        Create Account | EventSphere
    </title>


    <!-- Google Fonts -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >


    <style>

        /* =====================================
           ROOT
        ===================================== */

        :root {

            --navy: #071a36;

            --blue: #123761;

            --gold: #c99a3e;

            --gold-light: #e5c16f;

            --cream: #f6f7f9;

            --white: #ffffff;

            --ink: #172338;

            --muted: #697386;

            --line: #e2e7ed;

            --danger: #b43b3b;

        }


        /* =====================================
           RESET
        ===================================== */

        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }


        body {

            min-height: 100vh;

            font-family:
                "DM Sans",
                sans-serif;

            background:

                radial-gradient(
                    circle at 10% 10%,
                    rgba(
                        201,
                        154,
                        62,
                        .10
                    ),
                    transparent 25%
                ),

                radial-gradient(
                    circle at 90% 90%,
                    rgba(
                        18,
                        55,
                        97,
                        .10
                    ),
                    transparent 28%
                ),

                var(--cream);

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 30px;

        }


        a {

            text-decoration: none;

            color: inherit;

        }


        /* =====================================
           MAIN CARD
        ===================================== */

        .register-wrapper {

            width: 100%;

            max-width: 1080px;

            display: grid;

            grid-template-columns:
                .85fr 1.15fr;

            background: var(--white);

            border-radius: 20px;

            overflow: hidden;

            box-shadow:
                0 30px 90px
                rgba(
                    7,
                    26,
                    54,
                    .15
                );

            animation:
                pageEnter
                .6s
                ease;

        }


        @keyframes pageEnter {

            from {

                opacity: 0;

                transform:
                    translateY(25px);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0);

            }

        }


        /* =====================================
           LEFT PANEL
        ===================================== */

        .register-intro {

            position: relative;

            overflow: hidden;

            padding: 50px;

            color: white;

            background:

                linear-gradient(
                    145deg,
                    #06152c,
                    #123761
                );

            display: flex;

            flex-direction: column;

            justify-content: space-between;

        }


        .register-intro::before {

            content: "";

            position: absolute;

            width: 380px;

            height: 380px;

            border:
                1px solid
                rgba(
                    229,
                    193,
                    111,
                    .18
                );

            border-radius: 50%;

            right: -210px;

            top: -130px;

        }


        .register-intro::after {

            content: "";

            position: absolute;

            width: 280px;

            height: 280px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .08
                );

            border-radius: 50%;

            left: -160px;

            bottom: -130px;

        }


        .intro-content {

            position: relative;

            z-index: 2;

        }


        /* =====================================
           BRAND
        ===================================== */

        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 65px;

        }


        .brand-mark {

            width: 45px;

            height: 51px;

            display: grid;

            place-items: center;

            background: var(--navy);

            border:
                2px solid
                var(--gold);

            color:
                var(--gold-light);

            font-family: Georgia, serif;

            font-size: 21px;

            font-weight: bold;

            clip-path:
                polygon(
                    0 0,
                    100% 0,
                    100% 78%,
                    50% 100%,
                    0 78%
                );

        }


        .brand-name {

            font-family:
                "Playfair Display",
                serif;

            font-size: 20px;

            letter-spacing: 1.5px;

        }


        .brand-tagline {

            display: block;

            margin-top: 2px;

            color:
                var(--gold-light);

            font-size: 7px;

            letter-spacing: 3px;

        }


        /* =====================================
           INTRO TEXT
        ===================================== */

        .intro-kicker {

            color:
                var(--gold-light);

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 3px;

            text-transform: uppercase;

            margin-bottom: 14px;

        }


        .register-intro h1 {

            font-family:
                "Playfair Display",
                serif;

            font-size: 48px;

            line-height: 1.08;

        }


        .register-intro h1 span {

            color:
                var(--gold-light);

        }


        .intro-text {

            margin-top: 20px;

            color: #d1dce8;

            font-size: 12px;

            line-height: 1.9;

            max-width: 370px;

        }


        /* =====================================
           BENEFITS
        ===================================== */

        .benefits {

            position: relative;

            z-index: 2;

            display: grid;

            gap: 10px;

        }


        .benefit {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 12px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .09
                );

            background:
                rgba(
                    255,
                    255,
                    255,
                    .04
                );

            border-radius: 8px;

        }


        .benefit-icon {

            width: 30px;

            height: 30px;

            border-radius: 50%;

            display: grid;

            place-items: center;

            color:
                var(--gold-light);

            border:
                1px solid
                rgba(
                    229,
                    193,
                    111,
                    .4
                );

            font-size: 11px;

        }


        .benefit strong {

            display: block;

            font-size: 10px;

        }


        .benefit small {

            display: block;

            margin-top: 2px;

            color: #aebdcd;

            font-size: 8px;

        }


        /* =====================================
           FORM AREA
        ===================================== */

        .register-form-area {

            padding: 48px 55px;

        }


        .form-heading {

            margin-bottom: 25px;

        }


        .form-heading small {

            color:
                var(--gold);

            font-size: 9px;

            font-weight: 800;

            letter-spacing: 2.5px;

            text-transform: uppercase;

        }


        .form-heading h2 {

            margin-top: 7px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 34px;

        }


        .form-heading p {

            margin-top: 5px;

            color:
                var(--muted);

            font-size: 10px;

        }


        /* =====================================
           ERROR
        ===================================== */

        .alert {

            padding: 11px 13px;

            margin-bottom: 17px;

            border-radius: 7px;

            color:
                var(--danger);

            background:
                #fff4f4;

            border:
                1px solid #f0d0d0;

            font-size: 10px;

            line-height: 1.5;

        }


        /* =====================================
           FORM GRID
        ===================================== */

        .form-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;

        }


        .form-group {

            margin-bottom: 15px;

        }


        .full-width {

            grid-column:
                1 / -1;

        }


        .form-group label {

            display: block;

            margin-bottom: 6px;

            color:
                #29364a;

            font-size: 9px;

            font-weight: 800;

            letter-spacing: 1px;

            text-transform: uppercase;

        }


        .required {

            color:
                var(--gold);

        }


        .form-input {

            width: 100%;

            height: 45px;

            padding:
                0 13px;

            border:
                1px solid
                var(--line);

            border-radius: 6px;

            outline: none;

            background:
                #fbfcfd;

            color:
                var(--ink);

            font-size: 11px;

            transition:
                .25s;

        }


        .form-input::placeholder {

            color:
                #a3acb9;

        }


        .form-input:focus {

            border-color:
                var(--gold);

            background: white;

            box-shadow:
                0 0 0 3px
                rgba(
                    201,
                    154,
                    62,
                    .09
                );

        }


        /* =====================================
           PASSWORD
        ===================================== */

        .password-wrapper {

            position: relative;

        }


        .password-wrapper .form-input {

            padding-right: 60px;

        }


        .show-password {

            position: absolute;

            right: 12px;

            top: 50%;

            transform:
                translateY(-50%);

            border: none;

            background: transparent;

            color:
                #7c8795;

            font-size: 8px;

            font-weight: 700;

            cursor: pointer;

        }


        /* =====================================
           SUBMIT
        ===================================== */

        .register-button {

            width: 100%;

            height: 48px;

            margin-top: 5px;

            border: none;

            border-radius: 7px;

            background:

                linear-gradient(
                    135deg,
                    var(--navy),
                    var(--blue)
                );

            color: white;

            font-size: 9px;

            font-weight: 800;

            letter-spacing: 1.5px;

            cursor: pointer;

            transition:
                .25s;

        }


        .register-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 13px 28px
                rgba(
                    7,
                    26,
                    54,
                    .20
                );

        }


        /* =====================================
           LOGIN LINK
        ===================================== */

        .login-link {

            text-align: center;

            margin-top: 18px;

            color:
                var(--muted);

            font-size: 10px;

        }


        .login-link a {

            color:
                var(--navy);

            font-weight: 800;

            margin-left: 4px;

        }


        .login-link a:hover {

            color:
                var(--gold);

        }


        .back-home {

            display: block;

            text-align: center;

            margin-top: 15px;

            color:
                #8a94a3;

            font-size: 9px;

        }


        .back-home:hover {

            color:
                var(--navy);

        }


        /* =====================================
           RESPONSIVE
        ===================================== */

        @media (max-width: 850px) {

            body {

                padding: 15px;

            }


            .register-wrapper {

                grid-template-columns:
                    1fr;

                max-width: 580px;

            }


            .register-intro {

                min-height: 400px;

                padding: 35px;

            }


            .register-form-area {

                padding: 35px;

            }

        }


        @media (max-width: 520px) {

            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .full-width {

                grid-column:
                    auto;

            }


            .register-intro {

                padding: 28px;

            }


            .register-form-area {

                padding: 28px;

            }


            .register-intro h1 {

                font-size: 39px;

            }

        }

    </style>

</head>


<body>


    <main class="register-wrapper">


        <!-- =====================================
             LEFT INTRO
        ====================================== -->

        <section class="register-intro">


            <div class="intro-content">


                <!-- BRAND -->

                <a
                    href="index.php"
                    class="brand"
                >

                    <div class="brand-mark">
                        E
                    </div>

                    <div>

                        <div class="brand-name">
                            EventSphere
                        </div>

                        <span class="brand-tagline">
                            COLLEGE COMMUNITY
                        </span>

                    </div>

                </a>


                <!-- HEADING -->

                <div class="intro-kicker">

                    Join EventSphere

                </div>


                <h1>

                    Start your
                    <span>
                        campus journey.
                    </span>

                </h1>


                <p class="intro-text">

                    Create your student account
                    and get connected with your
                    college community, events,
                    activities, opportunities
                    and more.

                </p>

            </div>


            <!-- BENEFITS -->

            <div class="benefits">


                <div class="benefit">

                    <div class="benefit-icon">
                        ✓
                    </div>

                    <div>

                        <strong>
                            One Campus Account
                        </strong>

                        <small>
                            Access your student
                            portal from one place.
                        </small>

                    </div>

                </div>


                <div class="benefit">

                    <div class="benefit-icon">
                        ◈
                    </div>

                    <div>

                        <strong>
                            Discover Events
                        </strong>

                        <small>
                            Register for workshops,
                            competitions and events.
                        </small>

                    </div>

                </div>


                <div class="benefit">

                    <div class="benefit-icon">
                        ✦
                    </div>

                    <div>

                        <strong>
                            Stay Connected
                        </strong>

                        <small>
                            Keep up with your campus
                            community.
                        </small>

                    </div>

                </div>


            </div>


        </section>



        <!-- =====================================
             FORM
        ====================================== -->

        <section class="register-form-area">


            <div class="form-heading">

                <small>
                    Student Registration
                </small>

                <h2>
                    Create Account
                </h2>

                <p>
                    Fill in your details to
                    create your EventSphere account.
                </p>

            </div>


            <!-- ERROR -->

            <?php if (!empty($error)): ?>

                <div class="alert">

                    <?= sanitize($error) ?>

                </div>

            <?php endif; ?>


            <!-- FORM -->

            <form
                method="POST"
                action=""
            >


                <div class="form-grid">


                    <!-- FULL NAME -->

                    <div class="form-group full-width">

                        <label for="full_name">

                            Full Name

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input
                            id="full_name"
                            class="form-input"
                            type="text"
                            name="full_name"
                            placeholder="Enter your full name"
                            value="<?= sanitize($_POST['full_name'] ?? '') ?>"
                            required
                        >

                    </div>



                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">

                            Email Address

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input
                            id="email"
                            class="form-input"
                            type="email"
                            name="email"
                            placeholder="student@example.com"
                            value="<?= sanitize($_POST['email'] ?? '') ?>"
                            required
                        >

                    </div>



                    <!-- PHONE -->

                    <div class="form-group">

                        <label for="phone">

                            Phone Number

                        </label>


                        <input
                            id="phone"
                            class="form-input"
                            type="tel"
                            name="phone"
                            placeholder="+92 300 1234567"
                            value="<?= sanitize($_POST['phone'] ?? '') ?>"
                        >

                    </div>



                    <!-- ROLL NUMBER -->

                    <div class="form-group">

                        <label for="roll_number">

                            Roll Number

                        </label>


                        <input
                            id="roll_number"
                            class="form-input"
                            type="text"
                            name="roll_number"
                            placeholder="e.g. CS-2026-001"
                            value="<?= sanitize($_POST['roll_number'] ?? '') ?>"
                        >

                    </div>



                    <!-- DEPARTMENT -->

                    <div class="form-group">

                        <label for="dept_id">

                            Department

                        </label>


                        <input
                            id="dept_id"
                            class="form-input"
                            type="text"
                            name="dept_id"
                            placeholder="e.g. Computer Science"
                            value="<?= sanitize($_POST['dept_id'] ?? '') ?>"
                        >

                    </div>



                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">

                            Password

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="password-wrapper">

                            <input
                                id="password"
                                class="form-input"
                                type="password"
                                name="password"
                                placeholder="Minimum 6 characters"
                                required
                            >


                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword('password', this)"
                            >
                                SHOW
                            </button>

                        </div>

                    </div>



                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">

                            Confirm Password

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="password-wrapper">

                            <input
                                id="confirm_password"
                                class="form-input"
                                type="password"
                                name="confirm_password"
                                placeholder="Repeat your password"
                                required
                            >


                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword('confirm_password', this)"
                            >
                                SHOW
                            </button>

                        </div>

                    </div>


                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="register-button"
                >

                    CREATE STUDENT ACCOUNT
                    &nbsp; →

                </button>


            </form>


            <!-- LOGIN -->

            <div class="login-link">

                Already have an account?

                <a href="login.php">

                    Sign In →

                </a>

            </div>


            <!-- HOME -->

            <a
                href="index.php"
                class="back-home"
            >

                ← Back to EventSphere

            </a>


        </section>


    </main>



    <!-- =====================================
         JAVASCRIPT
    ====================================== -->

    <script>

        function togglePassword(
            fieldId,
            button
        ) {

            const field =
                document.getElementById(
                    fieldId
                );


            if (
                field.type ===
                "password"
            ) {

                field.type =
                    "text";

                button.textContent =
                    "HIDE";

            } else {

                field.type =
                    "password";

                button.textContent =
                    "SHOW";

            }

        }

    </script>


</body>

</html>
```
