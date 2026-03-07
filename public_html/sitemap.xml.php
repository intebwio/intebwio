<?php
/**
 * Sitemap Generator for Intebwio
 * Dynamically generates XML sitemap from database pages
 * Created by: Yaroslav Ostapenko
 */

header('Content-Type: application/xml; charset=utf-8');

// Include database configuration
require_once __DIR__ . '/includes/config.php';

try {
    // Start XML output
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Add main pages
    $mainPages = [
        [
            'url' => 'https://intebwio.com/',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => '1.0'
        ],
        [
            'url' => 'https://intebwio.com/landing-page-generator.html',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.9'
        ],
        [
            'url' => 'https://intebwio.com/health-check.html',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.7'
        ]
    ];
    
    // Add main pages to sitemap
    foreach ($mainPages as $page) {
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($page['url']) . '</loc>' . "\n";
        echo '    <lastmod>' . $page['lastmod'] . '</lastmod>' . "\n";
        echo '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
        echo '    <priority>' . $page['priority'] . '</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    
    // Add generated pages from database
    if (isset($pdo)) {
        try {
            $query = "
                SELECT page_slug, updated_at 
                FROM pages 
                WHERE page_slug IS NOT NULL 
                AND page_slug != '' 
                ORDER BY updated_at DESC 
                LIMIT 50000
            ";
            
            $stmt = $pdo->query($query);
            $pages = $stmt->fetchAll();
            
            foreach ($pages as $page) {
                if (!empty($page['page_slug'])) {
                    $url = 'https://intebwio.com/?page=' . urlencode($page['page_slug']);
                    $lastmod = !empty($page['updated_at']) ? substr($page['updated_at'], 0, 10) : date('Y-m-d');
                    
                    echo '  <url>' . "\n";
                    echo '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
                    echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
                    echo '    <changefreq>weekly</changefreq>' . "\n";
                    echo '    <priority>0.8</priority>' . "\n";
                    echo '  </url>' . "\n";
                }
            }
        } catch (Exception $e) {
            error_log('Sitemap generation error: ' . $e->getMessage());
        }
    }
    
    echo '</urlset>' . "\n";
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<error>' . htmlspecialchars($e->getMessage()) . '</error>' . "\n";
}