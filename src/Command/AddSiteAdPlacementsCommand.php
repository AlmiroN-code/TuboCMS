<?php

namespace App\Command;

use App\Entity\Ad;
use App\Entity\AdPlacement;
use App\Repository\AdPlacementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-site-ad-placements',
    description: 'Добавляет рекламные места на все страницы сайта'
)]
class AddSiteAdPlacementsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private AdPlacementRepository $placementRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Определяем места размещения для всех страниц
        $placements = [
            // Главная страница
            [
                'name' => 'Главная - Верхний баннер',
                'slug' => 'home_top_banner',
                'type' => 'banner',
                'position' => 'header',
                'width' => 728,
                'height' => 90,
                'allowed_pages' => ['app_home'],
                'description' => 'Горизонтальный баннер в верхней части главной страницы'
            ],
            [
                'name' => 'Главная - Сайдбар верх',
                'slug' => 'home_sidebar_top',
                'type' => 'banner',
                'position' => 'sidebar',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => ['app_home'],
                'description' => 'Квадратный баннер в верхней части сайдбара главной'
            ],
            [
                'name' => 'Главная - Между блоками',
                'slug' => 'home_content_middle',
                'type' => 'banner',
                'position' => 'content',
                'width' => 728,
                'height' => 90,
                'allowed_pages' => ['app_home'],
                'description' => 'Баннер между блоками контента на главной'
            ],
            [
                'name' => 'Главная - Сайдбар низ',
                'slug' => 'home_sidebar_bottom',
                'type' => 'banner',
                'position' => 'sidebar',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => ['app_home'],
                'description' => 'Квадратный баннер в нижней части сайдбара главной'
            ],

            // Страница моделей
            [
                'name' => 'Модели - Верхний баннер',
                'slug' => 'models_top_banner',
                'type' => 'banner',
                'position' => 'header',
                'width' => 728,
                'height' => 90,
                'allowed_pages' => ['app_models'],
                'description' => 'Горизонтальный баннер на странице моделей'
            ],
            [
                'name' => 'Модели - Сайдбар',
                'slug' => 'models_sidebar',
                'type' => 'banner',
                'position' => 'sidebar',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => ['app_models'],
                'description' => 'Квадратный баннер в сайдбаре страницы моделей'
            ],
            [
                'name' => 'Модели - Между карточками',
                'slug' => 'models_between_cards',
                'type' => 'native',
                'position' => 'content',
                'width' => null,
                'height' => null,
                'allowed_pages' => ['app_models'],
                'description' => 'Нативная реклама между карточками моделей'
            ],

            // Страница участников
            [
                'name' => 'Участники - Верхний баннер',
                'slug' => 'members_top_banner',
                'type' => 'banner',
                'position' => 'header',
                'width' => 728,
                'height' => 90,
                'allowed_pages' => ['app_members'],
                'description' => 'Горизонтальный баннер на странице участников'
            ],
            [
                'name' => 'Участники - Сайдбар',
                'slug' => 'members_sidebar',
                'type' => 'banner',
                'position' => 'sidebar',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => ['app_members'],
                'description' => 'Квадратный баннер в сайдбаре страницы участников'
            ],

            // Страница категорий
            [
                'name' => 'Категории - Верхний баннер',
                'slug' => 'categories_top_banner',
                'type' => 'banner',
                'position' => 'header',
                'width' => 728,
                'height' => 90,
                'allowed_pages' => ['app_categories'],
                'description' => 'Горизонтальный баннер на странице категорий'
            ],
            [
                'name' => 'Категории - Сайдбар',
                'slug' => 'categories_sidebar',
                'type' => 'banner',
                'position' => 'sidebar',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => ['app_categories'],
                'description' => 'Квадратный баннер в сайдбаре страницы категорий'
            ],

            // Страницы в категориях
            [
                'name' => 'Категория - Верхний баннер',
                'slug' => 'category_top_banner',
                'type' => 'banner',
                'position' => 'header',
                'width' => 728,
                'height' => 90,
                'allowed_pages' => ['app_category_show'],
                'description' => 'Горизонтальный баннер на странице категории'
            ],
            [
                'name' => 'Категория - Сайдбар',
                'slug' => 'category_sidebar',
                'type' => 'banner',
                'position' => 'sidebar',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => ['app_category_show'],
                'description' => 'Квадратный баннер в сайдбаре страницы категории'
            ],
            [
                'name' => 'Категория - Между видео',
                'slug' => 'category_between_videos',
                'type' => 'native',
                'position' => 'content',
                'width' => null,
                'height' => null,
                'allowed_pages' => ['app_category_show'],
                'description' => 'Нативная реклама между видео в категории'
            ],

            // Футер (все страницы)
            [
                'name' => 'Футер - Горизонтальный баннер',
                'slug' => 'footer_banner',
                'type' => 'banner',
                'position' => 'footer',
                'width' => 728,
                'height' => 90,
                'allowed_pages' => [],
                'description' => 'Горизонтальный баннер перед футером на всех страницах'
            ],
            [
                'name' => 'Футер - Левый блок',
                'slug' => 'footer_left',
                'type' => 'banner',
                'position' => 'footer',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => [],
                'description' => 'Левый квадратный баннер в футере'
            ],
            [
                'name' => 'Футер - Правый блок',
                'slug' => 'footer_right',
                'type' => 'banner',
                'position' => 'footer',
                'width' => 300,
                'height' => 250,
                'allowed_pages' => [],
                'description' => 'Правый квадратный баннер в футере'
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($placements as $placementData) {
            // Проверяем, существует ли уже такое место
            $existing = $this->placementRepository->findBySlug($placementData['slug']);
            if ($existing) {
                $io->note("Место размещения '{$placementData['name']}' уже существует");
                $skipped++;
                continue;
            }

            // Создаем новое место размещения
            $placement = new AdPlacement();
            $placement->setName($placementData['name']);
            $placement->setSlug($placementData['slug']);
            $placement->setType($placementData['type']);
            $placement->setPosition($placementData['position']);
            $placement->setWidth($placementData['width']);
            $placement->setHeight($placementData['height']);
            $placement->setAllowedPages($placementData['allowed_pages']);
            $placement->setDescription($placementData['description']);
            $placement->setIsActive(true);
            $placement->setOrderPosition($created + 1);
            $placement->setCreatedAt(new \DateTimeImmutable());
            $placement->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($placement);
            $created++;

            $io->text("✓ Создано место размещения: {$placementData['name']}");
        }

        $this->em->flush();

        // Создаем тестовые объявления для новых мест
        $this->createTestAds($io);

        $io->success("Обработка завершена! Создано: {$created}, пропущено: {$skipped}");
        
        return Command::SUCCESS;
    }

    private function createTestAds(SymfonyStyle $io): void
    {
        $io->section('Создание тестовых объявлений');

        // Получаем все места размещения
        $placements = $this->placementRepository->findAll();
        
        $testAds = [
            [
                'name' => 'Премиум подписка RexTube',
                'description' => 'Получите доступ к эксклюзивному контенту без рекламы',
                'format' => Ad::FORMAT_IMAGE,
                'image_url' => 'https://via.placeholder.com/728x90/4F46E5/FFFFFF?text=Premium+Subscription',
                'click_url' => '/premium',
                'alt_text' => 'Премиум подписка RexTube'
            ],
            [
                'name' => 'Загрузите свои видео',
                'description' => 'Станьте создателем контента на RexTube',
                'format' => Ad::FORMAT_IMAGE,
                'image_url' => 'https://via.placeholder.com/300x250/10B981/FFFFFF?text=Upload+Videos',
                'click_url' => '/videos/upload',
                'alt_text' => 'Загрузите свои видео'
            ],
            [
                'name' => 'Лучшие модели месяца',
                'description' => 'Откройте для себя самых популярных создателей',
                'format' => Ad::FORMAT_IMAGE,
                'image_url' => 'https://via.placeholder.com/728x90/EF4444/FFFFFF?text=Top+Models',
                'click_url' => '/models/top',
                'alt_text' => 'Лучшие модели месяца'
            ],
            [
                'name' => 'Мобильное приложение',
                'description' => 'Скачайте приложение RexTube для мобильных устройств',
                'format' => Ad::FORMAT_IMAGE,
                'image_url' => 'https://via.placeholder.com/300x250/8B5CF6/FFFFFF?text=Mobile+App',
                'click_url' => '/mobile-app',
                'alt_text' => 'Мобильное приложение RexTube'
            ],
            [
                'name' => 'Партнерская программа',
                'description' => 'Зарабатывайте с нашей партнерской программой',
                'format' => Ad::FORMAT_IMAGE,
                'image_url' => 'https://via.placeholder.com/728x90/F59E0B/FFFFFF?text=Affiliate+Program',
                'click_url' => '/affiliate',
                'alt_text' => 'Партнерская программа'
            ],
            [
                'name' => 'VIP статус',
                'description' => 'Получите VIP статус и эксклюзивные привилегии',
                'format' => Ad::FORMAT_IMAGE,
                'image_url' => 'https://via.placeholder.com/300x250/EC4899/FFFFFF?text=VIP+Status',
                'click_url' => '/vip',
                'alt_text' => 'VIP статус'
            ]
        ];

        $createdAds = 0;
        
        foreach ($placements as $placement) {
            // Проверяем, есть ли уже объявления для этого места
            if (!$placement->getAds()->isEmpty()) {
                continue;
            }

            // Выбираем случайное тестовое объявление
            $adData = $testAds[array_rand($testAds)];
            
            $ad = new Ad();
            $ad->setName($adData['name'] . ' - ' . $placement->getName());
            $ad->setDescription($adData['description']);
            $ad->setFormat($adData['format']);
            $ad->setImageUrl($adData['image_url']);
            $ad->setClickUrl($adData['click_url']);
            $ad->setAltText($adData['alt_text']);
            $ad->setPlacement($placement);
            $ad->setStatus(Ad::STATUS_ACTIVE);
            $ad->setIsActive(true);
            $ad->setPriority(1);
            $ad->setWeight(100);
            $ad->setOpenInNewTab(true);
            $ad->setCreatedAt(new \DateTimeImmutable());
            $ad->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($ad);
            $createdAds++;
            
            $io->text("✓ Создано объявление для: {$placement->getName()}");
        }

        if ($createdAds > 0) {
            $this->em->flush();
            $io->success("Создано {$createdAds} тестовых объявлений");
        } else {
            $io->note('Тестовые объявления уже существуют');
        }
    }
}