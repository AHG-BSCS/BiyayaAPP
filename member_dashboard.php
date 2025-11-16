<?php
// Member Dashboard page
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

// Get user's contributions from database
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

// Get total members
$result = $conn->query("SELECT COUNT(*) as total FROM membership_records");
$row = $result->fetch_assoc();
$total_members = $row['total'];
// Get total events
$result = $conn->query("SELECT COUNT(*) as total FROM events");
$row = $result->fetch_assoc();
$total_events = $row['total'];
// Get total pending prayers
$result = $conn->query("SELECT COUNT(*) as total FROM prayer_requests");
$row = $result->fetch_assoc();
$total_prayers = $row['total'];
// Add random Bible verse for the day
$bible_verses = [
    [
        'ref' => 'Philippians 4:13',
        'text' => 'I can do all things through Christ who strengthens me.'
    ],
    [
        'ref' => 'Jeremiah 29:11',
        'text' => 'For I know the plans I have for you, declares the Lord, plans to prosper you and not to harm you, plans to give you hope and a future.'
    ],
    [
        'ref' => 'Psalm 23:1',
        'text' => 'The Lord is my shepherd; I shall not want.'
    ],
    [
        'ref' => 'Romans 8:28',
        'text' => 'And we know that in all things God works for the good of those who love him, who have been called according to his purpose.'
    ],
    [
        'ref' => 'Proverbs 3:5-6',
        'text' => 'Trust in the Lord with all your heart and lean not on your own understanding; in all your ways submit to him, and he will make your paths straight.'
    ],
    [
        'ref' => 'Isaiah 41:10',
        'text' => 'So do not fear, for I am with you; do not be dismayed, for I am your God. I will strengthen you and help you; I will uphold you with my righteous right hand.'
    ],
];
$verse_index = intval(date('z')) % count($bible_verses);
$verse_of_the_day = $bible_verses[$verse_index];

// Fetch tithes and offerings data for line graph (last 12 months)
$tithes_offerings_data = [];
$sql = "
    SELECT 
        DATE_FORMAT(entry_date, '%Y-%m') as month,
        DATE_FORMAT(entry_date, '%Y-%m-01') as month_start,
        SUM(tithes) as total_tithes,
        SUM(offerings) as total_offerings
    FROM breakdown_income
    WHERE (tithes > 0 OR offerings > 0)
        AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
    ORDER BY month ASC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tithes_offerings_data[] = [
            'month' => $row['month'],
            'month_start' => $row['month_start'],
            'tithes' => floatval($row['total_tithes']),
            'offerings' => floatval($row['total_offerings'])
        ];
    }
}

// Prepare data for chart
$chart_dates = [];
$chart_tithes = [];
$chart_offerings = [];
foreach ($tithes_offerings_data as $data) {
    $chart_dates[] = date('F Y', strtotime($data['month_start']));
    $chart_tithes[] = $data['tithes'];
    $chart_offerings[] = $data['offerings'];
}

$live_message = getLiveMessage($conn);

// Get gender statistics for summary card (add this before HTML output)
try {
    $gender_stats = $conn->query("SELECT sex, COUNT(*) as count FROM membership_records GROUP BY sex");
    $male_count = 0;
    $female_count = 0;
    while ($row = $gender_stats->fetch_assoc()) {
        if ($row['sex'] === 'Male') {
            $male_count = $row['count'];
        } else if ($row['sex'] === 'Female') {
            $female_count = $row['count'];
        }
    }
} catch(Exception $e) {
    $male_count = 0;
    $female_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | <?php echo $church_name; ?></title>
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
        
        /* Custom Drawer Navigation Styles (EXACT from superadmin_dashboard.php) */
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
        /* Ensure content doesn't overlap with the button */
        .content-area {
            padding-top: 80px;
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
                margin-top: 0;
            }
        }
        .dashboard-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .summary-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            position: relative;
            border: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            margin-bottom: 0;
            min-width: 0;
            width: 100%;
            max-width: none;
        }
        .summary-card:hover {
            transform: translateY(-10px);
        }
        .summary-card.full-width {
            grid-column: 1 / -1;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .summary-card .card-icon {
            background: #fff;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        .summary-card .card-icon i,
        .gender-icon i {
            color: #008b1e !important;
            font-size: 24px;
        }
        .summary-card .card-content {
            flex: 1;
            text-align: left;
        }
        .summary-card h3 {
            font-size: 16px;
            color: #666;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .card-number {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
            line-height: 1;
            animation: countUp 2s ease-out;
        }
        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .summary-card .card-subtitle {
            font-size: 13px;
            color: #888;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .card-decoration {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
            border-radius: 0 0 16px 16px;
        }
        .summary-card:hover .card-decoration {
            transform: translateX(100%);
        }
        .members-card,
        .events-card,
        .prayers-card,
        .gender-card {
            background: var(--white);
            border-left: 4px solid #ffffff;
        }
        .members-card .card-icon,
        .events-card .card-icon,
        .prayers-card .card-icon,
        .gender-card .card-icon {
            background: #ffffff;
        }
        /* Gender Card Styling */
        .gender-stats {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin: 15px 0;
        }
        .gender-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            flex: 1;
        }
        .gender-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .gender-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            transition: all 0.3s ease;
        }
        .gender-item.male .gender-icon,
        .gender-item.female .gender-icon {
            background: #ffffff;
        }
        .gender-item:hover .gender-icon {
            transform: scale(1.05);
        }
        .gender-info {
            flex: 1;
        }
        .gender-number {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            line-height: 1;
            margin-bottom: 2px;
            animation: countUp 2s ease-out;
        }
        .gender-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        /* Remove or override any previous per-card color backgrounds, borders, or icon color overrides below this line */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fff;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            margin-top: 60px;
            width: 100%;
        }
        .top-bar h2 {
            font-size: 24px;
        }
        .content-area {
            flex: 1;
            padding: 20px;
            max-width: 100%;
            width: 100%;
            box-sizing: border-box;
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
                    <div class="role"><?php echo htmlspecialchars($user_profile['role']); ?></div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        <!-- Drawer Overlay -->
        <div id="drawer-overlay" class="drawer-overlay"></div>
        
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
                    <h2>Member Dashboard</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>
            
            <div class="dashboard-content">
                <div class="summary-card members-card">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Members</h3>
                        <div class="card-number"><?php echo $total_members; ?></div>
                        <div class="card-subtitle">Active Congregation</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card events-card">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3>Upcoming Events</h3>
                        <div class="card-number"><?php echo $total_events; ?></div>
                        <div class="card-subtitle">Scheduled Activities</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card prayers-card">
                    <div class="card-icon">
                        <i class="fas fa-hands-praying"></i>
                    </div>
                    <div class="card-content">
                        <h3>Prayer Requests</h3>
                        <div class="card-number"><?php echo $total_prayers; ?></div>
                        <div class="card-subtitle">Needs Prayer</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card gender-card">
                    <div class="card-icon">
                        <i class="fas fa-venus-mars"></i>
                    </div>
                    <div class="card-content">
                        <h3>Gender Distribution</h3>
                        <div class="gender-stats">
                            <div class="gender-item male">
                                <div class="gender-icon">
                                    <i class="fas fa-mars"></i>
                                </div>
                                <div class="gender-info">
                                    <div class="gender-number"><?php echo $male_count; ?></div>
                                    <div class="gender-label">Male</div>
                                </div>
                            </div>
                            <div class="gender-item female">
                                <div class="gender-icon">
                                    <i class="fas fa-venus"></i>
                                </div>
                                <div class="gender-info">
                                    <div class="gender-number"><?php echo $female_count; ?></div>
                                    <div class="gender-label">Female</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-subtitle">Congregational Split</div>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card full-width">
                    <div class="card-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="card-content">
                        <h3>Bible Verse of the Day</h3>
                        <p style="font-size:16px; color:#333; font-style:italic; margin-bottom:8px;">"<?php echo $verse_of_the_day['text']; ?>"</p>
                        <p style="font-size:14px; color:#008b1e; text-align:right; margin:0;"><b><?php echo $verse_of_the_day['ref']; ?></b></p>
                    </div>
                    <div class="card-decoration"></div>
                </div>
                <div class="summary-card full-width">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-content" style="width: 100%;">
                        <h3>Tithes and Offerings Trend (Last 12 Months)</h3>
                        <div style="position: relative; height: 300px; margin-top: 20px;">
                            <canvas id="tithesOfferingsChart"></canvas>
                        </div>
                    </div>
                    <div class="card-decoration"></div>
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
            console.log('Inactivity detected: showing warning and logging out soon.');
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Tithes and Offerings Line Chart
        const tithesOfferingsCtx = document.getElementById('tithesOfferingsChart');
        if (tithesOfferingsCtx) {
            const chartDates = <?php echo json_encode($chart_dates); ?>;
            const chartTithes = <?php echo json_encode($chart_tithes); ?>;
            const chartOfferings = <?php echo json_encode($chart_offerings); ?>;
            
            new Chart(tithesOfferingsCtx, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [
                        {
                            label: 'Tithes',
                            data: chartTithes,
                            borderColor: 'rgba(0, 139, 30, 1)',
                            backgroundColor: 'rgba(0, 139, 30, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: 'rgba(0, 139, 30, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Offerings',
                            data: chartOfferings,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                                },
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

    </script>
    <script>
    // Drawer navigation toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const drawer = document.getElementById('drawer-navigation');
        const overlay = document.getElementById('drawer-overlay');
        const openBtn = document.getElementById('nav-toggle');
        const closeBtn = document.getElementById('drawer-close');

        function openDrawer() {
            drawer.classList.add('open');
            overlay.classList.add('open');
        }
        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
        }

        openBtn.addEventListener('click', openDrawer);
        closeBtn.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDrawer();
        });
    });
    </script>
</body>
</html>