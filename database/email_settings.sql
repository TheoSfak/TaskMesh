-- Add email_settings table to TaskMesh database
USE taskmesh_db;

-- Add email_settings table
CREATE TABLE IF NOT EXISTS email_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(500) NOT NULL,
    smtp_from_email VARCHAR(255) NOT NULL,
    smtp_from_name VARCHAR(255) NOT NULL DEFAULT 'TaskMesh Notifications',
    smtp_encryption ENUM('tls', 'ssl') NOT NULL DEFAULT 'tls',
    notifications_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    app_base_url VARCHAR(500) NOT NULL DEFAULT 'http://localhost/TaskMesh',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings (admin must configure)
INSERT INTO email_settings (smtp_host, smtp_port, smtp_username, smtp_password, smtp_from_email, smtp_from_name, smtp_encryption, notifications_enabled, app_base_url) 
VALUES ('smtp.gmail.com', 587, '', '', '', 'TaskMesh Notifications', 'tls', FALSE, 'http://localhost/TaskMesh');
