<?php
require_once 'config.php';

echo "<h2>Simple Collation Fix</h2>";

try {
    // Fix user_profiles table collation
    $sql1 = "ALTER TABLE user_profiles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql1)) {
        echo "✓ user_profiles table collation fixed<br>";
    } else {
        echo "✗ Error fixing user_profiles: " . $conn->error . "<br>";
    }
    
    // Fix contributions table collation
    $sql2 = "ALTER TABLE contributions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql2)) {
        echo "✓ contributions table collation fixed<br>";
    } else {
        echo "✗ Error fixing contributions: " . $conn->error . "<br>";
    }
    
    echo "<br><strong>Collation fix complete! You can now access member_contributions.php</strong>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 