<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

if (!isset($_GET['id'])) {
    die("No member ID provided");
}

$member_id = $_GET['id'];

try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT * FROM membership_records WHERE id = :id");
    $stmt->bindParam(':id', $member_id);
    $stmt->execute();
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        die("Member not found");
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
    <title>Membership Certificate - <?php echo htmlspecialchars($member['name']); ?></title>
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
        .member-name {
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
        .membership-message {
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
        .join-date {
            position: absolute;
            top: 70%;
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
            top: 75%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
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
            <img src="certificates/Membership Certificate.jpg" alt="Certificate Template">
            <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
            <div class="membership-message">This certifies that the above named person is an official and legitimate member of Church of Christ-Disciples (Lopez Jaena) Inc.</div>
            <div class="join-date">Member since: <?php echo date('F d, Y', strtotime($member['join_date'])); ?></div>
            <div class="print-date">Certificate issued on: <?php echo date('F d, Y'); ?></div>
            <div class="certificate-number">Certificate No: <?php echo htmlspecialchars($member['id']); ?></div>
        </div>
    </div>
</body>
</html> 