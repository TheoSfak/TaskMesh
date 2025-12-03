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

try {
    switch($method) {
        case 'GET':
            // Get dependencies for a task or all tasks
            if (isset($_GET['task_id'])) {
                $task_id = intval($_GET['task_id']);
                
                // Get dependencies where this task depends on others
                $stmt = $pdo->prepare("
                    SELECT 
                        td.id,
                        td.task_id,
                        td.depends_on_task_id,
                        td.dependency_type,
                        td.created_at,
                        t.title as depends_on_task_title,
                        t.status as depends_on_task_status,
                        t.deadline as depends_on_task_deadline
                    FROM task_dependencies td
                    JOIN tasks t ON t.id = td.depends_on_task_id
                    WHERE td.task_id = ?
                ");
                $stmt->execute([$task_id]);
                $dependencies = $stmt->fetchAll();
                
                // Get tasks that depend on this task
                $stmt = $pdo->prepare("
                    SELECT 
                        td.id,
                        td.task_id,
                        td.depends_on_task_id,
                        td.dependency_type,
                        td.created_at,
                        t.title as task_title,
                        t.status as task_status,
                        t.deadline as task_deadline
                    FROM task_dependencies td
                    JOIN tasks t ON t.id = td.task_id
                    WHERE td.depends_on_task_id = ?
                ");
                $stmt->execute([$task_id]);
                $dependents = $stmt->fetchAll();
                
                echo json_encode([
                    'dependencies' => $dependencies,
                    'dependents' => $dependents
                ]);
            } else {
                // Get all dependencies
                $stmt = $pdo->query("
                    SELECT 
                        td.*,
                        t1.title as task_title,
                        t2.title as depends_on_task_title
                    FROM task_dependencies td
                    JOIN tasks t1 ON t1.id = td.task_id
                    JOIN tasks t2 ON t2.id = td.depends_on_task_id
                    ORDER BY td.created_at DESC
                ");
                $dependencies = $stmt->fetchAll();
                echo json_encode($dependencies);
            }
            break;
            
        case 'POST':
            // Add a new dependency
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['task_id']) || !isset($data['depends_on_task_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'task_id and depends_on_task_id are required']);
                exit;
            }
            
            $task_id = intval($data['task_id']);
            $depends_on_task_id = intval($data['depends_on_task_id']);
            $dependency_type = $data['dependency_type'] ?? 'must_finish_before';
            
            // Validate tasks exist
            $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id IN (?, ?)");
            $stmt->execute([$task_id, $depends_on_task_id]);
            if ($stmt->rowCount() < 2) {
                http_response_code(404);
                echo json_encode(['error' => 'One or both tasks not found']);
                exit;
            }
            
            // Check if same task
            if ($task_id === $depends_on_task_id) {
                http_response_code(400);
                echo json_encode(['error' => 'A task cannot depend on itself']);
                exit;
            }
            
            // Check for circular dependency
            if (hasCircularDependency($pdo, $task_id, $depends_on_task_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'This would create a circular dependency']);
                exit;
            }
            
            // Check if dependency already exists
            $stmt = $pdo->prepare("
                SELECT id FROM task_dependencies 
                WHERE task_id = ? AND depends_on_task_id = ?
            ");
            $stmt->execute([$task_id, $depends_on_task_id]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'This dependency already exists']);
                exit;
            }
            
            // Insert dependency
            $stmt = $pdo->prepare("
                INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$task_id, $depends_on_task_id, $dependency_type]);
            
            // Get the created dependency
            $dependency_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("
                SELECT 
                    td.*,
                    t.title as depends_on_task_title,
                    t.status as depends_on_task_status,
                    t.deadline as depends_on_task_deadline
                FROM task_dependencies td
                JOIN tasks t ON t.id = td.depends_on_task_id
                WHERE td.id = ?
            ");
            $stmt->execute([$dependency_id]);
            $dependency = $stmt->fetch();
            
            http_response_code(201);
            echo json_encode($dependency);
            break;
            
        case 'DELETE':
            // Delete a dependency
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Dependency ID is required']);
                exit;
            }
            
            $dependency_id = intval($_GET['id']);
            
            // Check if dependency exists
            $stmt = $pdo->prepare("SELECT id FROM task_dependencies WHERE id = ?");
            $stmt->execute([$dependency_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Dependency not found']);
                exit;
            }
            
            // Delete dependency
            $stmt = $pdo->prepare("DELETE FROM task_dependencies WHERE id = ?");
            $stmt->execute([$dependency_id]);
            
            echo json_encode(['message' => 'Dependency deleted successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Check if adding a dependency would create a circular dependency
 * Uses depth-first search to detect cycles
 */
function hasCircularDependency($pdo, $task_id, $depends_on_task_id) {
    $visited = [];
    return checkCycle($pdo, $depends_on_task_id, $task_id, $visited);
}

function checkCycle($pdo, $current_task, $target_task, &$visited) {
    if ($current_task === $target_task) {
        return true; // Cycle detected
    }
    
    if (in_array($current_task, $visited)) {
        return false; // Already visited this node
    }
    
    $visited[] = $current_task;
    
    // Get all tasks that current_task depends on
    $stmt = $pdo->prepare("
        SELECT depends_on_task_id 
        FROM task_dependencies 
        WHERE task_id = ?
    ");
    $stmt->execute([$current_task]);
    $dependencies = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($dependencies as $dep_task_id) {
        if (checkCycle($pdo, $dep_task_id, $target_task, $visited)) {
            return true;
        }
    }
    
    return false;
}
