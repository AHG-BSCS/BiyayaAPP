<?php
// Dashboard page
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in and is super administrator only
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "Super Admin") {
    header("Location: index.php");
    exit;
}
// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Always update session role from database
$_SESSION["user_role"] = $user_profile['role'];

// Check if user is super administrator
$is_super_admin = ($_SESSION["user_role"] === "Super Admin");

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize default session data for profile
if (!isset($_SESSION["user_email"])) {
    $_SESSION["user_email"] = "admin@example.com";
}

// Get total members count from database
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM membership_records");
    $row = $result->fetch_assoc();
    $total_members = $row['total'];

    // Get gender statistics
    $gender_stats = $conn->query("SELECT sex, COUNT(*) as count FROM membership_records GROUP BY sex");
    $male_count = 0;
    $female_count = 0;
    while ($row = $gender_stats->fetch_assoc()) {
        if ($row['sex'] === 'Male') {
            $male_count = $row['count'];
        } else if ($row['sex'] === 'Female') {
            $female_count = $row['count'];
        }
    }
} catch(Exception $e) {
    $total_members = 0;
    $male_count = 0;
    $female_count = 0;
}

// Get total events count from database
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM events");
    $row = $result->fetch_assoc();
    $total_events = $row['total'];
} catch(Exception $e) {
    $total_events = 0;
}

// Get total prayer requests count from database
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM prayer_requests");
    $row = $result->fetch_assoc();
    $total_prayers = $row['total'];
} catch(Exception $e) {
    $total_prayers = 0;
}

// Dashboard statistics
$dashboard_stats = [
    "total_members" => $total_members,
    "upcoming_events" => $total_events,
    "pending_prayers" => $total_prayers
];

// Add random Bible verse for the day (copied from member_dashboard.php)
$bible_verses = [
    [
        'ref' => 'Philippians 4:13',
        'text' => 'I can do all things through Christ who strengthens me.'
    ],
    [
        'ref' => 'Jeremiah 29:11',
        'text' => 'For I know the plans I have for you, declares the Lord, plans to prosper you and not to harm you, plans to give you hope and a future.'
    ],
    [
        'ref' => 'Psalm 23:1',
        'text' => 'The Lord is my shepherd; I shall not want.'
    ],
    [
        'ref' => 'Romans 8:28',
        'text' => 'And we know that in all things God works for the good of those who love him, who have been called according to his purpose.'
    ],
    [
        'ref' => 'Proverbs 3:5-6',
        'text' => 'Trust in the Lord with all your heart and lean not on your own understanding; in all your ways submit to him, and he will make your paths straight.'
    ],
    [
        'ref' => 'Isaiah 41:10',
        'text' => 'So do not fear, for I am with you; do not be dismayed, for I am your God. I will strengthen you and help you; I will uphold you with my righteous right hand.'
    ],
];
$verse_index = intval(date('z')) % count($bible_verses);
$verse_of_the_day = $bible_verses[$verse_index];

// Fetch tithes and offerings data for line graph (last 12 months)
$tithes_offerings_data = [];
$sql = "
    SELECT 
        DATE_FORMAT(entry_date, '%Y-%m') as month,
        DATE_FORMAT(entry_date, '%Y-%m-01') as month_start,
        SUM(tithes) as total_tithes,
        SUM(offerings) as total_offerings
    FROM breakdown_income
    WHERE (tithes > 0 OR offerings > 0)
        AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
    ORDER BY month ASC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tithes_offerings_data[] = [
            'month' => $row['month'],
            'month_start' => $row['month_start'],
            'tithes' => floatval($row['total_tithes']),
            'offerings' => floatval($row['total_offerings'])
        ];
    }
}

// Prepare data for chart
$chart_dates = [];
$chart_tithes = [];
$chart_offerings = [];
foreach ($tithes_offerings_data as $data) {
    $chart_dates[] = date('F Y', strtotime($data['month_start']));
    $chart_tithes[] = $data['tithes'];
    $chart_offerings[] = $data['offerings'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <style>
        :root {
            --primary-color: #3a3a3a;
            --accent-color: rgb(0, 139, 30);
            --light-gray: #d0d0d0;
            --white: #ffffff;
            --sidebar-width: 250px;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: var(--primary-color);
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            padding: 20px;
            margin-left: 0;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--white);
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .top-bar h2 {
            font-size: 24px;
        }

        .dashboard-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .summary-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
                position: relative;
            border: 1px solid #f0f0f0;
                display: flex;
            align-items: center;
            margin-bottom: 0;
            min-width: 0;
                width: 100%;
            max-width: none;
        }
        .summary-card:hover {
            transform: translateY(-10px);
        }
        .summary-card.full-width {
            grid-column: 1 / -1;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .summary-card .card-icon {
            background: #fff;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        .summary-card .card-content {
            flex: 1;
            text-align: left;
        }
        .summary-card h3 {
            font-size: 16px;
            color: #666;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .card-number {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
            line-height: 1;
        }
        .summary-card .card-subtitle {
            font-size: 13px;
            color: #888;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .card-decoration {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
            border-radius: 0 0 16px 16px;
        }
        .summary-card:hover .card-decoration {
            transform: translateX(100%);
        }

        /* Individual Card Themes */
        .members-card {
            background: var(--white);
            border-left: 4px solid #ffffff;
        }

        .members-card .card-icon {
            background: #ffffff;
        }

        .events-card {
            background: var(--white);
            border-left: 4px solid #ffffff;
        }

        .events-card .card-icon {
            background: #ffffff;
        }

        .prayers-card {
            background: var(--white);
            border-left: 4px solid #ffffff;
        }

        .prayers-card .card-icon {
            background: #ffffff;
        }

        /* Gender Card Styling */
        .gender-card {
            background: var(--white);
            border-left: 4px solid #ffffff;
        }

        .gender-card .card-icon {
            background: #ffffff;
        }

        /* Gender Stats Styling */
        .gender-stats {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin: 15px 0;
        }

        .gender-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            flex: 1;
        }

        .gender-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .gender-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            transition: all 0.3s ease;
        }

        .gender-item.male .gender-icon {
            background: #ffffff;
        }

        .gender-item.female .gender-icon {
            background: #ffffff;
        }

        .gender-item:hover .gender-icon {
            transform: scale(1.05);
        }

        .gender-info {
            flex: 1;
        }

        .gender-number {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            line-height: 1;
            margin-bottom: 2px;
        }

        .gender-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Gender Chart Container */
        .gender-card .chart-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 15px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Animation for gender items */
        .gender-item {
            animation: slideInUp 0.6s ease-out;
        }

        .gender-item:nth-child(1) {
            animation-delay: 0.1s;
        }

        .gender-item:nth-child(2) {
            animation-delay: 0.2s;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Pulse animation for gender icons */
        .gender-icon {
            animation: genderPulse 3s infinite;
        }

        @keyframes genderPulse {
            0% {
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
            }
            50% {
                box-shadow: 0 6px 20px rgba(255, 255, 255, 0.5);
            }
            100% {
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
            }
        }

        /* Animation for card numbers */
        .card-number {
            animation: countUp 2s ease-out;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Pulse animation for icons */
        .card-icon {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 8px 25px rgba(255, 255, 255, 0.4);
            }
            50% {
                box-shadow: 0 8px 35px rgba(255, 255, 255, 0.6);
            }
            100% {
                box-shadow: 0 8px 25px rgba(255, 255, 255, 0.4);
            }
        }
        .prediction-chart {
            height: 300px;
            margin-bottom: 20px;
            position: relative;
        }
        .prediction-chart canvas {
            width: 100% !important;
            height: 100% !important;
        }
        
        .chart-container {
            height: 300px;
            margin-bottom: 20px;
            position: relative;
        }
        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }
        .prediction-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .prediction-metric {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .prediction-metric:last-child {
            margin-bottom: 0;
        }
        .prediction-metric .label {
            color: #666;
        }
        .prediction-metric .value {
            font-weight: bold;
            color: var(--accent-color);
        }
        .prediction-metric .value.positive {
            color: #28a745;
        }
        .prediction-metric .value.negative {
            color: #dc3545;
        }

        /* Custom Drawer Navigation Styles */
        .nav-toggle-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 50;
        }

        .nav-toggle-btn {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-toggle-btn:hover {
            background-color: #2563eb;
        }

        .custom-drawer {
            position: fixed;
            top: 0;
            left: -300px;
            width: 300px;
            height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ef 100%);
            color: #3a3a3a;
            z-index: 1000;
            transition: left 0.3s ease;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .custom-drawer.open {
            left: 0;
        }

        .drawer-header {
            padding: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            min-height: 120px;
        }

        .drawer-logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            min-height: 100px;
            justify-content: center;
            flex: 1;
        }

        .drawer-logo {
            height: 60px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .drawer-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            text-align: center;
            color: #3a3a3a;
            max-width: 200px;
            word-wrap: break-word;
            line-height: 1.2;
            min-height: 20px;
        }

        .drawer-close {
            background: none;
            border: none;
            color: #3a3a3a;
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
        }

        .drawer-close:hover {
            color: #666;
        }

        .drawer-content {
            padding: 20px 0 0 0;
            flex: 1;
        }

        .drawer-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .drawer-menu li {
            margin: 0;
        }

        .drawer-link {
            display: flex;
            align-items: center;
            padding: 12px 18px; /* reduced padding */
            color: #3a3a3a;
            text-decoration: none;
            font-size: 15px; /* reduced font size */
            font-weight: 500;
            gap: 10px; /* reduced gap */
            border-left: 4px solid transparent;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
            position: relative;
        }
        .drawer-link i {
            font-size: 18px; /* reduced icon size */
            min-width: 22px;
            text-align: center;
        }

        .drawer-link.active {
            background: linear-gradient(90deg, #e0ffe7 0%, #f5f5f5 100%);
            border-left: 4px solid var(--accent-color);
            color: var(--accent-color);
        }

        .drawer-link.active i {
            color: var(--accent-color);
        }

        .drawer-link:hover {
            background: rgba(0, 139, 30, 0.07);
            color: var(--accent-color);
        }

        .drawer-link:hover i {
            color: var(--accent-color);
        }

        .drawer-profile {
            padding: 24px 20px 20px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255,255,255,0.85);
        }
        .drawer-profile .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--accent-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: bold;
            overflow: hidden;
        }
        .drawer-profile .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .drawer-profile .profile-info {
            flex: 1;
        }
        .drawer-profile .name {
            font-size: 16px;
            font-weight: 600;
            color: #222;
        }
        .drawer-profile .role {
            font-size: 13px;
            color: var(--accent-color);
            font-weight: 500;
            margin-top: 2px;
        }
        .drawer-profile .logout-btn {
            background: #f44336;
            color: #fff;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .drawer-profile .logout-btn:hover {
            background: #d32f2f;
        }

        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .drawer-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        /* Ensure content doesn't overlap with the button */
        .content-area {
            padding-top: 80px;
        }
        /* --- ULTRA-COMPACT SUMMARY CARDS --- */
        .simple-summary {
            display: flex;
            gap: 4px;
            margin-bottom: 8px;
        }
        .simple-card {
            background: #fff;
            border-radius: 16px;
            padding: 4px 0 2px 0;
            box-shadow: none;
            text-align: center;
            border: 1px solid #eee;
            min-width: 80px;
            min-height: unset;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .simple-card-title {
            font-size: 10px;
            color: #444;
            margin-bottom: 0;
            font-weight: 500;
            line-height: 1.1;
        }
        .simple-card-value {
            font-size: 0.95rem;
            font-weight: bold;
            color: #008b1e;
            line-height: 1.1;
        }
        .simple-gender {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-top: 0;
        }
        .simple-gender-item {
            text-align: center;
        }
        .simple-gender-value {
            font-size: 0.95rem;
            font-weight: bold;
            color: #444;
            line-height: 1.1;
            padding: 0 2px;
        }
        .summary-card .card-icon i,
        .gender-icon i {
            color: #008b1e !important;
            font-size: 24px;
        }
        /* Remove per-card border-left color and background overrides */
        .members-card,
        .events-card,
        .prayers-card,
        .gender-card {
            border-left: none !important;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Toggle Button -->
        <div class="nav-toggle-container">
           <button class="nav-toggle-btn" type="button" id="nav-toggle">
           <i class="fas fa-bars"></i> Menu
           </button>
        </div>

        <!-- Custom Drawer Navigation -->
        <div id="drawer-navigation" class="custom-drawer">
            <div class="drawer-header">
                <div class="drawer-logo-section">
                    <img src="<?php echo htmlspecialchars($church_logo); ?>" alt="Church Logo" class="drawer-logo">
                    <h5 class="drawer-title"><?php echo $church_name; ?></h5>
                </div>
                <button type="button" class="drawer-close" id="drawer-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="drawer-content">
                <ul class="drawer-menu">
                    <li>
                        <a href="superadmin_dashboard.php" class="drawer-link <?php echo $current_page == 'superadmin_dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="events.php" class="drawer-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </li>
                    <li>
                        <a href="prayers.php" class="drawer-link <?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hands-praying"></i>
                            <span>Prayer Requests</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="drawer-link <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>">
                            <i class="fas fa-video"></i>
                            <span>Messages</span>
                        </a>
                    </li>
                    <li>
                        <a href="member_records.php" class="drawer-link <?php echo $current_page == 'member_records.php' ? 'active' : ''; ?>">
                            <i class="fas fa-address-book"></i>
                            <span>Member Records</span>
                        </a>
                    </li>
                    <li>
                        <a href="superadmin_financialreport.php" class="drawer-link <?php echo $current_page == 'superadmin_financialreport.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Financial Reports</span>
                        </a>
                    </li>
                    <?php if ($is_super_admin): ?>
                    <li>
                        <a href="superadmin_contribution.php" class="drawer-link <?php echo $current_page == 'superadmin_contribution.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hand-holding-dollar"></i>
                            <span>Stewardship Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php" class="drawer-link <?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
                            <i class="fas fa-boxes"></i>
                            <span>Inventory</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="settings.php" class="drawer-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <?php if ($is_super_admin): ?>
                    <li>
                        <a href="login_logs.php" class="drawer-link <?php echo $current_page == 'login_logs.php' ? 'active' : ''; ?>">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login Logs</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="drawer-profile">
                <div class="avatar">
                    <?php if (!empty($user_profile['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_profile['full_name'] ?? $user_profile['username'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <div class="name"><?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username'] ?? 'Unknown User'); ?></div>
                    <div class="role"><?php echo htmlspecialchars($user_profile['role'] ?? 'Super Admin'); ?></div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        
        <!-- Drawer Overlay -->
        <div id="drawer-overlay" class="drawer-overlay"></div>
        
        <main class="content-area">
            <div class="top-bar">
                <div>
                    <h2>Super Admin Dashboard</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>
            
            <div class="dashboard-content">
                <div class="summary-card members-card">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Members</h3>
                        <div class="card-number"><?php echo $dashboard_stats["total_members"]; ?></div>
                        <div class="card-subtitle">Active Congregation</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card events-card">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3>Upcoming Events</h3>
                        <div class="card-number"><?php echo $dashboard_stats["upcoming_events"]; ?></div>
                        <div class="card-subtitle">Scheduled Activities</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card prayers-card">
                    <div class="card-icon">
                        <i class="fas fa-hands-praying"></i>
                    </div>
                    <div class="card-content">
                        <h3>Prayer Requests</h3>
                        <div class="card-number"><?php echo $dashboard_stats["pending_prayers"]; ?></div>
                        <div class="card-subtitle">Needs Prayer</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card gender-card">
                    <div class="card-icon">
                        <i class="fas fa-venus-mars"></i>
                    </div>
                    <div class="card-content">
                        <h3>Gender Distribution</h3>
                        <div class="gender-stats">
                            <div class="gender-item male">
                                <div class="gender-icon">
                                    <i class="fas fa-mars"></i>
                                </div>
                                <div class="gender-info">
                                    <div class="gender-number"><?php echo $male_count; ?></div>
                                    <div class="gender-label">Male</div>
                                </div>
                            </div>
                            <div class="gender-item female">
                                <div class="gender-icon">
                                    <i class="fas fa-venus"></i>
                                </div>
                                <div class="gender-info">
                                    <div class="gender-number"><?php echo $female_count; ?></div>
                                    <div class="gender-label">Female</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-subtitle">Congregational Split</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card full-width">
                    <div class="card-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="card-content">
                        <h3>Bible Verse of the Day</h3>
                        <p style="font-size:16px; color:#333; font-style:italic; margin-bottom:8px;">"<?php echo $verse_of_the_day['text']; ?>"</p>
                        <p style="font-size:14px; color:#008b1e; text-align:right; margin:0;"><b><?php echo $verse_of_the_day['ref']; ?></b></p>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card full-width">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-content" style="width: 100%;">
                        <h3>Tithes and Offerings Trend (Last 12 Months)</h3>
                        <div style="position: relative; height: 300px; margin-top: 20px;">
                            <canvas id="tithesOfferingsChart"></canvas>
                        </div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Tithes and Offerings Line Chart
        const tithesOfferingsCtx = document.getElementById('tithesOfferingsChart');
        if (tithesOfferingsCtx) {
            const chartDates = <?php echo json_encode($chart_dates); ?>;
            const chartTithes = <?php echo json_encode($chart_tithes); ?>;
            const chartOfferings = <?php echo json_encode($chart_offerings); ?>;
            
            new Chart(tithesOfferingsCtx, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [
                        {
                            label: 'Tithes',
                            data: chartTithes,
                            borderColor: 'rgba(0, 139, 30, 1)',
                            backgroundColor: 'rgba(0, 139, 30, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: 'rgba(0, 139, 30, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Offerings',
                            data: chartOfferings,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                                },
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

        // Custom Drawer Navigation JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.getElementById('nav-toggle');
            const drawer = document.getElementById('drawer-navigation');
            const drawerClose = document.getElementById('drawer-close');
            const overlay = document.getElementById('drawer-overlay');

            // Open drawer
            navToggle.addEventListener('click', function() {
                drawer.classList.add('open');
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            });

            // Close drawer
            function closeDrawer() {
                drawer.classList.remove('open');
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }

            drawerClose.addEventListener('click', closeDrawer);
            overlay.addEventListener('click', closeDrawer);

            // Close drawer on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDrawer();
                }
            });
        });

        // Automatic logout on inactivity
        let inactivityTimeout;
        let logoutWarningShown = false;

        function resetInactivityTimer() {
            clearTimeout(inactivityTimeout);
            if (logoutWarningShown) {
                const warning = document.getElementById('logout-warning');
                if (warning) warning.remove();
                logoutWarningShown = false;
            }
            inactivityTimeout = setTimeout(() => {
                console.log('Inactivity detected: showing warning and logging out soon.');
                showLogoutWarning();
                setTimeout(() => {
                    window.location.href = 'logout.php';
                }, 2000);
            }, 60000); // 1 minute
        }

        function showLogoutWarning() {
            if (!logoutWarningShown) {
                const warning = document.createElement('div');
                warning.id = 'logout-warning';
                warning.style.position = 'fixed';
                warning.style.top = '30px';
                warning.style.right = '30px';
                warning.style.background = '#f44336';
                warning.style.color = 'white';
                warning.style.padding = '20px 30px';
                warning.style.borderRadius = '8px';
                warning.style.fontSize = '18px';
                warning.style.zIndex = '9999';
                warning.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
                warning.innerHTML = '<i class="fas fa-lock"></i> Logging out due to inactivity...';
                document.body.appendChild(warning);
                logoutWarningShown = true;
            }
        }

        // Reset timer on user activity
        ['mousemove', 'keydown', 'mousedown', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, resetInactivityTimer, true);
        });

        // Initialize timer
        resetInactivityTimer();
    </script>
</body>
</html> 