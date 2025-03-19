<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;
    private UtilisateurRepository $utilisateurRepository;
    private UserPasswordHasherInterface $passwordHasher;
    
    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordHasher = $passwordHasher;
    }
    
    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }
    
    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        
        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                
                $email = $googleUser->getEmail();
                
                // 1) Vérifier si cet utilisateur Google existe déjà
                $existingUser = $this->utilisateurRepository->findOneBy(['email' => $email]);
                
                if ($existingUser) {
                    // Mise à jour des infos si nécessaire
                    $existingUser->setDernierLogin(new \DateTime());
                    $this->entityManager->persist($existingUser);
                    $this->entityManager->flush();
                    
                    return $existingUser;
                }
                
                // 2) Sinon, créer un nouvel utilisateur
                $utilisateur = new Utilisateur();
                $utilisateur->setEmail($email);
                $utilisateur->setNom($googleUser->getLastName() ?? 'Nom Google');
                $utilisateur->setPrenom($googleUser->getFirstName() ?? 'Prénom Google');
                $utilisateur->setIsVerified(true);
                $utilisateur->setDernierLogin(new \DateTime());
                
                // Générer un mot de passe aléatoire
                $randomPassword = bin2hex(random_bytes(12));
                $hashedPassword = $this->passwordHasher->hashPassword($utilisateur, $randomPassword);
                $utilisateur->setPassword($hashedPassword);
                
                // Ajouter un badge pour les utilisateurs Google
                $utilisateur->addBadge('google_user');
                
                $this->entityManager->persist($utilisateur);
                $this->entityManager->flush();
                
                return $utilisateur;
            })
        );
    }
    
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Rediriger vers la page d'accueil après connexion réussie
        return new RedirectResponse(
            $this->router->generate('formation_lister')
        );
    }
    
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());
        
        return new RedirectResponse(
            $this->router->generate('app_login', ['error' => $message])
        );
    }
    
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->router->generate('app_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}