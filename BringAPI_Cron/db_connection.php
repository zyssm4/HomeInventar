<?php
// Load environment variables
require_once dirname(__DIR__) . '/env_loader.php';

// Get database credentials from environment
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'homeinventar';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

// Connect using MySQLi
$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

// PDO-compatible wrapper for MySQLi
class PDOWrapper {
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    public function prepare($query) {
        return new StatementWrapper($this->mysqli, $query);
    }
    
    public function query($query) {
        $result = $this->mysqli->query($query);
        if (!$result) {
            throw new Exception($this->mysqli->error);
        }
        return new ResultWrapper($result);
    }
    
    public function lastInsertId() {
        return $this->mysqli->insert_id;
    }
    
    public function exec($query) {
        return $this->mysqli->query($query);
    }
}

class StatementWrapper {
    private $mysqli;
    private $query;
    private $result;
    
    public function __construct($mysqli, $query) {
        $this->mysqli = $mysqli;
        $this->query = $query;
    }
    
    public function execute($params = []) {
        $query = $this->query;
        
        // Replace ? placeholders with escaped values
        foreach ($params as $param) {
            if ($param === null) {
                $escaped = 'NULL';
            } else {
                $escaped = "'" . $this->mysqli->real_escape_string($param) . "'";
            }
            $query = preg_replace('/\?/', $escaped, $query, 1);
        }
        
        $this->result = $this->mysqli->query($query);
        
        if (!$this->result) {
            throw new Exception($this->mysqli->error . " | Query: " . $query);
        }
        
        return true;
    }
    
    public function fetch($mode = null) {
        if ($this->result && $this->result instanceof mysqli_result) {
            return $this->result->fetch_assoc();
        }
        return false;
    }
    
    public function fetchAll($mode = null) {
        $rows = [];
        if ($this->result && $this->result instanceof mysqli_result) {
            while ($row = $this->result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
    
    public function fetchColumn() {
        if ($this->result && $this->result instanceof mysqli_result) {
            $row = $this->result->fetch_row();
            return $row ? $row[0] : null;
        }
        return null;
    }
    
    public function rowCount() {
        return $this->mysqli->affected_rows;
    }
}

class ResultWrapper {
    private $result;
    
    public function __construct($result) {
        $this->result = $result;
    }
    
    public function fetch($mode = null) {
        return $this->result->fetch_assoc();
    }
    
    public function fetchAll($mode = null) {
        $rows = [];
        while ($row = $this->result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function fetchColumn() {
        $row = $this->result->fetch_row();
        return $row ? $row[0] : null;
    }
}

// Create PDO-compatible object
$pdo = new PDOWrapper($mysqli);

// For compatibility with PDO constants
if (!defined('PDO::FETCH_ASSOC')) {
    define('PDO::FETCH_ASSOC', 1);
}
?>