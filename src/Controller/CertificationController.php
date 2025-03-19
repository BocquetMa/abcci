<?php
// src/Controller/CertificationController.php
namespace App\Controller;

use App\Entity\Certification;
use App\Entity\Formation;
use App\Entity\Utilisateur;
use Dompdf\Dompdf;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Inscription;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/certification')]
class CertificationController extends AbstractController
{
    #[Route('/generer/{formation}/{utilisateur}', name: 'generer')]
    public function generer(
        Formation $formation, 
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier si l'utilisateur a suivi la formation
        $inscription = $entityManager->getRepository(Inscription::class)->findOneBy([
            'utilisateur' => $utilisateur,
            'formation' => $formation,
            'estValide' => true
        ]);

        if (!$inscription) {
            throw $this->createAccessDeniedException('Utilisateur non inscrit à cette formation');
        }

        // Configuration de DOMPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);
        
        // Générer le contenu HTML du certificat
        $html = $this->renderView('certification/template.html.twig', [
            'formation' => $formation,
            'utilisateur' => $utilisateur,
            'date' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Créer le fichier PDF
        $fileName = sprintf('certification_%s_%s.pdf', 
            $utilisateur->getId(),
            $formation->getId()
        );
        $outputPath = $this->getParameter('certifications_directory') . '/' . $fileName;
        file_put_contents($outputPath, $dompdf->output());

        // Enregistrer la certification dans la base de données
        $certification = new Certification();
        $certification->setUtilisateur($utilisateur);
        $certification->setFormation($formation);
        $certification->setNomFichier($fileName);
        $certification->setDateObtention(new \DateTimeImmutable());
        $certification->setEstGenereAutomatiquement(true);

        $entityManager->persist($certification);
        $entityManager->flush();

        return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
    }

    #[Route('/telecharger/{id}', name: 'telecharger')]
    public function telecharger(Certification $certification): Response
    {
        $filePath = $this->getParameter('certifications_directory') . '/' . $certification->getNomFichier();

        return $this->file($filePath, sprintf(
            'certification_%s.pdf',
            $certification->getFormation()->getTitre()
        ));
    }
}