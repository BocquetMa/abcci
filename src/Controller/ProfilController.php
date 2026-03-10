<?php

namespace App\Controller;

use App\Entity\Badge;
use App\Form\ProfileType;
use App\Repository\BadgeRepository;
use App\Repository\InscriptionRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
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
    public function planning(): Response
    {
        $user = $this->getUser();
        $planning = [];

        if ($this->isGranted('ROLE_FORMATEUR')) {
            // Pour un formateur : afficher les formations qu'il anime
            foreach ($user->getFormationsAnimees() as $formation) {
                $date = $formation->getDateDebut()
                    ? $formation->getDateDebut()->format('Y-m-d')
                    : '0000-00-00';
                $planning[$date][] = ['type' => 'formation', 'formation' => $formation];
            }
        } else {
            // Pour un stagiaire : inscriptions validées
            foreach ($user->getInscriptions() as $inscription) {
                if ($inscription->isEstValide() && $inscription->getDateDebut()) {
                    $date = $inscription->getDateDebut()->format('Y-m-d');
                    $planning[$date][] = ['type' => 'inscription', 'inscription' => $inscription];
                }
            }
        }

        ksort($planning);

        return $this->render('profil/planning.html.twig', [
            'planning' => $planning,
            'is_formateur' => $this->isGranted('ROLE_FORMATEUR'),
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
    
    #[Route('/modifier', name: 'modifier')]
    public function modifier(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($photoFile = $form->get('photoFile')->getData()) {
                $this->uploadPhoto($photoFile, $user);
            }
            if ($cvFile = $form->get('cvFile')->getData()) {
                $this->uploadCv($cvFile, $user);
            }
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès');
            return $this->redirectToRoute('profil_modifier');
        }

        return $this->render('profil/modifier.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    private function uploadPhoto(UploadedFile $file, $user): void
    {
        $filename = uniqid() . '.' . $file->guessExtension();
        $file->move($this->getParameter('photos_directory'), $filename);
        if ($old = $user->getPhoto()) {
            $oldPath = $this->getParameter('photos_directory') . '/' . $old;
            if (file_exists($oldPath)) unlink($oldPath);
        }
        $user->setPhoto($filename);
    }

    private function uploadCv(UploadedFile $file, $user): void
    {
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '-' . uniqid() . '.' . $file->guessExtension();
        $file->move($this->getParameter('cv_directory'), $filename);
        if ($old = $user->getCvFilename()) {
            $oldPath = $this->getParameter('cv_directory') . '/' . $old;
            if (file_exists($oldPath)) unlink($oldPath);
        }
        $user->setCvFilename($filename);
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