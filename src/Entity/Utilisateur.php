<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['utilisateur' => Utilisateur::class, 'formateur' => Formateur::class])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Champs d'authentification
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    // Informations personnelles de base
    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    // Photo et CV
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $cvFilename = null;

    // Biographies et description
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $biographie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $biographieDetaillee = null;

    // Liens sociaux et profils
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $liensSociaux = [];

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $competences = [];

    // Badges et récompenses
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $badges = [];

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $points = 0;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $niveau = null;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: SocialLink::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $socialLinks;

    // Statistiques et activité
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dernierLogin = null;

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $statistiques = [];

    // Relations
    #[ORM\OneToMany(targetEntity: Inscription::class, mappedBy: 'utilisateur')]
    private Collection $inscriptions;

    #[ORM\ManyToMany(targetEntity: Formation::class, inversedBy: 'utilisateurs')]
    #[ORM\JoinTable(name: 'utilisateur_formation')]
    private Collection $formations;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Certification::class)]
    private Collection $certifications;

    #[ORM\OneToMany(mappedBy: 'destinataire', targetEntity: Notification::class)]
    private Collection $notifications;

    // Préférences
    #[ORM\Column(type: 'json')]
    private array $preferences = [
        'notifications_email' => true,
        'notifications_site' => true,
        'profil_public' => false,
        'theme' => 'light',
        'langue' => 'fr'
    ];

    #[ORM\OneToMany(mappedBy: 'validePar', targetEntity: Inscription::class)]
    private Collection $inscriptionsValidees;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: QuizTentative::class, orphanRemoval: true)]
    private Collection $quizTentatives;

    public function __construct()
    {
        $this->inscriptions = new ArrayCollection();
        $this->formations = new ArrayCollection();
        $this->certifications = new ArrayCollection();
        $this->socialLinks = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->inscriptionsValidees = new ArrayCollection();
        $this->liensSociaux = [];
        $this->competences = [];
        $this->badges = [];
        $this->statistiques = [
            'formations_completees' => 0,
            'certifications_obtenues' => 0,
            'temps_total_formation' => 0
        ];
        $this->quizTentatives = new ArrayCollection();
    }

    /**
     * Compte le nombre de notifications non lues pour l'utilisateur
     * @return int
     */

     public function getQuizTentatives(): Collection
    {
        return $this->quizTentatives;
    }

    public function addQuizTentative(QuizTentative $quizTentative): static
    {
        if (!$this->quizTentatives->contains($quizTentative)) {
            $this->quizTentatives->add($quizTentative);
            $quizTentative->setUtilisateur($this);
        }

        return $this;
    }

    public function removeQuizTentative(QuizTentative $quizTentative): static
    {
        if ($this->quizTentatives->removeElement($quizTentative)) {
            // set the owning side to null (unless already changed)
            if ($quizTentative->getUtilisateur() === $this) {
                $quizTentative->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * Récupère la dernière tentative non terminée pour un quiz donné
     */
    public function getTentativeEnCours(Quiz $quiz): ?QuizTentative
    {
        foreach ($this->quizTentatives as $tentative) {
            if ($tentative->getQuiz() === $quiz && !$tentative->isTermine()) {
                return $tentative;
            }
        }
        
        return null;
    }

    /**
     * Vérifie si l'utilisateur a déjà réussi un quiz
     */
    public function aReussiQuiz(Quiz $quiz): bool
    {
        foreach ($this->quizTentatives as $tentative) {
            if ($tentative->getQuiz() === $quiz && $tentative->isTermine() && $tentative->isReussi()) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtient le meilleur score de l'utilisateur pour un quiz donné
     */
    public function getMeilleurScorePourQuiz(Quiz $quiz): ?int
    {
        $meilleurScore = null;
        
        foreach ($this->quizTentatives as $tentative) {
            if ($tentative->getQuiz() === $quiz && $tentative->isTermine()) {
                if ($meilleurScore === null || $tentative->getScore() > $meilleurScore) {
                    $meilleurScore = $tentative->getScore();
                }
            }
        }
        
        return $meilleurScore;
    }

    /**
     * Obtient le nombre de tentatives pour un quiz donné
     */
    public function getNombreTentativesPourQuiz(Quiz $quiz): int
    {
        $count = 0;
        
        foreach ($this->quizTentatives as $tentative) {
            if ($tentative->getQuiz() === $quiz && $tentative->isTermine()) {
                $count++;
            }
        }
        
        return $count;
    }

    public function getNonReadNotificationsCount(): int
    {
        $count = 0;
        foreach ($this->notifications as $notification) {
            if (!$notification->isLu()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Obtient le nombre de certificats de l'utilisateur
     * @return int
     */
    public function getCertificatsCount(): int
    {
        return $this->certifications->count();
    }

    /**
     * Calcule la progression moyenne à travers toutes les formations
     * @return string
     */
    public function getProgressionMoyenne(): string
    {
        if ($this->inscriptions->isEmpty()) {
            return '0%';
        }
        
        $total = 0;
        $count = 0;
        
        foreach ($this->inscriptions as $inscription) {
            $progression = $inscription->getProgression();
            if ($progression !== null) {
                $total += $progression;
                $count++;
            }
        }
        
        if ($count === 0) {
            return '0%';
        }
        
        return round($total / $count) . '%';
    }

    public function getInscriptionsValidees(): Collection
    {
        return $this->inscriptionsValidees;
    }

    public function addInscriptionsValidee(Inscription $inscription): self
    {
        if (!$this->inscriptionsValidees->contains($inscription)) {
            $this->inscriptionsValidees->add($inscription);
            $inscription->setValidePar($this);
        }

        return $this;
    }

    public function removeInscriptionsValidee(Inscription $inscription): self
    {
        if ($this->inscriptionsValidees->removeElement($inscription)) {
            // set the owning side to null (unless already changed)
            if ($inscription->getValidePar() === $this) {
                $inscription->setValidePar(null);
            }
        }

        return $this;
    }
    
    /**
     * Récupère les formations auxquelles l'utilisateur est inscrit (inscriptions acceptées)
     * 
     * @return Collection<int, Formation>
     */
    public function getFormationsInscrites(): Collection
    {
        return $this->inscriptions
            ->filter(function(Inscription $inscription) {
                return $inscription->isAcceptee();
            })
            ->map(function(Inscription $inscription) {
                return $inscription->getFormation();
            });
    }
    
    /**
     * Vérifie si l'utilisateur est inscrit à une formation
     */
    public function estInscritA(Formation $formation): bool
    {
        foreach ($this->inscriptions as $inscription) {
            if ($inscription->getFormation() === $formation && $inscription->isAcceptee()) {
                return true;
            }
        }
        
        return false;
    }
    
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications[] = $notification;
            $notification->setDestinataire($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getDestinataire() === $this) {
                $notification->setDestinataire(null);
            }
        }

        return $this;
    }
    
    public function getCvFilename(): ?string
    {
        return $this->cvFilename;
    }

    public function setCvFilename(?string $cvFilename): self
    {
        $this->cvFilename = $cvFilename;
        return $this;
    }

    public function getBiographieDetaillee(): ?string
    {
        return $this->biographieDetaillee;
    }

    public function setBiographieDetaillee(?string $biographieDetaillee): self
    {
        $this->biographieDetaillee = $biographieDetaillee;
        return $this;
    }

    public function getLiensSociaux(): array
    {
        return $this->liensSociaux;
    }

    public function setLiensSociaux(?array $liensSociaux): self
    {
        $this->liensSociaux = $liensSociaux ?? [];
        return $this;
    }

    public function getSocialLinks(): Collection
    {
        return $this->socialLinks;
    }

    public function addSocialLink(SocialLink $socialLink): self
    {
        if (!$this->socialLinks->contains($socialLink)) {
            $this->socialLinks->add($socialLink);
            $socialLink->setUtilisateur($this);
        }

        return $this;
    }

    public function removeSocialLink(SocialLink $socialLink): self
    {
        if ($this->socialLinks->removeElement($socialLink)) {
            // set the owning side to null (unless already changed)
            if ($socialLink->getUtilisateur() === $this) {
                $socialLink->setUtilisateur(null);
            }
        }

        return $this;
    }

    public function getCompetences(): array
    {
        return $this->competences;
    }

    public function setCompetences(?array $competences): self
    {
        $this->competences = $competences ?? [];
        return $this;
    }

    public function addCompetence(string $competence, int $niveau): self
    {
        $this->competences[$competence] = $niveau;
        return $this;
    }

    public function getBadges(): array
    {
        return $this->badges;
    }

    public function setBadges(?array $badges): self
    {
        $this->badges = $badges ?? [];
        return $this;
    }

    public function addBadge(string $badge): self
    {
        if (!in_array($badge, $this->badges)) {
            $this->badges[] = $badge;
        }
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        $this->updateNiveau();
        return $this;
    }

    public function addPoints(int $points): self
    {
        $this->points += $points;
        $this->updateNiveau();
        return $this;
    }

    private function updateNiveau(): void
    {
        $this->niveau = match(true) {
            $this->points >= 1000 => 'Expert',
            $this->points >= 500 => 'Avancé',
            $this->points >= 100 => 'Intermédiaire',
            default => 'Débutant'
        };
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function getDernierLogin(): ?\DateTimeInterface
    {
        return $this->dernierLogin;
    }

    public function setDernierLogin(?\DateTimeInterface $dernierLogin): self
    {
        $this->dernierLogin = $dernierLogin;
        return $this;
    }

    public function getStatistiques(): array
    {
        return $this->statistiques;
    }

    public function setStatistiques(?array $statistiques): self
    {
        $this->statistiques = $statistiques ?? [
            'formations_completees' => 0,
            'certifications_obtenues' => 0,
            'temps_total_formation' => 0
        ];
        return $this;
    }

    public function incrementStatistique(string $cle): self
    {
        if (isset($this->statistiques[$cle])) {
            $this->statistiques[$cle]++;
        }
        return $this;
    }

    public function getCertifications(): Collection
    {
        return $this->certifications;
    }

    public function addCertification(Certification $certification): self
    {
        if (!$this->certifications->contains($certification)) {
            $this->certifications->add($certification);
            $certification->setUtilisateur($this);
            $this->incrementStatistique('certifications_obtenues');
            $this->addPoints(50); // Points pour une nouvelle certification
        }
        return $this;
    }

    public function removeCertification(Certification $certification): self
    {
        if ($this->certifications->removeElement($certification)) {
            // set the owning side to null (unless already changed)
            if ($certification->getUtilisateur() === $this) {
                $certification->setUtilisateur(null);
            }
        }
        return $this;
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): self
    {
        $this->preferences = $preferences ?? [
            'notifications_email' => true,
            'notifications_site' => true,
            'profil_public' => false,
            'theme' => 'light',
            'langue' => 'fr'
        ];
        return $this;
    }

    public function getPreference(string $key, $default = null)
    {
        return $this->preferences[$key] ?? $default;
    }

    public function setPreference(string $key, $value): self
    {
        $this->preferences[$key] = $value;
        return $this;
    }

    public function getBiographie(): ?string
    {
        return $this->biographie;
    }

    public function setBiographie(?string $biographie): self
    {
        $this->biographie = $biographie;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
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
            $inscription->setUtilisateur($this);
        }
        return $this;
    }

    public function removeInscription(Inscription $inscription): static
    {
        if ($this->inscriptions->removeElement($inscription)) {
            if ($inscription->getUtilisateur() === $this) {
                $inscription->setUtilisateur(null);
            }
        }
        return $this;
    }

    public function getFormations(): Collection
    {
        return $this->formations;
    }

    public function addFormation(Formation $formation): self
    {
        if (!$this->formations->contains($formation)) {
            $this->formations->add($formation);
        }
        return $this;
    }

    public function removeFormation(Formation $formation): self
    {
        $this->formations->removeElement($formation);
        return $this;
    }
}