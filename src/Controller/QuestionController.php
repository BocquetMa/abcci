<?php

namespace App\Controller;

use App\Entity\Quiz;
use App\Entity\Question;
use App\Entity\Reponse;
use App\Form\QuestionType;
use App\Form\ReponseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/quiz')]
#[IsGranted('ROLE_FORMATEUR')]
class QuestionController extends AbstractController
{
    #[Route('/{id}/questions', name: 'question_liste')]
    public function liste(Quiz $quiz): Response
    {
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à gérer les questions de ce quiz.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        // Récupérer les questions du quiz, triées par ordre
        $questions = $quiz->getQuestions()->toArray();
        usort($questions, function($a, $b) {
            return $a->getOrdre() <=> $b->getOrdre();
        });
        
        return $this->render('question/liste.html.twig', [
            'quiz' => $quiz,
            'formation' => $formation,
            'questions' => $questions
        ]);
    }

    #[Route('/{id}/question/ajouter', name: 'question_ajouter')]
    public function ajouter(Quiz $quiz, Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à ajouter des questions à ce quiz.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        $question = new Question();
        $question->setQuiz($quiz);
        
        // Définir l'ordre de la question (à la suite des questions existantes)
        $ordre = $quiz->getQuestions()->count();
        $question->setOrdre($ordre);
        
        // Ajouter quelques réponses par défaut pour les types choix unique/multiple
        for ($i = 0; $i < 4; $i++) {
            $reponse = new Reponse();
            $reponse->setOrdre($i);
            
            // Définir la première réponse comme correcte par défaut
            if ($i === 0) {
                $reponse->setCorrect(true);
            }
            
            $question->addReponse($reponse);
        }
        
        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Si le type est texte, supprimer les réponses à choix multiple
            if ($question->getType() === Question::TYPE_TEXTE) {
                foreach ($question->getReponses() as $reponse) {
                    $question->removeReponse($reponse);
                }
            }
            
            $entityManager->persist($question);
            $entityManager->flush();
            
            $quiz->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();
            
            $this->addFlash('success', 'La question a été ajoutée avec succès.');
            return $this->redirectToRoute('question_liste', ['id' => $quiz->getId()]);
        }
        
        return $this->render('question/ajouter.html.twig', [
            'form' => $form->createView(),
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/question/{id}/editer', name: 'question_editer')]
    public function editer(Question $question, Request $request, EntityManagerInterface $entityManager): Response
    {
        $quiz = $question->getQuiz();
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à modifier cette question.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        $originalType = $question->getType();
        
        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer le changement de type de question
            $newType = $question->getType();
            
            // Si on passe au type texte, supprimer les réponses à choix multiple
            if ($newType === Question::TYPE_TEXTE && $originalType !== Question::TYPE_TEXTE) {
                foreach ($question->getReponses() as $reponse) {
                    $question->removeReponse($reponse);
                    $entityManager->remove($reponse);
                }
            }
            // Si on passe d'un type texte à un autre type, ajouter des réponses par défaut
            elseif ($originalType === Question::TYPE_TEXTE && $newType !== Question::TYPE_TEXTE) {
                for ($i = 0; $i < 4; $i++) {
                    $reponse = new Reponse();
                    $reponse->setTexte('Réponse ' . ($i + 1));
                    $reponse->setOrdre($i);
                    
                    // Définir la première réponse comme correcte par défaut
                    if ($i === 0) {
                        $reponse->setCorrect(true);
                    }
                    
                    $question->addReponse($reponse);
                    $entityManager->persist($reponse);
                }
            }
            
            // Pour le type vrai/faux, s'assurer qu'il n'y a que deux réponses
            if ($newType === Question::TYPE_VRAI_FAUX) {
                // Supprimer toutes les réponses existantes
                foreach ($question->getReponses() as $reponse) {
                    $question->removeReponse($reponse);
                    $entityManager->remove($reponse);
                }
                
                // Ajouter les réponses Vrai et Faux
                $reponseVrai = new Reponse();
                $reponseVrai->setTexte('Vrai');
                $reponseVrai->setOrdre(0);
                $reponseVrai->setCorrect(true); // Par défaut, "Vrai" est la réponse correcte
                $question->addReponse($reponseVrai);
                $entityManager->persist($reponseVrai);
                
                $reponseFaux = new Reponse();
                $reponseFaux->setTexte('Faux');
                $reponseFaux->setOrdre(1);
                $reponseFaux->setCorrect(false);
                $question->addReponse($reponseFaux);
                $entityManager->persist($reponseFaux);
            }
            
            $entityManager->flush();
            
            $quiz->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();
            
            $this->addFlash('success', 'La question a été mise à jour avec succès.');
            
            // Rediriger vers la gestion des réponses si nécessaire
            if ($newType !== Question::TYPE_TEXTE) {
                return $this->redirectToRoute('reponse_liste', ['id' => $question->getId()]);
            }
            
            return $this->redirectToRoute('question_liste', ['id' => $quiz->getId()]);
        }
        
        return $this->render('question/editer.html.twig', [
            'form' => $form->createView(),
            'question' => $question,
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/question/{id}/supprimer', name: 'question_supprimer')]
    public function supprimer(Question $question, Request $request, EntityManagerInterface $entityManager): Response
    {
        $quiz = $question->getQuiz();
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à supprimer cette question.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        if ($request->isMethod('POST')) {
            $entityManager->remove($question);
            
            // Réordonner les questions restantes
            $questions = $quiz->getQuestions()->toArray();
            usort($questions, function($a, $b) {
                return $a->getOrdre() <=> $b->getOrdre();
            });
            
            $ordre = 0;
            foreach ($questions as $q) {
                if ($q->getId() !== $question->getId()) {
                    $q->setOrdre($ordre++);
                }
            }
            
            $quiz->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();
            
            $this->addFlash('success', 'La question a été supprimée avec succès.');
            return $this->redirectToRoute('question_liste', ['id' => $quiz->getId()]);
        }
        
        return $this->render('question/supprimer.html.twig', [
            'question' => $question,
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/question/{id}/reordonner', name: 'question_reordonner', methods: ['POST'])]
    public function reordonner(Question $question, Request $request, EntityManagerInterface $entityManager): Response
    {
        $quiz = $question->getQuiz();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        $formation = $quiz->getFormation();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        $direction = $data['direction'] ?? 'up';
        
        // Récupérer toutes les questions du quiz, triées par ordre
        $questions = $quiz->getQuestions()->toArray();
        usort($questions, function($a, $b) {
            return $a->getOrdre() <=> $b->getOrdre();
        });
        
        // Trouver l'index de la question actuelle
        $index = -1;
        foreach ($questions as $i => $q) {
            if ($q->getId() === $question->getId()) {
                $index = $i;
                break;
            }
        }
        
        if ($index === -1) {
            return $this->json(['success' => false, 'message' => 'Question non trouvée'], 404);
        }
        
        // Déterminer la question avec laquelle échanger l'ordre
        $targetIndex = ($direction === 'up') ? $index - 1 : $index + 1;
        
        // Vérifier que l'index cible est valide
        if ($targetIndex < 0 || $targetIndex >= count($questions)) {
            return $this->json(['success' => false, 'message' => 'Impossible de déplacer la question dans cette direction'], 400);
        }
        
        // Échanger l'ordre des questions
        $targetQuestion = $questions[$targetIndex];
        $tempOrdre = $question->getOrdre();
        $question->setOrdre($targetQuestion->getOrdre());
        $targetQuestion->setOrdre($tempOrdre);
        
        $quiz->setDateModification(new \DateTimeImmutable());
        $entityManager->flush();
        
        return $this->json(['success' => true]);
    }
}