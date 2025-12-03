-- ============================================
-- TaskMesh Timeline Feature - SQL Schema
-- Production Deployment Script
-- ============================================
-- Date: December 3, 2025
-- Feature: Timeline, Gantt Chart, Milestones
-- ============================================

-- Create task_dependencies table
-- Used for tracking dependencies between tasks
CREATE TABLE IF NOT EXISTS task_dependencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL COMMENT 'Task that has the dependency',
    depends_on_task_id INT NOT NULL COMMENT 'Task that must be completed first',
    dependency_type ENUM('blocks', 'must_finish_before', 'related') DEFAULT 'must_finish_before' COMMENT 'Type of dependency',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (depends_on_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dependency (task_id, depends_on_task_id),
    INDEX idx_task_id (task_id),
    INDEX idx_depends_on (depends_on_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task dependencies for Gantt chart and critical path';

-- Create milestones table
-- Used for project milestones/checkpoints
CREATE TABLE IF NOT EXISTS milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL COMMENT 'Associated team',
    title VARCHAR(255) NOT NULL COMMENT 'Milestone title',
    description TEXT COMMENT 'Optional description',
    target_date DATE NOT NULL COMMENT 'Target completion date',
    status ENUM('upcoming', 'in_progress', 'completed', 'missed') DEFAULT 'upcoming' COMMENT 'Current status',
    created_by INT NOT NULL COMMENT 'User who created the milestone',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_team_date (team_id, target_date),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Project milestones for timeline tracking';

-- Create task_assignments table
-- Used for tracking which users are assigned to tasks
CREATE TABLE IF NOT EXISTS task_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL COMMENT 'Assigned task',
    user_id INT NOT NULL COMMENT 'Assigned user',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When assignment was made',
    assigned_by INT COMMENT 'User who made the assignment',
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_assignment (task_id, user_id),
    INDEX idx_task (task_id),
    INDEX idx_user (user_id),
    INDEX idx_assigned_by (assigned_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task to user assignments';

-- ============================================
-- Verification Queries
-- ============================================

-- Check if tables were created successfully
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME,
    TABLE_COMMENT
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('task_dependencies', 'milestones', 'task_assignments');

-- ============================================
-- Sample Data (Optional - for testing)
-- ============================================

-- Insert sample milestone (uncomment if needed for testing)
-- INSERT INTO milestones (team_id, title, description, target_date, status, created_by)
-- VALUES (1, 'Phase 1 Complete', 'Complete all basic features', '2025-12-31', 'upcoming', 1);

-- ============================================
-- Rollback Script (if needed)
-- ============================================
-- CAUTION: This will delete all data!
-- Uncomment only if you need to rollback

-- DROP TABLE IF EXISTS task_assignments;
-- DROP TABLE IF EXISTS task_dependencies;
-- DROP TABLE IF EXISTS milestones;

-- ============================================
-- End of SQL Script
-- ============================================
