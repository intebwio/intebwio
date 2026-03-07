<?php
/**
 * Intebwio - Weekly Update Script
 * Scheduled to run once per week to update cached pages
 * Can be run via cron: 0 0 * * 0 /usr/bin/php /path/to/cron/update.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ContentAggregator.php';
require_once __DIR__ . '/../includes/PageGenerator.php';

class UpdateManager {
    private $pdo;
    private $db;
    private $aggregator;
    private $generator;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->db = new Database($pdo);
        $this->aggregator = new ContentAggregator($pdo);
        $this->generator = new PageGenerator($pdo);
    }
    
    /**
     * Run weekly updates
     */
    public function runUpdates() {
        try {
            $this->logMessage("Starting weekly update process...");
            
            $pagesForUpdate = $this->db->getPagesForUpdate();
            $totalPages = count($pagesForUpdate);
            
            if ($totalPages === 0) {
                $this->logMessage("No pages to update");
                return ['success' => true, 'updated' => 0, 'message' => 'No pages to update'];
            }
            
            $this->logMessage("Found $totalPages pages to update");
            
            $updated = 0;
            $failed = 0;
            
            foreach ($pagesForUpdate as $page) {
                if ($this->updatePage($page)) {
                    $updated++;
                    $this->logMessage("✓ Updated: {$page['search_query']}");
                } else {
                    $failed++;
                    $this->logMessage("✗ Failed: {$page['search_query']}");
                }
                
                // Rate limiting - be nice to APIs
                sleep(2);
            }
            
            $message = "Updated $updated pages, $failed failed";
            $this->logMessage("Completed. " . $message);
            
            return [
                'success' => true,
                'updated' => $updated,
                'failed' => $failed,
                'message' => $message
            ];
        } catch (Exception $e) {
            $this->logMessage("Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update a single page
     */
    private function updatePage($page) {
        try {
            // Aggregate fresh content
            $results = $this->aggregator->aggregateContent($page['search_query'], $page['id']);
            
            if (empty($results)) {
                return false;
            }
            
            // Generate new HTML
            $htmlContent = $this->generator->generatePage($page['search_query'], $results);
            
            // Update page in database
            $stmt = $this->pdo->prepare("
                UPDATE pages 
                SET html_content = ?, content = ?, last_scan_date = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $htmlContent,
                strip_tags($htmlContent),
                $page['id']
            ]);
            
            // Clear old search results
            $stmt = $this->pdo->prepare("DELETE FROM search_results WHERE page_id = ?");
            $stmt->execute([$page['id']]);
            
            // Store new results
            foreach ($results as $index => $result) {
                $result['position_index'] = $index;
                // Insert new results
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO search_results 
                    (page_id, source_name, source_url, title, description, image_url, author, published_date, relevance_score, position_index)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insertStmt->execute([
                    $page['id'],
                    $result['source_name'] ?? NULL,
                    $result['source_url'] ?? NULL,
                    $result['title'] ?? NULL,
                    substr($result['description'] ?? '', 0, 1000),
                    $result['image_url'] ?? NULL,
                    $result['author'] ?? NULL,
                    $result['published_date'] ?? date('Y-m-d H:i:s'),
                    $result['relevance_score'] ?? 0.5,
                    $result['position_index'] ?? 0
                ]);
            }
            
            // Update update_queue
            $stmt = $this->pdo->prepare("
                UPDATE update_queue 
                SET status = 'completed', scheduled_date = DATE_ADD(NOW(), INTERVAL 7 DAY)
                WHERE page_id = ?
            ");
            $stmt->execute([$page['id']]);
            
            return true;
        } catch (Exception $e) {
            $this->logMessage("Error updating page {$page['id']}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log messages
     */
    private function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] " . $message . PHP_EOL;
        
        // Log to file
        error_log($logMessage, 3, LOG_FILE);
        
        // Also output to console for cron visibility
        echo $logMessage;
    }
}

// Run if called directly
if (php_sapi_name() === 'cli' || php_sapi_name() === 'cgi-fcgi') {
    $manager = new UpdateManager($pdo);
    $result = $manager->runUpdates();
    exit($result['success'] ? 0 : 1);
}