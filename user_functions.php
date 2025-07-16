<?php
// user_functions.php - Common user profile functions

/**
 * Get user profile from database
 * @param mysqli $conn Database connection
 * @param string $username Username to look up
 * @return array User profile data or default profile if not found
 */
function getUserProfile($conn, $username) {
    // First try to find by username
    $sql = "SELECT * FROM user_profiles WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $profile = $result->fetch_assoc();
        return $profile;
    }
    
    // If not found by username, try to find by user_id
    $sql = "SELECT * FROM user_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $profile = $result->fetch_assoc();
        return $profile;
    }
    
    // Return default profile if user not found
    return [
        'user_id' => $username,
        'username' => $username,
        'full_name' => $username, // Use username as fallback for full_name
        'email' => '',
        'role' => 'Member',  // Always default to Member if not found in database (Super Admin should be explicitly set)
        'profile_picture' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Update user profile in database
 * @param mysqli $conn Database connection
 * @param string $username Username to update
 * @param array $profile_data Profile data to update
 * @return bool Success status
 */
function updateUserProfile($conn, $username, $profile_data) {
    $sql = "UPDATE user_profiles SET 
            username = ?,
            full_name = ?,
            email = ?,
            contact_number = ?,
            address = ?,
            profile_picture = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", 
        $profile_data['username'],
        $profile_data['full_name'],
        $profile_data['email'],
        $profile_data['contact_number'],
        $profile_data['address'],
        $profile_data['profile_picture'],
        $username
    );
    
    return $stmt->execute();
}

/**
 * Handle file upload for profile pictures
 * @param array $file Uploaded file data
 * @param string $upload_dir Directory to upload to
 * @return array Upload result with success status and path/message
 */
function handleFileUpload($file, $upload_dir) {
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $target_file = $upload_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ["success" => false, "message" => "File is not an image."];
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "File is too large."];
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        return ["success" => false, "message" => "Only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "path" => $target_file];
    } else {
        return ["success" => false, "message" => "Failed to upload file."];
    }
}

/**
 * Get the latest live message (title or outline contains 'live' or 'ongoing')
 * @param mysqli $conn
 * @return array|null
 */
function getLiveMessage($conn) {
    $sql = "SELECT * FROM messages ORDER BY date DESC LIMIT 10";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $today = date('Y-m-d');
        while ($row = $result->fetch_assoc()) {
            $outline = json_decode($row['outline'], true);
            $is_live = false;
            if ((stripos($row['title'], 'live') !== false || stripos($row['title'], 'ongoing') !== false)) {
                $is_live = true;
            } elseif (is_array($outline)) {
                foreach ($outline as $point) {
                    $text = is_array($point) && isset($point['text']) ? $point['text'] : (is_string($point) ? $point : '');
                    if (stripos($text, 'live') !== false || stripos($text, 'ongoing') !== false) {
                        $is_live = true;
                        break;
                    }
                }
            }
            // Only show alert if the message date is today or in the future
            if ($is_live && isset($row['date']) && $row['date'] >= $today) {
                $row['outline'] = $outline;
                return $row;
            }
        }
    }
    return null;
}
?> 