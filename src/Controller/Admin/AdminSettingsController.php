<?php

namespace App\Controller\Admin;

use App\Entity\VideoEncodingProfile;
use App\Repository\VideoEncodingProfileRepository;
use App\Service\CategoryPosterService;
use App\Service\ImageService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class AdminSettingsController extends AbstractController
{
    public function __construct(
        private VideoEncodingProfileRepository $profileRepository,
        private EntityManagerInterface $em,
        private SettingsService $settingsService,
        private CategoryPosterService $categoryPosterService,
        private ImageService $imageService
    ) {
    }

    #[Route('', name: 'admin_settings')]
    public function index(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            // Обработка загрузки логотипа
            $logoFile = $request->files->get('site_logo');
            if ($logoFile && $logoFile->isValid()) {
                try {
                    $logoFileName = $this->imageService->processSiteLogo($logoFile);
                    $settingsRepo->setValue('site_logo', '/media/site/' . $logoFileName, 'string', 'Логотип сайта');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Ошибка при загрузке логотипа: ' . $e->getMessage());
                }
            }
            
            // Обработка загрузки фавикона
            $faviconFile = $request->files->get('site_favicon');
            if ($faviconFile && $faviconFile->isValid()) {
                try {
                    $faviconFileName = $this->imageService->processSiteFavicon($faviconFile);
                    $settingsRepo->setValue('site_favicon', '/media/site/' . $faviconFileName, 'string', 'Фавикон сайта');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Ошибка при загрузке фавикона: ' . $e->getMessage());
                }
            }
            
            // Общие настройки
            $settingsRepo->setValue('site_name', $request->request->get('site_name'), 'string', 'Название сайта');
            $settingsRepo->setValue('append_site_name_to_title', $request->request->get('append_site_name_to_title') === '1', 'boolean', 'Добавлять название сайта в title');
            $settingsRepo->setValue('site_description', $request->request->get('site_description'), 'string', 'Описание сайта');
            $settingsRepo->setValue('site_keywords', $request->request->get('site_keywords'), 'string', 'Ключевые слова');
            $settingsRepo->setValue('contact_email', $request->request->get('contact_email'), 'string', 'Email для связи');
            
            // Настройки видео
            $settingsRepo->setValue('max_video_size', $request->request->get('max_video_size'), 'integer', 'Максимальный размер видео (MB)');
            $settingsRepo->setValue('allowed_video_formats', $request->request->get('allowed_video_formats'), 'string', 'Разрешенные форматы видео');
            $settingsRepo->setValue('videos_per_page', $request->request->get('videos_per_page'), 'integer', 'Видео на странице');
            
            // Настройки регистрации
            $settingsRepo->setValue('registration_enabled', $request->request->get('registration_enabled') === '1', 'boolean', 'Разрешить регистрацию');
            $settingsRepo->setValue('email_verification_required', $request->request->get('email_verification_required') === '1', 'boolean', 'Требовать подтверждение email');
            
            // Настройки комментариев
            $settingsRepo->setValue('comments_enabled', $request->request->get('comments_enabled') === '1', 'boolean', 'Включить комментарии');
            $settingsRepo->setValue('comments_moderation', $request->request->get('comments_moderation') === '1', 'boolean', 'Модерация комментариев');
            
            // Очистить кеш настроек
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки сохранены');
            return $this->redirectToRoute('admin_settings');
        }
        
        $settings = [
            'site_logo' => $settingsRepo->getValue('site_logo', null),
            'site_favicon' => $settingsRepo->getValue('site_favicon', null),
            'site_name' => $settingsRepo->getValue('site_name', 'RexTube'),
            'append_site_name_to_title' => $settingsRepo->getValue('append_site_name_to_title', true),
            'site_description' => $settingsRepo->getValue('site_description', 'Видео хостинг'),
            'site_keywords' => $settingsRepo->getValue('site_keywords', 'видео, хостинг, онлайн'),
            'contact_email' => $settingsRepo->getValue('contact_email', 'admin@rextube.test'),
            'max_video_size' => $settingsRepo->getValue('max_video_size', 500),
            'allowed_video_formats' => $settingsRepo->getValue('allowed_video_formats', 'mp4,avi,mov,mkv'),
            'videos_per_page' => $settingsRepo->getValue('videos_per_page', 24),
            'registration_enabled' => $settingsRepo->getValue('registration_enabled', true),
            'email_verification_required' => $settingsRepo->getValue('email_verification_required', false),
            'comments_enabled' => $settingsRepo->getValue('comments_enabled', true),
            'comments_moderation' => $settingsRepo->getValue('comments_moderation', false),
        ];
        
        return $this->render('admin/settings/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/encoding-profiles', name: 'admin_encoding_profiles')]
    public function encodingProfiles(): Response
    {
        return $this->render('admin/settings/encoding_profiles.html.twig', [
            'profiles' => $this->profileRepository->findAll(),
        ]);
    }

    #[Route('/encoding-profiles/new', name: 'admin_encoding_profiles_new')]
    public function newProfile(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleProfileSave($request, new VideoEncodingProfile());
        }

        return $this->render('admin/settings/encoding_profile_form.html.twig', [
            'profile' => new VideoEncodingProfile(),
        ]);
    }

    #[Route('/encoding-profiles/{id}/edit', name: 'admin_encoding_profiles_edit')]
    public function editProfile(Request $request, VideoEncodingProfile $profile): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleProfileSave($request, $profile);
        }

        return $this->render('admin/settings/encoding_profile_form.html.twig', [
            'profile' => $profile,
        ]);
    }

    #[Route('/encoding-profiles/{id}/delete', name: 'admin_encoding_profiles_delete', methods: ['POST'])]
    public function deleteProfile(VideoEncodingProfile $profile): Response
    {
        // Проверяем, есть ли связанные видеофайлы
        $videoFilesCount = $this->em->getRepository(\App\Entity\VideoFile::class)
            ->count(['profile' => $profile]);
        
        if ($videoFilesCount > 0) {
            $this->addFlash('error', "Невозможно удалить профиль кодирования: он используется в {$videoFilesCount} видеофайлах. Сначала удалите связанные видеофайлы.");
            return $this->redirectToRoute('admin_encoding_profiles');
        }
        
        $this->em->remove($profile);
        $this->em->flush();
        
        $this->addFlash('success', 'Профиль кодирования удален');
        return $this->redirectToRoute('admin_encoding_profiles');
    }

    #[Route('/main', name: 'admin_main_page_settings')]
    public function mainPageSettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            $settingsRepo->setValue('home_new_videos_count', (int) $request->request->get('home_new_videos_count'), 'integer', 'Количество новых видео на главной');
            $settingsRepo->setValue('home_popular_videos_count', (int) $request->request->get('home_popular_videos_count'), 'integer', 'Количество популярных видео на главной');
            $settingsRepo->setValue('home_featured_videos_count', (int) $request->request->get('home_featured_videos_count'), 'integer', 'Количество избранных видео на главной');
            $settingsRepo->setValue('home_recently_watched_count', (int) $request->request->get('home_recently_watched_count'), 'integer', 'Количество недавно просмотренных видео на главной');
            $settingsRepo->setValue('home_show_new_videos', $request->request->get('home_show_new_videos') === '1', 'boolean', 'Показывать новые видео');
            $settingsRepo->setValue('home_show_popular_videos', $request->request->get('home_show_popular_videos') === '1', 'boolean', 'Показывать популярные видео');
            $settingsRepo->setValue('home_show_featured_videos', $request->request->get('home_show_featured_videos') === '1', 'boolean', 'Показывать избранные видео');
            $settingsRepo->setValue('home_show_recently_watched', $request->request->get('home_show_recently_watched') === '1', 'boolean', 'Показывать недавно просмотренные видео');
            
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки главной страницы сохранены');
            return $this->redirectToRoute('admin_main_page_settings');
        }
        
        $settings = [
            'home_new_videos_count' => $settingsRepo->getValue('home_new_videos_count', 12),
            'home_popular_videos_count' => $settingsRepo->getValue('home_popular_videos_count', 12),
            'home_featured_videos_count' => $settingsRepo->getValue('home_featured_videos_count', 10),
            'home_recently_watched_count' => $settingsRepo->getValue('home_recently_watched_count', 8),
            'home_show_new_videos' => $settingsRepo->getValue('home_show_new_videos', true),
            'home_show_popular_videos' => $settingsRepo->getValue('home_show_popular_videos', true),
            'home_show_featured_videos' => $settingsRepo->getValue('home_show_featured_videos', true),
            'home_show_recently_watched' => $settingsRepo->getValue('home_show_recently_watched', true),
        ];
        
        return $this->render('admin/settings/main_page.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/transcoding', name: 'admin_transcoding_settings')]
    public function transcodingSettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            // Настройки постера
            $settingsRepo->setValue('poster_width', (int) $request->request->get('poster_width'), 'integer', 'Ширина постера (px)');
            $settingsRepo->setValue('poster_height', (int) $request->request->get('poster_height'), 'integer', 'Высота постера (px)');
            $settingsRepo->setValue('poster_format', $request->request->get('poster_format'), 'string', 'Формат постера');
            $settingsRepo->setValue('poster_quality', (int) $request->request->get('poster_quality'), 'integer', 'Качество постера (%)');
            
            // Настройки видео-превью
            $settingsRepo->setValue('preview_width', (int) $request->request->get('preview_width'), 'integer', 'Ширина видео-превью (px)');
            $settingsRepo->setValue('preview_height', (int) $request->request->get('preview_height'), 'integer', 'Высота видео-превью (px)');
            $settingsRepo->setValue('preview_duration', (int) $request->request->get('preview_duration'), 'integer', 'Длительность видео-превью (сек)');
            $settingsRepo->setValue('preview_segments', (int) $request->request->get('preview_segments'), 'integer', 'Количество фрагментов');
            $settingsRepo->setValue('preview_format', $request->request->get('preview_format'), 'string', 'Формат видео-превью');
            $settingsRepo->setValue('preview_quality', $request->request->get('preview_quality'), 'string', 'Качество видео-превью');
            
            // Очистить кеш настроек
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки транскодирования сохранены');
            return $this->redirectToRoute('admin_transcoding_settings');
        }
        
        $settings = [
            // Настройки постера
            'poster_width' => $settingsRepo->getValue('poster_width', 400),
            'poster_height' => $settingsRepo->getValue('poster_height', 225),
            'poster_format' => $settingsRepo->getValue('poster_format', 'JPEG'),
            'poster_quality' => $settingsRepo->getValue('poster_quality', 85),
            
            // Настройки видео-превью
            'preview_width' => $settingsRepo->getValue('preview_width', 640),
            'preview_height' => $settingsRepo->getValue('preview_height', 360),
            'preview_duration' => $settingsRepo->getValue('preview_duration', 12),
            'preview_segments' => $settingsRepo->getValue('preview_segments', 6),
            'preview_format' => $settingsRepo->getValue('preview_format', 'MP4'),
            'preview_quality' => $settingsRepo->getValue('preview_quality', 'medium'),
        ];
        
        return $this->render('admin/settings/transcoding.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/seo', name: 'admin_seo_settings')]
    public function seoSettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            // Мета-теги страницы видео
            $settingsRepo->setValue('seo_video_title_prefix', $request->request->get('seo_video_title_prefix'), 'string', 'Префикс заголовка видео');
            $settingsRepo->setValue('seo_video_title_suffix', $request->request->get('seo_video_title_suffix'), 'string', 'Постфикс заголовка видео');
            
            // Мета-теги главной страницы
            $settingsRepo->setValue('seo_home_title', $request->request->get('seo_home_title'), 'string', 'Заголовок главной страницы');
            $settingsRepo->setValue('seo_home_description', $request->request->get('seo_home_description'), 'string', 'Описание для главной страницы');
            $settingsRepo->setValue('seo_home_keywords', $request->request->get('seo_home_keywords'), 'string', 'Ключевые слова для главной');
            $settingsRepo->setValue('seo_home_only', $request->request->get('seo_home_only') === '1', 'boolean', 'Использовать SEO только для главной');
            
            // Мета-теги страницы списка категорий
            $settingsRepo->setValue('seo_categories_title', $request->request->get('seo_categories_title'), 'string', 'Заголовок страницы категорий');
            $settingsRepo->setValue('seo_categories_description', $request->request->get('seo_categories_description'), 'string', 'Описание страницы категорий');
            $settingsRepo->setValue('seo_categories_keywords', $request->request->get('seo_categories_keywords'), 'string', 'Ключевые слова страницы категорий');
            
            // Мета-теги страницы списка видео
            $settingsRepo->setValue('seo_videos_title', $request->request->get('seo_videos_title'), 'string', 'Заголовок страницы списка видео');
            $settingsRepo->setValue('seo_videos_description', $request->request->get('seo_videos_description'), 'string', 'Описание страницы списка видео');
            $settingsRepo->setValue('seo_videos_keywords', $request->request->get('seo_videos_keywords'), 'string', 'Ключевые слова страницы списка видео');
            
            // Мета-теги страницы моделей
            $settingsRepo->setValue('seo_models_title', $request->request->get('seo_models_title'), 'string', 'Заголовок страницы моделей');
            $settingsRepo->setValue('seo_models_description', $request->request->get('seo_models_description'), 'string', 'Описание страницы моделей');
            $settingsRepo->setValue('seo_models_keywords', $request->request->get('seo_models_keywords'), 'string', 'Ключевые слова страницы моделей');
            
            // Мета-теги страницы сообщества
            $settingsRepo->setValue('seo_members_title', $request->request->get('seo_members_title'), 'string', 'Заголовок страницы сообщества');
            $settingsRepo->setValue('seo_members_description', $request->request->get('seo_members_description'), 'string', 'Описание страницы сообщества');
            $settingsRepo->setValue('seo_members_keywords', $request->request->get('seo_members_keywords'), 'string', 'Ключевые слова страницы сообщества');
            
            // Мета-теги страницы тегов
            $settingsRepo->setValue('seo_tags_title', $request->request->get('seo_tags_title'), 'string', 'Заголовок страницы тегов');
            $settingsRepo->setValue('seo_tags_description', $request->request->get('seo_tags_description'), 'string', 'Описание страницы тегов');
            $settingsRepo->setValue('seo_tags_keywords', $request->request->get('seo_tags_keywords'), 'string', 'Ключевые слова страницы тегов');
            
            // Мета-теги страницы постов
            $settingsRepo->setValue('seo_posts_title', $request->request->get('seo_posts_title'), 'string', 'Заголовок страницы постов');
            $settingsRepo->setValue('seo_posts_description', $request->request->get('seo_posts_description'), 'string', 'Описание страницы постов');
            $settingsRepo->setValue('seo_posts_keywords', $request->request->get('seo_posts_keywords'), 'string', 'Ключевые слова страницы постов');
            
            // Мета-теги страницы каналов
            $settingsRepo->setValue('seo_channels_title', $request->request->get('seo_channels_title'), 'string', 'Заголовок страницы каналов');
            $settingsRepo->setValue('seo_channels_description', $request->request->get('seo_channels_description'), 'string', 'Описание страницы каналов');
            $settingsRepo->setValue('seo_channels_keywords', $request->request->get('seo_channels_keywords'), 'string', 'Ключевые слова страницы каналов');
            
            // Мета-теги страницы live стримов
            $settingsRepo->setValue('seo_live_title', $request->request->get('seo_live_title'), 'string', 'Заголовок страницы live стримов');
            $settingsRepo->setValue('seo_live_description', $request->request->get('seo_live_description'), 'string', 'Описание страницы live стримов');
            $settingsRepo->setValue('seo_live_keywords', $request->request->get('seo_live_keywords'), 'string', 'Ключевые слова страницы live стримов');
            
            // Robots.txt
            $settingsRepo->setValue('robots_txt_content', $request->request->get('robots_txt_content'), 'text', 'Содержимое robots.txt');
            
            // Sitemap настройки
            $settingsRepo->setValue('sitemap_video_priority', $request->request->get('sitemap_video_priority'), 'string', 'Приоритет видео в sitemap');
            $settingsRepo->setValue('sitemap_category_priority', $request->request->get('sitemap_category_priority'), 'string', 'Приоритет категорий в sitemap');
            $settingsRepo->setValue('sitemap_model_priority', $request->request->get('sitemap_model_priority'), 'string', 'Приоритет моделей в sitemap');
            
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'SEO настройки сохранены');
            return $this->redirectToRoute('admin_seo_settings');
        }
        
        $settings = [
            'seo_video_title_prefix' => $settingsRepo->getValue('seo_video_title_prefix', ''),
            'seo_video_title_suffix' => $settingsRepo->getValue('seo_video_title_suffix', ''),
            'seo_home_title' => $settingsRepo->getValue('seo_home_title', ''),
            'seo_home_description' => $settingsRepo->getValue('seo_home_description', ''),
            'seo_home_keywords' => $settingsRepo->getValue('seo_home_keywords', ''),
            'seo_home_only' => $settingsRepo->getValue('seo_home_only', false),
            'seo_categories_title' => $settingsRepo->getValue('seo_categories_title', ''),
            'seo_categories_description' => $settingsRepo->getValue('seo_categories_description', ''),
            'seo_categories_keywords' => $settingsRepo->getValue('seo_categories_keywords', ''),
            'seo_videos_title' => $settingsRepo->getValue('seo_videos_title', ''),
            'seo_videos_description' => $settingsRepo->getValue('seo_videos_description', ''),
            'seo_videos_keywords' => $settingsRepo->getValue('seo_videos_keywords', ''),
            'seo_models_title' => $settingsRepo->getValue('seo_models_title', ''),
            'seo_models_description' => $settingsRepo->getValue('seo_models_description', ''),
            'seo_models_keywords' => $settingsRepo->getValue('seo_models_keywords', ''),
            'seo_members_title' => $settingsRepo->getValue('seo_members_title', ''),
            'seo_members_description' => $settingsRepo->getValue('seo_members_description', ''),
            'seo_members_keywords' => $settingsRepo->getValue('seo_members_keywords', ''),
            // Теги
            'seo_tags_title' => $settingsRepo->getValue('seo_tags_title', ''),
            'seo_tags_description' => $settingsRepo->getValue('seo_tags_description', ''),
            'seo_tags_keywords' => $settingsRepo->getValue('seo_tags_keywords', ''),
            // Посты
            'seo_posts_title' => $settingsRepo->getValue('seo_posts_title', ''),
            'seo_posts_description' => $settingsRepo->getValue('seo_posts_description', ''),
            'seo_posts_keywords' => $settingsRepo->getValue('seo_posts_keywords', ''),
            // Каналы
            'seo_channels_title' => $settingsRepo->getValue('seo_channels_title', ''),
            'seo_channels_description' => $settingsRepo->getValue('seo_channels_description', ''),
            'seo_channels_keywords' => $settingsRepo->getValue('seo_channels_keywords', ''),
            // Live стримы
            'seo_live_title' => $settingsRepo->getValue('seo_live_title', ''),
            'seo_live_description' => $settingsRepo->getValue('seo_live_description', ''),
            'seo_live_keywords' => $settingsRepo->getValue('seo_live_keywords', ''),
            // Robots.txt
            'robots_txt_content' => $settingsRepo->getValue('robots_txt_content', ''),
            // Sitemap
            'sitemap_video_priority' => $settingsRepo->getValue('sitemap_video_priority', '0.8'),
            'sitemap_category_priority' => $settingsRepo->getValue('sitemap_category_priority', '0.7'),
            'sitemap_model_priority' => $settingsRepo->getValue('sitemap_model_priority', '0.7'),
        ];
        
        return $this->render('admin/settings/seo.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/categories', name: 'admin_category_settings')]
    public function categorySettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            $settingsRepo->setValue(
                'category_poster_auto_generate', 
                $request->request->get('category_poster_auto_generate') === '1', 
                'boolean', 
                'Автогенерация постеров категорий'
            );
            $settingsRepo->setValue(
                'category_poster_criteria', 
                $request->request->get('category_poster_criteria'), 
                'string', 
                'Критерий выбора видео для постера категории'
            );
            
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки категорий сохранены');
            return $this->redirectToRoute('admin_category_settings');
        }
        
        $settings = [
            'category_poster_auto_generate' => $settingsRepo->getValue('category_poster_auto_generate', false),
            'category_poster_criteria' => $settingsRepo->getValue('category_poster_criteria', CategoryPosterService::CRITERIA_MOST_VIEWED),
        ];
        
        return $this->render('admin/settings/categories.html.twig', [
            'settings' => $settings,
            'criteria_options' => CategoryPosterService::getAvailableCriteria(),
        ]);
    }

    #[Route('/categories/generate-posters', name: 'admin_generate_category_posters', methods: ['POST'])]
    public function generateCategoryPosters(Request $request): Response
    {
        $force = $request->request->get('force') === '1';
        
        $stats = $this->categoryPosterService->generateAllPosters($force);
        
        $this->addFlash('success', sprintf(
            'Генерация завершена. Создано: %d, Пропущено: %d, Ошибок: %d',
            $stats['generated'],
            $stats['skipped'],
            $stats['failed']
        ));
        
        return $this->redirectToRoute('admin_category_settings');
    }

    private function handleProfileSave(Request $request, VideoEncodingProfile $profile): Response
    {
        $width = (int) $request->request->get('width');
        $height = (int) $request->request->get('height');
        
        $profile->setName($request->request->get('name'));
        $profile->setResolution("{$width}x{$height}");
        $profile->setBitrate((int) $request->request->get('bitrate'));
        $profile->setCodec($request->request->get('codec', 'h264'));
        $profile->setFormat($request->request->get('format', 'mp4'));
        $profile->setOrderPosition((int) $request->request->get('order_position', 0));
        $profile->setActive($request->request->get('is_active') === '1');
        
        $this->em->persist($profile);
        $this->em->flush();
        
        $this->addFlash('success', 'Профиль кодирования сохранен');
        return $this->redirectToRoute('admin_encoding_profiles');
    }

    #[Route('/analytics', name: 'admin_analytics_settings')]
    public function analyticsSettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            // Google Analytics
            $settingsRepo->setValue('google_analytics_id', $request->request->get('google_analytics_id'), 'string', 'Google Analytics ID (GA4)');
            
            // Яндекс.Метрика
            $settingsRepo->setValue('yandex_metrica_id', $request->request->get('yandex_metrica_id'), 'string', 'Яндекс.Метрика ID');
            
            // Google Search Console
            $settingsRepo->setValue('google_search_console_code', $request->request->get('google_search_console_code'), 'string', 'Google Search Console verification code');
            
            // Facebook Pixel
            $settingsRepo->setValue('facebook_pixel_id', $request->request->get('facebook_pixel_id'), 'string', 'Facebook Pixel ID');
            
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки аналитики сохранены');
            return $this->redirectToRoute('admin_analytics_settings');
        }
        
        $settings = [
            'google_analytics_id' => $settingsRepo->getValue('google_analytics_id', ''),
            'yandex_metrica_id' => $settingsRepo->getValue('yandex_metrica_id', ''),
            'google_search_console_code' => $settingsRepo->getValue('google_search_console_code', ''),
            'facebook_pixel_id' => $settingsRepo->getValue('facebook_pixel_id', ''),
        ];
        
        return $this->render('admin/settings/analytics.html.twig', [
            'settings' => $settings,
        ]);
    }
}

