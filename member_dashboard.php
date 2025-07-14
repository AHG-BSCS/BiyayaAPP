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
    header("Location: dashboard.php");
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

// Debug: Check what user_id we're using
error_log("Member Dashboard - Username: {$username}, User ID: {$user_id}");

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

// Debug: Check if we got any results
$num_rows = $result->num_rows;
error_log("Member Dashboard - Found {$num_rows} contributions for user {$user_id}");

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
// Get user_id
$stmt = $conn->prepare("SELECT user_id FROM user_profiles WHERE username = ?");
$stmt->bind_param("s", $_SESSION["user"]);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data ? $user_data['user_id'] : null;
// Get monthly contributions for the last 12 months
$monthly_contributions = array_fill(1, 12, 0);
$month_labels = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $month_labels[] = date('M Y', strtotime($month.'-01'));
    $monthly_contributions[(int)date('n', strtotime($month.'-01'))] = 0;
}
if ($user_id) {
    $stmt = $conn->prepare("SELECT DATE_FORMAT(contribution_date, '%Y-%m') as month, SUM(amount) as total FROM contributions WHERE user_id = ? AND contribution_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $month_num = (int)date('n', strtotime($row['month'].'-01'));
        $monthly_contributions[$month_num] = (float)$row['total'];
    }
}

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
        }
        
        .sidebar-header img {
            height: 60px;
            margin-bottom: 10px;
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
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 24px rgba(0, 139, 30, 0.15), 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
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
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
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
        .card.full-width {
            grid-column: 1 / -1;
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
                    <li><a href="member_dashboard.php" class="<?php echo $current_page == 'member_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="member_events.php" class="<?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="member_messages.php" class="<?php echo $current_page == 'member_messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i> <span>Messages</span></a></li>
                    <li><a href="member_prayers.php" class="<?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                    <li><a href="member_financialreport.php" class="<?php echo $current_page == 'member_financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span>Financial Reports</span></a></li>
                    <li><a href="member_collection.php" class="<?php echo $current_page == 'member_collection.php' ? 'active' : ''; ?>"><i class="fas fa-list-alt"></i> <span>My Report</span></a></li>
                    <li><a href="member_settings.php" class="<?php echo $current_page == 'member_settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                </ul>
            </div>
        </aside>
        
        <main class="content-area">
            <div class="top-bar">
                <div>
                    <h2>Member Dashboard</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
                <div class="user-profile">
                    <div class="avatar">
                        <?php if (!empty($user_profile['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_profile['full_name'] ?? $user_profile['username'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?></h4>
                        <p><?php echo htmlspecialchars($user_profile['role']); ?></p>
                    </div>
                    <form action="logout.php" method="post">
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>
            
            <div class="dashboard-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div class="card" style="background: linear-gradient(135deg, #e0ffe7 0%, #f5f5f5 100%);">
                    <i class="fas fa-users"></i>
                    <h3>Total Members</h3>
                    <p><?php echo $total_members; ?></p>
                </div>
                <div class="card" style="background: linear-gradient(135deg, #e0ffe7 0%, #f5f5f5 100%);">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Upcoming Events</h3>
                    <p><?php echo $total_events; ?></p>
                </div>
                <div class="card" style="background: linear-gradient(135deg, #e0ffe7 0%, #f5f5f5 100%);">
                    <i class="fas fa-hands-praying"></i>
                    <h3>Need Prayer</h3>
                    <p><?php echo $total_prayers; ?></p>
                </div>
                <div class="card full-width" style="background: linear-gradient(135deg, #e0ffe7 0%, #f5f5f5 100%);">
                    <i class="fas fa-book-open"></i>
                    <h3>Bible Verse of the Day</h3>
                    <p style="font-size:16px; color:#333; font-style:italic; margin-bottom:8px;">"<?php echo $verse_of_the_day['text']; ?>"</p>
                    <p style="font-size:14px; color:#008b1e; text-align:right; margin:0;"><b><?php echo $verse_of_the_day['ref']; ?></b></p>
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
        $(document).ready(function() {
            // Removed contributions table initialization
        });

        const monthLabels = <?php echo json_encode($month_labels); ?>;
        const monthlyContributions = <?php echo json_encode(array_values($monthly_contributions)); ?>;
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
                        text: 'My Monthly Contributions (Last 12 Months)'
                    }
                }
            }
        });
    </script>
</body>
</html>