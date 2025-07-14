<?php
// Member Prayer Requests page
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: index.php");
    exit;
}

// Check if the user is a member
$is_member = ($_SESSION["user_role"] === "Member");

if (!$is_member) {
    header("Location: dashboard.php");
    exit;
}

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Process prayer request submission
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_prayer"])) {
    $category = $_POST["prayer_category"] ?? "";
    $request = trim($_POST["prayer_request"] ?? "");
    $urgency = $_POST["prayer_urgency"] ?? "normal";
    $anonymous = isset($_POST["anonymous"]);
    
    if (!empty($category) && !empty($request)) {
        $member_name = $anonymous ? "Anonymous" : ($_SESSION["user"] ?? "Unknown Member");
        
        $sql = "INSERT INTO prayer_requests (member_name, prayer_request, category, urgency, anonymous) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $member_name, $request, $category, $urgency, $anonymous);
        
        if ($stmt->execute()) {
            $message = "Prayer request submitted successfully!";
            $messageType = "success";
        } else {
            $message = "Error submitting prayer request. Please try again.";
            $messageType = "danger";
        }
    } else {
        $message = "Please fill in all required fields.";
        $messageType = "danger";
    }
}

// Handle reaction updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["react"])) {
    $prayer_id = $_POST["prayer_id"];
    $reaction_type = $_POST["reaction_type"];
    
    // Map reaction type to column name
    $column_map = [
        'heart' => 'heart_reactions',
        'praying' => 'praying_reactions',
        'like' => 'like_reactions'
    ];
    
    if (isset($column_map[$reaction_type])) {
        $column = $column_map[$reaction_type];
        $sql = "UPDATE prayer_requests SET $column = $column + 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $prayer_id);
        
        if ($stmt->execute()) {
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to update reaction']);
    exit;
}

// Get prayer requests from database
$sql = "SELECT * FROM prayer_requests ORDER BY created_at DESC";
$result = $conn->query($sql);
$prayer_requests = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $prayer_requests[] = [
            "id" => $row['id'],
            "member" => $row['member_name'],
            "request" => $row['prayer_request'],
            "category" => $row['category'],
            "urgency" => $row['urgency'],
            "anonymous" => $row['anonymous'],
            "date" => $row['created_at'],
            "reactions" => [
                "heart" => $row['heart_reactions'],
                "praying" => $row['praying_reactions'],
                "like" => $row['like_reactions']
            ]
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Prayer Requests | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a3a3a;
            --accent-color: rgb(0, 139, 30);
            --light-gray: #d0d0d0;
            --white: #ffffff;
            --sidebar-width: 250px;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
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
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header img {
            height: 60px;
            margin-bottom: 10px;
        }
        
        .sidebar-header h3 {
            font-size: 18px;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--white);
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu a.active {
            background-color: var(--accent-color);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .content-area {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--white);
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .top-bar h2 {
            font-size: 24px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-profile .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            overflow: hidden;
        }
        
        .user-profile .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info {
            margin-right: 15px;
        }
        
        .user-info p {
            font-size: 14px;
            color: #666;
        }
        
        .logout-btn {
            background-color: #f0f0f0;
            color: var(--primary-color);
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .logout-btn:hover {
            background-color: #e0e0e0;
        }
        
        .prayer-content {
            margin-top: 20px;
        }
        
        .card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card h3 {
            margin-bottom: 15px;
            font-size: 18px;
            color: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: var(--white);
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: rgb(0, 112, 9);
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }
        
        /* Prayer Request Cards */
        .prayer-request {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--accent-color);
        }
        
        .prayer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .prayer-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .prayer-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .prayer-category {
            background-color: rgba(0, 139, 30, 0.1);
            color: var(--accent-color);
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .urgency-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .urgency-normal {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--info-color);
        }
        
        .urgency-urgent {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }
        
        .urgency-emergency {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }
        
        .prayer-content-text {
            margin-bottom: 15px;
            line-height: 1.6;
            color: #333;
        }
        
        .prayer-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .prayer-date {
            font-size: 12px;
            color: #999;
        }
        
        /* Reaction Buttons */
        .reaction-buttons {
            display: flex;
            gap: 10px;
        }
        
        .reaction-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background-color: #f8f9fa;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .reaction-btn:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }
        
        .reaction-btn.heart {
            border-color: #ff6b6b;
        }
        
        .reaction-btn.heart:hover {
            background-color: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
        }
        
        .reaction-btn.praying {
            border-color: #4ecdc4;
        }
        
        .reaction-btn.praying:hover {
            background-color: rgba(78, 205, 196, 0.1);
            color: #4ecdc4;
        }
        
        .reaction-btn.like {
            border-color: #45b7d1;
        }
        
        .reaction-btn.like:hover {
            background-color: rgba(69, 183, 209, 0.1);
            color: #45b7d1;
        }
        
        .reaction-count {
            font-weight: 500;
        }
        
        /* Form Styling */
        .urgency-levels {
            display: flex;
            gap: 20px;
        }
        
        .urgency-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .urgency-option input[type="radio"] {
            margin: 0;
        }
        
        .urgency-label {
            font-size: 14px;
        }
        
        .urgency-label.urgent {
            color: var(--warning-color);
        }
        
        .urgency-label.emergency {
            color: var(--danger-color);
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin: 0;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
            border-radius: 10px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background-color: #f0f0f0;
            color: var(--primary-color);
        }

        /* Action Button */
        .action-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 139, 30, 0.2);
        }

        .btn-primary:hover {
            background-color: rgb(0, 112, 9);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 139, 30, 0.3);
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h3, .sidebar-menu span {
                display: none;
            }
            .content-area {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar-menu {
                display: flex;
                padding: 0;
                overflow-x: auto;
            }
            .sidebar-menu ul {
                display: flex;
                width: 100%;
            }
            .sidebar-menu li {
                margin-bottom: 0;
                flex: 1;
            }
            .sidebar-menu a {
                padding: 10px;
                justify-content: center;
            }
            .sidebar-menu i {
                margin-right: 0;
            }
            .content-area {
                margin-left: 0;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .user-profile {
                margin-top: 10px;
            }
            .prayer-header {
                flex-direction: column;
                gap: 10px;
            }
            .prayer-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            .urgency-levels {
                flex-direction: column;
                gap: 10px;
            }
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo htmlspecialchars($church_logo); ?>" alt="Church Logo">
                <h3><?php echo $church_name; ?></h3>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="member_dashboard.php" class="<?php echo $current_page == 'member_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="member_events.php" class="<?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="member_messages.php" class="<?php echo $current_page == 'member_messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i> <span>Messages</span></a></li>
                    <li><a href="member_prayers.php" class="<?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                    <li><a href="member_financialreport.php" class="<?php echo $current_page == 'member_financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span>Financial Reports</span></a></li>
                    <li><a href="member_settings.php" class="<?php echo $current_page == 'member_settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                </ul>
            </div>
        </aside>
        
        <main class="content-area">
            <div class="top-bar">
                <h2>Prayer Requests</h2>
                <div class="user-profile">
                    <div class="avatar">
                        <?php if (!empty($user_profile['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_profile['full_name'] ?? $user_profile['username'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username'] ?? 'Unknown User'); ?></h4>
                        <p><?php echo htmlspecialchars($user_profile['role'] ?? 'Member'); ?></p>
                    </div>
                    <form action="logout.php" method="post">
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>
            
            <div class="prayer-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="action-bar">
                    <button class="btn-primary" onclick="openPrayerModal()">
                        <i class="fas fa-plus-circle"></i>
                        Submit a Prayer Request
                    </button>
                </div>

                <!-- Prayer Requests List -->
                <div class="card">
                    <h3><i class="fas fa-hands-praying"></i> Prayer Requests from Members</h3>
                    <?php if (empty($prayer_requests)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No prayer requests yet. Be the first to submit one!</p>
                    <?php else: ?>
                        <?php foreach ($prayer_requests as $prayer): ?>
                            <div class="prayer-request">
                                <div class="prayer-header">
                                    <div>
                                        <div class="prayer-meta">
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($prayer['member']); ?></span>
                                            <span class="prayer-category"><?php echo htmlspecialchars($prayer['category']); ?></span>
                                            <span class="urgency-badge urgency-<?php echo $prayer['urgency']; ?>">
                                                <?php echo ucfirst($prayer['urgency']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="prayer-content-text">
                                    <?php echo nl2br(htmlspecialchars($prayer['request'])); ?>
                                </div>
                                
                                <div class="prayer-footer">
                                    <div class="prayer-date">
                                        <i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($prayer['date'])); ?>
                                    </div>
                                    
                                    <div class="reaction-buttons">
                                        <button class="reaction-btn heart" onclick="react(<?php echo $prayer['id']; ?>, 'heart', event)">
                                            <i class="fas fa-heart"></i>
                                            <span class="reaction-count"><?php echo $prayer['reactions']['heart']; ?></span>
                                        </button>
                                        <button class="reaction-btn praying" onclick="react(<?php echo $prayer['id']; ?>, 'praying', event)">
                                            <i class="fas fa-hands-praying"></i>
                                            <span class="reaction-count"><?php echo $prayer['reactions']['praying']; ?></span>
                                        </button>
                                        <button class="reaction-btn like" onclick="react(<?php echo $prayer['id']; ?>, 'like', event)">
                                            <i class="fas fa-thumbs-up"></i>
                                            <span class="reaction-count"><?php echo $prayer['reactions']['like']; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Prayer Request Modal -->
    <div class="modal" id="prayerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Submit a Prayer Request</h3>
                <button class="close-btn" onclick="closePrayerModal()">&times;</button>
            </div>
            <form action="" method="post">
                <div class="form-group">
                    <label for="prayer_category">Category</label>
                    <select id="prayer_category" name="prayer_category" class="form-control" required>
                        <option value="">Select a category</option>
                        <option value="Personal">Personal</option>
                        <option value="Family">Family</option>
                        <option value="Health">Health</option>
                        <option value="Financial">Financial</option>
                        <option value="Spiritual">Spiritual</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prayer_request">Your Prayer Request</label>
                    <textarea id="prayer_request" name="prayer_request" class="form-control" placeholder="Please share your prayer request in detail. We are here to support you in prayer." required rows="6"></textarea>
                    <small class="form-text">Your prayer request will be kept confidential and shared only with the prayer team.</small>
                </div>

                <div class="form-group">
                    <label for="prayer_urgency">Urgency Level</label>
                    <div class="urgency-levels">
                        <label class="urgency-option">
                            <input type="radio" name="prayer_urgency" value="normal" checked>
                            <span class="urgency-label">Normal</span>
                        </label>
                        <label class="urgency-option">
                            <input type="radio" name="prayer_urgency" value="urgent">
                            <span class="urgency-label urgent">Urgent</span>
                        </label>
                        <label class="urgency-option">
                            <input type="radio" name="prayer_urgency" value="emergency">
                            <span class="urgency-label emergency">Emergency</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="anonymous" id="anonymous">
                        <span>Submit anonymously</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn" name="submit_prayer">
                        <i class="fas fa-paper-plane"></i> Submit Prayer Request
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Clear Form
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function react(prayerId, reactionType, event) {
            const formData = new FormData();
            formData.append('react', '1');
            formData.append('prayer_id', prayerId);
            formData.append('reaction_type', reactionType);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the reaction count
                    const button = event.target.closest('.reaction-btn');
                    const countSpan = button.querySelector('.reaction-count');
                    const currentCount = parseInt(countSpan.textContent);
                    countSpan.textContent = currentCount + 1;
                    
                    // Add visual feedback
                    button.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        button.style.transform = 'scale(1)';
                    }, 200);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Modal functions
        function openPrayerModal() {
            document.getElementById('prayerModal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closePrayerModal() {
            document.getElementById('prayerModal').classList.remove('active');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside
        document.getElementById('prayerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePrayerModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePrayerModal();
            }
        });
    </script>
</body>
</html>