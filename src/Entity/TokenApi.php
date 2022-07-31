<?php

namespace App\Entity;

use App\Repository\TokenApiRepository;
use DateInterval;
use DateTime;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use phpDocumentor\Reflection\Types\Boolean;

// Ces constantes iraient normalement dans des variables d'environnement
define('AUTH_TOKEN_DELAIS_MIN', 15);
define('REFRESH_TOKEN_DELAIS_H', 1);
define('CIPHER', 'aes-128-gcm');
define('KEY', 'cc3da93850f4d1dd031f41eaf6ab4140');


#[ORM\Entity(repositoryClass: TokenApiRepository::class)]
class TokenApi
{
    

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private ?int $id = null;

    // Portion encrypté du token qui sera enregistré dans les variables de session de l'utilisateur afin de procéder à l'autentification
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expiration = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?bool $estRefresh = null;

    #[ORM\Column]
    private ?bool $estActif = null;

    #[ORM\Column(nullable: true)]
    private ?string $tag = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iv = null;

    public function __construct(User $user, bool $estRefresh)
    {
        $exp = new DateTime();
        if (!$estRefresh){
            $delais = '+' . AUTH_TOKEN_DELAIS_MIN . ' minutes';
        } else {
            $delais = '+' . REFRESH_TOKEN_DELAIS_H . ' hours';
        }
       
        $this->expiration = $exp->modify($delais);
        $this->user = $user;
        $this->estRefresh = $estRefresh;
        $this->estActif = true;
    }

    // La création du token encrypté doit venir après l'enregistrement de l'entité dans la BD afin d'y inclure son ID
    public function creerToken(): ?string
    {
        // S'assurer que le token existe déjà dans la BD
        if ($this->id == null) {
            throw new Exception('Objet token non inclus dans la BD');
        }
        // Assembler les informations qui seront incluses dans la chaine token encryptée et transformer le tableau en chaine
        $tokenInfo = array(
            'tokenID' => $this->id,
            'expiration' => $this->expiration,
            'userID' => $this->user->getId(),
            'estRefresh' => $this->estRefresh
        );
        $tokenInfo = json_encode(array('item' => $tokenInfo), JSON_FORCE_OBJECT);
        // encrypter le token
        $ivlen = openssl_cipher_iv_length(CIPHER);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $token = openssl_encrypt($tokenInfo, CIPHER, KEY,$options=0, $iv, $tag);
        // enregistrer les informations manquantes au token sauvegardé en BD
        $this->token = $token;
        $this->tag = base64_encode($tag);
        $this->iv = base64_encode($iv);
        return $token;
    }

    // Décoder une chaine token pour en tirer les information encryptés
    public static function lireToken(string $token, string $tag, string $iv): ?array
    {
        $tokenInfo = openssl_decrypt($token, CIPHER, KEY, $options=0, $iv, $tag[0]);
        $tokenInfo = json_decode($tokenInfo, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $tokenInfo["item"];
        }
        throw new Exception('Le token fournit est invalide');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getExpiration(): ?\DateTimeInterface
    {
        return $this->expiration;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function isEstRefresh(): ?bool
    {
        return $this->estRefresh;
    }

    // J'ai retiré les setters automatiques, ces propriétés ne devraient être enregistrables que de l'intérieur de l'objet.

    public function getEstActif(): ?bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): self
    {
        $this->estActif = $estActif;

        return $this;
    }

    public function getTag(): ?string
    {
        //rewind($this->tag);
        //return unserialize(stream_get_contents($this->tag));
        return base64_decode($this->tag);
    }

    public function setTag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function getIv(): ?string
    {
        return base64_decode($this->iv);
    }

    public function setIv(?string $iv): self
    {
        $this->iv = $iv;

        return $this;
    }
}
