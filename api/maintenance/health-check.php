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

// Only admins can access
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check database connection
    $dbStatus = 'OK';
    try {
        $db->query('SELECT 1');
    } catch (Exception $e) {
        $dbStatus = 'ERROR: ' . $e->getMessage();
    }

    // Check WebSocket server
    $wsStatus = 'Not Running';
    $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'websocket_port'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $port = $stmt->fetchColumn() ?: 8080;
    
    $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
    if ($socket) {
        $wsStatus = 'Running';
        fclose($socket);
    }

    // Check disk space
    $diskFree = disk_free_space(__DIR__);
    $diskTotal = disk_total_space(__DIR__);
    $diskUsed = $diskTotal - $diskFree;
    $diskPercent = round(($diskUsed / $diskTotal) * 100, 1);
    $diskStatus = number_format($diskFree / 1024 / 1024 / 1024, 2) . ' GB free (' . $diskPercent . '% used)';

    echo json_encode([
        'success' => true,
        'health' => [
            'database' => $dbStatus,
            'websocket' => $wsStatus,
            'disk' => $diskStatus
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Health check failed: ' . $e->getMessage()]);
}
?>
