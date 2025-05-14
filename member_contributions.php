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
    header("Location: login.php");
    exit;
}

$current_page = 'member_contributions.php';
$user_id = $_SESSION["user"];
$is_admin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;

// Get user's contributions
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.amount,
        c.contribution_type,
        c.contribution_date,
        c.payment_method,
        c.reference_number,
        c.status,
        c.notes
    FROM contributions c
    WHERE c.user_id = ?
    ORDER BY c.contribution_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$contributions = $stmt->get_result();

// Get total contributions
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN contribution_type = 'tithe' THEN amount ELSE 0 END) as total_tithe,
        SUM(CASE WHEN contribution_type = 'offering' THEN amount ELSE 0 END) as total_offering,
        SUM(amount) as total_contributions
    FROM contributions 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();

// Handle form submission for new contribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contribution'])) {
    $amount = floatval($_POST['amount']);
    $contribution_type = $_POST['contribution_type'];
    $payment_method = $_POST['payment_method'];
    $reference_number = $_POST['reference_number'];
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("
        INSERT INTO contributions (
            user_id, amount, contribution_type, contribution_date, 
            payment_method, reference_number, status, notes
        ) VALUES (?, ?, ?, NOW(), ?, ?, 'pending', ?)
    ");
    $stmt->bind_param("idssss", $user_id, $amount, $contribution_type, $payment_method, $reference_number, $notes);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Contribution submitted successfully!";
        header("Location: member_contributions.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error submitting contribution. Please try again.";
    }
}

// Create a temporary table to handle the collation mismatch
$conn->query("DROP TEMPORARY TABLE IF EXISTS temp_members");
$conn->query("
    CREATE TEMPORARY TABLE temp_members (
        id INT,
        name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
        user_id INT
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
");

// Insert data into temporary table with explicit collation
$conn->query("
    INSERT INTO temp_members (id, name, user_id)
    SELECT 
        m.id,
        m.name COLLATE utf8mb4_general_ci,
        u.id
    FROM membership_records m
    LEFT JOIN users u ON u.username COLLATE utf8mb4_general_ci = m.name COLLATE utf8mb4_general_ci
");

// Update the contributions query to use the temporary table
$contributions_query = "
    SELECT 
        c.id,
        c.amount,
        c.contribution_type,
        c.contribution_date,
        c.payment_method,
        c.reference_number,
        c.status,
        tm.name as member_name
    FROM contributions c
    JOIN users u ON c.user_id = u.id
    JOIN temp_members tm ON u.id = tm.user_id
    ORDER BY c.contribution_date DESC
";
$contributions = $conn->query($contributions_query);

// Update the admin contribution submission code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_submit_contribution'])) {
    $member_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $contribution_type = $_POST['contribution_type'];
    $payment_method = $_POST['payment_method'];
    $reference_number = $_POST['reference_number'];
    
    // First get the member's name
    $member_query = "SELECT name FROM membership_records WHERE id = ?";
    $stmt = $conn->prepare($member_query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $member_result = $stmt->get_result();
    $member_data = $member_result->fetch_assoc();
    
    if ($member_data) {
        $member_name = $member_data['name'];
        
        // Then find the user by converting both names to the same case
        $user_query = "SELECT id FROM users WHERE LOWER(username) = LOWER(?)";
        $stmt = $conn->prepare($user_query);
        $stmt->bind_param("s", $member_name);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        
        if ($user_data) {
            $user_id = $user_data['id'];
            
            // Now insert the contribution using the user_id
            $stmt = $conn->prepare("
                INSERT INTO contributions (
                    user_id, amount, contribution_type, contribution_date, 
                    payment_method, reference_number, status
                ) VALUES (?, ?, ?, NOW(), ?, ?, 'approved')
            ");
            $stmt->bind_param("idsss", $user_id, $amount, $contribution_type, $payment_method, $reference_number);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Contribution added successfully!";
                header("Location: member_contributions.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error adding contribution: " . $stmt->error;
            }
        } else {
            $_SESSION['error_message'] = "Member '$member_name' not found in the users system. Please ensure the member has a user account.";
        }
    } else {
        $_SESSION['error_message'] = "Member not found in the membership records.";
    }
}

// Update the members query to use the temporary table
$members_query = "SELECT id, name FROM membership_records WHERE status = 'Active' ORDER BY name";
$members_result = $conn->query($members_query);
$members = [];
while ($row = $members_result->fetch_assoc()) {
    $members[] = $row;
}

// Site configuration
$church_name = "Church of Christ-Disciples";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Contributions | <?php echo $church_name; ?></title>
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
        }

        .sidebar-header img {
            width: 60px;
            height: 60px;
            margin-bottom: 10px;
        }

        .sidebar-header h3 {
            font-size: 18px;
            color: var(--white);
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

        /* Updated Table Styles */
        .table-responsive {
            margin-top: 0;
            background-color: var(--white);
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        th {
            background-color: #f8f9fa;
            color: var(--primary-color);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        tr:hover {
            background-color: #f8f9fa;
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
                position: fixed;
                height: 100vh;
            }
            .sidebar-header h3,
            .sidebar-menu span {
                display: none;
            }
            .content-area {
                margin-left: 70px;
            }
            .top-bar {
                margin-left: 0;
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
            .content-area {
                margin-left: 0;
                padding: 10px;
            }
            .content {
                padding: 10px;
            }
            .summary-cards {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                gap: 10px;
                padding: 10px;
            }
            .user-profile {
                width: 100%;
                justify-content: space-between;
            }
            .card {
                padding: 15px;
            }
            .table-responsive {
                padding: 10px;
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
            margin: 5% auto;
            padding: 20px;
            border-radius: 5px;
            width: 90%;
            max-width: 600px;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
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
                margin: 10% auto;
                width: 95%;
                padding: 15px;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
            }

            .table-responsive {
                overflow-x: auto;
            }

            th, td {
                padding: 10px;
                font-size: 14px;
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
                    <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="events.php" class="<?php echo $current_page == 'events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i> <span>Messages</span></a></li>
                    <li><a href="member_records.php" class="<?php echo $current_page == 'member_records.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span>Member Records</span></a></li>
                    <li><a href="prayers.php" class="<?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                    <li><a href="financialreport.php" class="<?php echo $current_page == 'financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span>Financial Reports</span></a></li>
                    <li><a href="member_contributions.php" class="<?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-dollar"></i> <span>Member Contributions</span></a></li>
                    <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                </ul>
            </div>
        </aside>

        <main class="content-area">
            <div class="top-bar">
                <h2>Member Contributions</h2>
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

                <!-- Contribution Summary Cards -->
                <div class="summary-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-hand-holding-dollar"></i>
                        </div>
                        <div class="card-info">
                            <h3>Total Contributions</h3>
                            <p>₱<?php echo number_format($totals['total_contributions'] ?? 0, 2); ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="card-info">
                            <h3>Total Tithes</h3>
                            <p>₱<?php echo number_format($totals['total_tithe'] ?? 0, 2); ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="card-info">
                            <h3>Total Offerings</h3>
                            <p>₱<?php echo number_format($totals['total_offering'] ?? 0, 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Contributions Table -->
                <div class="card">
                    <h2>Member Contributions</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member Name</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $contributions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['contribution_date'])); ?></td>
                                    <td><?php echo ucfirst($row['contribution_type']); ?></td>
                                    <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['reference_number']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $row['status']; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
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
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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

    <script>
        function openAdminModal() {
            document.getElementById('adminContributionModal').style.display = 'block';
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