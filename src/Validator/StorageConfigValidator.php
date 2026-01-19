<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Storage;

/**
 * Валидатор конфигурации хранилища.
 * 
 * Проверяет наличие обязательных полей в зависимости от типа хранилища.
 */
class StorageConfigValidator
{
    /**
     * Обязательные поля для FTP хранилища.
     * Requirements 1.2: host, port, username, password, basePath
     */
    private const FTP_REQUIRED_FIELDS = ['host', 'port', 'username', 'password', 'basePath'];

    /**
     * Обязательные поля для SFTP хранилища.
     * Requirements 1.3: host, port, username, authType, basePath
     * Дополнительно: password или privateKey в зависимости от authType
     */
    private const SFTP_REQUIRED_FIELDS = ['host', 'port', 'username', 'authType', 'basePath'];

    /**
     * Обязательные поля для HTTP хранилища.
     * Requirements 1.4: baseUrl, authToken, uploadEndpoint
     */
    private const HTTP_REQUIRED_FIELDS = ['baseUrl', 'authToken', 'uploadEndpoint'];

    /**
     * Обязательные поля для S3 хранилища.
     * Поддерживает BunnyCDN, AWS S3, MinIO и другие S3-совместимые хранилища.
     */
    private const S3_REQUIRED_FIELDS = ['endpoint', 'bucket', 'accessKey', 'secretKey'];

    /**
     * Валидирует конфигурацию хранилища.
     * 
     * @param Storage $storage Хранилище для валидации
     * @return array<string, string> Массив ошибок [поле => сообщение]
     */
    public function validate(Storage $storage): array
    {
        $errors = [];
        $config = $storage->getConfig();
        $type = $storage->getType();

        $requiredFields = match ($type) {
            Storage::TYPE_FTP => self::FTP_REQUIRED_FIELDS,
            Storage::TYPE_SFTP => self::SFTP_REQUIRED_FIELDS,
            Storage::TYPE_HTTP => self::HTTP_REQUIRED_FIELDS,
            Storage::TYPE_S3 => self::S3_REQUIRED_FIELDS,
            Storage::TYPE_LOCAL => [],
            default => [],
        };

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || $this->isEmpty($config[$field])) {
                $errors[$field] = sprintf('Поле "%s" обязательно для типа хранилища "%s"', $field, $type);
            }
        }

        // Дополнительная валидация для SFTP
        if ($type === Storage::TYPE_SFTP && empty($errors)) {
            $authType = $config['authType'] ?? null;
            
            if ($authType === 'password' && $this->isEmpty($config['password'] ?? null)) {
                $errors['password'] = 'Пароль обязателен при выборе аутентификации по паролю';
            }
            
            if ($authType === 'key' && $this->isEmpty($config['privateKey'] ?? null)) {
                $errors['privateKey'] = 'Приватный ключ обязателен при выборе аутентификации по ключу';
            }
        }

        // Валидация порта
        if (in_array($type, [Storage::TYPE_FTP, Storage::TYPE_SFTP])) {
            $port = $config['port'] ?? null;
            if ($port !== null && (!is_numeric($port) || (int)$port < 1 || (int)$port > 65535)) {
                $errors['port'] = 'Порт должен быть числом от 1 до 65535';
            }
        }

        // Валидация URL для HTTP
        if ($type === Storage::TYPE_HTTP) {
            $baseUrl = $config['baseUrl'] ?? '';
            if (!empty($baseUrl) && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                $errors['baseUrl'] = 'Некорректный URL';
            }
        }

        // Валидация для S3
        if ($type === Storage::TYPE_S3) {
            $endpoint = $config['endpoint'] ?? '';
            if (!empty($endpoint) && !filter_var($endpoint, FILTER_VALIDATE_URL)) {
                $errors['endpoint'] = 'Некорректный URL endpoint';
            }
            
            $cdnUrl = $config['cdnUrl'] ?? '';
            if (!empty($cdnUrl) && !filter_var($cdnUrl, FILTER_VALIDATE_URL)) {
                $errors['cdnUrl'] = 'Некорректный CDN URL';
            }
        }

        return $errors;
    }

    /**
     * Проверяет, является ли значение пустым.
     */
    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return empty($value);
    }

    /**
     * Возвращает список обязательных полей для типа хранилища.
     * 
     * @param string $type Тип хранилища
     * @return array<string> Список обязательных полей
     */
    public function getRequiredFields(string $type): array
    {
        return match ($type) {
            Storage::TYPE_FTP => self::FTP_REQUIRED_FIELDS,
            Storage::TYPE_SFTP => self::SFTP_REQUIRED_FIELDS,
            Storage::TYPE_HTTP => self::HTTP_REQUIRED_FIELDS,
            Storage::TYPE_S3 => self::S3_REQUIRED_FIELDS,
            default => [],
        };
    }
}
