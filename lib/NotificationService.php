<?php
// Notification Helper - Create notifications and broadcast via WebSocket

require_once __DIR__ . '/NotificationQueue.php';

class NotificationService {
    
    /**
     * Create a notification for a user
     */
    public static function create($userId, $type, $title, $message, $link = null) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "INSERT INTO notifications (user_id, type, title, message, link) 
                      VALUES (:user_id, :type, :title, :message, :link)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $userId);
            $stmt->bindParam(":type", $type);
            $stmt->bindParam(":title", $title);
            $stmt->bindParam(":message", $message);
            $stmt->bindParam(":link", $link);
            $stmt->execute();
            
            $notificationId = $db->lastInsertId();
            
            // Try to broadcast via WebSocket (if server is running)
            self::broadcastToWebSocket($userId, [
                'id' => $notificationId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $notificationId;
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Broadcast notification to WebSocket server
     */
    private static function broadcastToWebSocket($userId, $notification) {
        try {
            // Use file-based queue for WebSocket broadcasting
            NotificationQueue::push($userId, $notification);
            error_log("Notification queued for user $userId");
        } catch (Exception $e) {
            // WebSocket not available, notification still saved in DB
            error_log("WebSocket broadcast failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create notification for task assignment
     */
    public static function taskAssigned($assigneeId, $taskTitle, $taskId, $assignedByName) {
        return self::create(
            $assigneeId,
            'task_assigned',
            '📋 Νέο Task',
            "Ο/Η $assignedByName σου ανέθεσε: $taskTitle",
            "#tasks?id=$taskId"
        );
    }
    
    /**
     * Create notification for task completion
     */
    public static function taskCompleted($managerId, $taskTitle, $taskId, $completedByName) {
        return self::create(
            $managerId,
            'task_completed',
            '✅ Task Ολοκληρώθηκε',
            "Ο/Η $completedByName ολοκλήρωσε: $taskTitle",
            "#tasks?id=$taskId"
        );
    }
    
    /**
     * Create notification for subtask creation
     */
    public static function subtaskCreated($assigneeId, $subtaskTitle, $taskTitle, $taskId, $createdByName) {
        return self::create(
            $assigneeId,
            'subtask_created',
            '📝 Νέο Subtask',
            "Ο/Η $createdByName πρόσθεσε: $subtaskTitle στο $taskTitle",
            "#tasks?id=$taskId"
        );
    }
    
    /**
     * Create notification for new comment
     */
    public static function commentAdded($recipientId, $taskTitle, $taskId, $commentAuthor) {
        return self::create(
            $recipientId,
            'comment_added',
            '💬 Νέο Σχόλιο',
            "Ο/Η $commentAuthor σχολίασε στο: $taskTitle",
            "#tasks?id=$taskId"
        );
    }
    
    /**
     * Create notification for team invitation
     */
    public static function teamInvitation($memberId, $teamName, $teamId, $invitedByName) {
        return self::create(
            $memberId,
            'team_invitation',
            '👥 Πρόσκληση σε Ομάδα',
            "Ο/Η $invitedByName σε πρόσκλησε στην ομάδα: $teamName",
            "#teams?id=$teamId"
        );
    }
    
    /**
     * Create notification for direct message
     */
    public static function directMessage($recipientId, $senderName) {
        return self::create(
            $recipientId,
            'direct_message',
            '✉️ Νέο Μήνυμα',
            "Νέο μήνυμα από: $senderName",
            "#messages"
        );
    }
    
    /**
     * Create notification for task status change
     */
    public static function taskStatusChanged($recipientId, $taskTitle, $taskId, $oldStatus, $newStatus, $changedByName) {
        $statusNames = [
            'TODO' => 'Προς Εκτέλεση',
            'IN_PROGRESS' => 'Σε Εξέλιξη',
            'IN_REVIEW' => 'Σε Αναθεώρηση',
            'COMPLETED' => 'Ολοκληρωμένο',
            'CANCELLED' => 'Ακυρωμένο'
        ];
        
        $oldStatusText = $statusNames[$oldStatus] ?? $oldStatus;
        $newStatusText = $statusNames[$newStatus] ?? $newStatus;
        
        return self::create(
            $recipientId,
            'status_changed',
            '🔄 Αλλαγή Κατάστασης Task',
            "Ο/Η $changedByName άλλαξε την κατάσταση του \"$taskTitle\" από $oldStatusText σε $newStatusText",
            "#tasks?id=$taskId"
        );
    }
}
