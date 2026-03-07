<?php
/**
 * Timeout Debugging Script
 * Tests page generation with detailed timing information
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);  // No limit for this script

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ContentAggregator.php';
require_once __DIR__ . '/includes/AIService.php';
require_once __DIR__ . '/includes/AdvancedPageGenerator.php';

echo "=== Page Generation Timeout Test ===\n\n";

$testQuery = "artificial intelligence";

echo "Test Configuration:\n";
echo "- Query: $testQuery\n";
echo "- Max execution time: " . ini_get('max_execution_time') . "s\n";
echo "- Socket timeout: " . ini_get('default_socket_timeout') . "s\n";
echo "- AI Provider: " . AI_PROVIDER . "\n";
echo "- API Key: " . substr(GEMINI_API_KEY, 0, 20) . "...\n\n";

$globalStart = microtime(true);

// Step 1: Content Aggregation
echo "Step 1: Content Aggregation\n";
echo "────────────────────────────\n";
$step1Start = microtime(true);

try {
    $aggregator = new ContentAggregator($pdo ?? null);
    $results = $aggregator->aggregateContent($testQuery, 0);
    
    $step1Time = microtime(true) - $step1Start;
    echo "✓ Completed in " . round($step1Time, 2) . " seconds\n";
    echo "  Found " . count($results) . " content items\n\n";
    
} catch (Exception $e) {
    $step1Time = microtime(true) - $step1Start;
    echo "✗ Failed in " . round($step1Time, 2) . " seconds\n";
    echo "  Error: " . $e->getMessage() . "\n\n";
    $results = [];
}

// Step 2: AI Content Generation
echo "Step 2: AI Content Generation (Gemini API)\n";
echo "──────────────────────────────────────────\n";
$step2Start = microtime(true);

try {
    $aiService = new AIService('gemini', GEMINI_API_KEY);
    
    echo "Calling AI model...\n";
    $aiContent = $aiService->generatePageContent($testQuery, $results);
    
    $step2Time = microtime(true) - $step2Start;
    
    if ($aiContent) {
        echo "✓ Completed in " . round($step2Time, 2) . " seconds\n";
        echo "  Generated " . strlen($aiContent) . " characters\n";
        echo "  Word count: " . str_word_count(strip_tags($aiContent)) . "\n\n";
    } else {
        echo "✗ Failed in " . round($step2Time, 2) . " seconds\n";
        echo "  No content generated\n\n";
        exit(1);
    }
    
} catch (Exception $e) {
    $step2Time = microtime(true) - $step2Start;
    echo "✗ Failed in " . round($step2Time, 2) . " seconds\n";
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 3: Page Generation
echo "Step 3: Page Generation\n";
echo "──────────────────────────\n";
$step3Start = microtime(true);

try {
    $pageGenerator = new AdvancedPageGenerator($pdo ?? null, $aiService);
    $htmlContent = $pageGenerator->generateAIPage($testQuery, $results, $aiContent);
    
    $step3Time = microtime(true) - $step3Start;
    
    if ($htmlContent) {
        echo "✓ Completed in " . round($step3Time, 2) . " seconds\n";
        echo "  Generated " . strlen($htmlContent) . " bytes of HTML\n\n";
    } else {
        echo "✗ Failed in " . round($step3Time, 2) . " seconds\n";
        echo "  No HTML generated\n\n";
        exit(1);
    }
    
} catch (Exception $e) {
    $step3Time = microtime(true) - $step3Start;
    echo "✗ Failed in " . round($step3Time, 2) . " seconds\n";
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Summary
echo "=== Total Generation Time ===\n";
$totalTime = microtime(true) - $globalStart;
echo "Total: " . round($totalTime, 2) . " seconds\n\n";

echo "Breakdown:\n";
echo "- Content Aggregation: " . round($step1Time, 2) . "s (" . round(($step1Time/$totalTime)*100) . "%)\n";
echo "- AI Generation:       " . round($step2Time, 2) . "s (" . round(($step2Time/$totalTime)*100) . "%)\n";
echo "- Page Generation:     " . round($step3Time, 2) . "s (" . round(($step3Time/$totalTime)*100) . "%)\n";

if ($totalTime > 120) {
    echo "\n⚠️  Warning: Total time exceeds 2 minutes!\n";
    echo "   Consider:\n";
    echo "   1. Using background job processing\n";
    echo "   2. Improving content aggregator speed\n";
    echo "   3. Caching aggregated content\n";
} else if ($totalTime > 30) {
    echo "\n⚠️  Warning: Total time exceeds 30 seconds!\n";
    echo "   This may timeout in browser requests\n";
} else {
    echo "\n✓ Generation time is acceptable!\n";
}

echo "\n=== End of Test ===\n";