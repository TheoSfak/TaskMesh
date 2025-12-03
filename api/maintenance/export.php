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

// Only admins can export data
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $exportData = [
            'export_date' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'data' => []
        ];

        // Tables to export
        $tables = ['users', 'teams', 'team_members', 'tasks', 'task_comments', 'notifications', 'system_settings'];

        foreach ($tables as $table) {
            $query = "SELECT * FROM $table";
            $stmt = $db->query($query);
            $exportData['data'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Create export directory
        $exportDir = __DIR__ . '/../../temp/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Generate export filename
        $timestamp = date('Y-m-d_H-i-s');
        $exportFile = $exportDir . '/export_' . $timestamp . '.json';

        // Write JSON file
        file_put_contents($exportFile, json_encode($exportData, JSON_PRETTY_PRINT));

        // Return file for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="export_' . $timestamp . '.json"');
        header('Content-Length: ' . filesize($exportFile));
        readfile($exportFile);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to export data: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
