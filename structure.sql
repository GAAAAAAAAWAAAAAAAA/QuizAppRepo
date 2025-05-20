CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_guest BOOLEAN DEFAULT FALSE
);

-- Projects table to store quiz projects
CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_name VARCHAR(100) NOT NULL,
    project_code VARCHAR(20) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Question types table
CREATE TABLE question_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL,
    description TEXT
);

-- Insert default question types
INSERT INTO question_types (type_name, description) VALUES
    ('identification', 'Text input answer format'),
    ('multiple_choice', 'Multiple options with one correct answer'),
    ('true_false', 'True or False options');

-- Flash cards table to store individual questions
CREATE TABLE flashcards (
    flashcard_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    type_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (type_id) REFERENCES question_types(type_id)
);

-- Multiple choice options table
CREATE TABLE multiple_choice_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    flashcard_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    option_label CHAR(1) NOT NULL,
    FOREIGN KEY (flashcard_id) REFERENCES flashcards(flashcard_id) ON DELETE CASCADE
);

-- Project sharing permissions
CREATE TABLE project_sharing (
    sharing_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    permission_type ENUM('view_only', 'use_and_review', 'edit') NOT NULL DEFAULT 'view_only',
    is_locked BOOLEAN DEFAULT FALSE,
    start_time DATETIME,
    end_time DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- Shared projects (tracking which users have access to shared projects)
CREATE TABLE shared_projects (
    shared_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_shared_project (project_id, user_id)
);

-- Quiz settings table
CREATE TABLE quiz_settings (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    has_points BOOLEAN DEFAULT TRUE,
    has_timer BOOLEAN DEFAULT TRUE,
    time_limit_minutes INT DEFAULT NULL,
    points_per_question INT DEFAULT 10,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- Quiz attempts table to track user attempts
CREATE TABLE quiz_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    score INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- User answers table to store individual answers for review
CREATE TABLE user_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    flashcard_id INT NOT NULL,
    user_answer TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (flashcard_id) REFERENCES flashcards(flashcard_id) ON DELETE CASCADE
);

-- AI generated content table
CREATE TABLE ai_generated_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prompt TEXT NOT NULL,
    generated_content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_saved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
