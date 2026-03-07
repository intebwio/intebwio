<?php
/**
 * Intebwio - Analytics API
 * Returns detailed analytics about pages and user behavior
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : 'overview';
    
    $db = new Database($pdo);
    
    switch ($action) {
        case 'overview':
            // Get overall statistics
            $stats = $db->getStatistics();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'top_pages':
            // Get most viewed pages
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $stmt = $pdo->prepare("
                SELECT id, search_query as title, view_count, created_at, last_scan_date
                FROM pages 
                WHERE status = 'active'
                ORDER BY view_count DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $pages = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $pages]);
            break;
            
        case 'recent_pages':
            // Get recently created pages
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $stmt = $pdo->prepare("
                SELECT id, search_query as title, view_count, created_at
                FROM pages 
                WHERE status = 'active'
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $pages = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $pages]);
            break;
            
        case 'activity':
            // Get user activity
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            $stmt = $pdo->prepare("
                SELECT page_id, search_query, action_type, created_at
                FROM user_activity 
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $activity = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $activity]);
            break;
            
        case 'page_details':
            // Get details about a specific page
            $pageId = isset($_GET['page_id']) ? intval($_GET['page_id']) : 0;
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                break;
            }
            
            $page = $db->getPageById($pageId);
            if (!$page) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Page not found']);
                break;
            }
            
            echo json_encode(['success' => true, 'data' => $page]);
            break;
            
        case 'trending':
            // Get trending topics (most searched in last 7 days)
            $stmt = $pdo->query("
                SELECT search_query, COUNT(*) as search_count, MAX(created_at) as last_searched
                FROM user_activity 
                WHERE action_type = 'search' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY search_query
                ORDER BY search_count DESC
                LIMIT 20
            ");
            $trending = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $trending]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}