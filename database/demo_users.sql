-- TaskMesh Demo Users
-- 5 Members + 2 Managers + 1 Admin (already exists)
-- All passwords: demo123

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE taskmesh_db;

-- Insert 2 Managers
INSERT INTO users (email, password, first_name, last_name, role, is_active) VALUES
('manager1@taskmesh.com', '$2y$12$fG4X47fUqSzneUGwhNBr6untHeZni8d2URaY84ijH9xLv6yWtmPvS', 'Γιώργος', 'Παπαδόπουλος', 'MANAGER', TRUE),
('manager2@taskmesh.com', '$2y$12$fG4X47fUqSzneUGwhNBr6untHeZni8d2URaY84ijH9xLv6yWtmPvS', 'Μαρία', 'Ιωάννου', 'MANAGER', TRUE);

-- Insert 5 Members
INSERT INTO users (email, password, first_name, last_name, role, is_active) VALUES
('user1@taskmesh.com', '$2y$12$fG4X47fUqSzneUGwhNBr6untHeZni8d2URaY84ijH9xLv6yWtmPvS', 'Νίκος', 'Αντωνίου', 'MEMBER', TRUE),
('user2@taskmesh.com', '$2y$12$fG4X47fUqSzneUGwhNBr6untHeZni8d2URaY84ijH9xLv6yWtmPvS', 'Ελένη', 'Δημητρίου', 'MEMBER', TRUE),
('user3@taskmesh.com', '$2y$12$fG4X47fUqSzneUGwhNBr6untHeZni8d2URaY84ijH9xLv6yWtmPvS', 'Κώστας', 'Νικολάου', 'MEMBER', TRUE),
('user4@taskmesh.com', '$2y$12$fG4X47fUqSzneUGwhNBr6untHeZni8d2URaY84ijH9xLv6yWtmPvS', 'Σοφία', 'Γεωργίου', 'MEMBER', TRUE),
('user5@taskmesh.com', '$2y$12$fG4X47fUqSzneUGwhNBr6untHeZni8d2URaY84ijH9xLv6yWtmPvS', 'Δημήτρης', 'Κωνσταντίνου', 'MEMBER', TRUE);

SELECT 'Demo users created successfully!' as message;
SELECT email, first_name, last_name, role FROM users ORDER BY role DESC, first_name ASC;