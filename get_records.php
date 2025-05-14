<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user"] !== "admin") {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$date = isset($_GET['date']) ? $_GET['date'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Validate year if provided
if ($year && ($year < 2020 || $year > 2025)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid year']);
    exit;
}

// Validate date if provided
if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Validate type
$valid_types = ['tithes', 'offerings', 'bank-gifts', 'specified-gifts'];
if (!in_array($type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record type']);
    exit;
}

// Map type to table name
$table_map = [
    'tithes' => 'tithes',
    'offerings' => 'offerings',
    'bank-gifts' => 'bank_gifts',
    'specified-gifts' => 'specified_gifts'
];

$table = $table_map[$type];

// Prepare and execute query based on whether we're searching by date or year
if ($date) {
    $sql = "SELECT * FROM $table WHERE date = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
} else {
    $sql = "SELECT * FROM $table WHERE YEAR(date) = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $year);
}

$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($records);
?> 