<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'CampusEvent360'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html, body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f5;
            color: #1a1a2e;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1 0 auto;
            position: relative;
            z-index: 1;
            padding-top: 90px;
            padding-bottom: 40px;
        }

        /* ===== PROFESSIONAL WIDE NAVBAR ===== */
        .navbar-enterprise {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0;
            transition: all 0.3s ease;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.04);
            height: 80px;
        }

        .navbar-enterprise.scrolled {
            background: rgba(255, 255, 255, 0.99);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            height: 70px;
        }

        .navbar-enterprise .container {
            max-width: 1440px;
            padding: 0 30px;
            height: 100%;
        }

        .navbar-enterprise .navbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            width: 100%;
            gap: 20px;
        }

        /* ===== BRAND ===== */
        .navbar-enterprise .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .navbar-enterprise .brand .brand-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
        }

        .navbar-enterprise .brand:hover .brand-icon {
            transform: scale(1.05) rotate(-5deg);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .navbar-enterprise .brand .brand-text {
            font-weight: 800;
            font-size: 1.5rem;
            color: #1a1a2e;
            letter-spacing: -0.5px;
        }

        .navbar-enterprise .brand .brand-text span {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .navbar-enterprise .brand .brand-sub {
            font-size: 0.6rem;
            color: #9ca3af;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: -2px;
        }

        /* ===== NAV LINKS ===== */
        .navbar-enterprise .nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            justify-content: center;
            padding: 0 20px;
        }

        .navbar-enterprise .nav-links a {
            color: #6b7280;
            text-decoration: none;
            padding: 10px 22px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
        }

        .navbar-enterprise .nav-links a i {
            margin-right: 8px;
            font-size: 0.9rem;
        }

        .navbar-enterprise .nav-links a:hover {
            color: #1a1a2e;
            background: rgba(102, 126, 234, 0.06);
        }

        .navbar-enterprise .nav-links a.active {
            color: #667eea;
            background: rgba(102, 126, 234, 0.08);
        }

        .navbar-enterprise .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 30px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
        }

        .navbar-enterprise .nav-links a .badge-dot {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 10px;
            height: 10px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.95);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }

        /* ===== USER PROFILE ===== */
        .navbar-enterprise .user-section {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 4px 18px 4px 4px;
            border-radius: 40px;
            background: rgba(102, 126, 234, 0.06);
            border: 1.5px solid rgba(102, 126, 234, 0.08);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            height: 52px;
        }

        .user-profile:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .user-profile .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .user-profile .user-info .name {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1a1a2e;
        }

        .user-profile .user-info .role {
            font-size: 0.65rem;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-profile .chevron {
            color: #9ca3af;
            font-size: 0.7rem;
            transition: all 0.3s;
        }

        .user-profile:hover .chevron {
            color: #667eea;
        }

        /* ===== AUTH BUTTONS ===== */
        .btn-auth-primary {
            padding: 10px 28px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-auth-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
            color: white;
        }

        .btn-auth-outline {
            padding: 10px 28px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #4b5563;
            border: 1.5px solid #e5e7eb;
            background: transparent;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-auth-outline:hover {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.04);
        }

        /* ===== DROPDOWN ===== */
        .dropdown-enterprise {
            background: rgba(255, 255, 255, 0.99);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 16px;
            padding: 8px;
            min-width: 240px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-top: 8px;
        }

        .dropdown-enterprise .dropdown-item {
            border-radius: 10px;
            padding: 10px 16px;
            color: #4b5563;
            font-weight: 500;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
        }

        .dropdown-enterprise .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.06);
            color: #667eea;
        }

        .dropdown-enterprise .dropdown-item i {
            width: 20px;
            color: #9ca3af;
        }

        .dropdown-enterprise .dropdown-item:hover i {
            color: #667eea;
        }

        .dropdown-enterprise .dropdown-divider {
            border-top: 1px solid rgba(0, 0, 0, 0.04);
            margin: 6px 0;
        }

        .dropdown-enterprise .dropdown-item.text-danger {
            color: #ef4444;
        }

        .dropdown-enterprise .dropdown-item.text-danger:hover {
            background: rgba(239, 68, 68, 0.06);
            color: #dc2626;
        }

        /* ===== MOBILE TOGGLE ===== */
        .nav-toggle {
            display: none;
            background: rgba(102, 126, 234, 0.06);
            border: none;
            color: #1a1a2e;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 1.4rem;
            cursor: pointer;
            transition: all 0.3s;
            height: 48px;
            width: 48px;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .nav-toggle:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .nav-toggle:focus {
            outline: none;
        }

        /* ===== MOBILE NAV ===== */
        .mobile-nav {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.99);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            padding: 16px 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            border-radius: 0 0 20px 20px;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }

        .mobile-nav.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .mobile-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #4b5563;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .mobile-nav a:hover {
            background: rgba(102, 126, 234, 0.06);
            color: #667eea;
        }

        .mobile-nav a i {
            width: 24px;
            text-align: center;
            color: #9ca3af;
        }

        .mobile-nav a:hover i {
            color: #667eea;
        }

        .mobile-nav .divider {
            border-top: 1px solid rgba(0, 0, 0, 0.04);
            margin: 8px 0;
        }

        .mobile-nav .mobile-user {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            background: rgba(102, 126, 234, 0.04);
            border-radius: 12px;
            margin-bottom: 8px;
        }

        .mobile-nav .mobile-user .m-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.9rem;
        }

        .mobile-nav .mobile-user .m-info .m-name {
            font-weight: 600;
            color: #1a1a2e;
        }

        .mobile-nav .mobile-user .m-info .m-role {
            font-size: 0.7rem;
            color: #9ca3af;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .navbar-enterprise .nav-links a { padding: 8px 16px; font-size: 0.85rem; }
            .navbar-enterprise .brand .brand-text { font-size: 1.3rem; }
        }

        @media (max-width: 992px) {
            .navbar-enterprise .nav-links { display: none; }
            .nav-toggle { display: flex !important; }
            .navbar-enterprise .brand .brand-sub { display: none; }
            .navbar-enterprise .brand .brand-text { font-size: 1.2rem; }
            .navbar-enterprise .brand .brand-icon { width: 42px; height: 42px; font-size: 1.2rem; }
            .navbar-enterprise { height: 72px; }
            .navbar-enterprise.scrolled { height: 64px; }
            .main-content { padding-top: 82px; }
            .user-profile .user-info { display: none; }
            .user-profile { padding: 4px 12px 4px 4px; height: 46px; }
            .user-profile .avatar { width: 38px; height: 38px; font-size: 0.8rem; }
            .btn-auth-primary, .btn-auth-outline { padding: 8px 18px; font-size: 0.8rem; }
        }

        @media (max-width: 576px) {
            .navbar-enterprise .container { padding: 0 16px; }
            .navbar-enterprise { height: 64px; }
            .navbar-enterprise.scrolled { height: 58px; }
            .main-content { padding-top: 74px; }
            .navbar-enterprise .brand .brand-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 10px; }
            .navbar-enterprise .brand .brand-text { font-size: 1rem; }
            .navbar-enterprise .brand { gap: 10px; }
            .user-profile { height: 40px; padding: 3px 10px 3px 3px; }
            .user-profile .avatar { width: 34px; height: 34px; font-size: 0.7rem; }
            .btn-auth-primary, .btn-auth-outline { padding: 6px 14px; font-size: 0.75rem; }
            .nav-toggle { height: 40px; width: 40px; font-size: 1.2rem; }
            .mobile-nav { padding: 12px 16px; max-height: calc(100vh - 64px); }
            .mobile-nav a { padding: 10px 14px; font-size: 0.9rem; }
        }

        @media (max-width: 400px) {
            .navbar-enterprise .brand .brand-text { font-size: 0.85rem; }
            .navbar-enterprise .brand .brand-icon { width: 32px; height: 32px; font-size: 0.8rem; border-radius: 8px; }
            .btn-auth-primary, .btn-auth-outline { padding: 5px 10px; font-size: 0.7rem; }
            .user-profile { height: 36px; }
            .user-profile .avatar { width: 30px; height: 30px; font-size: 0.6rem; }
        }
    </style>
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar-enterprise" id="mainNav">
        <div class="container">
            <div class="navbar-inner">
                <!-- Brand -->
                <a href="/" class="brand">
                    <span class="brand-icon"><i class="fas fa-calendar-alt"></i></span>
                    <div>
                        <div class="brand-text">Campus<span>Event</span>360</div>
                        <div class="brand-sub">Student Portal</div>
                    </div>
                </a>

                <!-- Nav Links -->
                <div class="nav-links">
                    <a href="/" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a href="/modules/public/events.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'events.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> Events
                    </a>
                    <?php if (function_exists('isAuthenticated') && isAuthenticated()): ?>
                        <?php if (function_exists('hasRole') && hasRole('student')): ?>
                        <a href="/modules/student/dashboard.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-th-large"></i> Dashboard
                            <?php 
                            if (isset($pdo) && isset($_SESSION['user_id'])) {
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND status = 'waitlisted'");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    if ($stmt->fetchColumn() > 0) {
                                        echo '<span class="badge-dot"></span>';
                                    }
                                } catch(Exception $e) {}
                            }
                            ?>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- User Section -->
                <div class="user-section">
                    <?php if (function_exists('isAuthenticated') && isAuthenticated()): ?>
                        <div class="dropdown">
                            <a href="#" class="user-profile" id="userDropdown" data-bs-toggle="dropdown">
                                <span class="avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 2)); ?></span>
                                <div class="user-info">
                                    <div class="name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                                    <div class="role"><?php echo $_SESSION['role'] ?? 'student'; ?></div>
                                </div>
                                <span class="chevron"><i class="fas fa-chevron-down"></i></span>
                            </a>
                            <ul class="dropdown-menu dropdown-enterprise dropdown-menu-end">
                                <li><a class="dropdown-item" href="/modules/student/dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                                <li><a class="dropdown-item" href="/modules/student/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                                <li><a class="dropdown-item" href="/modules/student/my-events.php"><i class="fas fa-ticket-alt"></i> My Events</a></li>
                                <li><a class="dropdown-item" href="/modules/student/certificates.php"><i class="fas fa-certificate"></i> Certificates</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/login.php" class="btn-auth-primary">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="/register.php" class="btn-auth-outline">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    <?php endif; ?>
                    
                    <button class="nav-toggle" onclick="toggleMobile()">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Nav -->
            <div class="mobile-nav" id="mobileNav">
                <?php if (function_exists('isAuthenticated') && isAuthenticated()): ?>
                    <div class="mobile-user">
                        <div class="m-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 2)); ?></div>
                        <div class="m-info">
                            <div class="m-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                            <div class="m-role"><?php echo $_SESSION['role'] ?? 'student'; ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <a href="/"><i class="fas fa-home"></i> Home</a>
                <a href="/modules/public/events.php"><i class="fas fa-calendar"></i> Events</a>
                <?php if (function_exists('isAuthenticated') && isAuthenticated()): ?>
                    <a href="/modules/student/dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
                    <a href="/modules/student/profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="/modules/student/my-events.php"><i class="fas fa-ticket-alt"></i> My Events</a>
                    <a href="/modules/student/certificates.php"><i class="fas fa-certificate"></i> Certificates</a>
                    <div class="divider"></div>
                    <a href="/logout.php" style="color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <div class="divider"></div>
                    <a href="/login.php" style="color:#667eea;"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="/register.php"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script>
        function toggleMobile() {
            document.getElementById('mobileNav').classList.toggle('active');
        }

        window.addEventListener('scroll', function() {
            const nav = document.getElementById('mainNav');
            if (window.scrollY > 20) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });

        document.querySelectorAll('#mobileNav a').forEach(link => {
            link.addEventListener('click', function() {
                document.getElementById('mobileNav').classList.remove('active');
            });
        });

        document.addEventListener('click', function(e) {
            const nav = document.getElementById('mobileNav');
            const toggle = document.querySelector('.nav-toggle');
            if (nav && toggle) {
                if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('active');
                }
            }
        });

        // Close dropdown on mobile when clicking outside
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.dropdown-menu');
            dropdowns.forEach(d => {
                if (!d.contains(e.target) && !d.previousElementSibling.contains(e.target)) {
                    // Bootstrap will handle this
                }
            });
        });
    </script>
    
    <div class="main-content">