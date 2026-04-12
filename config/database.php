<?php
// config/database.php
// Database configuration for local/shared-hosting style environments.
// Defaults now match the SQL files in /database and standard XAMPP usage.

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'shopfusion');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $dbh;
    private $error;

    public function __construct() {
        // Set DSN
        $dsn = 'mysql:host=' . $this->host . ';port=' . DB_PORT . ';dbname=' . $this->dbname . ';charset=utf8mb4';
        
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
