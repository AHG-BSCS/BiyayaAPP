<?php
# inventory.php
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

// Restrict access to Super Administrator only
if (!$is_super_admin) {
    header("Location: index.php");
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize message variables
$message = '';
$messageType = '';

// Handle form submission for adding church property
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_church_property"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $quantity = $_POST['quantity'] ?? '0';
        $notes = $_POST['notes'] ?? '';
        
        // Check if table exists, if not create it
        $stmt = $conn->query("SHOW TABLES LIKE 'church_property_inventory'");
        if ($stmt->num_rows == 0) {
            $createTable = "CREATE TABLE IF NOT EXISTS church_property_inventory (
                id VARCHAR(50) PRIMARY KEY,
                item_name VARCHAR(255) NOT NULL,
                quantity INT DEFAULT 0,
                notes TEXT,
                category VARCHAR(100),
                location VARCHAR(255),
                status VARCHAR(50) DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $conn->query($createTable);
        }
        
        // Insert the record
        $sql = "INSERT INTO church_property_inventory (id, item_name, quantity, notes) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssis", $id, $item_name, $quantity, $notes);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Church property added successfully!";
            $messageType = "success";
            // Refresh the page to show the new record
            header("Location: " . $_SERVER['PHP_SELF'] . "#church-property");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to add property: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle form submission for adding office supplies
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_office_supplies"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $quantity = $_POST['quantity'] ?? '0';
        $notes = $_POST['notes'] ?? '';
        
        // Check if table exists, if not create it
        $stmt = $conn->query("SHOW TABLES LIKE 'office_supplies_inventory'");
        if ($stmt->num_rows == 0) {
            $createTable = "CREATE TABLE IF NOT EXISTS office_supplies_inventory (
                id VARCHAR(50) PRIMARY KEY,
                item_name VARCHAR(255) NOT NULL,
                quantity INT DEFAULT 0,
                notes TEXT,
                category VARCHAR(100),
                unit VARCHAR(50),
                status VARCHAR(50) DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $conn->query($createTable);
        }
        
        // Insert the record
        $sql = "INSERT INTO office_supplies_inventory (id, item_name, quantity, notes) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssis", $id, $item_name, $quantity, $notes);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Office supply added successfully!";
            $messageType = "success";
            // Refresh the page to show the new record
            header("Location: " . $_SERVER['PHP_SELF'] . "#office-supplies");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to add office supply: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle form submission for adding technical equipments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_technical_equipments"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $quantity = $_POST['quantity'] ?? '0';
        $status = $_POST['status'] ?? 'Working';
        $notes = $_POST['notes'] ?? '';
        
        // Check if table exists, if not create it
        $stmt = $conn->query("SHOW TABLES LIKE 'technical_equipments_inventory'");
        if ($stmt->num_rows == 0) {
            $createTable = "CREATE TABLE IF NOT EXISTS technical_equipments_inventory (
                id VARCHAR(50) PRIMARY KEY,
                item_name VARCHAR(255) NOT NULL,
                quantity INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'Working',
                notes TEXT,
                category VARCHAR(100),
                location VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $conn->query($createTable);
        }
        
        // Insert the record
        $sql = "INSERT INTO technical_equipments_inventory (id, item_name, quantity, status, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiss", $id, $item_name, $quantity, $status, $notes);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Technical equipment added successfully!";
            $messageType = "success";
            // Refresh the page to show the new record
            header("Location: " . $_SERVER['PHP_SELF'] . "#technical-equipments");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to add technical equipment: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle edit church property
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_church_property"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $quantity = $_POST['quantity'] ?? '0';
        $notes = $_POST['notes'] ?? '';
        
        $sql = "UPDATE church_property_inventory SET item_name = ?, quantity = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siss", $item_name, $quantity, $notes, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Church property updated successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#church-property");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to update property: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle delete church property
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_church_property"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        
        $sql = "DELETE FROM church_property_inventory WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Church property deleted successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#church-property");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to delete property: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle edit office supplies
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_office_supplies"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $quantity = $_POST['quantity'] ?? '0';
        $notes = $_POST['notes'] ?? '';
        
        $sql = "UPDATE office_supplies_inventory SET item_name = ?, quantity = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siss", $item_name, $quantity, $notes, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Office supply updated successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#office-supplies");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to update office supply: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle delete office supplies
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_office_supplies"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        
        $sql = "DELETE FROM office_supplies_inventory WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Office supply deleted successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#office-supplies");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to delete office supply: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle edit technical equipments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_technical_equipments"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $quantity = $_POST['quantity'] ?? '0';
        $status = $_POST['status'] ?? 'Working';
        $notes = $_POST['notes'] ?? '';
        
        $sql = "UPDATE technical_equipments_inventory SET item_name = ?, quantity = ?, status = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siss", $item_name, $quantity, $status, $notes, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Technical equipment updated successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#technical-equipments");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to update technical equipment: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle delete technical equipments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_technical_equipments"]) && $is_super_admin) {
    try {
        $id = $_POST['id'] ?? '';
        
        $sql = "DELETE FROM technical_equipments_inventory WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = "Technical equipment deleted successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#technical-equipments");
            exit();
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to delete technical equipment: " . $error);
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch church property inventory from database
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'church_property_inventory'");
    if ($stmt->num_rows > 0) {
        $stmt = $conn->query("SELECT * FROM church_property_inventory ORDER BY id");
        $church_property_records = [];
        while ($row = $stmt->fetch_assoc()) {
            $church_property_records[] = $row;
        }
    } else {
        $church_property_records = [];
    }
} catch(Exception $e) {
    $church_property_records = [];
}

// Fetch office supplies inventory from database
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'office_supplies_inventory'");
    if ($stmt->num_rows > 0) {
        $stmt = $conn->query("SELECT * FROM office_supplies_inventory ORDER BY id");
        $office_supplies_records = [];
        while ($row = $stmt->fetch_assoc()) {
            $office_supplies_records[] = $row;
        }
    } else {
        $office_supplies_records = [];
    }
} catch(Exception $e) {
    $office_supplies_records = [];
}

// Fetch technical equipments inventory from database
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'technical_equipments_inventory'");
    if ($stmt->num_rows > 0) {
        $stmt = $conn->query("SELECT * FROM technical_equipments_inventory ORDER BY id");
        $technical_equipments_records = [];
        while ($row = $stmt->fetch_assoc()) {
            $technical_equipments_records[] = $row;
        }
    } else {
        $technical_equipments_records = [];
    }
} catch(Exception $e) {
    $technical_equipments_records = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
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

        .records-content {
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

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: var(--white);
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #008020;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .dataTables_wrapper {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 6px 10px;
            margin-left: 6px;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eeeeee;
        }

        table th {
            background-color: #f5f5f5;
            font-weight: 600;
            color: var(--primary-color);
        }

        tbody tr:hover {
            background-color: #f9f9f9;
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

        .action-btn.view-btn {
            background-color: var(--info-color);
        }

        .action-btn.view-btn:hover {
            background-color: #1976d2;
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            border-radius: 5px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .form-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .form-header h3 {
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .form-header h4 {
            color: var(--accent-color);
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        select.form-control {
            cursor: pointer;
            background-color: var(--white);
        }

        select.form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }

        .exit-btn {
            background-color: var(--danger-color);
        }

        .exit-btn:hover {
            background-color: #d32f2f;
        }

        .view-field {
            padding: 10px 15px;
            background-color: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            min-height: 20px;
            color: var(--primary-color);
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
            <div class="top-bar" style="background-color: #fff; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 20px; margin-top: 0;">
                <div>
                    <h2>Inventory</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>

            <div class="records-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="tab-navigation">
                    <a href="#church-property" class="active" data-tab="church-property">Church Property Inventory</a>
                    <a href="#office-supplies" data-tab="office-supplies">Office Supplies</a>
                    <a href="#technical-equipments" data-tab="technical-equipments">Technical Equipments</a>
                </div>

                <div class="tab-content">
                    <!-- Church Property Inventory Tab -->
                    <div class="tab-pane active" id="church-property">
                        <div class="action-bar">
                            <?php if ($is_super_admin): ?>
                                <button class="btn" id="add-church-property-btn">
                                    <i class="fas fa-plus"></i> Add New Property
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table id="church-property-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($church_property_records)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 20px;">
                                                No church property records found. Click "Add New Property" to add one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($church_property_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($record['item_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($record['quantity'] ?? '0'); ?></td>
                                                <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="action-btn view-btn" title="View" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="church-property">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($is_super_admin): ?>
                                                            <button class="action-btn edit-btn" title="Edit" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="church-property">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="action-btn delete-btn" title="Delete" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="church-property">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Office Supplies Tab -->
                    <div class="tab-pane" id="office-supplies">
                        <div class="action-bar">
                            <?php if ($is_super_admin): ?>
                                <button class="btn" id="add-office-supplies-btn">
                                    <i class="fas fa-plus"></i> Add New Supply
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table id="office-supplies-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($office_supplies_records)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 20px;">
                                                No office supplies records found. Click "Add New Supply" to add one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($office_supplies_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($record['item_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($record['quantity'] ?? '0'); ?></td>
                                                <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="action-btn view-btn" title="View" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="office-supplies">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($is_super_admin): ?>
                                                            <button class="action-btn edit-btn" title="Edit" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="office-supplies">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="action-btn delete-btn" title="Delete" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="office-supplies">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Technical Equipments Tab -->
                    <div class="tab-pane" id="technical-equipments">
                        <div class="action-bar">
                            <?php if ($is_super_admin): ?>
                                <button class="btn" id="add-technical-equipments-btn">
                                    <i class="fas fa-plus"></i> Add New Equipment
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table id="technical-equipments-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($technical_equipments_records)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 20px;">
                                                No technical equipments records found. Click "Add New Equipment" to add one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($technical_equipments_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($record['item_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($record['quantity'] ?? '0'); ?></td>
                                                <td>
                                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; 
                                                        <?php 
                                                        $status = strtolower($record['status'] ?? '');
                                                        if ($status === 'working') {
                                                            echo 'background-color: #2ecc71; color: white;';
                                                        } else {
                                                            echo 'background-color: #e74c3c; color: white;';
                                                        }
                                                        ?>">
                                                        <?php echo htmlspecialchars($record['status'] ?? ''); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="action-btn view-btn" title="View" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="technical-equipments">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($is_super_admin): ?>
                                                            <button class="action-btn edit-btn" title="Edit" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="technical-equipments">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="action-btn delete-btn" title="Delete" data-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-type="technical-equipments">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Modals -->
    <!-- View Church Property Modal -->
    <div class="modal" id="view-church-property-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Church Property Details</h4>
            </div>
            <div class="form-group">
                <label>ID</label>
                <div class="view-field" id="view-church-property-id"></div>
            </div>
            <div class="form-group">
                <label>Item Name</label>
                <div class="view-field" id="view-church-property-item-name"></div>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <div class="view-field" id="view-church-property-quantity"></div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <div class="view-field" id="view-church-property-notes"></div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn exit-btn" id="view-church-property-exit-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- View Office Supplies Modal -->
    <div class="modal" id="view-office-supplies-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Office Supply Details</h4>
            </div>
            <div class="form-group">
                <label>ID</label>
                <div class="view-field" id="view-office-supplies-id"></div>
            </div>
            <div class="form-group">
                <label>Item Name</label>
                <div class="view-field" id="view-office-supplies-item-name"></div>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <div class="view-field" id="view-office-supplies-quantity"></div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <div class="view-field" id="view-office-supplies-notes"></div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn exit-btn" id="view-office-supplies-exit-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- View Technical Equipments Modal -->
    <div class="modal" id="view-technical-equipments-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Technical Equipment Details</h4>
            </div>
            <div class="form-group">
                <label>ID</label>
                <div class="view-field" id="view-technical-equipments-id"></div>
            </div>
            <div class="form-group">
                <label>Item Name</label>
                <div class="view-field" id="view-technical-equipments-item-name"></div>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <div class="view-field" id="view-technical-equipments-quantity"></div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <div class="view-field" id="view-technical-equipments-status"></div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <div class="view-field" id="view-technical-equipments-notes"></div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn exit-btn" id="view-technical-equipments-exit-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Modals -->
    <!-- Edit Church Property Modal -->
    <div class="modal" id="edit-church-property-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Edit Church Property</h4>
            </div>
            <form action="" method="post">
                <input type="hidden" name="edit_church_property" value="1">
                <input type="hidden" name="id" id="edit-church-property-id">
                
                <div class="form-group">
                    <label for="edit-church-property-item-name">Item Name</label>
                    <input type="text" id="edit-church-property-item-name" name="item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-church-property-quantity">Quantity</label>
                    <input type="number" id="edit-church-property-quantity" name="quantity" class="form-control" min="0" value="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-church-property-notes">Notes</label>
                    <textarea id="edit-church-property-notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button type="button" class="btn exit-btn" id="edit-church-property-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Office Supplies Modal -->
    <div class="modal" id="edit-office-supplies-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Edit Office Supply</h4>
            </div>
            <form action="" method="post">
                <input type="hidden" name="edit_office_supplies" value="1">
                <input type="hidden" name="id" id="edit-office-supplies-id">
                
                <div class="form-group">
                    <label for="edit-office-supplies-item-name">Item Name</label>
                    <input type="text" id="edit-office-supplies-item-name" name="item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-office-supplies-quantity">Quantity</label>
                    <input type="number" id="edit-office-supplies-quantity" name="quantity" class="form-control" min="0" value="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-office-supplies-notes">Notes</label>
                    <textarea id="edit-office-supplies-notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button type="button" class="btn exit-btn" id="edit-office-supplies-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Technical Equipments Modal -->
    <div class="modal" id="edit-technical-equipments-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Edit Technical Equipment</h4>
            </div>
            <form action="" method="post">
                <input type="hidden" name="edit_technical_equipments" value="1">
                <input type="hidden" name="id" id="edit-technical-equipments-id">
                
                <div class="form-group">
                    <label for="edit-technical-equipments-item-name">Item Name</label>
                    <input type="text" id="edit-technical-equipments-item-name" name="item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-technical-equipments-quantity">Quantity</label>
                    <input type="number" id="edit-technical-equipments-quantity" name="quantity" class="form-control" min="0" value="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-technical-equipments-status">Status</label>
                    <select id="edit-technical-equipments-status" name="status" class="form-control" required>
                        <option value="Working">Working</option>
                        <option value="Not Working">Not Working</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-technical-equipments-notes">Notes</label>
                    <textarea id="edit-technical-equipments-notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button type="button" class="btn exit-btn" id="edit-technical-equipments-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="delete-confirmation-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3>⚠️ Confirm Deletion</h3>
                <p id="delete-confirmation-message">Are you sure you want to delete this record?</p>
                <p style="color: #f44336; font-weight: 600;">⚠️ This action cannot be undone.</p>
            </div>
            <form action="" method="post" id="delete-form">
                <input type="hidden" name="id" id="delete-record-id">
                <input type="hidden" name="delete_type" id="delete-record-type">
                <div class="modal-buttons">
                    <button type="submit" class="btn" style="background-color: var(--danger-color);">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <button type="button" class="btn exit-btn" id="delete-confirmation-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Church Property Modal -->
    <div class="modal" id="add-church-property-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Add Church Property</h4>
            </div>
            <form action="" method="post">
                <input type="hidden" name="add_church_property" value="1">
                
                <div class="form-group">
                    <label for="property-id">ID</label>
                    <input type="text" id="property-id" name="id" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="property-item-name">Item Name</label>
                    <input type="text" id="property-item-name" name="item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="property-quantity">Quantity</label>
                    <input type="number" id="property-quantity" name="quantity" class="form-control" min="0" value="0" required>
                </div>
                
                <div class="form-group">
                    <label for="property-notes">Notes</label>
                    <textarea id="property-notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Submit
                    </button>
                    <button type="button" class="btn exit-btn" id="church-property-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Office Supplies Modal -->
    <div class="modal" id="add-office-supplies-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Add Office Supply</h4>
            </div>
            <form action="" method="post">
                <input type="hidden" name="add_office_supplies" value="1">
                
                <div class="form-group">
                    <label for="supply-id">ID</label>
                    <input type="text" id="supply-id" name="id" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="supply-item-name">Item Name</label>
                    <input type="text" id="supply-item-name" name="item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="supply-quantity">Quantity</label>
                    <input type="number" id="supply-quantity" name="quantity" class="form-control" min="0" value="0" required>
                </div>
                
                <div class="form-group">
                    <label for="supply-notes">Notes</label>
                    <textarea id="supply-notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Submit
                    </button>
                    <button type="button" class="btn exit-btn" id="office-supplies-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Technical Equipments Modal -->
    <div class="modal" id="add-technical-equipments-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3><?php echo $church_name; ?></h3>
                <h4>Add Technical Equipment</h4>
            </div>
            <form action="" method="post">
                <input type="hidden" name="add_technical_equipments" value="1">
                
                <div class="form-group">
                    <label for="equipment-id">ID</label>
                    <input type="text" id="equipment-id" name="id" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="equipment-item-name">Item Name</label>
                    <input type="text" id="equipment-item-name" name="item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="equipment-quantity">Quantity</label>
                    <input type="number" id="equipment-quantity" name="quantity" class="form-control" min="0" value="0" required>
                </div>
                
                <div class="form-group">
                    <label for="equipment-status">Status</label>
                    <select id="equipment-status" name="status" class="form-control" required>
                        <option value="Working">Working</option>
                        <option value="Not Working">Not Working</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="equipment-notes">Notes</label>
                    <textarea id="equipment-notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Submit
                    </button>
                    <button type="button" class="btn exit-btn" id="technical-equipments-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script>
        // Variables to track DataTables instances
        let churchPropertyTable = null;
        let officeSuppliesTable = null;
        let technicalEquipmentsTable = null;

        // Function to initialize DataTable for a specific table
        function initializeDataTable(tableId) {
            const table = $('#' + tableId);
            if (!table.length) {
                return null;
            }
            
            // Check if DataTable is already initialized
            if ($.fn.DataTable.isDataTable('#' + tableId)) {
                return table.DataTable();
            }
            
            // Check if table has data rows (not just the empty message row with colspan)
            const tbodyRows = table.find('tbody tr');
            const hasDataRows = tbodyRows.length > 0 && 
                               !tbodyRows.first().find('td[colspan]').length;
            
            // DataTables configuration
            const dtConfig = {
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "order": [[0, "asc"]],
                "language": {
                    "emptyTable": "No records found. Click 'Add New' to add records.",
                    "zeroRecords": "No matching records found",
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                },
                "responsive": true,
                "autoWidth": false
            };
            
            // Only add order if we have data rows
            if (hasDataRows) {
                try {
                    return table.DataTable(dtConfig);
                } catch (e) {
                    console.error('Error initializing DataTable for ' + tableId + ':', e);
                    return null;
                }
            } else {
                // For empty tables, remove the colspan row and let DataTables handle empty state
                const emptyRow = table.find('tbody tr td[colspan]').closest('tr');
                if (emptyRow.length) {
                    emptyRow.remove();
                }
                // Add an empty tbody if needed
                if (table.find('tbody').length === 0) {
                    table.append('<tbody></tbody>');
                }
                try {
                    return table.DataTable(dtConfig);
                } catch (e) {
                    console.error('Error initializing DataTable for ' + tableId + ':', e);
                    return null;
                }
            }
        }

        // Function to destroy and reinitialize DataTable
        function reinitializeDataTable(tableId) {
            const table = $('#' + tableId);
            if ($.fn.DataTable.isDataTable('#' + tableId)) {
                table.DataTable().destroy();
            }
            return initializeDataTable(tableId);
        }

        // Modal Handling Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Tab Navigation
        const tabLinks = document.querySelectorAll('.tab-navigation a');
        const tabPanes = document.querySelectorAll('.tab-pane');

        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                tabLinks.forEach(l => l.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                link.classList.add('active');
                const tabId = link.getAttribute('data-tab');
                const tabPane = document.getElementById(tabId);
                tabPane.classList.add('active');
                
                // Reinitialize DataTable when tab becomes visible
                setTimeout(function() {
                    if (tabId === 'church-property') {
                        churchPropertyTable = reinitializeDataTable('church-property-table');
                    } else if (tabId === 'office-supplies') {
                        officeSuppliesTable = reinitializeDataTable('office-supplies-table');
                    } else if (tabId === 'technical-equipments') {
                        technicalEquipmentsTable = reinitializeDataTable('technical-equipments-table');
                    }
                }, 100);
            });
        });

        // Hash-based tab activation and DataTables initialization
        $(document).ready(function() {
            let hash = window.location.hash;
            let defaultTab = 'church-property';
            if (hash && document.querySelector('.tab-navigation a[data-tab="' + hash.replace('#', '') + '"]')) {
                defaultTab = hash.replace('#', '');
            }
            
            // Activate default tab
            document.querySelectorAll('.tab-navigation a').forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.querySelector('.tab-navigation a[data-tab="' + defaultTab + '"]').classList.add('active');
            document.getElementById(defaultTab).classList.add('active');

            // Remove the hash from the URL after activating the tab
            if (window.location.hash) {
                history.replaceState(null, '', window.location.pathname);
            }

            // Initialize DataTables after a short delay to ensure tables are visible
            setTimeout(function() {
                // Initialize the active tab's DataTable
                if (defaultTab === 'church-property') {
                    churchPropertyTable = initializeDataTable('church-property-table');
                } else if (defaultTab === 'office-supplies') {
                    officeSuppliesTable = initializeDataTable('office-supplies-table');
                } else if (defaultTab === 'technical-equipments') {
                    technicalEquipmentsTable = initializeDataTable('technical-equipments-table');
                }
            }, 200);

            // Add Church Property Button Event Listener
            document.getElementById('add-church-property-btn')?.addEventListener('click', function() {
                openModal('add-church-property-modal');
            });

            // Add Office Supplies Button Event Listener
            document.getElementById('add-office-supplies-btn')?.addEventListener('click', function() {
                openModal('add-office-supplies-modal');
            });

            // Add Technical Equipments Button Event Listener
            document.getElementById('add-technical-equipments-btn')?.addEventListener('click', function() {
                openModal('add-technical-equipments-modal');
            });

            // Close Modal Button Event Listeners
            document.getElementById('church-property-exit-btn')?.addEventListener('click', function() {
                closeModal('add-church-property-modal');
            });

            document.getElementById('office-supplies-exit-btn')?.addEventListener('click', function() {
                closeModal('add-office-supplies-modal');
            });

            document.getElementById('technical-equipments-exit-btn')?.addEventListener('click', function() {
                closeModal('add-technical-equipments-modal');
            });

            // Close modal when clicking outside of it
            document.getElementById('add-church-property-modal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal('add-church-property-modal');
                }
            });

            document.getElementById('add-office-supplies-modal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal('add-office-supplies-modal');
                }
            });

            document.getElementById('add-technical-equipments-modal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal('add-technical-equipments-modal');
                }
            });

            // View Button Handlers
            document.querySelectorAll('.view-btn[data-type]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const type = this.getAttribute('data-type');
                    const row = this.closest('tr');
                    
                    if (type === 'church-property') {
                        document.getElementById('view-church-property-id').textContent = row.cells[0].textContent;
                        document.getElementById('view-church-property-item-name').textContent = row.cells[1].textContent;
                        document.getElementById('view-church-property-quantity').textContent = row.cells[2].textContent;
                        document.getElementById('view-church-property-notes').textContent = row.cells[3].textContent || 'N/A';
                        openModal('view-church-property-modal');
                    } else if (type === 'office-supplies') {
                        document.getElementById('view-office-supplies-id').textContent = row.cells[0].textContent;
                        document.getElementById('view-office-supplies-item-name').textContent = row.cells[1].textContent;
                        document.getElementById('view-office-supplies-quantity').textContent = row.cells[2].textContent;
                        document.getElementById('view-office-supplies-notes').textContent = row.cells[3].textContent || 'N/A';
                        openModal('view-office-supplies-modal');
                    } else if (type === 'technical-equipments') {
                        document.getElementById('view-technical-equipments-id').textContent = row.cells[0].textContent;
                        document.getElementById('view-technical-equipments-item-name').textContent = row.cells[1].textContent;
                        document.getElementById('view-technical-equipments-quantity').textContent = row.cells[2].textContent;
                        document.getElementById('view-technical-equipments-status').textContent = row.cells[3].textContent.trim();
                        document.getElementById('view-technical-equipments-notes').textContent = row.cells[4].textContent || 'N/A';
                        openModal('view-technical-equipments-modal');
                    }
                });
            });

            // Edit Button Handlers
            document.querySelectorAll('.edit-btn[data-type]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const type = this.getAttribute('data-type');
                    const row = this.closest('tr');
                    
                    if (type === 'church-property') {
                        document.getElementById('edit-church-property-id').value = id;
                        document.getElementById('edit-church-property-item-name').value = row.cells[1].textContent;
                        document.getElementById('edit-church-property-quantity').value = row.cells[2].textContent;
                        document.getElementById('edit-church-property-notes').value = row.cells[3].textContent || '';
                        openModal('edit-church-property-modal');
                    } else if (type === 'office-supplies') {
                        document.getElementById('edit-office-supplies-id').value = id;
                        document.getElementById('edit-office-supplies-item-name').value = row.cells[1].textContent;
                        document.getElementById('edit-office-supplies-quantity').value = row.cells[2].textContent;
                        document.getElementById('edit-office-supplies-notes').value = row.cells[3].textContent || '';
                        openModal('edit-office-supplies-modal');
                    } else if (type === 'technical-equipments') {
                        document.getElementById('edit-technical-equipments-id').value = id;
                        document.getElementById('edit-technical-equipments-item-name').value = row.cells[1].textContent;
                        document.getElementById('edit-technical-equipments-quantity').value = row.cells[2].textContent;
                        const statusText = row.cells[3].textContent.trim();
                        document.getElementById('edit-technical-equipments-status').value = statusText;
                        document.getElementById('edit-technical-equipments-notes').value = row.cells[4].textContent || '';
                        openModal('edit-technical-equipments-modal');
                    }
                });
            });

            // Delete Button Handlers
            document.querySelectorAll('.delete-btn[data-type]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const type = this.getAttribute('data-type');
                    const row = this.closest('tr');
                    const itemName = row.cells[1].textContent;
                    
                    document.getElementById('delete-record-id').value = id;
                    document.getElementById('delete-record-type').value = type;
                    document.getElementById('delete-confirmation-message').innerHTML = 
                        `Are you sure you want to delete <strong>${itemName}</strong> (ID: ${id})?`;
                    openModal('delete-confirmation-modal');
                });
            });

            // Delete Form Submission Handler
            document.getElementById('delete-form')?.addEventListener('submit', function(e) {
                const deleteType = document.getElementById('delete-record-type').value;
                const form = this;
                
                // Remove any existing delete action inputs
                form.querySelectorAll('input[name^="delete_"]').forEach(input => {
                    if (input.name !== 'delete_type') {
                        input.remove();
                    }
                });
                
                // Create hidden input based on delete type
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                
                if (deleteType === 'church-property') {
                    hiddenInput.name = 'delete_church_property';
                } else if (deleteType === 'office-supplies') {
                    hiddenInput.name = 'delete_office_supplies';
                } else if (deleteType === 'technical-equipments') {
                    hiddenInput.name = 'delete_technical_equipments';
                }
                
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
            });

            // Close View Modal Buttons
            document.getElementById('view-church-property-exit-btn')?.addEventListener('click', function() {
                closeModal('view-church-property-modal');
            });

            document.getElementById('view-office-supplies-exit-btn')?.addEventListener('click', function() {
                closeModal('view-office-supplies-modal');
            });

            document.getElementById('view-technical-equipments-exit-btn')?.addEventListener('click', function() {
                closeModal('view-technical-equipments-modal');
            });

            // Close Edit Modal Buttons
            document.getElementById('edit-church-property-exit-btn')?.addEventListener('click', function() {
                closeModal('edit-church-property-modal');
            });

            document.getElementById('edit-office-supplies-exit-btn')?.addEventListener('click', function() {
                closeModal('edit-office-supplies-modal');
            });

            document.getElementById('edit-technical-equipments-exit-btn')?.addEventListener('click', function() {
                closeModal('edit-technical-equipments-modal');
            });

            // Close Delete Confirmation Modal
            document.getElementById('delete-confirmation-exit-btn')?.addEventListener('click', function() {
                closeModal('delete-confirmation-modal');
            });

            // Close modals when clicking outside
            ['view-church-property-modal', 'view-office-supplies-modal', 'view-technical-equipments-modal',
             'edit-church-property-modal', 'edit-office-supplies-modal', 'edit-technical-equipments-modal',
             'delete-confirmation-modal'].forEach(modalId => {
                document.getElementById(modalId)?.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(modalId);
                    }
                });
            });
        });
    </script>
</body>
</html>

