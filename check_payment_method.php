<?php
require_once 'config.php';

try {
    // Check the current structure of the payment_method column
    $result = $conn->query("DESCRIBE contributions");
    
    echo "Contributions table structure:\n";
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] == 'payment_method') {
            echo "Payment method column: " . $row['Type'] . "\n";
        }
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    
    // Check what payment methods are currently in the database
    $result = $conn->query("SELECT DISTINCT payment_method FROM contributions");
    echo "\nCurrent payment methods in database:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['payment_method'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?> 