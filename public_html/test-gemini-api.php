<?php
/**
 * Gemini API Diagnostic Test Script
 * Run this to see detailed error messages from the Gemini API
 */

// Load configuration with API keys
require_once __DIR__ . '/includes/config.php';

$apiKey = GEMINI_API_KEY;
$apiProvider = AI_PROVIDER;

echo "=== Gemini API Diagnostic Test ===\n\n";
echo "Configuration:\n";
echo "- API Provider: " . $apiProvider . "\n";
echo "- API Key (first 20 chars): " . substr($apiKey, 0, 20) . "...\n";
echo "- API Key Length: " . strlen($apiKey) . "\n";

if ($apiKey === 'YOUR_GEMINI_API_KEY' || empty($apiKey)) {
    echo "\n❌ ERROR: API key is not configured properly!\n";
    exit(1);
}

echo "\n--- Testing Gemini API Connection ---\n";

$testPrompt = "Write a brief hello world message.";
$modelName = 'gemini-2.5-flash';  // Latest Gemini model

$url = 'https://generativelanguage.googleapis.com/v1/models/' . $modelName . ':generateContent?key=' . $apiKey;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $testPrompt]
            ]
        ]
    ]
];

echo "\nRequest Details:\n";
echo "- URL: " . substr($url, 0, 80) . "...\n";
echo "- Method: POST\n";
echo "- Payload Size: " . strlen(json_encode($data)) . " bytes\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_VERBOSE => true
]);

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

curl_close($ch);

echo "\n--- cURL Response ---\n";
echo "HTTP Status Code: " . $httpCode . "\n";

if ($curlError) {
    echo "\n❌ cURL Error: " . $curlError . "\n";
}

echo "\nResponse Body:\n";
echo "---\n";
if ($response) {
    // Try to format JSON response
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo $response . "\n";
    }
} else {
    echo "(No response body)\n";
}
echo "---\n";

// Parse and display common Gemini API errors
if ($httpCode !== 200) {
    echo "\n❌ API Request Failed\n";
    
    $decoded = json_decode($response, true);
    if (isset($decoded['error'])) {
        echo "\nAPI Error Details:\n";
        echo "- Code: " . ($decoded['error']['code'] ?? 'unknown') . "\n";
        echo "- Message: " . ($decoded['error']['message'] ?? 'unknown') . "\n";
        if (isset($decoded['error']['details'])) {
            echo "- Details: " . json_encode($decoded['error']['details'], JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    // Provide solutions based on HTTP code
    echo "\nPossible Causes:\n";
    switch ($httpCode) {
        case 400:
            echo "- Invalid API request format\n";
            echo "- Check that the prompt and request structure is correct\n";
            break;
        case 401:
            echo "- Invalid or missing API key\n";
            echo "- Check GEMINI_API_KEY in config.php\n";
            break;
        case 403:
            echo "- API key does not have permission to access Gemini API\n";
            echo "- Check that Gemini API is enabled in Google Cloud Console\n";
            break;
        case 429:
            echo "- Rate limit exceeded\n";
            echo "- Wait before making another request\n";
            break;
        case 500:
        case 503:
            echo "- Google Gemini API is temporarily unavailable\n";
            echo "- Try again later\n";
            break;
        default:
            echo "- HTTP $httpCode error\n";
            break;
    }
} else {
    echo "\n✅ API Request Successful!\n";
    
    $decoded = json_decode($response, true);
    if (isset($decoded['candidates']) && !empty($decoded['candidates'])) {
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text) {
            echo "\nGenerated Content:\n";
            echo $text . "\n";
        }
    }
}

echo "\n=== End of Diagnostic Test ===\n";