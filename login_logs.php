<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in and is super admin only
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "Super Admin") {
    header("Location: index.php");
    exit;
}

// Get church logo
$church_logo = getChurchLogo($conn);
// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);
// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get all login logs (DataTables will handle pagination)
$sql = "SELECT * FROM login_logs ORDER BY login_time DESC";
$result = $conn->query($sql);
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
        body { background-color: var(--light-gray); color: var(--primary-color); margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .content-area { flex: 1; margin-left: 0; padding: 20px; min-height: 100vh; background-color: #f5f5f5; padding-top: 80px; }
        /* Drawer Navigation Styles (from superadmin_dashboard.php) */
        .nav-toggle-container { position: fixed; top: 20px; left: 20px; z-index: 50; }
        .nav-toggle-btn { background-color: #3b82f6; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; transition: background-color 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; }
        .nav-toggle-btn:hover { background-color: #2563eb; }
        .custom-drawer { position: fixed; top: 0; left: -300px; width: 300px; height: 100vh; background: linear-gradient(135deg, #f8fafc 0%, #e0e7ef 100%); color: #3a3a3a; z-index: 1000; transition: left 0.3s ease; overflow-y: auto; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; justify-content: space-between; }
        .custom-drawer.open { left: 0; }
        .drawer-header { padding: 20px; border-bottom: 1px solid rgba(0, 0, 0, 0.1); display: flex; justify-content: space-between; align-items: flex-start; min-height: 120px; }
        .drawer-logo-section { display: flex; flex-direction: column; align-items: center; gap: 10px; min-height: 100px; justify-content: center; flex: 1; }
        .drawer-logo { height: 60px; width: auto; max-width: 200px; object-fit: contain; flex-shrink: 0; }
        .drawer-title { font-size: 16px; font-weight: bold; margin: 0; text-align: center; color: #3a3a3a; max-width: 200px; word-wrap: break-word; line-height: 1.2; min-height: 20px; }
        .drawer-close { background: none; border: none; color: #3a3a3a; font-size: 20px; cursor: pointer; padding: 5px; }
        .drawer-close:hover { color: #666; }
        .drawer-content { padding: 20px 0 0 0; flex: 1; }
        .drawer-menu { list-style: none; margin: 0; padding: 0; }
        .drawer-menu li { margin: 0; }
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
        .drawer-link.active { background: linear-gradient(90deg, #e0ffe7 0%, #f5f5f5 100%); border-left: 4px solid var(--accent-color); color: var(--accent-color); }
        .drawer-link.active i { color: var(--accent-color); }
        .drawer-link:hover { background: rgba(0, 139, 30, 0.07); color: var(--accent-color); }
        .drawer-link:hover i { color: var(--accent-color); }
        .drawer-profile { padding: 24px 20px 20px 20px; border-top: 1px solid #e5e7eb; display: flex; align-items: center; gap: 14px; background: rgba(255,255,255,0.85); }
        .drawer-profile .avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--accent-color); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; overflow: hidden; }
        .drawer-profile .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .drawer-profile .profile-info { flex: 1; }
        .drawer-profile .name { font-size: 16px; font-weight: 600; color: #222; }
        .drawer-profile .role { font-size: 13px; color: var(--accent-color); font-weight: 500; margin-top: 2px; }
        .drawer-profile .logout-btn { background: #f44336; color: #fff; border: none; padding: 7px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; margin-left: 10px; cursor: pointer; transition: background 0.2s; }
        .drawer-profile .logout-btn:hover { background: #d32f2f; }
        .drawer-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .drawer-overlay.open { opacity: 1; visibility: visible; }
        /* Stats Cards */
        .stats-cards { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .stats-card { background: var(--white); border-radius: 8px; padding: 18px 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); flex: 1; text-align: center; min-width: 180px; margin-bottom: 10px; }
        .stats-card h3 { font-size: 16px; color: #666; margin-bottom: 8px; }
        .stats-card p { font-size: 22px; font-weight: bold; color: var(--accent-color); margin: 0; }
        .stats-card .stat-label { font-size: 13px; color: #888; margin-top: 4px; }
        /* Table Styles */
        .logs-table { background-color: var(--white); border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden; padding: 20px; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: 600; color: var(--primary-color); }
        tr:hover { background-color: #f8f9fa; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; text-transform: uppercase; }
        .status-badge.success { background-color: #4caf50; color: white; }
        .status-badge.failed { background-color: #f44336; color: white; }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--white);
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
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
        .user-profile .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            overflow: hidden;
        }
        .user-profile .user-info h4 {
            margin: 0;
            color: var(--primary-color);
            font-size: 16px;
        }
        .user-profile .user-info p {
            margin: 0;
            color: var(--accent-color);
            font-size: 13px;
        }
        .user-profile .logout-btn {
            background: #f44336;
            color: #fff;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .user-profile .logout-btn:hover {
            background: #d32f2f;
        }
        @media (max-width: 992px) { .content-area { margin-left: 0; } .stats-cards { flex-direction: column; } }
        @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .content-area { margin-left: 0; } .stats-cards { flex-direction: column; } }
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
                <li><a href="superadmin_dashboard.php" class="drawer-link <?php echo $current_page == 'superadmin_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                <li><a href="events.php" class="drawer-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i><span>Events</span></a></li>
                <li><a href="messages.php" class="drawer-link <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i><span>Messages</span></a></li>
                <li><a href="member_records.php" class="drawer-link <?php echo $current_page == 'member_records.php' ? 'active' : ''; ?>"><i class="fas fa-address-book"></i><span>Member Records</span></a></li>
                <li><a href="prayers.php" class="drawer-link <?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i><span>Prayer Requests</span></a></li>
                <li><a href="superadmin_financialreport.php" class="drawer-link <?php echo $current_page == 'superadmin_financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i><span>Financial Reports</span></a></li>
                <li><a href="superadmin_contribution.php" class="drawer-link <?php echo $current_page == 'superadmin_contribution.php' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-dollar"></i><span>Stewardship Report</span></a></li>
                <li><a href="settings.php" class="drawer-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i><span>Settings</span></a></li>
                <li><a href="login_logs.php" class="drawer-link <?php echo $current_page == 'login_logs.php' ? 'active' : ''; ?>"><i class="fas fa-sign-in-alt"></i><span>Login Logs</span></a></li>
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
        <div class="top-bar" style="background-color: #fff; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 20px; margin-top: 0;">
            <div>
                <h2>Login Logs</h2>
                <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                    Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                </p>
            </div>
        </div>
        <div class="stats-cards">
            <div class="stats-card">
                <h3>Total Login Attempts</h3>
                <p><?php echo $stats['total_attempts']; ?></p>
                <div class="stat-label">All login attempts</div>
            </div>
            <div class="stats-card">
                <h3>Successful Logins</h3>
                <p><?php echo $stats['successful_logins']; ?></p>
                <div class="stat-label">Successes</div>
            </div>
            <div class="stats-card">
                <h3>Failed Logins</h3>
                <p><?php echo $stats['failed_logins']; ?></p>
                <div class="stat-label">Failures</div>
            </div>
            <div class="stats-card">
                <h3>Unique Users</h3>
                <p><?php echo $stats['unique_users']; ?></p>
                <div class="stat-label">Distinct usernames</div>
            </div>
            <div class="stats-card">
                <h3>Success Rate</h3>
                <p><?php echo $success_rate; ?>%</p>
                <div class="stat-label">Success / Attempts</div>
            </div>
        </div>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script>
    // Drawer Navigation JS
    document.addEventListener('DOMContentLoaded', function() {
        const navToggle = document.getElementById('nav-toggle');
        const drawer = document.getElementById('drawer-navigation');
        const drawerClose = document.getElementById('drawer-close');
        const overlay = document.getElementById('drawer-overlay');
        navToggle.addEventListener('click', function() {
            drawer.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
        drawerClose.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDrawer();
            }
        });
    });
    // DataTable
    $(document).ready(function() {
        $('#login-logs-table').DataTable({
            columnDefs: [
                { width: '5%', targets: 0 },
                { width: '12%', targets: 1 },
                { width: '15%', targets: 2 },
                { width: '12%', targets: 3 },
                { width: '8%', targets: 4 },
                { width: '15%', targets: 5 },
                { width: '20%', targets: 6 }
            ],
            autoWidth: false,
            responsive: true
        });
    });
</script>
</body>
</html> 