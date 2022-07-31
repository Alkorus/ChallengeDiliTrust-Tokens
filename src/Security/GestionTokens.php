<?php

namespace App\Security;

use App\Entity\TokenApi;
use App\Entity\User;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

define('FENETRE_RAFRAICHISSEMENT_MIN', 5);

class GestionTokens extends AbstractController
{
    // Méthode responsable de gérer la création complète d'un token pour un utilisateur
    // Retourne la string Token encryptée à enregistrer dasn les variables de session
    public static function CreerToken(ManagerRegistry $doctrine, User $user, bool $estRefresh): ?string
    {
        $em = $doctrine->getManager();
        $token = new TokenApi($user, $estRefresh);
        $em->persist($token);
        $em->flush();
        // On a créé le token en BD, maintenant on génère sa version crypté pour le client.
        $tokenStr = $token->creerToken();
        // On ajoute le token à l'utilisateur
        if (!$estRefresh) {
            // Désactiver le token présent si il y en a un
            if ($user->getAuthToken() != null){
                $user->getAuthToken()->setEstActif(false);
                $em->persist($user->getAuthToken());
            }
            $user->setAuthToken($token);
        } else {
            if ($user->getRefreshToken() != null){
                $user->getRefreshToken()->setEstActif(false);
                $em->persist($user->getRefreshToken());
            }
            $user->setRefreshToken($token);
        }
        $em->persist($user);
        
        $em->flush();
        return $tokenStr;
    }

    // Méthode analysant un token et retournant un tableau de trois booléens en fonction de son rapport avec l'utilisateur fourni
    // valide => est-ce que le token est valide et lié à l'utilisateur
    // refresh => le token d'autorisation est bientôt expiré et il doit être refraichit
    // timeout => le token a expiré
    public static function EvaluerToken(ManagerRegistry $doctrine, User $user, string $token): ?array
    {
        $em = $doctrine->getManager();
        $reponse = array('valide' => false, 'refresh' => false, 'timeOut' => false);
        // Commence par vérifier si la chaine token fournie correspond à l'un des deux tokens en mémoire pour l'utilisateur
        if($user->getAuthToken()->getToken() == $token)
        {
            $tag = $user->getAuthToken()->getTag();
            $iv = $user->getAuthToken()->getIv();
        } elseif ($user->getRefreshToken()->getToken() == $token)
        {
            $tag = $user->getRefreshToken()->getTag();
            $iv = $user->getRefreshToken()->getIv();
        } else {
            
            return  $reponse;
        }
        // Essayer de décoder la chaine token
        try{
            $tokenInfo = TokenApi::lireToken($token, $tag, $iv);
        } catch(Exception $e) {
            // si il y a eu une erreur dans la lecture du token, on considère qu'il est invalide
            
            return  $reponse;
        }
        
        // Tester que le Token est le bon (comparer le token enregistré dans la BD et celui passé en param)
        $tokenBD = $em->getRepository(TokenApi::class)->find($tokenInfo['tokenID']);
        if ($tokenBD->getToken() != $token){
           
            return  $reponse;
        }
        // Tester que le Token est encore actif
        if (!$tokenBD->getEstActif()){
            
            return  $reponse;
        }
        // Vérifier si le token est expiré
        $maintenant = new DateTime();
        if ($maintenant > $tokenBD->getExpiration())
        {
            $reponse['timeOut'] = true;
            $tokenBD->setEstActif(false);   // Désactiver le token expiré
            
            // On continue le processus car on ne fera un message d'expiration que si le reste du token est OK, limiter l'info donnée
        }
        
        if ($tokenInfo['estRefresh']) {
            // On vérifie que l'utilisateur ayant passé le token est le propriétaire de celui-ci
            if ($user->getRefreshToken()->getToken() != $token){
                
                return  $reponse;
            }

        } else {
            // Vérifier que l'utilisateur ayant passé le token est le propriétaire de celui-ci
            if ($user->getAuthToken()->getToken() != $token){
              
                return  $reponse;
            }

            // Vérifier si le token d'autorisation est près d'expirer pour lancer le rafraichissement
            if ($maintenant > $tokenBD->getExpiration()->modify('-' . FENETRE_RAFRAICHISSEMENT_MIN . ' minutes') && $maintenant < $tokenBD->getExpiration()){
                $reponse['refresh'] = true;
            }
        }
        // Rendu ici le token est valide
        $reponse['valide'] = true;    

        return $reponse;
    }

    // Méthode effectuant la désactivation complète des tokens d'un utilisateur
    public static function DesactiverTokensUtilisateur(ManagerRegistry $doctrine, User $user)
    {
        $em = $doctrine->getManager();
        // Désactiver les tokens
        $authToken = $user->getAuthToken();
        $refreshToken = $user->getRefreshToken();
        $authToken->setEstActif(false);
        $refreshToken->setEstActif(false);
        // Retirer les tokens de l'utilisateur
        $user->setAuthToken(Null);
        $user->setRefreshToken(Null);
        $em->persist($user);
        $em->persist($authToken);
        $em->persist($refreshToken);
        $em->flush();
    }

}