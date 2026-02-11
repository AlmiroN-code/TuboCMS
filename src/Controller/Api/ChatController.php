<?php

namespace App\Controller\Api;

use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private ChatService $chatService
    ) {
    }

    #[Route('/rooms/{roomId}/messages', name: 'api_chat_messages', methods: ['GET'])]
    public function getMessages(string $roomId, Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));
        $offset = max(0, $request->query->getInt('offset', 0));

        $messages = $this->chatService->getMessages($roomId, $limit, $offset);
        
        $formattedMessages = array_map(
            fn($msg) => $this->chatService->formatMessageForClient($msg),
            $messages
        );

        return $this->json([
            'messages' => $formattedMessages,
            'count' => count($formattedMessages),
        ]);
    }

    #[Route('/rooms/{roomId}/messages', name: 'api_chat_send_message', methods: ['POST'])]
    public function sendMessage(string $roomId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['message']) || empty(trim($data['message']))) {
            return $this->json(['error' => 'Message is required'], Response::HTTP_BAD_REQUEST);
        }

        $message = trim($data['message']);
        $type = $data['type'] ?? 'text';
        $replyToId = $data['replyToId'] ?? null;

        if (mb_strlen($message) > 1000) {
            return $this->json(['error' => 'Message too long'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $chatMessage = $this->chatService->sendMessage($roomId, $user, $message, $type, $replyToId);

        return $this->json([
            'success' => true,
            'message' => $this->chatService->formatMessageForClient($chatMessage),
        ], Response::HTTP_CREATED);
    }

    #[Route('/messages/{id}', name: 'api_chat_delete_message', methods: ['DELETE'])]
    public function deleteMessage(int $id): JsonResponse
    {
        $user = $this->getUser();
        $success = $this->chatService->deleteMessage($id, $user);

        if (!$success) {
            return $this->json(['error' => 'Cannot delete message'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/rooms/{roomId}/recent', name: 'api_chat_recent_messages', methods: ['GET'])]
    public function getRecentMessages(string $roomId, Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));
        
        $messages = $this->chatService->getRecentMessages($roomId, $limit);
        
        $formattedMessages = array_map(
            fn($msg) => $this->chatService->formatMessageForClient($msg),
            $messages
        );

        return $this->json([
            'messages' => $formattedMessages,
            'count' => count($formattedMessages),
        ]);
    }
}
