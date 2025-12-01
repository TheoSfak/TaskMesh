<?php
// TaskMesh - Users API (Admin: GET all, Update role/status | User: GET profile, Update profile)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle HTTP method override for PUT/DELETE
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// GET - Get all users (admin only) or single user
if ($method === 'GET') {
    $user_id = isset($_GET['id']) ? $_GET['id'] : null;
    $role_filter = isset($_GET['role']) ? $_GET['role'] : null;
    
    if (!$user_id) {
        // Get all users
        $user = authenticate();
        
        // If requesting managers for dropdown (no auth required for public endpoint)
        if ($role_filter === 'MANAGER') {
            $query = "SELECT id, email, first_name, last_name, role, is_active FROM users WHERE role = 'MANAGER' AND is_active = 1 ORDER BY first_name, last_name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            http_response_code(200);
            echo json_encode($users);
            exit();
        }
        
        // For admin: show all users
        // For manager: show only their members
        if ($user['role'] === 'ADMIN') {
            $query = "SELECT id, email, first_name, last_name, role, avatar, is_active, manager_id, created_at FROM users ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
        } else if ($user['role'] === 'MANAGER') {
            // Manager sees only their members
            $query = "SELECT id, email, first_name, last_name, role, avatar, is_active, manager_id, created_at FROM users WHERE manager_id = :manager_id ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":manager_id", $user['id']);
            $stmt->execute();
        } else {
            http_response_code(403);
            echo json_encode(array("error" => "Access denied"));
            exit();
        }
        
        $users = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode($users);
        exit();
    } else {
        // Get single user
        $user = authenticate();
        
        // User can get own profile or admin can get any
        if ($user['id'] != $user_id && $user['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(array("error" => "Access denied"));
            exit();
        }
        
        $query = "SELECT id, email, first_name, last_name, role, avatar, is_active, created_at FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(array("error" => "User not found"));
            exit();
        }
        
        $profile = $stmt->fetch();
        http_response_code(200);
        echo json_encode($profile);
        exit();
    }
}

// PUT - Update user profile or role/status
if ($method === 'PUT') {
    $user = authenticate();
    $user_id = isset($_GET['id']) ? $_GET['id'] : null;
    $action = isset($_GET['action']) ? $_GET['action'] : 'profile';
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(array("error" => "User ID is required"));
        exit();
    }
    
    $data = json_decode(file_get_contents("php://input"));
    
    // Change role (admin only)
    if ($action === 'role') {
        if ($user['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(array("error" => "Admin access required"));
            exit();
        }
        
        if ($user['id'] == $user_id) {
            http_response_code(400);
            echo json_encode(array("error" => "Cannot change your own role"));
            exit();
        }
        
        if (!isset($data->role)) {
            http_response_code(400);
            echo json_encode(array("error" => "Role is required"));
            exit();
        }
        
        $query = "UPDATE users SET role = :role WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":role", $data->role);
        $stmt->bindParam(":id", $user_id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Role updated successfully"));
        } else {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to update role"));
        }
        exit();
    }
    
    // Toggle active status (admin only)
    if ($action === 'status') {
        if ($user['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(array("error" => "Admin access required"));
            exit();
        }
        
        if ($user['id'] == $user_id) {
            http_response_code(400);
            echo json_encode(array("error" => "Cannot change your own status"));
            exit();
        }
        
        $query = "UPDATE users SET is_active = NOT is_active WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $user_id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Status updated successfully"));
        } else {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to update status"));
        }
        exit();
    }
    
    // Reset password (admin only)
    if ($action === 'password') {
        if ($user['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(array("error" => "Admin access required"));
            exit();
        }
        
        if (!isset($data->password)) {
            http_response_code(400);
            echo json_encode(array("error" => "New password is required"));
            exit();
        }
        
        if (strlen($data->password) < 8) {
            http_response_code(400);
            echo json_encode(array("error" => "Password must be at least 8 characters"));
            exit();
        }
        
        $password_hash = password_hash($data->password, PASSWORD_BCRYPT, ['cost' => 12]);
        $query = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":password", $password_hash);
        $stmt->bindParam(":id", $user_id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Password reset successfully"));
        } else {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to reset password"));
        }
        exit();
    }
    
    // Change email (admin only)
    if ($action === 'email') {
        if ($user['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(array("error" => "Admin access required"));
            exit();
        }
        
        if (!isset($data->email) || empty($data->email)) {
            http_response_code(400);
            echo json_encode(array("error" => "New email is required"));
            exit();
        }
        
        // Validate email format
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(array("error" => "Invalid email format"));
            exit();
        }
        
        // Check if email already exists for another user
        $checkQuery = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":email", $data->email);
        $checkStmt->bindParam(":user_id", $user_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(array("error" => "This email is already in use by another user"));
            exit();
        }
        
        $query = "UPDATE users SET email = :email WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":id", $user_id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Email updated successfully", "email" => $data->email));
        } else {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to update email"));
        }
        exit();
    }
    
    // Update profile
    if ($user['id'] != $user_id && $user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(array("error" => "You can only update your own profile"));
        exit();
    }
    
    $updates = [];
    $params = [':id' => $user_id];
    
    if (isset($data->first_name)) {
        $updates[] = "first_name = :first_name";
        $params[':first_name'] = $data->first_name;
    }
    if (isset($data->last_name)) {
        $updates[] = "last_name = :last_name";
        $params[':last_name'] = $data->last_name;
    }
    if (isset($data->avatar)) {
        $updates[] = "avatar = :avatar";
        $params[':avatar'] = $data->avatar;
    }
    
    // Password change requires current password
    if (isset($data->password) && isset($data->current_password)) {
        // Verify current password
        $query = "SELECT password FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->execute();
        $current_user = $stmt->fetch();
        
        if (!password_verify($data->current_password, $current_user['password'])) {
            http_response_code(400);
            echo json_encode(array("error" => "Current password is incorrect"));
            exit();
        }
        
        if (strlen($data->password) < 8) {
            http_response_code(400);
            echo json_encode(array("error" => "Password must be at least 8 characters"));
            exit();
        }
        
        $password_hash = password_hash($data->password, PASSWORD_BCRYPT, ['cost' => 12]);
        $updates[] = "password = :password";
        $params[':password'] = $password_hash;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(array("error" => "No fields to update"));
        exit();
    }
    
    $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($params)) {
        http_response_code(200);
        echo json_encode(array("message" => "Profile updated successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to update profile"));
    }
    exit();
}

// DELETE - Delete user (admin only)
if ($method === 'DELETE') {
    $user = requireAdmin();
    $user_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(array("error" => "User ID is required"));
        exit();
    }
    
    if ($user['id'] == $user_id) {
        http_response_code(400);
        echo json_encode(array("error" => "Cannot delete yourself"));
        exit();
    }
    
    $query = "DELETE FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $user_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "User deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to delete user"));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>