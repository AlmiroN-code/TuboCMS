<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findPaginated(int $page, int $limit, string $search = '', string $sortBy = 'newest'): array
    {
        $qb = $this->createQueryBuilder('u');
        
        if ($search) {
            $qb->where('u.username LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        switch ($sortBy) {
            case 'popular':
                $qb->orderBy('u.subscribersCount', 'DESC');
                break;
            case 'videos':
                $qb->orderBy('u.videosCount', 'DESC');
                break;
            case 'oldest':
                $qb->orderBy('u.createdAt', 'ASC');
                break;
            default: // newest
                $qb->orderBy('u.createdAt', 'DESC');
        }
        
        // Подсчет общего количества
        $totalQb = clone $qb;
        $total = $totalQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        
        // Получение пользователей с пагинацией
        $users = $qb->setMaxResults($limit)
                   ->setFirstResult(($page - 1) * $limit)
                   ->getQuery()
                   ->getResult();
        
        return [
            'users' => $users,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Подсчет пользователей за период
     */
    public function countByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt < :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
