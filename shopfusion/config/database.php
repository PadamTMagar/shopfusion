<?php
// config/database.php
// Database configuration for XAMPP local development

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Empty password for XAMPP default
define('DB_NAME', 'shopfusion');
define('DB_PORT', '3306');

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $dbh;
    private $error;

    public function __construct() {
        // Set DSN
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8mb4';
        
        // PDO options
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        );

        // Create PDO instance
        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            die('Database connection failed: ' . $this->error);
        }
    }

    // Get database connection
    public function getConnection() {
        return $this->dbh;
    }
}

// Create global database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch(Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}
?>