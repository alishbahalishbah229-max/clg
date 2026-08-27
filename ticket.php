```php
<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
requireRole('student');

$user = getCurrentUser();

$user_id = $_SESSION['user_id'];

$event_id = $_GET['event_id'] ?? null;

$event = null;
$registration = null;
$registered_events = [];


// ======================================================
// GET SELECTED REGISTERED EVENT
// ======================================================

if ($event_id) {

    $stmt = $pdo->prepare("
        SELECT
            e.event_id,
            e.title,
            e.subtitle,
            e.description,
            e.category,
            e.start_date,
            e.end_date,
            e.venue_id,
            e.banner_image,
            e.approval_state,

            r.reg_id,
            r.status,
            r.qr_hash,
            r.queue_position,
            r.registered_at

        FROM registrations r

        INNER JOIN events e
            ON r.event_id = e.event_id

        WHERE r.user_id = ?
        AND r.event_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $user_id,
        $event_id
    ]);

    $result = $stmt->fetch();

    if ($result) {

        $event = $result;

        $registration = $result;
    }
}


// ======================================================
// GET ALL REGISTERED EVENTS
// ======================================================

if (!$event) {

    $stmt = $pdo->prepare("
        SELECT
            e.event_id,
            e.title,
            e.subtitle,
            e.category,
            e.start_date,
            e.end_date,
            e.venue_id,
            e.banner_image,

            r.reg_id,
            r.status,
            r.qr_hash,
            r.registered_at

        FROM registrations r

        INNER JOIN events e
            ON r.event_id = e.event_id

        WHERE r.user_id = ?

        ORDER BY e.start_date DESC
    ");

    $stmt->execute([
        $user_id
    ]);

    $registered_events = $stmt->fetchAll();
}


// ======================================================
// QR HASH
// ======================================================

$qr_data = '';

if ($event && $registration) {

    $qr_data = generateQRHash(
        $registration['reg_id'],
        $user_id,
        $event['event_id']
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
        My Ticket | EventShere
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

        :root {

            --navy: #071a36;
            --blue: #123761;

            --gold: #c99a3e;
            --gold-light: #e5c16f;

            --cream: #f5f7fa;
            --white: #ffffff;

            --ink: #172338;
            --muted: #697386;

            --line: #e5e9ef;

            --green: #2f8f5b;

        }


        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }


        html {

            scroll-behavior: smooth;

        }


        body {

            font-family:
                "DM Sans",
                sans-serif;

            background:
                var(--cream);

            color:
                var(--ink);

            line-height:
                1.6;

        }


        a {

            text-decoration: none;

            color: inherit;

        }


        /* ==========================================
           SIDEBAR
        ========================================== */

        .sidebar {

            position: fixed;

            top: 0;

            left: 0;

            width: 250px;

            height: 100vh;

            padding: 28px 18px;

            background:
                var(--navy);

            color:
                white;

            z-index: 100;

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 11px;

            padding:
                0 12px 30px;

            border-bottom:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .1
                );

        }


        .brand-mark {

            width: 40px;

            height: 46px;

            display: grid;

            place-items: center;

            background:
                #06152c;

            border:
                2px solid
                var(--gold);

            color:
                var(--gold-light);

            font-family:
                Georgia,
                serif;

            font-size: 19px;

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


        .brand strong {

            display: block;

            font-family:
                "Playfair Display",
                serif;

            font-size: 16px;

            letter-spacing: 1px;

        }


        .brand small {

            display: block;

            margin-top: 2px;

            color:
                var(--gold-light);

            font-size: 7px;

            letter-spacing: 2px;

        }


        .sidebar-nav {

            margin-top: 30px;

        }


        .nav-label {

            padding:
                0 12px 10px;

            color:
                #718198;

            font-size: 9px;

            font-weight: 700;

            letter-spacing: 1.5px;

            text-transform:
                uppercase;

        }


        .sidebar-nav a {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 12px;

            margin-bottom: 5px;

            border-radius: 7px;

            color:
                #b9c5d4;

            font-size: 12px;

            transition:
                .25s;

        }


        .sidebar-nav a:hover {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .07
                );

            color:
                white;

        }


        .sidebar-nav a.active {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .09
                );

            color:
                white;

            border-left:
                3px solid
                var(--gold);

        }


        .nav-icon {

            width: 25px;

            text-align: center;

            font-size: 15px;

        }


        .logout {

            position: absolute;

            left: 18px;

            right: 18px;

            bottom: 25px;

        }


        .logout a {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 12px;

            color:
                #9ba8b9;

            font-size: 12px;

            border-radius: 7px;

        }


        .logout a:hover {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .06
                );

            color:
                white;

        }


        /* ==========================================
           MAIN
        ========================================== */

        .main {

            margin-left:
                250px;

            min-height:
                100vh;

        }


        /* ==========================================
           TOPBAR
        ========================================== */

        .topbar {

            position: sticky;

            top: 0;

            z-index: 50;

            height: 76px;

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            padding:
                0 38px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .96
                );

            backdrop-filter:
                blur(12px);

            border-bottom:
                1px solid
                var(--line);

        }


        .page-title {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 25px;

        }


        .welcome {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .welcome-text {

            text-align: right;

        }


        .welcome-text strong {

            display: block;

            font-size: 12px;

        }


        .welcome-text small {

            display: block;

            margin-top: 2px;

            color:
                var(--muted);

            font-size: 9px;

        }


        .avatar {

            width: 40px;

            height: 40px;

            display: grid;

            place-items: center;

            border-radius: 50%;

            background:
                var(--navy);

            color:
                var(--gold-light);

            font-size: 14px;

            font-weight: 700;

        }


        /* ==========================================
           CONTENT
        ========================================== */

        .content {

            max-width:
                1150px;

            padding:
                38px;

        }


        .intro {

            margin-bottom:
                28px;

        }


        .eyebrow {

            margin-bottom: 7px;

            color:
                var(--gold);

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 2px;

            text-transform:
                uppercase;

        }


        .intro h1 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 35px;

        }


        .intro p {

            margin-top: 7px;

            color:
                var(--muted);

            font-size: 12px;

        }


        /* ==========================================
           TICKET
        ========================================== */

        .ticket {

            display: grid;

            grid-template-columns:
                1fr 280px;

            overflow: hidden;

            background:
                white;

            border:
                1px solid
                var(--line);

            border-radius:
                14px;

            box-shadow:
                0 20px 50px
                rgba(
                    7,
                    26,
                    54,
                    .08
                );

        }


        .ticket-main {

            position: relative;

            padding:
                35px;

        }


        .ticket-main::after {

            content: "";

            position: absolute;

            top: 0;

            right: -1px;

            width: 1px;

            height: 100%;

            border-right:
                2px dashed
                #dce1e8;

        }


        .ticket-brand {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            margin-bottom:
                45px;

        }


        .ticket-brand strong {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 18px;

            letter-spacing: 1px;

        }


        .registered {

            padding:
                7px 11px;

            border-radius:
                20px;

            background:
                #edf8f1;

            color:
                var(--green);

            font-size: 9px;

            font-weight: 700;

        }


        .event-category {

            margin-bottom: 9px;

            color:
                var(--gold);

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 2px;

            text-transform:
                uppercase;

        }


        .event-title {

            max-width:
                600px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                clamp(
                    30px,
                    4vw,
                    48px
                );

            line-height:
                1.08;

        }


        .event-subtitle {

            max-width:
                600px;

            margin-top:
                12px;

            color:
                var(--muted);

            font-size: 12px;

        }


        /* ==========================================
           EVENT DETAILS
        ========================================== */

        .details {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                20px;

            margin-top:
                40px;

            padding-top:
                25px;

            border-top:
                1px solid
                var(--line);

        }


        .detail-label {

            margin-bottom:
                5px;

            color:
                var(--muted);

            font-size: 9px;

            font-weight: 700;

            letter-spacing: 1px;

            text-transform:
                uppercase;

        }


        .detail-value {

            color:
                var(--ink);

            font-size: 12px;

            font-weight: 600;

        }


        /* ==========================================
           STUDENT INFORMATION
        ========================================== */

        .student-info {

            margin-top:
                35px;

            padding:
                18px;

            background:
                #f7f8fa;

            border-radius:
                8px;

        }


        .student-info h3 {

            margin-bottom:
                12px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 17px;

        }


        .student-row {

            display:
                flex;

            justify-content:
                space-between;

            gap:
                20px;

            padding:
                7px 0;

            border-bottom:
                1px solid
                #e8ebef;

            font-size:
                10px;

        }


        .student-row:last-child {

            border-bottom:
                none;

        }


        .student-row span:first-child {

            color:
                var(--muted);

        }


        .student-row span:last-child {

            color:
                var(--ink);

            font-weight:
                600;

            text-align:
                right;

            word-break:
                break-word;

        }


        /* ==========================================
           TICKET SIDE
        ========================================== */

        .ticket-side {

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            padding:
                30px;

            background:
                var(--navy);

            color:
                white;

            text-align:
                center;

        }


        .qr-heading {

            margin-bottom:
                18px;

            color:
                var(--gold-light);

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                2px;

            text-transform:
                uppercase;

        }


        .qr-box {

            width:
                175px;

            height:
                175px;

            display:
                grid;

            place-items:
                center;

            padding:
                12px;

            background:
                white;

            border-radius:
                10px;

        }


        .qr-placeholder {

            width:
                100%;

            height:
                100%;

            display:
                grid;

            place-items:
                center;

            border:
                2px dashed
                #cbd2dc;

            color:
                var(--navy);

            font-size:
                12px;

            font-weight:
                700;

        }


        .qr-note {

            max-width:
                210px;

            margin-top:
                18px;

            color:
                #b9c5d4;

            font-size:
                9px;

            line-height:
                1.6;

        }


        .registration-id {

            margin-top:
                18px;

            color:
                #e1e7ee;

            font-size:
                9px;

        }


        .registration-id strong {

            display:
                block;

            margin-top:
                3px;

            color:
                var(--gold-light);

            font-size:
                11px;

        }


        /* ==========================================
           STATUS
        ========================================== */

        .status {

            display:
                inline-block;

            margin-top:
                5px;

            padding:
                5px 9px;

            border-radius:
                20px;

            background:
                #edf8f1;

            color:
                var(--green);

            font-size:
                9px;

            font-weight:
                700;

            text-transform:
                uppercase;

        }


        /* ==========================================
           EMPTY STATE
        ========================================== */

        .empty {

            padding:
                70px 30px;

            background:
                white;

            border:
                1px solid
                var(--line);

            border-radius:
                12px;

            text-align:
                center;

        }


        .empty-icon {

            width:
                60px;

            height:
                60px;

            display:
                grid;

            place-items:
                center;

            margin:
                0 auto 18px;

            border-radius:
                50%;

            background:
                #edf2f8;

            color:
                var(--navy);

            font-size:
                22px;

        }


        .empty h2 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                24px;

        }


        .empty p {

            max-width:
                450px;

            margin:
                8px auto 20px;

            color:
                var(--muted);

            font-size:
                11px;

        }


        .back-btn {

            display:
                inline-block;

            padding:
                11px 18px;

            border-radius:
                5px;

            background:
                var(--navy);

            color:
                white;

            font-size:
                10px;

            font-weight:
                700;

        }


        /* ==========================================
           MOBILE
        ========================================== */

        @media (
            max-width: 850px
        ) {

            .sidebar {

                width:
                    70px;

                padding:
                    20px 8px;

            }


            .brand {

                justify-content:
                    center;

                padding:
                    0 0 25px;

            }


            .brand > div:last-child {

                display:
                    none;

            }


            .nav-label {

                display:
                    none;

            }


            .sidebar-nav a {

                justify-content:
                    center;

            }


            .sidebar-nav a span:not(
                .nav-icon
            ) {

                display:
                    none;

            }


            .logout a {

                justify-content:
                    center;

            }


            .logout a span:not(
                .nav-icon
            ) {

                display:
                    none;

            }


            .main {

                margin-left:
                    70px;

            }


            .topbar {

                padding:
                    0 20px;

            }


            .content {

                padding:
                    22px;

            }


            .ticket {

                grid-template-columns:
                    1fr;

            }


            .ticket-main::after {

                display:
                    none;

            }


            .ticket-side {

                padding:
                    35px;

            }

        }


        @media (
            max-width: 550px
        ) {

            .welcome-text {

                display:
                    none;

            }


            .details {

                grid-template-columns:
                    1fr;

            }


            .ticket-main {

                padding:
                    24px;

            }


            .event-title {

                font-size:
                    31px;

            }

        }
#qrcode {
    width: 151px;
    height: 151px;
    display: grid;
    place-items: center;
}

#qrcode img {
    width: 151px;
    height: 151px;
}
    </style>
    

</head>


<body>


    <!-- ==========================================
         SIDEBAR
    ========================================== -->

    <aside class="sidebar">


        <a
            href="../../index.php"
            class="brand"
        >

            <div class="brand-mark">
                E
            </div>

            <div>

                <strong>
                    EventShere
                </strong>

                <small>
                    COLLEGE COMMUNITY
                </small>

            </div>

        </a>


        <nav class="sidebar-nav">


            <div class="nav-label">
                Student Portal
            </div>


            <a href="dashboard.php">

                <span class="nav-icon">
                    ▦
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            <a href="my-events.php">

                <span class="nav-icon">
                    ◈
                </span>

                <span>
                    My Events
                </span>

            </a>


            <a href="profile.php">

                <span class="nav-icon">
                    ♙
                </span>

                <span>
                    My Profile
                </span>

            </a>


            <a
                href="ticket.php"
                class="active"
            >

                <span class="nav-icon">
                    ▤
                </span>

                <span>
                    My Tickets
                </span>

            </a>


        </nav>


        <div class="logout">

            <a href="../../logout.php">

                <span class="nav-icon">
                    ↪
                </span>

                <span>
                    Logout
                </span>

            </a>

        </div>


    </aside>



    <!-- ==========================================
         MAIN
    ========================================== -->

    <main class="main">


        <header class="topbar">


            <div class="page-title">
                My Ticket
            </div>


            <div class="welcome">


                <div class="welcome-text">

                    <strong>

                        <?= sanitize(
                            $user['full_name']
                        ) ?>

                    </strong>

                    <small>
                        Student Account
                    </small>

                </div>


                <div class="avatar">

                    <?= strtoupper(
                        substr(
                            $user['full_name'],
                            0,
                            1
                        )
                    ) ?>

                </div>


            </div>


        </header>



        <div class="content">


            <section class="intro">


                <div class="eyebrow">
                    Event Access
                </div>


                <h1>
                    My Event Ticket
                </h1>


                <p>
                    Your registered event information
                    and attendance ticket.
                </p>


            </section>



            <?php if ($event): ?>


                <!-- ==========================================
                     TICKET
                ========================================== -->

                <section class="ticket">


                    <div class="ticket-main">


                        <div class="ticket-brand">


                            <strong>
                                EventShere
                            </strong>


                            <?php if (
                                $registration['status']
                                === 'confirmed'
                            ): ?>

                                <span class="registered">
                                    ● REGISTERED
                                </span>

                            <?php elseif (
                                $registration['status']
                                === 'waitlisted'
                            ): ?>

                                <span
                                    class="registered"
                                    style="
                                        background:#fff7e6;
                                        color:#a56a00;
                                    "
                                >
                                    ● WAITLISTED
                                </span>

                            <?php else: ?>

                                <span
                                    class="registered"
                                    style="
                                        background:#fceeee;
                                        color:#b33a3a;
                                    "
                                >
                                    ●
                                    <?= sanitize(
                                        strtoupper(
                                            $registration[
                                                'status'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            <?php endif; ?>


                        </div>



                        <div class="event-category">

                            <?= sanitize(
                                strtoupper(
                                    $event['category']
                                )
                            ) ?>

                        </div>



                        <h2 class="event-title">

                            <?= sanitize(
                                $event['title']
                            ) ?>

                        </h2>



                        <?php if (
                            !empty(
                                $event['subtitle']
                            )
                        ): ?>

                            <p class="event-subtitle">

                                <?= sanitize(
                                    $event['subtitle']
                                ) ?>

                            </p>

                        <?php endif; ?>



                        <div class="details">


                            <div>

                                <div class="detail-label">
                                    Start Date & Time
                                </div>

                                <div class="detail-value">

                                    <?= formatDateTime(
                                        $event['start_date']
                                    ) ?>

                                </div>

                            </div>



                            <div>

                                <div class="detail-label">
                                    End Date & Time
                                </div>

                                <div class="detail-value">

                                    <?= formatDateTime(
                                        $event['end_date']
                                    ) ?>

                                </div>

                            </div>



                            <div>

                                <div class="detail-label">
                                    Venue
                                </div>

                                <div class="detail-value">

                                    <?php

                                    if (
                                        !empty(
                                            $event['venue_id']
                                        )
                                    ) {

                                        echo 'Venue ID: '
                                            . sanitize(
                                                (string)
                                                $event[
                                                    'venue_id'
                                                ]
                                            );

                                    } else {

                                        echo 'Not assigned';

                                    }

                                    ?>

                                </div>

                            </div>



                            <div>

                                <div class="detail-label">
                                    Registration Status
                                </div>

                                <div class="detail-value">

                                    <?= sanitize(
                                        ucfirst(
                                            $registration[
                                                'status'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </div>


                        </div>



                        <div class="student-info">


                            <h3>
                                Attendee Information
                            </h3>


                            <div class="student-row">

                                <span>
                                    Full Name
                                </span>

                                <span>

                                    <?= sanitize(
                                        $user['full_name']
                                    ) ?>

                                </span>

                            </div>



                            <div class="student-row">

                                <span>
                                    Email
                                </span>

                                <span>

                                    <?= sanitize(
                                        $user['email']
                                    ) ?>

                                </span>

                            </div>



                            <div class="student-row">

                                <span>
                                    Roll Number
                                </span>

                                <span>

                                    <?php

                                    if (
                                        !empty(
                                            $user['roll_number']
                                        )
                                    ) {

                                        echo sanitize(
                                            $user[
                                                'roll_number'
                                            ]
                                        );

                                    } else {

                                        echo 'Not provided';

                                    }

                                    ?>

                                </span>

                            </div>



                            <div class="student-row">

                                <span>
                                    Registered On
                                </span>

                                <span>

                                    <?= formatDateTime(
                                        $registration[
                                            'registered_at'
                                        ]
                                    ) ?>

                                </span>

                            </div>


                        </div>


                    </div>



                    <!-- ==========================================
                         QR AREA
                    ========================================== -->

                    <aside class="ticket-side">


                        <div class="qr-heading">
                            Attendance QR
                        </div>



                        <div class="qr-box">


                            <div
    id="qrcode"
    data-qr="<?= sanitize($qr_data) ?>"
></div>


                        </div>



                        <p class="qr-note">

                            Present this ticket at
                            the event entrance.

                            Your secure QR code will
                            be used for attendance
                            verification.

                        </p>



                        <div class="registration-id">

                            Registration ID


                            <strong>

                                <?= sanitize(
                                    $registration[
                                        'reg_id'
                                    ]
                                ) ?>

                            </strong>

                        </div>


                    </aside>


                </section>


            <?php else: ?>


                <!-- ==========================================
                     EMPTY STATE
                ========================================== -->

                <section class="empty">


                    <div class="empty-icon">
                        ▤
                    </div>


                    <h2>
                        No Ticket Selected
                    </h2>


                    <p>

                        You have not selected a
                        registered event yet.

                        Open My Events and select
                        an event to view your ticket.

                    </p>


                    <a
                        href="my-events.php"
                        class="back-btn"
                    >
                        VIEW MY EVENTS
                    </a>


                </section>


            <?php endif; ?>


        </div>


    </main>

<script src="../../assets/js/qrcode.min.js"></script>

<script>

    const qrElement = document.getElementById("qrcode");

    if (qrElement) {

        const qrValue = qrElement.dataset.qr;

        if (qrValue) {

            new QRCode(qrElement, {
                text: qrValue,
                width: 151,
                height: 151,
                correctLevel: QRCode.CorrectLevel.H
            });

        }

    }

</script>
</body>

</html>
```
