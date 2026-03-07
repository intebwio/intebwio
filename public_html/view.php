<?php
/**
 * Intebwio - Display Generated Pages by Query
 * Shows pages accessed via /?q=topic&slug=slug-name
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';

try {
    // Get query parameters
    $query = isset($_GET['q']) ? trim($_GET['q']) : null;
    $slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
    
    // Handle direct slug access: /view/slug-name or /s/slug-name
    if (!$slug && isset($_GET['s'])) {
        $slug = trim($_GET['s']);
    }
    
    if (!$slug && !$query) {
        // If no parameters, redirect to home
        header('Location: /');
        exit;
    }
    
    $db = new Database($pdo);
    $page = null;
    
    // Fetch page by slug if provided (most reliable)
    if ($slug) {
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'active'");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();
    }
    
    // If not found by slug, try by query (fallback)
    if (!$page && $query) {
        $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $query));
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE query = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$normalizedQuery]);
        $page = $stmt->fetch();
    }
    
    if (!$page) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Page Not Found - Intebwio</title>
            <style>
                * {
                    margin: 0; padding: 0; box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                }
                .error-container {
                    text-align: center;
                    background: white;
                    padding: 60px 40px;
                    border-radius: 12px;
                    box-shadow: 0 20px 25px rgba(0,0,0,0.1);
                    max-width: 500px;
                }
                h1 {
                    font-size: 48px;
                    margin-bottom: 16px;
                    color: #2563eb;
                }
                p {
                    font-size: 18px;
                    color: #64748b;
                    margin-bottom: 24px;
                }
                a {
                    display: inline-block;
                    padding: 12px 28px;
                    background: #2563eb;
                    color: white;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    transition: background 0.3s;
                }
                a:hover {
                    background: #1d4ed8;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>404</h1>
                <p>Page not found</p>
                <p style="font-size: 14px; color: #94a3b8;">The page you're looking for doesn't exist or has been deleted.</p>
                <a href="/">← Generate New Page</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Record view
    try {
        $viewStmt = $pdo->prepare("UPDATE pages SET view_count = view_count + 1 WHERE id = ?");
        $viewStmt->execute([$page['id']]);
        
        // Record activity
        $actStmt = $pdo->prepare("
            INSERT INTO activity (page_id, user_ip, user_agent, action) 
            VALUES (?, ?, ?, 'view')
        ");
        $actStmt->execute([
            $page['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Error recording page view: " . $e->getMessage());
    }
    
    // Output the HTML content directly
    // Remove any duplicate html/head/body tags if they exist
    $html = $page['html_content'];
    
    // If the content doesn't have proper HTML structure, add it
    if (strpos($html, '<!DOCTYPE') === false) {
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>' . $html . '</body></html>';
    }
    
    echo $html;
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . htmlspecialchars($e->getMessage());
}