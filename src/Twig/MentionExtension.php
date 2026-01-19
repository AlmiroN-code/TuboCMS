<?php

namespace App\Twig;

use App\Service\MentionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MentionExtension extends AbstractExtension
{
    public function __construct(
        private MentionService $mentionService
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_mentions', [$this, 'formatMentions'], ['is_safe' => ['html']]),
        ];
    }

    public function formatMentions(string $text): string
    {
        // First escape HTML, then format mentions
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return $this->mentionService->formatMentions($escaped);
    }
}
