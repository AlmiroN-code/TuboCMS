<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-user-stats',
    description: 'Update user statistics (videos count, views, etc.)',
)]
class UpdateUserStatsCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Обновление статистики пользователей');

        // Получаем всех пользователей с подсчетом видео
        $qb = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.videos', 'v')
            ->addSelect('COUNT(v.id) as videosCount')
            ->addSelect('COALESCE(SUM(v.viewsCount), 0) as totalViews')
            ->groupBy('u.id');

        $results = $qb->getQuery()->getResult();

        $updated = 0;
        $batchSize = 50;

        foreach ($results as $result) {
            $user = $result[0];
            $actualVideosCount = (int) $result['videosCount'];
            $actualTotalViews = (int) $result['totalViews'];

            $needsUpdate = false;

            if ($user->getVideosCount() !== $actualVideosCount) {
                $user->setVideosCount($actualVideosCount);
                $needsUpdate = true;
            }

            if ($user->getTotalViews() !== $actualTotalViews) {
                $user->setTotalViews($actualTotalViews);
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $this->entityManager->persist($user);
                $updated++;

                if ($updated % $batchSize === 0) {
                    $this->entityManager->flush();
                    $io->progressAdvance($batchSize);
                }
            }
        }

        // Финальный flush
        $this->entityManager->flush();

        $io->success("Обновлено пользователей: {$updated}");

        return Command::SUCCESS;
    }
}