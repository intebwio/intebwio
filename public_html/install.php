#!/usr/bin/env php
<?php
/**
 * Intebwio Installation Script
 * Complete setup and deployment tool
 * ~250 lines
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class IntebwioInstaller {
    private $configPath;
    private $dbHost;
    private $dbName;
    private $dbUser;
    private $dbPass;
    private $pdo;
    
    public function __construct() {
        $this->configPath = __DIR__ . '/public_html/includes/config.php';
        $this->displayWelcome();
    }
    
    private function displayWelcome() {
        echo "\n";
        echo "╔════════════════════════════════════════╗\n";
        echo "║   INTEBWIO - Installation Wizard       ║\n";
        echo "║   AI-Powered Landing Page Generator   ║\n";
        echo "╚════════════════════════════════════════╝\n\n";
    }
    
    public function run() {
        try {
            echo "[1/6] Checking system requirements...\n";
            $this->checkRequirements();
            
            echo "[2/6] Configuring database connection...\n";
            $this->configureDatabaseConnection();
            
            echo "[3/6] Testing database connection...\n";
            $this->testDatabaseConnection();
            
            echo "[4/6] Running database migrations...\n";
            $this->runMigrations();
            
            echo "[5/6] Creating directories...\n";
            $this->createDirectories();
            
            echo "[6/6] Final configuration...\n";
            $this->finalizeSetup();
            
            echo "\n✅ Installation completed successfully!\n\n";
            echo "Next steps:\n";
            echo "  1. Configure API keys in: " . $this->configPath . "\n";
            echo "  2. Access the application at: http://localhost/index-ai.html\n";
            echo "  3. Check health status: curl http://localhost/api/health.php\n\n";
            
        } catch (Exception $e) {
            echo "❌ Installation failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function checkRequirements() {
        $requirements = [
            'PHP Version' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'JSON Extension' => extension_loaded('json'),
            'cURL Extension' => extension_loaded('curl'),
            'OpenSSL Extension' => extension_loaded('openssl')
        ];
        
        $allMet = true;
        foreach ($requirements as $name => $met) {
            $status = $met ? '✅' : '❌';
            echo "  $status $name\n";
            if (!$met) $allMet = false;
        }
        
        if (!$allMet) {
            throw new Exception("Some system requirements are not met");
        }
    }
    
    private function configureDatabaseConnection() {
        echo "  Database Host [127.0.0.1]: ";
        $this->dbHost = trim(fgets(STDIN)) ?: '127.0.0.1';
        
        echo "  Database Name [u757840095_Intebwio]: ";
        $this->dbName = trim(fgets(STDIN)) ?: 'u757840095_Intebwio';
        
        echo "  Database User [u757840095_Yaroslav]: ";
        $this->dbUser = trim(fgets(STDIN)) ?: 'u757840095_Yaroslav';
        
        echo "  Database Password: ";
        system('stty -echo');
        $this->dbPass = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
        
        $this->generateConfigFile();
    }
    
    private function generateConfigFile() {
        $configContent = <<<'PHP'
<?php
// Database Configuration
define('DB_HOST', '%s');
define('DB_NAME', '%s');
define('DB_USER', '%s');
define('DB_PASS', '%s');

// AI Provider Configuration
define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'openai');
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_MODEL', 'gpt-4');
define('AI_TIMEOUT', 30);

// Application Settings
define('APP_URL', 'http://localhost');
define('APP_NAME', 'Intebwio');
define('PAGES_PER_REQUEST', 1);

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_DRIVER', 'apcu');
define('CACHE_TTL', 604800);

// Update Configuration
define('AUTO_UPDATE_ENABLED', true);
define('AUTO_UPDATE_INTERVAL', 604800);
define('SIMILARITY_THRESHOLD', 0.75);

// Features
define('ENABLE_COMMENTS', true);
define('ENABLE_ANALYTICS', true);
define('ENABLE_NOTIFICATIONS', false);

// Security
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600);

// Webhook URLs (optional)
define('SLACK_WEBHOOK', getenv('SLACK_WEBHOOK') ?: '');
define('EMAIL_NOTIFICATIONS', getenv('EMAIL_NOTIFICATIONS') ?: '');

// Logging
define('LOG_DIR', __DIR__ . '/../logs');
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');
?>
PHP;
        
        $config = sprintf(
            $configContent,
            $this->dbHost,
            $this->dbName,
            $this->dbUser,
            $this->dbPass
        );
        
        file_put_contents($this->configPath, $config);
        echo "  ✅ Configuration file created\n";
    }
    
    private function testDatabaseConnection() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->dbHost}",
                $this->dbUser,
                $this->dbPass
            );
            
            // Create database if not exists
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Switch to database
            $this->pdo->exec("USE `{$this->dbName}`");
            
            echo "  ✅ Database connection established\n";
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function runMigrations() {
        require_once __DIR__ . '/public_html/includes/DatabaseMigration.php';
        
        try {
            $migration = new DatabaseMigration($this->pdo);
            $results = $migration->runMigrations();
            $migration->createIndexes();
            echo "  ✅ Database migrations completed\n";
        } catch (Exception $e) {
            throw new Exception("Migration failed: " . $e->getMessage());
        }
    }
    
    private function createDirectories() {
        $directories = [
            'public_html/uploads',
            'public_html/logs',
            'public_html/backups',
            'public_html/cache',
            'public_html/tmp'
        ];
        
        foreach ($directories as $dir) {
            $fullPath = __DIR__ . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                echo "  ✅ Created: $dir\n";
            }
        }
    }
    
    private function finalizeSetup() {
        // Create .env file template
        $envTemplate = <<<'ENV'
# AI API Configuration
AI_PROVIDER=openai
AI_API_KEY=your-api-key-here
AI_MODEL=gpt-4

# Optional integrations
SLACK_WEBHOOK=
EMAIL_NOTIFICATIONS=

# Debug mode
DEBUG_MODE=false
ENV;
        
        $envPath = __DIR__ . '/.env.example';
        file_put_contents($envPath, $envTemplate);
        echo "  ✅ Created .env.example template\n";
        
        // Set permissions
        chmod($this->configPath, 0644);
        chmod(__DIR__ . '/public_html/logs', 0755);
        chmod(__DIR__ . '/public_html/uploads', 0755);
        
        echo "  ✅ Permissions configured\n";
    }
}

// Run installer
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

$installer = new IntebwioInstaller();
$installer->run();