<?php

namespace App\Command;

use App\Repository\VideoRepository;
use App\Service\VideoProcessingService;
use App\Service\StorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:video:generate-previews',
    description: 'Generate previews for videos that don\'t have them',
)]
class GenerateVideoPreviewsCommand extends Command
{
    public function __construct(
        private VideoRepository $videoRepository,
        private VideoProcessingService $videoProcessor,
        private StorageManager $storageManager,
        private EntityManagerInterface $em,
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('video-id', null, InputOption::VALUE_REQUIRED, 'Process specific video by ID')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force regeneration even if preview exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating video previews');

        $videoId = $input->getOption('video-id');
        $force = $input->getOption('force');

        // Если указан конкретный ID
        if ($videoId) {
            $video = $this->videoRepository->find($videoId);
            if (!$video) {
                $io->error(sprintf('Video with ID %d not found', $videoId));
                return Command::FAILURE;
            }
            $videos = [$video];
        } else {
            // Найти все видео без превью
            $qb = $this->videoRepository->createQueryBuilder('v')
                ->where('v.preview IS NULL OR v.poster IS NULL');
            
            if (!$force) {
                $qb->andWhere('v.tempVideoFile IS NOT NULL');
            }
            
            $videos = $qb->getQuery()->getResult();
        }

        if (empty($videos)) {
            $io->success('All videos already have previews!');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d videos to process', count($videos)));

        $mediaDir = $this->projectDir . '/public/media';
        $processed = 0;
        $failed = 0;

        foreach ($videos as $video) {
            $io->text(sprintf('Processing: %s (ID: %d)', $video->getTitle(), $video->getId()));

            $videoPath = null;
            $tempFile = null;

            // Сначала пробуем tempVideoFile
            if ($video->getTempVideoFile()) {
                $videoPath = $this->projectDir . '/public/media/' . $video->getTempVideoFile();
                if (!file_exists($videoPath)) {
                    $videoPath = null;
                }
            }

            // Если нет temp файла, пробуем закодированные файлы
            if (!$videoPath) {
                $primaryFile = $video->getPrimaryVideoFile();
                if ($primaryFile) {
                    $localPath = $this->projectDir . '/public/media/' . $primaryFile->getFile();
                    
                    if (file_exists($localPath)) {
                        $videoPath = $localPath;
                    } elseif ($primaryFile->isRemote() && $primaryFile->getStorage()) {
                        // Скачиваем с удалённого хранилища
                        $io->text('  Downloading from remote storage...');
                        $tempFile = sys_get_temp_dir() . '/video_' . $video->getId() . '_' . uniqid() . '.mp4';
                        
                        try {
                            $adapter = $this->storageManager->getAdapter($primaryFile->getStorage());
                            if ($adapter && $adapter->download($primaryFile->getRemotePath(), $tempFile)) {
                                $videoPath = $tempFile;
                                $io->text('  Downloaded successfully');
                            }
                        } catch (\Exception $e) {
                            $io->warning(sprintf('  Failed to download: %s', $e->getMessage()));
                        }
                    }
                }
            }

            if (!$videoPath || !file_exists($videoPath)) {
                $io->warning(sprintf('  No video file available for: %s', $video->getTitle()));
                $failed++;
                continue;
            }

            try {
                $needsPoster = !$video->getPoster() || $force;
                $needsPreview = !$video->getPreview() || $force;

                // Создать постер
                if ($needsPoster) {
                    $posterFilename = 'poster_' . uniqid() . '.avif';
                    $posterPath = $mediaDir . '/posters/' . $posterFilename;

                    if (!is_dir(dirname($posterPath))) {
                        mkdir(dirname($posterPath), 0777, true);
                    }

                    if ($this->videoProcessor->extractPoster($videoPath, $posterPath)) {
                        $video->setPoster('posters/' . $posterFilename);
                        $io->text('  ✓ Poster created');
                    } else {
                        $io->text('  ✗ Failed to create poster');
                    }
                }

                // Создать превью
                if ($needsPreview) {
                    $previewFilename = 'preview_' . uniqid() . '.mp4';
                    $previewPath = $mediaDir . '/previews/' . $previewFilename;

                    if (!is_dir(dirname($previewPath))) {
                        mkdir(dirname($previewPath), 0777, true);
                    }

                    if ($this->videoProcessor->extractPreview($videoPath, $previewPath)) {
                        $video->setPreview('previews/' . $previewFilename);
                        $io->text('  ✓ Preview created');
                    } else {
                        $io->text('  ✗ Failed to create preview');
                    }
                }

                $this->em->flush();
                $processed++;
                $io->success(sprintf('✓ Processed: %s', $video->getTitle()));

            } catch (\Exception $e) {
                $io->error(sprintf('✗ Error: %s', $e->getMessage()));
                $failed++;
            } finally {
                // Удаляем временный файл
                if ($tempFile && file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        }

        $io->newLine();
        $io->success(sprintf('Processed: %d, Failed: %d', $processed, $failed));

        return Command::SUCCESS;
    }
}
