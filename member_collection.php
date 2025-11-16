<?php
// Member Collection (Overview) page
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: index.php");
    exit;
}

// Check if the user is a member
$is_member = ($_SESSION["user_role"] === "Member");

// If not a member, redirect to standard dashboard
if (!$is_member) {
    header("Location: index.php");
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Get church logo
$church_logo = getChurchLogo($conn);

// Retrieve contributions for the logged-in member from database
$username = $_SESSION["user"];
$donations = [];

// Get the correct user_id from user_profiles table
$stmt = $conn->prepare("SELECT user_id FROM user_profiles WHERE username = ? OR user_id = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    $user_id = $user_data['user_id'];
} else {
    $user_id = $username; // Fallback to session value
}

// Get user's contributions from database - Fixed query to use proper user_id
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.amount,
        c.contribution_type,
        c.contribution_date,
        c.payment_method,
        c.reference_number,
        c.status
    FROM contributions c
    WHERE c.user_id = ?
    ORDER BY c.contribution_date DESC
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $donations[] = [
        'id' => $row['id'],
        'date' => date('M d, Y', strtotime($row['contribution_date'])),
        'contribution_date' => $row['contribution_date'], // Add raw date for graph
        'amount' => $row['amount'],
        'purpose' => ucfirst($row['contribution_type']),
        'payment_method' => ucfirst(str_replace('_', ' ', $row['payment_method'] ?? '')),
        'reference_number' => $row['reference_number'] ?? '',
        'status' => $row['status']
    ];
}

// Calculate total donations
$total_donated = array_sum(array_column($donations, 'amount'));

// Calculate totals by type
$total_tithes = 0;
$total_offerings = 0;

foreach ($donations as $donation) {
    if (strtolower($donation['purpose']) === 'tithe') {
        $total_tithes += $donation['amount'];
    } elseif (strtolower($donation['purpose']) === 'offering') {
        $total_offerings += $donation['amount'];
    }
}

// Get recent contributions count
$recent_contributions = count(array_filter($donations, function($donation) {
    return strtotime($donation['date']) >= strtotime('-30 days');
}));

// Find the earliest and latest contribution dates
$earliest_date = null;
$latest_date = null;
if (!empty($donations)) {
    $dates = array_map(function($d) { return date('Y-m-01', strtotime($d['date'])); }, $donations);
    $earliest_date = min($dates);
    $latest_date = max($dates);
} else {
    $earliest_date = date('Y-01-01');
    $latest_date = date('Y-m-01');
}
// Build month range from earliest to latest
$start = new DateTime($earliest_date);
$end = new DateTime($latest_date);
$end->modify('+1 month'); // include last month
$month_labels = [];
$monthly_contributions = [];
$month_map = [];
foreach ($donations as $donation) {
    // Use the original contribution_date for accurate month mapping
    $orig_date = null;
    if (isset($donation['contribution_date'])) {
        $orig_date = $donation['contribution_date'];
    } else if (isset($donation['date'])) {
        // fallback if only formatted date is present
        $orig_date = $donation['date'];
    }
    $month = date('Y-m', strtotime($orig_date));
    if (!isset($month_map[$month])) $month_map[$month] = 0;
    $month_map[$month] += $donation['amount'];
}
for ($dt = clone $start; $dt < $end; $dt->modify('+1 month')) {
    $label = $dt->format('M Y');
    $key = $dt->format('Y-m');
    $month_labels[] = $label;
    $monthly_contributions[] = isset($month_map[$key]) ? $month_map[$key] : 0;
}

$live_message = getLiveMessage($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Report | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/flowbite@2.2.1/dist/flowbite.min.js"></script>
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
        
        .content-area {
            flex: 1;
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
            margin-top: 60px;
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
        
        .dashboard-content {
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
        }
        
        .card p {
            font-size: 24px;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .amount-display {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .amount-display p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .toggle-btn {
            background: none;
            border: none;
            color: var(--accent-color);
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }
        
        .toggle-btn:hover {
            background-color: rgba(0, 139, 30, 0.1);
            transform: scale(1.1);
        }
        
        .toggle-btn:active {
            transform: scale(0.95);
        }
        
        .amount-hidden {
            letter-spacing: 2px;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            min-width: 600px;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eeeeee;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        table th {
            background-color: #f5f5f5;
            font-weight: 600;
            color: var(--primary-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        /* Prevent DataTable layout shifts */
        .dataTables_wrapper {
            width: 100%;
        }
        
        .dataTables_scroll {
            overflow-x: auto;
        }
        
        /* Ensure table doesn't move during initialization */
        #contributions-table {
            visibility: hidden;
        }
        
        #contributions-table.dataTable {
            visibility: visible;
        }
        
        .live-alert {
            display: block;
            background: linear-gradient(90deg, #e3f0ff 0%, #f5faff 100%);
            color: #155fa0;
            border: 1px solid #b6d4fe;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(21,95,160,0.07);
            padding: 12px 18px;
            margin-bottom: 16px;
            font-size: 14px;
            position: relative;
            transition: background 0.2s;
        }
        .live-alert .live-alert-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 15px;
        }
        .live-alert .live-alert-body {
            margin: 6px 0 10px 0;
            font-size: 13px;
        }
        .live-alert .live-alert-actions {
            display: flex;
            gap: 8px;
        }
        .live-alert .live-alert-btn {
            background: #155fa0;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 4px 12px;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .live-alert .live-alert-btn:hover {
            background: #0d4377;
        }
        .live-alert .live-alert-dismiss {
            background: transparent;
            color: #155fa0;
            border: 1px solid #b6d4fe;
            padding: 4px 12px;
        }
        .live-alert .live-alert-dismiss:hover {
            background: #e3f0ff;
        }
        @media (prefers-color-scheme: dark) {
            .live-alert {
                background: linear-gradient(90deg, #1e293b 0%, #334155 100%);
                color: #93c5fd;
                border: 1px solid #334155;
                box-shadow: 0 2px 8px rgba(30,41,59,0.12);
            }
            .live-alert .live-alert-btn {
                background: #2563eb;
                color: #fff;
            }
            .live-alert .live-alert-btn:hover {
                background: #1e40af;
            }
            .live-alert .live-alert-dismiss {
                color: #93c5fd;
                border: 1px solid #334155;
                background: transparent;
            }
            .live-alert .live-alert-dismiss:hover {
                background: #1e293b;
            }
        }
        
        @media (max-width: 768px) {
            .custom-drawer {
                width: 280px;
                left: -280px;
                position: fixed;
                height: 100vh;
            }
            .custom-drawer.open {
                left: 0;
            }
            .drawer-header {
                padding: 15px;
                min-height: auto;
            }
            .drawer-logo {
                height: 40px;
            }
            .drawer-title {
                font-size: 14px;
            }
            .drawer-close {
                font-size: 18px;
            }
            .drawer-content {
                padding: 10px 0;
            }
            .drawer-menu {
                display: block;
            }
            .drawer-menu li {
                margin-bottom: 0;
            }
            .drawer-link {
                padding: 12px 18px;
                justify-content: flex-start;
                font-size: 14px;
            }
            .drawer-link i {
                font-size: 16px;
                min-width: 20px;
            }
            .drawer-profile {
                padding: 15px;
                flex-direction: row;
                align-items: center;
                text-align: left;
            }
            .drawer-profile .avatar {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
            .drawer-profile .name {
                font-size: 14px;
                margin-bottom: 2px;
                line-height: 1.3;
                overflow-wrap: normal;
                word-break: normal;
            }
            .drawer-profile .role {
                font-size: 12px;
                line-height: 1.3;
                overflow-wrap: normal;
                word-break: normal;
            }
            .drawer-profile .logout-btn {
                padding: 6px 12px;
                font-size: 12px;
                margin-left: 8px;
            }
            .nav-toggle-container {
                display: block;
            }
            .content-area {
                margin-left: 0;
                padding-top: 70px;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                margin-top: 0;
            }
            .user-profile {
                margin-top: 10px;
            }
            .table-responsive {
                padding: 10px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            table {
                font-size: 14px;
                min-width: 600px;
                width: auto !important;
                table-layout: auto;
            }
            #contributions-table {
                min-width: 600px !important;
                width: auto !important;
            }
            th, td {
                padding: 8px 10px;
                font-size: 13px;
            }
            .dataTables_wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        @media (max-width: 480px) {
            table {
                font-size: 12px;
                min-width: 500px;
            }
            #contributions-table {
                min-width: 500px !important;
            }
            th, td {
                padding: 6px 8px;
                font-size: 12px;
            }
            .table-responsive {
                padding: 5px;
            }
        }
        /* Drawer Navigation CSS (from member_prayers.php) */
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
        @media (max-width: 992px) {
            .content-area {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Drawer Navigation Markup (from member_prayers.php) -->
        <div class="nav-toggle-container">
           <button class="nav-toggle-btn" type="button" id="nav-toggle">
           <i class="fas fa-bars"></i> Menu
           </button>
        </div>
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
                    <li><a href="member_dashboard.php" class="drawer-link <?php echo $current_page == 'member_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                    <li><a href="member_events.php" class="drawer-link <?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i><span>Events</span></a></li>
                    <li><a href="member_messages.php" class="drawer-link <?php echo $current_page == 'member_messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i><span>Messages</span></a></li>
                    <li><a href="member_prayers.php" class="drawer-link <?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i><span>Prayer Requests</span></a></li>
                    <li><a href="member_financialreport.php" class="drawer-link <?php echo $current_page == 'member_financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i><span>Financial Reports</span></a></li>
                    <li><a href="member_collection.php" class="drawer-link <?php echo $current_page == 'member_collection.php' ? 'active' : ''; ?>"><i class="fas fa-list-alt"></i><span>My Report</span></a></li>
                    <li><a href="member_settings.php" class="drawer-link <?php echo $current_page == 'member_settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i><span>Settings</span></a></li>
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
                    <div class="role"><?php echo htmlspecialchars($user_profile['role'] ?? 'Member'); ?></div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        <div id="drawer-overlay" class="drawer-overlay"></div>
        <!-- Drawer Navigation JS (from member_prayers.php) -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const drawer = document.getElementById('drawer-navigation');
            const overlay = document.getElementById('drawer-overlay');
            const openBtn = document.getElementById('nav-toggle');
            const closeBtn = document.getElementById('drawer-close');

            function openDrawer() {
                drawer.classList.add('open');
                overlay.classList.add('open');
                if (window.innerWidth > 992) {
                    document.body.classList.add('drawer-open');
                }
            }
            function closeDrawer() {
                drawer.classList.remove('open');
                overlay.classList.remove('open');
                document.body.classList.remove('drawer-open');
            }

            openBtn.addEventListener('click', openDrawer);
            closeBtn.addEventListener('click', closeDrawer);
            overlay.addEventListener('click', closeDrawer);

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeDrawer();
            });

            // Remove drawer-open class on resize if needed
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 992) {
                    document.body.classList.remove('drawer-open');
                }
            });
        });
        </script>
        
        <main class="content-area">
<?php if ($live_message): ?>
<div class="live-alert" role="alert">
  <div class="live-alert-header">
    <svg width="18" height="18" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/></svg>
    Live Service Ongoing
  </div>
  <div class="live-alert-body">
    The church service is currently live! Join us now or watch the ongoing stream below.
  </div>
  <div class="live-alert-actions">
    <a href="member_messages.php?message=0" class="live-alert-btn">
      <svg width="13" height="13" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 14"><path d="M10 0C4.612 0 0 5.336 0 7c0 1.742 3.546 7 10 7 6.454 0 10-5.258 10-7 0-1.664-4.612-7-10-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>
      View Live
    </a>
    <button type="button" class="live-alert-btn live-alert-dismiss" aria-label="Close" onclick="this.closest('.live-alert').style.display='none';">Dismiss</button>
  </div>
</div>
<?php endif; ?>
            <div class="top-bar">
                <div>
                    <h2><strong>My Report</strong></h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>
            
            <div class="dashboard-content">
                <div class="card full-width">
                    <h3><strong>My Monthly Contributions</strong></h3>
                    <div class="prediction-chart">
                        <canvas id="memberContributionsLineChart"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3><strong>Total Amount</strong></h3>
                    <div class="amount-display">
                        <p id="amount-text">₱<?php echo number_format($total_donated, 2); ?></p>
                        <button id="toggle-amount" class="toggle-btn" title="Toggle amount visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="card">
                    <h3><strong>History</strong></h3>
                    <div class="table-responsive">
                        <table id="contributions-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Payment Method</th>
                                    <th>Reference Number</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($donations)): ?>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($donation['date']); ?></strong></td>
                                            <td>₱<?php echo number_format($donation['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($donation['purpose']); ?></td>
                                            <td><?php echo htmlspecialchars($donation['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($donation['reference_number']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    let inactivityTimeout;
    let logoutWarningShown = false;
    let isAmountVisible = true;
    let originalAmount = '₱<?php echo number_format($total_donated, 2); ?>';

    function resetInactivityTimer() {
        clearTimeout(inactivityTimeout);
        if (logoutWarningShown) {
            const warning = document.getElementById('logout-warning');
            if (warning) warning.remove();
            logoutWarningShown = false;
        }
        inactivityTimeout = setTimeout(() => {
            showLogoutWarning();
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 2000);
        }, 60000); // 1 minute
    }

    function showLogoutWarning() {
        if (!logoutWarningShown) {
            const warning = document.createElement('div');
            warning.id = 'logout-warning';
            warning.style.position = 'fixed';
            warning.style.top = '30px';
            warning.style.right = '30px';
            warning.style.background = '#f44336';
            warning.style.color = 'white';
            warning.style.padding = '20px 30px';
            warning.style.borderRadius = '8px';
            warning.style.fontSize = '18px';
            warning.style.zIndex = '9999';
            warning.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
            warning.innerHTML = '<i class="fas fa-lock"></i> Logging out due to inactivity...';
            document.body.appendChild(warning);
            logoutWarningShown = true;
        }
    }

    // Get the username from PHP for per-user storage
    const dashboardUsername = <?php echo json_encode($user_profile['username']); ?>;
    const localStorageKey = 'amountVisible_' + dashboardUsername;

    function setAmountVisibility(visible) {
        const amountText = document.getElementById('amount-text');
        const toggleBtn = document.getElementById('toggle-amount');
        const icon = toggleBtn.querySelector('i');
        if (visible) {
            amountText.textContent = originalAmount;
            amountText.classList.remove('amount-hidden');
            icon.className = 'fas fa-eye';
            isAmountVisible = true;
            localStorage.setItem(localStorageKey, 'true');
        } else {
            const hiddenAmount = originalAmount.replace(/[0-9,.]/g, '*');
            amountText.textContent = hiddenAmount;
            amountText.classList.add('amount-hidden');
            icon.className = 'fas fa-eye-slash';
            isAmountVisible = false;
            localStorage.setItem(localStorageKey, 'false');
        }
    }

    function toggleAmount() {
        setAmountVisibility(!isAmountVisible);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggle-amount');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleAmount);
        }
        // Set initial state based on per-user localStorage
        const stored = localStorage.getItem(localStorageKey);
        if (stored === 'false') {
            setAmountVisibility(false);
        } else {
            setAmountVisibility(true);
        }
    });

    ['mousemove', 'keydown', 'mousedown', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetInactivityTimer, true);
    });

    resetInactivityTimer();
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const monthLabels = <?php echo json_encode($month_labels); ?>;
        const monthlyContributions = <?php echo json_encode($monthly_contributions); ?>;
        const ctx = document.getElementById('memberContributionsLineChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'My Contributions',
                    data: monthlyContributions,
                    fill: false,
                    borderColor: 'rgba(0, 139, 30, 1)',
                    backgroundColor: 'rgba(0, 139, 30, 0.2)',
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Amount (₱)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    },
                    title: {
                        display: true,
                        text: 'My Monthly Contributions'
                    }
                }
            }
        });
    </script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#contributions-table').DataTable({
                columnDefs: [
                    { width: '20%', targets: 0 }, // Date
                    { width: '20%', targets: 1 }, // Amount
                    { width: '15%', targets: 2 }, // Type
                    { width: '20%', targets: 3 }, // Payment Method
                    { width: '25%', targets: 4 }  // Reference Number
                ],
                autoWidth: false,
                responsive: true,
                scrollX: true,
                scrollCollapse: true,
                language: {
                    emptyTable: "No donations recorded."
                },
                initComplete: function() {
                    // Show table after initialization is complete
                    $('#contributions-table').css('visibility', 'visible');
                }
            });
        });
    </script>
</body>
</html> 