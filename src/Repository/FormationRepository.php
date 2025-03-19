<?php

namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function findByFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('f');

        if (!empty($filters['theme'])) {
            $qb->andWhere('f.theme = :theme')
               ->setParameter('theme', $filters['theme']);
        }

        if (!empty($filters['niveau'])) {
            $qb->andWhere('f.niveau = :niveau')
               ->setParameter('niveau', $filters['niveau']);
        }

        if (!empty($filters['dureeMin'])) {
            $qb->andWhere('f.duree >= :dureeMin')
               ->setParameter('dureeMin', $filters['dureeMin']);
        }

        if (!empty($filters['dureeMax'])) {
            $qb->andWhere('f.duree <= :dureeMax')
               ->setParameter('dureeMax', $filters['dureeMax']);
        }

        if (!empty($filters['prixMin'])) {
            $qb->andWhere('f.prix >= :prixMin')
               ->setParameter('prixMin', $filters['prixMin']);
        }

        if (!empty($filters['prixMax'])) {
            $qb->andWhere('f.prix <= :prixMax')
               ->setParameter('prixMax', $filters['prixMax']);
        }

        if (!empty($filters['mot_cles'])) {
            $qb->join('f.motsCles', 'mc')
               ->andWhere('mc IN (:mot_cles)')
               ->setParameter('mot_cles', $filters['mot_cles']);
        }

        return $qb->getQuery()->getResult();
    }

    public function searchFormations(string $query): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.titre LIKE :query')
            ->orWhere('f.description LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->getQuery()
            ->getResult();
    }

    public function createAdvancedSearchQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.motsCles', 'm')
            ->leftJoin('f.formateur', 'fr');

        if (!empty($filters['theme'])) {
            $qb->andWhere('f.theme = :theme')
               ->setParameter('theme', $filters['theme']);
        }

        if (!empty($filters['niveau'])) {
            $qb->andWhere('f.niveau = :niveau')
               ->setParameter('niveau', $filters['niveau']);
        }

        if (!empty($filters['dateDebut'])) {
            $qb->andWhere('f.dateDebut >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($filters['dateDebut']));
        }

        if (!empty($filters['dateFin'])) {
            $qb->andWhere('f.dateFin <= :dateFin')
               ->setParameter('dateFin', new \DateTime($filters['dateFin']));
        }

        if (!empty($filters['prixMin'])) {
            $qb->andWhere('f.prix >= :prixMin')
               ->setParameter('prixMin', $filters['prixMin']);
        }

        if (!empty($filters['prixMax'])) {
            $qb->andWhere('f.prix <= :prixMax')
               ->setParameter('prixMax', $filters['prixMax']);
        }

        if (!empty($filters['placesDisponibles'])) {
            $qb->andWhere('(f.nombrePlacesTotal - f.placesOccupees) > 0');
        }

        if (!empty($filters['motsCles'])) {
            $qb->andWhere('m.id IN (:motsCles)')
               ->setParameter('motsCles', $filters['motsCles']);
        }

        return $qb;
    }

    public function getTotalParticipants(): int
    {
        return $this->createQueryBuilder('f')
            ->select('SUM(f.placesOccupees)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getFormationsPopulaires(int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.placesOccupees', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getRevenuTotal(): float
    {
        return $this->createQueryBuilder('f')
            ->select('SUM(f.prix * f.placesOccupees)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0.0;
    }

    public function getTauxRemplissageMoyen(): float
    {
        $result = $this->createQueryBuilder('f')
            ->select('AVG((f.placesOccupees * 100) / f.nombrePlacesTotal)')
            ->where('f.nombrePlacesTotal > 0')
            ->getQuery()
            ->getSingleScalarResult();
            
        return round($result ?? 0, 2);
    }


}