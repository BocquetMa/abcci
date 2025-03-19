<?php

namespace App\Entity;

use App\Repository\InscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
class Inscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dateInscription;

    #[ORM\Column(type: 'string', length: 20)]
    private string $statut = 'en_attente'; // en_attente, acceptee, refusee

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $dateConfirmation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $dateFin = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $validePar = null;

    #[ORM\Column(type: 'boolean')]
    private bool $estValide = false;

    #[ORM\Column(type: 'boolean')]
    private bool $estTerminee = false;

    public function __construct()
    {
        $this->dateInscription = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

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

    public function getDateInscription(): \DateTimeImmutable
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeImmutable $dateInscription): self
    {
        $this->dateInscription = $dateInscription;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): self
    {
        $this->motif = $motif;

        return $this;
    }

    public function getDateConfirmation(): ?\DateTime
    {
        return $this->dateConfirmation;
    }

    public function setDateConfirmation(?\DateTime $dateConfirmation): self
    {
        $this->dateConfirmation = $dateConfirmation;

        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTime $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTime $dateFin): self
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getValidePar(): ?Utilisateur
    {
        return $this->validePar;
    }

    public function setValidePar(?Utilisateur $validePar): self
    {
        $this->validePar = $validePar;

        return $this;
    }

    public function isEstValide(): bool
    {
        return $this->estValide;
    }

    public function setEstValide(bool $estValide): self
    {
        $this->estValide = $estValide;

        return $this;
    }

    public function isEstTerminee(): bool
    {
        return $this->estTerminee;
    }

    public function setEstTerminee(bool $estTerminee): self
    {
        $this->estTerminee = $estTerminee;

        return $this;
    }

    public function accepter(Utilisateur $validateur, \DateTime $dateDebut, \DateTime $dateFin): self
    {
        $this->statut = 'acceptee';
        $this->validePar = $validateur;
        $this->dateConfirmation = new \DateTime();
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->estValide = true;

        return $this;
    }

    public function refuser(Utilisateur $validateur, string $motif): self
    {
        $this->statut = 'refusee';
        $this->validePar = $validateur;
        $this->dateConfirmation = new \DateTime();
        $this->motif = $motif;
        $this->estValide = false;

        return $this;
    }

    public function terminer(): self
    {
        $this->estTerminee = true;
        return $this;
    }

    public function isEnAttente(): bool
    {
        return $this->statut === 'en_attente';
    }

    public function isAcceptee(): bool
    {
        return $this->statut === 'acceptee';
    }

    public function isRefusee(): bool
    {
        return $this->statut === 'refusee';
    }
}