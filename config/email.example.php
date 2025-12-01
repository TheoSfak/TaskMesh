<?php
/**
 * Email Configuration Example
 * 
 * Copy this file to email.php and configure with your SMTP settings.
 * DO NOT commit email.php with real credentials!
 */

// ============================================
// SMTP Server Configuration
// ============================================

// Gmail Example:
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);  // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-16-char-app-password');  // NOT your regular password!
define('SMTP_ENCRYPTION', 'tls');  // 'tls' or 'ssl'

// Other SMTP Providers:
// 
// Outlook/Hotmail:
// define('SMTP_HOST', 'smtp-mail.outlook.com');
// define('SMTP_PORT', 587);
//
// Yahoo:
// define('SMTP_HOST', 'smtp.mail.yahoo.com');
// define('SMTP_PORT', 465);
//
// SendGrid:
// define('SMTP_HOST', 'smtp.sendgrid.net');
// define('SMTP_PORT', 587);
// define('SMTP_USERNAME', 'apikey');
// define('SMTP_PASSWORD', 'your-sendgrid-api-key');
//
// Mailgun:
// define('SMTP_HOST', 'smtp.mailgun.org');
// define('SMTP_PORT', 587);

// ============================================
// Email Display Settings
// ============================================

define('SMTP_FROM_EMAIL', 'your-email@gmail.com');  // Must match SMTP_USERNAME for most providers
define('SMTP_FROM_NAME', 'TaskMesh Notifications');

// ============================================
// Application Settings
// ============================================

// Enable/disable all email notifications
define('EMAIL_NOTIFICATIONS_ENABLED', true);  // Set to false to disable

// Base URL for links in emails (update for production!)
define('APP_BASE_URL', 'http://localhost/TaskMesh');

// ============================================
// Development Settings (Optional)
// ============================================

// Log emails instead of sending (for development)
// define('EMAIL_LOG_MODE', true);  // Uncomment to enable

// ============================================
// Setup Instructions
// ============================================

/*
 * Gmail Setup:
 * 1. Go to https://myaccount.google.com/security
 * 2. Enable 2-Step Verification
 * 3. Go to https://myaccount.google.com/apppasswords
 * 4. Create new App Password for "Mail"
 * 5. Use that 16-character password in SMTP_PASSWORD above
 * 
 * Testing:
 * Run: php test_email_config.php
 * 
 * Documentation:
 * See EMAIL_NOTIFICATIONS.md for full setup guide
 */
?>
