<?php

// ==================================================
// CAMPUS360 - CONTACT US
// ==================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

$isLoggedIn = isAuthenticated();

$currentUser = null;

if ($isLoggedIn) {
    $currentUser = getCurrentUser();
}


/*
|--------------------------------------------------------------------------
| DASHBOARD URL
|--------------------------------------------------------------------------
*/

$dashboardUrl = '#';

if ($currentUser) {

    switch (
        $currentUser['role']
        ?? 'student'
    ) {

        case 'admin':

            $dashboardUrl =
                'modules/admin/dashboard.php';

            break;


        case 'organizer':

            $dashboardUrl =
                'modules/organizer/dashboard.php';

            break;


        case 'student':

            $dashboardUrl =
                'modules/student/dashboard.php';

            break;
    }
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
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
| FORM VARIABLES
|--------------------------------------------------------------------------
*/

$name = '';
$email = '';
$subject = '';
$message = '';

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $name =
        trim(
            $_POST['name'] ?? ''
        );

    $email =
        trim(
            $_POST['email'] ?? ''
        );

    $subject =
        trim(
            $_POST['subject'] ?? ''
        );

    $message =
        trim(
            $_POST['message'] ?? ''
        );


    try {

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $name === ''
        ) {

            throw new Exception(
                'Please enter your name.'
            );
        }


        if (
            mb_strlen($name) > 100
        ) {

            throw new Exception(
                'Name cannot exceed 100 characters.'
            );
        }


        if (
            $email === ''
        ) {

            throw new Exception(
                'Please enter your email address.'
            );
        }


        if (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            throw new Exception(
                'Please enter a valid email address.'
            );
        }


        if (
            mb_strlen($email) > 255
        ) {

            throw new Exception(
                'Email address is too long.'
            );
        }


        if (
            $subject === ''
        ) {

            throw new Exception(
                'Please enter a subject.'
            );
        }


        if (
            mb_strlen($subject) > 200
        ) {

            throw new Exception(
                'Subject cannot exceed 200 characters.'
            );
        }


        if (
            $message === ''
        ) {

            throw new Exception(
                'Please enter your message.'
            );
        }


        if (
            mb_strlen($message) > 5000
        ) {

            throw new Exception(
                'Message cannot exceed 5000 characters.'
            );
        }


        if (
            !$pdoConnection instanceof PDO
        ) {

            throw new Exception(
                'Database connection is not available.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | INSERT MESSAGE
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdoConnection->prepare("
                INSERT INTO contact_messages
                (
                    message_id,
                    name,
                    email,
                    subject,
                    message,
                    status
                )
                VALUES
                (
                    UUID(),
                    :name,
                    :email,
                    :subject,
                    :message,
                    'new'
                )
            ");


        $stmt->execute([

            ':name' =>
                $name,

            ':email' =>
                $email,

            ':subject' =>
                $subject,

            ':message' =>
                $message

        ]);


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        $successMessage =
            'Your message has been sent successfully. Our administration team will review it shortly.';


        /*
        |--------------------------------------------------------------------------
        | CLEAR FORM
        |--------------------------------------------------------------------------
        */

        $name = '';
        $email = '';
        $subject = '';
        $message = '';


    } catch (
        PDOException $e
    ) {

        error_log(
            'Contact Form Database Error: ' .
            $e->getMessage()
        );

        $errorMessage =
            'We could not send your message right now. Please try again.';


    } catch (
        Exception $e
    ) {

        $errorMessage =
            $e->getMessage();
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
    Contact Us | Campus360
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


/* ==================================================
   ROOT
================================================== */

:root{

    --navy:#071a36;

    --navy-light:#102c52;

    --blue:#123761;

    --gold:#c99a3e;

    --gold-light:#e5c16f;

    --cream:#f6f7f9;

    --white:#ffffff;

    --ink:#172338;

    --muted:#697386;

    --line:#e2e7ed;

    --green:#28794d;

    --green-bg:#f1faf5;

    --red:#b43b3b;

    --red-bg:#fff4f4;

    --shadow:
        0 22px 65px
        rgba(7,26,54,.10);

}


/* ==================================================
   RESET
================================================== */

*{

    box-sizing:border-box;

    margin:0;

    padding:0;

}


html{

    scroll-behavior:smooth;

}


body{

    min-height:100vh;

    font-family:
        "DM Sans",
        sans-serif;

    color:
        var(--ink);

    background:
        radial-gradient(
            circle at 8% 8%,
            rgba(
                201,
                154,
                62,
                .10
            ),
            transparent 25%
        ),

        radial-gradient(
            circle at 93% 90%,
            rgba(
                18,
                55,
                97,
                .08
            ),
            transparent 30%
        ),

        var(--cream);

}


a{

    color:inherit;

    text-decoration:none;

}


button,
input,
textarea{

    font-family:inherit;

}


/* ==================================================
   TOP BAR
================================================== */

.topbar{

    min-height:42px;

    background:
        var(--navy);

    color:
        #c7d1de;

    font-size:8px;

}


.topbar .container{

    min-height:42px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:25px;

}


.utility{

    display:flex;

    align-items:center;

    gap:18px;

}


.utility span{

    white-space:nowrap;

}


.portal{

    display:flex;

    align-items:center;

    gap:17px;

}


.portal span{

    color:
        var(--gold-light);

}


.portal a{

    color:
        #d7e0ea;

    transition:.2s;

}


.portal a:hover{

    color:white;

}


/* ==================================================
   CONTAINER
================================================== */

.container{

    width:100%;

    max-width:1240px;

    margin:0 auto;

    padding-left:25px;

    padding-right:25px;

}


/* ==================================================
   MAIN NAV
================================================== */

.mainnav{

    background:
        var(--white);

    border-bottom:
        1px solid
        var(--line);

}


.mainnav > .container{

    min-height:80px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

}


/* BRAND */

.brand{

    display:flex;

    align-items:center;

    gap:11px;

    flex-shrink:0;

}


.crest{

    width:42px;

    height:48px;

    display:grid;

    place-items:center;

    background:
        var(--navy);

    border:
        2px solid
        var(--gold);

    color:
        var(--gold-light);

    font-family:
        Georgia,
        serif;

    font-size:19px;

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


.brand strong{

    display:block;

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:18px;

    letter-spacing:1.1px;

}


.brand small{

    display:block;

    margin-top:2px;

    color:
        var(--gold);

    font-size:6px;

    letter-spacing:2px;

}


/* NAV LINKS */

.navlinks{

    display:flex;

    align-items:center;

    justify-content:center;

    gap:19px;

}


.navlinks a{

    position:relative;

    color:
        var(--muted);

    font-size:8px;

    font-weight:700;

    letter-spacing:.7px;

    transition:.2s;

}


.navlinks a:hover{

    color:
        var(--navy);

}


.navlinks a.active{

    color:
        var(--navy);

}


.navlinks a.active::after{

    content:"";

    position:absolute;

    left:0;

    right:0;

    bottom:-10px;

    height:2px;

    background:
        var(--gold);

}


/* APPLY BUTTON */

.apply{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:
        11px 14px;

    border-radius:5px;

    background:
        var(--navy);

    color:white;

    font-size:7px;

    font-weight:800;

    letter-spacing:.8px;

    white-space:nowrap;

    transition:.2s;

}


.apply:hover{

    background:
        var(--blue);

}


/* ==================================================
   PAGE
================================================== */

.page{

    max-width:1180px;

    margin:0 auto;

    padding:
        58px 25px 70px;

}


/* PAGE INTRO */

.page-intro{

    max-width:720px;

    margin-bottom:30px;

}


.eyebrow{

    margin-bottom:9px;

    color:
        var(--gold);

    font-size:9px;

    font-weight:800;

    letter-spacing:2.5px;

    text-transform:uppercase;

}


.page-intro h1{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:52px;

    line-height:1.05;

    letter-spacing:-.7px;

}


.page-intro p{

    margin-top:13px;

    color:
        var(--muted);

    font-size:12px;

    line-height:1.75;

}


/* ==================================================
   CONTACT GRID
================================================== */

.contact-grid{

    display:grid;

    grid-template-columns:
        .78fr
        1.22fr;

    gap:22px;

}


/* ==================================================
   CONTACT INFORMATION
================================================== */

.contact-info{

    position:relative;

    overflow:hidden;

    padding:30px;

    border-radius:15px;

    background:
        linear-gradient(
            145deg,
            #071a36,
            #123761
        );

    color:white;

    box-shadow:
        var(--shadow);

}


.contact-info::before{

    content:"";

    position:absolute;

    width:310px;

    height:310px;

    border:
        1px solid
        rgba(
            229,
            193,
            111,
            .13
        );

    border-radius:50%;

    right:-170px;

    top:-145px;

}


.contact-info::after{

    content:"";

    position:absolute;

    width:230px;

    height:230px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .07
        );

    border-radius:50%;

    left:-135px;

    bottom:-145px;

}


.contact-info-inner{

    position:relative;

    z-index:2;

}


.contact-info h2{

    color:
        var(--gold-light);

    font-family:
        "Playfair Display",
        serif;

    font-size:28px;

    line-height:1.2;

}


.contact-info > p{

    max-width:360px;

    margin-top:9px;

    color:#c8d3df;

    font-size:10px;

    line-height:1.75;

}


/* INFO ITEMS */

.contact-list{

    margin-top:25px;

}


.contact-item{

    display:flex;

    align-items:flex-start;

    gap:12px;

    padding:
        15px 0;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

}


.contact-item:last-child{

    border-bottom:none;

}


.contact-icon{

    width:36px;

    height:36px;

    display:grid;

    place-items:center;

    flex-shrink:0;

    border:
        1px solid
        rgba(
            229,
            193,
            111,
            .42
        );

    border-radius:8px;

    color:
        var(--gold-light);

    font-size:14px;

}


.contact-item strong{

    display:block;

    color:white;

    font-size:9px;

}


.contact-item span{

    display:block;

    margin-top:3px;

    color:#aebdcd;

    font-size:8px;

    line-height:1.5;

}


.contact-item a{

    color:#aebdcd;

    transition:.2s;

}


.contact-item a:hover{

    color:white;

}


/* HOURS */

.office-hours{

    margin-top:20px;

    padding-top:18px;

    border-top:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

}


.office-hours strong{

    display:block;

    color:
        var(--gold-light);

    font-size:8px;

    font-weight:800;

    letter-spacing:1px;

    text-transform:uppercase;

}


.office-hours span{

    display:block;

    margin-top:6px;

    color:#aebdcd;

    font-size:8px;

}


/* ==================================================
   FORM CARD
================================================== */

.contact-form-card{

    padding:30px;

    background:
        var(--white);

    border:
        1px solid
        var(--line);

    border-radius:15px;

    box-shadow:
        var(--shadow);

}


.contact-form-card h2{

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:28px;

}


.contact-form-card > p{

    margin-top:5px;

    color:
        var(--muted);

    font-size:9px;

}


.alert{

    margin-top:18px;

    padding:
        13px 15px;

    border-radius:7px;

    font-size:9px;

    line-height:1.55;

}


.alert-success{

    background:
        var(--green-bg);

    border:
        1px solid
        #cce8d8;

    color:
        var(--green);

}


.alert-error{

    background:
        var(--red-bg);

    border:
        1px solid
        #f0d0d0;

    color:
        var(--red);

}


/* FORM GRID */

.form-grid{

    display:grid;

    grid-template-columns:
        1fr
        1fr;

    gap:16px;

    margin-top:23px;

}


.form-group{

    display:flex;

    flex-direction:column;

}


.form-group.full{

    grid-column:
        1 / -1;

}


.form-group label{

    margin-bottom:7px;

    color:
        #29364a;

    font-size:8px;

    font-weight:800;

    letter-spacing:1px;

    text-transform:uppercase;

}


.form-control{

    width:100%;

    min-height:46px;

    padding:
        0 12px;

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

    transition:.2s;

}


textarea.form-control{

    min-height:155px;

    padding:
        12px;

    resize:vertical;

    line-height:1.6;

}


.form-control::placeholder{

    color:#9ba4b2;

}


.form-control:focus{

    border-color:
        var(--gold);

    background:white;

    box-shadow:
        0 0 0 3px
        rgba(
            201,
            154,
            62,
            .10
        );

}


/* SUBMIT */

.submit-button{

    width:100%;

    margin-top:19px;

    padding:14px;

    border:none;

    border-radius:6px;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--blue)
        );

    color:white;

    cursor:pointer;

    font-size:9px;

    font-weight:800;

    letter-spacing:1px;

    transition:.2s;

}


.submit-button:hover{

    transform:
        translateY(-1px);

    box-shadow:
        0 12px 25px
        rgba(
            7,
            26,
            54,
            .16
        );

}


/* FORM FOOT NOTE */

.form-note{

    margin-top:12px;

    color:
        #8a94a3;

    font-size:7px;

    line-height:1.6;

}


/* ==================================================
   FOOTER
================================================== */

footer{

    background:
        var(--navy);

    color:white;

}


.foot{

    padding:
        65px 25px 25px;

}


.footgrid{

    display:grid;

    grid-template-columns:
        1.5fr
        1fr
        1fr
        1fr;

    gap:40px;

}


.footbrand .brand{

    display:inline-flex;

    margin-bottom:18px;

}


.footbrand .brand strong{

    color:white;

}


.footbrand .brand small{

    color:
        var(--gold-light);

}


.footbrand p{

    max-width:320px;

    color:#aebbd0;

    font-size:10px;

    line-height:1.8;

}


.footgrid h4{

    margin-bottom:15px;

    color:
        var(--gold-light);

    font-family:
        "Playfair Display",
        serif;

    font-size:13px;

}


.footgrid > div > a{

    display:block;

    margin-bottom:10px;

    color:#aebbd0;

    font-size:9px;

    transition:.2s;

}


.footgrid > div > a:hover{

    color:white;

}


.copyright{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin-top:45px;

    padding-top:18px;

    border-top:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

    color:#8492a5;

    font-size:7px;

}


.copyright span:last-child{

    text-align:right;

}


/* ==================================================
   RESPONSIVE
================================================== */

@media(max-width:1050px){

    .navlinks{

        gap:12px;

    }


    .navlinks a{

        font-size:7px;

    }


    .mainnav > .container{

        gap:12px;

    }


    .contact-grid{

        grid-template-columns:
            1fr;

    }


    .contact-info{

        min-height:auto;

    }

}


@media(max-width:850px){

    .topbar .container{

        justify-content:center;

    }


    .utility{

        display:none;

    }


    .portal{

        gap:15px;

    }


    .navlinks{

        display:none;

    }


    .mainnav > .container{

        min-height:72px;

    }


    .page{

        padding:
            45px 20px 55px;

    }


    .page-intro h1{

        font-size:43px;

    }


    .foot{

        padding:
            50px 25px 25px;

    }


    .footgrid{

        grid-template-columns:
            1fr 1fr;

        gap:30px;

    }

}


@media(max-width:600px){

    .topbar{

        min-height:38px;

    }


    .portal span{

        display:none;

    }


    .portal{

        justify-content:center;

    }


    .brand strong{

        font-size:15px;

    }


    .brand small{

        font-size:5px;

    }


    .apply{

        padding:
            9px 10px;

        font-size:6px;

    }


    .page{

        padding:
            35px 15px 45px;

    }


    .page-intro h1{

        font-size:36px;

    }


    .page-intro p{

        font-size:10px;

    }


    .contact-info,
    .contact-form-card{

        padding:22px;

    }


    .form-grid{

        grid-template-columns:
            1fr;

    }


    .form-group.full{

        grid-column:auto;

    }


    .foot{

        padding:
            42px 20px 20px;

    }


    .footgrid{

        grid-template-columns:
            1fr;

        gap:28px;

    }


    .copyright{

        flex-direction:column;

        align-items:flex-start;

    }


    .copyright span:last-child{

        text-align:left;

    }

}

</style>

</head>


<body>


<!-- ==================================================
     TOP BAR
================================================== -->

<div class="topbar">

    <div class="container">


        <div class="utility">

            <span>
                ☎ +92 300 1234567
            </span>

            <span>
                ✉ info@campus360.edu.pk
            </span>

            <span>
                ⌖ Main Campus, Lahore
            </span>

        </div>


        <div class="portal">


            <?php if (
                $isLoggedIn
            ): ?>


                <span>

                    Hi,
                    <?= sanitize(
                        $currentUser[
                            'full_name'
                        ] ?? 'User'
                    ) ?>

                </span>


                <a
                    href="<?= sanitize(
                        $dashboardUrl
                    ) ?>"
                >
                    Dashboard
                </a>


                <a href="logout.php">
                    Logout
                </a>


            <?php else: ?>


                <a href="login.php">
                    Login
                </a>


                <a href="register.php">
                    Register
                </a>


            <?php endif; ?>


        </div>


    </div>

</div>



<!-- ==================================================
     MAIN NAVIGATION
================================================== -->

<nav class="mainnav">

    <div class="container">

        <a
            class="brand"
            href="index.php"
        >

            <div class="crest">
                C
            </div>

            <div>

                <strong>
                    CAMPUS360
                </strong>

                <small>
                    COLLEGE COMMUNITY
                </small>

            </div>

        </a>


        <div class="navlinks">

            <a href="index.php">
                HOME
            </a>

            <a href="#about">
                ABOUT
            </a>

            <a href="#academics">
                ACADEMICS
            </a>

            <a href="#campus">
                CAMPUS LIFE
            </a>

            <a href="#events">
                EVENTS
            </a>

            <a href="#news">
                NEWS & BLOG
            </a>

            <a href="contact.php">
                CONTACT
            </a>

        </div>


        <?php if ($isLoggedIn): ?>

            <a
                class="apply"
                href="<?= $dashboardUrl; ?>"
            >
                MY DASHBOARD →
            </a>

        <?php else: ?>

            <a
                class="apply"
                href="modules/auth/register.php"
            >
                APPLY NOW →
            </a>

        <?php endif; ?>


    </div>

</nav>



<!-- ==================================================
     MAIN CONTACT CONTENT
================================================== -->

<main class="page">


    <!-- PAGE INTRO -->

    <div class="page-intro">


        <div class="eyebrow">
            Get In Touch
        </div>


        <h1>
            Contact Campus360.
        </h1>


        <p>
            Have a question, suggestion or request?
            Send a message to the Campus360 team and
            we'll make sure it reaches the right people.
        </p>


    </div>



    <!-- CONTACT GRID -->

    <div class="contact-grid">


        <!-- ==================================================
             INFORMATION
        ================================================== -->

        <section class="contact-info">


            <div class="contact-info-inner">


                <h2>
                    Let's talk.
                </h2>


                <p>
                    We're here to help students,
                    organizers and members of the
                    college community with Campus360.
                </p>


                <div class="contact-list">


                    <!-- PHONE -->

                    <div class="contact-item">


                        <div class="contact-icon">
                            ☎
                        </div>


                        <div>

                            <strong>
                                Phone
                            </strong>


                            <span>

                                <a
                                    href="tel:+923001234567"
                                >
                                    +92 300 1234567
                                </a>

                            </span>

                        </div>


                    </div>


                    <!-- EMAIL -->

                    <div class="contact-item">


                        <div class="contact-icon">
                            ✉
                        </div>


                        <div>

                            <strong>
                                Email
                            </strong>


                            <span>

                                <a
                                    href="mailto:info@campus360.edu.pk"
                                >
                                    info@campus360.edu.pk
                                </a>

                            </span>

                        </div>


                    </div>


                    <!-- CAMPUS -->

                    <div class="contact-item">


                        <div class="contact-icon">
                            ⌖
                        </div>


                        <div>

                            <strong>
                                Campus
                            </strong>


                            <span>
                                Main Campus, Lahore
                            </span>

                        </div>


                    </div>


                    <!-- SUPPORT -->

                    <div class="contact-item">


                        <div class="contact-icon">
                            ✦
                        </div>


                        <div>

                            <strong>
                                Platform Support
                            </strong>


                            <span>
                                General Campus360 questions
                                and support requests.
                            </span>

                        </div>


                    </div>


                </div>


                <div class="office-hours">


                    <strong>
                        Office Hours
                    </strong>


                    <span>
                        Monday – Friday · 9:00 AM – 4:00 PM
                    </span>


                </div>


            </div>


        </section>



        <!-- ==================================================
             FORM
        ================================================== -->

        <section class="contact-form-card">


            <h2>
                Send a Message
            </h2>


            <p>
                Complete the form below and your message
                will be sent to the Campus360 administration.
            </p>


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


            <form
                method="POST"
                action=""
            >


                <div class="form-grid">


                    <!-- NAME -->

                    <div class="form-group">


                        <label
                            for="name"
                        >
                            Your Name
                        </label>


                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            maxlength="100"
                            value="<?= sanitize(
                                $name
                            ) ?>"
                            placeholder="Enter your full name"
                            required
                        >


                    </div>



                    <!-- EMAIL -->

                    <div class="form-group">


                        <label
                            for="email"
                        >
                            Email Address
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            maxlength="255"
                            value="<?= sanitize(
                                $email
                            ) ?>"
                            placeholder="you@example.com"
                            required
                        >


                    </div>



                    <!-- SUBJECT -->

                    <div class="form-group full">


                        <label
                            for="subject"
                        >
                            Subject
                        </label>


                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            class="form-control"
                            maxlength="200"
                            value="<?= sanitize(
                                $subject
                            ) ?>"
                            placeholder="What would you like to ask?"
                            required
                        >


                    </div>



                    <!-- MESSAGE -->

                    <div class="form-group full">


                        <label
                            for="message"
                        >
                            Message
                        </label>


                        <textarea
                            id="message"
                            name="message"
                            class="form-control"
                            maxlength="5000"
                            placeholder="Write your message here..."
                            required
                        ><?= sanitize(
                            $message
                        ) ?></textarea>


                    </div>


                </div>


                <button
                    type="submit"
                    class="submit-button"
                >
                    SEND MESSAGE →
                </button>


                <div class="form-note">

                    Your message will be stored securely
                    and reviewed by the Campus360 administration team.

                </div>


            </form>


        </section>


    </div>


</main>



<!-- ==================================================
     FOOTER
================================================== -->

<footer>


    <div class="container foot">


        <div class="footgrid">


            <!-- BRAND -->

            <div class="footbrand">


                <a
                    class="brand"
                    href="index.php"
                >


                    <div class="crest">
                        C
                    </div>


                    <div>

                        <strong>
                            CAMPUS360
                        </strong>

                        <small>
                            COLLEGE COMMUNITY
                        </small>

                    </div>


                </a>


                <p>

                    A modern college community platform
                    connecting academics, student life,
                    opportunities and people in one place.

                </p>


            </div>



            <!-- EXPLORE -->

            <div>


                <h4>
                    Explore
                </h4>


                <a href="index.php#about">
                    About us
                </a>


                <a href="index.php#academics">
                    Academics
                </a>


                <a href="index.php#campus">
                    Campus life
                </a>


                <a href="modules/public/events.php">
                    Events
                </a>


            </div>



            <!-- STUDENT -->

            <div>


                <h4>
                    Student
                </h4>


                <?php if (
                    $isLoggedIn
                ): ?>


                    <a
                        href="<?= sanitize(
                            $dashboardUrl
                        ) ?>"
                    >
                        Dashboard
                    </a>


                    <a href="logout.php">
                        Logout
                    </a>


                <?php else: ?>


                    <a href="login.php">
                        Student portal
                    </a>


                    <a href="register.php">
                        Admissions
                    </a>


                <?php endif; ?>


                <a href="#">
                    Clubs & societies
                </a>


                <a href="#">
                    Career center
                </a>


            </div>



            <!-- CONTACT -->

            <div>


                <h4>
                    Contact
                </h4>


                <a
                    href="tel:+923001234567"
                >
                    +92 300 1234567
                </a>


                <a
                    href="mailto:info@campus360.edu.pk"
                >
                    info@campus360.edu.pk
                </a>


                <a href="#">
                    Main Campus, Lahore
                </a>


            </div>


        </div>



        <div class="copyright">


            <span>

                © <?= date('Y') ?>
                Campus360 College Community

            </span>


            <span>

                Privacy · Terms · Accessibility

            </span>


        </div>


    </div>


</footer>


</body>

</html>