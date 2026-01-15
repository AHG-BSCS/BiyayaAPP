<?php
// events.php
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

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Always update session role from database
$_SESSION["user_role"] = $user_profile['role'];

// Check if user is super administrator
$is_super_admin = ($_SESSION["user_role"] === "Super Admin");

// Restrict access to Super Admin only
if (!$is_super_admin) {
    header("Location: index.php");
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize message variables
$message = null;
$messageType = null;

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
                'event_image' => isset($row['event_image']) ? $row['event_image'] : null,
                'is_pinned' => $row['is_pinned'],
                'created_by' => $row['created_by'],
                'created_at' => $row['created_at']
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
            'event_image' => isset($row['event_image']) ? $row['event_image'] : null,
            'is_pinned' => $row['is_pinned'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at']
        ];
    }
    return null;
}

// Handle event submission (Add) - Super Admin only
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_event"])) {
    $title = htmlspecialchars(trim($_POST["title"]));
    $category = htmlspecialchars(trim($_POST["category"]));
    $event_date = $_POST["date"];
    $event_time = $_POST["time"];
    $description = htmlspecialchars(trim($_POST["description"]));
    $created_by = $_SESSION["user"];
    $event_image = "";

    // Handle image upload
    if (isset($_FILES["event_image"]) && $_FILES["event_image"]["error"] == 0) {
        $allowed_types = ["image/jpeg", "image/jpg", "image/png", "image/gif"];
        $file_type = $_FILES["event_image"]["type"];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = "uploads/events/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["event_image"]["name"], PATHINFO_EXTENSION);
            $file_name = "event_" . time() . "_" . uniqid() . "." . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES["event_image"]["tmp_name"], $upload_path)) {
                $event_image = $upload_path;
            } else {
                $message = "Error uploading image.";
                $messageType = "danger";
            }
        } else {
            $message = "Invalid file type. Please upload JPEG, PNG, or GIF images only.";
            $messageType = "danger";
        }
    }

    if (!isset($message)) { // Only proceed if no upload errors
        $sql = "INSERT INTO events (title, category, event_date, event_time, description, event_image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $title, $category, $event_date, $event_time, $description, $event_image, $created_by);
        
        if ($stmt->execute()) {
            $message = "Event added successfully!";
            $messageType = "success";
        } else {
            $message = "Error adding event: " . $conn->error;
            $messageType = "danger";
        }
    }
}

// Handle event removal - Super Admin only
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_event"])) {
    $event_id = (int)$_POST["event_id"];
    
    $sql = "DELETE FROM events WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        $message = "Event removed successfully!";
        $messageType = "success";
    } else {
        $message = "Error removing event: " . $conn->error;
        $messageType = "danger";
    }
}

// Handle event edit - Super Admin only
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_event"])) {
    $event_id = (int)$_POST["event_id"];
    $title = htmlspecialchars(trim($_POST["title"]));
    $category = htmlspecialchars(trim($_POST["category"]));
    $event_date = $_POST["date"];
    $event_time = $_POST["time"];
    $description = htmlspecialchars(trim($_POST["description"]));

    // Get current event image
    $current_image_sql = "SELECT event_image FROM events WHERE id = ?";
    $current_image_stmt = $conn->prepare($current_image_sql);
    $current_image_stmt->bind_param("i", $event_id);
    $current_image_stmt->execute();
    $current_image_result = $current_image_stmt->get_result();
    $current_event = $current_image_result->fetch_assoc();
    $event_image = $current_event['event_image'];

    // Handle new image upload
    if (isset($_FILES["event_image"]) && $_FILES["event_image"]["error"] == 0) {
        $allowed_types = ["image/jpeg", "image/jpg", "image/png", "image/gif"];
        $file_type = $_FILES["event_image"]["type"];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = "uploads/events/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["event_image"]["name"], PATHINFO_EXTENSION);
            $file_name = "event_" . time() . "_" . uniqid() . "." . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES["event_image"]["tmp_name"], $upload_path)) {
                // Delete old image if it exists
                if (!empty($event_image) && file_exists($event_image)) {
                    unlink($event_image);
                }
                $event_image = $upload_path;
            } else {
                $message = "Error uploading image.";
                $messageType = "danger";
            }
        } else {
            $message = "Invalid file type. Please upload JPEG, PNG, or GIF images only.";
            $messageType = "danger";
        }
    }

    if (!isset($message)) { // Only proceed if no upload errors
        $sql = "UPDATE events SET title = ?, category = ?, event_date = ?, event_time = ?, description = ?, event_image = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $title, $category, $event_date, $event_time, $description, $event_image, $event_id);
        
        if ($stmt->execute()) {
            $message = "Event updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating event: " . $conn->error;
            $messageType = "danger";
        }
    }
}

// Handle pinning event - Super Admin only
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["pin_event"])) {
    $event_id = (int)$_POST["event_id"];
    
    // Check if the event is already pinned
    $check_sql = "SELECT is_pinned FROM events WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $event = $check_result->fetch_assoc();
    
    if ($event && $event['is_pinned']) {
        // Event was pinned, so unpin it
        $sql = "UPDATE events SET is_pinned = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $message = "Event unpinned successfully!";
    } else {
        // First, unpin all other events
        $sql = "UPDATE events SET is_pinned = 0";
        $conn->query($sql);
        
        // Then pin the selected event
        $sql = "UPDATE events SET is_pinned = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $message = "Event pinned successfully!";
    }
    $messageType = "success";
}

// Get event to edit (for pre-populating form) - Super Admin only
$edit_event = null;
if (isset($_POST["prepare_edit"])) {
    $event_id = (int)$_POST["event_id"];
    $sql = "SELECT * FROM events WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $edit_event = $result->fetch_assoc();
    }
}

// Get all events from database
$events = getAllEvents($conn);

// Get pinned event
$pinned_event = getPinnedEvent($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events | <?php echo $church_name; ?></title>
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
            visibility: hidden;
        }

        .custom-drawer.open {
            left: 0;
            visibility: visible;
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
            min-width: 0;
        }
        .drawer-profile .name {
            font-size: 16px;
            font-weight: 600;
            color: #222;
            line-height: 1.3;
            overflow-wrap: normal;
            word-break: normal;
        }
        .drawer-profile .role {
            font-size: 13px;
            color: var(--accent-color);
            font-weight: 500;
            margin-top: 2px;
            line-height: 1.3;
            overflow-wrap: normal;
            word-break: normal;
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
        .drawer-profile .logout-btn:hover { background: #d32f2f; }

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

        /* Content Area */
        .content-area {
            flex: 1;
            margin-left: 0;
            padding: 20px;
            padding-top: 80px; /* Space for the menu button */
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

        .user-info h4 {
            font-size: 14px;
            margin: 0;
        }

        .user-info p {
            font-size: 12px;
            margin: 0;
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

        @media (max-width: 768px) {
            .custom-drawer {
                width: 260px;
                left: -260px;
            }
            
            .custom-drawer.open {
                left: 0;
            }
            
            .content-area {
                padding: 15px;
                padding-top: 70px;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            
            .top-bar h2 {
                font-size: 20px;
            }
            
            .user-profile {
                margin-top: 10px;
            }

            /* Sidebar profile - mobile adjustments */
            .drawer-profile {
                padding: 16px;
                gap: 12px;
            }
            .drawer-profile .avatar {
                width: 44px;
                height: 44px;
                font-size: 20px;
            }
            .drawer-profile .name {
                font-size: 14px;
            }
            .drawer-profile .role {
                font-size: 12px;
            }
            .drawer-profile .logout-btn {
                padding: 6px 12px;
                font-size: 13px;
                white-space: nowrap;
            }
            
            /* Events specific mobile styles */
            .events-content {
                margin-top: 15px;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                margin-bottom: 15px;
            }
            
            .search-box {
                width: 100%;
                margin-bottom: 0;
            }
            
            .btn {
                width: 100%;
                text-align: center;
                padding: 12px 20px;
            }
            
            .event-form {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-control {
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .events-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-top: 15px;
            }
            
            .event-category {
                padding: 15px;
            }
            
            .event-category h3 {
                font-size: 16px;
                margin-bottom: 12px;
            }
            
            .event-item {
                padding: 12px;
                margin-bottom: 12px;
            }
            
            .event-details h4 {
                font-size: 16px;
                margin-bottom: 8px;
            }
            
            .event-details p {
                font-size: 14px;
                margin-bottom: 8px;
                word-wrap: break-word;
            }
            
            .event-details img {
                max-width: 100% !important;
                height: auto !important;
                margin-bottom: 10px !important;
            }
            
            .event-actions {
                flex-direction: column;
                gap: 8px;
                margin-top: 12px;
            }
            
            .event-actions .btn {
                width: 100%;
                margin: 0;
            }
            
            .event-actions form {
                width: 100%;
                display: block;
            }
            
            .current-image {
                padding: 12px;
            }
            
            .current-image img {
                max-width: 100% !important;
                width: 100% !important;
            }
        }
        
        @media (max-width: 480px) {
            .custom-drawer {
                width: 260px;
                left: -260px;
            }
            
            .custom-drawer.open {
                left: 0;
            }
            
            .content-area {
                padding: 10px;
                padding-top: 70px;
            }
            
            .top-bar {
                padding: 12px;
            }
            
            .top-bar h2 {
                font-size: 18px;
            }
            
            .event-form {
                padding: 12px;
            }
            
            .event-category {
                padding: 12px;
            }
            
            .event-item {
                padding: 10px;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 13px;
            }
        }

        /* Events specific styles */
        .events-content {
            margin-top: 20px;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
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

        .event-form {
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: none;
        }

        .event-form.active {
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

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }


        .event-category {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .event-category.pinned {
            border: 2px solid var(--accent-color);
        }

        .event-category h3 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 18px;
        }

        .event-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent-color);
        }

        .event-item:last-child {
            margin-bottom: 0;
        }

        .event-details h4 {
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .event-details p {
            margin-bottom: 5px;
            color: #666;
        }

        .event-details p i {
            margin-right: 5px;
            color: var(--accent-color);
        }

        .event-actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-warning {
            background-color: var(--warning-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
        }

        .btn-info {
            background-color: var(--info-color);
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

        /* File input styling */
        .form-control[type="file"] {
            padding: 8px;
            border: 2px dashed var(--light-gray);
            background-color: #fafafa;
            cursor: pointer;
        }

        .form-control[type="file"]:hover {
            border-color: var(--accent-color);
            background-color: #f0f8f0;
        }

        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .current-image {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid var(--light-gray);
        }

        .current-image p {
            margin: 0 0 10px 0;
            font-weight: 500;
        }

        /* Date and Time Input Styling */
        .date-input-wrapper {
            position: relative;
            max-width: 300px;
        }

        .date-icon {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            display: flex;
            align-items: center;
            padding-left: 12px;
            pointer-events: none;
            z-index: 1;
        }

        .date-icon svg {
            width: 12px;
            height: 12px;
            color: #6b7280;
        }

        .date-input {
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            color: #111827;
            font-size: 14px;
            border-radius: 8px;
            padding: 10px 10px 10px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .time-input {
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            color: #111827;
            font-size: 14px;
            border-radius: 8px;
            padding: 10px 15px;
            width: 100%;
            box-sizing: border-box;
        }

        .event-image {
            width: 100%;
            max-width: 320px;
            height: auto;
            border-radius: 8px;
            margin-bottom: 10px;
            object-fit: cover;
        }

        @media (max-width: 768px) {
            .date-input-wrapper {
                max-width: 100%;
            }
            
            .date-input,
            .time-input {
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .event-image {
                max-width: 100%;
            }
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
                    <div class="role"><?php echo htmlspecialchars($user_profile['role'] ?? ($_SESSION['user_role'] ?? 'User')); ?></div>
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
                    <h2>Events</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>

            <div class="events-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" id="message-alert">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Event Form (Super Admin Only) -->
                <?php if ($is_super_admin): ?>
                    <div class="action-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search events...">
                        </div>
                        <button class="btn" id="add-event-btn">
                            <i class="fas fa-plus"></i> Add Event
                        </button>
                    </div>

                    <!-- Add Event Form -->
                    <div class="event-form" id="add-event-form">
                        <h3>Add New Event</h3>
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="add_title">Event Title</label>
                                <input type="text" id="add_title" name="title" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="add_category">Category</label>
                                <select id="add_category" name="category" class="form-control" required>
                                    <option value="AMEN Fellowship">AMEN Fellowship</option>
                                    <option value="WOW Fellowship">WOW Fellowship</option>
                                    <option value="Youth Fellowship">Youth Fellowship</option>
                                    <option value="Sunday School Outreach">Sunday School Outreach</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="add_date">Date</label>
                                <div class="date-input-wrapper">
                                  <div class="date-icon">
                                     <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                                      </svg>
                                  </div>
                                  <input id="add_date" name="date" type="date" class="form-control date-input" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="add_time">Time</label>
                                <input id="add_time" name="time" type="time" class="form-control time-input" required>
                            </div>
                            <div class="form-group">
                                <label for="add_description">Description</label>
                                <textarea id="add_description" name="description" class="form-control" rows="4" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="add_event_image">Event Image (Optional)</label>
                                <input type="file" id="add_event_image" name="event_image" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Upload JPEG, PNG, or GIF image. This image will be displayed on the homepage.</small>
                            </div>
                            <button type="submit" class="btn" name="add_event">
                                <i class="fas fa-calendar-plus"></i> Add Event
                            </button>
                            <button type="button" class="btn" id="add-cancel-btn" style="background-color: #f0f0f0; color: var(--primary-color); margin-left: 10px;">
                                Cancel
                            </button>
                        </form>
                    </div>

                    <!-- Edit Event Form -->
                    <div class="event-form<?php echo $edit_event ? ' active' : ''; ?>" id="edit-event-form">
                        <h3>Edit Event</h3>
                        <form action="" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="event_id" value="<?php echo $edit_event['id'] ?? ''; ?>">
                            <div class="form-group">
                                <label for="edit_title">Event Title</label>
                                <input type="text" id="edit_title" name="title" class="form-control" value="<?php echo $edit_event['title'] ?? ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_category">Category</label>
                                <select id="edit_category" name="category" class="form-control" required>
                                    <option value="AMEN Fellowship" <?php echo ($edit_event['category'] ?? '') === 'AMEN Fellowship' ? 'selected' : ''; ?>>AMEN Fellowship</option>
                                    <option value="WOW Fellowship" <?php echo ($edit_event['category'] ?? '') === 'WOW Fellowship' ? 'selected' : ''; ?>>WOW Fellowship</option>
                                    <option value="Youth Fellowship" <?php echo ($edit_event['category'] ?? '') === 'Youth Fellowship' ? 'selected' : ''; ?>>Youth Fellowship</option>
                                    <option value="Sunday School Outreach" <?php echo ($edit_event['category'] ?? '') === 'Sunday School Outreach' ? 'selected' : ''; ?>>Sunday School Outreach</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_date">Date</label>
                                <div class="date-input-wrapper">
                                  <div class="date-icon">
                                     <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                                      </svg>
                                  </div>
                                  <input id="edit_date" name="date" type="date" class="form-control date-input" value="<?php echo ($edit_event ? $edit_event['event_date'] : ''); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="edit_time">Time</label>
                                <input id="edit_time" name="time" type="time" class="form-control time-input" value="<?php echo ($edit_event ? $edit_event['event_time'] : ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_description">Description</label>
                                <textarea id="edit_description" name="description" class="form-control" rows="4" required><?php echo $edit_event['description'] ?? ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="edit_event_image">Event Image (Optional)</label>
                                <?php if (!empty($edit_event['event_image'])): ?>
                                    <div class="current-image">
                                        <p><strong>Current Image:</strong></p>
                                        <img src="<?php echo htmlspecialchars($edit_event['event_image']); ?>" alt="Current Event Image" style="max-width: 200px; height: auto; margin: 10px 0;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" id="edit_event_image" name="event_image" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Upload a new image to replace the current one. Leave empty to keep the current image.</small>
                            </div>
                            <button type="submit" class="btn" name="edit_event">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn" id="edit-cancel-btn" style="background-color: #f0f0f0; color: var(--primary-color); margin-left: 10px;">
                                Cancel
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Events Display -->
                <div class="events-grid">
                    <!-- Pinned Event Box -->
                    <?php if ($pinned_event): ?>
                        <div class="event-category pinned">
                            <h3>Pinned Event</h3>
                            <div class="event-item event-search-item">
                                <div class="event-details">
                                    <?php if (!empty($pinned_event['event_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($pinned_event['event_image']); ?>" alt="Event Image" class="event-image" />
                                    <?php endif; ?>
                                    <h4><?php echo $pinned_event['title']; ?></h4>
                                    <p><i class="fas fa-calendar-alt"></i> <?php echo $pinned_event['date']; ?> at <?php echo date("h:i A", strtotime($pinned_event['time'])); ?></p>
                                    <p><?php echo $pinned_event['description']; ?></p>
                                                                                <?php if ($is_super_admin): ?>
                                        <div class="event-actions">
                                            <form action="" method="post" style="display: inline;">
                                                <input type="hidden" name="event_id" value="<?php echo $pinned_event['id']; ?>">
                                                <button type="submit" class="btn btn-warning" name="prepare_edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </form>
                                            <form action="" method="post" style="display: inline;">
                                                <input type="hidden" name="event_id" value="<?php echo $pinned_event['id']; ?>">
                                                <button type="submit" class="btn btn-danger" name="remove_event">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </form>
                                            <form action="" method="post" style="display: inline;">
                                                <input type="hidden" name="event_id" value="<?php echo $pinned_event['id']; ?>">
                                                <button type="submit" class="btn btn-info" name="pin_event">
                                                    <i class="fas fa-thumbtack"></i> Unpin
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Category Boxes -->
                    <?php
                    $categories = ["AMEN Fellowship", "WOW Fellowship", "Youth Fellowship", "Sunday School Outreach"];
                    foreach ($categories as $category):
                        // Filter events for this category, excluding pinned event only if there are other events to show
                        $category_events = array_filter($events, function($event) use ($category, $pinned_event) {
                            return $event['category'] === $category && (!$pinned_event || $event['id'] !== $pinned_event['id']);
                        });
                        // Check if there are any events in this category, including pinned event if it matches
                        $all_category_events = array_filter($events, function($event) use ($category) {
                            return $event['category'] === $category;
                        });
                        
                        // Only show category if it has events to display (not all pinned)
                        if (!empty($category_events) || empty($all_category_events)):
                    ?>
                        <div class="event-category">
                            <h3><?php echo $category; ?></h3>
                            <?php if (empty($all_category_events)): ?>
                                <p>No upcoming events in this category.</p>
                            <?php else: ?>
                                <?php foreach ($category_events as $event): ?>
                                    <div class="event-item event-search-item">
                                        <div class="event-details">
                                            <?php if (!empty($event['event_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($event['event_image']); ?>" alt="Event Image" class="event-image" />
                                            <?php endif; ?>
                                            <h4><?php echo $event['title']; ?></h4>
                                            <p><i class="fas fa-calendar-alt"></i> <?php echo $event['date']; ?> at <?php echo date("h:i A", strtotime($event['time'])); ?></p>
                                            <p><?php echo $event['description']; ?></p>
                                            <?php if ($is_super_admin): ?>
                                                <div class="event-actions">
                                                    <form action="" method="post" style="display: inline;">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                        <button type="submit" class="btn btn-warning" name="prepare_edit">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    </form>
                                                    <form action="" method="post" style="display: inline;">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                        <button type="submit" class="btn btn-danger" name="remove_event">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                    <form action="" method="post" style="display: inline;">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                        <button type="submit" class="btn btn-info" name="pin_event">
                                                            <i class="fas fa-thumbtack"></i> Pin
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>

        // Event form handling
        $(document).ready(function() {
            $('#add-event-btn').click(function() {
                $('#add-event-form').addClass('active');
                $('#edit-event-form').removeClass('active');
            });

            $('#add-cancel-btn').click(function() {
                $('#add-event-form').removeClass('active');
            });

            $('#edit-cancel-btn').click(function() {
                $('#edit-event-form').removeClass('active');
            });

            // Auto-hide alerts after 3 seconds
            setTimeout(function() {
                $('#message-alert').fadeOut();
            }, 3000);

            // Event search/filter
            $('.search-box input').on('input', function() {
                var query = $(this).val().toLowerCase();
                $('.event-search-item').each(function() {
                    var text = $(this).text().toLowerCase();
                    if (text.indexOf(query) > -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });

        // Custom Drawer Navigation JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.getElementById('nav-toggle');
            const drawer = document.getElementById('drawer-navigation');
            const drawerClose = document.getElementById('drawer-close');
            const overlay = document.getElementById('drawer-overlay');

            // Ensure drawer is closed on page load
            if (drawer) {
                drawer.classList.remove('open');
            }
            if (overlay) {
                overlay.classList.remove('open');
            }

            // Open drawer
            if (navToggle) {
                navToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (drawer) {
                        drawer.classList.add('open');
                    }
                    if (overlay) {
                        overlay.classList.add('open');
                    }
                    document.body.style.overflow = 'hidden';
                });
            }

            // Close drawer
            function closeDrawer() {
                if (drawer) {
                    drawer.classList.remove('open');
                }
                if (overlay) {
                    overlay.classList.remove('open');
                }
                document.body.style.overflow = '';
            }

            if (drawerClose) {
                drawerClose.addEventListener('click', closeDrawer);
            }
            if (overlay) {
                overlay.addEventListener('click', closeDrawer);
            }

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