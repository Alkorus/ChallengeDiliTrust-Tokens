<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\ManyToMany;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $lien = null;

    #[ORM\Column()]
    private ?DateTime $MomentEnregistrement = null;

    #[ManyToOne(targetEntity:"User", inversedBy:"enfants" )]
    private ?User $auteur;

    // La liste des utilisateurs ayant accès au document
    #[ManyToMany(targetEntity:"User", inversedBy:"bibliotheque")]
    #[JoinTable(name:"document_user")]
    private $proprietaires;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tag = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iv = null;

    public function __construct(User $user)
    {
        $this->MomentEnregistrement = new DateTime();
        $this->auteur = $user;
        $this->proprietaires = new ArrayCollection();
        $this->ajouterProprietaire($user);
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

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(string $lien): self
    {
        $this->lien = $lien;

        return $this;
    }

    public function getMomentEnregistrement(): ?DateTime
    {
        return $this->MomentEnregistrement;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function getProprietaires(): Collection
    {
        return $this->proprietaires;
    }

    public function ajouterProprietaire(User $proprietaire)
    {
        if($this->proprietaires->contains($proprietaire)){
            return;
        }
        $this->proprietaires->add($proprietaire);
    }

    public function retirerProprietaire(User $proprietaire)
    {
        if(!$this->proprietaires->contains($proprietaire)){
            return;
        }
        $this->proprietaires->removeElement($proprietaire);
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTag(): ?string
    {
        return base64_decode($this->tag);
    }

    public function setTag(?string $tag): self
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
