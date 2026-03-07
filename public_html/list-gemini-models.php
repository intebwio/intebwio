<?php
/**
 * List all available Gemini models
 */

require_once __DIR__ . '/includes/config.php';

$apiKey = GEMINI_API_KEY;

echo "=== Available Gemini Models ===\n\n";
echo "Fetching model list from Google API...\n";

$url = 'https://generativelanguage.googleapis.com/v1/models?key=' . $apiKey;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nHTTP Status: $httpCode\n\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    
    if (isset($result['models']) && !empty($result['models'])) {
        echo "Found " . count($result['models']) . " models:\n\n";
        
        foreach ($result['models'] as $model) {
            $name = $model['name'] ?? 'Unknown';
            $displayName = $model['displayName'] ?? $name;
            $version = $model['version'] ?? 'N/A';
            
            // Extract just the model name without "models/"
            $shortName = str_replace('models/', '', $name);
            
            echo "✓ $shortName\n";
            echo "  Display: $displayName\n";
            echo "  Version: $version\n";
            
            if (isset($model['supportedGenerationMethods'])) {
                echo "  Methods: " . implode(', ', $model['supportedGenerationMethods']) . "\n";
            }
            
            echo "\n";
        }
    }
} else {
    echo "Error fetching models:\n";
    echo "Response: " . $response . "\n";
}