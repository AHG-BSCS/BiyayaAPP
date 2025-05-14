<?php
// Member Events page
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Check user role
$is_member = ($_SESSION["user_role"] === "Member");
$is_admin = ($_SESSION["user_role"] === "Administrator");

// Redirect admins to events.php
if ($is_admin) {
    header("Location: events.php");
    exit;
}

// Redirect non-members to dashboard.php
if (!$is_member) {
    header("Location: dashboard.php");
    exit;
}

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Ensure events array exists
if (!isset($_SESSION['events'])) {
    $_SESSION['events'] = [];
}

// Get pinned event
$pinned_event = null;
if (isset($_SESSION['pinned_event_id'])) {
    $pinned_event = array_filter($_SESSION['events'], function($event) {
        return $event['id'] === $_SESSION['pinned_event_id'];
    });
    $pinned_event = reset($pinned_event);
}

// Debugging: Log session events to console
$debug_message = "Session events count: " . count($_SESSION['events']);
if (empty($_SESSION['events'])) {
    $debug_message .= " (No events in session)";
} else {
    $debug_message .= " (Events available)";
}

// User-friendly message
$user_message = empty($_SESSION['events']) ? "No upcoming events scheduled at this time." : "Check out our upcoming events below!";

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);
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
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
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

        .user-profile {
            display: flex;
            align-items: center;
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
        }

        .user-info {
            margin-right: 15px;
        }

        .user-info p {
            font-size: 14px;
            color: #666;
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

        .events-content {
            margin-top: 20px;
        }

        .action-bar {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background-color: #f0f0f0;
            border-radius: 5px;
            padding: 5px 15px;
            width: 300px;
        }

        .search-box input {
            border: none;
            background-color: transparent;
            padding: 8px;
            flex: 1;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
        }

        .search-box i {
            color: #666;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .event-category {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .event-category:hover {
            transform: translateY(-10px);
        }

        .event-category.pinned {
            border: 3px solid var(--accent-color);
            background-color: rgba(0, 139, 30, 0.05);
        }

        .event-category h3 {
            color: var(--accent-color);
            margin-bottom: 15px;
            font-size: 20px;
        }

        .event-item {
            border-bottom: 1px solid #eeeeee;
            padding: 10px 0;
            text-align: left;
        }

        .event-item:last-child {
            border-bottom: none;
        }

        .event-details h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .event-details p {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            display: flex;
            align-items: center;
        }

        .notification.no-events {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }

        .notification i {
            margin-right: 10px;
            font-size: 20px;
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
            }
            .user-profile {
                margin-top: 10px;
            }
            .action-bar {
                flex-direction: column;
                gap: 10px;
            }
            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo htmlspecialchars($church_logo); ?>" alt="Church Logo">
                <h3><?php echo $church_name; ?></h3>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="member_dashboard.php" class="<?php echo $current_page == 'member_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="member_events.php" class="<?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="member_prayers.php" class="<?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                </ul>
            </div>
        </aside>

        <main class="content-area">
            <div class="top-bar">
                <h2>Upcoming Events</h2>
                <div class="user-profile">
                    <div class="avatar">
                        <?php if (!empty($user_profile['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_profile['username'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_profile['username'] ?? 'Unknown User'); ?></h4>
                        <p><?php echo htmlspecialchars($user_profile['role'] ?? 'Member'); ?></p>
                    </div>
                    <form action="logout.php" method="post">
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>

            <div class="events-content">
                <!-- User-Friendly Notification -->
                <div class="notification <?php echo empty($_SESSION['events']) ? 'no-events' : ''; ?>">
                    <i class="fas fa-<?php echo empty($_SESSION['events']) ? 'exclamation-circle' : 'calendar-alt'; ?>"></i>
                    <?php echo $user_message; ?>
                </div>

                <!-- Action Bar with Search -->
                <div class="action-bar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search events...">
                    </div>
                </div>

                <!-- Events Display -->
                <div class="events-grid">
                    <!-- Pinned Event Box -->
                    <?php if ($pinned_event): ?>
                        <div class="event-category pinned">
                            <h3>Pinned Event</h3>
                            <div class="event-item">
                                <div class="event-details">
                                    <h4><?php echo htmlspecialchars($pinned_event['title']); ?></h4>
                                    <p><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($pinned_event['date']); ?> at <?php echo date("h:i A", strtotime($pinned_event['time'])); ?></p>
                                    <p><i class="fas fa-folder"></i> <?php echo htmlspecialchars($pinned_event['category']); ?></p>
                                    <p><?php echo htmlspecialchars($pinned_event['description']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Category Boxes -->
                    <?php
                    $categories = ["AMEN Fellowship", "WOW Fellowship", "Youth Fellowship", "Sunday School Outreach"];
                    foreach ($categories as $category):
                        // Filter events for this category, excluding pinned event
                        $category_events = array_filter($_SESSION['events'], function($event) use ($category) {
                            return $event['category'] === $category && (!isset($_SESSION['pinned_event_id']) || $event['id'] !== $_SESSION['pinned_event_id']);
                        });
                        // Check if there are any events in this category, including pinned event
                        $all_category_events = array_filter($_SESSION['events'], function($event) use ($category) {
                            return $event['category'] === $category;
                        });
                    ?>
                        <div class="event-category">
                            <h3><?php echo $category; ?></h3>
                            <?php if (empty($all_category_events)): ?>
                                <p>No upcoming events in this category.</p>
                            <?php else: ?>
                                <?php if (empty($category_events) && $pinned_event && $pinned_event['category'] === $category): ?>
                                    <p>(Pinned event displayed above)</p>
                                <?php else: ?>
                                    <?php foreach ($category_events as $event): ?>
                                        <div class="event-item">
                                            <div class="event-details">
                                                <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                                <p><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($event['date']); ?> at <?php echo date("h:i A", strtotime($event['time'])); ?></p>
                                                <p><i class="fas fa-folder"></i> <?php echo htmlspecialchars($event['category']); ?></p>
                                                <p><?php echo htmlspecialchars($event['description']); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Debug Output for Developers -->
    <script>
        console.log(<?php echo json_encode($debug_message); ?>);
    </script>
</body>
</html>