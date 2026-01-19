<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Storage;
use App\Entity\VideoFile;
use Psr\Log\LoggerInterface;

/**
 * Сервис для генерации и верификации подписанных URL.
 * 
 * Requirement 3.4: WHEN generating video URLs THEN the System SHALL 
 * support optional signed URLs with expiration for security
 * 
 * Property 7: Signed URLs contain signature and expiration
 * For any signed URL generated with expiration time T, the URL SHALL contain 
 * a signature parameter and expire parameter with value T.
 */
class SignedUrlService
{
    private const SIGNATURE_ALGORITHM = 'sha256';
    private const SIGNATURE_PARAM = 'signature';
    private const EXPIRES_PARAM = 'expires';
    private const STORAGE_PARAM = 'storage';

    public function __construct(
        private readonly string $secretKey,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Генерирует подписанный URL для файла.
     * 
     * @param string $path Путь к файлу
     * @param int $expiresIn Время жизни URL в секундах (по умолчанию 1 час)
     * @param int|null $storageId ID хранилища (для верификации)
     * @return string Подписанный URL с параметрами expires и signature
     */
    public function generateSignedUrl(string $path, int $expiresIn = 3600, ?int $storageId = null): string
    {
        $expires = time() + $expiresIn;
        $signature = $this->generateSignature($path, $expires, $storageId);
        
        $params = [
            self::EXPIRES_PARAM => $expires,
            self::SIGNATURE_PARAM => $signature,
        ];
        
        if ($storageId !== null) {
            $params[self::STORAGE_PARAM] = $storageId;
        }
        
        $separator = str_contains($path, '?') ? '&' : '?';
        
        return $path . $separator . http_build_query($params);
    }

    /**
     * Генерирует подписанный URL для VideoFile.
     * 
     * @param VideoFile $videoFile Файл видео
     * @param string $baseUrl Базовый URL для файла
     * @param int $expiresIn Время жизни URL в секундах
     * @return string Подписанный URL
     */
    public function generateSignedUrlForVideoFile(
        VideoFile $videoFile, 
        string $baseUrl, 
        int $expiresIn = 3600
    ): string {
        $storage = $videoFile->getStorage();
        $storageId = $storage?->getId();
        
        return $this->generateSignedUrl($baseUrl, $expiresIn, $storageId);
    }


    /**
     * Верифицирует подписанный URL.
     * 
     * @param string $path Путь к файлу (без query параметров)
     * @param int $expires Время истечения (timestamp)
     * @param string $signature Подпись из URL
     * @param int|null $storageId ID хранилища
     * @return bool True если подпись валидна и URL не истёк
     */
    public function verifySignedUrl(
        string $path, 
        int $expires, 
        string $signature, 
        ?int $storageId = null
    ): bool {
        // Проверяем срок действия
        if ($expires < time()) {
            $this->logger->debug('Signed URL expired', [
                'path' => $path,
                'expires' => $expires,
                'current_time' => time(),
            ]);
            return false;
        }
        
        // Генерируем ожидаемую подпись
        $expectedSignature = $this->generateSignature($path, $expires, $storageId);
        
        // Сравниваем подписи безопасным способом
        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('Invalid signature for signed URL', [
                'path' => $path,
                'storage_id' => $storageId,
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Верифицирует подписанный URL из массива параметров запроса.
     * 
     * @param string $path Путь к файлу
     * @param array $queryParams Параметры запроса
     * @return bool True если URL валиден
     */
    public function verifyFromQueryParams(string $path, array $queryParams): bool
    {
        $expires = isset($queryParams[self::EXPIRES_PARAM]) 
            ? (int) $queryParams[self::EXPIRES_PARAM] 
            : 0;
            
        $signature = $queryParams[self::SIGNATURE_PARAM] ?? '';
        
        $storageId = isset($queryParams[self::STORAGE_PARAM]) 
            ? (int) $queryParams[self::STORAGE_PARAM] 
            : null;
        
        if (empty($signature) || $expires === 0) {
            return false;
        }
        
        return $this->verifySignedUrl($path, $expires, $signature, $storageId);
    }

    /**
     * Извлекает параметры подписи из URL.
     * 
     * @param string $url Полный URL с параметрами
     * @return array{path: string, expires: int, signature: string, storage_id: int|null}
     */
    public function parseSignedUrl(string $url): array
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        
        parse_str($query, $params);
        
        return [
            'path' => $path,
            'expires' => isset($params[self::EXPIRES_PARAM]) 
                ? (int) $params[self::EXPIRES_PARAM] 
                : 0,
            'signature' => $params[self::SIGNATURE_PARAM] ?? '',
            'storage_id' => isset($params[self::STORAGE_PARAM]) 
                ? (int) $params[self::STORAGE_PARAM] 
                : null,
        ];
    }

    /**
     * Проверяет, истёк ли подписанный URL.
     * 
     * @param int $expires Время истечения (timestamp)
     * @return bool True если URL истёк
     */
    public function isExpired(int $expires): bool
    {
        return $expires < time();
    }

    /**
     * Возвращает оставшееся время жизни URL в секундах.
     * 
     * @param int $expires Время истечения (timestamp)
     * @return int Оставшееся время в секундах (0 если истёк)
     */
    public function getRemainingTime(int $expires): int
    {
        $remaining = $expires - time();
        return max(0, $remaining);
    }

    /**
     * Генерирует подпись для URL.
     * 
     * @param string $path Путь к файлу
     * @param int $expires Время истечения
     * @param int|null $storageId ID хранилища
     * @return string HMAC подпись
     */
    private function generateSignature(string $path, int $expires, ?int $storageId = null): string
    {
        $data = $path . ':' . $expires;
        
        if ($storageId !== null) {
            $data .= ':' . $storageId;
        }
        
        return hash_hmac(self::SIGNATURE_ALGORITHM, $data, $this->secretKey);
    }

    /**
     * Возвращает имя параметра подписи.
     */
    public function getSignatureParamName(): string
    {
        return self::SIGNATURE_PARAM;
    }

    /**
     * Возвращает имя параметра времени истечения.
     */
    public function getExpiresParamName(): string
    {
        return self::EXPIRES_PARAM;
    }

    /**
     * Возвращает имя параметра хранилища.
     */
    public function getStorageParamName(): string
    {
        return self::STORAGE_PARAM;
    }
}
