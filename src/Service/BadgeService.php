<?php
// src/Service/BadgeService.php
namespace App\Service;

use App\Entity\Badge;
use App\Entity\Utilisateur;
use App\Entity\Formateur;
use App\Entity\Formation;
use Doctrine\ORM\EntityManagerInterface;

class BadgeService
{
    private const BADGES_CONFIG = [
        'quiz' => [
            'debutant' => [
                'points' => 10,
                'image' => 'quiz-debutant.png',
                'description' => 'Premier quiz réussi'
            ],
            'expert' => [
                'points' => 50,
                'image' => 'quiz-expert.png',
                'description' => 'Expert des quiz'
            ]
        ],
        'formation' => [
            'assidu' => [
                'points' => 30,
                'image' => 'formation-assidu.png',
                'description' => 'Formation suivie avec assiduité'
            ],
            'excellence' => [
                'points' => 100,
                'image' => 'formation-excellence.png',
                'description' => 'Excellence en formation'
            ]
        ]
    ];

    public function __construct(private EntityManagerInterface $em)
    {}

    public function attribuerBadgeQuiz(Utilisateur $utilisateur, string $type, int $score): void
    {
        // Vérifier que l'utilisateur peut recevoir un badge
        if (!$this->peutRecevoirBadge($utilisateur)) {
            return;
        }
    
        // Détermine le niveau du badge en fonction du score
        $niveau = $this->determinerNiveauQuiz($score);
        
        // On crée un nouvel objet Badge
        $badge = new Badge();
        $badge->setType(Badge::TYPE_QUIZ)
            ->setNom($this->determinerNomBadgeQuiz($score))
            ->setPoints($this->calculerPointsQuiz($score))
            ->setImage(self::BADGES_CONFIG['quiz'][$niveau]['image'])
            ->setDescription(self::BADGES_CONFIG['quiz'][$niveau]['description'] . ' (Score : ' . $score . '%)')
            ->setUtilisateur($utilisateur)
            ->setObtenuLe(new \DateTimeImmutable()); // S'assurer que la date est définie
    
        // Persistez et enregistrez le badge
        $this->em->persist($badge);
        $this->em->flush();
    
        // Ajouter des points à l'utilisateur
        $utilisateur->addPoints($badge->getPoints());
        $this->em->flush(); // Assurez-vous que les points sont également enregistrés
    }

    public function attribuerBadgeFormation(
        Utilisateur $utilisateur, 
        Formateur $formateur, 
        Formation $formation,
        string $type,
        string $niveau = 'assidu'
    ): void {
        // Vérifier si l'utilisateur peut recevoir ce badge
        if (!$this->peutRecevoirBadge($utilisateur, Badge::TYPE_FORMATION)) {
            return;
        }
    
        // Logique existante de création de badge
        $badge = new Badge();
        $badge->setType(Badge::TYPE_FORMATION)
            ->setNom("Badge {$formation->getTitre()}")
            ->setPoints(self::BADGES_CONFIG['formation'][$niveau]['points'])
            ->setImage(self::BADGES_CONFIG['formation'][$niveau]['image'])
            ->setDescription(self::BADGES_CONFIG['formation'][$niveau]['description'])
            ->setUtilisateur($utilisateur)
            ->setAttributeurBadge($formateur)
            ->setFormation($formation);
    
        $this->em->persist($badge);
        $this->em->flush();
    
        // N'ajouter des points que pour les utilisateurs classiques
        if (in_array('ROLE_USER', $utilisateur->getRoles())) {
            $utilisateur->addPoints($badge->getPoints());
        }
    }

    private function peutRecevoirBadge(Utilisateur $utilisateur, string $type = null): bool
    {
        // Autoriser tous les types de badges pour un utilisateur classique
        if (in_array('ROLE_USER', $utilisateur->getRoles())) {
            return true;
        }

        // Cas spécifiques pour les formateurs et admins
        if ($type === Badge::TYPE_FORMATION && in_array('ROLE_FORMATEUR', $utilisateur->getRoles())) {
            return true;
        }

        return false;
    }

    private function determinerNiveauQuiz(int $score): string
    {
        return $score >= 80 ? 'expert' : 'debutant';
    }

    private function determinerNomBadgeQuiz(int $score): string
    {
        return $score >= 80 ? 'Expert Quiz' : 'Badge Découverte';
    }

    private function calculerPointsQuiz(int $score): int
    {
        return $score >= 80 ? 50 : 10;
    }
    public function attribuerBadgePersonnalise(
        Utilisateur $utilisateur, 
        Formateur $formateur, 
        string $nom, 
        string $description, 
        ?string $image = null,
        int $points = 0
    ): void {
        // Vérifier si le formateur peut attribuer des badges personnalisés
        if (!in_array('ROLE_FORMATEUR', $formateur->getRoles())) {
            return;
        }
    
        $badge = new Badge();
        $badge->setType(Badge::TYPE_ACHIEVEMENT)
            ->setNom($nom)
            ->setDescription($description)
            ->setImage($image ?? 'default-achievement.png')
            ->setPoints($points)
            ->setUtilisateur($utilisateur)
            ->setAttributeurBadge($formateur);
    
        $this->em->persist($badge);
        $this->em->flush();
    
        // N'ajouter des points que pour les utilisateurs classiques
        if (in_array('ROLE_USER', $utilisateur->getRoles())) {
            $utilisateur->addPoints($badge->getPoints());
        }
    }
}