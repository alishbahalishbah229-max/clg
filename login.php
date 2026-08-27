
<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';


// ------------------------------------
// LOGIN PROCESS
// ------------------------------------

$error = '';

$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';

    $remember = isset($_POST['remember']);


    if (empty($email) || empty($password)) {

        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        if (login($email, $password)) {

            $role = $_SESSION['role'] ?? 'student';


            if ($role === 'admin') {

                header(
                    'Location: /a-dashboard.php'
                );

                exit;

            }


            if ($role === 'organizer') {

                header(
                    'Location: /o-dashboard.php'
                );

                exit;

            }


            // Default student

            header(
                'Location: /student-dashboard.php'
            );

            exit;

        } else {

            $error =
                'Invalid email or password.';

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
        Login | EventSphere
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


        /* =========================================
           ROOT
        ========================================= */

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

            --success: #28794d;

        }


        /* =========================================
           RESET
        ========================================= */

        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }


        html {

            scroll-behavior: smooth;

        }


        body {

            min-height: 100vh;

            font-family:
                "DM Sans",
                sans-serif;

            color: var(--ink);

            background:

                radial-gradient(
                    circle at 8% 10%,
                    rgba(
                        201,
                        154,
                        62,
                        0.12
                    ),
                    transparent 27%
                ),

                radial-gradient(
                    circle at 92% 90%,
                    rgba(
                        18,
                        55,
                        97,
                        0.10
                    ),
                    transparent 30%
                ),

                var(--cream);

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 30px;

        }


        a {

            text-decoration: none;

            color: inherit;

        }


        button,
        input {

            font-family: inherit;

        }


        /* =========================================
           PAGE WRAPPER
        ========================================= */

        .login-wrapper {

            width: 100%;

            max-width: 1120px;

            min-height: 650px;

            display: grid;

            grid-template-columns:
                1fr 1fr;

            background: var(--white);

            border-radius: 20px;

            overflow: hidden;

            box-shadow:

                0 30px 90px
                rgba(
                    7,
                    26,
                    54,
                    0.15
                );

            animation:

                pageEnter
                0.65s
                ease;

        }


        @keyframes pageEnter {

            from {

                opacity: 0;

                transform:
                    translateY(25px)
                    scale(.985);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);

            }

        }


        /* =========================================
           LEFT SIDE
        ========================================= */

        .login-visual {

            position: relative;

            overflow: hidden;

            padding: 55px;

            color: var(--white);

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            background:

                linear-gradient(
                    135deg,
                    rgba(
                        4,
                        17,
                        38,
                        .97
                    ),
                    rgba(
                        18,
                        55,
                        97,
                        .94
                    )
                );

        }


        .login-visual::before {

            content: "";

            position: absolute;

            width: 420px;

            height: 420px;

            border: 1px solid
                rgba(
                    229,
                    193,
                    111,
                    .18
                );

            border-radius: 50%;

            right: -210px;

            top: -170px;

        }


        .login-visual::after {

            content: "";

            position: absolute;

            width: 300px;

            height: 300px;

            border: 1px solid
                rgba(
                    255,
                    255,
                    255,
                    .08
                );

            border-radius: 50%;

            left: -160px;

            bottom: -140px;

        }


        .visual-content {

            position: relative;

            z-index: 2;

        }


        /* =========================================
           BRAND
        ========================================= */

        .brand {

            display: inline-flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 70px;

        }


        .brand-mark {

            width: 46px;

            height: 52px;

            display: grid;

            place-items: center;

            background: var(--navy);

            border: 2px solid
                var(--gold);

            color: var(--gold-light);

            font-family:
                Georgia,
                serif;

            font-size: 22px;

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

            font-size: 21px;

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


        /* =========================================
           VISUAL TEXT
        ========================================= */

        .visual-kicker {

            margin-bottom: 13px;

            color:
                var(--gold-light);

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 3px;

            text-transform: uppercase;

        }


        .login-visual h1 {

            max-width: 450px;

            font-family:
                "Playfair Display",
                serif;

            font-size: clamp(
                42px,
                5vw,
                62px
            );

            line-height: 1.04;

            letter-spacing: -1px;

        }


        .login-visual h1 span {

            color:
                var(--gold-light);

        }


        .visual-description {

            max-width: 430px;

            margin-top: 22px;

            color: #d2dce8;

            font-size: 13px;

            line-height: 1.9;

        }


        /* =========================================
           FEATURES
        ========================================= */

        .features {

            position: relative;

            z-index: 2;

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 12px;

        }


        .feature {

            padding: 15px;

            border: 1px solid
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            border-radius: 9px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .045
                );

            backdrop-filter:
                blur(10px);

        }


        .feature-icon {

            color:
                var(--gold-light);

            font-size: 16px;

            margin-bottom: 7px;

        }


        .feature strong {

            display: block;

            font-size: 11px;

            color: white;

        }


        .feature small {

            display: block;

            margin-top: 3px;

            color: #aebdcd;

            font-size: 9px;

            line-height: 1.5;

        }


        /* =========================================
           RIGHT SIDE
        ========================================= */

        .login-form-area {

            padding: 55px;

            display: flex;

            flex-direction: column;

            justify-content: center;

        }


        .form-heading {

            margin-bottom: 28px;

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

            margin-top: 8px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 38px;

            line-height: 1.15;

        }


        .form-heading p {

            margin-top: 7px;

            color:
                var(--muted);

            font-size: 11px;

        }


        /* =========================================
           MESSAGES
        ========================================= */

        .alert {

            padding: 12px 14px;

            margin-bottom: 18px;

            border-radius: 7px;

            font-size: 11px;

            line-height: 1.5;

        }


        .alert-error {

            color:
                var(--danger);

            background:
                #fff4f4;

            border:
                1px solid #f0d0d0;

        }


        .alert-success {

            color:
                var(--success);

            background:
                #f1faf5;

            border:
                1px solid #cce8d8;

        }


        /* =========================================
           FORM
        ========================================= */

        .form-group {

            margin-bottom: 18px;

        }


        .form-label-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 7px;

        }


        .form-label-row label {

            color:
                #29364a;

            font-size: 9px;

            font-weight: 800;

            letter-spacing: 1px;

            text-transform: uppercase;

        }


        .forgot-link {

            color:
                var(--gold);

            font-size: 9px;

            font-weight: 700;

        }


        .forgot-link:hover {

            color:
                var(--navy);

        }


        .input-wrapper {

            position: relative;

        }


        .input-icon {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color:
                #8a94a3;

            font-size: 13px;

            pointer-events: none;

        }


        .form-input {

            width: 100%;

            height: 48px;

            padding:
                0 42px;

            border:
                1px solid var(--line);

            border-radius: 7px;

            outline: none;

            background:
                #fbfcfd;

            color:
                var(--ink);

            font-size: 12px;

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

            background:
                white;

            box-shadow:
                0 0 0 3px
                rgba(
                    201,
                    154,
                    62,
                    .10
                );

        }


        /* =========================================
           PASSWORD TOGGLE
        ========================================= */

        .password-toggle {

            position: absolute;

            right: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            border: none;

            background: transparent;

            color:
                #8a94a3;

            cursor: pointer;

            font-size: 11px;

        }


        .password-toggle:hover {

            color:
                var(--navy);

        }


        /* =========================================
           OPTIONS
        ========================================= */

        .form-options {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-top: 4px;

            margin-bottom: 23px;

        }


        .remember {

            display: flex;

            align-items: center;

            gap: 7px;

            color:
                var(--muted);

            font-size: 10px;

            cursor: pointer;

        }


        .remember input {

            accent-color:
                var(--navy);

            cursor: pointer;

        }


        /* =========================================
           LOGIN BUTTON
        ========================================= */

        .login-button {

            width: 100%;

            height: 49px;

            border: none;

            border-radius: 7px;

            background:

                linear-gradient(
                    135deg,
                    var(--navy),
                    var(--blue)
                );

            color: white;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 1.5px;

            cursor: pointer;

            transition:
                .25s;

        }


        .login-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 14px 30px
                rgba(
                    7,
                    26,
                    54,
                    .20
                );

        }


        .login-button:active {

            transform:
                translateY(0);

        }


        /* =========================================
           REGISTER
        ========================================= */

        .register-box {

            margin-top: 25px;

            padding-top: 23px;

            border-top:
                1px solid var(--line);

            text-align: center;

        }


        .register-box p {

            color:
                var(--muted);

            font-size: 10px;

        }


        .register-box a {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            margin-left: 4px;

            color:
                var(--navy);

            font-weight: 800;

            transition:
                .2s;

        }


        .register-box a:hover {

            color:
                var(--gold);

        }


        /* =========================================
           BACK HOME
        ========================================= */

        .back-home {

            display: block;

            margin-top: 20px;

            text-align: center;

            color:
                #8a94a3;

            font-size: 10px;

            transition:
                .2s;

        }


        .back-home:hover {

            color:
                var(--navy);

        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 850px) {

            body {

                padding: 15px;

            }


            .login-wrapper {

                grid-template-columns:
                    1fr;

                max-width: 560px;

            }


            .login-visual {

                min-height: 430px;

                padding: 40px;

            }


            .brand {

                margin-bottom: 45px;

            }


            .login-form-area {

                padding: 40px;

            }

        }


        @media (max-width: 500px) {

            .login-visual {

                padding: 30px;

            }


            .login-form-area {

                padding: 30px;

            }


            .login-visual h1 {

                font-size: 40px;

            }


            .features {

                grid-template-columns:
                    1fr;

            }


            .form-heading h2 {

                font-size: 32px;

            }

        }


    </style>

</head>


<body>


    <main class="login-wrapper">


        <!-- =====================================
             LEFT VISUAL SECTION
        ====================================== -->

        <section class="login-visual">


            <div class="visual-content">


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

                <div class="visual-kicker">

                    Welcome Back

                </div>


                <h1>

                    Your campus.
                    <br>

                    Your <span>community.</span>

                </h1>


                <p class="visual-description">

                    Sign in to stay connected
                    with your classes, events,
                    opportunities, societies
                    and everything happening
                    across EventSphere.

                </p>

            </div>


            <!-- FEATURES -->

            <div class="features">


                <div class="feature">

                    <div class="feature-icon">
                        ◈
                    </div>

                    <strong>
                        Campus Events
                    </strong>

                    <small>
                        Discover upcoming
                        activities and events.
                    </small>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        ✦
                    </div>

                    <strong>
                        Student Community
                    </strong>

                    <small>
                        Connect with your
                        campus community.
                    </small>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        ◇
                    </div>

                    <strong>
                        Digital Tickets
                    </strong>

                    <small>
                        Access your event
                        registrations.
                    </small>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        ✓
                    </div>

                    <strong>
                        Opportunities
                    </strong>

                    <small>
                        Find workshops,
                        clubs and more.
                    </small>

                </div>


            </div>


        </section>



        <!-- =====================================
             RIGHT LOGIN SECTION
        ====================================== -->

        <section class="login-form-area">


            <div class="form-heading">

                <small>
                    EventSphere Portal
                </small>

                <h2>
                    Sign in
                </h2>

                <p>
                    Enter your account details
                    to continue.
                </p>

            </div>


            <!-- REGISTRATION SUCCESS -->

            <?php if ($registered): ?>

                <div class="alert alert-success">

                    Your student account has been
                    created successfully.
                    Please sign in to continue.

                </div>

            <?php endif; ?>


            <!-- LOGIN ERROR -->

            <?php if (!empty($error)): ?>

                <div class="alert alert-error">

                    <?= sanitize($error) ?>

                </div>

            <?php endif; ?>


            <!-- LOGIN FORM -->

            <form
                method="POST"
                action=""
            >


                <!-- EMAIL -->

                <div class="form-group">


                    <div class="form-label-row">

                        <label for="email">

                            Email Address

                        </label>

                    </div>


                    <div class="input-wrapper">

                        <span class="input-icon">
                            @
                        </span>


                        <input
                            id="email"
                            class="form-input"
                            type="email"
                            name="email"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">


                    <div class="form-label-row">

                        <label for="password">

                            Password

                        </label>


                        <a
                            href="#"
                            class="forgot-link"
                            onclick="return false;"
                        >

                            Forgot password?

                        </a>

                    </div>


                    <div class="input-wrapper">

                        <span class="input-icon">
                            ●
                        </span>


                        <input
                            id="password"
                            class="form-input"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                            aria-label="Show password"
                        >
                            SHOW
                        </button>

                    </div>

                </div>



                <!-- OPTIONS -->

                <div class="form-options">


                    <label class="remember">

                        <input
                            type="checkbox"
                            name="remember"
                        >

                        Remember me

                    </label>


                </div>



                <!-- LOGIN -->

                <button
                    type="submit"
                    class="login-button"
                >

                    SIGN IN TO EventSphere
                    &nbsp; →

                </button>


            </form>



            <!-- REGISTER -->

            <div class="register-box">

                <p>

                    Don't have a student account?

                    <a href="register.php">

                        Create Account
                        →

                    </a>

                </p>

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


        function togglePassword() {


            const password =
                document.getElementById(
                    "password"
                );


            const button =
                document.querySelector(
                    ".password-toggle"
                );


            if (
                password.type ===
                "password"
            ) {

                password.type =
                    "text";

                button.textContent =
                    "HIDE";

            } else {

                password.type =
                    "password";

                button.textContent =
                    "SHOW";

            }

        }


    </script>


</body>

</html>
```
