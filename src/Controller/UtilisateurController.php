<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Form\UtilisateurEditType;
use App\Form\ProfileType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/utilisateur', name: 'admin_utilisateur_')]
#[IsGranted('ROLE_ADMIN')]
class UtilisateurController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        $utilisateurs = $utilisateurRepository->findAll();
        
        return $this->render('admin/utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurs,
        ]);
    }

    #[Route('/ajouter', name: 'ajouter')]
    public function ajouter(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion du mot de passe
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $utilisateur,
                    $plainPassword
                );
                $utilisateur->setPassword($hashedPassword);
            }

            // Gestion de la photo de profil
            if ($photoFile = $form->get('photoFile')->getData()) {
                $this->handlePhotoUpload($photoFile, $utilisateur);
            }

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès');
            return $this->redirectToRoute('admin_utilisateur_index');
        }

        return $this->render('admin/utilisateur/ajouter.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/modifier/{id}', name: 'modifier')]
    public function modifier(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $isFormateur = $utilisateur instanceof Formateur;
        
        $form = $this->createForm(UtilisateurEditType::class, $utilisateur, [
            'is_formateur' => $isFormateur
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion du mot de passe
            if ($plainPassword = $form->get('plainPassword')->getData()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $utilisateur,
                    $plainPassword
                );
                $utilisateur->setPassword($hashedPassword);
            }

            // Gestion de la photo de profil
            if ($photoFile = $form->get('photoFile')->getData()) {
                $this->handlePhotoUpload($photoFile, $utilisateur);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès');
            return $this->redirectToRoute('admin_utilisateur_index');
        }

        return $this->render('admin/utilisateur/modifier.html.twig', [
            'form' => $form->createView(),
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/supprimer/{id}', name: 'supprimer')]
    public function supprimer(
        Utilisateur $utilisateur, 
        EntityManagerInterface $entityManager
    ): Response {
        // Suppression de la photo si elle existe
        if ($utilisateur->getPhoto()) {
            $photoPath = $this->getParameter('photos_directory') . '/' . $utilisateur->getPhoto();
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }

        $entityManager->remove($utilisateur);
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès');
        return $this->redirectToRoute('admin_utilisateur_index');
    }

    private function handlePhotoUpload(UploadedFile $photoFile, Utilisateur $utilisateur): void
    {
        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $newFilename = $originalFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

        try {
            $photoFile->move(
                $this->getParameter('photos_directory'),
                $newFilename
            );

            // Supprimer l'ancienne photo si elle existe
            if ($utilisateur->getPhoto()) {
                $oldPhotoPath = $this->getParameter('photos_directory') . '/' . $utilisateur->getPhoto();
                if (file_exists($oldPhotoPath)) {
                    unlink($oldPhotoPath);
                }
            }

            $utilisateur->setPhoto($newFilename);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de l\'upload de la photo');
        }
    }

    #[Route('/change-role/{id}/{role}', name: 'change_role', methods: ['POST'])]
    public function changeRole(
        Utilisateur $utilisateur, 
        string $role, 
        EntityManagerInterface $entityManager
    ): Response {
        if (!in_array($role, ['ROLE_USER', 'ROLE_FORMATEUR', 'ROLE_ADMIN'])) {
            throw $this->createNotFoundException('Rôle invalide');
        }

        $utilisateur->setRoles([$role]);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/profile', name: 'profile')]
    public function profile(
        Request $request, 
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de la photo de profil
            if ($photoFile = $form->get('photoFile')->getData()) {
                $this->handlePhotoUpload($photoFile, $user);
            }

            // Gestion du CV
            if ($cvFile = $form->get('cvFile')->getData()) {
                $this->handleCvUpload($cvFile, $user);
            }

            // Gestion des liens sociaux
            if ($links = $form->get('liensSociaux')->getData()) {
                foreach ($links as $platform => $url) {
                    $user->addLienSocial($platform, $url);
                }
            }

            // Mise à jour des badges et points
            $this->updateUserAchievements($user);

            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès');

            return $this->redirectToRoute('admin_utilisateur_profile');
        }

        // Récupération des statistiques
        $stats = [
            'formations_completees' => count($user->getFormations()),
            'certifications' => count($user->getCertifications()),
            'points' => $user->getPoints(),
            'niveau' => $user->getNiveau()
        ];

        return $this->render('admin/utilisateur/profile.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'stats' => $stats
        ]);
    }

    private function handleCvUpload(UploadedFile $cvFile, Utilisateur $utilisateur): void
    {
        $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
        $newFilename = $originalFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

        try {
            $cvFile->move(
                $this->getParameter('cv_directory'),
                $newFilename
            );

            // Supprimer l'ancien CV s'il existe
            if ($utilisateur->getCvFilename()) {
                $oldCvPath = $this->getParameter('cv_directory') . '/' . $utilisateur->getCvFilename();
                if (file_exists($oldCvPath)) {
                    unlink($oldCvPath);
                }
            }

            $utilisateur->setCvFilename($newFilename);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de l\'upload du CV');
        }
    }

    private function updateUserAchievements(Utilisateur $user): void
    {
        // Attribution de badges basée sur les accomplissements
        $formations = count($user->getFormations());
        $certifications = count($user->getCertifications());

        if ($formations >= 5) {
            $user->addBadge('expert_formation');
            $user->addPoints(100);
        }

        if ($certifications >= 3) {
            $user->addBadge('master_certifie');
            $user->addPoints(150);
        }

        // Mise à jour des statistiques
        $user->getStatistiques()['formations_completees'] = $formations;
        $user->getStatistiques()['certifications_obtenues'] = $certifications;
    }

    #[Route('/tableau-de-bord', name: 'dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        
        return $this->render('admin/utilisateur/dashboard.html.twig', [
            'user' => $user,
            'formations_en_cours' => $user->getFormations()->filter(
                fn($f) => $f->getDateFin() > new \DateTime()
            ),
            'certifications_recentes' => $user->getCertifications()->slice(0, 5),
            'badges' => $user->getBadges(),
            'statistiques' => $user->getStatistiques()
        ]);
    }


}