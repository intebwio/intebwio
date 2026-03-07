<?php
/**
 * Intebwio - Cache Manager
 * Handles page caching and performance optimization
 * ~200 lines
 */

class CacheManager {
    private $pdo;
    const CACHE_LIFETIME = 604800; // 7 days
    const MAX_CACHE_SIZE = 5000; // Maximum pages to cache
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get cached page
     */
    public function getPageFromCache($query) {
        try {
            $cacheKey = 'intebwio_' . md5(strtolower($query));
            
            // Try APCu first (fastest)
            if (function_exists('apcu_fetch')) {
                $cached = apcu_fetch($cacheKey);
                if ($cached !== false) {
                    return $cached;
                }
            }
            
            // Check database
            $stmt = $this->pdo->prepare("
                SELECT id, html_content, view_count, created_at
                FROM pages 
                WHERE LOWER(search_query) = LOWER(?)
                AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$query]);
            $page = $stmt->fetch();
            
            if ($page) {
                // Store in APCu
                if (function_exists('apcu_store')) {
                    apcu_store($cacheKey, $page, self::CACHE_LIFETIME);
                }
                return $page;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Cache retrieval error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Store page in cache
     */
    public function cachePageData($query, $pageData) {
        try {
            $cacheKey = 'intebwio_' . md5(strtolower($query));
            
            // Store in APCu
            if (function_exists('apcu_store')) {
                apcu_store($cacheKey, $pageData, self::CACHE_LIFETIME);
            }
            
            // Log cache hit
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_stats (page_id, cache_hit)
                VALUES (?, TRUE)
            ");
            $stmt->execute([$pageData['id']]);
            
            return true;
        } catch (Exception $e) {
            error_log("Cache storage error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear cache for a page
     */
    public function clearPageCache($query) {
        try {
            $cacheKey = 'intebwio_' . md5(strtolower($query));
            
            if (function_exists('apcu_delete')) {
                apcu_delete($cacheKey);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Cache clear error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all cache
     */
    public function clearAllCache() {
        try {
            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Cache clear all error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN cache_hit = TRUE THEN 1 ELSE 0 END) as hits,
                    AVG(load_time_ms) as avg_load_time
                FROM cache_stats 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Cache stats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Warm cache - pre-load popular pages
     */
    public function warmCache() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, search_query, html_content, view_count
                FROM pages 
                WHERE status = 'active'
                ORDER BY view_count DESC
                LIMIT 100
            ");
            $stmt->execute();
            $pages = $stmt->fetchAll();
            
            $warmed = 0;
            foreach ($pages as $page) {
                $cacheKey = 'intebwio_' . md5(strtolower($page['search_query']));
                if (function_exists('apcu_store')) {
                    apcu_store($cacheKey, $page, self::CACHE_LIFETIME);
                    $warmed++;
                }
            }
            
            return ['success' => true, 'warmed' => $warmed];
        } catch (Exception $e) {
            error_log("Cache warm error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Monitor cache usage
     */
    public function monitorCacheUsage() {
        try {
            if (!function_exists('apcu_cache_info')) {
                return null;
            }
            
            $info = apcu_cache_info();
            return [
                'memory_used' => $info['mem_size'] ?? 0,
                'memory_available' => ini_get('apc.shm_size'),
                'number_of_slots' => $info['num_slots'] ?? 0,
                'number_of_entries' => $info['num_entries'] ?? 0
            ];
        } catch (Exception $e) {
            error_log("Cache monitor error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Suggest cache optimization
     */
    public function suggestOptimization() {
        $usage = $this->monitorCacheUsage();
        
        if (!$usage) {
            return ['message' => 'Cache monitoring unavailable'];
        }
        
        $totalPages = $this->pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'active'")
            ->fetchColumn();
        
        $suggestions = [];
        
        if ($usage['number_of_entries'] > self::MAX_CACHE_SIZE * 0.9) {
            $suggestions[] = 'Cache is getting full. Consider increasing APCu size.';
        }
        
        if ($totalPages > 1000) {
            $suggestions[] = 'Large number of pages. Consider enabling compression.';
        }
        
        return $suggestions;
    }
}