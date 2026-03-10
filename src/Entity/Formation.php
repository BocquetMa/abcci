<?php

namespace App\Entity;

use App\Repository\InscriptionRepository;
use App\Repository\FormationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private ?int $duree = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $prix = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\OneToMany(targetEntity: Inscription::class, mappedBy: 'formation')]
    private Collection $inscriptions;

    #[ORM\ManyToOne(targetEntity: Formateur::class, inversedBy: 'formations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formateur $formateur = null;

    #[ORM\Column(length: 50)]
    private ?string $theme = null;

    #[ORM\Column(length: 20)]
    private ?string $niveau = null;

    #[ORM\ManyToMany(targetEntity: MotCle::class, inversedBy: 'formations')]
    #[ORM\JoinTable(name: 'formation_mot_cle')] 
    private Collection $motsCles;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nombrePlacesTotal = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $placesOccupees = 0;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'formations')]
    private Collection $utilisateurs;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: Document::class, cascade: ['persist', 'remove'])]
    private Collection $documents;

    #[ORM\OneToMany(targetEntity: Certification::class, mappedBy: 'formation')]
    private Collection $certifications;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: Quiz::class, orphanRemoval: true)]
    private Collection $quizzes;

    public function __construct()
    {
        $this->inscriptions = new ArrayCollection();
        $this->utilisateurs = new ArrayCollection();
        $this->motsCles = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->certifications = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
    }

    public function getQuizzes(): Collection
    {
        return $this->quizzes;
    }

    public function addQuiz(Quiz $quiz): static
    {
        if (!$this->quizzes->contains($quiz)) {
            $this->quizzes->add($quiz);
            $quiz->setFormation($this);
        }

        return $this;
    }

    public function removeQuiz(Quiz $quiz): static
    {
        if ($this->quizzes->removeElement($quiz)) {
            // set the owning side to null (unless already changed)
            if ($quiz->getFormation() === $this) {
                $quiz->setFormation(null);
            }
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(int $duree): static
    {
        $this->duree = $duree;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float|string $prix): static
    {
        $this->prix = (float) $prix;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getInscriptions(): Collection
    {
        return $this->inscriptions;
    }

    public function addInscription(Inscription $inscription): static
    {
        if (!$this->inscriptions->contains($inscription)) {
            $this->inscriptions->add($inscription);
            $inscription->setFormation($this);
        }
        return $this;
    }

    public function removeInscription(Inscription $inscription): static
    {
        if ($this->inscriptions->removeElement($inscription)) {
            if ($inscription->getFormation() === $this) {
                $inscription->setFormation(null);
            }
        }
        return $this;
    }

    public function getFormateur(): ?Formateur
    {
        return $this->formateur;
    }

    public function setFormateur(?Formateur $formateur): static
    {
        $this->formateur = $formateur;
        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(string $niveau): static
    {
        $this->niveau = $niveau;

        return $this;
    }

    /**
     * @return Collection<int, MotCle>
     */
    public function getMotsCles(): Collection
    {
        return $this->motsCles;
    }

    public function addMotCle(MotCle $motCle): self
    {
        if (!$this->motsCles->contains($motCle)) {
            $this->motsCles->add($motCle);
        }
        return $this;
    }

    public function removeMotCle(MotCle $motCle): self
    {
        $this->motsCles->removeElement($motCle);
        return $this;
    }

    public function getNombrePlacesTotal(): int
    {
        return $this->nombrePlacesTotal;
    }

    public function setNombrePlacesTotal(int $nombrePlacesTotal): self
    {
        $this->nombrePlacesTotal = $nombrePlacesTotal;
        return $this;
    }

    public function getPlacesOccupees(): int
    {
        return $this->placesOccupees;
    }

    public function setPlacesOccupees(int $placesOccupees): self
    {
        $this->placesOccupees = $placesOccupees;
        return $this;
    }

    public function placesDisponibles(): int
    {
        return max(0, $this->nombrePlacesTotal - $this->placesOccupees);
    }

    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): self
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->add($utilisateur);
            $utilisateur->addFormation($this);
        }
        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): self
    {
        if ($this->utilisateurs->removeElement($utilisateur)) {
            $utilisateur->removeFormation($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setFormation($this);
        }
        return $this;
    }

    public function removeDocument(Document $document): self
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getFormation() === $this) {
                $document->setFormation(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Certification>
     */
    public function getCertifications(): Collection
    {
        return $this->certifications;
    }

    public function addCertification(Certification $certification): static
    {
        if (!$this->certifications->contains($certification)) {
            $this->certifications->add($certification);
            $certification->setFormation($this);
        }

        return $this;
    }

    public function removeCertification(Certification $certification): static
    {
        if ($this->certifications->removeElement($certification)) {
            // set the owning side to null (unless already changed)
            if ($certification->getFormation() === $this) {
                $certification->setFormation(null);
            }
        }

        return $this;
    }

    public function getNombrePlaces(): int
{
    return $this->nombrePlacesTotal;
}
}
