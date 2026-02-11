<?php

namespace App\Controller\Admin;

use App\Entity\Channel;
use App\Entity\User;
use App\Repository\ChannelRepository;
use App\Repository\UserRepository;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/channels')]
class AdminChannelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelRepository $channelRepository,
        private UserRepository $userRepository,
        private SettingsService $settingsService,
        private SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'admin_channels_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', $this->settingsService->getVideosPerPage());
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = $this->settingsService->getVideosPerPage();
        }
        
        $limit = $perPage;
        $offset = ($page - 1) * $limit;

        $filters = [
            'search' => $request->query->get('search'),
            'type' => $request->query->get('type'),
            'verified' => $request->query->get('verified') === '1',
            'premium' => $request->query->get('premium') === '1',
            'sort' => $request->query->get('sort', 'popular'),
            'status' => $request->query->get('status')
        ];

        $channels = $this->channelRepository->findWithFilters($filters, $limit, $offset);
        $totalChannels = $this->channelRepository->countWithFilters($filters);
        $totalPages = ceil($totalChannels / $limit);

        return $this->render('admin/channels/index.html.twig', [
            'channels' => $channels,
            'current_page' => $page,
            'perPage' => $perPage,
            'total_pages' => $totalPages,
            'total_channels' => $totalChannels,
            'filters' => $filters,
        ]);
    }

    #[Route('/create', name: 'admin_channels_create')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $type = $request->request->get('type', Channel::TYPE_PERSONAL);
            $ownerId = $request->request->getInt('owner_id');
            $isVerified = $request->request->getBoolean('is_verified');
            $isActive = $request->request->getBoolean('is_active');
            $isPremium = $request->request->getBoolean('is_premium');
            $website = $request->request->get('website');
            $email = $request->request->get('email');
            $twitter = $request->request->get('twitter');
            $instagram = $request->request->get('instagram');
            $onlyfans = $request->request->get('onlyfans');
            $primaryColor = $request->request->get('primary_color_text');
            $secondaryColor = $request->request->get('secondary_color_text');

            if (!$name || !$ownerId) {
                $this->addFlash('error', 'Название и владелец обязательны');
                return $this->redirectToRoute('admin_channels_create');
            }

            $owner = $this->userRepository->find($ownerId);
            if (!$owner) {
                $this->addFlash('error', 'Пользователь не найден');
                return $this->redirectToRoute('admin_channels_create');
            }

            $channel = new Channel();
            $channel->setName($name);
            $channel->setDescription($description);
            $channel->setType($type);
            $channel->setOwner($owner);
            $channel->setIsVerified($isVerified);
            $channel->setIsActive($isActive);
            $channel->setIsPremium($isPremium);
            $channel->setWebsite($website);
            $channel->setEmail($email);
            $channel->setTwitter($twitter);
            $channel->setInstagram($instagram);
            $channel->setOnlyfans($onlyfans);
            $channel->setPrimaryColor($primaryColor);
            $channel->setSecondaryColor($secondaryColor);
            $channel->generateSlug($this->slugger);

            // Обработка загрузки аватара
            $avatarFile = $request->files->get('avatar_file');
            if ($avatarFile) {
                $avatarFilename = $this->handleFileUpload($avatarFile, 'avatars');
                if ($avatarFilename) {
                    $channel->setAvatar($avatarFilename);
                }
            }

            // Обработка загрузки баннера
            $bannerFile = $request->files->get('banner_file');
            if ($bannerFile) {
                $bannerFilename = $this->handleFileUpload($bannerFile, 'banners');
                if ($bannerFilename) {
                    $channel->setBanner($bannerFilename);
                }
            }

            // Проверка уникальности slug
            $originalSlug = $channel->getSlug();
            $counter = 1;
            while (!$this->channelRepository->isSlugUnique($channel->getSlug())) {
                $channel->setSlug($originalSlug . '-' . $counter);
                $counter++;
            }

            $this->entityManager->persist($channel);
            $this->entityManager->flush();

            $this->addFlash('success', 'Канал успешно создан');
            return $this->redirectToRoute('admin_channels_edit', ['id' => $channel->getId()]);
        }

        $users = $this->userRepository->findBy([], ['username' => 'ASC']);

        return $this->render('admin/channels/create.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_channels_edit')]
    public function edit(Channel $channel, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $type = $request->request->get('type');
            $isVerified = $request->request->getBoolean('is_verified');
            $isActive = $request->request->getBoolean('is_active');
            $isPremium = $request->request->getBoolean('is_premium');
            $subscriptionPrice = $request->request->get('subscription_price');
            $website = $request->request->get('website');
            $email = $request->request->get('email');
            $twitter = $request->request->get('twitter');
            $instagram = $request->request->get('instagram');
            $onlyfans = $request->request->get('onlyfans');
            $primaryColor = $request->request->get('primary_color_text');
            $secondaryColor = $request->request->get('secondary_color_text');

            if (!$name) {
                $this->addFlash('error', 'Название обязательно');
                return $this->redirectToRoute('admin_channels_edit', ['id' => $channel->getId()]);
            }

            // Обработка загрузки аватара
            $avatarFile = $request->files->get('avatar_file');
            if ($avatarFile) {
                $avatarFilename = $this->handleFileUpload($avatarFile, 'avatars', $channel->getAvatar());
                if ($avatarFilename) {
                    $channel->setAvatar($avatarFilename);
                }
            }

            // Обработка загрузки баннера
            $bannerFile = $request->files->get('banner_file');
            if ($bannerFile) {
                $bannerFilename = $this->handleFileUpload($bannerFile, 'banners', $channel->getBanner());
                if ($bannerFilename) {
                    $channel->setBanner($bannerFilename);
                }
            }

            $oldSlug = $channel->getSlug();
            $channel->setName($name);
            $channel->setDescription($description);
            $channel->setType($type);
            $channel->setIsVerified($isVerified);
            $channel->setIsActive($isActive);
            $channel->setIsPremium($isPremium);
            $channel->setSubscriptionPrice($subscriptionPrice);
            $channel->setWebsite($website);
            $channel->setEmail($email);
            $channel->setTwitter($twitter);
            $channel->setInstagram($instagram);
            $channel->setOnlyfans($onlyfans);
            $channel->setPrimaryColor($primaryColor);
            $channel->setSecondaryColor($secondaryColor);

            // Обновить slug если изменилось название
            $newSlug = $this->slugger->slug($name)->lower();
            if ($newSlug !== $oldSlug) {
                $channel->setSlug($newSlug);
                
                // Проверка уникальности slug
                $originalSlug = $channel->getSlug();
                $counter = 1;
                while (!$this->channelRepository->isSlugUnique($channel->getSlug(), $channel->getId())) {
                    $channel->setSlug($originalSlug . '-' . $counter);
                    $counter++;
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Канал успешно обновлен');
            return $this->redirectToRoute('admin_channels_edit', ['id' => $channel->getId()]);
        }

        return $this->render('admin/channels/edit.html.twig', [
            'channel' => $channel,
        ]);
    }

    /**
     * Обработка загрузки файлов
     */
    private function handleFileUpload($file, string $subfolder, ?string $oldFilename = null): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        // Валидация типа файла
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', 'Неподдерживаемый тип файла. Используйте JPG, PNG, GIF или WebP');
            return null;
        }

        // Валидация размера файла
        $maxSize = $subfolder === 'avatars' ? 2 * 1024 * 1024 : 5 * 1024 * 1024; // 2MB для аватаров, 5MB для баннеров
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            $this->addFlash('error', "Размер файла не должен превышать {$maxSizeMB}MB");
            return null;
        }

        try {
            // Создание директории если не существует
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/media/channels/' . $subfolder;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Генерация уникального имени файла
            $extension = $file->guessExtension();
            $filename = uniqid() . '.' . $extension;
            
            // Перемещение файла
            $file->move($uploadDir, $filename);

            // Удаление старого файла если есть
            if ($oldFilename) {
                $oldFilePath = $uploadDir . '/' . $oldFilename;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            return $filename;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка при загрузке файла: ' . $e->getMessage());
            return null;
        }
    }

    #[Route('/{id}/delete', name: 'admin_channels_delete', methods: ['POST'])]
    public function delete(Channel $channel, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_channel_' . $channel->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_channels_index');
        }

        $this->entityManager->remove($channel);
        $this->entityManager->flush();

        $this->addFlash('success', 'Канал успешно удален');
        return $this->redirectToRoute('admin_channels_index');
    }

    #[Route('/{id}/toggle-verification', name: 'admin_channels_toggle_verification', methods: ['POST'])]
    public function toggleVerification(Channel $channel, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle_verification_' . $channel->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_channels_index');
        }

        $channel->setIsVerified(!$channel->isVerified());
        $this->entityManager->flush();

        $status = $channel->isVerified() ? 'верифицирован' : 'не верифицирован';
        $this->addFlash('success', "Канал {$status}");

        return $this->redirectToRoute('admin_channels_index');
    }

    #[Route('/{id}/toggle-active', name: 'admin_channels_toggle_active', methods: ['POST'])]
    public function toggleActive(Channel $channel, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle_active_' . $channel->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_channels_index');
        }

        $channel->setIsActive(!$channel->isActive());
        $this->entityManager->flush();

        $status = $channel->isActive() ? 'активирован' : 'деактивирован';
        $this->addFlash('success', "Канал {$status}");

        return $this->redirectToRoute('admin_channels_index');
    }

    #[Route('/bulk', name: 'admin_channels_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_channels', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_channels_index');
        }

        $channelIds = $request->request->all('channel_ids');
        $action = $request->request->get('bulk_action');

        if (empty($channelIds)) {
            $this->addFlash('error', 'Не выбрано ни одного канала');
            return $this->redirectToRoute('admin_channels_index');
        }

        if (empty($action)) {
            $this->addFlash('error', 'Не выбрано действие');
            return $this->redirectToRoute('admin_channels_index');
        }

        $channels = $this->channelRepository->findBy(['id' => $channelIds]);
        $count = count($channels);

        switch ($action) {
            case 'verify':
                foreach ($channels as $channel) {
                    $channel->setIsVerified(true);
                }
                $this->entityManager->flush();
                $this->addFlash('success', "Верифицировано каналов: {$count}");
                break;

            case 'unverify':
                foreach ($channels as $channel) {
                    $channel->setIsVerified(false);
                }
                $this->entityManager->flush();
                $this->addFlash('success', "Снята верификация у каналов: {$count}");
                break;

            case 'premium':
                foreach ($channels as $channel) {
                    $channel->setIsPremium(true);
                }
                $this->entityManager->flush();
                $this->addFlash('success', "Выдан премиум каналам: {$count}");
                break;

            case 'remove_premium':
                foreach ($channels as $channel) {
                    $channel->setIsPremium(false);
                }
                $this->entityManager->flush();
                $this->addFlash('success', "Снят премиум у каналов: {$count}");
                break;

            case 'activate':
                foreach ($channels as $channel) {
                    $channel->setIsActive(true);
                }
                $this->entityManager->flush();
                $this->addFlash('success', "Активировано каналов: {$count}");
                break;

            case 'deactivate':
                foreach ($channels as $channel) {
                    $channel->setIsActive(false);
                }
                $this->entityManager->flush();
                $this->addFlash('success', "Деактивировано каналов: {$count}");
                break;

            case 'delete':
                foreach ($channels as $channel) {
                    $this->entityManager->remove($channel);
                }
                $this->entityManager->flush();
                $this->addFlash('success', "Удалено каналов: {$count}");
                break;

            default:
                $this->addFlash('error', 'Неизвестное действие');
        }

        return $this->redirectToRoute('admin_channels_index');
    }
}