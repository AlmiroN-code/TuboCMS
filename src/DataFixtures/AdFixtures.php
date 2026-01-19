<?php

namespace App\DataFixtures;

use App\Entity\AdPlacement;
use App\Entity\Ad;
use App\Entity\AdCampaign;
use App\Entity\AdSegment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AdFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Создание мест размещения
        $placements = [
            [
                'name' => 'Шапка сайта',
                'slug' => 'header',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_HEADER,
                'width' => 728,
                'height' => 90,
                'description' => 'Горизонтальный баннер в верхней части сайта'
            ],
            [
                'name' => 'Сайдбар',
                'slug' => 'sidebar',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_SIDEBAR,
                'width' => 300,
                'height' => 250,
                'description' => 'Прямоугольный баннер в боковой панели'
            ],
            [
                'name' => 'Между видео',
                'slug' => 'between_videos',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_BETWEEN_VIDEOS,
                'width' => 728,
                'height' => 90,
                'description' => 'Баннер между списком видео'
            ],
            [
                'name' => 'Подвал',
                'slug' => 'footer',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_FOOTER,
                'width' => 728,
                'height' => 90,
                'description' => 'Баннер в нижней части страницы'
            ],
            [
                'name' => 'Preroll видео',
                'slug' => 'video_preroll',
                'type' => AdPlacement::TYPE_VAST,
                'position' => AdPlacement::POSITION_VIDEO_PREROLL,
                'description' => 'Видеореклама перед основным видео'
            ],
            [
                'name' => 'Overlay видео',
                'slug' => 'video_overlay',
                'type' => AdPlacement::TYPE_BANNER,
                'position' => AdPlacement::POSITION_VIDEO_OVERLAY,
                'width' => 300,
                'height' => 60,
                'description' => 'Баннер поверх видеоплеера'
            ],
            // Новые места размещения для страницы видео
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

        $placementObjects = [];
        foreach ($placements as $placementData) {
            $placement = new AdPlacement();
            $placement->setName($placementData['name']);
            $placement->setSlug($placementData['slug']);
            $placement->setType($placementData['type']);
            $placement->setPosition($placementData['position']);
            $placement->setWidth($placementData['width'] ?? null);
            $placement->setHeight($placementData['height'] ?? null);
            $placement->setDescription($placementData['description']);
            $placement->setIsActive(true);
            $placement->setOrderPosition(count($placementObjects));
            
            // Устанавливаем разрешенные страницы, если указаны
            if (isset($placementData['allowedPages'])) {
                $placement->setAllowedPages($placementData['allowedPages']);
            }

            $manager->persist($placement);
            $placementObjects[] = $placement;
        }

        // Создание сегментов аудитории
        $segments = [
            [
                'name' => 'Новые пользователи',
                'slug' => 'new_users',
                'type' => AdSegment::TYPE_BEHAVIOR,
                'description' => 'Пользователи, зарегистрированные менее 30 дней назад',
                'rules' => ['registration_days' => ['<' => 30]]
            ],
            [
                'name' => 'Активные пользователи',
                'slug' => 'active_users',
                'type' => AdSegment::TYPE_BEHAVIOR,
                'description' => 'Пользователи с высокой активностью',
                'rules' => ['videos_watched' => ['>' => 10]]
            ],
            [
                'name' => 'Мобильные пользователи',
                'slug' => 'mobile_users',
                'type' => AdSegment::TYPE_DEMOGRAPHIC,
                'description' => 'Пользователи с мобильных устройств',
                'rules' => ['device' => 'mobile']
            ]
        ];

        $segmentObjects = [];
        foreach ($segments as $segmentData) {
            $segment = new AdSegment();
            $segment->setName($segmentData['name']);
            $segment->setSlug($segmentData['slug']);
            $segment->setType($segmentData['type']);
            $segment->setDescription($segmentData['description']);
            $segment->setRules($segmentData['rules']);
            $segment->setIsActive(true);
            $segment->setUsersCount(rand(100, 5000));

            $manager->persist($segment);
            $segmentObjects[] = $segment;
        }

        // Создание тестовой кампании
        $campaign = new AdCampaign();
        $campaign->setName('Тестовая кампания');
        $campaign->setDescription('Демонстрационная рекламная кампания');
        $campaign->setStatus(AdCampaign::STATUS_ACTIVE);
        $campaign->setStartDate(new \DateTime('-7 days'));
        $campaign->setEndDate(new \DateTime('+30 days'));
        $campaign->setTotalBudget('10000.00');
        $campaign->setDailyBudget('300.00');

        $manager->persist($campaign);

        // Создание тестовых объявлений
        $ads = [
            [
                'name' => 'Баннер в шапке',
                'placement' => $placementObjects[0], // header
                'format' => Ad::FORMAT_HTML,
                'content' => '<div style="background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px;">
                    <h3 style="margin: 0; font-size: 18px;">🎬 Премиум контент</h3>
                    <p style="margin: 5px 0 0 0; font-size: 14px;">Откройте доступ к эксклюзивным видео</p>
                </div>',
                'clickUrl' => 'https://example.com/premium'
            ],
            [
                'name' => 'Баннер в сайдбаре',
                'placement' => $placementObjects[1], // sidebar
                'format' => Ad::FORMAT_HTML,
                'content' => '<div style="background: #f8f9fa; border: 2px dashed #dee2e6; padding: 30px; text-align: center; border-radius: 8px;">
                    <div style="font-size: 24px; margin-bottom: 10px;">📱</div>
                    <h4 style="margin: 0 0 8px 0; color: #495057;">Мобильное приложение</h4>
                    <p style="margin: 0; font-size: 12px; color: #6c757d;">Скачайте наше приложение</p>
                </div>',
                'clickUrl' => 'https://example.com/app'
            ],
            [
                'name' => 'Баннер между видео',
                'placement' => $placementObjects[2], // between_videos
                'format' => Ad::FORMAT_HTML,
                'content' => '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <span style="font-size: 16px; font-weight: bold;">🎯 Реклама здесь • Свяжитесь с нами для размещения</span>
                </div>',
                'clickUrl' => 'https://example.com/advertise'
            ],
            // Новые объявления для страницы видео
            [
                'name' => 'Баннер перед плеером',
                'placement' => $placementObjects[6], // video_before_player
                'format' => Ad::FORMAT_HTML,
                'content' => '<div style="background: linear-gradient(90deg, #ff6b6b 0%, #ee5a24 100%); color: white; padding: 15px; text-align: center; border-radius: 8px; margin: 10px 0;">
                    <span style="font-size: 16px; font-weight: bold;">🔥 Горячие предложения • Не пропустите!</span>
                </div>',
                'clickUrl' => 'https://example.com/hot-deals'
            ],
            [
                'name' => 'Сайдбар видео - верхний блок',
                'placement' => $placementObjects[7], // video_sidebar_top
                'format' => Ad::FORMAT_HTML,
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
            [
                'name' => 'Сайдбар видео - средний блок',
                'placement' => $placementObjects[8], // video_sidebar_middle
                'format' => Ad::FORMAT_HTML,
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
            [
                'name' => 'Сайдбар видео - нижний блок',
                'placement' => $placementObjects[9], // video_sidebar_bottom
                'format' => Ad::FORMAT_HTML,
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
            [
                'name' => 'После описания видео',
                'placement' => $placementObjects[10], // video_after_description
                'format' => Ad::FORMAT_HTML,
                'content' => '<div style="background: linear-gradient(45deg, #ffecd2 0%, #fcb69f 100%); padding: 15px; text-align: center; border-radius: 8px; margin: 15px 0;">
                    <span style="font-size: 16px; font-weight: bold; color: #8b4513;">☕ Кофе-брейк • Время для рекламы</span>
                </div>',
                'clickUrl' => 'https://example.com/coffee'
            ],
            [
                'name' => 'Перед похожими видео',
                'placement' => $placementObjects[11], // video_before_related
                'format' => Ad::FORMAT_HTML,
                'content' => '<div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <span style="font-size: 16px; font-weight: bold; color: #2c3e50;">🎯 Рекомендуем • Специально для вас</span>
                </div>',
                'clickUrl' => 'https://example.com/recommendations'
            ]
        ];

        foreach ($ads as $adData) {
            $ad = new Ad();
            $ad->setName($adData['name']);
            $ad->setFormat($adData['format']);
            $ad->setHtmlContent($adData['content']);
            $ad->setClickUrl($adData['clickUrl']);
            $ad->setPlacement($adData['placement']);
            $ad->setCampaign($campaign);
            $ad->setStatus(Ad::STATUS_ACTIVE);
            $ad->setIsActive(true);
            $ad->setOpenInNewTab(true);
            $ad->setPriority(rand(1, 10));
            $ad->setWeight(100);
            $ad->setStartDate(new \DateTime('-1 day'));
            $ad->setEndDate(new \DateTime('+30 days'));

            // Добавляем случайные сегменты
            if (rand(0, 1)) {
                $ad->addSegment($segmentObjects[array_rand($segmentObjects)]);
            }

            // Симуляция статистики
            $ad->setImpressionsCount(rand(1000, 50000));
            $ad->setClicksCount(rand(50, 2000));
            $ad->setUniqueImpressionsCount(rand(800, 40000));
            $ad->setUniqueClicksCount(rand(40, 1500));
            $ad->setSpentAmount((string)(rand(100, 5000) / 100));

            $manager->persist($ad);
        }

        $manager->flush();
    }
}