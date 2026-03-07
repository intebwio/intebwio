<?php
/**
 * Intebwio - Comments API
 * Handle page discussions and comments
 * ~150 lines
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : 'get';
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    
    switch ($action) {
        case 'get':
            // Get comments for a query
            $stmt = $pdo->prepare("
                SELECT id, page_id, author, SUBSTRING(comment_text, 1, 200) as text, 
                       likes, created_at, approved
                FROM page_comments 
                WHERE search_query = ? AND approved = TRUE
                ORDER BY likes DESC, created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$query]);
            $comments = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'comments' => $comments,
                'total' => count($comments)
            ]);
            break;
            
        case 'post':
            // Add new comment
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['query']) || !isset($data['comment']) || !isset($data['author'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }
            
            $query = trim($data['query']);
            $comment = trim($data['comment']);
            $author = trim($data['author']) ?? 'Anonymous';
            
            if (strlen($comment) < 5 || strlen($comment) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Comment must be between 5-2000 characters']);
                break;
            }
            
            // Get page ID
            $stmt = $pdo->prepare("SELECT id FROM pages WHERE LOWER(search_query) = LOWER(?)");
            $stmt->execute([$query]);
            $page = $stmt->fetch();
            
            if (!$page) {
                echo json_encode(['success' => false, 'message' => 'Page not found']);
                break;
            }
            
            // Insert comment (pending approval)
            $insertStmt = $pdo->prepare("
                INSERT INTO page_comments (page_id, search_query, author, comment_text, ip_address, approved)
                VALUES (?, ?, ?, ?, ?, FALSE)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $insertStmt->execute([
                $page['id'],
                $query,
                $author,
                $comment,
                $ipAddress
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Comment submitted for review'
            ]);
            break;
            
        case 'like':
            // Like a comment
            $commentId = intval($_POST['comment_id'] ?? 0);
            
            if ($commentId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
                break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE page_comments 
                SET likes = likes + 1 
                WHERE id = ?
            ");
            $stmt->execute([$commentId]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Comments API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}