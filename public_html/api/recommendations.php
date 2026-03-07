<?php
/**
 * Intebwio - Recommendations API
 * Provides personalized recommendations and trending content
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/RecommendationEngine.php';
require_once __DIR__ . '/../includes/SessionManager.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : 'personalized';
    $engine = new RecommendationEngine($pdo);
    $sessionManager = new SessionManager($pdo);
    
    switch ($action) {
        case 'personalized':
            // Get personalized recommendations
            $sessionId = $_COOKIE['intebwio_session'] ?? session_id();
            $limit = intval($_GET['limit'] ?? 10);
            
            $recommendations = $engine->getPersonalizedRecommendations($sessionId, $limit);
            echo json_encode([
                'success' => true,
                'recommendations' => $recommendations,
                'type' => 'personalized'
            ]);
            break;
            
        case 'trending':
            // Get trending pages
            $days = intval($_GET['days'] ?? 7);
            $limit = intval($_GET['limit'] ?? 20);
            
            $trending = $engine->getTrendingPages($days, $limit);
            echo json_encode([
                'success' => true,
                'pages' => $trending,
                'type' => 'trending',
                'period_days' => $days
            ]);
            break;
            
        case 'featured':
            // Get featured pages
            $limit = intval($_GET['limit'] ?? 10);
            
            $featured = $engine->getFeaturedPages($limit);
            echo json_encode([
                'success' => true,
                'pages' => $featured,
                'type' => 'featured'
            ]);
            break;
            
        case 'related':
            // Get related content
            $pageId = intval($_GET['page_id'] ?? 0);
            
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                exit;
            }
            
            $limit = intval($_GET['limit'] ?? 8);
            $related = $engine->getRelatedContent($pageId, $limit);
            echo json_encode([
                'success' => true,
                'pages' => $related,
                'for_page' => $pageId
            ]);
            break;
            
        case 'category':
            // Get pages by category
            $category = $_GET['category'] ?? '';
            
            if (empty($category)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'category required']);
                exit;
            }
            
            $limit = intval($_GET['limit'] ?? 10);
            $pages = $engine->getPopularByCategory($category, $limit);
            echo json_encode([
                'success' => true,
                'pages' => $pages,
                'category' => $category
            ]);
            break;
            
        case 'smart':
            // Get smart suggestions
            $query = $_GET['q'] ?? '';
            
            if (strlen($query) < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Query too short']);
                exit;
            }
            
            $limit = intval($_GET['limit'] ?? 10);
            $suggestions = $engine->getSmartSuggestions($query, $limit);
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions,
                'query' => $query
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Recommendations error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}