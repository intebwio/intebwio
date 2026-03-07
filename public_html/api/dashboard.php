<?php
/**
 * Intebwio - Dashboard API
 * Analytics and statistics for admin panel
 * ~150 lines
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/CacheManager.php';

try {
    $action = isset($_GET['action']) ? $_GET['action'] : 'overview';
    
    $db = new Database($pdo);
    $cacheManager = new CacheManager($pdo);
    
    switch ($action) {
        case 'overview':
            // Dashboard overview
            $stats = $db->getStatistics();
            $cacheStats = $cacheManager->getCacheStats();
            $usage = $cacheManager->monitorCacheUsage();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'pages' => $stats,
                    'cache' => $cacheStats,
                    'performance' => $usage
                ]
            ]);
            break;
            
        case 'pages':
            // List all pages
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
            
            $stmt = $pdo->prepare("
                SELECT id, search_query as title, view_count, created_at, last_scan_date, status
                FROM pages 
                ORDER BY view_count DESC, created_at DESC
                LIMIT ?, ?
            ");
            $stmt->execute([$offset, $limit]);
            $pages = $stmt->fetchAll();
            
            // Get total count
            $total = $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'pages' => $pages,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        case 'search_trends':
            // Get trending searches
            $days = intval($_GET['days'] ?? 7);
            
            $stmt = $pdo->prepare("
                SELECT search_query, COUNT(*) as count
                FROM user_activity
                WHERE action_type = 'search' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY search_query
                ORDER BY count DESC
                LIMIT 20
            ");
            $stmt->execute([$days]);
            $trends = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'trends' => $trends,
                'period' => $days . ' days'
            ]);
            break;
            
        case 'page_performance':
            // Get performance metrics
            $pageId = intval($_GET['page_id'] ?? 0);
            
            if ($pageId <= 0) {
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, page_id, load_time_ms, cache_hit, bandwidth_bytes, access_count
                FROM cache_stats
                WHERE page_id = ?
                ORDER BY created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$pageId]);
            $metrics = $stmt->fetchAll();
            
            if (empty($metrics)) {
                echo json_encode(['success' => true, 'metrics' => []]);
                break;
            }
            
            // Calculate statistics
            $avgLoadTime = array_sum(array_column($metrics, 'load_time_ms')) / count($metrics);
            $cacheHitRate = (count(array_filter($metrics, fn($m) => $m['cache_hit'])) / count($metrics)) * 100;
            
            echo json_encode([
                'success' => true,
                'metrics' => $metrics,
                'statistics' => [
                    'avg_load_time' => $avgLoadTime,
                    'cache_hit_rate' => $cacheHitRate,
                    'total_bandwidth' => array_sum(array_column($metrics, 'bandwidth_bytes')),
                    'total_views' => array_sum(array_column($metrics, 'access_count'))
                ]
            ]);
            break;
            
        case 'system_health':
            // System health check
            $health = [
                'database' => 'healthy',
                'cache' => 'healthy',
                'api' => 'healthy',
                'issues' => []
            ];
            
            // Check database
            try {
                $result = $pdo->query("SELECT 1");
                if (!$result) {
                    $health['database'] = 'error';
                    $health['issues'][] = 'Database connection failed';
                }
            } catch (Exception $e) {
                $health['database'] = 'error';
                $health['issues'][] = 'Database error: ' . $e->getMessage();
            }
            
            // Check cache
            $cacheUsage = $cacheManager->monitorCacheUsage();
            if (!$cacheUsage) {
                $health['cache'] = 'warning';
                $health['issues'][] = 'Cache unavailable';
            }
            
            echo json_encode([
                'success' => true,
                'health' => $health,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}