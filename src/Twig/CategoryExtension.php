<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(
        private CategoryRepository $categoryRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_categories', [$this, 'getCategories']),
            new TwigFunction('get_sidebar_categories', [$this, 'getSidebarCategories']),
        ];
    }

    public function getCategories(): array
    {
        return $this->categoryRepository->findBy(
            ['isActive' => true],
            ['name' => 'ASC']
        );
    }

    public function getSidebarCategories(int $limit = 20): array
    {
        return $this->categoryRepository->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.videosCount', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}