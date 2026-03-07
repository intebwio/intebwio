<?php
/**
 * Intebwio - Advanced Configuration
 * Extended settings for AI, caching, and optimization
 * ~180 lines
 */

// Database Settings
define('DB_DEBUG', false);

// AI ServiceSettings
define('AI_PROVIDER', getenv('AI_PROVIDER') ?? 'openai'); // openai, gemini, anthropic
define('AI_API_KEY', getenv('AI_API_KEY') ?? '');
define('AI_TIMEOUT', 60); // seconds
define('AI_MAX_RETRIES', 3);

// Content Aggregation Settings
define('AGGREGATION_TIMEOUT', 15);
define('MIN_CONTENT_SOURCES', 3);
define('MAX_RESULTS_PER_SOURCE', 8);

// Caching Settings
define('CACHE_ENABLED', true);
define('CACHE_DRIVER', 'apcu'); // apcu, redis, memcached
define('CACHE_TTL', 604800); // 7 days
define('CACHE_WARM_ON_STARTUP', true);

// Performance Optimization
define('ENABLE_COMPRESSION', true);
define('ENABLE_MINIFICATION', true);
define('ENABLE_LAZY_LOADING', true);
define('ENABLE_CDN', false);

// Page Generation Settings
define('PAGES_PER_REQUEST', 1); // One page per topic
define('AUTO_GENERATE_VARIANTS', false);
define('ENABLE_COMMENTS', true);
define('COMMENTS_REQUIRE_APPROVAL', true);

// Update Settings
define('AUTO_UPDATE_ENABLED', true);
define('AUTO_UPDATE_INTERVAL', 604800); // 7 days
define('UPDATE_BATCH_SIZE', 10);
define('UPDATE_TIMEOUT', 120);

// Security Settings
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // requests
define('RATE_LIMIT_WINDOW', 3600); // seconds (1 hour)
define('IP_WHITELIST', []); // empty = all allowed

// API Integration
define('GOOGLE_API_KEY', getenv('GOOGLE_API_KEY') ?? '');
define('SERPAPI_KEY', getenv('SERPAPI_KEY') ?? '');
define('NEWSAPI_KEY', getenv('NEWSAPI_KEY') ?? '');

// Analytics Settings
define('ANALYTICS_ENABLED', true);
define('TRACKING_ENABLED', true);
define('HEAT_MAPPING_ENABLED', false);

// SEO Settings
define('SITEMAPS_ENABLED', true);
define('ROBOTS_TXT_ENABLED', true);
define('CANONICAL_URLS', true);
define('STRUCTURED_DATA', true);

// Notification Settings
define('SLACK_WEBHOOK', getenv('SLACK_WEBHOOK') ?? '');
define('EMAIL_NOTIFICATIONS', getenv('NOTIFICATION_EMAIL') ?? 'admin@intebwio.com');
define('ENABLE_NOTIFICATIONS', true);

// Feature Flags
define('FEATURE_AI_GENERATION', true);
define('FEATURE_AUTO_UPDATES', true);
define('FEATURE_COMMENTS', true);
define('FEATURE_RELATED_TOPICS', true);
define('FEATURE_QUICK_REFERENCE', true);
define('FEATURE_PDF_EXPORT', false);

// Logging Settings
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR, CRITICAL
define('LOG_QUERIES', DB_DEBUG);
define('LOG_SEARCHES', true);
define('LOG_ERRORS', true);

/**
 * Get feature flag status
 */
function isFeatureEnabled($feature) {
    $flags = [
        'ai_generation' => FEATURE_AI_GENERATION,
        'auto_updates' => FEATURE_AUTO_UPDATES,
        'comments' => FEATURE_COMMENTS,
        'related_topics' => FEATURE_RELATED_TOPICS,
        'quick_reference' => FEATURE_QUICK_REFERENCE,
        'pdf_export' => FEATURE_PDF_EXPORT
    ];
    
    return $flags[strtolower($feature)] ?? false;
}

/**
 * Get cache driver instance
 */
function getCacheDriver() {
    switch (CACHE_DRIVER) {
        case 'redis':
            return new RedisCacheDriver();
        case 'memcached':
            return new MemcachedCacheDriver();
        case 'apcu':
        default:
            return new APCuCacheDriver();
    }
}

/**
 * Cache driver classes
 */
class APCuCacheDriver {
    public function get($key) {
        if (function_exists('apcu_fetch')) {
            return apcu_fetch('intebwio_' . $key, $success);
        }
        return false;
    }
    
    public function set($key, $value, $ttl = 0) {
        if (function_exists('apcu_store')) {
            return apcu_store('intebwio_' . $key, $value, $ttl);
        }
        return false;
    }
    
    public function delete($key) {
        if (function_exists('apcu_delete')) {
            return apcu_delete('intebwio_' . $key);
        }
        return false;
    }
}

/**
 * Logging utility
 */
class Logger {
    private $level;
    
    public function __construct($level = 'INFO') {
        $this->level = $level;
    }
    
    public function log($message, $level = 'INFO') {
        if (!LOG_QUERIES && $level === 'DEBUG') return;
        
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
        
        if ($levels[$level] >= $levels[LOG_LEVEL]) {
            $timestamp = date('Y-m-d H:i:s');
            $logLine = "[$timestamp] [$level] " . $message . PHP_EOL;
            
            error_log($logLine, 3, LOG_FILE);
        }
    }
    
    public function debug($message) { $this->log($message, 'DEBUG'); }
    public function info($message) { $this->log($message, 'INFO'); }
    public function warning($message) { $this->log($message, 'WARNING'); }
    public function error($message) { $this->log($message, 'ERROR'); }
    public function critical($message) { $this->log($message, 'CRITICAL'); }
}

/**
 * Rate limiter
 */
class RateLimiter {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function checkLimit($identifier) {
        if (!RATE_LIMIT_ENABLED) return true;
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as requests FROM api_requests
            WHERE identifier = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$identifier, RATE_LIMIT_WINDOW]);
        $result = $stmt->fetch();
        
        return $result['requests'] < RATE_LIMIT_REQUESTS;
    }
    
    public function recordRequest($identifier) {
        $stmt = $this->pdo->prepare("
            INSERT INTO api_requests (identifier) VALUES (?)
        ");
        $stmt->execute([$identifier]);
    }
}