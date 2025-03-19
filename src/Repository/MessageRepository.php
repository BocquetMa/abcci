<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Trouve les conversations d'un utilisateur
     * Version corrigée en utilisant SQL natif au lieu de DQL
     */
    public function findConversations(Utilisateur $utilisateur): array
    {
        $entityManager = $this->getEntityManager();
        $userId = $utilisateur->getId();
        
        // Utiliser une requête SQL native pour éviter les limitations de DQL
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Message::class, 'm');
        $rsm->addFieldResult('m', 'id', 'id');
        $rsm->addFieldResult('m', 'contenu', 'contenu');
        $rsm->addFieldResult('m', 'date_envoi', 'dateEnvoi');
        $rsm->addFieldResult('m', 'lu', 'lu');
        $rsm->addFieldResult('m', 'date_lecture', 'dateLecture');
        $rsm->addFieldResult('m', 'supprime', 'supprime');
        $rsm->addFieldResult('m', 'important', 'important');
        $rsm->addFieldResult('m', 'piece_jointe', 'pieceJointe');
        
        // Relations
        $rsm->addMetaResult('m', 'expediteur_id', 'expediteur_id');
        $rsm->addMetaResult('m', 'destinataire_id', 'destinataire_id');
        $rsm->addJoinedEntityResult('App\Entity\Utilisateur', 'u1', 'm', 'expediteur');
        $rsm->addFieldResult('u1', 'expediteur_id', 'id');
        $rsm->addJoinedEntityResult('App\Entity\Utilisateur', 'u2', 'm', 'destinataire');
        $rsm->addFieldResult('u2', 'destinataire_id', 'id');
        
        // Requête SQL native
        $sql = "
            SELECT m.*, m.expediteur_id, m.destinataire_id
            FROM messages m
            INNER JOIN (
                SELECT 
                    MAX(m2.id) as max_id,
                    CASE 
                        WHEN m2.expediteur_id = :userId THEN m2.destinataire_id 
                        ELSE m2.expediteur_id 
                    END as other_user_id
                FROM messages m2
                WHERE 
                    ((m2.expediteur_id = :userId AND m2.supprime = 0) OR (m2.destinataire_id = :userId AND m2.supprime = 0))
                GROUP BY other_user_id
            ) as latest_messages ON m.id = latest_messages.max_id
            WHERE 
                (m.expediteur_id = :userId OR m.destinataire_id = :userId) AND m.supprime = 0
            ORDER BY m.date_envoi DESC
        ";
        
        $query = $entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('userId', $userId);
        
        return $query->getResult();
    }

    /**
     * Version alternative utilisant deux requêtes DQL au lieu d'une requête SQL native
     */
    /**
 * Version alternative plus robuste avec gestion des erreurs
 */
    public function findConversationsAlternative(Utilisateur $utilisateur): array
    {
        try {
            // Récupérer tous les messages où l'utilisateur est impliqué
            $allMessages = $this->createQueryBuilder('m')
                ->where('m.expediteur = :user OR m.destinataire = :user')
                ->andWhere('m.supprime = false')
                ->setParameter('user', $utilisateur)
                ->orderBy('m.dateEnvoi', 'DESC')
                ->getQuery()
                ->getResult();
            
            // Structure pour stocker les derniers messages par conversation
            $conversationsMap = [];
            
            // Parcourir tous les messages pour identifier les conversations
            foreach ($allMessages as $message) {
                // Déterminer l'autre utilisateur dans la conversation
                $otherUser = ($message->getExpediteur()->getId() === $utilisateur->getId()) 
                    ? $message->getDestinataire() 
                    : $message->getExpediteur();
                
                $otherUserId = $otherUser->getId();
                
                // Si nous n'avons pas encore de message pour cette conversation, l'ajouter
                if (!isset($conversationsMap[$otherUserId])) {
                    $conversationsMap[$otherUserId] = $message;
                }
            }
            
            // Convertir la map en tableau de messages
            $conversations = array_values($conversationsMap);
            
            // Trier les conversations par date d'envoi décroissante
            usort($conversations, function($a, $b) {
                return $b->getDateEnvoi() <=> $a->getDateEnvoi();
            });
            
            return $conversations;
        } catch (\Exception $e) {
            // Log de l'erreur pour le débogage
            error_log('Erreur dans findConversationsAlternative: ' . $e->getMessage());
            
            // Retourner un tableau vide en cas d'erreur
            return [];
        }
    }

    /**
     * Trouve les messages entre deux utilisateurs
     */
    public function findMessagesBetweenUsers(Utilisateur $user1, Utilisateur $user2): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.expediteur = :user1 AND m.destinataire = :user2) OR (m.expediteur = :user2 AND m.destinataire = :user1)')
            ->andWhere('m.supprime = false')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->orderBy('m.dateEnvoi', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les messages non lus d'un utilisateur
     */
    public function countUnreadMessages(Utilisateur $utilisateur): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.destinataire = :utilisateur')
            ->andWhere('m.lu = false')
            ->andWhere('m.supprime = false')
            ->setParameter('utilisateur', $utilisateur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les derniers messages reçus par un utilisateur
     */
    public function findLatestReceivedMessages(Utilisateur $utilisateur, int $limit = 5): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.destinataire = :utilisateur')
            ->andWhere('m.supprime = false')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque comme lus tous les messages d'une conversation
     */
    public function markConversationAsRead(Utilisateur $currentUser, Utilisateur $otherUser): int
    {
        $qb = $this->createQueryBuilder('m')
            ->update()
            ->set('m.lu', 'true')
            ->set('m.dateLecture', ':now')
            ->where('m.destinataire = :currentUser')
            ->andWhere('m.expediteur = :otherUser')
            ->andWhere('m.lu = false')
            ->setParameter('currentUser', $currentUser)
            ->setParameter('otherUser', $otherUser)
            ->setParameter('now', new \DateTime());
        
        return $qb->getQuery()->execute();
    }

    /**
     * Recherche des messages par contenu
     */
    public function searchMessages(Utilisateur $utilisateur, string $terme): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.expediteur = :utilisateur OR m.destinataire = :utilisateur)')
            ->andWhere('m.contenu LIKE :terme')
            ->andWhere('m.supprime = false')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('m.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
 * Méthode de diagnostic pour vérifier les problèmes potentiels
 */
public function diagnosticConversations(Utilisateur $utilisateur): array
{
    $diagnosis = [];
    
    // Vérifier le nombre total de messages
    $totalMessages = $this->createQueryBuilder('m')
        ->select('COUNT(m.id)')
        ->getQuery()
        ->getSingleScalarResult();
    $diagnosis['totalMessages'] = $totalMessages;
    
    // Vérifier les messages de l'utilisateur
    $userMessages = $this->createQueryBuilder('m')
        ->select('COUNT(m.id)')
        ->where('m.expediteur = :user OR m.destinataire = :user')
        ->setParameter('user', $utilisateur)
        ->getQuery()
        ->getSingleScalarResult();
    $diagnosis['userMessages'] = $userMessages;
    
    // Vérifier les messages avec supprime = true
    $deletedMessages = $this->createQueryBuilder('m')
        ->select('COUNT(m.id)')
        ->where('m.supprime = true')
        ->getQuery()
        ->getSingleScalarResult();
    $diagnosis['deletedMessages'] = $deletedMessages;
    
    // Vérifier les messages avec des utilisateurs manquants
    try {
        $invalidMessages = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.expediteur IS NULL OR m.destinataire IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $diagnosis['invalidMessages'] = $invalidMessages;
    } catch (\Exception $e) {
        $diagnosis['invalidMessagesError'] = $e->getMessage();
    }
    
    return $diagnosis;
}
}