<?php

namespace App\Controller;

use App\Entity\Video;
use App\Form\VideoUploadType;
use App\Message\ProcessVideoEncodingMessage;
use App\Service\FileValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;

#[Route('/videos')]
#[IsGranted('ROLE_USER')]
class VideoUploadController extends AbstractController
{
    #[Route('/upload', name: 'video_upload')]
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        MessageBusInterface $messageBus,
        RateLimiterFactory $videoUploadLimiter,
        FileValidationService $fileValidator,
        LoggerInterface $logger
    ): Response
    {
        // Apply rate limiting
        $limiter = $videoUploadLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Слишком много загрузок. Попробуйте позже.');
            $logger->warning('Rate limit exceeded for video upload', [
                'ip' => $request->getClientIp(),
                'user' => $this->getUser()->getUserIdentifier()
            ]);
            return $this->redirectToRoute('video_my_videos');
        }
        $video = new Video();
        $form = $this->createForm(VideoUploadType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $videoFile = $form->get('videoFile')->getData();

            if ($videoFile) {
                // Валидируем файл
                $validationErrors = $fileValidator->validateVideoFile($videoFile);
                if (!empty($validationErrors)) {
                    foreach ($validationErrors as $error) {
                        $this->addFlash('error', $error);
                    }
                    return $this->redirectToRoute('video_upload');
                }

                // Генерируем безопасное имя файла
                $newFilename = $fileValidator->generateSecureFilename($videoFile->getClientOriginalName()) . '.mp4';

                try {
                    // Создаем временную директорию
                    $tempDir = $this->getParameter('kernel.project_dir').'/public/media/videos/tmp';
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0777, true);
                    }
                    
                    $videoFile->move($tempDir, $newFilename);
                    
                    $video->setTempVideoFile('videos/tmp/'.$newFilename);
                    $video->setStatus(Video::STATUS_PROCESSING);
                    $video->setProcessingStatus('pending');
                    
                    $logger->info('Video file uploaded successfully', [
                        'user' => $this->getUser()->getUserIdentifier(),
                        'filename' => $newFilename,
                        'originalName' => $videoFile->getClientOriginalName(),
                        'size' => $videoFile->getSize()
                    ]);
                    
                } catch (FileException $e) {
                    $logger->error('File upload error', [
                        'error' => $e->getMessage(),
                        'user' => $this->getUser()->getUserIdentifier()
                    ]);
                    $this->addFlash('error', 'Ошибка загрузки файла');
                    return $this->redirectToRoute('video_upload');
                } catch (\Exception $e) {
                    $logger->error('Video processing error', [
                        'error' => $e->getMessage(),
                        'user' => $this->getUser()->getUserIdentifier()
                    ]);
                    $this->addFlash('error', 'Ошибка обработки видео: ' . $e->getMessage());
                    return $this->redirectToRoute('video_upload');
                }
            }

            // Generate slug
            $slug = $slugger->slug($video->getTitle())->lower();
            $baseSlug = $slug;
            $counter = 1;
            while ($em->getRepository(Video::class)->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }
            $video->setSlug($slug);

            $video->setCreatedBy($this->getUser());

            $em->persist($video);
            $em->flush();

            // Отправляем сообщение в очередь для асинхронной обработки
            $logger->info('DISPATCHING VIDEO MESSAGE', [
                'video_id' => $video->getId(),
                'title' => $video->getTitle(),
                'status' => $video->getStatus()
            ]);
            $messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
            $logger->info('MESSAGE DISPATCHED', ['video_id' => $video->getId()]);

            $this->addFlash('success', 'Видео успешно загружено! Обработка началась автоматически.');
            return $this->redirectToRoute('video_detail', ['slug' => $video->getSlug()]);
        }

        return $this->render('video/upload.html.twig', [
            'uploadForm' => $form,
        ]);
    }

    #[Route('/my-videos', name: 'video_my_videos')]
    public function myVideos(EntityManagerInterface $em): Response
    {
        $videos = $em->getRepository(Video::class)->findBy(
            ['createdBy' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('video/my_videos.html.twig', [
            'videos' => $videos,
        ]);
    }
}
