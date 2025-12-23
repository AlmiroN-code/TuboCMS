<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class FileValidationService
{
    private const ALLOWED_VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/webm',
        'video/x-flv',
        'video/3gpp',
        'video/x-ms-wmv'
    ];

    private const ALLOWED_VIDEO_EXTENSIONS = [
        'mp4', 'mpeg', 'mpg', 'mov', 'avi', 'mkv', 'webm', 'flv', '3gp', 'wmv'
    ];

    private const MAX_FILE_SIZE = 2147483648; // 2GB in bytes

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function validateVideoFile(UploadedFile $file): array
    {
        $errors = [];

        // Проверяем размер файла
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $errors[] = 'Файл слишком большой. Максимальный размер: 2GB';
        }

        // Проверяем MIME-тип
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_VIDEO_MIME_TYPES)) {
            $errors[] = 'Недопустимый тип файла. Разрешены только видео файлы.';
            $this->logger->warning('Invalid MIME type uploaded', [
                'mimeType' => $mimeType,
                'filename' => $file->getClientOriginalName()
            ]);
        }

        // Проверяем расширение файла
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_VIDEO_EXTENSIONS)) {
            $errors[] = 'Недопустимое расширение файла.';
        }

        // Проверяем, что файл действительно загружен через HTTP POST
        if (!$file->isValid()) {
            $errors[] = 'Ошибка загрузки файла: ' . $file->getErrorMessage();
        }

        // Дополнительная проверка на вредоносный контент
        if ($this->containsSuspiciousContent($file)) {
            $errors[] = 'Файл содержит подозрительный контент.';
            $this->logger->alert('Suspicious file upload attempt', [
                'filename' => $file->getClientOriginalName(),
                'mimeType' => $mimeType,
                'size' => $file->getSize()
            ]);
        }

        return $errors;
    }

    public function generateSecureFilename(string $originalFilename): string
    {
        // Удаляем путь и оставляем только имя файла
        $filename = pathinfo($originalFilename, PATHINFO_FILENAME);
        
        // Очищаем от опасных символов
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        
        // Ограничиваем длину
        $filename = substr($filename, 0, 50);
        
        // Добавляем timestamp и случайную строку для уникальности
        return $filename . '_' . time() . '_' . bin2hex(random_bytes(8));
    }

    private function containsSuspiciousContent(UploadedFile $file): bool
    {
        // Проверяем первые байты файла на наличие подозрительных сигнатур
        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
            return true; // Если не можем прочитать файл, считаем подозрительным
        }

        $header = fread($handle, 1024);
        fclose($handle);

        // Проверяем на наличие исполняемых файлов
        $suspiciousSignatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xCA\xFE\xBA\xBE", // Mach-O executable
            "<?php", // PHP code
            "<script", // JavaScript
        ];

        foreach ($suspiciousSignatures as $signature) {
            if (strpos($header, $signature) !== false) {
                return true;
            }
        }

        return false;
    }

    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_VIDEO_MIME_TYPES;
    }

    public function getAllowedExtensions(): array
    {
        return self::ALLOWED_VIDEO_EXTENSIONS;
    }
}