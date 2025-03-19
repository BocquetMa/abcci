<?php

namespace App\Entity;

use App\Repository\FormateurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormateurRepository::class)]
class Formateur extends Utilisateur
{
    public function __construct()
    {
        // Appel du constructeur parent pour initialiser toutes les propriétés
        parent::__construct();
        
        // S'assurer que badges est bien initialisé
        $this->badges = [];
    }
}