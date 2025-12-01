<?php
/**
 * Email Configuration Test Script
 * 
 * Run this to verify your email settings work correctly
 * Usage: php test_email_config.php
 */

require_once __DIR__ . '/config/email.php';
require_once __DIR__ . '/lib/PHPMailer.php';

echo "=== TaskMesh Email Configuration Test ===\n\n";

// Display current configuration
echo "Current Configuration:\n";
echo "---------------------\n";
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP Username: " . SMTP_USERNAME . "\n";
echo "SMTP Encryption: " . SMTP_ENCRYPTION . "\n";
echo "From Email: " . SMTP_FROM_EMAIL . "\n";
echo "From Name: " . SMTP_FROM_NAME . "\n";
echo "Notifications Enabled: " . (EMAIL_NOTIFICATIONS_ENABLED ? 'YES' : 'NO') . "\n";
echo "Log Mode: " . (defined('EMAIL_LOG_MODE') && EMAIL_LOG_MODE ? 'YES' : 'NO') . "\n";
echo "App Base URL: " . APP_BASE_URL . "\n\n";

// Get test email address
echo "Enter test email address: ";
$testEmail = trim(fgets(STDIN));

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email address!\n");
}

echo "\nSending test email to: $testEmail\n";
echo "Please wait...\n\n";

// Send test email
$subject = "TaskMesh Email Test - " . date('Y-m-d H:i:s');
$body = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .success { background: #10B981; color: white; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center; }
        .info { background: #EFF6FF; border-left: 4px solid #3B82F6; padding: 15px; margin: 15px 0; }
        .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>✓ TaskMesh Email Test</h2>
        </div>
        <div class='content'>
            <div class='success'>
                <h3>Email Configuration Works!</h3>
                <p>If you're reading this, your email settings are configured correctly.</p>
            </div>
            
            <div class='info'>
                <h4>Test Details:</h4>
                <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>SMTP Host:</strong> " . SMTP_HOST . "</p>
                <p><strong>From:</strong> " . SMTP_FROM_EMAIL . "</p>
            </div>
            
            <h3>Next Steps:</h3>
            <ol>
                <li>✓ Email configuration is working</li>
                <li>Test actual notifications by using the app</li>
                <li>Set up cron job for deadline reminders</li>
                <li>Update APP_BASE_URL for production</li>
            </ol>
            
            <div class='footer'>
                <p>TaskMesh Project Management System</p>
                <p>This is an automated test email</p>
            </div>
        </div>
    </div>
</body>
</html>
";

$result = EmailService::send($testEmail, $subject, $body);

if ($result) {
    echo "✓ SUCCESS! Email sent successfully!\n";
    echo "\nCheck your inbox at: $testEmail\n";
    echo "Note: It may take a few moments to arrive.\n";
    echo "Don't forget to check your spam folder!\n";
} else {
    echo "✗ FAILED! Could not send email.\n\n";
    echo "Troubleshooting Steps:\n";
    echo "1. Check SMTP credentials in config/email.php\n";
    echo "2. For Gmail, ensure you're using an App Password\n";
    echo "3. Check PHP error logs for details\n";
    echo "4. Verify firewall allows connections on port " . SMTP_PORT . "\n";
    echo "5. Enable EMAIL_LOG_MODE to see if the function is called\n";
}

echo "\n=== Test Complete ===\n";
?>
