<?php
// Dashboard page
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: index.php");
    exit;
}

// Check if user is an admin
$is_admin = ($_SESSION["user_role"] === "Administrator");

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize default session data for profile
if (!isset($_SESSION["user_email"])) {
    $_SESSION["user_email"] = "admin@example.com";
}

// Get total members count from database
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM membership_records");
    $row = $result->fetch_assoc();
    $total_members = $row['total'];

    // Get gender statistics
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
    $total_members = 0;
    $male_count = 0;
    $female_count = 0;
}

// Get total events count from database
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM events");
    $row = $result->fetch_assoc();
    $total_events = $row['total'];
} catch(Exception $e) {
    $total_events = 0;
}

// Dashboard statistics
$dashboard_stats = [
    "total_members" => $total_members,
    "upcoming_events" => $total_events,
    "pending_prayers" => 5
];

// --- LIVE DATA FOR ACTUAL VS PREDICTED INCOME CHART (2025) ---
// Use the same comprehensive prediction function as financialreport.php
if (!function_exists('getProphetPrediction')) {
    function getProphetPrediction($conn) {
        // Fetch ALL monthly data from ALL income sources (no date restriction)
        $sql = "
            SELECT 
                DATE_FORMAT(date, '%Y-%m') as month,
                SUM(amount) as total
            FROM (
                SELECT date, amount FROM tithes
                UNION ALL
                SELECT date, amount FROM offerings
                UNION ALL
                SELECT date, amount FROM bank_gifts
                UNION ALL
                SELECT date, amount FROM specified_gifts
            ) combined
            GROUP BY DATE_FORMAT(date, '%Y-%m')
            ORDER BY month";
        
        $result = $conn->query($sql);
        $prophet_data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $prophet_data[] = [
                    'ds' => $row['month'] . '-01',
                    'y' => floatval($row['total'])
                ];
            }
        }
        
        // Debug: Log the data being sent to Prophet
        error_log("Dashboard Prophet data points: " . count($prophet_data));
        if (count($prophet_data) > 0) {
            error_log("Dashboard Data range: " . $prophet_data[0]['ds'] . " to " . end($prophet_data)['ds']);
        }
        
        if (count($prophet_data) < 3) {
            error_log("Dashboard: Not enough data points for Prophet prediction");
            return null;
        }
        
        $ch = curl_init('http://localhost:5000/predict');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => $prophet_data]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200 && !empty($response)) {
            $predictions = json_decode($response, true);
            if (is_array($predictions) && !empty($predictions)) {
                error_log("Dashboard: Prophet predictions received successfully");
                
                // Filter predictions for 2025
                $predictions_2025 = [];
                foreach ($predictions as $pred) {
                    $date = new DateTime($pred['ds']);
                    if ($date->format('Y') === '2025') {
                        $predictions_2025[] = [
                            'month' => $date->format('Y-m'),
                            'date_formatted' => $date->format('F 01, Y'),
                            'yhat' => $pred['yhat'],
                            'yhat_lower' => $pred['yhat_lower'],
                            'yhat_upper' => $pred['yhat_upper']
                        ];
                    }
                }
                
                if (count($predictions_2025) === 12) {
                    return $predictions_2025;
                }
            }
        }
        
        error_log("Dashboard: Prophet prediction failed. HTTP code: " . $http_code);
        error_log("Dashboard: Curl error: " . $curl_error);
        
        // Fallback: Calculate monthly averages for all data
        $monthly_averages = [];
        foreach ($prophet_data as $data) {
            $month = date('m', strtotime($data['ds']));
            if (!isset($monthly_averages[$month])) {
                $monthly_averages[$month] = ['total' => 0, 'count' => 0];
            }
            $monthly_averages[$month]['total'] += $data['y'];
            $monthly_averages[$month]['count']++;
        }
        
        $predictions_2025 = [];
        for ($month = 1; $month <= 12; $month++) {
            $month_key = str_pad($month, 2, '0', STR_PAD_LEFT);
            $avg = isset($monthly_averages[$month_key]) 
                ? $monthly_averages[$month_key]['total'] / $monthly_averages[$month_key]['count']
                : array_sum(array_column($prophet_data, 'y')) / count($prophet_data);
            
            $date = DateTime::createFromFormat('Y-m', "2025-" . $month_key);
            
            $predictions_2025[] = [
                'month' => "2025-" . $month_key,
                'date_formatted' => $date->format('F 01, Y'),
                'yhat' => $avg,
                'yhat_lower' => $avg * 0.9,
                'yhat_upper' => $avg * 1.1
            ];
        }
        return $predictions_2025;
    }
}
$prophet_predictions = getProphetPrediction($conn);
$actuals_2025 = [];
$sql_actuals_2025 = "
    SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as total
    FROM (
        SELECT date, amount FROM tithes
        UNION ALL
        SELECT date, amount FROM offerings
        UNION ALL
        SELECT date, amount FROM bank_gifts
        UNION ALL
        SELECT date, amount FROM specified_gifts
    ) combined
    WHERE YEAR(date) = 2025
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month ASC
";
$result_actuals_2025 = $conn->query($sql_actuals_2025);
if ($result_actuals_2025) {
    while ($row = $result_actuals_2025->fetch_assoc()) {
        $actuals_2025[$row['month']] = floatval($row['total']);
    }
}
$months_2025 = array_map(function($p) { return $p['month']; }, $prophet_predictions ?? []);
$actual_data_2025 = [];
$predicted_data_2025 = [];
foreach ($months_2025 as $i => $month) {
    $actual_data_2025[] = isset($actuals_2025[$month]) ? $actuals_2025[$month] : 0;
    $predicted_data_2025[] = isset($prophet_predictions[$i]['yhat']) ? $prophet_predictions[$i]['yhat'] : 0;
}

// Calculate prediction summary for dashboard
$prediction_summary = [];
if ($prophet_predictions && count($prophet_predictions) > 0) {
    $predicted_values = array_column($prophet_predictions, 'yhat');
    $total_predicted = array_sum($predicted_values);
    $avg_monthly = $total_predicted / count($predicted_values);
    
    // Find best and worst months
    $best_month = $prophet_predictions[array_search(max($predicted_values), $predicted_values)];
    $worst_month = $prophet_predictions[array_search(min($predicted_values), $predicted_values)];
    
    $prediction_summary = [
        'total_predicted_income' => $total_predicted,
        'average_monthly_income' => $avg_monthly,
        'best_month' => [
            'date_formatted' => $best_month['date_formatted'] ?? $best_month['month'],
            'yhat' => $best_month['yhat']
        ],
        'worst_month' => [
            'date_formatted' => $worst_month['date_formatted'] ?? $worst_month['month'],
            'yhat' => $worst_month['yhat']
        ]
    ];
} else {
    $prediction_summary = [
        'total_predicted_income' => 0,
        'average_monthly_income' => 0,
        'best_month' => ['date_formatted' => '', 'yhat' => 0],
        'worst_month' => ['date_formatted' => '', 'yhat' => 0]
    ];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
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

        .dashboard-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-10px);
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

        .card i {
            font-size: 30px;
            margin-bottom: 10px;
            color: var(--accent-color);
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding-top: 10px;
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

        .summary-card {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .summary-card.full-width {
            grid-column: 1 / -1;
        }
        .prediction-chart {
            height: 300px;
            margin-bottom: 20px;
            position: relative;
        }
        .prediction-chart canvas {
            width: 100% !important;
            height: 100% !important;
        }
        .prediction-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .prediction-metric {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .prediction-metric:last-child {
            margin-bottom: 0;
        }
        .prediction-metric .label {
            color: #666;
        }
        .prediction-metric .value {
            font-weight: bold;
            color: var(--accent-color);
        }
        .prediction-metric .value.positive {
            color: #28a745;
        }
        .prediction-metric .value.negative {
            color: #dc3545;
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
                <h2>Dashboard</h2>
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
            
            <div class="dashboard-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div class="card">
                    <i class="fas fa-users"></i>
                    <h3>Total Members</h3>
                    <p><?php echo $dashboard_stats["total_members"]; ?></p>
                </div>
                <div class="card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Upcoming Events</h3>
                    <p><?php echo $dashboard_stats["upcoming_events"]; ?></p>
                </div>
                <div class="card">
                    <i class="fas fa-hands-praying"></i>
                    <h3>Pending Prayers</h3>
                    <p><?php echo $dashboard_stats["pending_prayers"]; ?></p>
                </div>
                <div class="card">
                    <i class="fas fa-venus-mars"></i>
                    <h3>Congregational Gender Split</h3>
                    <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
                <div class="card">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Actual Income vs Predicted Income (2025)</h3>
                    <div class="prediction-chart">
                        <canvas id="dashboardActualVsPredictedChart"></canvas>
                    </div>
                </div>
                <div class="card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Monthly Income Predictions (2025)</h3>
                    <div class="prediction-chart">
                        <canvas id="dashboardPredictionChart2025"></canvas>
                    </div>
                    <div class="prediction-details">
                        <div class="prediction-metric">
                            <span class="label">Total Predicted Income</span>
                            <span class="value">₱<?php echo number_format($prediction_summary['total_predicted_income'], 2); ?></span>
                        </div>
                        <div class="prediction-metric">
                            <span class="label">Average Monthly Income</span>
                            <span class="value">₱<?php echo number_format($prediction_summary['average_monthly_income'], 2); ?></span>
                        </div>
                        <div class="prediction-metric">
                            <span class="label">Best Month</span>
                            <span class="value"><?php echo $prediction_summary['best_month']['date_formatted']; ?> (₱<?php echo number_format($prediction_summary['best_month']['yhat'], 2); ?>)</span>
                        </div>
                        <div class="prediction-metric">
                            <span class="label">Worst Month</span>
                            <span class="value"><?php echo $prediction_summary['worst_month']['date_formatted']; ?> (₱<?php echo number_format($prediction_summary['worst_month']['yhat'], 2); ?>)</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>

        const ctx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [<?php echo $male_count; ?>, <?php echo $female_count; ?>],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Actual vs Predicted Income Chart (placeholder data)
        // TODO: Replace with real data from financialreport.php
        const months2025 = <?php echo json_encode($months_2025); ?>;
        const actualData2025 = <?php echo json_encode($actual_data_2025); ?>;
        const predictedData2025 = <?php echo json_encode($predicted_data_2025); ?>;
        const ctxDashboardActualVsPredicted = document.getElementById('dashboardActualVsPredictedChart').getContext('2d');
        new Chart(ctxDashboardActualVsPredicted, {
            type: 'bar',
            data: {
                labels: months2025,
                datasets: [
                    {
                        label: 'Actual Income',
                        data: actualData2025,
                        backgroundColor: 'rgba(255, 140, 0, 0.85)',
                        borderColor: 'rgba(255, 140, 0, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Predicted Amount',
                        data: predictedData2025,
                        backgroundColor: 'rgba(0, 100, 0, 0.85)',
                        borderColor: 'rgba(0, 100, 0, 1)',
                        borderWidth: 2
                    }
                ]
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
                        text: 'Actual Income vs Predicted Amount (2025)'
                    }
                }
            }
        });

        // Monthly Income Predictions Chart (2025)
        const ctxDashboardPrediction2025 = document.getElementById('dashboardPredictionChart2025').getContext('2d');
        new Chart(ctxDashboardPrediction2025, {
            type: 'bar',
            data: {
                labels: months2025,
                datasets: [
                    {
                        label: 'Predicted Amount',
                        data: predictedData2025,
                        backgroundColor: 'rgba(0, 100, 0, 0.8)',
                        borderColor: 'rgba(0, 100, 0, 1)',
                        borderWidth: 2
                    }
                ]
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
                        text: 'Monthly Income Predictions (2025)'
                    }
                }
            }
        });
    </script>
</body>
</html>