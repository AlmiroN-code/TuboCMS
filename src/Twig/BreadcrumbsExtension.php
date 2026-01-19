<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class BreadcrumbsExtension extends AbstractExtension implements GlobalsInterface
{
    private array $breadcrumbs = [];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('add_breadcrumb', [$this, 'addBreadcrumb']),
            new TwigFunction('set_breadcrumbs', [$this, 'setBreadcrumbs']),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'breadcrumbs' => $this->breadcrumbs,
        ];
    }

    public function addBreadcrumb(string $label, string $url): void
    {
        $this->breadcrumbs[] = [
            'label' => $label,
            'url' => $url,
        ];
    }

    public function setBreadcrumbs(array $breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }
}
