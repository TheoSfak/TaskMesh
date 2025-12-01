<?php
// TaskMesh - Test Email API (Admin only)

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/PHPMailer.php';

$user = authenticate();
$database = new Database();
$db = $database->getConnection();

// Only admin can send test emails
if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(array("error" => "Only admin can send test emails"));
    exit();
}

// POST - Send test email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->test_email) || empty($data->test_email)) {
        http_response_code(400);
        echo json_encode(array("error" => "test_email is required"));
        exit();
    }
    
    // Validate email
    if (!filter_var($data->test_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(array("error" => "Invalid email address"));
        exit();
    }
    
    // Send test email
    $subject = "✅ TaskMesh Email Test - " . date('Y-m-d H:i:s');
    
    $dateTime = date('d/m/Y H:i:s');
    $adminName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
    $recipientEmail = htmlspecialchars($data->test_email);
    
    $body = '
    <!DOCTYPE html>
    <html lang="el">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>TaskMesh Email Test</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; line-height: 1.6;">
        <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f0f2f5; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse;">
                        <!-- Header with Gradient -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; border-radius: 16px 16px 0 0; text-align: center;">
                                <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                                <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    Email Test Επιτυχές!
                                </h1>
                                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">
                                    Οι ρυθμίσεις email λειτουργούν σωστά
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Main Content -->
                        <tr>
                            <td style="background-color: #ffffff; padding: 40px 30px;">
                                <!-- Success Message -->
                                <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 30px;">
                                    <div style="font-size: 32px; margin-bottom: 10px;">🎉</div>
                                    <h2 style="color: #ffffff; margin: 0 0 10px 0; font-size: 20px;">Επιτυχής Αποστολή!</h2>
                                    <p style="color: rgba(255,255,255,0.95); margin: 0; font-size: 15px;">
                                        Αν βλέπεις αυτό το μήνυμα, όλα λειτουργούν τέλεια!
                                    </p>
                                </div>
                                
                                <!-- Test Details -->
                                <div style="background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%); border-radius: 12px; padding: 25px; margin-bottom: 30px; border-left: 4px solid #667eea;">
                                    <h3 style="color: #1e293b; margin: 0 0 15px 0; font-size: 16px; display: flex; align-items: center;">
                                        <span style="margin-right: 10px;">📋</span> Στοιχεία Δοκιμής
                                    </h3>
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Ημερομηνία:</td>
                                            <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">' . $dateTime . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Από:</td>
                                            <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">' . $adminName . ' (Admin)</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Προς:</td>
                                            <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">' . $recipientEmail . '</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <!-- What This Means -->
                                <h3 style="color: #1e293b; margin: 0 0 15px 0; font-size: 18px;">
                                    <span style="margin-right: 8px;">✨</span>Τι σημαίνει αυτό;
                                </h3>
                                <div style="margin-bottom: 30px;">
                                    <div style="display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                        <span style="background: #10b981; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-block; text-align: center; line-height: 24px; font-size: 14px; margin-right: 12px;">✓</span>
                                        <span style="color: #374151; font-size: 14px;">Οι ρυθμίσεις SMTP είναι σωστές</span>
                                    </div>
                                    <div style="display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                        <span style="background: #10b981; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-block; text-align: center; line-height: 24px; font-size: 14px; margin-right: 12px;">✓</span>
                                        <span style="color: #374151; font-size: 14px;">Η σύνδεση με τον email server λειτουργεί</span>
                                    </div>
                                    <div style="display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                        <span style="background: #10b981; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-block; text-align: center; line-height: 24px; font-size: 14px; margin-right: 12px;">✓</span>
                                        <span style="color: #374151; font-size: 14px;">Τα emails μπορούν να σταλούν επιτυχώς</span>
                                    </div>
                                    <div style="display: flex; align-items: center; padding: 12px 0;">
                                        <span style="background: #10b981; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-block; text-align: center; line-height: 24px; font-size: 14px; margin-right: 12px;">✓</span>
                                        <span style="color: #374151; font-size: 14px;">Οι ειδοποιήσεις είναι έτοιμες προς χρήση</span>
                                    </div>
                                </div>
                                
                                <!-- Next Steps -->
                                <h3 style="color: #1e293b; margin: 0 0 15px 0; font-size: 18px;">
                                    <span style="margin-right: 8px;">🚀</span>Επόμενα Βήματα
                                </h3>
                                <div style="background: #f8fafc; border-radius: 12px; padding: 20px;">
                                    <div style="display: flex; align-items: flex-start; padding: 10px 0;">
                                        <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; min-width: 28px; height: 28px; border-radius: 50%; display: inline-block; text-align: center; line-height: 28px; font-size: 14px; margin-right: 12px;">1</span>
                                        <span style="color: #374151; font-size: 14px; padding-top: 4px;">Ενεργοποίησε τις ειδοποιήσεις στις ρυθμίσεις</span>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; padding: 10px 0;">
                                        <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; min-width: 28px; height: 28px; border-radius: 50%; display: inline-block; text-align: center; line-height: 28px; font-size: 14px; margin-right: 12px;">2</span>
                                        <span style="color: #374151; font-size: 14px; padding-top: 4px;">Δοκίμασε πραγματικές ειδοποιήσεις (ανάθεση task, σχόλια)</span>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; padding: 10px 0;">
                                        <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; min-width: 28px; height: 28px; border-radius: 50%; display: inline-block; text-align: center; line-height: 28px; font-size: 14px; margin-right: 12px;">3</span>
                                        <span style="color: #374151; font-size: 14px; padding-top: 4px;">Ρύθμισε το cron job για deadline reminders</span>
                                    </div>
                                </div>
                                
                                <!-- CTA Button -->
                                <div style="text-align: center; margin-top: 30px;">
                                    <a href="#" style="display: inline-block; background: #ffffff; color: #667eea; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px; border: 2px solid #667eea; transition: all 0.3s;">
                                        Μετάβαση στο TaskMesh
                                    </a>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 30px; border-radius: 0 0 16px 16px; text-align: center;">
                                <div style="font-size: 24px; margin-bottom: 10px;">📧</div>
                                <p style="color: rgba(255,255,255,0.9); margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">
                                    TaskMesh Project Management
                                </p>
                                <p style="color: rgba(255,255,255,0.6); margin: 0; font-size: 13px;">
                                    Αυτό είναι ένα αυτοματοποιημένο δοκιμαστικό email
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';
    
    try {
        $result = EmailService::send($data->test_email, $subject, $body);
        
        if ($result) {
            http_response_code(200);
            echo json_encode(array(
                "success" => true,
                "message" => "Test email sent successfully to " . $data->test_email
            ));
        } else {
            http_response_code(500);
            echo json_encode(array(
                "success" => false,
                "error" => "Failed to send email. Check SMTP settings and try again."
            ));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array(
            "success" => false,
            "error" => "Email error: " . $e->getMessage()
        ));
    }
    exit();
}

http_response_code(405);
echo json_encode(array("error" => "Method not allowed"));
?>
