<?php
// financialreport.php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in and is admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "Administrator") {
            header("Location: index.php");
    exit;
}

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);



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

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = "";
    $messageType = "success";

    if (isset($_POST["add_tithes"])) {
        $date = htmlspecialchars(trim($_POST["date"]));
        $amount = floatval($_POST["amount"]);

        // Get the next available ID
        $result = $conn->query("SELECT MAX(id) as max_id FROM tithes");
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        // Check if a record with the same date already exists
        $check_sql = "SELECT id FROM tithes WHERE date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "A tithes record for this date already exists!";
            $messageType = "error";
        } else {
            // Insert into database
            $sql = "INSERT INTO tithes (id, date, amount) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isd", $next_id, $date, $amount);
            
            if ($stmt->execute()) {
                $message = "Tithes record added successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=tithes");
                exit();
            } else {
                $message = "Error adding tithes record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }

    // Handle edit tithes
    if (isset($_POST["edit_tithes"])) {
        $id = intval($_POST["record_id"]);
        $date = htmlspecialchars(trim($_POST["date"]));
        $amount = floatval($_POST["amount"]);

        // Check if another record with the same date exists
        $check_sql = "SELECT id FROM tithes WHERE date = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $date, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "A tithes record for this date already exists!";
            $messageType = "error";
        } else {
            // Update the record
            $sql = "UPDATE tithes SET date = ?, amount = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdi", $date, $amount, $id);

            if ($stmt->execute()) {
                $message = "Tithes record updated successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=tithes");
                exit();
            } else {
                $message = "Error updating tithes record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    
    // Handle edit offerings
    if (isset($_POST["edit_offering"])) {
        $id = intval($_POST["record_id"]);
        $date = htmlspecialchars(trim($_POST["date"]));
        $amount = floatval($_POST["amount"]);

        // Check if another record with the same date exists
        $check_sql = "SELECT id FROM offerings WHERE date = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $date, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "An offering record for this date already exists!";
            $messageType = "error";
        } else {
            // Update the record
            $sql = "UPDATE offerings SET date = ?, amount = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdi", $date, $amount, $id);

            if ($stmt->execute()) {
                $message = "Offering record updated successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=offering");
                exit();
            } else {
                $message = "Error updating offering record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }

    if (isset($_POST["add_offering"])) {
        $date = htmlspecialchars(trim($_POST["date"]));
        $amount = floatval($_POST["amount"]);

        // Get the next available ID
        $result = $conn->query("SELECT MAX(id) as max_id FROM offerings");
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        // Check if a record with the same date already exists
        $check_sql = "SELECT id FROM offerings WHERE date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "An offering record for this date already exists!";
            $messageType = "error";
        } else {
            // Insert into database
            $sql = "INSERT INTO offerings (id, date, amount) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isd", $next_id, $date, $amount);
            
            if ($stmt->execute()) {
                $message = "Offering record added successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=offering");
                exit();
            } else {
                $message = "Error adding offering record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } elseif (isset($_POST["add_bank_gift"])) {
        $date = htmlspecialchars(trim($_POST["date"]));
        $date_deposited = htmlspecialchars(trim($_POST["date_deposited"]));
        $date_updated = htmlspecialchars(trim($_POST["date_updated"]));
        $amount = floatval($_POST["amount"]);

        // Get the next available ID
        $result = $conn->query("SELECT MAX(id) as max_id FROM bank_gifts");
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        // Check if a record with the same date already exists
        $check_sql = "SELECT id FROM bank_gifts WHERE date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "A bank gift record for this date already exists!";
            $messageType = "error";
        } else {
            // Insert into database
            $sql = "INSERT INTO bank_gifts (id, date, date_deposited, date_updated, amount) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssd", $next_id, $date, $date_deposited, $date_updated, $amount);
            
            if ($stmt->execute()) {
                $message = "Bank gift record added successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=bank_gift");
                exit();
            } else {
                $message = "Error adding bank gift record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } elseif (isset($_POST["edit_bank_gift"])) {
        $id = intval($_POST["record_id"]);
        $date = htmlspecialchars(trim($_POST["date"]));
        $date_deposited = htmlspecialchars(trim($_POST["date_deposited"]));
        $date_updated = htmlspecialchars(trim($_POST["date_updated"]));
        $amount = floatval($_POST["amount"]);
    
        // Check if another record with the same date exists
        $check_sql = "SELECT id FROM bank_gifts WHERE date = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $date, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
    
        if ($check_result->num_rows > 0) {
            $message = "A bank gift record for this date already exists!";
            $messageType = "error";
        } else {
            // Update the record
            $sql = "UPDATE bank_gifts SET date = ?, date_deposited = ?, date_updated = ?, amount = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdi", $date, $date_deposited, $date_updated, $amount, $id);
    
            if ($stmt->execute()) {
                $message = "Bank gift record updated successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=bank_gift");
                exit();
            } else {
                $message = "Error updating bank gift record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } elseif (isset($_POST["add_specified_gift"])) {
        $date = htmlspecialchars(trim($_POST["date"]));
        $category = htmlspecialchars(trim($_POST["category"]));
        $amount = floatval($_POST["amount"]);

        // Get the next available ID
        $result = $conn->query("SELECT MAX(id) as max_id FROM specified_gifts");
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        // Check if a record with the same date and category already exists
        $check_sql = "SELECT id FROM specified_gifts WHERE date = ? AND category = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $date, $category);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "A specified gift record for this date and category already exists!";
            $messageType = "error";
        } else {
            // Insert into database
            $sql = "INSERT INTO specified_gifts (id, date, category, amount) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issd", $next_id, $date, $category, $amount);
            
            if ($stmt->execute()) {
                $message = "Specified gift record added successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=specified_gift");
                exit();
            } else {
                $message = "Error adding specified gift record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } elseif (isset($_POST["edit_specified_gift"])) {
        $id = intval($_POST["record_id"]);
        $date = htmlspecialchars(trim($_POST["date"]));
        $category = htmlspecialchars(trim($_POST["category"]));
        $amount = floatval($_POST["amount"]);

        // Check if another record with the same date and category exists
        $check_sql = "SELECT id FROM specified_gifts WHERE date = ? AND category = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $date, $category, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "A specified gift record for this date and category already exists!";
            $messageType = "error";
        } else {
            // Update the record
            $sql = "UPDATE specified_gifts SET date = ?, category = ?, amount = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $date, $category, $amount, $id);

            if ($stmt->execute()) {
                $message = "Specified gift record updated successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=specified_gift");
                exit();
            } else {
                $message = "Error updating specified gift record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }

    // Handle delete operations
    if (isset($_POST["delete_record"])) {
        $id = intval($_POST["record_id"]);
        $type = $_POST["record_type"];
        
        switch ($type) {
            case 'tithes':
                $sql = "DELETE FROM tithes WHERE id = ?";
                $table = "tithes";
                break;
            case 'offerings':
                $sql = "DELETE FROM offerings WHERE id = ?";
                $table = "offerings";
                break;
            case 'bank-gifts':
                $sql = "DELETE FROM bank_gifts WHERE id = ?";
                $table = "bank_gifts";
                break;
            case 'specified-gifts':
                $sql = "DELETE FROM specified_gifts WHERE id = ?";
                $table = "specified_gifts";
                break;
            default:
                $message = "Invalid record type";
                $messageType = "error";
                break;
        }

        if (isset($sql)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $conn->query("SET @count = 0");
                $conn->query("UPDATE $table SET id = @count:= @count + 1");
                $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
                
                $message = ucfirst($type) . " record deleted successfully!";
            } else {
                $message = "Error deleting record: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
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
// Calculate average weekly amounts using Prophet for offerings
function calculateWeeklyOfferingsProphet($conn) {
    // Fetch ALL weekly offerings data (no date restriction)
    $sql = "
        SELECT 
            DATE_FORMAT(date, '%Y-%U') as week,
            SUM(amount) as total
        FROM offerings
        GROUP BY DATE_FORMAT(date, '%Y-%U')
        ORDER BY week ASC";
    $result = $conn->query($sql);
    $weekly_data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Convert week to a date (start of the week)
            $year_week = explode('-', $row['week']);
            $year = $year_week[0];
            $week = ltrim($year_week[1], '0') ?: 0;
            $date = date('Y-m-d', strtotime("$year-01-01 +$week weeks"));
            $weekly_data[] = [
                'ds' => $date,
                'y' => floatval($row['total'])
            ];
        }
    }

    // Make API call to Prophet endpoint
    $ch = curl_init('http://localhost:5000/predict');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => $weekly_data]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $predictions = json_decode($response, true);
        if (is_array($predictions) && !empty($predictions)) {
            // Get predictions for the last 4 weeks
            $last_4_weeks = array_slice($predictions, -4);
            $total = 0;
            $count = 0;
            foreach ($last_4_weeks as $pred) {
                if (isset($pred['yhat']) && $pred['yhat'] >= 0) {
                    $total += $pred['yhat'];
                    $count++;
                }
            }
            return $count > 0 ? $total / $count : 0;
        }
    }

    // Fallback: Calculate simple average if Prophet fails (using ALL data)
    $sql = "
        SELECT 
            AVG(total) as avg_weekly
        FROM (
            SELECT 
                DATE_FORMAT(date, '%Y-%U') as week,
                SUM(amount) as total
            FROM offerings
            GROUP BY DATE_FORMAT(date, '%Y-%U')
        ) weekly";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['avg_weekly'] ?? 0;
}

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

// Add debugging
error_log("Weekly Averages: " . print_r($weekly_averages, true));

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

// Add debugging
error_log("SQL Query for historical data: " . $sql);

$result = $conn->query($sql);
$historical_data = [];

if ($result === false) {
    error_log("SQL Error: " . $conn->error);
} else {
    if ($result->num_rows === 0) {
        error_log("No historical data found");
    }
    while ($row = $result->fetch_assoc()) {
        $historical_data[$row['month']] = $row['total'];
        error_log("Month: " . $row['month'] . ", Total: " . $row['total']);
    }
}

// If no data found, initialize with zeros for the last 6 months
if (empty($historical_data)) {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $historical_data[$month] = 0;
    }                           
}

error_log("Final Historical Data: " . print_r($historical_data, true));

// Calculate predicted next month income using Prophet
function getProphetPrediction($conn) {
    // Fetch ALL monthly data from tithes and offerings (no date restriction)
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
    
    // Debug: Log the data being sent to Prophet
    error_log("Prophet data points: " . count($prophet_data));
    error_log("Prophet data sample: " . json_encode(array_slice($prophet_data, 0, 5)));
    if (count($prophet_data) > 0) {
        error_log("Data range: " . $prophet_data[0]['ds'] . " to " . end($prophet_data)['ds']);
        error_log("Value range: " . min(array_column($prophet_data, 'y')) . " to " . max(array_column($prophet_data, 'y')));
    }

    // Check if we have enough data points
    if (count($prophet_data) < 3) {
        error_log("Not enough data points for Prophet prediction");
        return null;
    }

    // Make API call to Prophet endpoint
    $ch = curl_init('http://localhost:5000/predict');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => $prophet_data]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Add timeout

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200 && !empty($response)) {
        $predictions = json_decode($response, true);
        if (is_array($predictions) && !empty($predictions)) {
            error_log("Prophet predictions received successfully");
            
            // Filter predictions for 2025
            $predictions_2025 = [];
            foreach ($predictions as $pred) {
                $date = new DateTime($pred['ds']);
                if ($date->format('Y') === '2025') {
                    $predictions_2025[] = [
                        'month' => $date->format('Y-m'),
                        'date_formatted' => $date->format('F 01, Y'), // Add formatted date
                        'yhat' => $pred['yhat'],
                        'yhat_lower' => $pred['yhat_lower'],
                        'yhat_upper' => $pred['yhat_upper']
                    ];
                }
            }

            // Verify we have predictions for all months
            if (count($predictions_2025) === 12) {
                return $predictions_2025;
            }
        }
    }

    error_log("Prophet prediction failed. HTTP code: " . $http_code);
    error_log("Curl error: " . $curl_error);
    error_log("Response: " . $response);
    
    // Calculate monthly averages for the past year
    $monthly_averages = [];
    foreach ($prophet_data as $data) {
        $month = date('m', strtotime($data['ds']));
        if (!isset($monthly_averages[$month])) {
            $monthly_averages[$month] = ['total' => 0, 'count' => 0];
        }
        $monthly_averages[$month]['total'] += $data['y'];
        $monthly_averages[$month]['count']++;
    }

    // Generate predictions using monthly patterns
    $predictions_2025 = [];
    for ($month = 1; $month <= 12; $month++) {
        $month_key = str_pad($month, 2, '0', STR_PAD_LEFT);
        $avg = isset($monthly_averages[$month_key]) 
            ? $monthly_averages[$month_key]['total'] / $monthly_averages[$month_key]['count']
            : array_sum(array_column($prophet_data, 'y')) / count($prophet_data);
        
        // Create date for formatting
        $date = DateTime::createFromFormat('Y-m', "2025-" . $month_key);
        
        $predictions_2025[] = [
            'month' => "2025-" . $month_key,
            'date_formatted' => $date->format('F 01, Y'), // Add formatted date
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

error_log("Predicted Monthly: " . $predicted_monthly);
error_log("Prediction Lower: " . $prediction_lower);
error_log("Prediction Upper: " . $prediction_upper);
error_log("Avg Weekly Tithes: " . $avg_weekly_tithes);
error_log("Avg Weekly Offerings: " . $avg_weekly_offerings);
error_log("Avg Weekly Bank Gifts: " . $avg_weekly_bank_gifts);
error_log("Avg Weekly Specified Gifts: " . $avg_weekly_specified_gifts);

// Calculate prediction summary from prophet predictions
if ($prophet_predictions && count($prophet_predictions) > 0) {
    $predicted_values = array_column($prophet_predictions, 'yhat');
    $total_predicted = array_sum($predicted_values);
    $avg_monthly = $total_predicted / count($predicted_values);
    
    // Find best and worst months
    $best_month = $prophet_predictions[array_search(max($predicted_values), $predicted_values)];
    $worst_month = $prophet_predictions[array_search(min($predicted_values), $predicted_values)];
    
    // Calculate growth rate by comparing with historical average
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
    // Fallback initialization if no predictions
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
// Align actuals and predictions for 2025
$months_2025 = array_map(function($p) { return $p['month']; }, $prophet_predictions ?? []);
$actual_data_2025 = [];
$predicted_data_2025 = [];
foreach ($months_2025 as $i => $month) {
    $actual_data_2025[] = isset($actuals_2025[$month]) ? $actuals_2025[$month] : 0;
    $predicted_data_2025[] = isset($prophet_predictions[$i]['yhat']) ? $prophet_predictions[$i]['yhat'] : 0;
}

// Pass the calculated values to JavaScript (without console logging for security)
echo "<script>
    const historicalData = " . json_encode($historical_data) . ";
    const avgWeeklyTithes = " . $avg_weekly_tithes . ";
    const avgWeeklyOfferings = " . $avg_weekly_offerings . ";
    const avgWeeklyBankGifts = " . $avg_weekly_bank_gifts . ";
    const avgWeeklySpecifiedGifts = " . $avg_weekly_specified_gifts . ";
    const predictedMonthly = " . $predicted_monthly . ";
    const predictionLower = " . $prediction_lower . ";
    const predictionUpper = " . $prediction_upper . ";
    const prophetPredictions = " . json_encode($prophet_predictions) . ";
    const predictionSummary = " . json_encode($prediction_summary) . ";
    
    // Validate data before creating charts (silent validation)
    const hasPredictionData = prophetPredictions && prophetPredictions.length > 0;
    const hasHistoricalData = historicalData && Object.keys(historicalData).length > 0;

    // 1. Fetch actual totals for each month in 2025
    const actuals2025 = " . json_encode($prophet_predictions) . ";
    const months2025 = " . json_encode($months_2025) . ";
    const actualData2025 = " . json_encode($actual_data_2025) . ";
    const predictedData2025 = " . json_encode($predicted_data_2025) . ";
</script>";



// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'tithes':
            $message = "Tithes record added successfully!";
            break;
        case 'offering':
            $message = "Offering record added successfully!";
            break;
        case 'bank_gift':
            $message = "Bank gift record added or updated successfully!";
            break;
        case 'specified_gift':
            $message = "Specified gift record added or updated successfully!";
            break;
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

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
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

        .btn i {
            margin-right: 5px;
        }

        .financial-form {
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
            display: none;
        }

        .financial-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .denomination-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            transition: opacity 0.3s ease-in-out;
        }

        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .alert-error {
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
            .action-bar {
                flex-direction: column;
                gap: 10px;
            }
            .denomination-grid {
                grid-template-columns: 1fr;
            }
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

        .action-btn.edit-btn {
            background-color: #4a90e2;
        }

        .action-btn.edit-btn:hover {
            background-color: #357abd;
        }

        .action-btn.delete-btn {
            background-color: #e74c3c;
        }

        .action-btn.delete-btn:hover {
            background-color: #c0392b;
        }

        .action-btn i {
            font-size: 14px;
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

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 20px;
            padding: 10px;
        }

        .year-buttons {
            display: flex;
            gap: 10px;
        }

        .year-btn {
            padding: 8px 16px;
            border: 1px solid var(--accent-color);
            background: white;
            color: var(--accent-color);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .year-btn.active {
            background: var(--accent-color);
            color: white;
        }

        .year-btn:hover:not(.active) {
            background: rgba(0, 139, 30, 0.1);
        }

        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid var(--accent-color);
            background: white;
            color: var(--accent-color);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn:not(:disabled):hover {
            background: rgba(0, 139, 30, 0.1);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-card.full-width {
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
            <h2>Financial Reports</h2>
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

        <div class="financial-content">
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo ($messageType === 'error') ? 'alert-error' : 'alert-success'; ?>">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

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
                    <div class="action-bar">
                        <button class="btn" id="add-tithes-btn">
                            <i class="fas fa-plus"></i> Add New Tithes
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tithes-tbody">
                                <?php foreach ($tithes_records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['id']; ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn edit-btn" 
                                                        data-id="<?php echo $record['id']; ?>" 
                                                        data-type="tithes"
                                                        data-date="<?php echo $record['date']; ?>"
                                                        data-amount="<?php echo $record['amount']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn delete-btn" data-id="<?php echo $record['id']; ?>" data-type="tithes">
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

                <!-- Offerings Tab -->
                <div class="tab-pane" id="offerings">
                    <div class="action-bar">
                        <button class="btn" id="add-offering-btn">
                            <i class="fas fa-plus"></i> Add New Offering
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="offerings-tbody">
                                <?php foreach ($offerings_records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['id']; ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn edit-btn" 
                                                        data-id="<?php echo $record['id']; ?>" 
                                                        data-type="offerings"
                                                        data-date="<?php echo $record['date']; ?>"
                                                        data-amount="<?php echo $record['amount']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn delete-btn" data-id="<?php echo $record['id']; ?>" data-type="offerings">
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

                <!-- Bank Gifts Tab -->
                <div class="tab-pane" id="bank-gifts">
                    <div class="action-bar">
                        <button class="btn" id="add-bank-gift-btn">
                            <i class="fas fa-plus"></i> Add New Bank Gift
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Date Deposited</th>
                                    <th>Date Updated</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
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
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn edit-btn" 
                                                        data-id="<?php echo $record['id']; ?>" 
                                                        data-type="bank-gifts"
                                                        data-date="<?php echo $record['date']; ?>"
                                                        data-date-deposited="<?php echo $record['date_deposited']; ?>"
                                                        data-date-updated="<?php echo $record['date_updated']; ?>"
                                                        data-amount="<?php echo $record['amount']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn delete-btn" data-id="<?php echo $record['id']; ?>" data-type="bank-gifts">
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

                <!-- Specified Gifts Tab -->
                <div class="tab-pane" id="specified-gifts">
                    <div class="action-bar">
                        <button class="btn" id="add-specified-gift-btn">
                            <i class="fas fa-plus"></i> Add New Specified Gift
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="specified-gifts-tbody">
                                <?php foreach ($specified_gifts_records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['id']; ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['date'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($record['category']); ?></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn edit-btn" 
                                                        data-id="<?php echo $record['id']; ?>" 
                                                        data-type="specified-gifts"
                                                        data-date="<?php echo $record['date']; ?>"
                                                        data-category="<?php echo htmlspecialchars($record['category']); ?>"
                                                        data-amount="<?php echo $record['amount']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn delete-btn" data-id="<?php echo $record['id']; ?>" data-type="specified-gifts">
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

                <!-- Summary Tab -->
                <div class="tab-pane" id="summary">
                    <div class="summary-content">
                        <div class="summary-grid">
                            <div class="summary-card full-width">
                                <h3>Actual Income vs Predicted Amount</h3>
                                <div class="prediction-chart">
                                    <canvas id="actualVsPredictedChart"></canvas>
                                </div>
                            </div>
                            <div class="summary-card full-width">
                                <h3>Monthly Income Predictions</h3>
                                <div class="prediction-chart">
                                    <canvas id="predictionChart2025"></canvas>
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
                                        <span class="label">Predicted Growth Rate</span>
                                        <span class="value <?php echo $prediction_summary['predicted_growth_rate'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo ($prediction_summary['predicted_growth_rate'] >= 0 ? '+' : '') . number_format($prediction_summary['predicted_growth_rate'], 1); ?>%
                                        </span>
                                    </div>
                                    <div class="prediction-metric">
                                        <span class="label">Best Month</span>
                                        <span class="value"><?php echo $prediction_summary['best_month']['date_formatted'] ?? $prediction_summary['best_month']['month']; ?> (₱<?php echo number_format($prediction_summary['best_month']['yhat'], 2); ?>)</span>
                                    </div>
                                    <div class="prediction-metric">
                                        <span class="label">Worst Month</span>
                                        <span class="value"><?php echo $prediction_summary['worst_month']['date_formatted'] ?? $prediction_summary['worst_month']['month']; ?> (₱<?php echo number_format($prediction_summary['worst_month']['yhat'], 2); ?>)</span>
                                    </div>
                                </div>
                            </div>
                            <div class="summary-card full-width">
                                <h3>Historical Income Trend</h3>
                                <div class="prediction-chart">
                                    <canvas id="trendChart"></canvas>
                                </div>
                                <div class="prediction-details">
                                    <div class="prediction-metric">
                                        <span class="label">Total Historical Income</span>
                                        <span class="value">₱<?php echo number_format(array_sum(array_values($historical_data)), 2); ?></span>
                                    </div>
                                    <div class="prediction-metric">
                                        <span class="label">Average Monthly Income</span>
                                        <span class="value">₱<?php echo number_format(array_sum(array_values($historical_data)) / max(count($historical_data), 1), 2); ?></span>
                                    </div>
                                    <div class="prediction-metric">
                                        <span class="label">Data Period</span>
                                        <span class="value"><?php echo count($historical_data); ?> months</span>
                                    </div>
                                    <div class="prediction-metric">
                                        <span class="label">Highest Month</span>
                                        <span class="value">₱<?php echo number_format(max(array_values($historical_data)), 2); ?></span>
                                    </div>
                                    <div class="prediction-metric">
                                        <span class="label">Lowest Month</span>
                                        <span class="value">₱<?php echo number_format(min(array_values($historical_data)), 2); ?></span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Tithes Form Modal -->
            <div id="tithes-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="tithes-modal-title">Add New Tithes</h3>
                        <span class="close">×</span>
                    </div>
                    <form id="tithes-form" method="post" action="">
                        <input type="hidden" name="record_id" id="tithes-record-id">
                        <div class="form-group">
                            <label for="tithes-date">Date</label>
                            <input type="date" class="form-control" id="tithes-date" name="date" required>
                        </div>
                        <div class="form-group">
                            <label for="tithes-amount">Amount (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="tithes-amount" name="amount" required>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancel-tithes">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="add_tithes" id="tithes-submit-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Offerings Form Modal -->
            <div id="offering-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="offering-modal-title">Add New Offering</h3>
                        <span class="close">×</span>
                    </div>
                    <form id="offering-form" method="post" action="">
                        <input type="hidden" name="record_id" id="offering-record-id">
                        <div class="form-group">
                            <label for="offering-date">Date</label>
                            <input type="date" class="form-control" id="offering-date" name="date" required>
                        </div>
                        <div class="form-group">
                            <label for="offering-amount">Amount (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="offering-amount" name="amount" required>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancel-offering">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="add_offering" id="offering-submit-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bank Gifts Form Modal -->
            <div id="bank-gift-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="bank-gift-modal-title">Add New Bank Gift</h3>
                        <span class="close">×</span>
                    </div>
                    <form id="bank-gift-form" method="post" action="">
                        <input type="hidden" name="record_id" id="bank-gift-record-id">
                        <div class="form-group">
                            <label for="bank-gift-date">Date</label>
                            <input type="date" class="form-control" id="bank-gift-date" name="date" required>
                        </div>
                        <div class="form-group">
                            <label for="bank-gift-date-deposited">Date Deposited</label>
                            <input type="date" class="form-control" id="bank-gift-date-deposited" name="date_deposited" required>
                        </div>
                        <div class="form-group">
                            <label for="bank-gift-date-updated">Date Updated</label>
                            <input type="date" class="form-control" id="bank-gift-date-updated" name="date_updated" required>
                        </div>
                        <div class="form-group">
                            <label for="bank-gift-amount">Amount (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="bank-gift-amount" name="amount" required>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancel-bank-gift">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="add_bank_gift" id="bank-gift-submit-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Specified Gifts Form Modal -->
            <div id="specified-gift-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="specified-gift-modal-title">Add New Specified Gift</h3>
                        <span class="close">×</span>
                    </div>
                    <form id="specified-gift-form" method="post" action="">
                        <input type="hidden" name="record_id" id="specified-gift-record-id">
                        <div class="form-group">
                            <label for="specified-gift-date">Date</label>
                            <input type="date" class="form-control" id="specified-gift-date" name="date" required>
                        </div>
                        <div class="form-group">
                            <label for="specified-gift-category">Category</label>
                            <select class="form-control" id="specified-gift-category" name="category" required>
                                <?php foreach ($specified_gifts as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="specified-gift-amount">Amount (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="specified-gift-amount" name="amount" required>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancel-specified-gift">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="add_specified_gift" id="specified-gift-submit-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div id="delete-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Confirm Deletion</h3>
                        <span class="close">×</span>
                    </div>
                    <form id="delete-form" method="post" action="">
                        <input type="hidden" name="record_id" id="delete-record-id">
                        <input type="hidden" name="record_type" id="delete-record-type">
                        <p>Are you sure you want to delete this record?</p>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancel-delete">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="delete_record">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script>
    // Tab Navigation
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

    // Modal Handling
    const modals = document.querySelectorAll('.modal');
    const closeButtons = document.querySelectorAll('.close');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            modals.forEach(modal => modal.style.display = 'none');
        });
    });

    window.addEventListener('click', (e) => {
        modals.forEach(modal => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Tithes Form Handling
    document.getElementById('add-tithes-btn').addEventListener('click', () => {
        const modal = document.getElementById('tithes-modal');
        document.getElementById('tithes-modal-title').textContent = 'Add New Tithes';
        document.getElementById('tithes-record-id').value = '';
        document.getElementById('tithes-date').value = '';
        document.getElementById('tithes-amount').value = '';
        document.getElementById('tithes-submit-btn').setAttribute('name', 'add_tithes');
        modal.style.display = 'block';
    });

    document.getElementById('cancel-tithes').addEventListener('click', () => {
        document.getElementById('tithes-modal').style.display = 'none';
    });

    document.querySelectorAll('.edit-btn[data-type="tithes"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('tithes-modal');
            document.getElementById('tithes-modal-title').textContent = 'Edit Tithes';
            document.getElementById('tithes-record-id').value = btn.dataset.id;
            document.getElementById('tithes-date').value = btn.dataset.date;
            document.getElementById('tithes-amount').value = btn.dataset.amount;
            document.getElementById('tithes-submit-btn').setAttribute('name', 'edit_tithes');
            modal.style.display = 'block';
        });
    });

    // Offerings Form Handling
    document.getElementById('add-offering-btn').addEventListener('click', () => {
        const modal = document.getElementById('offering-modal');
        document.getElementById('offering-modal-title').textContent = 'Add New Offering';
        document.getElementById('offering-record-id').value = '';
        document.getElementById('offering-date').value = '';
        document.getElementById('offering-amount').value = '';
        document.getElementById('offering-submit-btn').setAttribute('name', 'add_offering');
        modal.style.display = 'block';
    });

    document.getElementById('cancel-offering').addEventListener('click', () => {
        document.getElementById('offering-modal').style.display = 'none';
    });

    document.querySelectorAll('.edit-btn[data-type="offerings"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('offering-modal');
            document.getElementById('offering-modal-title').textContent = 'Edit Offering';
            document.getElementById('offering-record-id').value = btn.dataset.id;
            document.getElementById('offering-date').value = btn.dataset.date;
            document.getElementById('offering-amount').value = btn.dataset.amount;
            document.getElementById('offering-submit-btn').setAttribute('name', 'edit_offering');
            modal.style.display = 'block';
        });
    });

    // Bank Gifts Form Handling
    document.getElementById('add-bank-gift-btn').addEventListener('click', () => {
        const modal = document.getElementById('bank-gift-modal');
        document.getElementById('bank-gift-modal-title').textContent = 'Add New Bank Gift';
        document.getElementById('bank-gift-record-id').value = '';
        document.getElementById('bank-gift-date').value = '';
        document.getElementById('bank-gift-date-deposited').value = '';
        document.getElementById('bank-gift-date-updated').value = '';
        document.getElementById('bank-gift-amount').value = '';
        document.getElementById('bank-gift-submit-btn').setAttribute('name', 'add_bank_gift');
        modal.style.display = 'block';
    });

    document.getElementById('cancel-bank-gift').addEventListener('click', () => {
        document.getElementById('bank-gift-modal').style.display = 'none';
    });

    document.querySelectorAll('.edit-btn[data-type="bank-gifts"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('bank-gift-modal');
            document.getElementById('bank-gift-modal-title').textContent = 'Edit Bank Gift';
            document.getElementById('bank-gift-record-id').value = btn.dataset.id;
            document.getElementById('bank-gift-date').value = btn.dataset.date;
            document.getElementById('bank-gift-date-deposited').value = btn.dataset.dateDeposited;
            document.getElementById('bank-gift-date-updated').value = btn.dataset.dateUpdated;
            document.getElementById('bank-gift-amount').value = btn.dataset.amount;
            document.getElementById('bank-gift-submit-btn').setAttribute('name', 'edit_bank_gift');
            modal.style.display = 'block';
        });
    });

    // Specified Gifts Form Handling
    document.getElementById('add-specified-gift-btn').addEventListener('click', () => {
        const modal = document.getElementById('specified-gift-modal');
        document.getElementById('specified-gift-modal-title').textContent = 'Add New Specified Gift';
        document.getElementById('specified-gift-record-id').value = '';
        document.getElementById('specified-gift-date').value = '';
        document.getElementById('specified-gift-category').value = '';
        document.getElementById('specified-gift-amount').value = '';
        document.getElementById('specified-gift-submit-btn').setAttribute('name', 'add_specified_gift');
        modal.style.display = 'block';
    });

    document.getElementById('cancel-specified-gift').addEventListener('click', () => {
        document.getElementById('specified-gift-modal').style.display = 'none';
    });

    document.querySelectorAll('.edit-btn[data-type="specified-gifts"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('specified-gift-modal');
            document.getElementById('specified-gift-modal-title').textContent = 'Edit Specified Gift';
            document.getElementById('specified-gift-record-id').value = btn.dataset.id;
            document.getElementById('specified-gift-date').value = btn.dataset.date;
            document.getElementById('specified-gift-category').value = btn.dataset.category;
            document.getElementById('specified-gift-amount').value = btn.dataset.amount;
            document.getElementById('specified-gift-submit-btn').setAttribute('name', 'edit_specified_gift');
            modal.style.display = 'block';
        });
    });

    // Delete Confirmation Handling
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('delete-modal');
            document.getElementById('delete-record-id').value = btn.dataset.id;
            document.getElementById('delete-record-type').value = btn.dataset.type;
            modal.style.display = 'block';
        });
    });

    document.getElementById('cancel-delete').addEventListener('click', () => {
        document.getElementById('delete-modal').style.display = 'none';
    });



    // Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    
    // Check if we have historical data
    if (historicalData && Object.keys(historicalData).length > 0) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: Object.keys(historicalData),
                datasets: [{
                    label: 'Monthly Income',
                    data: Object.values(historicalData),
                    fill: false,
                    borderColor: 'rgba(0, 139, 30, 1)',
                    tension: 0.1
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
                            text: 'Amount'
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
                        text: 'Historical Income Trend (Last 6 Months)'
                    }
                }
            }
        });
            } else {
            // Create a placeholder chart or show message (silent error handling)
            trendCtx.canvas.style.display = 'none';
            const chartContainer = trendCtx.canvas.parentElement;
            chartContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No historical data available. Please add some tithes and offerings records.</p>';
        }

    // Monthly Income Predictions Chart
    const predictionCtx2025 = document.getElementById('predictionChart2025').getContext('2d');
    
            // Check if we have prediction data
        if (hasPredictionData) {
        
        new Chart(predictionCtx2025, {
            type: 'bar',
            data: {
                labels: prophetPredictions.map(p => p.date_formatted || p.month),
                datasets: [{
                    label: 'Predicted Amount',
                    data: prophetPredictions.map(p => p.yhat),
                    backgroundColor: 'rgba(0, 100, 0, 0.8)',
                    borderColor: 'rgba(0, 100, 0, 1)',
                    borderWidth: 1
                }, {
                    label: 'Confidence Range (Lower)',
                    data: prophetPredictions.map(p => p.yhat_lower),
                    backgroundColor: 'rgba(255, 165, 0, 0.6)',
                    borderColor: 'rgba(255, 140, 0, 1)',
                    borderWidth: 1
                }, {
                    label: 'Confidence Range (Upper)',
                    data: prophetPredictions.map(p => p.yhat_upper),
                    backgroundColor: 'rgba(220, 53, 69, 0.6)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
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
                        text: 'Monthly Income Predictions'
                    }
                }
            }
        });
            } else {
            // Create a placeholder chart or show message (silent error handling)
            predictionCtx2025.canvas.style.display = 'none';
            const chartContainer = predictionCtx2025.canvas.parentElement;
            chartContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No prediction data available. Please ensure you have sufficient tithes and offerings data.</p>';
        }

    // Helper to format 'YYYY-MM' to 'Month 01, YYYY'
    function formatMonthLabel(ym) {
        const [year, month] = ym.split('-');
        const date = new Date(year, parseInt(month, 10) - 1, 1);
        const monthName = date.toLocaleString('default', { month: 'long' });
        return `${monthName} 01, ${year}`;
    }

    // Actual vs Predicted Chart
    (function() {
        // Check if we have prediction data
        if (!hasPredictionData) {
            const ctx = document.getElementById('actualVsPredictedChart');
            if (ctx) {
                ctx.style.display = 'none';
                const chartContainer = ctx.parentElement;
                chartContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No prediction data available for comparison.</p>';
            }
            return;
        }

        // Use all months from prophetPredictions
        const months = prophetPredictions.map(p => p.month);
        // Prepare data arrays
        const actualData = months.map(m => historicalData[m] !== undefined ? historicalData[m] : 0);
        const predictedData = prophetPredictions.map(p => p.yhat);
        // Format labels using date_formatted if available
        const labels = prophetPredictions.map(p => p.date_formatted || formatMonthLabel(p.month));
        
        // Draw chart
        const ctx = document.getElementById('actualVsPredictedChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Actual Income',
                        data: actualData,
                        backgroundColor: 'rgba(255, 140, 0, 0.85)', // dark orange
                        borderColor: 'rgba(255, 140, 0, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Predicted Amount',
                        data: predictedData,
                        backgroundColor: 'rgba(0, 100, 0, 0.85)', // dark green
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
                        text: 'Actual Income vs Predicted Amount'
                    }
                }
            }
        });
    })();

    // DataTables Initialization (jQuery version)
    $(document).ready(function() {
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
    });

    // Add Weekly Reports DataTable initialization
    $(document).ready(function() {
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
    });
</script>
</body>
</html>