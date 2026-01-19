<?php

namespace App\Command;

use App\Service\CategoryPosterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-category-posters',
    description: 'Генерирует постеры для категорий на основе видео'
)]
class GenerateCategoryPostersCommand extends Command
{
    public function __construct(
        private CategoryPosterService $categoryPosterService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Перезаписать существующие постеры')
            ->addOption('criteria', 'c', InputOption::VALUE_REQUIRED, 'Критерий выбора видео (most_viewed, most_recent, most_liked, random)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Генерация постеров категорий');

        $criteria = $this->categoryPosterService->getSelectionCriteria();
        $criteriaLabels = CategoryPosterService::getAvailableCriteria();
        
        $io->info(sprintf('Критерий выбора: %s', $criteriaLabels[$criteria] ?? $criteria));

        if ($force) {
            $io->warning('Режим принудительной перезаписи включён');
        }

        $stats = $this->categoryPosterService->generateAllPosters($force);

        $io->success(sprintf(
            'Готово! Сгенерировано: %d, Пропущено: %d, Ошибок: %d',
            $stats['generated'],
            $stats['skipped'],
            $stats['failed']
        ));

        return Command::SUCCESS;
    }
}
