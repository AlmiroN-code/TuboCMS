<?php

namespace App\Scheduler\Handler;

use App\Entity\Video;
use App\Repository\CategoryRepository;
use App\Scheduler\Message\UpdateCategoryCountersMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateCategoryCountersHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateCategoryCountersMessage $message): void
    {
        $this->logger->info('Starting category counters update');
        
        $categories = $this->categoryRepository->findAll();
        $updated = 0;
        
        foreach ($categories as $category) {
            // Подсчитываем опубликованные видео в категории (ManyToMany)
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
            
            if ($category->getVideosCount() !== (int) $count) {
                $category->setVideosCount((int) $count);
                $updated++;
            }
        }
        
        $this->em->flush();
        
        $this->logger->info('Category counters update completed', [
            'total' => count($categories),
            'updated' => $updated,
        ]);
    }
}
