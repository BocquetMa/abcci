<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\Reponse;
use App\Form\ReponseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/quiz/question')]
#[IsGranted('ROLE_FORMATEUR')]
class ReponseController extends AbstractController
{
    #[Route('/{id}/reponses', name: 'reponse_liste')]
    public function liste(Question $question): Response
    {
        $quiz = $question->getQuiz();
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à gérer les réponses de cette question.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        // Vérifier que le type de question permet des réponses
        if ($question->getType() === Question::TYPE_TEXTE) {
            $this->addFlash('info', 'Cette question est de type texte et ne nécessite pas de réponses prédéfinies.');
            return $this->redirectToRoute('question_editer', ['id' => $question->getId()]);
        }
        
        // Récupérer les réponses de la question, triées par ordre
        $reponses = $question->getReponses()->toArray();
        usort($reponses, function($a, $b) {
            return $a->getOrdre() <=> $b->getOrdre();
        });
        
        return $this->render('reponse/liste.html.twig', [
            'question' => $question,
            'quiz' => $quiz,
            'formation' => $formation,
            'reponses' => $reponses
        ]);
    }

    #[Route('/{id}/reponse/ajouter', name: 'reponse_ajouter')]
    public function ajouter(Question $question, Request $request, EntityManagerInterface $entityManager): Response
    {
        $quiz = $question->getQuiz();
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à ajouter des réponses à cette question.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        // Vérifier que le type de question permet des réponses
        if ($question->getType() === Question::TYPE_TEXTE) {
            $this->addFlash('info', 'Cette question est de type texte et ne nécessite pas de réponses prédéfinies.');
            return $this->redirectToRoute('question_editer', ['id' => $question->getId()]);
        }
        
        // Pour les questions vrai/faux, limiter à 2 réponses
        if ($question->getType() === Question::TYPE_VRAI_FAUX && $question->getReponses()->count() >= 2) {
            $this->addFlash('warning', 'Les questions vrai/faux ne peuvent avoir que 2 réponses.');
            return $this->redirectToRoute('reponse_liste', ['id' => $question->getId()]);
        }
        
        $reponse = new Reponse();
        $reponse->setQuestion($question);
        
        // Définir l'ordre de la réponse (à la suite des réponses existantes)
        $ordre = $question->getReponses()->count();
        $reponse->setOrdre($ordre);
        
        $form = $this->createForm(ReponseType::class, $reponse);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reponse);
            
            // Mettre à jour la date de modification du quiz
            $quiz->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();
            
            $this->addFlash('success', 'La réponse a été ajoutée avec succès.');
            return $this->redirectToRoute('reponse_liste', ['id' => $question->getId()]);
        }
        
        return $this->render('reponse/ajouter.html.twig', [
            'form' => $form->createView(),
            'question' => $question,
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/reponse/{id}/editer', name: 'reponse_editer')]
    public function editer(Reponse $reponse, Request $request, EntityManagerInterface $entityManager): Response
    {
        $question = $reponse->getQuestion();
        $quiz = $question->getQuiz();
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à modifier cette réponse.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        // Garder une référence à l'état actuel de la réponse
        $wasCorrect = $reponse->isCorrect();
        
        $form = $this->createForm(ReponseType::class, $reponse);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Pour les questions à choix unique et vrai/faux, s'assurer qu'une seule réponse est correcte
            if (($question->getType() === Question::TYPE_CHOIX_UNIQUE || $question->getType() === Question::TYPE_VRAI_FAUX) 
                && $reponse->isCorrect() && !$wasCorrect) {
                // Mettre toutes les autres réponses à incorrect
                foreach ($question->getReponses() as $autreReponse) {
                    if ($autreReponse->getId() !== $reponse->getId()) {
                        $autreReponse->setCorrect(false);
                    }
                }
            }
            
            // Mettre à jour la date de modification du quiz
            $quiz->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();
            
            $this->addFlash('success', 'La réponse a été mise à jour avec succès.');
            return $this->redirectToRoute('reponse_liste', ['id' => $question->getId()]);
        }
        
        return $this->render('reponse/editer.html.twig', [
            'form' => $form->createView(),
            'reponse' => $reponse,
            'question' => $question,
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/reponse/{id}/supprimer', name: 'reponse_supprimer')]
    public function supprimer(Reponse $reponse, Request $request, EntityManagerInterface $entityManager): Response
    {
        $question = $reponse->getQuestion();
        $quiz = $question->getQuiz();
        $formation = $quiz->getFormation();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à supprimer cette réponse.');
            return $this->redirectToRoute('quiz_admin_liste');
        }
        
        // Pour les questions vrai/faux, empêcher la suppression si cela laisse moins de 2 réponses
        if ($question->getType() === Question::TYPE_VRAI_FAUX && $question->getReponses()->count() <= 2) {
            $this->addFlash('warning', 'Les questions vrai/faux doivent avoir exactement 2 réponses.');
            return $this->redirectToRoute('reponse_liste', ['id' => $question->getId()]);
        }
        
        if ($request->isMethod('POST')) {
            $entityManager->remove($reponse);
            
            // Réordonner les réponses restantes
            $reponses = $question->getReponses()->toArray();
            usort($reponses, function($a, $b) {
                return $a->getOrdre() <=> $b->getOrdre();
            });
            
            $ordre = 0;
            foreach ($reponses as $r) {
                if ($r->getId() !== $reponse->getId()) {
                    $r->setOrdre($ordre++);
                }
            }
            
            // Mettre à jour la date de modification du quiz
            $quiz->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();
            
            $this->addFlash('success', 'La réponse a été supprimée avec succès.');
            return $this->redirectToRoute('reponse_liste', ['id' => $question->getId()]);
        }
        
        return $this->render('reponse/supprimer.html.twig', [
            'reponse' => $reponse,
            'question' => $question,
            'quiz' => $quiz,
            'formation' => $formation
        ]);
    }

    #[Route('/reponse/{id}/reordonner', name: 'reponse_reordonner', methods: ['POST'])]
    public function reordonner(Reponse $reponse, Request $request, EntityManagerInterface $entityManager): Response
    {
        $question = $reponse->getQuestion();
        $quiz = $question->getQuiz();
        
        // Vérifier que le formateur est bien assigné à cette formation
        $utilisateur = $this->getUser();
        $formation = $quiz->getFormation();
        if (!$this->isGranted('ROLE_ADMIN') && $formation->getFormateur() !== $utilisateur) {
            return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        $direction = $data['direction'] ?? 'up';
        
        // Récupérer toutes les réponses de la question, triées par ordre
        $reponses = $question->getReponses()->toArray();
        usort($reponses, function($a, $b) {
            return $a->getOrdre() <=> $b->getOrdre();
        });
        
        // Trouver l'index de la réponse actuelle
        $index = -1;
        foreach ($reponses as $i => $r) {
            if ($r->getId() === $reponse->getId()) {
                $index = $i;
                break;
            }
        }
        
        if ($index === -1) {
            return $this->json(['success' => false, 'message' => 'Réponse non trouvée'], 404);
        }
        
        // Déterminer la réponse avec laquelle échanger l'ordre
        $targetIndex = ($direction === 'up') ? $index - 1 : $index + 1;
        
        // Vérifier que l'index cible est valide
        if ($targetIndex < 0 || $targetIndex >= count($reponses)) {
            return $this->json(['success' => false, 'message' => 'Impossible de déplacer la réponse dans cette direction'], 400);
        }
        
        // Échanger l'ordre des réponses
        $targetReponse = $reponses[$targetIndex];
        $tempOrdre = $reponse->getOrdre();
        $reponse->setOrdre($targetReponse->getOrdre());
        $targetReponse->setOrdre($tempOrdre);
        
        // Mettre à jour la date de modification du quiz
        $quiz->setDateModification(new \DateTimeImmutable());
        $entityManager->flush();
        
        return $this->json(['success' => true]);
    }
}