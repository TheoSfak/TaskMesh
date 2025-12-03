<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Verify authentication (authenticate() exits on failure, returns user on success)
$user = authenticate();

// Only admins can manage system settings
if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// GET - Fetch all system settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $category = $_GET['category'] ?? null;
        
        $query = "SELECT setting_key, setting_value, setting_type, category, description 
                  FROM system_settings";
        
        if ($category) {
            $query .= " WHERE category = :category";
        }
        
        $query .= " ORDER BY category, setting_key";
        
        $stmt = $db->prepare($query);
        if ($category) {
            $stmt->bindParam(':category', $category);
        }
        $stmt->execute();
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Convert value based on type
            $value = $row['setting_value'];
            switch ($row['setting_type']) {
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = $value === 'true' || $value === '1';
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            if ($category) {
                $settings[$row['setting_key']] = $value;
            } else {
                if (!isset($settings[$row['category']])) {
                    $settings[$row['category']] = [];
                }
                $settings[$row['category']][$row['setting_key']] = $value;
            }
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch settings: ' . $e->getMessage()]);
    }
}

// POST - Update system settings
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request format']);
            exit;
        }
        
        $db->beginTransaction();
        
        $updated = 0;
        foreach ($data['settings'] as $key => $value) {
            // Get setting type
            $typeQuery = "SELECT setting_type FROM system_settings WHERE setting_key = :key";
            $typeStmt = $db->prepare($typeQuery);
            $typeStmt->bindParam(':key', $key);
            $typeStmt->execute();
            $typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$typeRow) {
                continue; // Skip unknown settings
            }
            
            // Convert value to string for storage
            $stringValue = $value;
            switch ($typeRow['setting_type']) {
                case 'boolean':
                    $stringValue = $value ? 'true' : 'false';
                    break;
                case 'json':
                    $stringValue = json_encode($value);
                    break;
                default:
                    $stringValue = (string)$value;
            }
            
            $query = "UPDATE system_settings 
                      SET setting_value = :value, 
                          updated_by = :user_id, 
                          updated_at = NOW()
                      WHERE setting_key = :key";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':value', $stringValue);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            
            $updated += $stmt->rowCount();
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "$updated settings updated successfully"
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update settings: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
