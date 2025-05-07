<?php
// Include database connection
require_once '../../config/db_connect.php';

// Check if business_hours table exists
$result = $conn->query("SHOW TABLES LIKE 'business_hours'");
$tableExists = $result->num_rows > 0;

if (!$tableExists) {
    // Create business_hours table
    $createTableSQL = "
    CREATE TABLE `business_hours` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `day_of_week` tinyint(1) NOT NULL COMMENT '0 = Sunday, 1 = Monday, etc.',
      `is_open` tinyint(1) NOT NULL DEFAULT 1,
      `open_time` time NOT NULL DEFAULT '09:00:00',
      `close_time` time NOT NULL DEFAULT '17:00:00',
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `day_of_week` (`day_of_week`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    if ($conn->query($createTableSQL) === TRUE) {
        echo "Table 'business_hours' created successfully<br>";
        
        // Insert default business hours
        $insertDataSQL = "
        INSERT INTO `business_hours` (`day_of_week`, `is_open`, `open_time`, `close_time`) VALUES
        (0, 0, '00:00:00', '00:00:00'), -- Sunday (closed)
        (1, 1, '09:00:00', '17:00:00'), -- Monday
        (2, 1, '09:00:00', '17:00:00'), -- Tuesday
        (3, 1, '09:00:00', '17:00:00'), -- Wednesday
        (4, 1, '09:00:00', '17:00:00'), -- Thursday
        (5, 1, '09:00:00', '17:00:00'), -- Friday
        (6, 0, '00:00:00', '00:00:00')  -- Saturday (closed)
        ";
        
        if ($conn->query($insertDataSQL) === TRUE) {
            echo "Default business hours added successfully<br>";
        } else {
            echo "Error inserting default business hours: " . $conn->error . "<br>";
        }
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "Table 'business_hours' already exists<br>";
}

// Check if special_days table exists
$result = $conn->query("SHOW TABLES LIKE 'special_days'");
$tableExists = $result->num_rows > 0;

if (!$tableExists) {
    // Create special_days table
    $createTableSQL = "
    CREATE TABLE `special_days` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `date` date NOT NULL,
      `description` varchar(255) NOT NULL,
      `is_closed` tinyint(1) NOT NULL DEFAULT 1,
      `alternative_open_time` time DEFAULT NULL,
      `alternative_close_time` time DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    if ($conn->query($createTableSQL) === TRUE) {
        echo "Table 'special_days' created successfully<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "Table 'special_days' already exists<br>";
}

// Check if booking_statuses table exists
$result = $conn->query("SHOW TABLES LIKE 'booking_statuses'");
$tableExists = $result->num_rows > 0;

if (!$tableExists) {
    // Create booking_statuses table
    $createTableSQL = "
    CREATE TABLE `booking_statuses` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(50) NOT NULL,
      `description` text,
      `color` varchar(7) DEFAULT '#808080' COMMENT 'Hex color code for UI display',
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    if ($conn->query($createTableSQL) === TRUE) {
        echo "Table 'booking_statuses' created successfully<br>";
        
        // Insert default booking statuses
        $insertDataSQL = "
        INSERT INTO `booking_statuses` (`name`, `description`, `color`) VALUES
        ('pending', 'Booking has been requested but not confirmed', '#FFA500'),
        ('confirmed', 'Booking has been confirmed', '#008000'),
        ('cancelled_by_user', 'Booking was cancelled by the user', '#FF0000'),
        ('cancelled_by_admin', 'Booking was cancelled by an administrator', '#8B0000'),
        ('cancelled_by_consultant', 'Booking was cancelled by the consultant', '#B22222'),
        ('completed', 'Booking has been completed', '#0000FF'),
        ('rescheduled', 'Booking has been rescheduled', '#9932CC'),
        ('no_show', 'Client did not show up for the booking', '#808080');
        ";
        
        if ($conn->query($insertDataSQL) === TRUE) {
            echo "Default booking statuses added successfully<br>";
        } else {
            echo "Error inserting default booking statuses: " . $conn->error . "<br>";
        }
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "Table 'booking_statuses' already exists<br>";
}

echo "<br>Setup complete. <a href='bookings.php'>Return to Bookings</a>";
?> 