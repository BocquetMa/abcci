<?php

namespace App\Controller;

use App\Entity\Formateur;
use App\Form\FormateurType;
use App\Repository\FormateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/formateur', name: 'formateur_')]
class FormateurController extends AbstractController
{
    #[Route('/mes-formations', name: 'mes_formations')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function mesFormations(): Response
    {
        /** @var Formateur $formateur */
        $formateur = $this->getUser();
        return $this->render('formateur/mes_formations.html.twig', [
            'formations' => $formateur->getFormationsAnimees(),
        ]);
    }

    #[Route('/lister', name: 'lister')]
    public function lister(FormateurRepository $formateurRepository): Response
    {
        $formateurs = $formateurRepository->findAll();
        return $this->render('formateur/lister.html.twig', [
            'formateurs' => $formateurs,
        ]);
    }

    #[Route('/ajouter', name: 'ajouter')]
    #[IsGranted('ROLE_ADMIN')]
    public function ajouter(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $formateur = new Formateur();
        $form = $this->createForm(FormateurType::class, $formateur, [
            'require_password' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Définir le rôle formateur
            $formateur->setRoles(['ROLE_FORMATEUR']);
            
            // Hasher le mot de passe
            $hashedPassword = $passwordHasher->hashPassword(
                $formateur,
                $form->get('password')->getData()
            );
            $formateur->setPassword($hashedPassword);

            $entityManager->persist($formateur);
            $entityManager->flush();
            return $this->redirectToRoute('formateur_lister');
        }

        return $this->render('formateur/ajouter.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/modifier/{id}', name: 'modifier')]
    #[IsGranted('ROLE_ADMIN')]
    public function modifier(
        Formateur $formateur, 
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $form = $this->createForm(FormateurType::class, $formateur, [
            'require_password' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si un nouveau mot de passe a été fourni
            if ($password = $form->get('password')->getData()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $formateur,
                    $password
                );
                $formateur->setPassword($hashedPassword);
            }

            $entityManager->flush();
            return $this->redirectToRoute('formateur_lister');
        }

        return $this->render('formateur/modifier.html.twig', [
            'form' => $form->createView(),
            'formateur' => $formateur,
        ]);
    }

    #[Route('/supprimer/{id}', name: 'supprimer')]
    #[IsGranted('ROLE_ADMIN')]
    public function supprimer(Formateur $formateur, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($formateur);
        $entityManager->flush();
        return $this->redirectToRoute('formateur_lister');
    }

    #[Route('/{id}', name: 'voir', methods: ['GET'])]
    public function voir(Formateur $formateur): Response
    {
        return $this->render('formateur/voir.html.twig', [
            'formateur' => $formateur,
        ]);
    }
}