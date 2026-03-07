<?php
/**
 * Intebwio - Admin Panel
 * Dashboard for monitoring and managing the application
 */

session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';

// Simple auth (in production, use proper authentication)
$adminPassword = 'intebwio_admin_2026';

if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['password']) && $_POST['password'] === $adminPassword) {
            $_SESSION['admin_authenticated'] = true;
        } else {
            $error = 'Invalid password';
        }
    }
    
    if (!isset($_SESSION['admin_authenticated'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Intebwio Admin - Login</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                }
                .login-container {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 20px 25px rgba(0,0,0,0.2);
                    width: 100%;
                    max-width: 400px;
                }
                h1 {
                    text-align: center;
                    margin-bottom: 30px;
                    color: #1e293b;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 600;
                    color: #1e293b;
                }
                input {
                    width: 100%;
                    padding: 12px;
                    border: 2px solid #e2e8f0;
                    border-radius: 8px;
                    font-size: 16px;
                    box-sizing: border-box;
                }
                input:focus {
                    outline: none;
                    border-color: #2563eb;
                }
                button {
                    width: 100%;
                    padding: 12px;
                    background: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.3s;
                }
                button:hover {
                    background: #1d4ed8;
                }
                .error {
                    color: #ef4444;
                    margin-bottom: 20px;
                    padding: 12px;
                    background: #fee2e2;
                    border-radius: 8px;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <h1>Intebwio Admin</h1>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="password">Admin Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Admin is authenticated - show dashboard
try {
    $db = new Database($pdo);
    $stats = $db->getStatistics();
    
    // Handle manual update trigger
    $updateResult = null;
    if (isset($_POST['run_update'])) {
        require_once __DIR__ . '/cron/update.php';
        $manager = new UpdateManager($pdo);
        $updateResult = $manager->runUpdates();
    }
    
    // Handle database initialization
    if (isset($_POST['init_db'])) {
        $init = $db->initializeTables();
        $initResult = $init ? 'Database initialized successfully' : 'Database initialization failed';
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intebwio Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .header {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        button {
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        button:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(37, 99, 235, 0.2);
        }

        button:active {
            transform: translateY(0);
        }

        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
        }

        .info-box {
            background: #dbeafe;
            color: #1e40af;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background: #f1f5f9;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:hover {
            background: #f8fafc;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Intebwio Admin Dashboard</h1>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($updateResult)): ?>
            <div class="<?php echo $updateResult['success'] ? 'success' : 'error-box'; ?>">
                <?php echo htmlspecialchars($updateResult['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($initResult)): ?>
            <div class="success">
                <?php echo htmlspecialchars($initResult); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_pages'] ?? 0; ?></div>
                <div class="stat-label">Total Pages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
                <div class="stat-label">Total Views</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['updated_this_week'] ?? 0; ?></div>
                <div class="stat-label">Updated This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo round(($stats['total_pages'] > 0 ? $stats['updated_this_week'] / $stats['total_pages'] * 100 : 0)); ?>%</div>
                <div class="stat-label">Update Rate</div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="section">
            <h2>🎮 Control Panel</h2>
            
            <div class="info-box">
                💡 Use the buttons below to manage your Intebwio instance. Updates scan all pages and refresh content from sources. Initialize DB only on first setup.
            </div>

            <div class="button-group">
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="run_update">🔄 Run Weekly Update Now</button>
                </form>
                
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="init_db" onclick="return confirm('This will create/reinitialize database tables. Continue?');">🔧 Initialize Database</button>
                </form>
                
                <a href="/" style="display: inline-block; background: #10b981; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">🏠 Go to Website</a>
            </div>
        </div>

        <!-- System Information -->
        <div class="section">
            <h2>ℹ️ System Information</h2>
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Intebwio Version</td>
                    <td><?php echo APP_VERSION; ?></td>
                </tr>
                <tr>
                    <td>Database</td>
                    <td><?php echo DB_DATABASE; ?> @ <?php echo DB_HOST; ?></td>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <td>Server</td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                </tr>
                <tr>
                    <td>Current Time</td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
                <tr>
                    <td>Auto-Update Interval</td>
                    <td>7 days (<?php echo UPDATE_INTERVAL / 86400; ?> days)</td>
                </tr>
                <tr>
                    <td>Similarity Threshold</td>
                    <td><?php echo round(SIMILARITY_THRESHOLD * 100); ?>%</td>
                </tr>
            </table>
        </div>

        <!-- Cron Setup -->
        <div class="section">
            <h2>⏰ Automated Updates Setup</h2>
            <div class="info-box">
                To enable automatic weekly updates, add this to your crontab:
            </div>
            <pre style="background: #1e293b; color: #10b981; padding: 16px; border-radius: 8px; overflow-x: auto;">0 0 * * 0 /usr/bin/php /path/to/public_html/cron/update.php</pre>
            <p style="margin-top: 12px; color: #64748b;">This runs every Sunday at midnight. Replace the path with your actual installation path.</p>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2026 Intebwio. Admin Dashboard.</p>
    </div>

    <?php
    // Handle logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
</body>
</html>