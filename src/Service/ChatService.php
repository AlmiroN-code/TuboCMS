<?php

namespace App\Service;

use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatMessageRepository;
use Doctrine\ORM\EntityManagerInterface;

class ChatService
{
    public function __construct(
        private ChatMessageRepository $messageRepository,
        private EntityManagerInterface $em
    ) {
    }

    public function sendMessage(string $roomId, User $user, string $message, string $type = 'text', ?int $replyToId = null): ChatMessage
    {
        $chatMessage = new ChatMessage();
        $chatMessage->setRoomId($roomId);
        $chatMessage->setUser($user);
        $chatMessage->setMessage($message);
        $chatMessage->setType($type);
        
        if ($replyToId) {
            $chatMessage->setReplyToId($replyToId);
        }

        $this->messageRepository->save($chatMessage);

        return $chatMessage;
    }

    public function getMessages(string $roomId, int $limit = 50, int $offset = 0): array
    {
        return $this->messageRepository->findByRoom($roomId, $limit, $offset);
    }

    public function getRecentMessages(string $roomId, int $limit = 50): array
    {
        return $this->messageRepository->findRecentByRoom($roomId, $limit);
    }

    public function deleteMessage(int $messageId, User $user): bool
    {
        $message = $this->messageRepository->find($messageId);
        
        if (!$message || $message->getUser()->getId() !== $user->getId()) {
            return false;
        }

        $message->setDeleted(true);
        $message->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        return true;
    }

    public function cleanOldMessages(int $daysOld = 30): int
    {
        $before = new \DateTimeImmutable("-{$daysOld} days");
        return $this->messageRepository->deleteOldMessages($before);
    }

    public function formatMessageForClient(ChatMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'roomId' => $message->getRoomId(),
            'user' => [
                'id' => $message->getUser()->getId(),
                'username' => $message->getUser()->getUsername(),
                'avatar' => $message->getUser()->getAvatar(),
                'isVerified' => $message->getUser()->isVerified(),
            ],
            'message' => $message->getMessage(),
            'type' => $message->getType(),
            'createdAt' => $message->getCreatedAt()->format('c'),
            'replyToId' => $message->getReplyToId(),
        ];
    }
}
