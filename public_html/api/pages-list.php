<?php
/**
 * Pages List and Stats API
 * Retrieves cached pages, statistics, and page management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require database for pages operations
requireDatabase();

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? $_POST['offset'] ?? 0);
    
    switch ($action) {
        case 'list':
            listPages($pdo, $limit, $offset);
            break;
        case 'stats':
            getStats($pdo);
            break;
        case 'search':
            searchPages($pdo, $limit, $offset);
            break;
        case 'recent':
            getRecentPages($pdo, $limit);
            break;
        case 'trending':
            getTrendingPages($pdo, $limit);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * List all pages with pagination
 */
function listPages($pdo, $limit, $offset) {
    try {
        // Get total count
        $countResult = $pdo->query("SELECT COUNT(*) as total FROM pages WHERE status = 'active'");
        $total = $countResult->fetch()['total'];
        
        // Get paginated results
        $stmt = $pdo->prepare("
            SELECT 
                id, query, slug, title, description, 
                view_count, created_at, updated_at
            FROM pages 
            WHERE status = 'active'
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $pages = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'pages' => $pages,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $total
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get application statistics
 */
function getStats($pdo) {
    try {
        // Total pages
        $result = $pdo->query("SELECT COUNT(*) as count FROM pages WHERE status = 'active'");
        $totalPages = $result->fetch()['count'];
        
        // Total views
        $result = $pdo->query("SELECT SUM(view_count) as total FROM pages");
        $totalViews = $result->fetch()['total'] ?? 0;
        
        // Pages generated today
        $result = $pdo->query("
            SELECT COUNT(*) as count FROM pages 
            WHERE status = 'active' 
            AND DATE(created_at) = CURDATE()
        ");
        $pagesGeneratedToday = $result->fetch()['count'];
        
        // Average views per page
        $avgViews = $totalPages > 0 ? $totalViews / $totalPages : 0;
        
        // Get database size
        $result = $pdo->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
            FROM information_schema.tables 
            WHERE table_schema = :db
        ");
        $result->bindValue(':db', DB_NAME, PDO::PARAM_STR);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'stats' => [
                'totalPages' => (int)$totalPages,
                'totalViews' => (int)$totalViews,
                'pagesGeneratedToday' => (int)$pagesGeneratedToday,
                'averageViewsPerPage' => round($avgViews, 2),
                'database' => DB_NAME,
                'host' => DB_HOST
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Search pages by query
 */
function searchPages($pdo, $limit, $offset) {
    try {
        $searchQuery = $_GET['q'] ?? $_POST['q'] ?? '';
        
        if (empty($searchQuery)) {
            throw new Exception('Search query parameter "q" is required');
        }
        
        $searchQuery = '%' . trim($searchQuery) . '%';
        
        // Get total count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM pages 
            WHERE (query LIKE :query OR title LIKE :query OR description LIKE :query)
            AND status = 'active'
        ");
        $stmt->execute(['query' => $searchQuery]);
        $total = $stmt->fetch()['total'];
        
        // Get results
        $stmt = $pdo->prepare("
            SELECT 
                id, query, slug, title, description, 
                view_count, created_at, updated_at
            FROM pages 
            WHERE (query LIKE :query OR title LIKE :query OR description LIKE :query)
            AND status = 'active'
            ORDER BY 
                CASE WHEN query LIKE :exact THEN 0 ELSE 1 END,
                view_count DESC,
                created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':query', $searchQuery, PDO::PARAM_STR);
        $stmt->bindValue(':exact', trim(str_replace('%', '', $searchQuery), '%'), PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $pages = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'pages' => $pages,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $total
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get recently created pages
 */
function getRecentPages($pdo, $limit) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, query, slug, title, description, 
                view_count, created_at, updated_at
            FROM pages 
            WHERE status = 'active'
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $pages = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'pages' => $pages,
            'count' => count($pages),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get trending pages by view count
 */
function getTrendingPages($pdo, $limit) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, query, slug, title, description, 
                view_count, created_at, updated_at
            FROM pages 
            WHERE status = 'active'
            ORDER BY view_count DESC 
            LIMIT :limit
        ");
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $pages = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'pages' => $pages,
            'count' => count($pages),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}