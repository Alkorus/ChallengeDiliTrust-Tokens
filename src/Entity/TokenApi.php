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
define('TAG', '0f4d1dd031');


#[ORM\Entity(repositoryClass: TokenApiRepository::class)]
class TokenApi
{
    

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private ?int $id = null;

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
        if ($estRefresh){
            $delais = '+' . AUTH_TOKEN_DELAIS_MIN . ' minutes';
        } else {
            $delais = '+' . REFRESH_TOKEN_DELAIS_H . ' hours';
        }
        $this->expiration = $exp->modify($delais);
        $this->user = $user;
        $this->estRefresh = $estRefresh;
        $this->estActif = true;
    }

    public function creerToken(): ?string
    {
        if ($this->id < 1) {
            throw new Exception('Objet token non inclus dans la BD');
        }
        $tokenInfo = array(
            'tokenID' => $this->id,
            'expiration' => $this->expiration,
            'userID' => $this->user->getId(),
            'estRefresh' => $this->estRefresh
        );
        $tokenInfo = json_encode(array('item' => $tokenInfo), JSON_FORCE_OBJECT);
        $ivlen = openssl_cipher_iv_length(CIPHER);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $token = openssl_encrypt($tokenInfo, CIPHER, KEY,$options=0, $iv, $tag);
        $this->token = $token;
        //var_dump($tag);
        $this->tag = base64_encode($tag);
        //var_dump($this->tag);
        $this->iv = base64_encode($iv);
        return $token;
    }

    public static function lireToken(string $token, string $tag, string $iv): ?array
    {
        $tokenInfo = openssl_decrypt($token, CIPHER, KEY, $options=0, $iv, $tag[0]);
        $tokenInfo = json_decode($tokenInfo, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            //var_dump($tokenInfo);
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
