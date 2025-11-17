<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

if (!isset($_GET['id'])) {
    die("No child dedication record ID provided");
}

$dedication_id = $_GET['id'];

try {
    // Use database credentials from config.php
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM child_dedication_records WHERE id = :id");
    $stmt->bindParam(':id', $dedication_id);
    $stmt->execute();
    $dedication = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dedication) {
        die("Child dedication record not found");
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
    <title>Child Dedication Certificate - <?php echo htmlspecialchars($dedication['child_name']); ?></title>
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
        .child-name {
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
        .dedication-message {
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
        .dedication-message-2 {
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
        .dedication-message-3 {
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
        .dedication-message-4 {
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
        .dedication-details {
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
        .dedication-details-left {
            position: absolute;
            top: 92%;
            left: 25%;
            transform: translate(-50%, -50%);
            font-size: 16px;
            color: #000;
            text-align: center;
            width: 30%;
            z-index: 2;
        }
        .dedication-details-right {
            position: absolute;
            top: 92%;
            left: 75%;
            transform: translate(-50%, -50%);
            font-size: 16px;
            color: #000;
            text-align: center;
            width: 30%;
            z-index: 2;
        }
        .dedication-date {
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
        .parents-names {
            position: absolute;
            top: 68%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 22px;
            color: #000;
            text-align: center;
            width: 80%;
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
            <img src="certificates/Dedication Certificate.jpg" alt="Child Dedication Certificate Template">
            <div class="dedication-message">This certifies that</div>
            <div class="dedication-message-2">was dedicated to the Lord</div>
            <div class="dedication-message-3">______________________</div>
            <div class="dedication-message-4">Officiating Pastor</div>
            <div class="officiated_by"><?php echo htmlspecialchars($dedication['officiated_by']); ?></div>
            <div class="dedication-date">on <?php 
                $date = new DateTime($dedication['dedication_date']);
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
            <div class="child-name"><?php echo htmlspecialchars($dedication['child_name']); ?></div>
            <div class="parents-names">Son/Daughter of <?php echo htmlspecialchars($dedication['father_name']); ?> & <?php echo htmlspecialchars($dedication['mother_name']); ?></div>
            <div class="dedication-details-right">Certificate No: <?php echo htmlspecialchars($dedication['id']); ?></div>
        </div>
    </div>
</body>
</html> 