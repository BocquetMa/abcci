<?php
namespace App\Service;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private NotifierInterface $notifier;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        NotifierInterface $notifier
    ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->notifier = $notifier;
    }

    /**
     * Créer une notification pour un utilisateur
     */
    public function creerNotification(
        Utilisateur $destinataire, 
        string $titre, 
        string $contenu, 
        string $type, 
        ?array $donnees = null
    ): Notification {
        $notification = new Notification();
        $notification
            ->setDestinataire($destinataire)
            ->setTitre($titre)
            ->setContenu($contenu)
            ->setType($type)
            ->setDonnees($donnees);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Envoyer une notification par email
     */
    public function envoyerEmailNotification(Notification $notification): void
    {
        $destinataire = $notification->getDestinataire();
        
        if (!$destinataire || !$destinataire->getPreference('notifications_email', true)) {
            return;
        }

        $email = (new Email())
            ->from('notifications@abcci.fr')
            ->to($destinataire->getEmail())
            ->subject($notification->getTitre())
            ->html($this->genererContenuEmail($notification));

        $this->mailer->send($email);
    }

    /**
     * Envoyer une notification SMS
     */
    public function envoyerSmsNotification(Notification $notification): void
    {
        $destinataire = $notification->getDestinataire();
        
        if (!$destinataire || !$destinataire->getTelephone() || 
            !$destinataire->getPreference('notifications_sms', false)) {
            return;
        }

        $sms = new SmsMessage(
            $destinataire->getTelephone(), 
            $this->genererContenuSms($notification)
        );

        $this->notifier->send($sms);
    }

    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLue(Notification $notification): void
    {
        $notification->setLu(true);
        $this->entityManager->flush();
    }

    /**
     * Générer le contenu HTML pour l'email
     */
    private function genererContenuEmail(Notification $notification): string
    {
        return sprintf(
            '<h2>%s</h2><p>%s</p><small>Reçu le %s</small>',
            htmlspecialchars($notification->getTitre()),
            htmlspecialchars($notification->getContenu()),
            $notification->getDateCreation()->format('d/m/Y H:i')
        );
    }

    /**
     * Générer le contenu SMS
     */
    private function genererContenuSms(Notification $notification): string
    {
        return sprintf(
            '%s: %s', 
            $notification->getTitre(), 
            substr($notification->getContenu(), 0, 100)
        );
    }

    /**
     * Récupérer les notifications non lues
     */
    public function recupererNotificationsNonLues(Utilisateur $utilisateur): array
    {
        return $this->entityManager
            ->getRepository(Notification::class)
            ->findBy([
                'destinataire' => $utilisateur,
                'lu' => false
            ], [
                'dateCreation' => 'DESC'
            ]);
    }
}