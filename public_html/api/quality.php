<?php
/**
 * Intebwio - Quality Score API
 * Provides page quality assessment endpoints
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/QualityScoringSystem.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $scoringSystem = new QualityScoringSystem($pdo);
    
    switch ($action) {
        case 'score':
            // Calculate quality score for a page
            $pageId = intval($_GET['page_id'] ?? 0);
            
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                exit;
            }
            
            $result = $scoringSystem->calculateQualityScore($pageId);
            echo json_encode($result);
            break;
            
        case 'report':
            // Get quality report
            $pageId = intval($_GET['page_id'] ?? 0);
            
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                exit;
            }
            
            $result = $scoringSystem->getQualityReport($pageId);
            echo json_encode($result);
            break;
            
        case 'bulk_score':
            // Score multiple pages
            $pageIds = isset($_GET['page_ids']) ? explode(',', $_GET['page_ids']) : [];
            $results = [];
            
            foreach ($pageIds as $pageId) {
                $pageId = intval($pageId);
                if ($pageId > 0) {
                    $result = $scoringSystem->calculateQualityScore($pageId);
                    if ($result['success']) {
                        $results[] = $result;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'scores' => $results,
                'count' => count($results)
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Quality score API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}