<?php
// TaskMesh - User Login API

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
if (!isset($data->email) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(array("error" => "Email and password are required"));
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user by email
$query = "SELECT id, email, password, first_name, last_name, role, avatar, is_active FROM users WHERE email = :email";
$stmt = $db->prepare($query);
$stmt->bindParam(":email", $data->email);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    http_response_code(401);
    echo json_encode(array("error" => "Invalid email or password"));
    exit();
}

$user = $stmt->fetch();

// Check if user is active
if (!$user['is_active']) {
    http_response_code(403);
    echo json_encode(array("error" => "Account is inactive"));
    exit();
}

// Verify password
if (!password_verify($data->password, $user['password'])) {
    http_response_code(401);
    echo json_encode(array("error" => "Invalid email or password"));
    exit();
}

// Generate JWT token
$token = JWT::encode(array("user_id" => $user['id']));

// Remove password from response
unset($user['password']);

http_response_code(200);
echo json_encode(array(
    "message" => "Login successful",
    "token" => $token,
    "user" => $user
));
?>