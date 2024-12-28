<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\WebsiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Validator\WebsiteConstraint;

#[ORM\Entity(repositoryClass: WebsiteRepository::class)]
class Website
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[WebsiteConstraint(type: 'url')]
    private ?string $url = null;

    #[ORM\Column(length: 255)]
    #[WebsiteConstraint(type: 'sitename')]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\ManyToOne(inversedBy: 'websites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    /**
     * @var Collection<int, Status>
     */
    #[ORM\OneToMany(targetEntity: Status::class, mappedBy: 'website', orphanRemoval: true)]
    private Collection $statuses;

    /**
     * @var Collection<int, Alerts>
     */
    #[ORM\OneToMany(targetEntity: Alerts::class, mappedBy: 'website', orphanRemoval: true)]
    private Collection $alerts;

    /**
     * @var Collection<int, Metrics>
     */
    #[ORM\OneToMany(targetEntity: Metrics::class, mappedBy: 'website', orphanRemoval: true)]
    private Collection $metrics;

    #[ORM\OneToOne(mappedBy: 'website', cascade: ['persist', 'remove'])]
    private ?Agent $agent = null;

    #[ORM\OneToOne(mappedBy: 'website', cascade: ['persist', 'remove'])]
    private ?Threshold $threshold = null;

    public function __construct()
    {
        $this->statuses = new ArrayCollection();
        $this->alerts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getClientId(): ?Client
    {
        return $this->client;
    }

    public function setClientId(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Collection<int, Status>
     */
    public function getStatuses(): Collection
    {
        return $this->statuses;
    }

    public function addStatus(Status $status): static
    {
        if (!$this->statuses->contains($status)) {
            $this->statuses->add($status);
            $status->setWebsiteId($this);
        }

        return $this;
    }

    public function removeStatus(Status $status): static
    {
        if ($this->statuses->removeElement($status) && $status->getWebsiteId() === $this) {
            $status->setWebsiteId(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Alerts>
     */
    public function getAlerts(): Collection
    {
        return $this->alerts;
    }

    public function addAlert(Alerts $alert): static
    {
        if (!$this->alerts->contains($alert)) {
            $this->alerts->add($alert);
            $alert->setWebsite($this);
        }

        return $this;
    }

    public function removeAlert(Alerts $alert): static
    {
        if ($this->alerts->removeElement($alert) && $alert->getWebsite() === $this) {
            $alert->setWebsite(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Metrics>
     */
    public function getMetrics(): Collection
    {
        return $this->metrics;
    }

    public function addMetric(Metrics $metric): static
    {
        if (!$this->metrics->contains($metric)) {
            $this->metrics->add($metric);
            $metric->setWebsite($this);
        }

        return $this;
    }

    public function removeMetric(Metrics $metric): static
    {
        if ($this->metrics->removeElement($metric) && $metric->getWebsite() === $this) {
            $metric->setWebsite(null);
        }

        return $this;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(Agent $agent): static
    {
        // set the owning side of the relation if necessary
        if ($agent->getWebsite() !== $this) {
            $agent->setWebsite($this);
        }

        $this->agent = $agent;

        return $this;
    }

    public function getThreshold(): ?Threshold
    {
        return $this->threshold;
    }

    public function setThreshold(Threshold $threshold): static
    {
        // set the owning side of the relation if necessary
        if ($threshold->getWebsite() !== $this) {
            $threshold->setWebsite($this);
        }

        $this->threshold = $threshold;

        return $this;
    }
}
