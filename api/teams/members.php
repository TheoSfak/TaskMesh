<?php
// TaskMesh - Team Members API (Add/Remove members)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/PHPMailer.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Handle HTTP method override for DELETE
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// GET - Get team members
if ($method === 'GET') {
    $team_id = isset($_GET['team_id']) ? $_GET['team_id'] : null;
    
    if (!$team_id) {
        http_response_code(400);
        echo json_encode(array("error" => "team_id is required"));
        exit();
    }
    
    // Check if user is a member of the team
    $query = "SELECT COUNT(*) as count FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $team_id);
    $stmt->bindParam(":user_id", $user['id']);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0 && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "You are not a member of this team"));
        exit();
    }
    
    // Get members with their details
    $query = "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar
              FROM team_members tm
              JOIN users u ON tm.user_id = u.id
              WHERE tm.team_id = :team_id
              ORDER BY u.first_name, u.last_name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $team_id);
    $stmt->execute();
    $members = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode($members);
    exit();
}

// POST - Add member to team
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->team_id) || !isset($data->user_id)) {
        http_response_code(400);
        echo json_encode(array("error" => "team_id and user_id are required"));
        exit();
    }
    
    // Check if user is team owner or admin
    $query = "SELECT owner_id FROM teams WHERE id = :team_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $data->team_id);
    $stmt->execute();
    $team = $stmt->fetch();
    
    if (!$team || ($team['owner_id'] != $user['id'] && $user['role'] !== 'ADMIN')) {
        http_response_code(403);
        echo json_encode(array("error" => "Only team owner or admin can add members"));
        exit();
    }
    
    // Check if user exists
    $query = "SELECT id FROM users WHERE id = :user_id AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $data->user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(array("error" => "User not found"));
        exit();
    }
    
    // Add member
    $query = "INSERT INTO team_members (team_id, user_id) VALUES (:team_id, :user_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $data->team_id);
    $stmt->bindParam(":user_id", $data->user_id);
    
    try {
        if ($stmt->execute()) {
            // Send email notification to new member
            $memberQuery = "SELECT u.email, u.first_name, u.last_name, t.name as team_name
                           FROM users u, teams t
                           WHERE u.id = :user_id AND t.id = :team_id";
            $memberStmt = $db->prepare($memberQuery);
            $memberStmt->bindParam(":user_id", $data->user_id);
            $memberStmt->bindParam(":team_id", $data->team_id);
            $memberStmt->execute();
            $memberInfo = $memberStmt->fetch();
            
            if ($memberInfo) {
                EmailService::sendAddedToTeam(
                    $memberInfo['email'],
                    $memberInfo['first_name'] . ' ' . $memberInfo['last_name'],
                    $memberInfo['team_name'],
                    $data->team_id,
                    $user['first_name'] . ' ' . $user['last_name']
                );
            }
            
            http_response_code(201);
            echo json_encode(array("message" => "Member added successfully"));
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode(array("error" => "User is already a member"));
        } else {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to add member"));
        }
    }
    exit();
}

// DELETE - Remove member from team
if ($method === 'DELETE') {
    $team_id = isset($_GET['team_id']) ? $_GET['team_id'] : null;
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    
    if (!$team_id || !$user_id) {
        http_response_code(400);
        echo json_encode(array("error" => "team_id and user_id are required"));
        exit();
    }
    
    // Check if user is team owner or admin
    $query = "SELECT owner_id FROM teams WHERE id = :team_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $team_id);
    $stmt->execute();
    $team = $stmt->fetch();
    
    if (!$team || ($team['owner_id'] != $user['id'] && $user['role'] !== 'ADMIN')) {
        http_response_code(403);
        echo json_encode(array("error" => "Only team owner or admin can remove members"));
        exit();
    }
    
    // Cannot remove owner
    if ($team['owner_id'] == $user_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Cannot remove team owner"));
        exit();
    }
    
    // Remove member
    $query = "DELETE FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":team_id", $team_id);
    $stmt->bindParam(":user_id", $user_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "Member removed successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to remove member"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>