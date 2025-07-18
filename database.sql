CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Store only hashed passwords',
  `user_type` enum('applicant','admin','member') NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Set to 1 after OTP verification',
  `email_verification_token` varchar(100) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `google_id` VARCHAR(255) NULL,
  `auth_provider` ENUM('local', 'google') DEFAULT 'local',
  `profile_picture` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_user_type_status` (`user_type`, `status`, `deleted_at`),
  KEY `idx_users_email_verified` (`email_verified`),
  UNIQUE KEY (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE oauth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (provider, provider_user_id)
);
-- Create a table for team members
CREATE TABLE `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('Case Manager', 'Document Creator', 'Career Consultant', 'Business Plan Creator', 'Immigration Assistant', 'Social Media Manager', 'Leads & CRM Manager', 'Custom') NOT NULL,
  `custom_role_name` varchar(100) DEFAULT NULL COMMENT 'Name of custom role if role is set to Custom',
  `permissions` text DEFAULT NULL COMMENT 'JSON string of permissions associated with this role',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`, `deleted_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Main tasks table (revised)
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `admin_id` int(11) NOT NULL COMMENT 'The admin who created the task',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_priority` (`priority`),
  KEY `idx_tasks_due_date` (`due_date`),
  CONSTRAINT `tasks_admin_id_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task assignments table for multiple assignees
CREATE TABLE `task_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `team_member_id` int(11) NOT NULL COMMENT 'The team member assigned to the task',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_team_member` (`task_id`, `team_member_id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `idx_task_assignments_status` (`status`),
  CONSTRAINT `task_assignments_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignments_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task Comments table (unchanged but with updated foreign keys)
CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Can be admin or team member',
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `task_comments_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task attachments table (unchanged but with updated foreign keys)
CREATE TABLE `task_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Who uploaded the attachment',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `task_attachments_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_attachments_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task activity log (expanded to include assignment activities)
CREATE TABLE `task_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `team_member_id` int(11) DEFAULT NULL COMMENT 'The team member being acted upon, if applicable',
  `activity_type` enum('created','updated','status_changed','assigned','unassigned','member_status_changed','commented','attachment_added') NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `idx_task_activity_type` (`activity_type`),
  CONSTRAINT `task_activity_logs_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_activity_logs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_activity_logs_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- System notifications table
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User who receives the notification',
  `related_user_id` int(11) DEFAULT NULL COMMENT 'User who triggered the notification, if applicable',
  `notification_type` enum(
    'application_status_change',
    'document_requested',
    'document_submitted',
    'document_approved',
    'document_rejected',
    'booking_created',
    'booking_confirmed',
    'booking_rescheduled',
    'booking_cancelled',
    'task_assigned',
    'task_updated',
    'task_completed',
    'message_received',
    'comment_added',
    'team_member_assigned',
    'system_alert'
  ) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `related_to_type` enum('application','booking','task','message','document','user','system') NOT NULL,
  `related_to_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `is_actionable` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether notification requires user action',
  `action_url` varchar(255) DEFAULT NULL COMMENT 'URL to direct user for action',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL COMMENT 'When notification should expire/be removed',
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_type` (`notification_type`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_related` (`related_to_type`, `related_to_id`),
  KEY `idx_notifications_related_user` (`related_user_id`),
  CONSTRAINT `notifications_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_related_user_id_fk` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notification preferences table
CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_type` enum(
    'application_status_change',
    'document_requested',
    'document_submitted',
    'document_approved',
    'document_rejected',
    'booking_created',
    'booking_confirmed',
    'booking_rescheduled',
    'booking_cancelled',
    'task_assigned',
    'task_updated',
    'task_completed',
    'message_received',
    'comment_added',
    'team_member_assigned',
    'system_alert',
    'all' -- Special type to control all notifications
  ) NOT NULL,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `in_app_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_notification_type_unique` (`user_id`, `notification_type`),
  CONSTRAINT `preferences_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default notification preferences for common user types
INSERT INTO `notification_preferences` (`user_id`, `notification_type`, `email_enabled`, `push_enabled`, `in_app_enabled`)
SELECT `id`, 'all', 1, 1, 1 FROM `users` WHERE `user_type` IN ('admin', 'member');

-- Create the countries table with active status
CREATE TABLE countries (
    country_id INT PRIMARY KEY AUTO_INCREMENT,
    country_name VARCHAR(100) NOT NULL,
    country_code CHAR(3) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,  -- New column to track if country is active
    inactive_reason VARCHAR(255),    -- Optional reason for inactivity
    inactive_since DATE              -- Optional date since when inactive
);

-- Create the visas table with country relationship
CREATE TABLE visas (
    visa_id INT PRIMARY KEY AUTO_INCREMENT,
    country_id INT NOT NULL,
    visa_type VARCHAR(100) NOT NULL,
    description TEXT,
    validity_period INT, -- in days
    fee DECIMAL(10, 2),
    requirements TEXT,
    is_active BOOLEAN DEFAULT TRUE,  -- New column to track if visa is active
    inactive_reason VARCHAR(255),    -- Optional reason for inactivity
    inactive_since DATE,             -- Optional date since when inactive
    FOREIGN KEY (country_id) REFERENCES countries(country_id) ON DELETE CASCADE
);
-- Create service types table
CREATE TABLE service_types (
    service_type_id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create consultation modes table
CREATE TABLE consultation_modes (
    consultation_mode_id INT PRIMARY KEY AUTO_INCREMENT,
    mode_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_custom BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create visa_services table that connects visas with service types and base pricing
CREATE TABLE visa_services (
    visa_service_id INT PRIMARY KEY AUTO_INCREMENT,
    visa_id INT NOT NULL,
    service_type_id INT NOT NULL,
    base_price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (visa_id) REFERENCES visas(visa_id) ON DELETE CASCADE,
    FOREIGN KEY (service_type_id) REFERENCES service_types(service_type_id) ON DELETE CASCADE,
    -- Ensure unique combination of visa and service type
    UNIQUE KEY (visa_id, service_type_id)
);

-- Create service_consultation_modes table to link services with available consultation modes and their additional fees
CREATE TABLE service_consultation_modes (
    service_consultation_id INT PRIMARY KEY AUTO_INCREMENT,
    visa_service_id INT NOT NULL,
    consultation_mode_id INT NOT NULL,
    additional_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    duration_minutes INT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (visa_service_id) REFERENCES visa_services(visa_service_id) ON DELETE CASCADE,
    FOREIGN KEY (consultation_mode_id) REFERENCES consultation_modes(consultation_mode_id) ON DELETE CASCADE,
    -- Ensure unique combination of service and consultation mode
    UNIQUE KEY (visa_service_id, consultation_mode_id)
);

-- Create a view to display visa services with their consultation modes
CREATE OR REPLACE VIEW visa_services_with_modes AS
SELECT 
    vs.visa_service_id,
    vs.visa_id,
    vs.service_type_id,
    vs.base_price,
    vs.description,
    vs.is_active,
    v.visa_type,
    c.country_name,
    st.service_name,
    GROUP_CONCAT(DISTINCT cm.mode_name ORDER BY cm.mode_name ASC SEPARATOR ', ') AS available_modes,
    COUNT(DISTINCT scm.consultation_mode_id) AS mode_count
FROM 
    visa_services vs
JOIN 
    visas v ON vs.visa_id = v.visa_id
JOIN 
    countries c ON v.country_id = c.country_id
JOIN 
    service_types st ON vs.service_type_id = st.service_type_id
LEFT JOIN 
    service_consultation_modes scm ON vs.visa_service_id = scm.visa_service_id AND scm.is_available = 1
LEFT JOIN 
    consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
GROUP BY 
    vs.visa_service_id, vs.visa_id, vs.service_type_id, vs.base_price, vs.description, vs.is_active,
    v.visa_type, c.country_name, st.service_name;

DELIMITER //

CREATE TRIGGER after_task_assignment_update
AFTER UPDATE ON task_assignments
FOR EACH ROW
BEGIN
    DECLARE total_count INT;
    DECLARE completed_count INT;
    DECLARE in_progress_count INT;
    DECLARE cancelled_count INT;
    
    -- Count total active assignments
    SELECT COUNT(*) INTO total_count FROM task_assignments 
    WHERE task_id = NEW.task_id AND deleted_at IS NULL;
    
    -- Count completed assignments
    SELECT COUNT(*) INTO completed_count FROM task_assignments 
    WHERE task_id = NEW.task_id AND status = 'completed' AND deleted_at IS NULL;
    
    -- Count in-progress assignments
    SELECT COUNT(*) INTO in_progress_count FROM task_assignments 
    WHERE task_id = NEW.task_id AND status = 'in_progress' AND deleted_at IS NULL;
    
    -- Count cancelled assignments
    SELECT COUNT(*) INTO cancelled_count FROM task_assignments 
    WHERE task_id = NEW.task_id AND status = 'cancelled' AND deleted_at IS NULL;
    
    -- Update the main task status based on assignment statuses
    IF total_count = completed_count THEN
        UPDATE tasks SET status = 'completed', completed_at = NOW(), updated_at = NOW() 
        WHERE id = NEW.task_id;
    ELSEIF total_count = cancelled_count THEN
        UPDATE tasks SET status = 'cancelled', updated_at = NOW() 
        WHERE id = NEW.task_id;
    ELSEIF in_progress_count > 0 THEN
        UPDATE tasks SET status = 'in_progress', updated_at = NOW() 
        WHERE id = NEW.task_id;
    END IF;
END //

DELIMITER ;

ALTER TABLE task_assignments 
ADD COLUMN deleted_at datetime DEFAULT NULL AFTER updated_at;

-- Create a new table for consultant professional information
CREATE TABLE `consultant_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `license_type` varchar(100) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `education` text DEFAULT NULL,
  `specialty_areas` text DEFAULT NULL COMMENT 'JSON array of visa/immigration specialties',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_member_id` (`team_member_id`),
  CONSTRAINT `consultant_profiles_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for consultant languages
CREATE TABLE `consultant_languages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_profile_id` int(11) NOT NULL,
  `language` varchar(50) NOT NULL,
  `proficiency_level` enum('basic','intermediate','fluent','native') NOT NULL DEFAULT 'fluent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consultant_profile_id` (`consultant_profile_id`),
  CONSTRAINT `consultant_languages_profile_id_fk` FOREIGN KEY (`consultant_profile_id`) REFERENCES `consultant_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table for consultant certification and credentials
CREATE TABLE `consultant_certifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_profile_id` int(11) NOT NULL,
  `certification_name` varchar(100) NOT NULL,
  `issuing_authority` varchar(100) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `verification_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consultant_profile_id` (`consultant_profile_id`),
  CONSTRAINT `consultant_certifications_profile_id_fk` FOREIGN KEY (`consultant_profile_id`) REFERENCES `consultant_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create table for consultant reviews
CREATE TABLE `consultant_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Applicant who left the review',
  `booking_id` int(11) DEFAULT NULL COMMENT 'Related booking if applicable',
  `application_id` int(11) DEFAULT NULL COMMENT 'Related application if applicable',
  `rating` tinyint(1) NOT NULL CHECK (rating BETWEEN 1 AND 5),
  `review_text` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `review_date` datetime NOT NULL DEFAULT current_timestamp(),
  `admin_response` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `user_id` (`user_id`),
  KEY `booking_id` (`booking_id`),
  KEY `application_id` (`application_id`),
  KEY `responded_by` (`responded_by`),
  CONSTRAINT `consultant_reviews_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultant_reviews_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultant_reviews_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consultant_reviews_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consultant_reviews_responded_by_fk` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create table for consultant services (which visa services they can provide)
CREATE TABLE `consultant_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `visa_service_id` int(11) NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `custom_fee_adjustment` decimal(10,2) DEFAULT NULL COMMENT 'Additional fee this consultant may charge',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_member_visa_service` (`team_member_id`, `visa_service_id`),
  KEY `visa_service_id` (`visa_service_id`),
  CONSTRAINT `consultant_services_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultant_services_visa_service_id_fk` FOREIGN KEY (`visa_service_id`) REFERENCES `visa_services` (`visa_service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a view to display consultant information with ratings
CREATE OR REPLACE VIEW consultant_profiles_view AS
SELECT 
    cp.id AS consultant_profile_id,
    tm.id AS team_member_id,
    u.id AS user_id,
    CONCAT(u.first_name, ' ', u.last_name) AS consultant_name,
    tm.role,
    tm.custom_role_name,
    u.email,
    u.profile_picture,
    tm.phone,
    cp.license_number,
    cp.license_expiry,
    cp.license_type,
    cp.years_of_experience,
    cp.bio,
    cp.education,
    cp.specialty_areas,
    ROUND(AVG(cr.rating), 1) AS average_rating,
    COUNT(cr.id) AS review_count,
    (SELECT GROUP_CONCAT(DISTINCT cl.language ORDER BY cl.language SEPARATOR ', ')
     FROM consultant_languages cl 
     WHERE cl.consultant_profile_id = cp.id) AS languages,
    (SELECT COUNT(DISTINCT cs.visa_service_id) 
     FROM consultant_services cs 
     WHERE cs.team_member_id = tm.id) AS service_count,
    (SELECT GROUP_CONCAT(DISTINCT v.visa_type ORDER BY v.visa_type SEPARATOR ', ')
     FROM consultant_services cs 
     JOIN visa_services vs ON cs.visa_service_id = vs.visa_service_id
     JOIN visas v ON vs.visa_id = v.visa_id
     WHERE cs.team_member_id = tm.id) AS visa_types
FROM 
    team_members tm
JOIN 
    users u ON tm.user_id = u.id
LEFT JOIN 
    consultant_profiles cp ON tm.id = cp.team_member_id
LEFT JOIN 
    consultant_reviews cr ON tm.id = cr.team_member_id AND cr.status = 'approved'
WHERE 
    tm.role = 'Immigration Assistant'
    AND u.status = 'active'
    AND tm.deleted_at IS NULL
    AND u.deleted_at IS NULL
GROUP BY 
    cp.id, tm.id, u.id, u.first_name, u.last_name, tm.role, 
    tm.custom_role_name, u.email, u.profile_picture, tm.phone,
    cp.license_number, cp.license_expiry, cp.license_type,
    cp.years_of_experience, cp.bio, cp.education, cp.specialty_areas;

-- Modify the existing member registration process in become-member.php to include additional fields
-- (This would be added to the PHP file, around line 80 where the team member record is created)

