-- Questions table
CREATE TABLE `decision_tree_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_text` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Options/Answers table
CREATE TABLE `decision_tree_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `next_question_id` int(11) DEFAULT NULL COMMENT 'Next question to show if this option is selected, NULL if endpoint',
  `is_endpoint` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'If true, selecting this option ends the assessment',
  `endpoint_result` text DEFAULT NULL COMMENT 'Result to show if this is an endpoint',
  `endpoint_eligible` tinyint(1) DEFAULT NULL COMMENT 'Whether this endpoint indicates eligibility',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  KEY `next_question_id` (`next_question_id`),
  CONSTRAINT `option_question_id_fk` FOREIGN KEY (`question_id`) REFERENCES `decision_tree_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `option_next_question_id_fk` FOREIGN KEY (`next_question_id`) REFERENCES `decision_tree_questions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User assessments table
CREATE TABLE `user_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL DEFAULT current_timestamp(),
  `end_time` datetime DEFAULT NULL,
  `is_complete` tinyint(1) NOT NULL DEFAULT 0,
  `result_eligible` tinyint(1) DEFAULT NULL,
  `result_text` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `assessment_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User assessment answers table
CREATE TABLE `user_assessment_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `answer_time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assessment_id` (`assessment_id`),
  KEY `question_id` (`question_id`),
  KEY `option_id` (`option_id`),
  CONSTRAINT `answer_assessment_id_fk` FOREIGN KEY (`assessment_id`) REFERENCES `user_assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `answer_question_id_fk` FOREIGN KEY (`question_id`) REFERENCES `decision_tree_questions` (`id`),
  CONSTRAINT `answer_option_id_fk` FOREIGN KEY (`option_id`) REFERENCES `decision_tree_options` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Categories for questions (optional)
CREATE TABLE `decision_tree_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add category_id to decision_tree_questions
ALTER TABLE `decision_tree_questions` 
ADD COLUMN `category_id` int(11) DEFAULT NULL,
ADD CONSTRAINT `question_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `decision_tree_categories` (`id`) ON DELETE SET NULL;
