<?php
// Admin Settings page
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

// Check if the user is an admin
$is_admin = ($_SESSION["user_role"] === "Administrator");

if (!$is_admin) {
    header("Location: dashboard.php");
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
                    header("Location: admin_settings.php?updated=1");
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
            header("Location: admin_settings.php?updated=1");
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
    <title>Admin Settings | <?php echo $church_name; ?></title>
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
        /* --- Drawer Navigation Styles (EXACT from superadmin_dashboard.php) --- */
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
        .content-area {
            flex: 1;
            padding: 20px 24px 24px 24px;
            margin-left: 0;
            transition: margin-left 0.3s;
        }
        /* Responsive Drawer Navigation (EXACT from superadmin_dashboard.php) */
        @media (max-width: 900px) {
            .custom-drawer {
                left: -300px;
                width: 80vw;
                min-width: 200px;
                max-width: 320px;
            }
            .custom-drawer.open {
                left: 0;
            }
            .content-area {
                margin-left: 0;
            }
            .nav-toggle-container {
                display: block;
            }
        }
        @media (max-width: 700px) {
            .custom-drawer {
                width: 90vw;
                min-width: 180px;
                max-width: 98vw;
            }
            .drawer-link {
                padding: 12px 10px;
                font-size: 14px;
            }
            .drawer-header {
                padding: 14px 10px 8px 10px;
            }
            .drawer-profile {
                padding: 16px 10px 10px 10px;
            }
        }
        /* --- Settings Content Styles (match member_settings.php) --- */
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
            margin-top: 0;
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
                        <a href="dashboard.php" class="drawer-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_events.php" class="drawer-link <?php echo $current_page == 'admin_events.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_prayers.php" class="drawer-link <?php echo $current_page == 'admin_prayers.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hands-praying"></i>
                            <span>Prayer Requests</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_messages.php" class="drawer-link <?php echo $current_page == 'admin_messages.php' ? 'active' : ''; ?>">
                            <i class="fas fa-video"></i>
                            <span>Messages</span>
                        </a>
                    </li>
                    <li>
                        <a href="financialreport.php" class="drawer-link <?php echo $current_page == 'financialreport.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Financial Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="member_contributions.php" class="drawer-link <?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list-alt"></i>
                            <span>Stewardship Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_settings.php" class="drawer-link <?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="drawer-profile">
                <div class="avatar">
                    <?php if (!empty($user_profile['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_profile['username'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <div class="name"><?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username'] ?? 'Unknown User'); ?></div>
                    <div class="role"><?php echo htmlspecialchars($_SESSION['user_role']); ?></div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        <!-- Drawer Overlay -->
        <div id="drawer-overlay" class="drawer-overlay"></div>
        
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
                <a href="messages.php?message=0" class="live-alert-btn">
                  <svg width="13" height="13" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 14"><path d="M10 0C4.612 0 0 5.336 0 7c0 1.742 3.546 7 10 7 6.454 0 10-5.258 10-7 0-1.664-4.612-7-10-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>
                  View Live
                </a>
                <button type="button" class="live-alert-btn live-alert-dismiss" aria-label="Close" onclick="this.closest('.live-alert').style.display='none';">Dismiss</button>
              </div>
            </div>
            <?php endif; ?>
            <div class="top-bar">
                <div>
                    <h2>Admin Settings</h2>
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
                        <h3 style="background: none; color: inherit;">Profile Settings</h3>
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
        // Custom Drawer Navigation JavaScript (EXACT from superadmin_dashboard.php)
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