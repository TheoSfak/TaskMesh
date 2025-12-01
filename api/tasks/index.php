<?php
// TaskMesh - Tasks API (GET all with filters, POST create)
// Supports multiple assignees per task

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/PHPMailer.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Helper function to get assignees for tasks
function getTaskAssignees($db, $taskIds) {
    if (empty($taskIds)) return [];
    
    $placeholders = str_repeat('?,', count($taskIds) - 1) . '?';
    $query = "SELECT ta.task_id, ta.user_id, u.first_name, u.last_name, u.avatar, u.email
              FROM task_assignees ta
              JOIN users u ON ta.user_id = u.id
              WHERE ta.task_id IN ($placeholders)
              ORDER BY ta.assigned_at";
    $stmt = $db->prepare($query);
    $stmt->execute($taskIds);
    return $stmt->fetchAll();
}

// GET - Get all tasks with filters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $team_id = isset($_GET['team_id']) ? $_GET['team_id'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $priority = isset($_GET['priority']) ? $_GET['priority'] : null;
    $assignee_id = isset($_GET['assignee_id']) ? $_GET['assignee_id'] : null;
    
    $query = "SELECT DISTINCT t.*, 
              u2.first_name as creator_first_name, 
              u2.last_name as creator_last_name,
              team.name as team_name,
              team.color as team_color
              FROM tasks t
              LEFT JOIN users u2 ON t.creator_id = u2.id
              LEFT JOIN teams team ON t.team_id = team.id
              LEFT JOIN task_assignees ta ON t.id = ta.task_id";
    
    $params = [];
    
    // Admin sees all tasks, regular users see only their tasks
    if ($user['role'] !== 'ADMIN') {
        $query .= " WHERE (t.creator_id = :user_id2 
                    OR ta.user_id = :user_id
                    OR t.team_id IN (
                      SELECT team_id FROM team_members WHERE user_id = :user_id3
                  ))";
        $params[':user_id'] = $user['id'];
        $params[':user_id2'] = $user['id'];
        $params[':user_id3'] = $user['id'];
    }
    
    // Track if WHERE clause exists
    $hasWhere = ($user['role'] !== 'ADMIN');
    
    if ($team_id) {
        $query .= ($hasWhere ? " AND" : " WHERE") . " t.team_id = :team_id";
        $hasWhere = true;
        $params[':team_id'] = $team_id;
    }
    if ($status) {
        $query .= ($hasWhere ? " AND" : " WHERE") . " t.status = :status";
        $hasWhere = true;
        $params[':status'] = $status;
    }
    if ($priority) {
        $query .= ($hasWhere ? " AND" : " WHERE") . " t.priority = :priority";
        $hasWhere = true;
        $params[':priority'] = $priority;
    }
    if ($assignee_id) {
        $query .= ($hasWhere ? " AND" : " WHERE") . " ta.user_id = :assignee_id";
        $hasWhere = true;
        $params[':assignee_id'] = $assignee_id;
    }
    
    $query .= " ORDER BY t.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Get all task IDs
    $taskIds = array_column($tasks, 'id');
    
    // Get all assignees for these tasks
    $assignees = getTaskAssignees($db, $taskIds);
    
    // Group assignees by task_id
    $assigneesByTask = [];
    foreach ($assignees as $assignee) {
        $taskId = $assignee['task_id'];
        if (!isset($assigneesByTask[$taskId])) {
            $assigneesByTask[$taskId] = [];
        }
        $assigneesByTask[$taskId][] = [
            'id' => $assignee['user_id'],
            'first_name' => $assignee['first_name'],
            'last_name' => $assignee['last_name'],
            'avatar' => $assignee['avatar'],
            'email' => $assignee['email']
        ];
    }
    
    // Add assignees array to each task
    foreach ($tasks as &$task) {
        $task['assignees'] = isset($assigneesByTask[$task['id']]) ? $assigneesByTask[$task['id']] : [];
        // Keep backward compatibility
        if (!empty($task['assignees'])) {
            $task['assignee_id'] = $task['assignees'][0]['id'];
            $task['assignee_first_name'] = $task['assignees'][0]['first_name'];
            $task['assignee_last_name'] = $task['assignees'][0]['last_name'];
            $task['assignee_avatar'] = $task['assignees'][0]['avatar'];
        } else {
            $task['assignee_id'] = null;
            $task['assignee_first_name'] = null;
            $task['assignee_last_name'] = null;
            $task['assignee_avatar'] = null;
        }
    }
    
    http_response_code(200);
    echo json_encode($tasks);
    exit();
}

// POST - Create new task
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->title)) {
        http_response_code(400);
        echo json_encode(array("error" => "Title is required"));
        exit();
    }
    
    $description = isset($data->description) ? $data->description : null;
    $status = isset($data->status) ? $data->status : 'TODO';
    $priority = isset($data->priority) ? $data->priority : 'MEDIUM';
    $deadline = isset($data->deadline) ? $data->deadline : null;
    $team_id = isset($data->team_id) ? $data->team_id : null;
    
    // Support both single assignee_id and multiple assignee_ids
    $assignee_ids = [];
    if (isset($data->assignee_ids) && is_array($data->assignee_ids)) {
        $assignee_ids = array_filter($data->assignee_ids, function($id) { return $id !== '' && $id !== null; });
    } elseif (isset($data->assignee_id) && $data->assignee_id !== '' && $data->assignee_id !== null) {
        $assignee_ids = [$data->assignee_id];
    }
    
    // If deadline is empty string, set to null
    if ($deadline === '') $deadline = null;
    if ($team_id === '') $team_id = null;
    
    // Validate team membership if team_id provided
    if ($team_id) {
        $query = "SELECT * FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->bindParam(":user_id", $user['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(array("error" => "You are not a member of this team"));
            exit();
        }
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Insert task (assignee_id kept for backward compatibility)
        $firstAssigneeId = !empty($assignee_ids) ? $assignee_ids[0] : null;
        $query = "INSERT INTO tasks (title, description, status, priority, deadline, team_id, assignee_id, creator_id) 
                  VALUES (:title, :description, :status, :priority, :deadline, :team_id, :assignee_id, :creator_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":title", $data->title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":priority", $priority);
        $stmt->bindParam(":deadline", $deadline);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->bindParam(":assignee_id", $firstAssigneeId);
        $stmt->bindParam(":creator_id", $user['id']);
        $stmt->execute();
        
        $task_id = $db->lastInsertId();
        
        // Insert all assignees into task_assignees table
        if (!empty($assignee_ids)) {
            $insertAssigneeQuery = "INSERT INTO task_assignees (task_id, user_id, assigned_by) VALUES (:task_id, :user_id, :assigned_by)";
            $insertAssigneeStmt = $db->prepare($insertAssigneeQuery);
            
            foreach ($assignee_ids as $assigneeId) {
                $insertAssigneeStmt->bindParam(":task_id", $task_id);
                $insertAssigneeStmt->bindParam(":user_id", $assigneeId);
                $insertAssigneeStmt->bindParam(":assigned_by", $user['id']);
                $insertAssigneeStmt->execute();
            }
        }
        
        $db->commit();
        
        // Get created task with assignees
        $query = "SELECT t.*, 
                  u2.first_name as creator_first_name, 
                  u2.last_name as creator_last_name
                  FROM tasks t
                  LEFT JOIN users u2 ON t.creator_id = u2.id
                  WHERE t.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $task_id);
        $stmt->execute();
        $task = $stmt->fetch();
        
        // Get assignees for this task
        $assignees = getTaskAssignees($db, [$task_id]);
        $task['assignees'] = [];
        foreach ($assignees as $assignee) {
            $task['assignees'][] = [
                'id' => $assignee['user_id'],
                'first_name' => $assignee['first_name'],
                'last_name' => $assignee['last_name'],
                'avatar' => $assignee['avatar'],
                'email' => $assignee['email']
            ];
        }
        
        // Backward compatibility
        if (!empty($task['assignees'])) {
            $task['assignee_id'] = $task['assignees'][0]['id'];
            $task['assignee_first_name'] = $task['assignees'][0]['first_name'];
            $task['assignee_last_name'] = $task['assignees'][0]['last_name'];
            $task['assignee_avatar'] = $task['assignees'][0]['avatar'];
        }
        
        // Send email notification to all assignees
        foreach ($task['assignees'] as $assignee) {
            EmailService::sendTaskAssigned(
                $assignee['email'],
                $assignee['first_name'] . ' ' . $assignee['last_name'],
                $data->title,
                $task_id,
                $user['first_name'] . ' ' . $user['last_name']
            );
        }
        
        http_response_code(201);
        echo json_encode($task);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(array("error" => "Failed to create task: " . $e->getMessage()));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>