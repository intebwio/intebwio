<?php
/**
 * Intebwio - AI-Powered Web Browser
 * Database Configuration
 */

// Database Configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_USER', 'u757840095_Yaroslav');
define('DB_PASS', 'l1@ArIsM');
define('DB_DATABASE', 'u757840095_Intebwio');

// PDO Connection - with graceful error handling
$pdo = null;
$db_error = null;

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Don't die - log the error but allow the app to continue
    // This allows API testing even if database is not available
    $db_error = $e->getMessage();
    error_log("⚠️  Database connection failed: " . $db_error);
    error_log("   Check: 1) Database server running 2) PDO MySQL extension 3) Credentials");
    
    // For HTML pages, we can still work with file-based fallback
    // For API, we'll need to handle this gracefully in the endpoint
    $pdo = null; // Set to null so code can check if DB is available
}

// Application Configuration
define('APP_NAME', 'Intebwio');
define('APP_VERSION', '2.0.0');
define('DEBUG_MODE', false);
define('UPDATE_INTERVAL', 604800); // 7 days in seconds
define('SIMILARITY_THRESHOLD', 0.75); // 75% similarity to consider as duplicate
define('MAX_PAGE_CACHE', 5000); // Maximum pages to cache
define('ALLOW_MULTIPLE_PAGES_PER_TOPIC', true); // Allow multiple variations of same topic
define('CONTENT_SOURCES', [
    'wikipedia' => 'https://en.wikipedia.org/w/api.php',
    'google_news' => 'https://news.google.com',
    'medium' => 'https://medium.com',
    'github' => 'https://api.github.com'
]);

// Load API Keys from centralized APIKeyManager
require_once __DIR__ . '/apikeys.php';

// Helper to ensure database availability in API endpoints
function requireDatabase() {
    global $pdo;
    if (!$pdo) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection unavailable',
            'details' => 'Ensure PDO MySQL extension is installed and database server is running'
        ]);
        exit;
    }
}


// API Keys (loaded from environment variables or apikeys.php)
define('GOOGLE_API_KEY', APIKeyManager::getKey('gemini'));
define('GEMINI_API_KEY', APIKeyManager::getKey('gemini'));
define('AI_PROVIDER', 'gemini');
define('SERPAPI_KEY', APIKeyManager::getKey('serpapi'));
define('GOOGLE_SEARCH_API_KEY', APIKeyManager::getKey('google_search'));

// Logging
define('LOG_FILE', dirname(__FILE__) . '/../logs/intebwio.log');
if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

// Timeout Settings for long-running operations
ini_set('max_execution_time', 600);  // 10 minutes for content generation
ini_set('default_socket_timeout', 180);  // 3 minutes for external API calls