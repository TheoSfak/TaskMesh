<?php
// TaskMesh - Email Templates API (Admin only)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Only admin can manage templates
if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(array("error" => "Only admin can manage email templates"));
    exit();
}

// GET - Get template by type
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    
    if (!$type) {
        // Return all templates
        try {
            $stmt = $db->prepare("SELECT * FROM email_templates WHERE is_active = 1 ORDER BY template_type");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode($templates);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to load templates: " . $e->getMessage()));
        }
    } else {
        // Return specific template
        try {
            $stmt = $db->prepare("SELECT * FROM email_templates WHERE template_type = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$type]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                http_response_code(404);
                echo json_encode(array("error" => "Template not found"));
            } else {
                http_response_code(200);
                echo json_encode($template);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(array("error" => "Failed to load template: " . $e->getMessage()));
        }
    }
    exit();
}

// PUT - Update template
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->template_type)) {
        http_response_code(400);
        echo json_encode(array("error" => "template_type is required"));
        exit();
    }
    
    try {
        // Check if template exists
        $stmt = $db->prepare("SELECT id FROM email_templates WHERE template_type = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$data->template_type]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE email_templates SET
                    subject = ?,
                    header_gradient_start = ?,
                    header_gradient_end = ?,
                    header_icon = ?,
                    button_color = ?,
                    button_text_color = ?,
                    content_template = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                isset($data->subject) ? $data->subject : '',
                isset($data->header_gradient_start) ? $data->header_gradient_start : '#667eea',
                isset($data->header_gradient_end) ? $data->header_gradient_end : '#764ba2',
                isset($data->header_icon) ? $data->header_icon : '📧',
                isset($data->button_color) ? $data->button_color : '#667eea',
                isset($data->button_text_color) ? $data->button_text_color : '#ffffff',
                isset($data->content_template) ? $data->content_template : '',
                $user['id'],
                $existing['id']
            ]);
        } else {
            // Create new custom template
            $stmt = $db->prepare("
                INSERT INTO email_templates (
                    template_type,
                    name,
                    subject,
                    header_gradient_start,
                    header_gradient_end,
                    header_icon,
                    button_color,
                    button_text_color,
                    content_template,
                    is_active,
                    is_default,
                    created_by,
                    updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)
            ");
            
            $stmt->execute([
                $data->template_type,
                isset($data->name) ? $data->name : 'Custom Template',
                isset($data->subject) ? $data->subject : '',
                isset($data->header_gradient_start) ? $data->header_gradient_start : '#667eea',
                isset($data->header_gradient_end) ? $data->header_gradient_end : '#764ba2',
                isset($data->header_icon) ? $data->header_icon : '📧',
                isset($data->button_color) ? $data->button_color : '#667eea',
                isset($data->button_text_color) ? $data->button_text_color : '#ffffff',
                isset($data->content_template) ? $data->content_template : '',
                $user['id'],
                $user['id']
            ]);
        }
        
        http_response_code(200);
        echo json_encode(array("success" => true, "message" => "Template saved successfully"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to save template: " . $e->getMessage()));
    }
    exit();
}

// DELETE - Reset template to default
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $reset = isset($_GET['reset']) ? $_GET['reset'] : null;
    
    if (!$type) {
        http_response_code(400);
        echo json_encode(array("error" => "template_type is required"));
        exit();
    }
    
    try {
        if ($reset) {
            // Delete custom template, restore default
            $stmt = $db->prepare("DELETE FROM email_templates WHERE template_type = ? AND is_default = 0");
            $stmt->execute([$type]);
            
            // Check if default exists, if not create it
            $stmt = $db->prepare("SELECT id FROM email_templates WHERE template_type = ? AND is_default = 1");
            $stmt->execute([$type]);
            
            if (!$stmt->fetch()) {
                // Restore default based on type
                $defaults = [
                    'task_assigned' => [
                        'name' => 'Task Assignment (Default)',
                        'subject' => '📋 Νέα ανάθεση εργασίας: {{task_title}}',
                        'icon' => '📋',
                        'content' => '<p>Σας ανατέθηκε νέα εργασία.</p>'
                    ],
                    'task_completed' => [
                        'name' => 'Task Completion (Default)',
                        'subject' => '✅ Ολοκληρώθηκε εργασία: {{task_title}}',
                        'icon' => '✅',
                        'content' => '<p>Η εργασία ολοκληρώθηκε επιτυχώς.</p>'
                    ]
                ];
                
                $default = isset($defaults[$type]) ? $defaults[$type] : [
                    'name' => ucfirst(str_replace('_', ' ', $type)) . ' (Default)',
                    'subject' => 'TaskMesh Notification',
                    'icon' => '📧',
                    'content' => '<p>You have a new notification.</p>'
                ];
                
                $stmt = $db->prepare("
                    INSERT INTO email_templates (template_type, name, subject, header_icon, content_template, is_active, is_default)
                    VALUES (?, ?, ?, ?, ?, 1, 1)
                ");
                $stmt->execute([$type, $default['name'], $default['subject'], $default['icon'], $default['content']]);
            }
        }
        
        http_response_code(200);
        echo json_encode(array("success" => true, "message" => "Template reset to default"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("error" => "Failed to reset template: " . $e->getMessage()));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>
