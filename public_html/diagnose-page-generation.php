<?php
/**
 * Diagnostic Script for Page Generation Issues
 * Helps identify why page generation returns 500 error
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Page Generation Diagnostic</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ddd; }
        .success { border-left-color: #28a745; background: #f0f8f4; }
        .warning { border-left-color: #ffc107; background: #fffbf0; }
        .error { border-left-color: #dc3545; background: #fdf8f7; }
        .title { font-weight: bold; color: #333; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; }
        h2 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
    </style>
</head>
<body>

<h2>Intebwio - Page Generation Diagnostic</h2>\n";

$checks = [];

// 1. Check if config.php can be loaded
echo "<h3>1. Configuration Loading</h3>";
try {
    $configFile = __DIR__ . '/includes/config.php';
    if (!file_exists($configFile)) {
        echo '<div class="box error"><span class="title">❌ config.php not found</span><br>' . $configFile . '</div>';
        $checks[] = false;
    } else {
        require_once $configFile;
        echo '<div class="box success"><span class="title">✓ config.php loaded successfully</span></div>';
        $checks[] = true;
    }
} catch (Exception $e) {
    echo '<div class="box error"><span class="title">❌ Error loading config.php</span><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    $checks[] = false;
}

// 2. Check Database Connection
echo "<h3>2. Database Connection</h3>";
try {
    if (isset($pdo)) {
        $result = $pdo->query("SELECT 1");
        if ($result) {
            echo '<div class="box success"><span class="title">✓ Database connection works</span><br>PDO is connected</div>';
            $checks[] = true;
        } else {
            echo '<div class="box error"><span class="title">❌ Database query failed</span></div>';
            $checks[] = false;
        }
    } else {
        echo '<div class="box error"><span class="title">❌ PDO not initialized</span><br>$pdo variable not set</div>';
        $checks[] = false;
    }
} catch (Exception $e) {
    echo '<div class="box error"><span class="title">❌ Database connection error</span><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    $checks[] = false;
}

// 3. Check Required Classes
echo "<h3>3. Required PHP Classes</h3>";
$requiredFiles = [
    'AIService.php',
    'AdvancedPageGenerator.php',
    'ContentAggregator.php',
    'PageGenerator.php'
];

foreach ($requiredFiles as $file) {
    $path = __DIR__ . '/includes/' . $file;
    if (file_exists($path)) {
        echo '<div class="box success"><span class="title">✓</span> ' . $file . ' found</div>';
        $checks[] = true;
    } else {
        echo '<div class="box error"><span class="title">❌</span> ' . $file . ' NOT FOUND</div>';
        $checks[] = false;
    }
}

// 4. Check API Configuration
echo "<h3>4. API Configuration</h3>";
if (defined('GEMINI_API_KEY')) {
    $key = GEMINI_API_KEY;
    $keyStatus = (strpos($key, 'AIzaSy') === 0 && strlen($key) > 30) ? 'valid format' : 'invalid format';
    $masked = substr($key, 0, 10) . '...' . substr($key, -5);
    echo '<div class="box ' . (strpos($keyStatus, 'valid') !== false ? 'success' : 'warning') . '"><span class="title">Gemini API Key:</span> ' . $masked . ' (' . $keyStatus . ')</div>';
    $checks[] = strpos($keyStatus, 'valid') !== false;
} else {
    echo '<div class="box error"><span class="title">❌ GEMINI_API_KEY not defined</span></div>';
    $checks[] = false;
}

if (defined('AI_PROVIDER')) {
    $provider = AI_PROVIDER;
    echo '<div class="box success"><span class="title">AI Provider:</span> ' . htmlspecialchars($provider) . '</div>';
} else {
    echo '<div class="box error"><span class="title">❌ AI_PROVIDER not defined</span></div>';
    $checks[] = false;
}

// 5. Test Page Generation
echo "<h3>5. Test Page Generation</h3>";

try {
    // Check if we can include the required files
    require_once __DIR__ . '/includes/AIService.php';
    require_once __DIR__ . '/includes/AdvancedPageGenerator.php';
    
    echo '<div class="box warning"><span class="title">⚠ Classes loaded</span><br>
    Test: Creating AIService instance...<br>';
    
    $aiService = new AIService('gemini', GEMINI_API_KEY);
    echo '✓ AIService created<br>';
    
    echo 'Test: Generating sample content...<br>';
    
    // Try to generate a small test prompt
    $testQuery = 'test';
    $result = $aiService->generatePageContent($testQuery, []);
    
    if ($result) {
        echo '✓ AI content generated successfully (' . strlen($result) . ' chars)<br>';
        $checks[] = true;
    } else {
        echo '❌ AI content generation returned null<br>Check error logs<br>';
        $checks[] = false;
    }
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="box error"><span class="title">❌ Error during test</span><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    $checks[] = false;
}

// 6. Check JSON Encoding
echo "<h3>6. JSON Encoding Test</h3>";
try {
    $testData = [
        'success' => true,
        'html' => '<html><body>Test HTML content with special chars: é à ü & < > " \'</body></html>',
        'text' => 'Test text with emoji 🎉 and special chars'
    ];
    
    $json = json_encode($testData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json !== false) {
        echo '<div class="box success"><span class="title">✓ JSON encoding works</span><br>Encoded size: ' . strlen($json) . ' bytes</div>';
        $checks[] = true;
    } else {
        echo '<div class="box error"><span class="title">❌ JSON encoding failed</span><br>Last error: ' . json_last_error_msg() . '</div>';
        $checks[] = false;
    }
} catch (Exception $e) {
    echo '<div class="box error"><span class="title">❌ JSON test error</span><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    $checks[] = false;
}

// 7. Check Error Logging
echo "<h3>7. Logging Configuration</h3>";
if (defined('LOG_FILE')) {
    $logFile = LOG_FILE;
    $logDir = dirname($logFile);
    
    if (is_dir($logDir)) {
        if (is_writable($logDir)) {
            echo '<div class="box success"><span class="title">✓ Log directory writable</span><br>' . htmlspecialchars($logDir) . '</div>';
            $checks[] = true;
        } else {
            echo '<div class="box error"><span class="title">❌ Log directory not writable</span><br>' . htmlspecialchars($logDir) . '</div>';
            $checks[] = false;
        }
    } else {
        echo '<div class="box warning"><span class="title">⚠ Log directory does not exist</span><br>' . htmlspecialchars($logDir) . '</div>';
        $checks[] = true; // Not critical
    }
} else {
    echo '<div class="box warning"><span class="title">⚠ LOG_FILE not defined</span></div>';
    $checks[] = true; // Not critical
}

// Summary
echo "<h2>Summary</h2>";
$passed = count(array_filter($checks));
$total = count($checks);
$percentage = $total > 0 ? ($passed / $total * 100) : 0;

$statusColor = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'error');
echo '<div class="box ' . $statusColor . '">';
echo '<span class="title">Overall Status: ' . round($percentage) . '% (' . $passed . '/' . $total . ')</span><br>';

if ($percentage >= 80) {
    echo '<strong style="color: #28a745;">✓ System appears to be configured correctly</strong><br>';
    echo 'If you still get 500 errors, check your server error logs for more details.';
} elseif ($percentage >= 50) {
    echo '<strong style="color: #ffc107;">⚠ Some issues detected</strong><br>';
    echo 'Please fix the warnings above before trying to generate pages.';
} else {
    echo '<strong style="color: #dc3545;">❌ Critical issues found</strong><br>';
    echo 'Please fix the errors above - page generation will not work.';
}

echo '</div>';

// Show next steps
echo "<h2>Next Steps</h2>";
echo '<div class="box">';
echo 'If all checks pass:<br>';
echo '1. Try generating a page via the landing page generator<br>';
echo '2. Check your Hostinger server error logs at <code>/home/cpanel-username/public_html/logs/</code><br>';
echo '3. Enable error logging by checking the config<br>';
echo '<br>';
echo 'If checks fail:<br>';
echo '1. Fix the issues listed above<br>';
echo '2. Make sure your API key is correct and not revoked<br>';
echo '3. Ensure database credentials are correct<br>';
echo '</div>';

echo "</body></html>";