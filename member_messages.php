<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: index.php");
    exit;
}
// Restrict access to Member only
if ($_SESSION["user_role"] !== "Member") {
    header("Location: index.php");
    exit;
}

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch messages from database
$messages = [];
$sql = "SELECT * FROM messages ORDER BY date DESC";
$result = $conn->query($sql);

if (!$result) {
    error_log("Database query failed: " . $conn->error);
    echo '<div style="padding: 30px; text-align: center; color: #888;">Error loading messages. Please try again later.</div>';
    exit;
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $outline = json_decode($row['outline'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
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

if (empty($messages)) {
    echo '<div style="padding: 30px; text-align: center; color: #888;">No messages available.</div>';
    exit;
}

$current_message = isset($_GET['message']) ? (int)$_GET['message'] : 0;
if ($current_message < 0 || $current_message >= count($messages)) {
    $current_message = 0;
}

$message = $messages[$current_message];

if (!isset($message['title']) || !isset($message['youtube_id']) || !isset($message['outline'])) {
    echo '<div style="padding: 30px; text-align: center; color: #888;">Error: Invalid message data. Please contact the administrator.</div>';
    exit;
}

$show_live_alert = false;
if (
    (isset($message['title']) && stripos($message['title'], 'live') !== false)
    || (isset($message['outline']) && is_array($message['outline']) && preg_grep('/live|ongoing/i', $message['outline']))
) {
    $show_live_alert = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | <?php echo htmlspecialchars($church_name); ?></title>
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

        /* Remove legacy sidebar styles */
        /* .sidebar, .sidebar-header, .sidebar-menu, .sidebar-menu ul, .sidebar-menu li, .sidebar-menu a, .sidebar-menu a.active, .sidebar-menu i { ... } */
        .content-area {
            flex: 1;
            padding: 20px;
            width: 100%;
            max-width: none;
            margin-left: 0;
            margin-right: 0;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--white);
            padding: 15px 20px 15px 20px; /* left padding aligns with menu button */
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            margin-top: 60px; 
            z-index: 100;
        }
        .top-bar h2 {
            color: var(--primary-color);
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
            padding-bottom: 56.25%;
            height: 0;
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
            background-color: transparent;
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
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

        .search-results {
            display: none;
            margin-top: 20px;
            background-color: var(--white);
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            z-index: 1000;
        }

        .search-results.show {
            display: block !important;
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

        .live-alert {
  display: block;
  background: linear-gradient(90deg, #e3f0ff 0%, #f5faff 100%);
  color: #155fa0;
  border: 1px solid #b6d4fe;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(21,95,160,0.07);
  padding: 12px 18px;
  margin-bottom: 16px;
  font-size: 14px;
  position: relative;
  transition: background 0.2s;
}
.live-alert .live-alert-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 15px;
}
.live-alert .live-alert-body {
  margin: 6px 0 10px 0;
  font-size: 13px;
}
.live-alert .live-alert-actions {
  display: flex;
  gap: 8px;
}
.live-alert .live-alert-btn {
  background: #155fa0;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 4px 12px;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.live-alert .live-alert-btn:hover {
  background: #0d4377;
}
.live-alert .live-alert-dismiss {
  background: transparent;
  color: #155fa0;
  border: 1px solid #b6d4fe;
  padding: 4px 12px;
}
.live-alert .live-alert-dismiss:hover {
  background: #e3f0ff;
}
@media (prefers-color-scheme: dark) {
  .live-alert {
    background: linear-gradient(90deg, #1e293b 0%, #334155 100%);
    color: #93c5fd;
    border: 1px solid #334155;
    box-shadow: 0 2px 8px rgba(30,41,59,0.12);
  }
  .live-alert .live-alert-btn {
    background: #2563eb;
    color: #fff;
  }
  .live-alert .live-alert-btn:hover {
    background: #1e40af;
  }
  .live-alert .live-alert-dismiss {
    color: #93c5fd;
    border: 1px solid #334155;
    background: transparent;
  }
  .live-alert .live-alert-dismiss:hover {
    background: #1e293b;
  }
}

    </style>
    <!-- Drawer Navigation CSS (from member_dashboard.php) -->
    <style>
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
            padding: 12px 18px;
            color: #3a3a3a;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            gap: 10px;
            border-left: 4px solid transparent;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
            position: relative;
        }
        .drawer-link i {
            font-size: 18px;
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
    </style>
    <!-- Drawer Navigation Markup (from member_dashboard.php) -->
    <div class="nav-toggle-container">
       <button class="nav-toggle-btn" type="button" id="nav-toggle">
       <i class="fas fa-bars"></i> Menu
       </button>
    </div>
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
                <li><a href="member_dashboard.php" class="drawer-link <?php echo $current_page == 'member_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                <li><a href="member_events.php" class="drawer-link <?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i><span>Events</span></a></li>
                <li><a href="member_messages.php" class="drawer-link <?php echo $current_page == 'member_messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i><span>Messages</span></a></li>
                <li><a href="member_prayers.php" class="drawer-link <?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i><span>Prayer Requests</span></a></li>
                <li><a href="member_financialreport.php" class="drawer-link <?php echo $current_page == 'member_financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i><span>Financial Reports</span></a></li>
                <li><a href="member_collection.php" class="drawer-link <?php echo $current_page == 'member_collection.php' ? 'active' : ''; ?>"><i class="fas fa-list-alt"></i><span>My Report</span></a></li>
                <li><a href="member_settings.php" class="drawer-link <?php echo $current_page == 'member_settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i><span>Settings</span></a></li>
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
                <div class="role"><?php echo htmlspecialchars($user_profile['role']); ?></div>
            </div>
            <form action="logout.php" method="post" style="margin:0;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>
    <div id="drawer-overlay" class="drawer-overlay"></div>
    <!-- Drawer Navigation JS (from member_dashboard.php) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const drawer = document.getElementById('drawer-navigation');
        const overlay = document.getElementById('drawer-overlay');
        const openBtn = document.getElementById('nav-toggle');
        const closeBtn = document.getElementById('drawer-close');

        function openDrawer() {
            drawer.classList.add('open');
            overlay.classList.add('open');
            if (window.innerWidth > 992) {
                document.body.classList.add('drawer-open');
            }
        }
        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
            document.body.classList.remove('drawer-open');
        }

        openBtn.addEventListener('click', openDrawer);
        closeBtn.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDrawer();
        });

        // Remove drawer-open class on resize if needed
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 992) {
                document.body.classList.remove('drawer-open');
            }
        });
    });
    </script>
</head>
<body>
    <div class="dashboard-container">
        <main class="content-area">
<?php if ($show_live_alert): ?>
<div class="live-alert" role="alert">
  <div class="live-alert-header">
    <svg width="18" height="18" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/></svg>
    Live Service Ongoing
  </div>
  <div class="live-alert-body">
    The church service is currently live! Join us now or watch the ongoing stream below.
  </div>
  <div class="live-alert-actions">
    <a href="member_messages.php?message=0" class="live-alert-btn">
      <svg width="13" height="13" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 14"><path d="M10 0C4.612 0 0 5.336 0 7c0 1.742 3.546 7 10 7 6.454 0 10-5.258 10-7 0-1.664-4.612-7-10-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>
      View Live
    </a>
    <button type="button" class="live-alert-btn live-alert-dismiss" aria-label="Close" onclick="this.closest('.live-alert').style.display='none';">Dismiss</button>
  </div>
</div>
<?php endif; ?>
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
                <button id="search-btn">Search</button>
            </div>
            <div class="search-results" id="search-results"></div>
            <div class="video-container" id="videoContainer">
                <div class="message-title">
                    <?php echo htmlspecialchars($message['title']); ?>
                    <span class="message-date"><?php echo date('F j, Y', strtotime($message['date'])); ?></span>
                </div>
                <div class="video-wrapper">
                    <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($message['youtube_id']); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
                <div class="message-controls">
                    <div class="navigation-buttons">
                        <button onclick="window.location.href='member_messages.php?message=<?php echo max(0, $current_message - 1); ?>'" <?php echo $current_message <= 0 ? 'disabled' : ''; ?>>Previous</button>
                        <button onclick="window.location.href='member_messages.php?message=<?php echo min(count($messages) - 1, $current_message + 1); ?>'" <?php echo $current_message >= count($messages) - 1 ? 'disabled' : ''; ?>>Next</button>
                    </div>
                    <button class="outline-toggle" onclick="toggleOutline()">Show Outline</button>
                </div>
                <div class="message-outline" id="message-outline" style="display: none;">
                    <ul>
                        <?php foreach ($message['outline'] as $point): ?>
                            <li class="<?php 
                                if (is_array($point) && isset($point['bold']) && $point['bold']) {
                                    echo 'bold';
                                } elseif (is_string($point) && (strpos($point, 'Main Point') !== false || strpos($point, 'I.') !== false || strpos($point, 'II.') !== false || strpos($point, 'III.') !== false || strpos($point, 'IV.') !== false || strpos($point,'V.') !== false || strpos($point, 'TEXT:') !== false)) {
                                    echo 'bold';
                                }
                            ?>">
                                <?php 
                                if (is_array($point) && isset($point['text'])) {
                                    echo htmlspecialchars($point['text']);
                                } elseif (is_string($point)) {
                                    echo htmlspecialchars($point);
                                } else {
                                    echo htmlspecialchars(json_encode($point));
                                }
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Store messages data globally
        const messages = <?php echo json_encode($messages); ?>;

        // Toggle outline visibility
        function toggleOutline() {
            console.log('Toggle outline function called');
            const outline = document.getElementById('message-outline');
            const toggleButton = document.querySelector('.outline-toggle');
            
            console.log('Elements found:', {
                outline: !!outline,
                toggleButton: !!toggleButton
            });
            
            if (outline && toggleButton) {
                console.log('Current outline display:', outline.style.display);
                if (outline.style.display === 'none') {
                    outline.style.display = 'block';
                    toggleButton.textContent = 'Hide Outline';
                    console.log('Outline shown');
                } else {
                    outline.style.display = 'none';
                    toggleButton.textContent = 'Show Outline';
                    console.log('Outline hidden');
                }
            } else {
                console.error('Required elements not found for outline toggle');
            }
        }

        // Search functionality
        function searchMessages() {
            console.log('Search function called');
            const searchInput = document.getElementById('search-input');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const searchResults = document.getElementById('search-results');
            
            if (!searchInput || !searchResults) {
                console.error('Required elements not found');
                return;
            }
            
            if (!searchTerm) {
                searchInput.setCustomValidity('Please enter a search term');
                searchInput.reportValidity();
                return;
            }
            
            searchInput.setCustomValidity(''); // Clear any previous validation message

            console.log('Search term:', searchTerm);
            console.log('Messages data:', messages);
            
            let foundMessages = [];

            // Helper function to format date for searching
            function formatDateForSearch(dateString) {
                if (!dateString) return '';
                try {
                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) return dateString.toLowerCase();
                    
                    const months = ['january', 'february', 'march', 'april', 'may', 'june', 
                                   'july', 'august', 'september', 'october', 'november', 'december'];
                    const monthAbbr = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 
                                      'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
                    
                    const year = date.getFullYear();
                    const month = date.getMonth();
                    const day = date.getDate();
                    const monthName = months[month];
                    const monthAbbrName = monthAbbr[month];
                    
                    // Create searchable date string with multiple formats
                    return [
                        dateString.toLowerCase(), // Original format (e.g., "2024-01-15")
                        `${monthName} ${day}, ${year}`, // "January 15, 2024"
                        `${monthName} ${day}`, // "January 15"
                        `${day} ${monthName}`, // "15 January"
                        monthName, // "January"
                        monthAbbrName, // "Jan"
                        day.toString(), // "15"
                        year.toString(), // "2024"
                        `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`, // "2024-01-15"
                        `${month + 1}/${day}/${year}`, // "1/15/2024"
                        `${day}/${month + 1}/${year}` // "15/1/2024"
                    ].join(' ').toLowerCase();
                } catch (e) {
                    return dateString.toLowerCase();
                }
            }

            for (let i = 0; i < messages.length; i++) {
                const message = messages[i];
                const title = message.title.toLowerCase();
                const dateSearchText = formatDateForSearch(message.date);
                
                // Debug the outline for this message
                console.log('Message outline:', message.outline);
                
                // Get all outline points as a single string for searching
                const outlineText = Array.isArray(message.outline) ? 
                    message.outline.map(point => {
                        if (typeof point === 'object' && point.text) {
                            return point.text;
                        } else if (typeof point === 'string') {
                            return point;
                        }
                        return '';
                    }).join(' ').toLowerCase() : '';
                
                console.log('Outline text for searching:', outlineText);
                
                // Check if search term exists in any part of the message
                const titleMatch = title.includes(searchTerm);
                const dateMatch = dateSearchText.includes(searchTerm);
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
                        matchingOutlinePoints = message.outline.filter(point => {
                            const pointText = typeof point === 'object' && point.text ? point.text : 
                                            typeof point === 'string' ? point : '';
                            return pointText.toLowerCase().includes(searchTerm);
                        }).map(point => {
                            return typeof point === 'object' && point.text ? point.text : 
                                   typeof point === 'string' ? point : '';
                        });
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
                    const dateSearchText = formatDateForSearch(message.date);
                    const outlineText = Array.isArray(message.outline) ? 
                        message.outline.map(point => {
                            if (typeof point === 'object' && point.text) {
                                return point.text;
                            } else if (typeof point === 'string') {
                                return point;
                            }
                            return '';
                        }).join(' ') : '';
                    
                    result.occurrences = countOccurrences(titleText, searchTerm) +
                                       countOccurrences(dateSearchText, searchTerm) +
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
                            (Array.isArray(message.outline) ? message.outline.slice(0, 2).map(point => {
                                if (typeof point === 'object' && point.text) {
                                    return point.text;
                                } else if (typeof point === 'string') {
                                    return point;
                                }
                                return '';
                            }).join(' ') : '');
                        
                        return `
                            <div class="search-result-item" onclick="location.href='member_messages.php?message=${result.index}'">
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
                // Hide video container when showing search results
                document.getElementById('videoContainer').style.display = 'none';
            } else {
                searchInput.setCustomValidity('No messages found matching your search');
                searchInput.reportValidity();
                searchResults.classList.remove('show');
                // Show video container when no results
                document.getElementById('videoContainer').style.display = 'block';
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
            if (!searchTerm || !text) return text;
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
        document.getElementById('search-btn').addEventListener('click', function(e) {
            e.preventDefault();
            searchMessages();
        });

        // Clear validation message and search results when user starts typing
        document.getElementById('search-input').addEventListener('input', function() {
            this.setCustomValidity('');
            if (this.value.trim() === '') {
                document.getElementById('search-results').classList.remove('show');
                document.getElementById('videoContainer').style.display = 'block';
            }
        });
    </script>
</body>
</html>