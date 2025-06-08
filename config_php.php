<?php
// config.php - Konfigurasi Database dan Aplikasi Portal Ekstrakurikuler UNSRAT

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'portal_ekstrakurikuler_unsrat');

// Application Configuration
define('SITE_NAME', 'Portal Ekstrakurikuler UNSRAT');
define('SITE_URL', 'http://localhost/portal-ekstrakurikuler-unsrat');
define('ADMIN_EMAIL', 'admin@unsrat.ac.id');

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Default Images
define('DEFAULT_ACTIVITY_IMAGE', 'assets/images/default-activity.jpg');

// Session Configuration
ini_set('session.gc_maxlifetime', 3600); // 1 hour
session_set_cookie_params(3600);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Class
class Database {
    private $host = DB_HOST;
    private $username = DB_USERNAME;
    private $password = DB_PASSWORD;
    private $database = DB_NAME;
    private $connection;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function affectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Global Database Instance
$db = new Database();

// Utility Functions
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}

function isStudent() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isAdmin()) {
        return $_SESSION['admin_data'];
    } elseif (isStudent()) {
        return $_SESSION['user_data'];
    }
    return null;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo '<div class="alert alert-' . $alert['type'] . '">' . $alert['message'] . '</div>';
        unset($_SESSION['alert']);
    }
}

function formatDate($date) {
    return date('d M Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d M Y H:i', strtotime($datetime));
}

function uploadImage($file, $targetDir = UPLOAD_PATH) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    $uploadDir = $targetDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $fileName;
    }
    
    return false;
}

function deleteImage($fileName, $targetDir = UPLOAD_PATH) {
    $filePath = $targetDir . $fileName;
    if (file_exists($filePath) && $fileName !== 'default-activity.jpg') {
        return unlink($filePath);
    }
    return false;
}

function getImagePath($fileName) {
    if (empty($fileName) || $fileName === 'default-activity.jpg') {
        return DEFAULT_ACTIVITY_IMAGE;
    }
    
    $uploadPath = UPLOAD_PATH . $fileName;
    if (file_exists($uploadPath)) {
        return $uploadPath;
    }
    
    return DEFAULT_ACTIVITY_IMAGE;
}

// Error Handling
function handleError($message, $redirectUrl = null) {
    showAlert($message, 'error');
    if ($redirectUrl) {
        redirect($redirectUrl);
    }
}

// Security Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>