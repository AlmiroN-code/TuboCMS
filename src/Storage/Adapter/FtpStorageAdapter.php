<?php

declare(strict_types=1);

namespace App\Storage\Adapter;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;

/**
 * FTP Storage Adapter for uploading/downloading files via FTP protocol.
 * 
 * Supports passive mode and SSL/TLS (FTPS).
 * Validates: Requirements 2.2, 5.2
 */
class FtpStorageAdapter extends AbstractStorageAdapter
{
    /** @var \FTP\Connection|null */
    private mixed $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $basePath,
        private readonly bool $passive = true,
        private readonly bool $ssl = false,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Upload a file to FTP storage.
     * 
     * Requirement 2.2: WHEN uploading to FTP storage THEN the System SHALL 
     * create necessary directory structure and transfer files using FTP protocol
     */
    public function upload(string $localPath, string $remotePath): UploadResult
    {
        if (!file_exists($localPath)) {
            return UploadResult::failure("Local file not found: {$localPath}");
        }

        try {
            return $this->executeWithRetry(function () use ($localPath, $remotePath): UploadResult {
                $this->connect();
                
                $fullRemotePath = $this->getFullPath($remotePath);
                $remoteDir = dirname($fullRemotePath);
                
                // Create directory structure if needed
                $this->createDirectoryRecursive($remoteDir);

                
                $fileSize = filesize($localPath);
                $success = @ftp_put($this->connection, $fullRemotePath, $localPath, FTP_BINARY);
                
                if (!$success) {
                    throw new \RuntimeException("Failed to upload file to FTP: {$fullRemotePath}");
                }
                
                return UploadResult::success(
                    remotePath: $remotePath,
                    url: $this->getUrl($remotePath),
                    fileSize: $fileSize ?: null
                );
            }, 'upload');
        } catch (\Throwable $e) {
            return UploadResult::failure($e->getMessage());
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Download a file from FTP storage.
     */
    public function download(string $remotePath, string $localPath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath, $localPath): bool {
                $this->connect();
                
                $fullRemotePath = $this->getFullPath($remotePath);
                $localDir = dirname($localPath);
                
                if (!is_dir($localDir)) {
                    mkdir($localDir, 0755, true);
                }
                
                $success = @ftp_get($this->connection, $localPath, $fullRemotePath, FTP_BINARY);
                
                if (!$success) {
                    throw new \RuntimeException("Failed to download file from FTP: {$fullRemotePath}");
                }
                
                return true;
            }, 'download');
        } catch (\Throwable) {
            return false;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Delete a file from FTP storage.
     * 
     * Requirement 5.2: WHEN deleting from FTP storage THEN the System SHALL 
     * remove the file using FTP DELETE command
     */
    public function delete(string $remotePath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath): bool {
                $this->connect();
                
                $fullRemotePath = $this->getFullPath($remotePath);
                $success = @ftp_delete($this->connection, $fullRemotePath);
                
                if (!$success) {
                    throw new \RuntimeException("Failed to delete file from FTP: {$fullRemotePath}");
                }
                
                return true;
            }, 'delete');
        } catch (\Throwable) {
            return false;
        } finally {
            $this->disconnect();
        }
    }


    /**
     * Check if a file exists on FTP storage.
     */
    public function exists(string $remotePath): bool
    {
        try {
            $this->connect();
            
            $fullRemotePath = $this->getFullPath($remotePath);
            $size = @ftp_size($this->connection, $fullRemotePath);
            
            return $size !== -1;
        } catch (\Throwable) {
            return false;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Get URL for a file. FTP doesn't support direct URLs, returns proxy path.
     */
    public function getUrl(string $remotePath): string
    {
        // FTP files need to be served through a proxy controller
        return '/storage/proxy/' . ltrim($remotePath, '/');
    }

    /**
     * Get signed URL with expiration for secure access.
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
     * Test connection to FTP server.
     * 
     * Requirement 1.6: WHEN an administrator tests storage connection THEN the System 
     * SHALL attempt to connect and report success or detailed error message
     */
    public function testConnection(): ConnectionTestResult
    {
        $startTime = microtime(true);
        
        try {
            $this->connect();
            
            $latencyMs = (microtime(true) - $startTime) * 1000;
            $serverInfo = @ftp_systype($this->connection);
            
            return ConnectionTestResult::success(
                message: 'FTP connection successful',
                latencyMs: $latencyMs,
                serverInfo: $serverInfo ?: null
            );
        } catch (\Throwable $e) {
            return ConnectionTestResult::failure($e->getMessage());
        } finally {
            $this->disconnect();
        }
    }


    /**
     * Get quota information from FTP server.
     * Note: Standard FTP doesn't support quota queries, returns null.
     */
    public function getQuota(): ?StorageQuota
    {
        // Standard FTP protocol doesn't support quota information
        // Some servers support SITE QUOTA command but it's not standardized
        return null;
    }

    /**
     * Create a directory on FTP storage.
     */
    public function createDirectory(string $path): bool
    {
        try {
            $this->connect();
            
            $fullPath = $this->getFullPath($path);
            
            return $this->createDirectoryRecursive($fullPath);
        } catch (\Throwable) {
            return false;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Connect to FTP server.
     * 
     * @throws \RuntimeException If connection fails
     */
    private function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        // Use SSL/TLS if enabled
        if ($this->ssl) {
            $connection = @ftp_ssl_connect($this->host, $this->port, $this->timeout);
        } else {
            $connection = @ftp_connect($this->host, $this->port, $this->timeout);
        }

        if ($connection === false) {
            throw new \RuntimeException(
                \sprintf('Failed to connect to FTP server: %s:%d', $this->host, $this->port)
            );
        }

        $this->connection = $connection;

        // Login
        $loginResult = @ftp_login($this->connection, $this->username, $this->password);
        
        if (!$loginResult) {
            $this->disconnect();
            throw new \RuntimeException(
                \sprintf('FTP login failed for user: %s', $this->username)
            );
        }

        // Enable passive mode if configured
        if ($this->passive) {
            if (!@ftp_pasv($this->connection, true)) {
                $this->logger?->warning('Failed to enable FTP passive mode');
            }
        }
    }


    /**
     * Disconnect from FTP server.
     */
    private function disconnect(): void
    {
        if ($this->connection !== null) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Get full path including base path.
     */
    private function getFullPath(string $path): string
    {
        $basePath = rtrim($this->basePath, '/');
        $path = ltrim($path, '/');
        
        return $basePath . '/' . $path;
    }

    /**
     * Create directory recursively on FTP server.
     */
    private function createDirectoryRecursive(string $path): bool
    {
        if ($this->connection === null) {
            return false;
        }

        // Check if directory already exists
        $originalDir = @ftp_pwd($this->connection);
        if (@ftp_chdir($this->connection, $path)) {
            @ftp_chdir($this->connection, $originalDir);
            return true;
        }

        // Create directories recursively
        $parts = explode('/', trim($path, '/'));
        $currentPath = '';
        
        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }
            
            $currentPath .= '/' . $part;
            
            // Try to change to directory, if fails - create it
            if (!@ftp_chdir($this->connection, $currentPath)) {
                if (!@ftp_mkdir($this->connection, $currentPath)) {
                    // Directory might have been created by another process
                    if (!@ftp_chdir($this->connection, $currentPath)) {
                        return false;
                    }
                }
            }
        }

        // Return to original directory
        @ftp_chdir($this->connection, $originalDir ?: '/');
        
        return true;
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
        $secret = $_ENV['APP_SECRET'] ?? $this->password;
        $data = $path . ':' . $expires;
        
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Get the host.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Get the port.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get the base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Check if SSL is enabled.
     */
    public function isSsl(): bool
    {
        return $this->ssl;
    }

    /**
     * Check if passive mode is enabled.
     */
    public function isPassive(): bool
    {
        return $this->passive;
    }
}
