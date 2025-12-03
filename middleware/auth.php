<?php
// TaskMesh - Authentication Middleware

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/database.php';

// Polyfill for getallheaders() if not available (CLI, nginx, etc.)
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

function authenticate() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(array("error" => "No authorization header provided"));
        exit();
    }

    $authHeader = $headers['Authorization'];
    $arr = explode(" ", $authHeader);

    if (count($arr) !== 2 || $arr[0] !== 'Bearer') {
        http_response_code(401);
        echo json_encode(array("error" => "Invalid authorization format"));
        exit();
    }

    $jwt = $arr[1];
    $decoded = JWT::decode($jwt);

    if (!$decoded) {
        http_response_code(401);
        echo json_encode(array("error" => "Invalid or expired token"));
        exit();
    }

    // Verify user still exists and is active
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.avatar, u.is_active, u.manager_id,
              CONCAT(m.first_name, ' ', m.last_name) as manager_name
              FROM users u
              LEFT JOIN users m ON u.manager_id = m.id
              WHERE u.id = :id AND u.is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $decoded['user_id']);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(401);
        echo json_encode(array("error" => "User not found or inactive"));
        exit();
    }

    $user = $stmt->fetch();
    return $user;
}

function requireAdmin() {
    $user = authenticate();
    
    if ($user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "Admin access required"));
        exit();
    }

    return $user;
}

function requireManager() {
    $user = authenticate();
    
    if ($user['role'] !== 'ADMIN' && $user['role'] !== 'MANAGER') {
        http_response_code(403);
        echo json_encode(array("error" => "Manager or Admin access required"));
        exit();
    }

    return $user;
}

// Alias for authenticate() - used by newer API files
function verifyJWT() {
    return authenticate();
}
?>