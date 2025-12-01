<?php
// TaskMesh - Email Settings API (Admin only)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Only admin can manage email settings
if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(array("error" => "Only admin can manage email settings"));
    exit();
}

// Handle HTTP method override
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// GET - Retrieve email settings (without password)
if ($method === 'GET') {
    $query = "SELECT id, smtp_host, smtp_port, smtp_username, smtp_from_email, 
              smtp_from_name, smtp_encryption, notifications_enabled, app_base_url,
              updated_at, updated_by
              FROM email_settings 
              ORDER BY id DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Return default settings if none exist
        $settings = array(
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => 'TaskMesh Notifications',
            'smtp_encryption' => 'tls',
            'notifications_enabled' => false,
            'app_base_url' => 'http://localhost/TaskMesh'
        );
    }
    
    // Add flag if password is set (without revealing it)
    $settings['password_is_set'] = !empty($settings['smtp_username']);
    
    http_response_code(200);
    echo json_encode($settings);
    exit();
}

// PUT - Update email settings
if ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    // Get current settings first
    $query = "SELECT * FROM email_settings ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $currentSettings = $stmt->fetch();
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    if (isset($data->smtp_host)) {
        $updates[] = "smtp_host = :smtp_host";
        $params[':smtp_host'] = $data->smtp_host;
    }
    if (isset($data->smtp_port)) {
        $updates[] = "smtp_port = :smtp_port";
        $params[':smtp_port'] = $data->smtp_port;
    }
    if (isset($data->smtp_username)) {
        $updates[] = "smtp_username = :smtp_username";
        $params[':smtp_username'] = $data->smtp_username;
    }
    if (isset($data->smtp_password) && !empty($data->smtp_password)) {
        // Only update password if provided (allows keeping existing)
        $updates[] = "smtp_password = :smtp_password";
        $params[':smtp_password'] = $data->smtp_password;
    }
    if (isset($data->smtp_from_email)) {
        $updates[] = "smtp_from_email = :smtp_from_email";
        $params[':smtp_from_email'] = $data->smtp_from_email;
    }
    if (isset($data->smtp_from_name)) {
        $updates[] = "smtp_from_name = :smtp_from_name";
        $params[':smtp_from_name'] = $data->smtp_from_name;
    }
    if (isset($data->smtp_encryption)) {
        $updates[] = "smtp_encryption = :smtp_encryption";
        $params[':smtp_encryption'] = $data->smtp_encryption;
    }
    if (isset($data->notifications_enabled)) {
        $updates[] = "notifications_enabled = :notifications_enabled";
        $params[':notifications_enabled'] = $data->notifications_enabled ? 1 : 0;
    }
    if (isset($data->app_base_url)) {
        $updates[] = "app_base_url = :app_base_url";
        $params[':app_base_url'] = $data->app_base_url;
    }
    
    $updates[] = "updated_by = :updated_by";
    $params[':updated_by'] = $user['id'];
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(array("error" => "No fields to update"));
        exit();
    }
    
    if ($currentSettings) {
        // Update existing settings
        $query = "UPDATE email_settings SET " . implode(", ", $updates) . " WHERE id = :id";
        $params[':id'] = $currentSettings['id'];
    } else {
        // Insert new settings (shouldn't happen if schema.sql was run)
        $query = "INSERT INTO email_settings SET " . implode(", ", $updates);
    }
    
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($params)) {
        // Return updated settings (without password)
        $query = "SELECT id, smtp_host, smtp_port, smtp_username, smtp_from_email, 
                  smtp_from_name, smtp_encryption, notifications_enabled, app_base_url,
                  updated_at, updated_by
                  FROM email_settings 
                  ORDER BY id DESC 
                  LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetch();
        
        $settings['password_is_set'] = true;
        
        http_response_code(200);
        echo json_encode($settings);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to update email settings"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>
