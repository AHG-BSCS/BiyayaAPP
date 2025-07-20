<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in and is super administrator    
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "Super Admin") {
    header("Location: index.php");
    exit;
}

// Get user profile and site settings
$user_profile = getUserProfile($conn, $_SESSION["user"]);
$church_logo = getChurchLogo($conn);
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get all contributions for all users
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

// Get total contributions summary
$totals = [
    'total_tithe' => 0,
    'total_offering' => 0,
    'total_contributions' => 0
];
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN c.contribution_type = 'tithe' THEN c.amount ELSE 0 END) as total_tithe,
        SUM(CASE WHEN c.contribution_type = 'offering' THEN c.amount ELSE 0 END) as total_offering,
        SUM(c.amount) as total_contributions
    FROM contributions c
");
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Stewardship Report | <?php echo $church_name; ?></title>
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f5f5; color: var(--primary-color); line-height: 1.6; }
        .dashboard-container { display: flex; min-height: 100vh; }
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
        .content-area { flex: 1; margin-left: 0; padding: 20px; min-height: 100vh; background-color: #f5f5f5; padding-top: 80px; }
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
        /* Summary Cards */
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background-color: var(--white); padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
        .card h2 { margin-bottom: 20px; color: var(--primary-color); }
        .card-info h3 { font-size: 16px; margin-bottom: 5px; color: var(--primary-color); }
        .card-info p { font-size: 24px; font-weight: bold; color: var(--accent-color); }
        /* Table Styles */
        .table-responsive { overflow-x: auto; margin-top: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; table-layout: fixed; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; }
        table th { background-color: #f8f9fa; font-weight: 600; color: #333; position: sticky; top: 0; z-index: 10; }
        table tr:hover { background-color: #f5f5f5; }
        .dataTables_wrapper { width: 100%; }
        .dataTables_scroll { overflow-x: auto; }
        #contributionsTable { visibility: hidden; }
        #contributionsTable.dataTable { visibility: visible; }
        /* Role Badge Styles */
        .role-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; text-transform: uppercase; }
        .role-badge.administrator { background-color: #4a90e2; color: white; }
        .role-badge.pastor { background-color: #2ecc71; color: white; }
        .role-badge.member { background-color: #95a5a6; color: white; }
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
                    <li><a href="prayers.php" class="drawer-link <?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i><span>Prayer Requests</span></a></li>
                    <li><a href="messages.php" class="drawer-link <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i><span>Messages</span></a></li>
                    <li><a href="member_records.php" class="drawer-link <?php echo $current_page == 'member_records.php' ? 'active' : ''; ?>"><i class="fas fa-address-book"></i><span>Member Records</span></a></li>
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
            <div class="top-bar">
                <div>
                    <h2>Stewardship Report</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>
            <div class="summary-cards">
                <div class="card">
                    <div class="card-info">
                        <h3>Total Tithes</h3>
                        <p>₱<?php echo number_format($totals['total_tithe'], 2); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-info">
                        <h3>Total Offerings</h3>
                        <p>₱<?php echo number_format($totals['total_offering'], 2); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-info">
                        <h3>Total Amount</h3>
                        <p>₱<?php echo number_format($totals['total_contributions'], 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="card">
                <h2>All Member Stewardship Report</h2>
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
            $('#contributionsTable').DataTable({
                columnDefs: [
                    { width: '15%', targets: 0 },
                    { width: '20%', targets: 1 },
                    { width: '10%', targets: 2 },
                    { width: '10%', targets: 3 },
                    { width: '15%', targets: 4 },
                    { width: '15%', targets: 5 },
                    { width: '25%', targets: 6 }
                ],
                autoWidth: false,
                responsive: true
            });
        });
    </script>
</body>
</html> 