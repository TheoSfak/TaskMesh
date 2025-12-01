<?php
// TaskMesh - Comments API (GET by task, POST create, DELETE)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/PHPMailer.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Handle HTTP method override for DELETE
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// GET - Get comments for a task
if ($method === 'GET') {
    $task_id = isset($_GET['task_id']) ? $_GET['task_id'] : null;
    
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(array("error" => "task_id is required"));
        exit();
    }
    
    $query = "SELECT c.*, 
              u.first_name, u.last_name, u.avatar
              FROM comments c
              LEFT JOIN users u ON c.user_id = u.id
              WHERE c.task_id = :task_id
              ORDER BY c.created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $task_id);
    $stmt->execute();
    
    $comments = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode($comments);
    exit();
}

// POST - Create comment
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->task_id) || !isset($data->content)) {
        http_response_code(400);
        echo json_encode(array("error" => "task_id and content are required"));
        exit();
    }
    
    // Check if task exists
    $query = "SELECT id FROM tasks WHERE id = :task_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $data->task_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(array("error" => "Task not found"));
        exit();
    }
    
    // Insert comment
    $query = "INSERT INTO comments (task_id, user_id, content) VALUES (:task_id, :user_id, :content)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $data->task_id);
    $stmt->bindParam(":user_id", $user['id']);
    $stmt->bindParam(":content", $data->content);
    
    if ($stmt->execute()) {
        $comment_id = $db->lastInsertId();
        
        // Get created comment
        $query = "SELECT c.*, 
                  u.first_name, u.last_name, u.avatar
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $comment_id);
        $stmt->execute();
        $comment = $stmt->fetch();
        
        // Send email notification to task creator and assignee
        $taskQuery = "SELECT t.title, t.creator_id, t.assignee_id, 
                      u1.email as creator_email, u1.first_name as creator_first, u1.last_name as creator_last,
                      u2.email as assignee_email, u2.first_name as assignee_first, u2.last_name as assignee_last
                      FROM tasks t
                      LEFT JOIN users u1 ON t.creator_id = u1.id
                      LEFT JOIN users u2 ON t.assignee_id = u2.id
                      WHERE t.id = :task_id";
        $taskStmt = $db->prepare($taskQuery);
        $taskStmt->bindParam(":task_id", $data->task_id);
        $taskStmt->execute();
        $taskInfo = $taskStmt->fetch();
        
        if ($taskInfo) {
            $commentAuthor = $user['first_name'] . ' ' . $user['last_name'];
            
            // Notify creator if not the comment author
            if ($taskInfo['creator_id'] != $user['id'] && $taskInfo['creator_email']) {
                EmailService::sendCommentAdded(
                    $taskInfo['creator_email'],
                    $taskInfo['creator_first'] . ' ' . $taskInfo['creator_last'],
                    $taskInfo['title'],
                    $data->task_id,
                    $commentAuthor,
                    $data->content
                );
            }
            
            // Notify assignee if not the comment author and different from creator
            if ($taskInfo['assignee_id'] && 
                $taskInfo['assignee_id'] != $user['id'] && 
                $taskInfo['assignee_id'] != $taskInfo['creator_id'] && 
                $taskInfo['assignee_email']) {
                EmailService::sendCommentAdded(
                    $taskInfo['assignee_email'],
                    $taskInfo['assignee_first'] . ' ' . $taskInfo['assignee_last'],
                    $taskInfo['title'],
                    $data->task_id,
                    $commentAuthor,
                    $data->content
                );
            }
        }
        
        http_response_code(201);
        echo json_encode($comment);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to create comment"));
    }
    exit();
}

// DELETE - Delete comment
if ($method === 'DELETE') {
    $comment_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$comment_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Comment ID is required"));
        exit();
    }
    
    // Check if user owns comment or is admin
    $query = "SELECT * FROM comments WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $comment_id);
    $stmt->execute();
    $comment = $stmt->fetch();
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(array("error" => "Comment not found"));
        exit();
    }
    
    if ($comment['user_id'] != $user['id'] && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "You can only delete your own comments"));
        exit();
    }
    
    $query = "DELETE FROM comments WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $comment_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "Comment deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to delete comment"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>