<?php
require_once 'config.php';
$new_hash = '$2y$10$1RpyERH4rb63usJOwKRR5.91z12nfqYPrjJY./EGELcPBMZbvj83i';
$sql = "UPDATE user_profiles SET password = ? WHERE user_id = 'admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $new_hash);
if ($stmt->execute()) {
    echo "Admin password updated successfully!";
} else {
    echo "Error updating password: " . $conn->error;
} 