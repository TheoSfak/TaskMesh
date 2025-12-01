<?php
// TaskMesh - Teams API (GET all teams, POST create team, PUT update team, DELETE team)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Handle HTTP method override for PUT/DELETE
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// GET - Get all teams where user is a member (or all teams if admin)
if ($method === 'GET') {
    if ($user['role'] === 'ADMIN') {
        // Admin sees all teams
        $query = "SELECT DISTINCT t.*, 
                  u.first_name as owner_first_name, 
                  u.last_name as owner_last_name,
                  CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                  (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
                  FROM teams t
                  LEFT JOIN users u ON t.owner_id = u.id
                  ORDER BY t.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
    } else {
        // Regular users see only teams they're members of
        $query = "SELECT DISTINCT t.*, 
                  u.first_name as owner_first_name, 
                  u.last_name as owner_last_name,
                  CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                  (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
                  FROM teams t
                  LEFT JOIN team_members tm ON t.id = tm.team_id
                  LEFT JOIN users u ON t.owner_id = u.id
                  WHERE tm.user_id = :user_id OR t.owner_id = :user_id2
                  ORDER BY t.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->bindParam(":user_id2", $user['id']);
        $stmt->execute();
    }
    
    $teams = $stmt->fetchAll();
    
    // Get members for each team
    foreach ($teams as &$team) {
        $query = "SELECT u.id, u.first_name, u.last_name, u.email 
                  FROM team_members tm
                  JOIN users u ON tm.user_id = u.id
                  WHERE tm.team_id = :team_id
                  ORDER BY u.first_name, u.last_name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":team_id", $team['id']);
        $stmt->execute();
        $team['members'] = $stmt->fetchAll();
    }
    
    http_response_code(200);
    echo json_encode($teams);
    exit();
}

// POST - Create new team
if ($method === 'POST') {
    // Check if user can create teams (ADMIN or MANAGER)
    if ($user['role'] !== 'ADMIN' && $user['role'] !== 'MANAGER') {
        http_response_code(403);
        echo json_encode(array("error" => "Only Admins and Managers can create teams"));
        exit();
    }
    
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->name)) {
        http_response_code(400);
        echo json_encode(array("error" => "Team name is required"));
        exit();
    }
    
    $color = isset($data->color) ? $data->color : '#6366f1';
    $description = isset($data->description) ? $data->description : null;
    
    // Insert team
    $query = "INSERT INTO teams (name, description, color, owner_id) VALUES (:name, :description, :color, :owner_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":name", $data->name);
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":color", $color);
    $stmt->bindParam(":owner_id", $user['id']);
    
    if ($stmt->execute()) {
        $team_id = $db->lastInsertId();
        
        // Add owner as team member
        $query = "INSERT INTO team_members (team_id, user_id) VALUES (:team_id, :user_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->execute();
        
        // Get created team with owner name and member count
        $query = "SELECT t.*, 
                  CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                  1 as member_count
                  FROM teams t
                  LEFT JOIN users u ON t.owner_id = u.id
                  WHERE t.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $team_id);
        $stmt->execute();
        $team = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode($team);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to create team"));
    }
    exit();
}

// PUT - Update team
if ($method === 'PUT') {
    $team_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$team_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Team ID is required"));
        exit();
    }
    
    // Check if team exists and user is owner, manager, or admin
    $query = "SELECT * FROM teams WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $team_id);
    $stmt->execute();
    $team = $stmt->fetch();
    
    if (!$team) {
        http_response_code(404);
        echo json_encode(array("error" => "Team not found"));
        exit();
    }
    
    if ($team['owner_id'] != $user['id'] && $user['role'] !== 'ADMIN' && $user['role'] !== 'MANAGER') {
        http_response_code(403);
        echo json_encode(array("error" => "Only team owner, manager, or admin can edit the team"));
        exit();
    }
    
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->name) || empty($data->name)) {
        http_response_code(400);
        echo json_encode(array("error" => "Team name is required"));
        exit();
    }
    
    $name = $data->name;
    $description = isset($data->description) ? $data->description : null;
    $color = isset($data->color) ? $data->color : $team['color'];
    
    // Update team
    $query = "UPDATE teams SET name = :name, description = :description, color = :color WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":color", $color);
    $stmt->bindParam(":id", $team_id);
    
    if ($stmt->execute()) {
        // Get updated team with owner name and member count
        $query = "SELECT t.*, 
                  CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                  (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
                  FROM teams t
                  LEFT JOIN users u ON t.owner_id = u.id
                  WHERE t.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $team_id);
        $stmt->execute();
        $updated_team = $stmt->fetch();
        
        http_response_code(200);
        echo json_encode($updated_team);
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to update team"));
    }
    exit();
}

// DELETE - Delete team
if ($method === 'DELETE') {
    $team_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$team_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Team ID is required"));
        exit();
    }
    
    // Check if team exists and user is owner or admin
    $query = "SELECT * FROM teams WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $team_id);
    $stmt->execute();
    $team = $stmt->fetch();
    
    if (!$team) {
        http_response_code(404);
        echo json_encode(array("error" => "Team not found"));
        exit();
    }
    
    if ($team['owner_id'] != $user['id'] && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "Only team owner or admin can delete the team"));
        exit();
    }
    
    // Delete team (this will cascade delete team_members due to foreign key)
    $query = "DELETE FROM teams WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $team_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "Team deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to delete team"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>