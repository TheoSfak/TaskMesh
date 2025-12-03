<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Verify authentication
$user = authenticate();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $targetUserId = $input['user_id'] ?? null;
        $title = $input['title'] ?? 'TaskMesh Notification';
        $body = $input['body'] ?? '';
        $url = $input['url'] ?? '/TaskMesh/dashboard.html';
        $icon = $input['icon'] ?? '/TaskMesh/icons/icon-192x192.png';
        $data = $input['data'] ?? [];

        if (!$body) {
            throw new Exception('Notification body is required');
        }

        // Add to notification queue
        $query = "INSERT INTO notification_queue 
                  (user_id, title, body, icon, url, data, status) 
                  VALUES (:user_id, :title, :body, :icon, :url, :data, 'pending')";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $targetUserId);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':body', $body);
        $stmt->bindParam(':icon', $icon);
        $stmt->bindParam(':url', $url);
        $dataJson = json_encode($data);
        $stmt->bindParam(':data', $dataJson);
        $stmt->execute();

        $notificationId = $db->lastInsertId();

        // Send push notifications immediately (requires web-push library)
        // For now, just mark as pending for a cron job to process
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification queued',
            'notification_id' => $notificationId
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// GET - Retrieve notification queue status
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Only admins can view queue
        if ($user['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $status = $_GET['status'] ?? 'all';
        
        $query = "SELECT nq.*, u.email, u.first_name, u.last_name 
                  FROM notification_queue nq
                  JOIN users u ON nq.user_id = u.id";
        
        if ($status !== 'all') {
            $query .= " WHERE nq.status = :status";
        }
        
        $query .= " ORDER BY nq.created_at DESC LIMIT 100";
        
        $stmt = $db->prepare($query);
        if ($status !== 'all') {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();

        $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'queue' => $queue
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
