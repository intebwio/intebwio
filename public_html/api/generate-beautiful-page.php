<?php
/**
 * Intebwio - Enhanced Page Generation API
 * Generates beautiful AI-powered pages with visualizations and stores to database
 * ~350 lines
 */

// Start output buffering to prevent accidental output
ob_start();

// Increase timeout for content generation
set_time_limit(300);  // 5 minutes
ini_set('default_socket_timeout', 120);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/AIService.php';
require_once __DIR__ . '/../includes/EnhancedPageGeneratorV2.php';

// Clean any accidental output from includes
ob_end_clean();
ob_start();

try {
    // Get input data (handle both JSON and GET parameters)
    $jsonInput = file_get_contents('php://input');
    $data = null;
    
    if (!empty($jsonInput)) {
        $data = json_decode($jsonInput, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON input: ' . json_last_error_msg(),
                'code' => 'INVALID_JSON'
            ]);
            exit;
        }
    }
    
    // Fallback to GET parameters if no JSON data
    if (!$data) {
        $data = $_GET;
    }
    
    // Validate query parameter
    if (!isset($data['query']) || empty(trim($data['query']))) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Query parameter is required',
            'code' => 'MISSING_QUERY'
        ]);
        exit;
    }
    
    $searchQuery = trim($data['query']);
    $useExisting = isset($data['use_existing']) ? (bool)$data['use_existing'] : false;
    $skipAggregation = isset($data['skip_aggregation']) ? (bool)$data['skip_aggregation'] : false;
    
    // Normalize query
    $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $searchQuery));
    
    // Validate query length
    if (strlen($normalizedQuery) < 2 || strlen($normalizedQuery) > 500) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Query must be between 2 and 500 characters',
            'code' => 'INVALID_QUERY_LENGTH'
        ]);
        exit;
    }
    
    $startTime = microtime(true);
    error_log("=== Enhanced Page Generation Request ===");
    error_log("Query: $normalizedQuery");
    error_log("Use Existing: " . ($useExisting ? 'Yes' : 'No'));
    error_log("Skip Aggregation: " . ($skipAggregation ? 'Yes' : 'No'));
    
    // Step 1: Check for existing page (if database available)
    $existingPageId = null;
    if ($useExisting && is_object($pdo)) {
        try {
            error_log("Checking for existing page...");
            $stmt = $pdo->prepare("
                SELECT id, title, description, html_content, view_count, created_at 
                FROM pages 
                WHERE LOWER(search_query) = LOWER(?)
                LIMIT 1
            ");
            $stmt->execute([$normalizedQuery]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                error_log("Found existing page with ID: {$existing['id']}");
                
                // Update view count
                $updateStmt = $pdo->prepare("UPDATE pages SET view_count = view_count + 1, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$existing['id']]);
                
                ob_end_clean();
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'is_new' => false,
                    'page_id' => $existing['id'],
                    'title' => $existing['title'],
                    'description' => $existing['description'],
                    'html' => $existing['html_content'],
                    'view_count' => $existing['view_count'] + 1,
                    'created_at' => $existing['created_at'],
                    'message' => 'Retrieved existing page',
                    'metadata' => [
                        'source' => 'database',
                        'generation_time_seconds' => 0,
                        'from_cache' => true
                    ]
                ]);
                exit;
            }
        } catch (Exception $dbError) {
            error_log("Database check error: " . $dbError->getMessage());
            // Continue without database
        }
    }
    
    // Step 2: Get AI content
    error_log("Initializing AI service...");
    $aiService = new AIService('gemini', GEMINI_API_KEY);
    
    // Build comprehensive prompt for AI
    $prompt = buildEnhancedPrompt($normalizedQuery);
    
    error_log("Generating AI content...");
    $aiContentStart = microtime(true);
    $aiContent = $aiService->generatePageContent($normalizedQuery, []);
    $aiContentTime = microtime(true) - $aiContentStart;
    
    if (!$aiContent) {
        error_log("AI content generation failed");
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate content using AI',
            'code' => 'AI_GENERATION_FAILED'
        ]);
        exit;
    }
    
    error_log("AI content generated in " . round($aiContentTime, 2) . "s");
    
    // Step 3: Generate beautiful page
    error_log("Generating beautiful page layout...");
    $pageGenStart = microtime(true);
    
    $pageGenerator = new EnhancedPageGeneratorV2($pdo ?? null, $aiService);
    $htmlContent = $pageGenerator->generateBeautifulPage(
        ucfirst($normalizedQuery),
        substr($aiContent, 0, 200),
        $aiContent,
        ['timeline', 'comparison', 'statistics']
    );
    
    $pageGenTime = microtime(true) - $pageGenStart;
    
    if (!$htmlContent) {
        error_log("Page generation failed");
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate beautiful page',
            'code' => 'PAGE_GENERATION_FAILED'
        ]);
        exit;
    }
    
    error_log("Beautiful page generated in " . round($pageGenTime, 2) . "s");
    
    // Step 4: Extract metadata
    $title = ucfirst($normalizedQuery);
    $description = substr($aiContent, 0, 500);
    
    // Step 5: Store in database (if available)
    $pageId = null;
    if (is_object($pdo)) {
        try {
            error_log("Storing page in database...");
            
            $insertStmt = $pdo->prepare("
                INSERT INTO pages (
                    search_query, title, description, html_content, 
                    ai_provider, ai_model, view_count, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, 'active', NOW(), NOW())
            ");
            
            $insertStmt->execute([
                $normalizedQuery,
                $title,
                $description,
                $htmlContent,
                'gemini',
                'gemini-2.5-flash'
            ]);
            
            $pageId = $pdo->lastInsertId();
            error_log("Page stored with ID: $pageId");
            
            // Store page metadata
            $metaStmt = $pdo->prepare("
                INSERT INTO page_metadata (page_id, meta_key, meta_value, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $metadata = [
                'content_length' => strlen($htmlContent),
                'word_count' => str_word_count(strip_tags($aiContent)),
                'ai_model' => 'gemini-2.5-flash',
                'has_visualizations' => 'true',
                'generator_version' => 'v2'
            ];
            
            foreach ($metadata as $key => $value) {
                try {
                    $metaStmt->execute([$pageId, $key, $value]);
                } catch (Exception $e) {
                    error_log("Metadata insert error: " . $e->getMessage());
                }
            }
            
        } catch (Exception $dbError) {
            error_log("Database storage error: " . $dbError->getMessage());
            // Continue without database storage
            $pageId = null;
        }
    }
    
    // Step 6: Calculate total time
    $totalTime = microtime(true) - $startTime;
    
    error_log("=== Page Generation Complete ===");
    error_log("Total time: " . round($totalTime, 2) . "s");
    error_log("AI generation: " . round($aiContentTime, 2) . "s");
    error_log("Page layout: " . round($pageGenTime, 2) . "s");
    
    // Step 7: Return response
    ob_end_clean();
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'is_new' => true,
        'page_id' => $pageId,
        'title' => $title,
        'description' => substr($description, 0, 150) . '...',
        'html' => $htmlContent,
        'query' => $searchQuery,
        'normalized_query' => $normalizedQuery,
        'message' => 'Beautiful page generated successfully',
        'metadata' => [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-flash',
            'generator_version' => 'v2-enhanced',
            'generation_time_seconds' => round($totalTime, 2),
            'ai_time_seconds' => round($aiContentTime, 2),
            'layout_time_seconds' => round($pageGenTime, 2),
            'content_length' => strlen($htmlContent),
            'word_count' => str_word_count(strip_tags($aiContent)),
            'has_visualizations' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    error_log("Enhanced API Error: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
    
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'code' => 'SERVER_ERROR'
    ]);
}

/**
 * Build enhanced prompt for AI content generation
 */
function buildEnhancedPrompt($query) {
    return <<<PROMPT
You are an expert content creator. Generate a comprehensive, well-structured, and visually organized HTML page about "$query".

Requirements:
1. Create detailed, accurate content covering:
   - Overview and definition
   - Key concepts (3-5 main points)
   - Historical development
   - Current state and trends
   - Real-world applications
   - Future prospects
   - FAQs (5-7 questions)

2. Content quality:
   - Use professional language
   - Include specific facts and statistics
   - Organize into clear sections
   - Make it informative and engaging
   - Include examples where relevant

3. Structure:
   - Executive summary (2-3 sentences)
   - Main content sections
   - Key takeaways
   - Conclusion

Use HTML formatting for structure. Make it comprehensive (2000+ words).
PROMPT;
}