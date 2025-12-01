-- TaskMesh - Admin Email Preferences & Email Templates Schema
-- Run this SQL to add the new tables

USE taskmesh_db;

-- =============================================
-- Admin/User Email Preferences Table
-- =============================================
-- Αποθηκεύει τις προτιμήσεις email για κάθε χρήστη (κυρίως Admin/Manager)
CREATE TABLE IF NOT EXISTS user_email_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Notification Types (τι θέλει να λαμβάνει)
    notify_task_assigned BOOLEAN DEFAULT TRUE,
    notify_task_completed BOOLEAN DEFAULT TRUE,
    notify_subtask_created BOOLEAN DEFAULT TRUE,
    notify_subtask_completed BOOLEAN DEFAULT TRUE,
    notify_comment_added BOOLEAN DEFAULT TRUE,
    notify_deadline_reminder BOOLEAN DEFAULT TRUE,
    notify_team_invitation BOOLEAN DEFAULT TRUE,
    notify_direct_message BOOLEAN DEFAULT TRUE,
    
    -- Team Filters (από ποιες ομάδες)
    -- 'all' = από όλες τις ομάδες
    -- comma-separated team IDs = μόνο από συγκεκριμένες ομάδες
    team_filter VARCHAR(500) DEFAULT 'all',
    
    -- Extra options
    email_digest ENUM('instant', 'daily', 'weekly', 'none') DEFAULT 'instant',
    quiet_hours_start TIME DEFAULT NULL,
    quiet_hours_end TIME DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Email Templates Table
-- =============================================
-- Αποθηκεύει customizable email templates
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Template Type
    template_type ENUM(
        'task_assigned',
        'task_completed',
        'subtask_completed',
        'comment_added',
        'deadline_reminder',
        'team_invitation',
        'direct_message',
        'test_email'
    ) NOT NULL,
    
    -- Template Name (for UI display)
    name VARCHAR(100) NOT NULL,
    
    -- Template Subject
    subject VARCHAR(255) NOT NULL,
    
    -- Design Settings
    header_gradient_start VARCHAR(7) DEFAULT '#667eea',
    header_gradient_end VARCHAR(7) DEFAULT '#764ba2',
    header_icon VARCHAR(50) DEFAULT '📧',
    
    button_color VARCHAR(7) DEFAULT '#667eea',
    button_text_color VARCHAR(7) DEFAULT '#ffffff',
    
    footer_gradient_start VARCHAR(7) DEFAULT '#1e293b',
    footer_gradient_end VARCHAR(7) DEFAULT '#334155',
    
    -- Content Template (HTML with placeholders)
    -- Available placeholders: {{user_name}}, {{task_title}}, {{team_name}}, 
    -- {{deadline}}, {{message}}, {{sender_name}}, {{action_url}}, {{button_text}}
    content_template TEXT NOT NULL,
    
    -- Preview text (for email clients)
    preview_text VARCHAR(255) DEFAULT '',
    
    -- Is this the active template for this type?
    is_active BOOLEAN DEFAULT FALSE,
    
    -- Is this a default (system) template?
    is_default BOOLEAN DEFAULT FALSE,
    
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (template_type),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_active_template (template_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Insert Default Email Preferences for Existing Admin
-- =============================================
INSERT INTO user_email_preferences (user_id, notify_task_assigned, notify_task_completed, notify_subtask_created, notify_subtask_completed, notify_comment_added, notify_deadline_reminder, notify_team_invitation, notify_direct_message, team_filter)
SELECT id, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, 'all'
FROM users WHERE role = 'ADMIN'
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =============================================
-- Insert Default Email Templates
-- =============================================

-- Task Assigned Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('task_assigned', 'Task Assignment (Default)', '📋 Νέα ανάθεση εργασίας: {{task_title}}', '📋', 
'<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Γεια σου <strong>{{user_name}}</strong>,
</p>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Σου ανατέθηκε μια νέα εργασία στην ομάδα <strong>{{team_name}}</strong>:
</p>
<div style="background: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #667eea;">
    <h3 style="color: #1e293b; margin: 0 0 10px 0;">{{task_title}}</h3>
    <p style="color: #64748b; margin: 0;">Deadline: <strong>{{deadline}}</strong></p>
</div>',
TRUE, TRUE);

-- Task Completed Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('task_completed', 'Task Completion (Default)', '✅ Ολοκληρώθηκε εργασία: {{task_title}}', '✅',
'<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Γεια σου <strong>{{user_name}}</strong>,
</p>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Η εργασία <strong>{{task_title}}</strong> ολοκληρώθηκε επιτυχώς!
</p>
<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; padding: 20px; margin-bottom: 20px; text-align: center;">
    <span style="font-size: 48px;">🎉</span>
    <p style="color: #ffffff; font-size: 18px; margin: 10px 0 0 0;">Εργασία Ολοκληρώθηκε!</p>
</div>',
TRUE, TRUE);

-- Subtask Completed Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('subtask_completed', 'Subtask Completion (Default)', '☑️ Ολοκληρώθηκε υποεργασία στο: {{task_title}}', '☑️',
'<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Γεια σου <strong>{{user_name}}</strong>,
</p>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Μια υποεργασία ολοκληρώθηκε στην εργασία <strong>{{task_title}}</strong>.
</p>
<div style="background: #f0fdf4; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #22c55e;">
    <p style="color: #166534; margin: 0;"><i class="fas fa-check-circle"></i> {{subtask_title}}</p>
</div>',
TRUE, TRUE);

-- Comment Added Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('comment_added', 'New Comment (Default)', '💬 Νέο σχόλιο στο: {{task_title}}', '💬',
'<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Γεια σου <strong>{{user_name}}</strong>,
</p>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Ο/Η <strong>{{sender_name}}</strong> σχολίασε στην εργασία <strong>{{task_title}}</strong>:
</p>
<div style="background: #eff6ff; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #3b82f6;">
    <p style="color: #1e40af; margin: 0; font-style: italic;">"{{message}}"</p>
</div>',
TRUE, TRUE);

-- Deadline Reminder Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('deadline_reminder', 'Deadline Reminder (Default)', '⏰ Υπενθύμιση: Deadline πλησιάζει για {{task_title}}', '⏰',
'<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Γεια σου <strong>{{user_name}}</strong>,
</p>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Το deadline για την εργασία <strong>{{task_title}}</strong> πλησιάζει!
</p>
<div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 12px; padding: 20px; margin-bottom: 20px; text-align: center;">
    <span style="font-size: 48px;">⚠️</span>
    <p style="color: #ffffff; font-size: 18px; margin: 10px 0 0 0;">Deadline: <strong>{{deadline}}</strong></p>
</div>',
TRUE, TRUE);

-- Team Invitation Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('team_invitation', 'Team Invitation (Default)', '👥 Πρόσκληση στην ομάδα: {{team_name}}', '👥',
'<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Γεια σου <strong>{{user_name}}</strong>,
</p>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Προστέθηκες στην ομάδα <strong>{{team_name}}</strong>!
</p>
<div style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); border-radius: 12px; padding: 20px; margin-bottom: 20px; text-align: center;">
    <span style="font-size: 48px;">🎊</span>
    <p style="color: #ffffff; font-size: 18px; margin: 10px 0 0 0;">Καλωσήρθες!</p>
</div>',
TRUE, TRUE);

-- Direct Message Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('direct_message', 'Direct Message (Default)', '📨 Νέο μήνυμα από {{sender_name}}', '📨',
'<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Γεια σου <strong>{{user_name}}</strong>,
</p>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Έλαβες νέο μήνυμα από τον/την <strong>{{sender_name}}</strong>:
</p>
<div style="background: #faf5ff; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #a855f7;">
    <p style="color: #6b21a8; margin: 0;">"{{message}}"</p>
</div>',
TRUE, TRUE);

-- Test Email Template
INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default) VALUES
('test_email', 'Test Email (Default)', '✅ TaskMesh Email Test - {{date}}', '✅',
'<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 30px;">
    <div style="font-size: 32px; margin-bottom: 10px;">🎉</div>
    <h2 style="color: #ffffff; margin: 0 0 10px 0; font-size: 20px;">Επιτυχής Αποστολή!</h2>
    <p style="color: rgba(255,255,255,0.95); margin: 0; font-size: 15px;">
        Αν βλέπεις αυτό το μήνυμα, όλα λειτουργούν τέλεια!
    </p>
</div>
<p style="color: #374151; font-size: 16px; margin-bottom: 20px;">
    Οι ρυθμίσεις email λειτουργούν σωστά. Μπορείς τώρα να ενεργοποιήσεις τις ειδοποιήσεις.
</p>',
TRUE, TRUE);
