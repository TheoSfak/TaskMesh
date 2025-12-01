<?php
// TaskMesh - Single Task API (GET/:id, PUT/:id, DELETE/:id)
// Supports multiple assignees per task

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/PHPMailer.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Handle HTTP method override for PUT/DELETE
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

$task_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$task_id) {
    http_response_code(400);
    echo json_encode(array("error" => "Task ID is required"));
    exit();
}

// Helper function to get assignees for a task
function getTaskAssignees($db, $taskId) {
    $query = "SELECT ta.task_id, ta.user_id, u.first_name, u.last_name, u.avatar, u.email
              FROM task_assignees ta
              JOIN users u ON ta.user_id = u.id
              WHERE ta.task_id = :task_id
              ORDER BY ta.assigned_at";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $taskId);
    $stmt->execute();
    $assignees = [];
    while ($row = $stmt->fetch()) {
        $assignees[] = [
            'id' => $row['user_id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'avatar' => $row['avatar'],
            'email' => $row['email']
        ];
    }
    return $assignees;
}

// Helper function to get current assignee IDs
function getCurrentAssigneeIds($db, $taskId) {
    $query = "SELECT user_id FROM task_assignees WHERE task_id = :task_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":task_id", $taskId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// GET - Get single task
if ($method === 'GET') {
    $query = "SELECT t.*, 
              u2.first_name as creator_first_name, 
              u2.last_name as creator_last_name,
              u3.first_name as completed_by_first_name,
              u3.last_name as completed_by_last_name,
              team.name as team_name,
              team.color as team_color
              FROM tasks t
              LEFT JOIN users u2 ON t.creator_id = u2.id
              LEFT JOIN users u3 ON t.completed_by = u3.id
              LEFT JOIN teams team ON t.team_id = team.id
              WHERE t.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $task_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(array("error" => "Task not found"));
        exit();
    }
    
    $task = $stmt->fetch();
    
    // Get assignees for this task
    $task['assignees'] = getTaskAssignees($db, $task_id);
    
    // Backward compatibility
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
    
    http_response_code(200);
    echo json_encode($task);
    exit();
}

// PUT - Update task
if ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    // Check if user can update (creator, assignee, or admin)
    $query = "SELECT * FROM tasks WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $task_id);
    $stmt->execute();
    $task = $stmt->fetch();
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(array("error" => "Task not found"));
        exit();
    }
    
    // Get current assignees for permission check
    $currentAssigneeIds = getCurrentAssigneeIds($db, $task_id);
    
    // Check permission: creator, assignee, or admin
    $isAssignee = in_array($user['id'], $currentAssigneeIds);
    if ($task['creator_id'] != $user['id'] && !$isAssignee && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "You don't have permission to update this task"));
        exit();
    }
    
    // Build update query
    $updates = [];
    $params = [':id' => $task_id];
    
    if (isset($data->title)) {
        $updates[] = "title = :title";
        $params[':title'] = $data->title;
    }
    if (isset($data->description)) {
        $updates[] = "description = :description";
        $params[':description'] = $data->description;
    }
    if (isset($data->status)) {
        $updates[] = "status = :status";
        $params[':status'] = $data->status;
        
        // Set completed_at and completed_by if status is COMPLETED
        if ($data->status === 'COMPLETED') {
            $updates[] = "completed_at = NOW()";
            $updates[] = "completed_by = :completed_by";
            $params[':completed_by'] = $user['id'];
        } else {
            $updates[] = "completed_at = NULL";
            $updates[] = "completed_by = NULL";
        }
    }
    if (isset($data->priority)) {
        $updates[] = "priority = :priority";
        $params[':priority'] = $data->priority;
    }
    if (isset($data->deadline)) {
        $updates[] = "deadline = :deadline";
        $params[':deadline'] = $data->deadline;
    }
    
    // Handle assignees update (supports both assignee_id and assignee_ids)
    $newAssigneeIds = null;
    if (isset($data->assignee_ids) && is_array($data->assignee_ids)) {
        $newAssigneeIds = array_filter($data->assignee_ids, function($id) { return $id !== '' && $id !== null; });
    } elseif (isset($data->assignee_id)) {
        if ($data->assignee_id === '' || $data->assignee_id === null) {
            $newAssigneeIds = [];
        } else {
            $newAssigneeIds = [$data->assignee_id];
        }
    }
    
    // Update legacy assignee_id field for backward compatibility
    if ($newAssigneeIds !== null) {
        $firstAssigneeId = !empty($newAssigneeIds) ? $newAssigneeIds[0] : null;
        $updates[] = "assignee_id = :assignee_id";
        $params[':assignee_id'] = $firstAssigneeId;
    }
    
    // Track if status changed to COMPLETED for email notification
    $statusChangedToCompleted = isset($data->status) && $data->status === 'COMPLETED' && $task['status'] !== 'COMPLETED';
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Update task fields
        if (!empty($updates)) {
            $query = "UPDATE tasks SET " . implode(", ", $updates) . " WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
        }
        
        // Update task_assignees table if assignees changed
        if ($newAssigneeIds !== null) {
            // Find added and removed assignees
            $addedAssignees = array_diff($newAssigneeIds, $currentAssigneeIds);
            $removedAssignees = array_diff($currentAssigneeIds, $newAssigneeIds);
            
            // Remove old assignees
            if (!empty($removedAssignees)) {
                $placeholders = str_repeat('?,', count($removedAssignees) - 1) . '?';
                $deleteQuery = "DELETE FROM task_assignees WHERE task_id = ? AND user_id IN ($placeholders)";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteParams = array_merge([$task_id], array_values($removedAssignees));
                $deleteStmt->execute($deleteParams);
            }
            
            // Add new assignees
            if (!empty($addedAssignees)) {
                $insertQuery = "INSERT INTO task_assignees (task_id, user_id, assigned_by) VALUES (:task_id, :user_id, :assigned_by)";
                $insertStmt = $db->prepare($insertQuery);
                
                foreach ($addedAssignees as $assigneeId) {
                    $insertStmt->bindParam(":task_id", $task_id);
                    $insertStmt->bindParam(":user_id", $assigneeId);
                    $insertStmt->bindParam(":assigned_by", $user['id']);
                    $insertStmt->execute();
                }
            }
        }
        
        $db->commit();
        
        // Get updated task
        $query = "SELECT t.*, 
                  u2.first_name as creator_first_name, 
                  u2.last_name as creator_last_name,
                  u3.first_name as completed_by_first_name,
                  u3.last_name as completed_by_last_name
                  FROM tasks t
                  LEFT JOIN users u2 ON t.creator_id = u2.id
                  LEFT JOIN users u3 ON t.completed_by = u3.id
                  WHERE t.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $task_id);
        $stmt->execute();
        $task = $stmt->fetch();
        
        // Get updated assignees
        $task['assignees'] = getTaskAssignees($db, $task_id);
        
        // Backward compatibility
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
        
        // Send email notifications to newly added assignees
        if ($newAssigneeIds !== null && !empty($addedAssignees)) {
            foreach ($task['assignees'] as $assignee) {
                if (in_array($assignee['id'], $addedAssignees)) {
                    EmailService::sendTaskAssigned(
                        $assignee['email'],
                        $assignee['first_name'] . ' ' . $assignee['last_name'],
                        $task['title'],
                        $task_id,
                        $user['first_name'] . ' ' . $user['last_name']
                    );
                }
            }
        }
        
        // Send email notification if task completed
        if ($statusChangedToCompleted) {
            // Get creator (manager) email
            $creatorQuery = "SELECT email, first_name, last_name FROM users WHERE id = :id";
            $creatorStmt = $db->prepare($creatorQuery);
            $creatorStmt->bindParam(":id", $task['creator_id']);
            $creatorStmt->execute();
            $creator = $creatorStmt->fetch();
            
            if ($creator) {
                EmailService::sendTaskCompleted(
                    $creator['email'],
                    $creator['first_name'] . ' ' . $creator['last_name'],
                    $task['title'],
                    $task_id,
                    $user['first_name'] . ' ' . $user['last_name']
                );
            }
        }
        
        http_response_code(200);
        echo json_encode($task);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(array("error" => "Failed to update task: " . $e->getMessage()));
    }
    exit();
}

// DELETE - Delete task
if ($method === 'DELETE') {
    // Check if user can delete (creator or admin)
    $query = "SELECT * FROM tasks WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $task_id);
    $stmt->execute();
    $task = $stmt->fetch();
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(array("error" => "Task not found"));
        exit();
    }
    
    if ($task['creator_id'] != $user['id'] && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "Only task creator or admin can delete"));
        exit();
    }
    
    // Delete task (task_assignees will be deleted automatically due to ON DELETE CASCADE)
    $query = "DELETE FROM tasks WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $task_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "Task deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to delete task"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>