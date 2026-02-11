<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\VideoRepository;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('analytics_chart')]
class AnalyticsChart
{
    public string $type = 'engagement';
    public int $days = 30;

    public function __construct(
        private ChartBuilderInterface $chartBuilder,
        private VideoRepository $videoRepository,
    ) {}

    public function getChart(): Chart
    {
        return match ($this->type) {
            'engagement' => $this->buildEngagementChart(),
            'duration' => $this->buildDurationChart(),
            'status' => $this->buildStatusChart(),
            default => $this->buildEngagementChart(),
        };
    }

    private function buildEngagementChart(): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $data = $this->getEngagementData();

        $chart->setData([
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Вовлеченность',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.8)',
                    'borderColor' => 'rgb(139, 92, 246)',
                    'data' => $data['values'],
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ]);

        return $chart;
    }

    private function buildDurationChart(): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $data = $this->getDurationData();

        $chart->setData([
            'labels' => ['Короткие (< 5 мин)', 'Средние (5-20 мин)', 'Длинные (> 20 мин)'],
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(139, 92, 246)',
                    ],
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'font' => ['size' => 11],
                    ],
                ],
            ],
        ]);

        return $chart;
    }

    private function buildStatusChart(): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $data = $this->getStatusData();

        $chart->setData([
            'labels' => array_keys($data),
            'datasets' => [
                [
                    'label' => 'Количество',
                    'data' => array_values($data),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ]);

        return $chart;
    }

    private function getEngagementData(): array
    {
        $labels = [];
        $values = [];
        
        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-{$i} days");
            $labels[] = $date->format('d.m');
            
            $result = $this->videoRepository->createQueryBuilder('v')
                ->select('SUM(v.likesCount) + COUNT(c.id) as engagement')
                ->leftJoin('v.comments', 'c')
                ->where('v.createdAt >= :start')
                ->andWhere('v.createdAt < :end')
                ->setParameter('start', $date->setTime(0, 0, 0))
                ->setParameter('end', $date->setTime(23, 59, 59))
                ->getQuery()
                ->getSingleScalarResult();
            
            $values[] = (int) ($result ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function getDurationData(): array
    {
        $result = $this->videoRepository->createQueryBuilder('v')
            ->select('
                SUM(CASE WHEN v.duration < 300 THEN 1 ELSE 0 END) as short,
                SUM(CASE WHEN v.duration >= 300 AND v.duration < 1200 THEN 1 ELSE 0 END) as medium,
                SUM(CASE WHEN v.duration >= 1200 THEN 1 ELSE 0 END) as long
            ')
            ->where('v.status = :status')
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleResult();
        
        return [
            (int) $result['short'],
            (int) $result['medium'],
            (int) $result['long'],
        ];
    }

    private function getStatusData(): array
    {
        $conn = $this->videoRepository->getEntityManager()->getConnection();
        
        $sql = "SELECT status, COUNT(*) as count FROM video GROUP BY status";
        $result = $conn->executeQuery($sql);
        
        $stats = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $stats[ucfirst($row['status'])] = (int) $row['count'];
        }
        
        return $stats;
    }
}
