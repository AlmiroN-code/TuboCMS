<?php

declare(strict_types=1);

namespace App\Storage\Adapter;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;

/**
 * Local Storage Adapter for storing files on local filesystem.
 * 
 * Implements existing local storage behavior as a storage adapter.
 * Validates: Requirements 2.1
 */
class LocalStorageAdapter extends AbstractStorageAdapter
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $publicUrl = '',
    ) {}

    /**
     * Upload (copy) a file to local storage.
     * 
     * Requirement 2.1: WHEN a video is processed THEN the System SHALL upload 
     * all generated files to the configured default storage
     */
    public function upload(string $localPath, string $remotePath): UploadResult
    {
        if (!file_exists($localPath)) {
            return UploadResult::failure("Local file not found: {$localPath}");
        }

        try {
            $fullPath = $this->getFullPath($remotePath);
            $directory = dirname($fullPath);
            
            // Create directory structure if needed
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    return UploadResult::failure("Failed to create directory: {$directory}");
                }
            }
            
            // Copy file to destination
            if (!copy($localPath, $fullPath)) {
                return UploadResult::failure("Failed to copy file to: {$fullPath}");
            }
            
            $fileSize = filesize($fullPath);
            
            return UploadResult::success(
                remotePath: $remotePath,
                url: $this->getUrl($remotePath),
                fileSize: $fileSize ?: null
            );
        } catch (\Throwable $e) {
            return UploadResult::failure($e->getMessage());
        }
    }


    /**
     * Download (copy) a file from local storage.
     */
    public function download(string $remotePath, string $localPath): bool
    {
        try {
            $fullPath = $this->getFullPath($remotePath);
            
            if (!file_exists($fullPath)) {
                return false;
            }
            
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }
            
            return copy($fullPath, $localPath);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delete a file from local storage.
     */
    public function delete(string $remotePath): bool
    {
        try {
            $fullPath = $this->getFullPath($remotePath);
            
            if (!file_exists($fullPath)) {
                return true; // File doesn't exist, consider it deleted
            }
            
            return unlink($fullPath);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a file exists in local storage.
     */
    public function exists(string $remotePath): bool
    {
        $fullPath = $this->getFullPath($remotePath);
        
        return file_exists($fullPath);
    }

    /**
     * Get URL for a file in local storage.
     */
    public function getUrl(string $remotePath): string
    {
        $path = ltrim($remotePath, '/');
        
        if (!empty($this->publicUrl)) {
            return rtrim($this->publicUrl, '/') . '/' . $path;
        }
        
        // Default to relative path from public directory
        return '/' . $path;
    }

    /**
     * Get signed URL with expiration for secure access.
     * For local storage, generates a URL with signature parameter.
     * 
     * Property 7: Signed URLs contain signature and expiration
     * For any signed URL generated with expiration time T, the URL SHALL contain 
     * a signature parameter and expire parameter with value T.
     * 
     * Validates: Requirements 3.4
     */
    public function getSignedUrl(string $remotePath, int $expiresIn = 3600): string
    {
        $expires = time() + $expiresIn;
        $signature = $this->generateSignature($remotePath, $expires);
        
        return $this->getUrl($remotePath) . '?' . http_build_query([
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }


    /**
     * Test connection to local storage (check if base path is accessible).
     */
    public function testConnection(): ConnectionTestResult
    {
        $startTime = microtime(true);
        
        try {
            // Check if base path exists
            if (!is_dir($this->basePath)) {
                return ConnectionTestResult::failure(
                    "Base path does not exist: {$this->basePath}"
                );
            }
            
            // Check if base path is readable
            if (!is_readable($this->basePath)) {
                return ConnectionTestResult::failure(
                    "Base path is not readable: {$this->basePath}"
                );
            }
            
            // Check if base path is writable
            if (!is_writable($this->basePath)) {
                return ConnectionTestResult::failure(
                    "Base path is not writable: {$this->basePath}"
                );
            }
            
            $latencyMs = (microtime(true) - $startTime) * 1000;
            
            return ConnectionTestResult::success(
                message: 'Local storage accessible',
                latencyMs: $latencyMs,
                serverInfo: 'Local filesystem'
            );
        } catch (\Throwable $e) {
            return ConnectionTestResult::failure($e->getMessage());
        }
    }

    /**
     * Get quota information for local storage.
     */
    public function getQuota(): ?StorageQuota
    {
        try {
            $totalSpace = disk_total_space($this->basePath);
            $freeSpace = disk_free_space($this->basePath);
            
            if ($totalSpace === false || $freeSpace === false) {
                return null;
            }
            
            $usedSpace = $totalSpace - $freeSpace;
            
            return new StorageQuota(
                usedBytes: (int) $usedSpace,
                totalBytes: (int) $totalSpace
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Create a directory in local storage.
     */
    public function createDirectory(string $path): bool
    {
        try {
            $fullPath = $this->getFullPath($path);
            
            if (is_dir($fullPath)) {
                return true;
            }
            
            return mkdir($fullPath, 0755, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get full path including base path.
     */
    private function getFullPath(string $path): string
    {
        $basePath = rtrim($this->basePath, '/\\');
        $path = ltrim($path, '/\\');
        
        return $basePath . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Generate signature for signed URLs.
     * 
     * Property 7: Signed URLs contain signature and expiration
     * For any signed URL generated with expiration time T, the URL SHALL contain 
     * a signature parameter and expire parameter with value T.
     */
    private function generateSignature(string $path, int $expires): string
    {
        // Use APP_SECRET for signing (consistent across all adapters)
        $secret = $_ENV['APP_SECRET'] ?? 'local-storage-secret';
        $data = $path . ':' . $expires;
        
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Get the base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the public URL prefix.
     */
    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }
}
