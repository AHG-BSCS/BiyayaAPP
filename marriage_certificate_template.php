<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

if (!isset($_GET['id'])) {
    die("No marriage record ID provided");
}

$marriage_id = $_GET['id'];

try {
    // Use database credentials from config.php
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM marriage_records WHERE id = :id");
    $stmt->bindParam(':id', $marriage_id);
    $stmt->execute();
    $marriage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$marriage) {
        die("Marriage record not found");
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
    <title>Marriage Certificate - <?php echo htmlspecialchars($marriage['husband_name']); ?> & <?php echo htmlspecialchars($marriage['wife_name']); ?></title>
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
        .couple-names {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 80px;
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
        .marriage-message {
            position: absolute;
            top: 33%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;
            color: #000;
            text-align: center;
            width: 80%;
            font-style: italic;
            z-index: 2;
        }
        .marriage-message-2 {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;
            color: #000;
            text-align: center;
            width: 80%;
            font-style: italic;
            z-index: 2;
        }
        .marriage-message-3 {
            position: absolute;
            top: 80%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .marriage-message-4 {
            position: absolute;
            top: 87%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;    
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .officiated_by {
            position: absolute;
            top: 85%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;    
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .marriage-details {
            position: absolute;
            top: 85%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .marriage-details-left {
            position: absolute;
            top: 85%;
            left: 25%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #000;
            text-align: center;
            width: 30%;
            z-index: 2;
        }
        .marriage-details-right {
            position: absolute;
            top: 85%;
            left: 75%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #000;
            text-align: center;
            width: 30%;
            z-index: 2;
        }
        .marriage-message-4 {
            position: absolute;
            top: 89%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .officiating-pastor {
            position: absolute;
            top: 83%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;    
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .marriage-date {
            position: absolute;
            top: 60%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;
            color: #000;
            text-align: center;
            font-style: italic;
            width: 80%;
            z-index: 2;
        }
        .marriage-venue {
            position: absolute;
            top: 75%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #000;
            text-align: center;
            width: 80%;
            z-index: 2;
        }
        .print-date {
            position: absolute;
            top: 80%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #000;
            text-align: right;
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
            <img src="certificates/Marriage Certificate.jpg" alt="Marriage Certificate Template">
            <div class="marriage-message">This certifies that</div>
            <div class="marriage-message-2">are united in marriage</div>
            <div class="marriage-message-3">______________________</div>
            <div class="marriage-message-4">Officiating Pastor</div>
            <div class="officiated_by"><?php echo htmlspecialchars($marriage['officiated_by']); ?></div>
            <div class="marriage-date">on <?php 
                $date = new DateTime($marriage['marriage_date']);
                $day = $date->format('j');
                $suffix = '';
                if ($day == 1 || $day == 21 || $day == 31) {
                    $suffix = 'st';
                } elseif ($day == 2 || $day == 22) {
                    $suffix = 'nd';
                } elseif ($day == 3 || $day == 23) {
                    $suffix = 'rd';
                } else {
                    $suffix = 'th';
                }
                echo $day . $suffix . ' day of ' . $date->format('F') . ' in the year of ' . $date->format('Y');
            ?></div>
            <div class="couple-names"><?php echo htmlspecialchars($marriage['husband_name']); ?> & <?php echo htmlspecialchars($marriage['wife_name']); ?></div>
            <div class="marriage-details-left">Registry No: <?php echo htmlspecialchars($marriage['registry_no']); ?></div>
            <div class="marriage-details-right">License No: <?php echo htmlspecialchars($marriage['marriage_license_no']); ?></div>
        </div>
    </div>
</body>
</html> 