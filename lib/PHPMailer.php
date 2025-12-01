<?php
// Email Service - Uses PHPMailer with SMTP settings from database
// Beautiful email templates with gradient designs
// Supports custom templates from email_templates table

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    
    // Template type constants matching email_templates table
    const TEMPLATE_TYPES = [
        'task_assigned' => 'task_assigned',
        'task_completed' => 'task_completed',
        'subtask_completed' => 'subtask_completed',
        'comment_added' => 'comment_added',
        'team_invitation' => 'team_invitation',
        'direct_message' => 'direct_message',
        'deadline_reminder' => 'deadline_reminder',
        'welcome' => 'welcome'
    ];
    
    // Auto-detect base URL from server or fallback to database/default
    private static function getBaseUrl() {
        // Try to get from current request (when called from web)
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            
            // Extract base path (e.g., /TaskMesh from /TaskMesh/api/tasks/index.php)
            $uri = $_SERVER['REQUEST_URI'];
            if (preg_match('#^(/[^/]+)#', $uri, $matches)) {
                return $protocol . '://' . $host . $matches[1];
            }
            return $protocol . '://' . $host . '/TaskMesh';
        }
        
        // Fallback: try to get from database (for cron jobs)
        try {
            $database = new Database();
            $db = $database->getConnection();
            $query = "SELECT app_base_url FROM email_settings ORDER BY id DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['app_base_url'])) {
                return $result['app_base_url'];
            }
        } catch (Exception $e) {
            // Ignore
        }
        
        // Final fallback
        return 'http://localhost/TaskMesh';
    }
    
    // Get custom template from database
    private static function getTemplateFromDB($type) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT * FROM email_templates WHERE template_type = :type AND is_active = 1 LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':type', $type);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return null;
        } catch (Exception $e) {
            error_log("Error loading email template: " . $e->getMessage());
            return null;
        }
    }
    
    // Check if user wants to receive this type of email
    private static function shouldSendEmail($userId, $notificationType) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT * FROM user_email_preferences WHERE user_id = :user_id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Map notification type to preference column
                $columnMap = [
                    'task_assigned' => 'notify_task_assigned',
                    'task_completed' => 'notify_task_completed',
                    'subtask_completed' => 'notify_subtask_completed',
                    'comment_added' => 'notify_comments',
                    'team_invitation' => 'notify_team_invitation',
                    'direct_message' => 'notify_messages',
                    'deadline_reminder' => 'notify_deadline_reminder'
                ];
                
                if (isset($columnMap[$notificationType]) && isset($prefs[$columnMap[$notificationType]])) {
                    return (bool)$prefs[$columnMap[$notificationType]];
                }
            }
            
            // Default: send email if no preferences found
            return true;
        } catch (Exception $e) {
            error_log("Error checking email preferences: " . $e->getMessage());
            return true; // Default to sending
        }
    }
    
    // Get user ID by email
    private static function getUserIdByEmail($email) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                return $user['id'];
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    // Build email body using template from DB or defaults
    private static function buildEmailBody($type, $defaultIcon, $defaultTitle, $defaultSubtitle, $defaultGradientStart, $defaultGradientEnd, $content, $buttonText, $buttonUrl) {
        // Try to load custom template from database
        $template = self::getTemplateFromDB($type);
        
        if ($template) {
            // Use custom template values
            $icon = !empty($template['header_icon']) ? $template['header_icon'] : $defaultIcon;
            $gradientStart = !empty($template['header_gradient_start']) ? $template['header_gradient_start'] : $defaultGradientStart;
            $gradientEnd = !empty($template['header_gradient_end']) ? $template['header_gradient_end'] : $defaultGradientEnd;
            $buttonColor = !empty($template['button_color']) ? $template['button_color'] : $defaultGradientStart;
            
            // Build custom email body with template colors
            return self::getEmailTemplateWithColors(
                $icon,
                $defaultTitle,
                $defaultSubtitle,
                $gradientStart,
                $gradientEnd,
                $content,
                $buttonText,
                $buttonUrl,
                $buttonColor
            );
        }
        
        // Fallback to default template
        return self::getEmailTemplate($defaultIcon, $defaultTitle, $defaultSubtitle, $defaultGradientStart, $defaultGradientEnd, $content, $buttonText, $buttonUrl);
    }
    
    // Get email template with custom button color
    private static function getEmailTemplateWithColors($icon, $title, $subtitle, $gradientStart, $gradientEnd, $content, $buttonText, $buttonUrl, $buttonColor) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, ' . $gradientStart . ' 0%, ' . $gradientEnd . ' 100%); padding: 40px 30px; text-align: center;">
                                    <div style="font-size: 50px; margin-bottom: 15px;">' . $icon . '</div>
                                    <h1 style="color: white; margin: 0; font-size: 28px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">' . $title . '</h1>
                                    <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">' . $subtitle . '</p>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 35px;">
                                    ' . $content . '
                                    
                                    <!-- Button -->
                                    <div style="text-align: center; margin-top: 35px;">
                                        <a href="' . $buttonUrl . '" style="display: inline-block; background: linear-gradient(135deg, ' . $buttonColor . ' 0%, ' . $gradientEnd . ' 100%); color: white; padding: 14px 35px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; box-shadow: 0 4px 14px rgba(0,0,0,0.15);">' . $buttonText . '</a>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f9fafb; padding: 25px 35px; text-align: center; border-top: 1px solid #e5e7eb;">
                                    <p style="color: #9ca3af; margin: 0; font-size: 13px;">
                                        TaskMesh - Collaboration Made Simple
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
    
    private static function getSettings() {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM email_settings ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetch();
        
        if (!$settings) {
            return array('notifications_enabled' => false);
        }
        
        return $settings;
    }
    
    // Base email template
    private static function getEmailTemplate($icon, $title, $subtitle, $gradientStart, $gradientEnd, $content, $buttonText, $buttonUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td align="center" style="padding: 40px 20px;">
                        <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, ' . $gradientStart . ' 0%, ' . $gradientEnd . ' 100%); padding: 45px 30px; text-align: center;">
                                    <div style="font-size: 56px; margin-bottom: 15px;">' . $icon . '</div>
                                    <h1 style="color: white; margin: 0; font-size: 26px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">' . $title . '</h1>
                                    <p style="color: rgba(255,255,255,0.9); margin: 12px 0 0 0; font-size: 15px;">' . $subtitle . '</p>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 35px;">
                                    ' . $content . '
                                    
                                    <!-- CTA Button -->
                                    <div style="text-align: center; margin: 35px 0 10px 0;">
                                        <a href="' . $buttonUrl . '" style="display: inline-block; background: linear-gradient(135deg, ' . $gradientStart . ' 0%, ' . $gradientEnd . ' 100%); color: white; text-decoration: none; padding: 16px 45px; border-radius: 50px; font-weight: 600; font-size: 15px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15); letter-spacing: 0.3px;">
                                            ' . $buttonText . ' →
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background: #f9fafb; padding: 28px 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                                    <p style="color: #6b7280; font-size: 13px; margin: 0; font-weight: 500;">
                                        TaskMesh - Project Management System
                                    </p>
                                    <p style="color: #9ca3af; font-size: 11px; margin: 8px 0 0 0;">
                                        Αυτοματοποιημένο email • Μην απαντήσεις σε αυτό το μήνυμα
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
    
    public static function send($to, $subject, $body, $altBody = '') {
        $settings = self::getSettings();
        
        if (!$settings['notifications_enabled']) {
            error_log("Email notifications are disabled");
            return true;
        }
        
        if (empty($settings['smtp_host']) || empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
            error_log("Email settings not configured properly");
            return false;
        }
        
        try {
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_username'];
            $mail->Password   = $settings['smtp_password'];
            
            if ($settings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($settings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            $mail->Port = intval($settings['smtp_port']);
            
            $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);
            
            $mail->send();
            error_log("Email sent successfully to: $to");
            return true;
            
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function sendTaskAssigned($assigneeEmail, $assigneeName, $taskTitle, $taskId, $assignedByName) {
        // Check if user wants to receive this type of email
        $userId = self::getUserIdByEmail($assigneeEmail);
        if ($userId && !self::shouldSendEmail($userId, 'task_assigned')) {
            error_log("User $assigneeEmail has disabled task_assigned notifications");
            return true; // Return success but don't send
        }
        
        $baseUrl = self::getBaseUrl();
        
        $content = '
            <p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">
                Γεια σου <strong style="color: #111827;">' . htmlspecialchars($assigneeName) . '</strong>,
            </p>
            <p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">
                Ο/Η <strong style="color: #374151;">' . htmlspecialchars($assignedByName) . '</strong> σου ανέθεσε ένα νέο task:
            </p>
            
            <!-- Task Card -->
            <div style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 10px; border-left: 5px solid #7c3aed;">
                <h2 style="color: #5b21b6; margin: 0; font-size: 19px; font-weight: 600;">' . htmlspecialchars($taskTitle) . '</h2>
            </div>
        ';
        
        $body = self::buildEmailBody(
            'task_assigned',
            '📋',
            'Νέο Task',
            'Σου ανατέθηκε μια νέα εργασία',
            '#667eea', '#764ba2',
            $content,
            'Δες το Task',
            $baseUrl . '/dashboard.html#tasks'
        );
        
        return self::send($assigneeEmail, "📋 Νέο Task: " . $taskTitle, $body);
    }
    
    public static function sendTaskCompleted($managerEmail, $managerName, $taskTitle, $taskId, $completedByName) {
        // Check if user wants to receive this type of email
        $userId = self::getUserIdByEmail($managerEmail);
        if ($userId && !self::shouldSendEmail($userId, 'task_completed')) {
            error_log("User $managerEmail has disabled task_completed notifications");
            return true;
        }
        
        $baseUrl = self::getBaseUrl();
        
        $content = '
            <p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">
                Γεια σου <strong style="color: #111827;">' . htmlspecialchars($managerName) . '</strong>,
            </p>
            <p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">
                Το παρακάτω task ολοκληρώθηκε από τον/την <strong style="color: #374151;">' . htmlspecialchars($completedByName) . '</strong>:
            </p>
            
            <!-- Task Card -->
            <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 10px; border-left: 5px solid #10b981;">
                <div style="display: flex; align-items: center;">
                    <span style="font-size: 24px; margin-right: 12px;">✓</span>
                    <h2 style="color: #065f46; margin: 0; font-size: 19px; font-weight: 600;">' . htmlspecialchars($taskTitle) . '</h2>
                </div>
            </div>
        ';
        
        $body = self::buildEmailBody(
            'task_completed',
            '✅',
            'Task Ολοκληρώθηκε',
            'Μια εργασία ολοκληρώθηκε επιτυχώς',
            '#10b981', '#059669',
            $content,
            'Δες το Task',
            $baseUrl . '/dashboard.html#tasks'
        );
        
        return self::send($managerEmail, "✅ Task Ολοκληρώθηκε: " . $taskTitle, $body);
    }
    
    public static function sendSubtaskCompleted($managerEmail, $managerName, $taskTitle, $subtaskTitle, $taskId, $completedByName) {
        // Check if user wants to receive this type of email
        $userId = self::getUserIdByEmail($managerEmail);
        if ($userId && !self::shouldSendEmail($userId, 'subtask_completed')) {
            error_log("User $managerEmail has disabled subtask_completed notifications");
            return true;
        }
        
        $baseUrl = self::getBaseUrl();
        
        $content = '
            <p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">
                Γεια σου <strong style="color: #111827;">' . htmlspecialchars($managerName) . '</strong>,
            </p>
            <p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">
                Ο/Η <strong style="color: #374151;">' . htmlspecialchars($completedByName) . '</strong> ολοκλήρωσε ένα subtask:
            </p>
            
            <!-- Subtask Card -->
            <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 15px; border-left: 5px solid #10b981;">
                <p style="color: #6b7280; font-size: 13px; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px;">Subtask</p>
                <h2 style="color: #065f46; margin: 0; font-size: 18px; font-weight: 600;">' . htmlspecialchars($subtaskTitle) . '</h2>
            </div>
            
            <p style="color: #9ca3af; font-size: 14px; margin: 0;">
                Στο task: <strong style="color: #6b7280;">' . htmlspecialchars($taskTitle) . '</strong>
            </p>
        ';
        
        $body = self::buildEmailBody(
            'subtask_completed',
            '☑️',
            'Subtask Ολοκληρώθηκε',
            'Ένα subtask ολοκληρώθηκε',
            '#10b981', '#059669',
            $content,
            'Δες το Task',
            $baseUrl . '/dashboard.html#tasks'
        );
        
        return self::send($managerEmail, "☑️ Subtask Ολοκληρώθηκε: " . $subtaskTitle, $body);
    }
    
    public static function sendCommentAdded($recipientEmail, $recipientName, $taskTitle, $taskId, $commentAuthor, $commentText) {
        // Check if user wants to receive this type of email
        $userId = self::getUserIdByEmail($recipientEmail);
        if ($userId && !self::shouldSendEmail($userId, 'comment_added')) {
            error_log("User $recipientEmail has disabled comment_added notifications");
            return true;
        }
        
        $baseUrl = self::getBaseUrl();
        
        $commentPreview = strlen($commentText) > 150 ? substr($commentText, 0, 150) . "..." : $commentText;
        
        $content = '
            <p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">
                Γεια σου <strong style="color: #111827;">' . htmlspecialchars($recipientName) . '</strong>,
            </p>
            <p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">
                Ο/Η <strong style="color: #374151;">' . htmlspecialchars($commentAuthor) . '</strong> σχολίασε στο task:
            </p>
            
            <p style="color: #6b7280; font-size: 14px; margin: 0 0 15px 0;">
                <strong style="color: #374151;">' . htmlspecialchars($taskTitle) . '</strong>
            </p>
            
            <!-- Comment Card -->
            <div style="background: #fffbeb; border-radius: 12px; padding: 20px 25px; margin-bottom: 10px; border-left: 5px solid #f59e0b;">
                <p style="color: #92400e; margin: 0; font-size: 15px; font-style: italic; line-height: 1.6;">
                    "' . htmlspecialchars($commentPreview) . '"
                </p>
            </div>
        ';
        
        $body = self::buildEmailBody(
            'comment_added',
            '💬',
            'Νέο Σχόλιο',
            'Κάποιος σχολίασε σε ένα task',
            '#f59e0b', '#d97706',
            $content,
            'Δες το Σχόλιο',
            $baseUrl . '/dashboard.html#tasks'
        );
        
        return self::send($recipientEmail, "💬 Νέο Σχόλιο: " . $taskTitle, $body);
    }
    
    public static function sendTeamInvitation($memberEmail, $memberName, $teamName, $invitedByName, $teamRole) {
        // Check if user wants to receive this type of email
        $userId = self::getUserIdByEmail($memberEmail);
        if ($userId && !self::shouldSendEmail($userId, 'team_invitation')) {
            error_log("User $memberEmail has disabled team_invitation notifications");
            return true;
        }
        
        $baseUrl = self::getBaseUrl();
        
        $content = '
            <p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">
                Γεια σου <strong style="color: #111827;">' . htmlspecialchars($memberName) . '</strong>,
            </p>
            <p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">
                Ο/Η <strong style="color: #374151;">' . htmlspecialchars($invitedByName) . '</strong> σε προσκάλεσε στο team:
            </p>
            
            <!-- Team Card -->
            <div style="background: linear-gradient(135deg, #ede9fe 0%, #e9d5ff 100%); border-radius: 12px; padding: 25px; margin-bottom: 10px; border-left: 5px solid #8b5cf6; text-align: center;">
                <h2 style="color: #5b21b6; margin: 0 0 12px 0; font-size: 22px; font-weight: 700;">' . htmlspecialchars($teamName) . '</h2>
                <span style="display: inline-block; background: white; color: #7c3aed; padding: 6px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; box-shadow: 0 2px 8px rgba(124, 58, 237, 0.2);">
                    ' . htmlspecialchars($teamRole) . '
                </span>
            </div>
        ';
        
        $body = self::buildEmailBody(
            'team_invitation',
            '👥',
            'Πρόσκληση σε Team',
            'Προσκλήθηκες σε μια νέα ομάδα',
            '#8b5cf6', '#7c3aed',
            $content,
            'Δες το Team',
            $baseUrl . '/dashboard.html#teams'
        );
        
        return self::send($memberEmail, "👥 Πρόσκληση: " . $teamName, $body);
    }
    
    public static function sendDirectMessage($recipientEmail, $recipientName, $senderName, $messagePreview) {
        // Check if user wants to receive this type of email
        $userId = self::getUserIdByEmail($recipientEmail);
        if ($userId && !self::shouldSendEmail($userId, 'direct_message')) {
            error_log("User $recipientEmail has disabled direct_message notifications");
            return true;
        }
        
        $baseUrl = self::getBaseUrl();
        
        $preview = strlen($messagePreview) > 150 ? substr($messagePreview, 0, 150) . "..." : $messagePreview;
        
        $content = '
            <p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">
                Γεια σου <strong style="color: #111827;">' . htmlspecialchars($recipientName) . '</strong>,
            </p>
            <p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">
                Έλαβες νέο μήνυμα από τον/την <strong style="color: #374151;">' . htmlspecialchars($senderName) . '</strong>:
            </p>
            
            <!-- Message Card -->
            <div style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 12px; padding: 22px 25px; margin-bottom: 10px; border-left: 5px solid #3b82f6;">
                <p style="color: #1e40af; margin: 0; font-size: 15px; line-height: 1.6;">
                    "' . htmlspecialchars($preview) . '"
                </p>
            </div>
        ';
        
        $body = self::buildEmailBody(
            'direct_message',
            '✉️',
            'Νέο Μήνυμα',
            'Έχεις νέο προσωπικό μήνυμα',
            '#3b82f6', '#2563eb',
            $content,
            'Απάντησε',
            $baseUrl . '/dashboard.html#chat'
        );
        
        return self::send($recipientEmail, "✉️ Νέο Μήνυμα από " . $senderName, $body);
    }
    
    public static function sendDeadlineReminder($assigneeEmail, $assigneeName, $taskTitle, $taskId, $deadline, $hoursLeft) {
        // Check if user wants to receive this type of email
        $userId = self::getUserIdByEmail($assigneeEmail);
        if ($userId && !self::shouldSendEmail($userId, 'deadline_reminder')) {
            error_log("User $assigneeEmail has disabled deadline_reminder notifications");
            return true;
        }
        
        $baseUrl = self::getBaseUrl();
        
        $isUrgent = $hoursLeft <= 24;
        $icon = $isUrgent ? '🔴' : '⏰';
        $title = $isUrgent ? 'ΕΠΕΙΓΟΝ' : 'Υπενθύμιση';
        $gradientStart = $isUrgent ? '#ef4444' : '#f59e0b';
        $gradientEnd = $isUrgent ? '#dc2626' : '#d97706';
        $cardBg = $isUrgent ? 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)' : 'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)';
        $cardBorder = $isUrgent ? '#ef4444' : '#f59e0b';
        $cardTextColor = $isUrgent ? '#991b1b' : '#92400e';
        
        $formattedDeadline = date('d/m/Y H:i', strtotime($deadline));
        
        $content = '
            <p style="color: #374151; font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">
                Γεια σου <strong style="color: #111827;">' . htmlspecialchars($assigneeName) . '</strong>,
            </p>
            <p style="color: #6b7280; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;">
                ' . ($isUrgent ? '⚠️ Προσοχή! Η προθεσμία πλησιάζει:' : 'Υπενθύμιση για το παρακάτω task:') . '
            </p>
            
            <!-- Task Card -->
            <div style="background: ' . $cardBg . '; border-radius: 12px; padding: 22px 25px; margin-bottom: 20px; border-left: 5px solid ' . $cardBorder . ';">
                <h2 style="color: ' . $cardTextColor . '; margin: 0 0 15px 0; font-size: 19px; font-weight: 600;">' . htmlspecialchars($taskTitle) . '</h2>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">⏰</span>
                    <span style="color: ' . $cardTextColor . '; font-size: 15px; font-weight: 600;">Προθεσμία: ' . $formattedDeadline . '</span>
                </div>
            </div>
            
            <p style="text-align: center; color: #6b7280; font-size: 14px; margin: 0;">
                Απομένουν περίπου <strong style="color: ' . $cardTextColor . ';">' . $hoursLeft . ' ώρες</strong>
            </p>
        ';
        
        $body = self::buildEmailBody(
            'deadline_reminder',
            $icon,
            $title,
            'Προθεσμία task σύντομα',
            $gradientStart, $gradientEnd,
            $content,
            'Δες το Task',
            $baseUrl . '/dashboard.html#tasks'
        );
        
        $subjectIcon = $isUrgent ? '🔴' : '⏰';
        return self::send($assigneeEmail, "$subjectIcon $title: " . $taskTitle, $body);
    }
}
?>
