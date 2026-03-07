<?php
/**
 * Intebwio - Database Management Class
 */

class Database {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Initialize database tables
     */
    public function initializeTables() {
        try {
            // Pages table - now with slug for URL-friendly access
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                query VARCHAR(500) NOT NULL,
                slug VARCHAR(500) UNIQUE NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                html_content LONGTEXT NOT NULL,
                ai_provider VARCHAR(50),
                ai_model VARCHAR(100),
                view_count INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_query (query),
                INDEX idx_slug (slug),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Search Results Cache
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS search_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT NOT NULL,
                source_name VARCHAR(100),
                source_url VARCHAR(500),
                title VARCHAR(255),
                description TEXT,
                image_url VARCHAR(500),
                author VARCHAR(100),
                published_date DATETIME,
                relevance_score FLOAT,
                position_index INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                INDEX idx_page (page_id),
                INDEX idx_source (source_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // User Activity Tracking
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT,
                user_ip VARCHAR(45),
                user_agent TEXT,
                action VARCHAR(50),
                metadata JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL,
                INDEX idx_page (page_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Analytics Table
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS analytics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT NOT NULL,
                daily_date DATE,
                view_count INT DEFAULT 0,
                unique_visitors INT DEFAULT 0,
                avg_time_on_page INT DEFAULT 0,
                bounce_rate FLOAT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                UNIQUE KEY unique_daily (page_id, daily_date),
                INDEX idx_page (page_id),
                INDEX idx_date (daily_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Recommendations Table
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS recommendations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT NOT NULL,
                recommendation_type VARCHAR(50),
                score FLOAT,
                metadata JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                INDEX idx_page (page_id),
                INDEX idx_type (recommendation_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Page Cache Table
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS page_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT NOT NULL,
                cache_key VARCHAR(255) UNIQUE,
                expires_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                INDEX idx_page (page_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Settings Table
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value LONGTEXT,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Insert default settings if not exists
            $this->pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
                ('app_version', '2.0.0', 'Application version'),
                ('max_pages', '10000', 'Maximum pages to store'),
                ('cache_ttl', '604800', 'Cache time to live in seconds'),
                ('enable_analytics', '1', 'Enable analytics tracking'),
                ('enable_recommendations', '1', 'Enable recommendation engine')
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("Database initialization error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new page or return existing if similar
     */
    public function createOrGetPage($searchQuery, $title, $description, $htmlContent) {
        try {
            // Check if exact match exists
            $stmt = $this->pdo->prepare("SELECT id FROM pages WHERE search_query = ?");
            $stmt->execute([$searchQuery]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update view count
                $this->updateViewCount($existing['id']);
                return $existing['id'];
            }
            
            // Check for similar pages
            $similarPageId = $this->findSimilarPage($searchQuery, $title);
            if ($similarPageId) {
                $this->updateViewCount($similarPageId);
                return $similarPageId;
            }
            
            // Create new page
            $stmt = $this->pdo->prepare("
                INSERT INTO pages (search_query, title, description, html_content, content)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $searchQuery,
                $title,
                $description,
                $htmlContent,
                strip_tags($htmlContent)
            ]);
            
            $pageId = $this->pdo->lastInsertId();
            
            // Schedule update
            $this->scheduleUpdate($pageId);
            
            return $pageId;
        } catch (Exception $e) {
            error_log("Error creating page: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find similar pages using cosine similarity
     */
    private function findSimilarPage($searchQuery, $title) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, search_query, title FROM pages 
                WHERE status = 'active'
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $pages = $stmt->fetchAll();
            
            $threshold = SIMILARITY_THRESHOLD;
            
            foreach ($pages as $page) {
                $similarity = $this->calculateSimilarity($searchQuery, $page['search_query']);
                if ($similarity >= $threshold) {
                    return $page['id'];
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error finding similar page: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate string similarity (cosine similarity)
     */
    private function calculateSimilarity($str1, $str2) {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        
        // Create n-grams
        $len = 2;
        $gram1 = array_count_values(str_split($str1, $len));
        $gram2 = array_count_values(str_split($str2, $len));
        
        $common = array_intersect_key($gram1, $gram2);
        $similarity = array_sum($common) / (array_sum($gram1) + array_sum($gram2) - array_sum($common));
        
        return $similarity === 0 ? 0 : min(1, max(0, $similarity));
    }
    
    /**
     * Get page by ID
     */
    public function getPageById($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM pages WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$pageId]);
            $page = $stmt->fetch();
            
            if ($page) {
                // Get search results
                $stmt = $this->pdo->prepare("
                    SELECT * FROM search_results WHERE page_id = ? 
                    ORDER BY position_index ASC
                ");
                $stmt->execute([$pageId]);
                $page['search_results'] = $stmt->fetchAll();
                
                // Get elements
                $stmt = $this->pdo->prepare("
                    SELECT * FROM page_elements WHERE page_id = ? 
                    ORDER BY position_index ASC
                ");
                $stmt->execute([$pageId]);
                $page['elements'] = $stmt->fetchAll();
            }
            
            return $page;
        } catch (Exception $e) {
            error_log("Error getting page: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search pages by query
     */
    public function searchPages($query) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, search_query, title, description, thumbnail_image, view_count, created_at
                FROM pages 
                WHERE (status = 'active') AND 
                      (MATCH(search_query, title, description) AGAINST(? IN BOOLEAN MODE) OR 
                       search_query LIKE ?)
                ORDER BY view_count DESC, updated_at DESC
                LIMIT 50
            ");
            
            $likeQuery = '%' . $query . '%';
            $stmt->execute([$query, $likeQuery]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error searching pages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add search result to page
     */
    public function addSearchResult($pageId, $sourceData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO search_results 
                (page_id, source_name, source_url, title, description, image_url, author, published_date, relevance_score, position_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $pageId,
                $sourceData['source_name'] ?? NULL,
                $sourceData['source_url'] ?? NULL,
                $sourceData['title'] ?? NULL,
                $sourceData['description'] ?? NULL,
                $sourceData['image_url'] ?? NULL,
                $sourceData['author'] ?? NULL,
                $sourceData['published_date'] ?? NULL,
                $sourceData['relevance_score'] ?? 0,
                $sourceData['position_index'] ?? 0
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error adding search result: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update view count
     */
    public function updateViewCount($pageId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE pages SET view_count = view_count + 1 WHERE id = ?");
            return $stmt->execute([$pageId]);
        } catch (Exception $e) {
            error_log("Error updating view count: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Schedule page update
     */
    public function scheduleUpdate($pageId) {
        try {
            $nextUpdate = date('Y-m-d H:i:s', strtotime('+7 days'));
            $stmt = $this->pdo->prepare("
                INSERT INTO update_queue (page_id, scheduled_date, status)
                VALUES (?, ?, 'pending')
            ");
            return $stmt->execute([$pageId, $nextUpdate]);
        } catch (Exception $e) {
            error_log("Error scheduling update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pages due for update
     */
    public function getPagesForUpdate() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.search_query, p.title 
                FROM pages p
                LEFT JOIN update_queue uq ON p.id = uq.page_id
                WHERE (uq.status IS NULL OR uq.status = 'failed') AND
                      (p.last_scan_date IS NULL OR p.last_scan_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
                LIMIT 100
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting pages for update: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Record user activity
     */
    public function recordActivity($pageId = null, $searchQuery = null, $actionType = 'search') {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity (page_id, search_query, ip_address, user_agent, action_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([$pageId, $searchQuery, $ipAddress, $userAgent, $actionType]);
        } catch (Exception $e) {
            error_log("Error recording activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Total pages
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM pages WHERE status = 'active'");
            $stats['total_pages'] = $stmt->fetch()['count'];
            
            // Total views
            $stmt = $this->pdo->query("SELECT SUM(view_count) as total FROM pages");
            $stats['total_views'] = $stmt->fetch()['total'] ?? 0;
            
            // Pages updated this week
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM pages WHERE last_scan_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['updated_this_week'] = $stmt->fetch()['count'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting statistics: " . $e->getMessage());
            return [];
        }
    }
}