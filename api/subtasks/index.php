<?php
// TaskMesh - Subtasks API (GET by task, POST create, PUT update, DELETE)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/PHPMailer.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Handle HTTP method override for PUT/DELETE
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// GET - Get subtasks by task_id
if ($method === 'GET') {
    $task_id = isset($_GET['task_id']) ? $_GET['task_id'] : null;
    
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(array("error" => "task_id is required"));
        exit();
    }
    
    // Verify user has access to task (admin sees all)
    if ($user['role'] !== 'ADMIN') {
        $query = "SELECT * FROM tasks WHERE id = :task_id 
                  AND (creator_id = :user_id OR assignee_id = :user_id2 OR team_id IN (
                      SELECT team_id FROM team_members WHERE user_id = :user_id3
                  ))";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":task_id", $task_id);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->bindParam(":user_id2", $user['id']);
        $stmt->bindParam(":user_id3", $user['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(array("error" => "Access denied to this task"));
            exit();
        }
    }
    
    // Get subtasks
    $query = "SELECT s.*, 
              u.first_name as completed_by_first_name, 
              u.last_name as completed_by_last_name 
              FROM subtasks s
              LEFT JOIN users u ON s.completed_by = u.id
              WHERE s.task_id = :task_id 
              ORDER BY s.created_at ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $task_id);
    $stmt->execute();
    
    $subtasks = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode($subtasks);
    exit();
}

// POST - Create new subtask
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->task_id) || !isset($data->title)) {
        http_response_code(400);
        echo json_encode(array("error" => "task_id and title are required"));
        exit();
    }
    
    // Verify user has access to task
    $query = "SELECT * FROM tasks WHERE id = :task_id 
              AND (creator_id = :user_id OR assignee_id = :user_id2 OR team_id IN (
                  SELECT team_id FROM team_members WHERE user_id = :user_id3
              ))";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $data->task_id);
    $stmt->bindParam(":user_id", $user['id']);
    $stmt->bindParam(":user_id2", $user['id']);
    $stmt->bindParam(":user_id3", $user['id']);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(array("error" => "Access denied to this task"));
        exit();
    }
    
    $status = isset($data->status) ? $data->status : 'TODO';
    $deadline = isset($data->deadline) ? $data->deadline : null;
    
    // Create subtask
    $query = "INSERT INTO subtasks (task_id, title, status, deadline) 
              VALUES (:task_id, :title, :status, :deadline)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $data->task_id);
    $stmt->bindParam(":title", $data->title);
    $stmt->bindParam(":status", $status);
    $stmt->bindParam(":deadline", $deadline);
    
    if ($stmt->execute()) {
        $subtask_id = $db->lastInsertId();
        
        // Get created subtask
        $query = "SELECT * FROM subtasks WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $subtask_id);
        $stmt->execute();
        $subtask = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode($subtask);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to create subtask"));
    }
    exit();
}

// PUT - Update subtask
if ($method === 'PUT') {
    $subtask_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$subtask_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Subtask ID is required"));
        exit();
    }
    
    $data = json_decode(file_get_contents("php://input"));
    
    // Get subtask and verify access
    $query = "SELECT s.*, t.creator_id, t.assignee_id, t.team_id 
              FROM subtasks s
              JOIN tasks t ON s.task_id = t.id
              WHERE s.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $subtask_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(array("error" => "Subtask not found"));
        exit();
    }
    
    $subtask = $stmt->fetch();
    
    // Verify user has access
    $has_access = false;
    if ($subtask['creator_id'] == $user['id'] || $subtask['assignee_id'] == $user['id']) {
        $has_access = true;
    } else if ($subtask['team_id']) {
        $query = "SELECT * FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":team_id", $subtask['team_id']);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $has_access = true;
        }
    }
    
    if (!$has_access && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "Access denied"));
        exit();
    }
    
    // Build update query
    $updates = [];
    $params = [':id' => $subtask_id];
    
    if (isset($data->title)) {
        $updates[] = "title = :title";
        $params[':title'] = $data->title;
    }
    if (isset($data->status)) {
        $updates[] = "status = :status";
        $params[':status'] = $data->status;
        
        // Set completed_at and completed_by if status is COMPLETED
        if ($data->status === 'COMPLETED') {
            $updates[] = "completed_at = NOW()";
            $updates[] = "completed_by = :completed_by";
            $params[':completed_by'] = $user['id'];
        } else {
            $updates[] = "completed_at = NULL";
            $updates[] = "completed_by = NULL";
        }
    }
    
    // Track if status changed to COMPLETED for email notification
    $statusChangedToCompleted = isset($data->status) && $data->status === 'COMPLETED' && $subtask['status'] !== 'COMPLETED';
    
    if (isset($data->deadline)) {
        $updates[] = "deadline = :deadline";
        $params[':deadline'] = $data->deadline;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(array("error" => "No fields to update"));
        exit();
    }
    
    $query = "UPDATE subtasks SET " . implode(", ", $updates) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($params)) {
        // Get updated subtask
        $query = "SELECT s.*, 
                  u.first_name as completed_by_first_name, 
                  u.last_name as completed_by_last_name 
                  FROM subtasks s
                  LEFT JOIN users u ON s.completed_by = u.id
                  WHERE s.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $subtask_id);
        $stmt->execute();
        $subtask = $stmt->fetch();
        
        // Send email notification if subtask completed
        if ($statusChangedToCompleted) {
            // Get task and creator (manager) information
            $taskQuery = "SELECT t.title, t.creator_id, u.email, u.first_name, u.last_name 
                         FROM tasks t
                         JOIN users u ON t.creator_id = u.id
                         WHERE t.id = :task_id";
            $taskStmt = $db->prepare($taskQuery);
            $taskStmt->bindParam(":task_id", $subtask['task_id']);
            $taskStmt->execute();
            $taskInfo = $taskStmt->fetch();
            
            if ($taskInfo) {
                EmailService::sendSubtaskCompleted(
                    $taskInfo['email'],
                    $taskInfo['first_name'] . ' ' . $taskInfo['last_name'],
                    $taskInfo['title'],
                    $subtask['title'],
                    $subtask['task_id'],
                    $user['first_name'] . ' ' . $user['last_name']
                );
            }
        }
        
        http_response_code(200);
        echo json_encode($subtask);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to update subtask"));
    }
    exit();
}

// DELETE - Delete subtask
if ($method === 'DELETE') {
    $subtask_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$subtask_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Subtask ID is required"));
        exit();
    }
    
    // Get subtask and verify access
    $query = "SELECT s.*, t.creator_id, t.assignee_id, t.team_id 
              FROM subtasks s
              JOIN tasks t ON s.task_id = t.id
              WHERE s.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $subtask_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(array("error" => "Subtask not found"));
        exit();
    }
    
    $subtask = $stmt->fetch();
    
    // Only creator or admin can delete
    if ($subtask['creator_id'] != $user['id'] && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "Only task creator or admin can delete subtasks"));
        exit();
    }
    
    $query = "DELETE FROM subtasks WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $subtask_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "Subtask deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to delete subtask"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>
