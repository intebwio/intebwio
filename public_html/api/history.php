<?php
/**
 * Intebwio - Search History API
 * Returns user's search history and recent searches
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : 'history';
    
    switch ($action) {
        case 'history':
            // Get pages from localStorage history
            $history = isset($_GET['history']) ? json_decode($_GET['history'], true) : [];
            echo json_encode(['success' => true, 'history' => $history]);
            break;
            
        case 'save_history':
            // Save search to database for analytics
            $data = json_decode(file_get_contents('php://input'), true);
            $searchQuery = $data['query'] ?? null;
            
            if ($searchQuery) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_activity (search_query, action_type, ip_address, user_agent)
                    VALUES (?, 'search', ?, ?)
                ");
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
                $stmt->execute([$searchQuery, $ipAddress, $userAgent]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Query required']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("History error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}