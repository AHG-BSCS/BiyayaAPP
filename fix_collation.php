<?php
require_once 'config.php';

try {
    // Fix user_profiles.user_id
    $sql1 = "ALTER TABLE user_profiles MODIFY user_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    if ($conn->query($sql1)) {
        echo "user_profiles.user_id collation set to utf8mb4_unicode_ci\n";
    } else {
        echo "Error updating user_profiles.user_id: " . $conn->error . "\n";
    }

    // Fix contributions.user_id
    $sql2 = "ALTER TABLE contributions MODIFY user_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    if ($conn->query($sql2)) {
        echo "contributions.user_id collation set to utf8mb4_unicode_ci\n";
    } else {
        echo "Error updating contributions.user_id: " . $conn->error . "\n";
    }

    echo "Collation fix complete.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?> 