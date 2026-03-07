<?php
/**
 * Search Optimizer and Indexing Service
 * Optimize search functionality and maintain indexes
 * ~200 lines
 */

class SearchOptimizer {
    private $pdo;
    private $logger;
    
    public function __construct($pdo, $logger = null) {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }
    
    /**
     * Optimize search index
     */
    public function optimizeIndex() {
        $tables = ['pages', 'search_results', 'user_activity'];
        $results = [];
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("OPTIMIZE TABLE `$table`");
                $results[$table] = 'optimized';
                $this->log("Optimized table: $table");
            } catch (Exception $e) {
                $results[$table] = 'failed: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Analyze and suggest search improvements
     */
    public function analyzeSuggestions() {
        $suggestions = [];
        
        // Check for unindexed searches
        $stmt = $this->pdo->query("
            SELECT search_query, COUNT(*) as count
            FROM user_activity
            WHERE action_type = 'search'
            AND search_query NOT IN (SELECT search_query FROM pages)
            GROUP BY search_query
            ORDER BY count DESC
            LIMIT 20
        ");
        
        $unindexed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($unindexed)) {
            $suggestions[] = [
                'type' => 'unindexed_searches',
                'description' => 'Popular searches with no indexed page',
                'data' => $unindexed
            ];
        }
        
        // Check for low-performing pages
        $lowPerformanceStmt = $this->pdo->query("
            SELECT id, search_query, view_count, seo_score
            FROM pages
            WHERE view_count < 5
            AND created_at < NOW() - INTERVAL 30 DAY
            ORDER BY seo_score ASC
            LIMIT 20
        ");
        
        $lowPerforming = $lowPerformanceStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($lowPerforming)) {
            $suggestions[] = [
                'type' => 'low_performing_pages',
                'description' => 'Pages with low views and SEO score',
                'data' => $lowPerforming
            ];
        }
        
        // Check for search trends
        $trendsStmt = $this->pdo->prepare("
            SELECT search_query, COUNT(*) as frequency
            FROM user_activity
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND action_type = 'search'
            GROUP BY search_query
            ORDER BY frequency DESC
            LIMIT 15
        ");
        
        $trendsStmt->execute();
        $trends = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $suggestions[] = [
            'type' => 'trending_searches',
            'description' => 'Currently trending search topics',
            'data' => $trends
        ];
        
        return $suggestions;
    }
    
    /**
     * Generate search statistics
     */
    public function getSearchStatistics() {
        $stats = [];
        
        // Total searches
        $totalStmt = $this->pdo->query("SELECT COUNT(*) as count FROM user_activity WHERE action_type = 'search'");
        $stats['total_searches'] = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Searches per day
        $dailyStmt = $this->pdo->query("
            SELECT AVG(daily_count) as avg_daily FROM (
                SELECT COUNT(*) as daily_count
                FROM user_activity
                WHERE action_type = 'search'
                GROUP BY DATE(created_at)
            ) as daily_data
        ");
        $stats['avg_searches_per_day'] = round($dailyStmt->fetch(PDO::FETCH_ASSOC)['avg_daily'] ?? 0, 2);
        
        // Unique search terms
        $uniqueStmt = $this->pdo->query("
            SELECT COUNT(DISTINCT search_query) as count 
            FROM user_activity 
            WHERE action_type = 'search'
        ");
        $stats['unique_search_terms'] = $uniqueStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Average query length
        $lengthStmt = $this->pdo->query("
            SELECT AVG(CHAR_LENGTH(search_query)) as avg_length
            FROM user_activity
            WHERE action_type = 'search'
        ");
        $stats['avg_query_length'] = round($lengthStmt->fetch(PDO::FETCH_ASSOC)['avg_length'] ?? 0, 1);
        
        // Popular search terms
        $popullarStmt = $this->pdo->query("
            SELECT search_query, COUNT(*) as frequency
            FROM user_activity
            WHERE action_type = 'search'
            GROUP BY search_query
            ORDER BY frequency DESC
            LIMIT 20
        ");
        $stats['top_searches'] = $popullarStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * Rebuild full-text search index
     */
    public function rebuildFulltextIndex() {
        try {
            // Rebuild FULLTEXT index
            $this->pdo->exec("REPAIR TABLE pages");
            $this->log("Rebuilt full-text index");
            return true;
        } catch (Exception $e) {
            $this->log("Failed to rebuild index: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Suggest query improvements
     */
    public function suggestQueryImprovements($query) {
        $suggestions = [];
        $normalizedQuery = strtolower(trim($query));
        
        // Check for exact match
        $stmt = $this->pdo->prepare("
            SELECT id FROM pages 
            WHERE LOWER(search_query) = ?
            LIMIT 1
        ");
        $stmt->execute([$normalizedQuery]);
        if ($stmt->fetch()) {
            return ['exact_match_found' => true];
        }
        
        // Check for partial matches
        $partialStmt = $this->pdo->prepare("
            SELECT search_query, MATCH(title, description) AGAINST(? IN BOOLEAN MODE) as relevance
            FROM pages
            WHERE MATCH(title, description) AGAINST(? IN BOOLEAN MODE)
            ORDER BY relevance DESC
            LIMIT 5
        ");
        
        try {
            $partialStmt->execute([$query, $query]);
            $matches = $partialStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($matches)) {
                $suggestions['partial_matches'] = $matches;
            }
        } catch (Exception $e) {
            // Full-text search not available
        }
        
        // Suggest similar topics
        $keywords = array_values(array_filter(preg_split('/[\s\-_]+/', $normalizedQuery)));
        if (count($keywords) > 0) {
            $placeholders = implode(',', array_fill(0, count($keywords), '?'));
            $simularStmt = $this->pdo->prepare("
                SELECT DISTINCT search_query
                FROM pages
                WHERE " . implode(' OR ', array_map(function() { return 'search_query LIKE ?'; }, $keywords)) . "
                LIMIT 10
            ");
            
            $params = [];
            foreach ($keywords as $keyword) {
                $params[] = '%' . $keyword . '%';
            }
            
            $simularStmt->execute($params);
            $similar = $simularStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($similar)) {
                $suggestions['similar_topics'] = $similar;
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Calculate search metrics
     */
    public function calculateSearchMetrics($pageId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_views,
                COUNT(DISTINCT ip_address) as unique_viewers,
                AVG(duration_seconds) as avg_read_time,
                AVG(scroll_depth) as avg_scroll_depth,
                COUNT(CASE WHEN action_type = 'comment' THEN 1 END) as comment_count,
                COUNT(CASE WHEN duration_seconds > 60 THEN 1 END) as engaged_users
            FROM user_activity
            WHERE page_id = ?
        ");
        
        $stmt->execute([$pageId]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'page_id' => $pageId,
            'total_views' => (int)$metrics['total_views'],
            'unique_viewers' => (int)$metrics['unique_viewers'],
            'avg_read_time_seconds' => (int)($metrics['avg_read_time'] ?? 0),
            'avg_scroll_depth_percent' => (int)($metrics['avg_scroll_depth'] ?? 0),
            'comment_count' => (int)$metrics['comment_count'],
            'engaged_user_count' => (int)$metrics['engaged_users'],
            'engagement_rate' => $metrics['total_views'] > 0 ? 
                round(($metrics['engaged_users'] / $metrics['total_views']) * 100, 2) : 0
        ];
    }
    
    /**
     * Log message
     */
    private function log($message) {
        if ($this->logger) {
            $this->logger->log($message);
        }
        error_log('[SearchOptimizer] ' . $message);
    }
}