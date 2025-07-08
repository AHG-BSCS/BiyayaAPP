<?php
require_once 'config.php';

try {
    // Check if contributions table exists
    $check_table = "SHOW TABLES LIKE 'contributions'";
    $result = $conn->query($check_table);
    
    if ($result->num_rows > 0) {
        echo "Contributions table exists. Updating structure...\n";
        
        // Drop existing foreign key constraint if it exists
        $conn->query("ALTER TABLE contributions DROP FOREIGN KEY IF EXISTS fk_user_id");
        $conn->query("ALTER TABLE contributions DROP FOREIGN KEY IF EXISTS fk_contributions_user_id");
        
        // Modify user_id column to VARCHAR(50)
        $conn->query("ALTER TABLE contributions MODIFY COLUMN user_id VARCHAR(50) NOT NULL");
        
        // Add new foreign key constraint to user_profiles
        $conn->query("ALTER TABLE contributions ADD CONSTRAINT fk_contributions_user_id FOREIGN KEY (user_id) REFERENCES user_profiles(user_id) ON DELETE CASCADE");
        
        // Update payment_method enum to include 'maya'
        $conn->query("ALTER TABLE contributions MODIFY COLUMN payment_method ENUM('cash', 'gcash', 'maya', 'bank_transfer') NOT NULL");
        
        // First, let's check the current table structure
        $stmt = $conn->query("DESCRIBE contributions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Current table structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Check if status column exists
        $statusExists = false;
        $dateExists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'status') {
                $statusExists = true;
            }
            if ($column['Field'] === 'contribution_date') {
                $dateExists = true;
            }
        }
        
        if ($statusExists) {
            echo "Status column found. Removing it...<br>";
            
            // Remove the status column
            $sql = "ALTER TABLE contributions DROP COLUMN status";
            $conn->exec($sql);
            echo "Status column removed successfully.<br>";
        } else {
            echo "Status column not found.<br>";
        }
        
        if (!$dateExists) {
            echo "Adding contribution_date column...<br>";
            
            // Add the contribution_date column
            $sql = "ALTER TABLE contributions ADD COLUMN contribution_date DATE NOT NULL DEFAULT CURRENT_DATE";
            $conn->exec($sql);
            echo "Contribution_date column added successfully.<br>";
        } else {
            echo "Contribution_date column already exists.<br>";
        }
        
        // Show the updated table structure
        $stmt = $conn->query("DESCRIBE contributions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Updated table structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        echo "Contributions table updated successfully!\n";
    } else {
        echo "Contributions table does not exist. Creating new table...\n";
        
        // Create the contributions table with reference to user_profiles
        $sql = "CREATE TABLE contributions (
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
            echo "Contributions table created successfully!\n";
        } else {
            echo "Error creating table: " . $conn->error . "\n";
        }
    }
    
    echo "Contributions table is now properly configured to work with user_profiles table.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?> 