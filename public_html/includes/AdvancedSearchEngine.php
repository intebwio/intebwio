<?php
/**
 * Intebwio - Advanced Search Engine
 * Sophisticated search with filters, faceting, and ranking
 */

class AdvancedSearchEngine {
    private $pdo;
    private $db;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once __DIR__ . '/Database.php';
        $this->db = new Database($pdo);
    }
    
    /**
     * Multi-faceted search with filtering
     */
    public function search($query, $filters = []) {
        try {
            // Sanitize and prepare query
            $cleanQuery = $this->sanitizeQuery($query);
            
            // Build search query with filters
            $baseQuery = "
                SELECT 
                    p.id,
                    p.search_query,
                    p.title,
                    p.description,
                    p.created_at,
                    p.updated_at,
                    p.view_count,
                    p.relevance_score,
                    p.thumbnail_image,
                    COUNT(sr.id) as source_count,
                    AVG(sr.relevance_score) as avg_relevance,
                    SUM(CASE WHEN sr.source_name = 'Wikipedia' THEN 1 ELSE 0 END) as wiki_sources,
                    SUM(CASE WHEN sr.source_name = 'GitHub' THEN 1 ELSE 0 END) as github_sources,
                    SUM(CASE WHEN sr.source_name = 'News' THEN 1 ELSE 0 END) as news_sources
                FROM pages p
                LEFT JOIN search_results sr ON p.id = sr.page_id
                WHERE p.status = 'active' AND (
                    MATCH(p.search_query, p.title) AGAINST(? IN BOOLEAN MODE) OR
                    MATCH(p.title, p.description) AGAINST(? IN BOOLEAN MODE) OR
                    p.search_query LIKE ?
                )
            ";
            
            $params = [$cleanQuery, $cleanQuery, '%' . $cleanQuery . '%'];
            
            // Apply date filter
            if (!empty($filters['date_from'])) {
                $baseQuery .= " AND p.created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $baseQuery .= " AND p.created_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Apply view count filter
            if (!empty($filters['min_views'])) {
                $baseQuery .= " AND p.view_count >= ?";
                $params[] = intval($filters['min_views']);
            }
            
            // Apply relevance filter
            if (!empty($filters['min_relevance'])) {
                $baseQuery .= " AND p.relevance_score >= ?";
                $params[] = floatval($filters['min_relevance']);
            }
            
            // Apply source filter
            if (!empty($filters['sources'])) {
                $sourceList = implode(',', array_map(function($s) { return "'" . $s . "'"; }, (array)$filters['sources']));
                $baseQuery .= " AND EXISTS (
                    SELECT 1 FROM search_results sr2 
                    WHERE sr2.page_id = p.id AND sr2.source_name IN ($sourceList)
                )";
            }
            
            // Grouping
            $baseQuery .= " GROUP BY p.id";
            
            // Apply sorting
            $sortBy = $filters['sort_by'] ?? 'relevance';
            switch ($sortBy) {
                case 'newest':
                    $baseQuery .= " ORDER BY p.created_at DESC";
                    break;
                case 'oldest':
                    $baseQuery .= " ORDER BY p.created_at ASC";
                    break;
                case 'most_viewed':
                    $baseQuery .= " ORDER BY p.view_count DESC";
                    break;
                case 'recently_updated':
                    $baseQuery .= " ORDER BY p.updated_at DESC";
                    break;
                case 'relevance':
                default:
                    $baseQuery .= " ORDER BY avg_relevance DESC, p.view_count DESC";
            }
            
            // Pagination
            $limit = intval($filters['limit'] ?? 20);
            $offset = intval($filters['offset'] ?? 0);
            $baseQuery .= " LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($baseQuery);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "
                SELECT COUNT(DISTINCT p.id) as total
                FROM pages p
                WHERE p.status = 'active' AND (
                    MATCH(p.search_query, p.title) AGAINST(? IN BOOLEAN MODE) OR
                    MATCH(p.title, p.description) AGAINST(? IN BOOLEAN MODE) OR
                    p.search_query LIKE ?
                )
            ";
            
            if (!empty($filters['date_from'])) {
                $countQuery .= " AND p.created_at >= ?";
            }
            if (!empty($filters['date_to'])) {
                $countQuery .= " AND p.created_at <= ?";
            }
            if (!empty($filters['min_views'])) {
                $countQuery .= " AND p.view_count >= ?";
            }
            if (!empty($filters['min_relevance'])) {
                $countQuery .= " AND p.relevance_score >= ?";
            }
            
            $countStmt = $this->pdo->prepare($countQuery);
            $countStmt->execute(array_slice($params, 0, 3));
            $total = $countStmt->fetch()['total'];
            
            return [
                'success' => true,
                'results' => $results,
                'total' => $total,
                'query' => $query,
                'took' => $this->getQueryTime()
            ];
            
        } catch (Exception $e) {
            error_log("Search error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'results' => []
            ];
        }
    }
    
    /**
     * Get search suggestions with auto-complete
     */
    public function getSuggestions($query, $limit = 10) {
        try {
            $cleanQuery = $this->sanitizeQuery($query);
            
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
                    p.search_query,
                    p.view_count,
                    COUNT(sr.id) as result_count
                FROM pages p
                LEFT JOIN search_results sr ON p.id = sr.page_id
                WHERE p.status = 'active' AND p.search_query LIKE ?
                GROUP BY p.search_query
                ORDER BY p.view_count DESC, p.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute(['%' . $cleanQuery . '%', $limit]);
            $suggestions = $stmt->fetchAll();
            
            return [
                'success' => true,
                'suggestions' => $suggestions,
                'query' => $query
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'suggestions' => []
            ];
        }
    }
    
    /**
     * Get related/similar searches
     */
    public function getRelatedSearches($pageId, $limit = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    sp.similar_page_id as id,
                    p.search_query,
                    p.title,
                    p.view_count,
                    sp.similarity_score
                FROM similar_pages sp
                JOIN pages p ON sp.similar_page_id = p.id
                WHERE sp.page_id = ? AND p.status = 'active'
                ORDER BY sp.similarity_score DESC, p.view_count DESC
                LIMIT ?
            ");
            
            $stmt->execute([$pageId, $limit]);
            return [
                'success' => true,
                'related' => $stmt->fetchAll()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'related' => []];
        }
    }
    
    /**
     * Get search analytics
     */
    public function getSearchAnalytics($query = null) {
        try {
            if ($query) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        COUNT(*) as total_searches,
                        COUNT(DISTINCT ip_address) as unique_users,
                        MIN(created_at) as first_search,
                        MAX(created_at) as last_search,
                        DATEDIFF(NOW(), MAX(created_at)) as days_since_search
                    FROM user_activity
                    WHERE search_query = ? AND action_type = 'search'
                ");
                $stmt->execute([$query]);
            } else {
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total_searches,
                        COUNT(DISTINCT ip_address) as unique_users,
                        COUNT(DISTINCT search_query) as unique_queries
                    FROM user_activity
                    WHERE action_type = 'search'
                ");
            }
            
            $analytics = $query ? $stmt->fetch() : $stmt->fetch();
            return [
                'success' => true,
                'analytics' => $analytics
            ];
        } catch (Exception $e) {
            return ['success' => false];
        }
    }
    
    /**
     * Calculate page relevance score
     */
    public function calculateRelevanceScore($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    AVG(relevance_score) as avg_relevance,
                    COUNT(*) as result_count,
                    SUM(CASE WHEN relevance_score >= 0.8 THEN 1 ELSE 0 END) as high_relevance_count
                FROM search_results
                WHERE page_id = ?
            ");
            
            $stmt->execute([$pageId]);
            $stats = $stmt->fetch();
            
            $score = $stats['result_count'] > 0 
                ? ($stats['avg_relevance'] * 0.7 + ($stats['high_relevance_count'] / $stats['result_count']) * 0.3)
                : 0;
            
            // Update page relevance score
            $updateStmt = $this->pdo->prepare("UPDATE pages SET relevance_score = ? WHERE id = ?");
            $updateStmt->execute([min($score, 1.0), $pageId]);
            
            return [
                'success' => true,
                'score' => min($score, 1.0),
                'stats' => $stats
            ];
        } catch (Exception $e) {
            return ['success' => false];
        }
    }
    
    /**
     * Sanitize search query
     */
    private function sanitizeQuery($query) {
        $query = trim($query);
        $query = preg_replace('/[^a-zA-Z0-9\s\-\+\"\']/', '', $query);
        return substr($query, 0, 500);
    }
    
    /**
     * Get query execution time
     */
    private function getQueryTime() {
        global $queryStartTime;
        if (isset($queryStartTime)) {
            return round((microtime(true) - $queryStartTime) * 1000, 2);
        }
        return 0;
    }
}