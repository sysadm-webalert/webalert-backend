<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\AgentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentRepository::class)]
class Agent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $isInstalled = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastCheckedAt = null;

    #[ORM\OneToOne(inversedBy: 'agent', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Website $website = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isInstalled(): ?bool
    {
        return $this->isInstalled;
    }

    public function setInstalled(bool $isInstalled): static
    {
        $this->isInstalled = $isInstalled;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getLastCheckedAt(): ?\DateTimeInterface
    {
        return $this->lastCheckedAt;
    }

    public function setLastCheckedAt(?\DateTimeInterface $lastCheckedAt): static
    {
        $this->lastCheckedAt = $lastCheckedAt;

        return $this;
    }

    public function getWebsite(): ?Website
    {
        return $this->website;
    }

    public function setWebsite(Website $website): static
    {
        $this->website = $website;

        return $this;
    }
}
