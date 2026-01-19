<?php

namespace App\Controller;

use App\Service\ContentProtectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/secure-media')]
class SecureMediaController extends AbstractController
{
    public function __construct(
        private ContentProtectionService $protectionService,
        private string $projectDir
    ) {
    }

    /**
     * Защищенная раздача видео
     */
    #[Route('/videos/{filename}', name: 'secure_media_video', methods: ['GET'])]
    public function serveVideo(Request $request, string $filename): Response
    {
        return $this->serveFile($request, 'videos', $filename);
    }

    /**
     * Защищенная раздача постеров
     */
    #[Route('/posters/{filename}', name: 'secure_media_poster', methods: ['GET'])]
    public function servePoster(Request $request, string $filename): Response
    {
        return $this->serveFile($request, 'posters', $filename);
    }

    /**
     * Защищенная раздача превью
     */
    #[Route('/previews/{filename}', name: 'secure_media_preview', methods: ['GET'])]
    public function servePreview(Request $request, string $filename): Response
    {
        return $this->serveFile($request, 'previews', $filename);
    }

    /**
     * Общий метод для раздачи файлов с проверкой защиты
     */
    private function serveFile(Request $request, string $type, string $filename): Response
    {
        // Валидация запроса
        $errors = $this->protectionService->validateRequest($request);
        
        if (!empty($errors)) {
            throw new AccessDeniedHttpException('Access denied: ' . implode(', ', $errors));
        }

        // Проверка подписанного URL
        $path = "/secure-media/{$type}/{$filename}";
        if (!$this->protectionService->validateSignedUrl($request, $path)) {
            throw new AccessDeniedHttpException('Invalid or expired token');
        }

        // Путь к файлу
        $filePath = $this->projectDir . "/public/media/{$type}/{$filename}";

        // Проверка существования файла
        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('File not found');
        }

        // Определение MIME типа
        $mimeType = $this->getMimeType($type, $filename);

        // Создание ответа с файлом
        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', $mimeType);
        
        // Запрет кеширования для максимальной защиты
        $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Для видео - поддержка Range запросов (для перемотки)
        if ($type === 'videos') {
            $response->headers->set('Accept-Ranges', 'bytes');
        }

        return $response;
    }

    /**
     * Определение MIME типа по типу файла
     */
    private function getMimeType(string $type, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($type) {
            'videos' => match ($extension) {
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'ogg' => 'video/ogg',
                'mov' => 'video/quicktime',
                'avi' => 'video/x-msvideo',
                default => 'application/octet-stream',
            },
            'posters', 'previews' => match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            },
            default => 'application/octet-stream',
        };
    }
}
