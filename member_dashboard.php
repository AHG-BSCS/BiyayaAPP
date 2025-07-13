<?php
// Member Dashboard page
session_start();
require_once 'config.php';
require_once 'user_functions.php';

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
            
            <div class="dashboard-content">
                <div class="card">
                    <h3>Total Amount</h3>
                    <div class="amount-display">
                        <p id="amount-text">₱<?php echo number_format($total_donated, 2); ?></p>
                        <button id="toggle-amount" class="toggle-btn" title="Toggle amount visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <h3>History</h3>
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