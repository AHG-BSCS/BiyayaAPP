<?php
require_once 'config.php';

// Check if member_id column exists
$check_sql = "SHOW COLUMNS FROM users LIKE 'member_id'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    // Remove the foreign key constraint first
    $conn->query("ALTER TABLE users DROP FOREIGN KEY users_ibfk_1");
    
    // Remove the member_id column
    $conn->query("ALTER TABLE users DROP COLUMN member_id");
    
    echo "Successfully removed member_id column from users table.";
} else {
    echo "member_id column does not exist in users table.";
}

$conn->close();
?> 