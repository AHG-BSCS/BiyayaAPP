<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: index.php");
    exit;
}

// Check if user is an admin
if ($_SESSION["user_role"] !== "Administrator") {
    header("Location: member_dashboard.php");
    exit;
}

// Get church logo
$church_logo = getChurchLogo($conn);

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Handle delete action
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_log"])) {
    $log_id = $_POST["delete_log_id"];
    
    $delete_sql = "DELETE FROM login_logs WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $log_id);
    
    if ($stmt->execute()) {
        $message = "Login log deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Error deleting login log: " . $conn->error;
        $messageType = "danger";
    }
}

// DataTables will handle pagination, so we fetch all records

// Get all login logs (no filters needed since DataTables handles searching)
$where_clause = '';
$params = [];
$param_types = '';

// Get total count for statistics
$count_sql = "SELECT COUNT(*) as total FROM login_logs $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}
$total_logs = $count_result->fetch_assoc()['total'];

// Get all login logs (DataTables will handle pagination)
$sql = "SELECT * FROM login_logs $where_clause ORDER BY login_time DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$login_logs = [];
while ($row = $result->fetch_assoc()) {
    $login_logs[] = $row;
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_attempts,
    SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as successful_logins,
    SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed_logins,
    COUNT(DISTINCT username) as unique_users
FROM login_logs";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Calculate success rate
$success_rate = $stats['total_attempts'] > 0 ? round(($stats['successful_logins'] / $stats['total_attempts']) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Logs | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
    <style>
        :root {
            --primary-color: #3a3a3a;
            --accent-color: rgb(0, 139, 30);
            --light-gray: #f5f5f5;
            --white: #ffffff;
            --sidebar-width: 250px;
            --danger-color: #f44336;
        }
        body {
            background-color: var(--light-gray);
            color: var(--primary-color);
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            padding: 0;
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
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-badge.success {
            background-color: #4caf50;
            color: white;
        }

        .status-badge.failed {
            background-color: #f44336;
            color: white;
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
        .logs-table {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            padding: 20px;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--primary-color);
        }
        tr:hover {
            background-color: #f8f9fa;
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
        
        .action-btn.delete-btn {
            background-color: var(--danger-color);
        }
        
        .action-btn.delete-btn:hover {
            background-color: #c0392b;
        }
        
        .action-btn i {
            font-size: 14px;
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
            max-width: 500px;
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
        }
        
        .btn-outline:hover {
            background-color: var(--accent-color);
            color: var(--white);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
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
            color: #4caf50;
        }
        
        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
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
            <h2>Login Logs</h2>
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
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>" id="message-alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="logs-table">
            <div class="table-container">
                <table id="login-logs-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Login Time</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Failure Reason</th>
                            <th>User Agent</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($login_logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['login_time']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($log['status']); ?>">
                                        <?php echo htmlspecialchars($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $log['failure_reason'] ? htmlspecialchars($log['failure_reason']) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($log['user_agent']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="action-btn delete-btn" onclick="deleteLog(<?php echo $log['id']; ?>)">
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
    </main>
</div>

<!-- Delete Log Confirmation Modal -->
<div class="modal" id="delete-log-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Login Log</h3>
            <button class="modal-close">×</button>
        </div>
        <form action="" method="post">
            <input type="hidden" id="delete_log_id" name="delete_log_id">
            <p>Are you sure you want to delete this login log? This action cannot be undone.</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
                <button type="submit" class="btn btn-danger" name="delete_log">Delete Log</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#login-logs-table').DataTable({
            columnDefs: [
                { width: '5%', targets: 0 }, // ID
                { width: '12%', targets: 1 }, // Username
                { width: '15%', targets: 2 }, // Login Time
                { width: '12%', targets: 3 }, // IP Address
                { width: '8%', targets: 4 }, // Status
                { width: '15%', targets: 5 }, // Failure Reason
                { width: '20%', targets: 6 }, // User Agent
                { width: '8%', targets: 7 }  // Actions
            ],
            autoWidth: false,
            responsive: true
        });
        
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
        
        // Modal functions
        const modal = document.getElementById('delete-log-modal');
        const closeModalBtns = document.querySelectorAll('.modal-close, .modal-close-btn');
        
        // Close modals
        closeModalBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                modal.classList.remove('show');
            });
        });
        
        // Close modal when clicking outside the modal content
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });

    // Delete log function
    function deleteLog(id) {
        const modal = document.getElementById('delete-log-modal');
        document.getElementById('delete_log_id').value = id;
        modal.classList.add('show');
    }
</script>
</body>
</html> 