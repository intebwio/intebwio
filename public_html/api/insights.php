<?php
/**
 * Extended Analytics and Insights API
 * Detailed analytics with trend analysis and advanced metrics
 * ~220 lines
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$type = $_GET['type'] ?? 'summary';
$days = min((int)($_GET['days'] ?? 30), 365);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    switch ($type) {
        case 'summary':
            getAnalyticsSummary($pdo, $days);
            break;
        case 'daily-trend':
            getDailyTrends($pdo, $days);
            break;
        case 'ai-providers':
            getAIProviderStats($pdo);
            break;
        case 'search-patterns':
            getSearchPatterns($pdo, $days);
            break;
        case 'engagement':
            getEngagementMetrics($pdo, $days);
            break;
        case 'growth':
            getGrowthAnalytics($pdo, $days);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getAnalyticsSummary($pdo, $days) {
    $startDate = date('Y-m-d', time() - ($days * 86400));
    
    $stmt = $pdo->prepare("SELECT 
        (SELECT COUNT(*) FROM pages) as total_pages,
        (SELECT SUM(view_count) FROM pages) as total_views,
        (SELECT COUNT(DISTINCT ip_address) FROM user_activity WHERE created_at >= ?) as unique_visitors,
        (SELECT COUNT(*) FROM page_comments WHERE is_approved = 1) as total_comments,
        (SELECT COUNT(*) FROM user_activity WHERE created_at >= ? AND action_type = 'search') as recent_searches,
        (SELECT AVG(seo_score) FROM pages WHERE seo_score IS NOT NULL) as avg_seo_score,
        (SELECT COUNT(*) FROM pages WHERE created_at >= ?) as new_pages_period
    ");
    
    $stmt->execute([$startDate, $startDate, $startDate]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'days' => $days,
        'summary' => [
            'total_pages' => (int)$data['total_pages'],
            'total_views' => (int)($data['total_views'] ?? 0),
            'unique_visitors_period' => (int)($data['unique_visitors'] ?? 0),
            'total_comments' => (int)($data['total_comments'] ?? 0),
            'recent_searches' => (int)($data['recent_searches'] ?? 0),
            'avg_seo_score' => round((float)$data['avg_seo_score'], 1),
            'new_pages_in_period' => (int)($data['new_pages_period'] ?? 0)
        ]
    ]);
}

function getDailyTrends($pdo, $days) {
    $startDate = date('Y-m-d', time() - ($days * 86400));
    
    $stmt = $pdo->prepare("SELECT 
        DATE(created_at) as date,
        COUNT(*) as searches,
        COUNT(DISTINCT ip_address) as unique_users,
        SUM(CASE WHEN action_type = 'view' THEN 1 ELSE 0 END) as page_views,
        AVG(duration_seconds) as avg_session_length
    FROM user_activity
    WHERE created_at >= ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC");
    
    $stmt->execute([$startDate]);
    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'date_range' => ['start' => $startDate, 'end' => date('Y-m-d')],
        'daily_trends' => $trends
    ]);
}

function getAIProviderStats($pdo) {
    $stmt = $pdo->query("SELECT 
        ai_provider,
        COUNT(*) as pages_generated,
        AVG(seo_score) as avg_quality,
        AVG(view_count) as avg_views,
        MAX(view_count) as best_performing_page
    FROM pages
    WHERE ai_provider IS NOT NULL
    GROUP BY ai_provider
    ORDER BY pages_generated DESC");
    
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ai_providers' => $providers
    ]);
}

function getSearchPatterns($pdo, $days) {
    $startDate = date('Y-m-d', time() - ($days * 86400));
    
    // Top searches
    $topStmt = $pdo->prepare("SELECT 
        search_query as query,
        COUNT(*) as count,
        COUNT(DISTINCT ip_address) as unique_users
    FROM user_activity
    WHERE created_at >= ? AND action_type = 'search'
    GROUP BY search_query
    ORDER BY count DESC
    LIMIT 30");
    
    $topStmt->execute([$startDate]);
    $topSearches = $topStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search keywords analysis
    $keywordStmt = $pdo->prepare("SELECT 
        LOWER(search_query) as keyword,
        COUNT(*) as frequency
    FROM user_activity
    WHERE created_at >= ? AND action_type = 'search'
    GROUP BY LOWER(search_query)
    ORDER BY frequency DESC
    LIMIT 50");
    
    $keywordStmt->execute([$startDate]);
    $keywords = $keywordStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'period_days' => $days,
        'top_searches' => $topSearches,
        'keyword_frequency' => $keywords
    ]);
}

function getEngagementMetrics($pdo, $days) {
    $startDate = date('Y-m-d', time() - ($days * 86400));
    
    $stmt = $pdo->prepare("SELECT 
        AVG(duration_seconds) as avg_session_duration,
        AVG(scroll_depth) as avg_scroll_depth,
        COUNT(DISTINCT CASE WHEN duration_seconds > 60 THEN ip_address END) as users_over_1min,
        COUNT(DISTINCT CASE WHEN scroll_depth > 50 THEN ip_address END) as users_scrolled_deep,
        COUNT(DISTINCT ip_address) as total_users
    FROM user_activity
    WHERE created_at >= ?");
    
    $stmt->execute([$startDate]);
    $engagement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'period_days' => $days,
        'engagement' => [
            'avg_session_duration_seconds' => (int)($engagement['avg_session_duration'] ?? 0),
            'avg_scroll_depth_percent' => (int)($engagement['avg_scroll_depth'] ?? 0),
            'users_with_long_sessions' => (int)($engagement['users_over_1min'] ?? 0),
            'users_with_deep_scrolling' => (int)($engagement['users_scrolled_deep'] ?? 0),
            'total_active_users' => (int)($engagement['total_users'] ?? 0)
        ]
    ]);
}

function getGrowthAnalytics($pdo, $days) {
    $startDate = date('Y-m-d', time() - ($days * 86400));
    $halfDaysAgo = date('Y-m-d', time() - (($days / 2) * 86400));
    
    // First half stats
    $firstStmt = $pdo->prepare("SELECT 
        COUNT(DISTINCT page_id) as pages,
        COUNT(DISTINCT ip_address) as users,
        SUM(CASE WHEN action_type = 'view' THEN 1 ELSE 0 END) as views
    FROM user_activity
    WHERE created_at >= ? AND created_at < ?");
    
    $firstStmt->execute([$startDate, $halfDaysAgo]);
    $firstHalf = $firstStmt->fetch(PDO::FETCH_ASSOC);
    
    // Second half stats
    $secondStmt = $pdo->prepare("SELECT 
        COUNT(DISTINCT page_id) as pages,
        COUNT(DISTINCT ip_address) as users,
        SUM(CASE WHEN action_type = 'view' THEN 1 ELSE 0 END) as views
    FROM user_activity
    WHERE created_at >= ?");
    
    $secondStmt->execute([$halfDaysAgo]);
    $secondHalf = $secondStmt->fetch(PDO::FETCH_ASSOC);
    
    $calculateGrowth = function($first, $second) {
        if (empty($first) || $first == 0) return 0;
        return round((($second - $first) / $first) * 100, 2);
    };
    
    echo json_encode([
        'period_days' => $days,
        'growth' => [
            'pages_growth_percent' => $calculateGrowth($firstHalf['pages'], $secondHalf['pages']),
            'users_growth_percent' => $calculateGrowth($firstHalf['users'], $secondHalf['users']),
            'views_growth_percent' => $calculateGrowth($firstHalf['views'], $secondHalf['views']),
            'first_period' => $firstHalf,
            'second_period' => $secondHalf
        ]
    ]);
}