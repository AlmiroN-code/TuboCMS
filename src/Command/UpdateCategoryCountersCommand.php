<?php

namespace App\Command;

use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-category-counters',
    description: 'Обновить счетчики видео в категориях'
)]
class UpdateCategoryCountersCommand extends Command
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Обновление счетчиков видео в категориях');

        $categories = $this->categoryRepository->findAll();
        $updated = 0;

        foreach ($categories as $category) {
            $count = $category->getVideos()->count();
            $category->setVideosCount($count);
            $updated++;
            
            $io->text("Категория '{$category->getName()}': {$count} видео");
        }

        $this->em->flush();

        $io->success("Обновлено счетчиков: {$updated}");

        return Command::SUCCESS;
    }
}