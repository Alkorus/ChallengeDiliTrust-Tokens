<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\JoinTable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['courriel'], message: 'There is already an account with this courriel')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $courriel = null;

    #[ORM\Column(length: 255)]
    private ?string $motdepasse = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Langue $langue = null;

    // La liste des documents accessibles pour un utilisateur
    #[ManyToMany(targetEntity:"Document", inversedBy:"proprietaires")]
    #[JoinTable(name:"document_user")]
    private ?Collection $bibliotheque;

    #[ManyToOne(targetEntity:"TokenApi", inversedBy:"id" )]
    private ?TokenApi $authToken = null;

    #[ManyToOne(targetEntity:"TokenApi", inversedBy:"id" )]
    private ?TokenApi $refreshToken = null;

    public function __construct()
    {
        $this->bibliotheque = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCourriel(): ?string
    {
        return $this->courriel;
    }

    public function setCourriel(string $courriel): self
    {
        $this->courriel = $courriel;

        return $this;
    }

    public function testerMotdepasse($motDePasse): ?bool
    {
        return password_verify($motDePasse, $this->motdepasse);
    }

    public function setMotdepasse(string $motdepasse): self
    {
        // Tenté d'utiliser sodium_crypto pour les hash, mais bien que mon IDE reconnaissait les métodes de hash et comparaison, au moment de les
        // rouler les métodes étaient undefined...
        //$this->motdepasse = sodium_crypto_pwhash_str($motdepasse, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
        $this->motdepasse = password_hash($motdepasse, PASSWORD_DEFAULT);

        return $this;
    }

    public function getMotdepasse(): ?string
    {
        return $this->motdepasse;
    }

    public function getLangue(): ?Langue
    {
        return $this->langue;
    }

    public function setLangue(?Langue $langue): self
    {
        $this->langue = $langue;

        return $this;
    }

    public function getAuthToken(): ?TokenApi
    {
        return $this->authToken;
    }

    public function setAuthToken(?TokenApi $token): self
    {
        $this->authToken = $token;

        return $this;
    }

    public function getRefreshToken(): ?TokenApi
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?TokenApi $token): self
    {
        $this->refreshToken = $token;

        return $this;
    }

    public function getBibliotheque(): Collection
    {
        return $this->bibliotheque;
    }

    public function ajouterABibliotheque(Document $doc)
    {
        if($this->bibliotheque->contains($doc)){
            return;
        }
        $this->bibliotheque->add($doc);
    }

    public function retirerABibliotheque(Document $doc)
    {
        if(!$this->bibliotheque->contains($doc)){
            return;
        }
        $this->bibliotheque->removeElement($doc);
    }


}
