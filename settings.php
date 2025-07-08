<?php
// Settings page for user management and profile settings
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "Administrator") {
    header("Location: login.php");
    exit;
}

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Process form submissions
$message = "";
$messageType = "";

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = [];

// Add this new PHP endpoint at the top of the file after the session checks
if (isset($_GET['ajax_search'])) {
    $search_query = trim($_GET['search']);
    $users = [];
    
    if (!empty($search_query)) {
        $search_sql = "SELECT user_id, username, full_name, email, contact_number, address, role, created_at FROM user_profiles WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
        $search_param = "%$search_query%";
        $stmt = $conn->prepare($search_sql);
        $stmt->bind_param("sss", $search_param, $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => $row['user_id'],
                'username' => $row['username'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'contact_number' => $row['contact_number'],
                'address' => $row['address'],
                'role' => $row['role'],
                'created_at' => $row['created_at']
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

if (!empty($search_query)) {
    $search_sql = "SELECT user_id, username, full_name, email, contact_number, address, role, created_at FROM user_profiles WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
    $search_param = "%$search_query%";
    $stmt = $conn->prepare($search_sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['user_id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'contact_number' => $row['contact_number'],
            'address' => $row['address'],
            'role' => $row['role'],
            'created_at' => $row['created_at']
        ];
    }
} else {
    // Get all users
    $sql = "SELECT user_id, username, full_name, email, contact_number, address, role, created_at FROM user_profiles";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['user_id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'contact_number' => $row['contact_number'],
            'address' => $row['address'],
            'role' => $row['role'],
            'created_at' => $row['created_at']
        ];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add_user"])) {
        $username = trim($_POST["new_username"]);
        $full_name = trim($_POST["new_full_name"]);
        $email = trim($_POST["new_email"]);
        $contact_number = trim($_POST["new_contact_number"]);
        $address = trim($_POST["new_address"]);
        $password = password_hash($_POST["new_password"], PASSWORD_DEFAULT);
        $role = $_POST["new_role"];
        $user_id = strtolower(str_replace(' ', '', $username)) . rand(100, 999); // Generate a unique user_id

        // Check if username or email already exists
        $check_sql = "SELECT * FROM user_profiles WHERE username = ? OR email = ? OR user_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("sss", $username, $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "Username, email, or generated ID already exists!";
            $messageType = "danger";
        } else {
            // Insert new user
            $insert_sql = "INSERT INTO user_profiles (user_id, username, full_name, email, contact_number, address, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssssssss", $user_id, $username, $full_name, $email, $contact_number, $address, $password, $role);
            
            if ($stmt->execute()) {
                $message = "User added successfully!";
                $messageType = "success";
            } else {
                $message = "Error adding user: " . $conn->error;
                $messageType = "danger";
            }
        }
    } elseif (isset($_POST["edit_user"])) {
        $user_id = $_POST["edit_user_id"];
        $username = trim($_POST["edit_username"]);
        $full_name = trim($_POST["edit_full_name"]);
        $email = trim($_POST["edit_email"]);
        $contact_number = trim($_POST["edit_contact_number"]);
        $address = trim($_POST["edit_address"]);
        $role = $_POST["edit_role"];

        // Check if username or email already exists for other users
        $check_sql = "SELECT * FROM user_profiles WHERE (username = ? OR email = ?) AND user_id != ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("sss", $username, $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "Username or email already exists!";
            $messageType = "danger";
        } else {
            // Update user
            $update_sql = "UPDATE user_profiles SET username = ?, full_name = ?, email = ?, contact_number = ?, address = ?, role = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sssssss", $username, $full_name, $email, $contact_number, $address, $role, $user_id);
            
            if ($stmt->execute()) {
        $message = "User updated successfully!";
        $messageType = "success";
            } else {
                $message = "Error updating user: " . $conn->error;
                $messageType = "danger";
            }
        }
    } elseif (isset($_POST["delete_user"])) {
        $user_id = $_POST["delete_user_id"];
        
        // Don't allow deleting the admin account
        if ($user_id === 'admin') {
            $message = "Cannot delete the admin account!";
            $messageType = "danger";
        } else {
            $delete_sql = "DELETE FROM user_profiles WHERE user_id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("s", $user_id);
            
            if ($stmt->execute()) {
        $message = "User deleted successfully!";
        $messageType = "success";
        } else {
                $message = "Error deleting user: " . $conn->error;
            $messageType = "danger";
        }
        }
    } elseif (isset($_POST["update_profile"])) {
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
            $profile_data['profile_picture'] = ''; // Clear profile picture
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
            } else {
                $message = $upload_result['message'];
                $messageType = "danger";
            }
        }

        if (empty($message)) {
            if (updateUserProfile($conn, $_SESSION["user"], $profile_data)) {
                $_SESSION["user"] = $profile_data['username'];
                $_SESSION["user_email"] = $profile_data['email'];
            $message = "Profile updated successfully!";
            $messageType = "success";
                // Refresh user profile
                $user_profile = getUserProfile($conn, $_SESSION["user"]);
            } else {
                $message = "Failed to update profile.";
                $messageType = "danger";
            }
        }
    } elseif (isset($_POST["reset_icon"])) {
        // Reset profile icon
        $_SESSION["profile_icon"] = "";
        $message = "Profile icon reset to default.";
        $messageType = "success";
    } elseif (isset($_POST["save_site_settings"])) {
        $settings = [
            'church_name' => $_POST['church_name'],
            'tagline' => $_POST['tagline'],
            'tagline2' => $_POST['tagline2'],
            'about_us' => $_POST['about_us'],
            'church_address' => $_POST['address'],
            'phone_number' => $_POST['phone'],
            'email_address' => $_POST['email'],
            'church_logo' => $_POST['current_logo'],
            'login_background' => $_POST['current_background'],
            'service_times' => json_encode([
                'Sunday Worship Service' => $_POST['sunday_service'],
                'Prayer Intercession' => $_POST['prayer_service']
            ])
        ];

        // Handle logo upload
        if (isset($_FILES['church_logo']) && $_FILES['church_logo']['error'] == UPLOAD_ERR_OK) {
            $upload_result = handleFileUpload($_FILES['church_logo'], 'uploads/logo/');
            if ($upload_result['success']) {
                $settings['church_logo'] = $upload_result['path'];
            } else {
                $message = $upload_result['message'];
                $messageType = "danger";
            }
        }

        // Handle background upload
        if (isset($_FILES['login_background']) && $_FILES['login_background']['error'] == UPLOAD_ERR_OK) {
            $upload_result = handleFileUpload($_FILES['login_background'], 'uploads/background/');
            if ($upload_result['success']) {
                $settings['login_background'] = $upload_result['path'];
            } else {
                $message = $upload_result['message'];
                $messageType = "danger";
            }
        }

        if (empty($message)) {
            if (updateSiteSettings($conn, $settings)) {
                $message = "Site settings updated successfully!";
                $messageType = "success";
            } else {
                $message = "Failed to update site settings.";
                $messageType = "danger";
            }
        }
    }
}

// Get current site settings
$site_settings = getSiteSettings($conn);
$service_times = json_decode($site_settings['service_times'], true);
$church_logo = getChurchLogo($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
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
            display: flex;
            background-color: var(--white);
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .tab-navigation a {
            flex: 1;
            text-align: center;
            padding: 15px;
            color: var(--primary-color);
            text-decoration: none;
            transition: background-color 0.3s;
            font-weight: 500;
        }
        
        .tab-navigation a.active {
            background-color: var(--accent-color);
            color: var(--white);
        }
        
        .tab-navigation a:hover:not(.active) {
            background-color: #f0f0f0;
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
        
        /* Prevent table movement */
        #users-table {
            animation: none !important;
            transition: none !important;
            transform: none !important;
        }
        
        #users-table * {
            animation: none !important;
            transition: none !important;
            transform: none !important;
        }
        
        .table-responsive {
            animation: none !important;
            transition: none !important;
            transform: translateZ(0) !important;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
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
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            min-height: 400px;
            transform: translateZ(0);
            will-change: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
            table-layout: fixed;
            position: relative;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        table tr {
            position: relative;
        }
        
        table tr:hover {
            background-color: #f5f5f5;
        }
        
        .address-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .address-cell:hover {
            white-space: normal;
            word-wrap: break-word;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }
        
        .status-inactive {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }
        
        .status-pending {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            color: white;
        }
        
        .action-btn.edit-btn {
            background-color: #4a90e2;
        }
        
        .action-btn.edit-btn:hover {
            background-color: #357abd;
        }
        
        .action-btn.delete-btn {
            background-color: #e74c3c;
        }
        
        .action-btn.delete-btn:hover {
            background-color: #c0392b;
        }
        
        .action-btn i {
            font-size: 14px;
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow: auto;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: var(--white);
            border-radius: 5px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            padding: 20px;
            position: relative;
            margin: 20px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eeeeee;
        }
        
        .modal-header h3 {
            font-size: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eeeeee;
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
        
        .alert-warning {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }
        
        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            margin: 0 5px;
            border-radius: 5px;
            background-color: #f0f0f0;
            color: var(--primary-color);
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .pagination a:hover {
            background-color: #e0e0e0;
        }
        
        .pagination a.active {
            background-color: var(--accent-color);
            color: var(--white);
        }
        
        .icon-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .icon-option {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.3s;
        }
        
        .icon-option img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .icon-option input {
            display: none;
        }
        
        .icon-option input:checked + img {
            border: 2px solid var(--accent-color);
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
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .tab-navigation a {
                padding: 10px;
            }
            
            .icon-selector {
                justify-content: center;
            }
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
        
        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-badge.administrator {
            background-color: #4a90e2;
            color: white;
        }

        .role-badge.pastor {
            background-color: #2ecc71;
            color: white;
        }

        .role-badge.member {
            background-color: #95a5a6;
            color: white;
        }

        .text-success {
            color: #4CAF50;
        }

        .text-danger {
            color: #F44336;
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
                    <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="events.php" class="<?php echo $current_page == 'events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i> <span>Messages</span></a></li>
                    <li><a href="member_records.php" class="<?php echo $current_page == 'member_records.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span>Member Records</span></a></li>
                    <li><a href="prayers.php" class="<?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                    <li><a href="financialreport.php" class="<?php echo $current_page == 'financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span>Financial Reports</span></a></li>
                    <li><a href="member_contributions.php" class="<?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-dollar"></i> <span>Stewardship Report</span></a></li>
                    <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                </ul>
            </div>
        </aside>
        
        <main class="content-area">
            <div class="top-bar">
                <h2>Settings</h2>
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
                        <p><?php echo htmlspecialchars($user_profile['role'] ?? 'Administrator'); ?></p>
                    </div>
                    <form action="logout.php" method="post">
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="settings-content">
                <div class="tab-navigation">
                    <a href="#user-management" class="active" data-tab="user-management">User Management</a>
                    <a href="#site-settings" data-tab="site-settings">Site Settings</a>
                    <a href="#profile-settings" data-tab="profile-settings">Profile Settings</a>
                </div>
                
                <div class="tab-content">
                    <div class="tab-pane active" id="user-management">
                        <div class="action-bar">
                            <!-- Removed custom search box, DataTables provides its own search UI -->
                        </div>
                        <div style="margin-bottom: 15px; text-align: right;">
                            <button class="btn" id="add-user-btn">
                                <i class="fas fa-user-plus"></i> Add New User
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Contact Number</th>
                                        <th>Address</th>
                                        <th>Role</th>
                                        <th>Created On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['id'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($user['contact_number'] ?? ''); ?></td>
                                            <td class="address-cell"><?php echo htmlspecialchars($user['address'] ?? ''); ?></td>
                                            <td>
                                                <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                                    <?php echo htmlspecialchars($user['role'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td><?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : ''; ?></td>
                                            <td>
                                                <?php if (isset($user['id']) && $user['id'] !== 'admin'): ?>
                                                <div class="action-buttons">
                                                        <button type="button" class="action-btn edit-btn" onclick="editUser('<?php echo htmlspecialchars($user['id']); ?>', '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['contact_number']); ?>', '<?php echo htmlspecialchars($user['address']); ?>', '<?php echo htmlspecialchars($user['role']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                        <button type="button" class="action-btn delete-btn" onclick="deleteUser('<?php echo htmlspecialchars($user['id']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Removed Roles & Permissions tab-pane -->
                    
                    <div class="tab-pane" id="site-settings">
                        <h3>Site Settings</h3>
                        <p>Configure general website settings.</p>
                        
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="church_name">Church Name</label>
                                <input type="text" id="church_name" name="church_name" class="form-control" value="<?php echo htmlspecialchars($site_settings['church_name']); ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="tagline">Tagline</label>
                                        <input type="text" id="tagline" name="tagline" class="form-control" value="<?php echo htmlspecialchars($site_settings['tagline']); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="tagline2">Secondary Tagline</label>
                                        <input type="text" id="tagline2" name="tagline2" class="form-control" value="<?php echo htmlspecialchars($site_settings['tagline2']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="about_us">About Us</label>
                                <textarea id="about_us" name="about_us" class="form-control" rows="4"><?php echo htmlspecialchars($site_settings['about_us']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Church Address</label>
                                <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($site_settings['church_address']); ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($site_settings['phone_number']); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($site_settings['email_address']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="sunday_service">Sunday Worship Service Time</label>
                                        <input type="text" id="sunday_service" name="sunday_service" class="form-control" value="<?php echo htmlspecialchars($service_times['Sunday Worship Service']); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="prayer_service">Prayer Intercession Time</label>
                                        <input type="text" id="prayer_service" name="prayer_service" class="form-control" value="<?php echo htmlspecialchars($service_times['Prayer Intercession']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="church_logo">Church Logo</label>
                                        <input type="file" id="church_logo" name="church_logo" class="form-control" accept="image/*">
                                        <input type="hidden" name="current_logo" value="<?php echo htmlspecialchars($site_settings['church_logo']); ?>">
                                        <?php if (!empty($site_settings['church_logo'])): ?>
                                            <div class="current-image">
                                                <img src="<?php echo htmlspecialchars($site_settings['church_logo']); ?>" alt="Current Logo" style="max-width: 200px; margin-top: 10px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="login_background">Login Background</label>
                                        <input type="file" id="login_background" name="login_background" class="form-control" accept="image/*">
                                        <input type="hidden" name="current_background" value="<?php echo htmlspecialchars($site_settings['login_background']); ?>">
                                        <?php if (!empty($site_settings['login_background'])): ?>
                                            <div class="current-image">
                                                <img src="<?php echo htmlspecialchars($site_settings['login_background']); ?>" alt="Current Background" style="max-width: 200px; margin-top: 10px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn" name="save_site_settings">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                    
                    <div class="tab-pane" id="profile-settings">
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
                                        <label>Account Information</label>
                                        <div class="info-box">
                                            <p><strong>Role:</strong> <?php echo htmlspecialchars($user_profile['role']); ?></p>
                                            <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user_profile['created_at'])); ?></p>
                                            <p><strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($user_profile['updated_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                            <button type="submit" class="btn" name="update_profile">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                                <button type="submit" class="btn btn-outline" name="reset_profile_picture">
                                    <i class="fas fa-undo"></i> Reset Profile Picture
                            </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal" id="add-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="modal-close">×</button>
            </div>
            <form action="" method="post">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_username">Username</label>
                            <input type="text" id="new_username" name="new_username" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_full_name">Full Name</label>
                            <input type="text" id="new_full_name" name="new_full_name" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_email">Email</label>
                            <input type="email" id="new_email" name="new_email" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_contact_number">Contact Number</label>
                            <input type="text" id="new_contact_number" name="new_contact_number" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_address">Address</label>
                    <textarea id="new_address" name="new_address" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_password">Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_confirm_password">Confirm Password</label>
                            <input type="password" id="new_confirm_password" name="new_confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_role">Role</label>
                    <select id="new_role" name="new_role" class="form-control" required>
                        <option value="Member">Member</option>
                        <option value="Pastor">Pastor</option>
                        <option value="Administrator">Administrator</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
                    <button type="submit" class="btn" name="add_user">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal" id="edit-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="modal-close">×</button>
            </div>
            <form action="" method="post">
                <input type="hidden" id="edit_user_id" name="edit_user_id">
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="edit_username" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_full_name">Full Name</label>
                            <input type="text" id="edit_full_name" name="edit_full_name" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="edit_email" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_contact_number">Contact Number</label>
                            <input type="text" id="edit_contact_number" name="edit_contact_number" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_address">Address</label>
                    <textarea id="edit_address" name="edit_address" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_role">Role</label>
                            <select id="edit_role" name="edit_role" class="form-control" required>
                                <option value="Member">Member</option>
                                <option value="Pastor">Pastor</option>
                                <option value="Administrator">Administrator</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
                    <button type="submit" class="btn" name="edit_user">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete User Confirmation Modal -->
    <div class="modal" id="delete-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
                <button class="modal-close">×</button>
            </div>
            <form action="" method="post">
                <input type="hidden" id="delete_user_id" name="delete_user_id">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
                    <button type="submit" class="btn" style="background-color: var(--danger-color);" name="delete_user">Delete User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
<<<<<<< HEAD
            // DataTable initialization for users table
            if (window.jQuery) {
                $('#users-table').DataTable({
                    columnDefs: [
                        { width: '7%', targets: 0 }, // ID
                        { width: '12%', targets: 1 }, // Username
                        { width: '15%', targets: 2 }, // Full Name
                        { width: '15%', targets: 3 }, // Email
                        { width: '12%', targets: 4 }, // Contact Number
                        { width: '15%', targets: 5 }, // Address
                        { width: '8%', targets: 6 }, // Role
                        { width: '10%', targets: 7 }, // Created On
                        { width: '8%', targets: 8 }  // Actions
                    ],
                    autoWidth: false,
                    responsive: true
=======
            // Prevent table movement
            const usersTable = document.getElementById('users-table');
            if (usersTable) {
                // Lock table dimensions
                usersTable.style.width = '100%';
                usersTable.style.tableLayout = 'fixed';
                usersTable.style.position = 'relative';
                
                // Prevent any dynamic changes
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                            // Revert any style changes that might cause movement
                            usersTable.style.width = '100%';
                            usersTable.style.tableLayout = 'fixed';
                            usersTable.style.position = 'relative';
                        }
                    });
                });
                
                observer.observe(usersTable, {
                    attributes: true,
                    attributeFilter: ['style']
>>>>>>> e72896b2a2e757c3b179363c20ce46759e263081
                });
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
            
            // Modal functions
            const modals = document.querySelectorAll('.modal');
            const addUserBtn = document.getElementById('add-user-btn');
            const closeModalBtns = document.querySelectorAll('.modal-close, .modal-close-btn');
            
            // Open add user modal
            if (addUserBtn) {
                addUserBtn.addEventListener('click', function() {
                    document.getElementById('add-user-modal').classList.add('show');
                });
            }
            
            // Close modals
            closeModalBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    modals.forEach(function(modal) {
                        modal.classList.remove('show');
                    });
                });
            });
            
            // Close modal when clicking outside the modal content
            modals.forEach(function(modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            });

            // Password confirmation validation
            const addUserForm = document.querySelector('#add-user-modal form');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    const password = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('new_confirm_password').value;
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                    }
                });
            }
        });

        // Edit user function
        function editUser(id, username, full_name, email, contact_number, address, role) {
            const modal = document.getElementById('edit-user-modal');
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = full_name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_contact_number').value = contact_number;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_role').value = role;
            modal.classList.add('show');
        }

        // Delete user function
        function deleteUser(id) {
            const modal = document.getElementById('delete-user-modal');
            document.getElementById('delete_user_id').value = id;
            modal.classList.add('show');
        }
<<<<<<< HEAD
=======
        
        // Restore search functionality
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchQuery = this.value.trim();
                if (searchQuery.length === 0) {
                    window.location.href = 'settings.php';
                    return;
                }
                searchTimeout = setTimeout(() => {
                    window.location.href = 'settings.php?search=' + encodeURIComponent(searchQuery);
                }, 300);
            });
        }
>>>>>>> e72896b2a2e757c3b179363c20ce46759e263081
    </script>
</body>
</html>