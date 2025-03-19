<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Formation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/document')]
class DocumentController extends AbstractController
{
    #[Route('/upload/{id}', name: 'document_upload')]
    #[IsGranted('ROLE_ADMIN')]
    public function upload(
        Request $request, 
        Formation $formation,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$request->files->has('document')) {
            $this->addFlash('error', 'Aucun fichier n\'a été envoyé.');
            return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
        }

        $uploadedFile = $request->files->get('document');
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $uploadedFile->guessExtension();
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;

        try {
            $uploadedFile->move(
                $this->getParameter('documents_directory'),
                $newFilename
            );

            $document = new Document();
            $document->setNom($originalFilename);
            $document->setNomFichier($newFilename);
            $document->setExtension($extension);
            $document->setFormation($formation);

            $entityManager->persist($document);
            $entityManager->flush();

            $this->addFlash('success', 'Document ajouté avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload du document : ' . $e->getMessage());
        }

        return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
    }

    #[Route('/telecharger/{id}', name: 'document_telecharger')]
    public function telecharger(Document $document): Response
    {
        $filepath = $this->getParameter('documents_directory').'/'.$document->getNomFichier();
        
        $response = new BinaryFileResponse($filepath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getNom() . '.' . $document->getExtension()
        );

        return $response;
    }
}