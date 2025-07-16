<?php
// Admin Prayer Requests page
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

// Check if the user is a super admin only (Admin/Pastor should not access this)
$is_super_admin = ($_SESSION["user_role"] === "Super Admin");

if (!$is_super_admin) {
    if ($_SESSION["user_role"] === "Administrator" || $_SESSION["user_role"] === "Pastor") {
        header("Location: dashboard.php");
    } else {
        header("Location: member_dashboard.php");
    }
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Handle prayer request deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_prayer"])) {
    $prayer_id = $_POST["prayer_id"];
    
    $sql = "DELETE FROM prayer_requests WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prayer_id);
    
    if ($stmt->execute()) {
        $message = "Prayer request deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Error deleting prayer request. Please try again.";
        $messageType = "danger";
    }
}

// Handle prayer request submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_prayer"])) {
    $category = $_POST["prayer_category"] ?? "";
    $request = trim($_POST["prayer_request"] ?? "");
    $urgency = $_POST["prayer_urgency"] ?? "normal";
    $anonymous = isset($_POST["anonymous"]);
    
    if (!empty($category) && !empty($request)) {
        // Use full name if available, otherwise username
        $member_name = $anonymous ? "Anonymous" : ($user_profile['full_name'] ?? ($user_profile['username'] ?? ($_SESSION["user"] ?? "Unknown User")));
        
        $sql = "INSERT INTO prayer_requests (member_name, prayer_request, category, urgency, anonymous) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $member_name, $request, $category, $urgency, $anonymous);
        
        if ($stmt->execute()) {
            $message = "Prayer request submitted successfully!";
            $messageType = "success";
        } else {
            $message = "Error submitting prayer request. Please try again.";
            $messageType = "danger";
        }
    } else {
        $message = "Please fill in all required fields.";
        $messageType = "danger";
    }
}

// Handle reaction updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["react"])) {
    $prayer_id = $_POST["prayer_id"];
    $reaction_type = $_POST["reaction_type"];
    
    // Map reaction type to column name
    $column_map = [
        'heart' => 'heart_reactions',
        'praying' => 'praying_reactions',
        'like' => 'like_reactions'
    ];
    
    if (isset($column_map[$reaction_type])) {
        $column = $column_map[$reaction_type];
        $sql = "UPDATE prayer_requests SET $column = $column + 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $prayer_id);
        
        if ($stmt->execute()) {
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to update reaction']);
    exit;
}

// Get prayer requests from database
$sql = "SELECT * FROM prayer_requests ORDER BY created_at DESC";
$result = $conn->query($sql);
$prayer_requests = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $prayer_requests[] = [
            "id" => $row['id'],
            "member" => $row['member_name'],
            "request" => $row['prayer_request'],
            "category" => $row['category'],
            "urgency" => $row['urgency'],
            "anonymous" => $row['anonymous'],
            "date" => $row['created_at'],
            "reactions" => [
                "heart" => $row['heart_reactions'],
                "praying" => $row['praying_reactions'],
                "like" => $row['like_reactions']
            ]
        ];
    }
}

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Prayer Requests | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a3a3a;
            --accent-color: rgb(0, 139, 30);
            --light-gray: #d0d0d0;
            --white: #ffffff;
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
            margin-left: 0;
            padding: 20px;
            padding-top: 80px;
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
        
        .prayer-content {
            margin-top: 20px;
        }
        
        .card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card h3 {
            margin-bottom: 15px;
            font-size: 18px;
            color: var(--primary-color);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
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
        
        /* Prayer Request Cards */
        .prayer-request {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--accent-color);
            position: relative;
        }
        
        .prayer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .prayer-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .prayer-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .prayer-category {
            background-color: rgba(0, 139, 30, 0.1);
            color: var(--accent-color);
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .urgency-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .urgency-normal {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--info-color);
        }
        
        .urgency-urgent {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }
        
        .urgency-emergency {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }
        
        .prayer-content-text {
            margin-bottom: 15px;
            line-height: 1.6;
            color: #333;
        }
        
        .prayer-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .prayer-date {
            font-size: 12px;
            color: #999;
        }
        
        /* Reaction Buttons */
        .reaction-buttons {
            display: flex;
            gap: 10px;
        }
        
        .reaction-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background-color: #f8f9fa;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .reaction-btn:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }
        
        .reaction-btn.heart {
            border-color: #ff6b6b;
        }
        
        .reaction-btn.heart:hover {
            background-color: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
        }
        
        .reaction-btn.praying {
            border-color: #4ecdc4;
        }
        
        .reaction-btn.praying:hover {
            background-color: rgba(78, 205, 196, 0.1);
            color: #4ecdc4;
        }
        
        .reaction-btn.like {
            border-color: #45b7d1;
        }
        
        .reaction-btn.like:hover {
            background-color: rgba(69, 183, 209, 0.1);
            color: #45b7d1;
        }
        
        .reaction-count {
            font-weight: 500;
        }

        /* Delete Button */
        .delete-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .delete-btn:hover {
            background-color: #d32f2f;
            transform: scale(1.1);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: 10px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background-color: #f0f0f0;
            color: var(--primary-color);
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
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

        .btn-danger {
            background-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #d32f2f;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Action Button */
        .action-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 139, 30, 0.2);
        }

        .btn-primary:hover {
            background-color: rgb(0, 112, 9);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 139, 30, 0.3);
        }

        /* Form Styling */
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

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .urgency-levels {
            display: flex;
            gap: 20px;
        }

        .urgency-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .urgency-option input[type="radio"] {
            margin: 0;
        }

        .urgency-label {
            font-size: 14px;
        }

        .urgency-label.urgent {
            color: var(--warning-color);
        }

        .urgency-label.emergency {
            color: var(--danger-color);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .checkbox-label input[type="checkbox"] {
            margin: 0;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 992px) {
            .content-area {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
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
            .prayer-header {
                flex-direction: column;
                gap: 10px;
            }
            .prayer-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
    <script>
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
                        <a href="prayers.php" class="drawer-link <?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hands-praying"></i>
                            <span>Prayer Requests</span>
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
                    <div class="role">Super Admin</div>
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
                    <h2>Prayer Requests Management</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>
            
            <div class="prayer-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="action-bar">
                    <button class="btn-primary" onclick="openPrayerModal()">
                        <i class="fas fa-plus-circle"></i>
                        Submit a Prayer Request
                    </button>
                </div>

                <!-- Prayer Requests List -->
                <div class="card">
                    <h3><i class="fas fa-hands-praying"></i> Prayer Requests from Members</h3>
                    <?php if (empty($prayer_requests)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No prayer requests yet.</p>
                    <?php else: ?>
                        <?php foreach ($prayer_requests as $prayer): ?>
                            <div class="prayer-request">
                                <button class="delete-btn" onclick="openDeleteModal(<?php echo $prayer['id']; ?>, '<?php echo htmlspecialchars($prayer['member']); ?>')" title="Delete Prayer Request">
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <div class="prayer-header">
                                    <div>
                                        <div class="prayer-meta">
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($prayer['member']); ?></span>
                                            <span class="prayer-category"><?php echo htmlspecialchars($prayer['category']); ?></span>
                                            <span class="urgency-badge urgency-<?php echo $prayer['urgency']; ?>">
                                                <?php echo ucfirst($prayer['urgency']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="prayer-content-text">
                                    <?php echo nl2br(htmlspecialchars($prayer['request'])); ?>
                                </div>
                                
                                <div class="prayer-footer">
                                    <div class="prayer-date">
                                        <i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($prayer['date'])); ?>
                                    </div>
                                    
                                    <div class="reaction-buttons">
                                        <button class="reaction-btn heart" onclick="react(<?php echo $prayer['id']; ?>, 'heart', event)">
                                            <i class="fas fa-heart"></i>
                                            <span class="reaction-count"><?php echo $prayer['reactions']['heart']; ?></span>
                                        </button>
                                        <button class="reaction-btn praying" onclick="react(<?php echo $prayer['id']; ?>, 'praying', event)">
                                            <i class="fas fa-hands-praying"></i>
                                            <span class="reaction-count"><?php echo $prayer['reactions']['praying']; ?></span>
                                        </button>
                                        <button class="reaction-btn like" onclick="react(<?php echo $prayer['id']; ?>, 'like', event)">
                                            <i class="fas fa-thumbs-up"></i>
                                            <span class="reaction-count"><?php echo $prayer['reactions']['like']; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Prayer Request Modal -->
    <div class="modal" id="prayerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Submit a Prayer Request</h3>
                <button class="close-btn" onclick="closePrayerModal()">&times;</button>
            </div>
            <form action="" method="post">
                <div class="form-group">
                    <label for="prayer_category">Category</label>
                    <select id="prayer_category" name="prayer_category" class="form-control" required>
                        <option value="">Select a category</option>
                        <option value="Personal">Personal</option>
                        <option value="Family">Family</option>
                        <option value="Health">Health</option>
                        <option value="Financial">Financial</option>
                        <option value="Spiritual">Spiritual</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prayer_request">Your Prayer Request</label>
                    <textarea id="prayer_request" name="prayer_request" class="form-control" placeholder="Please share your prayer request in detail. We are here to support you in prayer." required rows="6"></textarea>
                    <small class="form-text">Your prayer request will be kept confidential and shared only with the prayer team.</small>
                </div>

                <div class="form-group">
                    <label for="prayer_urgency">Urgency Level</label>
                    <div class="urgency-levels">
                        <label class="urgency-option">
                            <input type="radio" name="prayer_urgency" value="normal" checked>
                            <span class="urgency-label">Normal</span>
                        </label>
                        <label class="urgency-option">
                            <input type="radio" name="prayer_urgency" value="urgent">
                            <span class="urgency-label urgent">Urgent</span>
                        </label>
                        <label class="urgency-option">
                            <input type="radio" name="prayer_urgency" value="emergency">
                            <span class="urgency-label emergency">Emergency</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="anonymous" id="anonymous">
                        <span>Submit anonymously</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn" name="submit_prayer">
                        <i class="fas fa-paper-plane"></i> Submit Prayer Request
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Clear Form
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div style="margin-bottom: 20px;">
                <p>Are you sure you want to delete this prayer request?</p>
                <p><strong>Member:</strong> <span id="delete-member-name"></span></p>
                <p style="color: var(--danger-color); font-weight: 600;">⚠️ This action cannot be undone.</p>
            </div>
            <form method="post" id="delete-form">
                <input type="hidden" name="prayer_id" id="delete-prayer-id">
                <input type="hidden" name="delete_prayer" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Prayer Request
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function react(prayerId, reactionType, event) {
            const formData = new FormData();
            formData.append('react', '1');
            formData.append('prayer_id', prayerId);
            formData.append('reaction_type', reactionType);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the reaction count
                    const button = event.target.closest('.reaction-btn');
                    const countSpan = button.querySelector('.reaction-count');
                    const currentCount = parseInt(countSpan.textContent);
                    countSpan.textContent = currentCount + 1;
                    
                    // Add visual feedback
                    button.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        button.style.transform = 'scale(1)';
                    }, 200);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Modal functions
        function openDeleteModal(prayerId, memberName) {
            document.getElementById('delete-prayer-id').value = prayerId;
            document.getElementById('delete-member-name').textContent = memberName;
            document.getElementById('deleteModal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });

        // Modal functions for prayer request
        function openPrayerModal() {
            document.getElementById('prayerModal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closePrayerModal() {
            document.getElementById('prayerModal').classList.remove('active');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close prayer modal when clicking outside
        document.getElementById('prayerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePrayerModal();
            }
        });

        // Auto-hide success notifications after 2 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlerts = document.querySelectorAll('.alert-success');
            successAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 2000);
            });
        });
    </script>
</body>
</html>