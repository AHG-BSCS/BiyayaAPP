<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Get user profile
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Check if user is admin
if (!isset($user_profile['role']) || $user_profile['role'] !== 'Administrator') {
    header("Location: messages.php");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $title = trim($_POST['title'] ?? '');
    $youtube_id = trim($_POST['youtube_id'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $outline = trim($_POST['outline'] ?? '');

    // Basic validation
    if (empty($title) || empty($youtube_id) || empty($date) || empty($outline)) {
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: messages.php");
        exit;
    }

    // Convert outline text to array
    $outline_points = array_filter(explode("\n", $outline), 'trim');
    
    // Prepare the SQL statement
    $stmt = $conn->prepare("INSERT INTO messages (title, youtube_id, date, outline) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
        header("Location: messages.php");
        exit;
    }

    // Convert outline array to JSON
    $outline_json = json_encode($outline_points);
    
    // Bind parameters
    $stmt->bind_param("ssss", $title, $youtube_id, $date, $outline_json);
    
    // Execute the statement
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Message added successfully.";
    } else {
        $_SESSION['error_message'] = "Error adding message: " . $stmt->error;
    }
    
    // Close statement
    $stmt->close();
    
    // Redirect back to messages page
    header("Location: messages.php");
    exit;
} else {
    // If not a POST request, redirect to messages page
    header("Location: messages.php");
    exit;
}

$conn->close();
?> 