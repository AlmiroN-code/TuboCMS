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

#[AsCommand(
    name: 'app:video:generate-previews',
    description: 'Generate previews for videos that don\'t have them',
)]
class GenerateVideoPreviewsCommand extends Command
{
    public function __construct(
        private VideoRepository $videoRepository,
        private VideoProcessingService $videoProcessor,
        private EntityManagerInterface $em,
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating video previews');

        // Найти все видео без превью
        $videos = $this->videoRepository->createQueryBuilder('v')
            ->where('v.preview IS NULL')
            ->andWhere('v.tempVideoFile IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($videos)) {
            $io->success('All videos already have previews!');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d videos without previews', count($videos)));

        $mediaDir = $this->projectDir . '/public/media';
        $processed = 0;
        $failed = 0;

        foreach ($videos as $video) {
            $io->text(sprintf('Processing: %s', $video->getTitle()));

            $videoPath = $this->projectDir . '/public/media/' . $video->getTempVideoFile();

            if (!file_exists($videoPath)) {
                $io->warning(sprintf('Video file not found: %s', $videoPath));
                $failed++;
                continue;
            }

            try {
                // Создать превью
                $previewFilename = 'preview_' . uniqid() . '.mp4';
                $previewPath = $mediaDir . '/previews/' . $previewFilename;

                if (!is_dir(dirname($previewPath))) {
                    mkdir(dirname($previewPath), 0777, true);
                }

                if ($this->videoProcessor->extractPreview($videoPath, $previewPath)) {
                    $video->setPreview('previews/' . $previewFilename);
                    $this->em->flush();
                    $io->success(sprintf('✓ Preview created for: %s', $video->getTitle()));
                    $processed++;
                } else {
                    $io->error(sprintf('✗ Failed to create preview for: %s', $video->getTitle()));
                    $failed++;
                }
            } catch (\Exception $e) {
                $io->error(sprintf('✗ Error: %s', $e->getMessage()));
                $failed++;
            }
        }

        $io->newLine();
        $io->success(sprintf('Processed: %d, Failed: %d', $processed, $failed));

        return Command::SUCCESS;
    }
}
