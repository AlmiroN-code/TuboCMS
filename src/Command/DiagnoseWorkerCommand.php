<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:diagnose-worker',
    description: 'Диагностика проблем с Messenger Worker'
)]
class DiagnoseWorkerCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Диагностика Messenger Worker');
        
        // Проверяем основные пути
        $pidFile = $this->projectDir . '/var/messenger_worker.pid';
        $logFile = $this->projectDir . '/var/log/messenger_worker.log';
        $varDir = $this->projectDir . '/var';
        $logDir = $this->projectDir . '/var/log';
        
        $io->section('Проверка путей и прав доступа');
        
        // Проверка директории var
        $io->text("Проверка директории var: {$varDir}");
        if (is_dir($varDir)) {
            $io->success("✓ Директория var существует");
            $io->text("Права: " . substr(sprintf('%o', fileperms($varDir)), -4));
            $io->text("Владелец: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($varDir))['name'] ?? 'unknown' : 'unknown'));
        } else {
            $io->error("✗ Директория var не существует");
            return Command::FAILURE;
        }
        
        // Проверка директории log
        $io->text("Проверка директории log: {$logDir}");
        if (is_dir($logDir)) {
            $io->success("✓ Директория log существует");
            $io->text("Права: " . substr(sprintf('%o', fileperms($logDir)), -4));
            $io->text("Владелец: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($logDir))['name'] ?? 'unknown' : 'unknown'));
        } else {
            $io->warning("⚠ Директория log не существует, создаю...");
            if (mkdir($logDir, 0755, true)) {
                $io->success("✓ Директория log создана");
            } else {
                $io->error("✗ Не удалось создать директорию log");
                return Command::FAILURE;
            }
        }
        
        // Проверка лог-файла
        $io->text("Проверка лог-файла: {$logFile}");
        if (file_exists($logFile)) {
            $io->success("✓ Лог-файл существует");
            $io->text("Размер: " . filesize($logFile) . " байт");
            $io->text("Права: " . substr(sprintf('%o', fileperms($logFile)), -4));
            $io->text("Владелец: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($logFile))['name'] ?? 'unknown' : 'unknown'));
            $io->text("Читаемый: " . (is_readable($logFile) ? 'Да' : 'Нет'));
            $io->text("Записываемый: " . (is_writable($logFile) ? 'Да' : 'Нет'));
        } else {
            $io->warning("⚠ Лог-файл не существует, создаю...");
            if (file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Worker log initialized\n") !== false) {
                chmod($logFile, 0664);
                $io->success("✓ Лог-файл создан");
            } else {
                $io->error("✗ Не удалось создать лог-файл");
                return Command::FAILURE;
            }
        }
        
        // Проверка PID файла
        $io->text("Проверка PID файла: {$pidFile}");
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            $io->info("PID файл существует, содержит: {$pid}");
        } else {
            $io->text("PID файл не существует (это нормально если воркер не запущен)");
        }
        
        $io->section('Проверка PHP и системы');
        
        // Проверка PHP
        $phpPath = $this->findPhpPath();
        $io->text("PHP путь: {$phpPath}");
        $io->text("PHP версия: " . PHP_VERSION);
        $io->text("ОС: " . PHP_OS_FAMILY);
        $io->text("Пользователь: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] ?? 'unknown' : get_current_user()));
        
        // Проверка команды console
        $consolePath = $this->projectDir . '/bin/console';
        $io->text("Console путь: {$consolePath}");
        if (file_exists($consolePath)) {
            $io->success("✓ Console существует");
            $io->text("Исполняемый: " . (is_executable($consolePath) ? 'Да' : 'Нет'));
        } else {
            $io->error("✗ Console не найден");
            return Command::FAILURE;
        }
        
        $io->section('Тест создания лог-записи');
        
        // Тестируем запись в лог
        $testMessage = "[" . date('Y-m-d H:i:s') . "] Test log entry from diagnose command\n";
        if (file_put_contents($logFile, $testMessage, FILE_APPEND) !== false) {
            $io->success("✓ Тестовая запись в лог успешна");
        } else {
            $io->error("✗ Не удалось записать в лог-файл");
            return Command::FAILURE;
        }
        
        $io->section('Рекомендации');
        
        if (PHP_OS_FAMILY !== 'Windows') {
            $io->text('Для Ubuntu/Linux выполните следующие команды:');
            $io->text('');
            $io->text('# Установка правильных прав доступа:');
            $io->text("sudo chown -R www-data:www-data {$this->projectDir}/var");
            $io->text("sudo chmod -R 755 {$this->projectDir}/var");
            $io->text("sudo chmod -R 664 {$this->projectDir}/var/log/*");
            $io->text('');
            $io->text('# Или если запускаете от другого пользователя:');
            $io->text("sudo chown -R \$(whoami):\$(whoami) {$this->projectDir}/var");
        }
        
        $io->success('Диагностика завершена');
        
        return Command::SUCCESS;
    }
    
    private function findPhpPath(): string
    {
        // Сначала пробуем получить путь из PHP_BINARY константы
        if (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) {
            return PHP_BINARY;
        }

        if (PHP_OS_FAMILY === 'Windows') {
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
            // Для Linux/Unix систем
            $unixPaths = [
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/opt/php/bin/php',
                '/usr/bin/php8.4',
                '/usr/bin/php8.3',
                '/usr/bin/php8.2',
                '/usr/bin/php8.1',
            ];

            foreach ($unixPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Пробуем найти через which
            $output = shell_exec('which php 2>/dev/null');
            if ($output) {
                $phpPath = trim($output);
                if (file_exists($phpPath)) {
                    return $phpPath;
                }
            }
        }

        // Возвращаем 'php' как fallback
        return 'php';
    }
}