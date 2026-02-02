<?php

declare(strict_types=1);

namespace App\Entity\MaterializedView;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'video_stats_daily_view')]
class VideoStatsDailyView
{
    #[ORM\Id]
    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column]
    private int $totalVideos = 0;

    #[ORM\Column]
    private int $totalViews = 0;

    #[ORM\Column]
    private int $totalUploads = 0;

    #[ORM\Column]
    private int $totalComments = 0;

    #[ORM\Column]
    private int $totalLikes = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $avgDuration = '0.00';

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function getTotalVideos(): int
    {
        return $this->totalVideos;
    }

    public function getTotalViews(): int
    {
        return $this->totalViews;
    }

    public function getTotalUploads(): int
    {
        return $this->totalUploads;
    }

    public function getTotalComments(): int
    {
        return $this->totalComments;
    }

    public function getTotalLikes(): int
    {
        return $this->totalLikes;
    }

    public function getAvgDuration(): string
    {
        return $this->avgDuration;
    }
}