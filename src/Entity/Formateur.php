<?php

namespace App\Entity;

use App\Repository\FormateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormateurRepository::class)]
class Formateur extends Utilisateur
{
    #[ORM\OneToMany(mappedBy: 'formateur', targetEntity: Formation::class)]
    private Collection $formationsAnimees;

    public function __construct()
    {
        parent::__construct();
        $this->badges = [];
        $this->formationsAnimees = new ArrayCollection();
    }

    /** @return Collection<int, Formation> */
    public function getFormationsAnimees(): Collection
    {
        return $this->formationsAnimees;
    }
}