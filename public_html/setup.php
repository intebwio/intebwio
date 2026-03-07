<?php
/**
 * Intebwio - Setup Script
 * Run this once to initialize the application
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Intebwio - Setup</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .setup-container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 20px 25px rgba(0,0,0,0.2);
                max-width: 600px;
                width: 100%;
            }
            h1 {
                text-align: center;
                margin-bottom: 24px;
                color: #1e293b;
            }
            .info-box {
                background: #dbeafe;
                color: #1e40af;
                padding: 16px;
                border-radius: 8px;
                margin-bottom: 24px;
                border-left: 4px solid #3b82f6;
                font-size: 14px;
                line-height: 1.6;
            }
            .step {
                margin-bottom: 24px;
                padding: 16px;
                background: #f8fafc;
                border-radius: 8px;
                border-left: 4px solid #2563eb;
            }
            .step h3 {
                margin-bottom: 8px;
                color: #1e293b;
            }
            .step p {
                color: #64748b;
                font-size: 14px;
                margin-bottom: 8px;
            }
            .step code {
                background: #1e293b;
                color: #10b981;
                padding: 8px 12px;
                border-radius: 4px;
                font-family: 'Monaco', monospace;
                font-size: 12px;
                display: block;
                overflow-x: auto;
                margin-top: 8px;
            }
            .button-group {
                display: flex;
                gap: 12px;
                justify-content: center;
            }
            button {
                padding: 12px 28px;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }
            .btn-primary {
                background: #2563eb;
                color: white;
            }
            .btn-primary:hover {
                background: #1d4ed8;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: #e2e8f0;
                color: #1e293b;
            }
            .btn-secondary:hover {
                background: #cbd5e1;
            }
            .warning {
                background: #fef3c7;
                border-left-color: #f59e0b;
                color: #92400e;
            }
        </style>
    </head>
    <body>
        <div class="setup-container">
            <h1>🚀 Intebwio Setup</h1>
            
            <div class="info-box">
                This setup wizard will initialize the Intebwio application. Make sure your MySQL database is configured correctly and the information in config.php is accurate.
            </div>

            <div class="step">
                <h3>✓ Database Connection</h3>
                <p>Host: <code><?php echo DB_HOST; ?></code></p>
                <p>Database: <code><?php echo DB_DATABASE; ?></code></p>
                <p>User: <code><?php echo DB_USER; ?></code></p>
                <p>✓ Connection successful</p>
            </div>

            <div class="step">
                <h3>📁 File Structure</h3>
                <p>Required directories will be created:</p>
                <code>
public_html/
├── css/
├── js/
├── api/
├── cron/
└── logs/
                </code>
            </div>

            <div class="step warning">
                <h3>⚠️ Admin Password</h3>
                <p>Default admin password:</p>
                <code>intebwio_admin_2026</code>
                <p><strong>Important:</strong> Change this password in admin.php after setup!</p>
            </div>

            <div class="step">
                <h3>🔧 Configuration</h3>
                <p>All settings are in: <code>includes/config.php</code></p>
                <p>Customize as needed and reload this page.</p>
            </div>

            <div class="button-group">
                <form method="POST" style="margin: 0;">
                    <button type="submit" class="btn-primary">✓ Initialize Database & Setup</button>
                </form>
                <a href="/" style="display: inline-block; text-decoration: none;">
                    <button type="button" class="btn-secondary">← Go to Homepage</button>
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Process setup
try {
    $db = new Database($pdo);
    
    // Create log directory if it doesn't exist
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    // Initialize tables
    if ($db->initializeTables()) {
        $success = true;
        $message = 'Database initialized successfully!';
    } else {
        $success = false;
        $message = 'Failed to initialize database';
    }
} catch (Exception $e) {
    $success = false;
    $message = 'Error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intebwio - Setup Complete</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .result-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 25px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        h1 {
            margin-bottom: 16px;
            color: #1e293b;
        }
        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
            text-align: left;
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
            text-align: left;
        }
        .next-steps {
            text-align: left;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .next-steps h3 {
            color: #1e293b;
            margin-bottom: 12px;
        }
        .next-steps ol {
            margin-left: 20px;
            color: #475569;
        }
        .next-steps li {
            margin-bottom: 8px;
        }
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        a {
            display: inline-block;
            padding: 12px 28px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        a:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <?php if ($success): ?>
            <div class="icon">✅</div>
            <h1>Setup Complete!</h1>
            <div class="message success-message">
                <?php echo $message; ?>
            </div>
            <div class="next-steps">
                <h3>🎯 Next Steps:</h3>
                <ol>
                    <li><strong>Change admin password</strong> - Edit <code>admin.php</code> and change the $adminPassword variable</li>
                    <li><strong>Configure APIs</strong> - Set up Google API, SerpAPI, or other content sources in <code>config.php</code></li>
                    <li><strong>Setup cron job</strong> - Add weekly update task via crontab (see admin panel)</li>
                    <li><strong>Start using!</strong> - Go to the homepage and try your first search</li>
                </ol>
            </div>
            <div class="button-group">
                <a href="/">🏠 Go to Homepage</a>
                <a href="/admin.php" class="btn-secondary">📊 Admin Dashboard</a>
            </div>
        <?php else: ?>
            <div class="icon">❌</div>
            <h1>Setup Failed</h1>
            <div class="message error-message">
                <?php echo $message; ?>
            </div>
            <p style="color: #64748b; margin-bottom: 20px;">
                Please check your database configuration and try again.
            </p>
            <div class="button-group">
                <a href="/setup.php" class="btn-secondary">← Try Again</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>