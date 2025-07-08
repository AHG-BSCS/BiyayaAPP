<?php
require_once 'config.php';

try {
    // First create the users table
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
        member_id VARCHAR(10),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES membership_records(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql_users)) {
        echo "Users table created successfully\n";
        
        // Now create the contributions table
        $sql_contributions = "CREATE TABLE IF NOT EXISTS contributions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            contribution_type ENUM('tithe', 'offering') NOT NULL,
            contribution_date DATETIME NOT NULL,
            payment_method ENUM('cash', 'gcash', 'bank_transfer') NOT NULL,
            reference_number VARCHAR(50),
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        if ($conn->query($sql_contributions)) {
            echo "Contributions table created successfully\n";
            
            // Check if admin user exists
            $check_admin = "SELECT id FROM users WHERE email = 'admin@church.org' LIMIT 1";
            $result = $conn->query($check_admin);
            
            if ($result->num_rows == 0) {
                // Create default admin user
                $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
                $sql_admin = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')";
                $stmt = $conn->prepare($sql_admin);
                $stmt->bind_param("sss", $admin_username, $admin_email, $admin_password);
                
                $admin_username = "admin";
                $admin_email = "admin@church.org";
                
                if ($stmt->execute()) {
                    echo "Default admin user created successfully\n";
                } else {
                    echo "Error creating admin user: " . $stmt->error . "\n";
                }
            }
        } else {
            echo "Error creating contributions table: " . $conn->error . "\n";
        }
    } else {
        echo "Error creating users table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?> 