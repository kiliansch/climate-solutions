<?php

declare(strict_types=1);

namespace App\CalendarBundle\Entity;

use App\CalendarBundle\Repository\SlotUnavailabilityRepository;
use App\Entity\Slot;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SlotUnavailabilityRepository::class)]
#[ORM\Table(
    name: 'slot_unavailabilities',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_slot_unavailability_slot_date',
            columns: ['slot_id', 'blocked_date'],
        ),
    ],
)]
class SlotUnavailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Slot::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Slot $slot;

    #[ORM\ManyToOne(targetEntity: Unavailability::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Unavailability $unavailability;

    #[ORM\Column]
    private \DateTimeImmutable $blockedDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlot(): Slot
    {
        return $this->slot;
    }

    public function setSlot(Slot $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getUnavailability(): Unavailability
    {
        return $this->unavailability;
    }

    public function setUnavailability(Unavailability $unavailability): static
    {
        $this->unavailability = $unavailability;

        return $this;
    }

    public function getBlockedDate(): \DateTimeImmutable
    {
        return $this->blockedDate;
    }

    public function setBlockedDate(\DateTimeImmutable $blockedDate): static
    {
        $this->blockedDate = $blockedDate;

        return $this;
    }
}
