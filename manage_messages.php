<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: index.php");
    exit;
}

// Get user profile
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Check if user is admin
if (!isset($user_profile['role']) || $user_profile['role'] !== 'Administrator') {
    header("Location: messages.php");
    exit;
}

// Handle message deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_message'])) {
    $message_id = (int)$_POST['message_id'];
    
    // Prepare delete statement
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Message deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Error deleting message.";
    }
    
    header("Location: manage_messages.php");
    exit;
}

// Fetch all messages
$messages = [];
$sql = "SELECT * FROM messages ORDER BY date DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $messages[] = [
            "id" => $row['id'],
            "title" => $row['title'],
            "youtube_id" => $row['youtube_id'],
            "date" => $row['date'],
            "outline" => json_decode($row['outline'], true)
        ];
    }
}

$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Messages | <?php echo $church_name; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a3a3a;
            --accent-color: rgb(0, 139, 30);
            --light-gray: #d0d0d0;
            --white: #ffffff;
            --danger-color: #dc3545;
            --success-color: #28a745;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: var(--primary-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h1 {
            color: var(--primary-color);
        }

        .back-btn {
            background-color: var(--accent-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .messages-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .messages-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .messages-table th,
        .messages-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .messages-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .messages-table tr:hover {
            background-color: #f8f9fa;
        }

        .delete-btn {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .delete-btn:hover {
            background-color: #c82333;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .outline-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Manage Messages</h1>
            <a href="messages.php" class="back-btn">Back to Messages</a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="messages-table">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>YouTube ID</th>
                        <th>Outline Preview</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($message['title']); ?></td>
                        <td><?php echo htmlspecialchars($message['date']); ?></td>
                        <td><?php echo htmlspecialchars($message['youtube_id']); ?></td>
                        <td class="outline-preview">
                            <?php 
                            $outline_text = implode(", ", array_slice($message['outline'], 0, 3));
                            echo htmlspecialchars($outline_text);
                            ?>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                <button type="submit" name="delete_message" class="delete-btn">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 