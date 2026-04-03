<?php

declare(strict_types=1);

namespace App\CalendarBundle\Service;

use App\CalendarBundle\Dto\BookingRequestDTO;
use App\CalendarBundle\Entity\BookingRequest;
use App\CalendarBundle\Entity\SlotUnavailability;
use App\CalendarBundle\Message\BookingRequestCreatedMessage;
use App\CalendarBundle\Repository\BookingRequestRepository;
use App\CalendarBundle\Repository\SlotUnavailabilityRepository;
use App\Entity\Slot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRequestRepository $bookingRequestRepository,
        private readonly SlotUnavailabilityRepository $slotUnavailabilityRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function createRequest(Slot $slot, BookingRequestDTO $dto): BookingRequest
    {
        if ($slot->getStatus() !== 'open') {
            throw new \DomainException(sprintf(
                'Cannot book slot %d: slot status is "%s", expected "open".',
                $slot->getId(),
                $slot->getStatus(),
            ));
        }

        if ($slot->getType() === 'day' && $dto->selectedDate !== null) {
            $selectedDate = $dto->selectedDate->setTimezone(new \DateTimeZone('UTC'));

            if ($this->slotUnavailabilityRepository->isDateBlockedForSlot($slot, $selectedDate)) {
                throw new \DomainException('This date is not available for booking.');
            }
        }

        $request = new BookingRequest();
        $request->setSlot($slot);
        $request->setCustomerName($dto->customerName);
        $request->setCustomerEmail($dto->customerEmail);
        $request->setMessage($dto->message);
        $request->setStatus('pending');

        if ($slot->getType() === 'day' && $dto->selectedDate !== null) {
            $request->setSelectedDate($dto->selectedDate->setTimezone(new \DateTimeZone('UTC')));
        }

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        $id = $request->getId();
        if ($id === null) {
            throw new \LogicException('BookingRequest ID is null after flush.');
        }

        $this->bus->dispatch(new BookingRequestCreatedMessage(
            bookingRequestId: $id,
        ));

        return $request;
    }

    public function acceptRequest(BookingRequest $request, User $agent): void
    {
        $this->assertAgentOwnsSlot($request->getSlot(), $agent);

        $slot = $request->getSlot();
        $selectedDate = $request->getSelectedDate();

        $request->setStatus('accepted');

        if ($slot->getType() === 'day' && $selectedDate !== null) {
            $slotUnavailability = new SlotUnavailability();
            $slotUnavailability->setSlot($slot);
            $slotUnavailability->setBlockedDate($selectedDate);
            $this->entityManager->persist($slotUnavailability);

            // Flush the new block so the DB query below can count it
            $this->entityManager->flush();

            if ($this->slotUnavailabilityRepository->areAllDaysBlockedForSlot($slot)) {
                $slot->setStatus('booked');
            }

            $sameDayPending = $this->bookingRequestRepository->findPendingBySlotAndDate($slot, $selectedDate);
            foreach ($sameDayPending as $pending) {
                if ($pending->getId() !== $request->getId()) {
                    $pending->setStatus('declined');
                }
            }
        } else {
            $slot->setStatus('booked');

            $pendingRequests = $this->bookingRequestRepository->findPendingBySlot($slot);
            foreach ($pendingRequests as $pending) {
                if ($pending->getId() !== $request->getId()) {
                    $pending->setStatus('declined');
                }
            }
        }

        $this->entityManager->flush();
    }

    public function declineRequest(BookingRequest $request, User $agent): void
    {
        $this->assertAgentOwnsSlot($request->getSlot(), $agent);

        $request->setStatus('declined');

        $this->entityManager->flush();
    }

    private function assertAgentOwnsSlot(Slot $slot, User $agent): void
    {
        if ($slot->getCalendar()->getAgent()->getId() !== $agent->getId()) {
            throw new AccessDeniedException(
                'You do not have permission to manage this booking request.'
            );
        }
    }
}
