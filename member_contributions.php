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

$current_page = 'member_contributions.php';
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
$all_contributions = $conn->query($contributions_query);

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

// Get all users for admin dropdown
$users_query = "SELECT user_id, full_name FROM user_profiles WHERE role IN ('Member', 'Pastor') ORDER BY full_name";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Site configuration
$church_name = "Church of Christ-Disciples";

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
            margin-left: var(--sidebar-width);
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
            position: sticky;
            top: 0;
            z-index: 100;
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
                    <li><a href="login_logs.php" class="<?php echo $current_page == 'login_logs.php' ? 'active' : ''; ?>"><i class="fas fa-sign-in-alt"></i> <span>Login Logs</span></a></li>
            </ul>
            </div>
        </aside>

        <main class="content-area">
            <div class="top-bar">
                <h2>Stewardship Report</h2>
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
                        <p><?php echo htmlspecialchars($user_profile['role'] ?? 'User'); ?></p>
                    </div>
                    <form action="logout.php" method="post">
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $all_contributions->fetch_assoc()): ?>
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
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
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
                    { width: '15%', targets: 0 }, // Date
                    { width: '20%', targets: 1 }, // Member Name
                    { width: '10%', targets: 2 }, // Role
                    { width: '10%', targets: 3 }, // Type
                    { width: '15%', targets: 4 }, // Amount
                    { width: '15%', targets: 5 }, // Payment Method
                    { width: '25%', targets: 6 }  // Reference Number
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('adminContributionModal');
            if (event.target == modal) {
                modal.style.display = 'none';
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
    </script>
</body>
</html> 