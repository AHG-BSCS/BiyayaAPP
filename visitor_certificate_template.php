<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

if (!isset($_GET['id'])) {
    die("No visitor record ID provided");
}

$visitor_id = $_GET['id'];

try {
    // Use database credentials from config.php
    // Ensure variables are defined (fallback if config.php didn't load properly)
    if (!isset($servername)) $servername = "localhost";
    if (!isset($username)) $username = "root";
    if (!isset($password)) $password = "";
    if (!isset($dbname)) $dbname = "churchdb";
    
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM visitor_records WHERE id = :id");
    $stmt->bindParam(':id', $visitor_id);
    $stmt->execute();
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visitor) {
        die("Visitor record not found");
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Certificate - <?php echo htmlspecialchars($visitor['name']); ?></title>
    <!-- Load font directly from Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Pinyon+Script&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Pinyon Script';
            src: url('https://fonts.gstatic.com/s/pinyonscript/v14/6xKydSByOcG-9QEu7QZ_WR4tqD_4k6q.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        @page {
            size: landscape;
            margin: 0;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .certificate {
                width: 100%;
                height: 100%;
                padding: 0;
            }
            .certificate-content {
                width: 100%;
                height: 100%;
            }
            img {
                width: 100% !important;
                height: 100% !important;
                object-fit: contain !important;
            }
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            background: #fff;
        }
        .certificate {
            width: 11.69in;
            height: 8.27in;
            position: relative;
            background: #fff;
            padding: 20px;
            box-sizing: border-box;
        }
        .certificate-content {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .visitor-name {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 65px;
            color: #000;
            font-family: 'Pinyon Script', 'Brush Script MT', cursive !important;
            text-align: center;
            width: 100%;
            z-index: 2;
            font-weight: normal;
            line-height: 1.2;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.1);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .visitor-message {
            position: absolute;
            top: 60%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 25px;
            color: #000;
            text-align: center;
            width: 80%;
            font-style: italic;
            z-index: 2;
        }
        .visit-date {
            position: absolute;
            top: 70%;
            left: 30%;
            transform: translate(-50%, -50%);
            font-size: 25px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .visitor-purpose {
            position: absolute;
            top: 75%;
            left: 30%;
            transform: translate(-50%, -50%);
            font-size: 25px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .invited-by {
            position: absolute;
            top: 75%;
            left: 65.3%;
            transform: translate(-50%, -50%);
            font-size: 25px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .print-date {
            position: absolute;
            top: 70%;
            left: 70%;
            transform: translate(-50%, -50%);
            font-size: 25px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .certificate-number {
            position: absolute;
            bottom: 5px;
            right: 20px;
            font-size: 10px;
            color: #000;
            z-index: 2;
        }
        .certificate-content img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="certificate-content">
            <img src="certificates/VIsitor Certificate.jpg" alt="Visitor Certificate Template">
            <div class="visitor-name"><?php echo htmlspecialchars($visitor['name']); ?></div>
            <div class="visitor-message">This certifies that the above named person visited Church of Christ-Disciples (Lopez Jaena) Inc.</div>
            <div class="visit-date">Visit Date: <?php echo date('F d, Y', strtotime($visitor['visit_date'])); ?></div>
            <div class="visitor-purpose">Purpose: <?php echo htmlspecialchars($visitor['purpose']); ?></div>
            <div class="invited-by">Invited by: <?php echo htmlspecialchars($visitor['invited_by']); ?></div>
            <div class="print-date">Certificate issued on: <?php echo date('F d, Y'); ?></div>
            <div class="certificate-number">Certificate No: <?php echo htmlspecialchars($visitor['id']); ?></div>
        </div>
    </div>
</body>
</html> 