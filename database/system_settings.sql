-- System Settings Table
-- Stores global application configuration

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES
-- Security Settings
('password_min_length', '8', 'integer', 'security', 'Minimum password length'),
('password_require_uppercase', 'true', 'boolean', 'security', 'Require uppercase letters in password'),
('password_require_lowercase', 'true', 'boolean', 'security', 'Require lowercase letters in password'),
('password_require_numbers', 'true', 'boolean', 'security', 'Require numbers in password'),
('password_require_special', 'false', 'boolean', 'security', 'Require special characters in password'),
('session_timeout', '1440', 'integer', 'security', 'Session timeout in minutes (default 24 hours)'),
('max_login_attempts', '5', 'integer', 'security', 'Maximum failed login attempts before lockout'),
('lockout_duration', '15', 'integer', 'security', 'Account lockout duration in minutes'),

-- Appearance & Localization
('date_format', 'DD/MM/YYYY', 'string', 'appearance', 'Date display format'),
('time_format', '24h', 'string', 'appearance', 'Time format (12h or 24h)'),
('timezone', 'Europe/Athens', 'string', 'appearance', 'Default timezone'),
('language', 'el', 'string', 'appearance', 'Default language (el=Greek, en=English)'),
('logo_path', NULL, 'string', 'appearance', 'Path to uploaded logo'),
('primary_color', '#667eea', 'string', 'appearance', 'Primary brand color'),

-- System Configuration
('installation_path', '', 'string', 'system', 'Application installation path (auto-detected, e.g., /task or empty for root)'),
('task_auto_archive_days', '30', 'integer', 'system', 'Auto-archive completed tasks after X days'),
('registration_mode', 'open', 'string', 'system', 'User registration mode (open/invite/disabled)'),
('default_user_role', 'USER', 'string', 'system', 'Default role for new users'),
('max_team_size', '50', 'integer', 'system', 'Maximum members per team'),
('max_file_upload_mb', '10', 'integer', 'system', 'Maximum file upload size in MB'),
('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt', 'string', 'system', 'Allowed file upload types'),

-- Notifications
('websocket_enabled', 'true', 'boolean', 'notifications', 'Enable WebSocket real-time notifications'),
('websocket_port', '8080', 'integer', 'notifications', 'WebSocket server port'),
('notification_email', 'true', 'boolean', 'notifications', 'Enable email notifications'),
('notification_inapp', 'true', 'boolean', 'notifications', 'Enable in-app notifications'),
('notification_push', 'false', 'boolean', 'notifications', 'Enable browser push notifications'),
('notification_batch_mode', 'immediate', 'string', 'notifications', 'Notification batching (immediate/5min/15min/hourly)'),
('quiet_hours_start', NULL, 'string', 'notifications', 'Quiet hours start time (HH:MM)'),
('quiet_hours_end', NULL, 'string', 'notifications', 'Quiet hours end time (HH:MM)'),

-- Maintenance
('maintenance_mode', 'false', 'boolean', 'maintenance', 'Enable maintenance mode'),
('maintenance_message', 'System is under maintenance. Please check back soon.', 'string', 'maintenance', 'Maintenance mode message'),
('last_backup', NULL, 'string', 'maintenance', 'Last backup timestamp'),
('last_optimization', NULL, 'string', 'maintenance', 'Last database optimization timestamp'),
('last_cache_clear', NULL, 'string', 'maintenance', 'Last cache clear timestamp'),
('last_restore', NULL, 'string', 'maintenance', 'Last restore timestamp'),
('auto_backup_enabled', 'false', 'boolean', 'maintenance', 'Enable automatic backups'),
('backup_frequency', 'daily', 'string', 'maintenance', 'Backup frequency (daily/weekly/monthly)'),

-- Data Retention Policies
('archive_completed_tasks_days', '90', 'integer', 'retention', 'Auto-archive completed tasks after X days (0=disabled)'),
('delete_archived_tasks_days', '365', 'integer', 'retention', 'Permanently delete archived tasks after X days (0=disabled)'),
('delete_inactive_users_months', '12', 'integer', 'retention', 'Delete inactive users after X months (0=disabled)'),
('clean_old_notifications_days', '30', 'integer', 'retention', 'Clean old notifications after X days (0=disabled)'),
('clean_old_comments_days', '0', 'integer', 'retention', 'Clean old comments after X days (0=disabled)'),
('last_retention_cleanup', NULL, 'string', 'retention', 'Last retention cleanup timestamp');
