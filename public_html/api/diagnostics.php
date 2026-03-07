<?php
/**
 * Diagnostic tool for JSON API issues
 */
ob_start();
header('Content-Type: application/json');

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'memory_usage' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true),
    'output_buffering' => ini_get('output_buffering'),
    'display_errors' => ini_get('display_errors'),
    'log_errors' => ini_get('log_errors'),
    'max_execution_time' => ini_get('max_execution_time'),
    'default_socket_timeout' => ini_get('default_socket_timeout'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
    'files_exist' => [
        'config.php' => file_exists(__DIR__ . '/config.php'),
        'AIService.php' => file_exists(__DIR__ . '/AIService.php'),
        'EnhancedPageGeneratorV2.php' => file_exists(__DIR__ . '/EnhancedPageGeneratorV2.php'),
    ]
];

// Test database connection
$diagnostics['database_connection'] = 'Not tested';
try {
    require_once __DIR__ . '/config.php';
    if (isset($pdo)) {
        $stmt = $pdo->query('SELECT 1');
        $diagnostics['database_connection'] = 'OK';
        $diagnostics['database_host'] = DB_HOST;
        $diagnostics['database_name'] = DB_DATABASE;
    }
} catch (Exception $e) {
    $diagnostics['database_connection'] = 'Error: ' . $e->getMessage();
}

// Test API key
$diagnostics['api_key_set'] = !empty(GEMINI_API_KEY);
$diagnostics['api_key_length'] = strlen(GEMINI_API_KEY ?? '');

// Clean any buffered output and return clean JSON
ob_end_clean();
echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);