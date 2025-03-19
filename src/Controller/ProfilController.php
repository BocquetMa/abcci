<?php

namespace App\Controller;

use App\Entity\Badge;
use App\Repository\BadgeRepository;
use App\Repository\InscriptionRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil', name: 'profil_')]
#[IsGranted('ROLE_USER')]
class ProfilController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        // Récupérer les badges récents de l'utilisateur (les 5 derniers)
        $badgesRecents = $entityManager->getRepository(Badge::class)
            ->findBy(['utilisateur' => $user], ['obtenuLe' => 'DESC'], 5);
        
        return $this->render('profil/index.html.twig', [
            'user' => $user,
            'badges_recents' => $badgesRecents
        ]);
    }

    #[Route('/planning', name: 'planning')]
    public function planning(InscriptionRepository $inscriptionRepository): Response
    {
        $user = $this->getUser();
    
        // Récupérer les inscriptions validées de l'utilisateur
        $inscriptions = $user->getInscriptions()->filter(function($inscription) {
            return $inscription->isEstValide();
        });
    
        // Organiser les formations par date
        $planning = [];
        foreach ($inscriptions as $inscription) {
            if ($inscription->getDateDebut()) {
                $dateDebut = $inscription->getDateDebut()->format('Y-m-d');
                if (!isset($planning[$dateDebut])) {
                    $planning[$dateDebut] = [];
                }
                $planning[$dateDebut][] = $inscription;
            }
        }
    
        // Trier les dates
        ksort($planning);
        
        return $this->render('profil/planning.html.twig', [
            'planning' => $planning
        ]);
    }
    
    /**
     * Affiche tous les badges de l'utilisateur
     */
    #[Route('/badges', name: 'badges')]
    public function badges(BadgeRepository $badgeRepository): Response
    {
        $user = $this->getUser();
        
        // Récupérer tous les badges de l'utilisateur
        $badges = $badgeRepository->findBy(['utilisateur' => $user], ['obtenuLe' => 'DESC']);
        
        // Grouper les badges par type
        $badgesGroupes = [
            Badge::TYPE_QUIZ => [],
            Badge::TYPE_FORMATION => [],
            Badge::TYPE_ACHIEVEMENT => []
        ];

        foreach ($badges as $badge) {
            $badgesGroupes[$badge->getType()][] = $badge;
        }
        
        return $this->render('profil/badges.html.twig', [
            'badges_groupes' => $badgesGroupes,
            'total_badges' => count($badges),
            'total_points' => $user->getPoints()
        ]);
    }
    
    /**
     * Affiche les détails d'un badge spécifique
     */
    #[Route('/badge/{id}', name: 'badge_details')]
    public function badgeDetails(Badge $badge): Response
    {
        $user = $this->getUser();
        
        // Vérifier que le badge appartient bien à l'utilisateur actuel
        if ($badge->getUtilisateur() !== $user) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à voir ce badge.');
            return $this->redirectToRoute('profil_badges');
        }
        
        return $this->render('profil/badge_details.html.twig', [
            'badge' => $badge
        ]);
    }
    
    /**
     * Affiche les inscriptions de l'utilisateur
     */
    #[Route('/inscriptions', name: 'inscriptions')]
public function inscriptions(): Response
{
    $user = $this->getUser();
    
    // Récupérer les inscriptions par statut
    $inscriptionsEnAttente = [];
    $inscriptionsAcceptees = [];
    $inscriptionsRefusees = [];
    $inscriptionsTerminees = [];
    
    foreach ($user->getInscriptions() as $inscription) {
        if ($inscription->isEnAttente()) {
            $inscriptionsEnAttente[] = $inscription;
        } elseif ($inscription->isAcceptee()) {
            if ($inscription->isEstTerminee()) {
                $inscriptionsTerminees[] = $inscription;
            } else {
                $inscriptionsAcceptees[] = $inscription;
            }
        } elseif ($inscription->isRefusee()) {
            $inscriptionsRefusees[] = $inscription;
        }
    }
    
    // Combiner toutes les inscriptions pour la variable 'inscriptions'
    $inscriptions = array_merge(
        $inscriptionsEnAttente,
        $inscriptionsAcceptees,
        $inscriptionsRefusees,
        $inscriptionsTerminees
    );
    
    return $this->render('profil/inscriptions.html.twig', [
        'inscriptions' => $inscriptions,
        'inscriptions_en_attente' => $inscriptionsEnAttente,
        'inscriptions_acceptees' => $inscriptionsAcceptees,
        'inscriptions_refusees' => $inscriptionsRefusees,
        'inscriptions_terminees' => $inscriptionsTerminees
    ]);
}
}