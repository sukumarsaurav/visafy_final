<?php
// Include database connection
require_once __DIR__ . '/../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set response header
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if the table exists
$table_check = $conn->query("SHOW TABLES LIKE 'visa_service_configurations'");
if ($table_check->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Table already exists']);
    exit;
}

// Create the table if it doesn't exist
$create_table_sql = "
CREATE TABLE `visa_service_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_type_id` int(11) NOT NULL,
  `service_type_id` int(11) NOT NULL,
  `consultation_mode_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `visa_service_mode_unique` (`visa_type_id`, `service_type_id`, `consultation_mode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// Execute the query
if ($conn->query($create_table_sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Table created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating table: ' . $conn->error]);
} 