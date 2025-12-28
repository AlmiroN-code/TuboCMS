<?php

declare(strict_types=1);

namespace App\Storage\Adapter;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * HTTP Storage Adapter for uploading/downloading files via HTTP/HTTPS API.
 * 
 * Supports configurable endpoints and authentication headers.
 * Validates: Requirements 2.4, 5.4
 */
class HttpStorageAdapter extends AbstractStorageAdapter
{
    private const DEFAULT_TIMEOUT = 60;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $uploadEndpoint,
        private readonly string $deleteEndpoint,
        private readonly string $authToken,
        private readonly string $authHeader = 'Authorization',
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
        private readonly ?string $downloadEndpoint = null,
        private readonly ?string $existsEndpoint = null,
        private readonly ?string $quotaEndpoint = null,
    ) {}

    /**
     * Upload a file to HTTP storage.
     * 
     * Requirement 2.4: WHEN uploading to Remote Server THEN the System SHALL 
     * send files via HTTP POST/PUT requests to the configured endpoint
     */
    public function upload(string $localPath, string $remotePath): UploadResult
    {
        if (!file_exists($localPath)) {
            return UploadResult::failure("Local file not found: {$localPath}");
        }

        try {
            return $this->executeWithRetry(function () use ($localPath, $remotePath): UploadResult {
                $fileSize = filesize($localPath);
                $fileHandle = fopen($localPath, 'r');
                
                if ($fileHandle === false) {
                    throw new \RuntimeException("Cannot open file for reading: {$localPath}");
                }

                try {
                    $response = $this->httpClient->request('POST', $this->buildUrl($this->uploadEndpoint), [
                        'headers' => $this->getAuthHeaders(),
                        'timeout' => $this->timeout,
                        'body' => [
                            'file' => $fileHandle,
                            'path' => $remotePath,
                        ],
                    ]);

                    $statusCode = $response->getStatusCode();
                    
                    if ($statusCode >= 200 && $statusCode < 300) {
                        return UploadResult::success(
                            remotePath: $remotePath,
                            url: $this->getUrl($remotePath),
                            fileSize: $fileSize ?: null
                        );
                    }

                    $content = $response->getContent(false);
                    throw new \RuntimeException("HTTP upload failed with status {$statusCode}: {$content}");
                } finally {
                    fclose($fileHandle);
                }
            }, 'upload');
        } catch (\Throwable $e) {
            return UploadResult::failure($e->getMessage());
        }
    }


    /**
     * Download a file from HTTP storage.
     */
    public function download(string $remotePath, string $localPath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath, $localPath): bool {
                $endpoint = $this->downloadEndpoint ?? $this->baseUrl;
                $url = rtrim($endpoint, '/') . '/' . ltrim($remotePath, '/');
                
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => $this->getAuthHeaders(),
                    'timeout' => $this->timeout,
                ]);

                $statusCode = $response->getStatusCode();
                
                if ($statusCode < 200 || $statusCode >= 300) {
                    throw new \RuntimeException("HTTP download failed with status {$statusCode}");
                }

                $localDir = dirname($localPath);
                if (!is_dir($localDir)) {
                    mkdir($localDir, 0755, true);
                }

                $content = $response->getContent();
                $bytesWritten = file_put_contents($localPath, $content);
                
                if ($bytesWritten === false) {
                    throw new \RuntimeException("Failed to write file: {$localPath}");
                }

                return true;
            }, 'download');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delete a file from HTTP storage.
     * 
     * Requirement 5.4: WHEN deleting from Remote Server THEN the System SHALL 
     * send HTTP DELETE request to the configured endpoint
     */
    public function delete(string $remotePath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath): bool {
                $url = $this->buildUrl($this->deleteEndpoint);
                
                $response = $this->httpClient->request('DELETE', $url, [
                    'headers' => $this->getAuthHeaders(),
                    'timeout' => $this->timeout,
                    'query' => [
                        'path' => $remotePath,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                
                if ($statusCode >= 200 && $statusCode < 300) {
                    return true;
                }

                // 404 means file doesn't exist, consider it deleted
                if ($statusCode === 404) {
                    return true;
                }

                throw new \RuntimeException("HTTP delete failed with status {$statusCode}");
            }, 'delete');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a file exists on HTTP storage.
     */
    public function exists(string $remotePath): bool
    {
        try {
            $endpoint = $this->existsEndpoint ?? $this->baseUrl;
            $url = rtrim($endpoint, '/') . '/' . ltrim($remotePath, '/');
            
            $response = $this->httpClient->request('HEAD', $url, [
                'headers' => $this->getAuthHeaders(),
                'timeout' => $this->timeout,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get URL for a file. Returns direct URL to the remote file.
     * 
     * Requirement 3.3: WHEN video is stored on Remote Server THEN the System 
     * SHALL return the direct URL to the remote file
     */
    public function getUrl(string $remotePath): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($remotePath, '/');
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
     * Test connection to HTTP server.
     * 
     * Requirement 1.6: WHEN an administrator tests storage connection THEN the System 
     * SHALL attempt to connect and report success or detailed error message
     */
    public function testConnection(): ConnectionTestResult
    {
        $startTime = microtime(true);
        
        try {
            $response = $this->httpClient->request('HEAD', $this->baseUrl, [
                'headers' => $this->getAuthHeaders(),
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $latencyMs = (microtime(true) - $startTime) * 1000;
            
            if ($statusCode >= 200 && $statusCode < 400) {
                $serverInfo = $response->getHeaders(false)['server'][0] ?? null;
                
                return ConnectionTestResult::success(
                    message: 'HTTP connection successful',
                    latencyMs: $latencyMs,
                    serverInfo: $serverInfo
                );
            }

            return ConnectionTestResult::failure(
                "HTTP server returned status code: {$statusCode}"
            );
        } catch (TransportExceptionInterface $e) {
            return ConnectionTestResult::failure(
                "Connection failed: {$e->getMessage()}"
            );
        } catch (HttpExceptionInterface $e) {
            return ConnectionTestResult::failure(
                "HTTP error: {$e->getMessage()}"
            );
        } catch (\Throwable $e) {
            return ConnectionTestResult::failure($e->getMessage());
        }
    }

    /**
     * Get quota information from HTTP server.
     */
    public function getQuota(): ?StorageQuota
    {
        if ($this->quotaEndpoint === null) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $this->buildUrl($this->quotaEndpoint), [
                'headers' => $this->getAuthHeaders(),
                'timeout' => $this->timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode($response->getContent(), true);
            
            if (!isset($data['used'])) {
                return null;
            }

            return new StorageQuota(
                usedBytes: (int) $data['used'],
                totalBytes: isset($data['total']) ? (int) $data['total'] : null
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Create a directory on HTTP storage.
     * Note: Most HTTP storage APIs don't require explicit directory creation.
     */
    public function createDirectory(string $path): bool
    {
        // HTTP storage typically handles directory creation automatically during upload
        return true;
    }

    /**
     * Build full URL from endpoint.
     */
    private function buildUrl(string $endpoint): string
    {
        // If endpoint is already a full URL, return it
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * Get authentication headers.
     */
    private function getAuthHeaders(): array
    {
        $headers = [];
        
        if (!empty($this->authToken)) {
            $headers[$this->authHeader] = $this->authToken;
        }

        return $headers;
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
        $secret = $_ENV['APP_SECRET'] ?? $this->authToken;
        $data = $path . ':' . $expires;
        
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Get the base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the upload endpoint.
     */
    public function getUploadEndpoint(): string
    {
        return $this->uploadEndpoint;
    }

    /**
     * Get the delete endpoint.
     */
    public function getDeleteEndpoint(): string
    {
        return $this->deleteEndpoint;
    }

    /**
     * Get the auth header name.
     */
    public function getAuthHeader(): string
    {
        return $this->authHeader;
    }

    /**
     * Get the timeout.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
