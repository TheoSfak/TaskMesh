-- Fix Email Templates with proper UTF-8 encoding
-- Run with: mysql -u root taskmesh_db < fix_templates.sql

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Task Assigned Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('task_assigned', 'Task Assignment', 'Νέα Ανάθεση Εργασίας: {{task_title}}', '#667eea', '#764ba2', '📋', '#667eea', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>,</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Σου ανατέθηκε μια νέα εργασία από την ομάδα <strong style="color: #374151;">{{team_name}}</strong>:</p>
<div style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 20px; border-left: 5px solid #7c3aed;">
    <h2 style="color: #5b21b6; margin: 0 0 10px 0; font-size: 19px; font-weight: 600;">{{task_title}}</h2>
    <p style="color: #6b7280; margin: 0; font-size: 14px;">Προθεσμία: <strong>{{deadline}}</strong></p>
</div>', 1, 1);

-- Task Completed Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('task_completed', 'Task Completion', 'Ολοκληρώθηκε Εργασία: {{task_title}}', '#10b981', '#059669', '✅', '#10b981', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>,</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Η παρακάτω εργασία ολοκληρώθηκε με επιτυχία:</p>
<div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 20px; border-left: 5px solid #10b981;">
    <div style="display: flex; align-items: center;">
        <span style="font-size: 24px; margin-right: 12px;">✓</span>
        <h2 style="color: #065f46; margin: 0; font-size: 19px; font-weight: 600;">{{task_title}}</h2>
    </div>
</div>', 1, 1);

-- Subtask Completed Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('subtask_completed', 'Subtask Completion', 'Ολοκληρώθηκε Υποεργασία: {{subtask_title}}', '#10b981', '#059669', '☑️', '#10b981', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>,</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Μια υποεργασία ολοκληρώθηκε:</p>
<div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 15px; border-left: 5px solid #10b981;">
    <p style="color: #6b7280; font-size: 13px; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px;">Subtask</p>
    <h2 style="color: #065f46; margin: 0; font-size: 18px; font-weight: 600;">{{subtask_title}}</h2>
</div>
<p style="color: #9ca3af; font-size: 14px; margin: 0;">Στο task: <strong style="color: #6b7280;">{{task_title}}</strong></p>', 1, 1);

-- Comment Added Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('comment_added', 'New Comment', 'Νέο Σχόλιο στο: {{task_title}}', '#f59e0b', '#d97706', '💬', '#f59e0b', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>,</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Ο/Η <strong style="color: #374151;">{{sender_name}}</strong> σχολίασε στο task:</p>
<p style="color: #6b7280; font-size: 14px; margin: 0 0 15px 0;"><strong style="color: #374151;">{{task_title}}</strong></p>
<div style="background: #fffbeb; border-radius: 12px; padding: 20px 25px; margin-bottom: 10px; border-left: 5px solid #f59e0b;">
    <p style="color: #92400e; margin: 0; font-size: 15px; font-style: italic; line-height: 1.6;">"{{message}}"</p>
</div>', 1, 1);

-- Deadline Reminder Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('deadline_reminder', 'Deadline Reminder', 'Υπενθύμιση: Προθεσμία πλησιάζει για {{task_title}}', '#ef4444', '#dc2626', '⏰', '#ef4444', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>,</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">⚠️ Προσοχή! Η προθεσμία πλησιάζει:</p>
<div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 20px; border-left: 5px solid #ef4444;">
    <h2 style="color: #991b1b; margin: 0 0 15px 0; font-size: 19px; font-weight: 600;">{{task_title}}</h2>
    <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 18px;">⏰</span>
        <span style="color: #991b1b; font-size: 15px; font-weight: 600;">Προθεσμία: {{deadline}}</span>
    </div>
</div>', 1, 1);

-- Team Invitation Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('team_invitation', 'Team Invitation', 'Πρόσκληση στην ομάδα: {{team_name}}', '#8b5cf6', '#7c3aed', '👥', '#8b5cf6', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>,</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Ο/Η <strong style="color: #374151;">{{sender_name}}</strong> σε προσκάλεσε στην ομάδα:</p>
<div style="background: linear-gradient(135deg, #ede9fe 0%, #e9d5ff 100%); border-radius: 12px; padding: 25px; margin-bottom: 10px; border-left: 5px solid #8b5cf6; text-align: center;">
    <h2 style="color: #5b21b6; margin: 0 0 12px 0; font-size: 22px; font-weight: 700;">{{team_name}}</h2>
</div>', 1, 1);

-- Direct Message Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('direct_message', 'Direct Message', 'Νέο μήνυμα από {{sender_name}}', '#3b82f6', '#2563eb', '✉️', '#3b82f6', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>,</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Έλαβες νέο μήνυμα από τον/την <strong style="color: #374151;">{{sender_name}}</strong>:</p>
<div style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 10px; border-left: 5px solid #3b82f6;">
    <p style="color: #1e40af; margin: 0; font-size: 15px; line-height: 1.6;">"{{message}}"</p>
</div>', 1, 1);

-- Test Email Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('test_email', 'Test Email', 'TaskMesh Test Email - {{date}}', '#667eea', '#764ba2', '🧪', '#667eea', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου!</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Αυτό είναι ένα δοκιμαστικό email από το TaskMesh. Αν το βλέπεις, οι ρυθμίσεις SMTP λειτουργούν σωστά!</p>
<div style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 20px; border-left: 5px solid #7c3aed; text-align: center;">
    <span style="font-size: 48px;">✅</span>
    <h2 style="color: #5b21b6; margin: 10px 0 0 0; font-size: 18px;">Email Configuration Working!</h2>
</div>', 1, 1);

-- Welcome Email Template
INSERT INTO email_templates (template_type, name, subject, header_gradient_start, header_gradient_end, header_icon, button_color, button_text_color, content_template, is_active, is_default) VALUES
('welcome', 'Welcome Email', 'Καλώς ήρθες στο TaskMesh!', '#667eea', '#764ba2', '🎉', '#667eea', '#ffffff', '<p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεια σου <strong style="color: #111827;">{{user_name}}</strong>!</p>
<p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">Καλώς ήρθες στο TaskMesh! Είμαστε ενθουσιασμένοι που είσαι μαζί μας.</p>
<div style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border-radius: 12px; padding: 25px; margin-bottom: 20px; border-left: 5px solid #7c3aed; text-align: center;">
    <span style="font-size: 48px;">🚀</span>
    <h2 style="color: #5b21b6; margin: 10px 0 0 0; font-size: 18px;">Ξεκίνα τώρα!</h2>
</div>', 1, 1);
