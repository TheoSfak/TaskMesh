<?php
// Notifications API - GET notifications, Mark as read, Delete

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get user's notifications
if ($method === 'GET') {
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    
    $query = "SELECT * FROM notifications 
              WHERE user_id = :user_id";
    
    if ($unread_only) {
        $query .= " AND is_read = FALSE";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user['id']);
    $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll();
    
    // Also return unread count
    $countQuery = "SELECT COUNT(*) as unread_count FROM notifications 
                   WHERE user_id = :user_id AND is_read = FALSE";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(":user_id", $user['id']);
    $countStmt->execute();
    $countData = $countStmt->fetch();
    
    http_response_code(200);
    echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $countData['unread_count']
    ]);
    exit();
}

// PUT - Mark notification(s) as read
if ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (isset($data->id)) {
        // Mark single notification as read
        $query = "UPDATE notifications 
                  SET is_read = TRUE, read_at = NOW() 
                  WHERE id = :id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $data->id);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode(["message" => "Notification marked as read"]);
    } elseif (isset($data->mark_all_read) && $data->mark_all_read === true) {
        // Mark all as read
        $query = "UPDATE notifications 
                  SET is_read = TRUE, read_at = NOW() 
                  WHERE user_id = :user_id AND is_read = FALSE";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode(["message" => "All notifications marked as read"]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid request"]);
    }
    exit();
}

// DELETE - Delete notification
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Notification ID required"]);
        exit();
    }
    
    $query = "DELETE FROM notifications WHERE id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $id);
    $stmt->bindParam(":user_id", $user['id']);
    $stmt->execute();
    
    http_response_code(200);
    echo json_encode(["message" => "Notification deleted"]);
    exit();
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
