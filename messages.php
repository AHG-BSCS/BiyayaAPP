<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: index.php");
    exit;
}

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Always update session role from database
$_SESSION["user_role"] = $user_profile['role'];

// Check if user is super administrator
$is_super_admin = ($_SESSION["user_role"] === "Super Admin");

// Restrict access to Super Administrator only
if (!$is_super_admin) {
    header("Location: index.php");
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch messages from database
$messages = [];
$sql = "SELECT * FROM messages ORDER BY date DESC";
$result = $conn->query($sql);

if (!$result) {
    // Log the database error
    error_log("Database query failed: " . $conn->error);
    echo '<div style="padding: 30px; text-align: center; color: #888;">Error loading messages. Please try again later.</div>';
    exit;
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $outline = json_decode($row['outline'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON decode fails, use empty array
            $outline = [];
        }
        
        $messages[] = [
            "id" => $row['id'],
            "title" => $row['title'],
            "youtube_id" => $row['youtube_id'],
            "date" => $row['date'],
            "outline" => $outline
        ];
    }
}

// If no messages in database, show a message to the user
if (empty($messages)) {
    echo '<div style="padding: 30px; text-align: center; color: #888;">No messages available. Please add a new message.</div>';
    exit;
}

// Ensure we have a valid message index
$current_message = isset($_GET['message']) ? (int)$_GET['message'] : 0;
if ($current_message < 0 || $current_message >= count($messages)) {
    $current_message = 0;
}

// Get the current message
$message = $messages[$current_message];

// Verify message data is valid
if (!isset($message['title']) || !isset($message['youtube_id']) || !isset($message['outline'])) {
    echo '<div style="padding: 30px; text-align: center; color: #888;">Error: Invalid message data. Please contact the administrator.</div>';
    exit;
}

// Get church logo
$church_logo = getChurchLogo($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Main Content Area */
        .content-area {
            flex: 1;
            margin-left: 0; /* No sidebar */
            padding: 20px;
            padding-top: 80px; /* Ensure content doesn't overlap with the menu button */
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

        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-container input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-container input[type="text"]:invalid:not(:placeholder-shown) {
            border-color: #dc3545;
        }

        .search-container input[type="text"]::placeholder {
            color: #6c757d;
        }

        .search-container button {
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            white-space: nowrap;
            font-size: 14px;
            height: 40px;
        }

        .search-container button:hover {
            background-color: rgb(0, 112, 9);
        }

        .manage-messages-btn {
            background-color: #2196f3;
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
            height: 40px;
            white-space: nowrap;
        }

        .manage-messages-btn:hover {
            background-color: #1976d2;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-profile .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            overflow: hidden;
        }

        .user-profile .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            margin-right: 15px;
        }

        .user-info h4 {
            font-size: 14px;
            margin: 0;
        }

        .user-info p {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        .logout-btn {
            background-color: #f0f0f0;
            color: var(--primary-color);
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            height: 35px;
        }

        .logout-btn:hover {
            background-color: #e0e0e0;
        }

        .video-container {
            margin: 20px 0;
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .video-wrapper {
            position: relative;
            padding-bottom: 45%;
            height: 50%;
            overflow: hidden;
            border-radius: 5px;
        }

        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .message-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .navigation-buttons button {
            padding: 8px 15px;
            margin: 0 5px;
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .navigation-buttons button:hover {
            background-color: rgb(0, 112, 9);
        }

        .navigation-buttons button:disabled {
            background-color: var(--light-gray);
            cursor: not-allowed;
        }

        .outline-toggle {
            background-color: var(--info-color);
            color: var(--white);
            border: 2px solid var(--info-color);
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.15);
        }

        .outline-toggle:hover {
            background-color: var(--accent-color);
            color: var(--white);
        }

        .message-outline {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .message-outline.show {
            display: block;
        }

        .message-outline ul {
            list-style-type: none;
        }

        .message-outline li {
            margin: 8px 0;
            padding-left: 15px;
            position: relative;
        }

        .message-outline li.bold {
            font-weight: bold;
            color: var(--primary-color);
        }

        .message-outline li:before {
            content: "•";
            color: var(--accent-color);
            position: absolute;
            left: 0;
        }

        .message-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .message-date {
            font-size: 16px;
            color: #666;
            font-weight: normal;
        }

        .add-message-btn {
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
            height: 40px;
            white-space: nowrap;
        }

        .add-message-btn:hover {
            background-color: rgb(0, 112, 9);
        }

        .message-form {
            display: none;
            background-color: var(--white);
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .message-form.show {
            display: block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 150px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .form-actions button {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-btn {
            background-color: var(--accent-color);
            color: var(--white);
        }

        .cancel-btn {
            background-color: #f0f0f0;
            color: var(--primary-color);
        }

        .submit-btn:hover {
            background-color: rgb(0, 112, 9);
        }

        .cancel-btn:hover {
            background-color: #e0e0e0;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar-header h3 {
                display: none;
            }
            
            .sidebar-menu span {
                display: none;
            }
            
            .content-area {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .sidebar-header {
                padding: 10px;
            }
            
            .sidebar-menu {
                display: flex;
                padding: 0;
                overflow-x: auto;
            }
            
            .sidebar-menu ul {
                display: flex;
                width: 100%;
            }
            
            .sidebar-menu li {
                margin-bottom: 0;
                flex: 1;
            }
            
            .sidebar-menu a {
                padding: 10px;
                justify-content: center;
            }
            
            .sidebar-menu i {
                margin-right: 0;
            }
            
            .content-area {
                margin-left: 0;
            }
            
            .message-controls {
                flex-direction: column;
                gap: 10px;
            }

            .navigation-buttons {
                width: 100%;
                display: flex;
                justify-content: space-between;
            }

            .search-container {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .search-container input[type="text"] {
                width: 100%;
                margin-bottom: 0;
            }

            .search-container button,
            .add-message-btn,
            .manage-messages-btn {
                width: 100%;
                margin: 0;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .search-container {
                gap: 8px;
            }

            .search-container button,
            .add-message-btn,
            .manage-messages-btn {
                padding: 10px 15px;
                font-size: 13px;
            }
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            transition: opacity 0.3s ease-in-out;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .search-results {
            display: none;
            margin-top: 20px;
            background-color: var(--white);
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .search-results.show {
            display: block;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.3s;
            position: relative;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .search-result-thumbnail {
            width: 120px;
            height: 68px;
            margin-right: 15px;
            border-radius: 4px;
            overflow: hidden;
        }

        .search-result-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .search-result-content {
            flex: 1;
        }

        .search-result-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .search-result-date {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .search-result-outline {
            font-size: 14px;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }

        .search-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 2px solid #eee;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px 5px 0 0;
        }

        .search-results-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .search-results-count {
            font-size: 14px;
            color: var(--accent-color);
            font-weight: 600;
            background-color: var(--white);
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid var(--accent-color);
        }

        .search-result-count {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 11px;
            color: var(--accent-color);
            font-weight: 600;
            background-color: var(--white);
            padding: 6px 12px;
            border-radius: 15px;
            border: 1px solid var(--accent-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
            max-width: 250px;
            text-align: center;
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
                    <h2>Messages</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>

            <div class="search-container">
                <input type="text" id="search-input" placeholder="Search messages by title, date, or content..." required>
                <button onclick="searchMessages()">Search</button>
                <?php if ($is_super_admin): ?>
                <button class="add-message-btn" id="add-message-btn">Add New Message</button>
                <button class="manage-messages-btn" onclick="window.location.href='manage_messages.php'">Manage Messages</button>
                <?php endif; ?>
            </div>

            <div class="search-results" id="search-results"></div>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" id="success-message">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
            <?php endif; ?>

            <?php if ($is_super_admin): ?>
            <div class="message-form" id="message-form">
                <form action="add_message.php" method="POST" id="addMessageForm">
                    <div class="form-group">
                        <label for="title">Message Title:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="youtube_id">YouTube Video ID:</label>
                        <input type="text" id="youtube_id" name="youtube_id" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" id="date" name="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="outline">Message Outline (one point per line):</label>
                        <textarea id="outline" name="outline" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">Save Message</button>
                        <button type="button" class="cancel-btn" id="cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="video-container">
                <div class="message-title">
                    <?php echo $message['title']; ?>
                    <span class="message-date"><?php echo date('F d, Y', strtotime($message['date'])); ?></span>
                </div>
                
                <div class="video-wrapper">
                    <iframe 
                        src="https://www.youtube.com/embed/<?php echo $message['youtube_id']; ?>?rel=0" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                </div>

                <div class="message-controls">
                    <div class="navigation-buttons">
                        <button id="prev-btn" <?php echo $current_message == 0 ? 'disabled' : ''; ?> 
                            onclick="location.href='messages.php?message=<?php echo $current_message - 1; ?>'">
                            Previous
                        </button>
                        <button id="next-btn" <?php echo $current_message == count($messages) - 1 ? 'disabled' : ''; ?> 
                            onclick="location.href='messages.php?message=<?php echo $current_message + 1; ?>'">
                            Next
                        </button>
                    </div>
                    <button class="outline-toggle" id="outline-toggle">Show Outline</button>
                </div>

                <div class="message-outline" id="message-outline">
                    <h3>Message Outline</h3>
                    <ul>
                        <?php foreach ($message['outline'] as $point): ?>
                            <li class="<?php echo (strpos($point, 'Main Point') !== false || strpos($point, 'I.') !== false || strpos($point, 'II.') !== false || strpos($point, 'III.') !== false || strpos($point, 'IV.') !== false || strpos($point,'V.') !==false) !== false || strpos($point, 'TEXT:') !==false? 'bold' : ''; ?>"><?php echo $point; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Auto-hide success message after 3 seconds
        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 300);
            }, 3000);
        }

        // Search functionality
        function searchMessages() {
            const searchInput = document.getElementById('search-input');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const searchResults = document.getElementById('search-results');
            
            if (!searchTerm) {
                searchInput.setCustomValidity('Please enter a search term');
                searchInput.reportValidity();
                return;
            }
            
            searchInput.setCustomValidity(''); // Clear any previous validation message

            // Get messages data and ensure outline is properly decoded from JSON
            const messages = <?php 
                $messagesData = array_map(function($msg) {
                    // Debug the outline data
                    error_log("Original outline: " . print_r($msg['outline'], true));
                    
                    // Handle different possible outline formats
                    $outline = $msg['outline'];
                    if (is_string($outline)) {
                        $decoded = json_decode($outline, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $outline = $decoded;
                        } else {
                            // If not valid JSON, treat as a single string
                            $outline = [$outline];
                        }
                    }
                    
                    // Ensure we have an array
                    if (!is_array($outline)) {
                        $outline = [];
                    }
                    
                    error_log("Processed outline: " . print_r($outline, true));
                    
                    return [
                        'id' => $msg['id'],
                        'title' => $msg['title'],
                        'youtube_id' => $msg['youtube_id'],
                        'date' => $msg['date'],
                        'outline' => $outline
                    ];
                }, $messages);
                echo json_encode($messagesData);
            ?>;
            
            console.log('Search term:', searchTerm);
            console.log('Messages data:', messages);
            
            let foundMessages = [];

            for (let i = 0; i < messages.length; i++) {
                const message = messages[i];
                const title = message.title.toLowerCase();
                const date = message.date.toLowerCase();
                
                // Debug the outline for this message
                console.log('Message outline:', message.outline);
                
                // Get all outline points as a single string for searching
                const outlineText = Array.isArray(message.outline) ? 
                    message.outline.join(' ').toLowerCase() : '';
                
                console.log('Outline text for searching:', outlineText);
                
                // Check if search term exists in any part of the message
                const titleMatch = title.includes(searchTerm);
                const dateMatch = date.includes(searchTerm);
                const outlineMatch = outlineText.includes(searchTerm);
                
                console.log('Matches:', {
                    titleMatch,
                    dateMatch,
                    outlineMatch,
                    searchTerm,
                    outlineText
                });
                
                if (titleMatch || dateMatch || outlineMatch) {
                    // Find all matching outline points
                    let matchingOutlinePoints = [];
                    if (outlineMatch && Array.isArray(message.outline)) {
                        matchingOutlinePoints = message.outline.filter(point => 
                            point.toLowerCase().includes(searchTerm)
                        );
                    }

                    foundMessages.push({
                        index: i,
                        message: message,
                        matchType: titleMatch ? 'title' : dateMatch ? 'date' : 'outline',
                        matchingOutlinePoints: matchingOutlinePoints,
                        fullOutline: outlineText
                    });
                }
            }

            console.log('Found messages:', foundMessages);

            if (foundMessages.length > 0) {
                // Calculate occurrences for each message and add to result object
                foundMessages.forEach(result => {
                    const message = result.message;
                    const titleText = message.title || '';
                    const dateText = message.date || '';
                    const outlineText = Array.isArray(message.outline) ? 
                        message.outline.join(' ') : '';
                    
                    result.occurrences = countOccurrences(titleText, searchTerm) +
                                       countOccurrences(dateText, searchTerm) +
                                       countOccurrences(outlineText, searchTerm);
                });

                // Sort by occurrences (highest first)
                foundMessages.sort((a, b) => b.occurrences - a.occurrences);

                // Display search results with individual counts
                const resultsHTML = `
                    <div class="search-results-header">
                        <div class="search-results-title">Search Results (${foundMessages.length} ${foundMessages.length === 1 ? 'message' : 'messages'})</div>
                    </div>
                    ${foundMessages.map(result => {
                        const message = result.message;
                        const thumbnailUrl = `https://img.youtube.com/vi/${message.youtube_id}/mqdefault.jpg`;
                        const messageOccurrences = result.occurrences;
                        
                        // Show all matching outline points or first two points if no matches
                        const outlinePreview = result.matchingOutlinePoints.length > 0 ?
                            result.matchingOutlinePoints.join(' | ') :
                            (Array.isArray(message.outline) ? message.outline.slice(0, 2).join(' ') : '');
                        
                        return `
                            <div class="search-result-item" onclick="location.href='messages.php?message=${result.index}'">
                                <div class="search-result-thumbnail">
                                    <img src="${thumbnailUrl}" alt="${message.title}">
                                </div>
                                <div class="search-result-content">
                                    <div class="search-result-title">${highlightText(message.title, searchTerm)}</div>
                                    <div class="search-result-date">${message.date}</div>
                                    <div class="search-result-outline">${highlightText(outlinePreview, searchTerm)}</div>
                                </div>
                                <div class="search-result-count">The word "${searchTerm}" appears ${messageOccurrences} ${messageOccurrences === 1 ? 'time' : 'times'}</div>
                            </div>
                        `;
                    }).join('')}
                `;
                
                searchResults.innerHTML = resultsHTML;
                searchResults.classList.add('show');
            } else {
                searchInput.setCustomValidity('No messages found matching your search');
                searchInput.reportValidity();
                searchResults.classList.remove('show');
                setTimeout(() => {
                    searchInput.setCustomValidity('');
                }, 2000);
            }
        }

        // Function to count occurrences of a term in text
        function countOccurrences(text, searchTerm) {
            if (!text || !searchTerm) return 0;
            const regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
            const matches = text.match(regex);
            return matches ? matches.length : 0;
        }

        // Function to highlight matching text
        function highlightText(text, searchTerm) {
            if (!searchTerm) return text;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<span class="highlight">$1</span>');
        }

        // Add enter key support for search
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchMessages();
            }
        });

        // Add search button click handler
        document.querySelector('.search-container button').addEventListener('click', function(e) {
            e.preventDefault();
            searchMessages();
        });

        // Clear validation message and search results when user starts typing
        document.getElementById('search-input').addEventListener('input', function() {
            this.setCustomValidity('');
            if (this.value.trim() === '') {
                document.getElementById('search-results').classList.remove('show');
            }
        });

        document.getElementById('outline-toggle').addEventListener('click', function() {
            const outline = document.getElementById('message-outline');
            const isShown = outline.classList.contains('show');
            
            outline.classList.toggle('show');
            this.textContent = isShown ? 'Show Outline' : 'Hide Outline';
        });

        <?php if ($is_super_admin): ?>
        // Add Message Form Toggle
        const addMessageBtn = document.getElementById('add-message-btn');
        const messageForm = document.getElementById('message-form');
        const cancelBtn = document.getElementById('cancel-btn');

        addMessageBtn.addEventListener('click', function() {
            messageForm.classList.add('show');
            addMessageBtn.style.display = 'none';
        });

        cancelBtn.addEventListener('click', function() {
            messageForm.classList.remove('show');
            addMessageBtn.style.display = 'block';
        });
        <?php endif; ?>

        document.getElementById('addMessageForm').addEventListener('submit', function(e) {
            // Basic form validation
            const title = document.getElementById('title').value.trim();
            const youtubeId = document.getElementById('youtube_id').value.trim();
            const date = document.getElementById('date').value.trim();
            const outline = document.getElementById('outline').value.trim();

            if (!title || !youtubeId || !date || !outline) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });

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
    </script>
</body>
</html>