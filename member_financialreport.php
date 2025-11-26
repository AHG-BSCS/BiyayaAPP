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
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
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

// Load income breakdown entries for financial report tabs
$breakdownIncomeEntries = [];
$tithes_records = [];
$offerings_records = [];
$bank_gifts_records = [];
$specified_gifts_records = [];

$incomeBreakdownResult = $conn->query("
    SELECT id, entry_date, tithes, offerings, gifts_bank, others, notes
    FROM breakdown_income
    ORDER BY entry_date DESC, id DESC
");

if ($incomeBreakdownResult) {
    while ($row = $incomeBreakdownResult->fetch_assoc()) {
        $entryDate = $row['entry_date'];
        $notes = $row['notes'] ?? '';

        $row['tithes'] = isset($row['tithes']) ? floatval($row['tithes']) : 0;
        $row['offerings'] = isset($row['offerings']) ? floatval($row['offerings']) : 0;
        $row['gifts_bank'] = isset($row['gifts_bank']) ? floatval($row['gifts_bank']) : 0;
        $row['others'] = isset($row['others']) ? floatval($row['others']) : 0;

        $breakdownIncomeEntries[] = $row;

        if ($row['tithes'] > 0) {
            $tithes_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'amount' => $row['tithes'],
                'notes' => $notes
            ];
        }

        if ($row['offerings'] > 0) {
            $offerings_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'amount' => $row['offerings'],
                'notes' => $notes
            ];
        }

        if ($row['gifts_bank'] > 0) {
            $bank_gifts_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'amount' => $row['gifts_bank'],
                'notes' => $notes
            ];
        }

        if ($row['others'] > 0) {
            $specified_gifts_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'category' => 'Others',
                'amount' => $row['others'],
                'notes' => $notes
            ];
        }
    }
}

// Fallback to legacy tables if no breakdown data exists
if (empty($breakdownIncomeEntries)) {
    $sql = "SELECT * FROM tithes ORDER BY id ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tithes_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $row['date'],
                'amount' => floatval($row['amount']),
                'notes' => $row['notes'] ?? ''
            ];
        }
    }

    $sql = "SELECT * FROM offerings ORDER BY id ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $offerings_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $row['date'],
                'amount' => floatval($row['amount']),
                'notes' => $row['notes'] ?? ''
            ];
        }
    }

    $sql = "SELECT * FROM bank_gifts ORDER BY id ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bank_gifts_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $row['date'],
                'amount' => floatval($row['amount']),
                'notes' => $row['notes'] ?? ''
            ];
        }
    }

    $sql = "SELECT * FROM specified_gifts ORDER BY id ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $specified_gifts_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $row['date'],
                'category' => $row['category'] ?? 'Others',
                'amount' => floatval($row['amount']),
                'notes' => $row['notes'] ?? ''
            ];
        }
    }
}

// Fetch weekly summary for tithes and offerings
$weekly_reports = [];
$weeklyReportsMap = [];

if (!empty($breakdownIncomeEntries)) {
    foreach ($breakdownIncomeEntries as $entry) {
        $entryDate = $entry['entry_date'] ?? null;
        if (!$entryDate) {
            continue;
        }

        $tithesAmount = isset($entry['tithes']) ? floatval($entry['tithes']) : 0;
        $offeringsAmount = isset($entry['offerings']) ? floatval($entry['offerings']) : 0;

        if ($tithesAmount === 0 && $offeringsAmount === 0) {
            continue;
        }

        $date = new DateTime($entryDate);
        $isoYear = (int) $date->format('o');
        $isoWeek = (int) $date->format('W');

        $weekKey = sprintf('%d-W%02d', $isoYear, $isoWeek);
        $weekSortKey = sprintf('%04d%02d', $isoYear, $isoWeek);

        $weekStart = new DateTime();
        $weekStart->setISODate($isoYear, $isoWeek);
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');

        if (!isset($weeklyReportsMap[$weekKey])) {
            $weeklyReportsMap[$weekKey] = [
                'week' => $weekKey,
                'week_sort' => $weekSortKey,
                'start_date' => $weekStart->format('Y-m-d'),
                'end_date' => $weekEnd->format('Y-m-d'),
                'total_tithes' => 0,
                'total_offerings' => 0,
                'grand_total' => 0
            ];
        }

        $weeklyReportsMap[$weekKey]['total_tithes'] += $tithesAmount;
        $weeklyReportsMap[$weekKey]['total_offerings'] += $offeringsAmount;
    }

    if (!empty($weeklyReportsMap)) {
        $weekly_reports = array_values($weeklyReportsMap);
        usort($weekly_reports, function ($a, $b) {
            return strcmp($b['week_sort'], $a['week_sort']);
        });
        array_walk($weekly_reports, function (&$week) {
            $week['grand_total'] = $week['total_tithes'] + $week['total_offerings'];
            unset($week['week_sort']);
        });
    }
}

if (empty($weekly_reports)) {
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
}

// Fetch breakdown income and expense entries for Financial Breakdown tab and Monthly Expenses
$breakdownExpenseEntries = [];
$breakdownIncomeFullEntries = [];

// Fetch full breakdown income entries (with all fields)
$incomeFullResult = $conn->query("
    SELECT id, entry_date, tithes, offerings, gifts_bank, bank_interest, others, building, notes, created_by, created_at, updated_at
    FROM breakdown_income
    ORDER BY entry_date DESC, id DESC
");

if ($incomeFullResult) {
    while ($row = $incomeFullResult->fetch_assoc()) {
        $row['tithes'] = floatval($row['tithes'] ?? 0);
        $row['offerings'] = floatval($row['offerings'] ?? 0);
        $row['gifts_bank'] = floatval($row['gifts_bank'] ?? 0);
        $row['bank_interest'] = floatval($row['bank_interest'] ?? 0);
        $row['others'] = floatval($row['others'] ?? 0);
        $row['building'] = floatval($row['building'] ?? 0);
        $row['total_amount'] = $row['tithes'] + $row['offerings'] + $row['gifts_bank'] + $row['bank_interest'] + $row['others'] + $row['building'];
        $breakdownIncomeFullEntries[] = $row;
    }
}

// Fetch breakdown expense entries
$expenseFullResult = $conn->query("
    SELECT id, entry_date, speaker, workers, food, housekeeping, office_supplies, transportation, photocopy, internet, 
           government_concern, water_bill, electric_bill, special_events, needy_calamity, trainings,
           kids_ministry, youth_ministry, music_ministry, single_professionals_ministry, young_couples_ministry,
           wow_ministry, amen_ministry, couples_ministry, visitation_prayer_ministry, acquisitions, materials,
           labor, mission_support, land_title, notes, created_by, created_at, updated_at, total_amount
    FROM breakdown_expenses
    ORDER BY entry_date DESC, id DESC
");

if ($expenseFullResult) {
    while ($row = $expenseFullResult->fetch_assoc()) {
        $row['total_amount'] = isset($row['total_amount']) ? floatval($row['total_amount']) : 0;
        $breakdownExpenseEntries[] = $row;
    }
}

// Pagination for breakdown entries: Show 6 entries (cards) at a time
$breakdownPage = isset($_GET['breakdown_page']) ? max(0, intval($_GET['breakdown_page'])) : 0;
$entriesPerPage = 6;

// Entries are already sorted by date DESC, so we can paginate directly
$totalIncomeEntries = count($breakdownIncomeFullEntries);
$totalExpenseEntries = count($breakdownExpenseEntries);

// Calculate pagination for income entries
$incomeTotalPages = max(1, ceil($totalIncomeEntries / $entriesPerPage));
$incomeStartIndex = $breakdownPage * $entriesPerPage;
$incomeEndIndex = min($incomeStartIndex + $entriesPerPage, $totalIncomeEntries);
$displayIncomeEntries = array_slice($breakdownIncomeFullEntries, $incomeStartIndex, $entriesPerPage);

// Calculate pagination for expense entries
$expenseTotalPages = max(1, ceil($totalExpenseEntries / $entriesPerPage));
$expenseStartIndex = $breakdownPage * $entriesPerPage;
$expenseEndIndex = min($expenseStartIndex + $entriesPerPage, $totalExpenseEntries);
$displayExpenseEntries = array_slice($breakdownExpenseEntries, $expenseStartIndex, $entriesPerPage);

// Use the maximum pages for navigation (so both tabs can navigate)
$totalPages = max($incomeTotalPages, $expenseTotalPages);

// Ensure page is within valid range
if ($breakdownPage >= $totalPages) {
    $breakdownPage = $totalPages - 1;
}
if ($breakdownPage < 0) {
    $breakdownPage = 0;
}

// Store pagination info for use in HTML
$breakdownPagination = [
    'current_page' => $breakdownPage,
    'total_pages' => $totalPages,
    'total_income_entries' => $totalIncomeEntries,
    'total_expense_entries' => $totalExpenseEntries,
    'entries_per_page' => $entriesPerPage,
    'has_previous' => $breakdownPage > 0,
    'has_next' => $breakdownPage < $totalPages - 1
];

// Calculate monthly expenses from breakdown data
$monthlySummary = [];
foreach ($breakdownIncomeFullEntries as $entry) {
    $monthKey = date('Y-m', strtotime($entry['entry_date']));
    if (!isset($monthlySummary[$monthKey])) {
        $monthlySummary[$monthKey] = ['income' => 0, 'expenses' => 0];
    }
    $monthlySummary[$monthKey]['income'] += $entry['total_amount'];
}

foreach ($breakdownExpenseEntries as $entry) {
    $monthKey = date('Y-m', strtotime($entry['entry_date']));
    if (!isset($monthlySummary[$monthKey])) {
        $monthlySummary[$monthKey] = ['income' => 0, 'expenses' => 0];
    }
    $monthlySummary[$monthKey]['expenses'] += $entry['total_amount'];
}

$monthly_expenses_records = [];
$expenses_totals = [
    'total_income' => 0,
    'total_expenses' => 0,
    'total_difference' => 0,
    'count' => 0
];

if (!empty($monthlySummary)) {
    ksort($monthlySummary);
    foreach ($monthlySummary as $monthKey => $values) {
        $income = floatval($values['income'] ?? 0);
        $expenses = floatval($values['expenses'] ?? 0);
        $difference = $income - $expenses;
        
        $monthly_expenses_records[] = [
            'month' => $monthKey,
            'income' => $income,
            'expenses' => $expenses,
            'difference' => $difference
        ];
        
        $expenses_totals['total_income'] += $income;
        $expenses_totals['total_expenses'] += $expenses;
        $expenses_totals['total_difference'] += $difference;
        $expenses_totals['count']++;
    }
}

// Calculate averages for monthly expenses
$expenses_averages = [
    'avg_income' => $expenses_totals['count'] > 0 ? $expenses_totals['total_income'] / $expenses_totals['count'] : 0,
    'avg_expenses' => $expenses_totals['count'] > 0 ? $expenses_totals['total_expenses'] / $expenses_totals['count'] : 0,
    'avg_difference' => $expenses_totals['count'] > 0 ? $expenses_totals['total_difference'] / $expenses_totals['count'] : 0
];

// --- BEGIN: Copy calculations for averages, predictions, and chart data from financialreport.php ---
// Calculate average weekly amounts for tithes and offerings only (using ALL data)
$sql = "
    WITH weekly_totals AS (
        SELECT 
            DATE_FORMAT(entry_date, '%Y-%U') as week,
            'tithes' as type,
            SUM(tithes) as total
        FROM breakdown_income 
        GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(entry_date, '%Y-%U') as week,
            'offerings' as type,
            SUM(offerings) as total
        FROM breakdown_income 
        GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
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
            DATE_FORMAT(entry_date, '%Y-%U') as week,
            'tithes' as type,
            SUM(tithes) as total
        FROM breakdown_income 
        GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(entry_date, '%Y-%U') as week,
            'offerings' as type,
            SUM(offerings) as total
        FROM breakdown_income 
        GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(entry_date, '%Y-%U') as week,
            'bank_gifts' as type,
            SUM(gifts_bank) as total
        FROM breakdown_income 
        GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
        UNION ALL
        SELECT 
            DATE_FORMAT(entry_date, '%Y-%U') as week,
            'specified_gifts' as type,
            SUM(others) as total
        FROM breakdown_income 
        GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
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
    SELECT 
        DATE_FORMAT(entry_date, '%Y-%m') as month,
        SUM(tithes + offerings) as total
    FROM breakdown_income
    GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
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
    $minimumRequiredMonths = 6;
    $sql = "
        SELECT 
            DATE_FORMAT(entry_date, '%Y-%m') as month,
            SUM(tithes + offerings) as total
        FROM breakdown_income
        GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
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
    
    if (count($prophet_data) < $minimumRequiredMonths) {
        return null;
    }
    $ch = curl_init('https://biyaya-prediction.onrender.com/predict');
    $ch = curl_init('https://biyaya-prediction.onrender.com/predict');
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
    SELECT DATE_FORMAT(entry_date, '%Y-%m') as month, SUM(tithes + offerings) as total
    FROM breakdown_income
    WHERE YEAR(entry_date) = 2025
    GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
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
        /* Drawer Navigation Styles (copied from superadmin_dashboard.php) */
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
        .content-area {
            flex: 1;
            padding: 20px;
            margin-left: 0;
            padding-top: 80px;
        }
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
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
            .content-area {
                margin-left: 0;
                padding-top: 70px;
            }
            .tab-navigation {
                flex-wrap: wrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .tab-navigation a {
                padding: 12px 10px;
                font-size: 13px;
                min-width: auto;
                flex: 1 1 auto;
            }
            .tab-content {
                padding: 15px 10px;
            }
            .table-responsive {
                padding: 10px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            table {
                font-size: 14px;
                min-width: 600px;
            }
            #monthly-expenses-table {
                min-width: 800px;
            }
            table th, table td {
                padding: 8px 10px;
                font-size: 13px;
            }
            .top-bar {
                padding: 12px 15px;
            }
            .top-bar h2 {
                font-size: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .tab-navigation {
                flex-direction: column;
            }
            .tab-navigation a {
                width: 100%;
                padding: 12px;
                border-bottom: 1px solid #e0e0e0;
            }
            .tab-navigation a:last-child {
                border-bottom: none;
            }
            .table-responsive {
                padding: 5px;
            }
            table {
                font-size: 12px;
                min-width: 500px;
            }
            #monthly-expenses-table {
                min-width: 700px;
            }
            table th, table td {
                padding: 6px 8px;
                font-size: 12px;
            }
            .top-bar h2 {
                font-size: 18px;
            }
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
        /* --- Tab Navigation Styles (copied from financialreport.php) --- */
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
        .tab-navigation a[data-tab="financial-breakdown"] {
            font-size: 15px;
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
        .top-bar {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            background-color: #fff;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .top-bar h2 {
            font-size: 24px;
            font-weight: bold;
        }
        .prediction-chart, .trend-chart {
            height: 300px;
            margin-bottom: 20px;
            position: relative;
        }
        .prediction-chart canvas, .trend-chart canvas {
            width: 100% !important;
            height: 100% !important;
            max-height: 300px;
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
        .summary-card.full-width {
            grid-column: 1 / -1;
        }
        /* Financial Breakdown Tab Styles */
        .breakdown-tab-navigation {
            display: flex;
            background-color: #f8f9fa;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
            padding: 5px;
        }
        .breakdown-tab-navigation a {
            flex: 1;
            text-align: center;
            padding: 12px 20px;
            color: var(--primary-color);
            text-decoration: none;
            transition: background-color 0.3s;
            font-weight: 500;
            border-radius: 5px;
        }
        .breakdown-tab-navigation a.active {
            background-color: var(--accent-color);
            color: var(--white);
        }
        .breakdown-tab-navigation a:hover:not(.active) {
            background-color: #e9ecef;
        }
        .breakdown-tab-content {
            margin-top: 20px;
        }
        .breakdown-pane {
            display: none;
        }
        .breakdown-pane.active {
            display: block;
        }
        .expense-card-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .expense-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .expense-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .expense-card .card-header {
            background: linear-gradient(135deg, var(--accent-color) 0%, rgb(0, 112, 9) 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .expense-card .card-header .date {
            font-weight: 600;
            font-size: 16px;
        }
        .expense-card .card-body {
            padding: 20px;
        }
        .expense-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .expense-item:last-child {
            border-bottom: none;
        }
        .expense-item .label {
            color: #666;
            font-size: 14px;
            flex: 1;
        }
        .expense-item .value {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 14px;
            text-align: right;
        }
        .expense-card .card-footer {
            background: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .expense-card .card-footer .note {
            color: #666;
            font-size: 13px;
            flex: 1;
            min-width: 200px;
        }
        .expense-card .card-footer .total {
            color: var(--accent-color);
            font-weight: bold;
            font-size: 16px;
        }
        /* Monthly Expenses Tab Styles */
        .monthly-expenses-content { margin-top: 20px; }
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            -webkit-overflow-scrolling: touch;
            min-width: 0;
        }
        .table-responsive .dataTables_wrapper {
            width: 100%;
        }
        .table-responsive table.dataTable {
            width: 100% !important;
            table-layout: auto;
        }
        .table-responsive table.dataTable th,
        .table-responsive table.dataTable td {
            white-space: normal;
            word-break: break-word;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            min-width: 0;
        }
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            min-width: 0;
        }
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        table tr:hover {
            background-color: #f5f5f5;
        }
        .summary-row {
            background-color: #f8f9fa;
            font-weight: bold;
            border-top: 2px solid #dee2e6;
        }
        .summary-row td {
            color: #495057;
        }
        .positive-difference {
            color: var(--success-color);
        }
        .negative-difference {
            color: var(--danger-color);
        }
        .positive-income {
            color: var(--success-color);
            font-weight: bold;
        }
        .negative-expenses {
            color: var(--danger-color);
            font-weight: bold;
        }
        .month-column {
            font-weight: bold !important;
            text-align: left !important;
            vertical-align: middle !important;
            padding: 12px 15px !important;
            border-bottom: 1px solid #eee !important;
            border-right: 1px solid #ddd !important;
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
            border-left: 4px solid var(--accent-color);
        }
        .insight-card h3 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .insight-card h3 i {
            color: var(--accent-color);
        }
        .insight-metric {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .insight-metric:last-child {
            margin-bottom: 0;
        }
        .insight-metric .label {
            color: #666;
            font-weight: 500;
        }
        .insight-metric .value {
            font-weight: bold;
            color: var(--accent-color);
        }
        .insight-metric .value.positive {
            color: var(--success-color);
        }
        .insight-metric .value.negative {
            color: var(--danger-color);
        }

        /* Breakdown Pagination Styles */
        .breakdown-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 30px;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .breakdown-pagination .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            cursor: pointer;
        }

        .breakdown-pagination .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .breakdown-pagination .btn:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            background-color: rgb(0, 112, 9);
        }

        .breakdown-pagination .pagination-info {
            color: #666;
            font-size: 14px;
            font-weight: 500;
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
                    <div class="role"><?php echo htmlspecialchars($user_profile['role'] ?? 'Member'); ?></div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        <!-- Drawer Overlay -->
        <div id="drawer-overlay" class="drawer-overlay"></div>
        <main class="content-area">
<?php
$live_message = getLiveMessage($conn);
?>
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
                <h2>Financial Reports</h2>
                <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                    Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                </p>
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
                <a href="#financial-breakdown" data-tab="financial-breakdown">Financial Breakdown</a>
                <a href="#monthly-expenses" data-tab="monthly-expenses">Monthly Expenses</a>
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
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="tithes-tbody">
                                <?php foreach ($tithes_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $record['id']); ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
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
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="offerings-tbody">
                                <?php foreach ($offerings_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $record['id']); ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
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
                                    <th>Amount</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="bank-gifts-tbody">
                                <?php foreach ($bank_gifts_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $record['id']); ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
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
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="specified-gifts-tbody">
                                <?php foreach ($specified_gifts_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $record['id']); ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($record['category']); ?></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Financial Breakdown Tab -->
                <div class="tab-pane" id="financial-breakdown">
                    <div class="card">
                        <div class="breakdown-tab-navigation">
                            <a href="#breakdown-income" class="active" data-breakdown-tab="income">Income</a>
                            <a href="#breakdown-expenses" data-breakdown-tab="expenses">Expenses</a>
                        </div>

                        <div class="breakdown-tab-content">
                            <div class="breakdown-pane active" id="breakdown-income">
                                <?php if (!empty($displayIncomeEntries)): ?>
                                    <div class="expense-card-list">
                                        <?php foreach ($displayIncomeEntries as $incomeEntry): ?>
                                            <?php
                                                $incomeFields = [
                                                    'Tithes' => $incomeEntry['tithes'],
                                                    'Offerings' => $incomeEntry['offerings'],
                                                    'Gifts Received through Bank' => $incomeEntry['gifts_bank'],
                                                    'Bank Interest' => $incomeEntry['bank_interest'],
                                                    'Others' => $incomeEntry['others'],
                                                    'Building' => $incomeEntry['building']
                                                ];
                                            ?>
                                            <div class="expense-card">
                                                <div class="card-header">
                                                    <div class="date"><?php echo htmlspecialchars(date('F d, Y', strtotime($incomeEntry['entry_date']))); ?></div>
                                                </div>
                                                <div class="card-body">
                                                    <?php foreach ($incomeFields as $label => $value): ?>
                                                        <div class="expense-item">
                                                            <span class="label"><?php echo $label; ?></span>
                                                            <span class="value">₱<?php echo number_format((float) $value, 2); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="note">
                                                        <?php echo $incomeEntry['notes'] !== '' ? nl2br(htmlspecialchars($incomeEntry['notes'])) : 'No notes provided.'; ?>
                                                    </div>
                                                    <div class="total">Total: ₱<?php echo number_format($incomeEntry['total_amount'], 2); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (isset($breakdownPagination) && $breakdownPagination !== null && $breakdownPagination['total_pages'] > 1): ?>
                                        <div class="breakdown-pagination">
                                            <button type="button" class="btn" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] - 1; ?>)" <?php echo !$breakdownPagination['has_previous'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </button>
                                            <span class="pagination-info">
                                                Showing <?php echo count($displayIncomeEntries); ?> of <?php echo $breakdownPagination['total_income_entries']; ?> income entries 
                                                (Page <?php echo ($breakdownPagination['current_page'] + 1); ?> of <?php echo $breakdownPagination['total_pages']; ?>)
                                            </span>
                                            <button type="button" class="btn" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] + 1; ?>)" <?php echo !$breakdownPagination['has_next'] ? 'disabled' : ''; ?>>
                                                Next <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="text-align:center; color:#666; padding:25px; background:white; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.08);">
                                        No income breakdown entries found.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="breakdown-pane" id="breakdown-expenses">
                                <?php if (!empty($displayExpenseEntries)): ?>
                                    <div class="expense-card-list">
                                        <?php foreach ($displayExpenseEntries as $expenseEntry): ?>
                                            <?php
                                                $expenseFields = [
                                                    'Speaker' => $expenseEntry['speaker'] ?? 0,
                                                    'Workers' => $expenseEntry['workers'] ?? 0,
                                                    'Food' => $expenseEntry['food'] ?? 0,
                                                    'House Keeping' => $expenseEntry['housekeeping'] ?? 0,
                                                    'Office Supplies' => $expenseEntry['office_supplies'] ?? 0,
                                                    'Transportation' => $expenseEntry['transportation'] ?? 0,
                                                    'Photocopy' => $expenseEntry['photocopy'] ?? 0,
                                                    'Internet' => $expenseEntry['internet'] ?? 0,
                                                    'Government Concern' => $expenseEntry['government_concern'] ?? 0,
                                                    'Water Bill' => $expenseEntry['water_bill'] ?? 0,
                                                    'Electric Bill' => $expenseEntry['electric_bill'] ?? 0,
                                                    'Special Events' => $expenseEntry['special_events'] ?? 0,
                                                    'Needy / Calamity / Emergency' => $expenseEntry['needy_calamity'] ?? 0,
                                                    'Trainings' => $expenseEntry['trainings'] ?? 0,
                                                    'Kids Ministry' => $expenseEntry['kids_ministry'] ?? 0,
                                                    'Youth Ministry' => $expenseEntry['youth_ministry'] ?? 0,
                                                    'Music Ministry' => $expenseEntry['music_ministry'] ?? 0,
                                                    'Single Professionals Ministry' => $expenseEntry['single_professionals_ministry'] ?? 0,
                                                    'Young Couples Ministry' => $expenseEntry['young_couples_ministry'] ?? 0,
                                                    'WOW Ministry' => $expenseEntry['wow_ministry'] ?? 0,
                                                    'AMEN Ministry' => $expenseEntry['amen_ministry'] ?? 0,
                                                    'Couples Ministry' => $expenseEntry['couples_ministry'] ?? 0,
                                                    'Visitation / Prayer Ministry' => $expenseEntry['visitation_prayer_ministry'] ?? 0,
                                                    'Acquisitions' => $expenseEntry['acquisitions'] ?? 0,
                                                    'Materials' => $expenseEntry['materials'] ?? 0,
                                                    'Labor' => $expenseEntry['labor'] ?? 0,
                                                    'Mission Support' => $expenseEntry['mission_support'] ?? 0,
                                                    'Land Title' => $expenseEntry['land_title'] ?? 0
                                                ];
                                            ?>
                                            <div class="expense-card">
                                                <div class="card-header">
                                                    <div class="date"><?php echo htmlspecialchars(date('F d, Y', strtotime($expenseEntry['entry_date']))); ?></div>
                                                </div>
                                                <div class="card-body">
                                                    <?php foreach ($expenseFields as $label => $value): ?>
                                                        <?php if (floatval($value) > 0): ?>
                                                            <div class="expense-item">
                                                                <span class="label"><?php echo $label; ?></span>
                                                                <span class="value">₱<?php echo number_format((float) $value, 2); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="note">
                                                        <?php echo $expenseEntry['notes'] !== '' ? nl2br(htmlspecialchars($expenseEntry['notes'])) : 'No notes provided.'; ?>
                                                    </div>
                                                    <div class="total">Total: ₱<?php echo number_format($expenseEntry['total_amount'], 2); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (isset($breakdownPagination) && $breakdownPagination !== null && $breakdownPagination['total_pages'] > 1): ?>
                                        <div class="breakdown-pagination">
                                            <button type="button" class="btn" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] - 1; ?>)" <?php echo !$breakdownPagination['has_previous'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </button>
                                            <span class="pagination-info">
                                                Showing <?php echo count($displayExpenseEntries); ?> of <?php echo $breakdownPagination['total_expense_entries']; ?> expense entries 
                                                (Page <?php echo ($breakdownPagination['current_page'] + 1); ?> of <?php echo $breakdownPagination['total_pages']; ?>)
                                            </span>
                                            <button type="button" class="btn" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] + 1; ?>)" <?php echo !$breakdownPagination['has_next'] ? 'disabled' : ''; ?>>
                                                Next <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="text-align:center; color:#666; padding:25px; background:white; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.08);">
                                        No expense breakdown entries found.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Expenses Tab -->
                <div class="tab-pane" id="monthly-expenses">
                    <div class="monthly-expenses-content">
                        <!-- Monthly Expenses Table -->
                        <div class="table-responsive">
                            <table id="monthly-expenses-table">
                                <thead>
                                    <tr>
                                        <th class="month-column">Month</th>
                                        <th>Income (₱)</th>
                                        <th>Expenses (₱)</th>
                                        <th>Difference (₱)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($monthly_expenses_records)): ?>
                                        <?php foreach ($monthly_expenses_records as $row): ?>
                                        <tr>
                                            <td class="month-column" data-order="<?php echo strtotime($row['month'] . '-01'); ?>">
                                                <strong><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></strong>
                                            </td>
                                            <td class="<?php echo $row['income'] > $row['expenses'] ? 'positive-income' : ''; ?>">
                                                ₱<?php echo number_format($row['income'], 2); ?>
                                            </td>
                                            <td class="<?php echo $row['expenses'] > $row['income'] ? 'negative-expenses' : ''; ?>">
                                                ₱<?php echo number_format($row['expenses'], 2); ?>
                                            </td>
                                            <td class="<?php echo $row['expenses'] > $row['income'] ? 'negative-expenses' : ($row['difference'] >= 0 ? 'positive-difference' : 'negative-difference'); ?>">
                                                ₱<?php echo number_format($row['difference'], 2); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center; color:#666; padding:20px;">
                                                No monthly expenses data available. Please add breakdown entries.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="summary-row">
                                        <td><strong>AVERAGE</strong></td>
                                        <td><strong>₱<?php echo number_format($expenses_averages['avg_income'], 2); ?></strong></td>
                                        <td><strong>₱<?php echo number_format($expenses_averages['avg_expenses'], 2); ?></strong></td>
                                        <td class="<?php echo $expenses_averages['avg_expenses'] > $expenses_averages['avg_income'] ? 'negative-expenses' : ($expenses_averages['avg_difference'] >= 0 ? 'positive-difference' : 'negative-difference'); ?>">
                                            <strong>₱<?php echo number_format($expenses_averages['avg_difference'], 2); ?></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Monthly Expenses Chart -->
                        <div class="insight-card" style="margin-top: 20px;">
                            <h3><i class="fas fa-chart-area"></i> Monthly Income vs Expenses Trend</h3>
                            <div class="max-w-sm w-full bg-white rounded-lg shadow-sm dark:bg-gray-800 p-4 md:p-6">
                                <div class="mb-4">
                                    <div>
                                        <h5 class="leading-none text-2xl font-bold text-gray-900 dark:text-white pb-1">₱<?php echo number_format($expenses_totals['total_income'], 2); ?></h5>
                                        <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Total Income</p>
                                    </div>
                                </div>
                                <div id="monthly-expenses-chart" style="margin-top: 20px; height: 300px; width: 100%;"></div>
                            </div>
                        </div>
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
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script>
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
            scrollX: true,
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
                        scrollX: true,
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

        // Breakdown tab functionality (Income/Expenses within Financial Breakdown)
        $('.breakdown-tab-navigation a').on('click', function(e) {
            e.preventDefault();
            $('.breakdown-tab-navigation a').removeClass('active');
            $('.breakdown-pane').removeClass('active');
            $(this).addClass('active');
            var breakdownTabId = $(this).data('breakdown-tab');
            $('#breakdown-' + breakdownTabId).addClass('active');
        });

        // Restore breakdown tab from URL parameter on page load
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const breakdownTabParam = urlParams.get('breakdown_tab');
            if (breakdownTabParam === 'expenses' || breakdownTabParam === 'income') {
                $('.breakdown-tab-navigation a').removeClass('active');
                $('.breakdown-pane').removeClass('active');
                
                const targetTab = $('.breakdown-tab-navigation a[data-breakdown-tab="' + breakdownTabParam + '"]');
                const targetPane = $('#breakdown-' + breakdownTabParam);
                
                if (targetTab.length && targetPane.length) {
                    targetTab.addClass('active');
                    targetPane.addClass('active');
                }
            }
        });

        // Breakdown pagination navigation
        window.navigateBreakdownPage = function(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('breakdown_page', page);
            
            // Detect which breakdown tab is currently active (income or expenses)
            const activeBreakdownTab = document.querySelector('.breakdown-tab-navigation a.active');
            const breakdownTab = activeBreakdownTab ? activeBreakdownTab.getAttribute('data-breakdown-tab') : 'income';
            
            // Preserve the breakdown tab in URL parameter
            url.searchParams.set('breakdown_tab', breakdownTab);
            
            // Preserve the hash (main tab) if it exists
            const hash = window.location.hash || '#financial-breakdown';
            window.location.href = url.toString() + hash;
        };

        // Monthly Expenses Table DataTables initialization
        $('#monthly-expenses-table').DataTable({
            responsive: true,
            paging: true,
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            searching: true,
            ordering: true,
            info: true,
            order: [[0, 'asc']], // Sort by first column (Month) in ascending order
            orderClasses: false,
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

        // Monthly Expenses Chart Configuration
        <?php
        // Prepare data for the monthly expenses chart
        $chart_income = [];
        $chart_expenses = [];
        $chart_categories = [];

        // Get the last 6 months of data for the chart
        $chart_data = array_slice($monthly_expenses_records, -6);

        foreach ($chart_data as $row) {
            $chart_income[] = $row['income'];
            $chart_expenses[] = $row['expenses'];
            $chart_categories[] = date('M Y', strtotime($row['month'] . '-01'));
        }
        ?>

        const monthlyExpensesOptions = {
            series: [
                {
                    name: "Income",
                    data: <?php echo json_encode($chart_income); ?>,
                    color: "#10B981", // Green color for income
                },
                {
                    name: "Expenses",
                    data: <?php echo json_encode($chart_expenses); ?>,
                    color: "#EF4444", // Red color for expenses
                },
            ],
            chart: {
                height: 300,
                width: "100%",
                type: "area",
                fontFamily: "Inter, sans-serif",
                dropShadow: {
                    enabled: false,
                },
                toolbar: {
                    show: false,
                },
            },
            tooltip: {
                enabled: true,
                x: {
                    show: false,
                },
            },
            legend: {
                show: true
            },
            fill: {
                type: "gradient",
                gradient: {
                    opacityFrom: 0.55,
                    opacityTo: 0,
                    shade: "#1C64F2",
                    gradientToColors: ["#1C64F2"],
                },
            },
            dataLabels: {
                enabled: false,
            },
            stroke: {
                width: 6,
            },
            grid: {
                show: false,
                strokeDashArray: 4,
                padding: {
                    left: 2,
                    right: 2,
                    top: -26
                },
            },
            xaxis: {
                categories: <?php echo json_encode($chart_categories); ?>,
                labels: {
                    show: true,
                    style: {
                        colors: '#666',
                        fontSize: '12px',
                        fontFamily: 'Inter, sans-serif',
                    },
                },
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
            },
            yaxis: {
                show: true,
                labels: {
                    show: true,
                    formatter: function (value) {
                        return '₱' + value.toLocaleString();
                    },
                    style: {
                        colors: '#666',
                        fontSize: '12px',
                        fontFamily: 'Inter, sans-serif',
                    }
                },
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
            },
        };

        // Initialize the monthly expenses chart
        if (document.getElementById("monthly-expenses-chart")) {
            const chartData = <?php echo json_encode($chart_income); ?>;
            const chartCategories = <?php echo json_encode($chart_categories); ?>;
            
            if (chartData.length === 0 || chartCategories.length === 0) {
                document.getElementById("monthly-expenses-chart").innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No monthly expenses data available. Please add some monthly expense records.</p>';
            } else if (typeof ApexCharts !== 'undefined') {
                const monthlyExpensesChart = new ApexCharts(document.getElementById("monthly-expenses-chart"), monthlyExpensesOptions);
                monthlyExpensesChart.render();
            } else {
                document.getElementById("monthly-expenses-chart").innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Chart library loading...</p>';
            }
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
// Drawer Navigation JS
    document.addEventListener('DOMContentLoaded', function() {
        const navToggle = document.getElementById('nav-toggle');
        const drawer = document.getElementById('drawer-navigation');
        const drawerClose = document.getElementById('drawer-close');
        const overlay = document.getElementById('drawer-overlay');

        // Open drawer
        navToggle.addEventListener('click', function() {
            drawer.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });

        // Close drawer
        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        drawerClose.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        // Close drawer on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDrawer();
            }
        });
    });
</script>
</body>
</html> 