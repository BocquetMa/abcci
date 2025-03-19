<?php

namespace App\Controller;

use App\Entity\Quiz;
use App\Entity\QuizTentative;
use App\Entity\Formation;
use App\Entity\Badge;
use App\Service\BadgeService;
use App\Repository\QuizRepository;
use App\Repository\FormationRepository;
use App\Form\QuizType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/quiz')]
class QuizController extends AbstractController
{
    #[Route('/formation/{id}/liste', name: 'quiz_formation_liste')]
    public function listeQuizParFormation(Formation $formation): Response
    {
        // Vérifier que l'utilisateur est inscrit à cette formation
        $utilisateur = $this->getUser();
        if (!$utilisateur->getFormations()->contains($formation) && !$this->isGranted('ROLE_FORMATEUR') && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Vous devez être inscrit à cette formation pour accéder aux quiz.');
            return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
        }

        return $this->render('quiz/liste.html.twig', [
            'formation' => $formation,
            'quizzes' => $formation->getQuizzes()
        ]);
    }

    #[Route('/{id}/details', name: 'quiz_details')]
    public function details(Quiz $quiz): Response
    {
        $formation = $quiz->getFormation();
        
        // Vérifier que l'utilisateur est inscrit à cette formation
        $utilisateur = $this->getUser();
        if (!$utilisateur->getFormations()->contains($formation) && !$this->isGranted('ROLE_FORMATEUR') && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Vous devez être inscrit à cette formation pour accéder à ce quiz.');
            return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
        }

        // Récupérer les tentatives de l'utilisateur pour ce quiz
        $tentatives = $utilisateur->getQuizTentatives()->filter(function($tentative) use ($quiz) {
            return $tentative->getQuiz() === $quiz && $tentative->isTermine();
        });
        
        // Vérifier si l'utilisateur a une tentative en cours
        $tentativeEnCours = $utilisateur->getTentativeEnCours($quiz);

        return $this->render('quiz/details.html.twig', [
            'quiz' => $quiz,
            'formation' => $formation,
            'tentatives' => $tentatives,
            'tentativeEnCours' => $tentativeEnCours,
            'meilleurScore' => $utilisateur->getMeilleurScorePourQuiz($quiz)
        ]);
    }

    #[Route('/{id}/commencer', name: 'quiz_commencer')]
    public function commencer(Quiz $quiz, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        $formation = $quiz->getFormation();
        
        // Vérifier que l'utilisateur est inscrit à cette formation
        if (!$utilisateur->getFormations()->contains($formation) && !$this->isGranted('ROLE_FORMATEUR') && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Vous devez être inscrit à cette formation pour accéder à ce quiz.');
            return $this->redirectToRoute('formation_voir', ['id' => $formation->getId()]);
        }
        
        // Vérifier si le quiz est actif
        if (!$quiz->isActif()) {
            $this->addFlash('danger', 'Ce quiz n\'est pas disponible actuellement.');
            return $this->redirectToRoute('quiz_formation_liste', ['id' => $formation->getId()]);
        }
        
        // Vérifier si l'utilisateur a déjà une tentative en cours
        $tentativeEnCours = $utilisateur->getTentativeEnCours($quiz);
        
        if ($tentativeEnCours) {
            // Si la tentative en cours a dépassé le temps limite, on la termine automatiquement
            if ($tentativeEnCours->isTempsLimiteDepasse()) {
                $tentativeEnCours->terminer();
                $entityManager->flush();
                
                $this->addFlash('warning', 'Votre tentative précédente a été terminée car le temps limite a été dépassé.');
                return $this->redirectToRoute('quiz_details', ['id' => $quiz->getId()]);
            }
            
            // Sinon, on reprend la tentative en cours
            return $this->redirectToRoute('quiz_repondre', [
                'id' => $quiz->getId(),
                'tentative' => $tentativeEnCours->getId()
            ]);
        }
        
        // Créer une nouvelle tentative
        $tentative = new QuizTentative();
        $tentative->setQuiz($quiz);
        $tentative->setUtilisateur($utilisateur);
        
        $entityManager->persist($tentative);
        $entityManager->flush();
        
        return $this->redirectToRoute('quiz_repondre', [
            'id' => $quiz->getId(),
            'tentative' => $tentative->getId()
        ]);
    }

    #[Route('/{id}/repondre/{tentative}', name: 'quiz_repondre')]
    public function repondre(Quiz $quiz, QuizTentative $tentative, Request $request, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        
        // Vérifier que la tentative appartient bien à l'utilisateur actuel
        if ($tentative->getUtilisateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à accéder à cette tentative.');
            return $this->redirectToRoute('quiz_details', ['id' => $quiz->getId()]);
        }
        
        // Vérifier que la tentative n'est pas déjà terminée
        if ($tentative->isTermine()) {
            $this->addFlash('info', 'Cette tentative est déjà terminée.');
            return $this->redirectToRoute('quiz_resultats', [
                'id' => $quiz->getId(),
                'tentative' => $tentative->getId()
            ]);
        }
        
        // Vérifier si le temps limite est dépassé
        if ($tentative->isTempsLimiteDepasse()) {
            $tentative->terminer();
            $entityManager->flush();
            
            $this->addFlash('warning', 'Le temps limite pour ce quiz a été dépassé. Votre tentative a été terminée.');
            return $this->redirectToRoute('quiz_resultats', [
                'id' => $quiz->getId(),
                'tentative' => $tentative->getId()
            ]);
        }
        
        // Récupérer les questions du quiz, triées par ordre
        $questions = $quiz->getQuestions()->toArray();
        usort($questions, function($a, $b) {
            return $a->getOrdre() <=> $b->getOrdre();
        });
        
        // Traitement du formulaire
        if ($request->isMethod('POST')) {
            $reponses = $request->request->all();
            
            // Traiter les réponses pour chaque question
            foreach ($questions as $question) {
                $questionId = $question->getId();
                $reponseIds = $reponses['question_'.$questionId] ?? [];
                
                // Convertir en tableau si ce n'est pas déjà le cas (pour les questions à choix unique)
                if (!is_array($reponseIds)) {
                    $reponseIds = [$reponseIds];
                }
                
                // Pour les questions à texte, on stocke la réponse textuelle
                if ($question->getType() === 'texte') {
                    $reponseIds = [$reponses['question_texte_'.$questionId] ?? ''];
                }
                
                // Ajouter la réponse à la tentative
                $tentative->ajouterReponse($questionId, $reponseIds);
            }
            
            // Si l'utilisateur a cliqué sur "Terminer", on termine la tentative
            if (isset($reponses['terminer'])) {
                $tentative->terminer();
                
                // Mettre à jour les stats de l'utilisateur si le quiz est réussi
                if ($tentative->isReussi()) {
                    $utilisateur->incrementStatistique('formations_completees');
                    $utilisateur->addPoints(10); // Ajouter des points pour avoir réussi un quiz
                }
            }
            
            $entityManager->flush();
            
            // Si la tentative est terminée, rediriger vers les résultats
            if ($tentative->isTermine()) {
                return $this->redirectToRoute('quiz_resultats', [
                    'id' => $quiz->getId(),
                    'tentative' => $tentative->getId()
                ]);
            }
            
            $this->addFlash('success', 'Vos réponses ont été enregistrées.');
        }
        
        // Calculer le temps restant
        $tempsDebut = $tentative->getDateDebut();
        $tempsLimite = $quiz->getTempsLimite();
        $tempsEcoule = (new \DateTimeImmutable())->getTimestamp() - $tempsDebut->getTimestamp();
        $tempsRestantEnSecondes = max(0, ($tempsLimite * 60) - $tempsEcoule);
        
        return $this->render('quiz/repondre.html.twig', [
            'quiz' => $quiz,
            'tentative' => $tentative,
            'questions' => $questions,
            'reponses' => $tentative->getReponses(),
            'tempsRestantEnSecondes' => $tempsRestantEnSecondes
        ]);
    }

    #[Route('/{id}/resultats/{tentative}', name: 'quiz_resultats')]
public function resultats(Quiz $quiz, QuizTentative $tentative, BadgeService $badgeService, EntityManagerInterface $entityManager): Response
{
    $utilisateur = $this->getUser();
    
    // Vérifier que la tentative appartient bien à l'utilisateur actuel
    if ($tentative->getUtilisateur() !== $utilisateur && !$this->isGranted('ROLE_FORMATEUR') && !$this->isGranted('ROLE_ADMIN')) {
        $this->addFlash('danger', 'Vous n\'êtes pas autorisé à accéder à ces résultats.');
        return $this->redirectToRoute('quiz_details', ['id' => $quiz->getId()]);
    }
    
    // Vérifier que la tentative est terminée
    if (!$tentative->isTermine()) {
        $this->addFlash('info', 'Cette tentative n\'est pas encore terminée.');
        return $this->redirectToRoute('quiz_repondre', [
            'id' => $quiz->getId(),
            'tentative' => $tentative->getId()
        ]);
    }

    // Récupérer la formation associée au quiz
    $formation = $quiz->getFormation();
    
    // Calculer le score (au cas où getScore() n'est pas implémenté)
    $score = $tentative->getScore();

    // Vérifier si l'utilisateur a déjà des badges de type quiz
    $existingBadges = $entityManager->getRepository(Badge::class)->findBy([
        'utilisateur' => $utilisateur,
        'type' => Badge::TYPE_QUIZ
    ]);
    
    // On considère qu'un badge a déjà été attribué si l'utilisateur a au moins un badge de quiz
    $badgeDejaAttribue = count($existingBadges) > 0;

    // Attribuer un badge si le quiz est réussi (score >= 70%) et que l'utilisateur n'a pas encore de badge
    if ($score >= 70 && !$badgeDejaAttribue) {
        try {
            // Appel au service pour attribuer le badge
            $badgeService->attribuerBadgeQuiz($utilisateur, 'quiz_reussi', $score);
            
            $this->addFlash('success', 'Félicitations ! Vous avez obtenu un badge pour avoir réussi ce quiz.');
        } catch (\Exception $e) {
            // En cas d'erreur, afficher un message
            $this->addFlash('danger', 'Erreur lors de l\'attribution du badge: ' . $e->getMessage());
        }
    }
    
    // Récupérer les questions du quiz, triées par ordre
    $questions = $quiz->getQuestions()->toArray();
    usort($questions, function($a, $b) {
        return $a->getOrdre() <=> $b->getOrdre();
    });
    
    return $this->render('quiz/resultats.html.twig', [
        'quiz' => $quiz,
        'formation' => $formation,
        'tentative' => $tentative,
        'questions' => $questions,
        'reponses' => $tentative->getReponses()
    ]);
}

    // Routes pour l'administration des quiz (formateurs et admin)
    
    #[Route('/admin/liste', name: 'quiz_admin_liste')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function adminListe(QuizRepository $quizRepository): Response
    {
        $utilisateur = $this->getUser();
        
        // Les formateurs ne voient que leurs propres quiz, les admin voient tout
        if ($this->isGranted('ROLE_ADMIN')) {
            $quizzes = $quizRepository->findAll();
        } else {
            // Récupérer les formations du formateur
            $formations = $utilisateur->getFormations();
            
            // Récupérer les quiz de ces formations
            $quizzes = [];
            foreach ($formations as $formation) {
                foreach ($formation->getQuizzes() as $quiz) {
                    $quizzes[] = $quiz;
                }
            }
        }
        
        return $this->render('quiz/admin/liste.html.twig', [
            'quizzes' => $quizzes
        ]);
    }

    #[Route('/admin/formation/{id}/nouveau', name: 'quiz_admin_nouveau')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function adminNouveau(Formation $formation, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à créer un quiz pour cette formation.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        $quiz = new Quiz();
        $quiz->setFormation($formation);
        
        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($quiz);
            $entityManager->flush();
            
            $this->addFlash('success', 'Le quiz a été créé avec succès.');
            return $this->redirectToRoute('quiz_admin_editer', ['id' => $quiz->getId()]);
        }
        
        return $this->render('quiz/admin/nouveau.html.twig', [
            'form' => $form->createView(),
            'formation' => $formation
        ]);
    }

    #[Route('/admin/{id}/editer', name: 'quiz_admin_editer')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function adminEditer(Quiz $quiz, Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à modifier ce quiz.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $quiz->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();
            
            $this->addFlash('success', 'Le quiz a été mis à jour avec succès.');
            return $this->redirectToRoute('quiz_admin_editer', ['id' => $quiz->getId()]);
        }
        
        return $this->render('quiz/admin/editer.html.twig', [
            'form' => $form->createView(),
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/admin/{id}/supprimer', name: 'quiz_admin_supprimer')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function adminSupprimer(Quiz $quiz, Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à supprimer ce quiz.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        if ($request->isMethod('POST')) {
            $entityManager->remove($quiz);
            $entityManager->flush();
            
            $this->addFlash('success', 'Le quiz a été supprimé avec succès.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        return $this->render('quiz/admin/supprimer.html.twig', [
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/admin/{id}/resultats', name: 'quiz_admin_resultats')]
    #[IsGranted('ROLE_FORMATEUR')]
    public function adminResultats(Quiz $quiz): Response
    {
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à voir les résultats de ce quiz.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        // Récupérer toutes les tentatives terminées pour ce quiz
        $tentatives = $quiz->getTentatives()->filter(function($tentative) {
            return $tentative->isTermine();
        });
        
        return $this->render('quiz/admin/resultats.html.twig', [
            'quiz' => $quiz,
            'formation' => $formation,
            'tentatives' => $tentatives
        ]);
    }
}