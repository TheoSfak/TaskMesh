<?php
// Simple file-based notification queue for WebSocket broadcasting

class NotificationQueue {
    private static $queueFile = __DIR__ . '/../temp/notification_queue.json';
    
    public static function push($userId, $notification) {
        self::ensureQueueDir();
        
        $queue = self::getQueue();
        $queue[] = [
            'user_id' => $userId,
            'notification' => $notification,
            'timestamp' => time()
        ];
        
        file_put_contents(self::$queueFile, json_encode($queue), LOCK_EX);
    }
    
    public static function pop() {
        if (!file_exists(self::$queueFile)) {
            return null;
        }
        
        $queue = self::getQueue();
        if (empty($queue)) {
            return null;
        }
        
        $item = array_shift($queue);
        file_put_contents(self::$queueFile, json_encode($queue), LOCK_EX);
        
        return $item;
    }
    
    private static function getQueue() {
        if (!file_exists(self::$queueFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$queueFile);
        return json_decode($content, true) ?: [];
    }
    
    private static function ensureQueueDir() {
        $dir = dirname(self::$queueFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
?>
