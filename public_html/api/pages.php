<?php
/**
 * Page Management API
 * Create, read, update, and delete page operations
 * ~200 lines
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pageId = $_GET['id'] ?? $_POST['id'] ?? null;

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    switch ($action) {
        case 'get':
            getPage($pdo, $pageId);
            break;
        case 'list':
            listPages($pdo);
            break;
        case 'search':
            searchPages($pdo);
            break;
        case 'update':
            updatePage($pdo, $pageId);
            break;
        case 'delete':
            deletePage($pdo, $pageId);
            break;
        case 'archive':
            archivePage($pdo, $pageId);
            break;
        case 'publish':
            publishPage($pdo, $pageId);
            break;
        case 'duplicate':
            duplicatePage($pdo, $pageId);
            break;
        case 'stats':
            getPageStats($pdo, $pageId);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getPage($pdo, $pageId) {
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Page ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM pages WHERE id = ?
    ");
    $stmt->execute([$pageId]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        http_response_code(404);
        echo json_encode(['error' => 'Page not found']);
        return;
    }
    
    // Get related stats
    $statsStmt = $pdo->prepare("
        SELECT COUNT(*) as comment_count FROM page_comments WHERE page_id = ? AND is_approved = 1
    ");
    $statsStmt->execute([$pageId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    $page['comment_count'] = (int)$stats['comment_count'];
    $page['source_urls'] = json_decode($page['source_urls'], true);
    $page['categories'] = json_decode($page['categories'], true);
    $page['tags'] = json_decode($page['tags'], true);
    
    echo json_encode(['page' => $page]);
}

function listPages($pdo) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? 'active';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'DESC';
    
    // Validate sort column
    $validColumns = ['id', 'search_query', 'view_count', 'created_at', 'updated_at', 'seo_score'];
    if (!in_array($sort, $validColumns)) $sort = 'created_at';
    if (!in_array(strtoupper($order), ['ASC', 'DESC'])) $order = 'DESC';
    
    $whereClause = '';
    if ($status) {
        $whereClause = "WHERE status = '" . addslashes($status) . "'";
    }
    
    // Count total
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM pages $whereClause");
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get pages
    $stmt = $pdo->query("
        SELECT id, search_query as title, description, view_count, 
               unique_visitors, created_at, updated_at, status, seo_score
        FROM pages
        $whereClause
        ORDER BY $sort $order
        LIMIT $limit OFFSET $offset
    ");
    
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ],
        'pages' => $pages
    ]);
}

function searchPages($pdo) {
    $query = $_GET['q'] ?? $_POST['q'] ?? '';
    
    if (empty($query)) {
        http_response_code(400);
        echo json_encode(['error' => 'Search query required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, search_query as title, description, view_count, created_at
        FROM pages
        WHERE status = 'active'
        AND (MATCH(search_query, title, description) AGAINST(? IN BOOLEAN MODE)
             OR search_query LIKE ?)
        LIMIT 20
    ");
    
    $likeQuery = '%' . $query . '%';
    $stmt->execute([$query, $likeQuery]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'query' => $query,
        'results_count' => count($results),
        'results' => $results
    ]);
}

function updatePage($pdo, $pageId) {
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Page ID required']);
        return;
    }
    
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $tags = $_POST['tags'] ?? '[]';
    $categories = $_POST['categories'] ?? '[]';
    
    // Validate
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$pageId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Page not found']);
        return;
    }
    
    // Update
    $updateStmt = $pdo->prepare("
        UPDATE pages SET
            title = ?,
            description = ?,
            html_content = ?,
            tags = ?,
            categories = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $updateStmt->execute([
        $title,
        $description,
        $content,
        $tags,
        $categories,
        $pageId
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Page updated']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
    }
}

function deletePage($pdo, $pageId) {
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Page ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
    $result = $stmt->execute([$pageId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Page deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Deletion failed']);
    }
}

function archivePage($pdo, $pageId) {
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Page ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE pages SET status = 'archived', updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$pageId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Page archived']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Archive failed']);
    }
}

function publishPage($pdo, $pageId) {
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Page ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE pages SET status = 'active', updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$pageId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Page published']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Publish failed']);
    }
}

function duplicatePage($pdo, $pageId) {
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Page ID required']);
        return;
    }
    
    // Get original page
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$pageId]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        http_response_code(404);
        echo json_encode(['error' => 'Page not found']);
        return;
    }
    
    // Create duplicate
    $pageIDStmt = $pdo->prepare("
        INSERT INTO pages
        (search_query, title, description, html_content, json_content, ai_provider,
         ai_model, source_urls, categories, tags, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    
    $newQuery = $page['search_query'] . ' (copy)';
    $pageIDStmt->execute([
        $newQuery,
        $page['title'],
        $page['description'],
        $page['html_content'],
        $page['json_content'],
        $page['ai_provider'],
        $page['ai_model'],
        $page['source_urls'],
        $page['categories'],
        $page['tags']
    ]);
    
    $newPageId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Page duplicated',
        'original_id' => $pageId,
        'new_id' => (int)$newPageId
    ]);
}

function getPageStats($pdo, $pageId) {
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Page ID required']);
        return;
    }
    
    // Basic page info
    $pageStmt = $pdo->prepare("
        SELECT id, search_query, view_count, unique_visitors, avg_read_time, seo_score
        FROM pages WHERE id = ?
    ");
    $pageStmt->execute([$pageId]);
    $pageInfo = $pageStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pageInfo) {
        http_response_code(404);
        echo json_encode(['error' => 'Page not found']);
        return;
    }
    
    // Activity stats
    $activityStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_interactions,
            COUNT(DISTINCT ip_address) as unique_visitors,
            AVG(duration_seconds) as avg_session_duration,
            AVG(scroll_depth) as avg_scroll_depth,
            COUNT(DISTINCT DATE(created_at)) as days_active
        FROM user_activity
        WHERE page_id = ?
    ");
    $activityStmt->execute([$pageId]);
    $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);
    
    // Comments
    $commentsStmt = $pdo->prepare("
        SELECT COUNT(*) as total, 
               SUM(likes) as total_likes,
               COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved
        FROM page_comments WHERE page_id = ?
    ");
    $commentsStmt->execute([$pageId]);
    $comments = $commentsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'page_info' => $pageInfo,
        'activity_stats' => $activity,
        'comment_stats' => $comments
    ]);
}