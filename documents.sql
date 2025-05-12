-- Document categories for organization
CREATE TABLE `document_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert common document categories
INSERT INTO `document_categories` (`name`, `description`) VALUES
('Identity', 'Identity documents like passport, ID card'),
('Education', 'Educational certificates and transcripts'),
('Employment', 'Employment proof and work history'),
('Financial', 'Bank statements and financial documents'),
('Immigration', 'Previous visas and immigration history'),
('Medical', 'Medical certificates and health records'),
('Supporting', 'Supporting documents like cover letters, photos');

-- Document types master table
CREATE TABLE `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_document_category` (`category_id`),
  CONSTRAINT `document_types_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert common document types
INSERT INTO `document_types` (`category_id`, `name`, `description`) VALUES
(1, 'Passport', 'Valid passport with at least 6 months validity'),
(1, 'National ID Card', 'Government-issued national identification card'),
(2, 'Degree Certificate', 'University or college degree certificate'),
(2, 'Transcripts', 'Academic transcripts and mark sheets'),
(3, 'Employment Contract', 'Current employment contract'),
(3, 'Experience Letter', 'Work experience letter from employer'),
(4, 'Bank Statement', 'Bank statement for the last 6 months'),
(4, 'Income Tax Returns', 'Income tax returns for the last 3 years'),
(5, 'Previous Visa', 'Copy of previous visas'),
(6, 'Medical Certificate', 'Medical fitness certificate'),
(7, 'Photographs', 'Passport-sized photographs');

-- Document Templates table
CREATE TABLE `document_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `document_type_id` (`document_type_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `templates_document_type_id_fk` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `templates_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert a sample document template for testing
INSERT INTO `document_templates` (`name`, `document_type_id`, `content`, `is_active`, `created_by`) VALUES
('Service Agreement Template', 3, '<h1>Service Agreement</h1>
<p>This Service Agreement (the "Agreement") is made and entered into on {current_date} by and between:</p>
<p><strong>Visafy</strong>, a visa and immigration services company, and</p>
<p><strong>{client_name}</strong> (the "Client") with email address {client_email}.</p>
<h2>1. Services</h2>
<p>Visafy agrees to provide the following services to the Client:</p>
<ul>
<li>Consultation regarding visa application requirements</li>
<li>Assistance with documentation preparation</li>
<li>Application form review and submission guidance</li>
<li>Follow-up support during the visa processing period</li>
</ul>
<h2>2. Client Responsibilities</h2>
<p>The Client agrees to:</p>
<ul>
<li>Provide accurate and truthful information</li>
<li>Submit all required documents in a timely manner</li>
<li>Attend scheduled appointments</li>
<li>Pay all applicable fees as agreed</li>
</ul>
<h2>3. Fees and Payment</h2>
<p>The Client agrees to pay the fees as outlined in the fee schedule provided separately.</p>
<h2>4. Confidentiality</h2>
<p>Visafy agrees to maintain the confidentiality of all Client information and documents.</p>
<h2>5. Termination</h2>
<p>Either party may terminate this Agreement with written notice to the other party.</p>
<h2>6. Acceptance</h2>
<p>By engaging Visafy services, the Client acknowledges that they have read, understood, and agreed to the terms of this Agreement.</p>
<p>Signed and accepted on {current_date}</p>
<p>______________________<br>Visafy Representative</p>
<p>______________________<br>{client_name}<br>Client</p>', 1, 1);

-- Generated Documents table
CREATE TABLE `generated_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `generated_date` datetime NOT NULL,
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_type_id` (`document_type_id`),
  KEY `template_id` (`template_id`),
  KEY `client_id` (`client_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `generated_document_type_id_fk` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `generated_template_id_fk` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `generated_client_id_fk` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `generated_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `template_type` enum('general','welcome','password_reset','booking_confirmation','booking_reminder','booking_cancellation','application_status','document_request','document_approval','document_rejection','marketing','newsletter') NOT NULL DEFAULT 'general',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `template_type` (`template_type`),
  CONSTRAINT `email_templates_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create email queue table if it doesn't exist
CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_id` int(11) DEFAULT NULL COMMENT 'User ID if recipient exists in system',
  `recipient_email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `status` enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `scheduled_time` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `scheduled_time` (`scheduled_time`),
  CONSTRAINT `email_queue_recipient_id_fk` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_queue_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create received emails table if it doesn't exist
CREATE TABLE IF NOT EXISTS `received_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) DEFAULT NULL COMMENT 'User ID if sender exists in system',
  `sender_email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `received_at` datetime NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_by` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `read_by` (`read_by`),
  CONSTRAINT `received_emails_sender_id_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `received_emails_read_by_fk` FOREIGN KEY (`read_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create email automation settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS `email_automation_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default email automation settings if they don't exist
INSERT IGNORE INTO `email_automation_settings` (`setting_key`, `setting_value`) VALUES
('booking_confirmation_enabled', '1'),
('booking_reminder_enabled', '1'),
('booking_reminder_hours', '24'),
('booking_cancellation_enabled', '1'),
('application_status_enabled', '1'),
('document_request_enabled', '1'),
('document_review_enabled', '1'),
('welcome_email_enabled', '1'),
('password_reset_enabled', '1');
