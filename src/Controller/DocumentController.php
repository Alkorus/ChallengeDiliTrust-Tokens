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

// 28Mo de limite, la méthode d'encryption des documents requiert deux fois la taille du document pour travailler,
// on met donc la limite un peu en dessous de la moitié de la RAM disponible à PHP (60 MO)
define('MAX_FILE_SIZE', 29360128);  
define('CIPHER_DOC', 'aes-128-gcm');
define('KEY_DOC', '74d2d07841c8cf5d495147793cd82ff0');

class DocumentController extends AbstractController
{

    /**
     * @Route("/listeBibli", name="listeDocuments") 
     */
    // Accès à la liste des documents accessibles au client et gestion de l'ajout de document
    public function accesBibliAction(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger)
    {
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
        $em = $doctrine->getManager();
        
        $doc = new Document($user); // Document généré uniquement pour utiliser le créateur de formulaire
        $formDocument = $this->createForm(DocumentType::class, $doc);
        
        // Gérer l'envoi du formulaire d'enregistrement de document
        if($request->ismethod("POST"))
        {
            $em = $doctrine->getManager();
            $formDocument->handleRequest($request);
            $docFile = $formDocument->get('doc')->getData();
            
            // S'assurer que le document n'est pas trop volumineux
            if(filesize($docFile) > MAX_FILE_SIZE)
            {
                $this->addFlash(
                    'notice',
                    "Document trop volumineux, taille maximale de 28Mo"
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

        // Obtenir les documents dont le client est propriétaire
        $docs = $user->getBibliotheque();
        $documents = [];

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

        return $this->render("documentListe.html.twig", ['documents' => $documents, 'formDocument' => $formDocument->createView()]);

    }

    /**
     * @Route("/document/{id}", name="document") 
     */
    // Accès à la page des détails d'un document spécifique
    public function presenterDocument($id, ManagerRegistry $doctrine, Request $request)
    {
        
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
        if($doc == Null){
            $this->addFlash(
                'notice',
                'Action refusée.'
            );
            return $this->redirectToRoute('listeDocuments');
        }
        

        // Faire la liste des utilisateurs non propriétaires (usersNP)
        $em = $doctrine->getManager();
        $users = $em->getRepository(User::class)->findAll();
        $usersNP = [];
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
    // Transfert d'un document du serveur vers le client
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
        if($doc == Null){
            $this->addFlash(
                'notice',
                'Action refusée.'
            );
            return $this->redirectToRoute('listeDocuments');
        }

        // décrypter le document 
        $iv = $doc->getIv();
        $tag = $doc->getTag();
        // Ouvrir le document encrypté en lecture et un document vide en écriture
        $source = $this->getParameter('documents_dossier') . $doc->getLien();
        $cheminTemp = $this->getParameter('downloads_dossier') . $doc->getLien();

        $docEncrypt = fopen($source, 'rb');
        $docDecrypte = fopen($cheminTemp, 'w');
        // lire les données brutes du document en lecture
        $texteEncrypte = fread($docEncrypt, filesize($source));
        // Décrypter le document encrypté et écrire le résultat dans le document en écriture
        $document = openssl_decrypt($texteEncrypte, CIPHER, KEY_DOC,$options=0, $iv, $tag);
        fwrite($docDecrypte, $document);
        fclose($docDecrypte);
        fclose($docEncrypt);

        // Récupérer le document décrypté en un format utilisable pour la réponse
        $docBin = new BinaryFileResponse($cheminTemp);
       
        // Remettre le type du document original dans la réponse
        $docBin->headers->set('Content-Type', $doc->getType());
        $docBin->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $doc->getNom() . "." . $doc->getType()
        );

        // Effacer le document décrypté après son envoi
        $docBin->deleteFileAfterSend(true);

        ob_clean();     // nettoyer le buffer de sortie, sans ça le download finissait toujours juste avant la fin du document
        return $docBin;

    }

    /**
     * @Route("/partage/{id_doc}", name="partage") 
     */
    // Donner accès à un document à un utilisateur n'y ayant pas encore droit
    public function partagerDocument($id_doc, ManagerRegistry $doctrine, Request $request)
    {
        // Vérifier l'autentification du client et son droit d'accès au document
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
        if($doc == Null){
            $this->addFlash(
                'notice',
                'Action refusée.'
            );
            return $this->redirectToRoute('listeDocuments');
        }
        
        // Récupérer l'id de l'utilisateur avec lequel partager le document et s'assurer qu'il existe
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
        // Partager le document
        $doc->ajouterProprietaire($userPartage);
        $em->persist($doc);
        $em->flush();
        
        return $this->redirectToRoute('document', ['id' => $id_doc]);
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
        // vérifier que l'utilisateur y a accès
        if(!($user->getBibliotheque())->contains($doc)){
            return null;
        }
        return $doc;
    }

    // Encryption et sauvegarde d'un document sur le serveur
    public function sauvegarderDocument(SluggerInterface $slugger, UploadedFile $docFile, User $user): Document
    {
        // sortir les détails du document pour l'entité et créer le chemon d'accès du document temporaire et de l'ancrypté
        $doc = new Document($user);
        $extension = $docFile->guessExtension();
        $nomOriginal = pathinfo($docFile->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSecure = $slugger->slug($nomOriginal);
        $nomSave = $nomSecure . '-' . uniqid();
        $cheminTemp = $this->getParameter('temp_dossier') . $nomSave;
        $chemin = $this->getParameter('documents_dossier') . $nomSave;

        // enregistrer une version temporaire du document
        try {
            $docFile->move(
                $this->getParameter('temp_dossier'),
                $nomSave
            );
        } catch (FileException $e) {
            return null;
        }

        // commencer par ouvrir un document ou ira les données encryptées et le fichier temporaire
        $docCrypte = fopen($chemin, 'w');
        $docInit = fopen($cheminTemp, 'rb');
        // sortir du fichier temporaire les données numériques à encrypter
        $initial = fread($docInit, filesize($cheminTemp));
        // encrypter le document et enregistrer le document
        $ivlen = openssl_cipher_iv_length(CIPHER_DOC);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $document = openssl_encrypt($initial, CIPHER, KEY_DOC,$options=0, $iv, $tag);
        fwrite($docCrypte, $document);
        fclose($docCrypte);
        fclose($docInit);

        // effacer le document temporaire
        unlink($cheminTemp);

        // Enregistrer les données générales du document, y compris celles permettant de décrypter plus tard
        $doc->setNom($nomOriginal);
        $doc->setLien($nomSave);
        $doc->setType($extension);
        $doc->setTag(base64_encode($tag));
        $doc->setIv(base64_encode($iv));

        return $doc;
    }
}