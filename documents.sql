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
