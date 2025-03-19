<?php

namespace App\Entity;

use App\Repository\QuizTentativeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizTentativeRepository::class)]
class QuizTentative
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tentatives')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quiz $quiz = null;

    #[ORM\ManyToOne(inversedBy: 'quizTentatives')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column]
    private ?bool $termine = false;

    #[ORM\Column]
    private ?bool $reussi = false;

    #[ORM\Column(type: Types::JSON)]
    private array $reponses = [];

    public function __construct()
    {
        $this->dateDebut = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;
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

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function isTermine(): ?bool
    {
        return $this->termine;
    }

    public function setTermine(bool $termine): static
    {
        $this->termine = $termine;
        return $this;
    }

    public function isReussi(): ?bool
    {
        return $this->reussi;
    }

    public function setReussi(bool $reussi): static
    {
        $this->reussi = $reussi;
        return $this;
    }

    public function getReponses(): array
    {
        return $this->reponses;
    }

    public function setReponses(array $reponses): static
    {
        $this->reponses = $reponses;
        return $this;
    }
    
    /**
     * Ajoute ou met à jour la réponse de l'utilisateur à une question
     */
    public function ajouterReponse(int $questionId, array $reponseIds): static
    {
        $this->reponses[$questionId] = $reponseIds;
        return $this;
    }
    
    /**
     * Récupère les réponses de l'utilisateur pour une question spécifique
     */
    public function getReponsesPourQuestion(int $questionId): array
    {
        return $this->reponses[$questionId] ?? [];
    }
    
    /**
     * Calcule et met à jour le score de la tentative
     */
    public function calculerScore(): static
    {
        if (!$this->quiz) {
            return $this;
        }
        
        $pointsTotal = $this->quiz->getPointsTotal();
        if ($pointsTotal === 0) {
            $this->score = 0;
            $this->reussi = false;
            return $this;
        }
        
        $pointsObtenus = 0;
        
        foreach ($this->quiz->getQuestions() as $question) {
            $questionId = $question->getId();
            $reponsesUtilisateur = $this->reponses[$questionId] ?? [];
            
            if ($question->verifierReponses($reponsesUtilisateur)) {
                $pointsObtenus += $question->getPoints();
            }
        }
        
        // Calcul du score en pourcentage
        $this->score = intval(($pointsObtenus / $pointsTotal) * 100);
        
        // Détermination de la réussite
        $this->reussi = $this->score >= $this->quiz->getScoreReussite();
        
        return $this;
    }
    
    /**
     * Termine la tentative de quiz
     */
    public function terminer(): static
    {
        $this->termine = true;
        $this->dateFin = new \DateTimeImmutable();
        $this->calculerScore();
        
        return $this;
    }
    
    /**
     * Calcule le temps passé sur le quiz en minutes
     */
    public function getTempsPasseEnMinutes(): int
    {
        if (!$this->dateFin) {
            return 0;
        }
        
        $intervalle = $this->dateDebut->diff($this->dateFin);
        return ($intervalle->h * 60) + $intervalle->i;
    }
    
    /**
     * Vérifie si le temps limite est dépassé
     */
    public function isTempsLimiteDepasse(): bool
    {
        if (!$this->quiz || $this->termine) {
            return false;
        }
        
        $tempsLimite = $this->quiz->getTempsLimite();
        $maintenant = new \DateTimeImmutable();
        $intervalle = $this->dateDebut->diff($maintenant);
        $minutesPassees = ($intervalle->h * 60) + $intervalle->i;
        
        return $minutesPassees > $tempsLimite;
    }
}