<?php
// TaskMesh - User Registration API

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("error" => "Method not allowed"));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// Validation
if (!isset($data->email) || !isset($data->password) || !isset($data->first_name) || !isset($data->last_name)) {
    http_response_code(400);
    echo json_encode(array("error" => "Missing required fields"));
    exit();
}

if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(array("error" => "Invalid email format"));
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Load password policies from system settings
$query = "SELECT setting_key, setting_value, setting_type FROM system_settings 
          WHERE setting_key IN ('password_min_length', 'password_require_uppercase', 'password_require_lowercase', 'password_require_numbers', 'password_require_special', 'registration_mode', 'default_user_role')";
$stmt = $db->query($query);
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $value = $row['setting_value'];
    if ($row['setting_type'] === 'integer') {
        $value = (int)$value;
    } elseif ($row['setting_type'] === 'boolean') {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    $settings[$row['setting_key']] = $value;
}

// Check registration mode
$registrationMode = $settings['registration_mode'] ?? 'open';
if ($registrationMode === 'disabled') {
    http_response_code(403);
    echo json_encode(array("error" => "User registration is currently disabled"));
    exit();
}

// Validate password policies
$minLength = $settings['password_min_length'] ?? 8;
if (strlen($data->password) < $minLength) {
    http_response_code(400);
    echo json_encode(array("error" => "Password must be at least $minLength characters"));
    exit();
}

if (($settings['password_require_uppercase'] ?? false) && !preg_match('/[A-Z]/', $data->password)) {
    http_response_code(400);
    echo json_encode(array("error" => "Password must contain at least one uppercase letter"));
    exit();
}

if (($settings['password_require_lowercase'] ?? false) && !preg_match('/[a-z]/', $data->password)) {
    http_response_code(400);
    echo json_encode(array("error" => "Password must contain at least one lowercase letter"));
    exit();
}

if (($settings['password_require_numbers'] ?? false) && !preg_match('/[0-9]/', $data->password)) {
    http_response_code(400);
    echo json_encode(array("error" => "Password must contain at least one number"));
    exit();
}

if (($settings['password_require_special'] ?? false) && !preg_match('/[^A-Za-z0-9]/', $data->password)) {
    http_response_code(400);
    echo json_encode(array("error" => "Password must contain at least one special character"));
    exit();
}

// Check if email already exists
$query = "SELECT id FROM users WHERE email = :email";
$stmt = $db->prepare($query);
$stmt->bindParam(":email", $data->email);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    http_response_code(409);
    echo json_encode(array("error" => "Email already registered"));
    exit();
}

// Hash password
$password_hash = password_hash($data->password, PASSWORD_BCRYPT, ['cost' => 12]);

// Insert user with default role from settings
$query = "INSERT INTO users (email, password, first_name, last_name, role, manager_id) VALUES (:email, :password, :first_name, :last_name, :role, :manager_id)";
$stmt = $db->prepare($query);

// Use default role from settings if not provided
$defaultRole = $settings['default_user_role'] ?? 'MEMBER';
$role = isset($data->role) ? $data->role : $defaultRole;
if (!in_array($role, ['ADMIN', 'MANAGER', 'MEMBER'])) {
    $role = $defaultRole;
}

// Only set manager_id for MEMBER role
$manager_id = null;
if ($role === 'MEMBER' && isset($data->manager_id) && !empty($data->manager_id)) {
    $manager_id = $data->manager_id;
}

$stmt->bindParam(":email", $data->email);
$stmt->bindParam(":password", $password_hash);
$stmt->bindParam(":first_name", $data->first_name);
$stmt->bindParam(":last_name", $data->last_name);
$stmt->bindParam(":role", $role);
$stmt->bindParam(":manager_id", $manager_id);

if ($stmt->execute()) {
    $user_id = $db->lastInsertId();
    
    // Generate JWT token
    $token = JWT::encode(array("user_id" => $user_id));

    // Get user data
    $query = "SELECT id, email, first_name, last_name, role, avatar, is_active, created_at FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $user_id);
    $stmt->execute();
    $user = $stmt->fetch();

    http_response_code(201);
    echo json_encode(array(
        "message" => "User registered successfully",
        "token" => $token,
        "user" => $user
    ));
} else {
    http_response_code(500);
    echo json_encode(array("error" => "Failed to register user"));
}
?>