<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "churchdb";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Add missing columns
    $sql = "ALTER TABLE baptismal_records 
            ADD COLUMN IF NOT EXISTS venue VARCHAR(255) AFTER officiant,
            ADD COLUMN IF NOT EXISTS witnesses TEXT AFTER venue,
            ADD COLUMN IF NOT EXISTS remarks TEXT AFTER witnesses";

    $conn->exec($sql);
    echo "Columns added successfully";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$conn = null;
?> 