<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class MessengerWorkerService
{
    private string $pidFile;
    private string $logFile;

    public function __construct(
        private string $projectDir,
        private LoggerInterface $logger
    ) {
        $this->pidFile = $projectDir . '/var/messenger_worker.pid';
        // Используем стандартный лог Symfony для продакшена
        if ($_ENV['APP_ENV'] === 'prod' || getenv('APP_ENV') === 'prod') {
            $this->logFile = $projectDir . '/var/log/prod.log';
        } else {
            $this->logFile = $projectDir . '/var/log/messenger_worker.log';
        }
    }

    public function isRunning(): bool
    {
        if (file_exists($this->pidFile)) {
            $pid = (int) file_get_contents($this->pidFile);
            if ($pid > 0 && $this->isProcessRunning($pid)) {
                return true;
            }
        }
        
        $pid = $this->findWorkerPid();
        if ($pid) {
            file_put_contents($this->pidFile, $pid);
            return true;
        }
        
        if (file_exists($this->pidFile)) {
            @unlink($this->pidFile);
        }
        
        return false;
    }

    private function isProcessRunning(int $pid): bool
    {
        if ($this->isWindows()) {
            exec("tasklist /FI \"PID eq {$pid}\" /NH 2>NUL", $output);
            return !empty($output) && strpos(implode('', $output), 'php') !== false;
        }
        return posix_kill($pid, 0);
    }

    public function start(): array
    {
        if ($this->isRunning()) {
            return ['success' => false, 'message' => 'Воркер уже запущен', 'pid' => $this->getPid()];
        }

        try {
            $phpPath = $this->findPhpPath();
            $consolePath = $this->projectDir . '/bin/console';
            
            // Создаем директорию для логов если не существует
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                if (!mkdir($logDir, 0755, true)) {
                    throw new \Exception("Не удалось создать директорию для логов: {$logDir}");
                }
            }
            
            // Создаем лог-файл если не существует
            if (!file_exists($this->logFile)) {
                if (file_put_contents($this->logFile, '') === false) {
                    throw new \Exception("Не удалось создать лог-файл: {$this->logFile}");
                }
                // Устанавливаем права доступа для Linux
                if (!$this->isWindows()) {
                    chmod($this->logFile, 0664);
                }
            }
            
            // Проверяем, что лог-файл доступен для записи
            if (!is_writable($this->logFile)) {
                throw new \Exception("Лог-файл недоступен для записи: {$this->logFile}");
            }
            
            file_put_contents($this->logFile, "[" . date('Y-m-d H:i:s') . "] Starting worker...\n", FILE_APPEND);

            if ($this->isWindows()) {
                $pid = $this->startWindows($phpPath, $consolePath);
            } else {
                $pid = $this->startLinux($phpPath, $consolePath);
            }

            if ($pid > 0) {
                file_put_contents($this->pidFile, $pid);
                $this->logger->info('Messenger worker started', ['pid' => $pid]);
                return ['success' => true, 'message' => 'Воркер запущен', 'pid' => $pid];
            }

            return ['success' => false, 'message' => 'Не удалось запустить воркер'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to start worker', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    private function startWindows(string $phpPath, string $consolePath): int
    {
        // Для продакшена используем стандартный лог Symfony
        $logTarget = ($_ENV['APP_ENV'] === 'prod' || getenv('APP_ENV') === 'prod') 
            ? $this->projectDir . '/var/log/prod.log'
            : $this->logFile;
            
        // Способ 1: Через COM объект WScript.Shell (не блокирует)
        if (class_exists('COM')) {
            try {
                $WshShell = new \COM("WScript.Shell");
                $command = sprintf(
                    '"%s" "%s" messenger:consume async --time-limit=3600 --memory-limit=256M -vv >> "%s" 2>&1',
                    $phpPath,
                    $consolePath,
                    $logTarget
                );
                $WshShell->Run($command, 0, false); // 0 = hidden, false = don't wait
                
                sleep(2);
                $pid = $this->findWorkerPid();
                if ($pid) return $pid;
            } catch (\Exception $e) {
                $this->logger->warning('COM method failed', ['error' => $e->getMessage()]);
            }
        }
        
        // Способ 2: Через popen (асинхронный)
        $command = sprintf(
            'start /B "" "%s" "%s" messenger:consume async --time-limit=3600 --memory-limit=256M -vv >> "%s" 2>&1',
            $phpPath,
            $consolePath,
            $logTarget
        );
        pclose(popen($command, 'r'));
        
        sleep(2);
        return $this->findWorkerPid() ?? 0;
    }

    private function startLinux(string $phpPath, string $consolePath): int
    {
        // Для продакшена используем стандартный лог Symfony
        $logTarget = ($_ENV['APP_ENV'] === 'prod' || getenv('APP_ENV') === 'prod') 
            ? $this->projectDir . '/var/log/prod.log'
            : $this->logFile;
            
        $command = sprintf(
            'nohup %s %s messenger:consume async --time-limit=3600 --memory-limit=256M -vv >> %s 2>&1 & echo $!',
            escapeshellarg($phpPath), 
            escapeshellarg($consolePath), 
            escapeshellarg($logTarget)
        );
        return (int) trim(shell_exec($command) ?? '');
    }

    public function stop(): array
    {
        $pid = $this->getPid() ?: $this->findWorkerPid();
        
        if (!$pid) {
            @unlink($this->pidFile);
            return ['success' => true, 'message' => 'Воркер не был запущен'];
        }

        try {
            if ($this->isWindows()) {
                exec("taskkill /PID {$pid} /F 2>NUL");
            } else {
                exec("kill -TERM {$pid} 2>/dev/null");
            }

            @unlink($this->pidFile);
            $this->logger->info('Messenger worker stopped', ['pid' => $pid]);
            return ['success' => true, 'message' => 'Воркер остановлен', 'pid' => $pid];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    public function restart(): array
    {
        $this->stop();
        sleep(2);
        return $this->start();
    }

    public function getPid(): ?int
    {
        if (!file_exists($this->pidFile)) return null;
        $pid = (int) file_get_contents($this->pidFile);
        return $pid > 0 ? $pid : null;
    }

    public function getStatus(): array
    {
        $isRunning = $this->isRunning();
        $logExists = file_exists($this->logFile);
        $logSize = $logExists ? filesize($this->logFile) : 0;
        $lastLog = '';
        
        if ($logExists && $logSize > 0) {
            $lastLog = $this->getLastLogLines(10);
        }
        
        return [
            'running' => $isRunning,
            'pid' => $isRunning ? $this->getPid() : null,
            'pidFile' => $this->pidFile,
            'logFile' => $this->logFile,
            'logExists' => $logExists,
            'logReadable' => $logExists && is_readable($this->logFile),
            'lastLog' => $lastLog,
            'logSize' => $logSize,
            'pendingMessages' => $this->getPendingMessagesCount(),
            'phpPath' => $this->findPhpPath(),
            'projectDir' => $this->projectDir,
        ];
    }

    public function getLastLogLines(int $lines = 50): string
    {
        if (!file_exists($this->logFile)) {
            return '';
        }
        
        $fileSize = filesize($this->logFile);
        if ($fileSize === 0) {
            return '';
        }
        
        try {
            $file = new \SplFileObject($this->logFile, 'r');
            $file->seek(PHP_INT_MAX);
            $total = $file->key();
            
            if ($total === 0) {
                return '';
            }
            
            $start = max(0, $total - $lines);
            
            $result = [];
            $file->seek($start);
            while (!$file->eof()) {
                $line = $file->fgets();
                if ($line !== false && trim($line) !== '') {
                    $result[] = $line;
                }
            }
            return implode('', $result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to read log file', [
                'file' => $this->logFile,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    public function clearLog(): bool
    {
        return file_put_contents($this->logFile, '') !== false;
    }

    private function findWorkerPid(): ?int
    {
        if ($this->isWindows()) {
            // Используем Get-CimInstance вместо устаревшего wmic
            $command = 'powershell -Command "Get-CimInstance Win32_Process -Filter \"name=\'php.exe\'\" | Where-Object { $_.CommandLine -like \'*messenger:consume*\' } | Select-Object -ExpandProperty ProcessId -First 1"';
            $output = trim(shell_exec($command) ?? '');
            if ($output && is_numeric($output)) {
                return (int) $output;
            }
        } else {
            $output = trim(shell_exec("pgrep -f 'messenger:consume' 2>/dev/null") ?? '');
            if ($output) return (int) explode("\n", $output)[0];
        }
        return null;
    }

    private function findPhpPath(): string
    {
        // Сначала пробуем получить путь из PHP_BINARY константы
        if (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) {
            // Проверяем, что это не PHP-FPM
            if (strpos(PHP_BINARY, 'php-fpm') === false) {
                return PHP_BINARY;
            }
        }

        if ($this->isWindows()) {
            $windowsPaths = [
                'D:\\laragon\\bin\\php\\php-8.4.15-nts-Win32-vs17-x64\\php.exe',
                'D:\\laragon\\bin\\php\\php-8.4\\php.exe',
                'D:\\laragon\\bin\\php\\php-8.3\\php.exe',
                'C:\\laragon\\bin\\php\\php-8.4.15-nts-Win32-vs17-x64\\php.exe',
                'C:\\laragon\\bin\\php\\php-8.4\\php.exe',
                'C:\\laragon\\bin\\php\\php-8.3\\php.exe',
                'C:\\php\\php.exe',
                'C:\\xampp\\php\\php.exe',
            ];

            foreach ($windowsPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Пробуем найти через where
            $output = shell_exec('where php 2>nul');
            if ($output) {
                $phpPath = trim(explode("\n", $output)[0]);
                if (file_exists($phpPath)) {
                    return $phpPath;
                }
            }
        } else {
            // Для Linux/Unix систем - исключаем php-fpm пути
            $unixPaths = [
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/opt/php/bin/php',
                '/usr/bin/php8.4',
                '/usr/bin/php8.3',
                '/usr/bin/php8.2',
                '/usr/bin/php8.1',
                '/bin/php',
            ];

            foreach ($unixPaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }

            // Пробуем найти через which, исключая php-fpm
            $output = shell_exec('which php 2>/dev/null');
            if ($output) {
                $phpPath = trim($output);
                if (file_exists($phpPath) && strpos($phpPath, 'php-fpm') === false) {
                    return $phpPath;
                }
            }
            
            // Дополнительная проверка через whereis
            $output = shell_exec('whereis php 2>/dev/null');
            if ($output) {
                $paths = explode(' ', $output);
                foreach ($paths as $path) {
                    $path = trim($path);
                    if (strpos($path, '/') === 0 && file_exists($path) && is_executable($path) && strpos($path, 'php-fpm') === false) {
                        return $path;
                    }
                }
            }
        }

        // Возвращаем 'php' как fallback
        return 'php';
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    private function getPendingMessagesCount(): int
    {
        try {
            $dsn = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? '';
            if (preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:\/]+)(?::(\d+))?\/([^\?]+)/', $dsn, $m)) {
                $pdo = new \PDO("mysql:host={$m[3]};port=" . ($m[4] ?? 3306) . ";dbname={$m[5]}", $m[1], $m[2]);
                return (int) $pdo->query("SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL")->fetchColumn();
            }
        } catch (\Exception $e) {}
        return 0;
    }
}
