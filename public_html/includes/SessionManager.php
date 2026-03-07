<?php
/**
 * Intebwio - User Session Manager
 * Manages user sessions, preferences, and history
 */

class SessionManager {
    private $pdo;
    private $sessionId;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initSession();
    }
    
    /**
     * Initialize user session
     */
    private function initSession() {
        if (!isset($_COOKIE['intebwio_session'])) {
            $this->sessionId = $this->generateSessionId();
            setcookie('intebwio_session', $this->sessionId, time() + (30 * 24 * 60 * 60), '/');
        } else {
            $this->sessionId = $_COOKIE['intebwio_session'];
        }
        
        $this->updateSessionActivity();
    }
    
    /**
     * Generate secure session ID
     */
    private function generateSessionId() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Update session activity
     */
    private function updateSessionActivity() {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO session_tracking (session_id, ip_address, user_agent, last_activity)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE last_activity = NOW()
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
            
            $stmt->execute([$this->sessionId, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            error_log("Session update error: " . $e->getMessage());
        }
    }
    
    /**
     * Save user preference
     */
    public function setPreference($key, $value) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_preferences (session_id, preference_key, preference_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE preference_value = ?
            ");
            
            return $stmt->execute([$this->sessionId, $key, $value, $value]);
        } catch (Exception $e) {
            error_log("Preference save error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user preference
     */
    public function getPreference($key, $default = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT preference_value FROM user_preferences
                WHERE session_id = ? AND preference_key = ?
            ");
            
            $stmt->execute([$this->sessionId, $key]);
            $result = $stmt->fetch();
            
            return $result ? $result['preference_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Get all preferences
     */
    public function getAllPreferences() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT preference_key, preference_value FROM user_preferences
                WHERE session_id = ?
            ");
            
            $stmt->execute([$this->sessionId]);
            $results = $stmt->fetchAll();
            
            $preferences = [];
            foreach ($results as $pref) {
                $preferences[$pref['preference_key']] = $pref['preference_value'];
            }
            
            return $preferences;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Save to user history
     */
    public function addToHistory($pageId, $searchQuery, $actionType = 'view') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO session_history (session_id, page_id, search_query, action_type)
                VALUES (?, ?, ?, ?)
            ");
            
            return $stmt->execute([$this->sessionId, $pageId, $searchQuery, $actionType]);
        } catch (Exception $e) {
            error_log("History add error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user history
     */
    public function getHistory($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    sh.id,
                    sh.page_id,
                    sh.search_query,
                    sh.action_type,
                    sh.created_at,
                    p.title
                FROM session_history sh
                LEFT JOIN pages p ON sh.page_id = p.id
                WHERE sh.session_id = ?
                ORDER BY sh.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$this->sessionId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Mark page as favorite
     */
    public function addFavorite($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO user_favorites (session_id, page_id)
                VALUES (?, ?)
            ");
            
            return $stmt->execute([$this->sessionId, $pageId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Remove favorite
     */
    public function removeFavorite($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_favorites
                WHERE session_id = ? AND page_id = ?
            ");
            
            return $stmt->execute([$this->sessionId, $pageId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get user favorites
     */
    public function getFavorites() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.search_query,
                    p.title,
                    p.view_count,
                    p.created_at,
                    uf.created_at as favorited_at
                FROM user_favorites uf
                JOIN pages p ON uf.page_id = p.id
                WHERE uf.session_id = ?
                ORDER BY uf.created_at DESC
            ");
            
            $stmt->execute([$this->sessionId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get session info
     */
    public function getSessionInfo() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    session_id,
                    ip_address,
                    user_agent,
                    created_at,
                    last_activity,
                    TIMESTAMPDIFF(MINUTE, created_at, NOW()) as session_duration_minutes
                FROM session_tracking
                WHERE session_id = ?
            ");
            
            $stmt->execute([$this->sessionId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
}