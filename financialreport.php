<?php
// financialreport.php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get church logo
$church_logo = getChurchLogo($conn);

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Check if user is logged in and is admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user"] !== "admin") {
    header("Location: login.php");
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
                // Redirect to prevent form resubmission
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
                // Redirect to prevent form resubmission
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
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=bank_gift");
                exit();
            } else {
                $message = "Error adding bank gift record: " . $conn->error;
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
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=specified_gift");
                exit();
            } else {
                $message = "Error adding specified gift record: " . $conn->error;
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
        
        // Delete the record based on type
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
                // Reset auto-increment and reorder IDs
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

// Calculate average weekly amounts for each source
$sql = "SELECT 
    (SELECT COALESCE(AVG(amount), 0) FROM tithes WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 4 WEEK)) as avg_tithes,
    (SELECT COALESCE(AVG(amount), 0) FROM offerings WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 4 WEEK)) as avg_offerings,
    (SELECT COALESCE(AVG(amount), 0) FROM bank_gifts WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 4 WEEK)) as avg_bank_gifts,
    (SELECT COALESCE(AVG(amount), 0) FROM specified_gifts WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 4 WEEK)) as avg_specified_gifts";
$result = $conn->query($sql);
$weekly_averages = $result->fetch_assoc();

$avg_weekly_tithes = $weekly_averages['avg_tithes'] ?? 0;
$avg_weekly_offerings = $weekly_averages['avg_offerings'] ?? 0;
$avg_weekly_bank_gifts = $weekly_averages['avg_bank_gifts'] ?? 0;
$avg_weekly_specified_gifts = $weekly_averages['avg_specified_gifts'] ?? 0;

// Calculate monthly totals for predictions including all sources
$monthly_totals = [];
$current_month = date('Y-m');
$previous_month = date('Y-m', strtotime('-1 month'));

// Calculate current month total from all sources
$sql = "SELECT 
    (SELECT COALESCE(SUM(amount), 0) FROM tithes WHERE DATE_FORMAT(date, '%Y-%m') = ?) +
    (SELECT COALESCE(SUM(amount), 0) FROM offerings WHERE DATE_FORMAT(date, '%Y-%m') = ?) +
    (SELECT COALESCE(SUM(amount), 0) FROM bank_gifts WHERE DATE_FORMAT(date, '%Y-%m') = ?) +
    (SELECT COALESCE(SUM(amount), 0) FROM specified_gifts WHERE DATE_FORMAT(date, '%Y-%m') = ?) as total";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $current_month, $current_month, $current_month, $current_month);
$stmt->execute();
$result = $stmt->get_result();
$monthly_totals[$current_month] = $result->fetch_assoc()['total'] ?? 0;

// Calculate previous month total from all sources
$sql = "SELECT 
    (SELECT COALESCE(SUM(amount), 0) FROM tithes WHERE DATE_FORMAT(date, '%Y-%m') = ?) +
    (SELECT COALESCE(SUM(amount), 0) FROM offerings WHERE DATE_FORMAT(date, '%Y-%m') = ?) +
    (SELECT COALESCE(SUM(amount), 0) FROM bank_gifts WHERE DATE_FORMAT(date, '%Y-%m') = ?) +
    (SELECT COALESCE(SUM(amount), 0) FROM specified_gifts WHERE DATE_FORMAT(date, '%Y-%m') = ?) as total";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $previous_month, $previous_month, $previous_month, $previous_month);
$stmt->execute();
$result = $stmt->get_result();
$monthly_totals[$previous_month] = $result->fetch_assoc()['total'] ?? 0;

// Calculate growth rate
$growth_rate = 0;
if ($monthly_totals[$previous_month] > 0) {
    $growth_rate = (($monthly_totals[$current_month] - $monthly_totals[$previous_month]) / $monthly_totals[$previous_month]) * 100;
}

// Get historical data for trend analysis
$sql = "SELECT 
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
WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(date, '%Y-%m')
ORDER BY month ASC";
$result = $conn->query($sql);
$historical_data = [];
while ($row = $result->fetch_assoc()) {
    $historical_data[$row['month']] = $row['total'];
}

// Calculate predicted next month income
$predicted_monthly = ($avg_weekly_tithes + $avg_weekly_offerings + $avg_weekly_bank_gifts + $avg_weekly_specified_gifts) * 4;

// Calculate confidence level based on data quality
$sql = "SELECT 
    (SELECT COUNT(*) FROM tithes WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as tithes_count,
    (SELECT COUNT(*) FROM offerings WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as offerings_count,
    (SELECT COUNT(*) FROM bank_gifts WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as bank_gifts_count,
    (SELECT COUNT(*) FROM specified_gifts WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as specified_gifts_count";
$result = $conn->query($sql);
$data_points = $result->fetch_assoc();

$total_data_points = array_sum($data_points);
$confidence_level = min(95, max(60, 70 + ($total_data_points / 20)));

// Calculate prediction bounds
$prediction_lower = $predicted_monthly * 0.9; // 10% lower bound
$prediction_upper = $predicted_monthly * 1.1; // 10% upper bound

// Pass the calculated values to JavaScript
echo "<script>
    const monthlyTotals = " . json_encode($monthly_totals) . ";
    const historicalData = " . json_encode($historical_data) . ";
    const avgWeeklyTithes = " . $avg_weekly_tithes . ";
    const avgWeeklyOfferings = " . $avg_weekly_offerings . ";
    const avgWeeklyBankGifts = " . $avg_weekly_bank_gifts . ";
    const avgWeeklySpecifiedGifts = " . $avg_weekly_specified_gifts . ";
    const predictedMonthly = " . $predicted_monthly . ";
    const confidenceLevel = " . $confidence_level . ";
    const predictionLower = " . $prediction_lower . ";
    const predictionUpper = " . $prediction_upper . ";
    const growthRate = " . $growth_rate . ";
</script>";

// Calculate predicted next month income using weighted averages
$predicted_monthly = ($avg_weekly_tithes + $avg_weekly_offerings + $avg_weekly_bank_gifts + $avg_weekly_specified_gifts) * 4;

// Calculate confidence level based on data quality and consistency
$sql = "SELECT 
    (SELECT COUNT(*) FROM tithes WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as tithes_count,
    (SELECT COUNT(*) FROM offerings WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as offerings_count,
    (SELECT COUNT(*) FROM bank_gifts WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as bank_gifts_count,
    (SELECT COUNT(*) FROM specified_gifts WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)) as specified_gifts_count";
$result = $conn->query($sql);
$data_points = $result->fetch_assoc();

$total_data_points = array_sum($data_points);
$confidence_level = min(95, max(60, 70 + ($total_data_points / 20)));

// Replace the prediction calculation code with:
$predictor = new FinancialPredictor($historical_data);
$prediction = $predictor->predictNextMonth();

$predicted_monthly = $prediction['prediction'];
$confidence_level = $prediction['confidence'];
$prediction_lower = $prediction['lowerBound'];
$prediction_upper = $prediction['upperBound'];

// Get Prophet predictions
$prophet_predictions = getProphetPrediction($historical_data);

if ($prophet_predictions) {
    // Use Prophet predictions if available
    $predicted_monthly = $prophet_predictions[0]['yhat'];
    $confidence_level = 85; // Prophet typically has good confidence
    $prediction_lower = $prophet_predictions[0]['yhat_lower'] ?? ($predicted_monthly * 0.9);
    $prediction_upper = $prophet_predictions[0]['yhat_upper'] ?? ($predicted_monthly * 1.1);
}

// Pass the calculated values to JavaScript
echo "<script>
    // Debug logging
    console.log('Initializing chart data...');
    const monthlyTotals = " . json_encode($monthly_totals) . ";
    const historicalData = " . json_encode($historical_data) . ";
    const avgWeeklyTithes = " . $avg_weekly_tithes . ";
    const avgWeeklyOfferings = " . $avg_weekly_offerings . ";
    const avgWeeklyBankGifts = " . $avg_weekly_bank_gifts . ";
    const avgWeeklySpecifiedGifts = " . $avg_weekly_specified_gifts . ";
    const predictedMonthly = " . $predicted_monthly . ";
    const confidenceLevel = " . $confidence_level . ";
    const predictionLower = " . $prediction_lower . ";
    const predictionUpper = " . $prediction_upper . ";
    const growthRate = " . $growth_rate . ";
    const prophetPredictions = " . json_encode($prophet_predictions) . ";
    
    // Log the data
    console.log('Monthly Totals:', monthlyTotals);
    console.log('Historical Data:', historicalData);
    console.log('Weekly Averages:', {
        tithes: avgWeeklyTithes,
        offerings: avgWeeklyOfferings,
        bankGifts: avgWeeklyBankGifts,
        specifiedGifts: avgWeeklySpecifiedGifts
    });
    console.log('Predicted Monthly:', predictedMonthly);
    console.log('Confidence Level:', confidenceLevel);
    console.log('Prediction Range:', {
        lower: predictionLower,
        upper: predictionUpper
    });
    console.log('Growth Rate:', growthRate);
    console.log('Prophet Predictions:', prophetPredictions);
</script>";

// Function to calculate total amount from denominations
function calculate_total($denominations, $denomination_list) {
    $total = 0;
    foreach ($denomination_list['bills'] as $bill) {
        $total += $denominations["bill_$bill"] * $bill;
    }
    foreach ($denomination_list['coins'] as $coin) {
        $total += $denominations["coin_$coin"] * $coin;
    }
    return $total;
}

// Add this near the top of the file, after session_start()
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'tithes':
            $message = "Tithes record added successfully!";
            break;
        case 'offering':
            $message = "Offering record added successfully!";
            break;
        case 'bank_gift':
            $message = "Bank gift record added successfully!";
            break;
        case 'specified_gift':
            $message = "Specified gift record added successfully!";
            break;
    }
}

// Add this after the session_start() and before the user profile check
class FinancialPredictor {
    private $historicalData;
    private $alpha = 0.3; // Smoothing factor
    private $seasonalPeriod = 12; // Monthly seasonal pattern

    public function __construct($historicalData) {
        $this->historicalData = $historicalData;
    }

    public function calculateExponentialSmoothing() {
        if (empty($this->historicalData)) {
            return [];
        }

        $smoothed = [];
        $firstValue = reset($this->historicalData);
        $smoothed[key($this->historicalData)] = $firstValue;

        foreach ($this->historicalData as $date => $value) {
            if ($date === key($this->historicalData)) continue;
            $prevDate = array_key_last($smoothed);
            $smoothed[$date] = $this->alpha * $value + (1 - $this->alpha) * $smoothed[$prevDate];
        }

        return $smoothed;
    }

    public function detectSeasonality() {
        if (empty($this->historicalData)) {
            return array_fill(0, $this->seasonalPeriod, 1);
        }

        $values = array_values($this->historicalData);
        $n = count($values);
        if ($n < $this->seasonalPeriod * 2) {
            return array_fill(0, $this->seasonalPeriod, 1);
        }

        $seasonalFactors = array_fill(0, $this->seasonalPeriod, 0);
        $seasonalCounts = array_fill(0, $this->seasonalPeriod, 0);

        for ($i = 0; $i < $n; $i++) {
            $seasonalIndex = $i % $this->seasonalPeriod;
            $seasonalFactors[$seasonalIndex] += $values[$i];
            $seasonalCounts[$seasonalIndex]++;
        }

        for ($i = 0; $i < $this->seasonalPeriod; $i++) {
            if ($seasonalCounts[$i] > 0) {
                $seasonalFactors[$i] /= $seasonalCounts[$i];
            } else {
                $seasonalFactors[$i] = 1;
            }
        }

        return $seasonalFactors;
    }

    public function predictNextMonth() {
        if (empty($this->historicalData)) {
            return [
                'prediction' => 0,
                'lowerBound' => 0,
                'upperBound' => 0,
                'confidence' => 60
            ];
        }

        $smoothed = $this->calculateExponentialSmoothing();
        if (empty($smoothed)) {
            return [
                'prediction' => 0,
                'lowerBound' => 0,
                'upperBound' => 0,
                'confidence' => 60
            ];
        }

        $seasonalFactors = $this->detectSeasonality();
        
        $lastDate = array_key_last($smoothed);
        $lastValue = end($smoothed);
        
        // Calculate trend
        $trend = 0;
        if (count($smoothed) > 1) {
            $firstValue = reset($smoothed);
            $trend = ($lastValue - $firstValue) / (count($smoothed) - 1);
        }

        // Predict next value with seasonality
        $nextMonthIndex = (count($smoothed) % $this->seasonalPeriod);
        $seasonalFactor = $seasonalFactors[$nextMonthIndex] ?? 1;
        
        $prediction = $lastValue + $trend;
        $prediction *= $seasonalFactor;

        // Calculate confidence interval
        $stdDev = $this->calculateStandardDeviation();
        $confidenceInterval = 1.96 * $stdDev; // 95% confidence interval

        // Ensure prediction is not negative
        $prediction = max(0, $prediction);

        return [
            'prediction' => $prediction,
            'lowerBound' => max(0, $prediction - $confidenceInterval),
            'upperBound' => $prediction + $confidenceInterval,
            'confidence' => min(95, max(60, 100 - ($stdDev / max(0.01, $prediction) * 100)))
        ];
    }

    private function calculateStandardDeviation() {
        if (empty($this->historicalData)) {
            return 0;
        }

        $values = array_values($this->historicalData);
        $n = count($values);
        
        if ($n <= 1) {
            return 0;
        }

        $mean = array_sum($values) / $n;
        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return sqrt(array_sum($squaredDiffs) / $n);
    }
}

// Add this after the FinancialPredictor class
function getProphetPrediction($historical_data) {
    global $conn; // Use the global database connection
    
    // Format data for Prophet
    $prophet_data = [];
    
    // Get tithes and offerings data
    $sql = "SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        SUM(CASE WHEN source = 'tithes' THEN amount ELSE 0 END) as tithes_amount,
        SUM(CASE WHEN source = 'offerings' THEN amount ELSE 0 END) as offerings_amount
    FROM (
        SELECT date, amount, 'tithes' as source FROM tithes
        UNION ALL
        SELECT date, amount, 'offerings' as source FROM offerings
    ) combined
    WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $prophet_data[] = [
            'ds' => $row['month'] . '-01', // Add day to make it a full date
            'y' => floatval($row['tithes_amount'] + $row['offerings_amount'])
        ];
    }

    // Make API call to Python endpoint
    $ch = curl_init('http://localhost:5000/predict');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => $prophet_data]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $predictions = json_decode($response, true);
        if (is_array($predictions) && !empty($predictions)) {
            // Log the predictions for debugging
            error_log("Prophet predictions: " . print_r($predictions, true));
            return $predictions;
        }
    }

    // Log the error if prediction failed
    error_log("Prophet prediction failed. HTTP code: " . $http_code);
    error_log("Response: " . $response);
    
    // Fallback to simple prediction if API fails
    return null;
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
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            border: 1px solid #ddd;
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
            gap: 5px;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 12px;
            cursor: pointer;
            transition: transform 0.2s;
            border: 1px solid #000;
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom,
                rgba(255, 255, 255, 0.2) 0%,
                rgba(255, 255, 255, 0) 50%,
                rgba(0, 0, 0, 0.1) 50%,
                rgba(0, 0, 0, 0) 100%
            );
            transform: rotate(45deg);
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .view-btn {
            background-color: var(--accent-color);
        }

        .edit-btn {
            background-color: var(--info-color);
        }

        .delete-btn {
            background-color: var(--danger-color);
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
                <li><a href="member_contributions.php" class="<?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-dollar"></i> <span>Member Contributions</span></a></li>
                <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
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
                <a href="#tithes" class="active" data-tab="tithes">Tithes</a>
                <a href="#offerings" data-tab="offerings">Offerings</a>
                <a href="#bank-gifts" data-tab="bank-gifts">Bank Gifts</a>
                <a href="#specified-gifts" data-tab="specified-gifts">Specified Gifts</a>
                <a href="#summary" data-tab="summary">Summary</a>
            </div>

            <div class="tab-content">
                <!-- Tithes Tab -->
                <div class="tab-pane active" id="tithes">
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
                                        <td><?php echo $record['date']; ?></td>
                                        <td><?php echo number_format($record['amount'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view-btn" data-id="<?php echo $record['id']; ?>" data-type="tithes">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn edit-btn" data-id="<?php echo $record['id']; ?>" data-type="tithes">
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
                                        <td><?php echo $record['date']; ?></td>
                                        <td><?php echo number_format($record['amount'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view-btn" data-id="<?php echo $record['id']; ?>" data-type="offerings">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn edit-btn" data-id="<?php echo $record['id']; ?>" data-type="offerings">
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
                                        <td><?php echo $record['date']; ?></td>
                                        <td><?php echo $record['date_deposited']; ?></td>
                                        <td><?php echo $record['date_updated']; ?></td>
                                        <td><?php echo number_format($record['amount'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view-btn" data-id="<?php echo $record['id']; ?>" data-type="bank-gifts">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn edit-btn" data-id="<?php echo $record['id']; ?>" data-type="bank-gifts">
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
                                        <td><?php echo $record['date']; ?></td>
                                        <td><?php echo htmlspecialchars($record['category']); ?></td>
                                        <td><?php echo number_format($record['amount'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view-btn" data-id="<?php echo $record['id']; ?>" data-type="specified-gifts">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn edit-btn" data-id="<?php echo $record['id']; ?>" data-type="specified-gifts">
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
                            <div class="summary-card">
                                <h3>Monthly Income Prediction</h3>
                                <div class="prediction-chart">
                                    <canvas id="predictionChart"></canvas>
                                </div>
                                <div class="prediction-details">
                                    <div class="prediction-metric">
                                        <span class="label">Predicted Monthly Income:</span>
                                        <span class="value" id="predictedIncome">0.00</span>
                                    </div>
                                    <div class="prediction-metric">
                                        <span class="label">Confidence Level:</span>
                                        <span class="value" id="confidenceLevel">0%</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <h3>Historical Trends</h3>
                                <div class="trend-chart">
                                    <canvas id="trendChart"></canvas>
                                </div>
                            </div>

                            <div class="summary-card">
                                <h3>Key Metrics</h3>
                                <div class="metrics-grid">
                                    <div class="metric-item">
                                        <span class="metric-label">Average Weekly Tithes</span>
                                        <span class="metric-value" id="avgTithes">0.00</span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label">Average Weekly Offerings</span>
                                        <span class="metric-value" id="avgOfferings">0.00</span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label">Total Monthly Income</span>
                                        <span class="metric-value" id="totalMonthly">0.00</span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label">Growth Rate</span>
                                        <span class="metric-value" id="growthRate">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="tithes-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Tithes</h3>
            <span class="close">&times;</span>
        </div>
        <form id="tithes-form" method="POST" action="">
            <div class="form-group">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="amount">Amount:</label>
                <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_tithes" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<div id="offerings-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Offering</h3>
            <span class="close">&times;</span>
        </div>
        <form id="offerings-form" method="POST" action="">
            <div class="form-group">
                <label for="offering_date">Date:</label>
                <input type="date" id="offering_date" name="date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="offering_amount">Amount:</label>
                <input type="number" id="offering_amount" name="amount" class="form-control" step="0.01" min="0" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_offering" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<div id="bank-gifts-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Bank Gift</h3>
            <span class="close">&times;</span>
        </div>
        <form id="bank-gifts-form" method="POST" action="">
            <div class="form-group">
                <label for="bank_date">Date:</label>
                <input type="date" id="bank_date" name="date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="date_deposited">Date Deposited:</label>
                <input type="date" id="date_deposited" name="date_deposited" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="date_updated">Date Updated in COCD's Passbook:</label>
                <input type="date" id="date_updated" name="date_updated" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="bank_amount">Amount:</label>
                <input type="number" id="bank_amount" name="amount" class="form-control" step="0.01" min="0" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_bank_gift" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<div id="specified-gifts-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Specified Gift</h3>
            <span class="close">&times;</span>
        </div>
        <form id="specified-gifts-form" method="POST" action="">
            <div class="form-group">
                <label for="specified_date">Date:</label>
                <input type="date" id="specified_date" name="date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="category">Category:</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">Select a category</option>
                    <?php foreach ($specified_gifts as $gift): ?>
                        <option value="<?php echo htmlspecialchars($gift); ?>"><?php echo htmlspecialchars($gift); ?></option>
                    <?php endforeach; ?>
                    <option value="other">Other (specify below)</option>
                </select>
            </div>
            <div class="form-group" id="other_category_group" style="display: none;">
                <label for="other_category">Specify Category:</label>
                <input type="text" id="other_category" name="other_category" class="form-control">
            </div>
            <div class="form-group">
                <label for="specified_amount">Amount:</label>
                <input type="number" id="specified_amount" name="amount" class="form-control" step="0.01" min="0" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_specified_gift" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Load Chart.js with fallback -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Initialize charts when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded');
        
        // Tab Navigation
        const tabLinks = document.querySelectorAll('.tab-navigation a');
        const tabPanes = document.querySelectorAll('.tab-pane');

        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                tabLinks.forEach(l => l.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                link.classList.add('active');
                const tabId = link.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');

                // Initialize charts when Summary tab is clicked
                if (tabId === 'summary') {
                    console.log('Summary tab clicked, updating predictions');
                    updatePredictions();
                }
            });
        });

        // Initialize charts if Summary tab is active
        if (document.querySelector('.tab-navigation a.active').getAttribute('data-tab') === 'summary') {
            console.log('Summary tab is active, initializing charts');
            updatePredictions();
        }
    });

    // Chart update function
    function updatePredictions() {
        try {
            console.log('Updating predictions...');
            
            // Update metrics with proper formatting
            document.getElementById('avgTithes').textContent = (avgWeeklyTithes || 0).toFixed(2);
            document.getElementById('avgOfferings').textContent = (avgWeeklyOfferings || 0).toFixed(2);
            document.getElementById('predictedIncome').textContent = (predictedMonthly || 0).toFixed(2);
            document.getElementById('confidenceLevel').textContent = (confidenceLevel || 0) + '%';
            document.getElementById('totalMonthly').textContent = (monthlyTotals[Object.keys(monthlyTotals)[0]] || 0).toFixed(2);
            document.getElementById('growthRate').textContent = (growthRate || 0).toFixed(2) + '%';

            // Create new charts
            createPredictionChart();
            createTrendChart();

            console.log('Charts updated successfully');
        } catch (error) {
            console.error('Error updating predictions:', error);
        }
    }

    function createPredictionChart() {
        const predictionCtx = document.getElementById('predictionChart');
        if (!predictionCtx) {
            console.error('Prediction chart canvas not found');
            return;
        }

        try {
            // Destroy existing chart if it exists
            if (window.predictionChart) {
                window.predictionChart.destroy();
            }

            // Prepare data
            const labels = Object.keys(historicalData).map(date => {
                const [year, month] = date.split('-');
                return new Date(year, month - 1).toLocaleString('default', { month: 'short' });
            });

            // Add next month for prediction
            const lastDate = new Date(Object.keys(historicalData).pop());
            lastDate.setMonth(lastDate.getMonth() + 1);
            labels.push(lastDate.toLocaleString('default', { month: 'short' }));

            const historicalValues = Object.values(historicalData);
            const predictionData = [...Array(historicalValues.length).fill(null), predictedMonthly];
            const upperBoundData = [...Array(historicalValues.length).fill(null), predictionUpper];
            const lowerBoundData = [...Array(historicalValues.length).fill(null), predictionLower];

            // Create new chart
            window.predictionChart = new Chart(predictionCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Historical Income',
                        data: historicalValues,
                        borderColor: 'rgb(0, 139, 30)',
                        tension: 0.1,
                        fill: false
                    }, {
                        label: 'Predicted Income',
                        data: predictionData,
                        borderColor: 'rgb(255, 99, 132)',
                        borderDash: [5, 5],
                        tension: 0.1,
                        fill: false
                    }, {
                        label: 'Confidence Interval',
                        data: upperBoundData,
                        borderColor: 'rgba(255, 99, 132, 0.2)',
                        borderDash: [2, 2],
                        fill: '+1',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)'
                    }, {
                        label: 'Lower Bound',
                        data: lowerBoundData,
                        borderColor: 'rgba(255, 99, 132, 0.2)',
                        borderDash: [2, 2],
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Income History and Prediction'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.raw || 0).toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
            console.log('Prediction chart created successfully');
        } catch (error) {
            console.error('Error creating prediction chart:', error);
        }
    }

    function createTrendChart() {
        const trendCtx = document.getElementById('trendChart');
        if (!trendCtx) {
            console.error('Trend chart canvas not found');
            return;
        }

        try {
            // Destroy existing chart if it exists
            if (window.trendChart) {
                window.trendChart.destroy();
            }

            // Create new chart
            window.trendChart = new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: ['Tithes', 'Offerings', 'Bank Gifts', 'Specified Gifts'],
                    datasets: [{
                        label: 'Average Weekly Amount',
                        data: [
                            avgWeeklyTithes || 0,
                            avgWeeklyOfferings || 0,
                            avgWeeklyBankGifts || 0,
                            avgWeeklySpecifiedGifts || 0
                        ],
                        backgroundColor: [
                            'rgba(0, 139, 30, 0.7)',
                            'rgba(0, 112, 9, 0.7)',
                            'rgba(0, 85, 0, 0.7)',
                            'rgba(0, 60, 0, 0.7)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Average Weekly Income by Source'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.raw || 0).toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
            console.log('Trend chart created successfully');
        } catch (error) {
            console.error('Error creating trend chart:', error);
        }
    }

    // Modal Functions
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = "block";
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = "none";
        }
    }

    // Add click handlers for all modal open buttons
    document.getElementById('add-tithes-btn').addEventListener('click', function() {
        openModal('tithes-modal');
    });

    document.getElementById('add-offering-btn').addEventListener('click', function() {
        openModal('offerings-modal');
    });

    document.getElementById('add-bank-gift-btn').addEventListener('click', function() {
        openModal('bank-gifts-modal');
    });

    document.getElementById('add-specified-gift-btn').addEventListener('click', function() {
        openModal('specified-gifts-modal');
    });

    // Close modal when clicking the X
    document.querySelectorAll('.close').forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    });

    // Handle category selection for specified gifts
    const categorySelect = document.getElementById('category');
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const otherGroup = document.getElementById('other_category_group');
            if (otherGroup) {
                if (this.value === 'other') {
                    otherGroup.style.display = 'block';
                    document.getElementById('other_category').required = true;
                } else {
                    otherGroup.style.display = 'none';
                    document.getElementById('other_category').required = false;
                }
            }
        });
    }

    // Handle specified gifts form submission
    const specifiedGiftsForm = document.getElementById('specified-gifts-form');
    if (specifiedGiftsForm) {
        specifiedGiftsForm.addEventListener('submit', function(e) {
            const category = document.getElementById('category');
            if (category && category.value === 'other') {
                const otherCategory = document.getElementById('other_category');
                if (otherCategory && otherCategory.value.trim() === '') {
                    e.preventDefault();
                    alert('Please specify the category');
                    return;
                }
                category.value = otherCategory.value;
            }
        });
    }

    // Add delete functionality
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this record?')) {
                const id = this.getAttribute('data-id');
                const type = this.getAttribute('data-type');
                
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'record_id';
                idInput.value = id;
                
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'record_type';
                typeInput.value = type;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'delete_record';
                actionInput.value = '1';
                
                form.appendChild(idInput);
                form.appendChild(typeInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Add view functionality
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            // Implement view functionality here
            console.log('View record:', id, type);
        });
    });

    // Add edit functionality
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            // Implement edit functionality here
            console.log('Edit record:', id, type);
        });
    });

    // Auto-hide success messages after 3 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 3000);
    });

    // Handle category selection for specified gifts
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('category');
        const otherGroup = document.getElementById('other_category_group');
        const otherCategory = document.getElementById('other_category');

        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherGroup.style.display = 'block';
                    otherCategory.required = true;
                } else {
                    otherGroup.style.display = 'none';
                    otherCategory.required = false;
                }
            });
        }

        // Handle specified gifts form submission
        const specifiedGiftsForm = document.getElementById('specified-gifts-form');
        if (specifiedGiftsForm) {
            specifiedGiftsForm.addEventListener('submit', function(e) {
                const category = document.getElementById('category');
                if (category && category.value === 'other') {
                    const otherCategory = document.getElementById('other_category');
                    if (otherCategory && otherCategory.value.trim() === '') {
                        e.preventDefault();
                        alert('Please specify the category');
                        return;
                    }
                    // Set the category value to the other category input value
                    category.value = otherCategory.value;
                }
            });
        }
    });
</script>
</body>
</html>