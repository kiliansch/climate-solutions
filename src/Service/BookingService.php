<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\BookingRequestDTO;
use App\Entity\BookingRequest;
use App\Entity\Slot;
use App\Entity\User;
use App\Message\BookingRequestCreatedMessage;
use App\Repository\BookingRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRequestRepository $bookingRequestRepository,
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

        $request = new BookingRequest();
        $request->setSlot($slot);
        $request->setCustomerName($dto->customerName);
        $request->setCustomerEmail($dto->customerEmail);
        $request->setMessage($dto->message);
        $request->setStatus('pending');

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

        $request->setStatus('accepted');
        $request->getSlot()->setStatus('booked');

        $pendingRequests = $this->bookingRequestRepository->findPendingBySlot($request->getSlot());
        foreach ($pendingRequests as $pending) {
            if ($pending->getId() !== $request->getId()) {
                $pending->setStatus('declined');
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
