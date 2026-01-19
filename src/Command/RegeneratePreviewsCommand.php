<?php

namespace App\Command;

use App\Repository\VideoRepository;
use App\Service\VideoProcessingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:regenerate-previews',
    description: 'Регенерирует превью для видео, у которых их нет'
)]
class RegeneratePreviewsCommand extends Command
{
    public function __construct(
        private VideoRepository $videoRepository,
        private VideoProcessingService $videoProcessor,
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Регенерация превью для видео');

        // Находим все видео без превью
        $videos = $this->em->createQuery(
            'SELECT v FROM App\Entity\Video v 
             WHERE v.preview IS NULL 
             AND v.status = :status 
             AND v.processingStatus = :processingStatus'
        )
        ->setParameter('status', 'published')
        ->setParameter('processingStatus', 'completed')
        ->getResult();

        if (empty($videos)) {
            $io->success('Все видео уже имеют превью');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Найдено видео без превью: %d', count($videos)));

        $mediaDir = $this->projectDir . '/public/media';
        $processed = 0;
        $failed = 0;

        foreach ($videos as $video) {
            $io->text(sprintf('Обработка видео #%d: %s', $video->getId(), $video->getTitle()));

            // Находим исходный видеофайл
            $videoFiles = $video->getEncodedFiles();
            if ($videoFiles->isEmpty()) {
                $io->warning(sprintf('  Видео #%d не имеет файлов', $video->getId()));
                $failed++;
                continue;
            }

            // Берём первый файл (обычно это оригинал или лучшее качество)
            $videoFile = $videoFiles->first();
            $videoPath = $this->projectDir . '/public/media/' . $videoFile->getFile();

            if (!file_exists($videoPath)) {
                $io->warning(sprintf('  Файл не найден: %s', $videoPath));
                $failed++;
                continue;
            }

            // Создаём превью
            $previewFilename = 'preview_' . uniqid() . '.mp4';
            $previewPath = $mediaDir . '/previews/' . $previewFilename;

            try {
                if ($this->videoProcessor->extractPreview($videoPath, $previewPath)) {
                    $video->setPreview('previews/' . $previewFilename);
                    $this->em->flush();
                    $io->success(sprintf('  ✓ Превью создано: %s', $previewFilename));
                    $processed++;
                } else {
                    $io->error('  ✗ Не удалось создать превью');
                    $failed++;
                }
            } catch (\Exception $e) {
                $io->error(sprintf('  ✗ Ошибка: %s', $e->getMessage()));
                $failed++;
            }
        }

        $io->newLine();
        $io->success(sprintf('Обработано: %d, Ошибок: %d', $processed, $failed));

        return Command::SUCCESS;
    }
}
