<?php
// TaskMesh - Direct Messages API (1-on-1 private messaging)

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

// GET - Get conversations list or messages with specific user
if ($method === 'GET') {
    $other_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    
    if ($other_user_id) {
        // Get messages with specific user
        $query = "SELECT dm.*, 
                  s.first_name as sender_first_name, 
                  s.last_name as sender_last_name,
                  s.avatar as sender_avatar,
                  r.first_name as receiver_first_name,
                  r.last_name as receiver_last_name,
                  r.avatar as receiver_avatar
                  FROM direct_messages dm
                  LEFT JOIN users s ON dm.sender_id = s.id
                  LEFT JOIN users r ON dm.receiver_id = r.id
                  WHERE (dm.sender_id = :user_id AND dm.receiver_id = :other_user_id)
                     OR (dm.sender_id = :other_user_id2 AND dm.receiver_id = :user_id2)
                  ORDER BY dm.created_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->bindParam(":user_id2", $user['id']);
        $stmt->bindParam(":other_user_id", $other_user_id);
        $stmt->bindParam(":other_user_id2", $other_user_id);
        $stmt->execute();
        $messages = $stmt->fetchAll();
        
        // Mark messages as read
        $query = "UPDATE direct_messages SET is_read = TRUE 
                  WHERE receiver_id = :user_id AND sender_id = :other_user_id AND is_read = FALSE";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->bindParam(":other_user_id", $other_user_id);
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode($messages);
    } else {
        // Get list of conversations with last message and unread count
        $query = "SELECT 
                  u.id as user_id,
                  u.first_name,
                  u.last_name,
                  u.avatar,
                  u.role,
                  (SELECT content FROM direct_messages 
                   WHERE (sender_id = u.id AND receiver_id = :user_id) 
                      OR (sender_id = :user_id2 AND receiver_id = u.id)
                   ORDER BY created_at DESC LIMIT 1) as last_message,
                  (SELECT created_at FROM direct_messages 
                   WHERE (sender_id = u.id AND receiver_id = :user_id3) 
                      OR (sender_id = :user_id4 AND receiver_id = u.id)
                   ORDER BY created_at DESC LIMIT 1) as last_message_at,
                  (SELECT COUNT(*) FROM direct_messages 
                   WHERE sender_id = u.id AND receiver_id = :user_id5 AND is_read = FALSE) as unread_count
                  FROM users u
                  WHERE u.id != :user_id6 
                  AND u.is_active = TRUE
                  AND EXISTS (
                      SELECT 1 FROM direct_messages dm 
                      WHERE (dm.sender_id = u.id AND dm.receiver_id = :user_id7)
                         OR (dm.sender_id = :user_id8 AND dm.receiver_id = u.id)
                  )
                  ORDER BY last_message_at DESC";
        
        $stmt = $db->prepare($query);
        for ($i = 1; $i <= 8; $i++) {
            $stmt->bindParam(":user_id" . ($i > 1 ? $i : ''), $user['id']);
        }
        $stmt->execute();
        $conversations = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode($conversations);
    }
    exit();
}

// POST - Send new direct message
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->receiver_id) || !isset($data->content)) {
        http_response_code(400);
        echo json_encode(array("error" => "receiver_id and content are required"));
        exit();
    }
    
    // Check if receiver exists and is active
    $query = "SELECT id FROM users WHERE id = :receiver_id AND is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":receiver_id", $data->receiver_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(array("error" => "Receiver not found or inactive"));
        exit();
    }
    
    // Insert message
    $query = "INSERT INTO direct_messages (sender_id, receiver_id, content) 
              VALUES (:sender_id, :receiver_id, :content)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":sender_id", $user['id']);
    $stmt->bindParam(":receiver_id", $data->receiver_id);
    $stmt->bindParam(":content", $data->content);
    
    if ($stmt->execute()) {
        $message_id = $db->lastInsertId();
        
        // Get created message with user info
        $query = "SELECT dm.*, 
                  s.first_name as sender_first_name, 
                  s.last_name as sender_last_name,
                  s.avatar as sender_avatar,
                  r.first_name as receiver_first_name,
                  r.last_name as receiver_last_name,
                  r.avatar as receiver_avatar
                  FROM direct_messages dm
                  LEFT JOIN users s ON dm.sender_id = s.id
                  LEFT JOIN users r ON dm.receiver_id = r.id
                  WHERE dm.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $message_id);
        $stmt->execute();
        $message = $stmt->fetch();
        
        // Send email notification to receiver
        $receiverQuery = "SELECT email, first_name, last_name FROM users WHERE id = :receiver_id";
        $receiverStmt = $db->prepare($receiverQuery);
        $receiverStmt->bindParam(":receiver_id", $data->receiver_id);
        $receiverStmt->execute();
        $receiver = $receiverStmt->fetch();
        
        if ($receiver) {
            EmailService::sendDirectMessage(
                $receiver['email'],
                $receiver['first_name'] . ' ' . $receiver['last_name'],
                $user['first_name'] . ' ' . $user['last_name'],
                $data->content
            );
        }
        
        http_response_code(201);
        echo json_encode($message);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to send message"));
    }
    exit();
}

// DELETE - Delete message or entire conversation
if ($method === 'DELETE') {
    $message_id = isset($_GET['id']) ? $_GET['id'] : null;
    $delete_conversation = isset($_GET['conversation']) ? $_GET['conversation'] : null;
    
    // Delete entire conversation with a user
    if ($delete_conversation) {
        $other_user_id = $delete_conversation;
        
        // Delete all messages between the two users (both directions)
        $query = "DELETE FROM direct_messages 
                  WHERE (sender_id = :user_id AND receiver_id = :other_user_id)
                     OR (sender_id = :other_user_id2 AND receiver_id = :user_id2)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->bindParam(":user_id2", $user['id']);
        $stmt->bindParam(":other_user_id", $other_user_id);
        $stmt->bindParam(":other_user_id2", $other_user_id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Conversation deleted successfully"));
        } else {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to delete conversation"));
        }
        exit();
    }
    
    // Delete single message
    if (!$message_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Message ID is required"));
        exit();
    }
    
    // Check if message exists
    $query = "SELECT * FROM direct_messages WHERE id = :id";
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
    if ($message['sender_id'] != $user['id'] && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "You can only delete your own messages"));
        exit();
    }
    
    $query = "DELETE FROM direct_messages WHERE id = :id";
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
