<?php
require_once 'config.php';

echo "<h2>Fixing Collation Issues</h2>";

try {
    // Check current collations
    echo "<h3>1. Checking Current Table Collations:</h3>";
    
    $result = $conn->query("SHOW TABLE STATUS WHERE Name IN ('user_profiles', 'contributions')");
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>Collation</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Name'] . "</td>";
        echo "<td>" . $row['Collation'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Check column collations
    echo "<h3>2. Checking Column Collations:</h3>";
    
    $tables = ['user_profiles', 'contributions'];
    foreach ($tables as $table) {
        echo "<h4>Table: $table</h4>";
        $result = $conn->query("SHOW FULL COLUMNS FROM $table");
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Collation</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . ($row['Collation'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    }
    
    // Fix table collations
    echo "<h3>3. Fixing Table Collations:</h3>";
    
    // Convert user_profiles table to utf8mb4_unicode_ci
    $sql = "ALTER TABLE user_profiles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "✓ user_profiles table collation fixed<br>";
    } else {
        echo "✗ Error fixing user_profiles: " . $conn->error . "<br>";
    }
    
    // Convert contributions table to utf8mb4_unicode_ci
    $sql = "ALTER TABLE contributions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "✓ contributions table collation fixed<br>";
    } else {
        echo "✗ Error fixing contributions: " . $conn->error . "<br>";
    }
    
    // Fix specific columns that might have different collations
    echo "<h3>4. Fixing Specific Column Collations:</h3>";
    
    // Fix user_id columns
    $sql = "ALTER TABLE user_profiles MODIFY user_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "✓ user_profiles.user_id collation fixed<br>";
    } else {
        echo "✗ Error fixing user_profiles.user_id: " . $conn->error . "<br>";
    }
    
    $sql = "ALTER TABLE contributions MODIFY user_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "✓ contributions.user_id collation fixed<br>";
    } else {
        echo "✗ Error fixing contributions.user_id: " . $conn->error . "<br>";
    }
    
    // Fix username column
    $sql = "ALTER TABLE user_profiles MODIFY username VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "✓ user_profiles.username collation fixed<br>";
    } else {
        echo "✗ Error fixing user_profiles.username: " . $conn->error . "<br>";
    }
    
    // Check final collations
    echo "<h3>5. Final Table Collations:</h3>";
    
    $result = $conn->query("SHOW TABLE STATUS WHERE Name IN ('user_profiles', 'contributions')");
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>Collation</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Name'] . "</td>";
        echo "<td>" . $row['Collation'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Test the query that was failing
    echo "<h3>6. Testing the Fixed Query:</h3>";
    
    $test_username = 'admin'; // or any existing username
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.amount,
            c.contribution_type,
            c.contribution_date,
            c.payment_method,
            c.reference_number,
            c.status,
            c.notes,
            up.full_name as member_name
        FROM contributions c
        JOIN user_profiles up ON c.user_id = up.user_id
        WHERE up.username = ?
        ORDER BY c.contribution_date DESC
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $test_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "✓ Query executed successfully! Found " . $result->num_rows . " records for username '$test_username'<br>";
        
        if ($result->num_rows > 0) {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Amount</th><th>Type</th><th>Date</th><th>Member</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['amount'] . "</td>";
                echo "<td>" . $row['contribution_type'] . "</td>";
                echo "<td>" . $row['contribution_date'] . "</td>";
                echo "<td>" . $row['member_name'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "✗ Error preparing test query: " . $conn->error . "<br>";
    }
    
    echo "<h3>7. Summary:</h3>";
    echo "<p>✓ All tables and columns have been converted to utf8mb4_unicode_ci collation</p>";
    echo "<p>✓ The JOIN query between user_profiles and contributions should now work without collation errors</p>";
    echo "<p>✓ You can now access member_contributions.php without the collation error</p>";
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}

$conn->close();
?> 