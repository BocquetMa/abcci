<?php

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Formation;
use App\Entity\Utilisateur;
use App\Form\InscriptionType;
use App\Form\ValidationInscriptionType;
use App\Repository\InscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inscription', name: 'inscription_')]
class InscriptionController extends AbstractController
{
    #[Route('/demande/{id}', name: 'demande')]
    #[IsGranted('ROLE_USER')]
    public function demande(
        Formation $formation, 
        Request $request, 
        EntityManagerInterface $entityManager,
        InscriptionRepository $inscriptionRepository
    ): Response {
        $utilisateur = $this->getUser();
        
        // Vérifier si la formation est complète
        if ($formation->placesDisponibles() <= 0) {
            $this->addFlash('danger', 'Cette formation est complète');
            return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
        }
        
        // Vérifier si l'utilisateur est déjà inscrit
        if ($inscriptionRepository->estDejaInscrit($utilisateur, $formation)) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à cette formation');
            return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
        }
        
        $inscription = new Inscription();
        $inscription->setUtilisateur($utilisateur);
        $inscription->setFormation($formation);
        
        $form = $this->createForm(InscriptionType::class, $inscription);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($inscription);
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre demande d\'inscription a été enregistrée et est en attente de validation');
            return $this->redirectToRoute('profil_inscriptions');
        }
        
        return $this->render('inscription/demande.html.twig', [
            'formation' => $formation,
            'form' => $form->createView()
        ]);
    }
    
    #[Route('/annuler/{id}', name: 'annuler')]
    #[IsGranted('ROLE_USER')]
    public function annuler(Inscription $inscription, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est bien le propriétaire de l'inscription
        if ($inscription->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        // On ne peut annuler que les inscriptions en attente
        if (!$inscription->isEnAttente()) {
            $this->addFlash('warning', 'Vous ne pouvez pas annuler cette inscription car son statut est ' . $inscription->getStatut());
            return $this->redirectToRoute('profil_inscriptions');
        }
        
        $entityManager->remove($inscription);
        $entityManager->flush();
        
        $this->addFlash('success', 'Votre demande d\'inscription a été annulée');
        return $this->redirectToRoute('profil_inscriptions');
    }
    
    #[Route('/admin/liste', name: 'admin_liste')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminListe(InscriptionRepository $inscriptionRepository): Response
    {
        $inscriptions = $inscriptionRepository->findAll();
        
        return $this->render('inscription/admin/liste.html.twig', [
            'inscriptions' => $inscriptions
        ]);
    }
    
    #[Route('/admin/en-attente', name: 'admin_en_attente')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminEnAttente(InscriptionRepository $inscriptionRepository): Response
    {
        $inscriptions = $inscriptionRepository->findAllEnAttente();
        
        return $this->render('inscription/admin/en_attente.html.twig', [
            'inscriptions' => $inscriptions
        ]);
    }
    
    #[Route('/formateur/liste', name: 'formateur_liste')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function formateurListe(InscriptionRepository $inscriptionRepository): Response
    {
        $inscriptions = $inscriptionRepository->findByFormateur($this->getUser());
        
        return $this->render('inscription/formateur/liste.html.twig', [
            'inscriptions' => $inscriptions
        ]);
    }
    
    #[Route('/formateur/en-attente', name: 'formateur_en_attente')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function formateurEnAttente(InscriptionRepository $inscriptionRepository): Response
    {
        $inscriptions = $inscriptionRepository->findEnAttenteByFormateur($this->getUser());
        
        return $this->render('inscription/formateur/en_attente.html.twig', [
            'inscriptions' => $inscriptions
        ]);
    }
    
    #[Route('/valider/{id}', name: 'valider')]
#[IsGranted('ROLE_FORMATEUR')]
public function valider(
    Inscription $inscription, 
    Request $request, 
    EntityManagerInterface $entityManager
): Response {
    // Vérifier que le formateur est bien responsable de cette formation
    $formation = $inscription->getFormation();
    if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $this->getUser()) {
        throw $this->createAccessDeniedException();
    }
    
    // Vérifier que l'inscription est bien en attente
    if (!$inscription->isEnAttente()) {
        $this->addFlash('warning', 'Cette inscription a déjà été traitée');
        
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('inscription_admin_liste');
        } else {
            return $this->redirectToRoute('inscription_formateur_liste');
        }
    }
    
    $form = $this->createForm(ValidationInscriptionType::class, $inscription);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $action = $form->get('action')->getData();
        
        if ($action === 'accepter') {
            $dateDebut = $form->get('dateDebut')->getData();
            $dateFin = $form->get('dateFin')->getData();
            
            // Vérifier que les dates sont définies
            if (!$dateDebut || !$dateFin) {
                $this->addFlash('danger', 'Les dates de début et de fin sont obligatoires pour accepter une inscription.');
                return $this->render('inscription/valider.html.twig', [
                    'inscription' => $inscription,
                    'form' => $form->createView()
                ]);
            }
            
            $inscription->accepter($this->getUser(), $dateDebut, $dateFin);
            $this->addFlash('success', 'L\'inscription a été acceptée');
        } else {
            $motif = $form->get('motif')->getData();
            
            // Vérifier que le motif est défini
            if (!$motif) {
                $this->addFlash('danger', 'Le motif est obligatoire pour refuser une inscription.');
                return $this->render('inscription/valider.html.twig', [
                    'inscription' => $inscription,
                    'form' => $form->createView()
                ]);
            }
            
            $inscription->refuser($this->getUser(), $motif);
            $this->addFlash('success', 'L\'inscription a été refusée');
        }
        
        $entityManager->flush();
        
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('inscription_admin_en_attente');
        } else {
            return $this->redirectToRoute('inscription_formateur_en_attente');
        }
    }
    
    return $this->render('inscription/valider.html.twig', [
        'inscription' => $inscription,
        'form' => $form->createView()
    ]);
}
    
    #[Route('/details/{id}', name: 'details')]
    public function details(Inscription $inscription): Response
    {
        // Vérifier que l'utilisateur a le droit de voir cette inscription
        if (
            $inscription->getUtilisateur() !== $this->getUser() && 
            !$this->isGranted('ROLE_ADMIN') && 
            ($inscription->getFormation()->getFormateur() !== $this->getUser())
        ) {
            throw $this->createAccessDeniedException();
        }
        
        return $this->render('inscription/details.html.twig', [
            'inscription' => $inscription
        ]);
    }
    
    #[Route('/terminer/{id}', name: 'terminer')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function terminer(
        Inscription $inscription, 
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que le formateur est bien responsable de cette formation
        $formation = $inscription->getFormation();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        // Vérifier que l'inscription a bien été acceptée
        if (!$inscription->isAcceptee()) {
            $this->addFlash('warning', 'Seules les inscriptions acceptées peuvent être marquées comme terminées');
            
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('inscription_admin_liste');
            } else {
                return $this->redirectToRoute('inscription_formateur_liste');
            }
        }
        
        $inscription->terminer();
        $entityManager->flush();
        
        $this->addFlash('success', 'La formation a été marquée comme terminée pour cet utilisateur');
        
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('inscription_admin_liste');
        } else {
            return $this->redirectToRoute('inscription_formateur_liste');
        }
    }
}