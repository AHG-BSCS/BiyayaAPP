<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: index.php");
    exit;
}
// Restrict access to Administrator only
if ($_SESSION["user_role"] !== "Administrator") {
    header("Location: index.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$username = $_SESSION["user"];
$is_admin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;

// Get user's user_id from username
$stmt = $conn->prepare("SELECT user_id FROM user_profiles WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data ? $user_data['user_id'] : null;

// Ensure income/expenses connection is available
$incomeExpensesEnabled = isset($incomeExpensesConn) && $incomeExpensesConn instanceof mysqli && !$incomeExpensesConn->connect_error;

if (
    !$incomeExpensesEnabled &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (isset($_POST['breakdown_action']) || isset($_POST['delete_breakdown_entry']))
) {
    $_SESSION['error_message'] = "Income & expense breakdown storage is currently unavailable. Please contact the administrator.";
    header("Location: admin_expenses.php#financial-breakdown");
    exit();
}

$expenseDecimalCount = 28;
$expenseUpdateBindings = "s" . str_repeat("d", $expenseDecimalCount) . "si";
$expenseInsertBindings = "s" . str_repeat("d", $expenseDecimalCount) . "ss";

function ensureBreakdownExpenseSchema(mysqli $connection): void
{
    static $initialized = [];
    $connectionId = spl_object_id($connection);
    if (isset($initialized[$connectionId])) {
        return;
    }

    $result = $connection->query("SELECT DATABASE()");
    if (!$result) {
        return;
    }
    $row = $result->fetch_row();
    $databaseName = $row ? $row[0] : null;
    $result->free();

    if (!$databaseName) {
        return;
    }

    $requiredColumns = [
        'kids_ministry',
        'youth_ministry',
        'music_ministry',
        'single_professionals_ministry',
        'young_couples_ministry',
        'wow_ministry',
        'amen_ministry',
        'couples_ministry',
        'visitation_prayer_ministry',
        'acquisitions',
        'materials',
        'labor',
        'mission_support',
        'land_title'
    ];

    $placeholders = implode(',', array_fill(0, count($requiredColumns), "'%s'"));
    $columnList = implode(',', array_map(fn($col) => "'" . $connection->real_escape_string($col) . "'", $requiredColumns));

    $missingColumnsQuery = "
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '" . $connection->real_escape_string($databaseName) . "'
          AND TABLE_NAME = 'breakdown_expenses'
          AND COLUMN_NAME IN ({$columnList})
    ";

    $existingColumns = [];
    if ($columnsResult = $connection->query($missingColumnsQuery)) {
        while ($columnRow = $columnsResult->fetch_assoc()) {
            $existingColumns[] = $columnRow['COLUMN_NAME'];
        }
        $columnsResult->free();
    }

    $columnsToAdd = array_diff($requiredColumns, $existingColumns);
    foreach ($columnsToAdd as $columnName) {
        $connection->query("ALTER TABLE breakdown_expenses ADD COLUMN {$columnName} DECIMAL(12,2) DEFAULT 0");
    }

    $connection->query("
        ALTER TABLE breakdown_expenses
        MODIFY COLUMN total_amount DECIMAL(12,2) GENERATED ALWAYS AS (
            speaker + workers + food + housekeeping + office_supplies + transportation + photocopy +
            internet + government_concern + water_bill + electric_bill + special_events + needy_calamity + trainings +
            kids_ministry + youth_ministry + music_ministry + single_professionals_ministry + young_couples_ministry +
            wow_ministry + amen_ministry + couples_ministry + visitation_prayer_ministry + acquisitions + materials +
            labor + mission_support + land_title
        ) STORED
    ");

    $initialized[$connectionId] = true;
}

// Handle Financial Breakdown submissions
if ($incomeExpensesEnabled && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['breakdown_action'])) {
    $action = $_POST['breakdown_action'];
    $entryType = $_POST['breakdown_type'] ?? 'income';
    $entryDate = $_POST['breakdown_date'] ?? '';
    $notes = trim($_POST['breakdown_notes'] ?? '');

    if (empty($entryDate)) {
        $_SESSION['error_message'] = "Please provide a date for the breakdown entry.";
    } else {
        if ($entryType === 'income') {
            $tithes = isset($_POST['income_tithes']) ? floatval($_POST['income_tithes']) : 0;
            $offerings = isset($_POST['income_offerings']) ? floatval($_POST['income_offerings']) : 0;
            $giftsBank = isset($_POST['income_gifts_bank']) ? floatval($_POST['income_gifts_bank']) : 0;
            $bankInterest = isset($_POST['income_bank_interest']) ? floatval($_POST['income_bank_interest']) : 0;
            $others = isset($_POST['income_others']) ? floatval($_POST['income_others']) : 0;
            $building = isset($_POST['income_building']) ? floatval($_POST['income_building']) : 0;

            $total = $tithes + $offerings + $giftsBank + $bankInterest + $others + $building;

            if ($total <= 0) {
                $_SESSION['error_message'] = "Please enter at least one amount for the income categories.";
            } else {
                if ($action === 'add') {
                    $stmt = $incomeExpensesConn->prepare("
                        INSERT INTO breakdown_income (
                            entry_date, tithes, offerings, gifts_bank, bank_interest, others, building, notes, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $createdBy = $user_id ?? $username;
                    $stmt->bind_param(
                        "sddddddss",
                        $entryDate,
                        $tithes,
                        $offerings,
                        $giftsBank,
                        $bankInterest,
                        $others,
                        $building,
                        $notes,
                        $createdBy
                    );

        if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Income breakdown entry added successfully!";
                        header("Location: admin_expenses.php#financial-breakdown");
            exit();
        } else {
                        $_SESSION['error_message'] = "Error saving income breakdown entry. Please try again.";
                    }
                } elseif ($action === 'update') {
                    $entryId = isset($_POST['breakdown_entry_id']) ? intval($_POST['breakdown_entry_id']) : 0;
                    $originalType = $_POST['original_breakdown_type'] ?? 'income';

                    if ($entryId <= 0) {
                        $_SESSION['error_message'] = "Invalid income breakdown entry selected for update.";
                    } elseif ($originalType !== 'income') {
                        $_SESSION['error_message'] = "Cannot change the entry type while editing. Please delete and recreate the entry if needed.";
                    } else {
                        $stmt = $incomeExpensesConn->prepare("
                            UPDATE breakdown_income
                            SET entry_date = ?, tithes = ?, offerings = ?, gifts_bank = ?, bank_interest = ?, others = ?, building = ?, notes = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->bind_param(
                            "sddddddsi",
                            $entryDate,
                            $tithes,
                            $offerings,
                            $giftsBank,
                            $bankInterest,
                            $others,
                            $building,
                            $notes,
                            $entryId
                        );

                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = "Income breakdown entry updated successfully!";
                            header("Location: admin_expenses.php#financial-breakdown");
                            exit();
                        } else {
                            $_SESSION['error_message'] = "Error updating income breakdown entry. Please try again.";
                        }
                    }
                }
            }
        } else {
            $expenseFields = [
                'speaker' => isset($_POST['expense_speaker']) ? floatval($_POST['expense_speaker']) : 0,
                'workers' => isset($_POST['expense_workers']) ? floatval($_POST['expense_workers']) : 0,
                'food' => isset($_POST['expense_food']) ? floatval($_POST['expense_food']) : 0,
                'housekeeping' => isset($_POST['expense_housekeeping']) ? floatval($_POST['expense_housekeeping']) : 0,
                'office_supplies' => isset($_POST['expense_office_supplies']) ? floatval($_POST['expense_office_supplies']) : 0,
                'transportation' => isset($_POST['expense_transportation']) ? floatval($_POST['expense_transportation']) : 0,
                'photocopy' => isset($_POST['expense_photocopy']) ? floatval($_POST['expense_photocopy']) : 0,
                'internet' => isset($_POST['expense_internet']) ? floatval($_POST['expense_internet']) : 0,
                'government_concern' => isset($_POST['expense_government_concern']) ? floatval($_POST['expense_government_concern']) : 0,
                'water_bill' => isset($_POST['expense_water_bill']) ? floatval($_POST['expense_water_bill']) : 0,
                'electric_bill' => isset($_POST['expense_electric_bill']) ? floatval($_POST['expense_electric_bill']) : 0,
                'special_events' => isset($_POST['expense_special_events']) ? floatval($_POST['expense_special_events']) : 0,
                'needy_calamity' => isset($_POST['expense_needy_calamity']) ? floatval($_POST['expense_needy_calamity']) : 0,
                'trainings' => isset($_POST['expense_trainings']) ? floatval($_POST['expense_trainings']) : 0,
                'kids_ministry' => isset($_POST['expense_kids_ministry']) ? floatval($_POST['expense_kids_ministry']) : 0,
                'youth_ministry' => isset($_POST['expense_youth_ministry']) ? floatval($_POST['expense_youth_ministry']) : 0,
                'music_ministry' => isset($_POST['expense_music_ministry']) ? floatval($_POST['expense_music_ministry']) : 0,
                'single_professionals_ministry' => isset($_POST['expense_single_professionals_ministry']) ? floatval($_POST['expense_single_professionals_ministry']) : 0,
                'young_couples_ministry' => isset($_POST['expense_young_couples_ministry']) ? floatval($_POST['expense_young_couples_ministry']) : 0,
                'wow_ministry' => isset($_POST['expense_wow_ministry']) ? floatval($_POST['expense_wow_ministry']) : 0,
                'amen_ministry' => isset($_POST['expense_amen_ministry']) ? floatval($_POST['expense_amen_ministry']) : 0,
                'couples_ministry' => isset($_POST['expense_couples_ministry']) ? floatval($_POST['expense_couples_ministry']) : 0,
                'visitation_prayer_ministry' => isset($_POST['expense_visitation_prayer_ministry']) ? floatval($_POST['expense_visitation_prayer_ministry']) : 0,
                'acquisitions' => isset($_POST['expense_acquisitions']) ? floatval($_POST['expense_acquisitions']) : 0,
                'materials' => isset($_POST['expense_materials']) ? floatval($_POST['expense_materials']) : 0,
                'labor' => isset($_POST['expense_labor']) ? floatval($_POST['expense_labor']) : 0,
                'mission_support' => isset($_POST['expense_mission_support']) ? floatval($_POST['expense_mission_support']) : 0,
                'land_title' => isset($_POST['expense_land_title']) ? floatval($_POST['expense_land_title']) : 0
            ];

            $totalExpense = array_sum($expenseFields);

            if ($totalExpense <= 0) {
                $_SESSION['error_message'] = "Please enter at least one amount for the expense categories.";
            } else {
                if ($action === 'add') {
                    $stmt = $incomeExpensesConn->prepare("
                        INSERT INTO breakdown_expenses (
                            entry_date, speaker, workers, food, housekeeping, office_supplies, transportation, photocopy,
                            internet, government_concern, water_bill, electric_bill, special_events, needy_calamity, trainings,
                            kids_ministry, youth_ministry, music_ministry, single_professionals_ministry, young_couples_ministry,
                            wow_ministry, amen_ministry, couples_ministry, visitation_prayer_ministry, acquisitions, materials,
                            labor, mission_support, land_title, notes, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $createdBy = $user_id ?? $username;
                    $stmt->bind_param(
                        $expenseInsertBindings,
                        $entryDate,
                        $expenseFields['speaker'],
                        $expenseFields['workers'],
                        $expenseFields['food'],
                        $expenseFields['housekeeping'],
                        $expenseFields['office_supplies'],
                        $expenseFields['transportation'],
                        $expenseFields['photocopy'],
                        $expenseFields['internet'],
                        $expenseFields['government_concern'],
                        $expenseFields['water_bill'],
                        $expenseFields['electric_bill'],
                        $expenseFields['special_events'],
                        $expenseFields['needy_calamity'],
                        $expenseFields['trainings'],
                        $expenseFields['kids_ministry'],
                        $expenseFields['youth_ministry'],
                        $expenseFields['music_ministry'],
                        $expenseFields['single_professionals_ministry'],
                        $expenseFields['young_couples_ministry'],
                        $expenseFields['wow_ministry'],
                        $expenseFields['amen_ministry'],
                        $expenseFields['couples_ministry'],
                        $expenseFields['visitation_prayer_ministry'],
                        $expenseFields['acquisitions'],
                        $expenseFields['materials'],
                        $expenseFields['labor'],
                        $expenseFields['mission_support'],
                        $expenseFields['land_title'],
                        $notes,
                        $createdBy
                    );

                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Expense breakdown entry added successfully!";
                        header("Location: admin_expenses.php#financial-breakdown");
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Error saving expense breakdown entry. Please try again.";
                    }
                } elseif ($action === 'update') {
                    $entryId = isset($_POST['breakdown_entry_id']) ? intval($_POST['breakdown_entry_id']) : 0;
                    $originalType = $_POST['original_breakdown_type'] ?? 'expense';

                    if ($entryId <= 0) {
                        $_SESSION['error_message'] = "Invalid expense breakdown entry selected for update.";
                    } elseif ($originalType !== 'expense') {
                        $_SESSION['error_message'] = "Cannot change the entry type while editing. Please delete and recreate the entry if needed.";
                    } else {
                        $stmt = $incomeExpensesConn->prepare("
                            UPDATE breakdown_expenses
                            SET entry_date = ?, speaker = ?, workers = ?, food = ?, housekeeping = ?, office_supplies = ?, transportation = ?, photocopy = ?,
                                internet = ?, government_concern = ?, water_bill = ?, electric_bill = ?, special_events = ?, needy_calamity = ?, trainings = ?,
                                kids_ministry = ?, youth_ministry = ?, music_ministry = ?, single_professionals_ministry = ?, young_couples_ministry = ?,
                                wow_ministry = ?, amen_ministry = ?, couples_ministry = ?, visitation_prayer_ministry = ?, acquisitions = ?, materials = ?,
                                labor = ?, mission_support = ?, land_title = ?, notes = ?, updated_at = NOW()
        WHERE id = ?
    ");
                        $stmt->bind_param(
                            $expenseUpdateBindings,
                            $entryDate,
                            $expenseFields['speaker'],
                            $expenseFields['workers'],
                            $expenseFields['food'],
                            $expenseFields['housekeeping'],
                            $expenseFields['office_supplies'],
                            $expenseFields['transportation'],
                            $expenseFields['photocopy'],
                            $expenseFields['internet'],
                            $expenseFields['government_concern'],
                            $expenseFields['water_bill'],
                            $expenseFields['electric_bill'],
                            $expenseFields['special_events'],
                            $expenseFields['needy_calamity'],
                            $expenseFields['trainings'],
                            $expenseFields['kids_ministry'],
                            $expenseFields['youth_ministry'],
                            $expenseFields['music_ministry'],
                            $expenseFields['single_professionals_ministry'],
                            $expenseFields['young_couples_ministry'],
                            $expenseFields['wow_ministry'],
                            $expenseFields['amen_ministry'],
                            $expenseFields['couples_ministry'],
                            $expenseFields['visitation_prayer_ministry'],
                            $expenseFields['acquisitions'],
                            $expenseFields['materials'],
                            $expenseFields['labor'],
                            $expenseFields['mission_support'],
                            $expenseFields['land_title'],
                            $notes,
                            $entryId
                        );

    if ($stmt->execute()) {
                            $_SESSION['success_message'] = "Expense breakdown entry updated successfully!";
                            header("Location: admin_expenses.php#financial-breakdown");
        exit();
    } else {
                            $_SESSION['error_message'] = "Error updating expense breakdown entry. Please try again.";
                        }
                    }
                }
            }
        }
    }
}

if ($incomeExpensesEnabled && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_breakdown_entry'])) {
    $entryId = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $entryType = $_POST['entry_type'] ?? 'income';

    if ($entryId > 0) {
        if ($entryType === 'income') {
            $stmt = $incomeExpensesConn->prepare("DELETE FROM breakdown_income WHERE id = ?");
        } else {
            $stmt = $incomeExpensesConn->prepare("DELETE FROM breakdown_expenses WHERE id = ?");
        }

        if ($stmt) {
            $stmt->bind_param("i", $entryId);
    if ($stmt->execute()) {
                $_SESSION['success_message'] = "Breakdown entry deleted successfully!";
    } else {
                $_SESSION['error_message'] = "Error deleting breakdown entry. Please try again.";
            }
        }
    }

    header("Location: admin_expenses.php#financial-breakdown");
    exit();
}


// Financial breakdown data
$breakdownFilterDate = '';
if (!empty($_GET['breakdown_date'])) {
    $dateCandidate = DateTime::createFromFormat('Y-m-d', $_GET['breakdown_date']);
    if ($dateCandidate) {
        $breakdownFilterDate = $dateCandidate->format('Y-m-d');
    }
}
$hasBreakdownFilter = $breakdownFilterDate !== '';

$breakdownIncomeEntries = [];
$breakdownIncomeTotals = [
    'tithes' => 0,
    'offerings' => 0,
    'gifts_bank' => 0,
    'bank_interest' => 0,
    'others' => 0,
    'building' => 0,
    'total' => 0
];

$breakdownExpenseEntries = [];
$breakdownExpenseTotals = [
    'speaker' => 0,
    'workers' => 0,
    'food' => 0,
    'housekeeping' => 0,
    'office_supplies' => 0,
    'transportation' => 0,
    'photocopy' => 0,
    'internet' => 0,
    'government_concern' => 0,
    'water_bill' => 0,
    'electric_bill' => 0,
    'special_events' => 0,
    'needy_calamity' => 0,
    'trainings' => 0,
    'kids_ministry' => 0,
    'youth_ministry' => 0,
    'music_ministry' => 0,
    'single_professionals_ministry' => 0,
    'young_couples_ministry' => 0,
    'wow_ministry' => 0,
    'amen_ministry' => 0,
    'couples_ministry' => 0,
    'visitation_prayer_ministry' => 0,
    'acquisitions' => 0,
    'materials' => 0,
    'labor' => 0,
    'mission_support' => 0,
    'land_title' => 0,
    'total' => 0
];

$expenses_data = [];
$totals = [
    'total_income' => 0,
    'total_expenses' => 0,
    'total_difference' => 0,
    'count' => 0
];
$averages = [
    'avg_income' => 0,
    'avg_expenses' => 0,
    'avg_difference' => 0
];

if ($incomeExpensesEnabled) {
    ensureBreakdownExpenseSchema($incomeExpensesConn);
    $incomeResult = $incomeExpensesConn->query("
        SELECT id, entry_date, tithes, offerings, gifts_bank, bank_interest, others, building, notes, created_by, created_at, updated_at
        FROM breakdown_income
        ORDER BY entry_date DESC, id DESC
    ");

    if ($incomeResult) {
        while ($row = $incomeResult->fetch_assoc()) {
            $row['tithes'] = floatval($row['tithes']);
            $row['offerings'] = floatval($row['offerings']);
            $row['gifts_bank'] = floatval($row['gifts_bank']);
            $row['bank_interest'] = floatval($row['bank_interest']);
            $row['others'] = floatval($row['others']);
            $row['building'] = floatval($row['building']);

            $row['total_amount'] = $row['tithes'] + $row['offerings'] + $row['gifts_bank'] + $row['bank_interest'] + $row['others'] + $row['building'];

            $breakdownIncomeTotals['tithes'] += $row['tithes'];
            $breakdownIncomeTotals['offerings'] += $row['offerings'];
            $breakdownIncomeTotals['gifts_bank'] += $row['gifts_bank'];
            $breakdownIncomeTotals['bank_interest'] += $row['bank_interest'];
            $breakdownIncomeTotals['others'] += $row['others'];
            $breakdownIncomeTotals['building'] += $row['building'];
            $breakdownIncomeTotals['total'] += $row['total_amount'];

            $breakdownIncomeEntries[] = $row;
        }
    }

    $expenseResult = $incomeExpensesConn->query("
        SELECT id, entry_date, speaker, workers, food, housekeeping, office_supplies, transportation, photocopy, internet, government_concern, water_bill, electric_bill, special_events, needy_calamity, trainings,
               kids_ministry, youth_ministry, music_ministry, single_professionals_ministry, young_couples_ministry, wow_ministry, amen_ministry, couples_ministry, visitation_prayer_ministry,
               acquisitions, materials, labor, mission_support, land_title,
               total_amount, notes, created_by, created_at, updated_at
        FROM breakdown_expenses
        ORDER BY entry_date DESC, id DESC
    ");

    if ($expenseResult) {
        while ($row = $expenseResult->fetch_assoc()) {
            $row['speaker'] = floatval($row['speaker']);
            $row['workers'] = floatval($row['workers']);
            $row['food'] = floatval($row['food']);
            $row['housekeeping'] = floatval($row['housekeeping']);
            $row['office_supplies'] = floatval($row['office_supplies']);
            $row['transportation'] = floatval($row['transportation']);
            $row['photocopy'] = floatval($row['photocopy']);
            $row['internet'] = floatval($row['internet']);
            $row['government_concern'] = floatval($row['government_concern']);
            $row['water_bill'] = floatval($row['water_bill']);
            $row['electric_bill'] = floatval($row['electric_bill']);
            $row['special_events'] = floatval($row['special_events']);
            $row['needy_calamity'] = floatval($row['needy_calamity']);
            $row['trainings'] = floatval($row['trainings']);
            $row['kids_ministry'] = isset($row['kids_ministry']) ? floatval($row['kids_ministry']) : 0;
            $row['youth_ministry'] = isset($row['youth_ministry']) ? floatval($row['youth_ministry']) : 0;
            $row['music_ministry'] = isset($row['music_ministry']) ? floatval($row['music_ministry']) : 0;
            $row['single_professionals_ministry'] = isset($row['single_professionals_ministry']) ? floatval($row['single_professionals_ministry']) : 0;
            $row['young_couples_ministry'] = isset($row['young_couples_ministry']) ? floatval($row['young_couples_ministry']) : 0;
            $row['wow_ministry'] = isset($row['wow_ministry']) ? floatval($row['wow_ministry']) : 0;
            $row['amen_ministry'] = isset($row['amen_ministry']) ? floatval($row['amen_ministry']) : 0;
            $row['couples_ministry'] = isset($row['couples_ministry']) ? floatval($row['couples_ministry']) : 0;
            $row['visitation_prayer_ministry'] = isset($row['visitation_prayer_ministry']) ? floatval($row['visitation_prayer_ministry']) : 0;
            $row['acquisitions'] = isset($row['acquisitions']) ? floatval($row['acquisitions']) : 0;
            $row['materials'] = isset($row['materials']) ? floatval($row['materials']) : 0;
            $row['labor'] = isset($row['labor']) ? floatval($row['labor']) : 0;
            $row['mission_support'] = isset($row['mission_support']) ? floatval($row['mission_support']) : 0;
            $row['land_title'] = isset($row['land_title']) ? floatval($row['land_title']) : 0;
            $row['total_amount'] = isset($row['total_amount']) ? floatval($row['total_amount']) : (
                $row['speaker'] + $row['workers'] + $row['food'] + $row['housekeeping'] + $row['office_supplies'] +
                $row['transportation'] + $row['photocopy'] + $row['internet'] + $row['government_concern'] +
                $row['water_bill'] + $row['electric_bill'] + $row['special_events'] + $row['needy_calamity'] + $row['trainings'] +
                $row['kids_ministry'] + $row['youth_ministry'] + $row['music_ministry'] + $row['single_professionals_ministry'] +
                $row['young_couples_ministry'] + $row['wow_ministry'] + $row['amen_ministry'] + $row['couples_ministry'] +
                $row['visitation_prayer_ministry'] + $row['acquisitions'] + $row['materials'] + $row['labor'] +
                $row['mission_support'] + $row['land_title']
            );

            $breakdownExpenseTotals['speaker'] += $row['speaker'];
            $breakdownExpenseTotals['workers'] += $row['workers'];
            $breakdownExpenseTotals['food'] += $row['food'];
            $breakdownExpenseTotals['housekeeping'] += $row['housekeeping'];
            $breakdownExpenseTotals['office_supplies'] += $row['office_supplies'];
            $breakdownExpenseTotals['transportation'] += $row['transportation'];
            $breakdownExpenseTotals['photocopy'] += $row['photocopy'];
            $breakdownExpenseTotals['internet'] += $row['internet'];
            $breakdownExpenseTotals['government_concern'] += $row['government_concern'];
            $breakdownExpenseTotals['water_bill'] += $row['water_bill'];
            $breakdownExpenseTotals['electric_bill'] += $row['electric_bill'];
            $breakdownExpenseTotals['special_events'] += $row['special_events'];
            $breakdownExpenseTotals['needy_calamity'] += $row['needy_calamity'];
            $breakdownExpenseTotals['trainings'] += $row['trainings'];
            $breakdownExpenseTotals['kids_ministry'] += $row['kids_ministry'];
            $breakdownExpenseTotals['youth_ministry'] += $row['youth_ministry'];
            $breakdownExpenseTotals['music_ministry'] += $row['music_ministry'];
            $breakdownExpenseTotals['single_professionals_ministry'] += $row['single_professionals_ministry'];
            $breakdownExpenseTotals['young_couples_ministry'] += $row['young_couples_ministry'];
            $breakdownExpenseTotals['wow_ministry'] += $row['wow_ministry'];
            $breakdownExpenseTotals['amen_ministry'] += $row['amen_ministry'];
            $breakdownExpenseTotals['couples_ministry'] += $row['couples_ministry'];
            $breakdownExpenseTotals['visitation_prayer_ministry'] += $row['visitation_prayer_ministry'];
            $breakdownExpenseTotals['acquisitions'] += $row['acquisitions'];
            $breakdownExpenseTotals['materials'] += $row['materials'];
            $breakdownExpenseTotals['labor'] += $row['labor'];
            $breakdownExpenseTotals['mission_support'] += $row['mission_support'];
            $breakdownExpenseTotals['land_title'] += $row['land_title'];
            $breakdownExpenseTotals['total'] += $row['total_amount'];

            $breakdownExpenseEntries[] = $row;
        }
    }
}

$displayIncomeEntries = $breakdownIncomeEntries;
$displayExpenseEntries = $breakdownExpenseEntries;

if ($hasBreakdownFilter) {
    $displayIncomeEntries = array_values(array_filter($breakdownIncomeEntries, function ($entry) use ($breakdownFilterDate) {
        return isset($entry['entry_date']) && $entry['entry_date'] === $breakdownFilterDate;
    }));

    $displayExpenseEntries = array_values(array_filter($breakdownExpenseEntries, function ($entry) use ($breakdownFilterDate) {
        return isset($entry['entry_date']) && $entry['entry_date'] === $breakdownFilterDate;
    }));
    
    // No pagination when filtering by date
    $breakdownPagination = null;
} else {
    // Pagination: Show 6 entries (cards) at a time
    $breakdownPage = isset($_GET['breakdown_page']) ? max(0, intval($_GET['breakdown_page'])) : 0;
    $entriesPerPage = 6;
    
    // Entries are already sorted by date DESC, so we can paginate directly
    $totalIncomeEntries = count($breakdownIncomeEntries);
    $totalExpenseEntries = count($breakdownExpenseEntries);
    
    // Calculate pagination for income entries
    $incomeTotalPages = max(1, ceil($totalIncomeEntries / $entriesPerPage));
    $incomeStartIndex = $breakdownPage * $entriesPerPage;
    $incomeEndIndex = min($incomeStartIndex + $entriesPerPage, $totalIncomeEntries);
    $displayIncomeEntries = array_slice($breakdownIncomeEntries, $incomeStartIndex, $entriesPerPage);
    
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
}

$displayIncomeTotals = $breakdownIncomeTotals;
if ($hasBreakdownFilter) {
    $displayIncomeTotals = [
        'tithes' => 0,
        'offerings' => 0,
        'gifts_bank' => 0,
        'bank_interest' => 0,
        'others' => 0,
        'building' => 0,
        'total' => 0
    ];
    foreach ($displayIncomeEntries as $entry) {
        $displayIncomeTotals['tithes'] += isset($entry['tithes']) ? floatval($entry['tithes']) : 0;
        $displayIncomeTotals['offerings'] += isset($entry['offerings']) ? floatval($entry['offerings']) : 0;
        $displayIncomeTotals['gifts_bank'] += isset($entry['gifts_bank']) ? floatval($entry['gifts_bank']) : 0;
        $displayIncomeTotals['bank_interest'] += isset($entry['bank_interest']) ? floatval($entry['bank_interest']) : 0;
        $displayIncomeTotals['others'] += isset($entry['others']) ? floatval($entry['others']) : 0;
        $displayIncomeTotals['building'] += isset($entry['building']) ? floatval($entry['building']) : 0;
        $displayIncomeTotals['total'] += isset($entry['total_amount']) ? floatval($entry['total_amount']) : 0;
    }
}

$displayExpenseTotals = $breakdownExpenseTotals;
if ($hasBreakdownFilter) {
    $displayExpenseTotals = [
        'speaker' => 0,
        'workers' => 0,
        'food' => 0,
        'housekeeping' => 0,
        'office_supplies' => 0,
        'transportation' => 0,
        'photocopy' => 0,
        'internet' => 0,
        'government_concern' => 0,
        'water_bill' => 0,
        'electric_bill' => 0,
        'special_events' => 0,
        'needy_calamity' => 0,
        'trainings' => 0,
        'kids_ministry' => 0,
        'youth_ministry' => 0,
        'music_ministry' => 0,
        'single_professionals_ministry' => 0,
        'young_couples_ministry' => 0,
        'wow_ministry' => 0,
        'amen_ministry' => 0,
        'couples_ministry' => 0,
        'visitation_prayer_ministry' => 0,
        'acquisitions' => 0,
        'materials' => 0,
        'labor' => 0,
        'mission_support' => 0,
        'land_title' => 0,
        'total' => 0
    ];
    foreach ($displayExpenseEntries as $entry) {
        $displayExpenseTotals['speaker'] += isset($entry['speaker']) ? floatval($entry['speaker']) : 0;
        $displayExpenseTotals['workers'] += isset($entry['workers']) ? floatval($entry['workers']) : 0;
        $displayExpenseTotals['food'] += isset($entry['food']) ? floatval($entry['food']) : 0;
        $displayExpenseTotals['housekeeping'] += isset($entry['housekeeping']) ? floatval($entry['housekeeping']) : 0;
        $displayExpenseTotals['office_supplies'] += isset($entry['office_supplies']) ? floatval($entry['office_supplies']) : 0;
        $displayExpenseTotals['transportation'] += isset($entry['transportation']) ? floatval($entry['transportation']) : 0;
        $displayExpenseTotals['photocopy'] += isset($entry['photocopy']) ? floatval($entry['photocopy']) : 0;
        $displayExpenseTotals['internet'] += isset($entry['internet']) ? floatval($entry['internet']) : 0;
        $displayExpenseTotals['government_concern'] += isset($entry['government_concern']) ? floatval($entry['government_concern']) : 0;
        $displayExpenseTotals['water_bill'] += isset($entry['water_bill']) ? floatval($entry['water_bill']) : 0;
        $displayExpenseTotals['electric_bill'] += isset($entry['electric_bill']) ? floatval($entry['electric_bill']) : 0;
        $displayExpenseTotals['special_events'] += isset($entry['special_events']) ? floatval($entry['special_events']) : 0;
        $displayExpenseTotals['needy_calamity'] += isset($entry['needy_calamity']) ? floatval($entry['needy_calamity']) : 0;
        $displayExpenseTotals['trainings'] += isset($entry['trainings']) ? floatval($entry['trainings']) : 0;
        $displayExpenseTotals['kids_ministry'] += isset($entry['kids_ministry']) ? floatval($entry['kids_ministry']) : 0;
        $displayExpenseTotals['youth_ministry'] += isset($entry['youth_ministry']) ? floatval($entry['youth_ministry']) : 0;
        $displayExpenseTotals['music_ministry'] += isset($entry['music_ministry']) ? floatval($entry['music_ministry']) : 0;
        $displayExpenseTotals['single_professionals_ministry'] += isset($entry['single_professionals_ministry']) ? floatval($entry['single_professionals_ministry']) : 0;
        $displayExpenseTotals['young_couples_ministry'] += isset($entry['young_couples_ministry']) ? floatval($entry['young_couples_ministry']) : 0;
        $displayExpenseTotals['wow_ministry'] += isset($entry['wow_ministry']) ? floatval($entry['wow_ministry']) : 0;
        $displayExpenseTotals['amen_ministry'] += isset($entry['amen_ministry']) ? floatval($entry['amen_ministry']) : 0;
        $displayExpenseTotals['couples_ministry'] += isset($entry['couples_ministry']) ? floatval($entry['couples_ministry']) : 0;
        $displayExpenseTotals['visitation_prayer_ministry'] += isset($entry['visitation_prayer_ministry']) ? floatval($entry['visitation_prayer_ministry']) : 0;
        $displayExpenseTotals['acquisitions'] += isset($entry['acquisitions']) ? floatval($entry['acquisitions']) : 0;
        $displayExpenseTotals['materials'] += isset($entry['materials']) ? floatval($entry['materials']) : 0;
        $displayExpenseTotals['labor'] += isset($entry['labor']) ? floatval($entry['labor']) : 0;
        $displayExpenseTotals['mission_support'] += isset($entry['mission_support']) ? floatval($entry['mission_support']) : 0;
        $displayExpenseTotals['land_title'] += isset($entry['land_title']) ? floatval($entry['land_title']) : 0;
        $displayExpenseTotals['total'] += isset($entry['total_amount']) ? floatval($entry['total_amount']) : 0;
    }
}

$monthlySummary = [];

if ($incomeExpensesEnabled) {
    foreach ($breakdownIncomeEntries as $entry) {
        $monthKey = date('Y-m', strtotime($entry['entry_date']));

        if (!isset($monthlySummary[$monthKey])) {
            $monthlySummary[$monthKey] = [
                'income' => 0,
                'expenses' => 0
            ];
        }

        $monthlySummary[$monthKey]['income'] += $entry['total_amount'];
    }

    foreach ($breakdownExpenseEntries as $entry) {
        $monthKey = date('Y-m', strtotime($entry['entry_date']));

        if (!isset($monthlySummary[$monthKey])) {
            $monthlySummary[$monthKey] = [
                'income' => 0,
                'expenses' => 0
            ];
        }

        $monthlySummary[$monthKey]['expenses'] += $entry['total_amount'];
    }
}

if (!empty($monthlySummary)) {
    ksort($monthlySummary);

    foreach ($monthlySummary as $monthKey => $values) {
        $income = isset($values['income']) ? floatval($values['income']) : 0;
        $expenses = isset($values['expenses']) ? floatval($values['expenses']) : 0;
        $difference = $income - $expenses;

        $expenses_data[] = [
            'month' => $monthKey,
            'income' => $income,
            'expenses' => $expenses,
            'difference' => $difference
        ];

        $totals['total_income'] += $income;
        $totals['total_expenses'] += $expenses;
        $totals['total_difference'] += $difference;
        $totals['count']++;
    }
}

if ($totals['count'] > 0) {
    $averages['avg_income'] = $totals['total_income'] / $totals['count'];
    $averages['avg_expenses'] = $totals['total_expenses'] / $totals['count'];
    $averages['avg_difference'] = $totals['total_difference'] / $totals['count'];
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Expenses | <?php echo $church_name; ?></title>
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

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
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

        /* Main Content Area */
        .content-area {
            flex: 1;
            margin-left: 0;
            padding: 20px;
            min-height: 100vh;
            background-color: #f5f5f5;
        }

        /* Top Bar */
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
            color: var(--primary-color);
            font-size: 24px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-info h4 {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 14px;
            color: #666;
        }

        .logout-btn {
            padding: 8px 15px;
            background-color: #f0f0f0;
            color: var(--primary-color);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: #e0e0e0;
        }

        /* Content */
        .content {
            padding: 20px;
            background-color: #f5f5f5;
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: var(--white);
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .breakdown-card-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 16px;
        }

        .breakdown-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .breakdown-search-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .breakdown-search-form input[type="date"] {
            padding: 10px 14px;
            border: 1px solid #d5d5d5;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .breakdown-search-form input[type="date"]:hover,
        .breakdown-search-form input[type="date"]:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 139, 30, 0.15);
            outline: none;
        }

        .breakdown-header-actions .btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .breakdown-header-actions .btn-primary {
            background-color: var(--accent-color);
            color: #fff;
        }

        .breakdown-header-actions .btn-primary:hover {
            background-color: rgb(0, 112, 24);
        }

        .breakdown-header-actions .btn-primary i {
            font-size: 18px;
        }

        .breakdown-header-actions .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .breakdown-header-actions .btn-secondary:hover {
            background-color: #5a6268;
        }

        .card h2 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .breakdown-card-header h2 {
            margin: 0 !important;
        }

        .card-icon {
            font-size: 24px;
            color: var(--accent-color);
            margin-bottom: 10px;
        }

        .card-info h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .card-info p {
            font-size: 24px;
            font-weight: bold;
            color: var(--accent-color);
        }

        /* Forms */
        .expense-form {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 139, 30, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .form-actions button {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .form-actions .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }

        .form-actions .btn-primary:hover {
            background-color: rgb(0, 112, 24);
        }

        .form-actions .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .form-actions .btn-secondary:hover {
            background-color: #5a6268;
        }

        .form-actions .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .form-actions .btn-danger:hover {
            background-color: #d32f2f;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        /* Alerts */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }

        /* Action Bar Styles */
        .action-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
            padding: 10px;
            background-color: var(--white);
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .action-bar .btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 500;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .action-bar .btn:hover {
            background-color: rgb(0, 112, 24);
        }

        .action-bar .btn i {
            font-size: 18px;
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
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--white);
            margin: 2% auto;
            padding: 20px;
            border-radius: 5px;
            width: 90%;
            max-width: 600px;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--primary-color);
        }

        /* Alert Updates */
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: opacity 0.3s ease;
        }

        .alert i {
            margin-right: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive Updates */
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
                width: 100%;
            }
            table {
                font-size: 14px;
                min-width: 600px;
                width: auto !important;
            }
            #expensesTable {
                min-width: 600px !important;
                width: auto !important;
                table-layout: auto;
            }
            th, td {
                padding: 8px 10px;
                font-size: 13px;
            }
            #monthly-expenses .card {
                padding: 10px;
            }
            #monthly-expenses .table-responsive {
                margin: 10px 0 0;
                padding: 10px;
            }
            .top-bar {
                padding: 12px 15px;
            }
            .top-bar h2 {
                font-size: 20px;
            }
            .modal-content {
                margin: 5% auto;
                width: 95%;
                padding: 15px;
                max-height: 85vh;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
            }

            .dataTables_wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
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
                width: auto !important;
            }
            #expensesTable {
                min-width: 500px !important;
                width: auto !important;
                table-layout: auto;
            }
            th, td {
                padding: 6px 8px;
                font-size: 12px;
            }
            #monthly-expenses .card {
                padding: 8px;
            }
            #monthly-expenses .table-responsive {
                margin: 8px 0 0;
                padding: 8px;
            }
            .top-bar h2 {
                font-size: 18px;
            }
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1400px;
            table-layout: fixed;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
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

        .expense-card-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        @media (max-width: 1024px) {
            .expense-card-list {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 640px) {
            .expense-card-list {
                grid-template-columns: 1fr;
            }
        }

        .expense-card {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .expense-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(15, 23, 42, 0.14);
        }

        .expense-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--accent-color) 0%, rgb(0, 112, 9) 100%);
            color: #fff;
        }

        .expense-card .card-header .date {
            font-weight: 600;
            font-size: 16px;
        }

        .expense-card .card-header .actions {
            display: flex;
            gap: 10px;
        }

        .expense-card .card-header .actions .action-btn {
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.35);
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
            gap: 16px;
        }

        .expense-item:last-child {
            border-bottom: none;
        }

        .expense-item .label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            flex: 1;
        }

        .expense-item .value {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        .expense-card .card-footer {
            background: #f8f9fa;
            padding: 16px 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .expense-card .card-footer .note {
            color: #64748b;
            font-size: 13px;
            flex: 1;
            min-width: 200px;
        }

        .expense-card .card-footer .total {
            color: var(--accent-color);
            font-weight: 700;
            font-size: 16px;
            white-space: nowrap;
        }

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
        }

        .breakdown-pagination .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .breakdown-pagination .btn:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .breakdown-pagination .pagination-info {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .expense-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        .expense-summary-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 15px 18px;
        }

        .expense-summary-item .label {
            font-size: 13px;
            color: #555;
            margin-bottom: 6px;
        }

        .expense-summary-item .value {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Summary row styling */
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

        /* Action buttons in table */
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

        /* Prevent DataTable layout shifts */
        .dataTables_wrapper {
            width: 100%;
        }
        
        .dataTables_scroll {
            overflow-x: auto;
        }
        
        /* Ensure table doesn't move during initialization */
        #expensesTable {
            visibility: hidden;
            width: 100% !important;
            table-layout: fixed;
        }
        
        #expensesTable.dataTable {
            visibility: visible;
        }

        #expensesTable th,
        #expensesTable td {
            white-space: normal;
            word-break: break-word;
        }

        #monthly-expenses .table-responsive,
        #monthly-expenses .dataTables_wrapper {
            width: 100% !important;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

          

        /* Preserve custom styling for DataTable columns */
        .month-column {
            font-weight: bold !important;
            text-align: left !important;
            vertical-align: middle !important;
            padding: 12px 15px !important;
            border-bottom: 1px solid #eee !important;
        }

        .month-column strong {
            font-weight: bold !important;
        }

        /* Override DataTable default styling */
        .dataTables_wrapper .dataTable td.month-column {
            font-weight: bold !important;
            text-align: left !important;
            vertical-align: middle !important;
            padding: 12px 15px !important;
            border-bottom: 1px solid #eee !important;
            border-right: 1px solid #ddd !important;
        }

        .dataTables_wrapper .dataTable th.month-column {
            font-weight: 600 !important;
            text-align: left !important;
            vertical-align: middle !important;
            padding: 12px 15px !important;
            background-color: #f8f9fa !important;
            border-bottom: 1px solid #eee !important;
            border-right: 1px solid #ddd !important;
        }

        /* Ensure all table cells have proper borders */
        .dataTables_wrapper .dataTable td,
        .dataTables_wrapper .dataTable th {
            border-bottom: 1px solid #eee !important;
            border-right: 1px solid #ddd !important;
        }

        /* Remove right border from last column */
        .dataTables_wrapper .dataTable td:last-child,
        .dataTables_wrapper .dataTable th:last-child {
            border-right: none !important;
        }

        /* Hide or style sorting indicators */
        .dataTables_wrapper .dataTable th.sorting,
        .dataTables_wrapper .dataTable th.sorting_asc,
        .dataTables_wrapper .dataTable th.sorting_desc {
            background-image: none !important;
        }

        /* Optional: Add custom sorting indicators if needed */
        .dataTables_wrapper .dataTable th.sorting_asc::after {
            content: " ▲";
            color: var(--accent-color);
            font-weight: bold;
        }

        .dataTables_wrapper .dataTable th.sorting_desc::after {
            content: " ▼";
            color: var(--accent-color);
            font-weight: bold;
        }

        /* --- Drawer Navigation Styles --- */
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

        /* Tab Navigation Styles */
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

        /* Financial Breakdown inner tabs */
        .breakdown-tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .breakdown-tab-navigation a {
            padding: 10px 20px;
            background-color: #f3f4f6;
            color: var(--primary-color);
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s, color 0.3s;
        }

        .breakdown-tab-navigation a.active {
            background-color: var(--accent-color);
            color: var(--white);
        }

        .breakdown-tab-navigation a:hover:not(.active) {
            background-color: #e5e7eb;
        }

        .breakdown-tab-content .breakdown-pane {
            display: none;
        }

        .breakdown-tab-content .breakdown-pane.active {
            display: block;
        }

        /* Insights Tab Styles */
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

        /* DataTable Styling (matching member_contributions.php) */
        .dataTables_wrapper {
            width: 100%;
        }
        
        .dataTables_scroll {
            overflow-x: auto;
        }
        
        /* Ensure table doesn't move during initialization */
        #expensesTable {
            visibility: hidden;
            width: 100% !important;
            table-layout: fixed;
        }
        
        #expensesTable.dataTable {
            visibility: visible;
        }

        #expensesTable th,
        #expensesTable td {
            white-space: normal;
            word-break: break-word;
        }

        #monthly-expenses .table-responsive,
        #monthly-expenses .dataTables_wrapper {
            width: 100% !important;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #monthly-expenses > .card {
            max-width: 1320px;
            margin: 0 auto 30px;
        }

        #monthly-expenses .table-responsive {
            margin: 20px 0 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 0;
        }

        #monthly-expenses .table-responsive table {
            margin: 0;
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
                    <div class="role"><?php echo htmlspecialchars($user_profile['role'] ?? $_SESSION['user_role'] ?? 'Administrator'); ?></div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        <!-- Drawer Overlay -->
        <div id="drawer-overlay" class="drawer-overlay"></div>

        <main class="content-area">
            <div class="top-bar" style="background-color: #fff; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 20px; margin-top: 0;">
                <div>
                    <h2>Monthly Expenses</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>

            <div class="content">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-error">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <a href="#financial-breakdown" class="active" data-tab="financial-breakdown">Financial Report Breakdown</a>
                    <a href="#monthly-expenses" data-tab="monthly-expenses">Monthly Expenses</a>
                    <a href="#insights" data-tab="insights">Insights</a>
                </div>

                <div class="tab-content">
                    <!-- Financial Report Breakdown Tab -->
                    <div class="tab-pane active" id="financial-breakdown">
                        <!-- Action Button -->
                        <div class="card">
                            <div class="card-header breakdown-card-header">
                                <h2>Financial Report Breakdown</h2>
                                <div class="breakdown-header-actions">
                                    <form method="GET" action="" class="breakdown-search-form">
                                        <input type="date" name="breakdown_date" value="<?php echo htmlspecialchars($breakdownFilterDate); ?>">
                                        <?php foreach ($_GET as $paramKey => $paramValue): ?>
                                            <?php if ($paramKey === 'breakdown_date' || is_array($paramValue)) { continue; } ?>
                                            <input type="hidden" name="<?php echo htmlspecialchars($paramKey); ?>" value="<?php echo htmlspecialchars($paramValue); ?>">
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-secondary">Search</button>
                                        <?php if ($hasBreakdownFilter): ?>
                                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_expenses.php#financial-breakdown';">Clear</button>
                                        <?php endif; ?>
                                    </form>
                                    <button type="button" class="btn btn-primary" onclick="openBreakdownModal()">
                                <i class="fas fa-plus-circle"></i> Add Breakdown Entry
                            </button>
                                </div>
                        </div>

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
                                                    $incomeEntryPayload = htmlspecialchars(json_encode([
                                                        'id' => (int) $incomeEntry['id'],
                                                        'entry_date' => $incomeEntry['entry_date'],
                                                        'tithes' => (float) $incomeEntry['tithes'],
                                                        'offerings' => (float) $incomeEntry['offerings'],
                                                        'gifts_bank' => (float) $incomeEntry['gifts_bank'],
                                                        'bank_interest' => (float) $incomeEntry['bank_interest'],
                                                        'others' => (float) $incomeEntry['others'],
                                                        'building' => (float) $incomeEntry['building'],
                                                        'notes' => $incomeEntry['notes'] ?? ''
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
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
                                                        <div class="actions">
                                                            <button type="button" class="action-btn edit-btn" data-entry="<?php echo $incomeEntryPayload; ?>" onclick="handleIncomeEdit(this)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="action-btn delete-btn" onclick="confirmDeleteBreakdown(<?php echo intval($incomeEntry['id']); ?>, 'income')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                    </div>
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
                                        <?php if (!$hasBreakdownFilter && isset($breakdownPagination) && $breakdownPagination !== null && $breakdownPagination['total_pages'] > 1): ?>
                                            <div class="breakdown-pagination">
                                                <button type="button" class="btn btn-secondary" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] - 1; ?>)" <?php echo !$breakdownPagination['has_previous'] ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-chevron-left"></i> Previous
                                                </button>
                                                <span class="pagination-info">
                                                    Showing <?php echo count($displayIncomeEntries); ?> of <?php echo $breakdownPagination['total_income_entries']; ?> income entries 
                                                    (Page <?php echo ($breakdownPagination['current_page'] + 1); ?> of <?php echo $breakdownPagination['total_pages']; ?>)
                                                </span>
                                                <button type="button" class="btn btn-secondary" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] + 1; ?>)" <?php echo !$breakdownPagination['has_next'] ? 'disabled' : ''; ?>>
                                                    Next <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div style="text-align:center; color:#666; padding:25px; background:white; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.08);">
                                            <?php echo $hasBreakdownFilter ? 'No income breakdown entries found for the selected date.' : 'No income breakdown entries found.'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="breakdown-pane" id="breakdown-expenses">
                                    <?php if (!empty($displayExpenseEntries)): ?>
                                        <div class="expense-card-list">
                                            <?php foreach ($displayExpenseEntries as $expenseEntry): ?>
                                                <?php
                                                    $expenseEntryPayload = htmlspecialchars(json_encode([
                                                        'id' => (int) $expenseEntry['id'],
                                                        'entry_date' => $expenseEntry['entry_date'],
                                                        'speaker' => (float) $expenseEntry['speaker'],
                                                        'workers' => (float) $expenseEntry['workers'],
                                                        'food' => (float) $expenseEntry['food'],
                                                        'housekeeping' => (float) $expenseEntry['housekeeping'],
                                                        'office_supplies' => (float) $expenseEntry['office_supplies'],
                                                        'transportation' => (float) $expenseEntry['transportation'],
                                                        'photocopy' => (float) $expenseEntry['photocopy'],
                                                        'internet' => (float) $expenseEntry['internet'],
                                                        'government_concern' => (float) $expenseEntry['government_concern'],
                                                        'water_bill' => (float) $expenseEntry['water_bill'],
                                                        'electric_bill' => (float) $expenseEntry['electric_bill'],
                                                        'special_events' => (float) $expenseEntry['special_events'],
                                                        'needy_calamity' => (float) $expenseEntry['needy_calamity'],
                                                        'trainings' => (float) $expenseEntry['trainings'],
                                                        'kids_ministry' => (float) $expenseEntry['kids_ministry'],
                                                        'youth_ministry' => (float) $expenseEntry['youth_ministry'],
                                                        'music_ministry' => (float) $expenseEntry['music_ministry'],
                                                        'single_professionals_ministry' => (float) $expenseEntry['single_professionals_ministry'],
                                                        'young_couples_ministry' => (float) $expenseEntry['young_couples_ministry'],
                                                        'wow_ministry' => (float) $expenseEntry['wow_ministry'],
                                                        'amen_ministry' => (float) $expenseEntry['amen_ministry'],
                                                        'couples_ministry' => (float) $expenseEntry['couples_ministry'],
                                                        'visitation_prayer_ministry' => (float) $expenseEntry['visitation_prayer_ministry'],
                                                        'acquisitions' => (float) $expenseEntry['acquisitions'],
                                                        'materials' => (float) $expenseEntry['materials'],
                                                        'labor' => (float) $expenseEntry['labor'],
                                                        'mission_support' => (float) $expenseEntry['mission_support'],
                                                        'land_title' => (float) $expenseEntry['land_title'],
                                                        'notes' => $expenseEntry['notes'] ?? ''
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <div class="expense-card">
                                                    <div class="card-header">
                                                        <div class="date"><?php echo htmlspecialchars(date('F d, Y', strtotime($expenseEntry['entry_date']))); ?></div>
                                                        <div class="actions">
                                                            <button type="button" class="action-btn edit-btn" data-entry="<?php echo $expenseEntryPayload; ?>" onclick="handleExpenseEdit(this)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="action-btn delete-btn" onclick="confirmDeleteBreakdown(<?php echo intval($expenseEntry['id']); ?>, 'expense')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                    </div>
                                </div>
                                                    <div class="card-body">
                                                        <?php
                                                            $expenseFields = [
                                                                'Speaker' => $expenseEntry['speaker'],
                                                                'Workers' => $expenseEntry['workers'],
                                                                'Food' => $expenseEntry['food'],
                                                                'House Keeping' => $expenseEntry['housekeeping'],
                                                                'Office Supplies' => $expenseEntry['office_supplies'],
                                                                'Transportation' => $expenseEntry['transportation'],
                                                                'Photocopy' => $expenseEntry['photocopy'],
                                                                'Internet' => $expenseEntry['internet'],
                                                                'Government Concern' => $expenseEntry['government_concern'],
                                                                'Water Bill' => $expenseEntry['water_bill'],
                                                                'Electric Bill' => $expenseEntry['electric_bill'],
                                                                'Special Events' => $expenseEntry['special_events'],
                                                                'Needy / Calamity / Emergency' => $expenseEntry['needy_calamity'],
                                                                'Trainings' => $expenseEntry['trainings'],
                                                                'Kids Ministry' => $expenseEntry['kids_ministry'],
                                                                'Youth Ministry' => $expenseEntry['youth_ministry'],
                                                                'Music Ministry' => $expenseEntry['music_ministry'],
                                                                'Single Professionals Ministry' => $expenseEntry['single_professionals_ministry'],
                                                                'Young Couples Ministry' => $expenseEntry['young_couples_ministry'],
                                                                'WOW Ministry' => $expenseEntry['wow_ministry'],
                                                                'AMEN Ministry' => $expenseEntry['amen_ministry'],
                                                                'Couples Ministry' => $expenseEntry['couples_ministry'],
                                                                'Visitation / Prayer Ministry' => $expenseEntry['visitation_prayer_ministry'],
                                                                'Acquisitions' => $expenseEntry['acquisitions'],
                                                                'Materials' => $expenseEntry['materials'],
                                                                'Labor' => $expenseEntry['labor'],
                                                                'Mission Support' => $expenseEntry['mission_support'],
                                                                'Land Title' => $expenseEntry['land_title']
                                                            ];

                                                            $hasVisibleExpense = false;
                                                            foreach ($expenseFields as $value) {
                                                                if (floatval($value) > 0) {
                                                                    $hasVisibleExpense = true;
                                                                    break;
                                                                }
                                                            }
                                                        ?>
                                                        <?php if ($hasVisibleExpense): ?>
                                                            <?php foreach ($expenseFields as $label => $value): ?>
                                                                <?php if (floatval($value) > 0): ?>
                                                                    <div class="expense-item">
                                                                        <span class="label"><?php echo $label; ?></span>
                                                                        <span class="value">₱<?php echo number_format($value, 2); ?></span>
                            </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <div class="expense-item">
                                                                <span class="label">Expenses</span>
                                                                <span class="value">No individual expense categories recorded.</span>
                        </div>
                                                        <?php endif; ?>
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
                                        <?php if (!$hasBreakdownFilter && isset($breakdownPagination) && $breakdownPagination !== null && $breakdownPagination['total_pages'] > 1): ?>
                                            <div class="breakdown-pagination">
                                                <button type="button" class="btn btn-secondary" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] - 1; ?>)" <?php echo !$breakdownPagination['has_previous'] ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-chevron-left"></i> Previous
                                                </button>
                                                <span class="pagination-info">
                                                    Showing <?php echo count($displayExpenseEntries); ?> of <?php echo $breakdownPagination['total_expense_entries']; ?> expense entries 
                                                    (Page <?php echo ($breakdownPagination['current_page'] + 1); ?> of <?php echo $breakdownPagination['total_pages']; ?>)
                                                </span>
                                                <button type="button" class="btn btn-secondary" onclick="navigateBreakdownPage(<?php echo $breakdownPagination['current_page'] + 1; ?>)" <?php echo !$breakdownPagination['has_next'] ? 'disabled' : ''; ?>>
                                                    Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div style="text-align:center; color:#666; padding:25px; background:white; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.08);">
                                            <?php echo $hasBreakdownFilter ? 'No expense breakdown entries found for the selected date.' : 'No expense breakdown entries found.'; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                        $expenseTotalsLabels = [
                                            'speaker' => 'Speaker',
                                            'workers' => 'Workers',
                                            'food' => 'Food',
                                            'housekeeping' => 'House Keeping',
                                            'office_supplies' => 'Office Supplies',
                                            'transportation' => 'Transportation',
                                            'photocopy' => 'Photocopy',
                                            'internet' => 'Internet',
                                            'government_concern' => 'Government Concern',
                                            'water_bill' => 'Water Bill',
                                            'electric_bill' => 'Electric Bill',
                                            'special_events' => 'Special Events',
                                            'needy_calamity' => 'Needy / Calamity / Emergency',
                                            'trainings' => 'Trainings',
                                            'kids_ministry' => 'Kids Ministry',
                                            'youth_ministry' => 'Youth Ministry',
                                            'music_ministry' => 'Music Ministry',
                                            'single_professionals_ministry' => 'Single Professionals Ministry',
                                            'young_couples_ministry' => 'Young Couples Ministry',
                                            'wow_ministry' => 'WOW Ministry',
                                            'amen_ministry' => 'AMEN Ministry',
                                            'couples_ministry' => 'Couples Ministry',
                                            'visitation_prayer_ministry' => 'Visitation / Prayer Ministry',
                                            'acquisitions' => 'Acquisitions',
                                            'materials' => 'Materials',
                                            'labor' => 'Labor',
                                            'mission_support' => 'Mission Support',
                                            'land_title' => 'Land Title'
                                        ];
                                        // Totals retained for future use even though summary display is removed
                                    ?>
                                 </div>
                            </div>
                        </div>
                        </div>

                    <!-- Monthly Expenses Tab -->
                    <div class="tab-pane" id="monthly-expenses">
                        <!-- Expenses Table -->
                        <div class="card">
                            <h2>Monthly Financial Summary</h2>
                            <div class="table-responsive">
                                <table id="expensesTable">
                                    <thead>
                                        <tr>
                                            <th class="month-column">Month</th>
                                            <th>Income (₱)</th>
                                            <th>Expenses (₱)</th>
                                            <th>Difference (₱)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($expenses_data)): ?>
                                        <?php foreach ($expenses_data as $row): ?>
                                        <tr>
                                            <td class="month-column" data-order="<?php echo strtotime($row['month'] . '-01'); ?>"><strong><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></strong></td>
                                            <td class="<?php echo $row['income'] > $row['expenses'] ? 'positive-income' : ''; ?>">₱<?php echo number_format($row['income'], 2); ?></td>
                                            <td class="<?php echo $row['expenses'] > $row['income'] ? 'negative-expenses' : ''; ?>">₱<?php echo number_format($row['expenses'], 2); ?></td>
                                            <td class="<?php echo $row['expenses'] > $row['income'] ? 'negative-expenses' : ($row['difference'] >= 0 ? 'positive-difference' : 'negative-difference'); ?>">
                                                ₱<?php echo number_format($row['difference'], 2); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <?php if (!empty($expenses_data)): ?>
                                    <tfoot>
                                        <tr class="summary-row">
                                            <td><strong>AVERAGE</strong></td>
                                            <td><strong>₱<?php echo number_format($averages['avg_income'], 2); ?></strong></td>
                                            <td><strong>₱<?php echo number_format($averages['avg_expenses'], 2); ?></strong></td>
                                            <td class="<?php echo $averages['avg_expenses'] > $averages['avg_income'] ? 'negative-expenses' : ($averages['avg_difference'] >= 0 ? 'positive-difference' : 'negative-difference'); ?>">
                                                <strong>₱<?php echo number_format($averages['avg_difference'], 2); ?></strong>
                                            </td>
                                        </tr>
                                    </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Insights Tab -->
                    <div class="tab-pane" id="insights">
                        <div class="max-w-sm w-full bg-white rounded-lg shadow-sm dark:bg-gray-800 p-4 md:p-6">
                            <div class="mb-4">
                                <div>
                                    <h5 class="leading-none text-2xl font-bold text-gray-900 dark:text-white pb-1">₱<?php echo number_format($totals['total_income'], 2); ?></h5>
                                    <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Total Income</p>
                                </div>
                            </div>
                            <div id="legend-chart" style="margin-top: 20px;"></div>
                        </div>
                    </div>
        
        </div>
            </div>
        </main>
        </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteBreakdownModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close" onclick="closeDeleteBreakdownModal()">&times;</span>
            </div>
            <form method="POST" action="" id="deleteBreakdownForm">
                <input type="hidden" name="entry_id" id="delete_entry_id">
                <input type="hidden" name="entry_type" id="delete_entry_type">
                <p style="margin: 20px 0; font-size: 16px;">Are you sure you want to delete this entry? This action cannot be undone.</p>
                <div class="form-actions">
                    <button type="submit" name="delete_breakdown_entry" class="btn btn-primary" style="background-color: #f44336;">Yes, Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteBreakdownModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Breakdown Entry Modal -->
    <div id="breakdownModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="breakdownModalTitle">Add Breakdown Entry</h3>
                <span class="close" onclick="closeBreakdownModal()">&times;</span>
            </div>
            <form method="POST" action="" class="expense-form" id="breakdownForm">
                <input type="hidden" id="breakdown_action" name="breakdown_action" value="add">
                <input type="hidden" id="breakdown_entry_id" name="breakdown_entry_id">
                <input type="hidden" id="original_breakdown_type" name="original_breakdown_type" value="income">
                <div class="form-group">
                    <label for="breakdown_date">Date</label>
                    <input type="date" id="breakdown_date" name="breakdown_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="breakdown_type">Type</label>
                    <select id="breakdown_type" name="breakdown_type" class="form-control" required>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>

                <div id="income-fields" class="breakdown-type-section">
                <div class="form-group">
                        <label for="income_tithes">Tithes (₱)</label>
                        <input type="number" id="income_tithes" name="income_tithes" class="form-control" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                        <label for="income_offerings">Offerings (₱)</label>
                        <input type="number" id="income_offerings" name="income_offerings" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="income_gifts_bank">Gifts Received through Bank (₱)</label>
                        <input type="number" id="income_gifts_bank" name="income_gifts_bank" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="income_bank_interest">Bank Interest (₱)</label>
                        <input type="number" id="income_bank_interest" name="income_bank_interest" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="income_others">Others (e.g., wedding, dedication, etc.) (₱)</label>
                        <input type="number" id="income_others" name="income_others" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="income_building">Building (₱)</label>
                        <input type="number" id="income_building" name="income_building" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>

                <div id="expense-fields" class="breakdown-type-section" style="display:none;">
                    <div class="category-grid">
                    <div class="form-group">
                            <label for="expense_speaker">Speaker (₱)</label>
                            <input type="number" id="expense_speaker" name="expense_speaker" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_workers">Workers (₱)</label>
                            <input type="number" id="expense_workers" name="expense_workers" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_food">Food (₱)</label>
                            <input type="number" id="expense_food" name="expense_food" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_housekeeping">House Keeping (₱)</label>
                            <input type="number" id="expense_housekeeping" name="expense_housekeeping" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_office_supplies">Office Supplies (₱)</label>
                            <input type="number" id="expense_office_supplies" name="expense_office_supplies" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_transportation">Transportation (₱)</label>
                            <input type="number" id="expense_transportation" name="expense_transportation" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_photocopy">Photocopy (₱)</label>
                            <input type="number" id="expense_photocopy" name="expense_photocopy" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_internet">Internet (₱)</label>
                            <input type="number" id="expense_internet" name="expense_internet" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_government_concern">Government Concern (₱)</label>
                            <input type="number" id="expense_government_concern" name="expense_government_concern" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_water_bill">Water Bill (₱)</label>
                            <input type="number" id="expense_water_bill" name="expense_water_bill" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_electric_bill">Electric Bill (₱)</label>
                            <input type="number" id="expense_electric_bill" name="expense_electric_bill" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_special_events">Special Events (₱)</label>
                            <input type="number" id="expense_special_events" name="expense_special_events" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_needy_calamity">Needy/Calamity/Emergency (₱)</label>
                            <input type="number" id="expense_needy_calamity" name="expense_needy_calamity" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_trainings">Trainings (₱)</label>
                            <input type="number" id="expense_trainings" name="expense_trainings" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_kids_ministry">Kids Ministry (₱)</label>
                            <input type="number" id="expense_kids_ministry" name="expense_kids_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_youth_ministry">Youth Ministry (₱)</label>
                            <input type="number" id="expense_youth_ministry" name="expense_youth_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_music_ministry">Music Ministry (₱)</label>
                            <input type="number" id="expense_music_ministry" name="expense_music_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_single_professionals_ministry">Single Professionals Ministry (₱)</label>
                            <input type="number" id="expense_single_professionals_ministry" name="expense_single_professionals_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_young_couples_ministry">Young Couples Ministry (₱)</label>
                            <input type="number" id="expense_young_couples_ministry" name="expense_young_couples_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_wow_ministry">WOW Ministry (₱)</label>
                            <input type="number" id="expense_wow_ministry" name="expense_wow_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_amen_ministry">AMEN Ministry (₱)</label>
                            <input type="number" id="expense_amen_ministry" name="expense_amen_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_couples_ministry">Couples Ministry (₱)</label>
                            <input type="number" id="expense_couples_ministry" name="expense_couples_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_visitation_prayer_ministry">Visitation / Prayer Ministry (₱)</label>
                            <input type="number" id="expense_visitation_prayer_ministry" name="expense_visitation_prayer_ministry" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_acquisitions">Acquisitions (₱)</label>
                            <input type="number" id="expense_acquisitions" name="expense_acquisitions" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_materials">Materials (₱)</label>
                            <input type="number" id="expense_materials" name="expense_materials" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_labor">Labor (₱)</label>
                            <input type="number" id="expense_labor" name="expense_labor" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_mission_support">Mission Support (₱)</label>
                            <input type="number" id="expense_mission_support" name="expense_mission_support" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="expense_land_title">Land Title (₱)</label>
                            <input type="number" id="expense_land_title" name="expense_land_title" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="breakdown_notes">Notes</label>
                    <textarea id="breakdown_notes" name="breakdown_notes" class="form-control" placeholder="Optional notes..." rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" id="breakdownSubmitBtn" class="btn btn-primary">Save Entry</button>
                    <button type="button" class="btn btn-secondary" onclick="closeBreakdownModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        let breakdownIncomeTable;
        // let breakdownIncomeTable;

        function toggleBreakdownTypeFields() {
            const typeInput = document.getElementById('breakdown_type');
            const incomeSection = document.getElementById('income-fields');
            const expenseSection = document.getElementById('expense-fields');
            const type = typeInput ? typeInput.value : 'income';

            if (incomeSection) {
                incomeSection.style.display = type === 'income' ? 'block' : 'none';
            }
            if (expenseSection) {
                expenseSection.style.display = type === 'income' ? 'none' : 'block';
            }

            const incomeFieldIds = [
                'income_tithes',
                'income_offerings',
                'income_gifts_bank',
                'income_bank_interest',
                'income_others',
                'income_building'
            ];
            const expenseFieldIds = [
                'expense_speaker',
                'expense_workers',
                'expense_food',
                'expense_housekeeping',
                'expense_office_supplies',
                'expense_transportation',
                'expense_photocopy',
                'expense_internet',
                'expense_government_concern',
                'expense_water_bill',
                'expense_electric_bill',
                'expense_special_events',
                'expense_needy_calamity',
                'expense_trainings',
                'expense_kids_ministry',
                'expense_youth_ministry',
                'expense_music_ministry',
                'expense_single_professionals_ministry',
                'expense_young_couples_ministry',
                'expense_wow_ministry',
                'expense_amen_ministry',
                'expense_couples_ministry',
                'expense_visitation_prayer_ministry',
                'expense_acquisitions',
                'expense_materials',
                'expense_labor',
                'expense_mission_support',
                'expense_land_title'
            ];

            incomeFieldIds.forEach(id => {
                const field = document.getElementById(id);
                if (!field) {
                    return;
                }
                if (type === 'income') {
                    field.disabled = false;
                } else {
                    field.value = '';
                    field.disabled = true;
                }
            });

            expenseFieldIds.forEach(id => {
                const field = document.getElementById(id);
                if (!field) {
                    return;
                }
                if (type === 'expense') {
                    field.disabled = false;
                } else {
                    field.value = '';
                    field.disabled = true;
                }
            });
        }

        $(document).ready(function() {
            $('#expensesTable').DataTable({
                columnDefs: [
                    { width: '30%', targets: 0 }, // Month
                    { width: '23%', targets: 1 }, // Income
                    { width: '23%', targets: 2 }, // Expenses
                    { width: '24%', targets: 3 }  // Difference
                ],
                autoWidth: false,
                responsive: true,
                scrollX: true,
                order: [[0, 'asc']], // Sort by first column (Month) in ascending order
                orderClasses: false,
                language: {
                    emptyTable: 'No monthly summary data available yet. Add income and expense breakdown entries to populate this table.'
                }
            });

            const breakdownTypeSelect = document.getElementById('breakdown_type');
            if (breakdownTypeSelect) {
                breakdownTypeSelect.addEventListener('change', function () {
                    if (this.dataset.locked === 'true') {
                        const lockedValue = this.dataset.lockedValue || this.value;
                        if (this.value !== lockedValue) {
                            alert('Cannot change the entry type while editing. Please delete and recreate the entry if needed.');
                            this.value = lockedValue;
                        }
                    }
                    toggleBreakdownTypeFields();
                });
                toggleBreakdownTypeFields();
            }
        });

        function openBreakdownModal(options) {
            const config = options || {};
            const mode = config.mode || 'add';
            const entryType = config.type || 'income';
            const entry = config.entry || null;

            const modal = document.getElementById('breakdownModal');
            if (modal) {
                modal.style.display = 'block';
            }
            document.body.style.overflow = 'hidden';

            const form = document.getElementById('breakdownForm');
            if (form) {
                form.reset();
            }

            const actionInput = document.getElementById('breakdown_action');
            if (actionInput) {
                actionInput.value = mode === 'edit' ? 'update' : 'add';
            }

            const entryIdInput = document.getElementById('breakdown_entry_id');
            if (entryIdInput) {
                entryIdInput.value = entry && entry.id ? entry.id : '';
                }

            const originalTypeInput = document.getElementById('original_breakdown_type');
            if (originalTypeInput) {
                originalTypeInput.value = entryType;
            }

            const typeInput = document.getElementById('breakdown_type');
            if (typeInput) {
                typeInput.value = entryType;
                if (mode === 'edit') {
                    typeInput.dataset.locked = 'true';
                    typeInput.dataset.lockedValue = entryType;
                } else {
                    delete typeInput.dataset.locked;
                    delete typeInput.dataset.lockedValue;
                }
            }

            const submitBtn = document.getElementById('breakdownSubmitBtn');
            if (submitBtn) {
                submitBtn.textContent = mode === 'edit' ? 'Update Entry' : 'Save Entry';
            }

            const modalTitle = document.getElementById('breakdownModalTitle');
            if (modalTitle) {
                modalTitle.textContent = mode === 'edit' ? 'Edit Breakdown Entry' : 'Add Breakdown Entry';
            }

            const today = new Date();
            const formattedToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            const dateInput = document.getElementById('breakdown_date');
            if (dateInput) {
                if (entry && entry.entry_date) {
                    dateInput.value = entry.entry_date;
                } else {
                    dateInput.value = formattedToday;
                }
            }

            const notesInput = document.getElementById('breakdown_notes');
            if (notesInput) {
                notesInput.value = entry && entry.notes ? entry.notes : '';
            }

            toggleBreakdownTypeFields();

            if (entry) {
            if (entryType === 'income') {
                    const incomeFields = {
                        income_tithes: entry.tithes ?? 0,
                        income_offerings: entry.offerings ?? 0,
                        income_gifts_bank: entry.gifts_bank ?? 0,
                        income_bank_interest: entry.bank_interest ?? 0,
                        income_others: entry.others ?? 0,
                        income_building: entry.building ?? 0
                };
                    Object.keys(incomeFields).forEach(id => {
                        const field = document.getElementById(id);
                        if (field) {
                            field.value = incomeFields[id];
                        }
                    });
            } else {
                    const expenseFields = {
                        expense_speaker: entry.speaker ?? 0,
                        expense_workers: entry.workers ?? 0,
                        expense_food: entry.food ?? 0,
                        expense_housekeeping: entry.housekeeping ?? 0,
                        expense_office_supplies: entry.office_supplies ?? 0,
                        expense_transportation: entry.transportation ?? 0,
                        expense_photocopy: entry.photocopy ?? 0,
                        expense_internet: entry.internet ?? 0,
                        expense_government_concern: entry.government_concern ?? 0,
                        expense_water_bill: entry.water_bill ?? 0,
                        expense_electric_bill: entry.electric_bill ?? 0,
                        expense_special_events: entry.special_events ?? 0,
                        expense_needy_calamity: entry.needy_calamity ?? 0,
                        expense_trainings: entry.trainings ?? 0,
                        expense_kids_ministry: entry.kids_ministry ?? 0,
                        expense_youth_ministry: entry.youth_ministry ?? 0,
                        expense_music_ministry: entry.music_ministry ?? 0,
                        expense_single_professionals_ministry: entry.single_professionals_ministry ?? 0,
                        expense_young_couples_ministry: entry.young_couples_ministry ?? 0,
                        expense_wow_ministry: entry.wow_ministry ?? 0,
                        expense_amen_ministry: entry.amen_ministry ?? 0,
                        expense_couples_ministry: entry.couples_ministry ?? 0,
                        expense_visitation_prayer_ministry: entry.visitation_prayer_ministry ?? 0,
                        expense_acquisitions: entry.acquisitions ?? 0,
                        expense_materials: entry.materials ?? 0,
                        expense_labor: entry.labor ?? 0,
                        expense_mission_support: entry.mission_support ?? 0,
                        expense_land_title: entry.land_title ?? 0
                    };
                    Object.keys(expenseFields).forEach(id => {
                        const field = document.getElementById(id);
                        if (field) {
                            field.value = expenseFields[id];
                }
                    });
                }
            }
        }

        function closeBreakdownModal() {
            const modal = document.getElementById('breakdownModal');
            if (modal) {
                modal.style.display = 'none';
            }
            document.body.style.overflow = '';

            const form = document.getElementById('breakdownForm');
            if (form) {
                form.reset();
            }

            const actionInput = document.getElementById('breakdown_action');
            if (actionInput) {
                actionInput.value = 'add';
            }

            const entryIdInput = document.getElementById('breakdown_entry_id');
            if (entryIdInput) {
                entryIdInput.value = '';
            }

            const typeInput = document.getElementById('breakdown_type');
            if (typeInput) {
                delete typeInput.dataset.locked;
                delete typeInput.dataset.lockedValue;
                typeInput.value = 'income';
            }

            const originalTypeInput = document.getElementById('original_breakdown_type');
            if (originalTypeInput) {
                originalTypeInput.value = 'income';
                }

            const submitBtn = document.getElementById('breakdownSubmitBtn');
            if (submitBtn) {
                submitBtn.textContent = 'Save Entry';
            }

            const modalTitle = document.getElementById('breakdownModalTitle');
            if (modalTitle) {
                modalTitle.textContent = 'Add Breakdown Entry';
            }

            const today = new Date();
            const formattedToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            const dateInput = document.getElementById('breakdown_date');
            if (dateInput) {
                dateInput.value = formattedToday;
            }

            toggleBreakdownTypeFields();
        }

        // Delete Breakdown Modal Functions
        function confirmDeleteBreakdown(id, type) {
            document.getElementById('delete_entry_id').value = id;
            document.getElementById('delete_entry_type').value = type;
            document.getElementById('deleteBreakdownModal').style.display = 'block';
        }

        function closeDeleteBreakdownModal() {
            document.getElementById('deleteBreakdownModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const expenseModal = document.getElementById('expenseModal');
            const deleteModal = document.getElementById('deleteModal');
            const breakdownModal = document.getElementById('breakdownModal');
            const deleteBreakdownModal = document.getElementById('deleteBreakdownModal');
            
            if (event.target == expenseModal) {
                expenseModal.style.display = 'none';
            }
            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
            }
            if (event.target == breakdownModal) {
                closeBreakdownModal();
            }
            if (event.target == deleteBreakdownModal) {
                deleteBreakdownModal.style.display = 'none';
            }
        }

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

        // Tab Navigation JS
        document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.tab-navigation a').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.tab-navigation a').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                    const tabPane = document.getElementById(tabId);
                    if (tabPane) {
                        tabPane.classList.add('active');
                    }
                });
            });

            const breakdownTabs = document.querySelectorAll('.breakdown-tab-navigation a');
            const breakdownPanes = document.querySelectorAll('.breakdown-tab-content .breakdown-pane');

            breakdownTabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    breakdownTabs.forEach(t => t.classList.remove('active'));
                    breakdownPanes.forEach(pane => pane.classList.remove('active'));

                    this.classList.add('active');
                    const target = this.getAttribute('data-breakdown-tab');
                    const pane = document.getElementById(`breakdown-${target}`);
                    if (pane) {
                        pane.classList.add('active');
                    }
                    
                    // Update URL parameter to preserve breakdown tab state
                    const url = new URL(window.location.href);
                    url.searchParams.set('breakdown_tab', target);
                    // Preserve the hash (main tab) if it exists
                    const hash = window.location.hash || '#financial-breakdown';
                    window.history.replaceState({}, '', url.toString() + hash);
                });
            });
            
            // Restore breakdown tab state from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const breakdownTabParam = urlParams.get('breakdown_tab');
            if (breakdownTabParam === 'expenses' || breakdownTabParam === 'income') {
                breakdownTabs.forEach(t => t.classList.remove('active'));
                breakdownPanes.forEach(pane => pane.classList.remove('active'));
                
                const targetTab = document.querySelector(`.breakdown-tab-navigation a[data-breakdown-tab="${breakdownTabParam}"]`);
                const targetPane = document.getElementById(`breakdown-${breakdownTabParam}`);
                
                if (targetTab && targetPane) {
                    targetTab.classList.add('active');
                    targetPane.classList.add('active');
                }
            }
        });

        // Breakdown pagination navigation
        function navigateBreakdownPage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('breakdown_page', page);
            // Remove breakdown_date if it exists (pagination overrides date filter)
            url.searchParams.delete('breakdown_date');
            
            // Detect which breakdown tab is currently active (income or expenses)
            const activeBreakdownTab = document.querySelector('.breakdown-tab-navigation a.active');
            const breakdownTab = activeBreakdownTab ? activeBreakdownTab.getAttribute('data-breakdown-tab') : 'income';
            
            // Preserve the breakdown tab in URL parameter
            url.searchParams.set('breakdown_tab', breakdownTab);
            
            // Preserve the hash (main tab) if it exists
            const hash = window.location.hash || '#financial-breakdown';
            window.location.href = url.toString() + hash;
        }

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

        

        // Chart Configuration
        <?php
        // Prepare data for the chart
        $chart_income = [];
        $chart_expenses = [];
        $chart_categories = [];
        
        // Get the last 6 months of data for the chart
        $chart_data = array_slice($expenses_data, -6);
        
        foreach ($chart_data as $row) {
            $chart_income[] = $row['income'];
            $chart_expenses[] = $row['expenses'];
            $chart_categories[] = date('M Y', strtotime($row['month'] . '-01'));
        }
        ?>

        const options = {
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
                height: "100%",
                maxWidth: "100%",
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
        }

        if (document.getElementById("legend-chart") && typeof ApexCharts !== 'undefined') {
            const chart = new ApexCharts(document.getElementById("legend-chart"), options);
            chart.render();
        }

        function handleIncomeEdit(button) {
            if (!button) {
                return;
            }
            const payload = button.getAttribute('data-entry');
            if (!payload) {
                return;
            }
            try {
                const entry = JSON.parse(payload);
                openBreakdownModal({
                    mode: 'edit',
                    type: 'income',
                    entry
                });
            } catch (error) {
                console.error('Failed to parse income entry payload', error);
            }
        }

        function handleExpenseEdit(button) {
            if (!button) {
                return;
            }
            const payload = button.getAttribute('data-entry');
            if (!payload) {
                return;
            }
            try {
                const entry = JSON.parse(payload);
                openBreakdownModal({
                    mode: 'edit',
                    type: 'expense',
                    entry
                });
            } catch (error) {
                console.error('Failed to parse expense entry payload', error);
            }
        }
    </script>
<?php if ($hasBreakdownFilter): ?>
    <script>
        window.addEventListener('load', function () {
            if (location.hash !== '#financial-breakdown') {
                location.hash = 'financial-breakdown';
            }
        });
    </script>
<?php endif; ?>
</body>
</html> 