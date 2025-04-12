 -- Create the database
CREATE DATABASE IF NOT EXISTS examonline_db;
USE examonline_db;

-- Create the 'filieres' table (departments/majors)
CREATE TABLE IF NOT EXISTS filieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create the 'student_groups' table
CREATE TABLE IF NOT EXISTS student_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    filiere_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

-- Create the 'users' table with proper foreign keys
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    filiere_id INT,
    group_id INT,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES student_groups(id) ON DELETE SET NULL
);


-- Create the 'exams' table
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    duration INT NOT NULL COMMENT 'Duration in minutes',
    teacher_id INT,
    filiere_id INT,
    group_id INT,
    status ENUM('draft', 'published', 'in_progress', 'completed', 'archived') NOT NULL DEFAULT 'draft',
    is_filiere_wide BOOLEAN DEFAULT FALSE COMMENT 'If true, applies to all groups in filiere',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES student_groups(id) ON DELETE SET NULL
);

-- Create the 'questions' table
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq', 'open', 'true_false') NOT NULL DEFAULT 'mcq',
    points DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    image_path VARCHAR(255),
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Create the 'question_options' table (for MCQ)
CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    option_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Create the 'correct_answers' table (for open questions)
CREATE TABLE IF NOT EXISTS correct_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Create the 'exam_attempts' table
CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    submit_time DATETIME,
    is_completed BOOLEAN DEFAULT FALSE,
    total_score DECIMAL(5,2) DEFAULT NULL,
    graded_by INT,
    graded_at DATETIME,
    status ENUM('in_progress', 'submitted', 'graded') DEFAULT 'in_progress',
    final_score DECIMAL(5,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_exam (student_id, exam_id)
);


CREATE TABLE IF NOT EXISTS student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT,
    answer_text TEXT,
    points_earned DECIMAL(5,2) DEFAULT 0,
    feedback TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE SET NULL
);


-- Create the 'exam_results' table
CREATE TABLE IF NOT EXISTS exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    score DECIMAL(5,2) DEFAULT NULL,
    submitted_at DATETIME NOT NULL,
    corrected_at DATETIME DEFAULT NULL,
    status ENUM('pending', 'complete') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_exam (student_id, exam_id)
);

-- Create the 'notifications' table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('exam_published', 'exam_reminder', 'grade_available', 'account_status', 'system') NOT NULL,
    reference_id INT COMMENT 'Can refer to exam_id, attempt_id, etc.',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create the 'admin_roles' table
CREATE TABLE IF NOT EXISTS admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Conditional index creation to avoid duplicates
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @indexname = 'idx_users_filiere';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = @dbname
    AND table_name = @tablename
    AND index_name = @indexname
  ) > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(filiere_id)')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Repeat for all other indexes
SET @indexname = 'idx_users_group';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = @dbname
    AND table_name = @tablename
    AND index_name = @indexname
  ) > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(group_id)')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Continue with other tables and indexes following the same pattern...

-- Sample data insertion with IGNORE to prevent duplicates
INSERT IGNORE INTO filieres (name, description) VALUES 
('Développement Digital', 'Digital development and web programming'),
('Gestion Entreprise', 'Business management and administration');

INSERT IGNORE INTO student_groups (name, description, filiere_id) VALUES 
('Group 1', 'First year development students', 1),
('Group 2', 'Second year development students', 1),
('Group 3', 'First year business students', 2);

INSERT IGNORE INTO users (first_name, last_name, email, password, user_type, filiere_id, group_id, status) VALUES
('Ayoub', 'Baya', 'baya@gmail.com', 'baya@gmail.com', 'student', 1, 1, 'accepted'),
('Mehdi', 'Azou', 'azou@gmail.com', 'azou@gmail.com', 'teacher', 2, NULL, 'accepted'),
('Admin', 'User', 'admin@gmail.com', 'admin@gmail.com', 'admin', NULL, NULL, 'accepted');

INSERT IGNORE INTO admin_roles (id, role_name) VALUES (123, 'admin');

INSERT IGNORE INTO exams (title, description, start_date, end_date, duration, teacher_id, filiere_id, group_id, status) VALUES
('Final Exam', 'End of term comprehensive exam', NOW(), DATE_ADD(NOW(), INTERVAL 1 WEEK), 120, 2, 1, 1, 'published');

-- Add type column to questions and sync with question_type
ALTER TABLE questions ADD COLUMN type ENUM('mcq', 'open', 'true_false') AFTER question_type;
UPDATE questions SET type = question_type WHERE type IS NULL;

-- Create triggers to keep question_type and type in sync
DELIMITER //
DROP TRIGGER IF EXISTS sync_question_type_insert//
CREATE TRIGGER sync_question_type_insert BEFORE INSERT ON questions
FOR EACH ROW
BEGIN
    IF NEW.type IS NOT NULL AND NEW.question_type IS NULL THEN
        SET NEW.question_type = NEW.type;
    ELSEIF NEW.question_type IS NOT NULL AND NEW.type IS NULL THEN
        SET NEW.type = NEW.question_type;
    END IF;
END//

DROP TRIGGER IF EXISTS sync_question_type_update//
CREATE TRIGGER sync_question_type_update BEFORE UPDATE ON questions
FOR EACH ROW
BEGIN
    IF NEW.type != OLD.type THEN
        SET NEW.question_type = NEW.type;
    ELSEIF NEW.question_type != OLD.question_type THEN
        SET NEW.type = NEW.question_type;
    END IF;
END//
DELIMITER ;



-- First, ensure prerequisite tables exist
CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    attempt_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_score DECIMAL(5,2) DEFAULT 0,
    status ENUM('started', 'completed', 'abandoned') DEFAULT 'started'
);

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'short_answer', 'true_false') NOT NULL,
    total_points DECIMAL(5,2) DEFAULT 1.0,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium'
);

CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);