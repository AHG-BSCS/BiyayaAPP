<?php
require_once 'config.php';

try {
    // Check users table
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    echo "Users table exists: " . ($result->num_rows > 0 ? "Yes" : "No") . "\n";
    
    if ($result->num_rows > 0) {
        $result = $conn->query("DESCRIBE users");
        echo "\nUsers table structure:\n";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    }
    
    // Check contributions table
    $result = $conn->query("SHOW TABLES LIKE 'contributions'");
    echo "\nContributions table exists: " . ($result->num_rows > 0 ? "Yes" : "No") . "\n";
    
    if ($result->num_rows > 0) {
        $result = $conn->query("DESCRIBE contributions");
        echo "\nContributions table structure:\n";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    }
    
    // Check if admin user exists
    $result = $conn->query("SELECT * FROM users WHERE role = 'admin'");
    echo "\nAdmin user exists: " . ($result->num_rows > 0 ? "Yes" : "No") . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?> 