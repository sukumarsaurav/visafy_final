<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set response header
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get form data
$country_name = isset($_POST['country_name']) ? trim($_POST['country_name']) : '';
$country_code = isset($_POST['country_code']) ? strtoupper(trim($_POST['country_code'])) : '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Validate country name
if (empty($country_name)) {
    echo json_encode(['success' => false, 'error' => 'Country name is required']);
    exit;
}

// Validate country code
if (empty($country_code) || strlen($country_code) != 2) {
    echo json_encode(['success' => false, 'error' => 'Valid 2-letter country code is required']);
    exit;
}

// Process flag image if uploaded
$flag_image = null;
if (isset($_FILES['country_flag']) && $_FILES['country_flag']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png'];
    $file_type = $_FILES['country_flag']['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Only JPEG and PNG image files are allowed']);
        exit;
    }
    
    $max_size = 1024 * 1024; // 1MB
    if ($_FILES['country_flag']['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'File size must be less than 1MB']);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../../../uploads/flags/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $filename = $country_code . '_' . time() . '.' . pathinfo($_FILES['country_flag']['name'], PATHINFO_EXTENSION);
    $upload_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['country_flag']['tmp_name'], $upload_path)) {
        $flag_image = $filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload flag image']);
        exit;
    }
}

try {
    // Check if country already exists
    $check_stmt = $conn->prepare("SELECT id FROM countries WHERE name = ? OR code = ?");
    $check_stmt->bind_param("ss", $country_name, $country_code);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Country with this name or code already exists']);
        exit;
    }
    
    // Insert new country
    $stmt = $conn->prepare("INSERT INTO countries (name, code, flag_image, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssi", $country_name, $country_code, $flag_image, $is_active);
    
    if ($stmt->execute()) {
        $country_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Country added successfully', 
            'country_id' => $country_id,
            'country_name' => $country_name,
            'country_code' => $country_code,
            'flag_image' => $flag_image,
            'is_active' => $is_active
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add country: ' . $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 