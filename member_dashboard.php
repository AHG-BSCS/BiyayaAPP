<?php
// Member Dashboard page
session_start();

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
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
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Retrieve donations for the logged-in member
$username = $_SESSION["user"];
$donations = [];

// Ensure financial_data is initialized
if (!isset($_SESSION['financial_data'])) {
    $_SESSION['financial_data'] = [
        'tithes' => [],
        'offerings' => [],
        'bank_gifts' => [],
        'specified_gifts' => []
    ];
}

// Collect tithes
foreach ($_SESSION['financial_data']['tithes'] as $tithe) {
    if (strtolower($tithe['member_name']) === strtolower($username)) {
        $donations[] = [
            'id' => $tithe['id'],
            'date' => $tithe['date'],
            'amount' => $tithe['total'],
            'purpose' => 'Tithes'
        ];
    }
}

// Collect offerings
foreach ($_SESSION['financial_data']['offerings'] as $offering) {
    if (strtolower($offering['member_name']) === strtolower($username)) {
        $donations[] = [
            'id' => $offering['id'],
            'date' => $offering['date'],
            'amount' => $offering['total'],
            'purpose' => 'Offering'
        ];
    }
}

// Sort donations by date (newest first)
usort($donations, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate total donations
$total_donated = array_sum(array_column($donations, 'amount'));

// Retrieve user profile
require_once 'config.php';
require_once 'user_functions.php';
$user_profile = getUserProfile($conn, $username);

// Get church logo
$church_logo = getChurchLogo($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | <?php echo $church_name; ?></title>
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
                    <li><a href="member_dashboard.php" class="<?php echo $current_page == 'member_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="member_events.php" class="<?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="member_prayers.php" class="<?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                </ul>
            </div>
        </aside>
        
        <main class="content-area">
            <div class="top-bar">
                <h2>Member Dashboard</h2>
                <div class="user-profile">
                    <div class="avatar">
                        <?php if (!empty($user_profile['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_profile['username'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_profile['username']); ?></h4>
                        <p><?php echo htmlspecialchars($user_profile['role']); ?></p>
                    </div>
                    <form action="logout.php" method="post">
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>
            
            <div class="dashboard-content">
                <div class="card">
                    <h3>Total Donations</h3>
                    <p>₱<?php echo number_format($total_donated, 2); ?></p>
                </div>
                
                <div class="card">
                    <h3>Donation History</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $donation): ?>
                                    <tr>
                                        <td><?php echo $donation['date']; ?></td>
                                        <td>₱<?php echo number_format($donation['amount'], 2); ?></td>
                                        <td><?php echo $donation['purpose']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($donations)): ?>
                                    <tr>
                                        <td colspan="3">No donations recorded.</td>
                                    </tr>
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

    ['mousemove', 'keydown', 'mousedown', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetInactivityTimer, true);
    });

    resetInactivityTimer();
    </script>
</body>
</html>