<?php

namespace App\Controller;

use App\Entity\Document;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Doctrine\Persistence\ManagerRegistry;

class DocumentController extends AbstractController
{

    /**
     * @Route("/listeBibli", name="listeDocuments") 
     */
    public function accesBibliAction(ManagerRegistry $doctrine, Request $request)
    {
        var_dump('acces0');
        $gestionUser = new UserController();
        $user = $gestionUser->EvaluerConnection($doctrine, $request);
        var_dump('acces1');
        if($user == null){
            $this->addFlash(
                'notice',
                'Accès refusé'
            );
            return $this->redirectToRoute('pageAccueil');
        }
        var_dump('acces3');
        $em = $doctrine->getManager();
        $docs = $user->getBibliotheque();
        $documents = [];
        var_dump('acces4');
        // Nettoyer la liste des documents pour concerver seulement les infos essentielles
        foreach($docs as $doc)
        {
            $document = [];
            $document['id'] = $doc->getId();
            if($doc->getAuteur()->getId() == $user->getId())
            {
                $document['estAuteur'] = true;
            } else {
                $document['estAuteur'] = false;
            }
            $document['titre'] = $doc->getNom();
            array_push($documents, $document);
        }
        var_dump('acces5');

        return $this->render("documentListe.html.twig", ['documents' => $documents]);

    }

    /**
     * @Route("/document", name="document") 
     */
    public function presenterDocument(ManagerRegistry $doctrine, Request $request)
    {
        $gestionUser = new UserController();
        $user = $gestionUser->EvaluerConnection($doctrine, $request);
        if($user == null){
            $this->addFlash(
                'notice',
                'Accès refusé'
            );
            return $this->redirectToRoute('pageAccueil');
        }


    }

    /**
     * @Route("/upload", name="upload") 
     */
    public function uploadDocument(ManagerRegistry $doctrine, Request $request)
    {
        $gestionUser = new UserController();
        $user = $gestionUser->EvaluerConnection($doctrine, $request);
        if($user == null){
            $this->addFlash(
                'notice',
                'Accès refusé'
            );
            return $this->redirectToRoute('pageAccueil');
        }
        // Construction de nom temporaire en attendant de le prendre de l'élément uploadé
        $nbDocs = ($user->getBibliotheque())->count();
        $nom = $user->getId() . "doc" . $nbDocs;
        
        $em = $doctrine->getManager();
        $doc = new Document($user, $nom);

        $em->persist($doc);
        $em->flush();
        return $this->redirectToRoute('listeDocuments');
    }

    /**
     * @Route("/download", name="download") 
     */
    public function downloadDocument(ManagerRegistry $doctrine, Request $request)
    {
        $gestionUser = new UserController();
        $user = $gestionUser->EvaluerConnection($doctrine, $request);
        if($user == null){
            $this->addFlash(
                'notice',
                'Accès refusé'
            );
            return $this->redirectToRoute('pageAccueil');
        }


    }

    /**
     * @Route("/partage", name="partage") 
     */
    public function partagerDocument(ManagerRegistry $doctrine, Request $request)
    {
        $gestionUser = new UserController();
        $user = $gestionUser->EvaluerConnection($doctrine, $request);
        if($user == null){
            $this->addFlash(
                'notice',
                'Accès refusé'
            );
            return $this->redirectToRoute('pageAccueil');
        }


    }

    /**
     * @Route("/supprime", name="supprime") 
     */
    public function supprimerDocument(ManagerRegistry $doctrine, Request $request)
    {
        $gestionUser = new UserController();
        $user = $gestionUser->EvaluerConnection($doctrine, $request);
        if($user == null){
            $this->addFlash(
                'notice',
                'Accès refusé'
            );
            return $this->redirectToRoute('pageAccueil');
        }


    }
}