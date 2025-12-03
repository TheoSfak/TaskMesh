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

// Only admins can create backups
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Get database credentials (hardcoded as they're not exposed as constants)
        $host = 'localhost';
        $dbname = 'taskmesh_db';
        $username = 'root';
        $password = '';

        // Create backup directory
        $backupDir = __DIR__ . '/../../temp/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Generate backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';

        // mysqldump command
        $command = "mysqldump --host=$host --user=$username --password=$password $dbname > $backupFile 2>&1";
        
        // Execute backup
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($backupFile)) {
            // Update last backup timestamp
            $query = "UPDATE system_settings 
                      SET setting_value = NOW(), updated_by = :user_id 
                      WHERE setting_key = 'last_backup'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $auth['user']['id']);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Backup created successfully',
                'file' => 'backup_' . $timestamp . '.sql',
                'size' => filesize($backupFile)
            ]);
        } else {
            throw new Exception('Backup command failed: ' . implode("\n", $output));
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create backup: ' . $e->getMessage()]);
    }
}

else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Download backup file
    $file = isset($_GET['file']) ? basename($_GET['file']) : null;
    
    if (!$file) {
        http_response_code(400);
        echo json_encode(['error' => 'File parameter required']);
        exit;
    }

    $backupFile = __DIR__ . '/../../temp/backups/' . $file;
    
    if (!file_exists($backupFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found']);
        exit;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($backupFile));
    readfile($backupFile);
    exit;
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
