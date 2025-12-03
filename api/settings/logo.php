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

// Only admins can upload logo
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['logo'])) {
            throw new Exception('No file uploaded');
        }

        $file = $_FILES['logo'];
        
        // Validate file
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Only PNG and JPG files are allowed');
        }

        // Max 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('File size must be less than 2MB');
        }

        // Create uploads directory if not exists
        $uploadDir = __DIR__ . '/../../uploads/logo/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to move uploaded file');
        }

        // Delete old logo if exists
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'logo_path'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $oldLogo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($oldLogo && $oldLogo['setting_value']) {
            $oldPath = __DIR__ . '/../../' . $oldLogo['setting_value'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Save path to database
        $logoPath = 'uploads/logo/' . $filename;
        $query = "UPDATE system_settings 
                  SET setting_value = :path, updated_by = :user_id, updated_at = NOW()
                  WHERE setting_key = 'logo_path'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':path', $logoPath);
        $stmt->bindParam(':user_id', $auth['user']['id']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'logo_path' => $logoPath,
            'message' => 'Logo uploaded successfully'
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
