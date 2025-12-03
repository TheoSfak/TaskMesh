-- Real-time Notifications System
-- Stores in-app notifications for users

USE taskmesh_db;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'task_assigned', 'task_completed', 'comment_added', 'team_invitation', 'subtask_created', etc.
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) DEFAULT NULL, -- Link to relevant page (e.g., #tasks?id=123)
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user_created (user_id, created_at DESC),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some sample notifications for testing
INSERT INTO notifications (user_id, type, title, message, link, is_read) 
SELECT id, 'welcome', 'Καλώς ήρθες στο TaskMesh! 🎉', 
       'Ξεκίνα δημιουργώντας το πρώτο σου task ή ομάδα.', 
       '#tasks', FALSE
FROM users 
WHERE role = 'ADMIN'
LIMIT 1;

SELECT 'Notifications table created successfully!' as status;
