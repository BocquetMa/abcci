<?php

namespace App\Repository;

use App\Entity\QuizTentative;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizTentative>
 *
 * @method QuizTentative|null find($id, $lockMode = null, $lockVersion = null)
 * @method QuizTentative|null findOneBy(array $criteria, array $orderBy = null)
 * @method QuizTentative[]    findAll()
 * @method QuizTentative[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuizTentativeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizTentative::class);
    }

//    /**
//     * @return QuizTentative[] Returns an array of QuizTentative objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('q')
//            ->andWhere('q.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('q.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?QuizTentative
//    {
//        return $this->createQueryBuilder('q')
//            ->andWhere('q.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
