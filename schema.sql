DROP DATABASE IF EXISTS it_helpdesk;
CREATE DATABASE it_helpdesk;
USE it_helpdesk;

-- 1. Unified Users Profile Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('Requester', 'Agent', 'Admin') DEFAULT 'Requester',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Complete Enterprise Tickets Table
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL DEFAULT NULL,
    status ENUM('Open', 'Assigned', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Open',
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Low',
    created_by INT NOT NULL,
    assigned_to INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Populate system seed user instances with a safe, temporary default password hash
-- (The default hash below corresponds to the password: 'password123')
INSERT INTO users (id, name, email, role, password_hash) VALUES 
(1, 'Kamal Perera', 'kamal@company.com', 'Requester', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Admin User', 'admin@company.com', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(3, 'IT Specialist', 'agent@company.com', 'Agent', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Quick check query to ensure everything built beautifully
SELECT id, name, email, role FROM users;

CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent', 'failed') DEFAULT 'sent',
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);