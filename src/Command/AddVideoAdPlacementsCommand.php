<?php

namespace App\Command;

use App\Entity\AdPlacement;
use App\Entity\Ad;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-video-ad-placements',
    description: 'Добавляет новые места размещения рекламы для страницы видео'
)]
class AddVideoAdPlacementsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Новые места размещения для страницы видео
        $newPlacements = [
            [
                'name' => 'Перед видеоплеером',
                'slug' => 'video_before_player',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_CONTENT,
                'width' => 728,
                'height' => 90,
                'description' => 'Горизонтальный баннер перед видеоплеером',
                'allowedPages' => ['video_detail']
            ],
            [
                'name' => 'Сайдбар видео - верх',
                'slug' => 'video_sidebar_top',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_SIDEBAR,
                'width' => 300,
                'height' => 250,
                'description' => 'Баннер в верхней части сайдбара на странице видео',
                'allowedPages' => ['video_detail']
            ],
            [
                'name' => 'Сайдбар видео - середина',
                'slug' => 'video_sidebar_middle',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_SIDEBAR,
                'width' => 300,
                'height' => 600,
                'description' => 'Вертикальный баннер в середине сайдбара на странице видео',
                'allowedPages' => ['video_detail']
            ],
            [
                'name' => 'Сайдбар видео - низ',
                'slug' => 'video_sidebar_bottom',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_SIDEBAR,
                'width' => 300,
                'height' => 250,
                'description' => 'Sticky баннер в нижней части сайдбара на странице видео',
                'allowedPages' => ['video_detail']
            ],
            [
                'name' => 'После описания видео',
                'slug' => 'video_after_description',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_CONTENT,
                'width' => 728,
                'height' => 90,
                'description' => 'Горизонтальный баннер после описания видео',
                'allowedPages' => ['video_detail']
            ],
            [
                'name' => 'Перед похожими видео',
                'slug' => 'video_before_related',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_CONTENT,
                'width' => 728,
                'height' => 90,
                'description' => 'Горизонтальный баннер перед блоком похожих видео',
                'allowedPages' => ['video_detail']
            ]
        ];

        $placementRepository = $this->em->getRepository(AdPlacement::class);
        $createdPlacements = [];
        $skippedCount = 0;

        foreach ($newPlacements as $placementData) {
            // Проверяем, существует ли уже место размещения с таким slug
            $existing = $placementRepository->findOneBy(['slug' => $placementData['slug']]);
            if ($existing) {
                $io->warning("Место размещения '{$placementData['slug']}' уже существует, пропускаем");
                $skippedCount++;
                continue;
            }

            $placement = new AdPlacement();
            $placement->setName($placementData['name']);
            $placement->setSlug($placementData['slug']);
            $placement->setType($placementData['type']);
            $placement->setPosition($placementData['position']);
            $placement->setWidth($placementData['width'] ?? null);
            $placement->setHeight($placementData['height'] ?? null);
            $placement->setDescription($placementData['description']);
            $placement->setIsActive(true);
            $placement->setOrderPosition(0);
            $placement->setAllowedPages($placementData['allowedPages']);

            $this->em->persist($placement);
            $createdPlacements[] = $placement;
            
            $io->text("Создано место размещения: {$placementData['name']}");
        }

        if (!empty($createdPlacements)) {
            $this->em->flush();
            $io->success(sprintf('Создано %d новых мест размещения для страницы видео', count($createdPlacements)));

            // Создаем тестовые объявления для новых мест размещения
            $this->createTestAds($createdPlacements, $io);
        } else {
            $io->info('Все места размещения уже существуют');
        }

        if ($skippedCount > 0) {
            $io->note("Пропущено {$skippedCount} мест размещения (уже существуют)");
        }

        return Command::SUCCESS;
    }

    private function createTestAds(array $placements, SymfonyStyle $io): void
    {
        $testAds = [
            'video_before_player' => [
                'name' => 'Баннер перед плеером',
                'content' => '<div style="background: linear-gradient(90deg, #ff6b6b 0%, #ee5a24 100%); color: white; padding: 15px; text-align: center; border-radius: 8px; margin: 10px 0;">
                    <span style="font-size: 16px; font-weight: bold;">🔥 Горячие предложения • Не пропустите!</span>
                </div>',
                'clickUrl' => 'https://example.com/hot-deals'
            ],
            'video_sidebar_top' => [
                'name' => 'Сайдбар видео - верхний блок',
                'content' => '<div style="background: #ffffff; border: 1px solid #e9ecef; padding: 20px; text-align: center; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; margin-bottom: 10px;">💎</div>
                    <h4 style="margin: 0 0 8px 0; color: #212529; font-size: 16px;">VIP подписка</h4>
                    <p style="margin: 0 0 12px 0; font-size: 12px; color: #6c757d;">Безлимитный доступ ко всему контенту</p>
                    <div style="background: #28a745; color: white; padding: 8px 16px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                        Попробовать бесплатно
                    </div>
                </div>',
                'clickUrl' => 'https://example.com/vip'
            ],
            'video_sidebar_middle' => [
                'name' => 'Сайдбар видео - средний блок',
                'content' => '<div style="background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px; height: 580px; display: flex; flex-direction: column; justify-content: center;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🎮</div>
                    <h3 style="margin: 0 0 15px 0; font-size: 20px;">Игровая зона</h3>
                    <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.4;">Откройте для себя мир интерактивных развлечений</p>
                    <div style="background: rgba(255,255,255,0.2); padding: 12px 20px; border-radius: 6px; font-weight: bold;">
                        Играть сейчас
                    </div>
                </div>',
                'clickUrl' => 'https://example.com/games'
            ],
            'video_sidebar_bottom' => [
                'name' => 'Сайдбар видео - нижний блок',
                'content' => '<div style="background: #f8f9fa; border: 2px solid #dee2e6; padding: 20px; text-align: center; border-radius: 8px;">
                    <div style="font-size: 28px; margin-bottom: 10px;">📧</div>
                    <h4 style="margin: 0 0 8px 0; color: #495057; font-size: 14px;">Подписка на новости</h4>
                    <p style="margin: 0 0 12px 0; font-size: 11px; color: #6c757d;">Получайте уведомления о новых видео</p>
                    <div style="background: #007bff; color: white; padding: 6px 12px; border-radius: 4px; font-size: 11px;">
                        Подписаться
                    </div>
                </div>',
                'clickUrl' => 'https://example.com/subscribe'
            ],
            'video_after_description' => [
                'name' => 'После описания видео',
                'content' => '<div style="background: linear-gradient(45deg, #ffecd2 0%, #fcb69f 100%); padding: 15px; text-align: center; border-radius: 8px; margin: 15px 0;">
                    <span style="font-size: 16px; font-weight: bold; color: #8b4513;">☕ Кофе-брейк • Время для рекламы</span>
                </div>',
                'clickUrl' => 'https://example.com/coffee'
            ],
            'video_before_related' => [
                'name' => 'Перед похожими видео',
                'content' => '<div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <span style="font-size: 16px; font-weight: bold; color: #2c3e50;">🎯 Рекомендуем • Специально для вас</span>
                </div>',
                'clickUrl' => 'https://example.com/recommendations'
            ]
        ];

        $createdAds = 0;
        foreach ($placements as $placement) {
            $adData = $testAds[$placement->getSlug()] ?? null;
            if (!$adData) {
                continue;
            }

            $ad = new Ad();
            $ad->setName($adData['name']);
            $ad->setFormat(Ad::FORMAT_HTML);
            $ad->setHtmlContent($adData['content']);
            $ad->setClickUrl($adData['clickUrl']);
            $ad->setPlacement($placement);
            $ad->setStatus(Ad::STATUS_ACTIVE);
            $ad->setIsActive(true);
            $ad->setOpenInNewTab(true);
            $ad->setPriority(5);
            $ad->setWeight(100);
            $ad->setStartDate(new \DateTime('-1 day'));
            $ad->setEndDate(new \DateTime('+30 days'));

            // Симуляция статистики
            $ad->setImpressionsCount(rand(500, 5000));
            $ad->setClicksCount(rand(25, 250));
            $ad->setUniqueImpressionsCount(rand(400, 4000));
            $ad->setUniqueClicksCount(rand(20, 200));
            $ad->setSpentAmount((string)(rand(50, 500) / 100));

            $this->em->persist($ad);
            $createdAds++;
        }

        if ($createdAds > 0) {
            $this->em->flush();
            $io->success("Создано {$createdAds} тестовых объявлений");
        }
    }
}