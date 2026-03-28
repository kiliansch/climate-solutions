<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CalendarRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CalendarRepository::class)]
#[ORM\Table(name: 'calendars')]
#[ORM\HasLifecycleCallbacks]
class Calendar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(
        type: 'string',
        length: 10,
        columnDefinition: "VARCHAR(10) DEFAULT 'dayslot' CHECK (display_mode IN ('timeslot', 'dayslot'))"
    )]
    private string $displayMode = 'dayslot';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $client;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $agent;

    #[ORM\Column(length: 36, unique: true)]
    private string $publicToken;

    /** @var Collection<int, Slot> */
    #[ORM\OneToMany(targetEntity: Slot::class, mappedBy: 'calendar', fetch: 'EXTRA_LAZY')]
    private Collection $slots;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->slots = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->publicToken = Uuid::v4()->toRfc4122();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    public function setDisplayMode(string $displayMode): static
    {
        $this->displayMode = $displayMode;

        return $this;
    }

    public function getClient(): User
    {
        return $this->client;
    }

    public function setClient(User $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getAgent(): User
    {
        return $this->agent;
    }

    public function setAgent(User $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getPublicToken(): string
    {
        return $this->publicToken;
    }

    /**
     * @return Collection<int, Slot>
     */
    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
