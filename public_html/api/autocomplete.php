<?php
/**
 * Intebwio - Autocomplete API
 * Provides search suggestions
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

try {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (strlen($query) < 2) {
        echo json_encode(['suggestions' => []]);
        exit;
    }
    
    $db = new Database($pdo);
    
    // Search for similar pages
    $stmt = $pdo->prepare("
        SELECT DISTINCT search_query FROM pages 
        WHERE status = 'active' AND search_query LIKE ?
        ORDER BY view_count DESC
        LIMIT 10
    ");
    
    $stmt->execute(['%' . $query . '%']);
    $results = $stmt->fetchAll();
    
    $suggestions = [];
    foreach ($results as $result) {
        $suggestions[] = $result['search_query'];
    }
    
    // Add some trending suggestions if available
    if (empty($suggestions)) {
        $trending = $pdo->query("
            SELECT DISTINCT search_query FROM pages 
            WHERE status = 'active'
            ORDER BY view_count DESC, updated_at DESC
            LIMIT 5
        ")->fetchAll();
        
        foreach ($trending as $trend) {
            $suggestions[] = $trend['search_query'];
        }
    }
    
    echo json_encode([
        'suggestions' => $suggestions,
        'query' => $query
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Autocomplete error: ' . $e->getMessage(),
        'suggestions' => []
    ]);
}