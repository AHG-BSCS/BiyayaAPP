<?php
session_start();
require_once 'config.php';
require_once 'user_functions.php';

// Check if user is logged in and is administrator only
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["user_role"] ?? '') !== "Administrator") {
    header("Location: index.php");
    exit;
}
// Define $is_admin as true for use in the rest of the file
$is_admin = true;

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Site configuration
$site_settings = getSiteSettings($conn);
$church_name = $site_settings['church_name'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch messages from database
$messages = [];
$sql = "SELECT * FROM messages ORDER BY date DESC";
$result = $conn->query($sql);

if (!$result) {
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
    echo '<div style="padding: 30px; text-align: center; color: #888;">No messages available. Please add a new message.</div>';
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
$church_logo = getChurchLogo($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
<?php
// Copy the entire <style> block from messages.php here
$messages_file = file_get_contents('messages.php');
if (preg_match('/<style>(.*?)<\/style>/s', $messages_file, $matches)) {
    echo $matches[1];
}
?>
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Toggle Button -->
        <div class="nav-toggle-container">
           <button class="nav-toggle-btn" type="button" id="nav-toggle">
           <i class="fas fa-bars"></i> Menu
           </button>
        </div>
        <!-- Custom Drawer Navigation -->
        <div id="drawer-navigation" class="custom-drawer">
            <div class="drawer-header">
                <div class="drawer-logo-section">
                    <img src="<?php echo htmlspecialchars($church_logo); ?>" alt="Church Logo" class="drawer-logo">
                    <h5 class="drawer-title"><?php echo $church_name; ?></h5>
                </div>
                <button type="button" class="drawer-close" id="drawer-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="drawer-content">
                <ul class="drawer-menu">
                    <li>
                        <a href="dashboard.php" class="drawer-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_events.php" class="drawer-link <?php echo $current_page == 'admin_events.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_prayers.php" class="drawer-link <?php echo $current_page == 'admin_prayers.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hands-praying"></i>
                            <span>Prayer Requests</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_messages.php" class="drawer-link <?php echo $current_page == 'admin_messages.php' ? 'active' : ''; ?>">
                            <i class="fas fa-video"></i>
                            <span>Messages</span>
                        </a>
                    </li>
                    <li>
                        <a href="financialreport.php" class="drawer-link <?php echo $current_page == 'financialreport.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Financial Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_expenses.php" class="drawer-link <?php echo $current_page == 'admin_expenses.php' ? 'active' : ''; ?>">
                            <i class="fas fa-receipt"></i>
                            <span>Monthly Expenses</span>
                        </a>
                    </li>
                    <li>
                        <a href="member_contributions.php" class="drawer-link <?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hand-holding-dollar"></i>
                            <span>Stewardship Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_settings.php" class="drawer-link <?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="drawer-profile">
                <div class="avatar">
                    <?php if (!empty($user_profile['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user_profile['profile_picture']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_profile['full_name'] ?? $user_profile['username'] ?? 'A', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <div class="name"><?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username'] ?? 'Unknown Admin'); ?></div>
                    <div class="role">Administrator</div>
                </div>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        <div id="drawer-overlay" class="drawer-overlay"></div>
        <main class="content-area">
            <div class="top-bar">
                <div>
                    <h2>Messages</h2>
                    <p style="margin-top: 5px; color: #666; font-size: 16px; font-weight: 400;">
                        Welcome, <?php echo htmlspecialchars($user_profile['full_name'] ?? $user_profile['username']); ?>
                    </p>
                </div>
            </div>
            <!-- Main content copied and adapted from messages.php -->
            <div class="search-container">
                <input type="text" id="search-input" placeholder="Search messages by title, date, or content..." required>
                <button onclick="searchMessages()">Search</button>
            </div>
            <div class="search-results" id="search-results"></div>
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" id="success-message">
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
            <div class="video-container">
                <div class="message-title">
                    <?php echo $message['title']; ?>
                    <span class="message-date"><?php echo date('F d, Y', strtotime($message['date'])); ?></span>
                </div>
                <div class="video-wrapper">
                    <iframe 
                        src="https://www.youtube.com/embed/<?php echo $message['youtube_id']; ?>?rel=0" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                </div>
                <div class="message-controls">
                    <div class="navigation-buttons">
                        <button id="prev-btn" <?php echo $current_message == 0 ? 'disabled' : ''; ?> 
                            onclick="location.href='admin_messages.php?message=<?php echo $current_message - 1; ?>'">
                            Previous
                        </button>
                        <button id="next-btn" <?php echo $current_message == count($messages) - 1 ? 'disabled' : ''; ?> 
                            onclick="location.href='admin_messages.php?message=<?php echo $current_message + 1; ?>'">
                            Next
                        </button>
                    </div>
                    <button class="outline-toggle" id="outline-toggle">Show Outline</button>
                </div>
                <div class="message-outline" id="message-outline">
                    <h3>Message Outline</h3>
                    <ul>
                        <?php foreach ($message['outline'] as $point): ?>
                            <li class="<?php echo (strpos($point, 'Main Point') !== false || strpos($point, 'I.') !== false || strpos($point, 'II.') !== false || strpos($point, 'III.') !== false || strpos($point, 'IV.') !== false || strpos($point,'V.') !==false) !== false || strpos($point, 'TEXT:') !==false? 'bold' : ''; ?>"><?php echo $point; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Auto-hide success message after 3 seconds
        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 300);
            }, 3000);
        }

        // Search functionality
        function searchMessages() {
            const searchInput = document.getElementById('search-input');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const searchResults = document.getElementById('search-results');
            if (!searchTerm) {
                searchInput.setCustomValidity('Please enter a search term');
                searchInput.reportValidity();
                return;
            }
            searchInput.setCustomValidity('');
            const messages = <?php 
                $messagesData = array_map(function($msg) {
                    $outline = $msg['outline'];
                    if (is_string($outline)) {
                        $decoded = json_decode($outline, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $outline = $decoded;
                        } else {
                            $outline = [$outline];
                        }
                    }
                    if (!is_array($outline)) {
                        $outline = [];
                    }
                    return [
                        'id' => $msg['id'],
                        'title' => $msg['title'],
                        'youtube_id' => $msg['youtube_id'],
                        'date' => $msg['date'],
                        'outline' => $outline
                    ];
                }, $messages);
                echo json_encode($messagesData);
            ?>;
            let foundMessages = [];
            for (let i = 0; i < messages.length; i++) {
                const message = messages[i];
                const title = message.title.toLowerCase();
                const date = message.date.toLowerCase();
                const outlineText = Array.isArray(message.outline) ? message.outline.join(' ').toLowerCase() : '';
                const titleMatch = title.includes(searchTerm);
                const dateMatch = date.includes(searchTerm);
                const outlineMatch = outlineText.includes(searchTerm);
                if (titleMatch || dateMatch || outlineMatch) {
                    let matchingOutlinePoints = [];
                    if (outlineMatch && Array.isArray(message.outline)) {
                        matchingOutlinePoints = message.outline.filter(point => 
                            point.toLowerCase().includes(searchTerm)
                        );
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
            if (foundMessages.length > 0) {
                // Calculate occurrences for each message and add to result object
                foundMessages.forEach(result => {
                    const message = result.message;
                    const titleText = message.title || '';
                    const dateText = message.date || '';
                    const outlineText = Array.isArray(message.outline) ? 
                        message.outline.join(' ') : '';
                    
                    result.occurrences = countOccurrences(titleText, searchTerm) +
                                       countOccurrences(dateText, searchTerm) +
                                       countOccurrences(outlineText, searchTerm);
                });

                // Sort by occurrences (highest first)
                foundMessages.sort((a, b) => b.occurrences - a.occurrences);

                // Display search results with individual counts
                const resultsHTML = `
                    <div class="search-results-header">
                        <div class="search-results-title">Search Results (${foundMessages.length} ${foundMessages.length === 1 ? 'message' : 'messages'})</div>
                    </div>
                    ${foundMessages.map(result => {
                        const message = result.message;
                        const thumbnailUrl = `https://img.youtube.com/vi/${message.youtube_id}/mqdefault.jpg`;
                        const messageOccurrences = result.occurrences;
                        
                        const outlinePreview = result.matchingOutlinePoints.length > 0 ?
                            result.matchingOutlinePoints.join(' | ') :
                            (Array.isArray(message.outline) ? message.outline.slice(0, 2).join(' ') : '');
                        return `
                            <div class="search-result-item" onclick="location.href='admin_messages.php?message=${result.index}'">
                                <div class="search-result-thumbnail">
                                    <img src="${thumbnailUrl}" alt="${message.title}">
                                </div>
                                <div class="search-result-content">
                                    <div class="search-result-title">${highlightText(message.title, searchTerm)}</div>
                                    <div class="search-result-date">${message.date}</div>
                                    <div class="search-result-outline">${highlightText(outlinePreview, searchTerm)}</div>
                                </div>
                                <div class="search-result-count">The word "${searchTerm}" appears ${messageOccurrences} ${messageOccurrences === 1 ? 'time' : 'times'}</div>
                            </div>
                        `;
                    }).join('')}
                `;
                
                searchResults.innerHTML = resultsHTML;
                searchResults.classList.add('show');
            } else {
                searchInput.setCustomValidity('No messages found matching your search');
                searchInput.reportValidity();
                searchResults.classList.remove('show');
                setTimeout(() => {
                    searchInput.setCustomValidity('');
                }, 2000);
            }
        }
        // Function to count occurrences of a term in text
        function countOccurrences(text, searchTerm) {
            if (!text || !searchTerm) return 0;
            const regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
            const matches = text.match(regex);
            return matches ? matches.length : 0;
        }

        function highlightText(text, searchTerm) {
            if (!searchTerm) return text;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<span class="highlight">$1</span>');
        }
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchMessages();
            }
        });
        document.querySelector('.search-container button').addEventListener('click', function(e) {
            e.preventDefault();
            searchMessages();
        });
        document.getElementById('search-input').addEventListener('input', function() {
            this.setCustomValidity('');
            if (this.value.trim() === '') {
                document.getElementById('search-results').classList.remove('show');
            }
        });
        document.getElementById('outline-toggle').addEventListener('click', function() {
            const outline = document.getElementById('message-outline');
            const isShown = outline.classList.contains('show');
            outline.classList.toggle('show');
            this.textContent = isShown ? 'Show Outline' : 'Hide Outline';
        });
        // Custom Drawer Navigation JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.getElementById('nav-toggle');
            const drawer = document.getElementById('drawer-navigation');
            const drawerClose = document.getElementById('drawer-close');
            const overlay = document.getElementById('drawer-overlay');
            navToggle.addEventListener('click', function() {
                drawer.classList.add('open');
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
            function closeDrawer() {
                drawer.classList.remove('open');
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
            drawerClose.addEventListener('click', closeDrawer);
            overlay.addEventListener('click', closeDrawer);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDrawer();
                }
            });
        });
    </script>
</body>
</html> 