<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    public function save(ChatMessage $message): void
    {
        $this->getEntityManager()->persist($message);
        $this->getEntityManager()->flush();
    }

    public function findByRoom(string $roomId, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.roomId = :roomId')
            ->andWhere('m.isDeleted = false')
            ->setParameter('roomId', $roomId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findRecentByRoom(string $roomId, int $limit = 50): array
    {
        $messages = $this->findByRoom($roomId, $limit);
        return array_reverse($messages); // Возвращаем в хронологическом порядке
    }

    public function countByRoom(string $roomId): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.roomId = :roomId')
            ->andWhere('m.isDeleted = false')
            ->setParameter('roomId', $roomId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteOldMessages(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.isDeleted', 'true')
            ->set('m.deletedAt', ':now')
            ->where('m.createdAt < :before')
            ->setParameter('before', $before)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
