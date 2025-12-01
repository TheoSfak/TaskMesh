<?php
// TaskMesh - Database Configuration
class Database {
    private $host = "localhost";
    private $db_name = "taskmesh_db";
    private $username = "root";
    private $password = "";
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array("error" => "Database connection failed: " . $exception->getMessage()));
            exit();
        }

        return $this->conn;
    }
}
?>