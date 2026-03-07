<?php
/**
 * Intebwio - API Keys Configuration
 * SECURE API KEY STORAGE - Keep this file protected and never commit to git
 * 
 * Instructions:
 * 1. Copy your API keys here
 * 2. Add this file to .gitignore
 * 3. Upload manually to server (never through version control)
 * 4. Set proper file permissions: chmod 600 apikeys.php
 */

// API Key Loader
class APIKeyManager {
    private static $keys = [];
    private static $loaded = false;

    /**
     * Get API key by name
     * @param string $keyName - Name of the API key (e.g., 'gemini', 'google', 'serpapi')
     * @return string|null
     */
    public static function getKey($keyName) {
        self::loadKeys();
        return self::$keys[$keyName] ?? null;
    }

    /**
     * Load all API keys
     */
    private static function loadKeys() {
        if (self::$loaded) return;

        // =========================
        // OPTION 1: Environment variables (production recommended)
        // SET THESE VARIABLES ON YOUR SERVER:
        // export GEMINI_API_KEY='AIzaSy...'
        // =========================
        
        // =========================
        // OPTION 2: Direct key assignment (development)
        // Uncomment and paste your key here to test locally:
        // =========================
        
        // Gemini API Key (Google)
        // PASTE YOUR ACTUAL API KEY HERE:
        $geminiKey = getenv('GEMINI_API_KEY');
        if (!$geminiKey || strpos($geminiKey, 'YOUR_') !== false) {
            // Try to load from local file if environment not set
            // This is for development/testing only - NOT for production!
            // OPTION: Uncomment the line below and paste your real API key:
            // $geminiKey = 'AIzaSy_YOUR_ACTUAL_KEY_HERE';
            
            // If still no key, use placeholder
            if (!$geminiKey) {
                $geminiKey = 'YOUR_GEMINI_API_KEY_HERE';
            }
        }
        self::$keys['gemini'] = trim($geminiKey);
        
        // Google Custom Search API Key
        self::$keys['google_search'] = getenv('GOOGLE_SEARCH_API_KEY') ?: 'YOUR_GOOGLE_SEARCH_KEY_HERE';
        
        // SerpAPI Key (for enhanced web search)
        self::$keys['serpapi'] = getenv('SERPAPI_KEY') ?: 'YOUR_SERPAPI_KEY_HERE';
        
        // Bing Search API Key
        self::$keys['bing'] = getenv('BING_SEARCH_KEY') ?: 'YOUR_BING_SEARCH_KEY_HERE';
        
        // Unsplash API Key (for images)
        self::$keys['unsplash'] = getenv('UNSPLASH_API_KEY') ?: 'YOUR_UNSPLASH_API_KEY_HERE';
        
        // Pexels API Key (for images)
        self::$keys['pexels'] = getenv('PEXELS_API_KEY') ?: 'YOUR_PEXELS_API_KEY_HERE';
        
        // Database user (for additional security)
        self::$keys['db_user'] = getenv('DB_USER') ?: 'u757840095_Yaroslav';
        self::$keys['db_pass'] = getenv('DB_PASS') ?: 'l1@ArIsM';
        
        self::$loaded = true;
    }

    /**
     * Get all available keys (only for debugging - should be removed in production)
     * @return array
     */
    public static function getAllKeys() {
        self::loadKeys();
        // Return keys without sensitive data displayed
        $display = [];
        foreach (self::$keys as $name => $value) {
            $display[$name] = substr_replace($value, '***', 4, -4);
        }
        return $display;
    }

    /**
     * Validate that API keys are configured
     * @return array - Issues found
     */
    public static function validateConfiguration() {
        self::loadKeys();
        $issues = [];

        if (strpos(self::$keys['gemini'], 'YOUR_') !== false) {
            $issues[] = 'Gemini API key not configured';
        }
        if (strpos(self::$keys['google_search'], 'YOUR_') !== false) {
            $issues[] = 'Google Search API key not configured';
        }
        if (strpos(self::$keys['serpapi'], 'YOUR_') !== false) {
            $issues[] = 'SerpAPI key not configured';
        }

        return $issues;
    }
}

// Usage example:
// $geminiKey = APIKeyManager::getKey('gemini');
// $allKeys = APIKeyManager::getAllKeys();
// $issues = APIKeyManager::validateConfiguration();