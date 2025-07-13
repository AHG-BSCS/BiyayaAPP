<?php
// Database configuration
$servername = "localhost";
$username = "root";  // Your MySQL username
$password = "";      // Your MySQL password
$dbname = "churchdb";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select the database
$conn->select_db($dbname);

// Create site_settings table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY,
    church_name VARCHAR(255) NOT NULL,
    tagline VARCHAR(255) NOT NULL,
    tagline2 VARCHAR(255) NOT NULL,
    about_us TEXT NOT NULL,
    church_address TEXT NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    church_logo VARCHAR(255) NOT NULL,
    login_background VARCHAR(255) NOT NULL,
    service_times TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    // Check if default values exist
    $result = $conn->query("SELECT COUNT(*) as count FROM site_settings WHERE id = 1");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insert default values
        $sql = "INSERT INTO site_settings (id, church_name, tagline, tagline2, about_us, church_address, phone_number, email_address, church_logo, login_background, service_times)
                VALUES (
                    1,
                    'Church of Christ-Disciples',
                    'To God be the Glory',
                    'Becoming Christlike and Blessing Others',
                    'Welcome to Church of Christ-Disciples. We are a community of believers dedicated to sharing God\'s love and message with the world.',
                    '25 Artemio B. Fule St., San Pablo City, Laguna 4000 Philippines',
                    '0927 012 7127',
                    'cocd1910@gmail.com',
                    'logo/cocd_icon.png',
                    'logo/churchpic.jpg',
                    '{\"Sunday Worship Service\": \"9:00 AM - 11:00 AM\", \"Prayer Intercession\": \"5:00 PM - 7:00 PM\"}'
                )";
        $conn->query($sql);
    }
}

// Create user_profiles table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS user_profiles (
    user_id VARCHAR(50) PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20),
    address TEXT,
    password VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255),
    role ENUM('Member', 'Pastor', 'Administrator') NOT NULL DEFAULT 'Member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    // Check if admin profile exists
    $result = $conn->query("SELECT COUNT(*) as count FROM user_profiles WHERE user_id = 'admin'");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insert default admin profile with hashed password
        $admin_password = password_hash('church123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO user_profiles (user_id, username, full_name, email, password, role) 
                VALUES ('admin', 'admin', 'Administrator', 'cocd1910@gmail.com', ?, 'Administrator')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $admin_password);
        $stmt->execute();
    }
}

// Add new columns if they don't exist
$result = $conn->query("SHOW COLUMNS FROM user_profiles LIKE 'full_name'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE user_profiles ADD COLUMN full_name VARCHAR(255) AFTER username";
    $conn->query($sql);
}

$result = $conn->query("SHOW COLUMNS FROM user_profiles LIKE 'contact_number'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE user_profiles ADD COLUMN contact_number VARCHAR(20) AFTER email";
    $conn->query($sql);
}

$result = $conn->query("SHOW COLUMNS FROM user_profiles LIKE 'address'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE user_profiles ADD COLUMN address TEXT AFTER contact_number";
    $conn->query($sql);
}

// Add password column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM user_profiles LIKE 'password'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE user_profiles ADD COLUMN password VARCHAR(255) NOT NULL AFTER address";
    $conn->query($sql);
    
    // Update admin password if it's not set
    $result = $conn->query("SELECT password FROM user_profiles WHERE user_id = 'admin'");
    $row = $result->fetch_assoc();
    if (empty($row['password'])) {
        $admin_password = password_hash('church123', PASSWORD_DEFAULT);
        $sql = "UPDATE user_profiles SET password = ? WHERE user_id = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $admin_password);
        $stmt->execute();
    }
}

// Create membership_records table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS membership_records (
    id VARCHAR(10) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    join_date DATE NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    nickname VARCHAR(100),
    address TEXT,
    telephone VARCHAR(20),
    cellphone VARCHAR(20),
    email VARCHAR(100),
    civil_status VARCHAR(20),
    sex VARCHAR(10),
    birthday DATE,
    father_name VARCHAR(255),
    mother_name VARCHAR(255),
    children TEXT,
    education VARCHAR(100),
    course VARCHAR(100),
    school VARCHAR(255),
    year VARCHAR(4),
    company VARCHAR(255),
    position VARCHAR(100),
    business TEXT,
    spiritual_birthday DATE,
    inviter VARCHAR(255),
    how_know TEXT,
    attendance_duration VARCHAR(100),
    previous_church VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create baptismal_records table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS baptismal_records (
    id VARCHAR(10) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    nickname VARCHAR(100),
    address TEXT,
    telephone VARCHAR(20),
    cellphone VARCHAR(20),
    email VARCHAR(100),
    civil_status VARCHAR(20),
    sex VARCHAR(10),
    birthday DATE,
    father_name VARCHAR(255),
    mother_name VARCHAR(255),
    children TEXT,
    education VARCHAR(100),
    course VARCHAR(100),
    school VARCHAR(255),
    year VARCHAR(4),
    company VARCHAR(255),
    position VARCHAR(100),
    business TEXT,
    spiritual_birthday DATE,
    inviter VARCHAR(255),
    how_did_you_know TEXT,
    attendance_duration VARCHAR(100),
    previous_church VARCHAR(255),
    baptism_date DATE NOT NULL,
    officiant VARCHAR(255) NOT NULL,
    venue VARCHAR(255),
    witnesses TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create marriage_records table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS marriage_records (
    id VARCHAR(10) PRIMARY KEY,
    couple VARCHAR(255) NOT NULL,
    marriage_date DATE NOT NULL,
    venue VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create child_dedication_records table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS child_dedication_records (
    id VARCHAR(10) PRIMARY KEY,
    child_name VARCHAR(255) NOT NULL,
    child_birthday DATE NOT NULL,
    birth_place VARCHAR(255) NOT NULL,
    father_name VARCHAR(255) NOT NULL,
    mother_name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    father_lolo VARCHAR(255),
    father_lola VARCHAR(255),
    mother_lolo VARCHAR(255),
    mother_lola VARCHAR(255),
    officiating_pastor VARCHAR(255) NOT NULL,
    dedication_datetime DATETIME NOT NULL,
    dedication_place VARCHAR(255) NOT NULL,
    godparents TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create visitor_records table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS visitor_records (
    id VARCHAR(10) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    visit_date DATE NOT NULL,
    contact VARCHAR(100),
    address TEXT,
    purpose TEXT,
    invited_by VARCHAR(255),
    status ENUM('First Time', 'Returning', 'Regular') DEFAULT 'First Time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create burial_records table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS burial_records (
    id VARCHAR(10) PRIMARY KEY,
    deceased_name VARCHAR(255) NOT NULL,
    burial_date DATE NOT NULL,
    officiant VARCHAR(255) NOT NULL,
    venue VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create login_logs table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS login_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    login_time DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('Success', 'Failed') NOT NULL,
    failure_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create certificate_settings table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS certificate_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    membership_class_date DATE,
    certificate_issued_date DATE,
    certificate_template TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    // Check if any settings exist
    $result = $conn->query("SELECT COUNT(*) as count FROM certificate_settings");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insert default certificate template
        $default_template = '<div class="certificate">
            <div class="header">
                <img src="logo/cocd_icon.png" alt="Church Logo" class="logo">
                <h1>Church of Christ-Disciples</h1>
                <h2>Certificate of Membership</h2>
            </div>
            <div class="content">
                <p>This is to certify that</p>
                <h3 class="member-name">{MEMBER_NAME}</h3>
                <p>has completed the Membership Class on</p>
                <p class="date">{MEMBERSHIP_CLASS_DATE}</p>
                <p>and is hereby recognized as an official member of</p>
                <p class="church-name">Church of Christ-Disciples</p>
                <p>Certificate issued on</p>
                <p class="date">{CERTIFICATE_DATE}</p>
            </div>
            <div class="footer">
                <div class="signature">
                    <p>_______________________</p>
                    <p>Pastor</p>
                </div>
                <div class="seal">
                    <p>Church Seal</p>
                </div>
            </div>
        </div>';
        
        $sql = "INSERT INTO certificate_settings (membership_class_date, certificate_issued_date, certificate_template) 
                VALUES (CURRENT_DATE, CURRENT_DATE, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $default_template);
        $stmt->execute();
    }
}

// Create tithes table
$sql = "CREATE TABLE IF NOT EXISTS tithes (
    id INT NOT NULL,
    date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
)";
$conn->query($sql);

// Create offerings table
$sql = "CREATE TABLE IF NOT EXISTS offerings (
    id INT NOT NULL,
    date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
)";
$conn->query($sql);

// Create bank_gifts table
$sql = "CREATE TABLE IF NOT EXISTS bank_gifts (
    id INT NOT NULL,
    date DATE NOT NULL,
    date_deposited DATE NOT NULL,
    date_updated DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
)";
$conn->query($sql);

// Create specified_gifts table
$sql = "CREATE TABLE IF NOT EXISTS specified_gifts (
    id INT NOT NULL,
    date DATE NOT NULL,
    category VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
)";
$conn->query($sql);

// Create prayer_requests table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS prayer_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_name VARCHAR(255) NOT NULL,
    prayer_request TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    urgency ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
    anonymous BOOLEAN DEFAULT FALSE,
    heart_reactions INT DEFAULT 0,
    praying_reactions INT DEFAULT 0,
    like_reactions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Insert sample prayer requests if table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM prayer_requests");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $sample_requests = [
        [
            'member_name' => 'John Doe',
            'prayer_request' => 'Please pray for my mother who is in the hospital. She\'s been diagnosed with pneumonia and needs strength to recover.',
            'category' => 'Health',
            'urgency' => 'urgent',
            'anonymous' => 0,
            'heart_reactions' => 3,
            'praying_reactions' => 5,
            'like_reactions' => 2
        ],
        [
            'member_name' => 'Anonymous',
            'prayer_request' => 'I\'m struggling with my finances and need prayer for God\'s provision. Please pray for wisdom in managing my resources.',
            'category' => 'Financial',
            'urgency' => 'normal',
            'anonymous' => 1,
            'heart_reactions' => 1,
            'praying_reactions' => 4,
            'like_reactions' => 1
        ],
        [
            'member_name' => 'Sarah Smith',
            'prayer_request' => 'My family is going through a difficult time. Please pray for unity, understanding, and God\'s peace to reign in our home.',
            'category' => 'Family',
            'urgency' => 'normal',
            'anonymous' => 0,
            'heart_reactions' => 2,
            'praying_reactions' => 3,
            'like_reactions' => 1
        ]
    ];
    
    foreach ($sample_requests as $request) {
        $sql = "INSERT INTO prayer_requests (member_name, prayer_request, category, urgency, anonymous, heart_reactions, praying_reactions, like_reactions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiiii", 
            $request['member_name'],
            $request['prayer_request'],
            $request['category'],
            $request['urgency'],
            $request['anonymous'],
            $request['heart_reactions'],
            $request['praying_reactions'],
            $request['like_reactions']
        );
        $stmt->execute();
    }
}

// Function to get site settings
function getSiteSettings($conn) {
    $sql = "SELECT * FROM site_settings WHERE id = 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    // Return default values if no settings found
    return [
        'church_name' => 'Church of Christ-Disciples',
        'tagline' => 'To God be the Glory',
        'tagline2' => 'Becoming Christlike and Blessing Others',
        'about_us' => 'Welcome to Church of Christ-Disciples. We are a community of believers dedicated to sharing God\'s love and message with the world.',
        'church_address' => '25 Artemio B. Fule St., San Pablo City, Laguna 4000 Philippines',
        'phone_number' => '0927 012 7127',
        'email_address' => 'cocd1910@gmail.com',
        'church_logo' => 'logo/cocd_icon.png',
        'login_background' => 'logo/churchpic.jpg',
        'service_times' => json_encode([
            'Sunday Worship Service' => '9:00 AM - 11:00 AM',
            'Prayer Intercession' => '5:00 PM - 7:00 PM'
        ])
    ];
}

// Function to update site settings
function updateSiteSettings($conn, $settings) {
    $sql = "INSERT INTO site_settings (id, church_name, tagline, tagline2, about_us, church_address, phone_number, email_address, church_logo, login_background, service_times) 
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            church_name = VALUES(church_name),
            tagline = VALUES(tagline),
            tagline2 = VALUES(tagline2),
            about_us = VALUES(about_us),
            church_address = VALUES(church_address),
            phone_number = VALUES(phone_number),
            email_address = VALUES(email_address),
            church_logo = VALUES(church_logo),
            login_background = VALUES(login_background),
            service_times = VALUES(service_times)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", 
        $settings['church_name'],
        $settings['tagline'],
        $settings['tagline2'],
        $settings['about_us'],
        $settings['church_address'],
        $settings['phone_number'],
        $settings['email_address'],
        $settings['church_logo'],
        $settings['login_background'],
        $settings['service_times']
    );
    
    return $stmt->execute();
}

// Function to get church logo URL
function getChurchLogo($conn) {
    $settings = getSiteSettings($conn);
    return !empty($settings['church_logo']) ? $settings['church_logo'] : 'logo/cocd_icon.png';
}

// Include user functions
require_once 'user_functions.php';
?> 