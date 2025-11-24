<?php
// financialreport.php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in and is administrator only
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "Administrator") {
    header("Location: index.php");
    exit;
}
// Define $is_admin as true for use in the rest of the file
$is_admin = true;

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Get church logo
$church_logo = getChurchLogo($conn);

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
        $breakdownIncomeEntries[] = $row;
        $entryDate = $row['entry_date'];
        $notes = $row['notes'] ?? '';

        if (floatval($row['tithes']) > 0) {
            $tithes_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'amount' => (float) $row['tithes'],
                'notes' => $notes
            ];
        }

        if (floatval($row['offerings']) > 0) {
            $offerings_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'amount' => (float) $row['offerings'],
                'notes' => $notes
            ];
        }

        if (floatval($row['gifts_bank']) > 0) {
            $bank_gifts_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'amount' => (float) $row['gifts_bank'],
                'notes' => $notes
            ];
        }

        if (floatval($row['others']) > 0) {
            $specified_gifts_records[] = [
                'id' => (int) $row['id'],
                'entry_date' => $entryDate,
                'category' => 'Others',
                'amount' => (float) $row['others'],
                'notes' => $notes
            ];
        }
    }
}

$sortByDateDesc = function ($a, $b) {
    return strtotime($b['entry_date']) <=> strtotime($a['entry_date']);
};

usort($tithes_records, $sortByDateDesc);
usort($offerings_records, $sortByDateDesc);
usort($bank_gifts_records, $sortByDateDesc);
usort($specified_gifts_records, $sortByDateDesc);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Manual entry disabled: financial data now syncs from breakdown incomes.
}

// Calculate average weekly amounts for tithes and offerings only (using ALL data)
if (!function_exists('getProphetPredictionFromArray')) {
    function getProphetPredictionFromArray(array $prophet_data) {
        $minimumRequiredMonths = 6;
        if (count($prophet_data) < $minimumRequiredMonths) {
        return null;
    }

    $ch = curl_init('https://cocd-predict.onrender.com/predict');
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
                : (count($prophet_data) > 0 ? array_sum(array_column($prophet_data, 'y')) / count($prophet_data) : 0);
        
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

if (!empty($breakdownIncomeEntries)) {
    $weeklyTotals = [];
    $monthlyTotals = [];
    foreach ($breakdownIncomeEntries as $entry) {
        $entryDate = $entry['entry_date'] ?? null;
        if (!$entryDate) {
            continue;
        }

        $tithesAmount = floatval($entry['tithes'] ?? 0);
        $offeringsAmount = floatval($entry['offerings'] ?? 0);
        $bankGiftsAmount = floatval($entry['gifts_bank'] ?? 0);
        $specifiedGiftsAmount = floatval($entry['others'] ?? 0);

        $weekDate = new DateTime($entryDate);
        $isoYear = $weekDate->format('o');
        $isoWeek = $weekDate->format('W');
        $weekKey = sprintf('%s-W%s', $isoYear, $isoWeek);

        if (!isset($weeklyTotals[$weekKey])) {
            $weeklyTotals[$weekKey] = [
                'tithes' => 0,
                'offerings' => 0,
                'bank_gifts' => 0,
                'specified_gifts' => 0
            ];
        }

        $weeklyTotals[$weekKey]['tithes'] += $tithesAmount;
        $weeklyTotals[$weekKey]['offerings'] += $offeringsAmount;
        $weeklyTotals[$weekKey]['bank_gifts'] += $bankGiftsAmount;
        $weeklyTotals[$weekKey]['specified_gifts'] += $specifiedGiftsAmount;

        $monthKey = date('Y-m', strtotime($entryDate));
        if (!isset($monthlyTotals[$monthKey])) {
            $monthlyTotals[$monthKey] = 0;
        }
        $monthlyTotals[$monthKey] += $tithesAmount + $offeringsAmount;
    }

    $weekCount = count($weeklyTotals);
    if ($weekCount > 0) {
        $avg_weekly_tithes = array_sum(array_column($weeklyTotals, 'tithes')) / $weekCount;
        $avg_weekly_offerings = array_sum(array_column($weeklyTotals, 'offerings')) / $weekCount;
        $avg_weekly_bank_gifts = array_sum(array_column($weeklyTotals, 'bank_gifts')) / $weekCount;
        $avg_weekly_specified_gifts = array_sum(array_column($weeklyTotals, 'specified_gifts')) / $weekCount;
    } else {
        $avg_weekly_tithes = 0;
        $avg_weekly_offerings = 0;
        $avg_weekly_bank_gifts = 0;
        $avg_weekly_specified_gifts = 0;
    }

    ksort($monthlyTotals);
    $historical_data = $monthlyTotals;
    if (empty($historical_data)) {
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $historical_data[$month] = 0;
        }
    }

    $prophet_data = [];
    foreach ($historical_data as $month => $total) {
        $prophet_data[] = [
            'ds' => $month . '-01',
            'y' => floatval($total)
        ];
    }

    $prophet_predictions = getProphetPredictionFromArray($prophet_data);
if ($prophet_predictions) {
    $predicted_monthly = $prophet_predictions[0]['yhat'];
    $prediction_lower = $prophet_predictions[0]['yhat_lower'] ?? ($predicted_monthly * 0.9);
    $prediction_upper = $prophet_predictions[0]['yhat_upper'] ?? ($predicted_monthly * 1.1);
} else {
    $predicted_monthly = ($avg_weekly_tithes + $avg_weekly_offerings + $avg_weekly_bank_gifts + $avg_weekly_specified_gifts) * 4;
    $prediction_lower = $predicted_monthly * 0.9;
    $prediction_upper = $predicted_monthly * 1.1;
}

if ($prophet_predictions && count($prophet_predictions) > 0) {
    $predicted_values = array_column($prophet_predictions, 'yhat');
    $total_predicted = array_sum($predicted_values);
    $avg_monthly = $total_predicted / count($predicted_values);
    $best_month = $prophet_predictions[array_search(max($predicted_values), $predicted_values)];
    $worst_month = $prophet_predictions[array_search(min($predicted_values), $predicted_values)];
        $historical_avg = array_sum(array_values($historical_data)) / max(count($historical_data), 1);
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

    $actuals_2025 = [];
    foreach ($breakdownIncomeEntries as $entry) {
        $entryDate = $entry['entry_date'] ?? null;
        if (!$entryDate) {
            continue;
        }
        $year = date('Y', strtotime($entryDate));
        if ($year !== '2025') {
            continue;
        }
        $monthKey = date('Y-m', strtotime($entryDate));
        if (!isset($actuals_2025[$monthKey])) {
            $actuals_2025[$monthKey] = 0;
        }
        $actuals_2025[$monthKey] += floatval($entry['tithes'] ?? 0) + floatval($entry['offerings'] ?? 0);
    }
    ksort($actuals_2025);

    $months_2025 = $prophet_predictions ? array_map(function ($p) {
        return $p['month'];
    }, $prophet_predictions) : [];

    $actual_data_2025 = [];
    $predicted_data_2025 = [];
    foreach ($months_2025 as $idx => $month) {
        $actual_data_2025[] = isset($actuals_2025[$month]) ? $actuals_2025[$month] : 0;
        $predicted_data_2025[] = isset($prophet_predictions[$idx]['yhat']) ? $prophet_predictions[$idx]['yhat'] : 0;
    }
} else {
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

    function calculateWeeklyOfferingsProphet($conn) {
        $sql = "
            SELECT 
                DATE_FORMAT(entry_date, '%Y-%U') as week,
                SUM(offerings) as total
            FROM breakdown_income
            GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
            ORDER BY week ASC";
        $result = $conn->query($sql);
        $weekly_data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
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

        $ch = curl_init('https://cocd-predict.onrender.com/predict');
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

        $sql = "
            SELECT 
                AVG(total) as avg_weekly
            FROM (
                SELECT 
                    DATE_FORMAT(entry_date, '%Y-%U') as week,
                    SUM(offerings) as total
                FROM breakdown_income
                GROUP BY DATE_FORMAT(entry_date, '%Y-%U')
            ) weekly";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        return $row['avg_weekly'] ?? 0;
    }

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

    $sql = "
        SELECT 
            DATE_FORMAT(entry_date, '%Y-%m') as month,
            SUM(tithes + offerings) as total
        FROM breakdown_income
        GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
        ORDER BY month ASC";

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

    if (empty($historical_data)) {
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $historical_data[$month] = 0;
        }
    }

    error_log("Final Historical Data: " . print_r($historical_data, true));

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

        $ch = curl_init('https://cocd-predict.onrender.com/predict');
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

        error_log("Prophet prediction failed. HTTP code: " . $http_code);
        error_log("Curl error: " . $curl_error);
        error_log("Response: " . $response);

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
}

// Fetch actual totals for each month in 2025
if (empty($breakdownIncomeEntries)) {
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
const actuals2025 = " . json_encode($prophet_predictions ?? []) . ";
const months2025 = " . json_encode($months_2025 ?? []) . ";
const actualData2025 = " . json_encode($actual_data_2025 ?? []) . ";
const predictedData2025 = " . json_encode($predicted_data_2025 ?? []) . ";
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

// Weekly summary for tithes and offerings (prioritize breakdown data, fallback to legacy tables)
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
    $legacySql = "
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
    $result = $conn->query($legacySql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['grand_total'] = $row['total_tithes'] + $row['total_offerings'];
        $weekly_reports[] = $row;
        }
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
            margin-left: 0;
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
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eeeeee;
            white-space: nowrap;
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

        /* --- Drawer Navigation Styles (copied from superadmin_dashboard.php) --- */
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
            }
            th, td {
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
            th, td {
                padding: 6px 8px;
                font-size: 12px;
            }
            .top-bar h2 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
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
                <li>
                    <a href="dashboard.php" class="drawer-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="admin_events.php" class="drawer-link <?php echo $current_page == 'admin_events.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Events</span>
                    </a>
                </li>
                <li>
                    <a href="admin_prayers.php" class="drawer-link <?php echo $current_page == 'admin_prayers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hands-praying"></i>
                        <span>Prayer Requests</span>
                    </a>
                </li>
                <li>
                    <a href="admin_messages.php" class="drawer-link <?php echo $current_page == 'admin_messages.php' ? 'active' : ''; ?>">
                        <i class="fas fa-video"></i>
                        <span>Messages</span>
                    </a>
                </li>
                <li>
                    <a href="financialreport.php" class="drawer-link <?php echo $current_page == 'financialreport.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Financial Reports</span>
                    </a>
                </li>
                <li>
                    <a href="admin_expenses.php" class="drawer-link <?php echo $current_page == 'admin_expenses.php' ? 'active' : ''; ?>">
                        <i class="fas fa-receipt"></i>
                        <span>Monthly Expenses</span>
                    </a>
                </li>
                <li>
                    <a href="member_contributions.php" class="drawer-link <?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list-alt"></i>
                        <span>Stewardship Report</span>
                    </a>
                </li>
                <li>
                    <a href="admin_settings.php" class="drawer-link <?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
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
                <div class="role"><?php echo htmlspecialchars($user_profile['role'] ?? 'Administrator'); ?></div>
            </div>
            <form action="logout.php" method="post" style="margin:0;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>
    <!-- Drawer Overlay -->
    <div id="drawer-overlay" class="drawer-overlay"></div>

    <main class="content-area">
        <div class="top-bar">
            <div>
                <h2>Financial Reports</h2>
                <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                    Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                </p>
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
                                <?php if (!empty($weekly_reports)): ?>
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
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; color:#666; padding:20px;">
                                            No weekly records found in the financial breakdown.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tithes Tab -->
                <div class="tab-pane" id="tithes">
                    <div class="table-responsive">
                        <table id="tithes-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount (₱)</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($tithes_records)): ?>
                                <?php foreach ($tithes_records as $record): ?>
                                    <tr>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">No tithes records found in the financial breakdown.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Offerings Tab -->
                <div class="tab-pane" id="offerings">
                    <div class="table-responsive">
                        <table id="offerings-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount (₱)</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($offerings_records)): ?>
                                <?php foreach ($offerings_records as $record): ?>
                                    <tr>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">No offerings records found in the financial breakdown.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bank Gifts Tab -->
                <div class="tab-pane" id="bank-gifts">
                    <div class="table-responsive">
                        <table id="bank-gifts-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Amount (₱)</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($bank_gifts_records)): ?>
                                <?php foreach ($bank_gifts_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['id']); ?></td>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">No bank gifts records found in the financial breakdown.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Specified Gifts Tab -->
                <div class="tab-pane" id="specified-gifts">
                    <div class="table-responsive">
                        <table id="specified-gifts-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Amount (₱)</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($specified_gifts_records)): ?>
                                <?php foreach ($specified_gifts_records as $record): ?>
                                    <tr>
                                        <td><strong><?php echo date('F d, Y', strtotime($record['entry_date'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($record['category']); ?></td>
                                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['notes'] !== '' ? nl2br(htmlspecialchars($record['notes'])) : 'No notes provided.'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">-</td>
                                        <td style="text-align:center; color:#666; padding:20px;">No specified gifts (Others) records found in the financial breakdown.</td>
                                    </tr>
                                <?php endif; ?>
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

            <!-- Modals removed: breakdown data is read-only -->
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

    // Trend Chart
    const trendCtx = document.getElementById('trendChart') ? document.getElementById('trendChart').getContext('2d') : null;
    
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
        ['#tithes-table', '#offerings-table', '#bank-gifts-table', '#specified-gifts-table'].forEach(function(selector) {
            var $table = $(selector);
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
                        emptyTable: 'No records found in the financial breakdown.',
                            paginate: {
                                previous: 'Prev',
                                next: 'Next'
                            }
                        }
                    });
            }
        });
    });

    // Add Weekly Reports DataTable initialization
    $(document).ready(function() {
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
                emptyTable: 'No weekly records found in the financial breakdown.',
                paginate: {
                    previous: 'Prev',
                    next: 'Next'
                }
            }
        });
    });

    // Auto-hide alerts after 3 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 3000);
    });

    // Drawer Navigation JS (copied from superadmin_dashboard.php)
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