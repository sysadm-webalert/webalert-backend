<?php

namespace App\Entity;

use App\Repository\StatusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusRepository::class)]
class Status
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $statusCode = null;

    #[ORM\Column]
    private ?float $responseTime = null;

    #[ORM\Column]
    private ?bool $isUp = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $checkedAt = null;

    #[ORM\ManyToOne(inversedBy: 'statuses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Website $website = null;

    /**
     * @var Collection<int, Alerts>
     */
    #[ORM\OneToMany(targetEntity: Alerts::class, mappedBy: 'status', orphanRemoval: true)]
    private Collection $alerts;

    #[ORM\Column]
    private ?float $pageLoad = null;

    #[ORM\Column]
    private ?float $pageSize = null;

    public function __construct()
    {
        $this->alerts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getResponseTime(): ?float
    {
        return $this->responseTime;
    }

    public function setResponseTime(float $responseTime): static
    {
        $this->responseTime = $responseTime;

        return $this;
    }

    public function isUp(): ?bool
    {
        return $this->isUp;
    }

    public function setUp(bool $isUp): static
    {
        $this->isUp = $isUp;

        return $this;
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTimeImmutable $checkedAt): static
    {
        $this->checkedAt = $checkedAt;

        return $this;
    }

    public function getWebsiteId(): ?Website
    {
        return $this->website;
    }

    public function setWebsiteId(?Website $website): static
    {
        $this->website = $website;

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
            $alert->setStatus($this);
        }

        return $this;
    }

    public function removeAlert(Alerts $alert): static
    {
        if ($this->alerts->removeElement($alert) && $alert->getStatus() === $this) {
            $alert->setStatus(null);
        }

        return $this;
    }

    public function getPageLoad(): ?float
    {
        return $this->pageLoad;
    }

    public function setPageLoad(float $pageLoad): static
    {
        $this->pageLoad = $pageLoad;

        return $this;
    }

    public function getPageSize(): ?float
    {
        return $this->pageSize;
    }

    public function setPageSize(float $pageSize): static
    {
        $this->pageSize = $pageSize;

        return $this;
    }
}
