<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Verify JWT token
$user = verifyJWT();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get filters
    $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($team_id) {
        $where[] = "t.team_id = ?";
        $params[] = $team_id;
    }
    
    if ($user_id) {
        $where[] = "EXISTS (
            SELECT 1 FROM task_assignments ta 
            WHERE ta.task_id = t.id AND ta.user_id = ?
        )";
        $params[] = $user_id;
    }
    
    if ($start_date) {
        $where[] = "(t.deadline IS NULL OR t.deadline >= ?)";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $where[] = "(t.deadline IS NULL OR t.deadline <= ?)";
        $params[] = $end_date;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get tasks with all related data
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.title,
            t.description,
            t.status,
            t.priority,
            t.deadline,
            t.created_at,
            t.team_id,
            t.creator_id,
            tm.name as team_name,
            tm.color as team_color,
            u.first_name as creator_first_name,
            u.last_name as creator_last_name,
            (SELECT COUNT(*) FROM subtasks WHERE task_id = t.id) as total_subtasks,
            (SELECT COUNT(*) FROM subtasks WHERE task_id = t.id AND status = 'COMPLETED') as completed_subtasks
        FROM tasks t
        LEFT JOIN teams tm ON tm.id = t.team_id
        LEFT JOIN users u ON u.id = t.creator_id
        $whereClause
        ORDER BY t.deadline ASC, t.created_at ASC
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Get assignees for each task
    $stmt = $pdo->query("
        SELECT 
            ta.task_id,
            u.id,
            u.first_name,
            u.last_name,
            u.avatar
        FROM task_assignments ta
        JOIN users u ON u.id = ta.user_id
    ");
    $assigneesData = $stmt->fetchAll();
    
    $assignees = [];
    foreach ($assigneesData as $row) {
        if (!isset($assignees[$row['task_id']])) {
            $assignees[$row['task_id']] = [];
        }
        $assignees[$row['task_id']][] = [
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'avatar' => $row['avatar']
        ];
    }
    
    // Get dependencies
    $stmt = $pdo->query("
        SELECT 
            td.task_id,
            td.depends_on_task_id,
            td.dependency_type,
            t.title as depends_on_task_title
        FROM task_dependencies td
        JOIN tasks t ON t.id = td.depends_on_task_id
    ");
    $dependenciesData = $stmt->fetchAll();
    
    $dependencies = [];
    foreach ($dependenciesData as $row) {
        if (!isset($dependencies[$row['task_id']])) {
            $dependencies[$row['task_id']] = [];
        }
        $dependencies[$row['task_id']][] = [
            'depends_on_task_id' => $row['depends_on_task_id'],
            'depends_on_task_title' => $row['depends_on_task_title'],
            'dependency_type' => $row['dependency_type']
        ];
    }
    
    // Get milestones
    $milestones = [];
    if ($team_id) {
        // Get milestones for specific team
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name
            FROM milestones m
            JOIN users u ON u.id = m.created_by
            WHERE m.team_id = ?
            ORDER BY m.target_date ASC
        ");
        $stmt->execute([$team_id]);
        $milestones = $stmt->fetchAll();
    } else {
        // Get all milestones when no team filter
        $stmt = $pdo->query("
            SELECT 
                m.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                t.name as team_name
            FROM milestones m
            JOIN users u ON u.id = m.created_by
            LEFT JOIN teams t ON t.id = m.team_id
            ORDER BY m.target_date ASC
        ");
        $milestones = $stmt->fetchAll();
    }
    
    // Process tasks for timeline
    $timelineTasks = [];
    foreach ($tasks as $task) {
        $task_id = $task['id'];
        
        // Calculate progress
        $progress = 0;
        if ($task['total_subtasks'] > 0) {
            $progress = round(($task['completed_subtasks'] / $task['total_subtasks']) * 100);
        } elseif ($task['status'] === 'COMPLETED') {
            $progress = 100;
        } elseif ($task['status'] === 'IN_PROGRESS' || $task['status'] === 'IN_REVIEW') {
            $progress = 50;
        }
        
        // Calculate start and end dates
        $created = new DateTime($task['created_at']);
        $deadline = $task['deadline'] ? new DateTime($task['deadline']) : null;
        
        // If no deadline, estimate 7 days from creation
        if (!$deadline) {
            $deadline = clone $created;
            $deadline->modify('+7 days');
        }
        
        // Calculate duration
        $interval = $created->diff($deadline);
        $duration = $interval->days;
        
        $timelineTasks[] = [
            'id' => $task_id,
            'title' => $task['title'],
            'description' => $task['description'],
            'status' => $task['status'],
            'priority' => $task['priority'],
            'start_date' => $created->format('Y-m-d'),
            'end_date' => $deadline->format('Y-m-d'),
            'duration' => $duration,
            'progress' => $progress,
            'team_id' => $task['team_id'],
            'team_name' => $task['team_name'],
            'team_color' => $task['team_color'],
            'creator' => [
                'id' => $task['creator_id'],
                'name' => $task['creator_first_name'] . ' ' . $task['creator_last_name']
            ],
            'assignees' => $assignees[$task_id] ?? [],
            'dependencies' => $dependencies[$task_id] ?? [],
            'total_subtasks' => (int)$task['total_subtasks'],
            'completed_subtasks' => (int)$task['completed_subtasks']
        ];
    }
    
    echo json_encode([
        'tasks' => $timelineTasks,
        'milestones' => $milestones,
        'filters' => [
            'team_id' => $team_id,
            'user_id' => $user_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
