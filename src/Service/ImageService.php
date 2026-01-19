<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageService
{
    public function __construct(
        private SluggerInterface $slugger,
        private string $avatarsDirectory,
        private string $coversDirectory,
        private string $categoriesDirectory,
        private string $siteDirectory
    ) {
    }

    public function processAvatar(UploadedFile $file): string
    {
        return $this->processImage($file, $this->avatarsDirectory, 400, 400);
    }

    public function processCover(UploadedFile $file): string
    {
        return $this->processImage($file, $this->coversDirectory, 1200, 300);
    }

    public function processCategoryPoster(UploadedFile $file): string
    {
        return $this->processImage($file, $this->categoriesDirectory, 400, 300);
    }

    public function processSiteLogo(UploadedFile $file): string
    {
        return $this->processImage($file, $this->siteDirectory, 300, 100);
    }

    public function processSiteFavicon(UploadedFile $file): string
    {
        return $this->processImage($file, $this->siteDirectory, 64, 64);
    }

    private function processImage(UploadedFile $file, string $directory, int $maxWidth, int $maxHeight): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.webp';

        // Создаем изображение из загруженного файла
        $sourceImage = $this->createImageFromFile($file);
        if (!$sourceImage) {
            throw new \RuntimeException('Не удалось обработать изображение');
        }

        // Получаем размеры исходного изображения
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        // Вычисляем новые размеры с сохранением пропорций
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $newWidth = (int)($sourceWidth * $ratio);
        $newHeight = (int)($sourceHeight * $ratio);

        // Создаем новое изображение
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Сохраняем прозрачность для PNG
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefill($resizedImage, 0, 0, $transparent);

        // Изменяем размер изображения
        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );

        // Сохраняем как WebP
        $filePath = $directory . '/' . $newFilename;
        imagewebp($resizedImage, $filePath, 85); // 85% качество

        // Освобождаем память
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        return $newFilename;
    }

    private function createImageFromFile(UploadedFile $file)
    {
        $mimeType = $file->getMimeType();
        $filePath = $file->getPathname();

        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png' => imagecreatefrompng($filePath),
            'image/gif' => imagecreatefromgif($filePath),
            'image/webp' => imagecreatefromwebp($filePath),
            default => false,
        };
    }

    public function deleteImage(string $filename, string $directory): void
    {
        $filePath = $directory . '/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}