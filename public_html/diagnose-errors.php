<?php
/**
 * Comprehensive Server Error Diagnostics
 * Tests all components to find the 500 error cause
 */

header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== Server Error 500 Diagnostics ===\n\n";

// 1. Check PHP version
echo "1. PHP Version:\n";
echo "   " . phpversion() . "\n\n";

// 2. Check required extensions
echo "2. Required Extensions:\n";
$required = ['curl', 'json', 'pdo'];
foreach ($required as $ext) {
    $status = extension_loaded($ext) ? '✓' : '✗';
    echo "   $status $ext\n";
}
echo "\n";

// 3. Check optional database extensions
echo "3. Database Extensions:\n";
$db_exts = ['pdo_mysql', 'pdo_sqlite', 'mysqli'];
foreach ($db_exts as $ext) {
    $status = extension_loaded($ext) ? '✓' : '✗';
    echo "   $status $ext\n";
}
echo "\n";

// 4. Test config.php loading
echo "4. Testing Config Loading:\n";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "   ✓ Config loaded successfully\n";
    echo "   Database: " . (isset($pdo) && $pdo ? "Connected" : "Not connected (expected if MySQL not available)") . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Config failed: " . $e->getMessage() . "\n\n";
}

// 5. Test APIKeyManager
echo "5. Testing API Key Manager:\n";
try {
    require_once __DIR__ . '/includes/apikeys.php';
    $key = APIKeyManager::getKey('gemini');
    if ($key && strpos($key, 'YOUR_') === false) {
        echo "   ✓ Real API key loaded\n";
    } else {
        echo "   ⚠️  Placeholder API key (this is OK for testing)\n";
    }
    echo "   Key length: " . strlen($key) . "\n\n";
} catch (Exception $e) {
    echo "   ✗ API Key Manager failed: " . $e->getMessage() . "\n\n";
}

// 6. Test AIService
echo "6. Testing AIService:\n";
try {
    require_once __DIR__ . '/includes/AIService.php';
    echo "   ✓ AIService class loaded\n\n";
} catch (Exception $e) {
    echo "   ✗ AIService failed: " . $e->getMessage() . "\n\n";
}

// 7. Check file permissions
echo "7. File Permissions:\n";
$files = [
    'includes/config.php',
    'includes/apikeys.php',
    'includes/AIService.php',
    'api/generate.php',
    'logs/'
];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $readable = is_readable($path) ? '✓ R' : '✗';
        echo "   $readable ($perms) $file\n";
    } else {
        echo "   ✗ MISSING: $file\n";
    }
}
echo "\n";

// 8. Test API endpoint
echo "8. Testing API Endpoint:\n";
echo "   Simulating GET /api/generate.php?action=generate&query=test\n";
try {
    $_GET = ['action' => 'generate', 'query' => 'test'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Capture output
    ob_start();
    include __DIR__ . '/api/generate.php';
    $output = ob_get_clean();
    
    echo "   Response (first 200 chars):\n";
    echo "   " . substr($output, 0, 200) . "...\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

echo "=== End Diagnostics ===\n";
echo "\nIf you see errors above, that's the cause of your 500 error.\n";
echo "Common fixes:\n";
echo "  • Missing PHP extension: sudo apt-get install php8.0-mysql\n";
echo "  • API key not set: Edit includes/apikeys.php and add your key\n";
echo "  • Database not running: Check MySQL/MariaDB status\n";
echo "  • File permissions: chmod 644 on PHP files, 755 on directories\n";

?>
