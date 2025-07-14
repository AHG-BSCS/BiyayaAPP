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

// Get church logo
$church_logo = getChurchLogo($conn);

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

// Handle message update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_message'])) {
    $message_id = (int)$_POST['message_id'];
    $title = $_POST['title'];
    $date = $_POST['date'];
    $youtube_id = $_POST['youtube_id'];
    $outline = $_POST['outline'];
    
    // Convert outline text to JSON array
    $outline_array = array_filter(array_map('trim', explode(',', $outline)));
    $outline_json = json_encode($outline_array);
    
    // Prepare update statement
    $stmt = $conn->prepare("UPDATE messages SET title = ?, date = ?, youtube_id = ?, outline = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $title, $date, $youtube_id, $outline_json, $message_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Message updated successfully.";
    } else {
        $_SESSION['error_message'] = "Error updating message.";
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
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
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
            margin-top: 20px;
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

        .edit-btn {
            background-color: #4a90e2;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
        }

        .edit-btn:hover {
            background-color: #357abd;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: 8px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .modal-header h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .form-control[readonly] {
            background-color: #f9f9f9;
            border-color: #e0e0e0;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: rgb(0, 112, 9);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .btn i {
            margin-right: 5px;
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
            <table id="messagesTable">
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
                            <div class="action-buttons">
                                <button type="button" class="edit-btn" 
                                        data-id="<?php echo $message['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($message['title']); ?>"
                                        data-date="<?php echo htmlspecialchars($message['date']); ?>"
                                        data-youtube="<?php echo htmlspecialchars($message['youtube_id']); ?>"
                                        data-outline="<?php echo htmlspecialchars(implode(', ', $message['outline'])); ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                    <button type="submit" name="delete_message" class="delete-btn">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Message Modal -->
    <div id="editMessageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Message</h3>
                <p>Update the message information below</p>
            </div>
            <form method="POST" id="editMessageForm">
                <input type="hidden" name="message_id" id="edit_message_id">
                <input type="hidden" name="update_message" value="1">
                
                <div class="form-group">
                    <label for="edit_title">Title:</label>
                    <input type="text" id="edit_title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_date">Date:</label>
                    <input type="date" id="edit_date" name="date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_youtube_id">YouTube ID:</label>
                    <input type="text" id="edit_youtube_id" name="youtube_id" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_outline">Outline (comma-separated):</label>
                    <textarea id="edit_outline" name="outline" class="form-control" rows="4" placeholder="Enter outline points separated by commas"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#messagesTable').DataTable();
            
            // Add event listeners for edit buttons
            $(document).on('click', '.edit-btn', function() {
                const id = $(this).data('id');
                const title = $(this).data('title');
                const date = $(this).data('date');
                const youtube = $(this).data('youtube');
                const outline = $(this).data('outline');
                
                openEditModal(id, title, date, youtube, outline);
            });
        });

        // Modal functions
        function openEditModal(id, title, date, youtube_id, outline) {
            document.getElementById('edit_message_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_youtube_id').value = youtube_id;
            document.getElementById('edit_outline').value = outline;
            document.getElementById('editMessageModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editMessageModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('editMessageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html> 