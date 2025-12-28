<?php

namespace App\Repository;

use App\Entity\Storage;
use App\Entity\VideoFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoFile>
 */
class VideoFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoFile::class);
    }

    /**
     * Находит все файлы для указанного хранилища.
     * Если storage = null, возвращает файлы без привязки к хранилищу (локальные).
     * 
     * @return VideoFile[]
     */
    public function findByStorage(?Storage $storage): array
    {
        return $this->findBy(['storage' => $storage]);
    }

    /**
     * Возвращает ID всех файлов для указанного хранилища.
     * Оптимизировано для больших объёмов данных.
     * 
     * @return int[]
     */
    public function findIdsByStorage(?Storage $storage): array
    {
        $qb = $this->createQueryBuilder('vf')
            ->select('vf.id');
        
        if ($storage === null) {
            $qb->where('vf.storage IS NULL');
        } else {
            $qb->where('vf.storage = :storage')
               ->setParameter('storage', $storage);
        }
        
        $result = $qb->getQuery()->getScalarResult();
        
        return array_column($result, 'id');
    }
}
