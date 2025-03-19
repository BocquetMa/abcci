<?php

namespace App\Repository;

use App\Entity\Inscription;
use App\Entity\Utilisateur;
use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inscription>
 */
class InscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscription::class);
    }

    /**
     * Récupère toutes les inscriptions en attente
     */
    public function findAllEnAttente()
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->orderBy('i.dateInscription', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les inscriptions d'un formateur (formations qu'il anime)
     */
    public function findByFormateur(Utilisateur $formateur)
    {
        return $this->createQueryBuilder('i')
            ->join('i.formation', 'f')
            ->andWhere('f.formateur = :formateur')
            ->setParameter('formateur', $formateur)
            ->orderBy('i.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les inscriptions en attente d'un formateur
     */
    public function findEnAttenteByFormateur(Utilisateur $formateur)
    {
        return $this->createQueryBuilder('i')
            ->join('i.formation', 'f')
            ->andWhere('f.formateur = :formateur')
            ->andWhere('i.statut = :statut')
            ->setParameter('formateur', $formateur)
            ->setParameter('statut', 'en_attente')
            ->orderBy('i.dateInscription', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur est déjà inscrit à une formation
     */
    public function estDejaInscrit(Utilisateur $utilisateur, Formation $formation): bool
    {
        $result = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.utilisateur = :utilisateur')
            ->andWhere('i.formation = :formation')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('formation', $formation)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $result > 0;
    }

    /**
     * Récupère les inscriptions validées pour une période donnée
     */
    public function findValideesPourPeriode(\DateTime $debut, \DateTime $fin)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.statut = :statut')
            ->andWhere('i.dateDebut >= :debut')
            ->andWhere('i.dateFin <= :fin')
            ->setParameter('statut', 'acceptee')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('i.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques d'inscription pour un formateur
     */
    public function getStatistiquesFormateur(Utilisateur $formateur)
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id) as total, i.statut')
            ->join('i.formation', 'f')
            ->andWhere('f.formateur = :formateur')
            ->setParameter('formateur', $formateur)
            ->groupBy('i.statut');
        
        $results = $qb->getQuery()->getResult();
        
        // Transformer les résultats en tableau associatif
        $stats = [
            'en_attente' => 0,
            'acceptee' => 0,
            'refusee' => 0,
            'total' => 0
        ];
        
        foreach ($results as $result) {
            $stats[$result['statut']] = $result['total'];
            $stats['total'] += $result['total'];
        }
        
        return $stats;
    }
}