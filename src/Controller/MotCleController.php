<?php

namespace App\Controller;

use App\Entity\MotCle;
use App\Form\MotCleType;
use App\Repository\MotCleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/mot-cle', name: 'mot_cle_')]
class MotCleController extends AbstractController
{
    #[Route('/lister', name: 'lister')]
    public function lister(MotCleRepository $motCleRepository): Response
    {
        return $this->render('mot_cle/lister.html.twig', [
            'motsCles' => $motCleRepository->findAll(),
        ]);
    }

    #[Route('/ajouter', name: 'ajouter')]
    public function ajouter(Request $request, EntityManagerInterface $entityManager): Response
    {
        $motCle = new MotCle();
        $form = $this->createForm(MotCleType::class, $motCle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($motCle);
            $entityManager->flush();
            return $this->redirectToRoute('mot_cle_lister');
        }

        return $this->render('mot_cle/ajouter.html.twig', [
            'form' => $form->createView(),
            'mot_cle' => $motCle 
        ]);
    }

    #[Route('/modifier/{id}', name: 'modifier')]
    public function modifier(MotCle $motCle, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MotCleType::class, $motCle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('mot_cle_lister');
        }

        return $this->render('mot_cle/ajouter.html.twig', [
            'form' => $form->createView(),
            'mot_cle' => $motCle
        ]);
    }

    #[Route('/supprimer/{id}', name: 'supprimer')]
    public function supprimer(MotCle $motCle, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($motCle);
        $entityManager->flush();
        
        return $this->redirectToRoute('mot_cle_lister');
    }
}