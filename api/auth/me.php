<?php
// TaskMesh - Get Current User API

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array("error" => "Method not allowed"));
    exit();
}

$user = authenticate();

http_response_code(200);
echo json_encode(array("user" => $user));
?>