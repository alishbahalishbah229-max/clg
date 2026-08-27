<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| ADD PROGRAM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_program'])
) {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $status = $_POST['status'] ?? 'active';

    try {

        if ($title === '') {
            throw new Exception('Please enter program name.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $stmt = $pdo->prepare("
            INSERT INTO programs
            (
                program_id,
                title,
                description,
                image,
                status
            )
            VALUES
            (
                UUID(),
                :title,
                :description,
                :image,
                :status
            )
        ");

        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':image' => $image !== '' ? $image : null,
            ':status' => $status
        ]);

        $message = 'Program added successfully.';

    } catch (PDOException $e) {

        error_log('Admin Program Insert Error: ' . $e->getMessage());

        $error = 'Unable to add program.';

    } catch (Exception $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PROGRAM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_program'])
) {

    $programId = trim($_POST['program_id'] ?? '');

    if ($programId !== '') {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM programs
                WHERE program_id = :program_id
            ");

            $stmt->execute([
                ':program_id' => $programId
            ]);

            $message = 'Program deleted successfully.';

        } catch (PDOException $e) {

            error_log('Admin Program Delete Error: ' . $e->getMessage());

            $error = 'Unable to delete program.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| TOGGLE PROGRAM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['toggle_program'])
) {

    $programId = trim($_POST['program_id'] ?? '');

    if ($programId !== '') {

        try {

            $stmt = $pdo->prepare("
                UPDATE programs
                SET status =
                    CASE
                        WHEN status = 'active'
                        THEN 'inactive'
                        ELSE 'active'
                    END
                WHERE program_id = :program_id
            ");

            $stmt->execute([
                ':program_id' => $programId
            ]);

            $message = 'Program status updated.';

        } catch (PDOException $e) {

            error_log('Admin Program Status Error: ' . $e->getMessage());

            $error = 'Unable to update program.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD PROGRAMS
|--------------------------------------------------------------------------
*/

$programs = [];

try {

    $stmt = $pdo->query("
        SELECT
            program_id,
            title,
            description,
            image,
            status,
            created_at
        FROM programs
        ORDER BY created_at DESC
    ");

    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Admin Programs Load Error: ' . $e->getMessage());

    $error = 'Unable to load programs.';
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
    Manage Programs | EventSphere
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

    --shadow:
        0 18px 50px
        rgba(7,26,54,.07);
}


* {
    box-sizing:border-box;
    margin:0;
    padding:0;
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
    color:inherit;
    text-decoration:none;
}


button,
input,
textarea,
select {
    font-family:inherit;
}


/* ==================================================
   SIDEBAR
================================================== */

.sidebar {

    position:fixed;

    top:0;
    left:0;

    width:255px;
    height:100vh;

    padding:24px 16px;

    background:
        var(--navy);

    color:white;

    z-index:100;
}


.brand {

    display:flex;

    align-items:center;

    gap:12px;

    padding:
        4px 12px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.1);
}


.crest {

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


.brand-text strong {

    display:block;

    font-family:
        "Playfair Display",
        serif;

    font-size:17px;

    letter-spacing:1px;
}


.brand-text small {

    display:block;

    margin-top:2px;

    color:var(--gold-light);

    font-size:7px;

    letter-spacing:2px;
}


.nav-section {
    margin-top:30px;
}


.nav-title {

    padding:
        0 12px 10px;

    color:#718198;

    font-size:9px;

    font-weight:700;

    letter-spacing:1.7px;

    text-transform:uppercase;
}


.nav-link {

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


.nav-link:hover {

    background:
        rgba(255,255,255,.07);

    color:white;
}


.nav-link.active {

    background:
        rgba(255,255,255,.09);

    color:white;

    border-left:
        3px solid
        var(--gold);

    padding-left:9px;
}


.nav-icon {

    width:25px;
    height:25px;

    display:grid;

    place-items:center;

    font-size:13px;
}


/* ==================================================
   MAIN
================================================== */

.main {

    min-height:100vh;

    margin-left:255px;
}


/* ==================================================
   TOPBAR
================================================== */

.topbar {

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


.topbar-left {

    display:flex;

    flex-direction:column;
}


.topbar-label {

    color:
        var(--gold);

    font-size:9px;

    font-weight:700;

    letter-spacing:1.7px;

    text-transform:uppercase;
}


.page-title {

    color:
        var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:25px;
}


.admin-area {

    display:flex;

    align-items:center;

    gap:12px;
}


.admin-details {
    text-align:right;
}


.admin-details strong {

    display:block;

    font-size:12px;
}


.admin-details span {

    display:block;

    color:var(--muted);

    font-size:9px;
}


.avatar {

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


/* ==================================================
   CONTENT
================================================== */

.content {

    max-width:1250px;

    margin:0 auto;

    padding:
        38px 40px 50px;
}


/* ==================================================
   PAGE HEADER
================================================== */

.page-heading {

    display:flex;

    align-items:flex-end;

    justify-content:space-between;

    gap:20px;

    margin-bottom:25px;
}


.page-heading h1 {

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:31px;
}


.page-heading p {

    margin-top:5px;

    color:var(--muted);

    font-size:10px;
}


.back-link {

    color:var(--muted);

    font-size:9px;

    font-weight:700;
}


.back-link:hover {
    color:var(--gold);
}


/* ==================================================
   ALERTS
================================================== */

.alert {

    padding:13px 16px;

    margin-bottom:20px;

    border-radius:8px;

    font-size:10px;

    font-weight:600;
}


.alert.success {

    background:
        var(--green-bg);

    color:
        var(--green);

    border:
        1px solid
        #ccebd8;
}


.alert.error {

    background:
        var(--red-bg);

    color:
        var(--red);

    border:
        1px solid
        #efcccc;
}


/* ==================================================
   GRID
================================================== */

.management-grid {

    display:grid;

    grid-template-columns:
        .72fr
        1.28fr;

    gap:22px;

    align-items:start;
}


/* ==================================================
   CARD
================================================== */

.card {

    background:white;

    border:
        1px solid
        var(--line);

    border-radius:11px;

    box-shadow:
        var(--shadow);

    overflow:hidden;
}


.card-header {

    padding:
        20px 22px;

    border-bottom:
        1px solid
        var(--line);
}


.card-header h2 {

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:19px;
}


.card-header p {

    margin-top:4px;

    color:var(--muted);

    font-size:9px;
}


.card-body {

    padding:22px;
}


/* ==================================================
   FORM
================================================== */

.field {
    margin-bottom:16px;
}


.field label {

    display:block;

    margin-bottom:7px;

    color:var(--navy);

    font-size:9px;

    font-weight:700;

    letter-spacing:.3px;
}


.field input,
.field textarea,
.field select {

    width:100%;

    padding:
        11px 12px;

    border:
        1px solid
        #dfe4eb;

    border-radius:6px;

    outline:none;

    background:#fff;

    color:var(--ink);

    font-size:10px;

    transition:.2s;
}


.field input:focus,
.field textarea:focus,
.field select:focus {

    border-color:
        var(--gold);

    box-shadow:
        0 0 0 3px
        rgba(201,154,62,.1);
}


.field textarea {

    min-height:110px;

    resize:vertical;

    line-height:1.6;
}


.submit-btn {

    width:100%;

    padding:12px;

    border:none;

    border-radius:6px;

    background:
        var(--navy);

    color:white;

    cursor:pointer;

    font-size:9px;

    font-weight:700;

    letter-spacing:.8px;

    transition:.2s;
}


.submit-btn:hover {

    background:
        var(--blue);
}


/* ==================================================
   PROGRAM LIST
================================================== */

.program-list {
    padding:0 22px;
}


.program {

    display:grid;

    grid-template-columns:
        90px
        1fr
        auto;

    gap:18px;

    align-items:center;

    padding:
        18px 0;

    border-bottom:
        1px solid
        #edf0f3;
}


.program:last-child {
    border-bottom:none;
}


.program-image {

    width:90px;
    height:70px;

    border-radius:8px;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--blue)
        )
        center/cover
        no-repeat;

    overflow:hidden;
}


.program-info h3 {

    color:var(--navy);

    font-family:
        "Playfair Display",
        serif;

    font-size:16px;
}


.program-info p {

    margin-top:5px;

    color:var(--muted);

    font-size:9px;

    line-height:1.55;

    display:-webkit-box;

    -webkit-line-clamp:2;

    -webkit-box-orient:vertical;

    overflow:hidden;
}


.program-meta {

    margin-top:8px;

    font-size:8px;

    color:var(--muted);
}


.status {

    display:inline-flex;

    align-items:center;

    gap:5px;

    margin-left:4px;

    padding:
        4px 7px;

    border-radius:20px;

    font-size:7px;

    font-weight:700;
}


.status.active {

    background:
        var(--green-bg);

    color:
        var(--green);
}


.status.inactive {

    background:
        var(--red-bg);

    color:
        var(--red);
}


/* ==================================================
   ACTIONS
================================================== */

.actions {

    display:flex;

    flex-direction:column;

    gap:7px;
}


.action-btn {

    min-width:68px;

    padding:
        8px 10px;

    border:none;

    border-radius:5px;

    cursor:pointer;

    font-size:7px;

    font-weight:700;

    letter-spacing:.4px;
}


.show-hide {

    background:
        #fff8e9;

    color:
        #9a711d;
}


.delete {

    background:
        var(--red-bg);

    color:
        var(--red);
}


.empty {

    padding:
        40px 10px;

    text-align:center;

    color:var(--muted);

    font-size:10px;
}


/* ==================================================
   RESPONSIVE
================================================== */

@media(max-width:1050px) {

    .management-grid {

        grid-template-columns:1fr;
    }

}


@media(max-width:800px) {

    .sidebar {

        width:72px;

        padding:
            20px 8px;
    }

    .brand {

        justify-content:center;
    }

    .brand-text,
    .nav-title {

        display:none;
    }

    .nav-link {

        justify-content:center;
    }

    .nav-link span:last-child {

        display:none;
    }

    .main {

        margin-left:72px;
    }

    .content {

        padding:
            30px 24px 40px;
    }

}


@media(max-width:600px) {

    .topbar {

        height:68px;

        padding:
            0 18px;
    }

    .topbar-label,
    .admin-details {

        display:none;
    }

    .page-title {

        font-size:21px;
    }

    .content {

        padding:
            25px 17px 35px;
    }

    .page-heading {

        align-items:flex-start;

        flex-direction:column;
    }

    .program {

        grid-template-columns:
            70px 1fr;
    }

    .program-image {

        width:70px;
        height:60px;
    }

    .actions {

        grid-column:1 / -1;

        flex-direction:row;
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
        href="dashboard.php"
        class="brand"
    >

        <div class="crest">
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
            Administration
        </div>


        <a
            href="dashboard.php"
            class="nav-link"
        >
            <span class="nav-icon">▦</span>
            <span>Dashboard</span>
        </a>


        <a
            href="events.php"
            class="nav-link"
        >
            <span class="nav-icon">◈</span>
            <span>Events</span>
        </a>


        <a
            href="programs.php"
            class="nav-link active"
        >
            <span class="nav-icon">▤</span>
            <span>Programs</span>
        </a>


        <a
            href="users.php"
            class="nav-link"
        >
            <span class="nav-icon">♙</span>
            <span>Users</span>
        </a>


        <a
            href="registrations.php"
            class="nav-link"
        >
            <span class="nav-icon">▣</span>
            <span>Registrations</span>
        </a>


        <a
            href="contact-messages.php"
            class="nav-link"
        >
            <span class="nav-icon">✉</span>
            <span>Messages</span>
        </a>


        <a
            href="../../logout.php"
            class="nav-link"
        >
            <span class="nav-icon">↪</span>
            <span>Logout</span>
        </a>

    </nav>

</aside>


<!-- ==================================================
     MAIN
================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="topbar-left">

            <span class="topbar-label">
                Administration
            </span>

            <div class="page-title">
                Program Management
            </div>

        </div>


        <div class="admin-area">

            <div class="admin-details">

                <strong>
                    Administrator
                </strong>

                <span>
                    Admin Portal
                </span>

            </div>

            <div class="avatar">
                A
            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <div class="page-heading">

            <div>

                <h1>
                    Academic Programs
                </h1>

                <p>
                    Add, hide, show and remove programs displayed on the homepage.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="back-link"
            >
                ← BACK TO DASHBOARD
            </a>

        </div>


        <?php if ($message !== ''): ?>

            <div class="alert success">
                <?= sanitize($message); ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="alert error">
                <?= sanitize($error); ?>
            </div>

        <?php endif; ?>


        <div class="management-grid">


            <!-- ADD -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Add New Program
                    </h2>

                    <p>
                        Create a program for the homepage.
                    </p>

                </div>


                <div class="card-body">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="add_program"
                            value="1"
                        >


                        <div class="field">

                            <label>
                                PROGRAM NAME
                            </label>

                            <input
                                type="text"
                                name="title"
                                placeholder="e.g. Computer Science"
                                maxlength="150"
                                required
                            >

                        </div>


                        <div class="field">

                            <label>
                                DESCRIPTION
                            </label>

                            <textarea
                                name="description"
                                placeholder="Write a short description..."
                            ></textarea>

                        </div>


                        <div class="field">

                            <label>
                                IMAGE URL
                            </label>

                            <input
                                type="url"
                                name="image"
                                placeholder="https://example.com/image.jpg"
                            >

                        </div>


                        <div class="field">

                            <label>
                                STATUS
                            </label>

                            <select name="status">

                                <option value="active">
                                    Active — Show on homepage
                                </option>

                                <option value="inactive">
                                    Inactive — Hide from homepage
                                </option>

                            </select>

                        </div>


                        <button
                            type="submit"
                            class="submit-btn"
                        >
                            + ADD PROGRAM
                        </button>

                    </form>

                </div>

            </div>


            <!-- EXISTING -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Existing Programs
                    </h2>

                    <p>
                        Programs currently stored in the database.
                    </p>

                </div>


                <div class="program-list">


                    <?php if (!empty($programs)): ?>


                        <?php foreach ($programs as $program): ?>


                            <div class="program">


                                <div
                                    class="program-image"
                                    <?php if (!empty($program['image'])): ?>
                                        style="
                                            background-image:url(
                                                '<?= htmlspecialchars(
                                                    $program['image'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>'
                                            );
                                        "
                                    <?php endif; ?>
                                ></div>


                                <div class="program-info">

                                    <h3>
                                        <?= sanitize(
                                            $program['title']
                                        ); ?>
                                    </h3>


                                    <p>

                                        <?= sanitize(
                                            $program['description'] ?? ''
                                        ); ?>

                                    </p>


                                    <div class="program-meta">

                                        Status:

                                        <?php if (
                                            $program['status'] === 'active'
                                        ): ?>

                                            <span class="status active">
                                                ● Active
                                            </span>

                                        <?php else: ?>

                                            <span class="status inactive">
                                                ● Inactive
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="actions">


                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="program_id"
                                            value="<?= htmlspecialchars(
                                                $program['program_id'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="toggle_program"
                                            class="action-btn show-hide"
                                        >

                                            <?= $program['status'] === 'active'
                                                ? 'HIDE'
                                                : 'SHOW'; ?>

                                        </button>

                                    </form>


                                    <form
                                        method="POST"
                                        onsubmit="
                                            return confirm(
                                                'Delete this program?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="program_id"
                                            value="<?= htmlspecialchars(
                                                $program['program_id'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_program"
                                            class="action-btn delete"
                                        >
                                            DELETE
                                        </button>

                                    </form>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="empty">

                            No programs found.

                            <br>

                            Add your first academic program
                            using the form.

                        </div>


                    <?php endif; ?>


                </div>

            </div>


        </div>

    </section>

</main>

</body>

</html>