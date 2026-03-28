<?php

declare(strict_types=1);

namespace App\CalendarBundle\Entity;

use App\CalendarBundle\Repository\UnavailabilityRepository;
use App\Entity\Calendar;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnavailabilityRepository::class)]
#[ORM\Table(name: 'unavailabilities')]
class Unavailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $startAt;

    #[ORM\Column]
    private \DateTimeImmutable $endAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne(targetEntity: Calendar::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Calendar $calendar;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $client;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

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

    public function getClient(): User
    {
        return $this->client;
    }

    public function setClient(User $client): static
    {
        $this->client = $client;

        return $this;
    }
}
