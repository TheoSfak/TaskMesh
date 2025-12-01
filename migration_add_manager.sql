ALTER TABLE users ADD COLUMN manager_id INT NULL AFTER role;
ALTER TABLE users ADD FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE users ADD INDEX idx_manager (manager_id);
