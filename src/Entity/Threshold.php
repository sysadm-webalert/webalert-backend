<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ThresholdRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Validator\ThresholdConstraint;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ThresholdRepository::class)]
class Threshold
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'threshold', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Website $website = null;

    #[ORM\ManyToOne(inversedBy: 'thresholds')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(length: 255)]
    #[ThresholdConstraint(type: 'status_code')]
    private ?string $httpCode = null;

    #[ORM\Column]
    #[ThresholdConstraint(type: 'max_response')]
    private ?float $maxResponse = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[ThresholdConstraint(type: 'percent')]
    private ?float $maxCPU = null;

    #[ORM\Column(nullable: true)]
    #[ThresholdConstraint(type: 'percent')]
    private ?float $maxRAM = null;

    #[ORM\Column(nullable: true)]
    #[ThresholdConstraint(type: 'percent')]
    private ?float $maxDISK = null;

    public function __construct()
    {
        $this->maxCPU = 90;
        $this->maxRAM = 90;
        $this->maxDISK = 90;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getHttpCode(): ?string
    {
        return $this->httpCode;
    }

    public function setHttpCode(string $httpCode): static
    {
        $this->httpCode = $httpCode;

        return $this;
    }

    public function getMaxResponse(): ?float
    {
        return $this->maxResponse;
    }

    public function setMaxResponse(float $maxResponse): static
    {
        $this->maxResponse = $maxResponse;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getMaxCPU(): ?float
    {
        return $this->maxCPU;
    }

    public function setMaxCPU(?float $maxCPU): static
    {
        $this->maxCPU = $maxCPU;

        return $this;
    }

    public function getMaxRAM(): ?float
    {
        return $this->maxRAM;
    }

    public function setMaxRAM(?float $maxRAM): static
    {
        $this->maxRAM = $maxRAM;

        return $this;
    }

    public function getMaxDISK(): ?float
    {
        return $this->maxDISK;
    }

    public function setMaxDISK(?float $maxDISK): static
    {
        $this->maxDISK = $maxDISK;

        return $this;
    }
}
