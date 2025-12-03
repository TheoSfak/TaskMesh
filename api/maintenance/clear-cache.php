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

// Only admins can clear cache
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cacheDir = __DIR__ . '/../../temp/cache';
        $filesDeleted = 0;

        // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Clear cache files
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $filesDeleted++;
            }
        }

        // Update last cache clear timestamp
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "UPDATE system_settings 
                  SET setting_value = NOW(), updated_by = :user_id 
                  WHERE setting_key = 'last_cache_clear'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $auth['user']['id']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => "$filesDeleted cache files cleared successfully"
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to clear cache: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
