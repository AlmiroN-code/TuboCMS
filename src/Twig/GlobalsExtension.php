<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class GlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository
    ) {
    }

    public function getGlobals(): array
    {
        // Сортировка: сначала по orderPosition (если указан > 0), затем по алфавиту
        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('CASE WHEN c.orderPosition > 0 THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('c.orderPosition', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return [
            'global_sidebar_categories' => $categories,
        ];
    }
}