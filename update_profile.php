<?php
/**
 * Helakapuwa.com - Profile Update Handler
 * Handles user profile updates including personal info, preferences, and photo uploads
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Include database connection
require_once('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'ප්‍රවේශ වී නැත. කරුණාකර ප්‍රවේශ වන්න.',
        'redirect' => 'login.html'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'වැරදි request method.'
    ]);
    exit();
}

try {
    // Start transaction
    $conn->autocommit(false);
    
    // Determine update type
    $update_type = $_POST['update_type'] ?? 'basic_info';
    
    switch ($update_type) {
        case 'basic_info':
            $result = updateBasicInfo($user_id, $_POST, $conn);
            break;
            
        case 'personal_details':
            $result = updatePersonalDetails($user_id, $_POST, $conn);
            break;
            
        case 'partner_preferences':
            $result = updatePartnerPreferences($user_id, $_POST, $conn);
            break;
            
        case 'profile_picture':
            $result = updateProfilePicture($user_id, $_FILES, $conn);
            break;
            
        case 'contact_info':
            $result = updateContactInfo($user_id, $_POST, $conn);
            break;
            
        case 'privacy_settings':
            $result = updatePrivacySettings($user_id, $_POST, $conn);
            break;
            
        default:
            throw new Exception('වැරදි update type.');
    }
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Log profile update activity
    logUserActivity($user_id, 'profile_update', "Updated {$update_type}", $conn);
    
    // Update last modified timestamp
    updateLastModified($user_id, $conn);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile successfully updated!',
        'data' => $result['data'] ?? null
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Log error
    error_log("Profile update error for user {$user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->autocommit(true);
    $conn->close();
}

/**
 * Update Basic Information
 */
function updateBasicInfo($user_id, $data, $conn) {
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            return ['success' => false, 'message' => "අවශ්‍ය ක්ෂේත්‍රය හිස්: {$field}"];
        }
    }
    
    $first_name = trim($data['first_name']);
    $last_name = trim($data['last_name']);
    $email = trim($data['email']);
    $phone = trim($data['phone'] ?? '');
    $dob = $data['dob'] ?? null;
    $gender = $data['gender'] ?? null;
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'වැරදි email format.'];
    }
    
    // Check if email is already taken by another user
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'මෙම email address එක දැනටමත් භාවිතයේ ඇත.'];
    }
    $stmt->close();
    
    // Validate phone number (Sri Lankan format)
    if (!empty($phone) && !preg_match('/^(\+94|94|0)?[1-9][0-9]{8}$/', $phone)) {
        return ['success' => false, 'message' => 'වැරදි phone number format.'];
    }
    
    // Validate date of birth
    if ($dob && !validateDateOfBirth($dob)) {
        return ['success' => false, 'message' => 'වැරදි date of birth. අවම වශයෙන් වයස 18 විය යුතුයි.'];
    }
    
    // Validate gender
    if ($gender && !in_array($gender, ['Male', 'Female'])) {
        return ['success' => false, 'message' => 'වැරදි gender value.'];
    }
    
    // Update basic information
    $stmt = $conn->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, email = ?, phone = ?, dob = ?, gender = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("ssssssi", $first_name, $last_name, $email, $phone, $dob, $gender, $user_id);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Database update අසාර්ථකයි.'];
    }
    $stmt->close();
    
    return [
        'success' => true,
        'message' => 'මූලික තොරතුරු සාර්ථකව update කරන ලදි.',
        'data' => [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone
        ]
    ];
}

/**
 * Update Personal Details
 */
function updatePersonalDetails($user_id, $data, $conn) {
    $religion = trim($data['religion'] ?? '');
    $caste = trim($data['caste'] ?? '');
    $marital_status = trim($data['marital_status'] ?? '');
    $education = trim($data['education'] ?? '');
    $profession = trim($data['profession'] ?? '');
    $income_range = trim($data['income_range'] ?? '');
    $city = trim($data['city'] ?? '');
    $province = trim($data['province'] ?? '');
    $about_me = trim($data['about_me'] ?? '');
    
    // Validate enum values
    $valid_religions = ['Buddhist', 'Hindu', 'Christian', 'Islam', 'Other'];
    if (!empty($religion) && !in_array($religion, $valid_religions)) {
        return ['success' => false, 'message' => 'වැරදි religion value.'];
    }
    
    $valid_marital_status = ['Single', 'Divorced', 'Widowed'];
    if (!empty($marital_status) && !in_array($marital_status, $valid_marital_status)) {
        return ['success' => false, 'message' => 'වැරදි marital status value.'];
    }
    
    $valid_income_ranges = ['Below 30k', '30k-50k', '50k-100k', '100k-200k', 'Above 200k'];
    if (!empty($income_range) && !in_array($income_range, $valid_income_ranges)) {
        return ['success' => false, 'message' => 'වැරදි income range value.'];
    }
    
    // Validate text lengths
    if (strlen($about_me) > 1000) {
        return ['success' => false, 'message' => 'About me section ඉතා දිගයි. අක්ෂර 1000 ට සීමා කරන්න.'];
    }
    
    // Update personal details
    $stmt = $conn->prepare("
        UPDATE users 
        SET religion = ?, caste = ?, marital_status = ?, education = ?, 
            profession = ?, income_range = ?, city = ?, province = ?, about_me = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("sssssssssi", $religion, $caste, $marital_status, $education, 
                      $profession, $income_range, $city, $province, $about_me, $user_id);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Database update අසාර්ථකයි.'];
    }
    $stmt->close();
    
    return [
        'success' => true,
        'message' => 'පෞද්ගලික විස්තර සාර්ථකව update කරන ලදි.'
    ];
}

/**
 * Update Partner Preferences
 */
function updatePartnerPreferences($user_id, $data, $conn) {
    $min_age = (int)($data['min_age'] ?? 0);
    $max_age = (int)($data['max_age'] ?? 0);
    $pref_religion = trim($data['pref_religion'] ?? '');
    $pref_caste = trim($data['pref_caste'] ?? '');
    $pref_marital_status = trim($data['pref_marital_status'] ?? '');
    $pref_profession = trim($data['pref_profession'] ?? '');
    $pref_location = trim($data['pref_location'] ?? '');
    $pref_education = trim($data['pref_education'] ?? '');
    
    // Validate age ranges
    if ($min_age > 0 && ($min_age < 18 || $min_age > 70)) {
        return ['success' => false, 'message' => 'අවම වයස 18-70 අතර විය යුතුයි.'];
    }
    
    if ($max_age > 0 && ($max_age < 18 || $max_age > 70)) {
        return ['success' => false, 'message' => 'උපරිම වයස 18-70 අතර විය යුතුයි.'];
    }
    
    if ($min_age > 0 && $max_age > 0 && $min_age > $max_age) {
        return ['success' => false, 'message' => 'අවම වයස උපරිම වයසට වඩා වැඩි විය නොහැක.'];
    }
    
    // Check if preferences already exist
    $stmt = $conn->prepare("SELECT preference_id FROM partner_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $preference_exists = $result->num_rows > 0;
    $stmt->close();
    
    if ($preference_exists) {
        // Update existing preferences
        $stmt = $conn->prepare("
            UPDATE partner_preferences 
            SET min_age = ?, max_age = ?, pref_religion = ?, pref_caste = ?, 
                pref_marital_status = ?, pref_profession = ?, pref_location = ?, pref_education = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param("iissssssi", $min_age, $max_age, $pref_religion, $pref_caste, 
                          $pref_marital_status, $pref_profession, $pref_location, $pref_education, $user_id);
    } else {
        // Insert new preferences
        $stmt = $conn->prepare("
            INSERT INTO partner_preferences 
            (user_id, min_age, max_age, pref_religion, pref_caste, pref_marital_status, 
             pref_profession, pref_location, pref_education) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssssss", $user_id, $min_age, $max_age, $pref_religion, $pref_caste, 
                          $pref_marital_status, $pref_profession, $pref_location, $pref_education);
    }
    
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Preferences update අසාර්ථකයි.'];
    }
    $stmt->close();
    
    return [
        'success' => true,
        'message' => 'සහකරු කැමැත්ත සාර්ථකව update කරන ලදි.'
    ];
}

/**
 * Update Profile Picture
 */
function updateProfilePicture($user_id, $files, $conn) {
    if (!isset($files['profile_picture']) || $files['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'ෆොටෝ upload අසාර්ථකයි.'];
    }
    
    $file = $files['profile_picture'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'JPG, PNG, හෝ GIF ෆොටෝ පමණක් upload කරන්න.'];
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'ෆොටෝ size 5MB ට වඩා අඩු විය යුතුයි.'];
    }
    
    // Validate image dimensions
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['success' => false, 'message' => 'වැරදි image file.'];
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    
    if ($width < 200 || $height < 200) {
        return ['success' => false, 'message' => 'ෆොටෝ අවම වශයෙන් 200x200 pixels විය යුතුයි.'];
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // Get current profile picture to delete later
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_pic = $result->fetch_assoc()['profile_pic'] ?? '';
    $stmt->close();
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => false, 'message' => 'ෆොටෝ upload අසාර්ථකයි.'];
    }
    
    // Resize and optimize image
    $optimized_path = optimizeImage($upload_path, $width, $height);
    if ($optimized_path) {
        $upload_path = $optimized_path;
        $new_filename = basename($optimized_path);
    }
    
    // Update database
    $relative_path = 'uploads/profiles/' . $new_filename;
    $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
    $stmt->bind_param("si", $relative_path, $user_id);
    
    if (!$stmt->execute()) {
        $stmt->close();
        // Delete uploaded file on database error
        unlink($upload_path);
        return ['success' => false, 'message' => 'Database update අසාර්ථකයි.'];
    }
    $stmt->close();
    
    // Delete old profile picture
    if (!empty($current_pic) && file_exists('../' . $current_pic)) {
        unlink('../' . $current_pic);
    }
    
    return [
        'success' => true,
        'message' => 'Profile picture සාර්ථකව update කරන ලදි.',
        'data' => [
            'profile_pic' => $relative_path
        ]
    ];
}

/**
 * Update Contact Information
 */
function updateContactInfo($user_id, $data, $conn) {
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $address = trim($data['address'] ?? '');
    $city = trim($data['city'] ?? '');
    $province = trim($data['province'] ?? '');
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'වැරදි email format.'];
    }
    
    // Check if email is already taken
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'මෙම email address එක දැනටමත් භාවිතයේ ඇත.'];
        }
        $stmt->close();
    }
    
    // Validate phone number
    if (!empty($phone) && !preg_match('/^(\+94|94|0)?[1-9][0-9]{8}$/', $phone)) {
        return ['success' => false, 'message' => 'වැරදි phone number format.'];
    }
    
    // Update contact information
    $stmt = $conn->prepare("
        UPDATE users 
        SET phone = ?, email = ?, address = ?, city = ?, province = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("sssssi", $phone, $email, $address, $city, $province, $user_id);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Database update අසාර්ථකයි.'];
    }
    $stmt->close();
    
    return [
        'success' => true,
        'message' => 'සම්බන්ධතා තොරතුරු සාර්ථකව update කරන ලදි.'
    ];
}

/**
 * Update Privacy Settings
 */
function updatePrivacySettings($user_id, $data, $conn) {
    $show_phone = isset($data['show_phone']) ? (bool)$data['show_phone'] : false;
    $show_email = isset($data['show_email']) ? (bool)$data['show_email'] : false;
    $profile_visibility = $data['profile_visibility'] ?? 'public';
    
    // Validate profile visibility
    $valid_visibility = ['public', 'members_only', 'premium_only'];
    if (!in_array($profile_visibility, $valid_visibility)) {
        return ['success' => false, 'message' => 'වැරදි profile visibility value.'];
    }
    
    // Create or update privacy settings (assuming we add a privacy_settings table)
    $stmt = $conn->prepare("
        INSERT INTO user_privacy_settings 
        (user_id, show_phone, show_email, profile_visibility) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        show_phone = VALUES(show_phone), 
        show_email = VALUES(show_email), 
        profile_visibility = VALUES(profile_visibility)
    ");
    $stmt->bind_param("iiis", $user_id, $show_phone, $show_email, $profile_visibility);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Privacy settings update අසාර්ථකයි.'];
    }
    $stmt->close();
    
    return [
        'success' => true,
        'message' => 'Privacy settings සාර්ථකව update කරන ලදි.'
    ];
}

/**
 * Validate Date of Birth
 */
function validateDateOfBirth($dob) {
    $birth_date = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    
    return $age >= 18 && $age <= 70;
}

/**
 * Optimize Image
 */
function optimizeImage($source_path, $width, $height) {
    $max_width = 800;
    $max_height = 800;
    $quality = 85;
    
    // Calculate new dimensions
    if ($width > $max_width || $height > $max_height) {
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);
    } else {
        $new_width = $width;
        $new_height = $height;
    }
    
    // Create image resource based on type
    $file_extension = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
    
    switch ($file_extension) {
        case 'jpeg':
        case 'jpg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'png':
            $source_image = imagecreatefrompng($source_path);
            break;
        case 'gif':
            $source_image = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    if (!$source_image) {
        return false;
    }
    
    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($file_extension === 'png' || $file_extension === 'gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize image
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, 
                       $new_width, $new_height, $width, $height);
    
    // Save optimized image
    $optimized_path = pathinfo($source_path, PATHINFO_DIRNAME) . '/' . 
                      pathinfo($source_path, PATHINFO_FILENAME) . '_optimized.' . 
                      pathinfo($source_path, PATHINFO_EXTENSION);
    
    $success = false;
    switch ($file_extension) {
        case 'jpeg':
        case 'jpg':
            $success = imagejpeg($new_image, $optimized_path, $quality);
            break;
        case 'png':
            $success = imagepng($new_image, $optimized_path, 9);
            break;
        case 'gif':
            $success = imagegif($new_image, $optimized_path);
            break;
    }
    
    // Clean up
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    if ($success) {
        // Delete original file
        unlink($source_path);
        return $optimized_path;
    }
    
    return false;
}

/**
 * Update Last Modified Timestamp
 */
function updateLastModified($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Log User Activity
 */
function logUserActivity($user_id, $activity_type, $description, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO user_activity_log (user_id, activity_type, description, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $user_id, $activity_type, $description);
    $stmt->execute();
    $stmt->close();
}
?>
