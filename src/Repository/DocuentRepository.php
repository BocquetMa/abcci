<?php

namespace App\Repository;

use App\Entity\Docuent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Docuent>
 *
 * @method Docuent|null find($id, $lockMode = null, $lockVersion = null)
 * @method Docuent|null findOneBy(array $criteria, array $orderBy = null)
 * @method Docuent[]    findAll()
 * @method Docuent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocuentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Docuent::class);
    }

//    /**
//     * @return Docuent[] Returns an array of Docuent objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Docuent
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
