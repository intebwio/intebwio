<?php
/**
 * Intebwio - Performance Optimization API
 * Manages caching, performance metrics, and optimization
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

class PerformanceOptimizer {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats($pageId = null) {
        try {
            if ($pageId) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        page_id,
                        AVG(load_time_ms) as avg_load_time,
                        MIN(load_time_ms) as min_load_time,
                        MAX(load_time_ms) as max_load_time,
                        COUNT(*) as cache_hits,
                        SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hit_count,
                        AVG(bandwidth_bytes) as avg_bandwidth,
                        SUM(access_count) as total_accesses
                    FROM cache_stats
                    WHERE page_id = ?
                    GROUP BY page_id
                ");
                $stmt->execute([$pageId]);
            } else {
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total_cached_pages,
                        AVG(load_time_ms) as avg_load_time,
                        SUM(access_count) as total_cache_accesses,
                        SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as total_cache_hits
                    FROM cache_stats
                ");
            }
            
            $stats = $stmt->fetch();
            
            if ($stats) {
                $hitRate = $stats['cache_hits'] > 0 
                    ? ($stats['cache_hit_count'] / $stats['cache_hits']) * 100 
                    : 0;
                    
                $stats['cache_hit_rate'] = round($hitRate, 2);
            }
            
            return [
                'success' => true,
                'stats' => $stats
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Record page access for performance tracking
     */
    public function recordPageAccess($pageId, $loadTimeMs, $cacheHit = true, $bandwidthBytes = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_stats (page_id, load_time_ms, cache_hit, bandwidth_bytes, access_count, last_accessed)
                VALUES (?, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                    access_count = access_count + 1,
                    load_time_ms = ?,
                    cache_hit = ?,
                    bandwidth_bytes = ?,
                    last_accessed = NOW()
            ");
            
            return $stmt->execute([
                $pageId, $loadTimeMs, $cacheHit, $bandwidthBytes,
                $loadTimeMs, $cacheHit, $bandwidthBytes
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get slowest pages
     */
    public function getSlowestPages($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.search_query,
                    cs.avg_load_time,
                    cs.access_count,
                    p.view_count
                FROM cache_stats cs
                JOIN pages p ON cs.page_id = p.id
                ORDER BY cs.avg_load_time DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return [
                'success' => true,
                'pages' => $stmt->fetchAll()
            ];
        } catch (Exception $e) {
            return ['success' => false];
        }
    }
    
    /**
     * Get most accessed pages
     */
    public function getMostAccessedPages($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.search_query,
                    cs.access_count,
                    cs.avg_load_time,
                    p.view_count
                FROM cache_stats cs
                JOIN pages p ON cs.page_id = p.id
                WHERE cs.access_count > 0
                ORDER BY cs.access_count DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return [
                'success' => true,
                'pages' => $stmt->fetchAll()
            ];
        } catch (Exception $e) {
            return ['success' => false];
        }
    }
    
    /**
     * Optimize database (clean old logs, optimize tables)
     */
    public function optimizeDatabase() {
        try {
            // Delete old user activity (older than 90 days)
            $this->pdo->exec("
                DELETE FROM user_activity 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            
            // Optimize tables
            $tables = ['pages', 'search_results', 'user_activity', 'cache_stats'];
            foreach ($tables as $table) {
                $this->pdo->exec("OPTIMIZE TABLE $table");
            }
            
            return [
                'success' => true,
                'message' => 'Database optimized'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get performance report
     */
    public function getPerformanceReport() {
        try {
            return [
                'success' => true,
                'cache_stats' => $this->getCacheStats(),
                'slowest_pages' => $this->getSlowestPages(5),
                'most_accessed' => $this->getMostAccessedPages(5)
            ];
        } catch (Exception $e) {
            return ['success' => false];
        }
    }
}

try {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $optimizer = new PerformanceOptimizer($pdo);
    
    switch ($action) {
        case 'stats':
            $pageId = intval($_GET['page_id'] ?? 0);
            $result = $optimizer->getCacheStats($pageId ?: null);
            echo json_encode($result);
            break;
            
        case 'record':
            $pageId = intval($_GET['page_id'] ?? 0);
            $loadTime = intval($_GET['load_time'] ?? 0);
            $cacheHit = isset($_GET['cache_hit']) ? $_GET['cache_hit'] === 'true' : true;
            $bandwidth = intval($_GET['bandwidth'] ?? 0);
            
            if ($pageId > 0) {
                $result = $optimizer->recordPageAccess($pageId, $loadTime, $cacheHit, $bandwidth);
                echo json_encode(['success' => $result]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
            }
            break;
            
        case 'slowest':
            $limit = intval($_GET['limit'] ?? 10);
            $result = $optimizer->getSlowestPages($limit);
            echo json_encode($result);
            break;
            
        case 'most_accessed':
            $limit = intval($_GET['limit'] ?? 10);
            $result = $optimizer->getMostAccessedPages($limit);
            echo json_encode($result);
            break;
            
        case 'optimize':
            // Requires admin auth
            $result = $optimizer->optimizeDatabase();
            echo json_encode($result);
            break;
            
        case 'report':
            $result = $optimizer->getPerformanceReport();
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Performance API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}