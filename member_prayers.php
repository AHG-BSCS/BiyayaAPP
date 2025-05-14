<?php
// Member Prayer Requests page
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

// Check if the user is a member
$is_member = ($_SESSION["user_role"] === "Member");

if (!$is_member) {
    header("Location: dashboard.php");
    exit;
}

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Process prayer request submission
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prayer_request = trim($_POST["prayer_request"] ?? "");
    if (!empty($prayer_request)) {
        // In a real app, save to database
        $message = "Prayer request submitted successfully!";
        $messageType = "success";
    } else {
        $message = "Please enter a prayer request.";
        $messageType = "danger";
    }
}

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);
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
        }
        
        .card h3 {
            margin-bottom: 15px;
            font-size: 18px;
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
            min-height: 150px;
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
        }

        .calendar-card {
            margin-bottom: 30px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .calendar-nav h4 {
            font-size: 18px;
            font-weight: 500;
            min-width: 150px;
            text-align: center;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #f0f0f0;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            background-color: var(--accent-color);
            color: white;
            transform: scale(1.1);
        }

        .calendar-grid {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background-color: var(--accent-color);
            color: white;
            padding: 10px 0;
        }

        .calendar-weekdays div {
            text-align: center;
            font-weight: 500;
            font-size: 14px;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: #f0f0f0;
        }

        .calendar-day {
            aspect-ratio: 1;
            background-color: white;
            padding: 5px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .calendar-day:hover {
            background-color: #f8f8f8;
        }

        .calendar-day.today {
            background-color: rgba(0, 139, 30, 0.1);
        }

        .calendar-day.has-prayers {
            background-color: rgba(33, 150, 243, 0.1);
        }

        .calendar-day.has-prayers::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background-color: var(--accent-color);
            border-radius: 50%;
        }

        .calendar-day.other-month {
            color: #ccc;
        }

        .calendar-day-number {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .prayer-count {
            font-size: 12px;
            color: var(--accent-color);
        }

        .prayer-tooltip {
            position: absolute;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
            z-index: 1000;
            min-width: 200px;
            display: none;
        }

        .prayer-tooltip.show {
            display: block;
        }

        .prayer-tooltip h4 {
            margin-bottom: 5px;
            color: var(--accent-color);
        }

        .prayer-tooltip ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .prayer-tooltip li {
            font-size: 12px;
            margin-bottom: 3px;
            color: #666;
        }

        @media (max-width: 768px) {
            .calendar-header {
                flex-direction: column;
                gap: 15px;
            }

            .calendar-weekdays div {
                font-size: 12px;
            }

            .calendar-day-number {
                font-size: 12px;
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
                    <li><a href="member_prayers.php" class="<?php echo $current_page == 'member_prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
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
                            <?php echo strtoupper(substr($user_profile['username'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_profile['username'] ?? 'Unknown User'); ?></h4>
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

                <!-- Calendar View -->
                <div class="card calendar-card">
                    <div class="calendar-header">
                        <h3>Prayer Calendar</h3>
                        <div class="calendar-nav">
                            <button class="btn btn-icon" id="prevMonth">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h4 id="currentMonth">September 2023</h4>
                            <button class="btn btn-icon" id="nextMonth">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="calendar-grid">
                        <div class="calendar-weekdays">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>
                        <div class="calendar-days" id="calendarDays">
                            <!-- Calendar days will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Prayer Request Form -->
                <div class="card">
                    <h3>Submit a Prayer Request</h3>
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="prayer_title">Title of Prayer Request</label>
                            <input type="text" id="prayer_title" name="prayer_title" class="form-control" placeholder="Brief title for your prayer request" required>
                        </div>
                        
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
                            <textarea id="prayer_request" name="prayer_request" class="form-control" placeholder="Please share your prayer request in detail. We are here to support you in prayer." required></textarea>
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
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Prayer Request
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Clear Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarDays = document.getElementById('calendarDays');
            const currentMonthElement = document.getElementById('currentMonth');
            const prevMonthBtn = document.getElementById('prevMonth');
            const nextMonthBtn = document.getElementById('nextMonth');

            let currentDate = new Date();
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();

            function updateCalendar() {
                const firstDay = new Date(currentYear, currentMonth, 1);
                const lastDay = new Date(currentYear, currentMonth + 1, 0);
                const startingDay = firstDay.getDay();
                const totalDays = lastDay.getDate();

                // Update month and year display
                currentMonthElement.textContent = `${firstDay.toLocaleString('default', { month: 'long' })} ${currentYear}`;

                // Clear previous calendar
                calendarDays.innerHTML = '';

                // Add empty cells for days before the first day of the month
                for (let i = 0; i < startingDay; i++) {
                    const prevMonthDay = new Date(currentYear, currentMonth, -i);
                    const dayElement = createDayElement(prevMonthDay.getDate(), true);
                    calendarDays.appendChild(dayElement);
                }

                // Add days of the current month
                for (let day = 1; day <= totalDays; day++) {
                    const date = new Date(currentYear, currentMonth, day);
                    const isToday = date.toDateString() === new Date().toDateString();
                    const dayElement = createDayElement(day, false, isToday);
                    calendarDays.appendChild(dayElement);
                }

                // Add empty cells for remaining days
                const remainingDays = 42 - (startingDay + totalDays);
                for (let i = 1; i <= remainingDays; i++) {
                    const nextMonthDay = new Date(currentYear, currentMonth + 1, i);
                    const dayElement = createDayElement(nextMonthDay.getDate(), true);
                    calendarDays.appendChild(dayElement);
                }
            }

            function createDayElement(day, isOtherMonth, isToday = false) {
                const dayElement = document.createElement('div');
                dayElement.className = `calendar-day ${isOtherMonth ? 'other-month' : ''} ${isToday ? 'today' : ''}`;
                
                const dayNumber = document.createElement('div');
                dayNumber.className = 'calendar-day-number';
                dayNumber.textContent = day;
                dayElement.appendChild(dayNumber);

                // Simulate prayer requests (replace with actual data)
                if (Math.random() > 0.7 && !isOtherMonth) {
                    dayElement.classList.add('has-prayers');
                    const prayerCount = document.createElement('div');
                    prayerCount.className = 'prayer-count';
                    prayerCount.textContent = `${Math.floor(Math.random() * 5) + 1} prayers`;
                    dayElement.appendChild(prayerCount);

                    // Add tooltip
                    const tooltip = document.createElement('div');
                    tooltip.className = 'prayer-tooltip';
                    tooltip.innerHTML = `
                        <h4>Prayer Requests</h4>
                        <ul>
                            <li>Prayer for healing</li>
                            <li>Family prayer request</li>
                            <li>Financial blessing</li>
                        </ul>
                    `;
                    dayElement.appendChild(tooltip);

                    // Show tooltip on hover
                    dayElement.addEventListener('mouseenter', () => {
                        tooltip.classList.add('show');
                    });

                    dayElement.addEventListener('mouseleave', () => {
                        tooltip.classList.remove('show');
                    });
                }

                return dayElement;
            }

            // Event listeners for month navigation
            prevMonthBtn.addEventListener('click', () => {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                updateCalendar();
            });

            nextMonthBtn.addEventListener('click', () => {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                updateCalendar();
            });

            // Initial calendar render
            updateCalendar();
        });
    </script>
</body>
</html>