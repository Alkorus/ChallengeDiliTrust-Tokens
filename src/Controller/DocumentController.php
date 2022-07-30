<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Form\DocumentType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Doctrine\Persistence\ManagerRegistry;
use phpDocumentor\Reflection\Types\Null_;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\Slugger\SluggerInterface;

define('MAX_FILE_SIZE', 8388608);  // 8Mo de limite
define('CIPHER', 'aes-128-gcm');
define('KEY', '74d2d07841c8cf5d495147793cd82ff0');
define('TAILLE_BLOC_ENCRYPTION', 10000); // Taille des blocs encryptés à la fois pour les données

class DocumentController extends AbstractController
{

    /**
     * @Route("/listeBibli", name="listeDocuments") 
     */
    public function accesBibliAction(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger)
    {
        var_dump('acces0');
        // Autentifier l'utilisateur à partir de ses infos de session et des tokens
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
        
        var_dump('acces5');

        $doc = new Document($user);
        $formDocument = $this->createForm(DocumentType::class, $doc);
        
        // Gérer l'envoi du formulaire d'enregistrement de document
        if($request->ismethod("POST"))
        {
            $em = $doctrine->getManager();
            $formDocument->handleRequest($request);
            $docFile = $formDocument->get('doc')->getData();
            var_dump(filesize($docFile));
            if(filesize($docFile) > MAX_FILE_SIZE)
            {
                $this->addFlash(
                    'notice',
                    "Document trop volumineux, taille maximale de 8Mo"
                );
                return $this->redirectToRoute('listeDocuments');
            }
            $doc = $this->sauvegarderDocument($slugger, $docFile, $user);
            if($doc == null){
                $this->addFlash(
                    'notice',
                    "Sauvegarde échouée, veuillez réessayer"
                );
                return $this->redirectToRoute('listeDocuments');
            }
            $em->persist($doc);
            $em->flush();
        }

        // Obtenir les documents dont il est propriétaire
        $docs = $user->getBibliotheque();
        $documents = [];
        var_dump('acces4');
        // Nettoyer la liste des documents pour concerver seulement les infos essentielles
        // De mémoire le twig est remplis par le serveur avant d'être envoyé au cclient donc étape probablement superflue, 
        // mais mieux vaux être sûr
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

        var_dump('acces6');

        return $this->render("documentListe.html.twig", ['documents' => $documents, 'formDocument' => $formDocument->createView()]);

    }

    /**
     * @Route("/document/{id}", name="document") 
     */
    public function presenterDocument($id, ManagerRegistry $doctrine, Request $request)
    {
        var_dump('doc1');
        // Autentifier l'utilisateur à partir de ses infos de session et des tokens
        $gestionUser = new UserController();
        $user = $gestionUser->EvaluerConnection($doctrine, $request);
        if($user == null){
            $this->addFlash(
                'notice',
                'Accès refusé'
            );
            return $this->redirectToRoute('pageAccueil');
        }
        // Vérifier que l'utilisateur autentifée a le droit d'accès à ce document

        $doc = $this->accesDocument($doctrine, $user, $id);
        // Vérifier que le document demandé existe
        var_dump('doc1');
        if($doc == Null){
            $this->addFlash(
                'notice',
                'Action refusée.'
            );
            return $this->redirectToRoute('listeDocuments');
        }
        
        var_dump('doc1');
        // Faire la liste des utilisateurs non propriétaires (usersNP)
        $em = $doctrine->getManager();
        $users = $em->getRepository(User::class)->findAll();
        $usersNP = [];
        //var_dump($users::count());
        // Ajouter un premier utilisateur vide pour le dropdown
        $userNP['id'] = 0;
        $userNP['nom'] = '';
        $userNP['courriel'] = "";
        array_push($usersNP, $userNP);

        foreach ($users as $user)
        {
            if(!($doc->getProprietaires())->contains($user))
            {
                $userNP['id'] = $user->getId();
                $userNP['nom'] = $user->getNom();
                $userNP['courriel'] = $user->getCourriel();
                array_push($usersNP, $userNP);
            }
        }

        return $this->render("documentDetails.html.twig", ['document' => $doc, 'usersNP' => $usersNP]);
    }

   

    /**
     * @Route("/download/{id}", name="download") 
     */
    public function downloadDocument($id, ManagerRegistry $doctrine, Request $request)
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

        // Vérifier que l'utilisateur autentifée a le droit d'accès à ce document
        $doc = $this->accesDocument($doctrine, $user, $id);
        // Vérifier que le document demandé existe
        if($doc == Null){
            $this->addFlash(
                'notice',
                'Action refusée.'
            );
            return $this->redirectToRoute('listeDocuments');
        }

        $docFile = $this->getParameter('documents_dossier') . '/' . $doc->getLien();
        //$docFile = urldecode($docFile);
        var_dump($docFile);
        $docBin = new BinaryFileResponse($docFile);
       // var_dump(filesize($docBin));
        

        // décrypter le document 
        $iv = $doc->getIv();
        $tag = $doc->getTag();
        $docBin = openssl_decrypt($docBin, CIPHER, KEY, $options=0, $iv, $tag[0]);

        $docBin->headers->set('Content-Type', $doc->getType());
        $docBin->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $doc->getNom() . "." . $doc->getType()
        );
        //die();
        ob_clean();     // nettoyer le buffer de sortie, sans ça le download finissait toujours juste avant la fin du document
        return $docBin;

    }

    /**
     * @Route("/partage/{id_doc}", name="partage") 
     */
    public function partagerDocument($id_doc, ManagerRegistry $doctrine, Request $request)
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
        $doc = $this->accesDocument($doctrine, $user, $id_doc);
        var_dump('part1');
        if($doc == Null){
            $this->addFlash(
                'notice',
                'Action refusée.'
            );
            return $this->redirectToRoute('listeDocuments');
        }
        
        $idUserPartage = $_POST['partage'];
        $em = $doctrine->getManager();
        $userPartage = $em->getRepository(User::class)->find($idUserPartage);
        if(empty($userPartage))
        {
            $this->addFlash(
                'notice',
                'Partage échoué, veuillez réessayer.'
            );
            return $this->redirectToRoute('document', ['id' => $id_doc]);
        }
        $doc->ajouterProprietaire($userPartage);
        $em->persist($doc);
        $em->flush();
        
        return $this->redirectToRoute('document', ['id' => $id_doc]);
    }

    /**
     * @Route("/supprime/{id}", name="supprime") 
     */
    public function supprimerDocument($id, ManagerRegistry $doctrine, Request $request)
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

    // Méthode retournant le document si son accès est autorisé et Null sinon
    public function accesDocument(ManagerRegistry $doctrine, User $user, int $id): Document
    {
        $em = $doctrine->getManager();
        $doc = $em->getRepository(Document::class)->find($id);
        // Vérifier que le document demandé existe
        if(empty($doc)){
            return null;
        }
        if(!($user->getBibliotheque())->contains($doc)){
            return null;
        }
        return $doc;
    }

    public function sauvegarderDocument(SluggerInterface $slugger, UploadedFile $docFile, User $user): Document
    {
        $doc = new Document($user);
        $extension = $docFile->guessExtension();
        $nomOriginal = pathinfo($docFile->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSecure = $slugger->slug($nomOriginal);
        $nomSave = $nomSecure . '-' . uniqid() . '.' . $extension;

        // encrypter le document
        $ivlen = openssl_cipher_iv_length(CIPHER);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $document = openssl_encrypt($docFile, CIPHER, KEY,$options=0, $iv, $tag);

        try {
            $docFile->move(
                $this->getParameter('documents_dossier'),
                $nomSave
            );

        } catch (FileException $e) {
            return null;
        }
        $doc->setNom($nomOriginal);
        $doc->setLien($nomSave);
        $doc->setType($extension);
        $doc->setTag(base64_encode($tag));
        $doc->setIv(base64_encode($iv));

        return $doc;
    }
}