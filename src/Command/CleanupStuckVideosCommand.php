<?php

namespace App\Command;

use App\Entity\Video;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-stuck-videos',
    description: 'Очищает застрявшие в обработке видео'
)]
class CleanupStuckVideosCommand extends Command
{
    public function __construct(
        private readonly VideoRepository $videoRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Количество часов для определения застрявшего видео', 2)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать что будет сделано без выполнения')
            ->addOption('reset-to-draft', null, InputOption::VALUE_NONE, 'Сбросить статус в draft вместо rejected');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $hours = (int) $input->getOption('hours');
        $dryRun = $input->getOption('dry-run');
        $resetToDraft = $input->getOption('reset-to-draft');
        
        $threshold = new \DateTime("-{$hours} hours");
        
        $io->title('Очистка застрявших видео');
        $io->text("Поиск видео в статусе 'processing' старше {$hours} часов...");
        
        // Находим застрявшие видео
        $stuckVideos = $this->entityManager->createQuery(
            'SELECT v FROM App\Entity\Video v 
             WHERE v.status = :status 
             AND v.updatedAt < :threshold'
        )
        ->setParameter('status', Video::STATUS_PROCESSING)
        ->setParameter('threshold', $threshold)
        ->getResult();

        if (empty($stuckVideos)) {
            $io->success('Застрявших видео не найдено');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Найдено %d застрявших видео:', count($stuckVideos)));
        
        $table = [];
        foreach ($stuckVideos as $video) {
            $table[] = [
                $video->getId(),
                $video->getTitle(),
                $video->getCreatedBy()?->getUsername() ?? 'N/A',
                $video->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'N/A',
            ];
        }
        
        $io->table(['ID', 'Название', 'Пользователь', 'Последнее обновление'], $table);

        if ($dryRun) {
            $io->note('Режим dry-run: изменения не будут применены');
            return Command::SUCCESS;
        }

        if (!$io->confirm('Продолжить обработку застрявших видео?')) {
            $io->text('Операция отменена');
            return Command::SUCCESS;
        }

        $newStatus = $resetToDraft ? Video::STATUS_DRAFT : Video::STATUS_REJECTED;
        $statusText = $resetToDraft ? 'draft' : 'rejected';
        
        $processed = 0;
        foreach ($stuckVideos as $video) {
            try {
                $video->setStatus($newStatus);
                $video->setUpdatedAt(new \DateTime());
                
                if (!$resetToDraft) {
                    // Добавляем причину отклонения
                    $video->setProcessingError('Видео застряло в обработке более ' . $hours . ' часов');
                }
                
                $this->entityManager->persist($video);
                $processed++;
                
                $io->text("✓ Видео #{$video->getId()} переведено в статус '{$statusText}'");
            } catch (\Exception $e) {
                $io->error("Ошибка при обработке видео #{$video->getId()}: " . $e->getMessage());
            }
        }

        if ($processed > 0) {
            $this->entityManager->flush();
            $io->success("Обработано {$processed} видео");
        }

        return Command::SUCCESS;
    }
}