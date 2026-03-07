<?php
/**
 * Intebwio - User Management API
 * Handles user preferences, favorites, and session management
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SessionManager.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $sessionManager = new SessionManager($pdo);
    
    switch ($action) {
        case 'favorite':
            // Add/remove favorite
            $pageId = intval($_GET['page_id'] ?? 0);
            $method = $_SERVER['REQUEST_METHOD'];
            
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                exit;
            }
            
            if ($method === 'POST') {
                $result = $sessionManager->addFavorite($pageId);
                echo json_encode(['success' => $result]);
            } else {
                $result = $sessionManager->removeFavorite($pageId);
                echo json_encode(['success' => $result]);
            }
            break;
            
        case 'favorites':
            // Get all favorites
            $favorites = $sessionManager->getFavorites();
            echo json_encode([
                'success' => true,
                'favorites' => $favorites,
                'count' => count($favorites)
            ]);
            break;
            
        case 'history':
            // Get user history
            $limit = intval($_GET['limit'] ?? 50);
            $history = $sessionManager->getHistory($limit);
            echo json_encode([
                'success' => true,
                'history' => $history,
                'count' => count($history)
            ]);
            break;
            
        case 'preference':
            // Get/set preference
            $key = $_GET['key'] ?? null;
            $value = $_GET['value'] ?? null;
            
            if (!$key) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'key required']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Set preference
                $result = $sessionManager->setPreference($key, $value);
                echo json_encode(['success' => $result]);
            } else {
                // Get preference
                $value = $sessionManager->getPreference($key);
                echo json_encode([
                    'success' => true,
                    'key' => $key,
                    'value' => $value
                ]);
            }
            break;
            
        case 'preferences':
            // Get all preferences
            $preferences = $sessionManager->getAllPreferences();
            echo json_encode([
                'success' => true,
                'preferences' => $preferences
            ]);
            break;
            
        case 'session_info':
            // Get session information
            $info = $sessionManager->getSessionInfo();
            echo json_encode([
                'success' => true,
                'session' => $info
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("User API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}