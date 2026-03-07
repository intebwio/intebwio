<?php
/**
 * Intebwio - Latest Pages API
 * Returns recently generated pages
 */

header('Content-Type: application/json');
set_time_limit(300);

require_once __DIR__ . '/../includes/config.php';

try {
    // Get limit from query parameter (default 12)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
    $limit = max(1, min($limit, 100)); // Validate: 1-100
    
    $stmt = $pdo->prepare("
        SELECT id, search_query as title, thumbnail_image, view_count, created_at 
        FROM pages 
        WHERE status = 'active'
        ORDER BY created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $pages = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'pages' => $pages,
        'total' => count($pages)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}