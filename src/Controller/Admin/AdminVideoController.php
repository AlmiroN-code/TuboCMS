<?php

namespace App\Controller\Admin;

use App\Entity\Video;
use App\Message\DeleteFromStorageMessage;
use App\Message\ProcessVideoEncodingMessage;
use App\Repository\VideoRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Repository\ModelProfileRepository;
use App\Repository\ChannelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/admin/videos')]
#[IsGranted('ROLE_ADMIN')]
class AdminVideoController extends AbstractController
{
    public function __construct(
        private VideoRepository $videoRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private ModelProfileRepository $modelProfileRepository,
        private ChannelRepository $channelRepository,
        private EntityManagerInterface $em,
        private MessageBusInterface $messageBus
    ) {
    }

    #[Route('', name: 'admin_videos')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 15);
        
        // Ограничиваем допустимые значения
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 15;
        }
        
        $limit = $perPage;
        $offset = ($page - 1) * $limit;
        $sort = $request->query->get('sort');
        $status = $request->query->get('status');
        $categoryId = $request->query->get('category');
        $authorId = $request->query->get('author');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $search = $request->query->get('search');
        
        $filters = [
            'status' => $status,
            'categoryId' => $categoryId,
            'authorId' => $authorId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
        ];
        
        $videos = $this->videoRepository->findForAdminList($limit, $offset, $sort, $filters);
        $total = $this->videoRepository->countForAdminList($filters);
        
        // Для HTMX запросов возвращаем только таблицу
        if ($request->headers->get('HX-Request')) {
            return $this->render('admin/videos/_table.html.twig', [
                'videos' => $videos,
            ]);
        }
        
        // Получаем список категорий и авторов для фильтров
        $categories = $this->em->getRepository(\App\Entity\Category::class)->findAll();
        $authors = $this->em->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->select('u.id', 'u.username')
            ->where('u.id IN (SELECT IDENTITY(v.createdBy) FROM App\Entity\Video v)')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/videos/index.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'currentSort' => $sort,
            'filters' => $filters,
            'categories' => $categories,
            'authors' => $authors,
        ]);
    }

    #[Route('/new', name: 'admin_videos_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new Video());
        }

        return $this->render('admin/videos/form.html.twig', [
            'video' => new Video(),
            'categories' => $this->categoryRepository->findAll(),
            'tags' => $this->tagRepository->findAll(),
            'models' => $this->modelProfileRepository->findBy(['isActive' => true], ['displayName' => 'ASC']),
            'channels' => $this->channelRepository->findBy(['isActive' => true], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_videos_edit')]
    public function edit(Request $request, Video $video): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $video);
        }

        return $this->render('admin/videos/form.html.twig', [
            'video' => $video,
            'categories' => $this->categoryRepository->findAll(),
            'tags' => $this->tagRepository->findAll(),
            'models' => $this->modelProfileRepository->findBy(['isActive' => true], ['displayName' => 'ASC']),
            'channels' => $this->channelRepository->findBy(['isActive' => true], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_videos_delete', methods: ['POST'])]
    public function delete(Request $request, Video $video): Response
    {
        // Проверяем CSRF токен
        if (!$this->isCsrfTokenValid('delete_video_' . $video->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_videos');
        }

        // Уменьшаем счётчики видео у моделей-участников (Requirement 7.3)
        foreach ($video->getPerformers() as $performer) {
            $performer->setVideosCount(max(0, $performer->getVideosCount() - 1));
        }

        // Requirement 5.1: Queue deletion jobs for all associated remote files
        $this->queueRemoteFileDeletions($video);

        $this->em->remove($video);
        $this->em->flush();
        
        $this->addFlash('success', 'Видео удалено');
        return $this->redirectToRoute('admin_videos');
    }

    #[Route('/{id}/process', name: 'admin_videos_process', methods: ['POST'])]
    public function process(Request $request, Video $video): Response
    {
        // Проверяем CSRF токен
        if (!$this->isCsrfTokenValid('process_video_' . $video->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_videos');
        }
        if (!$video->getTempVideoFile()) {
            $this->addFlash('error', 'Видео файл не найден для обработки');
            return $this->redirectToRoute('admin_videos');
        }

        try {
            $videoPath = $this->getParameter('kernel.project_dir') . '/public/media/' . $video->getTempVideoFile();
            
            if (!file_exists($videoPath)) {
                $this->addFlash('error', "Файл не существует: {$videoPath}");
                return $this->redirectToRoute('admin_videos');
            }

            // Сбрасываем статус обработки
            $video->setProcessingStatus('pending');
            $video->setProcessingProgress(0);
            $video->setProcessingError(null);
            $this->em->flush();

            // Отправляем в очередь для асинхронной обработки
            $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
            
            $this->addFlash('success', 'Видео отправлено на обработку! Процесс запущен асинхронно.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка запуска обработки: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_videos');
    }

    #[Route('/{id}/reprocess', name: 'admin_videos_reprocess', methods: ['POST'])]
    public function reprocess(Video $video): Response
    {
        try {
            // Проверяем что есть временный файл
            if (!$video->getTempVideoFile()) {
                return $this->json([
                    'success' => false,
                    'message' => 'No temp video file found. Cannot reprocess.'
                ], 400);
            }

            $videoPath = $this->getParameter('kernel.project_dir') . '/public/media/' . $video->getTempVideoFile();
            
            if (!file_exists($videoPath)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Video file not found: ' . $video->getTempVideoFile()
                ], 400);
            }

            // Сбрасываем статус обработки
            $video->setProcessingStatus('pending');
            $video->setProcessingProgress(0);
            $video->setProcessingError(null);
            $this->em->flush();

            // Отправляем в очередь для асинхронной обработки
            $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
            
            return $this->json([
                'success' => true,
                'message' => 'Video queued for reprocessing'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/moderate/{action}', name: 'admin_videos_moderate', methods: ['POST'])]
    public function moderate(Video $video, string $action): Response
    {
        switch ($action) {
            case 'publish':
                $video->setStatus(Video::STATUS_PUBLISHED);
                break;
            case 'unpublish':
                $video->setStatus(Video::STATUS_DRAFT);
                break;
            case 'reject':
                $video->setStatus(Video::STATUS_REJECTED);
                break;
        }
        
        $this->em->flush();
        
        return $this->render('admin/videos/_row.html.twig', [
            'video' => $video,
        ]);
    }

    #[Route('/{id}/delete-htmx', name: 'admin_videos_delete_htmx', methods: ['DELETE'])]
    public function deleteHtmx(Video $video): Response
    {
        // Уменьшаем счётчики видео у моделей-участников (Requirement 7.3)
        foreach ($video->getPerformers() as $performer) {
            $performer->setVideosCount(max(0, $performer->getVideosCount() - 1));
        }

        // Requirement 5.1: Queue deletion jobs for all associated remote files
        $this->queueRemoteFileDeletions($video);

        $this->em->remove($video);
        $this->em->flush();
        
        return new Response('');
    }

    #[Route('/bulk', name: 'admin_videos_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        // Проверяем CSRF токен
        if (!$this->isCsrfTokenValid('bulk_videos', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_videos');
        }

        $videoIds = $request->request->all('video_ids');
        $action = $request->request->get('bulk_action');

        if (empty($videoIds)) {
            $this->addFlash('error', 'Не выбрано ни одного видео');
            return $this->redirectToRoute('admin_videos');
        }

        if (empty($action)) {
            $this->addFlash('error', 'Не выбрано действие');
            return $this->redirectToRoute('admin_videos');
        }

        $videos = $this->videoRepository->findBy(['id' => $videoIds]);
        $count = count($videos);

        switch ($action) {
            case 'publish':
                foreach ($videos as $video) {
                    $video->setStatus(Video::STATUS_PUBLISHED);
                }
                $this->em->flush();
                $this->addFlash('success', "Опубликовано видео: {$count}");
                break;

            case 'unpublish':
                foreach ($videos as $video) {
                    $video->setStatus(Video::STATUS_DRAFT);
                }
                $this->em->flush();
                $this->addFlash('success', "Снято с публикации видео: {$count}");
                break;

            case 'delete':
                foreach ($videos as $video) {
                    // Уменьшаем счётчики видео у моделей-участников
                    foreach ($video->getPerformers() as $performer) {
                        $performer->setVideosCount(max(0, $performer->getVideosCount() - 1));
                    }
                    // Queue deletion jobs for remote files
                    $this->queueRemoteFileDeletions($video);
                    $this->em->remove($video);
                }
                $this->em->flush();
                $this->addFlash('success', "Удалено видео: {$count}");
                break;

            case 'reprocess':
                $reprocessed = 0;
                foreach ($videos as $video) {
                    if ($video->getTempVideoFile()) {
                        $videoPath = $this->getParameter('kernel.project_dir') . '/public/media/' . $video->getTempVideoFile();
                        if (file_exists($videoPath)) {
                            $video->setProcessingStatus('pending');
                            $video->setProcessingProgress(0);
                            $video->setProcessingError(null);
                            $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
                            $reprocessed++;
                        }
                    }
                }
                $this->em->flush();
                $this->addFlash('success', "Отправлено на переобработку: {$reprocessed} из {$count}");
                break;

            case 'add_category':
                $categoryId = $request->request->get('category_id');
                if ($categoryId) {
                    $category = $this->categoryRepository->find($categoryId);
                    if ($category) {
                        foreach ($videos as $video) {
                            if (!$video->getCategories()->contains($category)) {
                                $video->addCategory($category);
                            }
                        }
                        $this->em->flush();
                        $this->addFlash('success', "Категория добавлена к {$count} видео");
                    } else {
                        $this->addFlash('error', 'Категория не найдена');
                    }
                } else {
                    $this->addFlash('error', 'Не выбрана категория');
                }
                break;

            case 'remove_category':
                $categoryId = $request->request->get('category_id');
                if ($categoryId) {
                    $category = $this->categoryRepository->find($categoryId);
                    if ($category) {
                        foreach ($videos as $video) {
                            $video->removeCategory($category);
                        }
                        $this->em->flush();
                        $this->addFlash('success', "Категория удалена у {$count} видео");
                    } else {
                        $this->addFlash('error', 'Категория не найдена');
                    }
                } else {
                    $this->addFlash('error', 'Не выбрана категория');
                }
                break;

            case 'replace_categories':
                $categoryId = $request->request->get('category_id');
                if ($categoryId) {
                    $category = $this->categoryRepository->find($categoryId);
                    if ($category) {
                        foreach ($videos as $video) {
                            // Удаляем все категории
                            foreach ($video->getCategories() as $oldCategory) {
                                $video->removeCategory($oldCategory);
                            }
                            // Добавляем новую
                            $video->addCategory($category);
                        }
                        $this->em->flush();
                        $this->addFlash('success', "Категории заменены у {$count} видео");
                    } else {
                        $this->addFlash('error', 'Категория не найдена');
                    }
                } else {
                    $this->addFlash('error', 'Не выбрана категория');
                }
                break;

            case 'add_tags':
                $tagNames = $request->request->get('tag_names');
                if ($tagNames) {
                    $tagNamesArray = array_map('trim', explode(',', $tagNames));
                    $tags = [];
                    foreach ($tagNamesArray as $tagName) {
                        if (!empty($tagName)) {
                            $tag = $this->tagRepository->findOneBy(['name' => $tagName]);
                            if (!$tag) {
                                $tag = new \App\Entity\Tag();
                                $tag->setName($tagName);
                                $slugger = new AsciiSlugger();
                                $tag->setSlug(strtolower($slugger->slug($tagName)));
                                $this->em->persist($tag);
                            }
                            $tags[] = $tag;
                        }
                    }
                    
                    foreach ($videos as $video) {
                        foreach ($tags as $tag) {
                            if (!$video->getTags()->contains($tag)) {
                                $video->addTag($tag);
                            }
                        }
                    }
                    $this->em->flush();
                    $this->addFlash('success', "Теги добавлены к {$count} видео");
                } else {
                    $this->addFlash('error', 'Не указаны теги');
                }
                break;

            case 'remove_tags':
                $tagNames = $request->request->get('tag_names');
                if ($tagNames) {
                    $tagNamesArray = array_map('trim', explode(',', $tagNames));
                    $tags = $this->tagRepository->findBy(['name' => $tagNamesArray]);
                    
                    foreach ($videos as $video) {
                        foreach ($tags as $tag) {
                            $video->removeTag($tag);
                        }
                    }
                    $this->em->flush();
                    $this->addFlash('success', "Теги удалены у {$count} видео");
                } else {
                    $this->addFlash('error', 'Не указаны теги');
                }
                break;

            case 'replace_tags':
                $tagNames = $request->request->get('tag_names');
                if ($tagNames) {
                    $tagNamesArray = array_map('trim', explode(',', $tagNames));
                    $tags = [];
                    foreach ($tagNamesArray as $tagName) {
                        if (!empty($tagName)) {
                            $tag = $this->tagRepository->findOneBy(['name' => $tagName]);
                            if (!$tag) {
                                $tag = new \App\Entity\Tag();
                                $tag->setName($tagName);
                                $slugger = new AsciiSlugger();
                                $tag->setSlug(strtolower($slugger->slug($tagName)));
                                $this->em->persist($tag);
                            }
                            $tags[] = $tag;
                        }
                    }
                    
                    foreach ($videos as $video) {
                        // Удаляем все теги
                        foreach ($video->getTags() as $oldTag) {
                            $video->removeTag($oldTag);
                        }
                        // Добавляем новые
                        foreach ($tags as $tag) {
                            $video->addTag($tag);
                        }
                    }
                    $this->em->flush();
                    $this->addFlash('success', "Теги заменены у {$count} видео");
                } else {
                    $this->addFlash('error', 'Не указаны теги');
                }
                break;

            default:
                $this->addFlash('error', 'Неизвестное действие');
        }

        return $this->redirectToRoute('admin_videos');
    }

    /**
     * Создаёт задачи на удаление файлов из удалённых хранилищ.
     * 
     * Requirement 5.1: WHEN a video is deleted THEN the System SHALL 
     * queue deletion jobs for all associated remote files
     * 
     * Property 10: For any video deletion with N associated VideoFile records 
     * on remote storage, exactly N DeleteFromStorageMessage jobs SHALL be queued.
     */
    private function queueRemoteFileDeletions(Video $video): void
    {
        foreach ($video->getEncodedFiles() as $videoFile) {
            // Проверяем, что файл на удалённом хранилище
            if ($videoFile->isRemote()) {
                $storage = $videoFile->getStorage();
                $remotePath = $videoFile->getRemotePath();
                
                if ($storage !== null && $remotePath !== null) {
                    $storageId = $storage->getId();
                    if ($storageId !== null) {
                        $this->messageBus->dispatch(
                            new DeleteFromStorageMessage($storageId, $remotePath)
                        );
                    }
                }
            }
        }
    }

    private function handleSave(Request $request, Video $video): Response
    {
        $isNew = $video->getId() === null;
        $oldStatus = $video->getStatus();
        $oldCategories = $video->getCategories()->toArray();
        
        $video->setTitle($request->request->get('title'));
        $video->setDescription($request->request->get('description'));
        $newStatus = $request->request->get('status', Video::STATUS_DRAFT);
        $video->setStatus($newStatus);
        $video->setFeatured($request->request->get('is_featured') === '1');
        
        // Обработка канала
        $channelId = $request->request->get('channel');
        if ($channelId) {
            $channel = $this->channelRepository->find($channelId);
            $video->setChannel($channel);
        } else {
            $video->setChannel(null);
        }
        
        // Обработка категорий (мультивыбор)
        $categoryIds = $request->request->all('categories');
        if (empty($categoryIds)) {
            // Обратная совместимость с одиночным выбором
            $categoryId = $request->request->get('category');
            if ($categoryId) {
                $categoryIds = [$categoryId];
            }
        }
        
        // Сохраняем старые ID категорий для сравнения
        $oldCategoryIds = array_map(fn($c) => $c->getId(), $oldCategories);
        $newCategoryIds = array_map('intval', $categoryIds);
        
        $video->getCategories()->clear();
        $newCategories = [];
        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryRepository->find($categoryId);
            if ($category) {
                $video->addCategory($category);
                $newCategories[] = $category;
            }
        }
        
        // Обновляем счётчики категорий
        $wasPublished = !$isNew && $oldStatus === Video::STATUS_PUBLISHED;
        $isPublished = $newStatus === Video::STATUS_PUBLISHED;
        
        if ($wasPublished && !$isPublished) {
            // Видео снято с публикации — уменьшаем счётчики старых категорий
            foreach ($oldCategories as $category) {
                $category->setVideosCount(max(0, $category->getVideosCount() - 1));
            }
        } elseif (!$wasPublished && $isPublished) {
            // Видео опубликовано — увеличиваем счётчики новых категорий
            foreach ($newCategories as $category) {
                $category->setVideosCount($category->getVideosCount() + 1);
            }
        } elseif ($wasPublished && $isPublished) {
            // Видео остаётся опубликованным — обновляем счётчики при изменении категорий
            // Уменьшаем для удалённых категорий
            foreach ($oldCategories as $category) {
                if (!in_array($category->getId(), $newCategoryIds, true)) {
                    $category->setVideosCount(max(0, $category->getVideosCount() - 1));
                }
            }
            // Увеличиваем для добавленных категорий
            foreach ($newCategories as $category) {
                if (!in_array($category->getId(), $oldCategoryIds, true)) {
                    $category->setVideosCount($category->getVideosCount() + 1);
                }
            }
        }
        
        $tagIds = $request->request->all('tags');
        $video->getTags()->clear();
        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find($tagId);
            if ($tag) {
                $video->addTag($tag);
            }
        }
        
        // Обработка моделей-участников (Requirements 7.1, 7.2, 7.3)
        $performerIds = $request->request->all('performers');
        $oldPerformers = $video->getPerformers()->toArray();
        $newPerformerIds = array_map('intval', $performerIds);
        
        // Удаляем старых участников и уменьшаем их счётчики
        foreach ($oldPerformers as $oldPerformer) {
            if (!in_array($oldPerformer->getId(), $newPerformerIds, true)) {
                $video->removePerformer($oldPerformer);
                // Уменьшаем счётчик видео у модели (Requirement 7.3)
                $oldPerformer->setVideosCount(max(0, $oldPerformer->getVideosCount() - 1));
            }
        }
        
        // Добавляем новых участников и увеличиваем их счётчики
        $oldPerformerIds = array_map(fn($p) => $p->getId(), $oldPerformers);
        foreach ($newPerformerIds as $performerId) {
            $performer = $this->modelProfileRepository->find($performerId);
            if ($performer && !in_array($performerId, $oldPerformerIds, true)) {
                $video->addPerformer($performer);
                // Увеличиваем счётчик видео у модели (Requirement 7.2)
                $performer->setVideosCount($performer->getVideosCount() + 1);
            }
        }
        
        $videoFile = $request->files->get('video_file');
        if ($videoFile && $videoFile->isValid()) {
            $slugger = new AsciiSlugger();
            $originalFilename = pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $videoFile->guessExtension();

            // Создаем временную директорию
            $tempDir = $this->getParameter('kernel.project_dir') . '/public/media/videos/tmp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            if (!$isNew && $video->getTempVideoFile()) {
                $oldPath = $this->getParameter('kernel.project_dir') . '/public/media/' . $video->getTempVideoFile();
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $videoFile->move($tempDir, $newFilename);
            $video->setTempVideoFile("videos/tmp/{$newFilename}");
            $video->setProcessingStatus('pending');
        }
        
        if ($isNew) {
            $video->setCreatedBy($this->getUser());
        }
        
        if (!$video->getSlug()) {
            $slugger = new AsciiSlugger();
            $slug = $slugger->slug($video->getTitle())->lower();
            $baseSlug = $slug;
            $counter = 1;
            
            while ($this->videoRepository->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            $video->setSlug($slug);
        }
        
        $this->em->persist($video);
        $this->em->flush();
        
        // Автоматически отправляем в очередь если есть видео файл для обработки
        if ($video->getTempVideoFile()) {
            // Устанавливаем статус pending если ещё не обработано
            if ($video->getProcessingStatus() !== 'completed') {
                $video->setProcessingStatus('pending');
                $video->setProcessingProgress(0);
                $this->em->flush();
            }
            
            // Отправляем в очередь
            $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
            $this->addFlash('success', ($isNew ? 'Видео создано' : 'Видео обновлено') . ' и отправлено на обработку');
        } else {
            $this->addFlash('success', $isNew ? 'Видео создано' : 'Видео обновлено');
        }
        
        return $this->redirectToRoute('admin_videos');
    }

    #[Route('/{id}/chapters', name: 'admin_videos_chapters')]
    public function chapters(Video $video): Response
    {
        return $this->render('admin/video/chapters.html.twig', [
            'video' => $video,
        ]);
    }

    #[Route('/export/{format}', name: 'admin_videos_export', requirements: ['format' => 'csv|xlsx'])]
    public function export(Request $request, string $format): Response
    {
        // Получаем те же фильтры что и в списке
        $status = $request->query->get('status');
        $categoryId = $request->query->get('category');
        $authorId = $request->query->get('author');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $search = $request->query->get('search');
        
        $filters = [
            'status' => $status,
            'categoryId' => $categoryId,
            'authorId' => $authorId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
        ];
        
        // Получаем все видео без пагинации
        $videos = $this->videoRepository->findForAdminList(null, 0, null, $filters);
        
        if ($format === 'csv') {
            return $this->exportCsv($videos);
        }
        
        return $this->exportExcel($videos);
    }

    private function exportCsv(array $videos): Response
    {
        $filename = 'videos_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        $handle = fopen('php://temp', 'r+');
        
        // BOM для корректного отображения UTF-8 в Excel
        fwrite($handle, "\xEF\xBB\xBF");
        
        // Заголовки
        fputcsv($handle, [
            'ID',
            'Название',
            'Slug',
            'Категория',
            'Теги',
            'Модели',
            'Канал',
            'Статус',
            'Обработка',
            'Длительность',
            'Разрешение',
            'Размер',
            'Показы',
            'Просмотры',
            'CTR (%)',
            'Лайки',
            'Дизлайки',
            'Комментарии',
            'Автор',
            'Дата создания',
            'Дата публикации',
            'URL',
        ], ';');
        
        // Данные
        foreach ($videos as $video) {
            $categories = array_map(fn($c) => $c->getName(), $video->getCategories()->toArray());
            $tags = array_map(fn($t) => $t->getName(), $video->getTags()->toArray());
            $performers = array_map(fn($p) => $p->getDisplayName(), $video->getPerformers()->toArray());
            
            fputcsv($handle, [
                $video->getId(),
                $video->getTitle(),
                $video->getSlug(),
                implode(', ', $categories),
                implode(', ', $tags),
                implode(', ', $performers),
                $video->getChannel()?->getName() ?? '-',
                $video->getStatus(),
                $video->getProcessingStatus(),
                $video->getDurationFormatted(),
                $video->getResolution() ?? '-',
                $video->getFileSizeFormatted(),
                $video->getImpressionsCount(),
                $video->getViewsCount(),
                $video->getCtr(),
                $video->getLikesCount(),
                $video->getDislikesCount(),
                $video->getCommentsCount(),
                $video->getCreatedBy()?->getUsername() ?? '-',
                $video->getCreatedAt()->format('d.m.Y H:i'),
                $video->getPublishedAt()?->format('d.m.Y H:i') ?? '-',
                $this->generateUrl('video_detail', ['slug' => $video->getSlug()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            ], ';');
        }
        
        rewind($handle);
        $response->setContent(stream_get_contents($handle));
        fclose($handle);
        
        return $response;
    }

    private function exportExcel(array $videos): Response
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Заголовки
        $headers = [
            'ID', 'Название', 'Slug', 'Категория', 'Теги', 'Модели', 'Канал',
            'Статус', 'Обработка', 'Длительность', 'Разрешение', 'Размер',
            'Показы', 'Просмотры', 'CTR (%)', 'Лайки', 'Дизлайки', 'Комментарии',
            'Автор', 'Дата создания', 'Дата публикации', 'URL'
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Стилизация заголовков
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:V1')->applyFromArray($headerStyle);
        
        // Данные
        $row = 2;
        foreach ($videos as $video) {
            $categories = array_map(fn($c) => $c->getName(), $video->getCategories()->toArray());
            $tags = array_map(fn($t) => $t->getName(), $video->getTags()->toArray());
            $performers = array_map(fn($p) => $p->getDisplayName(), $video->getPerformers()->toArray());
            
            $sheet->fromArray([
                $video->getId(),
                $video->getTitle(),
                $video->getSlug(),
                implode(', ', $categories),
                implode(', ', $tags),
                implode(', ', $performers),
                $video->getChannel()?->getName() ?? '-',
                $video->getStatus(),
                $video->getProcessingStatus(),
                $video->getDurationFormatted(),
                $video->getResolution() ?? '-',
                $video->getFileSizeFormatted(),
                $video->getImpressionsCount(),
                $video->getViewsCount(),
                $video->getCtr(),
                $video->getLikesCount(),
                $video->getDislikesCount(),
                $video->getCommentsCount(),
                $video->getCreatedBy()?->getUsername() ?? '-',
                $video->getCreatedAt()->format('d.m.Y H:i'),
                $video->getPublishedAt()?->format('d.m.Y H:i') ?? '-',
                $this->generateUrl('video_detail', ['slug' => $video->getSlug()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            ], null, 'A' . $row);
            
            $row++;
        }
        
        // Автоширина колонок
        foreach (range('A', 'V') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Создаем файл
        $filename = 'videos_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        ob_start();
        $writer->save('php://output');
        $response->setContent(ob_get_clean());
        
        return $response;
    }
}
