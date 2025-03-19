<?php

namespace App\Entity;

use App\Repository\MotCleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MotCleRepository::class)]
class MotCle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $libelle = null;

    #[ORM\ManyToMany(targetEntity: Formation::class, mappedBy: 'motCles')]
    private Collection $formations;

    public function __construct()
    {
        $this->formations = new ArrayCollection(); // Correction ici
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    /**
     * @return Collection<int, Formation>
     */
    public function getFormations(): Collection  // Correction ici
    {
        return $this->formations; // Correction ici
    }

    public function addFormation(Formation $formation): static
    {
        if (!$this->formations->contains($formation)) { // Correction ici
            $this->formations->add($formation); // Correction ici
            $formation->addMotCle($this);
        }

        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        if ($this->formations->removeElement($formation)) { // Correction ici
            $formation->removeMotCle($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->libelle;
    }
}