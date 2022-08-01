<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Doctrine\Persistence\ManagerRegistry;
use App\Form\UserType;
use App\Security\GestionTokens;
use phpDocumentor\Reflection\PseudoTypes\False_;

class UserController extends AbstractController
{

    /**
     * @Route("/", name="pageAccueil") 
     */
    // Accès à la page d'accueil
    public function presentationAction(Request $request)
    {
        return $this->render("accueil.html.twig");
    }

    /**
     * @Route("/login", name="connecterCompte") 
     */
    // Se connecter à un compte
    public function loginAction(ManagerRegistry $doctrine, Request $request)
    {
        //return $this->render("accueil.html.twig");
        // Si le client est déjà connecté
        if($this->EvaluerConnection($doctrine, $request) != null)
        {
            $this->addFlash(
                'notice',
                'Veuillez vous déconnecter avant de tenter une connexion'
            );
            return $this->redirectToRoute('pageAccueil');
        }

        $user = new User();
        $formUser = $this->createForm(UserType::class, $user);
        
        if($request->ismethod("POST"))
        {
            $em = $doctrine->getManager();
            $formUser->handleRequest($request);

            $courriel = $formUser->get('courriel')->getData(); // Le courriel est un champ unique
            $motdepasse = $_POST['pswd'];
            $user = $em->getRepository(User::class)->findOneBy(['courriel' => $courriel]);
            // Si l'utilisateur n'a pas été trouvé dans la BD
            if(empty($user)){
                $this->addFlash('notice', 'Erreur lors de la connexion, veuillez réessayer');
                return $this->render('userConnexion.html.twig', array('formUser' => $formUser->createView()));
            }

            if($user->testerMotdepasse($motdepasse)){

                // Créer le Token d'autentification
                $estRefresh = false;
                $autToken = GestionTokens::CreerToken($doctrine, $user, $estRefresh);
                // Puis le Token de rafraichissement
                $estRefresh = true;
                $refreshToken = GestionTokens::CreerToken($doctrine, $user, $estRefresh);

                // Enregistrer les informations de session
                $request->getSession()->set('user_id', $user->getID());
                $request->getSession()->set('user_nom', $user->getNom());
                $request->getSession()->set('token_auth', $autToken);
                $request->getSession()->set('token_refresh', $refreshToken);
                $this->addFlash(
                    'succ',
                    'Connexion réussie.'
                );
                return $this->redirectToRoute('pageAccueil');

            } else {
                $this->addFlash('notice', 'Erreur lors de la connexion, veuillez réessayer');
            }
        }
        return $this->render('userConnexion.html.twig', array('formUser' => $formUser->createView()));
    }

    /**
     * @Route("/logout", name="deconnecterCompte") 
     */
    // Se déconnecter d'un compte
    public function logoutAction(ManagerRegistry $doctrine, Request $request)
    {
        $user = $this->EvaluerConnection($doctrine, $request);
        if($user != null){
            // Désactiver les tokens de l'utilisateur
            GestionTokens::DesactiverTokensUtilisateur($doctrine, $user);
            // Effacer les variables de session
            $request->getSession()->remove('user_id');
            $request->getSession()->remove('user_nom');
            $request->getSession()->remove('token_auth');
            $request->getSession()->remove('token_refresh');
            $this->addFlash(
                'succ',
                'Déconnection effectuée'
            );
            return $this->redirectToRoute('pageAccueil');
        }
        // Si il y en a un, retirer le token d'autentification qui aurait permis à un browser de voir l'accès à cette option
        if($request->getSession()->get('token_auth'))
        { 
            $request->getSession()->remove('token_auth');
        }
        return $this->redirectToRoute('pageAccueil');
    }

    /**
     * @Route("/creationCompte", name="creationCompte") 
     */
    // Création d'un nouveau compte
    public function creationCompteAction(ManagerRegistry $doctrine, Request $request): Response
    {
        // Si le client est déjà connecté
        if($this->EvaluerConnection($doctrine, $request) != null)
        {
            $this->addFlash(
                'notice',
                'Veuillez vous déconnecter avant de créer un nouveau compte'
            );
            return $this->redirectToRoute('pageAccueil');
        }
        // Si il n'est pas connecté
        $user = new User();
        $formUser = $this->createForm(UserType::class, $user);
        
        if($request->ismethod("POST"))
        {
            $formUser->handleRequest($request);
            
            if($formUser->isValid())
            {
                $em = $doctrine->getManager();
                
                $em->persist($user);
                $em->flush();
                
                // Créer le Token d'autentification
                $estRefresh = false;
                $autToken = GestionTokens::CreerToken($doctrine, $user, $estRefresh);
                // Puis le Token de rafraichissement
                $estRefresh = true;
                $refreshToken = GestionTokens::CreerToken($doctrine, $user, $estRefresh);

                // Enregistrer les informations de session, le client n'aura pas à se reconnecter
                $request->getSession()->set('user_id', $user->getID());
                $request->getSession()->set('user_nom', $user->getNom());
                $request->getSession()->set('token_auth', $autToken);
                $request->getSession()->set('token_refresh', $refreshToken);
                $this->addFlash(
                    'succ',
                    'Compte créé.'
                );
                return $this->redirectToRoute('pageAccueil');
            }
        }
        
        return $this->render('userCreation.html.twig', array('formUser' => $formUser->createView()));
    }

    // Méthode analysant les variables de session du client pour évaluer la connexion
    // retourne null si le client n'est pas connecté
    // retourne l'objet User correspondant au client si il est autentifié
    // renvoi à la page d'accueil en cas d'échec d'autentification
    public function EvaluerConnection(ManagerRegistry $doctrine,  Request $request)
    {
        $em = $doctrine->getManager();
        // Vérifier que les 3 infos de connection (id user, nomuser et authToken) sont présents
        if($request->getSession()->get('user_id') && $request->getSession()->get('user_nom') && $request->getSession()->get('token_auth'))
        {
            $user = $em->getRepository(User::class)->find($request->getSession()->get('user_id'));
            if(empty($user)){
                
                // retirer les variables de session du client bloqué
                $request->getSession()->remove('user_id');
                $request->getSession()->remove('user_nom');
                $request->getSession()->remove('token_auth');
                return null;
            }
            $authToken = $request->getSession()->get('token_auth');
            
            $resultatsToken = GestionTokens::EvaluerToken($doctrine, $user, $authToken);
            

            
            // Bloquer l'accès si l'autentification échoue
            if(!$resultatsToken['valide']){
                
                // retirer les variables de session du client bloqué
                $request->getSession()->remove('user_id');
                $request->getSession()->remove('user_nom');
                $request->getSession()->remove('token_auth');
                return null;
            }
            // L'authentification est OK mais le token a expiré, laisser savoir au client pourquoi on le bloque
            if($resultatsToken['timeOut']){
                
                // retirer les variables de session du client bloqué
                $request->getSession()->remove('user_id');
                $request->getSession()->remove('user_nom');
                $request->getSession()->remove('token_auth');
            
                return null;
            }
            // Le token d'autentification expire bientôt, on le renouvelle
            if($resultatsToken['refresh']){
                if($request->getSession()->get('token_refresh'))
                {
                    $refreshToken = $request->getSession()->get('token_refresh');
                    $resultatsToken = GestionTokens::EvaluerToken($doctrine, $user, $refreshToken);
                    if(!$resultatsToken['valide'] || $resultatsToken['timeout'])
                    {
                        //Créer une nouvelle paire de tokens frais et les enregistrer dans la session
                        $authToken = GestionTokens::CreerToken($doctrine, $user, false);
                        $refreshToken = GestionTokens::CreerToken($doctrine, $user, true);
                        $request->getSession()->set('token_auth', $authToken);
                        $request->getSession()->set('token_refresh', $refreshToken);
                        return $user;

                    } else {
                       
                        // retirer les variables de session du client bloqué
                        $request->getSession()->remove('user_id');
                        $request->getSession()->remove('user_nom');
                        $request->getSession()->remove('token_auth');
                        $request->getSession()->remove('token_refresh');
                       
                        return null;
                    }

                } else {
                    // Si il n'y a pas de token de rafraichissement
                    
                    // retirer les variables de session du client bloqué
                    $request->getSession()->remove('user_id');
                    $request->getSession()->remove('user_nom');
                    $request->getSession()->remove('token_auth');
                
                    return null;
                }
                
            }
            // Le Token d'autentification est bon
            return $user;

        }
       
        return null;
    }
}