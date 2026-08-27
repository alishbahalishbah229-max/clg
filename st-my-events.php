```php
<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole('student');

$user = getCurrentUser();

$user_id = $_SESSION['user_id'];


// ==================================================
// GET STUDENT EVENTS
// ==================================================

$stmt = $pdo->prepare("
    SELECT
        e.event_id,
        e.title,
        e.subtitle,
        e.description,
        e.start_date,
        e.end_date,
        e.venue_id,
        e.category,
        e.banner_image,
        e.approval_state

    FROM registrations r

    INNER JOIN events e
        ON r.event_id = e.event_id

    WHERE r.user_id = ?

    ORDER BY e.start_date DESC
");

$stmt->execute([
    $user_id
]);

$events = $stmt->fetchAll();


// ==================================================
// SEPARATE UPCOMING AND COMPLETED
// ==================================================

$upcoming_events = [];
$completed_events = [];

foreach ($events as $event) {

    if (
        strtotime($event['start_date']) >= time()
    ) {

        $upcoming_events[] = $event;

    } else {

        $completed_events[] = $event;

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
        My Events | EventSphere
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

        }


        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }


        body {

            font-family:
                "DM Sans",
                sans-serif;

            background:
                var(--cream);

            color:
                var(--ink);

        }


        a {

            text-decoration: none;

            color: inherit;

        }


        /* ==================================================
           SIDEBAR
        ================================================== */

        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

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

            transition: .25s;

        }


        .sidebar-nav a:hover {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .07
                );

            color: white;

        }


        .sidebar-nav a.active {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .09
                );

            color: white;

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

            color: white;

        }


        /* ==================================================
           MAIN
        ================================================== */

        .main {

            margin-left: 250px;

            min-height: 100vh;

        }


        .topbar {

            position: sticky;

            top: 0;

            z-index: 50;

            height: 76px;

            display: flex;

            align-items: center;

            justify-content: space-between;

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

            font-family:
                "Playfair Display",
                serif;

            font-size: 25px;

            color:
                var(--navy);

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


        .content {

            padding:
                38px;

        }


        /* ==================================================
           PAGE INTRO
        ================================================== */

        .intro {

            margin-bottom:
                30px;

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

            max-width: 650px;

            margin-top: 7px;

            color:
                var(--muted);

            font-size: 12px;

        }


        /* ==================================================
           TABS
        ================================================== */

        .tabs {

            display: flex;

            gap: 8px;

            margin-bottom:
                22px;

        }


        .tab {

            padding:
                10px 17px;

            border:
                1px solid
                var(--line);

            border-radius: 5px;

            background:
                white;

            color:
                var(--muted);

            font-size: 10px;

            font-weight: 700;

            cursor: pointer;

        }


        .tab.active {

            background:
                var(--navy);

            border-color:
                var(--navy);

            color:
                white;

        }


        /* ==================================================
           EVENT GRID
        ================================================== */

        .event-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap: 18px;

        }


        .event-card {

            overflow: hidden;

            background:
                white;

            border:
                1px solid
                var(--line);

            border-radius:
                10px;

            transition:
                .25s;

        }


        .event-card:hover {

            transform:
                translateY(-5px);

            box-shadow:
                0 18px 40px
                rgba(
                    7,
                    26,
                    54,
                    .09
                );

        }


        .event-image {

            height: 175px;

            background:
                linear-gradient(
                    135deg,
                    var(--navy),
                    var(--blue)
                );

            display: flex;

            align-items:
                flex-end;

            padding:
                16px;

            background-size:
                cover;

            background-position:
                center;

        }


        .event-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;

        }


        .event-category {

            padding:
                6px 10px;

            border-radius:
                4px;

            background:
                rgba(
                    7,
                    26,
                    54,
                    .88
                );

            color:
                var(--gold-light);

            font-size: 8px;

            font-weight: 700;

            letter-spacing: 1px;

            text-transform:
                uppercase;

        }


        .event-body {

            padding:
                20px;

        }


        .event-body h3 {

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size:
                19px;

            line-height:
                1.3;

            margin-bottom:
                7px;

        }


        .subtitle {

            min-height:
                35px;

            margin-bottom:
                15px;

            color:
                var(--muted);

            font-size:
                10px;

            line-height:
                1.5;

        }


        .event-detail {

            display: flex;

            align-items: center;

            gap: 8px;

            margin-top: 8px;

            color:
                var(--muted);

            font-size: 10px;

        }


        .detail-icon {

            color:
                var(--gold);

        }


        .event-footer {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            margin-top:
                18px;

            padding-top:
                15px;

            border-top:
                1px solid
                var(--line);

        }


        .status {

            color:
                #2f8f5b;

            font-size: 9px;

            font-weight: 700;

        }


        .details-btn {

            padding:
                8px 12px;

            border-radius:
                4px;

            background:
                var(--navy);

            color:
                white;

            font-size: 9px;

            font-weight: 700;

            transition: .2s;

        }


        .details-btn:hover {

            background:
                var(--blue);

            transform:
                translateY(-1px);

        }


        /* ==================================================
           EMPTY
        ================================================== */

        .empty {

            grid-column:
                1 / -1;

            padding:
                65px 20px;

            text-align:
                center;

            background:
                white;

            border:
                1px dashed
                var(--line);

            border-radius:
                10px;

        }


        .empty-icon {

            width: 55px;

            height: 55px;

            display: grid;

            place-items: center;

            margin:
                0 auto 15px;

            border-radius:
                50%;

            background:
                #edf2f8;

            color:
                var(--navy);

            font-size: 20px;

        }


        .empty h3 {

            margin-bottom:
                6px;

            color:
                var(--navy);

            font-family:
                "Playfair Display",
                serif;

            font-size: 21px;

        }


        .empty p {

            color:
                var(--muted);

            font-size: 11px;

        }


        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (
            max-width: 1050px
        ) {

            .event-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );

            }

        }


        @media (
            max-width: 750px
        ) {

            .sidebar {

                width: 70px;

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
                    20px;

            }


            .event-grid {

                grid-template-columns:
                    1fr;

            }

        }

    </style>

</head>


<body>


    <!-- ==================================================
         SIDEBAR
    ================================================== -->

    <aside class="sidebar">


     <a
    href="student-dashboard.php"
    class="brand"
>

            <div class="brand-mark">
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


        <nav class="sidebar-nav">


            <div class="nav-label">
                Student Portal
            </div>


            <a
                href="dashboard.php"
            >

                <span class="nav-icon">
                    ▦
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="my-events.php"
                class="active"
            >

                <span class="nav-icon">
                    ◈
                </span>

                <span>
                    My Events
                </span>

            </a>


            <a
                href="profile.php"
            >

                <span class="nav-icon">
                    ♙
                </span>

                <span>
                    My Profile
                </span>

            </a>


            <a
                href="ticket.php"
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

            <a
                href="../../logout.php"
            >

                <span class="nav-icon">
                    ↪
                </span>

                <span>
                    Logout
                </span>

            </a>

        </div>


    </aside>



    <!-- ==================================================
         MAIN
    ================================================== -->

    <main class="main">


        <header class="topbar">


            <div class="page-title">
                My Events
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


            <!-- ==================================================
                 INTRO
            ================================================== -->

            <section class="intro">

                <div class="eyebrow">
                    Your Campus Activity
                </div>

                <h1>
                    My Registered Events
                </h1>

                <p>

                    Keep track of the events,
                    workshops, seminars and
                    activities you have registered
                    for through EventSphere

                </p>

            </section>



            <!-- ==================================================
                 TABS
            ================================================== -->

            <div class="tabs">


                <button
                    class="tab active"
                    onclick="showTab('upcoming', this)"
                >

                    Upcoming
                    (<?= count(
                        $upcoming_events
                    ) ?>)

                </button>


                <button
                    class="tab"
                    onclick="showTab('completed', this)"
                >

                    Completed
                    (<?= count(
                        $completed_events
                    ) ?>)

                </button>


            </div>



            <!-- ==================================================
                 UPCOMING EVENTS
            ================================================== -->

            <div
                id="upcoming"
                class="event-grid event-section"
            >


                <?php if (
                    empty(
                        $upcoming_events
                    )
                ): ?>


                    <div class="empty">

                        <div class="empty-icon">
                            ◈
                        </div>

                        <h3>
                            No Upcoming Events
                        </h3>

                        <p>

                            You don't have any
                            upcoming registered
                            events.

                        </p>

                    </div>


                <?php else: ?>


                    <?php foreach (
                        $upcoming_events
                        as $event
                    ): ?>


                        <article
                            class="event-card"
                        >


                            <div
                                class="event-image"
                                <?php if (
                                    !empty(
                                        $event[
                                            'banner_image'
                                        ]
                                    )
                                ): ?>

                                    style="
                                        background-image:
                                        url(
                                            '../../uploads/banners/<?= htmlspecialchars(
                                                $event[
                                                    'banner_image'
                                                ]
                                            ) ?>'
                                        );
                                    "

                                <?php endif; ?>
                            >


                                <span
                                    class="event-category"
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $event[
                                                'category'
                                            ]
                                        )
                                    ) ?>

                                </span>


                            </div>


                            <div
                                class="event-body"
                            >


                                <h3>

                                    <?= sanitize(
                                        $event[
                                            'title'
                                        ]
                                    ) ?>

                                </h3>


                                <p
                                    class="subtitle"
                                >

                                    <?= sanitize(
                                        $event[
                                            'subtitle'
                                        ]
                                            ??
                                        'Campus event'
                                    ) ?>

                                </p>


                                <div
                                    class="event-detail"
                                >

                                    <span
                                        class="detail-icon"
                                    >
                                        ◷
                                    </span>

                                    <span>

                                        <?= formatDateTime(
                                            $event[
                                                'start_date'
                                            ]
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="event-detail"
                                >

                                    <span
                                        class="detail-icon"
                                    >
                                        ⌖
                                    </span>

                                    <span>

                                        Venue ID:
                                        <?= sanitize(
                                            (string)
                                            (
                                                $event[
                                                    'venue_id'
                                                ]
                                                ??
                                                'Not assigned'
                                            )
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="event-footer"
                                >


                                    <span
                                        class="status"
                                    >
                                        ● Registered
                                    </span>


                                    <a
                                        href="ticket.php?event_id=<?= urlencode(
                                            $event[
                                                'event_id'
                                            ]
                                        ) ?>"
                                        class="details-btn"
                                    >
                                        VIEW TICKET
                                    </a>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>



            <!-- ==================================================
                 COMPLETED EVENTS
            ================================================== -->

            <div
                id="completed"
                class="event-grid event-section"
                style="display: none;"
            >


                <?php if (
                    empty(
                        $completed_events
                    )
                ): ?>


                    <div class="empty">

                        <div class="empty-icon">
                            ✓
                        </div>

                        <h3>
                            No Completed Events
                        </h3>

                        <p>

                            Your completed events
                            will appear here.

                        </p>

                    </div>


                <?php else: ?>


                    <?php foreach (
                        $completed_events
                        as $event
                    ): ?>


                        <article
                            class="event-card"
                        >


                            <div
                                class="event-image"
                                <?php if (
                                    !empty(
                                        $event[
                                            'banner_image'
                                        ]
                                    )
                                ): ?>

                                    style="
                                        background-image:
                                        url(
                                            '../../uploads/banners/<?= htmlspecialchars(
                                                $event[
                                                    'banner_image'
                                                ]
                                            ) ?>'
                                        );
                                    "

                                <?php endif; ?>
                            >


                                <span
                                    class="event-category"
                                >

                                    <?= sanitize(
                                        ucfirst(
                                            $event[
                                                'category'
                                            ]
                                        )
                                    ) ?>

                                </span>


                            </div>


                            <div
                                class="event-body"
                            >


                                <h3>

                                    <?= sanitize(
                                        $event[
                                            'title'
                                        ]
                                    ) ?>

                                </h3>


                                <p
                                    class="subtitle"
                                >

                                    <?= sanitize(
                                        $event[
                                            'subtitle'
                                        ]
                                            ??
                                        'Campus event'
                                    ) ?>

                                </p>


                                <div
                                    class="event-detail"
                                >

                                    <span
                                        class="detail-icon"
                                    >
                                        ◷
                                    </span>

                                    <span>

                                        <?= formatDateTime(
                                            $event[
                                                'start_date'
                                            ]
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="event-detail"
                                >

                                    <span
                                        class="detail-icon"
                                    >
                                        ⌖
                                    </span>

                                    <span>

                                        Venue ID:
                                        <?= sanitize(
                                            (string)
                                            (
                                                $event[
                                                    'venue_id'
                                                ]
                                                ??
                                                'Not assigned'
                                            )
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="event-footer"
                                >

                                    <span
                                        class="status"
                                        style="color: #697386;"
                                    >
                                        ● Completed
                                    </span>


                                    <a
                                        href="ticket.php?event_id=<?= urlencode(
                                            $event[
                                                'event_id'
                                            ]
                                        ) ?>"
                                        class="details-btn"
                                    >
                                        VIEW TICKET
                                    </a>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </div>


    </main>



    <!-- ==================================================
         JAVASCRIPT
    ================================================== -->

    <script>

        function showTab(
            tabName,
            button
        ) {

            document
                .querySelectorAll(
                    ".event-section"
                )
                .forEach(
                    function(section) {

                        section.style.display =
                            "none";

                    }
                );


            document
                .getElementById(
                    tabName
                )
                .style.display =
                    "grid";


            document
                .querySelectorAll(
                    ".tab"
                )
                .forEach(
                    function(tab) {

                        tab.classList.remove(
                            "active"
                        );

                    }
                );


            button.classList.add(
                "active"
            );

        }

    </script>


</body>

</html>
```
