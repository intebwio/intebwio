<?php
/**
 * Settings Management API
 * Manage application settings and configuration
 * ~180 lines
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

// Check for admin token in header or session
$authToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SESSION['admin_token'] ?? '';
$validToken = $_ENV['ADMIN_API_TOKEN'] ?? 'admin-dev-token-secret';

if (empty($authToken) || $authToken !== $validToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'get';
$setting = $_GET['setting'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    switch ($action) {
        case 'get':
            getSettings($pdo, $setting);
            break;
        case 'set':
            setSetting($pdo, $setting, $_POST['value'] ?? '');
            break;
        case 'get-all':
            getAllSettings($pdo);
            break;
        case 'reset':
            resetSettings($pdo);
            break;
        case 'validate':
            validateSettings($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getSettings($pdo, $setting = '') {
    if (empty($setting)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM application_settings WHERE setting_key LIKE '%'");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = json_decode($row['setting_value'], true) ?? $row['setting_value'];
        }
        
        echo json_encode(['settings' => $settings]);
    } else {
        $stmt = $pdo->prepare("SELECT setting_value FROM application_settings WHERE setting_key = ?");
        $stmt->execute([$setting]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'setting' => $setting,
                'value' => json_decode($result['setting_value'], true) ?? $result['setting_value']
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Setting not found']);
        }
    }
}

function setSetting($pdo, $setting, $value) {
    if (empty($setting)) {
        http_response_code(400);
        echo json_encode(['error' => 'Setting key required']);
        return;
    }
    
    // Validate setting
    $validSettings = [
        'app_name', 'app_url', 'ai_provider', 'ai_timeout', 'cache_enabled',
        'cache_ttl', 'auto_update_enabled', 'auto_update_interval', 'rate_limit_enabled',
        'rate_limit_requests', 'rate_limit_window', 'max_upload_size', 'enable_comments',
        'enable_analytics', 'maintenance_mode', 'debug_mode'
    ];
    
    if (!in_array($setting, $validSettings)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid setting key']);
        return;
    }
    
    // Convert value if it's JSON
    $storedValue = is_array($value) ? json_encode($value) : $value;
    
    $stmt = $pdo->prepare("
        INSERT INTO application_settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
    ");
    
    $result = $stmt->execute([$setting, $storedValue, $storedValue]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'setting' => $setting,
            'value' => $value
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update setting']);
    }
}

function getAllSettings($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT setting_key, setting_value, updated_at
            FROM application_settings
            ORDER BY setting_key
        ");
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $value = $row['setting_value'];
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
            
            $settings[$row['setting_key']] = [
                'value' => $value,
                'updated_at' => $row['updated_at']
            ];
        }
        
        echo json_encode([
            'total_settings' => count($settings),
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function resetSettings($pdo) {
    $defaultSettings = [
        'app_name' => 'Intebwio',
        'app_url' => 'http://localhost',
        'ai_provider' => 'openai',
        'ai_timeout' => '30',
        'cache_enabled' => 'true',
        'cache_ttl' => '604800',
        'auto_update_enabled' => 'true',
        'auto_update_interval' => '604800',
        'rate_limit_enabled' => 'true',
        'rate_limit_requests' => '100',
        'rate_limit_window' => '3600',
        'max_upload_size' => '5242880',
        'enable_comments' => 'true',
        'enable_analytics' => 'true',
        'maintenance_mode' => 'false',
        'debug_mode' => 'false'
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO application_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    
    foreach ($defaultSettings as $key => $value) {
        $stmt->execute([$key, $value, $value]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings reset to defaults',
        'count' => count($defaultSettings)
    ]);
}

function validateSettings($pdo) {
    $issues = [];
    
    // Check database connection
    try {
        $pdo->query("SELECT 1");
    } catch (Exception $e) {
        $issues[] = ['type' => 'error', 'message' => 'Database connection failed'];
    }
    
    // Check required tables
    $requiredTables = ['pages', 'user_activity', 'cache_metadata'];
    foreach ($requiredTables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$result) {
            $issues[] = ['type' => 'warning', 'message' => "Table '$table' not found"];
        }
    }
    
    // Check disk space
    $diskFree = disk_free_space('/');
    if ($diskFree < 104857600) { // Less than 100MB
        $issues[] = ['type' => 'warning', 'message' => 'Low disk space'];
    }
    
    // Check PHP extensions
    $extensions = ['pdo', 'pdo_mysql', 'json', 'curl'];
    foreach ($extensions as $ext) {
        if (!extension_loaded($ext)) {
            $issues[] = ['type' => 'error', 'message' => "PHP extension '$ext' not loaded"];
        }
    }
    
    // Check upload directory
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_writable($uploadDir)) {
        $issues[] = ['type' => 'warning', 'message' => 'Upload directory not writable'];
    }
    
    echo json_encode([
        'validation_status' => empty($issues) ? 'ok' : 'has_issues',
        'issues_count' => count($issues),
        'issues' => $issues
    ]);
}