<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    public const TYPE_CHOIX_UNIQUE = 'choix_unique';
    public const TYPE_CHOIX_MULTIPLE = 'choix_multiple';
    public const TYPE_TEXTE = 'texte';
    public const TYPE_VRAI_FAUX = 'vrai_faux';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La question est obligatoire")]
    private ?string $enonce = null;

    #[ORM\Column(length: 20)]
    private ?string $type = self::TYPE_CHOIX_UNIQUE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explication = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le nombre de points est obligatoire")]
    #[Assert\Positive(message: "Le nombre de points doit être positif")]
    private ?int $points = 1;

    #[ORM\Column]
    private ?int $ordre = 0;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quiz $quiz = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Reponse::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    private Collection $reponses;

    #[ORM\Column(nullable: true)]
    private ?string $reponseTexte = null;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnonce(): ?string
    {
        return $this->enonce;
    }

    public function setEnonce(string $enonce): static
    {
        $this->enonce = $enonce;
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

    public function getExplication(): ?string
    {
        return $this->explication;
    }

    public function setExplication(?string $explication): static
    {
        $this->explication = $explication;
        return $this;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
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

    /**
     * @return Collection<int, Reponse>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(Reponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setQuestion($this);
        }

        return $this;
    }

    public function removeReponse(Reponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getQuestion() === $this) {
                $reponse->setQuestion(null);
            }
        }

        return $this;
    }

    public function getReponseTexte(): ?string
    {
        return $this->reponseTexte;
    }

    public function setReponseTexte(?string $reponseTexte): static
    {
        $this->reponseTexte = $reponseTexte;
        return $this;
    }
    
    /**
     * Retourne la liste des réponses correctes pour cette question
     */
    public function getReponsesCorrectes(): array
    {
        if ($this->type === self::TYPE_TEXTE) {
            return [$this->reponseTexte];
        }
        
        return $this->reponses->filter(function (Reponse $reponse) {
            return $reponse->isCorrect();
        })->toArray();
    }
    
    /**
     * Vérifie si une liste de réponses est correcte pour cette question
     */
    public function verifierReponses(array $reponsesUtilisateur): bool
    {
        if ($this->type === self::TYPE_TEXTE) {
            // Pour une réponse textuelle, on compare sans tenir compte de la casse et des espaces
            $reponseAttendue = mb_strtolower(trim($this->reponseTexte));
            $reponseUtilisateur = mb_strtolower(trim($reponsesUtilisateur[0] ?? ''));
            return $reponseAttendue === $reponseUtilisateur;
        }
        
        if ($this->type === self::TYPE_VRAI_FAUX || $this->type === self::TYPE_CHOIX_UNIQUE) {
            // Pour un choix unique, on vérifie qu'une seule réponse correcte est sélectionnée
            if (count($reponsesUtilisateur) !== 1) {
                return false;
            }
            
            foreach ($this->reponses as $reponse) {
                if ($reponse->getId() == $reponsesUtilisateur[0] && $reponse->isCorrect()) {
                    return true;
                }
            }
            return false;
        }
        
        if ($this->type === self::TYPE_CHOIX_MULTIPLE) {
            // Pour un choix multiple, toutes les réponses correctes doivent être sélectionnées
            // et aucune réponse incorrecte ne doit être sélectionnée
            $reponsesCorrectes = $this->getReponsesCorrectes();
            $idsReponsesCorrectes = array_map(function($r) { return $r->getId(); }, $reponsesCorrectes);
            
            // Vérifier que chaque réponse utilisateur est correcte
            foreach ($reponsesUtilisateur as $idReponse) {
                if (!in_array($idReponse, $idsReponsesCorrectes)) {
                    return false;
                }
            }
            
            // Vérifier que toutes les réponses correctes sont sélectionnées
            return count($reponsesUtilisateur) === count($reponsesCorrectes);
        }
        
        return false;
    }
}