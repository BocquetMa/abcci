<?php
namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notification', name: 'notification_')]
class NotificationController extends AbstractController
{
    #[Route('/creer', name: 'creer')]
    #[IsGranted('ROLE_ADMIN')]
    public function creerNotification(
        Request $request, 
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que l'utilisateur est admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Si formulaire soumis
        if ($request->isMethod('POST')) {
            $titre = $request->request->get('titre');
            $contenu = $request->request->get('contenu');
            $type = $request->request->get('type') ?? Notification::TYPE_FORMATION;
            $destinataireType = $request->request->get('destinataire');

            // Logique de sélection des destinataires
            $destinataires = $this->determinerDestinataires(
                $destinataireType, 
                $entityManager
            );

            // Créer une notification pour chaque destinataire
            foreach ($destinataires as $destinataire) {
                $notification = new Notification();
                $notification->setTitre($titre)
                             ->setContenu($contenu)
                             ->setType($type)
                             ->setDestinataire($destinataire)
                             ->setLu(false);

                $entityManager->persist($notification);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Notifications envoyées avec succès');
            return $this->redirectToRoute('notification_creer');
        }

        // Afficher le formulaire
        return $this->render('notification/creer.html.twig', [
            'types' => [
                'Formation' => Notification::TYPE_FORMATION,
                'Inscription' => Notification::TYPE_INSCRIPTION,
                'Paiement' => Notification::TYPE_PAIEMENT,
                'Messagerie' => Notification::TYPE_MESSAGERIE,
                'Quiz' => Notification::TYPE_QUIZ
            ]
        ]);
    }

    private function determinerDestinataires(
        string $type, 
        EntityManagerInterface $entityManager
    ): array {
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        
        switch ($type) {
            case 'tous':
                return $utilisateurRepository->findAll();
            
            case 'utilisateurs':
                return $utilisateurRepository->findByRole('ROLE_USER');
            
            case 'formateurs':
                return $utilisateurRepository->findByRole('ROLE_FORMATEUR');
            
            case 'admins':
                return $utilisateurRepository->findByRole('ROLE_ADMIN');
            
            default:
                return [];
        }
    }

    #[Route('/liste', name: 'liste')]
    public function listeNotifications(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $notifications = $entityManager
            ->getRepository(Notification::class)
            ->findBy(
                ['destinataire' => $user], 
                ['dateCreation' => 'DESC']
            );

        return $this->render('notification/lister.html.twig', [
            'notifications' => $notifications
        ]);
    }
}