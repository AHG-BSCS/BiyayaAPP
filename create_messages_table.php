<?php
require_once 'config.php';

// Create messages table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    youtube_id VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    outline TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Messages table created successfully or already exists.";
} else {
    echo "Error creating table: " . $conn->error;
}

// Check if table exists and has data
$check_sql = "SELECT COUNT(*) as count FROM messages";
$result = $conn->query($check_sql);

if ($result) {
    $row = $result->fetch_assoc();
    echo "\nNumber of messages in database: " . $row['count'];
} else {
    echo "\nError checking messages table: " . $conn->error;
}

$conn->close();
?> 