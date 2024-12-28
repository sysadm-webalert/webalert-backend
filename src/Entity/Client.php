<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Website>
     */
    #[ORM\OneToMany(targetEntity: Website::class, mappedBy: 'client', orphanRemoval: true)]
    private Collection $websites;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'client')]
    private Collection $users;

    /**
     * @var Collection<int, Threshold>
     */
    #[ORM\OneToMany(targetEntity: Threshold::class, mappedBy: 'client', orphanRemoval: true)]
    private Collection $thresholds;

    /**
     * @var Collection<int, Events>
     */
    #[ORM\OneToMany(targetEntity: Events::class, mappedBy: 'client', orphanRemoval: true)]
    private Collection $events;

    public function __construct()
    {
        $this->websites = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->thresholds = new ArrayCollection();
        $this->events = new ArrayCollection();
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Website>
     */
    public function getWebsites(): Collection
    {
        return $this->websites;
    }

    public function addWebsite(Website $website): static
    {
        if (!$this->websites->contains($website)) {
            $this->websites->add($website);
            $website->setClientId($this);
        }

        return $this;
    }

    public function removeWebsite(Website $website): static
    {
        if ($this->websites->removeElement($website)) {
            if ($website->getClientId() === $this) {
                $website->setClientId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setClientId($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getClientId() === $this) {
                $user->setClientId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Threshold>
     */
    public function getThresholds(): Collection
    {
        return $this->thresholds;
    }

    public function addThreshold(Threshold $threshold): static
    {
        if (!$this->thresholds->contains($threshold)) {
            $this->thresholds->add($threshold);
            $threshold->setClient($this);
        }

        return $this;
    }

    public function removeThreshold(Threshold $threshold): static
    {
        if ($this->thresholds->removeElement($threshold)) {
            // set the owning side to null (unless already changed)
            if ($threshold->getClient() === $this) {
                $threshold->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Events>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Events $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setClient($this);
        }

        return $this;
    }

    public function removeEvent(Events $event): static
    {
        if ($this->events->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getClient() === $this) {
                $event->setClient(null);
            }
        }

        return $this;
    }
}
