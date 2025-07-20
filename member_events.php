<?php
// Member Events page
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

// Check user role
$is_member = ($_SESSION["user_role"] === "Member");
// Redirect non-members to dashboard.php
if (!$is_member) {
    header("Location: index.php");
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

$live_message = getLiveMessage($conn);

// Function to get all events from database
function getAllEvents($conn) {
    $events = [];
    $sql = "SELECT * FROM events ORDER BY event_date ASC, event_time ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'category' => $row['category'],
                'date' => $row['event_date'],
                'time' => $row['event_time'],
                'description' => $row['description'],
                'is_pinned' => $row['is_pinned'],
                'created_by' => $row['created_by'],
                'created_at' => $row['created_at'],
                'image' => $row['image'] ?? ''
            ];
        }
    }
    return $events;
}

// Function to get pinned event
function getPinnedEvent($conn) {
    $sql = "SELECT * FROM events WHERE is_pinned = 1 ORDER BY event_date ASC, event_time ASC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'id' => $row['id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'date' => $row['event_date'],
            'time' => $row['event_time'],
            'description' => $row['description'],
            'is_pinned' => $row['is_pinned'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'image' => $row['image'] ?? ''
        ];
    }
    return null;
}

// Get all events from database
$events = getAllEvents($conn);

// Get pinned event
$pinned_event = getPinnedEvent($conn);

// User-friendly message
$user_message = empty($events) ? "No upcoming events scheduled at this time." : "";

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Group events by day of the week
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$events_by_day = [];
foreach ($weekdays as $day) {
    $events_by_day[$day] = [];
}

// Populate events by day from database
foreach ($events as $event) {
    $event_date = new DateTime($event['date']);
    $day_name = $event_date->format('l'); // Gets day name (Monday, Tuesday, etc.)
    $time = date('H:i', strtotime($event['time']));
    
    $events_by_day[$day_name][] = [
        'day' => $day_name,
        'time' => $time,
        'title' => $event['title'],
        'category' => $event['category'],
        'description' => $event['description'],
        'date' => $event['date'],
        'is_pinned' => $event['is_pinned']
    ];
}

// No static events - only database events will be shown
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Events | <?php echo $church_name; ?></title>
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
            --schedule-bg: #f8f9fa;
            --schedule-border: #e9ecef;
            --schedule-text: #495057;
            --schedule-accent: #007bff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--schedule-bg);
            color: var(--schedule-text);
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header img {
            height: 60px;
            margin-bottom: 10px;
        }

        .sidebar-header h3 {
            font-size: 18px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--white);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu a.active {
            background-color: var(--accent-color);
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .content-area {
            margin-left: 0;
            padding: 20px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .schedule-container {
            width: 100%;
            max-width: 100%;
            margin: 0 0 30px 0;
            box-sizing: border-box;
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
            margin-top: 60px;
            width: 100%;
        }

        .top-bar h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .user-profile {
            display: flex;
            align-items: center;
        }

        .user-profile .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            overflow: hidden;
        }

        .user-profile .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            margin-right: 20px;
        }

        .user-info h4 {
            font-size: 16px;
            margin: 0;
            color: var(--primary-color);
        }

        .user-info p {
            font-size: 14px;
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
        }
        
        .logout-btn:hover {
            background-color: #e0e0e0;
        }

        /* Schedule Template Styles */
        .schedule-container {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .schedule-header {
            background: linear-gradient(135deg, var(--accent-color), #005a1f);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .schedule-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .schedule-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: 1fr; /* Changed to 1fr */
            min-height: 600px;
        }

        .time-column {
            background: #f8f9fa;
            border-right: 1px solid var(--schedule-border);
            padding: 20px 0;
        }

        .time-slot {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 500;
            color: var(--schedule-text);
            border-bottom: 1px solid var(--schedule-border);
        }

        .time-slot:last-child {
            border-bottom: none;
        }

        .schedule-content {
            padding: 20px;
        }

        .day-columns {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
            height: 100%;
        }

        .day-column {
            display: flex;
            flex-direction: column;
        }

        .day-header {
            background: var(--accent-color);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 10px;
        }

        .day-events {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .event-card {
            background: white;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: var(--accent-color);
        }

        .event-time {
            font-size: 12px;
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 3px;
        }

        .event-date {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 5px;
            font-style: italic;
        }

        .event-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 3px;
            line-height: 1.3;
        }

        .event-category {
            font-size: 11px;
            color: #6c757d;
            font-style: italic;
        }

        .event-description {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
            line-height: 1.3;
        }

        .pinned-event {
            border: 2px solid var(--accent-color);
            background: linear-gradient(135deg, rgba(0, 139, 30, 0.05), rgba(0, 139, 30, 0.1));
        }

        .pinned-event .event-title::before {
            content: "📌 ";
        }

        .notification {
            background: linear-gradient(135deg, var(--accent-color), #005a1f);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 139, 30, 0.2);
        }

        .notification.no-events {
            background: linear-gradient(135deg, var(--warning-color), #e68a00);
        }

        .notification i {
            margin-right: 15px;
            font-size: 24px;
        }

        .notification-text {
            font-size: 16px;
            font-weight: 500;
        }

        .search-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .search-box {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            transition: border-color 0.3s;
        }

        .search-box:focus-within {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 139, 30, 0.1);
        }

        .search-box input {
            border: none;
            background-color: transparent;
            padding: 0;
            flex: 1;
            font-size: 16px;
            color: var(--schedule-text);
        }

        .search-box input:focus {
            outline: none;
        }

        .search-box input::placeholder {
            color: #6c757d;
        }

        .search-box i {
            color: var(--accent-color);
            margin-right: 10px;
            font-size: 18px;
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

        @media (max-width: 1200px) {
            .day-columns {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h3, .sidebar-menu span {
                display: none;
            }
            .content-area {
                margin-left: 70px;
            }
            .day-columns {
                grid-template-columns: repeat(3, 1fr);
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
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .user-profile {
                margin-top: 10px;
            }
            .day-columns {
                grid-template-columns: repeat(2, 1fr);
            }
            .schedule-grid {
                grid-template-columns: 80px 1fr;
            }
            .time-slot {
                font-size: 12px;
                height: 50px;
            }
        }

        @media (max-width: 480px) {
            .day-columns {
                grid-template-columns: 1fr;
            }
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            .time-column {
                display: none;
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
        }
        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
        }

        openBtn.addEventListener('click', openDrawer);
        closeBtn.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDrawer();
        });
    });
    </script>
</head>
<body>
    <div class="dashboard-container">
        <main class="content-area">
<?php if ($live_message): ?>
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
                    <h2><strong>Events</strong></h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>



            <!-- Search -->
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search events by title, category, or description...">
                </div>
            </div>

            <!-- Schedule Template -->
            <div class="schedule-container">
                <div class="schedule-header">
                    <h1>Church Events Schedule</h1>
                    <p>Join us for fellowship, worship, and spiritual growth</p>
                </div>
                
                <div class="schedule-grid">
                    <!-- Removed time-column -->
                    
                    <div class="schedule-content">
                        <div class="day-columns">
                            <?php foreach ($weekdays as $day): ?>
                                <div class="day-column">
                                    <div class="day-header"><?php echo $day; ?></div>
                                    <div class="day-events">
                                        <?php 
                                        $day_events = $events_by_day[$day];
                                        if (empty($day_events)): ?>
                                            <div class="event-card" style="opacity: 0.5; text-align: center; font-style: italic; color: #6c757d;">
                                                No events
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($day_events as $event): ?>
                                                <div class="event-card <?php echo ($pinned_event && $pinned_event['title'] === $event['title']) ? 'pinned-event' : ''; ?>">
                                                    <?php if (!empty($event['image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="Event Image" style="width:100%;max-height:120px;object-fit:cover;border-radius:6px 6px 0 0;margin-bottom:8px;">
                                                    <?php endif; ?>
                                                    <div class="event-date"><?php echo date("M j, Y", strtotime($event['date'])); ?></div>
                                                    <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                                    <div class="event-category"><?php echo htmlspecialchars($event['category']); ?></div>
                                                    <div class="event-description"><?php echo htmlspecialchars($event['description']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-box input');
            const eventCards = document.querySelectorAll('.event-card');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                eventCards.forEach(card => {
                    const title = card.querySelector('.event-title')?.textContent.toLowerCase() || '';
                    const category = card.querySelector('.event-category')?.textContent.toLowerCase() || '';
                    const description = card.querySelector('.event-description')?.textContent.toLowerCase() || '';
                    
                    if (title.includes(searchTerm) || category.includes(searchTerm) || description.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>