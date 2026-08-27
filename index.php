
<?php

// ==================================================
// CAMPUS360 - MAIN HOMEPAGE
// ==================================================


require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';


$contactName = '';
$contactEmail = '';
$contactSubject = '';
$contactMessage = '';

$contactSuccess = '';
$contactError = '';


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['contact_form'])
) {

    $contactName =
        trim(
            $_POST['contact_name'] ?? ''
        );

    $contactEmail =
        trim(
            $_POST['contact_email'] ?? ''
        );

    $contactSubject =
        trim(
            $_POST['contact_subject'] ?? ''
        );

    $contactMessage =
        trim(
            $_POST['contact_message'] ?? ''
        );


    try {

        if ($contactName === '') {

            throw new Exception(
                'Please enter your name.'
            );
        }


        if ($contactEmail === '') {

            throw new Exception(
                'Please enter your email address.'
            );
        }


        if (
            !filter_var(
                $contactEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            throw new Exception(
                'Please enter a valid email address.'
            );
        }


        if ($contactSubject === '') {

            throw new Exception(
                'Please enter a subject.'
            );
        }


        if ($contactMessage === '') {

            throw new Exception(
                'Please enter your message.'
            );
        }


        $stmt =
            $pdo->prepare("
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
                $contactName,

            ':email' =>
                $contactEmail,

            ':subject' =>
                $contactSubject,

            ':message' =>
                $contactMessage

        ]);


        $contactSuccess =
            'Your message has been sent successfully. Our administration team will review it shortly.';


        $contactName = '';
        $contactEmail = '';
        $contactSubject = '';
        $contactMessage = '';


    } catch (
        PDOException $e
    ) {

        error_log(
            'Homepage Contact Error: ' .
            $e->getMessage()
        );

        $contactError =
            'We could not send your message right now. Please try again.';


    } catch (
        Exception $e
    ) {

        $contactError =
            $e->getMessage();
    }

}

// ==================================================
// AUTHENTICATION
// ==================================================

$isLoggedIn = isAuthenticated();

$currentUser = null;

if ($isLoggedIn) {
    $currentUser = getCurrentUser();
}


// ==================================================
// DASHBOARD URL
// ==================================================
// ==================================================
// APPROVED EVENTS FROM DATABASE
// ==================================================

$approvedEvents = [];

try {

    if (isset($pdo) && $pdo instanceof PDO) {

        $eventStmt = $pdo->prepare("
            SELECT
                e.event_id,
                e.title,
                e.subtitle,
                e.description,
                e.category,
                e.start_date,
                e.end_date,
                e.banner_image,
                e.venue_id,
                v.venue_name
            FROM events e

            LEFT JOIN venues v
                ON v.venue_id = e.venue_id

            WHERE e.approval_state = 'approved'

            ORDER BY e.start_date ASC

            LIMIT 3
        ");

        $eventStmt->execute();

        $approvedEvents =
            $eventStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {

    error_log(
        'Homepage Approved Events Error: ' .
        $e->getMessage()
    );

    $approvedEvents = [];
}
$dashboardUrl = '#';

if ($currentUser) {

    switch ($currentUser['role']) {

        case 'student':
            $dashboardUrl = 'modules/student/dashboard.php';
            break;

        case 'organizer':
            $dashboardUrl = 'modules/organizer/dashboard.php';
            break;

        case 'admin':
            $dashboardUrl = 'modules/admin/dashboard.php';
            break;
    }
}
// ==================================================
// APPROVED / ACTIVE PROGRAMS FROM DATABASE
// ==================================================

$programs = [];

try {

    if (isset($pdo) && $pdo instanceof PDO) {

        $programStmt = $pdo->prepare("
            SELECT
                program_id,
                title,
                description,
                image
            FROM programs
            WHERE status = 'active'
            ORDER BY created_at DESC
            LIMIT 4
        ");

        $programStmt->execute();

        $programs =
            $programStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {

    error_log(
        'Homepage Programs Error: ' .
        $e->getMessage()
    );

    $programs = [];
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
        EventSphere — College Community
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


    <!-- Main Website CSS -->

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

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
                ⌖ Main Campus, Larachi
            </span>

        </div>


        <div class="portal">

            <?php if ($isLoggedIn): ?>

                <span>
                    
                    <?= sanitize($currentUser['full_name']); ?>
                </span>

                <a href="<?= $dashboardUrl; ?>">
                    Dashboard
                </a>

                <a href="logout.php">
                    Logout
                </a>

            <?php else: ?>

                <a href="./login.php">
                    Login
                </a>

                <a href="./register.php">
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


        <!-- Logo -->

        <a
            class="brand"
            href="index.php"
        >

            <div class="crest">
                E
            </div>

            <div>

                <strong>
                    EventSphere
                </strong>

                <small>
                    COLLEGE COMMUNITY
                </small>

            </div>

        </a>


        <!-- Navigation Links -->

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

            <a href="#contact">
    CONTACT
</a>

        </div>


        <!-- Main Action -->

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
     HERO SECTION
================================================== -->

<section class="hero">

    <div class="container">

        <div class="hero-content reveal">

            <div class="kicker">
                Learn · Grow · Lead
            </div>


            <h1>

                Where ideas become
                <span>
                    your future.
                </span>

            </h1>


            <p>
                EventSphere is a connected college community
                built around learning, discovery, people and
                possibilities — from your first class to your
                biggest achievement.
            </p>


            <div class="hero-actions">

                <a
                    class="btn btn-gold"
                    href="#academics"
                >
                    EXPLORE ACADEMICS →
                </a>


                <a
                    class="btn btn-light"
                    href="#campus"
                >
                    DISCOVER CAMPUS ▶
                </a>

            </div>

        </div>

    </div>


    <!-- Hero Information Panel -->

    <div class="hero-panel reveal">


        <div>

            <span class="roundicon">
                🎓
            </span>

            <span>

                <b>
                    Quality Education
                </b>

                <small>
                    Learn from experienced faculty
                    & industry experts.
                </small>

            </span>

        </div>


        <div>

            <span class="roundicon">
                ⌂
            </span>

            <span>

                <b>
                    Modern Campus
                </b>

                <small>
                    Spaces designed for learning,
                    creativity and connection.
                </small>

            </span>

        </div>


        <div>

            <span class="roundicon">
                ✦
            </span>

            <span>

                <b>
                    Bright Future
                </b>

                <small>
                    Skills, careers and opportunities
                    beyond the classroom.
                </small>

            </span>

        </div>


    </div>

</section>



<!-- ==================================================
     QUICK ACCESS
================================================== -->

<section class="quick">

    <div class="container quick-grid">


        <div class="quick-card reveal">

            <div class="qicon">
                ▣
            </div>

            <h3>
                Admissions
            </h3>

            <p>
                Start your journey towards excellence.
            </p>

            <a
                class="link"
                href="modules/auth/register.php"
            >
                Apply now →
            </a>

        </div>


        <div class="quick-card reveal">

            <div class="qicon">
                ▤
            </div>

            <h3>
                Academics
            </h3>

            <p>
                Explore programs built for tomorrow.
            </p>

            <a
                class="link"
                href="#academics"
            >
                View programs →
            </a>

        </div>


        <div
            class="quick-card reveal"
            id="campus"
        >

            <div class="qicon">
                ♙
            </div>

            <h3>
                Student Life
            </h3>

            <p>
                Find clubs, societies, sports and more.
            </p>

            <a
                class="link"
                href="#about"
            >
                Explore →
            </a>

        </div>


        <div class="quick-card reveal">

            <div class="qicon">
                ◈
            </div>

            <h3>
                News & Events
            </h3>

            <p>
                Stay connected with campus life.
            </p>

            <a
                class="link"
                href="modules/public/events.php"
            >
                View events →
            </a>

        </div>


        <div class="quick-card tour reveal">

            <h3>
                Take a Virtual Tour
            </h3>

            <p>
                Explore our campus, spaces and
                community from anywhere.
            </p>

            <a
                class="link"
                href="#about"
            >
                START TOUR ▶
            </a>

        </div>


    </div>

</section>



<!-- ==================================================
     ACADEMICS
================================================== -->

<section
    class="section programs"
    id="academics"
>

    <div class="container">


        <div class="section-head reveal">

            <div>

                <div class="eyebrow">
                    Academic Excellence
                </div>

                <h2>
                    Programs designed<br>
                    for your success.
                </h2>

            </div>


            <p>
                Go beyond textbooks. Build practical skills,
                discover your interests and prepare for the
                world with programs shaped around modern careers.
            </p>

        </div>


       <div class="program-grid">

    <?php if (!empty($programs)): ?>

        <?php foreach ($programs as $index => $program): ?>

            <?php
                $programImage = !empty($program['image'])
                    ? $program['image']
                    : '';

                $programClass =
                    'p' . (($index % 4) + 1);
            ?>

            <div class="program reveal">

                <?php if ($programImage !== ''): ?>

                    <div
                        class="program-img"
                        style="
                            background-image:url('<?= htmlspecialchars(
                                $programImage,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>');
                            background-size:cover;
                            background-position:center;
                        "
                    ></div>

                <?php else: ?>

                    <div
                        class="program-img <?= $programClass; ?>"
                    ></div>

                <?php endif; ?>


                <div class="program-body">

                    <h3>
                        <?= sanitize(
                            $program['title']
                        ); ?>
                    </h3>

                    <p>
                        <?= sanitize(
                            $program['description']
                        ); ?>
                    </p>

                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div
            style="
                grid-column:1/-1;
                padding:35px;
                background:white;
                border:1px solid #e4e8ee;
                border-radius:10px;
                text-align:center;
            "
        >

            <h3>
                Programs Coming Soon
            </h3>

            <p
                style="
                    margin-top:8px;
                    color:#697386;
                    font-size:10px;
                "
            >
                Academic programs will appear here soon.
            </p>

        </div>

    <?php endif; ?>

</div>

    </div>

</section>



<!-- ==================================================
     ABOUT
================================================== -->

<section
    class="section"
    id="about"
>

    <div class="container community">


        <div class="community-img reveal"></div>


        <div class="community-copy reveal">

            <div class="eyebrow">
                More than a classroom
            </div>

            <h2>
                A college experience built around people.
            </h2>


            <p>
                Great education is not only about lectures
                and exams. It is about conversations, friendships,
                projects, mentors, competitions, clubs, creativity
                and the confidence to try something new.
            </p>


            <p>
                EventSphere connects all of it in one welcoming
                digital home — so students always know what is
                happening and where they belong.
            </p>


            <div class="facts">

                <div class="fact">

                    <b>
                        4.8K+
                    </b>

                    <span>
                        Students
                    </span>

                </div>


                <div class="fact">

                    <b>
                        120+
                    </b>

                    <span>
                        Programs & Clubs
                    </span>

                </div>


                <div class="fact">

                    <b>
                        35+
                    </b>

                    <span>
                        Campus Societies
                    </span>

                </div>

            </div>

        </div>

    </div>

</section>



<!-- ==================================================
     EVENTS
================================================== -->

<section
    class="section events-section"
    id="events"
>

    <div class="container">


        <div class="section-head reveal">

            <div>

                <div class="eyebrow">
                    EventSphere Calendar
                </div>

                <h2>
                    Life is happening here.
                </h2>

            </div>


            <p>
                Discover workshops, competitions, talks,
                sports, societies and the everyday moments
                that make college memorable.
            </p>

        </div>


        <div class="eventgrid">

    <?php if (!empty($approvedEvents)): ?>

        <?php foreach ($approvedEvents as $event): ?>

            <?php
                $eventDate = !empty($event['start_date'])
                    ? strtotime($event['start_date'])
                    : false;

                $eventDay = $eventDate
                    ? date('d', $eventDate)
                    : '—';

                $eventMonth = $eventDate
                    ? strtoupper(date('M', $eventDate))
                    : '';

                $eventCategory = !empty($event['category'])
                    ? strtoupper($event['category'])
                    : 'CAMPUS EVENT';

                $eventDescription = !empty($event['description'])
                    ? $event['description']
                    : (
                        !empty($event['subtitle'])
                            ? $event['subtitle']
                            : 'Join us for this exciting campus event.'
                    );

                $eventImage = !empty($event['banner_image'])
                    ? $event['banner_image']
                    : '';
            ?>

            <div class="event reveal">

                <a
                    href="modules/public/event-details.php?event_id=<?= urlencode($event['event_id']); ?>"
                    style="display:block;"
                >

                    <?php if ($eventImage !== ''): ?>

                        <div
                            class="event-img"
                            style="
                                background-image:url('<?= htmlspecialchars(
                                    $eventImage,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>');
                                background-size:cover;
                                background-position:center;
                            "
                        ></div>

                    <?php else: ?>

                        <div class="event-img e1"></div>

                    <?php endif; ?>


                    <div class="event-body">

                        <div class="date">

                            <?= sanitize($eventDay); ?>

                            <?= sanitize($eventMonth); ?>

                            ·

                            <?= sanitize($eventCategory); ?>

                        </div>


                        <h3>

                            <?= sanitize(
                                $event['title']
                            ); ?>

                        </h3>


                        <p>

                            <?= sanitize(
                                mb_strimwidth(
                                    $eventDescription,
                                    0,
                                    130,
                                    '...'
                                )
                            ); ?>

                        </p>


                        <?php if (!empty($event['venue_name'])): ?>

                            <small
                                style="
                                    display:block;
                                    margin-top:10px;
                                    color:#697386;
                                    font-size:8px;
                                "
                            >
                                📍 <?= sanitize(
                                    $event['venue_name']
                                ); ?>
                            </small>

                        <?php endif; ?>


                    </div>

                </a>

            </div>

        <?php endforeach; ?>


    <?php else: ?>

        <div
            style="
                grid-column:1/-1;
                padding:35px;
                background:white;
                border:1px solid #e4e8ee;
                border-radius:10px;
                text-align:center;
            "
        >

            <h3
                style="
                    color:#071a36;
                    font-family:'Playfair Display',serif;
                    font-size:20px;
                "
            >
                No Approved Events Yet
            </h3>

            <p
                style="
                    margin-top:7px;
                    color:#697386;
                    font-size:9px;
                "
            >
                New campus events will appear here
                once they are approved by administration.
            </p>

        </div>

    <?php endif; ?>

</div>


        <div style="margin-top: 30px;">

            <a
                class="btn btn-gold"
                href="modules/public/events.php"
            >
                VIEW ALL EVENTS →
            </a>

        </div>

    </div>

</section>



<!-- ==================================================
     NEWSLETTER
================================================== -->

<!-- ==================================================
     CONTACT
================================================== -->

<section
    class="newsletter contact-section"
    id="contact"
>

    <div class="container">

        <div class="contact-box reveal">


            <div class="contact-heading">

                <div class="eyebrow">
                    Get In Touch
                </div>

                <h2>
                    Have something to say?
                </h2>

                <p>
                    Questions, suggestions or campus support?
                    Send a message directly to the EventSphere
                    administration team.
                </p>


                <div class="contact-details">

                    <div>

                        <strong>
                            PHONE
                        </strong>

                        <span>
                            +92 300 1234567
                        </span>

                    </div>


                    <div>

                        <strong>
                            EMAIL
                        </strong>

                        <span>
                            info@eventsphere.edu.pk
                        </span>

                    </div>


                    <div>

                        <strong>
                            CAMPUS
                        </strong>

                        <span>
                            Main Campus, Karachi
                        </span>

                    </div>

                </div>


            </div>


            <div class="contact-form-wrap">


                <?php if (
                    $contactSuccess !== ''
                ): ?>

                    <div class="contact-alert success">

                        <?= sanitize(
                            $contactSuccess
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    $contactError !== ''
                ): ?>

                    <div class="contact-alert error">

                        <?= sanitize(
                            $contactError
                        ) ?>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action="#contact"
                    class="contact-form"
                >

                    <input
                        type="hidden"
                        name="contact_form"
                        value="1"
                    >


                    <div class="contact-form-row">


                        <div class="contact-field">

                            <label
                                for="contact_name"
                            >
                                Your Name
                            </label>


                            <input
                                type="text"
                                id="contact_name"
                                name="contact_name"
                                value="<?= sanitize(
                                    $contactName
                                ) ?>"
                                placeholder="Your full name"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="contact-field">

                            <label
                                for="contact_email"
                            >
                                Email Address
                            </label>


                            <input
                                type="email"
                                id="contact_email"
                                name="contact_email"
                                value="<?= sanitize(
                                    $contactEmail
                                ) ?>"
                                placeholder="you@example.com"
                                maxlength="255"
                                required
                            >

                        </div>


                    </div>


                    <div class="contact-field">

                        <label
                            for="contact_subject"
                        >
                            Subject
                        </label>


                        <input
                            type="text"
                            id="contact_subject"
                            name="contact_subject"
                            value="<?= sanitize(
                                $contactSubject
                            ) ?>"
                            placeholder="What would you like to ask?"
                            maxlength="200"
                            required
                        >

                    </div>


                    <div class="contact-field">

                        <label
                            for="contact_message"
                        >
                            Message
                        </label>


                        <textarea
                            id="contact_message"
                            name="contact_message"
                            placeholder="Write your message..."
                            maxlength="5000"
                            required
                        ><?= sanitize(
                            $contactMessage
                        ) ?></textarea>

                    </div>


                    <button
                        type="submit"
                        class="contact-submit"
                    >
                        SEND MESSAGE →
                    </button>


                    <small class="contact-note">

                        Your message will be securely stored
                        and reviewed by the EventSphere administration.

                    </small>


                </form>


            </div>


        </div>

    </div>

</section>

    <div class="container">

        <div class="newsbox reveal">

            <div>

                <div class="eyebrow">
                    Stay in the loop
                </div>

                <h2>
                    Never miss what's happening.
                </h2>

                <p>
                    Get campus news, opportunities and
                    upcoming activities in your inbox.
                </p>

            </div>


            <div class="email">

                <input
                    type="email"
                    placeholder="Your email address"
                >

                <button type="button">
                    SUBSCRIBE
                </button>

            </div>

        </div>

    </div>

</section>



<!-- ==================================================
     FOOTER
================================================== -->

<footer id="contact">

    <div class="container foot">

        <div class="footgrid">


            <div class="footbrand">

                <a
                    class="brand"
                    href="index.php"
                >

                    <div class="crest">
                        E
                    </div>

                    <div>

                        <strong>
                            EventSphere
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


            <div>

                <h4>
                    Explore
                </h4>

                <a href="#about">
                    About us
                </a>

                <a href="#academics">
                    Academics
                </a>

                <a href="#campus">
                    Campus life
                </a>

                <a href="modules/public/events.php">
                    Events
                </a>

            </div>


            <div>

                <h4>
                    Student
                </h4>


                <?php if ($isLoggedIn): ?>

                    <a href="<?= $dashboardUrl; ?>">
                        Dashboard
                    </a>

                    <a href="logout.php">
                        Logout
                    </a>

                <?php else: ?>

                    <a href="modules/auth/login.php">
                        Student portal
                    </a>

                    <a href="modules/auth/register.php">
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


            <div>

                <h4>
                    Contact
                </h4>

                <a href="tel:+923001234567">
                    +92 300 1234567
                </a>

                <a href="mailto:info@campus360.edu.pk">
                    info@campus360.edu.pk
                </a>

                <a href="#">
                    Main Campus, Karachi
                </a>

            </div>


        </div>


        <div class="copyright">

            <span>
                © 2026 EventSphere College Community
            </span>

            <span>
                Privacy · Terms · Accessibility
            </span>

        </div>

    </div>

</footer>



<!-- ==================================================
     SCROLL ANIMATION
================================================== -->

<script>

    const observer = new IntersectionObserver(

        (entries) => {

            entries.forEach((entry) => {

                if (entry.isIntersecting) {

                    entry.target.classList.add("show");

                    observer.unobserve(entry.target);

                }

            });

        },

        {
            threshold: 0.1
        }

    );


    document
        .querySelectorAll(".reveal")
        .forEach((element) => {

            observer.observe(element);

        });

</script>


</body>

</html>
```
