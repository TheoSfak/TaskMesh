<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Set JSON header
header('Content-Type: application/json');

// Verify JWT token
try {
    $user = verifyJWT();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            // Get milestones, optionally filtered by team
            $where = [];
            $params = [];
            
            if (isset($_GET['team_id'])) {
                $where[] = "m.team_id = ?";
                $params[] = intval($_GET['team_id']);
            }
            
            if (isset($_GET['status'])) {
                $where[] = "m.status = ?";
                $params[] = $_GET['status'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    t.name as team_name,
                    t.color as team_color,
                    u.first_name as creator_first_name,
                    u.last_name as creator_last_name,
                    (SELECT COUNT(*) FROM tasks WHERE team_id = m.team_id 
                     AND deadline <= m.target_date) as related_tasks_count,
                    (SELECT COUNT(*) FROM tasks WHERE team_id = m.team_id 
                     AND deadline <= m.target_date AND status = 'COMPLETED') as completed_tasks_count
                FROM milestones m
                JOIN teams t ON t.id = m.team_id
                JOIN users u ON u.id = m.created_by
                $whereClause
                ORDER BY m.target_date ASC
            ");
            $stmt->execute($params);
            $milestones = $stmt->fetchAll();
            
            // Calculate progress percentage
            foreach ($milestones as &$milestone) {
                if ($milestone['related_tasks_count'] > 0) {
                    $milestone['progress'] = round(($milestone['completed_tasks_count'] / $milestone['related_tasks_count']) * 100);
                } else {
                    $milestone['progress'] = 0;
                }
            }
            
            echo json_encode($milestones);
            break;
            
        case 'POST':
            // Create a new milestone
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (!isset($data['team_id']) || !isset($data['title']) || !isset($data['target_date'])) {
                http_response_code(400);
                echo json_encode(['error' => 'team_id, title, and target_date are required']);
                exit;
            }
            
            // Check permissions - only ADMIN and team members can create milestones
            $team_id = intval($data['team_id']);
            
            if ($user['role'] !== 'ADMIN') {
                // Check if user is member of the team
                $stmt = $pdo->prepare("
                    SELECT id FROM team_members 
                    WHERE team_id = ? AND user_id = ?
                ");
                $stmt->execute([$team_id, $user['id']]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'You do not have permission to create milestones for this team']);
                    exit;
                }
            }
            
            // Verify team exists
            $stmt = $pdo->prepare("SELECT id FROM teams WHERE id = ?");
            $stmt->execute([$team_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Team not found']);
                exit;
            }
            
            $title = $data['title'];
            $description = $data['description'] ?? null;
            $target_date = $data['target_date'];
            $status = $data['status'] ?? 'upcoming';
            
            // Insert milestone
            $stmt = $pdo->prepare("
                INSERT INTO milestones (team_id, title, description, target_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$team_id, $title, $description, $target_date, $status, $user['id']]);
            
            // Get the created milestone
            $milestone_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    t.name as team_name,
                    t.color as team_color,
                    u.first_name as creator_first_name,
                    u.last_name as creator_last_name
                FROM milestones m
                JOIN teams t ON t.id = m.team_id
                JOIN users u ON u.id = m.created_by
                WHERE m.id = ?
            ");
            $stmt->execute([$milestone_id]);
            $milestone = $stmt->fetch();
            
            http_response_code(201);
            echo json_encode($milestone);
            break;
            
        case 'PUT':
            // Update a milestone
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Milestone ID is required']);
                exit;
            }
            
            $milestone_id = intval($_GET['id']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Check if milestone exists
            $stmt = $pdo->prepare("SELECT * FROM milestones WHERE id = ?");
            $stmt->execute([$milestone_id]);
            $milestone = $stmt->fetch();
            
            if (!$milestone) {
                http_response_code(404);
                echo json_encode(['error' => 'Milestone not found']);
                exit;
            }
            
            // Check permissions
            if ($user['role'] !== 'ADMIN' && $milestone['created_by'] != $user['id']) {
                // Check if user is member of the team
                $stmt = $pdo->prepare("
                    SELECT id FROM team_members 
                    WHERE team_id = ? AND user_id = ?
                ");
                $stmt->execute([$milestone['team_id'], $user['id']]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'You do not have permission to update this milestone']);
                    exit;
                }
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            if (isset($data['title'])) {
                $updates[] = "title = ?";
                $params[] = $data['title'];
            }
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
            }
            if (isset($data['target_date'])) {
                $updates[] = "target_date = ?";
                $params[] = $data['target_date'];
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }
            
            $params[] = $milestone_id;
            
            $stmt = $pdo->prepare("
                UPDATE milestones 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            // Get updated milestone
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    t.name as team_name,
                    t.color as team_color,
                    u.first_name as creator_first_name,
                    u.last_name as creator_last_name
                FROM milestones m
                JOIN teams t ON t.id = m.team_id
                JOIN users u ON u.id = m.created_by
                WHERE m.id = ?
            ");
            $stmt->execute([$milestone_id]);
            $milestone = $stmt->fetch();
            
            echo json_encode($milestone);
            break;
            
        case 'DELETE':
            // Delete a milestone
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Milestone ID is required']);
                exit;
            }
            
            $milestone_id = intval($_GET['id']);
            
            // Check if milestone exists
            $stmt = $pdo->prepare("SELECT * FROM milestones WHERE id = ?");
            $stmt->execute([$milestone_id]);
            $milestone = $stmt->fetch();
            
            if (!$milestone) {
                http_response_code(404);
                echo json_encode(['error' => 'Milestone not found']);
                exit;
            }
            
            // Check permissions - only ADMIN or creator can delete
            if ($user['role'] !== 'ADMIN' && $milestone['created_by'] != $user['id']) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to delete this milestone']);
                exit;
            }
            
            // Delete milestone
            $stmt = $pdo->prepare("DELETE FROM milestones WHERE id = ?");
            $stmt->execute([$milestone_id]);
            
            echo json_encode(['message' => 'Milestone deleted successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
