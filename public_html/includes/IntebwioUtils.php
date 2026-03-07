<?php
/**
 * Intebwio - Utility Class
 * Common functions and helpers for the application
 * ~200 lines
 */

class IntebwioUtils {
    /**
     * Sanitize input string
     */
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Generate unique slug from text
     */
    public static function generateSlug($text) {
        // Convert to lowercase
        $text = strtolower($text);
        // Replace spaces with hyphens
        $text = preg_replace('/\s+/', '-', $text);
        // Remove special characters except hyphens
        $text = preg_replace('/[^a-z0-9-]/', '', $text);
        // Remove consecutive hyphens
        $text = preg_replace('/-+/', '-', $text);
        // Trim hyphens from beginning and end
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * Truncate text with ellipsis
     */
    public static function truncateText($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        $truncated = substr($text, 0, $length);
        $truncated = substr($truncated, 0, strrpos($truncated, ' '));
        
        return $truncated . $suffix;
    }
    
    /**
     * Format bytes as human readable
     */
    public static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Format time difference
     */
    public static function timeAgo($time) {
        $time = strtotime($time);
        $diff = time() - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return ceil($diff / 60) . ' minutes ago';
        if ($diff < 86400) return ceil($diff / 3600) . ' hours ago';
        if ($diff < 604800) return ceil($diff / 86400) . ' days ago';
        
        return date('M d, Y', $time);
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32) {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $result;
    }
    
    /**
     * Check if string contains similar matches
     */
    public static function calculateSimilarity($str1, $str2) {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if (levenshtein($str1, $str2) == 0) return 1.0;
        
        $length = max(strlen($str1), strlen($str2));
        return 1.0 - (levenshtein($str1, $str2) / $length);
    }
    
    /**
     * Extract domain from URL
     */
    public static function extractDomain($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? $parsed['path'] ?? $url;
    }
    
    /**
     * Check if page exists
     */
    public static function pageExists($pdo, $query) {
        $stmt = $pdo->prepare("
            SELECT id FROM pages 
            WHERE LOWER(search_query) = LOWER(?) 
            LIMIT 1
        ");
        $stmt->execute([$query]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Get page by query
     */
    public static function getPageByQuery($pdo, $query) {
        $stmt = $pdo->prepare("
            SELECT * FROM pages 
            WHERE LOWER(search_query) = LOWER(?) 
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$query]);
        return $stmt->fetch();
    }
    
    /**
     * Get page by ID
     */
    public static function getPageById($pdo, $pageId) {
        $stmt = $pdo->prepare("
            SELECT * FROM pages 
            WHERE id = ? 
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$pageId]);
        return $stmt->fetch();
    }
    
    /**
     * Search pages
     */
    public static function searchPages($pdo, $keyword, $limit = 20) {
        $stmt = $pdo->prepare("
            SELECT id, search_query as title, description, view_count
            FROM pages 
            WHERE status = 'active' 
            AND (search_query LIKE ? OR title LIKE ? OR description LIKE ?)
            ORDER BY view_count DESC
            LIMIT ?
        ");
        
        $likeKeyword = '%' . $keyword . '%';
        $stmt->execute([$likeKeyword, $likeKeyword, $likeKeyword, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Log activity
     */
    public static function logActivity($pdo, $pageId, $query, $actionType = 'search') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_activity 
                (page_id, search_query, ip_address, user_agent, action_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
            
            $stmt->execute([$pageId, $query, $ipAddress, $userAgent, $actionType]);
            return true;
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification
     */
    public static function sendNotification($title, $message, $type = 'info') {
        if (!ENABLE_NOTIFICATIONS) return false;
        
        // Send to Slack if configured
        if (!empty(SLACK_WEBHOOK)) {
            $payload = [
                'text' => $title,
                'attachments' => [
                    [
                        'color' => $type === 'error' ? 'danger' : 'good',
                        'text' => $message
                    ]
                ]
            ];
            
            $ch = curl_init(SLACK_WEBHOOK);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
        
        // Send email if configured
        if (!empty(EMAIL_NOTIFICATIONS)) {
            mail(EMAIL_NOTIFICATIONS, $title, $message, 'Content-Type: text/plain; charset=utf-8');
        }
        
        return true;
    }
    
    /**
     * Get file extension
     */
    public static function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Create directory if not exists
     */
    public static function ensureDirectory($path) {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }
    
    /**
     * Get JSON from file
     */
    public static function getJsonFromFile($filepath) {
        if (!file_exists($filepath)) {
            return null;
        }
        
        $content = file_get_contents($filepath);
        return json_decode($content, true);
    }
    
    /**
     * Save JSON to file
     */
    public static function saveJsonToFile($filepath, $data) {
        self::ensureDirectory(dirname($filepath));
        return file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}