<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Entity\User;
use App\Repository\BookingRequestRepository;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent')]
#[IsGranted('ROLE_AGENT')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingRequestRepository $bookingRequestRepository,
        private readonly BookingService $bookingService,
    ) {
    }

    #[Route('/bookings', name: 'agent_booking_list', methods: ['GET'])]
    public function list(): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $bookingRequests = $this->bookingRequestRepository->findByAgent($agent);

        return $this->json(array_map(
            static fn(\App\Entity\BookingRequest $br): array => [
                'id' => $br->getId(),
                'customerName' => $br->getCustomerName(),
                'customerEmail' => $br->getCustomerEmail(),
                'message' => $br->getMessage(),
                'status' => $br->getStatus(),
                'slotId' => $br->getSlot()->getId(),
                'createdAt' => $br->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $bookingRequests,
        ));
    }

    #[Route('/bookings/{id}/accept', name: 'agent_booking_accept', methods: ['PATCH'])]
    public function accept(int $id): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $bookingRequest = $this->bookingRequestRepository->find($id);

        if ($bookingRequest === null) {
            return $this->json(['error' => 'Booking request not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->bookingService->acceptRequest($bookingRequest, $agent);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->addFlash('success', 'Booking request accepted.');

        return $this->redirectToRoute('agent_booking_list');
    }

    #[Route('/bookings/{id}/decline', name: 'agent_booking_decline', methods: ['PATCH'])]
    public function decline(int $id): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $bookingRequest = $this->bookingRequestRepository->find($id);

        if ($bookingRequest === null) {
            return $this->json(['error' => 'Booking request not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->bookingService->declineRequest($bookingRequest, $agent);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        $this->addFlash('success', 'Booking request declined.');

        return $this->redirectToRoute('agent_booking_list');
    }
}
