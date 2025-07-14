<?php
# member_records.php
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

// Check if user is an admin
$is_admin = ($_SESSION["user_role"] === "Administrator");

// Get user profile from database
$user_profile = getUserProfile($conn, $_SESSION["user"]);

// Site configuration
$church_name = "Church of Christ-Disciples";
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to format dates
function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') {
        return '';
    }
    return date('F j, Y', strtotime($date));
}

// Initialize session arrays if not set
if (!isset($_SESSION['membership_records'])) {
    $_SESSION['membership_records'] = [];
}
if (!isset($_SESSION['baptismal_records'])) {
    $_SESSION['baptismal_records'] = [];
}
if (!isset($_SESSION['marriage_records'])) {
    $_SESSION['marriage_records'] = [];
}
if (!isset($_SESSION['child_dedication_records'])) {
    $_SESSION['child_dedication_records'] = [];
}

// Initialize visitor records if not set
if (!isset($_SESSION['visitor_records'])) {
    $_SESSION['visitor_records'] = [];
}

// Fetch visitor records from database
try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "church123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists first
    $stmt = $conn->query("SHOW TABLES LIKE 'visitor_records'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT * FROM visitor_records ORDER BY id");
        $visitor_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $visitor_records = [];
    }
} catch(PDOException $e) {
    $visitor_records = [];
    // Don't show error message for missing table
}

// Fetch membership records from database
try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "church123");
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
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "church123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $conn->query("SELECT * FROM baptismal_records ORDER BY id");
    $baptismal_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $baptismal_records = [];
    $message = "Error fetching baptismal records: " . $e->getMessage();
    $messageType = "danger";
}

// Fetch marriage records from database
try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "church123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $conn->query("SELECT * FROM marriage_records ORDER BY id");
    $marriage_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $marriage_records = [];
    $message = "Error fetching marriage records: " . $e->getMessage();
    $messageType = "danger";
}

// Fetch child dedication records from database
try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "church123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists first
    $stmt = $conn->query("SHOW TABLES LIKE 'child_dedication_records'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT * FROM child_dedication_records ORDER BY id");
        $child_dedication_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $child_dedication_records = [];
    }
} catch(PDOException $e) {
    $child_dedication_records = [];
    // Don't show error message for missing table
}

// Fetch burial records from database
try {
    $conn = new PDO("mysql:host=localhost;dbname=churchdb", "root", "church123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists first
    $stmt = $conn->query("SHOW TABLES LIKE 'burial_records'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT * FROM burial_records ORDER BY id");
        $burial_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Debug: Log the burial records
        error_log("Burial records fetched: " . print_r($burial_records, true));
    } else {
        $burial_records = [];
        error_log("Burial records table does not exist");
    }
} catch(PDOException $e) {
    $burial_records = [];
    error_log("Error fetching burial records: " . $e->getMessage());
    // Don't show error message for missing table
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_membership"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
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
        $join_date = $_POST['membership_class_date']; // Use the membership class date as join date
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
        $membership_class_officiant = $_POST['membership_class_officiant']; // new

        // Prepare SQL statement
        $sql = "INSERT INTO membership_records (
            id, name, join_date, status, nickname, address, telephone, cellphone, 
            email, civil_status, sex, birthday, father_name, mother_name, children, 
            education, course, school, year, company, position, business, 
            spiritual_birthday, inviter, how_know, attendance_duration, previous_church, membership_class_officiant
        ) VALUES (
            :id, :name, :join_date, :status, :nickname, :address, :telephone, :cellphone,
            :email, :civil_status, :sex, :birthday, :father_name, :mother_name, :children,
            :education, :course, :school, :year, :company, :position, :business,
            :spiritual_birthday, :inviter, :how_know, :attendance_duration, :previous_church, :membership_class_officiant
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
        $stmt->bindParam(':membership_class_officiant', $membership_class_officiant);

        // Execute the statement
        $stmt->execute();

        $message = "New member added successfully!";
    $messageType = "success";

        // Refresh the page to show the new record and stay on membership tab
        header("Location: " . $_SERVER['PHP_SELF'] . "#membership");
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
    $password = "church123";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];
        $name = $_POST['name'];
        $join_date = $_POST['membership_class_date']; // Use membership class date as join date
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
        $membership_class_officiant = $_POST['membership_class_officiant'];

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
                previous_church = :previous_church,
                membership_class_officiant = :membership_class_officiant
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
        $stmt->bindParam(':membership_class_officiant', $membership_class_officiant);

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
    $password = "church123";
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
        } else if ($type === 'marriage') {
            $stmt = $conn->prepare("SELECT couple FROM marriage_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $marriage = $stmt->fetch(PDO::FETCH_ASSOC);
            $coupleName = $marriage ? $marriage['couple'] : 'Unknown Couple';
            $stmt = $conn->prepare("DELETE FROM marriage_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "✅ Marriage record for <strong>{$coupleName}</strong> (ID: {$id}) has been successfully deleted from the system.";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#marriage");
            exit();
        } else if ($type === 'child_dedication') {
            $stmt = $conn->prepare("SELECT child_name FROM child_dedication_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            $childName = $child ? $child['child_name'] : 'Unknown Child';
            $stmt = $conn->prepare("DELETE FROM child_dedication_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "✅ Child dedication record for <strong>{$childName}</strong> (ID: {$id}) has been successfully deleted from the system.";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#child-dedication");
            exit();
        } else if ($type === 'visitor') {
            $stmt = $conn->prepare("SELECT name FROM visitor_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
            $visitorName = $visitor ? $visitor['name'] : 'Unknown Visitor';
            $stmt = $conn->prepare("DELETE FROM visitor_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "✅ Visitor record for <strong>{$visitorName}</strong> (ID: {$id}) has been successfully deleted from the system.";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#visitor");
            exit();
        } else if ($type === 'burial') {
            $stmt = $conn->prepare("SELECT deceased_name FROM burial_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            $deceasedName = $burial ? $burial['deceased_name'] : 'Unknown Deceased';
            $stmt = $conn->prepare("DELETE FROM burial_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "✅ Burial record for <strong>{$deceasedName}</strong> (ID: {$id}) has been successfully deleted from the system.";
            $messageType = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "#burial");
            exit();
        }
        // ... handle other types as needed ...
    } catch(PDOException $e) {
        $message = "Error deleting record: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}



if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_status"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
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

// Handle visitor record save (add or edit)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_visitor"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
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

        // Check if this is an add (empty id) or edit (existing id) operation
        if (empty($id)) {
            // Add new visitor record
            $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM visitor_records");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = "V" . sprintf("%03d", ($result['max_id'] ?? 0) + 1);

            // Prepare SQL statement for INSERT
            $sql = "INSERT INTO visitor_records (
                id, name, visit_date, contact, address, purpose, invited_by, status
            ) VALUES (
                :id, :name, :visit_date, :contact, :address, :purpose, :invited_by, :status
            )";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $next_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':visit_date', $visit_date);
            $stmt->bindParam(':contact', $contact);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->bindParam(':invited_by', $invited_by);
            $stmt->bindParam(':status', $status);

            $stmt->execute();
            $message = "New visitor record added successfully!";
        } else {
            // Update existing visitor record
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
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':visit_date', $visit_date);
            $stmt->bindParam(':contact', $contact);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->bindParam(':invited_by', $invited_by);
            $stmt->bindParam(':status', $status);

            $stmt->execute();
            $message = "Visitor record updated successfully!";
        }

        $messageType = "success";

        // Refresh the page to show the updated record and stay on visitor tab
        header("Location: " . $_SERVER['PHP_SELF'] . "#visitor");
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
    $password = "church123";
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

        // Refresh the page to show the updated records and stay on visitor tab
        header("Location: " . $_SERVER['PHP_SELF'] . "#visitor");
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle burial record save (add or edit)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_burial"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];
        $deceased_name = $_POST['deceased_name'];
        $burial_date = $_POST['burial_date'];
        $officiant = $_POST['officiant'];
        $venue = $_POST['venue'];

        // Check if this is an add (empty id) or edit (existing id) operation
        if (empty($id)) {
            // Add new burial record
            $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM burial_records");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = "B" . sprintf("%03d", ($result['max_id'] ?? 0) + 1);

            // Prepare SQL statement for INSERT
            $sql = "INSERT INTO burial_records (
                id, deceased_name, burial_date, officiant, venue
            ) VALUES (
                :id, :deceased_name, :burial_date, :officiant, :venue
            )";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $next_id);
            $stmt->bindParam(':deceased_name', $deceased_name);
            $stmt->bindParam(':burial_date', $burial_date);
            $stmt->bindParam(':officiant', $officiant);
            $stmt->bindParam(':venue', $venue);

            $stmt->execute();
            $message = "New burial record added successfully!";
        } else {
            // Update existing burial record
            $sql = "UPDATE burial_records SET 
                    deceased_name = :deceased_name,
                    burial_date = :burial_date,
                    officiant = :officiant,
                    venue = :venue
                    WHERE id = :id";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':deceased_name', $deceased_name);
            $stmt->bindParam(':burial_date', $burial_date);
            $stmt->bindParam(':officiant', $officiant);
            $stmt->bindParam(':venue', $venue);

            $stmt->execute();
            $message = "Burial record updated successfully!";
        }

        $messageType = "success";

        // Refresh the page to show the updated record and stay on burial tab
        header("Location: " . $_SERVER['PHP_SELF'] . "#burial");
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle burial record deletions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_burial"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];

        // Prepare SQL statement
        $sql = "DELETE FROM burial_records WHERE id = :id";
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id);

        // Execute the statement
        $stmt->execute();

        $message = "Burial record deleted successfully!";
        $messageType = "success";

        // Refresh the page to show the updated records and stay on burial tab
        header("Location: " . $_SERVER['PHP_SELF'] . "#burial");
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle marriage form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_marriage"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the next ID
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM marriage_records");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = "M" . sprintf("%03d", ($result['max_id'] ?? 0) + 1);

        // Store POST values in variables
        $marriage_date = $_POST['marriage_date'];
        $marriage_license_no = $_POST['marriage_license_no'];
        $husband_name = $_POST['husband_name'];
        $husband_age = $_POST['husband_age'];
        $husband_birthdate = $_POST['husband_birthdate'];
        $husband_birthplace = $_POST['husband_birthplace'];
        $husband_nationality = $_POST['husband_nationality'];
        $husband_residence = $_POST['husband_residence'];
        $husband_parents = $_POST['husband_parents'];
        $husband_parents_nationality = $_POST['husband_parents_nationality'];
        $wife_name = $_POST['wife_name'];
        $wife_age = $_POST['wife_age'];
        $wife_birthdate = $_POST['wife_birthdate'];
        $wife_birthplace = $_POST['wife_birthplace'];
        $wife_nationality = $_POST['wife_nationality'];
        $wife_residence = $_POST['wife_residence'];
        $wife_parents = $_POST['wife_parents'];
        $wife_parents_nationality = $_POST['wife_parents_nationality'];
        $witnesses = $_POST['witnesses'];
        $officiated_by = $_POST['officiated_by'];

        // Create couple name for display
        $couple_name = $husband_name . " & " . $wife_name;

        // Prepare SQL statement
        $sql = "INSERT INTO marriage_records (
            id, couple, marriage_date, marriage_license_no, husband_name, husband_age, 
            husband_birthdate, husband_birthplace, husband_nationality, husband_residence, 
            husband_parents, husband_parents_nationality, wife_name, wife_age, wife_birthdate, 
            wife_birthplace, wife_nationality, wife_residence, wife_parents, wife_parents_nationality, 
            witnesses, officiated_by
        ) VALUES (
            :id, :couple, :marriage_date, :marriage_license_no, :husband_name, :husband_age,
            :husband_birthdate, :husband_birthplace, :husband_nationality, :husband_residence,
            :husband_parents, :husband_parents_nationality, :wife_name, :wife_age, :wife_birthdate,
            :wife_birthplace, :wife_nationality, :wife_residence, :wife_parents, :wife_parents_nationality,
            :witnesses, :officiated_by
        )";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $next_id);
        $stmt->bindParam(':couple', $couple_name);
        $stmt->bindParam(':marriage_date', $marriage_date);
        $stmt->bindParam(':marriage_license_no', $marriage_license_no);
        $stmt->bindParam(':husband_name', $husband_name);
        $stmt->bindParam(':husband_age', $husband_age);
        $stmt->bindParam(':husband_birthdate', $husband_birthdate);
        $stmt->bindParam(':husband_birthplace', $husband_birthplace);
        $stmt->bindParam(':husband_nationality', $husband_nationality);
        $stmt->bindParam(':husband_residence', $husband_residence);
        $stmt->bindParam(':husband_parents', $husband_parents);
        $stmt->bindParam(':husband_parents_nationality', $husband_parents_nationality);
        $stmt->bindParam(':wife_name', $wife_name);
        $stmt->bindParam(':wife_age', $wife_age);
        $stmt->bindParam(':wife_birthdate', $wife_birthdate);
        $stmt->bindParam(':wife_birthplace', $wife_birthplace);
        $stmt->bindParam(':wife_nationality', $wife_nationality);
        $stmt->bindParam(':wife_residence', $wife_residence);
        $stmt->bindParam(':wife_parents', $wife_parents);
        $stmt->bindParam(':wife_parents_nationality', $wife_parents_nationality);
        $stmt->bindParam(':witnesses', $witnesses);
        $stmt->bindParam(':officiated_by', $officiated_by);

        // Execute the statement
        $stmt->execute();

        $message = "New marriage record added successfully!";
        $messageType = "success";

        // Refresh the page to show the new record
        header("Location: " . $_SERVER['PHP_SELF'] . "#marriage");
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle marriage record edits
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_marriage"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];
        $marriage_date = $_POST['marriage_date'];
        $marriage_license_no = $_POST['marriage_license_no'];
        $husband_name = $_POST['husband_name'];
        $husband_age = $_POST['husband_age'];
        $husband_birthdate = $_POST['husband_birthdate'];
        $husband_birthplace = $_POST['husband_birthplace'];
        $husband_nationality = $_POST['husband_nationality'];
        $husband_residence = $_POST['husband_residence'];
        $husband_parents = $_POST['husband_parents'];
        $husband_parents_nationality = $_POST['husband_parents_nationality'];
        $wife_name = $_POST['wife_name'];
        $wife_age = $_POST['wife_age'];
        $wife_birthdate = $_POST['wife_birthdate'];
        $wife_birthplace = $_POST['wife_birthplace'];
        $wife_nationality = $_POST['wife_nationality'];
        $wife_residence = $_POST['wife_residence'];
        $wife_parents = $_POST['wife_parents'];
        $wife_parents_nationality = $_POST['wife_parents_nationality'];
        $witnesses = $_POST['witnesses'];
        $officiated_by = $_POST['officiated_by'];

        // Create couple name for display
        $couple_name = $husband_name . " & " . $wife_name;

        // Prepare SQL statement
        $sql = "UPDATE marriage_records SET 
                couple = :couple,
                marriage_date = :marriage_date,
                marriage_license_no = :marriage_license_no,
                husband_name = :husband_name,
                husband_age = :husband_age,
                husband_birthdate = :husband_birthdate,
                husband_birthplace = :husband_birthplace,
                husband_nationality = :husband_nationality,
                husband_residence = :husband_residence,
                husband_parents = :husband_parents,
                husband_parents_nationality = :husband_parents_nationality,
                wife_name = :wife_name,
                wife_age = :wife_age,
                wife_birthdate = :wife_birthdate,
                wife_birthplace = :wife_birthplace,
                wife_nationality = :wife_nationality,
                wife_residence = :wife_residence,
                wife_parents = :wife_parents,
                wife_parents_nationality = :wife_parents_nationality,
                witnesses = :witnesses,
                officiated_by = :officiated_by
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':couple', $couple_name);
        $stmt->bindParam(':marriage_date', $marriage_date);
        $stmt->bindParam(':marriage_license_no', $marriage_license_no);
        $stmt->bindParam(':husband_name', $husband_name);
        $stmt->bindParam(':husband_age', $husband_age);
        $stmt->bindParam(':husband_birthdate', $husband_birthdate);
        $stmt->bindParam(':husband_birthplace', $husband_birthplace);
        $stmt->bindParam(':husband_nationality', $husband_nationality);
        $stmt->bindParam(':husband_residence', $husband_residence);
        $stmt->bindParam(':husband_parents', $husband_parents);
        $stmt->bindParam(':husband_parents_nationality', $husband_parents_nationality);
        $stmt->bindParam(':wife_name', $wife_name);
        $stmt->bindParam(':wife_age', $wife_age);
        $stmt->bindParam(':wife_birthdate', $wife_birthdate);
        $stmt->bindParam(':wife_birthplace', $wife_birthplace);
        $stmt->bindParam(':wife_nationality', $wife_nationality);
        $stmt->bindParam(':wife_residence', $wife_residence);
        $stmt->bindParam(':wife_parents', $wife_parents);
        $stmt->bindParam(':wife_parents_nationality', $wife_parents_nationality);
        $stmt->bindParam(':witnesses', $witnesses);
        $stmt->bindParam(':officiated_by', $officiated_by);

        // Execute the statement
        $stmt->execute();

        $message = "Marriage record updated successfully!";
        $messageType = "success";

        // Refresh the page to show the updated record
        header("Location: " . $_SERVER['PHP_SELF'] . "#marriage");
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle child dedication form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_child_dedication"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the next ID
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM child_dedication_records");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = "C" . sprintf("%03d", ($result['max_id'] ?? 0) + 1);

        // Store POST values in variables
        $dedication_date = $_POST['dedication_date'];
        $child_name = $_POST['child_name'];
        $child_birthdate = $_POST['child_birthdate'];
        $child_birthplace = $_POST['child_birthplace'];
        $father_name = $_POST['father_name'];
        $mother_name = $_POST['mother_name'];
        $address = $_POST['address'];
        $grandparents = $_POST['grandparents'];
        $witnesses = $_POST['witnesses'];
        $officiated_by = $_POST['officiated_by'];

        // Create parents name for display
        $parents_name = $father_name . " & " . $mother_name;

        // Prepare SQL statement
        $sql = "INSERT INTO child_dedication_records (
            id, dedication_date, child_name, child_birthdate, child_birthplace, 
            father_name, mother_name, parents, address, grandparents, 
            witnesses, officiated_by
        ) VALUES (
            :id, :dedication_date, :child_name, :child_birthdate, :child_birthplace,
            :father_name, :mother_name, :parents, :address, :grandparents,
            :witnesses, :officiated_by
        )";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $next_id);
        $stmt->bindParam(':dedication_date', $dedication_date);
        $stmt->bindParam(':child_name', $child_name);
        $stmt->bindParam(':child_birthdate', $child_birthdate);
        $stmt->bindParam(':child_birthplace', $child_birthplace);
        $stmt->bindParam(':father_name', $father_name);
        $stmt->bindParam(':mother_name', $mother_name);
        $stmt->bindParam(':parents', $parents_name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':grandparents', $grandparents);
        $stmt->bindParam(':witnesses', $witnesses);
        $stmt->bindParam(':officiated_by', $officiated_by);

        // Execute the statement
        $stmt->execute();

        $message = "New child dedication record added successfully!";
        $messageType = "success";

        // Refresh the page to show the new record
        header("Location: " . $_SERVER['PHP_SELF'] . "#child-dedication");
        exit();

    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
    $conn = null;
}

// Handle child dedication record edits
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_child_dedication"]) && $is_admin) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "church123";
    $dbname = "churchdb";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store POST values in variables
        $id = $_POST['id'];
        $dedication_date = $_POST['dedication_date'];
        $child_name = $_POST['child_name'];
        $child_birthdate = $_POST['child_birthdate'];
        $child_birthplace = $_POST['child_birthplace'];
        $father_name = $_POST['father_name'];
        $mother_name = $_POST['mother_name'];
        $address = $_POST['address'];
        $grandparents = $_POST['grandparents'];
        $witnesses = $_POST['witnesses'];
        $officiated_by = $_POST['officiated_by'];

        // Create parents name for display
        $parents_name = $father_name . " & " . $mother_name;

        // Prepare SQL statement
        $sql = "UPDATE child_dedication_records SET 
                dedication_date = :dedication_date,
                child_name = :child_name,
                child_birthdate = :child_birthdate,
                child_birthplace = :child_birthplace,
                father_name = :father_name,
                mother_name = :mother_name,
                parents = :parents,
                address = :address,
                grandparents = :grandparents,
                witnesses = :witnesses,
                officiated_by = :officiated_by
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':dedication_date', $dedication_date);
        $stmt->bindParam(':child_name', $child_name);
        $stmt->bindParam(':child_birthdate', $child_birthdate);
        $stmt->bindParam(':child_birthplace', $child_birthplace);
        $stmt->bindParam(':father_name', $father_name);
        $stmt->bindParam(':mother_name', $mother_name);
        $stmt->bindParam(':parents', $parents_name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':grandparents', $grandparents);
        $stmt->bindParam(':witnesses', $witnesses);
        $stmt->bindParam(':officiated_by', $officiated_by);

        // Execute the statement
        $stmt->execute();

        $message = "Child dedication record updated successfully!";
        $messageType = "success";

        // Refresh the page to show the updated record
        header("Location: " . $_SERVER['PHP_SELF'] . "#child-dedication");
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
        $password = "church123";
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
    $password = "church123";
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
            overflow: hidden;
        }

        .sidebar-header img {
            height: 60px;
            margin-bottom: 10px;
            transition: 0.3s;
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
            transition: all 0.3s;
            font-size: 16px;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu a.active {
            background-color: var(--accent-color);
        }

        .sidebar-menu i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 20px;
        }

        .sidebar-menu span {
            margin-left: 10px;
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

        /* Form Layout Styles */
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid var(--accent-color);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-col {
            flex: 1;
        }

        .form-section h5 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .form-section h5 i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-section {
                padding: 15px;
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
                    <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                    <li><a href="events.php" class="<?php echo $current_page == 'events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Events</span></a></li>
                    <li><a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class="fas fa-video"></i> <span>Messages</span></a></li>
                    <li><a href="member_records.php" class="<?php echo $current_page == 'member_records.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span>Member Records</span></a></li>
                    <li><a href="prayers.php" class="<?php echo $current_page == 'prayers.php' ? 'active' : ''; ?>"><i class="fas fa-hands-praying"></i> <span>Prayer Requests</span></a></li>
                    <li><a href="financialreport.php" class="<?php echo $current_page == 'financialreport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span>Financial Reports</span></a></li>
                    <li><a href="member_contributions.php" class="<?php echo $current_page == 'member_contributions.php' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-dollar"></i> <span>Stewardship Report</span></a></li>
                    <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                    <li><a href="login_logs.php" class="<?php echo $current_page == 'login_logs.php' ? 'active' : ''; ?>"><i class="fas fa-sign-in-alt"></i> <span>Login Logs</span></a></li>
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
                                        <th>Officiant</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membership_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['name']); ?></td>
                                            <td><?php echo formatDate($record['join_date']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($record['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo htmlspecialchars($record['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['membership_class_officiant'] ?? ''); ?></td>
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
                                            <td><?php echo formatDate($record['baptism_date']); ?></td>
                                            <td><?php echo $record['officiant']; ?></td>
                                            <td><?php echo isset($record['venue']) ? $record['venue'] : ''; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="baptismal-view-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="baptismal"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="baptismal-edit-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="baptismal"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="baptismal-delete-<?php echo $record['id']; ?>" data-id="<?php echo $record['id']; ?>" data-type="baptismal"><i class="fas fa-trash"></i></button>
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
                                        <th>Officiated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marriage_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($record['couple']); ?></strong></td>
                                            <td><?php echo formatDate($record['marriage_date']); ?></td>
                                            <td><?php echo htmlspecialchars($record['officiated_by']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="marriage-view-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="marriage"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="marriage-edit-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="marriage"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="marriage-delete-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="marriage"><i class="fas fa-trash"></i></button>
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
                                        <th>Officiated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($child_dedication_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['child_name']); ?></td>
                                            <td><?php echo formatDate($record['dedication_date']); ?></td>
                                            <td><?php echo htmlspecialchars($record['parents']); ?></td>
                                            <td><?php echo htmlspecialchars($record['officiated_by']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="child-view-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="child_dedication"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="child-edit-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="child_dedication"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="child-delete-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="child_dedication"><i class="fas fa-trash"></i></button>
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
                                    <?php foreach ($visitor_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['name'] ?? ''); ?></td>
                                            <td><?php echo formatDate($record['visit_date'] ?? ''); ?></td>
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
                                    <?php foreach ($burial_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['deceased_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['burial_date']); ?></td>
                                            <td><?php echo htmlspecialchars($record['officiant']); ?></td>
                                            <td><?php echo htmlspecialchars($record['venue']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn view-btn" id="burial-view-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="burial"><i class="fas fa-eye"></i></button>
                                                    <?php if ($is_admin): ?>
                                                        <button class="action-btn edit-btn" id="burial-edit-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="burial"><i class="fas fa-edit"></i></button>
                                                        <button class="action-btn delete-btn" id="burial-delete-<?php echo htmlspecialchars($record['id']); ?>" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-type="burial"><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
                        <input type="hidden" name="add_membership" value="1">
                        
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-user"></i> Personal Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="name">Name/Pangalan</label>
                                        <input type="text" id="name" name="name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="nickname">Nickname/Palayaw</label>
                                        <input type="text" id="nickname" name="nickname" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="birthday">Birthday/Kaarawan</label>
                                        <input type="date" id="birthday" name="birthday" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Sex</label>
                                        <div class="radio-group">
                                            <label><input type="radio" name="sex" value="Male" required> Male</label>
                                            <label><input type="radio" name="sex" value="Female"> Female</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Civil Status</label>
                                        <div class="radio-group">
                                            <label><input type="radio" name="civil_status" value="Single" required> Single</label>
                                            <label><input type="radio" name="civil_status" value="Married"> Married</label>
                                            <label><input type="radio" name="civil_status" value="Widowed"> Widowed</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="spiritual_birthday">Spiritual Birthday</label>
                                        <input type="date" id="spiritual_birthday" name="spiritual_birthday" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-address-book"></i> Contact Information
                            </h5>
                            <div class="form-group">
                                <label for="address">Address/Tirahan</label>
                                <input type="text" id="address" name="address" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="telephone">Telephone No./Telepono</label>
                                        <input type="tel" id="telephone" name="telephone" class="form-control">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="cellphone">Cellphone No.</label>
                                        <input type="tel" id="cellphone" name="cellphone" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email" class="form-control">
                            </div>
                        </div>

                        <!-- Family Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-users"></i> Family Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="father_name">Father's Name/Pangalan ng Tatay</label>
                                        <input type="text" id="father_name" name="father_name" class="form-control">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="mother_name">Mother's Name/Pangalan ng Nanay</label>
                                        <input type="text" id="mother_name" name="mother_name" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="children">Name of Children/Pangalan ng Anak</label>
                                <textarea id="children" name="children" class="form-control" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Educational Background Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-graduation-cap"></i> Educational Background
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="education">Educational Attainment/Antas na natapos</label>
                                        <input type="text" id="education" name="education" class="form-control">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="course">Course/Kurso</label>
                                        <input type="text" id="course" name="course" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="school">School/Paaralan</label>
                                        <input type="text" id="school" name="school" class="form-control">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="year">Year/Taon</label>
                                        <input type="text" id="year" name="year" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-briefcase"></i> Employment Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="company">If employed, what company/Pangalan ng kompanya</label>
                                        <input type="text" id="company" name="company" class="form-control">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="position">Position/Title/Trabaho</label>
                                        <input type="text" id="position" name="position" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="business">If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label>
                                <input type="text" id="business" name="business" class="form-control">
                            </div>
                        </div>

                        <!-- Church Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-church"></i> Church Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="inviter">Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label>
                                        <input type="text" id="inviter" name="inviter" class="form-control">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="attendance_duration">How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                                        <input type="text" id="attendance_duration" name="attendance_duration" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="how_know">How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label>
                                <textarea id="how_know" name="how_know" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="previous_church">Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                                <input type="text" id="previous_church" name="previous_church" class="form-control">
                            </div>
                        </div>

                        <!-- Membership Class Details Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-calendar-check"></i> Membership Class Details
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="membership_class_date">Date of Membership Class</label>
                                        <input type="date" id="membership_class_date" name="membership_class_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="membership_class_officiant">Officiant (Pastor who led the membership class)</label>
                                        <input type="text" id="membership_class_officiant" name="membership_class_officiant" class="form-control" required>
                                    </div>
                                </div>
                            </div>
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
                    <div class="form-group">
                        <label>Date of Membership Class</label>
                        <div class="view-field" id="view-membership-class-date"></div>
                    </div>
                    <div class="form-group">
                        <label>Officiant (Pastor who led the membership class)</label>
                        <div class="view-field" id="view-membership-class-officiant"></div>
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
                            <label for="edit-membership-join_date">Date of being Recorded</label>
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
                        <div class="form-group">
                            <label for="edit-membership-class-date">Date of Membership Class</label>
                            <input type="date" id="edit-membership-class-date" name="membership_class_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-membership-class-officiant">Officiant (Pastor who led the membership class)</label>
                            <input type="text" id="edit-membership-class-officiant" name="membership_class_officiant" class="form-control">
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
                        
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-user"></i> Personal Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_name">Name/Pangalan</label>
                                        <input type="text" id="bap_name" name="name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_nickname">Nickname/Palayaw</label>
                                        <input type="text" id="bap_nickname" name="nickname" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_birthday">Birthday/Kaarawan</label>
                                        <input type="date" id="bap_birthday" name="birthday" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Sex</label>
                                        <div class="radio-group">
                                            <label><input type="radio" name="sex" value="Male" required> Male</label>
                                            <label><input type="radio" name="sex" value="Female"> Female</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Civil Status</label>
                                        <div class="radio-group">
                                            <label><input type="radio" name="civil_status" value="Single" required> Single</label>
                                            <label><input type="radio" name="civil_status" value="Married"> Married</label>
                                            <label><input type="radio" name="civil_status" value="Widowed"> Widowed</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_spiritual_birthday">Spiritual Birthday</label>
                                        <input type="date" id="bap_spiritual_birthday" name="spiritual_birthday" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-address-book"></i> Contact Information
                            </h5>
                            <div class="form-group">
                                <label for="bap_address">Address/Tirahan</label>
                                <input type="text" id="bap_address" name="address" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_telephone">Telephone No./Telepono</label>
                                        <input type="tel" id="bap_telephone" name="telephone" class="form-control">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_cellphone">Cellphone No.</label>
                                        <input type="tel" id="bap_cellphone" name="cellphone" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bap_email">E-mail</label>
                                <input type="email" id="bap_email" name="email" class="form-control">
                            </div>
                        </div>

                        <!-- Family Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-users"></i> Family Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_father_name">Father's Name/Pangalan ng Tatay</label>
                                        <input type="text" id="bap_father_name" name="father_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_mother_name">Mother's Name/Pangalan ng Nanay</label>
                                        <input type="text" id="bap_mother_name" name="mother_name" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bap_children">Name of Children/Pangalan ng Anak</label>
                                <textarea id="bap_children" name="children" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>

                        <!-- Educational Background Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-graduation-cap"></i> Educational Background
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_education">Educational Attainment/Antas na natapos</label>
                                        <input type="text" id="bap_education" name="education" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_course">Course/Kurso</label>
                                        <input type="text" id="bap_course" name="course" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_school">School/Paaralan</label>
                                        <input type="text" id="bap_school" name="school" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_year">Year/Taon</label>
                                        <input type="text" id="bap_year" name="year" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-briefcase"></i> Employment Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_company">If employed, what company/Pangalan ng kompanya</label>
                                        <input type="text" id="bap_company" name="company" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_position">Position/Title/Trabaho</label>
                                        <input type="text" id="bap_position" name="position" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bap_business">If self-employed, what is the nature of your business?/Kung hindi namamasukan, ano ang klase ng negosyo?</label>
                                <input type="text" id="bap_business" name="business" class="form-control" required>
                            </div>
                        </div>

                        <!-- Church Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-church"></i> Church Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_inviter">Who invited you to COCD?/Sino ang nag-imbita sa iyo sa COCD?</label>
                                        <input type="text" id="bap_inviter" name="inviter" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_attendance_duration">How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                                        <input type="text" id="bap_attendance_duration" name="attendance_duration" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bap_how_know">How did you know about COCD?/Paano mo nalaman ang tungkol sa COCD?</label>
                                <textarea id="bap_how_know" name="how_know" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="bap_previous_church">Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                                <input type="text" id="bap_previous_church" name="previous_church" class="form-control" required>
                            </div>
                        </div>

                        <!-- Baptismal Details Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-water"></i> Baptismal Details
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_baptism_date">Date of Baptism</label>
                                        <input type="date" id="bap_baptism_date" name="baptism_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="bap_officiant">Officiating Pastor</label>
                                        <input type="text" id="bap_officiant" name="officiant" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bap_venue">Venue of Baptismal</label>
                                <input type="text" id="bap_venue" name="venue" class="form-control" required>
                            </div>
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
                        <input type="hidden" name="edit_bap_id" id="edit_bap_id">
                        <div class="form-group">
                            <label for="edit_bap_name">Name/Pangalan</label>
                            <input type="text" id="edit_bap_name" name="edit_bap_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_nickname">Nickname/Palayaw</label>
                            <input type="text" id="edit_bap_nickname" name="edit_bap_nickname" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_address">Address/Tirahan</label>
                            <input type="text" id="edit_bap_address" name="edit_bap_address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_telephone">Telephone No./Telepono</label>
                            <input type="tel" id="edit_bap_telephone" name="edit_bap_telephone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_cellphone">Cellphone No.</label>
                            <input type="tel" id="edit_bap_cellphone" name="edit_bap_cellphone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_email">E-mail</label>
                            <input type="email" id="edit_bap_email" name="edit_bap_email" class="form-control">
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
                            <input type="date" id="edit_bap_birthday" name="edit_bap_birthday" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_father_name">Father's Name/Pangalan ng Tatay</label>
                            <input type="text" id="edit_bap_father_name" name="edit_bap_father_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_mother_name">Mother's Name/Pangalan ng Nanay</label>
                            <input type="text" id="edit_bap_mother_name" name="edit_bap_mother_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_children">Name of Children/Pangalan ng Anak</label>
                            <textarea id="edit_bap_children" name="edit_bap_children" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_education">Educational Attainment/Antas na natapos</label>
                            <input type="text" id="edit_bap_education" name="edit_bap_education" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_course">Course/Kurso</label>
                            <input type="text" id="edit_bap_course" name="edit_bap_course" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_school">School/Paaralan</label>
                            <input type="text" id="edit_bap_school" name="edit_bap_school" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_year">Year/Taon</label>
                            <input type="text" id="edit_bap_year" name="edit_bap_year" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_company">Company/Kumpanya</label>
                            <input type="text" id="edit_bap_company" name="edit_bap_company" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_position">Position/Posisyon</label>
                            <input type="text" id="edit_bap_position" name="edit_bap_position" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_business">Business/Negosyo</label>
                            <input type="text" id="edit_bap_business" name="edit_bap_business" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_spiritual_birthday">Spiritual Birthday</label>
                            <input type="date" id="edit_bap_spiritual_birthday" name="edit_bap_spiritual_birthday" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_inviter">Inviter/Nag-anyaya</label>
                            <input type="text" id="edit_bap_inviter" name="edit_bap_inviter" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_how_know">How did you know COCD?/Paano mo nakilala ang COCD?</label>
                            <input type="text" id="edit_bap_how_know" name="edit_bap_how_know" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_attendance_duration">How long have you been attending at COCD?/Kailan ka pa dumadalo sa COCD?</label>
                            <input type="text" id="edit_bap_attendance_duration" name="edit_bap_attendance_duration" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_previous_church">Previous Church Membership?/Dating miembro ng anong simbahan?</label>
                            <input type="text" id="edit_bap_previous_church" name="edit_bap_previous_church" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_baptism_date">Date of Baptism</label>
                            <input type="date" id="edit_bap_baptism_date" name="edit_bap_baptism_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_officiant">Officiating Pastor</label>
                            <input type="text" id="edit_bap_officiant" name="edit_bap_officiant" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_bap_venue">Venue of Baptismal</label>
                            <input type="text" id="edit_bap_venue" name="edit_bap_venue" class="form-control" required>
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
                            <button type="submit" class="btn" name="save_visitor" id="save-visitor-btn">
                                <i class="fas fa-save"></i> Save
                            </button>
                            <button type="button" class="btn exit-btn" id="visitor-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add/Edit Burial Modal -->
            <div class="modal" id="burial-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Burial Record Form</h4>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" id="burial_id" name="id">
                        <div class="form-group">
                            <label for="deceased_name">Name of Deceased</label>
                            <input type="text" id="deceased_name" name="deceased_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="burial_date">Date of Burial</label>
                            <input type="date" id="burial_date" name="burial_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="officiant">Officiant</label>
                            <input type="text" id="officiant" name="officiant" class="form-control" placeholder="Name of officiating pastor/minister" required>
                        </div>
                        <div class="form-group">
                            <label for="venue">Venue</label>
                            <input type="text" id="venue" name="venue" class="form-control" placeholder="Burial location/cemetery" required>
                        </div>
                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="save_burial">
                                <i class="fas fa-save"></i> Submit
                            </button>
                            <button type="button" class="btn exit-btn" id="burial-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
                
                <!-- Add New Marriage Modal -->
                <div class="modal" id="marriage-modal">
                    <div class="modal-content">
                        <div class="form-header">
                            <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                            <p>25 Artemio B. Fule St., San Pablo City</p>
                            <h4>Marriage Application Form</h4>
                        </div>
                        <form action="" method="post">
                            <input type="hidden" name="add_marriage" value="1">
                            
                            <!-- Marriage Details Section -->
                            <div class="form-section">
                                <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                    <i class="fas fa-heart"></i> Marriage Details
                                </h5>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="marriage_date">Date of Marriage</label>
                                            <input type="date" id="marriage_date" name="marriage_date" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="marriage_license_no">Marriage License No.</label>
                                            <input type="text" id="marriage_license_no" name="marriage_license_no" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Husband Information Section -->
                            <div class="form-section">
                                <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                    <i class="fas fa-male"></i> Husband Information
                                </h5>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_name">Name of Husband</label>
                                            <input type="text" id="husband_name" name="husband_name" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_age">Age</label>
                                            <input type="number" id="husband_age" name="husband_age" class="form-control" min="18" max="120" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_birthdate">Birthdate</label>
                                            <input type="date" id="husband_birthdate" name="husband_birthdate" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_birthplace">Birthplace</label>
                                            <input type="text" id="husband_birthplace" name="husband_birthplace" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_nationality">Nationality</label>
                                            <input type="text" id="husband_nationality" name="husband_nationality" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_residence">Residence</label>
                                            <input type="text" id="husband_residence" name="husband_residence" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_parents">Parents</label>
                                            <input type="text" id="husband_parents" name="husband_parents" class="form-control" placeholder="Father's Name & Mother's Name" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="husband_parents_nationality">Nationality of Parents</label>
                                            <input type="text" id="husband_parents_nationality" name="husband_parents_nationality" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Wife Information Section -->
                            <div class="form-section">
                                <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                    <i class="fas fa-female"></i> Wife Information
                                </h5>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_name">Name of Wife</label>
                                            <input type="text" id="wife_name" name="wife_name" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_age">Age</label>
                                            <input type="number" id="wife_age" name="wife_age" class="form-control" min="18" max="120" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_birthdate">Birthdate</label>
                                            <input type="date" id="wife_birthdate" name="wife_birthdate" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_birthplace">Birthplace</label>
                                            <input type="text" id="wife_birthplace" name="wife_birthplace" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_nationality">Nationality</label>
                                            <input type="text" id="wife_nationality" name="wife_nationality" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_residence">Residence</label>
                                            <input type="text" id="wife_residence" name="wife_residence" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_parents">Parents</label>
                                            <input type="text" id="wife_parents" name="wife_parents" class="form-control" placeholder="Father's Name & Mother's Name" required>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="wife_parents_nationality">Nationality of Parents</label>
                                            <input type="text" id="wife_parents_nationality" name="wife_parents_nationality" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Marriage Details -->
                            <div class="form-section">
                                <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                    <i class="fas fa-church"></i> Ceremony Details
                                </h5>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="witnesses">Witnesses</label>
                                            <textarea id="witnesses" name="witnesses" class="form-control" rows="3" placeholder="Enter names of witnesses" required></textarea>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="officiated_by">Officiated By</label>
                                            <input type="text" id="officiated_by" name="officiated_by" class="form-control" placeholder="Name of officiating pastor/minister" required>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="modal-buttons">
                                <button type="submit" class="btn" name="add_marriage">
                                    <i class="fas fa-save"></i> Submit
                                </button>
                                <button type="button" class="btn exit-btn" id="marriage-exit-btn">
                                    <i class="fas fa-times"></i> Exit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <!-- Edit Marriage Modal -->
            <div class="modal" id="edit-marriage-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Edit Marriage Record</h4>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" id="edit-marriage-id" name="id">
                        <input type="hidden" name="edit_marriage" value="1">
                        
                        <!-- Marriage Details Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-heart"></i> Marriage Details
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-marriage-date">Date of Marriage</label>
                                        <input type="date" id="edit-marriage-date" name="marriage_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-marriage-license-no">Marriage License No.</label>
                                        <input type="text" id="edit-marriage-license-no" name="marriage_license_no" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Husband Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-male"></i> Husband Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-name">Name of Husband</label>
                                        <input type="text" id="edit-husband-name" name="husband_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-age">Age</label>
                                        <input type="number" id="edit-husband-age" name="husband_age" class="form-control" min="18" max="120" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-birthdate">Birthdate</label>
                                        <input type="date" id="edit-husband-birthdate" name="husband_birthdate" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-birthplace">Birthplace</label>
                                        <input type="text" id="edit-husband-birthplace" name="husband_birthplace" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-nationality">Nationality</label>
                                        <input type="text" id="edit-husband-nationality" name="husband_nationality" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-residence">Residence</label>
                                        <input type="text" id="edit-husband-residence" name="husband_residence" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-parents">Parents</label>
                                        <input type="text" id="edit-husband-parents" name="husband_parents" class="form-control" placeholder="Father's Name & Mother's Name" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-husband-parents-nationality">Nationality of Parents</label>
                                        <input type="text" id="edit-husband-parents-nationality" name="husband_parents_nationality" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Wife Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-female"></i> Wife Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-name">Name of Wife</label>
                                        <input type="text" id="edit-wife-name" name="wife_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-age">Age</label>
                                        <input type="number" id="edit-wife-age" name="wife_age" class="form-control" min="18" max="120" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-birthdate">Birthdate</label>
                                        <input type="date" id="edit-wife-birthdate" name="wife_birthdate" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-birthplace">Birthplace</label>
                                        <input type="text" id="edit-wife-birthplace" name="wife_birthplace" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-nationality">Nationality</label>
                                        <input type="text" id="edit-wife-nationality" name="wife_nationality" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-residence">Residence</label>
                                        <input type="text" id="edit-wife-residence" name="wife_residence" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-parents">Parents</label>
                                        <input type="text" id="edit-wife-parents" name="wife_parents" class="form-control" placeholder="Father's Name & Mother's Name" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-wife-parents-nationality">Nationality of Parents</label>
                                        <input type="text" id="edit-wife-parents-nationality" name="wife_parents_nationality" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Marriage Details -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-church"></i> Ceremony Details
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-witnesses">Witnesses</label>
                                        <textarea id="edit-witnesses" name="witnesses" class="form-control" rows="3" placeholder="Enter names of witnesses" required></textarea>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-officiated-by">Officiated By</label>
                                        <input type="text" id="edit-officiated-by" name="officiated_by" class="form-control" placeholder="Name of officiating pastor/minister" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="edit_marriage">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn exit-btn" id="edit-marriage-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Marriage Modal -->
            <div class="modal" id="view-marriage-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Marriage Record</h4>
                    </div>
                    
                    <!-- Marriage Details Section -->
                    <div class="form-section">
                        <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                            <i class="fas fa-heart"></i> Marriage Details
                        </h5>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>ID</label>
                                    <div class="view-field" id="view-marriage-id"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Date of Marriage</label>
                                    <div class="view-field" id="view-marriage-date"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Marriage License No.</label>
                            <div class="view-field" id="view-marriage-license"></div>
                        </div>
                    </div>

                    <!-- Husband Information Section -->
                    <div class="form-section">
                        <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                            <i class="fas fa-male"></i> Husband Information
                        </h5>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Name</label>
                                    <div class="view-field" id="view-marriage-husband-name"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Age</label>
                                    <div class="view-field" id="view-marriage-husband-age"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Birthdate</label>
                                    <div class="view-field" id="view-marriage-husband-birthdate"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Birthplace</label>
                                    <div class="view-field" id="view-marriage-husband-birthplace"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Nationality</label>
                                    <div class="view-field" id="view-marriage-husband-nationality"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Residence</label>
                                    <div class="view-field" id="view-marriage-husband-residence"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Parents</label>
                                    <div class="view-field" id="view-marriage-husband-parents"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Parents' Nationality</label>
                                    <div class="view-field" id="view-marriage-husband-parents-nationality"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Wife Information Section -->
                    <div class="form-section">
                        <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                            <i class="fas fa-female"></i> Wife Information
                        </h5>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Name</label>
                                    <div class="view-field" id="view-marriage-wife-name"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Age</label>
                                    <div class="view-field" id="view-marriage-wife-age"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Birthdate</label>
                                    <div class="view-field" id="view-marriage-wife-birthdate"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Birthplace</label>
                                    <div class="view-field" id="view-marriage-wife-birthplace"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Nationality</label>
                                    <div class="view-field" id="view-marriage-wife-nationality"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Residence</label>
                                    <div class="view-field" id="view-marriage-wife-residence"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Parents</label>
                                    <div class="view-field" id="view-marriage-wife-parents"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Parents' Nationality</label>
                                    <div class="view-field" id="view-marriage-wife-parents-nationality"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ceremony Details Section -->
                    <div class="form-section">
                        <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                            <i class="fas fa-church"></i> Ceremony Details
                        </h5>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Witnesses</label>
                                    <div class="view-field" id="view-marriage-witnesses"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Officiated By</label>
                                    <div class="view-field" id="view-marriage-officiated-by"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn print-btn" id="print-marriage-btn">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn exit-btn" id="view-marriage-exit-btn">
                            <i class="fas fa-times"></i> Exit
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Child Dedication Modal -->
            <div class="modal" id="child-dedication-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Child Dedication Application Form</h4>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" name="add_child_dedication" value="1">
                        
                        <!-- Child Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-baby"></i> Child Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="dedication_date">Date of Dedication</label>
                                        <input type="date" id="dedication_date" name="dedication_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="child_name">Name of Child</label>
                                        <input type="text" id="child_name" name="child_name" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="child_birthdate">Date of Birth</label>
                                        <input type="date" id="child_birthdate" name="child_birthdate" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="child_birthplace">Place of Birth</label>
                                        <input type="text" id="child_birthplace" name="child_birthplace" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Parents Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-users"></i> Parents Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="father_name">Name of Father</label>
                                        <input type="text" id="father_name" name="father_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="mother_name">Name of Mother</label>
                                        <input type="text" id="mother_name" name="mother_name" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" class="form-control" required>
                            </div>
                        </div>

                        <!-- Additional Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-info-circle"></i> Additional Information
                            </h5>
                            <div class="form-group">
                                <label for="grandparents">Grandparents</label>
                                <textarea id="grandparents" name="grandparents" class="form-control" rows="3" placeholder="Enter names of grandparents"></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="witnesses">Witnesses</label>
                                        <textarea id="witnesses" name="witnesses" class="form-control" rows="3" placeholder="Enter names of witnesses" required></textarea>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="officiated_by">Officiated By</label>
                                        <input type="text" id="officiated_by" name="officiated_by" class="form-control" placeholder="Name of officiating pastor/minister" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="add_child_dedication">
                                <i class="fas fa-save"></i> Submit
                            </button>
                            <button type="button" class="btn exit-btn" id="child-dedication-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Child Dedication Modal -->
            <div class="modal" id="edit-child-dedication-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Edit Child Dedication Record</h4>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" id="edit-child-dedication-id" name="id">
                        <input type="hidden" name="edit_child_dedication" value="1">
                        
                        <!-- Child Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-baby"></i> Child Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-dedication-date">Date of Dedication</label>
                                        <input type="date" id="edit-dedication-date" name="dedication_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-child-name">Name of Child</label>
                                        <input type="text" id="edit-child-name" name="child_name" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-child-birthdate">Date of Birth</label>
                                        <input type="date" id="edit-child-birthdate" name="child_birthdate" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-child-birthplace">Place of Birth</label>
                                        <input type="text" id="edit-child-birthplace" name="child_birthplace" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Parents Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-users"></i> Parents Information
                            </h5>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-father-name">Name of Father</label>
                                        <input type="text" id="edit-father-name" name="father_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-mother-name">Name of Mother</label>
                                        <input type="text" id="edit-mother-name" name="mother_name" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="edit-address">Address</label>
                                <input type="text" id="edit-address" name="address" class="form-control" required>
                            </div>
                        </div>

                        <!-- Additional Information Section -->
                        <div class="form-section">
                            <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                                <i class="fas fa-info-circle"></i> Additional Information
                            </h5>
                            <div class="form-group">
                                <label for="edit-grandparents">Grandparents</label>
                                <textarea id="edit-grandparents" name="grandparents" class="form-control" rows="3" placeholder="Enter names of grandparents"></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-witnesses">Witnesses</label>
                                        <textarea id="edit-witnesses" name="witnesses" class="form-control" rows="3" placeholder="Enter names of witnesses" required></textarea>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="edit-officiated-by">Officiated By</label>
                                        <input type="text" id="edit-officiated-by" name="officiated_by" class="form-control" placeholder="Name of officiating pastor/minister" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-buttons">
                            <button type="submit" class="btn" name="edit_child_dedication">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn exit-btn" id="edit-child-dedication-exit-btn">
                                <i class="fas fa-times"></i> Exit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Child Dedication Modal -->
            <div class="modal" id="view-child-dedication-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Child Dedication Record</h4>
                    </div>
                    
                    <!-- Child Information Section -->
                    <div class="form-section">
                        <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                            <i class="fas fa-baby"></i> Child Information
                        </h5>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>ID</label>
                                    <div class="view-field" id="view-child-dedication-id"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Name of Child</label>
                                    <div class="view-field" id="view-child-dedication-child_name"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Date of Dedication</label>
                                    <div class="view-field" id="view-child-dedication-dedication_date"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <div class="view-field" id="view-child-dedication-child_birthdate"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Place of Birth</label>
                            <div class="view-field" id="view-child-dedication-child_birthplace"></div>
                        </div>
                    </div>

                    <!-- Parents Information Section -->
                    <div class="form-section">
                        <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                            <i class="fas fa-users"></i> Parents Information
                        </h5>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Name of Father</label>
                                    <div class="view-field" id="view-child-dedication-father_name"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Name of Mother</label>
                                    <div class="view-field" id="view-child-dedication-mother_name"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <div class="view-field" id="view-child-dedication-address"></div>
                        </div>
                    </div>

                    <!-- Additional Information Section -->
                    <div class="form-section">
                        <h5 style="color: var(--accent-color); margin-bottom: 15px; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px;">
                            <i class="fas fa-info-circle"></i> Additional Information
                        </h5>
                        <div class="form-group">
                            <label>Grandparents</label>
                            <div class="view-field" id="view-child-dedication-grandparents"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Witnesses</label>
                                    <div class="view-field" id="view-child-dedication-witnesses"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label>Officiated By</label>
                                    <div class="view-field" id="view-child-dedication-officiated_by"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn print-btn" id="print-child-dedication-btn">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn exit-btn" id="view-child-dedication-exit-btn">
                            <i class="fas fa-times"></i> Exit
                        </button>
                    </div>
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

            <!-- View Burial Modal -->
            <div class="modal" id="view-burial-modal">
                <div class="modal-content">
                    <div class="form-header">
                        <h3>Church of Christ-Disciples (Lopez Jaena) Inc.</h3>
                        <p>25 Artemio B. Fule St., San Pablo City</p>
                        <h4>Burial Record</h4>
                    </div>
                    <div class="form-group">
                        <label>ID</label>
                        <div class="view-field" id="view-burial-id"></div>
                    </div>
                    <div class="form-group">
                        <label>Name of Deceased</label>
                        <div class="view-field" id="view-burial-deceased-name"></div>
                    </div>
                    <div class="form-group">
                        <label>Date of Burial</label>
                        <div class="view-field" id="view-burial-date"></div>
                    </div>
                    <div class="form-group">
                        <label>Officiant</label>
                        <div class="view-field" id="view-burial-officiant"></div>
                    </div>
                    <div class="form-group">
                        <label>Venue</label>
                        <div class="view-field" id="view-burial-venue"></div>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn exit-btn" id="view-burial-exit-btn">
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
            console.log('Setting up view buttons for:', recordType);
            const buttons = document.querySelectorAll(`.view-btn[data-type="${recordType}"]`);
            console.log('Found buttons:', buttons.length);
            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    console.log('View button clicked for:', recordType, 'id:', id);
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
                                document.getElementById('view-membership-class-date').textContent = record.membership_class_date || '';
                                document.getElementById('view-membership-class-officiant').textContent = record.membership_class_officiant || '';
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
                            records = <?php echo json_encode($marriage_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                const fill = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || ''; };
                                fill('view-marriage-id', record.id);
                                fill('view-marriage-date', record.marriage_date);
                                fill('view-marriage-license', record.marriage_license_no);
                                fill('view-marriage-husband-name', record.husband_name);
                                fill('view-marriage-husband-age', record.husband_age);
                                fill('view-marriage-husband-birthdate', record.husband_birthdate);
                                fill('view-marriage-husband-birthplace', record.husband_birthplace);
                                fill('view-marriage-husband-nationality', record.husband_nationality);
                                fill('view-marriage-husband-residence', record.husband_residence);
                                fill('view-marriage-husband-parents', record.husband_parents);
                                fill('view-marriage-husband-parents-nationality', record.husband_parents_nationality);
                                fill('view-marriage-wife-name', record.wife_name);
                                fill('view-marriage-wife-age', record.wife_age);
                                fill('view-marriage-wife-birthdate', record.wife_birthdate);
                                fill('view-marriage-wife-birthplace', record.wife_birthplace);
                                fill('view-marriage-wife-nationality', record.wife_nationality);
                                fill('view-marriage-wife-residence', record.wife_residence);
                                fill('view-marriage-wife-parents', record.wife_parents);
                                fill('view-marriage-wife-parents-nationality', record.wife_parents_nationality);
                                fill('view-marriage-witnesses', record.witnesses);
                                fill('view-marriage-officiated-by', record.officiated_by);
                                openModal('view-marriage-modal');
                            }
                            break;
                        case 'child_dedication':
                            console.log('Child dedication case triggered');
                            records = <?php echo json_encode($child_dedication_records); ?>;
                            console.log('Child dedication records:', records);
                            record = records.find(r => r.id === id);
                            console.log('Found record:', record);
                            if (record) {
                                const fill = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || ''; };
                                fill('view-child-dedication-id', record.id);
                                fill('view-child-dedication-child_name', record.child_name);
                                fill('view-child-dedication-dedication_date', record.dedication_date);
                                fill('view-child-dedication-child_birthdate', record.child_birthdate);
                                fill('view-child-dedication-child_birthplace', record.child_birthplace);
                                fill('view-child-dedication-father_name', record.father_name);
                                fill('view-child-dedication-mother_name', record.mother_name);
                                fill('view-child-dedication-address', record.address);
                                fill('view-child-dedication-grandparents', record.grandparents);
                                fill('view-child-dedication-witnesses', record.witnesses);
                                fill('view-child-dedication-officiated_by', record.officiated_by);
                                openModal('view-child-dedication-modal');
                            } else {
                                console.log('No record found for id:', id);
                            }
                            break;
                        case 'visitor':
                            records = <?php echo json_encode($visitor_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('view-visitor-id').textContent = record.id;
                                document.getElementById('view-visitor-name').textContent = record.name;
                                document.getElementById('view-visitor-date').textContent = record.visit_date;
                                document.getElementById('view-visitor-contact').textContent = record.contact;
                                document.getElementById('view-visitor-address').textContent = record.address;
                                document.getElementById('view-visitor-purpose').textContent = record.purpose;
                                document.getElementById('view-visitor-invited').textContent = record.invited_by;
                                document.getElementById('view-visitor-status').textContent = record.status;
                                openModal('view-visitor-modal');
                            }
                            break;
                        case 'burial':
                            records = <?php echo json_encode($burial_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('view-burial-id').textContent = record.id;
                                document.getElementById('view-burial-deceased-name').textContent = record.deceased_name;
                                document.getElementById('view-burial-date').textContent = record.burial_date;
                                document.getElementById('view-burial-officiant').textContent = record.officiant;
                                document.getElementById('view-burial-venue').textContent = record.venue;
                                openModal('view-burial-modal');
                            }
                            break;
                    }
                });
            });
        }

        // Edit Records Functionality
        function setupEditButtons(recordType) {
            console.log('Setting up edit buttons for:', recordType);
            const buttons = document.querySelectorAll(`.edit-btn[data-type="${recordType}"]`);
            console.log('Found edit buttons:', buttons.length);
            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    console.log('Edit button clicked for:', recordType, 'id:', id);
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
                                document.getElementById('edit-membership-class-date').value = record.membership_class_date || '';
                                document.getElementById('edit-membership-class-officiant').value = record.membership_class_officiant || '';
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
                            records = <?php echo json_encode($marriage_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('edit-marriage-id').value = record.id;
                                document.getElementById('edit-marriage-date').value = record.marriage_date;
                                document.getElementById('edit-marriage-license-no').value = record.marriage_license_no;
                                document.getElementById('edit-husband-name').value = record.husband_name;
                                document.getElementById('edit-husband-age').value = record.husband_age;
                                document.getElementById('edit-husband-birthdate').value = record.husband_birthdate;
                                document.getElementById('edit-husband-birthplace').value = record.husband_birthplace;
                                document.getElementById('edit-husband-nationality').value = record.husband_nationality;
                                document.getElementById('edit-husband-residence').value = record.husband_residence;
                                document.getElementById('edit-husband-parents').value = record.husband_parents;
                                document.getElementById('edit-husband-parents-nationality').value = record.husband_parents_nationality;
                                document.getElementById('edit-wife-name').value = record.wife_name;
                                document.getElementById('edit-wife-age').value = record.wife_age;
                                document.getElementById('edit-wife-birthdate').value = record.wife_birthdate;
                                document.getElementById('edit-wife-birthplace').value = record.wife_birthplace;
                                document.getElementById('edit-wife-nationality').value = record.wife_nationality;
                                document.getElementById('edit-wife-residence').value = record.wife_residence;
                                document.getElementById('edit-wife-parents').value = record.wife_parents;
                                document.getElementById('edit-wife-parents-nationality').value = record.wife_parents_nationality;
                                document.getElementById('edit-witnesses').value = record.witnesses;
                                document.getElementById('edit-officiated-by').value = record.officiated_by;
                                openModal('edit-marriage-modal');
                            }
                            break;
                        case 'child_dedication':
                            console.log('Child dedication edit case triggered');
                            records = <?php echo json_encode($child_dedication_records); ?>;
                            console.log('Child dedication edit records:', records);
                            record = records.find(r => r.id === id);
                            console.log('Found edit record:', record);
                            if (record) {
                                const fill = (id, value) => { const el = document.getElementById(id); if (el) el.value = value || ''; };
                                fill('edit-child-dedication-id', record.id);
                                fill('edit-dedication-date', record.dedication_date);
                                fill('edit-child-name', record.child_name);
                                fill('edit-child-birthdate', record.child_birthdate);
                                fill('edit-child-birthplace', record.child_birthplace);
                                fill('edit-father-name', record.father_name);
                                fill('edit-mother-name', record.mother_name);
                                fill('edit-address', record.address);
                                fill('edit-grandparents', record.grandparents);
                                fill('edit-witnesses', record.witnesses);
                                fill('edit-officiated-by', record.officiated_by);
                                openModal('edit-child-dedication-modal');
                            } else {
                                console.log('No edit record found for id:', id);
                            }
                            break;
                        case 'visitor':
                            records = <?php echo json_encode($visitor_records); ?>;
                            record = records.find(r => r.id === id);
                            if (record) {
                                document.getElementById('visitor-id').value = record.id;
                                document.getElementById('visitor-name').value = record.name;
                                document.getElementById('visitor-date').value = record.visit_date;
                                document.getElementById('visitor-contact').value = record.contact;
                                document.getElementById('visitor-address').value = record.address;
                                document.getElementById('visitor-purpose').value = record.purpose;
                                document.getElementById('visitor-invited').value = record.invited_by;
                                document.getElementById('visitor-status').value = record.status;
                                
                                openModal('visitor-modal');
                            }
                            break;
                        case 'burial':
                            console.log('Burial edit case triggered');
                            records = <?php echo json_encode($burial_records); ?>;
                            console.log('Burial records:', records);
                            record = records.find(r => r.id === id);
                            console.log('Found burial record:', record);
                            if (record) {
                                document.getElementById('burial_id').value = record.id;
                                document.getElementById('deceased_name').value = record.deceased_name;
                                document.getElementById('burial_date').value = record.burial_date;
                                document.getElementById('officiant').value = record.officiant;
                                document.getElementById('venue').value = record.venue;
                                
                                openModal('burial-modal');
                            } else {
                                console.log('No burial record found for id:', id);
                            }
                            break;
                    }
                });
            });
        }

        // Delete Records Functionality
        function setupDeleteButtons(recordType) {
            console.log('Setting up delete buttons for:', recordType);
            const buttons = document.querySelectorAll(`.delete-btn[data-type="${recordType}"]`);
            console.log('Found delete buttons:', buttons.length);
            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    console.log('Delete button clicked for:', recordType, 'id:', id);
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
            console.log('Initializing all handlers...');
            setupViewButtons('membership');
            setupViewButtons('baptismal');
            setupViewButtons('marriage');
            setupViewButtons('child_dedication');
            setupViewButtons('visitor');
            setupViewButtons('burial');

            setupEditButtons('membership');
            setupEditButtons('baptismal');
            setupEditButtons('marriage');
            setupEditButtons('child_dedication');
            setupEditButtons('visitor');
            setupEditButtons('burial');

            setupDeleteButtons('membership');
            setupDeleteButtons('baptismal');
            setupDeleteButtons('marriage');
            setupDeleteButtons('child_dedication');
            setupDeleteButtons('visitor');
            setupDeleteButtons('burial');

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
                // Reset form for adding new visitor
                document.getElementById('visitor-id').value = '';
                document.getElementById('visitor-name').value = '';
                document.getElementById('visitor-date').value = '';
                document.getElementById('visitor-contact').value = '';
                document.getElementById('visitor-address').value = '';
                document.getElementById('visitor-purpose').value = 'Sunday Service';
                document.getElementById('visitor-invited').value = '';
                document.getElementById('visitor-status').value = 'First Time';
                
                openModal('visitor-modal');
            });

            // Add Burial Modal
            document.getElementById('add-burial-btn')?.addEventListener('click', () => {
                // Reset form for adding new burial
                document.getElementById('burial_id').value = '';
                document.getElementById('deceased_name').value = '';
                document.getElementById('burial_date').value = '';
                document.getElementById('officiant').value = '';
                document.getElementById('venue').value = '';
                openModal('burial-modal');
            });

            // Add Baptismal Modal
            document.getElementById('add-baptismal-btn')?.addEventListener('click', () => {
                openModal('baptismal-modal');
            });
            document.getElementById('baptismal-exit-btn')?.addEventListener('click', () => {
                closeModal('baptismal-modal');
            });

            // Add Marriage Modal
            document.getElementById('add-marriage-btn')?.addEventListener('click', () => {
                openModal('marriage-modal');
            });
            document.getElementById('marriage-exit-btn')?.addEventListener('click', () => {
                closeModal('marriage-modal');
            });

            // Add Child Dedication Modal
            document.getElementById('add-child-dedication-btn')?.addEventListener('click', () => {
                openModal('child-dedication-modal');
            });
            document.getElementById('child-dedication-exit-btn')?.addEventListener('click', () => {
                closeModal('child-dedication-modal');
            });

            document.getElementById('edit-marriage-exit-btn')?.addEventListener('click', () => {
                closeModal('edit-marriage-modal');
            });

            document.getElementById('view-child-dedication-exit-btn')?.addEventListener('click', () => {
                closeModal('view-child-dedication-modal');
            });

            document.getElementById('edit-child-dedication-exit-btn')?.addEventListener('click', () => {
                closeModal('edit-child-dedication-modal');
            });

            document.getElementById('view-visitor-exit-btn')?.addEventListener('click', () => {
                closeModal('view-visitor-modal');
            });

            document.getElementById('view-burial-exit-btn')?.addEventListener('click', () => {
                closeModal('view-burial-modal');
            });

            // Visitor modal exit button
            document.getElementById('visitor-exit-btn')?.addEventListener('click', () => {
                closeModal('visitor-modal');
            });

            // Burial modal exit button
            document.getElementById('burial-exit-btn')?.addEventListener('click', () => {
                closeModal('burial-modal');
            });

            // Marriage modal event listeners
            document.getElementById('view-marriage-exit-btn')?.addEventListener('click', () => {
                closeModal('view-marriage-modal');
            });

            document.getElementById('print-marriage-btn')?.addEventListener('click', () => {
                const marriageId = document.getElementById('view-marriage-id').textContent;
                const printFrame = document.createElement('iframe');
                printFrame.style.display = 'none';
                document.body.appendChild(printFrame);
                
                printFrame.onload = function() {
                    printFrame.contentWindow.print();
                    setTimeout(() => {
                        document.body.removeChild(printFrame);
                    }, 1000);
                };
                
                printFrame.src = `marriage_certificate_template.php?id=${marriageId}`;
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

            // Print child dedication record
            document.getElementById('print-child-dedication-btn')?.addEventListener('click', () => {
                const childId = document.getElementById('view-child-dedication-id').textContent;
                const printFrame = document.createElement('iframe');
                printFrame.style.display = 'none';
                document.body.appendChild(printFrame);
                
                printFrame.onload = function() {
                    printFrame.contentWindow.print();
                    setTimeout(() => {
                        document.body.removeChild(printFrame);
                    }, 1000);
                };
                
                printFrame.src = `child_dedication_certificate_template.php?id=${childId}`;
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
            $('#burial-table').DataTable(); // Add this line for burial tab
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
                <p>25 Artemio B. Fule St.   , San Pablo City</p>
                <h4>Baptismal Record</h4>
            </div>
        </div>
    </div>   
</body>
</html>