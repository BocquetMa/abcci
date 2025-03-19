<?php

namespace App\Repository;

use App\Entity\MotCle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MotCleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MotCle::class);
    }

    /**
     * Récupère tous les mots-clés triés par libellé
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les mots-clés les plus utilisés
     */
    public function findMostUsed(int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'COUNT(f.id) as formationCount')
            ->leftJoin('m.formations', 'f')
            ->groupBy('m.id')
            ->orderBy('formationCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}