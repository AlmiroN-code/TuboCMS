<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RatingExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('rating_percent', [$this, 'getRatingPercent']),
            new TwigFilter('rating_emoji', [$this, 'getRatingEmoji']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rating_info', [$this, 'getRatingInfo']),
        ];
    }

    /**
     * Рассчитывает процент лайков
     */
    public function getRatingPercent(int $likes, int $dislikes): int
    {
        $total = $likes + $dislikes;
        
        if ($total === 0) {
            return 50; // Нейтральный рейтинг если нет голосов
        }
        
        return (int) round(($likes / $total) * 100);
    }

    /**
     * Возвращает эмодзи в зависимости от процента рейтинга
     */
    public function getRatingEmoji(int $percent): string
    {
        if ($percent >= 80) {
            return '😊'; // Радостный (80-100%)
        } elseif ($percent >= 50) {
            return '😐'; // Нейтральный (50-79%)
        } else {
            return '😞'; // Грустный (0-49%)
        }
    }

    /**
     * Возвращает полную информацию о рейтинге (процент + эмодзи)
     */
    public function getRatingInfo(int $likes, int $dislikes): array
    {
        $percent = $this->getRatingPercent($likes, $dislikes);
        $emoji = $this->getRatingEmoji($percent);
        
        return [
            'percent' => $percent,
            'emoji' => $emoji,
            'total' => $likes + $dislikes,
        ];
    }
}
