<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\String\Slugger\SluggerInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('formation_lister');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode peut être vide, Symfony gère la déconnexion.');
    }
    
    /**
     * Démarre le processus d'authentification avec Google
     */
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectGoogleStart(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google_main')
            ->redirect([
                'email', 'profile', 'openid'
            ]);
    }
    
    /**
     * Gère le callback de Google après l'authentification
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        // Cette méthode ne sera pas exécutée - le firewall Symfony gérera la redirection
        // Mais on ajoute du code ici pour la création de l'utilisateur si nécessaire
        
        /** @var GoogleClient $client */
        $client = $clientRegistry->getClient('google_main');
        
        try {
            /** @var GoogleUser $googleUser */
            $googleUser = $client->fetchUserFromToken($client->getAccessToken());
            
            // Chercher si l'utilisateur existe déjà par email
            $email = $googleUser->getEmail();
            $existingUser = $utilisateurRepository->findOneBy(['email' => $email]);
            
            if (!$existingUser) {
                // Créer un nouvel utilisateur
                $user = new Utilisateur();
                $user->setEmail($email);
                $user->setNom($googleUser->getLastName() ?? 'Nom Google');
                $user->setPrenom($googleUser->getFirstName() ?? 'Prénom Google');
                $user->setIsVerified(true);
                
                // Générer un mot de passe aléatoire (l'utilisateur devrait le changer)
                $randomPassword = bin2hex(random_bytes(12));
                $hashedPassword = $passwordHasher->hashPassword($user, $randomPassword);
                $user->setPassword($hashedPassword);
                
                // Sauvegarder la photo de profil Google si disponible
                if ($googleUser->getAvatar()) {
                    $photoUrl = $googleUser->getAvatar();
                    // Télécharger et sauvegarder l'image (à implémenter selon votre système)
                    // $filename = $this->saveGoogleProfilePicture($photoUrl, $slugger);
                    // $user->setPhoto($filename);
                }
                
                $entityManager->persist($user);
                $entityManager->flush();
                
                // Vous pourriez ajouter un message flash pour indiquer que le compte a été créé
                $this->addFlash('success', 'Votre compte a été créé avec succès !');
            }
            
            // Le SecurityAuthenticator prendra le relais pour l'authentification
            
        } catch (\Exception $e) {
            // En cas d'erreur, rediriger vers la page de connexion
            $this->addFlash('error', 'Une erreur est survenue lors de la connexion avec Google.');
            return $this->redirectToRoute('app_login');
        }
        
        return $this->redirectToRoute('formation_lister');
    }
}