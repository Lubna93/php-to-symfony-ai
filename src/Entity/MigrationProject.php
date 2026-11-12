<?php

namespace App\Entity;

use App\Config\StatusType;
use App\Repository\MigrationProjectRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MigrationProjectRepository::class)]
class MigrationProject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 150)]
    private ?string $sourceFramework = null;

    #[ORM\Column(length: 150)]
    private ?string $targetFramework = null;

    #[ORM\Column(length: 50)]
    private StatusType $status = StatusType::DRAFT;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $archiveFilename = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSourceFramework(): ?string
    {
        return $this->sourceFramework;
    }

    public function setSourceFramework(string $sourceFramework): static
    {
        $this->sourceFramework = $sourceFramework;

        return $this;
    }

    public function getTargetFramework(): ?string
    {
        return $this->targetFramework;
    }

    public function setTargetFramework(string $targetFramework): static
    {
        $this->targetFramework = $targetFramework;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getArchiveFilename(): ?string
    {
        return $this->archiveFilename;
    }

    public function setArchiveFilename(?string $archiveFilename): static
    {
        $this->archiveFilename = $archiveFilename;

        return $this;
    }

}
