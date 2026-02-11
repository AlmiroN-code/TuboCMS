<?php

declare(strict_types=1);

namespace App\Service;

use GeoIp2\Database\Reader;
use Psr\Log\LoggerInterface;

/**
 * Сервис для определения страны по IP-адресу через MaxMind GeoLite2
 */
class GeoIpService
{
    private ?Reader $reader = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {}

    /**
     * Получить код страны по IP-адресу
     */
    public function getCountryCode(string $ipAddress): ?string
    {
        // Пропускаем локальные IP
        if ($this->isLocalIp($ipAddress)) {
            return null;
        }

        try {
            $reader = $this->getReader();
            if (!$reader) {
                return null;
            }

            $record = $reader->country($ipAddress);
            return strtolower($record->country->isoCode ?? '');
        } catch (\Exception $e) {
            $this->logger->warning('GeoIP lookup failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Получить Reader для базы GeoLite2
     */
    private function getReader(): ?Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        $dbPath = $this->projectDir . '/var/geoip/GeoLite2-Country.mmdb';
        
        if (!file_exists($dbPath)) {
            $this->logger->warning('GeoIP database not found', ['path' => $dbPath]);
            return null;
        }

        try {
            $this->reader = new Reader($dbPath);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load GeoIP database', [
                'path' => $dbPath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return $this->reader;
    }

    /**
     * Проверка, является ли IP локальным
     */
    private function isLocalIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return in_array($ip, ['127.0.0.1', '::1', 'localhost'], true);
    }
}
