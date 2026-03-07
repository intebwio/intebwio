<?php
/**
 * Intebwio - Quality Scoring System
 * Scores pages based on content quality, sources, and relevance
 */

class QualityScoringSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate comprehensive quality score
     */
    public function calculateQualityScore($pageId) {
        try {
            // Get page data
            $pageStmt = $this->pdo->prepare("SELECT * FROM pages WHERE id = ?");
            $pageStmt->execute([$pageId]);
            $page = $pageStmt->fetch();
            
            if (!$page) {
                return ['success' => false, 'message' => 'Page not found'];
            }
            
            $score = 0;
            $details = [];
            
            // 1. Content Freshness (20%)
            $freshnessScore = $this->scoreFreshness($page['created_at'], $page['last_scan_date']);
            $score += $freshnessScore * 0.2;
            $details['freshness'] = ['score' => $freshnessScore, 'weight' => 0.2];
            
            // 2. Source Quality (25%)
            $sourceScore = $this->scoreSourceQuality($pageId);
            $score += $sourceScore * 0.25;
            $details['sources'] = ['score' => $sourceScore, 'weight' => 0.25];
            
            // 3. Content Completeness (20%)
            $completenessScore = $this->scoreCompleteness($pageId);
            $score += $completenessScore * 0.2;
            $details['completeness'] = ['score' => $completenessScore, 'weight' => 0.2];
            
            // 4. User Engagement (20%)
            $engagementScore = $this->scoreEngagement($pageId);
            $score += $engagementScore * 0.2;
            $details['engagement'] = ['score' => $engagementScore, 'weight' => 0.2];
            
            // 5. Relevance (15%)
            $relevanceScore = $page['relevance_score'] ?? 0;
            $score += $relevanceScore * 0.15;
            $details['relevance'] = ['score' => $relevanceScore, 'weight' => 0.15];
            
            // Normalize score to 0-100
            $finalScore = round($score * 100, 2);
            
            // Determine quality tier
            $tier = $this->getQualityTier($finalScore);
            
            // Store quality score
            $updateStmt = $this->pdo->prepare("
                INSERT INTO page_quality_scores (page_id, quality_score, quality_tier, details)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    quality_score = VALUES(quality_score),
                    quality_tier = VALUES(quality_tier),
                    details = VALUES(details)
            ");
            
            $updateStmt->execute([$pageId, $finalScore, $tier, json_encode($details)]);
            
            return [
                'success' => true,
                'page_id' => $pageId,
                'quality_score' => $finalScore,
                'tier' => $tier,
                'details' => $details
            ];
            
        } catch (Exception $e) {
            error_log("Quality scoring error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Score content freshness
     */
    private function scoreFreshness($createdAt, $lastScanDate) {
        $createdTime = strtotime($createdAt);
        $now = time();
        $daysSinceCreation = ($now - $createdTime) / (24 * 60 * 60);
        
        // Score decays over time (newer is better)
        $maxScore = 1.0;
        if ($daysSinceCreation === 0) {
            return $maxScore; // New page
        } elseif ($daysSinceCreation <= 7) {
            return 1.0; // Very fresh
        } elseif ($daysSinceCreation <= 30) {
            return 0.9; // Fresh
        } elseif ($daysSinceCreation <= 90) {
            return 0.7; // Moderate
        } else {
            return max(0.3, 1.0 - ($daysSinceCreation / 365) * 0.7); // Decaying
        }
    }
    
    /**
     * Score source quality
     */
    private function scoreSourceQuality($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_sources,
                    COUNT(DISTINCT source_name) as unique_sources,
                    AVG(relevance_score) as avg_relevance,
                    SUM(CASE WHEN source_name IN ('Wikipedia', 'GitHub', 'News') THEN 1 ELSE 0 END) as premium_sources,
                    MAX(relevance_score) as max_relevance,
                    MIN(relevance_score) as min_relevance
                FROM search_results
                WHERE page_id = ?
            ");
            
            $stmt->execute([$pageId]);
            $data = $stmt->fetch();
            
            if (!$data || $data['total_sources'] === 0) {
                return 0;
            }
            
            // Score based on:
            // - Number of unique sources
            // - Average relevance
            // - Presence of premium sources
            // - Consistency of sources
            
            $uniqueSourceScore = min($data['unique_sources'] / 10, 1.0); // Up to 10 unique sources
            $relevanceScore = $data['avg_relevance'] ?? 0;
            $premiumScore = min($data['premium_sources'] / 5, 1.0); // Up to 5 premium sources
            $consistencyScore = $data['max_relevance'] > 0 
                ? 1.0 - (($data['max_relevance'] - $data['min_relevance']) / $data['max_relevance'])
                : 0.5;
            
            return (
                $uniqueSourceScore * 0.3 +
                $relevanceScore * 0.4 +
                $premiumScore * 0.2 +
                $consistencyScore * 0.1
            );
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Score content completeness
     */
    private function scoreCompleteness($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as element_count,
                    SUM(CASE WHEN element_type = 'image' THEN 1 ELSE 0 END) as image_count,
                    SUM(CASE WHEN element_type = 'table' THEN 1 ELSE 0 END) as table_count,
                    SUM(CASE WHEN element_type = 'diagram' THEN 1 ELSE 0 END) as diagram_count
                FROM page_elements
                WHERE page_id = ?
            ");
            
            $stmt->execute([$pageId]);
            $data = $stmt->fetch();
            
            // Completeness based on:
            // - Total elements
            // - Variety of element types
            
            $elementScore = min(($data['element_count'] ?? 0) / 20, 1.0); // Max 20 elements
            
            $varietyScore = 0;
            if (($data['image_count'] ?? 0) > 0) $varietyScore += 0.25;
            if (($data['table_count'] ?? 0) > 0) $varietyScore += 0.25;
            if (($data['diagram_count'] ?? 0) > 0) $varietyScore += 0.25;
            $varietyScore += 0.25; // Text content
            
            return $elementScore * 0.6 + $varietyScore * 0.4;
            
        } catch (Exception $e) {
            return 0.5;
        }
    }
    
    /**
     * Score user engagement
     */
    private function scoreEngagement($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_activities,
                    SUM(CASE WHEN action_type = 'view' THEN 1 ELSE 0 END) as views,
                    SUM(CASE WHEN action_type = 'click' THEN 1 ELSE 0 END) as clicks,
                    COUNT(DISTINCT ip_address) as unique_users
                FROM user_activity
                WHERE page_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $stmt->execute([$pageId]);
            $data = $stmt->fetch();
            
            // Score based on activity in last 30 days
            $viewScore = min(($data['views'] ?? 0) / 100, 1.0); // Max 100 views
            $clickScore = min(($data['clicks'] ?? 0) / 50, 1.0); // Max 50 clicks
            $userScore = min(($data['unique_users'] ?? 0) / 20, 1.0); // Max 20 unique users
            
            return $viewScore * 0.4 + $clickScore * 0.3 + $userScore * 0.3;
            
        } catch (Exception $e) {
            return 0.5;
        }
    }
    
    /**
     * Get quality tier based on score
     */
    private function getQualityTier($score) {
        if ($score >= 85) {
            return 'Excellent';
        } elseif ($score >= 70) {
            return 'Good';
        } elseif ($score >= 55) {
            return 'Fair';
        } elseif ($score >= 40) {
            return 'Below Average';
        } else {
            return 'Poor';
        }
    }
    
    /**
     * Get quality report for page
     */
    public function getQualityReport($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM page_quality_scores WHERE page_id = ?
            ");
            
            $stmt->execute([$pageId]);
            $report = $stmt->fetch();
            
            if (!$report) {
                return $this->calculateQualityScore($pageId);
            }
            
            return [
                'success' => true,
                'report' => $report,
                'details' => json_decode($report['details'], true)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}