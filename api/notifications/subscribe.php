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
        
        if (!isset($input['endpoint']) || !isset($input['keys'])) {
            throw new Exception('Invalid subscription data');
        }

        $endpoint = $input['endpoint'];
        $auth = $input['keys']['auth'];
        $p256dh = $input['keys']['p256dh'];

        // Check if subscription already exists
        $query = "SELECT id FROM push_subscriptions 
                  WHERE user_id = :user_id AND endpoint = :endpoint";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':endpoint', $endpoint);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Update existing subscription
            $query = "UPDATE push_subscriptions 
                      SET auth_key = :auth, p256dh_key = :p256dh, is_active = 1, last_used = NOW()
                      WHERE user_id = :user_id AND endpoint = :endpoint";
        } else {
            // Insert new subscription
            $query = "INSERT INTO push_subscriptions (user_id, endpoint, auth_key, p256dh_key) 
                      VALUES (:user_id, :endpoint, :auth, :p256dh)";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':endpoint', $endpoint);
        $stmt->bindParam(':auth', $auth);
        $stmt->bindParam(':p256dh', $p256dh);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Push subscription saved'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// GET - Retrieve user's subscriptions
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT id, endpoint, created_at, last_used, is_active 
                  FROM push_subscriptions 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->execute();

        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'subscriptions' => $subscriptions
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// DELETE - Remove subscription
else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['endpoint'])) {
            throw new Exception('Endpoint required');
        }

        $query = "DELETE FROM push_subscriptions 
                  WHERE user_id = :user_id AND endpoint = :endpoint";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':endpoint', $input['endpoint']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Subscription removed'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
