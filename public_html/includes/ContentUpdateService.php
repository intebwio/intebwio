<?php
/**
 * Content Update Service
 * Automatically updates pages with fresh AI-generated content
 * ~200 lines
 */

class ContentUpdateService {
    private $pdo;
    private $aiService;
    private $contentAggregator;
    private $advancedGenerator;
    private $logger;
    
    public function __construct($pdo, $aiService, $contentAggregator, $advancedGenerator, $logger = null) {
        $this->pdo = $pdo;
        $this->aiService = $aiService;
        $this->contentAggregator = $contentAggregator;
        $this->advancedGenerator = $advancedGenerator;
        $this->logger = $logger;
    }
    
    /**
     * Check and update pages that need refreshing
     */
    public function updateOutdatedPages($maxAge = 604800) {
        $stmt = $this->pdo->prepare("
            SELECT id, search_query 
            FROM pages 
            WHERE status = 'active'
            AND (updated_at IS NULL OR updated_at < NOW() - INTERVAL ? SECOND)
            LIMIT 10
        ");
        
        $stmt->execute([$maxAge]);
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updates = [];
        foreach ($pages as $page) {
            try {
                $updated = $this->updatePage($page['id'], $page['search_query']);
                if ($updated) {
                    $updates[] = [
                        'page_id' => $page['id'],
                        'query' => $page['search_query'],
                        'status' => 'success'
                    ];
                }
            } catch (Exception $e) {
                $updates[] = [
                    'page_id' => $page['id'],
                    'query' => $page['search_query'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                $this->log("Update failed for page {$page['id']}: " . $e->getMessage());
            }
        }
        
        return $updates;
    }
    
    /**
     * Update individual page
     */
    private function updatePage($pageId, $query) {
        // Save current version
        $stmt = $this->pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$pageId]);
        $currentPage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentPage) {
            return false;
        }
        
        // Save to version history
        $versionStmt = $this->pdo->prepare("
            INSERT INTO page_versions 
            (page_id, version_number, html_content, json_content, ai_model, quality_score)
            SELECT id, COALESCE((SELECT MAX(version_number) FROM page_versions WHERE page_id = ?), 0) + 1,
                   html_content, json_content, ai_provider, seo_score
            FROM pages
            WHERE id = ?
        ");
        $versionStmt->execute([$pageId, $pageId]);
        
        // Generate new content
        $aggregatedContent = $this->contentAggregator->aggregateContent($query);
        $newHtmlContent = $this->advancedGenerator->generateAIPage($query, $aggregatedContent);
        
        // Extract SEO score
        $seoScore = $this->calculateSEOScore($newHtmlContent);
        
        // Update page with new content
        $updateStmt = $this->pdo->prepare("
            UPDATE pages 
            SET html_content = ?,
                seo_score = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $updateStmt->execute([$newHtmlContent, $seoScore, $pageId]);
        
        // Log update
        $logStmt = $this->pdo->prepare("
            INSERT INTO update_history 
            (page_id, old_content, new_content, changes_summary, update_type, triggered_by)
            VALUES (?, ?, ?, ?, 'auto_update', 'ContentUpdateService')
        ");
        
        $summary = "Auto-updated content. New SEO score: $seoScore";
        $logStmt->execute([
            $pageId,
            substr($currentPage['html_content'], 0, 1000),
            substr($newHtmlContent, 0, 1000),
            $summary
        ]);
        
        $this->log("Page $pageId updated successfully");
        return $result;
    }
    
    /**
     * Calculate SEO score for content
     */
    private function calculateSEOScore($htmlContent) {
        $score = 0;
        
        // Check for title
        if (preg_match('/<title>(.+?)<\/title>/i', $htmlContent)) $score += 20;
        
        // Check for meta description
        if (preg_match('/<meta name="description"/i', $htmlContent)) $score += 15;
        
        // Check for headings
        if (preg_match('/<h1/i', $htmlContent)) $score += 15;
        if (preg_match_all('/<h[2-6]/i', $htmlContent)) $score += 10;
        
        // Check for images with alt text
        if (preg_match('/<img[^>]+alt=["\'][^"\']+["\'][^>]*>/i', $htmlContent)) $score += 15;
        
        // Check for internal links
        $links = substr_count($htmlContent, '<a href');
        if ($links > 5) $score += 10;
        
        // Check content length
        $textLength = strlen(strip_tags($htmlContent));
        if ($textLength > 1000) $score += 10;
        
        // Check for structured data
        if (preg_match('/<script[^>]+application\/ld\+json/i', $htmlContent)) $score += 5;
        
        // Check for mobile viewport
        if (preg_match('/<meta name="viewport"/i', $htmlContent)) $score += 5;
        
        return min($score, 100);
    }
    
    /**
     * Find and merge duplicate pages
     */
    public function mergeDuplicatePages() {
        $stmt = $this->pdo->query("
            SELECT p1.id as page1_id, p2.id as page2_id, 
                   LEVENSHTEIN(p1.search_query, p2.search_query) as similarity
            FROM pages p1
            JOIN pages p2 ON p1.id < p2.id
            WHERE LEVENSHTEIN(p1.search_query, p2.search_query) < 5
            AND p1.status = 'active'
            AND p2.status = 'active'
        ");
        
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $merged = [];
        
        foreach ($duplicates as $dup) {
            // Keep page with more views
            $keep = $this->getPageWithMoreViews($dup['page1_id'], $dup['page2_id']);
            $remove = ($keep === $dup['page1_id']) ? $dup['page2_id'] : $dup['page1_id'];
            
            // Delete duplicate
            $deleteStmt = $this->pdo->prepare("UPDATE pages SET status = 'archived' WHERE id = ?");
            $deleteStmt->execute([$remove]);
            
            $merged[] = [
                'kept' => $keep,
                'removed' => $remove,
                'similarity' => $dup['similarity']
            ];
        }
        
        return $merged;
    }
    
    /**
     * Generate sitemap
     */
    public function generateSitemap($outputPath = null) {
        $stmt = $this->pdo->query("
            SELECT id, search_query, updated_at, view_count
            FROM pages
            WHERE status = 'active'
            ORDER BY updated_at DESC
        ");
        
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($pages as $page) {
            $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($page['search_query']));
            $url = APP_URL . '/page.php?id=' . $page['id'];
            
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
            $xml .= "    <lastmod>" . date('Y-m-d', strtotime($page['updated_at'])) . "</lastmod>\n";
            $xml .= "    <changefreq>" . ($page['view_count'] > 100 ? 'weekly' : 'monthly') . "</changefreq>\n";
            $xml .= "    <priority>" . min(1.0, 0.5 + ($page['view_count'] / 1000)) . "</priority>\n";
            $xml .= "  </url>\n";
        }
        
        $xml .= '</urlset>';
        
        if ($outputPath) {
            file_put_contents($outputPath, $xml);
        }
        
        return $xml;
    }
    
    /**
     * Get page with more views
     */
    private function getPageWithMoreViews($page1, $page2) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM pages 
            WHERE id IN (?, ?)
            ORDER BY view_count DESC
            LIMIT 1
        ");
        
        $stmt->execute([$page1, $page2]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'];
    }
    
    /**
     * Log message
     */
    private function log($message) {
        if ($this->logger) {
            $this->logger->log($message);
        } else {
            error_log('[ContentUpdateService] ' . $message);
        }
    }
}