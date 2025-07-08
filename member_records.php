<?php
# member_records.php
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

// Check if user is an admin
$is_admin = ($_SESSION["user_role"] === "Administrator");

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize session arrays if not set
if (!isset($_SESSION['membership_records'])) {
    $_SESSION['membership_records'] = [];
}
if (!isset($_SESSION['baptismal_records'])) {
    $_SESSION['baptismal_records'] = [];
}
if (!isset($_SESSION['marriage_records'])) {
    $_SESSION['marriage_records'] = [
        ["id" => "W001", "couple" => "Al John & Beep", "marriage_date" => "2030-01-01", "venue" => "Jollibee"]
    ];
}
if (!isset($_SESSION['child_dedication_records'])) {
    $_SESSION['child_dedication_records'] = [
        ["id" => "C001", "child_name" => "Baby John", "dedication_date" => "2024-01-15", "parents" => "John & Mary"]
    ];
}

// Initialize visitor records if not set
if (!isset($_SESSION['visitor_records'])) {
    $_SESSION['visitor_records'] = [
        [
            "id" => "V001",
            "name" => "John Doe",
            "visit_date" => "2024-03-15",
            "contact" => "09123456789",
            "address" => "123 Main St",
            "purpose" => "Sunday Service",
            "invited_by" => "Pastor James",
            "status" => "First Time"
        ]
    ];
}

// Fetch membership records from database
try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->query("SELECT * FROM membership_records ORDER BY id");
    $membership_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $membership_records = [];
    $message = "Error fetching records: " . $e->getMessage();
    $messageType = "danger";
}

// Fetch baptismal records from database
try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $conn->query("SELECT * FROM baptismal_records ORDER BY id");
    $baptismal_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $baptismal_records = [];
    $message = "Error fetching baptismal records: " . $e->getMessage();
    $messageType = "danger";
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_membership"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the next ID
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM membership_records");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = "M" . sprintf("%03d", ($result['max_id'] ?? 0) + 1);

        // Store POST values in variables
        $name = $_POST['name'];
        $join_date = date('Y-m-d');
        $status = 'Active';
        $nickname = $_POST['nickname'];
        $address = $_POST['address'];
        $telephone = $_POST['telephone'];
        $cellphone = $_POST['cellphone'];
        $email = $_POST['email'];
        $civil_status = $_POST['civil_status'];
        $sex = $_POST['sex'];
        $birthday = $_POST['birthday'];
        $father_name = $_POST['father_name'];
        $mother_name = $_POST['mother_name'];
        $children = $_POST['children'];
        $education = $_POST['education'];
        $course = $_POST['course'];
        $school = $_POST['school'];
        $year = $_POST['year'];
        $company = $_POST['company'];
        $position = $_POST['position'];
        $business = $_POST['business'];
        $spiritual_birthday = $_POST['spiritual_birthday'];
        $inviter = $_POST['inviter'];
        $how_know = $_POST['how_know'];
        $attendance_duration = $_POST['attendance_duration'];
        $previous_church = $_POST['previous_church'];

        // Prepare SQL statement
        $sql = "INSERT INTO membership_records (
            id, name, join_date, status, nickname, address, telephone, cellphone, 
            email, civil_status, sex, birthday, father_name, mother_name, children, 
            education, course, school, year, company, position, business, 
            spiritual_birthday, inviter, how_know, attendance_duration, previous_church
        ) VALUES (
            :id, :name, :join_date, :status, :nickname, :address, :telephone, :cellphone,
            :email, :civil_status, :sex, :birthday, :father_name, :mother_name, :children,
            :education, :course, :school, :year, :company, :position, :business,
            :spiritual_birthday, :inviter, :how_know, :attendance_duration, :previous_church
        )";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters using variables
        $stmt->bindParam(':id', $next_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':join_date', $join_date);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':nickname', $nickname);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':cellphone', $cellphone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':civil_status', $civil_status);
        $stmt->bindParam(':sex', $sex);
        $stmt->bindParam(':birthday', $birthday);
        $stmt->bindParam(':father_name', $father_name);
        $stmt->bindParam(':mother_name', $mother_name);
        $stmt->bindParam(':children', $children);
        $stmt->bindParam(':education', $education);
        $stmt->bindParam(':course', $course);
        $stmt->bindParam(':school', $school);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':company', $company);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':business', $business);
        $stmt->bindParam(':spiritual_birthday', $spiritual_birthday);
        $stmt->bindParam(':inviter', $inviter);
        $stmt->bindParam(':how_know', $how_know);
        $stmt->bindParam(':attendance_duration', $attendance_duration);
        $stmt->bindParam(':previous_church', $previous_church);

        // Execute the statement
        $stmt->execute();

        $message = "New member added successfully!";
    $messageType = "success";

        // Refresh the page to show the new record and stay on baptismal tab
        header("Location: " . $_SERVER['PHP_SELF'] . "#baptismal");
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_membership"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];
        $name = $_POST['name'];
        $join_date = $_POST['join_date'];
        $status = $_POST['status'];
        $nickname = $_POST['nickname'];
        $address = $_POST['address'];
        $telephone = $_POST['telephone'];
        $cellphone = $_POST['cellphone'];
        $email = $_POST['email'];
        $civil_status = $_POST['civil_status'];
        $sex = $_POST['sex'];
        $birthday = $_POST['birthday'];
        $father_name = $_POST['father_name'];
        $mother_name = $_POST['mother_name'];
        $children = $_POST['children'];
        $education = $_POST['education'];
        $course = $_POST['course'];
        $school = $_POST['school'];
        $year = $_POST['year'];
        $company = $_POST['company'];
        $position = $_POST['position'];
        $business = $_POST['business'];
        $spiritual_birthday = $_POST['spiritual_birthday'];
        $inviter = $_POST['inviter'];
        $how_know = $_POST['how_know'];
        $attendance_duration = $_POST['attendance_duration'];
        $previous_church = $_POST['previous_church'];

        // Prepare SQL statement
        $sql = "UPDATE membership_records SET 
                name = :name,
                join_date = :join_date,
                status = :status,
                nickname = :nickname,
                address = :address,
                telephone = :telephone,
                cellphone = :cellphone,
                email = :email,
                civil_status = :civil_status,
                sex = :sex,
                birthday = :birthday,
                father_name = :father_name,
                mother_name = :mother_name,
                children = :children,
                education = :education,
                course = :course,
                school = :school,
                year = :year,
                company = :company,
                position = :position,
                business = :business,
                spiritual_birthday = :spiritual_birthday,
                inviter = :inviter,
                how_know = :how_know,
                attendance_duration = :attendance_duration,
                previous_church = :previous_church
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':join_date', $join_date);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':nickname', $nickname);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':cellphone', $cellphone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':civil_status', $civil_status);
        $stmt->bindParam(':sex', $sex);
        $stmt->bindParam(':birthday', $birthday);
        $stmt->bindParam(':father_name', $father_name);
        $stmt->bindParam(':mother_name', $mother_name);
        $stmt->bindParam(':children', $children);
        $stmt->bindParam(':education', $education);
        $stmt->bindParam(':course', $course);
        $stmt->bindParam(':school', $school);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':company', $company);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':business', $business);
        $stmt->bindParam(':spiritual_birthday', $spiritual_birthday);
        $stmt->bindParam(':inviter', $inviter);
        $stmt->bindParam(':how_know', $how_know);
        $stmt->bindParam(':attendance_duration', $attendance_duration);
        $stmt->bindParam(':previous_church', $previous_church);

        // Execute the statement
        $stmt->execute();

        $message = "Member record updated successfully!";
            $messageType = "success";

        // Refresh the page to show the updated record
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_record"]) && $is_admin) {
    $id = $_POST['id'];
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($type === 'baptismal') {
            $stmt = $conn->prepare("DELETE FROM baptismal_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "Baptismal record deleted successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#baptismal");
            exit();
        } else if ($type === 'membership') {
            // Existing membership delete logic
            $stmt = $conn->prepare("SELECT name FROM membership_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            $memberName = $member ? $member['name'] : 'Unknown Member';
            $stmt = $conn->prepare("DELETE FROM membership_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "✅ Member record for <strong>{$memberName}</strong> (ID: {$id}) has been successfully deleted from the system.";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        // ... handle other types as needed ...
    } catch(PDOException $e) {
        $message = "Error deleting record: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_record"]) && $is_admin) {
    $id = htmlspecialchars(trim($_POST["id"]));
    $password = htmlspecialchars(trim($_POST["password"]));
    $record_type = htmlspecialchars(trim($_POST["type"]));
    
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password_db = "";
    $dbname = "church_db";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get admin's hashed password from database
        $stmt = $conn->prepare("SELECT password FROM users WHERE username = :username");
        $stmt->bindParam(':username', $_SESSION["user"]);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && password_verify($password, $result['password'])) {
            switch($record_type) {
                case 'membership':
                    $_SESSION['membership_records'] = array_filter($_SESSION['membership_records'], function($record) use ($id) {
                        return $record['id'] !== $id;
                    });
                    $_SESSION['membership_records'] = array_values($_SESSION['membership_records']);
                    break;
                case 'baptismal':
                    $_SESSION['baptismal_records'] = array_filter($_SESSION['baptismal_records'], function($record) use ($id) {
                        return $record['id'] !== $id;
                    });
                    $_SESSION['baptismal_records'] = array_values($_SESSION['baptismal_records']);
                    break;
                case 'marriage':
                    $_SESSION['marriage_records'] = array_filter($_SESSION['marriage_records'], function($record) use ($id) {
                        return $record['id'] !== $id;
                    });
                    $_SESSION['marriage_records'] = array_values($_SESSION['marriage_records']);
                    break;
                case 'child_dedication':
                    $_SESSION['child_dedication_records'] = array_filter($_SESSION['child_dedication_records'], function($record) use ($id) {
                        return $record['id'] !== $id;
                    });
                    $_SESSION['child_dedication_records'] = array_values($_SESSION['child_dedication_records']);
                    break;
            }
            $message = "Record deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Invalid password. Record not deleted.";
            $messageType = "danger";
        }
    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_status"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];
        $new_status = $_POST['status'];

        // Get member name and current status for better messaging
        $stmt = $conn->prepare("SELECT name, status FROM membership_records WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        $memberName = $member ? $member['name'] : 'Unknown Member';
        $oldStatus = $member ? $member['status'] : 'Unknown';

        // Prepare SQL statement
        $sql = "UPDATE membership_records SET status = :status WHERE id = :id";
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $new_status);

        // Execute the statement
        $stmt->execute();

        $statusIcon = $new_status === 'Active' ? '🟢' : '🔴';
        $message = "{$statusIcon} Member status updated successfully! <strong>{$memberName}</strong> (ID: {$id}) status changed from <span class='badge badge-" . ($oldStatus === 'Active' ? 'success' : 'warning') . "'>{$oldStatus}</span> to <span class='badge badge-" . ($newStatus === 'Active' ? 'success' : 'warning') . "'>{$newStatus}</span>.";
        $messageType = "success";

        // Refresh the page to show the updated record
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch(PDOException $e) {
        $message = "❌ Error: Unable to update member status. Please try again or contact support if the problem persists.";
        $messageType = "danger";
    }
    $conn = null;
}

// Handle visitor record additions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_visitor"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the next ID
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM visitor_records");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = "V" . sprintf("%03d", ($result['max_id'] ?? 0) + 1);

        // Store POST values in variables
        $name = $_POST['name'];
        $visit_date = $_POST['visit_date'];
        $contact = $_POST['contact'];
        $address = $_POST['address'];
        $purpose = $_POST['purpose'];
        $invited_by = $_POST['invited_by'];
        $status = $_POST['status'];

        // Prepare SQL statement
        $sql = "INSERT INTO visitor_records (
            id, name, visit_date, contact, address, purpose, invited_by, status
        ) VALUES (
            :id, :name, :visit_date, :contact, :address, :purpose, :invited_by, :status
        )";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $next_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':visit_date', $visit_date);
        $stmt->bindParam(':contact', $contact);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->bindParam(':invited_by', $invited_by);
        $stmt->bindParam(':status', $status);

        // Execute the statement
        $stmt->execute();

        $message = "New visitor record added successfully!";
        $messageType = "success";

        // Refresh the page to show the new record
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle visitor record edits
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_visitor"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];
        $name = $_POST['name'];
        $visit_date = $_POST['visit_date'];
        $contact = $_POST['contact'];
        $address = $_POST['address'];
        $purpose = $_POST['purpose'];
        $invited_by = $_POST['invited_by'];
        $status = $_POST['status'];

        // Prepare SQL statement
        $sql = "UPDATE visitor_records SET 
                name = :name,
                visit_date = :visit_date,
                contact = :contact,
                address = :address,
                purpose = :purpose,
                invited_by = :invited_by,
                status = :status
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':visit_date', $visit_date);
        $stmt->bindParam(':contact', $contact);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->bindParam(':invited_by', $invited_by);
        $stmt->bindParam(':status', $status);

        // Execute the statement
        $stmt->execute();

        $message = "Visitor record updated successfully!";
        $messageType = "success";

        // Refresh the page to show the updated record
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle visitor record deletions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_visitor"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];

        // Prepare SQL statement
        $sql = "DELETE FROM visitor_records WHERE id = :id";
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id);

        // Execute the statement
        $stmt->execute();

        $message = "Visitor record deleted successfully!";
        $messageType = "success";

        // Refresh the page to show the updated records
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_baptismal"]) && $is_admin) {
    $required_fields = [
        'name', 'nickname', 'address', 'telephone', 'cellphone', 'email', 'civil_status', 'sex', 'birthday',
        'father_name', 'mother_name', 'children', 'education', 'course', 'school', 'year', 'company', 'position',
        'business', 'spiritual_birthday', 'inviter', 'how_know', 'attendance_duration', 'previous_church',
        'baptism_date', 'officiant', 'venue'
    ];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            $message = "Error: All fields are required. Please fill in the $field field.";
            $messageType = "danger";
            break;
        }
    }
    if (!empty($message)) {
        // Do not proceed if validation failed
    } else {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "churchdb";
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM baptismal_records");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = "B" . sprintf("%03d", ($result['max_id'] ?? 0) + 1);
            // Store POST values in variables
            $name = $_POST['name'];
            $nickname = $_POST['nickname'];
            $address = $_POST['address'];
            $telephone = $_POST['telephone'];
            $cellphone = $_POST['cellphone'];
            $email = $_POST['email'];
            $civil_status = $_POST['civil_status'];
            $sex = $_POST['sex'];
            $birthday = $_POST['birthday'];
            $father_name = $_POST['father_name'];
            $mother_name = $_POST['mother_name'];
            $children = $_POST['children'];
            $education = $_POST['education'];
            $course = $_POST['course'];
            $school = $_POST['school'];
            $year = $_POST['year'];
            $company = $_POST['company'];
            $position = $_POST['position'];
            $business = $_POST['business'];
            $spiritual_birthday = $_POST['spiritual_birthday'];
            $inviter = $_POST['inviter'];
            $how_know = $_POST['how_know'];
            $attendance_duration = $_POST['attendance_duration'];
            $previous_church = $_POST['previous_church'];
            $baptism_date = $_POST['baptism_date'];
            $officiant = $_POST['officiant'];
            $venue = $_POST['venue'];
            $sql = "INSERT INTO baptismal_records (id, name, nickname, address, telephone, cellphone, email, civil_status, sex, birthday, father_name, mother_name, children, education, course, school, year, company, position, business, spiritual_birthday, inviter, how_know, attendance_duration, previous_church, baptism_date, officiant, venue) VALUES (:id, :name, :nickname, :address, :telephone, :cellphone, :email, :civil_status, :sex, :birthday, :father_name, :mother_name, :children, :education, :course, :school, :year, :company, :position, :business, :spiritual_birthday, :inviter, :how_know, :attendance_duration, :previous_church, :baptism_date, :officiant, :venue)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $next_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':nickname', $nickname);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':telephone', $telephone);
            $stmt->bindParam(':cellphone', $cellphone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':civil_status', $civil_status);
            $stmt->bindParam(':sex', $sex);
            $stmt->bindParam(':birthday', $birthday);
            $stmt->bindParam(':father_name', $father_name);
            $stmt->bindParam(':mother_name', $mother_name);
            $stmt->bindParam(':children', $children);
            $stmt->bindParam(':education', $education);
            $stmt->bindParam(':course', $course);
            $stmt->bindParam(':school', $school);
            $stmt->bindParam(':year', $year);
            $stmt->bindParam(':company', $company);
            $stmt->bindParam(':position', $position);
            $stmt->bindParam(':business', $business);
            $stmt->bindParam(':spiritual_birthday', $spiritual_birthday);
            $stmt->bindParam(':inviter', $inviter);
            $stmt->bindParam(':how_know', $how_know);
            $stmt->bindParam(':attendance_duration', $attendance_duration);
            $stmt->bindParam(':previous_church', $previous_church);
            $stmt->bindParam(':baptism_date', $baptism_date);
            $stmt->bindParam(':officiant', $officiant);
            $stmt->bindParam(':venue', $venue);
            $stmt->execute();
            $message = "New baptismal record added successfully!";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#baptismal");
            exit();
        } catch(PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
        $conn = null;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_baptismal"]) && $is_admin) {
    $id = $_POST['edit_bap_id'];
    $name = $_POST['edit_bap_name'];
    $nickname = $_POST['edit_bap_nickname'];
    $address = $_POST['edit_bap_address'];
    $telephone = $_POST['edit_bap_telephone'];
    $cellphone = $_POST['edit_bap_cellphone'];
    $email = $_POST['edit_bap_email'];
    $civil_status = $_POST['edit_bap_civil_status'] ?? '';
    $sex = $_POST['edit_bap_sex'] ?? '';
    $birthday = $_POST['edit_bap_birthday'];
    $father_name = $_POST['edit_bap_father_name'];
    $mother_name = $_POST['edit_bap_mother_name'];
    $children = $_POST['edit_bap_children'];
    $education = $_POST['edit_bap_education'];
    $course = $_POST['edit_bap_course'];
    $school = $_POST['edit_bap_school'];
    $year = $_POST['edit_bap_year'];
    $company = $_POST['edit_bap_company'];
    $position = $_POST['edit_bap_position'];
    $business = $_POST['edit_bap_business'];
    $spiritual_birthday = $_POST['edit_bap_spiritual_birthday'];
    $inviter = $_POST['edit_bap_inviter'];
    $how_know = $_POST['edit_bap_how_know'];
    $attendance_duration = $_POST['edit_bap_attendance_duration'];
    $previous_church = $_POST['edit_bap_previous_church'];
    $baptism_date = $_POST['edit_bap_baptism_date'];
    $officiant = $_POST['edit_bap_officiant'];
    $venue = $_POST['edit_bap_venue'];
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "churchdb";
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $conn->prepare("UPDATE baptismal_records SET name = :name, nickname = :nickname, address = :address, telephone = :telephone, cellphone = :cellphone, email = :email, civil_status = :civil_status, sex = :sex, birthday = :birthday, father_name = :father_name, mother_name = :mother_name, children = :children, education = :education, course = :course, school = :school, year = :year, company = :company, position = :position, business = :business, spiritual_birthday = :spiritual_birthday, inviter = :inviter, how_know = :how_know, attendance_duration = :attendance_duration, previous_church = :previous_church, baptism_date = :baptism_date, officiant = :officiant, venue = :venue WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':nickname', $nickname);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':cellphone', $cellphone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':civil_status', $civil_status);
        $stmt->bindParam(':sex', $sex);
        $stmt->bindParam(':birthday', $birthday);
        $stmt->bindParam(':father_name', $father_name);
        $stmt->bindParam(':mother_name', $mother_name);
        $stmt->bindParam(':children', $children);
        $stmt->bindParam(':education', $education);
        $stmt->bindParam(':course', $course);
        $stmt->bindParam(':school', $school);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':company', $company);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':business', $business);
        $stmt->bindParam(':spiritual_birthday', $spiritual_birthday);
        $stmt->bindParam(':inviter', $inviter);
        $stmt->bindParam(':how_know', $how_know);
        $stmt->bindParam(':attendance_duration', $attendance_duration);
        $stmt->bindParam(':previous_church', $previous_church);
        $stmt->bindParam(':baptism_date', $baptism_date);
        $stmt->bindParam(':officiant', $officiant);
        $stmt->bindParam(':venue', $venue);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $message = "Baptismal record updated successfully!";
        $messageType = "success";
        header("Location: " . $_SERVER['PHP_SELF'] . "#baptismal");
        exit();
    } catch(PDOException $e) {
        $message = "Error updating baptismal record: " . $e->getMessage();
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Records | <?php echo $church_name; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($church_logo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
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
        }

        .logout-btn:hover {
            background-color: #e0e0e0;
        }

        .records-content {
            margin-top: 20px;
        }

        .tab-navigation {
            display: flex;
            background-color: var(--white);
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .tab-navigation a {
            flex: 1;
            text-align: center;
            padding: 15px;
            color: var(--primary-color);
            text-decoration: none;
            transition: background-color 0.3s;
            font-weight: 500;
        }

        .tab-navigation a.active {
            background-color: var(--accent-color);
            color: var(--white);
        }

        .tab-navigation a:hover:not(.active) {
            background-color: #f0f0f0;
        }

        .tab-content {
            background-color: var(--white);
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background-color: #f0f0f0;
            border-radius: 5px;
            padding: 5px 15px;
            width: 300px;
        }

        .search-box input {
            border: none;
            background-color: transparent;
            padding: 8px;
            flex: 1;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
        }

        .search-box i {
            color: #666;
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

        .btn i {
            margin-right: 5px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eeeeee;
        }

        table th {
            background-color: #f5f5f5;
            font-weight: 600;
            color: var(--primary-color);
        }

        tbody tr:hover {
            background-color: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #2ecc71;
            color: white;
        }

        .status-inactive {
            background-color: #e74c3c;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            color: white;
        }

        .action-btn.edit-btn {
            background-color: #4a90e2;
        }

        .action-btn.edit-btn:hover {
            background-color: #357abd;
        }

        .action-btn.delete-btn {
            background-color: #e74c3c;
        }

        .action-btn.delete-btn:hover {
            background-color: #c0392b;
        }

        .action-btn i {
            font-size: 14px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            margin: 0 5px;
            border-radius: 5px;
            background-color: #f0f0f0;
            color: var(--primary-color);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .pagination a:hover {
            background-color: #e0e0e0;
        }

        .pagination a.active {
            background-color: var(--accent-color);
            color: var(--white);
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
            border-radius: 5px;
            padding: 30px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .form-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
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

        .radio-group {
            display: flex;
            gap: 25px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }

        .exit-btn {
            background-color: var(--danger-color);
        }

        .exit-btn:hover {
            background-color: #d32f2f;
        }

        .print-btn {
            background-color: var(--info-color);
        }

        .print-btn:hover {
            background-color: #1976d2;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            border-left: 4px solid;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 14px;
            line-height: 1.5;
            animation: slideInDown 0.3s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        .alert i {
            margin-right: 12px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border-left-color: #4caf50;
        }

        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: #c62828;
            border-left-color: #f44336;
        }

        .alert-warning {
            background-color: rgba(255, 152, 0, 0.1);
            color: #ef6c00;
            border-left-color: #ff9800;
        }

        .alert-info {
            background-color: rgba(33, 150, 243, 0.1);
            color: #1565c0;
            border-left-color: #2196f3;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background-color: #4caf50;
            color: white;
        }

        .badge-warning {
            background-color: #ff9800;
            color: white;
        }

        .badge-danger {
            background-color: #f44336;
            color: white;
        }

        .badge-info {
            background-color: #2196f3;
            color: white;
        }

        .alert strong {
            font-weight: 600;
        }

        .alert .alert-actions {
            margin-left: auto;
            display: flex;
            gap: 8px;
        }

        .alert .alert-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 16px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .alert .alert-close:hover {
            opacity: 1;
        }

        .view-field {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            font-size: 16px;
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
            .action-bar {
                flex-direction: column;
                gap: 10px;
            }
            .search-box {
                width: 100%;
            }
            .tab-navigation {
                flex-direction: column;
            }
            .tab-navigation a {
                padding: 10px;
            }
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }

        @media print {
            .modal {
                position: static;
                background-color: transparent;
                display: block;
            }
            .modal-content {
                box-shadow: none;
                width: 100%;
                max-height: none;
                padding: 20px;
            }
            .modal-buttons, .exit-btn, .print-btn {
                display: none;
            }
            body, .dashboard-container, .content-area, .records-content, .tab-content {
                margin: 0;
                padding: 0;
            }
            .sidebar, .top-bar, .tab-navigation, .action-bar, .pagination {
                display: none;
            }
            .modal-content {
                border: none;
            }
        }

        .status-btn {
            background-color: var(--info-color);
        }

        .status-btn.status-active {
            background-color: var(--success-color);
        }

        .status-btn.status-inactive {
            background-color: var(--warning-color);
        }

        .view-btn {
            background-color: var(--accent-color);
        }

        .view-btn:hover {
            background-color: rgb(0, 112, 9);
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
                    <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="member_events.php" class="<?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i> <span>Messages</span></a></li>
                    <li><a href="member_records.php" class="<?php echo $current_page == 'member_records.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span>Member Records</span></a></li>
                    <li><a href="prayers.php" class="<?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                    <li><a href="financialreport.php" class="<?php echo $current_page == 'financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span>Financial Reports</span></a></li>
                    <li><a href="member_contributions.php" class="<?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-dollar"></i> <span>Stewardship Report</span></a></li>
                    <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                </ul>
            </div>
        </aside>

        <main class="content-area">
            <div class="top-bar">
                <h2>Member Records</h2>
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
                        <p><?php echo htmlspecialchars($user_profile['role'] ?? 'User'); ?></p>
                    </div>
                    <form action="logout.php" method="post">
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>

            <div class="records-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="tab-navigation">
                    <a href="#membership" class="active" data-tab="membership">Membership</a>
                    <a href="#baptismal" data-tab="baptismal">Baptismal</a>
                    <a href="#marriage" data-tab="marriage">Marriage</a>
                    <a href="#child-dedication" data-tab="child-dedication">Child Dedication</a>
                    <a href="#visitor" data-tab="visitor">Visitor's Record</a>
                    <a href="#burial" data-tab="burial">Burial Records</a>
                </div>

                <div class="tab-content">
                    <!-- Membership Tab -->
                    <div class="tab-pane active" id="membership">
                        <div class="action-bar">
                            <?php if ($is_admin): ?>
                                <button class="btn" id="add-membership-btn">
                                    <i class="fas fa-user-plus"></i> Add New Member
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table id="membership-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Join Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membership_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['join_date']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($record['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo htmlspecialchars($record['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="membership-view-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="membership"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn status-btn <?php echo strtolower($record['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>" 
                                                                id="membership-status-<?php echo htmlspecialchars($record['id']); ?>"
                                                                data-id="<?php echo htmlspecialchars($record['id']); ?>" 
                                                                data-current-status="<?php echo htmlspecialchars($record['status']); ?>">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                        <button class="action-btn edit-btn" id="membership-edit-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="membership"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="membership-delete-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="membership"><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Baptismal Tab -->
                    <div class="tab-pane" id="baptismal">
                        <div class="action-bar">
                            <?php if ($is_admin): ?>
                                <button class="btn" id="add-baptismal-btn">
                                    <i class="fas fa-plus"></i> Add New Baptismal
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table id="baptismal-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Baptism Date</th>
                                        <th>Officiant</th>
                                        <th>Venue</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($baptismal_records as $record): ?>
                                        <tr>
                                            <td><?php echo $record['id']; ?></td>
                                            <td><?php echo $record['name']; ?></td>
                                            <td><?php echo $record['baptism_date']; ?></td>
                                            <td><?php echo $record['officiant']; ?></td>
                                            <td><?php echo isset($record['venue']) ? $record['venue'] : ''; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="baptismal-view-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="baptismal"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="baptismal-edit-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="baptismal"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" data-id="<?php echo $record['id']; ?>" data-type="baptismal"><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Marriage Tab -->
                    <div class="tab-pane" id="marriage">
                        <div class="action-bar">
                            <?php if ($is_admin): ?>
                                <button class="btn" id="add-marriage-btn">
                                    <i class="fas fa-plus"></i> Add New Marriage
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table id="marriage-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Couple</th>
                                        <th>Marriage Date</th>
                                        <th>Venue</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['marriage_records'] as $record): ?>
                                        <tr>
                                            <td><?php echo $record['id']; ?></td>
                                            <td><?php echo $record['couple']; ?></td>
                                            <td><?php echo $record['marriage_date']; ?></td>
                                            <td><?php echo $record['venue']; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="marriage-view-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="marriage"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="marriage-edit-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="marriage"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="marriage-delete-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="marriage"><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Child Dedication Tab -->
                    <div class="tab-pane" id="child-dedication">
                        <div class="action-bar">
                            <?php if ($is_admin): ?>
                                <button class="btn" id="add-child-dedication-btn">
                                    <i class="fas fa-plus"></i> Add New Child Dedication
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table id="child-dedication-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Child Name</th>
                                        <th>Dedication Date</th>
                                        <th>Parents</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['child_dedication_records'] as $record): ?>
                                        <tr>
                                            <td><?php echo $record['id']; ?></td>
                                            <td><?php echo $record['child_name']; ?></td>
                                            <td><?php echo $record['dedication_date']; ?></td>
                                            <td><?php echo $record['parents']; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="child-view-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="child"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="child-edit-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="child"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="child-delete-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="child"><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Visitor's Record Tab -->
                    <div class="tab-pane" id="visitor">
                        <div class="action-bar">
                            <?php if ($is_admin): ?>
                                <button class="btn" id="add-visitor-btn">
                                    <i class="fas fa-user-plus"></i> Add New Visitor
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table id="visitor-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Visit Date</th>
                                        <th>Contact</th>
                                        <th>Purpose</th>
                                        <th>Invited By</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['visitor_records'] as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['visit_date'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['contact'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['purpose'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['invited_by'] ?? ''); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($record['status'] ?? '') === 'first time' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo htmlspecialchars($record['status'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="visitor-view-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="visitor"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="visitor-edit-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="visitor"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="visitor-delete-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="visitor"><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Burial Records Tab -->
                    <div class="tab-pane" id="burial">
                        <div class="action-bar">
                            <?php if ($is_admin): ?>
                                <button class="btn" id="add-burial-btn">
                                    <i class="fas fa-plus"></i> Add New Burial Record
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table id="burial-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name of Deceased</th>
                                        <th>Date of Burial</th>
                                        <th>Officiant</th>
                                        <th>Venue</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Burial records will be listed here. You can populate this with PHP or JS as needed. -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Membership Modal -->
            <div class="modal" id="membership-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Membership Application Form</h4>
                    </div>
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="name">Name/Pangalan</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="nickname">Nickname/Palayaw</label>
                            <input type="text" id="nickname" name="nickname" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="address">Address/Tirahan</label>
                            <input type="text" id="address" name="address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="telephone">Telephone No./Telepono</label>
                            <input type="tel" id="telephone" name="telephone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="cellphone">Cellphone No.</label>
                            <input type="tel" id="cellphone" name="cellphone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <div class="radio-group">
                                <label><input type="radio" name="civil_status" value="Single" required> Single</label>
                                <label><input type="radio" name="civil_status" value="Married"> Married</label>
                                <label><input type="radio" name="civil_status" value="Widowed"> Widowed</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Sex</label>
                            <div class="radio-group">
                                <label><input type="radio" name="sex" value="Male" required> Male</label>
                                <label><input type="radio" name="sex" value="Female"> Female</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="birthday">Birthday/Kaarawan</label>
                            <input type="date" id="birthday" name="birthday" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="father_name">Father's Name/Pangalan ng Tatay</label>
                            <input type="text" id="father_name" name="father_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="mother_name">Mother's Name/Pangalan ng Nanay</label>
                            <input type="text" id="mother_name" name="mother_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="children">Name of Children/Pangalan ng Anak</label>
                            <textarea id="children" name="children" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="education">Educational Attainment/Antas na natapos</label>
                            <input type="text" id="education" name="education" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="course">Course/Kurso</label>
                            <input type="text" id="course" name="course" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="school">School/Paaralan</label>
                            <input type="text" id="school" name="school" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="year">Year/Taon</label>
                            <input type="text" id="year" name="year" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="company">If employed, what company/Pangalan ng kompanya</label>
                            <input type="text" id="company" name="company" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="position">Position/Title/Trabaho</label>
                            <input type="text" id="position" name="position" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="business">If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label>
                            <input type="text" id="business" name="business" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="spiritual_birthday">Spiritual Birthday</label>
                            <input type="date" id="spiritual_birthday" name="spiritual_birthday" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="inviter">Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label>
                            <input type="text" id="inviter" name="inviter" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="how_know">How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label>
                            <textarea id="how_know" name="how_know" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="attendance_duration">How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                            <input type="text" id="attendance_duration" name="attendance_duration" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="previous_church">Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                            <input type="text" id="previous_church" name="previous_church" class="form-control">
                        </div>
                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="add_membership">
                                <i class="fas fa-save"></i> Submit
                            </button>
                            <button type="button" class="btn exit-btn" id="membership-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Membership Modal -->
            <div class="modal" id="view-membership-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Membership Record</h4>
                    </div>
                    <div class="form-group">
                        <label>ID</label>
                        <div class="view-field" id="view-membership-id"></div>
                    </div>
                    <div class="form-group">
                        <label>Name/Pangalan</label>
                        <div class="view-field" id="view-membership-name"></div>
                    </div>
                    <div class="form-group">
                        <label>Join Date</label>
                        <div class="view-field" id="view-membership-join_date"></div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <div class="view-field" id="view-membership-status"></div>
                    </div>
                    <div class="form-group">
                        <label>Nickname/Palayaw</label>
                        <div class="view-field" id="view-membership-nickname"></div>
                    </div>
                    <div class="form-group">
                        <label>Address/Tirahan</label>
                        <div class="view-field" id="view-membership-address"></div>
                    </div>
                    <div class="form-group">
                        <label>Telephone No./Telepono</label>
                        <div class="view-field" id="view-membership-telephone"></div>
                    </div>
                    <div class="form-group">
                        <label>Cellphone No.</label>
                        <div class="view-field" id="view-membership-cellphone"></div>
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <div class="view-field" id="view-membership-email"></div>
                    </div>
                    <div class="form-group">
                        <label>Civil Status</label>
                        <div class="view-field" id="view-membership-civil_status"></div>
                    </div>
                    <div class="form-group">
                        <label>Sex</label>
                        <div class="view-field" id="view-membership-sex"></div>
                    </div>
                    <div class="form-group">
                        <label>Birthday/Kaarawan</label>
                        <div class="view-field" id="view-membership-birthday"></div>
                    </div>
                    <div class="form-group">
                        <label>Father's Name/Pangalan ng Tatay</label>
                        <div class="view-field" id="view-membership-father_name"></div>
                    </div>
                    <div class="form-group">
                        <label>Mother's Name/Pangalan ng Nanay</label>
                        <div class="view-field" id="view-membership-mother_name"></div>
                    </div>
                    <div class="form-group">
                        <label>Name of Children/Pangalan ng Anak</label>
                        <div class="view-field" id="view-membership-children"></div>
                    </div>
                    <div class="form-group">
                        <label>Educational Attainment/Antas na natapos</label>
                        <div class="view-field" id="view-membership-education"></div>
                    </div>
                    <div class="form-group">
                        <label>Course/Kurso</label>
                        <div class="view-field" id="view-membership-course"></div>
                    </div>
                    <div class="form-group">
                        <label>School/Paaralan</label>
                        <div class="view-field" id="view-membership-school"></div>
                    </div>
                    <div class="form-group">
                        <label>Year/Taon</label>
                        <div class="view-field" id="view-membership-year"></div>
                    </div>
                    <div class="form-group">
                        <label>If employed, what company/Pangalan ng kompanya</label>
                        <div class="view-field" id="view-membership-company"></div>
                    </div>
                    <div class="form-group">
                        <label>Position/Title/Trabaho</label>
                        <div class="view-field" id="view-membership-position"></div>
                    </div>
                    <div class="form-group">
                        <label>If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label>
                        <div class="view-field" id="view-membership-business"></div>
                    </div>
                    <div class="form-group">
                        <label>Spiritual Birthday</label>
                        <div class="view-field" id="view-membership-spiritual_birthday"></div>
                    </div>
                    <div class="form-group">
                        <label>Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label>
                        <div class="view-field" id="view-membership-inviter"></div>
                    </div>
                    <div class="form-group">
                        <label>How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label>
                        <div class="view-field" id="view-membership-how_know"></div>
                    </div>
                    <div class="form-group">
                        <label>How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                        <div class="view-field" id="view-membership-attendance_duration"></div>
                    </div>
                    <div class="form-group">
                        <label>Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                        <div class="view-field" id="view-membership-previous_church"></div>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn print-btn" id="print-membership-btn">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn exit-btn" id="view-membership-exit-btn">
                            <i class="fas fa-times"></i> Exit
                        </button>
                    </div>
                </div>
            </div>

            <!-- Edit Membership Modal -->
            <div class="modal" id="edit-membership-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Edit Membership Record</h4>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" id="edit-membership-id" name="id">
                        <div class="form-group">
                            <label for="edit-membership-name">Name/Pangalan</label>
                            <input type="text" id="edit-membership-name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-join_date">Join Date</label>
                            <input type="date" id="edit-membership-join_date" name="join_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-status">Status</label>
                            <select id="edit-membership-status" name="status" class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-nickname">Nickname/Palayaw</label>
                            <input type="text" id="edit-membership-nickname" name="nickname" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-address">Address/Tirahan</label>
                            <input type="text" id="edit-membership-address" name="address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-telephone">Telephone No./Telepono</label>
                            <input type="tel" id="edit-membership-telephone" name="telephone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-cellphone">Cellphone No.</label>
                            <input type="tel" id="edit-membership-cellphone" name="cellphone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-email">E-mail</label>
                            <input type="email" id="edit-membership-email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <div class="radio-group">
                                <label><input type="radio" name="civil_status" value="Single" required> Single</label>
                                <label><input type="radio" name="civil_status" value="Married"> Married</label>
                                <label><input type="radio" name="civil_status" value="Widowed"> Widowed</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Sex</label>
                            <div class="radio-group">
                                <label><input type="radio" name="sex" value="Male" required> Male</label>
                                <label><input type="radio" name="sex" value="Female"> Female</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-birthday">Birthday/Kaarawan</label>
                            <input type="date" id="edit-membership-birthday" name="birthday" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-father_name">Father's Name/Pangalan ng Tatay</label>
                            <input type="text" id="edit-membership-father_name" name="father_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-mother_name">Mother's Name/Pangalan ng Nanay</label>
                            <input type="text" id="edit-membership-mother_name" name="mother_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-children">Name of Children/Pangalan ng Anak</label>
                            <textarea id="edit-membership-children" name="children" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-education">Educational Attainment/Antas na natapos</label>
                            <input type="text" id="edit-membership-education" name="education" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-course">Course/Kurso</label>
                            <input type="text" id="edit-membership-course" name="course" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-school">School/Paaralan</label>
                            <input type="text" id="edit-membership-school" name="school" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-year">Year/Taon</label>
                            <input type="text" id="edit-membership-year" name="year" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-company">If employed, what company/Pangalan ng kompanya</label>
                            <input type="text" id="edit-membership-company" name="company" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-position">Position/Title/Trabaho</label>
                            <input type="text" id="edit-membership-position" name="position" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-business">If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label>
                            <input type="text" id="edit-membership-business" name="business" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-spiritual_birthday">Spiritual Birthday</label>
                            <input type="date" id="edit-membership-spiritual_birthday" name="spiritual_birthday" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-inviter">Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label>
                            <input type="text" id="edit-membership-inviter" name="inviter" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-how_know">How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label>
                            <textarea id="edit-membership-how_know" name="how_know" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-attendance_duration">How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                            <input type="text" id="edit-membership-attendance_duration" name="attendance_duration" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-previous_church">Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                            <input type="text" id="edit-membership-previous_church" name="previous_church" class="form-control">
                        </div>
                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="edit_membership">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn exit-btn" id="edit-membership-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal" id="delete-confirmation-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Confirm Deletion</h3>
                        <p>Are you sure you want to delete this record? This action cannot be undone.</p>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" id="delete-record-id" name="id">
                        <input type="hidden" id="delete-record-type" name="type">
                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="delete_record">
                                <i class="fas fa-trash"></i> Yes, Delete
                            </button>
                            <button type="button" class="btn exit-btn" id="delete-exit-btn">
                                <i class="fas fa-times"></i> No, Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Status Change Modal -->
            <div class="modal" id="status-change-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Change Member Status</h3>
                        <p>Are you sure you want to change this member's status?</p>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" id="status-change-id" name="id">
                        <input type="hidden" id="status-change-status" name="status">
                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="change_status">
                                <i class="fas fa-check"></i> Confirm
                            </button>
                            <button type="button" class="btn exit-btn" id="status-change-exit-btn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <!--#####################-->

         <div class="modal" id="baptismal-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Baptismal Application Form</h4>
                    </div>
                    <form action="" method="post" id="baptismal-form">
                        <input type="hidden" name="add_baptismal" value="1">
                        <input type="hidden" name="id" id="bap_id">
                        <div class="form-group">
                            <label for="bap_name">Name/Pangalan</label>
                            <input type="text" id="bap_name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_nickname">Nickname/Palayaw</label>
                            <input type="text" id="bap_nickname" name="nickname" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="bap_address">Address/Tirahan</label>
                            <input type="text" id="bap_address" name="address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_telephone">Telephone No./Telepono</label>
                            <input type="tel" id="bap_telephone" name="telephone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="bap_cellphone">Cellphone No.</label>
                            <input type="tel" id="bap_cellphone" name="cellphone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_email">E-mail</label>
                            <input type="email" id="bap_email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <div class="radio-group">
                                <label><input type="radio" name="civil_status" value="Single" required> Single</label>
                                <label><input type="radio" name="civil_status" value="Married"> Married</label>
                                <label><input type="radio" name="civil_status" value="Widowed"> Widowed</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Sex</label>
                            <div class="radio-group">
                                <label><input type="radio" name="sex" value="Male" required> Male</label>
                                <label><input type="radio" name="sex" value="Female"> Female</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="bap_birthday">Birthday/Kaarawan</label>
                            <input type="date" id="bap_birthday" name="birthday" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_father_name">Father's Name/Pangalan ng Tatay</label>
                            <input type="text" id="bap_father_name" name="father_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_mother_name">Mother's Name/Pangalan ng Nanay</label>
                            <input type="text" id="bap_mother_name" name="mother_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_children">Name of Children/Pangalan ng Anak</label>
                            <textarea id="bap_children" name="children" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="bap_education">Educational Attainment/Antas na natapos</label>
                            <input type="text" id="bap_education" name="education" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_course">Course/Kurso</label>
                            <input type="text" id="bap_course" name="course" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_school">School/Paaralan</label>
                            <input type="text" id="bap_school" name="school" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_year">Year/Taon</label>
                            <input type="text" id="bap_year" name="year" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_company">If employed, what company/Pangalan ng kompanya</label>
                            <input type="text" id="bap_company" name="company" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_position">Position/Title/Trabaho</label>
                            <input type="text" id="bap_position" name="position" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_business">If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label>
                            <input type="text" id="bap_business" name="business" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_spiritual_birthday">Spiritual Birthday</label>
                            <input type="date" id="bap_spiritual_birthday" name="spiritual_birthday" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_inviter">Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label>
                            <input type="text" id="bap_inviter" name="inviter" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_how_know">How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label>
                            <textarea id="bap_how_know" name="how_know" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="bap_attendance_duration">How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                            <input type="text" id="bap_attendance_duration" name="attendance_duration" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_previous_church">Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                            <input type="text" id="bap_previous_church" name="previous_church" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_baptism_date">Date of Baptism</label>
                            <input type="date" id="bap_baptism_date" name="baptism_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_officiant">Officiating Pastor</label>
                            <input type="text" id="bap_officiant" name="officiant" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bap_venue">Venue of Baptismal</label>
                            <input type="text" id="bap_venue" name="venue" class="form-control" required>
                        </div>
                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="add_baptismal">
                                <i class="fas fa-save"></i> Submit
                            </button>
                            <button type="button" class="btn exit-btn" id="baptismal-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
         <!-- Edit Baptismal Model -->      
          <div class="modal" id="edit-baptismal-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Edit Baptismal Record</h4>
                    </div>
                    <form id="edit-baptismal-form" method="POST">
                        <input type="hidden" name="edit_baptismal" value="1">
                        <input type="hidden" name="id" id="edit_bap_id">
                        <div class="form-group">
                            <label for="edit_bap_name">Name/Pangalan</label>
                            <input type="text" id="edit_bap_name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_nickname">Nickname/Palayaw</label>
                            <input type="text" id="edit_bap_nickname" name="nickname" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_address">Address/Tirahan</label>
                            <input type="text" id="edit_bap_address" name="address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_telephone">Telephone No./Telepono</label>
                            <input type="tel" id="edit_bap_telephone" name="telephone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_cellphone">Cellphone No.</label>
                            <input type="tel" id="edit_bap_cellphone" name="cellphone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_email">E-mail</label>
                            <input type="email" id="edit_bap_email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <div class="radio-group">
                                <label><input type="radio" name="edit_bap_civil_status" value="Single" required> Single</label>
                                <label><input type="radio" name="edit_bap_civil_status" value="Married"> Married</label>
                                <label><input type="radio" name="edit_bap_civil_status" value="Widowed"> Widowed</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Sex</label>
                            <div class="radio-group">
                                <label><input type="radio" name="edit_bap_sex" value="Male" required> Male</label>
                                <label><input type="radio" name="edit_bap_sex" value="Female"> Female</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_birthday">Birthday/Kaarawan</label>
                            <input type="date" id="edit_bap_birthday" name="birthday" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_father_name">Father's Name/Pangalan ng Tatay</label>
                            <input type="text" id="edit_bap_father_name" name="father_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_mother_name">Mother's Name/Pangalan ng Nanay</label>
                            <input type="text" id="edit_bap_mother_name" name="mother_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_children">Name of Children/Pangalan ng Anak</label>
                            <textarea id="edit_bap_children" name="children" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_education">Educational Attainment/Antas na natapos</label>
                            <input type="text" id="edit_bap_education" name="education" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_course">Course/Kurso</label>
                            <input type="text" id="edit_bap_course" name="course" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_school">School/Paaralan</label>
                            <input type="text" id="edit_bap_school" name="school" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_year">Year/Taon</label>
                            <input type="text" id="edit_bap_year" name="year" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_company">If employed, what company/Pangalan ng kompanya</label>
                            <input type="text" id="edit_bap_company" name="company" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_position">Position/Title/Trabaho</label>
                            <input type="text" id="edit_bap_position" name="position" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_business">If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label>
                            <input type="text" id="edit_bap_business" name="business" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_spiritual_birthday">Spiritual Birthday</label>
                            <input type="date" id="edit_bap_spiritual_birthday" name="spiritual_birthday" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_inviter">Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label>
                            <input type="text" id="edit_bap_inviter" name="inviter" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_how_know">How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label>
                            <textarea id="edit_bap_how_know" name="how_know" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_attendance_duration">How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                            <input type="text" id="edit_bap_attendance_duration" name="attendance_duration" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_previous_church">Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                            <input type="text" id="edit_bap_previous_church" name="previous_church" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_baptism_date">Date of Baptism</label>
                            <input type="date" id="edit_bap_baptism_date" name="baptism_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_officiant">Officiating Pastor</label>
                            <input type="text" id="edit_bap_officiant" name="officiant" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_venue">Venue of Baptismal</label>
                            <input type="text" id="edit_bap_venue" name="venue" class="form-control" required>
                        </div>
                        <div class="modal-buttons">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="button" class="btn exit-btn" id="edit-baptismal-exit-btn">Exit</button>
                        </div>
                    </form>
                </div>
            </div>                                             
            <!-- Add/Edit Visitor Modal -->
            <div class="modal" id="visitor-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Visitor Record Form</h4>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" id="visitor-id" name="id">
                        <div class="form-group">
                            <label for="visitor-name">Name/Pangalan</label>
                            <input type="text" id="visitor-name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="visitor-date">Visit Date</label>
                            <input type="date" id="visitor-date" name="visit_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="visitor-contact">Contact Number</label>
                            <input type="tel" id="visitor-contact" name="contact" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="visitor-address">Address/Tirahan</label>
                            <input type="text" id="visitor-address" name="address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="visitor-purpose">Purpose of Visit</label>
                            <select id="visitor-purpose" name="purpose" class="form-control" required>
                                <option value="Sunday Service">Sunday Service</option>
                                <option value="Special Event">Special Event</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="visitor-invited">Invited By</label>
                            <input type="text" id="visitor-invited" name="invited_by" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="visitor-status">Status</label>
                            <select id="visitor-status" name="status" class="form-control" required>
                                <option value="First Time">First Time</option>
                                <option value="Returning">Returning</option>
                                <option value="Regular">Regular</option>
                            </select>
                        </div>
                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="add_visitor">
                                <i class="fas fa-save"></i> Save
                            </button>
                            <button type="button" class="btn exit-btn" id="visitor-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Visitor Modal -->
            <div class="modal" id="view-visitor-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Visitor Record</h4>
                    </div>
                    <div class="form-group">
                        <label>ID</label>
                        <div class="view-field" id="view-visitor-id"></div>
                    </div>
                    <div class="form-group">
                        <label>Name/Pangalan</label>
                        <div class="view-field" id="view-visitor-name"></div>
                    </div>
                    <div class="form-group">
                        <label>Visit Date</label>
                        <div class="view-field" id="view-visitor-date"></div>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <div class="view-field" id="view-visitor-contact"></div>
                    </div>
                    <div class="form-group">
                        <label>Address/Tirahan</label>
                        <div class="view-field" id="view-visitor-address"></div>
                    </div>
                    <div class="form-group">
                        <label>Purpose of Visit</label>
                        <div class="view-field" id="view-visitor-purpose"></div>
                    </div>
                    <div class="form-group">
                        <label>Invited By</label>
                        <div class="view-field" id="view-visitor-invited"></div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <div class="view-field" id="view-visitor-status"></div>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn print-btn" id="print-visitor-btn">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn exit-btn" id="view-visitor-exit-btn">
                            <i class="fas fa-times"></i> Exit
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script>
        // Tab Navigation
        const tabLinks = document.querySelectorAll('.tab-navigation a');
        const tabPanes = document.querySelectorAll('.tab-pane');

        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                tabLinks.forEach(l => l.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                link.classList.add('active');
                const tabId = link.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Modal Handling
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // View Records Functionality
        function setupViewButtons(recordType) {
            document.querySelectorAll(`.view-btn[data-type="${recordType}"]`).forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    let records;
                    let record;
                    
                    switch(recordType) {
                        case 'membership':
                            records = <?php echo json_encode($membership_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('view-membership-id').textContent = record.id;
                                document.getElementById('view-membership-name').textContent = record.name;
                                document.getElementById('view-membership-join_date').textContent = record.join_date;
                                document.getElementById('view-membership-status').textContent = record.status;
                                document.getElementById('view-membership-nickname').textContent = record.nickname || '';
                                document.getElementById('view-membership-address').textContent = record.address || '';
                                document.getElementById('view-membership-telephone').textContent = record.telephone || '';
                                document.getElementById('view-membership-cellphone').textContent = record.cellphone || '';
                                document.getElementById('view-membership-email').textContent = record.email || '';
                                document.getElementById('view-membership-civil_status').textContent = record.civil_status || '';
                                document.getElementById('view-membership-sex').textContent = record.sex || '';
                                document.getElementById('view-membership-birthday').textContent = record.birthday || '';
                                document.getElementById('view-membership-father_name').textContent = record.father_name || '';
                                document.getElementById('view-membership-mother_name').textContent = record.mother_name || '';
                                document.getElementById('view-membership-children').textContent = record.children || '';
                                document.getElementById('view-membership-education').textContent = record.education || '';
                                document.getElementById('view-membership-course').textContent = record.course || '';
                                document.getElementById('view-membership-school').textContent = record.school || '';
                                document.getElementById('view-membership-year').textContent = record.year || '';
                                document.getElementById('view-membership-company').textContent = record.company || '';
                                document.getElementById('view-membership-position').textContent = record.position || '';
                                document.getElementById('view-membership-business').textContent = record.business || '';
                                document.getElementById('view-membership-spiritual_birthday').textContent = record.spiritual_birthday || '';
                                document.getElementById('view-membership-inviter').textContent = record.inviter || '';
                                document.getElementById('view-membership-how_know').textContent = record.how_know || '';
                                document.getElementById('view-membership-attendance_duration').textContent = record.attendance_duration || '';
                                document.getElementById('view-membership-previous_church').textContent = record.previous_church || '';
                                openModal('view-membership-modal');
                            }
                            break;
                        case 'baptismal':
                            records = <?php echo json_encode($baptismal_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                const fill = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || ''; };
                                fill('view_bap_id', record.id);
                                fill('view_bap_name', record.name);
                                fill('view_bap_nickname', record.nickname);
                                fill('view_bap_address', record.address);
                                fill('view_bap_telephone', record.telephone);
                                fill('view_bap_cellphone', record.cellphone);
                                fill('view_bap_email', record.email);
                                fill('view_bap_civil_status', record.civil_status);
                                fill('view_bap_sex', record.sex);
                                fill('view_bap_birthday', record.birthday);
                                fill('view_bap_father_name', record.father_name);
                                fill('view_bap_mother_name', record.mother_name);
                                fill('view_bap_children', record.children);
                                fill('view_bap_education', record.education);
                                fill('view_bap_course', record.course);
                                fill('view_bap_school', record.school);
                                fill('view_bap_year', record.year);
                                fill('view_bap_company', record.company);
                                fill('view_bap_position', record.position);
                                fill('view_bap_business', record.business);
                                fill('view_bap_spiritual_birthday', record.spiritual_birthday);
                                fill('view_bap_inviter', record.inviter);
                                fill('view_bap_how_know', record.how_know);
                                fill('view_bap_attendance_duration', record.attendance_duration);
                                fill('view_bap_previous_church', record.previous_church);
                                fill('view_bap_baptism_date', record.baptism_date);
                                fill('view_bap_officiant', record.officiant);
                                fill('view_bap_venue', record.venue);
                                openModal('view-baptismal-modal');
                            }
                            break;
                        case 'marriage':
                            records = <?php echo json_encode($_SESSION['marriage_records']); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('view-marriage-id').textContent = record.id;
                                document.getElementById('view-marriage-couple').textContent = record.couple;
                                document.getElementById('view-marriage-marriage_date').textContent = record.marriage_date;
                                document.getElementById('view-marriage-venue').textContent = record.venue;
                                openModal('view-marriage-modal');
                            }
                            break;
                        case 'child_dedication':
                            records = <?php echo json_encode($_SESSION['child_dedication_records']); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('view-child-dedication-id').textContent = record.id;
                                document.getElementById('view-child-dedication-child_name').textContent = record.child_name;
                                document.getElementById('view-child-dedication-dedication_date').textContent = record.dedication_date;
                                document.getElementById('view-child-dedication-parents').textContent = record.parents;
                                openModal('view-child-dedication-modal');
                            }
                            break;
                        case 'visitor':
                            records = <?php echo json_encode($_SESSION['visitor_records']); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('view-visitor-id').textContent = record.id;
                                document.getElementById('view-visitor-name').textContent = record.name;
                                document.getElementById('view-visitor-visit_date').textContent = record.visit_date;
                                document.getElementById('view-visitor-contact').textContent = record.contact;
                                document.getElementById('view-visitor-purpose').textContent = record.purpose;
                                document.getElementById('view-visitor-invited_by').textContent = record.invited_by;
                                document.getElementById('view-visitor-status').textContent = record.status;
                                openModal('view-visitor-modal');
                            }
                            break;
                    }
                });
            });
        }

        // Edit Records Functionality
        function setupEditButtons(recordType) {
            document.querySelectorAll(`.edit-btn[data-type="${recordType}"]`).forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    let records;
                    let record;
                    switch(recordType) {
                        case 'membership':
                            records = <?php echo json_encode($membership_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('edit-membership-id').value = record.id;
                                document.getElementById('edit-membership-name').value = record.name;
                                document.getElementById('edit-membership-join_date').value = record.join_date;
                                document.getElementById('edit-membership-status').value = record.status;
                                document.getElementById('edit-membership-nickname').value = record.nickname || '';
                                document.getElementById('edit-membership-address').value = record.address || '';
                                document.getElementById('edit-membership-telephone').value = record.telephone || '';
                                document.getElementById('edit-membership-cellphone').value = record.cellphone || '';
                                document.getElementById('edit-membership-email').value = record.email || '';
                                document.querySelector(`input[name="civil_status"][value="${record.civil_status}"]`).checked = true;
                                document.querySelector(`input[name="sex"][value="${record.sex}"]`).checked = true;
                                document.getElementById('edit-membership-birthday').value = record.birthday || '';
                                document.getElementById('edit-membership-father_name').value = record.father_name || '';
                                document.getElementById('edit-membership-mother_name').value = record.mother_name || '';
                                document.getElementById('edit-membership-children').value = record.children || '';
                                document.getElementById('edit-membership-education').value = record.education || '';
                                document.getElementById('edit-membership-course').value = record.course || '';
                                document.getElementById('edit-membership-school').value = record.school || '';
                                document.getElementById('edit-membership-year').value = record.year || '';
                                document.getElementById('edit-membership-company').value = record.company || '';
                                document.getElementById('edit-membership-position').value = record.position || '';
                                document.getElementById('edit-membership-business').value = record.business || '';
                                document.getElementById('edit-membership-spiritual_birthday').value = record.spiritual_birthday || '';
                                document.getElementById('edit-membership-inviter').value = record.inviter || '';
                                document.getElementById('edit-membership-how_know').value = record.how_know || '';
                                document.getElementById('edit-membership-attendance_duration').value = record.attendance_duration || '';
                                document.getElementById('edit-membership-previous_church').value = record.previous_church || '';
                                openModal('edit-membership-modal');
                            }
                            break;
                        case 'baptismal':
                            records = <?php echo json_encode($baptismal_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                const fill = (id, value) => { const el = document.getElementById(id); if (el) el.value = value || ''; };
                                fill('edit_bap_id', record.id);
                                fill('edit_bap_name', record.name);
                                fill('edit_bap_nickname', record.nickname);
                                fill('edit_bap_address', record.address);
                                fill('edit_bap_telephone', record.telephone);
                                fill('edit_bap_cellphone', record.cellphone);
                                fill('edit_bap_email', record.email);
                                if (record.civil_status) document.querySelector(`input[name="edit_bap_civil_status"][value="${record.civil_status}"]`)?.click();
                                if (record.sex) document.querySelector(`input[name="edit_bap_sex"][value="${record.sex}"]`)?.click();
                                fill('edit_bap_birthday', record.birthday);
                                fill('edit_bap_father_name', record.father_name);
                                fill('edit_bap_mother_name', record.mother_name);
                                fill('edit_bap_children', record.children);
                                fill('edit_bap_education', record.education);
                                fill('edit_bap_course', record.course);
                                fill('edit_bap_school', record.school);
                                fill('edit_bap_year', record.year);
                                fill('edit_bap_company', record.company);
                                fill('edit_bap_position', record.position);
                                fill('edit_bap_business', record.business);
                                fill('edit_bap_spiritual_birthday', record.spiritual_birthday);
                                fill('edit_bap_inviter', record.inviter);
                                fill('edit_bap_how_know', record.how_know);
                                fill('edit_bap_attendance_duration', record.attendance_duration);
                                fill('edit_bap_previous_church', record.previous_church);
                                fill('edit_bap_baptism_date', record.baptism_date);
                                fill('edit_bap_officiant', record.officiant);
                                fill('edit_bap_venue', record.venue);
                                openModal('edit-baptismal-modal');
                            } else {
                                console.log('Baptismal record not found for id:', id);
                            }
                            break;
                        case 'marriage':
                            records = <?php echo json_encode($_SESSION['marriage_records']); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('edit-marriage-id').value = record.id;
                                document.getElementById('edit-marriage-couple').value = record.couple;
                                document.getElementById('edit-marriage-marriage_date').value = record.marriage_date;
                                document.getElementById('edit-marriage-venue').value = record.venue;
                                openModal('edit-marriage-modal');
                            }
                            break;
                        case 'child_dedication':
                            records = <?php echo json_encode($_SESSION['child_dedication_records']); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('edit-child-dedication-id').value = record.id;
                                document.getElementById('edit-child-dedication-child_name').value = record.child_name;
                                document.getElementById('edit-child-dedication-dedication_date').value = record.dedication_date;
                                document.getElementById('edit-child-dedication-parents').value = record.parents;
                                openModal('edit-child-dedication-modal');
                            }
                            break;
                        case 'visitor':
                            records = <?php echo json_encode($_SESSION['visitor_records']); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('edit-visitor-id').value = record.id;
                                document.getElementById('edit-visitor-name').value = record.name;
                                document.getElementById('edit-visitor-visit_date').value = record.visit_date;
                                document.getElementById('edit-visitor-contact').value = record.contact;
                                document.getElementById('edit-visitor-purpose').value = record.purpose;
                                document.getElementById('edit-visitor-invited_by').value = record.invited_by;
                                document.getElementById('edit-visitor-status').value = record.status;
                                openModal('edit-visitor-modal');
                            }
                            break;
                    }
                });
            });
        }

        // Delete Records Functionality
        function setupDeleteButtons(recordType) {
            document.querySelectorAll(`.delete-btn[data-type="${recordType}"]`).forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    const row = btn.closest('tr');
                    const memberName = row ? row.cells[1].textContent : 'Unknown Member';
                    
                    // Update modal content with member details
                    const modal = document.getElementById('delete-confirmation-modal');
                    const modalContent = modal.querySelector('.modal-content');
                    const header = modalContent.querySelector('.form-header');
                    
                    header.innerHTML = `
                        <h3>⚠️ Confirm Deletion</h3>
                        <p>Are you sure you want to delete the record for <strong>${memberName}</strong> (ID: ${id})?</p>
                        <p style="color: #f44336; font-weight: 600;">⚠️ This action cannot be undone and will permanently remove all data for this member.</p>
                    `;
                    
                    document.getElementById('delete-record-id').value = id;
                    document.getElementById('delete-record-type').value = recordType;
                    openModal('delete-confirmation-modal');
                });
            });
        }

        // Search Functionality
        function setupSearch(tableId, searchInputId) {
            const searchInput = document.getElementById(searchInputId);
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
                    rows.forEach(row => {
                        const text = Array.from(row.cells).map(cell => cell.textContent.toLowerCase()).join(' ');
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            }
        }

        // Status Change Functionality
        function setupStatusButtons() {
            document.querySelectorAll('.status-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    const currentStatus = btn.getAttribute('data-current-status');
                    const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
                    
                    // Get member name for better messaging
                    const row = btn.closest('tr');
                    const memberName = row ? row.cells[1].textContent : 'Unknown Member';
                    
                    // Update modal content with member details
                    const modal = document.getElementById('status-change-modal');
                    const modalContent = modal.querySelector('.modal-content');
                    const header = modalContent.querySelector('.form-header');
                    
                    header.innerHTML = `
                        <h3>Change Member Status</h3>
                        <p>Are you sure you want to change the status of <strong>${memberName}</strong> (ID: ${id})?</p>
                        <p>Current Status: <span class="badge badge-${currentStatus === 'Active' ? 'success' : 'warning'}">${currentStatus}</span></p>
                        <p>New Status: <span class="badge badge-${newStatus === 'Active' ? 'success' : 'warning'}">${newStatus}</span></p>
                    `;
                    
                    document.getElementById('status-change-id').value = id;
                    document.getElementById('status-change-status').value = newStatus;
                    openModal('status-change-modal');
                });
            });
        }

        // Initialize all functionality
        function initializeAllHandlers() {
            setupViewButtons('membership');
            setupViewButtons('baptismal');
            setupViewButtons('marriage');
            setupViewButtons('child_dedication');
            setupViewButtons('visitor');

            setupEditButtons('membership');
            setupEditButtons('baptismal');
            setupEditButtons('marriage');
            setupEditButtons('child_dedication');
            setupEditButtons('visitor');

            setupDeleteButtons('membership');
            setupDeleteButtons('baptismal');
            setupDeleteButtons('marriage');
            setupDeleteButtons('child_dedication');
            setupDeleteButtons('visitor');

            setupSearch('membership-table', 'search-members');
            setupSearch('baptismal-table', 'search-baptismal');
            setupSearch('marriage-table', 'search-marriage');
            setupSearch('child-dedication-table', 'search-child-dedication');
            setupSearch('visitor-table', 'search-visitor');

            setupStatusButtons();
        }

        // Initialize when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeAllHandlers();
            setupAlertHandling();

            // Add Visitor Modal
            document.getElementById('add-visitor-btn')?.addEventListener('click', () => {
                openModal('visitor-modal');
            });

            // Add Baptismal Modal
            document.getElementById('add-baptismal-btn')?.addEventListener('click', () => {
                openModal('baptismal-modal');
            });
            document.getElementById('baptismal-exit-btn')?.addEventListener('click', () => {
                closeModal('baptismal-modal');
            });

            document.getElementById('view-visitor-exit-btn')?.addEventListener('click', () => {
                closeModal('view-visitor-modal');
            });

            // Print visitor record
            document.getElementById('print-visitor-btn')?.addEventListener('click', () => {
                const visitorId = document.getElementById('view-visitor-id').textContent;
                const printFrame = document.createElement('iframe');
                printFrame.style.display = 'none';
                document.body.appendChild(printFrame);
                
                printFrame.onload = function() {
                    printFrame.contentWindow.print();
                    setTimeout(() => {
                        document.body.removeChild(printFrame);
                    }, 1000);
                };
                
                printFrame.src = `visitor_certificate_template.php?id=${visitorId}`;
            });

            // Stay on baptismal tab if hash is present
            if (window.location.hash === '#baptismal') {
                document.querySelectorAll('.tab-navigation a').forEach(link => link.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                document.querySelector('.tab-navigation a[data-tab="baptismal"]').classList.add('active');
                document.getElementById('baptismal').classList.add('active');
            }

            // Hash-based tab activation FIRST
            let hash = window.location.hash;
            let defaultTab = 'membership';
            if (hash && document.querySelector('.tab-navigation a[data-tab="' + hash.replace('#', '') + '"]')) {
                defaultTab = hash.replace('#', '');
            }
            document.querySelectorAll('.tab-navigation a').forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.querySelector('.tab-navigation a[data-tab="' + defaultTab + '"]').classList.add('active');
            document.getElementById(defaultTab).classList.add('active');

            // Remove the hash from the URL after activating the tab
            if (window.location.hash) {
                history.replaceState(null, '', window.location.pathname);
            }

            document.getElementById('edit-baptismal-exit-btn')?.addEventListener('click', function() {
                closeModal('edit-baptismal-modal');
            });
            document.getElementById('view-baptismal-exit-btn')?.addEventListener('click', function() {
                closeModal('view-baptismal-modal');
            });

            document.getElementById('print-baptismal-btn')?.addEventListener('click', function() {
                const bapId = document.getElementById('view_bap_id').textContent.trim();
                if (!bapId) {
                    alert('No baptismal record ID found.');
                    return;
                }
                const printFrame = document.createElement('iframe');
                printFrame.style.display = 'none';
                document.body.appendChild(printFrame);
                printFrame.onload = function() {
                    printFrame.contentWindow.print();
                    setTimeout(() => {
                        document.body.removeChild(printFrame);
                    }, 1000);
                };
                printFrame.src = `baptismal_certificate_template.php?id=${encodeURIComponent(bapId)}`;
            });
        });

        // Add Membership Modal
        document.getElementById('add-membership-btn').addEventListener('click', () => {
            openModal('membership-modal');
        });

            // Modal exit buttons
        document.getElementById('membership-exit-btn').addEventListener('click', () => {
            closeModal('membership-modal');
        });

            document.getElementById('view-membership-exit-btn').addEventListener('click', () => {
                closeModal('view-membership-modal');
            });

            document.getElementById('edit-membership-exit-btn').addEventListener('click', () => {
                closeModal('edit-membership-modal');
            });

            document.getElementById('delete-exit-btn').addEventListener('click', () => {
                closeModal('delete-confirmation-modal');
            });

        document.getElementById('status-change-exit-btn').addEventListener('click', () => {
            closeModal('status-change-modal');
        });

            // Print functionality
            document.getElementById('print-membership-btn').addEventListener('click', () => {
            const memberId = document.getElementById('view-membership-id').textContent;
            const printFrame = document.createElement('iframe');
            printFrame.style.display = 'none';
            document.body.appendChild(printFrame);
            
            printFrame.onload = function() {
                printFrame.contentWindow.print();
                setTimeout(() => {
                    document.body.removeChild(printFrame);
                }, 1000);
            };
            
            printFrame.src = `certificate_template.php?id=${memberId}`;
        });

        // Reinitialize handlers after form submissions
        document.addEventListener('submit', function(e) {
            if (e.target.matches('form')) {
                setTimeout(initializeAllHandlers, 100);
            }
        });

        // Enhanced Alert Handling
        function setupAlertHandling() {
            const alerts = document.querySelectorAll('.alert');
            
            alerts.forEach(alert => {
                // Add close button if not present
                if (!alert.querySelector('.alert-close')) {
                    const closeBtn = document.createElement('button');
                    closeBtn.className = 'alert-close';
                    closeBtn.innerHTML = '×';
                    closeBtn.setAttribute('aria-label', 'Close alert');
                    
                    const actionsDiv = document.createElement('div');
                    actionsDiv.className = 'alert-actions';
                    actionsDiv.appendChild(closeBtn);
                    alert.appendChild(actionsDiv);
                    
                    closeBtn.addEventListener('click', () => {
                        dismissAlert(alert);
                    });
                }
                
                // Auto-dismiss success alerts after 5 seconds
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        dismissAlert(alert);
                    }, 5000);
                }
                
                // Auto-dismiss warning alerts after 8 seconds
                if (alert.classList.contains('alert-warning')) {
                    setTimeout(() => {
                        dismissAlert(alert);
                    }, 8000);
                }
            });
        }

        function dismissAlert(alert) {
            alert.style.animation = 'slideOutUp 0.3s ease-in forwards';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        }

        // Enhanced Status Change with better user feedback
        function setupStatusButtons() {
            document.querySelectorAll('.status-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    const currentStatus = btn.getAttribute('data-current-status');
                    const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
                    
                    // Get member name for better messaging
                    const row = btn.closest('tr');
                    const memberName = row ? row.cells[1].textContent : 'Unknown Member';
                    
                    // Update modal content with member details
                    const modal = document.getElementById('status-change-modal');
                    const modalContent = modal.querySelector('.modal-content');
                    const header = modalContent.querySelector('.form-header');
                    
                    header.innerHTML = `
                        <h3>Change Member Status</h3>
                        <p>Are you sure you want to change the status of <strong>${memberName}</strong> (ID: ${id})?</p>
                        <p>Current Status: <span class="badge badge-${currentStatus === 'Active' ? 'success' : 'warning'}">${currentStatus}</span></p>
                        <p>New Status: <span class="badge badge-${newStatus === 'Active' ? 'success' : 'warning'}">${newStatus}</span></p>
                    `;
                    
                    document.getElementById('status-change-id').value = id;
                    document.getElementById('status-change-status').value = newStatus;
                    openModal('status-change-modal');
                });
            });
        }

        // Enhanced Delete Confirmation with member details
        function setupDeleteButtons(recordType) {
            document.querySelectorAll(`.delete-btn[data-type="${recordType}"]`).forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    const row = btn.closest('tr');
                    const memberName = row ? row.cells[1].textContent : 'Unknown Member';
                    
                    // Update modal content with member details
                    const modal = document.getElementById('delete-confirmation-modal');
                    const modalContent = modal.querySelector('.modal-content');
                    const header = modalContent.querySelector('.form-header');
                    
                    header.innerHTML = `
                        <h3>⚠️ Confirm Deletion</h3>
                        <p>Are you sure you want to delete the record for <strong>${memberName}</strong> (ID: ${id})?</p>
                        <p style="color: #f44336; font-weight: 600;">⚠️ This action cannot be undone and will permanently remove all data for this member.</p>
                    `;
                    
                    document.getElementById('delete-record-id').value = id;
                    document.getElementById('delete-record-type').value = recordType;
                    openModal('delete-confirmation-modal');
                });
            });
        }
    </script>
    <script>
        $(document).ready(function() {
            $('#membership-table').DataTable();
            $('#baptismal-table').DataTable();
            $('#marriage-table').DataTable();
            $('#child-dedication-table').DataTable();
            $('#visitor-table').DataTable();
        });
    </script>
    <!-- Add this modal at the end of the file before </body> -->
    <div class="modal" id="delete-baptismal-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete this baptismal record?</p>
            </div>
            <form method="post" id="confirm-delete-baptismal-form">
                <input type="hidden" name="id" id="delete-baptismal-id">
                <input type="hidden" name="delete_baptismal" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="btn" style="background-color: var(--danger-color);">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <button type="button" class="btn exit-btn" id="delete-baptismal-exit-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Add this JS after DOMContentLoaded -->
    <script>
        // Baptismal delete modal logic
        document.querySelectorAll('.delete-baptismal-form .delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = btn.getAttribute('data-id');
                document.getElementById('delete-baptismal-id').value = id;
                openModal('delete-baptismal-modal');
            });
        });
        document.getElementById('delete-baptismal-exit-btn').addEventListener('click', function() {
            closeModal('delete-baptismal-modal');
        });
    </script>
    <!-- View Baptismal Modal -->
    <div class="modal" id="view-baptismal-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                <p>25 Artemio B. Fule St., San Pablo City</p>
                <h4>Baptismal Record</h4>
            </div>
            <div class="form-group"><label>ID</label><div class="view-field" id="view_bap_id"></div></div>
            <div class="form-group"><label>Name/Pangalan</label><div class="view-field" id="view_bap_name"></div></div>
            <div class="form-group"><label>Nickname/Palayaw</label><div class="view-field" id="view_bap_nickname"></div></div>
            <div class="form-group"><label>Address/Tirahan</label><div class="view-field" id="view_bap_address"></div></div>
            <div class="form-group"><label>Telephone No./Telepono</label><div class="view-field" id="view_bap_telephone"></div></div>
            <div class="form-group"><label>Cellphone No.</label><div class="view-field" id="view_bap_cellphone"></div></div>
            <div class="form-group"><label>E-mail</label><div class="view-field" id="view_bap_email"></div></div>
            <div class="form-group"><label>Civil Status</label><div class="view-field" id="view_bap_civil_status"></div></div>
            <div class="form-group"><label>Sex</label><div class="view-field" id="view_bap_sex"></div></div>
            <div class="form-group"><label>Birthday/Kaarawan</label><div class="view-field" id="view_bap_birthday"></div></div>
            <div class="form-group"><label>Father's Name/Pangalan ng Tatay</label><div class="view-field" id="view_bap_father_name"></div></div>
            <div class="form-group"><label>Mother's Name/Pangalan ng Nanay</label><div class="view-field" id="view_bap_mother_name"></div></div>
            <div class="form-group"><label>Name of Children/Pangalan ng Anak</label><div class="view-field" id="view_bap_children"></div></div>
            <div class="form-group"><label>Educational Attainment/Antas na natapos</label><div class="view-field" id="view_bap_education"></div></div>
            <div class="form-group"><label>Course/Kursong Natapos</label><div class="view-field" id="view_bap_course"></div></div>
            <div class="form-group"><label>School/Lokal ng Pag-aaral</label><div class="view-field" id="view_bap_school"></div></div>
            <div class="form-group"><label>Year Graduated/Taon na Natapos</label><div class="view-field" id="view_bap_year"></div></div>
            <div class="form-group"><label>If employed, what company/Pangalan ng kompanya</label><div class="view-field" id="view_bap_company"></div></div>
            <div class="form-group"><label>Position/Title/Trabaho</label><div class="view-field" id="view_bap_position"></div></div>
            <div class="form-group"><label>If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label><div class="view-field" id="view_bap_business"></div></div>
            <div class="form-group"><label>Spiritual Birthday</label><div class="view-field" id="view_bap_spiritual_birthday"></div></div>
            <div class="form-group"><label>Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label><div class="view-field" id="view_bap_inviter"></div></div>
            <div class="form-group"><label>How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label><div class="view-field" id="view_bap_how_know"></div></div>
            <div class="form-group"><label>How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label><div class="view-field" id="view_bap_attendance_duration"></div></div>
            <div class="form-group"><label>Previous Church Membership?/Dating miembro ng anong simbahan?</label><div class="view-field" id="view_bap_previous_church"></div></div>
            <div class="form-group"><label>Date of Baptism</label><div class="view-field" id="view_bap_baptism_date"></div></div>
            <div class="form-group"><label>Officiating Pastor</label><div class="view-field" id="view_bap_officiant"></div></div>
            <div class="form-group"><label>Venue of Baptismal</label><div class="view-field" id="view_bap_venue"></div></div>
            <div class="modal-buttons">
                <button type="button" class="btn print-btn" id="print-baptismal-btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="btn exit-btn" id="view-baptismal-exit-btn">Exit</button>
            </div>
        </div>
    </div>
    
    <div class="modal" id="edit-baptismal-modal">
        <div class="modal-content">
            <div class="form-header">
                <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                <p>25 Artemio B. Fule St., San Pablo City</p>
                <h4>Baptismal Record</h4>
            </div>
        </div>
    </div>
    
</body>
</html>