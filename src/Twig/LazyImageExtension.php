<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LazyImageExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('lazy_img', [$this, 'lazyImage'], ['is_safe' => ['html']]),
        ];
    }

    public function lazyImage(
        string $src, 
        string $alt = '', 
        string $class = '', 
        ?int $width = null, 
        ?int $height = null,
        bool $isAvatar = false
    ): string {
        // Используем нативный lazy loading браузера как fallback
        $attributes = [
            'src' => $src,
            'alt' => htmlspecialchars($alt),
            'class' => $class,
            'loading' => 'lazy'
        ];
        
        // Добавляем data-src только если JavaScript доступен
        $attributes['data-lazy-src'] = $src;
        
        if ($width) {
            $attributes['width'] = $width;
        }
        
        if ($height) {
            $attributes['height'] = $height;
        }
        
        // Собираем HTML
        $html = '<img';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . $key . '="' . $value . '"';
        }
        $html .= '>';
        
        return $html;
    }
}