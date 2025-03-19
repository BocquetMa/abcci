<?php

namespace App\Repository;

use App\Entity\Badge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

class BadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Badge::class);
    }

    /**
     * Compte le nombre de badges par type
     * @return array
     */
    public function compterBadgesParType(): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.type, COUNT(b) as nombre')
            ->groupBy('b.type')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre d'utilisateurs ayant des badges
     * @return int
     */
    public function compterUtilisateursAvecBadges(): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(DISTINCT b.utilisateur)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les badges les plus attribués
     * @param int $limite
     * @return array
     */
    public function topBadgesAttribues(int $limite = 5): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.nom, b.type, COUNT(b) as occurrences')
            ->groupBy('b.nom, b.type')
            ->orderBy('occurrences', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les badges d'un utilisateur avec filtres
     * @param int $utilisateurId
     * @param array $filtres
     * @return array
     */
    public function badgesUtilisateur(int $utilisateurId, array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateurId);

        // Filtres optionnels
        if (!empty($filtres['type'])) {
            $qb->andWhere('b.type = :type')
               ->setParameter('type', $filtres['type']);
        }

        if (!empty($filtres['min_points'])) {
            $qb->andWhere('b.points >= :min_points')
               ->setParameter('min_points', $filtres['min_points']);
        }

        if (!empty($filtres['date_debut'])) {
            $qb->andWhere('b.obtenuLe >= :date_debut')
               ->setParameter('date_debut', $filtres['date_debut']);
        }

        return $qb->orderBy('b.obtenuLe', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Calcule la progression des badges pour un utilisateur
     * @param int $utilisateurId
     * @return array
     */
    public function calculerProgressionBadges(int $utilisateurId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Requête pour calculer la progression des badges par type
        $sql = "
            SELECT 
                b.type,
                COUNT(b.id) as total_badges,
                MAX(b.points) as points_max,
                AVG(b.points) as points_moyenne
            FROM badge b
            WHERE b.utilisateur_id = :utilisateur
            GROUP BY b.type
        ";

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['utilisateur' => $utilisateurId]);

        return $resultSet->fetchAllAssociative();
    }

    /**
     * Récupère les badges personnalisés récemment attribués
     * @param int $limite
     * @return array
     */
    public function badgesPersonnalisesRecents(int $limite = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.type = :type_achievement')
            ->setParameter('type_achievement', Badge::TYPE_ACHIEVEMENT)
            ->orderBy('b.obtenuLe', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les badges uniques d'un utilisateur
     * @param int $utilisateurId
     * @return array
     */
    public function findBadgesUniques(int $utilisateurId): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('DISTINCT b.nom, b.type')
            ->where('b.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateurId);

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si un badge spécifique existe déjà pour un utilisateur
     * @param int $utilisateurId
     * @param string $nomBadge
     * @param string $typeBadge
     * @return bool
     */
    public function badgeExistePourUtilisateur(
        int $utilisateurId, 
        string $nomBadge, 
        string $typeBadge
    ): bool {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.utilisateur = :utilisateur')
            ->andWhere('b.nom = :nom')
            ->andWhere('b.type = :type')
            ->setParameters([
                'utilisateur' => $utilisateurId,
                'nom' => $nomBadge,
                'type' => $typeBadge
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Génère un rapport détaillé sur les badges
     * @param array $filtres
     * @return array
     */
    public function rapportBadges(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.type', 'b.nom', 'COUNT(b.id) as total', 'AVG(b.points) as points_moyen')
            ->groupBy('b.type', 'b.nom');

        // Filtres optionnels
        if (!empty($filtres['date_debut'])) {
            $qb->andWhere('b.obtenuLe >= :date_debut')
               ->setParameter('date_debut', $filtres['date_debut']);
        }

        if (!empty($filtres['date_fin'])) {
            $qb->andWhere('b.obtenuLe <= :date_fin')
               ->setParameter('date_fin', $filtres['date_fin']);
        }

        if (!empty($filtres['type'])) {
            $qb->andWhere('b.type = :type')
               ->setParameter('type', $filtres['type']);
        }

        $qb->orderBy('total', 'DESC');

        return $qb->getQuery()->getResult();
    }
}