<?php
// Member Settings page
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

// Check if the user is a member
$is_member = ($_SESSION["user_role"] === "Member");

if (!$is_member) {
    header("Location: index.php");
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Process form submissions
$message = "";
$messageType = "";

// Check if profile was just updated
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $message = "Profile updated successfully!";
    $messageType = "success";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["update_profile"])) {
        $profile_data = [
            'username' => $_POST['username'],
            'full_name' => $_POST['full_name'],
            'email' => $_POST['email'],
            'contact_number' => $_POST['contact_number'],
            'address' => $_POST['address'],
            'profile_picture' => $user_profile['profile_picture'] // Keep existing picture by default
        ];

        // Handle profile picture reset
        if (isset($_POST['reset_profile_picture'])) {
            // Delete old profile picture file if it exists
            if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])) {
                unlink($user_profile['profile_picture']);
                error_log("Old profile picture deleted: " . $user_profile['profile_picture']);
            }
            $profile_data['profile_picture'] = ''; // Clear profile picture
            error_log("Profile picture reset requested");
        }
        // Handle profile picture upload
        else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $upload_result = handleFileUpload($_FILES['profile_picture'], 'uploads/profiles/');
            if ($upload_result['success']) {
                // Delete old profile picture if it exists
                if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])) {
                    unlink($user_profile['profile_picture']);
                }
                $profile_data['profile_picture'] = $upload_result['path'];
                // Debug: Log the profile picture path
                error_log("Profile picture uploaded: " . $upload_result['path']);
            } else {
                $message = $upload_result['message'];
                $messageType = "danger";
                // Debug: Log upload error
                error_log("Profile picture upload failed: " . $upload_result['message']);
            }
        }

        // Handle password change
        if (!empty($_POST['new_password'])) {
            // Check if current password is provided
            if (empty($_POST['current_password'])) {
                $message = "Current password is required to change password.";
                $messageType = "danger";
            } else {
                // Verify current password
                $current_password = $_POST['current_password'];
                if (md5($current_password) !== $user_profile['password']) {
                    $message = "Current password is incorrect.";
                    $messageType = "danger";
                } else {
                    // Check if new password and confirm password match
                    if ($_POST['new_password'] !== $_POST['confirm_new_password']) {
                        $message = "New password and confirm password do not match.";
                        $messageType = "danger";
                    } else {
                        // Hash the new password
                        $profile_data['password'] = md5($_POST['new_password']);
                    }
                }
            }
        }

        if (empty($message)) {
            // Check if username or email already exists for other users
            $check_sql = "SELECT * FROM user_profiles WHERE (username = ? OR email = ?) AND user_id != ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("sss", $profile_data['username'], $profile_data['email'], $user_profile['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $message = "Username or email already exists!";
                $messageType = "danger";
            } else {
                if (updateUserProfile($conn, $user_profile['user_id'], $profile_data)) {
                    $_SESSION["user"] = $profile_data['username'];
                    $_SESSION["user_email"] = $profile_data['email'];
                    $message = "Profile updated successfully!";
                    $messageType = "success";
                    // Refresh user profile
                    $user_profile = getUserProfile($conn, $_SESSION["user"]);
                    
                    // Debug: Log the updated profile data
                    error_log("Profile updated successfully. New profile picture: " . $profile_data['profile_picture']);
                    
                    // Redirect to refresh the page and show updated profile picture
                    header("Location: member_settings.php?updated=1");
                    exit;
                } else {
                    $message = "Failed to update profile.";
                    $messageType = "danger";
                    // Debug: Log update failure
                    error_log("Profile update failed for user: " . $user_profile['user_id']);
                }
            }
        }
    }
    
    // Handle profile picture reset separately
    if (isset($_POST["reset_profile_picture"])) {
        // Delete old profile picture file if it exists
        if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])) {
            unlink($user_profile['profile_picture']);
            error_log("Old profile picture deleted: " . $user_profile['profile_picture']);
        }
        
        // Update database to clear profile picture
        $sql = "UPDATE user_profiles SET profile_picture = '', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $user_profile['user_id']);
        
        if ($stmt->execute()) {
            $message = "Profile picture reset successfully!";
            $messageType = "success";
            // Refresh user profile
            $user_profile = getUserProfile($conn, $_SESSION["user"]);
            error_log("Profile picture reset successfully");
            
            // Redirect to refresh the page
            header("Location: member_settings.php?updated=1");
            exit;
        } else {
            $message = "Failed to reset profile picture.";
            $messageType = "danger";
            error_log("Profile picture reset failed for user: " . $user_profile['user_id']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Settings | <?php echo $church_name; ?></title>
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
            padding: 20px;
            margin-left: 0 !important;
            max-width: 100%;
            width: 100%;
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
        
        .settings-content {
            margin-top: 20px;
        }
        
        .tab-navigation {
            display: none;
        }
        
        .tab-content {
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: var(--white);
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: rgb(0, 112, 9);
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
        }
        
        .btn-outline:hover {
            background-color: var(--accent-color);
            color: var(--white);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            transition: opacity 0.3s ease;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }
        
        .info-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .info-box p {
            margin-bottom: 8px;
        }
        
        .info-box p:last-child {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
            .form-row {
                flex-direction: column;
                gap: 0;
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
            .tab-navigation {
                flex-direction: column;
            }
            .tab-navigation a {
                padding: 10px;
            }
        }
        /* Drawer Navigation CSS (from member_collection.php) */
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
        @media (max-width: 992px) {
            .content-area {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Drawer Navigation Markup (from member_collection.php) -->
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
                    <div class="role"><?php echo htmlspecialchars($user_profile['role'] ?? 'Member'); ?></div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        <div id="drawer-overlay" class="drawer-overlay"></div>
        <!-- Drawer Navigation JS (from member_collection.php) -->
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
        <main class="content-area">
            <?php
            $live_message = getLiveMessage($conn);
            if ($live_message):
            ?>
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
                    <h2>Settings</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>" id="message-alert">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="settings-content">
                <!-- Tab navigation removed -->
                
                <div class="tab-content">
                    <div class="tab-pane active" id="profile-settings">
                        <h3>Profile Settings</h3>
                        <p>Update your profile details and picture.</p>
                        
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user_profile['username']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="full_name">Full Name</label>
                                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user_profile['full_name']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_profile['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="contact_number">Contact Number</label>
                                        <input type="text" id="contact_number" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($user_profile['contact_number']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($user_profile['address']); ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Current Profile Picture</label>
                                        <div class="current-profile-picture">
                                            <?php if (!empty($user_profile['profile_picture'])): ?>
                                                <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture" style="max-width: 200px; border-radius: 50%; margin: 10px 0;">
                                            <?php else: ?>
                                                <div class="default-avatar" style="width: 200px; height: 200px; background-color: var(--accent-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 72px; margin: 10px 0;">
                                                    <?php echo strtoupper(substr($user_profile['username'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="profile_picture">Upload New Profile Picture</label>
                                        <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/*">
                                        <small class="form-text text-muted">Recommended size: 200x200 pixels. Maximum file size: 5MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" class="form-control">
                                        <small class="form-text text-muted">Required to change password</small>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" id="new_password" name="new_password" class="form-control">
                                        <small class="form-text text-muted">Leave blank to keep current password</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="confirm_new_password">Confirm New Password</label>
                                        <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Account Information</label>
                                        <div class="info-box">
                                            <p><strong>Role:</strong> <?php echo htmlspecialchars($user_profile['role']); ?></p>
                                            <p><strong>Account Created:</strong> <?php echo date('F j, Y', strtotime($user_profile['created_at'])); ?></p>
                                            <p><strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($user_profile['updated_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn" name="update_profile">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                        
                        <!-- Separate form for reset profile picture -->
                        <form action="" method="post" style="margin-top: 10px;">
                            <button type="submit" class="btn btn-outline" name="reset_profile_picture">
                                <i class="fas fa-undo"></i> Reset Profile Picture
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success messages after 3 seconds
            const messageAlert = document.getElementById('message-alert');
            if (messageAlert) {
                setTimeout(function() {
                    messageAlert.style.opacity = '0';
                    setTimeout(function() {
                        messageAlert.style.display = 'none';
                    }, 300);
                }, 3000);
            }
            
            // Tab navigation
            const tabLinks = document.querySelectorAll('.tab-navigation a');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    tabLinks.forEach(function(link) {
                        link.classList.remove('active');
                    });
                    
                    // Hide all tab panes
                    tabPanes.forEach(function(pane) {
                        pane.classList.remove('active');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show the corresponding tab pane
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            

        });
    </script>
</body>
</html> 