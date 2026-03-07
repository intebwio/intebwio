<?php
/**
 * Intebwio - Page Loader
 * Loads page content from database by slug/page parameter
 * Handles: ?page=pagename, ?slug=pagename, ?id=pageid
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';

try {
    // Extract parameters
    $pageParam = isset($_GET['page']) ? trim($_GET['page']) : null;
    $slugParam = isset($_GET['slug']) ? trim($_GET['slug']) : null;
    $idParam = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$pageParam && !$slugParam && !$idParam) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing page, slug, or id parameter'
        ]);
        exit;
    }
    
    $db = new Database($pdo);
    $page = null;
    
    // Try to fetch page by different methods (in order of preference)
    if ($idParam > 0) {
        // Load by ID
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$idParam]);
        $page = $stmt->fetch();
    }
    
    if (!$page && $slugParam) {
        // Load by slug
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$slugParam]);
        $page = $stmt->fetch();
    }
    
    if (!$page && $pageParam) {
        // Try loading by ?page=pagename (convert to slug format)
        // First try exact match as slug
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$pageParam]);
        $page = $stmt->fetch();
        
        // If not found, try normalizing the page parameter to slug format
        if (!$page) {
            $normalizedSlug = strtolower(preg_replace('/[^a-z0-9-]/', '-', preg_replace('/\s+/', '-', $pageParam)));
            $normalizedSlug = preg_replace('/-+/', '-', trim($normalizedSlug, '-'));
            
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$normalizedSlug]);
            $page = $stmt->fetch();
        }
        
        // Last resort: search by query field (case-insensitive)
        if (!$page) {
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE LOWER(TRIM(query)) = LOWER(TRIM(?)) AND status = 'active' LIMIT 1");
            $stmt->execute([$pageParam]);
            $page = $stmt->fetch();
        }
    }
    
    if (!$page) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Page not found',
            'searched_for' => [
                'id' => $idParam,
                'slug' => $slugParam,
                'page' => $pageParam
            ]
        ]);
        exit;
    }
    
    // Record view/activity if this is a real view (not AJAX prefetch)
    if (isset($_GET['record_view']) && $_GET['record_view'] == 'true') {
        try {
            $db->recordActivity($page['id'], null, 'view');
            $db->updateViewCount($page['id']);
        } catch (Exception $e) {
            error_log("Error recording activity: " . $e->getMessage());
        }
    }
    
    // Return page data
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'page' => [
            'id' => (int)$page['id'],
            'query' => $page['query'],
            'slug' => $page['slug'],
            'title' => $page['title'],
            'description' => $page['description'],
            'html_content' => $page['html_content'],
            'view_count' => (int)$page['view_count'],
            'created_at' => $page['created_at'],
            'updated_at' => $page['updated_at'],
            'status' => $page['status']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    error_log("Page loading error: " . $e->getMessage());
}