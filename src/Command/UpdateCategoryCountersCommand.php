<?php

namespace App\Command;

use App\Entity\Video;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-category-counters',
    description: 'Пересчитывает счётчики видео во всех категориях'
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
        $io->title('Обновление счётчиков категорий');

        $categories = $this->categoryRepository->findAll();
        $updated = 0;

        foreach ($categories as $category) {
            $count = $this->em->createQueryBuilder()
                ->select('COUNT(DISTINCT v.id)')
                ->from(Video::class, 'v')
                ->innerJoin('v.categories', 'c')
                ->where('c.id = :category')
                ->andWhere('v.status = :status')
                ->setParameter('category', $category->getId())
                ->setParameter('status', Video::STATUS_PUBLISHED)
                ->getQuery()
                ->getSingleScalarResult();

            $oldCount = $category->getVideosCount();
            $newCount = (int) $count;

            if ($oldCount !== $newCount) {
                $category->setVideosCount($newCount);
                $updated++;
                $io->writeln(sprintf(
                    '  %s: %d → %d',
                    $category->getName(),
                    $oldCount,
                    $newCount
                ));
            }
        }

        $this->em->flush();

        $io->success(sprintf('Готово! Обновлено категорий: %d из %d', $updated, count($categories)));

        return Command::SUCCESS;
    }
}
