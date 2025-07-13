<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Get church logo
$church_logo = getChurchLogo($conn);

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch messages from database
$messages = [];
$sql = "SELECT * FROM messages ORDER BY date DESC";
$result = $conn->query($sql);

if (!$result) {
    error_log("Database query failed: " . $conn->error);
    echo '<div style="padding: 30px; text-align: center; color: #888;">Error loading messages. Please try again later.</div>';
    exit;
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $outline = json_decode($row['outline'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $outline = [];
        }
        
        $messages[] = [
            "id" => $row['id'],
            "title" => $row['title'],
            "youtube_id" => $row['youtube_id'],
            "date" => $row['date'],
            "outline" => $outline
        ];
    }
}

if (empty($messages)) {
    echo '<div style="padding: 30px; text-align: center; color: #888;">No messages available.</div>';
    exit;
}

$current_message = isset($_GET['message']) ? (int)$_GET['message'] : 0;
if ($current_message < 0 || $current_message >= count($messages)) {
    $current_message = 0;
}

$message = $messages[$current_message];

if (!isset($message['title']) || !isset($message['youtube_id']) || !isset($message['outline'])) {
    echo '<div style="padding: 30px; text-align: center; color: #888;">Error: Invalid message data. Please contact the administrator.</div>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | <?php echo htmlspecialchars($church_name); ?></title>
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

        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-container input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-container input[type="text"]:invalid:not(:placeholder-shown) {
            border-color: #dc3545;
        }

        .search-container input[type="text"]::placeholder {
            color: #6c757d;
        }

        .search-container button {
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            white-space: nowrap;
            font-size: 14px;
            height: 40px;
        }

        .search-container button:hover {
            background-color: rgb(0, 112, 9);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 10px;
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

        .user-info h4 {
            font-size: 14px;
            margin: 0;
        }

        .user-info p {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        .logout-btn {
            background-color: #f0f0f0;
            color: var(--primary-color);
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            height: 35px;
        }

        .logout-btn:hover {
            background-color: #e0e0e0;
        }

        .video-container {
            margin: 20px 0;
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 5px;
        }

        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .message-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .navigation-buttons button {
            padding: 8px 15px;
            margin: 0 5px;
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .navigation-buttons button:hover {
            background-color: rgb(0, 112, 9);
        }

        .navigation-buttons button:disabled {
            background-color: var(--light-gray);
            cursor: not-allowed;
        }

        .outline-toggle {
            background-color: transparent;
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .outline-toggle:hover {
            background-color: var(--accent-color);
            color: var(--white);
        }

        .message-outline {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .message-outline.show {
            display: block;
        }

        .message-outline ul {
            list-style-type: none;
        }

        .message-outline li {
            margin: 8px 0;
            padding-left: 15px;
            position: relative;
        }

        .message-outline li.bold {
            font-weight: bold;
            color: var(--primary-color);
        }

        .message-outline li:before {
            content: "•";
            color: var(--accent-color);
            position: absolute;
            left: 0;
        }

        .message-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .message-date {
            font-size: 16px;
            color: #666;
            font-weight: normal;
        }

        .search-results {
            display: none;
            margin-top: 20px;
            background-color: var(--white);
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            z-index: 1000;
        }

        .search-results.show {
            display: block !important;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .search-result-thumbnail {
            width: 120px;
            height: 68px;
            margin-right: 15px;
            border-radius: 4px;
            overflow: hidden;
        }

        .search-result-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .search-result-content {
            flex: 1;
        }

        .search-result-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .search-result-date {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .search-result-outline {
            font-size: 14px;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }

        .highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar-header h3 {
                display: none;
            }
            
            .sidebar-menu span {
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
            
            .sidebar-header {
                padding: 10px;
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
            
            .message-controls {
                flex-direction: column;
                gap: 10px;
            }

            .navigation-buttons {
                width: 100%;
                display: flex;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo htmlspecialchars($church_logo); ?>" alt="Church Logo">
                <h3><?php echo htmlspecialchars($church_name); ?></h3>
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
                <h2>Messages</h2>
                <div class="user-profile">
                    <div class="avatar">
                        <?php if (!empty($user_profile['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_profile['full_name'] ?? $user_profile['username'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username'] ?? 'Member'); ?></h4>
                        <p><?php echo htmlspecialchars($user_profile['role'] ?? 'Member'); ?></p>
                    </div>
                    <div class="user-actions">
                        <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
                    </div>
                </div>
            </div>
            <div class="search-container">
                <input type="text" id="search-input" placeholder="Search messages by title, date, or content..." required>
                <button id="search-btn">Search</button>
            </div>
            <div class="search-results" id="search-results"></div>
            <div class="video-container" id="videoContainer">
                <div class="message-title">
                    <?php echo htmlspecialchars($message['title']); ?>
                    <span class="message-date"><?php echo date('F j, Y', strtotime($message['date'])); ?></span>
                </div>
                <div class="video-wrapper">
                    <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($message['youtube_id']); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
                <div class="message-controls">
                    <div class="navigation-buttons">
                        <button onclick="window.location.href='member_messages.php?message=<?php echo max(0, $current_message - 1); ?>'" <?php echo $current_message <= 0 ? 'disabled' : ''; ?>>Previous</button>
                        <button onclick="window.location.href='member_messages.php?message=<?php echo min(count($messages) - 1, $current_message + 1); ?>'" <?php echo $current_message >= count($messages) - 1 ? 'disabled' : ''; ?>>Next</button>
                    </div>
                    <button class="outline-toggle" onclick="toggleOutline()">Show Outline</button>
                </div>
                <div class="message-outline" id="message-outline" style="display: none;">
                    <ul>
                        <?php foreach ($message['outline'] as $point): ?>
                            <li class="<?php 
                                if (is_array($point) && isset($point['bold']) && $point['bold']) {
                                    echo 'bold';
                                } elseif (is_string($point) && (strpos($point, 'Main Point') !== false || strpos($point, 'I.') !== false || strpos($point, 'II.') !== false || strpos($point, 'III.') !== false || strpos($point, 'IV.') !== false || strpos($point,'V.') !== false || strpos($point, 'TEXT:') !== false)) {
                                    echo 'bold';
                                }
                            ?>">
                                <?php 
                                if (is_array($point) && isset($point['text'])) {
                                    echo htmlspecialchars($point['text']);
                                } elseif (is_string($point)) {
                                    echo htmlspecialchars($point);
                                } else {
                                    echo htmlspecialchars(json_encode($point));
                                }
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Store messages data globally
        const messages = <?php echo json_encode($messages); ?>;

        // Toggle outline visibility
        function toggleOutline() {
            console.log('Toggle outline function called');
            const outline = document.getElementById('message-outline');
            const toggleButton = document.querySelector('.outline-toggle');
            
            console.log('Elements found:', {
                outline: !!outline,
                toggleButton: !!toggleButton
            });
            
            if (outline && toggleButton) {
                console.log('Current outline display:', outline.style.display);
                if (outline.style.display === 'none') {
                    outline.style.display = 'block';
                    toggleButton.textContent = 'Hide Outline';
                    console.log('Outline shown');
                } else {
                    outline.style.display = 'none';
                    toggleButton.textContent = 'Show Outline';
                    console.log('Outline hidden');
                }
            } else {
                console.error('Required elements not found for outline toggle');
            }
        }

        // Search functionality
        function searchMessages() {
            console.log('Search function called');
            const searchInput = document.getElementById('search-input');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const searchResults = document.getElementById('search-results');
            
            if (!searchInput || !searchResults) {
                console.error('Required elements not found');
                return;
            }
            
            if (!searchTerm) {
                searchInput.setCustomValidity('Please enter a search term');
                searchInput.reportValidity();
                return;
            }
            
            searchInput.setCustomValidity(''); // Clear any previous validation message

            console.log('Search term:', searchTerm);
            console.log('Messages data:', messages);
            
            let foundMessages = [];

            for (let i = 0; i < messages.length; i++) {
                const message = messages[i];
                const title = message.title.toLowerCase();
                const date = message.date.toLowerCase();
                
                // Debug the outline for this message
                console.log('Message outline:', message.outline);
                
                // Get all outline points as a single string for searching
                const outlineText = Array.isArray(message.outline) ? 
                    message.outline.map(point => {
                        if (typeof point === 'object' && point.text) {
                            return point.text;
                        } else if (typeof point === 'string') {
                            return point;
                        }
                        return '';
                    }).join(' ').toLowerCase() : '';
                
                console.log('Outline text for searching:', outlineText);
                
                // Check if search term exists in any part of the message
                const titleMatch = title.includes(searchTerm);
                const dateMatch = date.includes(searchTerm);
                const outlineMatch = outlineText.includes(searchTerm);
                
                console.log('Matches:', {
                    titleMatch,
                    dateMatch,
                    outlineMatch,
                    searchTerm,
                    outlineText
                });
                
                if (titleMatch || dateMatch || outlineMatch) {
                    // Find all matching outline points
                    let matchingOutlinePoints = [];
                    if (outlineMatch && Array.isArray(message.outline)) {
                        matchingOutlinePoints = message.outline.filter(point => {
                            const pointText = typeof point === 'object' && point.text ? point.text : 
                                            typeof point === 'string' ? point : '';
                            return pointText.toLowerCase().includes(searchTerm);
                        }).map(point => {
                            return typeof point === 'object' && point.text ? point.text : 
                                   typeof point === 'string' ? point : '';
                        });
                    }

                    foundMessages.push({
                        index: i,
                        message: message,
                        matchType: titleMatch ? 'title' : dateMatch ? 'date' : 'outline',
                        matchingOutlinePoints: matchingOutlinePoints,
                        fullOutline: outlineText
                    });
                }
            }

            console.log('Found messages:', foundMessages);

            if (foundMessages.length > 0) {
                // Display search results
                searchResults.innerHTML = foundMessages.map(result => {
                    const message = result.message;
                    const thumbnailUrl = `https://img.youtube.com/vi/${message.youtube_id}/mqdefault.jpg`;
                    
                    // Show all matching outline points or first two points if no matches
                    const outlinePreview = result.matchingOutlinePoints.length > 0 ?
                        result.matchingOutlinePoints.join(' | ') :
                        (Array.isArray(message.outline) ? message.outline.slice(0, 2).map(point => {
                            if (typeof point === 'object' && point.text) {
                                return point.text;
                            } else if (typeof point === 'string') {
                                return point;
                            }
                            return '';
                        }).join(' ') : '');
                    
                    return `
                        <div class="search-result-item" onclick="location.href='member_messages.php?message=${result.index}'">
                            <div class="search-result-thumbnail">
                                <img src="${thumbnailUrl}" alt="${message.title}">
                            </div>
                            <div class="search-result-content">
                                <div class="search-result-title">${highlightText(message.title, searchTerm)}</div>
                                <div class="search-result-date">${message.date}</div>
                                <div class="search-result-outline">${highlightText(outlinePreview, searchTerm)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                searchResults.classList.add('show');
                // Hide video container when showing search results
                document.getElementById('videoContainer').style.display = 'none';
            } else {
                searchInput.setCustomValidity('No messages found matching your search');
                searchInput.reportValidity();
                searchResults.classList.remove('show');
                // Show video container when no results
                document.getElementById('videoContainer').style.display = 'block';
                setTimeout(() => {
                    searchInput.setCustomValidity('');
                }, 2000);
            }
        }

        // Function to highlight matching text
        function highlightText(text, searchTerm) {
            if (!searchTerm || !text) return text;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<span class="highlight">$1</span>');
        }

        // Add enter key support for search
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchMessages();
            }
        });

        // Add search button click handler
        document.getElementById('search-btn').addEventListener('click', function(e) {
            e.preventDefault();
            searchMessages();
        });

        // Clear validation message and search results when user starts typing
        document.getElementById('search-input').addEventListener('input', function() {
            this.setCustomValidity('');
            if (this.value.trim() === '') {
                document.getElementById('search-results').classList.remove('show');
                document.getElementById('videoContainer').style.display = 'block';
            }
        });
    </script>
</body>
</html>