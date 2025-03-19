<?php

namespace App\Controller;

use App\Entity\Badge;
use App\Entity\Utilisateur;
use App\Entity\Formateur;
use App\Entity\Formation;
use App\Service\BadgeService;
use App\Repository\BadgeRepository;
use App\Form\BadgePersonnaliseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/badge', name: 'badge_')]
class BadgeController extends AbstractController
{
    private BadgeService $badgeService;
    private EntityManagerInterface $entityManager;

    public function __construct(BadgeService $badgeService, EntityManagerInterface $entityManager)
    {
        $this->badgeService = $badgeService;
        $this->entityManager = $entityManager;
    }

    /**
     * Liste des badges de l'utilisateur connecté
     */
    #[Route('/', name: 'liste')]
    #[IsGranted('ROLE_USER')]
    public function listeBadges(): Response
    {
        $user = $this->getUser();
        
        // Récupérer tous les badges de l'utilisateur
        $badges = $this->entityManager
            ->getRepository(Badge::class)
            ->findBy(['utilisateur' => $user], ['obtenuLe' => 'DESC']);

        // Grouper les badges par type
        $badgesGroupes = [
            Badge::TYPE_QUIZ => [],
            Badge::TYPE_FORMATION => [],
            Badge::TYPE_ACHIEVEMENT => []
        ];

        foreach ($badges as $badge) {
            $badgesGroupes[$badge->getType()][] = $badge;
        }

        return $this->render('badge/liste.html.twig', [
            'badges_groupes' => $badgesGroupes,
            'total_points' => $user->getPoints(),
            'niveau_actuel' => $user->getNiveau()
        ]);
    }

    /**
     * Les formateurs peuvent attribuer des badges personnalisés
     */
    #[Route('/attribuer', name: 'attribuer')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function attribuerBadgePersonnalise(Request $request): Response
    {
        /** @var Formateur $formateur */
        $formateur = $this->getUser();
        
        // Créer le formulaire pour attribuer un badge personnalisé
        $form = $this->createForm(BadgePersonnaliseType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->badgeService->attribuerBadgePersonnalise(
                    $data['utilisateur'],
                    $formateur,
                    $data['nom'],
                    $data['description'],
                    $data['image'] ?? null,
                    $data['points'] ?? 0
                );

                $this->addFlash('success', 'Badge personnalisé attribué avec succès');
                return $this->redirectToRoute('badge_liste');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'attribution du badge : ' . $e->getMessage());
            }
        }

        return $this->render('badge/attribuer.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Tableau de bord des badges pour les administrateurs
     */
    #[Route('/admin', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(BadgeRepository $badgeRepository): Response
    {
        $statistiques = [
            'total_badges' => $badgeRepository->count([]),
            'badges_par_type' => $badgeRepository->compterBadgesParType(),
            'utilisateurs_avec_badges' => $badgeRepository->compterUtilisateursAvecBadges(),
            'top_badges' => $badgeRepository->topBadgesAttribues(5)
        ];

        return $this->render('badge/adminPanel.html.twig', [
            'statistiques' => $statistiques
        ]);
    }

    #[Route('/{id}', name: 'details', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function detailsBadge(Badge $badge): Response
    {
        // Vérifier que l'utilisateur peut voir ce badge
        $this->denyAccessUnlessGranted('VIEW', $badge);

        return $this->render('badge/details.html.twig', [
            'badge' => $badge
        ]);
    }

    /**
     * Supprimer un badge (réservé aux admins)
     */
    #[Route('/supprimer/{id}', name: 'supprimer', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function supprimerBadge(Badge $badge): Response
    {
        try {
            $this->entityManager->remove($badge);
            $this->entityManager->flush();

            $this->addFlash('success', 'Badge supprimé avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression du badge : ' . $e->getMessage());
        }

        return $this->redirectToRoute('badge_admin_dashboard');
    }

    /**
     * Exporter les badges de l'utilisateur
     */
    #[Route('/exporter', name: 'exporter')]
    #[IsGranted('ROLE_USER')]
    public function exporterBadges(): Response
    {
        $user = $this->getUser();
        $badges = $this->entityManager
            ->getRepository(Badge::class)
            ->findBy(['utilisateur' => $user]);

        // Générer un fichier CSV
        $csvContent = $this->genererCSVBadges($badges);

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="mes_badges.csv"');

        return $response;
    }

    /**
     * Génère un fichier CSV à partir des badges
     */
    private function genererCSVBadges(array $badges): string
    {
        $csvContent = "Type,Nom,Description,Points,Date d'obtention\n";
        
        foreach ($badges as $badge) {
            $csvContent .= sprintf(
                "%s,%s,%s,%d,%s\n",
                $badge->getType(),
                str_replace(',', ' ', $badge->getNom()),
                str_replace(',', ' ', $badge->getDescription()),
                $badge->getPoints(),
                $badge->getObtenuLe()->format('Y-m-d H:i:s')
            );
        }

        return $csvContent;
    }
}