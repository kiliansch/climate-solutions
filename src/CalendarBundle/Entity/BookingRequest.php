<?php

declare(strict_types=1);

namespace App\CalendarBundle\Entity;

use App\CalendarBundle\Repository\BookingRequestRepository;
use App\Entity\Slot;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRequestRepository::class)]
#[ORM\Table(name: 'booking_requests')]
#[ORM\HasLifecycleCallbacks]
class BookingRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $customerName;

    #[ORM\Column(length: 255)]
    private string $customerEmail;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(
        type: 'string',
        length: 10,
        columnDefinition: "VARCHAR(10) DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'declined'))"
    )]
    private string $status = 'pending';

    #[ORM\ManyToOne(targetEntity: Slot::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Slot $slot;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $selectedDate = null;

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

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

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

    public function getSlot(): Slot
    {
        return $this->slot;
    }

    public function setSlot(Slot $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSelectedDate(): ?\DateTimeImmutable
    {
        return $this->selectedDate;
    }

    public function setSelectedDate(?\DateTimeImmutable $selectedDate): static
    {
        $this->selectedDate = $selectedDate;

        return $this;
    }
}
