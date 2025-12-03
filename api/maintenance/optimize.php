<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Verify authentication
$auth = authenticate();
if (!$auth['success']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only admins can optimize
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Get all tables
        $query = "SHOW TABLES";
        $stmt = $db->query($query);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $optimized = 0;
        foreach ($tables as $table) {
            $db->exec("OPTIMIZE TABLE `$table`");
            $optimized++;
        }

        // Update last optimization timestamp
        $query = "UPDATE system_settings 
                  SET setting_value = NOW(), updated_by = :user_id 
                  WHERE setting_key = 'last_optimization'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $auth['user']['id']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => "$optimized tables optimized successfully"
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to optimize database: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
