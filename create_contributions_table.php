<?php
require_once 'config.php';

try {
    // First, check if the user_profiles table exists and has the correct structure
    $check_user_profiles = "SHOW TABLES LIKE 'user_profiles'";
    $result = $conn->query($check_user_profiles);
    
    if ($result->num_rows == 0) {
        echo "Error: user_profiles table does not exist. Please create the user_profiles table first.";
        exit;
    }
    
    // Create the contributions table with reference to user_profiles
    $sql = "CREATE TABLE IF NOT EXISTS contributions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        contribution_type ENUM('tithe', 'offering') NOT NULL,
        contribution_date DATETIME NOT NULL,
        payment_method ENUM('cash', 'gcash', 'maya', 'bank_transfer') NOT NULL,
        reference_number VARCHAR(50),
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        CONSTRAINT fk_contributions_user_id FOREIGN KEY (user_id) REFERENCES user_profiles(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql)) {
        echo "Contributions table created successfully with user_profiles reference";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 