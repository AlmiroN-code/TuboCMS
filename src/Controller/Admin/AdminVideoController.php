<?php

namespace App\Controller\Admin;

use App\Entity\Video;
use App\Message\ProcessVideoEncodingMessage;
use App\Repository\VideoRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
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
        private EntityManagerInterface $em,
        private MessageBusInterface $messageBus
    ) {
    }

    #[Route('', name: 'admin_videos')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $videos = $this->videoRepository->findForAdminList($limit, $offset);
        $total = $this->videoRepository->count([]);
        
        return $this->render('admin/videos/index.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'total' => $total,
            'pages' => ceil($total / $limit),
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
        $this->em->remove($video);
        $this->em->flush();
        
        return new Response('');
    }

    private function handleSave(Request $request, Video $video): Response
    {
        $isNew = $video->getId() === null;
        
        $video->setTitle($request->request->get('title'));
        $video->setDescription($request->request->get('description'));
        $video->setStatus($request->request->get('status', Video::STATUS_DRAFT));
        
        $categoryId = $request->request->get('category');
        if ($categoryId) {
            $category = $this->categoryRepository->find($categoryId);
            $video->setCategory($category);
        }
        
        $tagIds = $request->request->all('tags');
        $video->getTags()->clear();
        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find($tagId);
            if ($tag) {
                $video->addTag($tag);
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
        
        if ($videoFile && $videoFile->isValid()) {
            // Отправляем в очередь для асинхронной обработки
            $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
        }
        
        $this->addFlash('success', $isNew ? 'Видео создано' : 'Видео обновлено');
        return $this->redirectToRoute('admin_videos');
    }


}
