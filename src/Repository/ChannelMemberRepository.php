<?php

namespace App\Repository;

use App\Entity\ChannelMember;
use App\Entity\Channel;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelMember>
 */
class ChannelMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelMember::class);
    }

    public function save(ChannelMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ChannelMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти участника канала
     */
    public function findMember(User $user, Channel $channel): ?ChannelMember
    {
        return $this->createQueryBuilder('cm')
            ->andWhere('cm.user = :user')
            ->andWhere('cm.channel = :channel')
            ->setParameter('user', $user)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Проверить является ли пользователь участником канала
     */
    public function isMember(User $user, Channel $channel): bool
    {
        return $this->findMember($user, $channel) !== null;
    }

    /**
     * Найти участников канала
     */
    public function findChannelMembers(Channel $channel, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('cm')
            ->join('cm.user', 'u')
            ->andWhere('cm.channel = :channel')
            ->andWhere('cm.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('active', true)
            ->orderBy('cm.role', 'ASC')
            ->addOrderBy('cm.joinedAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти каналы пользователя (где он участник)
     */
    public function findUserChannels(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('cm')
            ->join('cm.channel', 'c')
            ->andWhere('cm.user = :user')
            ->andWhere('cm.isActive = :active')
            ->andWhere('c.isActive = :channelActive')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->setParameter('channelActive', true)
            ->orderBy('cm.role', 'ASC')
            ->addOrderBy('cm.joinedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти администраторов канала
     */
    public function findChannelAdmins(Channel $channel): array
    {
        return $this->createQueryBuilder('cm')
            ->join('cm.user', 'u')
            ->andWhere('cm.channel = :channel')
            ->andWhere('cm.role IN (:roles)')
            ->andWhere('cm.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('roles', [ChannelMember::ROLE_ADMIN, ChannelMember::ROLE_OWNER])
            ->setParameter('active', true)
            ->orderBy('cm.role', 'ASC')
            ->addOrderBy('cm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти модераторов канала
     */
    public function findChannelModerators(Channel $channel): array
    {
        return $this->createQueryBuilder('cm')
            ->join('cm.user', 'u')
            ->andWhere('cm.channel = :channel')
            ->andWhere('cm.role IN (:roles)')
            ->andWhere('cm.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('roles', [
                ChannelMember::ROLE_MODERATOR,
                ChannelMember::ROLE_ADMIN,
                ChannelMember::ROLE_OWNER
            ])
            ->setParameter('active', true)
            ->orderBy('cm.role', 'ASC')
            ->addOrderBy('cm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчет участников канала
     */
    public function countChannelMembers(Channel $channel): int
    {
        return $this->createQueryBuilder('cm')
            ->select('COUNT(cm.id)')
            ->andWhere('cm.channel = :channel')
            ->andWhere('cm.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Подсчет каналов пользователя
     */
    public function countUserChannels(User $user): int
    {
        return $this->createQueryBuilder('cm')
            ->select('COUNT(cm.id)')
            ->join('cm.channel', 'c')
            ->andWhere('cm.user = :user')
            ->andWhere('cm.isActive = :active')
            ->andWhere('c.isActive = :channelActive')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->setParameter('channelActive', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Проверить права пользователя в канале
     */
    public function getUserRole(User $user, Channel $channel): ?string
    {
        $member = $this->findMember($user, $channel);
        return $member?->getRole();
    }

    /**
     * Проверить может ли пользователь управлять каналом
     */
    public function canManageChannel(User $user, Channel $channel): bool
    {
        $member = $this->findMember($user, $channel);
        return $member && $member->canManageChannel();
    }

    /**
     * Проверить может ли пользователь модерировать контент
     */
    public function canModerateContent(User $user, Channel $channel): bool
    {
        $member = $this->findMember($user, $channel);
        return $member && $member->canModerateContent();
    }
}