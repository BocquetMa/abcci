<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
#[ORM\HasLifecycleCallbacks]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $expediteur = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $destinataire = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le contenu du message ne peut pas être vide')]
    private ?string $contenu = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: 'boolean')]
    private bool $lu = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateLecture = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $supprime = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $important = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pieceJointe = null;

    public function __construct()
    {
        $this->dateEnvoi = new \DateTime();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->dateEnvoi = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpediteur(): ?Utilisateur
    {
        return $this->expediteur;
    }

    public function setExpediteur(?Utilisateur $expediteur): self
    {
        $this->expediteur = $expediteur;
        return $this;
    }

    public function getDestinataire(): ?Utilisateur
    {
        return $this->destinataire;
    }

    public function setDestinataire(?Utilisateur $destinataire): self
    {
        $this->destinataire = $destinataire;
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTimeInterface $dateEnvoi): self
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function isLu(): bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): self
    {
        $this->lu = $lu;
        
        if ($lu && $this->dateLecture === null) {
            $this->dateLecture = new \DateTime();
        }
        
        return $this;
    }

    public function getDateLecture(): ?\DateTimeInterface
    {
        return $this->dateLecture;
    }

    public function setDateLecture(?\DateTimeInterface $dateLecture): self
    {
        $this->dateLecture = $dateLecture;
        return $this;
    }

    public function isSupprime(): bool
    {
        return $this->supprime;
    }

    public function setSupprime(bool $supprime): self
    {
        $this->supprime = $supprime;
        return $this;
    }

    public function isImportant(): bool
    {
        return $this->important;
    }

    public function setImportant(bool $important): self
    {
        $this->important = $important;
        return $this;
    }

    public function getPieceJointe(): ?string
    {
        return $this->pieceJointe;
    }

    public function setPieceJointe(?string $pieceJointe): self
    {
        $this->pieceJointe = $pieceJointe;
        return $this;
    }

    /**
     * Retourne l'âge du message sous forme de texte convivial.
     */
    public function getTimeAgo(): string
    {
        $now = new \DateTime();
        $interval = $now->diff($this->dateEnvoi);
        
        if ($interval->y > 0) {
            return $interval->y > 1 ? "il y a {$interval->y} ans" : "il y a un an";
        }
        
        if ($interval->m > 0) {
            return $interval->m > 1 ? "il y a {$interval->m} mois" : "il y a un mois";
        }
        
        if ($interval->d > 0) {
            return $interval->d > 1 ? "il y a {$interval->d} jours" : "il y a un jour";
        }
        
        if ($interval->h > 0) {
            return $interval->h > 1 ? "il y a {$interval->h} heures" : "il y a une heure";
        }
        
        if ($interval->i > 0) {
            return $interval->i > 1 ? "il y a {$interval->i} minutes" : "il y a une minute";
        }
        
        return "à l'instant";
    }

    /**
     * Retourne un extrait court du contenu du message
     */
    public function getExtrait(int $longueur = 50): string
    {
        if (strlen($this->contenu) <= $longueur) {
            return $this->contenu;
        }
        
        return substr($this->contenu, 0, $longueur) . '...';
    }
}