<?php

declare(strict_types=1);

namespace App\Storage;

use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;

/**
 * Interface for storage adapters that handle file operations on different storage backends.
 * 
 * Implementations include FTP, SFTP, HTTP, and local filesystem adapters.
 */
interface StorageAdapterInterface
{
    /**
     * Upload a file from local path to remote storage.
     *
     * @param string $localPath Path to the local file
     * @param string $remotePath Destination path on remote storage
     * @return UploadResult Result of the upload operation
     */
    public function upload(string $localPath, string $remotePath): UploadResult;

    /**
     * Download a file from remote storage to local path.
     *
     * @param string $remotePath Path on remote storage
     * @param string $localPath Destination path on local filesystem
     * @return bool True if download succeeded
     */
    public function download(string $remotePath, string $localPath): bool;

    /**
     * Delete a file from remote storage.
     *
     * @param string $remotePath Path to the file on remote storage
     * @return bool True if deletion succeeded
     */
    public function delete(string $remotePath): bool;

    /**
     * Check if a file exists on remote storage.
     *
     * @param string $remotePath Path to check on remote storage
     * @return bool True if file exists
     */
    public function exists(string $remotePath): bool;

    /**
     * Get the public URL for a file.
     *
     * @param string $remotePath Path to the file on remote storage
     * @return string Public URL to access the file
     */
    public function getUrl(string $remotePath): string;

    /**
     * Get a signed URL with expiration for secure file access.
     *
     * @param string $remotePath Path to the file on remote storage
     * @param int $expiresIn Expiration time in seconds (default: 3600)
     * @return string Signed URL with expiration
     */
    public function getSignedUrl(string $remotePath, int $expiresIn = 3600): string;

    /**
     * Test the connection to the storage backend.
     *
     * @return ConnectionTestResult Result containing success status and error message if failed
     */
    public function testConnection(): ConnectionTestResult;

    /**
     * Get quota information for the storage.
     *
     * @return StorageQuota|null Quota information or null if not supported
     */
    public function getQuota(): ?StorageQuota;

    /**
     * Create a directory on remote storage.
     *
     * @param string $path Directory path to create
     * @return bool True if directory was created or already exists
     */
    public function createDirectory(string $path): bool;
}
