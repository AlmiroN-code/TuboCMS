<?php

namespace App\Command;

use App\Service\ImageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-image',
    description: 'Test image processing capabilities',
)]
class TestImageCommand extends Command
{
    public function __construct(
        private ImageService $imageService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Проверяем поддержку WebP
        if (!function_exists('imagewebp')) {
            $io->error('WebP support is not available in this PHP installation');
            return Command::FAILURE;
        }

        $io->success('WebP support is available');

        // Проверяем поддержку GD
        if (!extension_loaded('gd')) {
            $io->error('GD extension is not loaded');
            return Command::FAILURE;
        }

        $io->success('GD extension is loaded');

        // Показываем поддерживаемые форматы
        $formats = [];
        if (function_exists('imagecreatefromjpeg')) $formats[] = 'JPEG';
        if (function_exists('imagecreatefrompng')) $formats[] = 'PNG';
        if (function_exists('imagecreatefromgif')) $formats[] = 'GIF';
        if (function_exists('imagecreatefromwebp')) $formats[] = 'WebP';

        $io->table(['Supported formats'], array_map(fn($f) => [$f], $formats));

        return Command::SUCCESS;
    }
}