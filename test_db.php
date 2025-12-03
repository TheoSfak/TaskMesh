<?php
// Database Connection Test
// Access this file at: https://ecowatt.gr/task/test_db.php
// DELETE THIS FILE AFTER TESTING!

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>TaskMesh Database Connection Test</h1>";

// Test 1: Check if config file exists
echo "<h2>Test 1: Config File</h2>";
if (file_exists(__DIR__ . '/config/database.php')) {
    echo "✅ config/database.php exists<br>";
    require_once __DIR__ . '/config/database.php';
} else {
    echo "❌ config/database.php NOT FOUND<br>";
    die();
}

// Test 2: Try to create database connection
echo "<h2>Test 2: Database Connection</h2>";
try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✅ Database connection successful<br>";
        
        // Test 3: Check if tables exist
        echo "<h2>Test 3: Database Tables</h2>";
        $tables = ['users', 'tasks', 'teams', 'notifications', 'messages', 'team_members', 'system_settings'];
        
        foreach ($tables as $table) {
            $query = "SHOW TABLES LIKE '$table'";
            $stmt = $db->query($query);
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '$table' exists<br>";
            } else {
                echo "❌ Table '$table' NOT FOUND<br>";
            }
        }
        
        // Test 4: Check users
        echo "<h2>Test 4: Users Count</h2>";
        $query = "SELECT COUNT(*) as count FROM users";
        $stmt = $db->query($query);
        $result = $stmt->fetch();
        echo "✅ Users in database: " . $result['count'] . "<br>";
        
    } else {
        echo "❌ Failed to get database connection<br>";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<br><br><strong>⚠️ DELETE THIS FILE AFTER TESTING!</strong>";
?>
