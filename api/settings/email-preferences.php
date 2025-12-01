<?php
// TaskMesh - Email Preferences API (Admin)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// GET - Get user's email preferences
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("SELECT * FROM user_email_preferences WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$preferences) {
            // Create default preferences
            $stmt = $db->prepare("
                INSERT INTO user_email_preferences (user_id) VALUES (?)
                ON DUPLICATE KEY UPDATE updated_at = NOW()
            ");
            $stmt->execute([$user['id']]);
            
            $stmt = $db->prepare("SELECT * FROM user_email_preferences WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Convert boolean fields
        $preferences['notify_task_assigned'] = (bool)$preferences['notify_task_assigned'];
        $preferences['notify_task_completed'] = (bool)$preferences['notify_task_completed'];
        $preferences['notify_subtask_completed'] = (bool)$preferences['notify_subtask_completed'];
        $preferences['notify_comment_added'] = (bool)$preferences['notify_comment_added'];
        $preferences['notify_deadline_reminder'] = (bool)$preferences['notify_deadline_reminder'];
        $preferences['notify_team_invitation'] = (bool)$preferences['notify_team_invitation'];
        $preferences['notify_direct_message'] = (bool)$preferences['notify_direct_message'];
        
        http_response_code(200);
        echo json_encode($preferences);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to load preferences: " . $e->getMessage()));
    }
    exit();
}

// PUT - Update email preferences
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        $stmt = $db->prepare("
            INSERT INTO user_email_preferences (
                user_id,
                notify_task_assigned,
                notify_task_completed,
                notify_subtask_completed,
                notify_comment_added,
                notify_deadline_reminder,
                notify_team_invitation,
                notify_direct_message,
                team_filter,
                email_digest
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                notify_task_assigned = VALUES(notify_task_assigned),
                notify_task_completed = VALUES(notify_task_completed),
                notify_subtask_completed = VALUES(notify_subtask_completed),
                notify_comment_added = VALUES(notify_comment_added),
                notify_deadline_reminder = VALUES(notify_deadline_reminder),
                notify_team_invitation = VALUES(notify_team_invitation),
                notify_direct_message = VALUES(notify_direct_message),
                team_filter = VALUES(team_filter),
                email_digest = VALUES(email_digest),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $user['id'],
            isset($data->notify_task_assigned) ? (int)$data->notify_task_assigned : 1,
            isset($data->notify_task_completed) ? (int)$data->notify_task_completed : 1,
            isset($data->notify_subtask_completed) ? (int)$data->notify_subtask_completed : 1,
            isset($data->notify_comment_added) ? (int)$data->notify_comment_added : 1,
            isset($data->notify_deadline_reminder) ? (int)$data->notify_deadline_reminder : 1,
            isset($data->notify_team_invitation) ? (int)$data->notify_team_invitation : 1,
            isset($data->notify_direct_message) ? (int)$data->notify_direct_message : 1,
            isset($data->team_filter) ? $data->team_filter : 'all',
            isset($data->email_digest) ? $data->email_digest : 'instant'
        ]);
        
        http_response_code(200);
        echo json_encode(array("success" => true, "message" => "Preferences saved successfully"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to save preferences: " . $e->getMessage()));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>
