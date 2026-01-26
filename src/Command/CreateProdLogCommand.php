<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:create-prod-log',
    description: 'Создание prod.log файла с правильными правами доступа'
)]
class CreateProdLogCommand extends Command
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
        
        $io->title('Создание prod.log файла');
        
        $logDir = $this->projectDir . '/var/log';
        $prodLogFile = $this->projectDir . '/var/log/prod.log';
        
        // Создаем директорию если не существует
        if (!is_dir($logDir)) {
            if (mkdir($logDir, 0755, true)) {
                $io->success("Создана директория: {$logDir}");
            } else {
                $io->error("Не удалось создать директорию: {$logDir}");
                return Command::FAILURE;
            }
        }
        
        // Создаем prod.log файл
        if (!file_exists($prodLogFile)) {
            $initialContent = "[" . date('Y-m-d H:i:s') . "] Production log initialized\n";
            if (file_put_contents($prodLogFile, $initialContent) !== false) {
                $io->success("Создан файл: {$prodLogFile}");
                
                // Устанавливаем права доступа для Linux
                if (PHP_OS_FAMILY !== 'Windows') {
                    chmod($prodLogFile, 0664);
                    $io->text("Установлены права 664");
                }
            } else {
                $io->error("Не удалось создать файл: {$prodLogFile}");
                return Command::FAILURE;
            }
        } else {
            $io->info("Файл уже существует: {$prodLogFile}");
        }
        
        // Проверяем права доступа
        $io->section('Проверка прав доступа');
        
        if (is_readable($prodLogFile)) {
            $io->success("✓ Файл доступен для чтения");
        } else {
            $io->error("✗ Файл недоступен для чтения");
        }
        
        if (is_writable($prodLogFile)) {
            $io->success("✓ Файл доступен для записи");
        } else {
            $io->error("✗ Файл недоступен для записи");
            
            if (PHP_OS_FAMILY !== 'Windows') {
                $io->section('Команды для исправления прав доступа');
                $io->text("sudo chown www-data:www-data {$prodLogFile}");
                $io->text("sudo chmod 664 {$prodLogFile}");
                $io->text('');
                $io->text('Или если запускаете от другого пользователя:');
                $io->text("sudo chown \$(whoami):\$(whoami) {$prodLogFile}");
                $io->text("chmod 664 {$prodLogFile}");
            }
        }
        
        // Показываем информацию о файле
        $io->section('Информация о файле');
        $io->text("Путь: {$prodLogFile}");
        $io->text("Размер: " . filesize($prodLogFile) . " байт");
        
        if (PHP_OS_FAMILY !== 'Windows') {
            $io->text("Права: " . substr(sprintf('%o', fileperms($prodLogFile)), -4));
            if (function_exists('posix_getpwuid')) {
                $owner = posix_getpwuid(fileowner($prodLogFile));
                $group = posix_getgrgid(filegroup($prodLogFile));
                $io->text("Владелец: " . ($owner['name'] ?? 'unknown'));
                $io->text("Группа: " . ($group['name'] ?? 'unknown'));
            }
        }
        
        $io->success('Готово!');
        
        return Command::SUCCESS;
    }
}