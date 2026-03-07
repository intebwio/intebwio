<?php
/**
 * Backup and Database Export API
 * Secure backup functionality with database export
 * ~180 lines
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

// Check authorization
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$validToken = $_ENV['BACKUP_API_KEY'] ?? 'dev-backup-key-xyz';

if (empty($authHeader) || $authHeader !== 'Bearer ' . $validToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'status';
$backupDir = __DIR__ . '/../backups';

// Ensure backup directory exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

try {
    switch ($action) {
        case 'create':
            createBackup();
            break;
        case 'list':
            listBackups();
            break;
        case 'download':
            downloadBackup($_GET['file'] ?? '');
            break;
        case 'delete':
            deleteBackup($_GET['file'] ?? '');
            break;
        case 'status':
            printStatus();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function createBackup() {
    global $backupDir;
    
    $timestamp = date('Y-m-d-His');
    $filename = 'intebwio-backup-' . $timestamp . '.sql';
    $filepath = $backupDir . '/' . $filename;
    
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s --single-transaction > %s 2>&1',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($filepath)) {
        $size = filesize($filepath);
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => $filename,
            'size_bytes' => $size,
            'size_mb' => round($size / 1024 / 1024, 2),
            'created_at' => date('Y-m-d H:i:s'),
            'path' => $filepath
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Backup failed',
            'details' => implode("\n", $output)
        ]);
    }
}

function listBackups() {
    global $backupDir;
    
    $backups = [];
    if (is_dir($backupDir)) {
        $files = array_diff(scandir($backupDir), ['.', '..']);
        
        foreach ($files as $file) {
            if (strpos($file, 'backup') !== false && strpos($file, '.sql') !== false) {
                $filepath = $backupDir . '/' . $file;
                $backups[] = [
                    'filename' => $file,
                    'size_bytes' => filesize($filepath),
                    'size_mb' => round(filesize($filepath) / 1024 / 1024, 2),
                    'created_at' => date('Y-m-d H:i:s', filemtime($filepath)),
                    'age_days' => (time() - filemtime($filepath)) / 86400
                ];
            }
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    echo json_encode([
        'total_backups' => count($backups),
        'backups' => $backups,
        'backup_directory' => $backupDir
    ]);
}

function downloadBackup($filename) {
    global $backupDir;
    
    // Prevent directory traversal
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename']);
        return;
    }
    
    $filepath = $backupDir . '/' . $filename;
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found']);
        return;
    }
    
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

function deleteBackup($filename) {
    global $backupDir;
    
    // Prevent directory traversal
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename']);
        return;
    }
    
    $filepath = $backupDir . '/' . $filename;
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found']);
        return;
    }
    
    if (unlink($filepath)) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup deleted successfully',
            'filename' => $filename
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete backup']);
    }
}

function printStatus() {
    global $backupDir;
    
    $backups = [];
    $totalSize = 0;
    
    if (is_dir($backupDir)) {
        $files = array_diff(scandir($backupDir), ['.', '..']);
        foreach ($files as $file) {
            if (strpos($file, '.sql') !== false) {
                $size = filesize($backupDir . '/' . $file);
                $totalSize += $size;
                $backups[] = $file;
            }
        }
    }
    
    echo json_encode([
        'status' => 'operational',
        'backup_directory' => $backupDir,
        'is_writable' => is_writable($backupDir),
        'total_backups' => count($backups),
        'total_size_mb' => round($totalSize / 1024 / 1024, 2),
        'last_backup' => !empty($backups) ? end($backups) : null
    ]);
}