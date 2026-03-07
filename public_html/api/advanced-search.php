<?php
/**
 * Intebwio - Enhanced Search API
 * Advanced search endpoint with filtering and faceting
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/AdvancedSearchEngine.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : 'search';
    $engine = new AdvancedSearchEngine($pdo);
    
    switch ($action) {
        case 'search':
            // Multi-faceted search
            $query = $_GET['q'] ?? '';
            
            if (strlen($query) < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Query too short']);
                exit;
            }
            
            $filters = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'min_views' => $_GET['min_views'] ?? null,
                'min_relevance' => $_GET['min_relevance'] ?? null,
                'sources' => isset($_GET['sources']) ? explode(',', $_GET['sources']) : [],
                'sort_by' => $_GET['sort_by'] ?? 'relevance',
                'limit' => intval($_GET['limit'] ?? 20),
                'offset' => intval($_GET['offset'] ?? 0)
            ];
            
            $result = $engine->search($query, $filters);
            echo json_encode($result);
            break;
            
        case 'suggestions':
            // Auto-complete suggestions
            $query = $_GET['q'] ?? '';
            $limit = intval($_GET['limit'] ?? 10);
            
            $result = $engine->getSuggestions($query, $limit);
            echo json_encode($result);
            break;
            
        case 'related':
            // Get related searches
            $pageId = intval($_GET['page_id'] ?? 0);
            
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                exit;
            }
            
            $result = $engine->getRelatedSearches($pageId);
            echo json_encode($result);
            break;
            
        case 'analytics':
            // Get search analytics
            $query = $_GET['query'] ?? null;
            
            $result = $engine->getSearchAnalytics($query);
            echo json_encode($result);
            break;
            
        case 'relevance':
            // Calculate relevance score
            $pageId = intval($_GET['page_id'] ?? 0);
            
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                exit;
            }
            
            $result = $engine->calculateRelevanceScore($pageId);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Advanced search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}