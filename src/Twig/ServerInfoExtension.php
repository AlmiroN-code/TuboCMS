<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig-расширение для отображения серверной информации
 */
class ServerInfoExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('server_time', [$this, 'getServerTime']),
            new TwigFunction('server_load', [$this, 'getServerLoad']),
            new TwigFunction('server_info', [$this, 'getServerInfo']),
        ];
    }

    /**
     * Получить серверное время
     */
    public function getServerTime(string $format = 'H:i:s'): string
    {
        return (new \DateTime())->format($format);
    }

    /**
     * Получить загрузку сервера (CPU Load Average)
     */
    public function getServerLoad(): array
    {
        // На Windows используем PowerShell/WMI
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->getWindowsLoad();
        }

        // На Linux получаем реальный процент CPU
        return $this->getLinuxLoad();
    }

    /**
     * Получить загрузку CPU на Linux
     */
    private function getLinuxLoad(): array
    {
        // Используем только Load Average - это быстро
        $load = sys_getloadavg();
        if ($load === false) {
            return [
                'available' => false,
                'load_1' => 0,
                'load_5' => 0,
                'load_15' => 0,
            ];
        }

        // Конвертируем Load Average в примерный процент
        $numCores = $this->getLinuxCpuCores();
        $loadPercent = $numCores > 0 ? min(100, round(($load[0] / $numCores) * 100)) : round($load[0] * 100);

        return [
            'available' => true,
            'cpu_percent' => $loadPercent,
            'load_1' => $loadPercent,
            'load_5' => round($load[1], 2),
            'load_15' => round($load[2], 2),
            'load_avg' => round($load[0], 2),
            'cores' => $numCores,
        ];
    }

    /**
     * Получить количество ядер CPU на Linux (с кэшированием)
     */
    private static ?int $cpuCores = null;
    
    private function getLinuxCpuCores(): int
    {
        if (self::$cpuCores !== null) {
            return self::$cpuCores;
        }
        
        if (file_exists('/proc/cpuinfo')) {
            $cpuinfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuinfo) {
                self::$cpuCores = substr_count($cpuinfo, 'processor') ?: 1;
                return self::$cpuCores;
            }
        }
        
        self::$cpuCores = 1;
        return self::$cpuCores;
    }

    /**
     * Получить загрузку CPU на Windows
     */
    private function getWindowsLoad(): array
    {
        // Отключаем получение CPU на Windows из-за медленного вызова PowerShell
        // Это можно включить через AJAX-обновление в будущем
        return [
            'available' => false,
            'cpu_percent' => 0,
            'load_1' => 0,
            'load_5' => 0,
            'load_15' => 0,
        ];
    }

    /**
     * Получить полную информацию о сервере
     */
    public function getServerInfo(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->convertToBytes(ini_get('memory_limit') ?: '128M');

        // Информация о диске
        $projectDir = dirname(__DIR__, 2);
        $diskFree = @disk_free_space($projectDir);
        $diskTotal = @disk_total_space($projectDir);
        $diskUsed = $diskTotal && $diskFree ? $diskTotal - $diskFree : 0;
        $diskPercent = $diskTotal ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        // Uptime сервера (только для Linux)
        $uptime = $this->getUptime();

        return [
            'time' => (new \DateTime())->format('d.m.Y H:i:s'),
            'time_short' => (new \DateTime())->format('H:i:s'),
            'date' => (new \DateTime())->format('d.m.Y'),
            'timezone' => date_default_timezone_get(),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'os_full' => php_uname('s') . ' ' . php_uname('r'),
            'hostname' => gethostname() ?: 'localhost',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'uptime' => $uptime,
            'memory' => [
                'usage' => $this->formatBytes($memoryUsage),
                'usage_bytes' => $memoryUsage,
                'peak' => $this->formatBytes($memoryPeak),
                'peak_bytes' => $memoryPeak,
                'limit' => ini_get('memory_limit'),
                'limit_bytes' => $memoryLimit,
                'percent' => $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 1) : 0,
            ],
            'disk' => [
                'free' => $diskFree ? $this->formatBytes((int) $diskFree) : 'N/A',
                'total' => $diskTotal ? $this->formatBytes((int) $diskTotal) : 'N/A',
                'used' => $diskUsed ? $this->formatBytes((int) $diskUsed) : 'N/A',
                'percent' => $diskPercent,
            ],
            'load' => $this->getServerLoad(),
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'max_execution_time' => ini_get('max_execution_time') . 's',
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'opcache' => function_exists('opcache_get_status') && @opcache_get_status() !== false,
            ],
        ];
    }

    /**
     * Получить uptime сервера
     */
    private function getUptime(): ?string
    {
        // На Windows отключаем из-за медленного вызова
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        // Linux/Unix
        if (file_exists('/proc/uptime')) {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int) explode(' ', $uptime)[0];
                return $this->formatUptimeSeconds($seconds);
            }
        }

        return null;
    }

    private function formatUptime(\DateInterval $diff): string
    {
        $parts = [];
        if ($diff->d > 0) {
            $parts[] = $diff->d . 'д';
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h . 'ч';
        }
        if ($diff->i > 0) {
            $parts[] = $diff->i . 'м';
        }
        return implode(' ', $parts) ?: '< 1м';
    }

    private function formatUptimeSeconds(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'д';
        }
        if ($hours > 0) {
            $parts[] = $hours . 'ч';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . 'м';
        }
        return implode(' ', $parts) ?: '< 1м';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $floatBytes = (float) $bytes;
        
        for ($i = 0; $floatBytes > 1024 && $i < count($units) - 1; $i++) {
            $floatBytes /= 1024;
        }
        
        return round($floatBytes, 1) . ' ' . $units[$i];
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return PHP_INT_MAX;
        }
        
        $last = strtolower($value[strlen($value) - 1]);
        $numValue = (int) $value;

        return match($last) {
            'g' => $numValue * 1024 * 1024 * 1024,
            'm' => $numValue * 1024 * 1024,
            'k' => $numValue * 1024,
            default => $numValue,
        };
    }
}
