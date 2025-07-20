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

// Handle form submission for new expense entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
    $month = $_POST['month'];
    $income = floatval($_POST['income']);
    $expenses = floatval($_POST['expenses']);
    $notes = $_POST['notes'];
    
    // Check if entry for this month already exists
    $check_stmt = $conn->prepare("SELECT id FROM monthly_expenses WHERE month = ?");
    $check_stmt->bind_param("s", $month);
    $check_stmt->execute();
    $existing = $check_stmt->get_result();
    
    if ($existing->num_rows > 0) {
        $_SESSION['error_message'] = "An entry for this month already exists. Please update the existing entry.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO monthly_expenses (
                month, income, expenses, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sddss", $month, $income, $expenses, $notes, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Monthly expense entry added successfully!";
            header("Location: admin_expenses.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding expense entry. Please try again.";
        }
    }
}

// Handle form submission for updating expense entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_expense'])) {
    $expense_id = $_POST['expense_id'];
    $income = floatval($_POST['income']);
    $expenses = floatval($_POST['expenses']);
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("
        UPDATE monthly_expenses 
        SET income = ?, expenses = ?, notes = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("ddsi", $income, $expenses, $notes, $expense_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Expense entry updated successfully!";
        header("Location: admin_expenses.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating expense entry. Please try again.";
    }
}

// Handle deletion of expense entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expense'])) {
    $expense_id = $_POST['expense_id'];
    
    $stmt = $conn->prepare("DELETE FROM monthly_expenses WHERE id = ?");
    $stmt->bind_param("i", $expense_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Expense entry deleted successfully!";
        header("Location: admin_expenses.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error deleting expense entry. Please try again.";
    }
}

// Get all monthly expenses
$expenses_query = "
    SELECT 
        me.id,
        me.month,
        me.income,
        me.expenses,
        (me.income - me.expenses) as difference,
        me.notes,
        me.created_at,
        me.updated_at,
        up.full_name as created_by_name
    FROM monthly_expenses me
    LEFT JOIN user_profiles up ON me.created_by = up.user_id
    ORDER BY me.month ASC
";
$all_expenses = $conn->query($expenses_query);

// Calculate totals and averages
$totals = [
    'total_income' => 0,
    'total_expenses' => 0,
    'total_difference' => 0,
    'count' => 0
];

$expenses_data = [];
if ($all_expenses) {
    while ($row = $all_expenses->fetch_assoc()) {
        $expenses_data[] = $row;
        $totals['total_income'] += $row['income'];
        $totals['total_expenses'] += $row['expenses'];
        $totals['total_difference'] += $row['difference'];
        $totals['count']++;
    }
}

// Calculate averages
$averages = [
    'avg_income' => $totals['count'] > 0 ? $totals['total_income'] / $totals['count'] : 0,
    'avg_expenses' => $totals['count'] > 0 ? $totals['total_expenses'] / $totals['count'] : 0,
    'avg_difference' => $totals['count'] > 0 ? $totals['total_difference'] / $totals['count'] : 0
];

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

        .card h2 {
            margin-bottom: 20px;
            color: var(--primary-color);
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
            }

            th, td {
                padding: 10px;
                font-size: 14px;
            }
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
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
        }
        
        #expensesTable.dataTable {
            visibility: visible;
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
        }
        .drawer-profile .role {
            font-size: 13px;
            color: var(--accent-color);
            font-weight: 500;
            margin-top: 2px;
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
        }
        
        #expensesTable.dataTable {
            visibility: visible;
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
                        <?php echo strtoupper(substr($user_profile['username'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <div class="name"><?php echo htmlspecialchars($user_profile['username'] ?? 'Unknown User'); ?></div>
                    <div class="role"><?php echo htmlspecialchars($_SESSION['user_role']); ?></div>
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
                    <a href="#monthly-expenses" class="active" data-tab="monthly-expenses">Monthly Expenses</a>
                    <a href="#insights" data-tab="insights">Insights</a>
                </div>

                <div class="tab-content">
                    <!-- Monthly Expenses Tab -->
                    <div class="tab-pane active" id="monthly-expenses">
                        <!-- Action Button -->
                        <div class="action-bar">
                            <button class="btn btn-primary" onclick="openExpenseModal()">
                                <i class="fas fa-plus-circle"></i> Add Monthly Expense
                            </button>
                        </div>

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
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses_data as $row): ?>
                                        <tr>
                                            <td class="month-column" data-order="<?php echo strtotime($row['month'] . '-01'); ?>"><strong><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></strong></td>
                                            <td class="<?php echo $row['income'] > $row['expenses'] ? 'positive-income' : ''; ?>">₱<?php echo number_format($row['income'], 2); ?></td>
                                            <td class="<?php echo $row['expenses'] > $row['income'] ? 'negative-expenses' : ''; ?>">₱<?php echo number_format($row['expenses'], 2); ?></td>
                                            <td class="<?php echo $row['expenses'] > $row['income'] ? 'negative-expenses' : ($row['difference'] >= 0 ? 'positive-difference' : 'negative-difference'); ?>">
                                                ₱<?php echo number_format($row['difference'], 2); ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn edit-btn" onclick="editExpense(<?php echo $row['id']; ?>, '<?php echo $row['month']; ?>', <?php echo $row['income']; ?>, <?php echo $row['expenses']; ?>, '<?php echo htmlspecialchars($row['notes']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="action-btn delete-btn" onclick="deleteExpense(<?php echo $row['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="summary-row">
                                            <td><strong>AVERAGE</strong></td>
                                            <td><strong>₱<?php echo number_format($averages['avg_income'], 2); ?></strong></td>
                                            <td><strong>₱<?php echo number_format($averages['avg_expenses'], 2); ?></strong></td>
                                            <td class="<?php echo $averages['avg_expenses'] > $averages['avg_income'] ? 'negative-expenses' : ($averages['avg_difference'] >= 0 ? 'positive-difference' : 'negative-difference'); ?>">
                                                <strong>₱<?php echo number_format($averages['avg_difference'], 2); ?></strong>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
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

    <!-- Add Expense Modal -->
    <div id="expenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Monthly Expense</h3>
                <span class="close" onclick="closeExpenseModal()">&times;</span>
            </div>
            <form method="POST" action="" class="expense-form" id="expenseForm">
                <input type="hidden" id="expense_id" name="expense_id">
                <div class="form-group">
                    <label for="month">Month</label>
                    <input type="month" id="month" name="month" required>
                </div>
                <div class="form-group">
                    <label for="income">Income (₱)</label>
                    <input type="number" id="income" name="income" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="expenses">Expenses (₱)</label>
                    <input type="number" id="expenses" name="expenses" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Optional notes about this month's finances..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="submit_expense" id="submitBtn" class="btn btn-primary">Add Expense</button>
                    <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div style="padding: 20px;">
                <p>Are you sure you want to delete this expense entry? This action cannot be undone.</p>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" id="delete_expense_id" name="expense_id">
                    <div class="form-actions">
                        <button type="submit" name="delete_expense" class="btn btn-danger">Delete</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        $(document).ready(function() {
            $('#expensesTable').DataTable({
                columnDefs: [
                    { width: '20%', targets: 0 }, // Month
                    { width: '20%', targets: 1 }, // Income
                    { width: '20%', targets: 2 }, // Expenses
                    { width: '20%', targets: 3 }, // Difference
                    { width: '20%', targets: 4 }  // Actions
                ],
                autoWidth: false,
                responsive: true,
                order: [[0, 'asc']], // Sort by first column (Month) in ascending order
                orderClasses: false
            });
        });

        function openExpenseModal() {
            document.getElementById('modalTitle').textContent = 'Add Monthly Expense';
            document.getElementById('expenseForm').reset();
            document.getElementById('expense_id').value = '';
            document.getElementById('submitBtn').textContent = 'Add Expense';
            document.getElementById('submitBtn').name = 'submit_expense';
            document.getElementById('expenseModal').style.display = 'block';
            
            // Set default month to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            document.getElementById('month').value = `${year}-${month}`;
        }

        function editExpense(id, month, income, expenses, notes) {
            document.getElementById('modalTitle').textContent = 'Edit Monthly Expense';
            document.getElementById('expense_id').value = id;
            document.getElementById('month').value = month;
            document.getElementById('income').value = income;
            document.getElementById('expenses').value = expenses;
            document.getElementById('notes').value = notes;
            document.getElementById('submitBtn').textContent = 'Update Expense';
            document.getElementById('submitBtn').name = 'update_expense';
            document.getElementById('expenseModal').style.display = 'block';
        }

        function closeExpenseModal() {
            document.getElementById('expenseModal').style.display = 'none';
        }

        function deleteExpense(id) {
            document.getElementById('delete_expense_id').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const expenseModal = document.getElementById('expenseModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == expenseModal) {
                expenseModal.style.display = 'none';
            }
            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
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
    </script>
</body>
</html> 