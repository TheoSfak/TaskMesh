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

// Only admins can restore backups
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if file was uploaded
        if (!isset($_FILES['backup']) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No backup file uploaded');
        }

        $file = $_FILES['backup'];

        // Validate file type
        $allowedTypes = ['text/plain', 'application/sql', 'application/x-sql'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Also check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'sql' && !in_array($mimeType, $allowedTypes)) {
            throw new Exception('Invalid file type. Only SQL files are allowed.');
        }

        // Read SQL file
        $sqlContent = file_get_contents($file['tmp_name']);
        if ($sqlContent === false) {
            throw new Exception('Failed to read backup file');
        }

        // Execute restoration
        $database = new Database();
        $db = $database->getConnection();

        // Disable foreign key checks temporarily
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Execute SQL statements
        $statements = explode(';', $sqlContent);
        $executed = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $db->exec($statement);
                $executed++;
            }
        }

        // Re-enable foreign key checks
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Update last restore timestamp
        $query = "UPDATE system_settings 
                  SET setting_value = NOW(), updated_by = :user_id 
                  WHERE setting_key = 'last_restore'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $auth['user']['id']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => "Database restored successfully ($executed statements executed)"
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to restore backup: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
