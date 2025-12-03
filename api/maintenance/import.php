<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Verify authentication
$auth = authenticate();
if (!$auth['success']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only admins can import data
if ($auth['user']['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if file was uploaded
        if (!isset($_FILES['import']) || $_FILES['import']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No import file uploaded');
        }

        $file = $_FILES['import'];

        // Validate file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'json') {
            throw new Exception('Invalid file type. Only JSON files are allowed.');
        }

        // Read and parse JSON
        $jsonContent = file_get_contents($file['tmp_name']);
        if ($jsonContent === false) {
            throw new Exception('Failed to read import file');
        }

        $importData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }

        // Validate structure
        if (!isset($importData['data']) || !is_array($importData['data'])) {
            throw new Exception('Invalid import file structure');
        }

        $database = new Database();
        $db = $database->getConnection();

        // Start transaction
        $db->beginTransaction();

        try {
            $imported = 0;

            // Import data for each table
            foreach ($importData['data'] as $tableName => $rows) {
                if (empty($rows)) continue;

                // Get column names from first row
                $columns = array_keys($rows[0]);
                $placeholders = ':' . implode(', :', $columns);
                $columnList = '`' . implode('`, `', $columns) . '`';

                // Prepare insert statement with ON DUPLICATE KEY UPDATE
                $updateList = [];
                foreach ($columns as $col) {
                    if ($col !== 'id') { // Don't update id
                        $updateList[] = "`$col` = VALUES(`$col`)";
                    }
                }
                $updateClause = implode(', ', $updateList);

                $query = "INSERT INTO `$tableName` ($columnList) 
                          VALUES ($placeholders)";
                
                if (!empty($updateClause)) {
                    $query .= " ON DUPLICATE KEY UPDATE $updateClause";
                }

                $stmt = $db->prepare($query);

                // Insert each row
                foreach ($rows as $row) {
                    foreach ($columns as $col) {
                        $stmt->bindValue(':' . $col, $row[$col]);
                    }
                    $stmt->execute();
                    $imported++;
                }
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => "$imported records imported successfully"
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to import data: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
