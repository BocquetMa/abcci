<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'notification')]
class Notification
{
    public const TYPE_FORMATION = 'formation';
    public const TYPE_INSCRIPTION = 'inscription';
    public const TYPE_PAIEMENT = 'paiement';
    public const TYPE_MESSAGERIE = 'messagerie';
    public const TYPE_QUIZ = 'quiz';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $titre;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $contenu;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: [
        self::TYPE_FORMATION, 
        self::TYPE_INSCRIPTION, 
        self::TYPE_PAIEMENT, 
        self::TYPE_MESSAGERIE, 
        self::TYPE_QUIZ
    ])]
    private string $type;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $destinataire = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: 'boolean')]
    private bool $lu = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $donnees = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // Getters et setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function isLu(): bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): self
    {
        $this->lu = $lu;
        return $this;
    }

    public function getDonnees(): ?array
    {
        return $this->donnees;
    }

    public function setDonnees(?array $donnees): self
    {
        $this->donnees = $donnees;
        return $this;
    }
}