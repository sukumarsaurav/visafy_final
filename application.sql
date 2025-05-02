-- Applications status table
CREATE TABLE `application_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `color` varchar(7) DEFAULT '#808080' COMMENT 'Hex color code for UI display',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default application statuses
INSERT INTO `application_statuses` (`name`, `description`, `color`) VALUES
('draft', 'Application is being drafted', '#FFA500'),
('submitted', 'Application has been submitted', '#1E90FF'),
('under_review', 'Application is under review', '#9932CC'),
('additional_documents_requested', 'Additional documents have been requested', '#FF8C00'),
('processing', 'Application is being processed', '#4682B4'),
('approved', 'Application has been approved', '#008000'),
('rejected', 'Application has been rejected', '#FF0000'),
('on_hold', 'Application is on hold', '#808080'),
('completed', 'Application process has been completed', '#0000FF'),
('cancelled', 'Application has been cancelled', '#8B0000');

-- Required documents for visa types
CREATE TABLE `visa_required_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `visa_document` (`visa_id`, `document_type_id`),
  KEY `document_type_id` (`document_type_id`),
  CONSTRAINT `visa_required_documents_visa_id_fk` FOREIGN KEY (`visa_id`) REFERENCES `visas` (`visa_id`) ON DELETE CASCADE,
  CONSTRAINT `visa_required_documents_document_type_id_fk` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Main applications table
CREATE TABLE `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(20) NOT NULL COMMENT 'Unique application reference for tracking',
  `user_id` int(11) NOT NULL COMMENT 'Applicant',
  `visa_id` int(11) NOT NULL COMMENT 'Visa being applied for',
  `status_id` int(11) NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'Internal notes for application',
  `expected_completion_date` date DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `created_by` int(11) NOT NULL COMMENT 'Admin who created the application',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `user_id` (`user_id`),
  KEY `visa_id` (`visa_id`),
  KEY `status_id` (`status_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_applications_priority` (`priority`),
  CONSTRAINT `applications_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applications_visa_id_fk` FOREIGN KEY (`visa_id`) REFERENCES `visas` (`visa_id`) ON DELETE CASCADE,
  CONSTRAINT `applications_status_id_fk` FOREIGN KEY (`status_id`) REFERENCES `application_statuses` (`id`),
  CONSTRAINT `applications_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Application documents
CREATE TABLE `application_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','submitted','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_document` (`application_id`, `document_type_id`),
  KEY `document_type_id` (`document_type_id`),
  KEY `submitted_by` (`submitted_by`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_document_status` (`status`),
  CONSTRAINT `application_documents_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_documents_document_type_id_fk` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_documents_submitted_by_fk` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `application_documents_reviewed_by_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Application status history
CREATE TABLE `application_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `status_id` (`status_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `application_status_history_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_status_history_status_id_fk` FOREIGN KEY (`status_id`) REFERENCES `application_statuses` (`id`),
  CONSTRAINT `application_status_history_changed_by_fk` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Application comments
CREATE TABLE `application_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'If true, only visible to team members',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `application_comments_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_comments_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Create application assignments junction table
CREATE TABLE `application_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `team_member_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed','reassigned') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `app_assignments_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_assignments_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_assignments_assigned_by_fk` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Application activity logs
CREATE TABLE `application_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('created','updated','status_changed','document_added','document_updated','comment_added','assigned','completed') NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  CONSTRAINT `application_activity_logs_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_activity_logs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Function to generate application reference number
DELIMITER //
CREATE FUNCTION generate_application_reference() 
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE v_ref VARCHAR(20);
    DECLARE v_exists INT;
    
    SET v_exists = 1;
    
    WHILE v_exists > 0 DO
        -- Generate reference: APP + year + random 7 digits
        SET v_ref = CONCAT('APP', DATE_FORMAT(NOW(), '%y'), LPAD(FLOOR(RAND() * 10000000), 7, '0'));
        
        -- Check if it exists
        SELECT COUNT(*) INTO v_exists FROM applications WHERE reference_number = v_ref;
    END WHILE;
    
    RETURN v_ref;
END //
DELIMITER ;

-- Trigger to automatically generate reference number
DELIMITER //
CREATE TRIGGER before_application_insert
BEFORE INSERT ON applications
FOR EACH ROW
BEGIN
    IF NEW.reference_number IS NULL OR NEW.reference_number = '' THEN
        SET NEW.reference_number = generate_application_reference();
    END IF;
END //
DELIMITER ;

-- Create a view for applications with related information
DROP VIEW IF EXISTS applications_view;
CREATE VIEW applications_view AS
SELECT 
    a.id,
    a.reference_number COLLATE utf8mb4_general_ci AS reference_number,
    a.visa_id,
    a.status_id,
    aa.team_member_id,  -- From application_assignments
    a.submitted_at,
    a.expected_completion_date,
    a.priority COLLATE utf8mb4_general_ci AS priority,
    a.created_at,
    a.updated_at,
    a.deleted_at,
    ast.name COLLATE utf8mb4_general_ci AS status_name,
    ast.color COLLATE utf8mb4_general_ci AS status_color,
    u.id AS applicant_id,
    CONCAT(u.first_name, ' ', u.last_name) COLLATE utf8mb4_general_ci AS applicant_name,
    u.email COLLATE utf8mb4_general_ci AS applicant_email,
    v.visa_type COLLATE utf8mb4_general_ci,
    c.country_name COLLATE utf8mb4_general_ci,
    CONCAT(tm_u.first_name, ' ', tm_u.last_name) COLLATE utf8mb4_general_ci AS case_manager_name,
    tm.role COLLATE utf8mb4_general_ci AS case_manager_role,
    COUNT(DISTINCT ad.id) AS total_documents,
    SUM(CASE WHEN ad.status = 'approved' THEN 1 ELSE 0 END) AS approved_documents,
    SUM(CASE WHEN ad.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_documents,
    SUM(CASE WHEN ad.status = 'pending' THEN 1 ELSE 0 END) AS pending_documents,
    SUM(CASE WHEN ad.status = 'submitted' THEN 1 ELSE 0 END) AS submitted_documents
FROM 
    applications a
JOIN 
    application_statuses ast ON a.status_id = ast.id
JOIN 
    users u ON a.user_id = u.id
JOIN 
    visas v ON a.visa_id = v.visa_id
JOIN 
    countries c ON v.country_id = c.country_id
LEFT JOIN 
    application_assignments aa ON a.id = aa.application_id AND aa.status = 'active'
LEFT JOIN 
    team_members tm ON aa.team_member_id = tm.id
LEFT JOIN 
    users tm_u ON tm.user_id = tm_u.id
LEFT JOIN 
    application_documents ad ON a.id = ad.application_id
WHERE 
    a.deleted_at IS NULL
GROUP BY 
    a.id, a.reference_number, a.visa_id, a.status_id, aa.team_member_id, a.submitted_at,
    a.expected_completion_date, a.priority, a.created_at, a.updated_at, a.deleted_at, ast.name, ast.color,
    u.id, u.first_name, u.last_name, u.email, v.visa_type, c.country_name,
    tm_u.first_name, tm_u.last_name, tm.role
ORDER BY 
    FIELD(a.status_id, 
        (SELECT id FROM application_statuses WHERE name = 'draft'),
        (SELECT id FROM application_statuses WHERE name = 'submitted'),
        (SELECT id FROM application_statuses WHERE name = 'under_review'),
        (SELECT id FROM application_statuses WHERE name = 'additional_documents_requested'),
        (SELECT id FROM application_statuses WHERE name = 'processing'),
        (SELECT id FROM application_statuses WHERE name = 'on_hold'),
        (SELECT id FROM application_statuses WHERE name = 'approved'),
        (SELECT id FROM application_statuses WHERE name = 'completed'),
        (SELECT id FROM application_statuses WHERE name = 'rejected'),
        (SELECT id FROM application_statuses WHERE name = 'cancelled')
    ),
    a.updated_at DESC;
