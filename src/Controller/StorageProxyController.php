<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Storage;
use App\Repository\StorageRepository;
use App\Repository\VideoFileRepository;
use App\Service\ContentProtectionService;
use App\Service\SignedUrlService;
use App\Service\StorageManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Proxy controller for streaming files from FTP/SFTP storage.
 * 
 * Requirement 3.2: WHEN video is stored on FTP/SFTP THEN the System SHALL 
 * serve the file through a proxy endpoint or generate a temporary download URL
 */
#[Route('/storage')]
class StorageProxyController extends AbstractController
{
    public function __construct(
        private readonly StorageManager $storageManager,
        private readonly StorageRepository $storageRepository,
        private readonly VideoFileRepository $videoFileRepository,
        private readonly SignedUrlService $signedUrlService,
        private readonly ContentProtectionService $contentProtectionService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Stream a file from remote storage through proxy.
     * 
     * Supports signed URLs for secure access.
     * 
     * @param string $path Remote file path (captured as catch-all)
     */
    #[Route('/proxy/{path}', name: 'storage_proxy', requirements: ['path' => '.+'], methods: ['GET'])]
    public function proxy(Request $request, string $path): Response
    {
        // Проверка защиты контента (Hotlink, User-Agent)
        $errors = $this->contentProtectionService->validateRequest($request);
        if (!empty($errors)) {
            throw new AccessDeniedHttpException('Access denied: ' . implode(', ', $errors));
        }
        
        // Get storage ID from query params
        $storageId = $request->query->getInt('storage', 0);
        
        if ($storageId === 0) {
            throw new NotFoundHttpException('Storage not specified');
        }
        
        // Verify signed URL if signature is present
        $signature = $request->query->get('signature');
        $expires = $request->query->getInt('expires', 0);
        
        if ($signature !== null && $expires > 0) {
            $basePath = '/storage/proxy/' . $path;
            
            if (!$this->signedUrlService->verifySignedUrl($basePath, $expires, $signature, $storageId)) {
                throw new AccessDeniedHttpException('Invalid or expired signature');
            }
        }
        
        // Get storage
        $storage = $this->storageRepository->find($storageId);
        
        if ($storage === null) {
            throw new NotFoundHttpException('Storage not found');
        }
        
        if (!$storage->isEnabled()) {
            throw new NotFoundHttpException('Storage is disabled');
        }
        
        // Only FTP and SFTP need proxy
        if (!in_array($storage->getType(), [Storage::TYPE_FTP, Storage::TYPE_SFTP], true)) {
            throw new NotFoundHttpException('Storage type does not support proxy');
        }
        
        return $this->streamFromStorage($storage, $path);
    }

    /**
     * Stream a VideoFile by ID.
     * 
     * This endpoint is useful when you have the VideoFile ID and want to stream it.
     */
    #[Route('/file/{id}', name: 'storage_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function streamFile(Request $request, int $id): Response
    {
        // Проверка защиты контента (Hotlink, User-Agent)
        $errors = $this->contentProtectionService->validateRequest($request);
        if (!empty($errors)) {
            throw new AccessDeniedHttpException('Access denied: ' . implode(', ', $errors));
        }
        
        $videoFile = $this->videoFileRepository->find($id);
        
        if ($videoFile === null) {
            throw new NotFoundHttpException('Video file not found');
        }
        
        $storage = $videoFile->getStorage();
        $remotePath = $videoFile->getRemotePath();
        
        // If file is local, redirect to local path
        if ($storage === null || $remotePath === null) {
            $localPath = $videoFile->getFile();
            if ($localPath === null) {
                throw new NotFoundHttpException('File path not found');
            }
            
            return $this->redirectToRoute('app_home');
        }
        
        // Verify signed URL if signature is present
        $signature = $request->query->get('signature');
        $expires = $request->query->getInt('expires', 0);
        
        if ($signature !== null && $expires > 0) {
            $basePath = '/storage/file/' . $id;
            
            if (!$this->signedUrlService->verifySignedUrl($basePath, $expires, $signature, $storage->getId())) {
                throw new AccessDeniedHttpException('Invalid or expired signature');
            }
        }
        
        if (!$storage->isEnabled()) {
            throw new NotFoundHttpException('Storage is disabled');
        }
        
        // For HTTP storage, redirect to direct URL
        if ($storage->getType() === Storage::TYPE_HTTP) {
            $adapter = $this->storageManager->getAdapter($storage);
            return $this->redirect($adapter->getUrl($remotePath));
        }
        
        return $this->streamFromStorage($storage, $remotePath);
    }

    /**
     * Stream file content from storage.
     */
    private function streamFromStorage(Storage $storage, string $remotePath): StreamedResponse
    {
        // Create temp file for streaming
        $tempFile = sys_get_temp_dir() . '/storage_proxy_' . md5($remotePath . time()) . '_' . uniqid();
        
        try {
            // Download file from storage
            $downloaded = $this->storageManager->downloadFile($remotePath, $tempFile, $storage);
            
            if (!$downloaded || !file_exists($tempFile)) {
                throw new NotFoundHttpException('Failed to download file from storage');
            }
            
            $fileSize = filesize($tempFile);
            $mimeType = $this->getMimeType($remotePath, $tempFile);
            $filename = basename($remotePath);
            
            $response = new StreamedResponse(function () use ($tempFile) {
                $handle = fopen($tempFile, 'rb');
                
                if ($handle === false) {
                    return;
                }
                
                try {
                    while (!feof($handle)) {
                        echo fread($handle, 8192);
                        flush();
                    }
                } finally {
                    fclose($handle);
                    // Clean up temp file after streaming
                    if (file_exists($tempFile)) {
                        @unlink($tempFile);
                    }
                }
            });
            
            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('Content-Length', (string) $fileSize);
            $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
            $response->headers->set('Accept-Ranges', 'bytes');
            
            // Защита от скачивания
            $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            
            $this->logger->info('Streaming file from storage', [
                'storage_id' => $storage->getId(),
                'remote_path' => $remotePath,
                'file_size' => $fileSize,
            ]);
            
            return $response;
            
        } catch (NotFoundHttpException $e) {
            // Clean up temp file on error
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
        } catch (\Throwable $e) {
            // Clean up temp file on error
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            $this->logger->error('Failed to stream file from storage', [
                'storage_id' => $storage->getId(),
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            
            throw new NotFoundHttpException('Failed to stream file: ' . $e->getMessage());
        }
    }

    /**
     * Determine MIME type for file.
     */
    private function getMimeType(string $path, string $tempFile): string
    {
        // Try to get MIME type from file content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tempFile);
        
        if ($mimeType !== false && $mimeType !== 'application/octet-stream') {
            return $mimeType;
        }
        
        // Fallback to extension-based detection
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return match ($extension) {
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
    }
}
