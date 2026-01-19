<?php

namespace App\Twig\Components;

use App\Repository\VideoRepository;
use App\Repository\UserRepository;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('stats_chart')]
class StatsChart
{
    public string $type = 'views'; // views, uploads, users
    public int $days = 30;

    public function __construct(
        private ChartBuilderInterface $chartBuilder,
        private VideoRepository $videoRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function getChart(): Chart
    {
        return match ($this->type) {
            'views' => $this->buildViewsChart(),
            'uploads' => $this->buildUploadsChart(),
            'users' => $this->buildUsersChart(),
            default => $this->buildViewsChart(),
        };
    }

    private function buildViewsChart(): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $data = $this->getViewsData();

        $chart->setData([
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Просмотры',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'data' => $data['values'],
                    'fill' => true,
                    'tension' => 0.4,
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

    private function buildUploadsChart(): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $data = $this->getUploadsData();

        $chart->setData([
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Загрузки',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => 'rgb(16, 185, 129)',
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

    private function buildUsersChart(): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $data = $this->getUsersData();

        $chart->setData([
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Новые пользователи',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'borderColor' => 'rgb(139, 92, 246)',
                    'data' => $data['values'],
                    'fill' => true,
                    'tension' => 0.4,
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

    private function getViewsData(): array
    {
        $labels = [];
        $values = [];
        
        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-{$i} days");
            $labels[] = $date->format('d.m');
            // Здесь должен быть реальный запрос к статистике просмотров
            $values[] = rand(100, 1000); // Заглушка
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function getUploadsData(): array
    {
        $labels = [];
        $values = [];
        
        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-{$i} days");
            $labels[] = $date->format('d.m');
            
            $count = $this->videoRepository->createQueryBuilder('v')
                ->select('COUNT(v.id)')
                ->where('v.createdAt >= :start')
                ->andWhere('v.createdAt < :end')
                ->setParameter('start', $date->setTime(0, 0, 0))
                ->setParameter('end', $date->setTime(23, 59, 59))
                ->getQuery()
                ->getSingleScalarResult();
            
            $values[] = (int) $count;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function getUsersData(): array
    {
        $labels = [];
        $values = [];
        
        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-{$i} days");
            $labels[] = $date->format('d.m');
            
            $count = $this->userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.createdAt >= :start')
                ->andWhere('u.createdAt < :end')
                ->setParameter('start', $date->setTime(0, 0, 0))
                ->setParameter('end', $date->setTime(23, 59, 59))
                ->getQuery()
                ->getSingleScalarResult();
            
            $values[] = (int) $count;
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
