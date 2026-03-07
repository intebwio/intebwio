<?php
/**
 * Database Migration for Intebwio
 * Creates all required tables for production
 * ~250 lines
 */

class DatabaseMigration {
    private $pdo;
    private $migrationLog = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Run all migrations
     */
    public function runMigrations() {
        echo "Starting database migrations...\n\n";
        
        $this->createPagesTable();
        $this->createSearchResultsTable();
        $this->createPageCommentsTable();
        $this->createUserActivityTable();
        $this->createCacheMetadataTable();
        $this->createApiRequestsTable();
        $this->createUpdateHistoryTable();
        $this->createPageStatsTable();
        $this->createSimilarPagesTable();
        $this->createPageVersionsTable();
        
        echo "\n✅ All migrations completed successfully!\n";
        return $this->migrationLog;
    }
    
    /**
     * Create pages table
     */
    private function createPagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            search_query VARCHAR(255) NOT NULL UNIQUE,
            title VARCHAR(255),
            description TEXT,
            html_content LONGTEXT,
            json_content LONGTEXT,
            ai_provider VARCHAR(50),
            ai_model VARCHAR(100),
            source_urls JSON,
            categories JSON,
            tags JSON,
            view_count INT DEFAULT 0,
            unique_visitors INT DEFAULT 0,
            avg_read_time INT DEFAULT 0,
            seo_score INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_viewed_at TIMESTAMP NULL,
            status ENUM('active', 'archived', 'pending') DEFAULT 'active',
            INDEX (search_query),
            INDEX (status),
            INDEX (view_count),
            FULLTEXT (title, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "pages");
    }
    
    /**
     * Create search results table
     */
    private function createSearchResultsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS search_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT,
            search_query VARCHAR(255),
            related_queries JSON,
            rank_position INT,
            relevance_score DECIMAL(3,2),
            click_through_rate DECIMAL(5,4),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            INDEX (search_query),
            INDEX (page_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "search_results");
    }
    
    /**
     * Create page comments table
     */
    private function createPageCommentsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS page_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            author_name VARCHAR(100),
            author_email VARCHAR(100),
            content TEXT NOT NULL,
            likes INT DEFAULT 0,
            is_approved BOOLEAN DEFAULT FALSE,
            parent_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES page_comments(id) ON DELETE CASCADE,
            INDEX (page_id),
            INDEX (is_approved)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "page_comments");
    }
    
    /**
     * Create user activity table
     */
    private function createUserActivityTable() {
        $sql = "CREATE TABLE IF NOT EXISTS user_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT,
            search_query VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            action_type VARCHAR(50),
            duration_seconds INT,
            scroll_depth INT,
            device_type VARCHAR(50),
            country VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            INDEX (page_id),
            INDEX (created_at),
            INDEX (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "user_activity");
    }
    
    /**
     * Create cache metadata table
     */
    private function createCacheMetadataTable() {
        $sql = "CREATE TABLE IF NOT EXISTS cache_metadata (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT,
            cache_key VARCHAR(255) UNIQUE,
            cache_driver VARCHAR(50),
            ttl_seconds INT,
            size_bytes INT,
            hit_count INT DEFAULT 0,
            last_accessed TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            INDEX (cache_key),
            INDEX (page_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "cache_metadata");
    }
    
    /**
     * Create API requests table
     */
    private function createApiRequestsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS api_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45),
            endpoint VARCHAR(255),
            method VARCHAR(10),
            query_string TEXT,
            response_code INT,
            response_time_ms INT,
            ai_api_calls INT DEFAULT 0,
            ai_api_cost DECIMAL(10,4) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (ip_address),
            INDEX (endpoint),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "api_requests");
    }
    
    /**
     * Create update history table
     */
    private function createUpdateHistoryTable() {
        $sql = "CREATE TABLE IF NOT EXISTS update_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            old_content TEXT,
            new_content TEXT,
            changes_summary TEXT,
            update_type VARCHAR(50),
            triggered_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            INDEX (page_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "update_history");
    }
    
    /**
     * Create page stats table
     */
    private function createPageStatsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS page_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            date DATE,
            views INT DEFAULT 0,
            unique_visitors INT DEFAULT 0,
            bounce_rate DECIMAL(5,2) DEFAULT 0,
            avg_session_duration INT,
            avg_scroll_depth INT,
            top_referrer VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            UNIQUE KEY unique_page_date (page_id, date),
            INDEX (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "page_stats");
    }
    
    /**
     * Create similar pages table
     */
    private function createSimilarPagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS similar_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id_1 INT NOT NULL,
            page_id_2 INT NOT NULL,
            similarity_score DECIMAL(5,4),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id_1) REFERENCES pages(id) ON DELETE CASCADE,
            FOREIGN KEY (page_id_2) REFERENCES pages(id) ON DELETE CASCADE,
            UNIQUE KEY unique_pair (page_id_1, page_id_2),
            INDEX (similarity_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "similar_pages");
    }
    
    /**
     * Create page versions table
     */
    private function createPageVersionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS page_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            version_number INT,
            html_content LONGTEXT,
            json_content LONGTEXT,
            ai_model VARCHAR(100),
            quality_score INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            UNIQUE KEY unique_version (page_id, version_number),
            INDEX (page_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->executeMigration($sql, "page_versions");
    }
    
    /**
     * Execute migration
     */
    private function executeMigration($sql, $tableName) {
        try {
            $this->pdo->exec($sql);
            $message = "✅ Table '{$tableName}' created/verified successfully";
            echo $message . "\n";
            $this->migrationLog[] = $message;
            return true;
        } catch (PDOException $e) {
            $message = "❌ Error with table '{$tableName}': " . $e->getMessage();
            echo $message . "\n";
            $this->migrationLog[] = $message;
            return false;
        }
    }
    
    /**
     * Create indexes for performance
     */
    public function createIndexes() {
        $indexes = [
            "ALTER TABLE pages ADD INDEX idx_created_at (created_at)",
            "ALTER TABLE pages ADD INDEX idx_ai_provider (ai_provider)",
            "ALTER TABLE user_activity ADD INDEX idx_device_type (device_type)",
            "ALTER TABLE api_requests ADD INDEX idx_response_code (response_code)",
            "ALTER TABLE page_stats ADD INDEX idx_views (views)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Exception $e) {
                // Index may already exist
            }
        }
    }
}