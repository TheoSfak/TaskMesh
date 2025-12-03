<?php
// TaskMesh - Team Messages API (Get and Post messages for chat)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Handle HTTP method override for DELETE
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// GET - Get messages for team
if ($method === 'GET') {
    $team_id = isset($_GET['team_id']) ? $_GET['team_id'] : null;

    if (!$team_id) {
        http_response_code(400);
        echo json_encode(array("error" => "team_id is required"));
        exit();
    }

    // Check if user is member of team (skip check for admin)
    if ($user['role'] !== 'ADMIN') {
        $query = "SELECT * FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(array("error" => "You are not a member of this team"));
            exit();
        }
    }

    // Get messages (last 50)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

    $query = "SELECT m.*, 
              u.first_name, u.last_name, u.avatar
              FROM messages m
              LEFT JOIN users u ON m.user_id = u.id
              WHERE m.team_id = :team_id
              ORDER BY m.created_at DESC
              LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $team_id);
    $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    $stmt->execute();

    $messages = $stmt->fetchAll();

    // Reverse to get chronological order
    $messages = array_reverse($messages);

    http_response_code(200);
    echo json_encode($messages);
    exit();
}

// POST - Send new message
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->team_id) || !isset($data->content)) {
        http_response_code(400);
        echo json_encode(array("error" => "team_id and content are required"));
        exit();
    }
    
    // Check if user is member of team (skip check for admin)
    if ($user['role'] !== 'ADMIN') {
        $query = "SELECT * FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":team_id", $data->team_id);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(array("error" => "You are not a member of this team"));
            exit();
        }
    }
    
    // Insert message
    $query = "INSERT INTO messages (team_id, user_id, content) VALUES (:team_id, :user_id, :content)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $data->team_id);
    $stmt->bindParam(":user_id", $user['id']);
    $stmt->bindParam(":content", $data->content);
    
    if ($stmt->execute()) {
        $message_id = $db->lastInsertId();
        
        // Get created message with user info
        $query = "SELECT m.*, 
                  u.first_name, u.last_name, u.avatar
                  FROM messages m
                  LEFT JOIN users u ON m.user_id = u.id
                  WHERE m.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $message_id);
        $stmt->execute();
        $message = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode($message);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to send message"));
    }
    exit();
}

// DELETE - Delete message (owner or admin only)
if ($method === 'DELETE') {
    $message_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$message_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Message ID is required"));
        exit();
    }
    
    // Check if message exists
    $query = "SELECT * FROM messages WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $message_id);
    $stmt->execute();
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(array("error" => "Message not found"));
        exit();
    }
    
    // Check if user owns message or is admin
    if ($message['user_id'] != $user['id'] && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "You can only delete your own messages"));
        exit();
    }
    
    $query = "DELETE FROM messages WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $message_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "Message deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to delete message"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>