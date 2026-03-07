<?php
/**
 * Intebwio - Recommendation Engine
 * Generates personalized page recommendations
 */

class RecommendationEngine {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get recommendations based on user history
     */
    public function getPersonalizedRecommendations($sessionId, $limit = 10) {
        try {
            // Get pages similar to ones the user viewed
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT
                    sp.similar_page_id as id,
                    p.search_query,
                    p.title,
                    p.description,
                    p.view_count,
                    p.relevance_score,
                    p.thumbnail_image,
                    sp.similarity_score,
                    COUNT(DISTINCT ua.ip_address) as popular_among_users,
                    AVG(cs.load_time_ms) as avg_load_time
                FROM session_history sh
                JOIN similar_pages sp ON sh.page_id = sp.page_id
                JOIN pages p ON sp.similar_page_id = p.id
                LEFT JOIN user_activity ua ON p.id = ua.page_id
                LEFT JOIN cache_stats cs ON p.id = cs.page_id
                WHERE sh.session_id = ? AND p.status = 'active'
                GROUP BY p.id
                ORDER BY 
                    sp.similarity_score DESC,
                    p.view_count DESC,
                    p.relevance_score DESC
                LIMIT ?
            ");
            
            $stmt->execute([$sessionId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Recommendation error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get trending pages
     */
    public function getTrendingPages($days = 7, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.search_query,
                    p.title,
                    p.description,
                    p.view_count,
                    p.thumbnail_image,
                    COUNT(DISTINCT ua.id) as recent_views,
                    COUNT(DISTINCT uf.session_id) as favorite_count,
                    AVG(sr.relevance_score) as avg_relevance
                FROM pages p
                LEFT JOIN user_activity ua ON p.id = ua.page_id 
                    AND ua.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                LEFT JOIN user_favorites uf ON p.id = uf.page_id
                LEFT JOIN search_results sr ON p.id = sr.page_id
                WHERE p.status = 'active'
                GROUP BY p.id
                HAVING recent_views > 0
                ORDER BY recent_views DESC, p.view_count DESC
                LIMIT ?
            ");
            
            $stmt->execute([$days, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get featured pages
     */
    public function getFeaturedPages($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.search_query,
                    p.title,
                    p.description,
                    p.thumbnail_image,
                    p.view_count,
                    fp.featured_order,
                    fp.featured_from
                FROM featured_pages fp
                JOIN pages p ON fp.page_id = p.id
                WHERE fp.is_active = TRUE 
                    AND (fp.featured_until IS NULL OR fp.featured_until > NOW())
                    AND p.status = 'active'
                ORDER BY fp.featured_order ASC, fp.featured_from DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get related content based on tags/keywords
     */
    public function getRelatedContent($pageId, $limit = 8) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT
                    p2.id,
                    p2.search_query,
                    p2.title,
                    p2.description,
                    p2.thumbnail_image,
                    p2.view_count,
                    SUM(CASE WHEN sp.similar_page_id = p2.id THEN 1 ELSE 0 END) as similarity_count,
                    COUNT(DISTINCT sr.source_name) as unique_sources
                FROM pages p1
                LEFT JOIN similar_pages sp ON p1.id = sp.page_id
                LEFT JOIN pages p2 ON (sp.similar_page_id = p2.id OR 
                    MATCH(p2.search_query, p2.title) 
                    AGAINST(SUBSTRING_INDEX(p1.search_query, ' ', 1) IN BOOLEAN MODE))
                LEFT JOIN search_results sr ON p2.id = sr.page_id
                WHERE p1.id = ? AND p2.id != ? AND p2.status = 'active'
                GROUP BY p2.id
                ORDER BY similarity_count DESC, p2.view_count DESC
                LIMIT ?
            ");
            
            $stmt->execute([$pageId, $pageId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get popular pages in category
     */
    public function getPopularByCategory($category, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.search_query,
                    p.title,
                    p.description,
                    p.view_count,
                    p.thumbnail_image,
                    AVG(sr.relevance_score) as avg_relevance,
                    COUNT(DISTINCT sr.source_name) as source_variety
                FROM pages p
                JOIN search_results sr ON p.id = sr.page_id
                WHERE (p.search_query LIKE ? OR p.title LIKE ? OR p.description LIKE ?)
                    AND p.status = 'active'
                GROUP BY p.id
                ORDER BY p.view_count DESC, p.created_at DESC
                LIMIT ?
            ");
            
            $search = '%' . $category . '%';
            $stmt->execute([$search, $search, $search, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Calculate recommendation score based on multiple factors
     */
    public function calculateRecommendationScore($pageData) {
        $score = 0;
        $weights = [
            'relevance' => 0.3,
            'views' => 0.2,
            'freshness' => 0.2,
            'quality' => 0.15,
            'popularity' => 0.15
        ];
        
        // Relevance score (0-1)
        $relevance = $pageData['relevance_score'] ?? 0;
        $score += $relevance * $weights['relevance'];
        
        // View count score (normalized)
        $views = min($pageData['view_count'] ?? 0, 1000) / 1000;
        $score += $views * $weights['views'];
        
        // Freshness (newer is better)
        $daysOld = (time() - strtotime($pageData['created_at'])) / (24 * 60 * 60);
        $freshness = max(0, 1 - ($daysOld / 365)); // Decay over 1 year
        $score += $freshness * $weights['freshness'];
        
        // Quality estimation
        $quality = isset($pageData['unique_sources']) 
            ? min($pageData['unique_sources'] / 10, 1) 
            : 0.5;
        $score += $quality * $weights['quality'];
        
        // Popularity (recent engagement)
        $popularity = isset($pageData['recent_views']) 
            ? min($pageData['recent_views'] / 100, 1) 
            : 0;
        $score += $popularity * $weights['popularity'];
        
        return min($score, 1.0);
    }
    
    /**
     * Get smart suggestions based on partial query
     */
    public function getSmartSuggestions($partialQuery, $limit = 10) {
        try {
            // Get direct matches
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.search_query,
                    p.id,
                    p.view_count,
                    COUNT(sr.id) as result_count,
                    CASE
                        WHEN p.search_query = ? THEN 3
                        WHEN p.search_query LIKE ? THEN 2
                        WHEN MATCH(p.search_query) AGAINST(? IN BOOLEAN MODE) THEN 1
                        ELSE 0
                    END as match_priority
                FROM pages p
                LEFT JOIN search_results sr ON p.id = sr.page_id
                WHERE p.status = 'active' AND (
                    p.search_query = ? OR
                    p.search_query LIKE ? OR
                    MATCH(p.search_query) AGAINST(? IN BOOLEAN MODE)
                )
                GROUP BY p.id
                ORDER BY match_priority DESC, p.view_count DESC
                LIMIT ?
            ");
            
            $exact = $partialQuery;
            $like = $partialQuery . '%';
            $match = $partialQuery;
            
            $stmt->execute([$exact, $like, $match, $exact, $like, $match, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}