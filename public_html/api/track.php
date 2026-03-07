<?php
/**
 * Intebwio - Tracking API
 * Records user interactions and analytics
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action is required']);
        exit;
    }
    
    $db = new Database($pdo);
    
    switch ($data['action']) {
        case 'card_click':
            // Track card click
            $pageId = $data['page_id'] ?? null;
            $db->recordActivity($pageId, null, 'click');
            echo json_encode(['success' => true]);
            break;
            
        case 'page_view':
            $pageId = $data['page_id'] ?? null;
            if ($pageId) {
                $db->updateViewCount($pageId);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'get_stats':
            $stats = $db->getStatistics();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Tracking error'
    ]);
}