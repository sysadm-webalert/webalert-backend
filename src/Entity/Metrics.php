<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\MetricsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MetricsRepository::class)]
class Metrics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $cpu_usage = null;

    #[ORM\Column]
    private ?float $memory_usage = null;

    #[ORM\Column]
    private ?float $disk_usage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $checkedAt = null;

    #[ORM\ManyToOne(inversedBy: 'metrics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?website $website = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCpuUsage(): ?float
    {
        return $this->cpu_usage;
    }

    public function setCpuUsage(float $cpu_usage): static
    {
        $this->cpu_usage = $cpu_usage;

        return $this;
    }

    public function getMemoryUsage(): ?float
    {
        return $this->memory_usage;
    }

    public function setMemoryUsage(float $memory_usage): static
    {
        $this->memory_usage = $memory_usage;

        return $this;
    }

    public function getDiskUsage(): ?float
    {
        return $this->disk_usage;
    }

    public function setDiskUsage(float $disk_usage): static
    {
        $this->disk_usage = $disk_usage;

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

    public function getWebsiteId(): ?website
    {
        return $this->website;
    }

    public function setWebsiteId(?website $website): static
    {
        $this->website = $website;

        return $this;
    }
}
