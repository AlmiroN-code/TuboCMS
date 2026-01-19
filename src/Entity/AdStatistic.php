<?php

namespace App\Entity;

use App\Repository\AdStatisticRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdStatisticRepository::class)]
#[ORM\Table(name: 'ad_statistic')]
#[ORM\Index(columns: ['ad_id', 'date'], name: 'idx_ad_stat_ad_date')]
#[ORM\Index(columns: ['date'], name: 'idx_ad_stat_date')]
#[ORM\UniqueConstraint(name: 'unique_ad_date', columns: ['ad_id', 'date'])]
class AdStatistic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ad::class, inversedBy: 'statistics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ad $ad = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column]
    private ?int $impressions = 0;

    #[ORM\Column]
    private ?int $clicks = 0;

    #[ORM\Column]
    private ?int $uniqueImpressions = 0;

    #[ORM\Column]
    private ?int $uniqueClicks = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $spent = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $revenue = '0.00';

    #[ORM\Column]
    private ?int $conversions = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $hourlyData = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $geoData = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $deviceData = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAd(): ?Ad
    {
        return $this->ad;
    }

    public function setAd(?Ad $ad): static
    {
        $this->ad = $ad;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getImpressions(): ?int
    {
        return $this->impressions;
    }

    public function setImpressions(int $impressions): static
    {
        $this->impressions = $impressions;
        return $this;
    }

    public function incrementImpressions(): static
    {
        $this->impressions++;
        return $this;
    }

    public function getClicks(): ?int
    {
        return $this->clicks;
    }

    public function setClicks(int $clicks): static
    {
        $this->clicks = $clicks;
        return $this;
    }

    public function incrementClicks(): static
    {
        $this->clicks++;
        return $this;
    }

    public function getUniqueImpressions(): ?int
    {
        return $this->uniqueImpressions;
    }

    public function setUniqueImpressions(int $uniqueImpressions): static
    {
        $this->uniqueImpressions = $uniqueImpressions;
        return $this;
    }

    public function getUniqueClicks(): ?int
    {
        return $this->uniqueClicks;
    }

    public function setUniqueClicks(int $uniqueClicks): static
    {
        $this->uniqueClicks = $uniqueClicks;
        return $this;
    }

    public function getSpent(): ?string
    {
        return $this->spent;
    }

    public function setSpent(string $spent): static
    {
        $this->spent = $spent;
        return $this;
    }

    public function getRevenue(): ?string
    {
        return $this->revenue;
    }

    public function setRevenue(string $revenue): static
    {
        $this->revenue = $revenue;
        return $this;
    }

    public function getConversions(): ?int
    {
        return $this->conversions;
    }

    public function setConversions(int $conversions): static
    {
        $this->conversions = $conversions;
        return $this;
    }

    public function getHourlyData(): ?array
    {
        return $this->hourlyData;
    }

    public function setHourlyData(?array $hourlyData): static
    {
        $this->hourlyData = $hourlyData;
        return $this;
    }

    public function getGeoData(): ?array
    {
        return $this->geoData;
    }

    public function setGeoData(?array $geoData): static
    {
        $this->geoData = $geoData;
        return $this;
    }

    public function getDeviceData(): ?array
    {
        return $this->deviceData;
    }

    public function setDeviceData(?array $deviceData): static
    {
        $this->deviceData = $deviceData;
        return $this;
    }

    public function getCtr(): float
    {
        if ($this->impressions === 0) {
            return 0.0;
        }
        return round(($this->clicks / $this->impressions) * 100, 2);
    }

    public function getConversionRate(): float
    {
        if ($this->clicks === 0) {
            return 0.0;
        }
        return round(($this->conversions / $this->clicks) * 100, 2);
    }
}
