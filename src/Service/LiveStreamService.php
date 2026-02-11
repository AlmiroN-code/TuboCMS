<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Channel;
use App\Entity\LiveStream;
use App\Entity\User;
use App\Repository\LiveStreamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class LiveStreamService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LiveStreamRepository $repository,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function createStream(
        User $streamer,
        string $title,
        ?string $description = null,
        ?Channel $channel = null,
        ?\DateTimeImmutable $scheduledAt = null
    ): LiveStream {
        $stream = new LiveStream();
        $stream->setStreamer($streamer);
        $stream->setTitle($title);
        $stream->setDescription($description);
        $stream->setChannel($channel);
        $stream->setScheduledAt($scheduledAt);
        
        // Генерируем slug
        $slug = $this->generateUniqueSlug($title);
        $stream->setSlug($slug);
        
        $this->repository->save($stream);
        
        return $stream;
    }

    public function startStream(LiveStream $stream): void
    {
        if ($stream->getStatus() !== LiveStream::STATUS_SCHEDULED) {
            throw new \RuntimeException('Stream must be in scheduled status to start');
        }
        
        $stream->setStatus(LiveStream::STATUS_LIVE);
        $this->repository->save($stream);
    }

    public function endStream(LiveStream $stream): void
    {
        if ($stream->getStatus() !== LiveStream::STATUS_LIVE) {
            throw new \RuntimeException('Stream must be live to end');
        }
        
        $stream->setStatus(LiveStream::STATUS_ENDED);
        $this->repository->save($stream);
    }

    public function cancelStream(LiveStream $stream): void
    {
        if ($stream->getStatus() === LiveStream::STATUS_ENDED) {
            throw new \RuntimeException('Cannot cancel ended stream');
        }
        
        $stream->setStatus(LiveStream::STATUS_CANCELLED);
        $this->repository->save($stream);
    }

    public function updateViewersCount(LiveStream $stream, int $count): void
    {
        $stream->setViewersCount($count);
        $this->em->flush();
    }

    public function incrementViewer(LiveStream $stream): void
    {
        $stream->incrementViewersCount();
        $stream->incrementTotalViews();
        $this->em->flush();
    }

    public function decrementViewer(LiveStream $stream): void
    {
        $stream->decrementViewersCount();
        $this->em->flush();
    }

    public function regenerateStreamKey(LiveStream $stream): void
    {
        $stream->regenerateStreamKey();
        $this->repository->save($stream);
    }

    public function getLiveStreams(int $limit = 20, int $offset = 0): array
    {
        return $this->repository->findLiveStreams($limit, $offset);
    }

    public function getScheduledStreams(int $limit = 20, int $offset = 0): array
    {
        return $this->repository->findScheduledStreams($limit, $offset);
    }

    public function getStreamerStreams(User $streamer, int $limit = 20, int $offset = 0): array
    {
        return $this->repository->findByStreamer($streamer, $limit, $offset);
    }

    public function findByStreamKey(string $streamKey): ?LiveStream
    {
        return $this->repository->findByStreamKey($streamKey);
    }

    public function countLiveStreams(): int
    {
        return $this->repository->countLiveStreams();
    }

    public function getPopularStreams(int $days = 7, int $limit = 10): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        return $this->repository->findPopularStreams($since, $limit);
    }

    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = $this->slugger->slug($title)->lower()->toString();
        $slug = $baseSlug;
        $counter = 1;
        
        while ($this->repository->findOneBy(['slug' => $slug])) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}
