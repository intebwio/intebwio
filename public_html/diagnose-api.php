<?php
/**
 * Gemini API Diagnostics
 * Tests the API key loading and Gemini API connectivity
 */

header('Content-Type: text/plain');

echo "=== Gemini API Diagnostics ===\n\n";

// Load configuration
require_once __DIR__ . '/includes/config.php';

echo "1. Database Status:\n";
echo "─────────────────────────────────────\n";
if ($pdo) {
    echo "✓ Database connection: OK\n\n";
} else {
    global $db_error;
    echo "⚠️  Database connection: FAILED\n";
    echo "   Error: " . ($db_error ?? 'Unknown error') . "\n";
    echo "   This is OK for API testing - you can continue\n\n";
}

echo "2. API Key Status:\n";
echo "─────────────────────────────────────\n";
echo "GEMINI_API_KEY defined: " . (defined('GEMINI_API_KEY') ? 'YES' : 'NO') . "\n";
echo "Key length: " . strlen(GEMINI_API_KEY) . " characters\n";
echo "Key prefix: " . substr(GEMINI_API_KEY, 0, 10) . "...\n";
echo "Placeholder detected: " . (strpos(GEMINI_API_KEY, 'YOUR_') !== false ? 'YES (⚠️ PROBLEM)' : 'NO') . "\n\n";

if (strpos(GEMINI_API_KEY, 'YOUR_') !== false) {
    echo "⚠️  WARNING: API key is a placeholder!\n";
    echo "    The system loaded 'YOUR_GEMINI_API_KEY_HERE' instead of a real key.\n";
    echo "    This means environment variables are not set.\n\n";
    echo "HOW TO FIX:\n";
    echo "1. Edit /public_html/includes/apikeys.php\n";
    echo "2. Replace 'YOUR_GEMINI_API_KEY_HERE' with your actual API key\n";
    echo "3. Save the file\n";
    echo "4. Restart PHP-FPM or the web server\n\n";
    exit(1);
}

echo "✓ API Key loaded successfully\n\n";

// Test API connectivity
echo "3. Testing Gemini API Connectivity:\n";
echo "─────────────────────────────────────\n";

$model = 'gemini-2.5-flash';
$url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . GEMINI_API_KEY;

echo "Model: $model\n";
echo "Endpoint: " . substr($url, 0, 80) . "...\n";
echo "Sending test request...\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'contents' => [[
            'parts' => [['text' => 'Hello, say hi back!']]
        ]]
    ]),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$elapsedTime = microtime(true) - $startTime;

curl_close($ch);

echo "Response Time: " . round($elapsedTime, 2) . " seconds\n";
echo "HTTP Status: $httpCode\n\n";

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
    exit(1);
}

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['candidates']) && !empty($result['candidates'])) {
        echo "✓ Gemini API responded successfully!\n";
        echo "Response content: " . substr($result['candidates'][0]['content']['parts'][0]['text'] ?? 'N/A', 0, 100) . "...\n\n";
    } else {
        echo "❌ Unexpected response structure\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
} else {
    echo "❌ API Error (HTTP $httpCode)\n";
    $result = json_decode($response, true);
    if (isset($result['error'])) {
        echo "Error: " . $result['error']['message'] . "\n";
        
        if ($httpCode === 403 && strpos($result['error']['message'], 'leaked') !== false) {
            echo "\n⚠️  Your API key has been reported as leaked!\n";
            echo "    Get a new one: https://aistudio.google.com/apikey\n";
        }
    }
    echo "\nFull Response:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "\n=== ✓ All tests passed! ===\n";
echo "Your Gemini API is properly configured and working.\n";
if (!$pdo) {
    echo "\nNote: Database is not connected, but API key is working.\n";
    echo "Set up database for full functionality.\n";
} else {
    echo "Database and API are both working.\n";
}