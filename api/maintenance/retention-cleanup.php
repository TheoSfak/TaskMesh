<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Verify authentication (authenticate() exits on failure, returns user on success)
$user = authenticate();

// Only admins can run cleanup
if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Load retention settings
        $query = "SELECT setting_key, setting_value FROM system_settings 
                  WHERE category = 'retention'";
        $stmt = $db->query($query);
        $settings = [
            'archive_completed_tasks_days' => 0,
            'delete_archived_tasks_days' => 0,
            'delete_inactive_users_months' => 0,
            'clean_old_notifications_days' => 0,
            'clean_old_comments_days' => 0
        ];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = (int)$row['setting_value'];
        }

        $cleaned = [
            'tasks_archived' => 0,
            'tasks_deleted' => 0,
            'users_deleted' => 0,
            'notifications_deleted' => 0,
            'comments_deleted' => 0
        ];

        // 1. Archive completed tasks
        if ($settings['archive_completed_tasks_days'] > 0) {
            $query = "UPDATE tasks 
                      SET status = 'ARCHIVED' 
                      WHERE status = 'COMPLETED' 
                      AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                      AND status != 'ARCHIVED'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':days', $settings['archive_completed_tasks_days']);
            $stmt->execute();
            $cleaned['tasks_archived'] = $stmt->rowCount();
        }

        // 2. Delete archived tasks
        if ($settings['delete_archived_tasks_days'] > 0) {
            // First delete related comments
            $query = "DELETE FROM task_comments 
                      WHERE task_id IN (
                          SELECT id FROM tasks 
                          WHERE status = 'ARCHIVED' 
                          AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                      )";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':days', $settings['delete_archived_tasks_days']);
            $stmt->execute();

            // Then delete tasks
            $query = "DELETE FROM tasks 
                      WHERE status = 'ARCHIVED' 
                      AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':days', $settings['delete_archived_tasks_days']);
            $stmt->execute();
            $cleaned['tasks_deleted'] = $stmt->rowCount();
        }

        // 3. Delete inactive users
        if ($settings['delete_inactive_users_months'] > 0) {
            $query = "DELETE FROM users 
                      WHERE is_active = 0 
                      AND last_login < DATE_SUB(NOW(), INTERVAL :months MONTH)
                      AND role != 'ADMIN'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':months', $settings['delete_inactive_users_months']);
            $stmt->execute();
            $cleaned['users_deleted'] = $stmt->rowCount();
        }

        // 4. Clean old notifications
        if ($settings['clean_old_notifications_days'] > 0) {
            $query = "DELETE FROM notifications 
                      WHERE is_read = 1 
                      AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':days', $settings['clean_old_notifications_days']);
            $stmt->execute();
            $cleaned['notifications_deleted'] = $stmt->rowCount();
        }

        // 5. Clean old comments (only from deleted tasks)
        if ($settings['clean_old_comments_days'] > 0) {
            $query = "DELETE FROM task_comments 
                      WHERE task_id NOT IN (SELECT id FROM tasks)
                      AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':days', $settings['clean_old_comments_days']);
            $stmt->execute();
            $cleaned['comments_deleted'] = $stmt->rowCount();
        }

        // Update last cleanup timestamp
        $query = "UPDATE system_settings 
                  SET setting_value = NOW(), updated_by = :user_id 
                  WHERE setting_key = 'last_retention_cleanup'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Retention cleanup completed successfully',
            'cleaned' => $cleaned
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to run cleanup: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
