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
    // Get team_id filter if provided
    $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
    
    // Get all tasks with their dependencies
    $whereClause = $team_id ? 'WHERE t.team_id = ?' : '';
    $params = $team_id ? [$team_id] : [];
    
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.title,
            t.status,
            t.priority,
            t.deadline,
            t.created_at,
            t.team_id
        FROM tasks t
        $whereClause
        ORDER BY t.created_at ASC
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Build adjacency list for the graph
    $graph = [];
    $inDegree = [];
    
    foreach ($tasks as $task) {
        $task_id = $task['id'];
        $graph[$task_id] = [];
        $inDegree[$task_id] = 0;
    }
    
    // Get all dependencies
    $stmt = $pdo->query("
        SELECT task_id, depends_on_task_id 
        FROM task_dependencies
    ");
    $dependencies = $stmt->fetchAll();
    
    foreach ($dependencies as $dep) {
        $from = $dep['depends_on_task_id'];
        $to = $dep['task_id'];
        
        // Only include if both tasks are in our filtered set
        if (isset($graph[$from]) && isset($graph[$to])) {
            $graph[$from][] = $to;
            $inDegree[$to]++;
        }
    }
    
    // Calculate critical path using topological sort + longest path
    $criticalPath = calculateCriticalPath($graph, $inDegree, $tasks);
    
    echo json_encode([
        'critical_path' => $criticalPath['path'],
        'task_ids' => $criticalPath['task_ids'],
        'total_duration' => $criticalPath['duration'],
        'estimated_completion' => $criticalPath['completion_date']
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Calculate the critical path using topological sort and longest path algorithm
 * The critical path is the longest path through the dependency graph
 */
function calculateCriticalPath($graph, $inDegree, $tasks) {
    // Create a map of task_id to task data
    $taskMap = [];
    foreach ($tasks as $task) {
        $taskMap[$task['id']] = $task;
    }
    
    // Calculate duration for each task (days until deadline from creation)
    $duration = [];
    foreach ($tasks as $task) {
        $task_id = $task['id'];
        if ($task['deadline']) {
            $start = new DateTime($task['created_at']);
            $end = new DateTime($task['deadline']);
            $interval = $start->diff($end);
            $duration[$task_id] = max(1, $interval->days); // At least 1 day
        } else {
            $duration[$task_id] = 7; // Default 7 days if no deadline
        }
    }
    
    // Topological sort using Kahn's algorithm
    $queue = [];
    $tempInDegree = $inDegree;
    
    // Find all nodes with no incoming edges
    foreach ($tempInDegree as $node => $degree) {
        if ($degree === 0) {
            $queue[] = $node;
        }
    }
    
    $topOrder = [];
    while (!empty($queue)) {
        $node = array_shift($queue);
        $topOrder[] = $node;
        
        foreach ($graph[$node] as $neighbor) {
            $tempInDegree[$neighbor]--;
            if ($tempInDegree[$neighbor] === 0) {
                $queue[] = $neighbor;
            }
        }
    }
    
    // Calculate longest path (critical path)
    $dist = [];
    $parent = [];
    
    foreach (array_keys($graph) as $node) {
        $dist[$node] = 0;
        $parent[$node] = null;
    }
    
    // Process nodes in topological order
    foreach ($topOrder as $node) {
        foreach ($graph[$node] as $neighbor) {
            $newDist = $dist[$node] + $duration[$node];
            if ($newDist > $dist[$neighbor]) {
                $dist[$neighbor] = $newDist;
                $parent[$neighbor] = $node;
            }
        }
    }
    
    // Find the node with maximum distance (end of critical path)
    $maxDist = 0;
    $endNode = null;
    
    foreach ($dist as $node => $d) {
        $totalDist = $d + $duration[$node];
        if ($totalDist > $maxDist) {
            $maxDist = $totalDist;
            $endNode = $node;
        }
    }
    
    // Reconstruct the critical path
    $path = [];
    $taskIds = [];
    $current = $endNode;
    
    while ($current !== null) {
        array_unshift($path, [
            'task_id' => $current,
            'title' => $taskMap[$current]['title'],
            'duration' => $duration[$current],
            'status' => $taskMap[$current]['status'],
            'priority' => $taskMap[$current]['priority'],
            'deadline' => $taskMap[$current]['deadline']
        ]);
        array_unshift($taskIds, $current);
        $current = $parent[$current];
    }
    
    // Calculate estimated completion date
    $completionDate = null;
    if (!empty($path)) {
        $startDate = new DateTime($path[0]['deadline'] ?? 'now');
        $completionDate = clone $startDate;
        $completionDate->modify("+{$maxDist} days");
    }
    
    return [
        'path' => $path,
        'task_ids' => $taskIds,
        'duration' => $maxDist,
        'completion_date' => $completionDate ? $completionDate->format('Y-m-d') : null
    ];
}
