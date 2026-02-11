<?php

namespace App\Controller\Admin;

use App\Repository\ChatMessageRepository;
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/chat')]
#[IsGranted('ROLE_ADMIN')]
class AdminChatController extends AbstractController
{
    public function __construct(
        private ChatMessageRepository $messageRepository,
        private ChatService $chatService,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'admin_chat')]
    public function index(Request $request): Response
    {
        $roomId = $request->query->get('room', 'global');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 50);
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 50;
        }
        
        $limit = $perPage;
        $offset = ($page - 1) * $limit;

        $messages = $this->messageRepository->findByRoom($roomId, $limit, $offset);
        $total = $this->messageRepository->countByRoom($roomId);

        // Получаем список комнат (уникальные roomId)
        $rooms = $this->em->createQuery(
            'SELECT DISTINCT m.roomId FROM App\Entity\ChatMessage m ORDER BY m.roomId'
        )->getResult();

        return $this->render('admin/chat/index.html.twig', [
            'messages' => array_reverse($messages),
            'rooms' => array_column($rooms, 'roomId'),
            'currentRoom' => $roomId,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/message/{id}/delete', name: 'admin_chat_delete_message', methods: ['POST'])]
    public function deleteMessage(int $id): Response
    {
        $message = $this->messageRepository->find($id);
        
        if ($message) {
            $message->setDeleted(true);
            $message->setDeletedAt(new \DateTimeImmutable());
            $this->em->flush();
            
            $this->addFlash('success', 'Сообщение удалено');
        }

        return $this->redirectToRoute('admin_chat');
    }

    #[Route('/cleanup', name: 'admin_chat_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): Response
    {
        $days = max(1, $request->request->getInt('days', 30));
        $deleted = $this->chatService->cleanOldMessages($days);
        
        $this->addFlash('success', "Удалено сообщений: {$deleted}");
        
        return $this->redirectToRoute('admin_chat');
    }
}
