<?php
/**
 * Database Setup Script
 * Initializes database tables for Intebwio
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';

try {
    echo '<h2>Intebwio Database Setup</h2>';
    
    $db = new Database($pdo);
    
    // Initialize database tables
    $db->initializeTables();
    
    echo '<p style="color: green; font-weight: bold;">✓ Database tables initialized successfully!</p>';
    
    // Test connection
    $stmt = $pdo->query('SELECT 1');
    if ($stmt) {
        echo '<p>✓ Database connection verified</p>';
    }
    
    // Show database info
    $result = $pdo->query('SELECT COUNT(*) as count FROM pages');
    $pageCount = $result->fetch()['count'];
    
    echo '<h3>Database Status:</h3>';
    echo '<ul>';
    echo '<li>Database: ' . DB_NAME . '</li>';
    echo '<li>Host: ' . DB_HOST . '</li>';
    echo '<li>Total Pages: ' . $pageCount . '</li>';
    echo '</ul>';
    
    echo '<h3>Next Steps:</h3>';
    echo '<ol>';
    echo '<li>Visit the <a href="index.html">home page</a> to start searching</li>';
    echo '<li>Use the search bar to generate new pages</li>';
    echo '<li>Pages will be automatically cached in the database</li>';
    echo '</ol>';
    
} catch (Exception $e) {
    echo '<p style="color: red; font-weight: bold;">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    die();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Intebwio - Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2, h3 { color: #333; }
        ul, ol { line-height: 1.8; }
        code { background: #f0f0f0; padding: 2px 5px; }
    </style>
</head>
<body>
</body>
</html>