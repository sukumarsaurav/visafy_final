-- Create booking status table to track possible booking statuses
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

-- Insert default booking statuses
INSERT INTO `booking_statuses` (`name`, `description`, `color`) VALUES
('pending', 'Booking has been requested but not confirmed', '#FFA500'),
('confirmed', 'Booking has been confirmed', '#008000'),
('cancelled_by_user', 'Booking was cancelled by the user', '#FF0000'),
('cancelled_by_admin', 'Booking was cancelled by an administrator', '#8B0000'),
('cancelled_by_consultant', 'Booking was cancelled by the consultant', '#B22222'),
('completed', 'Booking has been completed', '#0000FF'),
('rescheduled', 'Booking has been rescheduled', '#9932CC'),
('no_show', 'Client did not show up for the booking', '#808080');

-- Create a table for business hours
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

-- Insert default business hours
INSERT INTO `business_hours` (`day_of_week`, `is_open`, `open_time`, `close_time`) VALUES
(0, 0, '00:00:00', '00:00:00'), -- Sunday (closed)
(1, 1, '09:00:00', '17:00:00'), -- Monday
(2, 1, '09:00:00', '17:00:00'), -- Tuesday
(3, 1, '09:00:00', '17:00:00'), -- Wednesday
(4, 1, '09:00:00', '17:00:00'), -- Thursday
(5, 1, '09:00:00', '17:00:00'), -- Friday
(6, 0, '00:00:00', '00:00:00'); -- Saturday (closed)

-- Create a table for holidays/special days
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

-- Create a table for team member availability
CREATE TABLE `team_member_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0 = Sunday, 1 = Monday, etc.',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration_minutes` int(11) NOT NULL DEFAULT 60,
  `buffer_time_minutes` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_member_id` (`team_member_id`),
  CONSTRAINT `availability_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for team member time off (Added missing table)
CREATE TABLE `team_member_time_off` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `time_off_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `time_off_approved_by_fk` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create the main bookings table
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(20) NOT NULL COMMENT 'Unique booking reference for client',
  `user_id` int(11) NOT NULL COMMENT 'Client who made the booking',
  `visa_service_id` int(11) NOT NULL,
  `service_consultation_id` int(11) NOT NULL COMMENT 'Links to service and consultation mode',
  `team_member_id` int(11) DEFAULT NULL COMMENT 'Assigned consultant, NULL if not yet assigned',
  `status_id` int(11) NOT NULL,
  `booking_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `client_notes` text DEFAULT NULL COMMENT 'Notes provided by client during booking',
  `admin_notes` text DEFAULT NULL COMMENT 'Internal notes for admins/team members',
  `location` text DEFAULT NULL COMMENT 'For in-person meetings',
  `meeting_link` varchar(255) DEFAULT NULL COMMENT 'For virtual meetings',
  `time_zone` varchar(50) NOT NULL DEFAULT 'UTC',
  `language_preference` varchar(50) DEFAULT 'English',
  `reminded_at` datetime DEFAULT NULL COMMENT 'When the last reminder was sent',
  `cancelled_by` int(11) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `reschedule_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times booking was rescheduled',
  `original_booking_id` int(11) DEFAULT NULL COMMENT 'If rescheduled, reference to original booking',
  `completed_by` int(11) DEFAULT NULL COMMENT 'User who marked booking as completed',
  `completion_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `user_id` (`user_id`),
  KEY `visa_service_id` (`visa_service_id`),
  KEY `service_consultation_id` (`service_consultation_id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `status_id` (`status_id`),
  KEY `cancelled_by` (`cancelled_by`),
  KEY `completed_by` (`completed_by`),
  KEY `original_booking_id` (`original_booking_id`),
  KEY `idx_bookings_datetime` (`booking_datetime`, `end_datetime`),
  KEY `idx_bookings_status_deleted` (`status_id`, `deleted_at`),
  CONSTRAINT `bookings_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_visa_service_id_fk` FOREIGN KEY (`visa_service_id`) REFERENCES `visa_services` (`visa_service_id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_service_consultation_id_fk` FOREIGN KEY (`service_consultation_id`) REFERENCES `service_consultation_modes` (`service_consultation_id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_status_id_fk` FOREIGN KEY (`status_id`) REFERENCES `booking_statuses` (`id`),
  CONSTRAINT `bookings_cancelled_by_fk` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_completed_by_fk` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_original_booking_id_fk` FOREIGN KEY (`original_booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for booking payments
CREATE TABLE `booking_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `payment_method` enum('credit_card','paypal','bank_transfer','cash','other') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded','partially_refunded') NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `idx_payments_status` (`payment_status`),
  CONSTRAINT `payments_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for booking refunds
CREATE TABLE `booking_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `processed_by` int(11) NOT NULL,
  `refund_transaction_id` varchar(255) DEFAULT NULL,
  `refund_date` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `refunds_payment_id_fk` FOREIGN KEY (`payment_id`) REFERENCES `booking_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `refunds_processed_by_fk` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for booking feedback/ratings
CREATE TABLE `booking_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (rating BETWEEN 1 AND 5),
  `feedback` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `admin_response` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_user` (`booking_id`, `user_id`),
  KEY `user_id` (`user_id`),
  KEY `responded_by` (`responded_by`),
  CONSTRAINT `feedback_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feedback_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feedback_responded_by_fk` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for booking reminders
CREATE TABLE `booking_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `reminder_type` enum('email','sms','push','system') NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `idx_reminders_scheduled` (`scheduled_time`, `status`),
  CONSTRAINT `reminders_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for booking documents
CREATE TABLE `booking_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `is_private` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'If true, only admins/team members can view',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `documents_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_uploaded_by_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create booking activity logs for audit trail
CREATE TABLE `booking_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('created','updated','status_changed','assigned','cancelled','rescheduled','payment_added','refund_processed','document_added','feedback_added','completed') NOT NULL,
  `description` text NOT NULL,
  `before_state` text DEFAULT NULL COMMENT 'JSON of relevant fields before change',
  `after_state` text DEFAULT NULL COMMENT 'JSON of relevant fields after change',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  CONSTRAINT `activity_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Stored procedure to check team member availability
-- Updated to consistently handle day_of_week conversion
DELIMITER //
CREATE PROCEDURE check_team_member_availability(
    IN p_team_member_id INT,
    IN p_start_datetime DATETIME,
    IN p_end_datetime DATETIME,
    OUT p_is_available BOOLEAN
)
BEGIN
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_day_of_week TINYINT;
    DECLARE v_is_working_day BOOLEAN DEFAULT FALSE;
    DECLARE v_is_special_day BOOLEAN DEFAULT FALSE;
    DECLARE v_is_available_time BOOLEAN DEFAULT FALSE;
    
    -- Convert MySQL WEEKDAY (0 = Monday, 6 = Sunday) to our format (0 = Sunday, 1 = Monday)
    SET v_day_of_week = WEEKDAY(p_start_datetime);
    IF v_day_of_week = 6 THEN 
        SET v_day_of_week = 0; -- Sunday
    ELSE 
        SET v_day_of_week = v_day_of_week + 1; -- Other days
    END IF;
    
    -- Check if it's a working day for the company
    SELECT COUNT(*) INTO v_count FROM business_hours 
    WHERE day_of_week = v_day_of_week AND is_open = 1;
    
    IF v_count > 0 THEN
        SET v_is_working_day = TRUE;
    END IF;
    
    -- Check if it's a special day (holiday or modified hours)
    SELECT COUNT(*) INTO v_count FROM special_days
    WHERE date = DATE(p_start_datetime);
    
    IF v_count > 0 THEN
        SET v_is_special_day = TRUE;
        
        -- Check if business is closed on that special day
        SELECT COUNT(*) INTO v_count FROM special_days
        WHERE date = DATE(p_start_datetime) AND is_closed = 1;
        
        IF v_count > 0 THEN
            SET v_is_working_day = FALSE;
        ELSE
            -- Check if booking is within alternative hours
            SELECT COUNT(*) INTO v_count FROM special_days
            WHERE date = DATE(p_start_datetime) 
            AND is_closed = 0
            AND TIME(p_start_datetime) >= alternative_open_time 
            AND TIME(p_end_datetime) <= alternative_close_time;
            
            IF v_count > 0 THEN
                SET v_is_working_day = TRUE;
            ELSE
                SET v_is_working_day = FALSE;
            END IF;
        END IF;
    END IF;
    
    -- If it's a working day (either regular or special with open hours)
    IF v_is_working_day THEN
        -- Check if within team member's availability hours (if not a special day)
        IF NOT v_is_special_day THEN
            SELECT COUNT(*) INTO v_count FROM team_member_availability
            WHERE team_member_id = p_team_member_id
            AND day_of_week = v_day_of_week
            AND is_available = 1
            AND TIME(p_start_datetime) >= start_time
            AND TIME(p_end_datetime) <= end_time;
            
            IF v_count > 0 THEN
                SET v_is_available_time = TRUE;
            END IF;
        ELSE
            -- For special days with open hours, we already checked the time is within business hours
            SET v_is_available_time = TRUE;
        END IF;
        
        -- Now check if team member doesn't have time off during this period
        IF v_is_available_time THEN
            SELECT COUNT(*) INTO v_count FROM team_member_time_off
            WHERE team_member_id = p_team_member_id
            AND status = 'approved'
            AND (
                (start_datetime <= p_start_datetime AND end_datetime >= p_start_datetime) OR
                (start_datetime <= p_end_datetime AND end_datetime >= p_end_datetime) OR
                (start_datetime >= p_start_datetime AND end_datetime <= p_end_datetime)
            );
            
            IF v_count > 0 THEN
                SET p_is_available = FALSE;
            ELSE
                -- Check if team member already has a booking during this time
                SELECT COUNT(*) INTO v_count FROM bookings b
                JOIN booking_statuses bs ON b.status_id = bs.id
                WHERE b.team_member_id = p_team_member_id
                AND bs.name IN ('pending', 'confirmed')
                AND b.deleted_at IS NULL
                AND (
                    (b.booking_datetime <= p_start_datetime AND b.end_datetime > p_start_datetime) OR
                    (b.booking_datetime < p_end_datetime AND b.end_datetime >= p_end_datetime) OR
                    (b.booking_datetime >= p_start_datetime AND b.end_datetime <= p_end_datetime)
                );
                
                IF v_count > 0 THEN
                    SET p_is_available = FALSE;
                ELSE
                    SET p_is_available = TRUE;
                END IF;
            END IF;
        ELSE
            SET p_is_available = FALSE;
        END IF;
    ELSE
        SET p_is_available = FALSE;
    END IF;
    
END //
DELIMITER ;

-- Function to generate a unique booking reference number
DELIMITER //
CREATE FUNCTION generate_booking_reference() 
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE v_ref VARCHAR(20);
    DECLARE v_exists INT;
    
    SET v_exists = 1;
    
    WHILE v_exists > 0 DO
        -- Generate reference: BK + year + random 8 digits
        SET v_ref = CONCAT('BK', DATE_FORMAT(NOW(), '%y'), LPAD(FLOOR(RAND() * 100000000), 8, '0'));
        
        -- Check if it exists
        SELECT COUNT(*) INTO v_exists FROM bookings WHERE reference_number = v_ref;
    END WHILE;
    
    RETURN v_ref;
END //
DELIMITER ;

-- Trigger to automatically generate booking reference number
DELIMITER //
CREATE TRIGGER before_booking_insert
BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    IF NEW.reference_number IS NULL OR NEW.reference_number = '' THEN
        SET NEW.reference_number = generate_booking_reference();
    END IF;
    
    -- Added: Set end_datetime based on duration_minutes during INSERT
    SET NEW.end_datetime = DATE_ADD(NEW.booking_datetime, INTERVAL NEW.duration_minutes MINUTE);
END //
DELIMITER ;

-- Trigger to set end_datetime based on duration_minutes
DELIMITER //
CREATE TRIGGER before_booking_datetime_update
BEFORE UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NEW.booking_datetime != OLD.booking_datetime OR NEW.duration_minutes != OLD.duration_minutes THEN
        SET NEW.end_datetime = DATE_ADD(NEW.booking_datetime, INTERVAL NEW.duration_minutes MINUTE);
    END IF;
END //
DELIMITER ;

-- View to get available team members for a given time slot
CREATE OR REPLACE VIEW available_team_members_view AS
SELECT 
    tm.id AS team_member_id,
    u.id AS user_id,
    CONCAT(u.first_name, ' ', u.last_name) AS team_member_name,
    tm.role,
    tm.custom_role_name,
    u.email,
    tm.phone,
    COUNT(DISTINCT tma.id) AS available_days_count
FROM 
    team_members tm
JOIN 
    users u ON tm.user_id = u.id
LEFT JOIN 
    team_member_availability tma ON tm.id = tma.team_member_id AND tma.is_available = 1
WHERE 
    u.status = 'active' 
    AND u.deleted_at IS NULL
    AND tm.deleted_at IS NULL
GROUP BY 
    tm.id, u.id, u.first_name, u.last_name, tm.role, tm.custom_role_name, u.email, tm.phone;

-- View for upcoming bookings with relevant details
CREATE OR REPLACE VIEW upcoming_bookings_view AS
SELECT 
    b.id,
    b.reference_number,
    b.booking_datetime,
    b.end_datetime,
    b.duration_minutes,
    bs.name AS status,
    bs.color AS status_color,
    u.id AS client_id,
    CONCAT(u.first_name, ' ', u.last_name) AS client_name,
    u.email AS client_email,
    v.visa_type,
    c.country_name,
    st.service_name,
    cm.mode_name AS consultation_mode,
    CONCAT(team_u.first_name, ' ', team_u.last_name) AS consultant_name,
    tm.role AS consultant_role,
    vs.base_price,
    scm.additional_fee,
    (vs.base_price + IFNULL(scm.additional_fee, 0)) AS total_price,
    bp.payment_status,
    b.meeting_link,
    b.location,
    b.time_zone
FROM 
    bookings b
JOIN 
    booking_statuses bs ON b.status_id = bs.id
JOIN 
    users u ON b.user_id = u.id
JOIN 
    visa_services vs ON b.visa_service_id = vs.visa_service_id
JOIN 
    service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
JOIN 
    consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
JOIN 
    visas v ON vs.visa_id = v.visa_id
JOIN 
    countries c ON v.country_id = c.country_id
JOIN 
    service_types st ON vs.service_type_id = st.service_type_id
LEFT JOIN 
    team_members tm ON b.team_member_id = tm.id
LEFT JOIN 
    users team_u ON tm.user_id = team_u.id
LEFT JOIN 
    booking_payments bp ON b.id = bp.booking_id AND bp.payment_status IN ('completed', 'partially_refunded')
WHERE 
    b.deleted_at IS NULL
    AND b.booking_datetime >= NOW()
    AND bs.name IN ('pending', 'confirmed')
ORDER BY 
    b.booking_datetime ASC;