<?php
/**
 * Complete Gemini API Issue Diagnostic
 * Shows problems and provides solutions
 */

require_once __DIR__ . '/includes/config.php';

$apiKey = GEMINI_API_KEY;

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       Gemini API - Complete Issue Diagnosis Report         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 1. Check API Key
echo "1. API KEY CHECK\n";
echo "─────────────────────────────────────────────────────────────\n";

if (empty($apiKey)) {
    echo "❌ API Key is EMPTY\n";
    echo "   Solution: Add GEMINI_API_KEY to config.php\n\n";
    exit(1);
} elseif (strpos($apiKey, 'YOUR_') !== false) {
    echo "❌ API Key is a PLACEHOLDER\n";
    echo "   Current: $apiKey\n";
    echo "   Solution: Replace with real API key from https://aistudio.google.com/apikey\n\n";
    exit(1);
}

echo "✓ API Key Present\n";
echo "  Length: " . strlen($apiKey) . " characters\n";
echo "  First 20 chars: " . substr($apiKey, 0, 20) . "...\n\n";

// 2. Test API Key Validity
echo "2. API KEY VALIDITY TEST\n";
echo "─────────────────────────────────────────────────────────────\n";

$testUrl = 'https://generativelanguage.googleapis.com/v1/models?key=' . $apiKey;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

if ($httpCode === 400 || $httpCode === 401 || $httpCode === 403) {
    echo "❌ API Key INVALID or DISABLED\n\n";
    
    if (isset($responseData['error']['message'])) {
        $msg = $responseData['error']['message'];
        echo "Error: $msg\n\n";
        
        if (strpos($msg, 'leaked') !== false) {
            echo "⚠️  KEY HAS BEEN REPORTED AS LEAKED!\n";
            echo "   Google disabled this API key for security reasons.\n";
            echo "   You MUST obtain a new API key.\n\n";
        } elseif (strpos($msg, 'restricted to IP') !== false) {
            echo "⚠️  KEY IS RESTRICTED TO SPECIFIC IP ADDRESSES\n";
            echo "   Your current IP is not authorized.\n\n";
        }
    }
} elseif ($httpCode === 200) {
    echo "✓ API Key is VALID and ACTIVE\n";
    
    if (isset($responseData['models'])) {
        echo "✓ Found " . count($responseData['models']) . " available models\n\n";
    }
} else {
    echo "⚠️  Unexpected HTTP $httpCode\n";
    echo "Response: " . substr($response, 0, 200) . "\n\n";
}

// 3. Test Content Generation
if ($httpCode === 200) {
    echo "3. CONTENT GENERATION TEST\n";
    echo "─────────────────────────────────────────────────────────────\n";
    
    $genUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
    
    $genData = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Hello, say "hello world"']
                ]
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $genUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($genData),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200) {
        echo "✓ Content generation WORKS\n\n";
    } else {
        echo "❌ Content generation FAILED (HTTP $httpCode)\n";
        
        if (isset($responseData['error']['message'])) {
            $msg = $responseData['error']['message'];
            echo "Error: $msg\n\n";
            
            if (strpos($msg, 'not found') !== false) {
                echo "⚠️  MODEL NOT FOUND\n";
                echo "   Try: gemini-pro, gemini-1.5-pro, gemini-1.0-pro\n\n";
            }
        }
    }
}

// 4. Summary & Solutions
echo "4. SOLUTIONS\n";
echo "─────────────────────────────────────────────────────────────\n";

$solutions = [];

if (!$apiKey || strpos($apiKey, 'YOUR_') !== false) {
    $solutions[] = [
        'issue' => 'Missing or placeholder API key',
        'fix' => 'Get new API key at https://aistudio.google.com/apikey'
    ];
}

if ($httpCode === 403) {
    $solutions[] = [
        'issue' => 'API Key disabled or invalid',
        'fix' => 'Generate a NEW API key at https://aistudio.google.com/apikey'
    ];
}

if (empty($solutions)) {
    echo "✓ No API issues detected!\n";
    echo "  If content generation still fails, check:\n";
    echo "  - Network connectivity\n";
    echo "  - Check error logs: /workspaces/intebwio/public_html/logs/intebwio.log\n";
    echo "  - Verify prompt format\n";
} else {
    foreach ($solutions as $i => $s) {
        echo "\n" . ($i + 1) . ". " . strtoupper($s['issue']) . "\n";
        echo "   → Solution: " . $s['fix'] . "\n";
    }
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║                   Diagnosis Complete                       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";