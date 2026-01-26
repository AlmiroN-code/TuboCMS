<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:setup-worker-logs',
    description: 'Настройка логов для Messenger Worker'
)]
class SetupWorkerLogsCommand extends Command
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
        
        $io->title('Настройка логов Messenger Worker');
        
        $varDir = $this->projectDir . '/var';
        $logDir = $this->projectDir . '/var/log';
        $workerLogFile = $this->projectDir . '/var/log/messenger_worker.log';
        $prodLogFile = $this->projectDir . '/var/log/prod.log';
        
        // Создаем директории
        if (!is_dir($varDir)) {
            if (mkdir($varDir, 0755, true)) {
                $io->success("Создана директория: {$varDir}");
            } else {
                $io->error("Не удалось создать директорию: {$varDir}");
                return Command::FAILURE;
            }
        }
        
        if (!is_dir($logDir)) {
            if (mkdir($logDir, 0755, true)) {
                $io->success("Создана директория: {$logDir}");
            } else {
                $io->error("Не удалось создать директорию: {$logDir}");
                return Command::FAILURE;
            }
        }
        
        // Создаем лог-файлы
        $files = [
            $workerLogFile => 'Worker log file',
            $prodLogFile => 'Production log file'
        ];
        
        foreach ($files as $file => $description) {
            if (!file_exists($file)) {
                $initialContent = "[" . date('Y-m-d H:i:s') . "] Log file initialized\n";
                if (file_put_contents($file, $initialContent) !== false) {
                    $io->success("Создан файл: {$file}");
                    
                    // Устанавливаем права доступа для Linux
                    if (PHP_OS_FAMILY !== 'Windows') {
                        chmod($file, 0664);
                        $io->text("Установлены права 664 для: {$file}");
                    }
                } else {
                    $io->error("Не удалось создать файл: {$file}");
                    return Command::FAILURE;
                }
            } else {
                $io->text("Файл уже существует: {$file}");
            }
        }
        
        // Проверяем права доступа
        $io->section('Проверка прав доступа');
        
        foreach ($files as $file => $description) {
            if (is_writable($file)) {
                $io->success("✓ {$description} доступен для записи");
            } else {
                $io->error("✗ {$description} недоступен для записи");
                
                if (PHP_OS_FAMILY !== 'Windows') {
                    $io->text("Выполните: sudo chown www-data:www-data {$file}");
                    $io->text("Или: sudo chown \$(whoami):\$(whoami) {$file}");
                }
            }
        }
        
        $io->success('Настройка логов завершена');
        
        return Command::SUCCESS;
    }
}