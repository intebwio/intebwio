<?php
/**
 * Intebwio - Search API Endpoint
 * Handles search queries and page creation/retrieval
 */

// Increase timeout for content aggregation and AI generation
set_time_limit(600);  // 10 minutes
ini_set('default_socket_timeout', 180);  // 3 minutes for socket operations

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ContentAggregator.php';
require_once __DIR__ . '/../includes/PageGenerator.php';

// Handle CORS preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure database is available before doing anything
requireDatabase();

try {
    // Get POST data - handle both JSON and form data
    $data = [];
    
    // Try to parse JSON from POST body first
    $postData = file_get_contents('php://input');
    if (!empty($postData)) {
        $jsonData = json_decode($postData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            $data = $jsonData;
        }
    }
    
    // Fall back to POST/GET parameters if JSON parsing failed or was empty
    if (empty($data)) {
        $data = $_REQUEST; // This includes both GET and POST parameters
    }
    
    if (!isset($data['query']) || empty(trim($data['query']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Query is required']);
        exit;
    }
    
    $searchQuery = trim($data['query']);
    
    // Validate search query
    if (strlen($searchQuery) < 2 || strlen($searchQuery) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Query must be between 2 and 500 characters']);
        exit;
    }
    
    // Initialize classes
    $db = new Database($pdo);
    $aggregator = new ContentAggregator($pdo);
    $generator = new PageGenerator($pdo);
    
    // Record activity
    $db->recordActivity(null, $searchQuery, 'search');
    
    // Check if page already exists
    $existingPages = $db->searchPages($searchQuery);
    
    if (!empty($existingPages)) {
        // Use existing page
        $pageId = $existingPages[0]['id'];
        $db->updateViewCount($pageId);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'exists' => true,
            'page_id' => $pageId,
            'message' => 'Existing page found'
        ]);
        exit;
    }
    
    // Create new page
    // Step 1: Aggregate content from multiple sources
    $results = $aggregator->aggregateContent($searchQuery, 0);
    
    if (empty($results)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to aggregate content'
        ]);
        exit;
    }
    
    // Step 2: Generate HTML page
    $htmlContent = $generator->generatePage($searchQuery, $results);
    
    // Step 3: Extract page metadata
    $title = ucfirst($searchQuery);
    $description = 'Comprehensive information about ' . $searchQuery . ' curated from the best sources.';
    
    // Get first image from results as thumbnail
    $thumbnailImage = null;
    foreach ($results as $result) {
        if (!empty($result['image_url'])) {
            $thumbnailImage = $result['image_url'];
            break;
        }
    }
    
    // Step 4: Store in database
    $pageId = $db->createOrGetPage($searchQuery, $title, $description, $htmlContent);
    
    if (!$pageId) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create page'
        ]);
        exit;
    }
    
    // Step 5: Store aggregated content
    foreach ($results as $index => $result) {
        $result['position_index'] = $index;
        $db->addSearchResult($pageId, $result);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'exists' => false,
        'page_id' => $pageId,
        'message' => 'Page created successfully',
        'results_count' => count($results)
    ]);
    
} catch (Exception $e) {
    error_log("Search API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}