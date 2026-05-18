<?php
// ClinicCare - Database Configuration
// Copy this file to config.php and edit the values to match your environment

define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'cliniccares');
define('DB_PORT', '3306');

define('SITE_URL', 'http://localhost/cliniccares');
define('SITE_NAME', 'ClinicCare');
define('SITE_EMAIL', 'noreply@cliniccares.com');
define('SITE_PHONE', '+63 900 000 0000');
define('SITE_ADDRESS', 'Your Address Here');

// SMTP Email Settings (PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_FROM', 'noreply@cliniccares.com');
define('SMTP_FROM_NAME', 'ClinicCare System');

// PayMongo Payment Gateway
// Get your keys at https://dashboard.paymongo.com → Developers → API Keys
define('PAYMONGO_PUBLIC_KEY',  'pk_test_your_public_key_here');
define('PAYMONGO_SECRET_KEY',  'sk_test_your_secret_key_here');
define('PAYMONGO_WEBHOOK_KEY', 'your_webhook_key_here');

// Security
define('SECRET_KEY', 'your_secret_key_here');
define('SESSION_LIFETIME', 3600); // 1 hour

// File Upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Currency
define('CURRENCY', '₱');
define('CURRENCY_CODE', 'PHP');

// Pagination
define('ITEMS_PER_PAGE', 15);

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->conn->lastInsertId();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
}

// Helper functions
function db() {
    return Database::getInstance();
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount) {
    return CURRENCY . number_format($amount, 2);
}

function formatDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

function formatTime($time) {
    if (!$time) return 'N/A';
    return date('h:i A', strtotime($time));
}

function generateInvoiceNumber() {
    return 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function generatePrescriptionNumber() {
    return 'RX-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/index.php?msg=Please+login+to+continue');
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        redirect(SITE_URL . '/index.php?error=Unauthorized+access');
    }
}

function requireAnyRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        redirect(SITE_URL . '/index.php?error=Unauthorized+access');
    }
}

function logActivity($userId, $action, $description = '', $req = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    db()->insert(
        "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?,?,?,?,?)",
        [$userId, $action, $description, $ip, $ua]
    );
}

function createNotification($userId, $title, $message, $type = 'system', $link = '') {
    db()->insert(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)",
        [$userId, $title, $message, $type, $link]
    );
}

function getUnreadNotifications($userId) {
    return db()->fetchAll(
        "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10",
        [$userId]
    );
}

function getStatusBadge($status) {
    $badges = [
        'pending'   => 'badge-warning',
        'confirmed' => 'badge-info',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'no_show'   => 'badge-secondary',
        'active'    => 'badge-success',
        'paid'      => 'badge-success',
        'partial'   => 'badge-warning',
        'refunded'  => 'badge-info',
    ];
    return $badges[$status] ?? 'badge-secondary';
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
