<?php

declare(strict_types=1);

namespace App\Storage\Adapter;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * SFTP Storage Adapter for uploading/downloading files via SFTP protocol.
 * 
 * Supports password and private key authentication.
 * Validates: Requirements 2.3, 5.3
 */
class SftpStorageAdapter extends AbstractStorageAdapter
{
    private ?SFTP $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly ?string $password,
        private readonly ?string $privateKey,
        private readonly ?string $privateKeyPassphrase,
        private readonly string $basePath,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Upload a file to SFTP storage.
     * 
     * Requirement 2.3: WHEN uploading to SFTP storage THEN the System SHALL 
     * create necessary directory structure and transfer files using SFTP protocol
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
                $success = $this->connection->put($fullRemotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);
                
                if (!$success) {
                    throw new \RuntimeException("Failed to upload file to SFTP: {$fullRemotePath}");
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
     * Download a file from SFTP storage.
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
                
                $success = $this->connection->get($fullRemotePath, $localPath);
                
                if (!$success) {
                    throw new \RuntimeException("Failed to download file from SFTP: {$fullRemotePath}");
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
     * Delete a file from SFTP storage.
     * 
     * Requirement 5.3: WHEN deleting from SFTP storage THEN the System SHALL 
     * remove the file using SFTP unlink command
     */
    public function delete(string $remotePath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath): bool {
                $this->connect();
                
                $fullRemotePath = $this->getFullPath($remotePath);
                $success = $this->connection->delete($fullRemotePath);
                
                if (!$success) {
                    throw new \RuntimeException("Failed to delete file from SFTP: {$fullRemotePath}");
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
     * Check if a file exists on SFTP storage.
     */
    public function exists(string $remotePath): bool
    {
        try {
            $this->connect();
            
            $fullRemotePath = $this->getFullPath($remotePath);
            
            return $this->connection->file_exists($fullRemotePath);
        } catch (\Throwable) {
            return false;
        } finally {
            $this->disconnect();
        }
    }


    /**
     * Get URL for a file. SFTP doesn't support direct URLs, returns proxy path.
     */
    public function getUrl(string $remotePath): string
    {
        // SFTP files need to be served through a proxy controller
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
     * Test connection to SFTP server.
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
            $serverInfo = $this->connection->getServerIdentification();
            
            return ConnectionTestResult::success(
                message: 'SFTP connection successful',
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
     * Get quota information from SFTP server.
     * 
     * Uses statvfs to get filesystem statistics if available.
     */
    public function getQuota(): ?StorageQuota
    {
        try {
            $this->connect();
            
            $stat = $this->connection->statvfs($this->basePath);
            
            if ($stat === false) {
                return null;
            }
            
            $blockSize = $stat['bsize'] ?? 1;
            $totalBlocks = $stat['blocks'] ?? 0;
            $freeBlocks = $stat['bfree'] ?? 0;
            
            $totalBytes = $totalBlocks * $blockSize;
            $freeBytes = $freeBlocks * $blockSize;
            $usedBytes = $totalBytes - $freeBytes;
            
            return new StorageQuota(
                usedBytes: (int) $usedBytes,
                totalBytes: (int) $totalBytes
            );
        } catch (\Throwable) {
            return null;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Create a directory on SFTP storage.
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
     * Connect to SFTP server.
     * 
     * Supports both password and private key authentication.
     * Requirement 1.3: WHEN an administrator creates a new SFTP storage THEN the System 
     * SHALL require host, port, username, authentication method (password or key), and base path fields
     * 
     * @throws \RuntimeException If connection fails
     */
    private function connect(): void
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            return;
        }

        $sftp = new SFTP($this->host, $this->port, $this->timeout);
        
        // Authenticate with private key or password
        if ($this->privateKey !== null) {
            $key = $this->loadPrivateKey();
            $authenticated = $sftp->login($this->username, $key);
        } elseif ($this->password !== null) {
            $authenticated = $sftp->login($this->username, $this->password);
        } else {
            throw new \RuntimeException('No authentication method provided (password or private key required)');
        }

        if (!$authenticated) {
            throw new \RuntimeException(
                \sprintf('SFTP authentication failed for user: %s@%s:%d', $this->username, $this->host, $this->port)
            );
        }

        $this->connection = $sftp;
    }

    /**
     * Load private key for authentication.
     * 
     * @throws \RuntimeException If key loading fails
     */
    private function loadPrivateKey(): mixed
    {
        try {
            if ($this->privateKeyPassphrase !== null) {
                return PublicKeyLoader::load($this->privateKey, $this->privateKeyPassphrase);
            }
            
            return PublicKeyLoader::load($this->privateKey);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to load private key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Disconnect from SFTP server.
     */
    private function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
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
     * Create directory recursively on SFTP server.
     */
    private function createDirectoryRecursive(string $path): bool
    {
        if ($this->connection === null) {
            return false;
        }

        // Check if directory already exists
        if ($this->connection->is_dir($path)) {
            return true;
        }

        // Create directories recursively using mkdir with recursive flag
        return $this->connection->mkdir($path, -1, true);
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
        $secret = $_ENV['APP_SECRET'] ?? $this->password ?? $this->privateKey ?? 'default-secret';
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
     * Get the username.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Check if using private key authentication.
     */
    public function isKeyAuthentication(): bool
    {
        return $this->privateKey !== null;
    }

    /**
     * Check if using password authentication.
     */
    public function isPasswordAuthentication(): bool
    {
        return $this->password !== null && $this->privateKey === null;
    }
}
