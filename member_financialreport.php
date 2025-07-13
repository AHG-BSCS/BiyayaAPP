<?php
// member_financialreport.php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in and is a member
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "Member") {
            header("Location: index.php");
    exit;
}

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Denominations for bills and coins
$denominations = [
    'bills' => [],
    'coins' => []
];

// Specified gift categories
$specified_gifts = [
    'Provident Fund',
    'Building Fund',
    'Building and Equipment',
    'Others (e.g., Wedding, etc.)',
    'Depreciation'
];

// Initialize financial data if not set
if (!isset($_SESSION['financial_data'])) {
    $_SESSION['financial_data'] = [
        'tithes' => [],
        'offerings' => [],
        'bank_gifts' => [],
        'specified_gifts' => []
    ];
}

// Fetch bank gifts records from database
$bank_gifts_records = [];
$sql = "SELECT * FROM bank_gifts ORDER BY id ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bank_gifts_records[] = $row;
    }
}

// Fetch specified gifts records from database
$specified_gifts_records = [];
$sql = "SELECT * FROM specified_gifts ORDER BY id ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $specified_gifts_records[] = $row;
    }
}

// Fetch tithes records from database
$tithes_records = [];
$sql = "SELECT * FROM tithes ORDER BY id ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tithes_records[] = $row;
    }
}

// Fetch offerings records from database
$offerings_records = [];
$sql = "SELECT * FROM offerings ORDER BY id ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $offerings_records[] = $row;
    }
}

// Fetch weekly summary for tithes and offerings
$weekly_reports = [];
$sql = "
    SELECT 
        week,
        MIN(date) as start_date,
        MAX(date) as end_date,
        SUM(tithes_amount) as total_tithes,
        SUM(offerings_amount) as total_offerings
    FROM (
        SELECT DATE_FORMAT(date, '%Y-W%u') as week, date, amount as tithes_amount, 0 as offerings_amount FROM tithes
        UNION ALL
        SELECT DATE_FORMAT(date, '%Y-W%u') as week, date, 0 as tithes_amount, amount as offerings_amount FROM offerings
    ) as combined
    GROUP BY week
    ORDER BY week DESC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['grand_total'] = $row['total_tithes'] + $row['total_offerings'];
        $weekly_reports[] = $row;
    }
}

// --- BEGIN: Copy calculations for averages, predictions, and chart data from financialreport.php ---
// Calculate average weekly amounts for tithes and offerings only (using ALL data)
$sql = "
    WITH weekly_totals AS (
        SELECT 
            DATE_FORMAT(date, '%Y-%U') as week,
            'tithes' as type,
            SUM(amount) as total
        FROM tithes 
        GROUP BY DATE_FORMAT(date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(date, '%Y-%U') as week,
            'offerings' as type,
            SUM(amount) as total
        FROM offerings 
        GROUP BY DATE_FORMAT(date, '%Y-%U')
    )
    SELECT 
        type,
        AVG(total) as avg_weekly
    FROM weekly_totals
    GROUP BY type";

$result = $conn->query($sql);
$weekly_averages = [
    'tithes' => 0,
    'offerings' => 0
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $weekly_averages[$row['type']] = $row['avg_weekly'];
    }
}

$avg_weekly_tithes = $weekly_averages['tithes'] ?? 0;
$avg_weekly_offerings = $weekly_averages['offerings'] ?? 0;

// Calculate average weekly amounts for other sources (using ALL data)
$sql = "
    WITH weekly_totals AS (
        SELECT 
            DATE_FORMAT(date, '%Y-%U') as week,
            'tithes' as type,
            SUM(amount) as total
        FROM tithes 
        GROUP BY DATE_FORMAT(date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(date, '%Y-%U') as week,
            'offerings' as type,
            SUM(amount) as total
        FROM offerings 
        GROUP BY DATE_FORMAT(date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(date, '%Y-%U') as week,
            'bank_gifts' as type,
            SUM(amount) as total
        FROM bank_gifts 
        GROUP BY DATE_FORMAT(date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(date, '%Y-%U') as week,
            'specified_gifts' as type,
            SUM(amount) as total
        FROM specified_gifts 
        GROUP BY DATE_FORMAT(date, '%Y-%U')
    )
    SELECT 
        type,
        AVG(total) as avg_weekly
    FROM weekly_totals
    GROUP BY type";

$result = $conn->query($sql);
$weekly_averages = [
    'tithes' => 0,
    'offerings' => 0,
    'bank_gifts' => 0,
    'specified_gifts' => 0
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $weekly_averages[$row['type']] = $row['avg_weekly'];
    }
}

$avg_weekly_tithes = $weekly_averages['tithes'] ?? 0;
$avg_weekly_offerings = $weekly_averages['offerings'] ?? 0;
$avg_weekly_bank_gifts = $weekly_averages['bank_gifts'] ?? 0;
$avg_weekly_specified_gifts = $weekly_averages['specified_gifts'] ?? 0;

// Get historical data for trend analysis
$sql = "
    WITH all_dates AS (
        SELECT date FROM tithes
        UNION ALL
        SELECT date FROM offerings
    ),
    date_range AS (
        SELECT 
            MIN(date) as start_date,
            MAX(date) as end_date
        FROM all_dates
    )
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        SUM(amount) as total
    FROM (
        SELECT date, amount FROM tithes
        UNION ALL
        SELECT date, amount FROM offerings
    ) combined
    WHERE date >= (SELECT start_date FROM date_range)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month ASC";

$result = $conn->query($sql);
$historical_data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $historical_data[$row['month']] = $row['total'];
    }
}

if (empty($historical_data)) {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $historical_data[$month] = 0;
    }
}

// Calculate predicted next month income using Prophet
function getProphetPrediction($conn) {
    $sql = "
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            SUM(amount) as total
        FROM (
            SELECT date, amount FROM tithes
            UNION ALL
            SELECT date, amount FROM offerings
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
    if (count($prophet_data) < 3) {
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
    curl_close($ch);
    if ($http_code === 200 && !empty($response)) {
        $predictions = json_decode($response, true);
        if (is_array($predictions) && !empty($predictions)) {
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

$prophet_predictions = getProphetPrediction($conn);
if ($prophet_predictions) {
    $predicted_monthly = $prophet_predictions[0]['yhat'];
    $prediction_lower = $prophet_predictions[0]['yhat_lower'] ?? ($predicted_monthly * 0.9);
    $prediction_upper = $prophet_predictions[0]['yhat_upper'] ?? ($predicted_monthly * 1.1);
} else {
    $predicted_monthly = ($avg_weekly_tithes + $avg_weekly_offerings + $avg_weekly_bank_gifts + $avg_weekly_specified_gifts) * 4;
    $prediction_lower = $predicted_monthly * 0.9;
    $prediction_upper = $predicted_monthly * 1.1;
}

// Calculate prediction summary from prophet predictions
if ($prophet_predictions && count($prophet_predictions) > 0) {
    $predicted_values = array_column($prophet_predictions, 'yhat');
    $total_predicted = array_sum($predicted_values);
    $avg_monthly = $total_predicted / count($predicted_values);
    $best_month = $prophet_predictions[array_search(max($predicted_values), $predicted_values)];
    $worst_month = $prophet_predictions[array_search(min($predicted_values), $predicted_values)];
    $historical_avg = array_sum(array_values($historical_data)) / count($historical_data);
    $growth_rate = $historical_avg > 0 ? (($avg_monthly - $historical_avg) / $historical_avg * 100) : 0;
    $prediction_summary = [
        'total_predicted_income' => $total_predicted,
        'average_monthly_income' => $avg_monthly,
        'predicted_growth_rate' => $growth_rate,
        'best_month' => [
            'date_formatted' => $best_month['date_formatted'] ?? $best_month['month'],
            'month' => $best_month['month'],
            'yhat' => $best_month['yhat']
        ],
        'worst_month' => [
            'date_formatted' => $worst_month['date_formatted'] ?? $worst_month['month'],
            'month' => $worst_month['month'],
            'yhat' => $worst_month['yhat']
        ],
        'total_months' => count($prophet_predictions)
    ];
} else {
    $prediction_summary = [
        'total_predicted_income' => 0,
        'average_monthly_income' => 0,
        'predicted_growth_rate' => 0,
        'best_month' => ['date_formatted' => '', 'month' => '', 'yhat' => 0],
        'worst_month' => ['date_formatted' => '', 'month' => '', 'yhat' => 0],
        'total_months' => 0
    ];
}

// Fetch actual totals for each month in 2025
$actuals_2025 = [];
$sql_actuals_2025 = "
    SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as total
    FROM (
        SELECT date, amount FROM tithes
        UNION ALL
        SELECT date, amount FROM offerings
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
// --- END: Copy calculations for averages, predictions, and chart data ---

// Handle profile picture update
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile_picture"])) {
    // Handle profile picture reset
    if (isset($_POST['reset_profile_picture'])) {
        // Delete old profile picture if it exists
        if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])) {
            unlink($user_profile['profile_picture']);
        }
        
        // Update database to clear profile picture
        $sql = "UPDATE user_profiles SET profile_picture = '', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $user_profile['user_id']);
        
        if ($stmt->execute()) {
            $message = "Profile picture removed successfully!";
            $messageType = "success";
            // Refresh user profile
            $user_profile = getUserProfile($conn, $_SESSION["user"]);
        } else {
            $message = "Failed to remove profile picture.";
            $messageType = "error";
        }
    }
    // Handle profile picture upload
    else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        if (!in_array($file['type'], $allowed_types)) {
            $message = "Invalid file type. Please upload JPG, PNG, or GIF files only.";
            $messageType = "error";
        }
        // Validate file size
        else if ($file['size'] > $max_size) {
            $message = "File size too large. Please upload files smaller than 5MB.";
            $messageType = "error";
        }
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old profile picture if it exists
                if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])) {
                    unlink($user_profile['profile_picture']);
                }
                
                // Update database
                $sql = "UPDATE user_profiles SET profile_picture = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $filepath, $user_profile['user_id']);
                
                if ($stmt->execute()) {
                    $message = "Profile picture updated successfully!";
                    $messageType = "success";
                    // Refresh user profile
                    $user_profile = getUserProfile($conn, $_SESSION["user"]);
                } else {
                    $message = "Failed to update profile picture in database.";
                    $messageType = "error";
                    // Delete uploaded file if database update failed
                    unlink($filepath);
                }
            } else {
                $message = "Failed to upload file. Please try again.";
                $messageType = "error";
            }
        }
    } else {
        $message = "Please select a file to upload.";
        $messageType = "error";
    }
}

// (Insert the summary/insights tab HTML and the script blocks for DataTables and Chart.js from financialreport.php, but remove all add/edit/delete/modal logic)

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
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

        .financial-content {
            margin-top: 20px;
        }

        .table-responsive {
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eeeeee;
        }

        th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        tbody tr:hover {
            background-color: #f9f9f9;
        }

        .tab-navigation {
            display: flex;
            background-color: var(--white);
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .tab-navigation a {
            flex: 1;
            text-align: center;
            padding: 15px;
            color: var(--primary-color);
            text-decoration: none;
            transition: background-color 0.3s;
            font-weight: 500;
        }

        .tab-navigation a.active {
            background-color: var(--accent-color);
            color: var(--white);
        }

        .tab-navigation a:hover:not(.active) {
            background-color: #f0f0f0;
        }

        .tab-content {
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .summary-content {
            padding: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .summary-card h3 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 18px;
        }

        .prediction-chart, .trend-chart {
            height: 300px;
            margin-bottom: 20px;
            position: relative;
        }

        .prediction-chart canvas, .trend-chart canvas {
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

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
        }

        .metric-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .metric-label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .metric-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: var(--accent-color);
        }

        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .insight-card {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .insight-card h4 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 16px;
        }

        .chart-container {
            height: 300px;
            margin-bottom: 20px;
            position: relative;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
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
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .profile-form .form-group {
            margin-bottom: 20px;
        }

        .profile-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .profile-form input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .profile-form input[type="checkbox"] {
            margin-right: 8px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
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

        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
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
            .tab-navigation {
                flex-direction: column;
            }
            .tab-navigation a {
                border-bottom: 1px solid #eee;
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
                <li><a href="member_messages.php" class="<?php echo $current_page == 'member_messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i> <span>Messages</span></a></li>
                <li><a href="member_prayers.php" class="<?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                <li><a href="member_financialreport.php" class="<?php echo $current_page == 'member_financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span>Financial Reports</span></a></li>
                <li><a href="member_settings.php" class="<?php echo $current_page == 'member_settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
        </div>
    </aside>

    <main class="content-area">
        <div class="top-bar">
            <h2>Financial Reports</h2>
            <div class="user-profile">
                <div class="avatar" onclick="openProfileModal()" style="cursor: pointer;" title="Click to change profile picture">
                    <?php if (!empty($user_profile['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_profile['full_name'] ?? $user_profile['username'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username'] ?? 'Unknown User'); ?></h4>
                    <p><?php echo htmlspecialchars($user_profile['role'] ?? 'User'); ?></p>
                </div>
                <form action="logout.php" method="post">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo ($messageType === 'error') ? 'error' : 'success'; ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 5px; display: flex; align-items: center;">
                <i class="fas fa-<?php echo ($messageType === 'error') ? 'exclamation-circle' : 'check-circle'; ?>" style="margin-right: 10px; font-size: 20px;"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="financial-content">
            <div class="tab-navigation">
                <a href="#weekly-reports" class="active" data-tab="weekly-reports">Weekly Reports</a>
                <a href="#tithes" data-tab="tithes">Tithes</a>
                <a href="#offerings" data-tab="offerings">Offerings</a>
                <a href="#bank-gifts" data-tab="bank-gifts">Bank Gifts</a>
                <a href="#specified-gifts" data-tab="specified-gifts">Specified Gifts</a>
                <a href="#summary" data-tab="summary">Insights</a>
            </div>

            <div class="tab-content">
                <!-- Weekly Reports Tab -->
                <div class="tab-pane active" id="weekly-reports">
                    <div class="table-responsive">
                        <table id="weekly-reports-table">
                            <thead>
                                <tr>
                                    <th>Week</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Total Tithes</th>
                                    <th>Total Offerings</th>
                                    <th>Grand Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weekly_reports as $week): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($week['week']); ?></strong></td>
                                        <td><strong><?php echo date('M d, Y', strtotime($week['start_date'])); ?></strong></td>
                                        <td><strong><?php echo date('M d, Y', strtotime($week['end_date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($week['total_tithes'], 2); ?></td>
                                        <td>₱<?php echo number_format($week['total_offerings'], 2); ?></td>
                                        <td><strong>₱<?php echo number_format($week['grand_total'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tithes Tab -->
                <div class="tab-pane" id="tithes">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="tithes-tbody">
                                <?php foreach ($tithes_records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['id']; ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Offerings Tab -->
                <div class="tab-pane" id="offerings">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="offerings-tbody">
                                <?php foreach ($offerings_records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['id']; ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bank Gifts Tab -->
                <div class="tab-pane" id="bank-gifts">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Date Deposited</th>
                                    <th>Date Updated</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="bank-gifts-tbody">
                                <?php foreach ($bank_gifts_records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['id']; ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date'])); ?></strong></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date_deposited'])); ?></strong></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date_updated'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Specified Gifts Tab -->
                <div class="tab-pane" id="specified-gifts">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="specified-gifts-tbody">
                                <?php foreach ($specified_gifts_records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['id']; ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($record['category']); ?></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary Tab -->
                <div class="tab-pane" id="summary">
                    <h3>Financial Insights</h3>
                    <div class="insights-grid">
                        <div class="insight-card">
                            <h4>Total Predicted Income (2025)</h4>
                            <p>₱<?php echo number_format($prediction_summary['total_predicted_income'], 2); ?></p>
                            <p>Average Monthly: ₱<?php echo number_format($prediction_summary['average_monthly_income'], 2); ?></p>
                            <p>Predicted Growth Rate: <?php echo number_format($prediction_summary['predicted_growth_rate'], 2); ?>%</p>
                        </div>
                        <div class="insight-card">
                            <h4>Best Performing Month (2025)</h4>
                            <p><?php echo htmlspecialchars($prediction_summary['best_month']['date_formatted'] ?? 'N/A'); ?></p>
                            <p>Amount: ₱<?php echo number_format($prediction_summary['best_month']['yhat'], 2); ?></p>
                        </div>
                        <div class="insight-card">
                            <h4>Worst Performing Month (2025)</h4>
                            <p><?php echo htmlspecialchars($prediction_summary['worst_month']['date_formatted'] ?? 'N/A'); ?></p>
                            <p>Amount: ₱<?php echo number_format($prediction_summary['worst_month']['yhat'], 2); ?></p>
                        </div>
                        <div class="insight-card">
                            <h4>Total Months Predicted</h4>
                            <p><?php echo $prediction_summary['total_months']; ?></p>
                        </div>
                    </div>

                    <h3>Historical Data (Last 6 Months)</h3>
                    <div class="chart-container">
                        <canvas id="historical-chart"></canvas>
                    </div>

                    <h3>Predicted Income (2025)</h3>
                    <div class="chart-container">
                        <canvas id="predicted-chart"></canvas>
                    </div>

                    <h3>Actual Income (2025)</h3>
                    <div class="chart-container">
                        <canvas id="actual-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Profile Picture Modal -->
<div id="profileModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Profile Picture</h3>
            <span class="close" onclick="closeProfileModal()">&times;</span>
        </div>
        <form method="POST" action="" enctype="multipart/form-data" class="profile-form">
            <div class="form-group">
                <label for="profile_picture">Select New Profile Picture</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" required>
                <small style="color: #666; font-size: 12px;">Supported formats: JPG, PNG, GIF (Max 5MB)</small>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="reset_profile_picture" value="1"> Remove current profile picture
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" name="update_profile_picture" class="btn btn-primary">Update Picture</button>
                <button type="button" class="btn btn-secondary" onclick="closeProfileModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script>
    // Profile Modal Functions
    function openProfileModal() {
        document.getElementById('profileModal').style.display = 'block';
    }

    function closeProfileModal() {
        document.getElementById('profileModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('profileModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Tab Navigation
    document.addEventListener('DOMContentLoaded', function() {
        // Tab functionality
        document.querySelectorAll('.tab-navigation a').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.tab-navigation a').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // DataTables initialization
        $('#weekly-reports-table').DataTable({
            responsive: true,
            paging: true,
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            searching: true,
            ordering: true,
            info: true,
            language: {
                search: 'Search:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                paginate: {
                    previous: 'Prev',
                    next: 'Next'
                }
            }
        });

        // Initialize DataTables for each tab
        ['tithes-tbody', 'offerings-tbody', 'bank-gifts-tbody', 'specified-gifts-tbody'].forEach(function(tbodyId) {
            var $tbody = $('#' + tbodyId);
            if ($tbody.length) {
                var $table = $tbody.closest('table');
                if ($table.length) {
                    $table.DataTable({
                        responsive: true,
                        paging: true,
                        pageLength: 10,
                        lengthMenu: [5, 10, 25, 50, 100],
                        searching: true,
                        ordering: true,
                        info: true,
                        language: {
                            search: 'Search:',
                            lengthMenu: 'Show _MENU_ entries',
                            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                            paginate: {
                                previous: 'Prev',
                                next: 'Next'
                            }
                        }
                    });
                }
            }
        });

        // Chart.js for Insights
        const ctxHistorical = document.getElementById('historical-chart');
        if (ctxHistorical) {
            new Chart(ctxHistorical.getContext('2d'), {
                type: 'line',
                data: {
                    labels: Object.keys(<?php echo json_encode($historical_data); ?>),
                    datasets: [{
                        label: 'Total Income (Historical)',
                        data: Object.values(<?php echo json_encode($historical_data); ?>),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Income (₱)'
                            }
                        }
                    }
                }
            });
        }

        const ctxPredicted = document.getElementById('predicted-chart');
        if (ctxPredicted) {
            new Chart(ctxPredicted.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($prophet_predictions ?? [], 'date_formatted')); ?>,
                    datasets: [{
                        label: 'Predicted Income (2025)',
                        data: <?php echo json_encode(array_column($prophet_predictions ?? [], 'yhat')); ?>,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Income (₱)'
                            }
                        }
                    }
                }
            });
        }

        const ctxActual = document.getElementById('actual-chart');
        if (ctxActual) {
            new Chart(ctxActual.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($prophet_predictions ?? [], 'date_formatted')); ?>,
                    datasets: [{
                        label: 'Actual Income (2025)',
                        data: <?php echo json_encode($actual_data_2025); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Income (₱)'
                            }
                        }
                    }
                }
            });
        }
    });
</script>
</body>
</html> 