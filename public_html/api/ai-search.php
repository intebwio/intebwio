<?php
/**
 * Intebwio - Advanced Search API with AI
 * Unlimited landing pages per topic - AI-powered search and generation
 * Full landing page generation using Gemini API
 * ~300 lines
 */

// Increase timeout for content aggregation and AI generation
set_time_limit(600);  // 10 minutes
ini_set('default_socket_timeout', 180);  // 3 minutes for socket operations

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ContentAggregator.php';
require_once __DIR__ . '/../includes/AIService.php';
require_once __DIR__ . '/../includes/AdvancedPageGenerator.php';

try {
    error_log("=== PAGE GENERATION START ===");
    error_log("Memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
    error_log("Max memory: " . ini_get('memory_limit'));
    
    // Check if PDO is available before proceeding
    if (!isset($pdo)) {
        error_log("ERROR: PDO not initialized");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    error_log("Step 1: PDO connection verified");
    
    // Accept both POST JSON and GET parameters
    $data = json_decode(file_get_contents('php://input'), true) ?? $_GET;
    error_log("Step 2: Input data parsed");
    
    if (!isset($data['query']) || empty(trim($data['query']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Query parameter is required']);
        exit;
    }
    
    $searchQuery = trim($data['query']);
    error_log("Step 3: Query received: '$searchQuery'")
    $useExisting = isset($data['use_existing']) ? (bool)$data['use_existing'] : false;
    $skipAggregation = isset($data['skip_aggregation']) ? (bool)$data['skip_aggregation'] : false;
    
    // Normalize query (lowercase, remove extra spaces)
    $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $searchQuery));
    error_log("Step 4: Query normalized to: '$normalizedQuery'");
    
    // Input validation
    if (strlen($normalizedQuery) < 2 || strlen($normalizedQuery) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Query must be between 2 and 500 characters']);
        exit;
    }
    
    error_log("Step 5: Input validation passed")
    
    // Optional: Check for existing page if requested
    if ($useExisting && function_exists('class_exists') && class_exists('Database')) {
        $stmt = $pdo->prepare("
            SELECT id, view_count FROM pages 
            WHERE LOWER(search_query) = LOWER(?)
            ORDER BY view_count DESC
            LIMIT 1
        ");
        $stmt->execute([$normalizedQuery]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'page_id' => $existing['id'],
                'is_new' => false,
                'message' => 'Returning existing page'
            ]);
            exit;
        }
    }
    
    // Generate new landing page with Gemini AI
    $startTime = microtime(true);
    
    // Step 1: Aggregate content from multiple sources (optional, can skip for speed)
    $aggregatedResults = [];
    error_log("Step 6: Starting content aggregation (skip: " . ($skipAggregation ? 'yes' : 'no') . ")");
    
    if (!$skipAggregation) {
        error_log("Content aggregation: Starting for query '$searchQuery'");
        $aggregationStart = microtime(true);
        
        $aggregator = new ContentAggregator($pdo);
        $aggregatedResults = $aggregator->aggregateContent($normalizedQuery, 0);
        
        $aggregationTime = microtime(true) - $aggregationStart;
        error_log("Content aggregation: Completed in " . round($aggregationTime, 2) . "s, found " . count($aggregatedResults) . " items");
        error_log("Step 7: Content aggregation complete");
    }
    
    // If no results from aggregator or skipped, use minimal content
    if (empty($aggregatedResults)) {
        $aggregatedResults = [[
            'title' => $normalizedQuery,
            'description' => 'Information about ' . $normalizedQuery,
            'source' => 'AI Generated'
        ]];
        error_log("Using fallback aggregated results");
    }
    
    // Step 2: Generate AI content with Gemini
    $aiService = new AIService(
        'gemini',
        GEMINI_API_KEY
    );
    
    error_log("Step 8: AIService created, starting content generation");
    error_log("Memory before AI generation: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
    
    // Generate comprehensive HTML content
    $aiContent = $aiService->generatePageContent($normalizedQuery, $aggregatedResults);
    
    error_log("Memory after AI generation: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
    error_log("Step 9: AI content generated, length: " . strlen($aiContent ?? '') . " chars");
    
    if (!$aiContent) {
        error_log("ERROR: AI content generation returned empty/null");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate content using Gemini API'
        ]);
        exit;
    }
    
    // Step 3: Generate full landing page using AdvancedPageGenerator
    error_log("Step 10: Creating AdvancedPageGenerator");
    $pageGenerator = new AdvancedPageGenerator($pdo, $aiService);
    
    error_log("Step 11: Generating beautiful page");
    $htmlContent = $pageGenerator->generateAIPage($normalizedQuery, $aggregatedResults, $aiContent);
    
    error_log("Step 12: Page generated, length: " . strlen($htmlContent ?? '') . " chars");
    error_log("Memory after page generation: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
    
    if (!$htmlContent) {
        error_log("ERROR: Page generation returned empty/null");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate complete landing page'
        ]);
        exit;
    }
    
    // Step 4: Extract metadata from content
    error_log("Step 13: Extracting metadata");
    $title = ucfirst($normalizedQuery);
    preg_match('/<meta name="description" content="([^"]*)"/', $htmlContent, $metaMatch);
    $description = $metaMatch[1] ?? 'AI-generated comprehensive page about ' . $normalizedQuery;
    error_log("Step 14: Metadata extracted - Title: '$title', Description length: " . strlen($description));
    
    // Step 5: Store page in database (if database available)
    error_log("Step 15: Starting database insert");
    $pageId = null;
    $pageSlug = null;
    if (function_exists('class_exists')) {
        try {
            // Generate URL-friendly slug from query
            $pageSlug = strtolower(trim($normalizedQuery));
            $pageSlug = preg_replace('/[^a-z0-9]+/', '-', $pageSlug);
            $pageSlug = trim($pageSlug, '-');
            
            // Make slug unique by adding timestamp if needed
            $baseSlug = $pageSlug;
            $slugExists = true;
            $counter = 1;
            
            while ($slugExists && $counter <= 10) {
                $checkStmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
                $checkStmt->execute([$pageSlug]);
                $slugExists = $checkStmt->fetch() !== false;
                
                if ($slugExists) {
                    // Add timestamp suffix to make unique
                    $pageSlug = $baseSlug . '-' . substr(time(), -4) . '-' . $counter;
                    $counter++;
                }
            }
            
            error_log("Preparing INSERT with slug: '$pageSlug'");
            $insertStmt = $pdo->prepare("
                INSERT INTO pages (
                    query, slug, title, description, html_content, 
                    ai_provider, ai_model, view_count, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'active', NOW(), NOW())
            ");
            
            error_log("Executing INSERT with: query='$normalizedQuery', slug='$pageSlug', title_len=" . strlen($title) . ", desc_len=" . strlen($description) . ", html_len=" . strlen($htmlContent));
            
            $insertStmt->execute([
                $normalizedQuery,
                $pageSlug,
                $title,
                substr($description, 0, 500),
                $htmlContent,
                'gemini',
                'gemini-2.5-flash'
            ]);
            
            $pageId = $pdo->lastInsertId();
            error_log("Step 16: Database insert successful, page_id: $pageId, slug: $pageSlug");
        } catch (Exception $dbError) {
            error_log("Database insert error: " . $dbError->getMessage());
            error_log("This is not critical - page is still generated, just not stored");
        }
    }
    
    // Step 6: Cache the generated page
    error_log("Step 17: Setting up cache");
    $cacheKey = 'page_' . md5($normalizedQuery . '_' . time());
    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, [
            'page_id' => $pageId,
            'query' => $normalizedQuery,
            'timestamp' => time(),
            'html' => $htmlContent
        ], 604800); // 7 days
        error_log("Step 18: Cache stored with key: $cacheKey");
    } else {
        error_log("Step 18: APCu not available, skipping cache");
    }
    
    $generationTime = microtime(true) - $startTime;
    error_log("Step 19: Total generation time: " . round($generationTime, 2) . "s");
    error_log("Memory peak: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . "MB");
    
    // Step 7: Return successful response with full page content
    error_log("Step 20: Building JSON response");
    http_response_code(201); // Created
    
    $responseData = [
        'success' => true,
        'message' => 'Full landing page generated successfully using Gemini AI',
        'page_id' => $pageId,
        'page_slug' => $pageSlug ?? null,
        'page_url' => $pageSlug ? ('/?q=' . urlencode($normalizedQuery) . '&slug=' . $pageSlug) : null,
        'is_new' => true,
        'query' => $searchQuery,
        'normalized_query' => $normalizedQuery,
        'title' => $title,
        'description' => $description,
        'html' => $htmlContent,
        'metadata' => [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-flash',
            'generation_time_seconds' => round($generationTime, 2),
            'sources_count' => count($aggregatedResults),
            'cache_key' => $cacheKey,
            'timestamp' => date('Y-m-d H:i:s'),
            'content_length' => strlen($htmlContent),
            'content_words' => str_word_count(strip_tags($htmlContent))
        ]
    ];
    
    error_log("Step 21: Response data prepared, page_url: " . ($pageSlug ? ('/?q=' . urlencode($normalizedQuery) . '&slug=' . $pageSlug) : 'N/A') . ", size: " . strlen(json_encode($responseData)) . " bytes");
    
    // Try to encode response, with fallback
    $jsonResponse = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonResponse === false) {
        // If JSON encoding fails, remove large HTML and try again
        error_log("ERROR: JSON encoding failed for full response, attempting fallback");
        $jsonResponse = json_encode([
            'success' => true,
            'message' => 'Page generated successfully (metadata only)',
            'page_id' => $pageId,
            'page_slug' => $pageSlug ?? null,
            'page_url' => $pageSlug ? ('/?q=' . urlencode($normalizedQuery) . '&slug=' . $pageSlug) : null,
            'is_new' => true,
            'query' => $searchQuery,
            'title' => $title,
            'description' => $description,
            'metadata' => $responseData['metadata']
        ]);
        error_log("Step 22: Using fallback response (metadata only)");
    } else {
        error_log("Step 22: JSON encoding successful, response size: " . strlen($jsonResponse) . " bytes");
    }
    
    error_log("Step 23: Sending response to client");
    echo $jsonResponse;
    error_log("=== PAGE GENERATION COMPLETE ===");
    
    
} catch (Exception $e) {
    error_log("=== PAGE GENERATION FAILED ===");
    error_log("Exception Type: " . get_class($e));
    error_log("Exception Message: " . $e->getMessage());
    error_log("Stack Trace:\n" . $e->getTraceAsString());
    error_log("Memory at error: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
    
    http_response_code(500);
    
    // Provide detailed error message for debugging
    $errorResponse = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'type' => get_class($e),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Include file/line info in debug mode
    if (ini_get('display_errors')) {
        $errorResponse['file'] = $e->getFile();
        $errorResponse['line'] = $e->getLine();
    }
    
    error_log("Sending error response to client");
    echo json_encode($errorResponse);
    error_log("=== ERROR RESPONSE SENT ===");
}