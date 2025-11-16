<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: index.php");
    exit;
}
// Restrict access to Administrator only
if ($_SESSION["user_role"] !== "Administrator") {
    header("Location: index.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$username = $_SESSION["user"];
$is_admin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;


// Get user's user_id from username
$stmt = $conn->prepare("SELECT user_id FROM user_profiles WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data ? $user_data['user_id'] : null;

// Get user's contributions
$contributions = null;
if ($user_id) {
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.amount,
            c.contribution_type,
            c.contribution_date,
            c.payment_method,
            c.reference_number,
            c.status,
            c.notes,
            up.full_name as member_name,
            up.role as member_role
        FROM contributions c
        JOIN user_profiles up ON c.user_id = up.user_id
        WHERE c.user_id = ?
        ORDER BY c.contribution_date DESC
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $contributions = $stmt->get_result();
}

// Get total contributions for the user
$totals = [
    'total_tithe' => 0,
    'total_offering' => 0,
    'total_contributions' => 0
];
if ($user_id) {
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN c.contribution_type = 'tithe' THEN c.amount ELSE 0 END) as total_tithe,
            SUM(CASE WHEN c.contribution_type = 'offering' THEN c.amount ELSE 0 END) as total_offering,
            SUM(c.amount) as total_contributions
        FROM contributions c
        JOIN user_profiles up ON c.user_id = up.user_id
        WHERE up.user_id = ?
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
}

// Handle form submission for new contribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contribution'])) {
    $amount = floatval($_POST['amount']);
    $contribution_type = $_POST['contribution_type'];
    $payment_method = $_POST['payment_method'];
    $reference_number = $_POST['reference_number'];
    $notes = $_POST['notes'];
    if ($user_id) {
        $stmt = $conn->prepare("
            INSERT INTO contributions (
                user_id, amount, contribution_type, contribution_date, 
                payment_method, reference_number, status, notes
            ) VALUES (?, ?, ?, NOW(), ?, ?, 'pending', ?)
        ");
        $stmt->bind_param("sdssss", $user_id, $amount, $contribution_type, $payment_method, $reference_number, $notes);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Contribution submitted successfully!";
            header("Location: member_contributions.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error submitting contribution. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "User profile not found.";
    }
}

// Get all contributions for admin view
$contributions_query = "
    SELECT 
        c.id,
        c.amount,
        c.contribution_type,
        c.contribution_date,
        c.payment_method,
        c.reference_number,
        up.full_name as member_name,
        up.role as member_role
    FROM contributions c
    JOIN user_profiles up ON c.user_id = up.user_id
    ORDER BY c.contribution_date DESC
";
$all_contributions_result = $conn->query($contributions_query);
$all_contributions = [];
if ($all_contributions_result) {
    while ($row = $all_contributions_result->fetch_assoc()) {
        $all_contributions[] = $row;
    }
}

// Update the admin contribution submission code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_submit_contribution'])) {
    $member_user_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $contribution_type = $_POST['contribution_type'];
    $payment_method = $_POST['payment_method'];
    $reference_number = $_POST['reference_number'];
    $contribution_date = $_POST['contribution_date'];
    // Check if the user exists in user_profiles
    $user_query = "SELECT user_id, full_name FROM user_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("s", $member_user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    if ($user_data) {
        // Now insert the contribution using the user_id and specified date
        $stmt = $conn->prepare("
            INSERT INTO contributions (
                user_id, amount, contribution_type, contribution_date, 
                payment_method, reference_number, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'approved')
        ");
        $stmt->bind_param("sdssss", $member_user_id, $amount, $contribution_type, $contribution_date, $payment_method, $reference_number);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Contribution added successfully for " . $user_data['full_name'] . "!";
            header("Location: member_contributions.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding contribution: " . $stmt->error;
        }
    } else {
        $_SESSION['error_message'] = "User not found in the user profiles.";
    }
}

// Handle edit contribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_contribution'])) {
    $contribution_id = intval($_POST['contribution_id']);
    $amount = floatval($_POST['amount']);
    $contribution_type = $_POST['contribution_type'];
    $payment_method = $_POST['payment_method'];
    $reference_number = $_POST['reference_number'];
    $contribution_date = $_POST['contribution_date'];
    
    $stmt = $conn->prepare("
        UPDATE contributions 
        SET amount = ?, contribution_type = ?, payment_method = ?, 
            reference_number = ?, contribution_date = ?
        WHERE id = ?
    ");
    $stmt->bind_param("dssssi", $amount, $contribution_type, $payment_method, $reference_number, $contribution_date, $contribution_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Contribution updated successfully!";
        header("Location: member_contributions.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating contribution: " . $stmt->error;
    }
}

// Handle delete contribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_contribution'])) {
    $contribution_id = intval($_POST['contribution_id']);
    
    $stmt = $conn->prepare("DELETE FROM contributions WHERE id = ?");
    $stmt->bind_param("i", $contribution_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Contribution deleted successfully!";
        header("Location: member_contributions.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error deleting contribution: " . $stmt->error;
    }
}

// Get contribution data for editing
$edit_contribution = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("
        SELECT c.*, up.full_name as member_name 
        FROM contributions c
        JOIN user_profiles up ON c.user_id = up.user_id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_contribution = $result->fetch_assoc();
}

// Get all users for admin dropdown
$users_query = "SELECT user_id, full_name FROM user_profiles WHERE role IN ('Member', 'Pastor') ORDER BY full_name";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add_contribution"])) {
        $member_user_id = $_POST["member_id"];
        $amount = $_POST["amount"];
        $contribution_type = $_POST["contribution_type"];
        $contribution_date = $_POST["contribution_date"];
        $payment_method = $_POST["payment_method"];
        $notes = $_POST["notes"];

        // Get member name from user_profiles
        $stmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_id = ?");
        $stmt->bind_param("s", $member_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $member = $result->fetch_assoc();

        if (!$member) {
            $message = "Member not found in the system.";
            $messageType = "danger";
        } else {
            // Insert contribution
            $stmt = $conn->prepare("INSERT INTO contributions (user_id, amount, contribution_type, contribution_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdssss", $member_user_id, $amount, $contribution_type, $contribution_date, $payment_method, $notes);
            
            if ($stmt->execute()) {
                $message = "Contribution added successfully!";
                $messageType = "success";
            } else {
                $message = "Error adding contribution: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stewardship Report | <?php echo $church_name; ?></title>
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

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .sidebar-header img {
            height: 60px;
            margin-bottom: 10px;
            transition: 0.3s;
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
            transition: all 0.3s;
            font-size: 16px;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu a.active {
            background-color: var(--accent-color);
        }

        .sidebar-menu i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 20px;
        }

        .sidebar-menu span {
            margin-left: 10px;
        }

        /* Main Content Area */
        .content-area {
            flex: 1;
            margin-left: 0;
            padding: 20px;
            min-height: 100vh;
            background-color: #f5f5f5;
        }

        /* Top Bar */
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
            color: var(--primary-color);
            font-size: 24px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-info h4 {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 14px;
            color: #666;
        }

        .logout-btn {
            padding: 8px 15px;
            background-color: #f0f0f0;
            color: var(--primary-color);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: #e0e0e0;
        }

        /* Content */
        .content {
            padding: 20px;
            background-color: #f5f5f5;
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: var(--white);
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card h2 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .card-icon {
            font-size: 24px;
            color: var(--accent-color);
            margin-bottom: 10px;
        }

        .card-info h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .card-info p {
            font-size: 24px;
            font-weight: bold;
            color: var(--accent-color);
        }

        /* Forms */
        .contribution-form {
            padding: 20px;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .form-actions button {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .form-actions .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }

        .form-actions .btn-primary:hover {
            background-color: rgb(0, 112, 24);
        }

        .form-actions .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .form-actions .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Alerts */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }

        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.pending {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }

        .status-badge.approved {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .status-badge.rejected {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h3,
            .sidebar-menu span {
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
        }

        /* Action Bar Styles */
        .action-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
            padding: 10px;
            background-color: var(--white);
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .action-bar .btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 500;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .action-bar .btn:hover {
            background-color: rgb(0, 112, 24);
        }

        .action-bar .btn i {
            font-size: 18px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--white);
            margin: 2% auto;
            padding: 20px;
            border-radius: 5px;
            width: 90%;
            max-width: 600px;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--primary-color);
        }

        /* Status Badge Updates */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Alert Updates */
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: opacity 0.3s ease;
        }

        .alert i {
            margin-right: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive Updates */
        @media (max-width: 768px) {
            .modal-content {
                margin: 5% auto;
                width: 95%;
                padding: 15px;
                max-height: 85vh;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
            }

            .dataTables_wrapper {
                overflow-x: auto;
            }

            th, td {
                padding: 10px;
                font-size: 14px;
            }
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
            table-layout: fixed; /* Prevent layout shifts */
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            word-wrap: break-word; /* Handle long content */
            overflow-wrap: break-word;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky; /* Keep headers visible */
            top: 0;
            z-index: 10;
        }
        
        table tr:hover {
            background-color: #f5f5f5;
        }
        
        /* Prevent DataTable layout shifts */
        .dataTables_wrapper {
            width: 100%;
        }
        
        .dataTables_scroll {
            overflow-x: auto;
        }
        
        /* Ensure table doesn't move during initialization */
        #contributionsTable {
            visibility: hidden;
        }
        
        #contributionsTable.dataTable {
            visibility: visible;
        }
        /* Role Badge Styles (copied from settings.php) */
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
        /* Action Buttons */
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
        /* Ensure content doesn't overlap with the button */
        .content-area {
            padding-top: 80px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
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
                        <a href="admin_expenses.php" class="drawer-link <?php echo $current_page == 'admin_expenses.php' ? 'active' : ''; ?>">
                            <i class="fas fa-receipt"></i>
                            <span>Monthly Expenses</span>
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
                    <div class="name"><?php echo htmlspecialchars($user_profile['username'] ?? 'Unknown User'); ?></div>
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
            <div class="top-bar" style="background-color: #fff; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 20px; margin-top: 0;">
                <div>
                    <h2>Stewardship Report</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>

            <div class="content">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-error">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Admin Button - Always show for testing -->
                <div class="action-bar">
                    <button class="btn btn-primary" onclick="openAdminModal()">
                        <i class="fas fa-plus-circle"></i> Add New Contribution
                    </button>
                </div>

                <!-- Contributions Table -->
                <div class="card">
                    <h2>Stewardship Report</h2>
                    <div class="table-responsive">
                        <table id="contributionsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member Name</th>
                                    <th>Role</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Reference Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_contributions as $row): ?>
                                <tr>
                                    <td><strong><?php echo date('F d, Y', strtotime($row['contribution_date'])); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo strtolower($row['member_role']); ?>">
                                            <?php echo htmlspecialchars($row['member_role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($row['contribution_type']); ?></td>
                                    <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['reference_number']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="action-btn edit-btn" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['member_name'], ENT_QUOTES); ?>', '<?php echo $row['contribution_date']; ?>', <?php echo $row['amount']; ?>, '<?php echo $row['contribution_type']; ?>', '<?php echo $row['payment_method']; ?>', '<?php echo htmlspecialchars($row['reference_number'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="action-btn delete-btn" onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Contribution Modal -->
    <div id="editContributionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Contribution</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="" class="contribution-form" id="editContributionForm">
                <input type="hidden" name="contribution_id" id="edit_contribution_id">
                <div class="form-group">
                    <label>Member Name</label>
                    <input type="text" id="edit_member_name" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label for="edit_contribution_date">Date</label>
                    <input type="date" id="edit_contribution_date" name="contribution_date" required>
                </div>
                <div class="form-group">
                    <label for="edit_amount">Amount (₱)</label>
                    <input type="number" id="edit_amount" name="amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="edit_contribution_type">Contribution Type</label>
                    <select id="edit_contribution_type" name="contribution_type" required>
                        <option value="tithe">Tithe</option>
                        <option value="offering">Offering</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_payment_method">Payment Method</label>
                    <select id="edit_payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_reference_number">Reference Number</label>
                    <input type="text" id="edit_reference_number" name="reference_number">
                </div>
                <div class="form-actions">
                    <button type="submit" name="edit_contribution" class="btn btn-primary">Update Contribution</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <form method="POST" action="" id="deleteContributionForm">
                <input type="hidden" name="contribution_id" id="delete_contribution_id">
                <p style="margin: 20px 0; font-size: 16px;">Are you sure you want to delete this contribution? This action cannot be undone.</p>
                <div class="form-actions">
                    <button type="submit" name="delete_contribution" class="btn btn-primary" style="background-color: #f44336;">Yes, Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Admin Contribution Modal -->
    <div id="adminContributionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Member Contribution</h3>
                <span class="close" onclick="closeAdminModal()">&times;</span>
            </div>
            <form method="POST" action="" class="contribution-form">
                <div class="form-group">
                    <label for="member_id">Member Name</label>
                    <select id="member_id" name="member_id" required>
                        <option value="">Select Member</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="contribution_date">Date</label>
                    <input type="date" id="contribution_date" name="contribution_date" required>
                    <small style="color: #666; font-size: 12px;">Select the date when the transaction was made</small>
                </div>
                <div class="form-group">
                    <label for="amount">Amount (₱)</label>
                    <input type="number" id="amount" name="amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="contribution_type">Contribution Type</label>
                    <select id="contribution_type" name="contribution_type" required>
                        <option value="tithe">Tithe</option>
                        <option value="offering">Offering</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number">
                </div>
                <div class="form-actions">
                    <button type="submit" name="admin_submit_contribution" class="btn btn-primary">Submit Contribution</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAdminModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#contributionsTable').DataTable({
                columnDefs: [
                    { width: '12%', targets: 0 }, // Date
                    { width: '15%', targets: 1 }, // Member Name
                    { width: '8%', targets: 2 }, // Role
                    { width: '8%', targets: 3 }, // Type
                    { width: '12%', targets: 4 }, // Amount
                    { width: '12%', targets: 5 }, // Payment Method
                    { width: '15%', targets: 6 }, // Reference Number
                    { width: '18%', targets: 7, orderable: false }  // Actions
                ],
                autoWidth: false,
                responsive: true
            });
        });

        function openAdminModal() {
            document.getElementById('adminContributionModal').style.display = 'block';
            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('contribution_date').value = today;
        }

        function closeAdminModal() {
            document.getElementById('adminContributionModal').style.display = 'none';
        }

        // Edit Modal Functions
        function openEditModal(id, memberName, date, amount, type, paymentMethod, referenceNumber) {
            document.getElementById('edit_contribution_id').value = id;
            document.getElementById('edit_member_name').value = memberName;
            document.getElementById('edit_contribution_date').value = date;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_contribution_type').value = type;
            document.getElementById('edit_payment_method').value = paymentMethod;
            document.getElementById('edit_reference_number').value = referenceNumber || '';
            document.getElementById('editContributionModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editContributionModal').style.display = 'none';
        }

        // Delete Modal Functions
        function confirmDelete(id) {
            document.getElementById('delete_contribution_id').value = id;
            document.getElementById('deleteConfirmModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const adminModal = document.getElementById('adminContributionModal');
            const editModal = document.getElementById('editContributionModal');
            const deleteModal = document.getElementById('deleteConfirmModal');
            
            if (event.target == adminModal) {
                adminModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
            }
        }

        // Auto-hide alerts after 3 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 3000);
        });

        // Drawer Navigation JS (copied from superadmin_dashboard.php)
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
</html> 