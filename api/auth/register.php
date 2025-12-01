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

if (strlen($data->password) < 8) {
    http_response_code(400);
    echo json_encode(array("error" => "Password must be at least 8 characters"));
    exit();
}

$database = new Database();
$db = $database->getConnection();

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

// Insert user
$query = "INSERT INTO users (email, password, first_name, last_name, role, manager_id) VALUES (:email, :password, :first_name, :last_name, :role, :manager_id)";
$stmt = $db->prepare($query);

$role = isset($data->role) ? $data->role : 'MEMBER';
if (!in_array($role, ['ADMIN', 'MANAGER', 'MEMBER'])) {
    $role = 'MEMBER';
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