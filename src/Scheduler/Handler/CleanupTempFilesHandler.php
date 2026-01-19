<?php

namespace App\Scheduler\Handler;

use App\Scheduler\Message\CleanupTempFilesMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupTempFilesHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private string $projectDir,
    ) {
    }

    public function __invoke(CleanupTempFilesMessage $message): void
    {
        $this->logger->info('Starting temp files cleanup', ['maxAgeHours' => $message->maxAgeHours]);
        
        $tempDirs = [
            $this->projectDir . '/public/media/temp',
            $this->projectDir . '/var/tmp',
        ];
        
        $deletedCount = 0;
        $deletedSize = 0;
        $threshold = new \DateTimeImmutable("-{$message->maxAgeHours} hours");
        
        foreach ($tempDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $finder = new Finder();
            $finder->files()->in($dir)->date("< {$threshold->format('Y-m-d H:i:s')}");
            
            foreach ($finder as $file) {
                try {
                    $size = $file->getSize();
                    $this->filesystem->remove($file->getRealPath());
                    $deletedCount++;
                    $deletedSize += $size;
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to delete temp file', [
                        'file' => $file->getRealPath(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        $this->logger->info('Temp files cleanup completed', [
            'deletedCount' => $deletedCount,
            'deletedSize' => $this->formatSize($deletedSize),
        ]);
    }
    
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
