<?php
/**
 * Intebwio - Page Display
 * Shows generated pages
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';

try {
    $pageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$pageId) {
        header('Location: /');
        exit;
    }
    
    $db = new Database($pdo);
    $page = $db->getPageById($pageId);
    
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
                }
                h1 {
                    font-size: 48px;
                    margin-bottom: 16px;
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
                <a href="/">← Back to Home</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Record view
    $db->recordActivity($pageId, null, 'view');
    $db->updateViewCount($pageId);
    
    // Output the HTML content directly
    echo $page['html_content'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}