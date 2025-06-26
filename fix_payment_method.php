<?php
require_once 'config.php';

try {
    echo "Updating payment_method ENUM to include 'maya'...\n";
    
    // Update the payment_method column to include 'maya'
    $sql = "ALTER TABLE contributions MODIFY COLUMN payment_method ENUM('cash', 'gcash', 'maya', 'bank_transfer') NOT NULL";
    
    if ($conn->query($sql)) {
        echo "Payment method ENUM updated successfully!\n";
        
        // Verify the change
        $result = $conn->query("DESCRIBE contributions");
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] == 'payment_method') {
                echo "Updated payment method column: " . $row['Type'] . "\n";
                break;
            }
        }
    } else {
        echo "Error updating payment method ENUM: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?> 