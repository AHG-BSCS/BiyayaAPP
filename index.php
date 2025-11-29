<?php
require_once 'config.php';

// Get site settings
$site_settings = getSiteSettings($conn);
$service_times = json_decode($site_settings['service_times'], true);

// Start session to access events from events.php
session_start();

// Function to get upcoming events from database
function getUpcomingEvents($conn) {
    $events = [];
    // Show all events, not just future ones for now
    $sql = "SELECT * FROM events ORDER BY event_date ASC, event_time ASC LIMIT 6";
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
                'event_image' => isset($row['event_image']) ? $row['event_image'] : null,
                'is_pinned' => $row['is_pinned']
            ];
        }
    }
    return $events;
}

// Function to get total upcoming events count
function getUpcomingEventsCount($conn) {
    // Count all events for now
    $sql = "SELECT COUNT(*) as total FROM events";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get upcoming events from database
$upcoming_events = getUpcomingEvents($conn);
$total_upcoming_events = getUpcomingEventsCount($conn);

// Function to log login attempts
function logLoginAttempt($conn, $username, $status, $failure_reason = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO login_logs (username, login_time, ip_address, user_agent, status, failure_reason) 
            VALUES (?, NOW(), ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $username, $ip_address, $user_agent, $status, $failure_reason);
    $stmt->execute();
}

// Login processing
$login_error = "";
$stored_username = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $stored_username = $username;

    // First check if user exists in the database
    $sql = "SELECT user_id, username, password, role FROM user_profiles WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // User exists, now verify password
        $user = $result->fetch_assoc();
        
        // Try MD5 first (new passwords)
        $password_valid = false;
        if (md5($password) === $user['password']) {
            $password_valid = true;
        } 
        // Fall back to password_verify for old bcrypt passwords
        else if (password_verify($password, $user['password'])) {
            $password_valid = true;
            // Migrate old password to MD5
            $new_md5_password = md5($password);
            $update_sql = "UPDATE user_profiles SET password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $new_md5_password, $user['user_id']);
            $update_stmt->execute();
        }
        
        if ($password_valid) {
            // Password is correct, log successful login
            logLoginAttempt($conn, $username, 'Success');
            
            // Set session variables
            $_SESSION["user"] = $user['username'];
            $_SESSION["user_role"] = $user['role'];
            $_SESSION["loggedin"] = true;
            
            // Redirect based on role
            if ($user['role'] === "Super Admin") {
                header("Location: superadmin_dashboard.php");
            } elseif ($user['role'] === "Administrator") {
                header("Location: dashboard.php");
            } else {
                header("Location: member_dashboard.php");
            }
            exit();
        } else {
            // Password is incorrect, log failed login
            logLoginAttempt($conn, $username, 'Failed', 'Invalid password');
            $login_error = "Invalid username or password";
        }
    } else {
        // User doesn't exist in the database, log failed login
        logLoginAttempt($conn, $username, 'Failed', 'User not found');
        $login_error = "Not a member. Please contact the administrator to register.";
    }
}

// Function to get event-specific image
function getEventImage($event_title, $custom_image = null) {
    // If custom image is provided and exists, use it
    if (!empty($custom_image) && file_exists($custom_image)) {
        return $custom_image;
    }
    
    // Check by exact title first
    switch ($event_title) {
        case "AMEN Prayer Meeting":
            return "logo/amen.jpg";
        case "WOW Bible Study":
            return "logo/wow.jpg";
        case "Youth Night":
            return "logo/dg.jpg";
        case "Sunday School Picnic":
            return "logo/sunday.jpg";
        default:
            // Check by category if title doesn't match
            if (strpos($event_title, 'AMEN') !== false || strpos($event_title, 'Prayer') !== false) {
                return "logo/amen.jpg";
            } elseif (strpos($event_title, 'WOW') !== false || strpos($event_title, 'Women') !== false) {
                return "logo/wow.jpg";
            } elseif (strpos($event_title, 'Youth') !== false) {
                return "logo/dg.jpg";
            } elseif (strpos($event_title, 'Sunday School') !== false || strpos($event_title, 'Outreach') !== false) {
                return "logo/sunday.jpg";
            } else {
                return "logo/churchpic.jpg"; // Use church picture as default
            }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_settings['church_name']); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_settings['church_logo']); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a3a3a;
            --accent-color: rgb(0, 139, 30);
            --light-gray: #d0d0d0;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--white);
            color: var(--primary-color);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            box-sizing: border-box;
        }

        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo h1 {
            font-size: 24px;
            margin-left: 10px;
            color: var(--primary-color);
        }

        .logo span {
            color: var(--accent-color);
        }

        nav ul {
            display: flex;
            list-style: none;
            align-items: center;
        }

        nav ul li {
            margin-left: 20px;
        }

        nav ul li a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
            font-size: 16px;
            padding: 8px 12px;
            display: inline-block;
            transition: color 0.3s;
        }

        nav ul li a:hover {
            color: var(--accent-color);
        }

        .hero {
            height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            text-align: center;
            margin-top: 70px;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?php echo htmlspecialchars($site_settings['login_background']); ?>');
            background-size: cover;
            background-position: center;
            filter: blur(5px);
            opacity: 0.8;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3);
        }

        .hero-content {
            position: relative;
            z-index: 10;
            width: 100%;
            padding: 0 20px;
        }

        .hero-content h2 {
            font-size: 48px;
            margin-bottom: 20px;
            color: #000000;
        }

        .hero-content p {
            font-size: 24px;
            margin-bottom: 30px;
            color: #000000;
        }

        .btn {
            display: inline-block;
            background-color: var(--accent-color);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: rgb(0, 112, 9);
        }

        .section {
            padding: 80px 0;
            text-align: center;
        }

        .section-title {
            font-size: 36px;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--accent-color);
        }

        .section-content {
            margin-top: 50px;
        }

        .services-grid {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .service-day {
            margin-bottom: 20px;
            width: 100%;
            max-width: 600px;
            background-color: var(--light-gray);
            padding: 20px;
            border-radius: 10px;
        }

        .service-day h3 {
            color: var(--accent-color);
            margin-bottom: 10px;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .events-summary-card {
            background: linear-gradient(135deg, var(--accent-color), rgb(0, 112, 9));
            color: var(--white);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 139, 30, 0.3);
        }

        .summary-content {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            text-align: center;
        }

        .summary-icon {
            font-size: 48px;
            opacity: 0.9;
        }

        .summary-info h3 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .summary-info p {
            font-size: 16px;
            opacity: 0.9;
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--white);
            color: var(--white);
        }

        .btn-outline:hover {
            background-color: var(--white);
            color: var(--accent-color);
        }

        .btn-large {
            padding: 15px 30px;
            font-size: 16px;
        }

        .view-all-events {
            text-align: center;
            margin-top: 40px;
        }

        .no-events-message {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-events-message i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--light-gray);
        }

        .no-events-message h3 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .event-card {
            background-color: var(--light-gray);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .event-card.pinned-event {
            border: 2px solid var(--accent-color);
        }

        .pinned-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent-color);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 10;
        }

        .event-details {
            padding: 20px;
            color: #000000;
        }

        .event-category {
            margin-bottom: 10px;
        }

        .category-badge {
            background-color: var(--accent-color);
            color: var(--white);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .event-details h3 {
            color: var(--accent-color);
            margin-bottom: 10px;
            font-size: 18px;
        }

        .event-date-time {
            font-weight: 500;
            margin-bottom: 15px;
        }

        .event-date-time p {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .event-date-time i {
            margin-right: 8px;
            color: var(--accent-color);
        }

        .event-description {
            font-size: 14px;
            line-height: 1.5;
            color: #555;
        }

        .contact-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-top: 50px;
        }

        .contact-item {
            background-color: var(--light-gray);
            padding: 30px;
            border-radius: 10px;
            width: 300px;
            text-align: center;
        }

        .contact-item i {
            font-size: 30px;
            color: var(--accent-color);
            margin-bottom: 15px;
        }

        footer {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 30px 0;
            text-align: center;
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .social-links {
            margin: 20px 0;
        }

        .social-links a {
            color: var(--white);
            font-size: 20px;
            margin: 0 10px;
            transition: color 0.3s;
        }

        .social-links a:hover {
            color: var(--accent-color);
        }

        .footer-nav {
            margin-bottom: 20px;
        }

        .footer-nav ul {
            display: flex;
            list-style: none;
            flex-wrap: wrap;
            justify-content: center;
        }

        .footer-nav ul li {
            margin: 0 15px;
        }

        .footer-nav ul li a {
            color: var(--white);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-nav ul li a:hover {
            color: var(--accent-color);
        }

        /* Enhanced Popup Login Styles */
        .login-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 200;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .login-form-container {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            max-width: 450px;
            width: 90%;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            animation: popupScale 0.3s ease forwards;
        }

        .login-form-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--primary-color);
            font-size: 28px;
            font-weight: 600;
        }

        .login-form-container .church-logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .login-form-container .church-logo img {
            height: 90px;
            transition: transform 0.3s;
        }

        .login-form-container .church-logo img:hover {
            transform: scale(1.05);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-color);
        }

        .form-group .input-icon {
            position: relative;
        }

        .form-group .input-icon input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group .input-icon input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 139, 30, 0.2);
        }

        .form-group .input-icon i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 18px;
        }

        .login-form-container input[type="submit"] {
            width: 100%;
            background-color: var(--accent-color);
            color: var(--white);
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        .login-form-container input[type="submit"]:hover {
            background-color: rgb(0, 112, 9);
            transform: translateY(-2px);
        }

        .login-form-container input[type="submit"]:active {
            transform: scale(0.98);
        }

        .login-form-container .error {
            color: #c62828;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 28px;
            cursor: pointer;
            color: var(--primary-color);
            background-color: var(--light-gray);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s, transform 0.2s;
        }

        .close-btn:hover {
            background-color: var(--accent-color);
            color: var(--white);
            transform: rotate(90deg);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes popupScale {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 768px) {
            header {
                padding: 0;
            }

            .header-content {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 12px 15px;
            }

            .logo {
                flex: 0 0 auto;
                margin-bottom: 0;
                display: flex;
                align-items: center;
            }

            .logo img {
                height: 45px !important;
                margin-right: 12px !important;
            }

            .logo h1 {
                font-size: 18px;
                margin-left: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 200px;
            }

            nav {
                flex: 1 1 auto;
                width: auto;
            }

            nav ul {
                margin-top: 0;
                flex-wrap: wrap;
                justify-content: flex-end;
                width: auto;
            }

            nav ul li {
                margin: 3px 5px;
            }

            nav ul li a {
                font-size: 13px;
                padding: 6px 10px;
            }

            .hero {
                margin-top: 100px;
            }

            .hero-content h2 {
                font-size: 28px;
            }

            .hero-content p {
                font-size: 16px;
            }

            .section {
                padding: 60px 0;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .login-form-container {
                width: 95%;
                padding: 20px;
            }

            .login-form-container h2 {
                font-size: 24px;
            }

            .login-form-container .church-logo img {
                height: 70px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 10px;
            }

            .header-content {
                padding: 10px;
                flex-wrap: wrap;
            }

            .logo {
                flex: 1 1 auto;
                min-width: 0;
            }

            .logo img {
                height: 40px !important;
                margin-right: 8px !important;
            }

            .logo h1 {
                font-size: 16px;
                max-width: 150px;
            }

            nav {
                flex: 1 1 100%;
                order: 3;
                width: 100%;
                margin-top: 10px;
                border-top: 1px solid rgba(0, 0, 0, 0.1);
                padding-top: 10px;
            }

            nav ul {
                justify-content: space-around;
                width: 100%;
            }

            nav ul li {
                margin: 2px;
            }

            nav ul li a {
                font-size: 12px;
                padding: 6px 8px;
            }

            .hero {
                margin-top: 120px;
            }

            .hero-content h2 {
                font-size: 24px;
            }

            .hero-content p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <img src="<?php echo htmlspecialchars($site_settings['church_logo']); ?>" alt="Church Logo" style="height: 50px; margin-right: 20px;">
                <h1><?php echo htmlspecialchars($site_settings['church_name']); ?></h1>
            </div>
            <nav>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#events">Events</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="#" onclick="showLoginPopup()">Login</a></li>
                </ul>
            </nav>
        </div>
    </div>
</header>

<section id="home" class="hero">
    <div class="hero-background" style="background-image: url('<?php echo htmlspecialchars($site_settings['login_background']); ?>');"></div>
    <div class="hero-overlay"></div>
    <div class="container">
        <div class="hero-content">
            <h2><?php echo htmlspecialchars($site_settings['church_name']); ?></h2>
            <p><?php echo htmlspecialchars($site_settings['tagline']); ?></p>
            <a href="#services" class="btn">Join Us Sunday</a>
        </div>
    </div>
</section>

<section id="about" class="section">
    <div class="container">
        <h2 class="section-title">About Us</h2>
        <div class="section-content">
            <p><?php echo htmlspecialchars($site_settings['about_us']); ?></p>
        </div>
    </div>
</section>

<section id="services" class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Service Times</h2>
        <div class="section-content">
            <div class="services-grid">
                <?php foreach ($service_times as $service => $time): ?>
                    <div class="service-day">
                        <h3><?php echo htmlspecialchars($service); ?><?php if ($service === 'Prayer Intercession'): ?> (Every Wednesday)<?php endif; ?></h3>
                        <p><?php echo htmlspecialchars($time); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section id="events" class="section">
    <div class="container">
        <h2 class="section-title">Upcoming Events</h2>
        <div class="section-content">
            <!-- Events Summary Card -->
            <div class="events-summary-card">
                <div class="summary-content">
                    <div class="summary-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="summary-info">
                        <h3><?php echo $total_upcoming_events; ?> Upcoming Events</h3>
                        <p>Join us for our upcoming activities and fellowship events</p>
                        <?php if ($total_upcoming_events == 0): ?>
                            <p style="font-size: 12px; margin-top: 5px; opacity: 0.8;">Debug: No events found in database</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Featured Events Grid -->
            <div class="events-grid">
                <?php if (empty($upcoming_events)): ?>
                    <div class="no-events-message">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Upcoming Events</h3>
                        <p>Check back soon for new events and activities!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_events as $event): ?>
                        <div class="event-card <?php echo $event['is_pinned'] ? 'pinned-event' : ''; ?>">
                            <?php if ($event['is_pinned']): ?>
                                <div class="pinned-badge">
                                    <i class="fas fa-thumbtack"></i> Pinned
                                </div>
                            <?php endif; ?>
                            <img src="<?php echo getEventImage($event['title'], $event['event_image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" style="width: 100%; height: 200px; object-fit: cover;">
                            <div class="event-details">
                                <div class="event-category">
                                    <span class="category-badge"><?php echo htmlspecialchars($event['category']); ?></span>
                                </div>
                                <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                <div class="event-date-time">
                                    <p><i class="fas fa-calendar"></i> <?php echo date("F j, Y", strtotime($event['date'])); ?></p>
                                    <p><i class="fas fa-clock"></i> <?php echo date("h:i A", strtotime($event['time'])); ?></p>
                                </div>
                                <p class="event-description"><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : ''); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>


        </div>
    </div>
</section>

<section id="contact" class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Contact Us</h2>
        <div class="contact-info">
            <div class="contact-item">
                <i class="fas fa-map-marker-alt"></i>
                <h3>Address</h3>
                <p><?php echo htmlspecialchars($site_settings['church_address']); ?></p>
            </div>
            <div class="contact-item">
                <i class="fas fa-phone"></i>
                <h3>Phone</h3>
                <p><?php echo htmlspecialchars($site_settings['phone_number']); ?></p>
            </div>
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <h3>Email</h3>
                <p><?php echo htmlspecialchars($site_settings['email_address']); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Login Popup -->
<div class="login-popup" id="loginPopup" <?php echo !empty($login_error) ? 'style="display: flex;"' : ''; ?>>
    <div class="login-form-container">
        <span class="close-btn" onclick="hideLoginPopup()">&times;</span>
        <div class="church-logo">
            <img src="<?php echo htmlspecialchars($site_settings['church_logo']); ?>" alt="Church Logo">
        </div>
        <h2>Member Login</h2>

        <?php if (!empty($login_error)): ?>
            <div class="error">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="login-form">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <div class="input-icon">
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($stored_username); ?>" required autocomplete="username">
                    <i class="fas fa-user"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-icon">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <i class="fas fa-lock"></i>
                </div>
            </div>

            <input type="submit" value="Sign In" class="btn">
        </form>
    </div>
</div>

<footer>
    <div class="container">
        <div class="footer-content">
            <div class="footer-nav">
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#events">Events</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            <div class="social-links">
                <a href="https://web.facebook.com/cocdspc"><i class="fab fa-facebook"></i></a>
                <a href="https://www.instagram.com/kabataangcocd?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="><i class="fab fa-instagram"></i></a>
                <a href="https://www.youtube.com/@cocdspc1171"><i class="fab fa-youtube"></i></a>
            </div>
            <p>&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($site_settings['church_name']); ?>. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<script>
    function showLoginPopup() {
        document.getElementById('loginPopup').style.display = 'flex';
    }

    function hideLoginPopup() {
        document.getElementById('loginPopup').style.display = 'none';
    }

    // Close popup when clicking outside the form
    document.getElementById('loginPopup').addEventListener('click', function(e) {
        if (e.target === this) {
            hideLoginPopup();
        }
    });

    // Keep popup visible if there's an error
    <?php if (!empty($login_error)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('loginPopup').style.display = 'flex';
    });
    <?php endif; ?>
</script>
</body>
</html>