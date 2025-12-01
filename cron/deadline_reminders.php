<?php
/**
 * Deadline Reminder Cron Job
 * 
 * This script sends reminder emails for tasks that are due within the next 24 hours.
 * Email settings are loaded from database (email_settings table).
 * 
 * Setup in Hostinger cPanel:
 * Advanced → Cron Jobs → Create New
 * Command: /usr/bin/php /home/YOUR_USERNAME/public_html/TaskMesh/cron/deadline_reminders.php >> /home/YOUR_USERNAME/public_html/TaskMesh/cron/cron.log 2>&1
 * 
 * Schedule Examples:
 * - Every hour: 0 * * * *
 * - Every 6 hours: 0 */6 * * *
 * - Daily at 9am: 0 9 * * *
 * - Twice daily (9am, 5pm): 0 9,17 * * *
 * 
 * Manual run: php deadline_reminders.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/PHPMailer.php';

echo "=== TaskMesh Deadline Reminders ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$database = new Database();
$db = $database->getConnection();

// Get tasks with deadlines in the next 24 hours that are not completed
$query = "SELECT t.id, t.title, t.deadline, t.assignee_id,
          u.email, u.first_name, u.last_name
          FROM tasks t
          JOIN users u ON t.assignee_id = u.id
          WHERE t.status != 'COMPLETED'
          AND t.deadline IS NOT NULL
          AND t.deadline >= NOW()
          AND t.deadline <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
          AND u.is_active = 1";

$stmt = $db->prepare($query);
$stmt->execute();
$tasks = $stmt->fetchAll();

echo "Found " . count($tasks) . " tasks with upcoming deadlines\n\n";

$sent = 0;
$failed = 0;

foreach ($tasks as $task) {
    echo "Processing task: {$task['title']}\n";
    echo "  Assignee: {$task['first_name']} {$task['last_name']} ({$task['email']})\n";
    echo "  Deadline: {$task['deadline']}\n";
    
    $result = EmailService::sendDeadlineReminder(
        $task['email'],
        $task['first_name'] . ' ' . $task['last_name'],
        $task['title'],
        $task['id'],
        date('d/m/Y H:i', strtotime($task['deadline']))
    );
    
    if ($result) {
        echo "  ✓ Email sent successfully\n\n";
        $sent++;
    } else {
        echo "  ✗ Failed to send email\n\n";
        $failed++;
    }
}

echo "=== Summary ===\n";
echo "Total tasks: " . count($tasks) . "\n";
echo "Emails sent: $sent\n";
echo "Emails failed: $failed\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
?>
