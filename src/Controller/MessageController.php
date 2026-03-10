<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Utilisateur;
use App\Form\MessageType;
use App\Repository\MessageRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/messagerie', name: 'messagerie_')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    private $messagesDirectory;

    public function __construct(string $messagesDirectory = null)
    {
        $this->messagesDirectory = $messagesDirectory;
    }

    /**
     * Page principale de la messagerie
     */
    /**
 * Page principale de la messagerie
 */
    #[Route('/', name: 'index')]
    public function index(MessageRepository $messageRepository): Response
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        
        try {
            // Utiliser findConversations au lieu de findConversationsAlternative
            $conversations = $messageRepository->findConversationsAlternative($utilisateur);
            
            // Compter les messages non lus
            $unreadCount = $messageRepository->countUnreadMessages($utilisateur);
            
            return $this->render('messagerie/index.html.twig', [
                'conversations' => $conversations,
                'unreadCount' => $unreadCount
            ]);
        } catch (\Exception $e) {
            // Log plus détaillé de l'erreur
            $this->addFlash('danger', 'Une erreur est survenue lors du chargement des conversations. Veuillez réessayer.');
            
            // Pour le débogage, afficher le message d'erreur en environnement de développement
            $debug = $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null;
            
            return $this->render('messagerie/index.html.twig', [
                'conversations' => [],
                'unreadCount' => 0,
                'error' => $debug
            ]);
        }
    }
    
    /**
     * Affiche et gère une conversation avec un autre utilisateur
     */
    #[Route('/conversation/{id}', name: 'conversation')]
    public function conversation(
        Utilisateur $destinataire, 
        Request $request, 
        EntityManagerInterface $entityManager,
        MessageRepository $messageRepository,
        SluggerInterface $slugger
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        
        // Éviter la conversation avec soi-même
        if ($destinataire->getId() === $utilisateur->getId()) {
            return $this->redirectToRoute('messagerie_index');
        }
        
        // Créer un nouveau message
        $message = new Message();
        $message->setExpediteur($utilisateur);
        $message->setDestinataire($destinataire);
        
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de la pièce jointe
            $pieceJointeFile = $form->get('pieceJointe')->getData();
            
            if ($pieceJointeFile) {
                $originalFilename = pathinfo($pieceJointeFile->getClientOriginalName(), PATHINFO_FILENAME);
                // Sécurisation du nom de fichier
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$pieceJointeFile->guessExtension();
                
                // Déplacer le fichier dans le répertoire des messages
                try {
                    $pieceJointeFile->move(
                        $this->getParameter('messages_directory'),
                        $newFilename
                    );
                    
                    $message->setPieceJointe($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Une erreur est survenue lors du téléchargement du fichier.');
                }
            }
            
            $entityManager->persist($message);
            $entityManager->flush();
            
            // Rediriger pour éviter la soumission multiple du formulaire
            return $this->redirectToRoute('messagerie_conversation', ['id' => $destinataire->getId()]);
        }
        
        // Récupérer les messages entre les deux utilisateurs
        $messages = $messageRepository->findMessagesBetweenUsers($utilisateur, $destinataire);
        
        // Marquer tous les messages non lus comme lus
        $messageRepository->markConversationAsRead($utilisateur, $destinataire);
        $entityManager->flush();
        
        return $this->render('messagerie/conversation.html.twig', [
            'messages' => $messages,
            'destinataire' => $destinataire,
            'form' => $form->createView()
        ]);
    }
    
    /**
     * Endpoint AJAX pour envoyer un message
     */
    #[Route('/envoyer-message', name: 'envoyer_message', methods: ['POST'])]
    public function envoyerMessage(
        Request $request, 
        EntityManagerInterface $entityManager,
        UtilisateurRepository $utilisateurRepository
    ): JsonResponse {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['destinataireId']) || !isset($data['contenu']) || empty($data['contenu'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }
        
        $destinataire = $utilisateurRepository->find($data['destinataireId']);
        
        if (!$destinataire) {
            return new JsonResponse(['success' => false, 'message' => 'Destinataire non trouvé'], 404);
        }
        
        $message = new Message();
        $message->setExpediteur($utilisateur);
        $message->setDestinataire($destinataire);
        $message->setContenu($data['contenu']);
        
        $entityManager->persist($message);
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true, 
            'message' => [
                'id' => $message->getId(),
                'contenu' => $message->getContenu(),
                'dateEnvoi' => $message->getDateEnvoi()->format('H:i'),
                'expediteurNom' => $utilisateur->getPrenom() . ' ' . $utilisateur->getNom(),
                'expediteurPhoto' => $utilisateur->getPhoto()
            ]
        ]);
    }
    
    /**
     * Endpoint AJAX pour récupérer les nouveaux messages
     */
    #[Route('/nouveaux-messages/{id}', name: 'nouveaux_messages', methods: ['GET'])]
    public function nouveauxMessages(
        Utilisateur $destinataire, 
        Request $request, 
        MessageRepository $messageRepository
    ): JsonResponse {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        
        $lastId = $request->query->get('lastId', 0);
        
        // Récupérer uniquement les nouveaux messages
        $messages = $messageRepository->createQueryBuilder('m')
            ->where('m.id > :lastId')
            ->andWhere('(m.expediteur = :expediteur AND m.destinataire = :destinataire) OR (m.expediteur = :destinataire AND m.destinataire = :expediteur)')
            ->setParameter('lastId', $lastId)
            ->setParameter('expediteur', $utilisateur)
            ->setParameter('destinataire', $destinataire)
            ->orderBy('m.dateEnvoi', 'ASC')
            ->getQuery()
            ->getResult();
        
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'id' => $message->getId(),
                'contenu' => $message->getContenu(),
                'dateEnvoi' => $message->getDateEnvoi()->format('H:i'),
                'expediteur' => $message->getExpediteur()->getId(),
                'estExpediteur' => $message->getExpediteur()->getId() === $utilisateur->getId()
            ];
        }
        
        return new JsonResponse(['messages' => $formattedMessages]);
    }
    
    /**
     * Marquer un message comme important
     */
    #[Route('/marquer-important/{id}', name: 'marquer_important')]
    public function marquerImportant(
        Message $message, 
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        
        // Vérifier que l'utilisateur est le destinataire ou l'expéditeur
        if ($message->getExpediteur() !== $utilisateur && $message->getDestinataire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce message');
        }
        
        $message->setImportant(!$message->isImportant());
        $entityManager->flush();
        
        // Si la requête est en AJAX
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'important' => $message->isImportant()]);
        }
        
        // Sinon, rediriger vers la conversation
        $otherUser = $message->getExpediteur() === $utilisateur 
            ? $message->getDestinataire() 
            : $message->getExpediteur();
            
        return $this->redirectToRoute('messagerie_conversation', ['id' => $otherUser->getId()]);
    }
    
    /**
     * Supprimer un message
     */
    #[Route('/supprimer/{id}', name: 'supprimer')]
    public function supprimer(
        Message $message, 
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        
        // Vérifier que l'utilisateur est le destinataire ou l'expéditeur
        if ($message->getExpediteur() !== $utilisateur && $message->getDestinataire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce message');
        }
        
        $message->setSupprime(true);
        $entityManager->flush();
        
        // Si la requête est en AJAX
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }
        
        // Récupérer l'autre utilisateur pour rediriger vers la conversation
        $otherUser = $message->getExpediteur() === $utilisateur 
            ? $message->getDestinataire() 
            : $message->getExpediteur();
            
        return $this->redirectToRoute('messagerie_conversation', ['id' => $otherUser->getId()]);
    }
    
    /**
     * Rechercher des utilisateurs pour la messagerie
     */
    #[Route('/rechercher-utilisateurs', name: 'rechercher_utilisateurs', methods: ['GET'])]
    public function rechercherUtilisateurs(
        Request $request, 
        UtilisateurRepository $utilisateurRepository
    ): JsonResponse {
        $terme = $request->query->get('q');
        
        if (empty($terme) || strlen($terme) < 2) {
            return new JsonResponse([]);
        }
        
        $utilisateurs = $utilisateurRepository->createQueryBuilder('u')
            ->where('u.prenom LIKE :terme OR u.nom LIKE :terme OR u.email LIKE :terme')
            ->setParameter('terme', '%' . $terme . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        $resultats = [];
        foreach ($utilisateurs as $utilisateur) {
            if ($utilisateur !== $this->getUser()) {
                $resultats[] = [
                    'id' => $utilisateur->getId(),
                    'text' => $utilisateur->getPrenom() . ' ' . $utilisateur->getNom(),
                    'email' => $utilisateur->getEmail(),
                    'photo' => $utilisateur->getPhoto()
                ];
            }
        }
        
        return new JsonResponse($resultats);
    }
}