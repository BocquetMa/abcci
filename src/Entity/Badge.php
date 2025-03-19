<?php
namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
class Badge
{
    public const TYPE_QUIZ = 'quiz';
    public const TYPE_FORMATION = 'formation';
    public const TYPE_ACHIEVEMENT = 'achievement';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $obtenuLe = null;

    #[ORM\ManyToOne(inversedBy: 'badges')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\ManyToOne]
    private ?Formateur $attributeurBadge = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $points = 0;

    #[ORM\ManyToOne]
    private ?Formation $formation = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estPersonnalise = false;

    public function __construct()
    {
        $this->obtenuLe = new \DateTime();
    }

    public function getEstPersonnalise(): bool
    {
        return $this->estPersonnalise;
    }

    public function setEstPersonnalise(bool $estPersonnalise): self
    {
        $this->estPersonnalise = $estPersonnalise;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getObtenuLe(): ?\DateTimeInterface
    {
        return $this->obtenuLe;
    }

    public function setObtenuLe(?\DateTimeInterface $obtenuLe): static
    {
        $this->obtenuLe = $obtenuLe;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
    public function getAttributeurBadge(): ?Formateur
    {
        return $this->attributeurBadge;
    }

    public function setAttributeurBadge(?Formateur $attributeurBadge): self
    {
        $this->attributeurBadge = $attributeurBadge;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;
        return $this;
    }
}