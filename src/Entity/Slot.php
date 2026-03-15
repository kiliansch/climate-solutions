<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SlotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SlotRepository::class)]
#[ORM\Table(name: 'slots')]
#[ORM\Index(columns: ['calendar_id', 'start_at', 'status'], name: 'idx_slot_calendar_start_status')]
#[ORM\HasLifecycleCallbacks]
class Slot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(
        type: 'string',
        length: 5,
        columnDefinition: "VARCHAR(5) CHECK (type IN ('day', 'time'))"
    )]
    private string $type;

    #[ORM\Column]
    private \DateTimeImmutable $startAt;

    #[ORM\Column]
    private \DateTimeImmutable $endAt;

    #[ORM\Column(
        type: 'string',
        length: 10,
        columnDefinition: "VARCHAR(10) DEFAULT 'open' CHECK (status IN ('open', 'closed', 'booked', 'overridden'))"
    )]
    private string $status = 'open';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $continent = null;

    #[ORM\ManyToOne(targetEntity: Calendar::class, inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false)]
    private Calendar $calendar;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getContinent(): ?string
    {
        return $this->continent;
    }

    public function setContinent(?string $continent): static
    {
        $this->continent = $continent;

        return $this;
    }

    public function getCalendar(): Calendar
    {
        return $this->calendar;
    }

    public function setCalendar(Calendar $calendar): static
    {
        $this->calendar = $calendar;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
