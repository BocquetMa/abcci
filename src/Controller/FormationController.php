<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Document;
use App\Entity\MotCle;
use App\Repository\InscriptionRepository;
use App\Form\FormationType;
use App\Form\FormationSearchType;
use App\Repository\FormationRepository;
use App\Repository\MotCleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/formation', name: 'formation_')]
class FormationController extends AbstractController
{
    #[Route('/lister', name: 'lister')]
    public function lister(
        Request $request, 
        FormationRepository $formationRepository,
        MotCleRepository $motCleRepository
    ): Response {
        $searchForm = $this->createForm(FormationSearchType::class);
        $searchForm->handleRequest($request);

        $formations = $formationRepository->findByFilters(
            $searchForm->isSubmitted() && $searchForm->isValid() 
                ? $searchForm->getData() 
                : []
        );
        
        // Récupération des mots-clés pour le filtre
        $motsCles = $motCleRepository->findAll();

        return $this->render('formation/lister.html.twig', [
            'formations' => $formations,
            'searchForm' => $searchForm->createView(),
            'motsCles' => $motsCles,
        ]);
    }

    #[Route('/ajouter', name: 'ajouter')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function ajouter(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $formation = new Formation();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $form = $this->createForm(FormationType::class, $formation, [
            'show_formateur' => $isAdmin,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si ce n'est pas un admin, on assigne le formateur connecté
            if (!$isAdmin) {
                /** @var \App\Entity\Formateur $user */
                $user = $this->getUser();
                $formation->setFormateur($user);
            }

            // Gérer les documents uploadés
            if ($form->has('documents')) {
                $documents = $form->get('documents')->getData();
                foreach ($documents as $documentFile) {
                    if ($documentFile instanceof UploadedFile) {
                        $this->handleDocumentUpload($documentFile, $formation, $slugger, $entityManager);
                    }
                }
            }

            $entityManager->persist($formation);
            $entityManager->flush();

            $this->addFlash('success', 'Formation ajoutée avec succès');
            return $this->redirectToRoute('formateur_mes_formations');
        }

        return $this->render('formation/ajouter.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/modifier/{id}', name: 'modifier')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function modifier(
        Formation $formation,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $form = $this->createForm(FormationType::class, $formation, [
            'show_formateur' => $isAdmin,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('documents')) {
                $documents = $form->get('documents')->getData();
                foreach ($documents as $documentFile) {
                    if ($documentFile instanceof UploadedFile) {
                        $this->handleDocumentUpload($documentFile, $formation, $slugger, $entityManager);
                    }
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Formation modifiée avec succès');
            return $this->redirectToRoute('formateur_mes_formations');
        }

        return $this->render('formation/modifier.html.twig', [
            'form' => $form->createView(),
            'formation' => $formation,
        ]);
    }

    #[Route('/supprimer/{id}', name: 'supprimer')]
    #[IsGranted('ROLE_ADMIN')]
    public function supprimer(Formation $formation, EntityManagerInterface $entityManager): Response
    {
        try {
            // Supprimer les documents associés
            foreach ($formation->getDocuments() as $document) {
                $filePath = $this->getParameter('documents_directory') . '/' . $document->getNomFichier();
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $entityManager->remove($document);
            }

            $entityManager->remove($formation);
            $entityManager->flush();
            $this->addFlash('success', 'Formation supprimée avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression');
        }

        return $this->redirectToRoute('formation_lister');
    }

    #[Route('/voir/{id}', name: 'voir')]
    public function voir(
        Formation $formation, 
        InscriptionRepository $inscriptionRepository
    ): Response {
        // Vérifier les places disponibles
        $placesDisponibles = $formation->placesDisponibles();
        $complet = $placesDisponibles <= 0;
        
        // Vérifier si l'utilisateur actuel est déjà inscrit
        $inscription = null;
        if ($this->getUser()) {
            $inscription = $inscriptionRepository->findOneBy([
                'utilisateur' => $this->getUser(),
                'formation' => $formation
            ]);
        }

        return $this->render('formation/voir.html.twig', [
            'formation' => $formation,
            'complet' => $complet,
            'placesDisponibles' => $placesDisponibles,
            'inscription' => $inscription
        ]);
    }

    #[Route('/document/supprimer/{id}', name: 'document_supprimer')]
    #[IsGranted('ROLE_ADMIN')]
    public function supprimerDocument(
        Document $document, 
        EntityManagerInterface $entityManager
    ): Response {
        $formation = $document->getFormation();

        // Supprimer le fichier physique
        $filePath = $this->getParameter('documents_directory') . '/' . $document->getNomFichier();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $entityManager->remove($document);
        $entityManager->flush();

        $this->addFlash('success', 'Document supprimé avec succès');
        return $this->redirectToRoute('formation_modifier', ['id' => $formation->getId()]);
    }

    private function handleDocumentUpload(
        UploadedFile $file,
        Formation $formation,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager
    ): void {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move(
                $this->getParameter('documents_directory'),
                $newFilename
            );

            $document = new Document();
            $document->setNom($originalFilename);
            $document->setNomFichier($newFilename);
            $document->setExtension($file->guessExtension());
            $document->setFormation($formation);
            $document->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($document);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'upload du document');
        }
    }

    #[Route('/recherche', name: 'recherche', methods: ['GET'])]
    public function recherche(Request $request, FormationRepository $formationRepository): Response
    {
        $query = $request->query->get('q');
        $formations = $formationRepository->searchFormations($query);

        return $this->render('formation/_resultats_recherche.html.twig', [
            'formations' => $formations
        ]);
    }

    #[Route('/export-pdf/{id}', name: 'export_pdf')]
    public function exportPdf(Formation $formation): Response
    {
        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);

        // Générer le HTML
        $html = $this->renderView('formation/pdf_template.html.twig', [
            'formation' => $formation,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Générer le nom du fichier
        $fileName = 'formation-' . $formation->getTitre() . '.pdf';
        
        // Envoyer le PDF au navigateur
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
            ]
        );
    }

    #[Route('/filtres-avances', name: 'filtres_avances')]
    public function filtresAvances(
        Request $request, 
        FormationRepository $repository,
        PaginatorInterface $paginator,
        MotCleRepository $motCleRepository
    ): Response {
        $filters = [
            'theme' => $request->query->get('theme'),
            'niveau' => $request->query->get('niveau'),
            'dateDebut' => $request->query->get('dateDebut'),
            'dateFin' => $request->query->get('dateFin'),
            'prixMin' => $request->query->get('prixMin'),
            'prixMax' => $request->query->get('prixMax'),
            'placesDisponibles' => $request->query->get('placesDisponibles'),
            'motsCles' => $request->query->get('motsCles'),
        ];

        $queryBuilder = $repository->createAdvancedSearchQueryBuilder($filters);
        
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10 // Nombre d'éléments par page
        );
        
        // Récupération des mots-clés pour le filtre
        $motsCles = $motCleRepository->findAll();

        if ($request->isXmlHttpRequest()) {
            return $this->render('formation/_resultats_filtres.html.twig', [
                'formations' => $pagination
            ]);
        }

        return $this->render('formation/filtres_avances.html.twig', [
            'formations' => $pagination,
            'filters' => $filters,
            'motsCles' => $motsCles
        ]);
    }

    #[Route('/stats', name: 'stats')]
    public function statistiques(Request $request, FormationRepository $repository): Response
    {
        $stats = [
            'total' => $repository->count([]),
            'participants' => $repository->getTotalParticipants(),
            'populaires' => $repository->getFormationsPopulaires(),
            'revenuTotal' => $repository->getRevenuTotal(),
            'tauxRemplissage' => $repository->getTauxRemplissageMoyen(),
        ];

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($stats);
        }

        return $this->render('formation/statistiques.html.twig', [
            'stats' => $stats
        ]);
    }
}