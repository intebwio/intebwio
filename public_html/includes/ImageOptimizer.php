<?php
/**
 * Image Optimization and Processing Service
 * Handle image optimization, caching, and serving
 * ~180 lines
 */

class ImageOptimizer {
    private $uploadDir = '/uploads';
    private $cacheDir = '/cache/images';
    private $maxWidth = 1920;
    private $maxHeight = 1080;
    private $quality = 85;
    
    public function __construct($uploadDir = null, $quality = 85) {
        if ($uploadDir) {
            $this->uploadDir = $uploadDir;
        }
        $this->quality = $quality;
        $this->ensureDirectories();
    }
    
    /**
     * Ensure directories exist
     */
    private function ensureDirectories() {
        @mkdir(__DIR__ . $this->uploadDir, 0755, true);
        @mkdir(__DIR__ . $this->cacheDir, 0755, true);
    }
    
    /**
     * Process and optimize uploaded image
     */
    public function processUploadedImage($file, $maxWidth = null, $maxHeight = null) {
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid upload');
        }
        
        $filename = $this->generateFilename($file['name']);
        $filepath = __DIR__ . $this->uploadDir . '/' . $filename;
        
        // Validate image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Invalid image file');
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5242880) {
            throw new Exception('File too large (max 5MB)');
        }
        
        // Optimize image
        $this->optimizeImage($file['tmp_name'], $filepath, $maxWidth, $maxHeight);
        
        return [
            'filename' => $filename,
            'path' => $this->uploadDir . '/' . $filename,
            'size' => filesize($filepath),
            'dimensions' => $imageInfo,
            'url' => APP_URL . $this->uploadDir . '/' . $filename
        ];
    }
    
    /**
     * Generate unique filename
     */
    private function generateFilename($originalName) {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $hash = hash('md5', time() . mt_rand());
        return substr($hash, 0, 8) . '.' . strtolower($ext);
    }
    
    /**
     * Optimize image file
     */
    private function optimizeImage($sourceFile, $destFile, $maxWidth = null, $maxHeight = null) {
        $maxWidth = $maxWidth ?? $this->maxWidth;
        $maxHeight = $maxHeight ?? $this->maxHeight;
        
        $imageInfo = getimagesize($sourceFile);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Calculate new dimensions while maintaining aspect ratio
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Load image based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourceFile);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourceFile);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($sourceFile);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($sourceFile);
                break;
            default:
                throw new Exception('Unsupported image type');
        }
        
        // Create resized image
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($resized, $destFile, $this->quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($resized, $destFile, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($resized, $destFile);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($resized, $destFile, $this->quality);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($resized);
        
        return true;
    }
    
    /**
     * Generate image thumbnail
     */
    public function generateThumbnail($imagePath, $thumbWidth = 200, $thumbHeight = 200) {
        $thumbFilename = 'thumb_' . basename($imagePath);
        $thumbPath = __DIR__ . $this->cacheDir . '/' . $thumbFilename;
        
        // Check if already cached
        if (file_exists($thumbPath)) {
            return [
                'url' => APP_URL . $this->cacheDir . '/' . $thumbFilename,
                'path' => $this->cacheDir . '/' . $thumbFilename,
                'cached' => true
            ];
        }
        
        $fullPath = __DIR__ . $imagePath;
        if (!file_exists($fullPath)) {
            throw new Exception('Image not found: ' . $imagePath);
        }
        
        $this->optimizeImage($fullPath, $thumbPath, $thumbWidth, $thumbHeight);
        
        return [
            'url' => APP_URL . $this->cacheDir . '/' . $thumbFilename,
            'path' => $this->cacheDir . '/' . $thumbFilename,
            'cached' => false
        ];
    }
    
    /**
     * Delete image and thumbnail
     */
    public function deleteImage($imagePath) {
        $fullPath = __DIR__ . $imagePath;
        $thumbPath = __DIR__ . $this->cacheDir . '/thumb_' . basename($imagePath);
        
        $deleted = false;
        if (file_exists($fullPath)) {
            unlink($fullPath);
            $deleted = true;
        }
        
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
        
        return $deleted;
    }
    
    /**
     * Get image statistics
     */
    public function getImageStats() {
        $uploadPath = __DIR__ . $this->uploadDir;
        $cachePath = __DIR__ . $this->cacheDir;
        
        $uploadSize = $this->getDirectorySize($uploadPath);
        $cacheSize = $this->getDirectorySize($cachePath);
        
        return [
            'upload_dir_size_mb' => round($uploadSize / 1024 / 1024, 2),
            'cache_dir_size_mb' => round($cacheSize / 1024 / 1024, 2),
            'total_size_mb' => round(($uploadSize + $cacheSize) / 1024 / 1024, 2),
            'optimization_quality' => $this->quality
        ];
    }
    
    /**
     * Get directory size recursively
     */
    private function getDirectorySize($dir) {
        $size = 0;
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = $dir . '/' . $file;
                if (is_file($path)) {
                    $size += filesize($path);
                } elseif (is_dir($path)) {
                    $size += $this->getDirectorySize($path);
                }
            }
        }
        return $size;
    }
    
    /**
     * Cleanup old cached images
     */
    public function cleanupCache($maxAgeDays = 30) {
        $cachePath = __DIR__ . $this->cacheDir;
        $minTime = time() - ($maxAgeDays * 86400);
        $deleted = 0;
        
        if (is_dir($cachePath)) {
            foreach (scandir($cachePath) as $file) {
                if ($file === '.' || $file === '..') continue;
                $filepath = $cachePath . '/' . $file;
                if (filemtime($filepath) < $minTime) {
                    unlink($filepath);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}